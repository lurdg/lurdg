<?php
declare(strict_types=1);

function admin_shipments_route(array $user, string $path): void
{
    if ($path === '/admin/shipments/' && !is_post()) {
        shipments_page($user);
        return;
    }
    if ($path === '/admin/shipments/new/') {
        require_permission($user, 'manage-shipments');
        shipment_edit($user, null);
        return;
    }
    if ($path === '/admin/shipments/export/' && !is_post()) {
        require_permission($user, 'export');
        export_shipments($user);
    }
    if (!preg_match('~\A/admin/shipments/([1-9][0-9]{0,17})(?:/([a-z-]+)(?:/([1-9][0-9]{0,17}))?)?/\z~', $path, $parts)) {
        throw new HttpError(404, 'Shipment page not found.');
    }
    $id = (int) $parts[1];
    $action = $parts[2] ?? '';
    $record = isset($parts[3]) ? (int) $parts[3] : null;
    $shipment = shipment_for_staff($user, $id);
    if (is_post() && $action !== 'edit') {
        shipment_action($user, $shipment, $action, $record);
        return;
    }
    match ($action) {
        '' => shipment_details($user, $shipment),
        'edit' => shipment_edit($user, $shipment),
        'print', 'label' => print_shipment($user, $shipment, $action === 'label'),
        'file' => download_attachment($user, $shipment, $record),
        default => throw new HttpError(404, 'Shipment page not found.'),
    };
}

function shipment_action(array $user, array $shipment, string $action, ?int $record): void
{
    $id = (int) $shipment['id'];
    $back = '/admin/shipments/' . $id . '/';
    try {
        switch ($action) {
            case 'event':
                if (array_key_exists('coordinate_lookup', $_POST)) {
                    if ($shipment['archived_at'] !== null || $shipment['deleted_at'] !== null) {
                        throw new ValidationError('Updates can only be added to active shipments.');
                    }
                    [$form, $lookup] = shipment_location_lookup($user, $_POST);
                    shipment_details($user, $shipment, '', $form, $lookup);
                    return;
                }
                save_event($user, $id, $_POST);
                flash('Tracking update recorded.');
                break;
            case 'remove-event':
                remove_event($user, $id, $record ?? 0);
                flash('Tracking event removed.');
                break;
            case 'archive':
            case 'unarchive':
            case 'trash':
            case 'restore':
            case 'purge':
                lifecycle($user, $id, $action);
                flash(match ($action) {
                    'archive' => 'Shipment archived.', 'unarchive' => 'Shipment returned to active records.',
                    'trash' => 'Shipment moved to trash.', 'restore' => 'Shipment restored.',
                    'purge' => 'Shipment permanently deleted.',
                });
                if ($action === 'purge') {
                    redirect('/admin/shipments/?state=trash');
                }
                break;
            case 'upload':
                upload_attachment($user, $id, $_FILES['attachment'] ?? []);
                flash('Document uploaded.');
                break;
            case 'remove-file':
                remove_attachment($user, $id, $record ?? 0);
                flash('Document deleted.');
                break;
            default:
                throw new HttpError(404, 'Action not found.');
        }
        redirect($back);
    } catch (ValidationError $error) {
        http_response_code(422);
        shipment_details($user, shipment_for_staff($user, $id), $error->getMessage(), $action === 'event' ? $_POST : []);
    }
}

function filters_query(array $source, array $more = []): string
{
    $filters = [];
    foreach (['q','state','status','origin','destination','from','to'] as $key) {
        $value = input($source, $key);
        if ($value !== '') {
            $filters[$key] = $value;
        }
    }
    return http_build_query($more + $filters);
}

function page_number(): int
{
    return max(1, min(100000, (int) input($_GET, 'page')));
}

function pagination(string $base, int $page, bool $next, string $query = ''): void
{
    $url = $base . '?' . ($query === '' ? '' : $query . '&');
    ?><nav class="paging" aria-label="Pagination">
    <?php if ($page > 1): ?><a class="text-link" href="<?= e($url . 'page=' . ($page - 1)) ?>">Previous page</a><?php endif; ?>
    <span>Page <?= e($page) ?></span>
    <?php if ($next): ?><a class="text-link" href="<?= e($url . 'page=' . ($page + 1)) ?>">Next page</a><?php endif; ?>
    </nav><?php
}

function shipments_page(array $user): void
{
    try {
        [$where, $params] = shipment_list_filter($user, $_GET);
        $message = '';
    } catch (ValidationError $error) {
        [$where, $params] = shipment_list_filter($user, []);
        $message = $error->getMessage();
    }
    $page = page_number();
    $offset = ($page - 1) * 25;
    $list = query('SELECT s.id,s.tracking_number,s.origin,s.destination,t.label AS status_label,t.bucket,s.created_at AS activity
        FROM shipments s JOIN statuses t ON s.status_id = t.id' . $where
        . " ORDER BY s.created_at DESC,s.id DESC LIMIT 26 OFFSET $offset", $params)->fetchAll();
    admin_layout('Shipments', $user, static function () use ($user, $list, $page): void {
        $state = input($_GET, 'state');
        ?>
        <div class="admin-toolbar">
        <?php if (can($user, 'manage-shipments')): ?><a class="button" href="/admin/shipments/new/">Create shipment</a><?php endif; ?>
        <?php if (can($user, 'export')): ?><a class="button secondary" href="/admin/shipments/export/?<?= e(filters_query($_GET)) ?>">Export filtered CSV</a><?php endif; ?>
        </div>
        <section class="panel">
            <form method="get" action="/admin/shipments/" class="admin-filters">
                <label>Tracking number or reference<input name="q" maxlength="100" value="<?= e(input($_GET, 'q')) ?>"></label>
                <?php if ($user['role'] !== 'agent'): ?>
                    <label>Record state<select name="state"><option value="">Active</option><option value="archive"<?= $state === 'archive' ? ' selected' : '' ?>>Archived</option>
                    <?php if (can($user, 'manage-shipments')): ?><option value="trash"<?= $state === 'trash' ? ' selected' : '' ?>>Trash</option><?php endif; ?></select></label>
                <?php endif; ?>
                <label>Status<select name="status"><option value="">All statuses</option>
                    <?php foreach (status_options() as $status): ?><option value="<?= e($status['id']) ?>"<?= (string) $status['id'] === input($_GET, 'status') ? ' selected' : '' ?>><?= e($status['label']) ?></option><?php endforeach; ?>
                </select></label>
                <?php foreach (['origin' => 'Origin', 'destination' => 'Destination', 'from' => 'Created from', 'to' => 'Created to'] as $key => $label): ?>
                    <label><?= e($label) ?><input name="<?= e($key) ?>" type="<?= in_array($key, ['from','to'], true) ? 'date' : 'text' ?>" value="<?= e(input($_GET, $key)) ?>"></label>
                <?php endforeach; ?>
                <div class="filter-toolbar"><button class="button">Apply filters</button><a class="text-link" href="/admin/shipments/">Clear</a></div>
            </form>
        </section>
        <section class="panel"><h2>Shipment records</h2><?php shipment_table(array_slice($list, 0, 25)); pagination('/admin/shipments/', $page, count($list) > 25, filters_query($_GET)); ?></section>
        <?php
    }, $message, $message !== '');
}

function shipment_table(array $list): void
{
    if ($list === []) {
        ?><p class="muted">No shipments found.</p><?php
        return;
    }
    ?>
    <div class="table-scroll"><table><thead><tr><th scope="col">Tracking number</th><th scope="col">Origin</th>
        <th scope="col">Destination</th><th scope="col">Status</th><th scope="col">Date</th></tr></thead><tbody>
    <?php foreach ($list as $record): ?>
        <tr><td><a class="text-link" href="/admin/shipments/<?= e($record['id']) ?>/"><?= e($record['tracking_number']) ?></a></td>
            <td><?= e($record['origin']) ?></td><td><?= e($record['destination']) ?></td>
            <td><span class="badge <?= e($record['bucket']) ?>"><?= e($record['status_label']) ?></span></td>
            <td><?= e(show_time($record['activity'], 'j M Y')) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php
}

function html_shipment_field(string $key, array $field, mixed $value): void
{
    [$label, $type, $max] = $field;
    $required = in_array($key, ['shipper_name','receiver_name','origin','destination','method'], true);
    if ($type === 'textarea') {
        ?><label class="wide"><?= e($label) ?><textarea name="<?= e($key) ?>" maxlength="<?= e($max) ?>"><?= e($value) ?></textarea></label><?php
        return;
    }
    $number = in_array($type, ['integer','decimal'], true);
    ?>
    <label><?= e($label) ?><input name="<?= e($key) ?>" type="<?= e($number ? 'number' : $type) ?>" value="<?= e($value) ?>"
        <?php if ($number): ?> min="<?= $type === 'integer' ? 1 : 0 ?>" max="<?= e($max) ?>" step="<?= $type === 'integer' ? '1' : '0.001' ?>"<?php elseif ($max > 0): ?> maxlength="<?= e($max) ?>"<?php endif; ?>
        <?= $required ? ' required' : '' ?>></label>
    <?php
}

function html_status_choice(array $statuses, int $chosen, string $name = 'status_id'): void
{
    ?><label>Status<select name="<?= e($name) ?>" required>
    <?php foreach ($statuses as $status): ?>
        <?php if ($status['active'] || (int) $status['id'] === $chosen): ?>
        <option value="<?= e($status['id']) ?>"<?= (int) $status['id'] === $chosen ? ' selected' : '' ?>><?= e($status['label']) ?><?= !$status['active'] ? ' (retired)' : '' ?></option>
        <?php endif; ?>
    <?php endforeach; ?>
    </select></label><?php
}

function shipment_edit(array $user, ?array $shipment): void
{
    require_permission($user, 'manage-shipments');
    $id = isset($shipment['id']) ? (int) $shipment['id'] : null;
    if ($shipment && ($shipment['deleted_at'] !== null || $shipment['archived_at'] !== null)) {
        throw new HttpError(409, 'Restore or unarchive this shipment before editing.');
    }
    $url = $id === null ? '/admin/shipments/new/' : '/admin/shipments/' . $id . '/edit/';
    $message = '';
    $searching = is_post() && array_key_exists('coordinate_lookup', $_POST);
    if (is_post() && !$searching) {
        try {
            $saved = save_shipment($user, $id, $_POST);
            flash($id ? 'Shipment updated.' : 'Shipment created.');
            redirect('/admin/shipments/' . $saved . '/');
        } catch (ValidationError $error) {
            $message = $error->getMessage();
            http_response_code(422);
        }
    }
    $statuses = status_options();
    $initialStatus = row("SELECT id FROM statuses WHERE code = 'created'");
    $agents = assignable_staff();
    if (isset($shipment['agent_id']) && !in_array($shipment['agent_id'], array_column($agents, 'id'), false)
        && ($current = row('SELECT id,name FROM staff WHERE id = ?', [$shipment['agent_id']]))) {
        $current['name'] .= ' (unavailable)';
        $agents[] = $current;
    }
    $customers = query('SELECT id,name FROM customers ORDER BY name,id LIMIT 500')->fetchAll();
    if (isset($shipment['customer_id']) && !in_array($shipment['customer_id'], array_column($customers, 'id'), false)
        && ($linked = row('SELECT id,name FROM customers WHERE id = ?', [$shipment['customer_id']]))) {
        $customers[] = $linked;
    }
    $form = is_post() ? $_POST : ($shipment ?? ['status_id' => $initialStatus['id']]);
    $lookup = [];
    if ($searching) {
        [$form, $lookup] = shipment_location_lookup($user, $form);
    }
    admin_layout($id ? 'Edit shipment' : 'Create shipment', $user, static function () use ($shipment, $url, $statuses, $agents, $customers, $form, $id, $lookup): void {
        $fields = shipment_fields();
        $groups = [
            'Shipment' => ['reference','origin','destination','method','category','packages','weight','dimensions','description'],
            'Sender' => ['shipper_name','shipper_phone','shipper_email','shipper_address'],
            'Receiver' => ['receiver_name','receiver_phone','receiver_email','receiver_address'],
            'Schedule and notes' => ['pickup_at','departure_at','estimated_delivery','internal_notes','public_notes'],
        ];
        $cancel = $id ? '/admin/shipments/' . $id . '/' : '/admin/shipments/';
        ?>
        <?php if ($shipment): ?><p class="muted">Tracking number <?= e($shipment['tracking_number']) ?>. Created <?= e(show_time($shipment['created_at'])) ?>.</p><?php endif; ?>
        <form method="post" action="<?= e($url) ?>" data-geocode>
            <?= csrf_field() ?>
            <?php foreach ($groups as $heading => $names): ?>
                <section class="panel"><h2><?= e($heading) ?></h2><div class="fields-grid">
                <?php foreach ($names as $key): ?>
                    <?php $fieldValue = $form[$key] ?? '';
                    if (!is_post() && $fields[$key][1] === 'datetime-local') {
                        $fieldValue = show_time($fieldValue, 'Y-m-d\TH:i');
                    }
                    html_shipment_field($key, $fields[$key], is_scalar($fieldValue) ? $fieldValue : ''); ?>
                <?php endforeach; ?>
                </div></section>
            <?php endforeach; ?>
            <section class="panel"><h2>Assignment and status</h2><div class="fields-grid">
                <?php html_status_choice($statuses, (int) ($form['status_id'] ?? 0)); ?>
                <label>Delivery agent<select name="agent_id"><option value="">Unassigned</option>
                    <?php foreach ($agents as $agent): ?><option value="<?= e($agent['id']) ?>"<?= (string) $agent['id'] === (string) ($form['agent_id'] ?? '') ? ' selected' : '' ?>><?= e($agent['name']) ?></option><?php endforeach; ?>
                </select></label>
                <label>Customer profile<select name="customer_id"><option value="">No profile linked</option>
                    <?php foreach ($customers as $customer): ?><option value="<?= e($customer['id']) ?>"<?= (string) $customer['id'] === (string) ($form['customer_id'] ?? '') ? ' selected' : '' ?>><?= e($customer['name']) ?></option><?php endforeach; ?>
                </select></label>
            </div></section>
            <section class="panel stack">
                <h2><?= $id === null ? 'Initial location' : 'Tracking location' ?></h2>
                <?php if ($id !== null): ?><p class="muted">Enter a location to add a tracking update. A location is required when changing the status.</p><?php endif; ?>
                <?php html_location_fields($form, false, $lookup); ?>
            </section>
            <div class="admin-toolbar"><button class="button">Save shipment</button><a class="text-link" href="<?= e($cancel) ?>">Cancel</a></div>
        </form>
        <?php
    }, $message, $message !== '');
}

function post_button(string $url, string $label, bool $confirm = false): void
{
    ?><form method="post" action="<?= e($url) ?>"<?= $confirm ? ' data-confirm="Confirm this action?"' : '' ?>>
        <?= csrf_field() ?><button class="button compact <?= $confirm ? 'danger' : 'secondary' ?>"><?= e($label) ?></button></form><?php
}

function shipment_details(array $user, array $shipment, string $message = '', array $eventForm = [], array $lookup = []): void
{
    $id = (int) $shipment['id'];
    $inactive = $shipment['archived_at'] !== null || $shipment['deleted_at'] !== null;
    $canManage = can($user, 'manage-shipments');
    $eventsPage = page_number();
    $offset = ($eventsPage - 1) * 40;
    $events = query("SELECT * FROM shipment_events WHERE shipment_id = ? ORDER BY occurred_at DESC,id DESC LIMIT 41 OFFSET $offset", [$id])->fetchAll();
    $files = $user['role'] !== 'agent'
        ? query('SELECT id,display_name,byte_count FROM attachments WHERE shipment_id = ? ORDER BY id DESC LIMIT 100', [$id])->fetchAll() : [];
    $agent = $shipment['agent_id'] ? row('SELECT name FROM staff WHERE id = ?', [$shipment['agent_id']]) : null;
    admin_layout($shipment['tracking_number'], $user, static function () use ($user, $shipment, $inactive, $canManage, $events, $files, $id, $agent, $eventsPage, $eventForm, $lookup): void {
        $url = "/admin/shipments/$id/";
        ?>
        <div class="admin-toolbar">
            <a class="text-link" href="/admin/shipments/">All shipments</a>
            <?php if ($canManage && !$inactive): ?><a class="button" href="<?= e($url . 'edit/') ?>">Edit shipment</a><?php endif; ?>
            <?php if ($canManage): ?><a class="button secondary" href="<?= e($url . 'print/') ?>">Print details</a><a class="button secondary" href="<?= e($url . 'label/') ?>">Shipping label</a><?php endif; ?>
        </div>
        <section class="panel"><div class="result-title"><div><h2>Shipment details</h2>
            <p class="muted"><?= e($shipment['deleted_at'] !== null ? 'In trash' : ($shipment['archived_at'] !== null ? 'Archived' : 'Active shipment')) ?> · Created <?= e(show_time($shipment['created_at'])) ?></p></div>
            <span class="badge <?= e($shipment['bucket']) ?>"><?= e($shipment['status_label']) ?></span></div>
            <dl class="facts">
                <?php foreach (shipment_fields() as $key => [$label, $type]): ?>
                    <?php if ($user['role'] === 'agent' && in_array($key, ['internal_notes','shipper_email','receiver_email','shipper_address','shipper_phone'], true)) { continue; }
                    $value = $shipment[$key];
                    if ($type === 'datetime-local') { $value = show_time($value); }
                    if ($type === 'date') { $value = date_label($value); }
                    if ($value === null || $value === '') { continue; } ?>
                    <div><dt><?= e($label) ?></dt><dd class="preserve-lines"><?= e($value) ?></dd></div>
                <?php endforeach; ?>
                <div><dt>Delivery agent</dt><dd><?= e($agent['name'] ?? 'Unassigned') ?></dd></div>
            </dl>
        </section>
        <?php if (!$inactive && can($user, 'event')): shipment_event_form($user, $shipment, $url, $eventForm, $lookup); endif; ?>
        <section class="panel"><h2>Tracking history</h2>
            <?php if ($events === []): ?><p class="muted">No events on this page.</p><?php endif; ?>
            <ol class="timeline">
            <?php foreach (array_slice($events, 0, 40) as $event): ?>
                <?php if ($event['deleted_at'] !== null && !can($user, 'correction')) { continue; } ?>
                <li><strong><?= e($event['kind'] === 'status' ? $event['status_label'] : 'Location recorded') ?><?= $event['deleted_at'] ? ' (removed)' : '' ?></strong>
                    <time datetime="<?= e(iso_datetime($event['occurred_at'])) ?>"><?= e(show_time($event['occurred_at'])) ?></time>
                    <?php if ($event['location'] !== ''): ?><p><?= e($event['location']) ?><?= $event['latitude'] !== null ? ' (' . e($event['latitude']) . ', ' . e($event['longitude']) . ')' : '' ?></p><?php endif; ?>
                    <?php if ($event['description'] !== '' && $user['role'] !== 'agent'): ?><p class="preserve-lines"><?= e($event['description']) ?></p><?php endif; ?>
                    <?php if ($event['customer_remarks'] !== ''): ?><p class="preserve-lines">Customer remarks · <?= e($event['customer_remarks']) ?></p><?php endif; ?>
                    <?php if ($event['actor_name'] !== ''): ?><p class="muted">Updated by <?= e($event['actor_name']) ?></p><?php endif; ?>
                    <?php if ($shipment['deleted_at'] === null && $event['deleted_at'] === null && can($user, 'correction')): post_button($url . 'remove-event/' . $event['id'] . '/', 'Remove event', true); endif; ?>
                </li>
            <?php endforeach; ?>
            </ol>
            <?php pagination($url, $eventsPage, count($events) > 40); ?>
        </section>
        <?php if ($user['role'] !== 'agent'): ?>
            <section class="panel"><h2>Private documents</h2>
                <?php if ($files === []): ?><p class="muted">No documents uploaded.</p><?php endif; ?>
                <?php foreach ($files as $file): ?>
                <div class="file-row"><a class="text-link" href="<?= e($url . 'file/' . $file['id'] . '/') ?>"><?= e($file['display_name']) ?></a>
                    <small><?= e((string) ceil($file['byte_count'] / 1024)) ?> KB</small>
                    <?php if ($canManage): post_button($url . 'remove-file/' . $file['id'] . '/', 'Delete document', true); endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if ($canManage && $shipment['deleted_at'] === null): ?>
                    <form action="<?= e($url . 'upload/') ?>" method="post" enctype="multipart/form-data" class="stack attachment-form">
                        <?= csrf_field() ?><label>PDF, PNG or JPEG document up to 8 MB<input type="file" name="attachment" accept=".pdf,.png,.jpg,.jpeg" required></label><button class="button secondary">Upload document</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        <?php if ($canManage): ?>
            <section class="panel"><h2>Manage record</h2>
                <div class="admin-toolbar">
                <?php if ($shipment['deleted_at'] !== null): ?>
                    <?php post_button($url . 'restore/', 'Restore shipment'); ?>
                    <?php if (can($user, 'purge')): post_button($url . 'purge/', 'Permanently delete shipment', true); endif; ?>
                <?php else: ?>
                    <?php post_button($url . ($shipment['archived_at'] !== null ? 'unarchive/' : 'archive/'), $shipment['archived_at'] !== null ? 'Unarchive' : 'Archive'); ?>
                    <?php post_button($url . 'trash/', 'Move to trash', true); ?>
                <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
        <?php
    }, $message, $message !== '');
}

function shipment_location_lookup(array $user, array $form): array
{
    $place = input($form, 'location');
    $choice = input($form, 'coordinate_lookup');
    try {
        if ($place === '') {
            throw new ValidationError('Enter a place name first.');
        }
        if ($choice !== 'find' && (!ctype_digit($choice) || $place !== input($form, 'lookup_place'))) {
            throw new ValidationError('Search for the location again to choose a match.');
        }
        $matches = geocode_lookup($user, $form)['matches'];
        if ($matches === []) {
            return [$form, ['message' => 'No matches found. Try adding a city or country, or enter coordinates manually.']];
        }
        if ($choice === 'find' && count($matches) > 1) {
            return [$form, [
                'message' => 'Choose a matching location to fill in the coordinates.',
                'place' => $place,
                'matches' => $matches,
            ]];
        }
        $match = $matches[$choice === 'find' ? 0 : (int) $choice] ?? null;
        if ($match === null) {
            throw new ValidationError('Search for the location again to choose a match.');
        }
        return [array_replace($form, [
            'location' => $match['name'],
            'latitude' => $match['latitude'],
            'longitude' => $match['longitude'],
        ]), ['message' => 'Coordinates filled in. Review the location before recording.', 'filled' => true]];
    } catch (ValidationError | HttpError $error) {
        http_response_code($error instanceof HttpError ? $error->status : 422);
        return [$form, ['message' => $error->getMessage()]];
    }
}

function html_location_fields(array $form, bool $required, array $lookup): void
{
    ?>
    <label>Location name<input name="location" maxlength="200" value="<?= e(input($form, 'location')) ?>"<?= $required ? ' required' : '' ?>></label>
    <div><button type="submit" class="button secondary" name="coordinate_lookup" value="find" formnovalidate data-find-location>Find coordinates</button></div>
    <div class="geocode-results" aria-live="polite"<?= $lookup !== [] ? ' tabindex="-1" autofocus' : '' ?><?= !empty($lookup['filled']) ? ' data-coordinates-filled' : '' ?>>
        <?= e($lookup['message'] ?? '') ?>
        <?php if (isset($lookup['matches'])): ?>
            <input type="hidden" name="lookup_place" value="<?= e($lookup['place']) ?>">
            <?php foreach ($lookup['matches'] as $index => $match): ?>
                <button type="submit" class="geocode-choice" name="coordinate_lookup" value="<?= e($index) ?>" formnovalidate><?= e($match['name']) ?></button>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php if (parse_url(config()['geocoder_url'] ?? '', PHP_URL_HOST) === 'nominatim.openstreetmap.org'): ?>
        <small>Location search data © <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a></small>
    <?php endif; ?>
    <div class="fields-grid">
        <label>Latitude<input name="latitude" type="number" step="any" min="-90" max="90" value="<?= e(input($form, 'latitude')) ?>"></label>
        <label>Longitude<input name="longitude" type="number" step="any" min="-180" max="180" value="<?= e(input($form, 'longitude')) ?>"></label>
    </div>
    <?php
}

function shipment_event_form(array $user, array $shipment, string $url, array $form, array $lookup): void
{
    $statuses = status_options();
    if ($user['role'] === 'agent') {
        $statuses = array_filter($statuses, static fn (array $status): bool => in_array($status['code'], ['out-for-delivery','delivered','attempted','exception'], true));
    }
    ?>
    <section class="panel">
        <h2 id="record-status-heading">Record status</h2>
        <form class="stack" method="post" action="<?= e($url . 'event/') ?>" aria-labelledby="record-status-heading" data-geocode>
            <?= csrf_field() ?>
            <div class="fields-grid">
                <?php html_status_choice($statuses, (int) (input($form, 'status_id') ?: $shipment['status_id'])); ?>
                <label>Event time (<?= e(site_zone()->getName()) ?>)<input type="datetime-local" name="occurred_at" value="<?= e(input($form, 'occurred_at')) ?>"></label>
            </div>
            <?php html_location_fields($form, true, $lookup); ?>
            <div class="fields-grid">
                <label>Internal description<textarea name="description" maxlength="2500"><?= e(input($form, 'description')) ?></textarea></label>
                <label>Customer-visible remarks<textarea name="customer_remarks" maxlength="2500"><?= e(input($form, 'customer_remarks')) ?></textarea></label>
            </div>
            <div><button class="button">Save tracking update</button></div>
        </form>
    </section>
    <?php
}

function csv_cell(mixed $value): string
{
    $cell = (string) $value;
    return preg_match('/^(?:[\x00-\x20]*[=+\-@]|[\t\r\n])/u', $cell) ? "'" . $cell : $cell;
}

function export_shipments(array $user): never
{
    [$filter, $params] = shipment_list_filter($user, $_GET);
    $columns = ['tracking_number','reference','origin','destination','status','shipper_name','shipper_phone','shipper_email',
        'shipper_address','receiver_name','receiver_phone','receiver_email','receiver_address','description','category',
        'packages','weight','dimensions','method','pickup_at','departure_at','estimated_delivery','created_at','agent',
        'internal_notes','public_notes','archived_at','deleted_at'];
    record_audit($user, 'exported CSV', 'shipment', null);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="afrilink-shipments-' . gmdate('Y-m-d') . '.csv"');
    ob_end_clean();
    $stream = fopen('php://output', 'wb');
    fputcsv($stream, $columns, ',', '"', '');
    $select = implode(',', array_map(static fn (string $col): string => match ($col) {
        'status' => 't.label AS status', 'agent' => 'a.name AS agent', default => 's.' . $col,
    }, $columns));
    db()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    $records = query("SELECT $select FROM shipments s JOIN statuses t ON s.status_id = t.id
        LEFT JOIN staff a ON a.id = s.agent_id $filter ORDER BY s.created_at DESC,s.id DESC", $params);
    while ($record = $records->fetch()) {
        fputcsv($stream, array_map('csv_cell', array_values($record)), ',', '"', '');
    }
    $records->closeCursor();
    fclose($stream);
    exit;
}

function print_shipment(array $user, array $shipment, bool $label): void
{
    require_permission($user, 'manage-shipments');
    admin_layout($label ? 'Shipping label' : 'Shipment printout', $user, static function () use ($shipment, $label): void {
        ?>
        <div class="admin-toolbar no-print"><button class="button" type="button" data-print>Print</button>
            <a class="text-link" href="/admin/shipments/<?= e($shipment['id']) ?>/">Back to shipment</a></div>
        <section class="panel print-card<?= $label ? ' shipping-label' : '' ?>">
            <?= brand() ?><h2><?= e($shipment['tracking_number']) ?></h2>
            <div class="print-parties">
            <?php foreach (['shipper' => 'From', 'receiver' => 'Deliver to'] as $field => $heading): ?>
                <div><h3><?= e($heading) ?></h3><p class="preserve-lines"><b><?= e($shipment[$field . '_name']) ?></b><br>
                    <?= e($shipment[$field . '_address']) ?><br><?= e($shipment[$field . '_phone']) ?></p></div>
            <?php endforeach; ?>
            </div>
            <p><b>Route</b> <?= e($shipment['origin']) ?> → <?= e($shipment['destination']) ?></p>
            <?php foreach (['method' => 'Shipping method','packages' => 'Packages','weight' => 'Weight (kg)','dimensions' => 'Dimensions','reference' => 'Reference'] as $column => $heading): ?>
                <?php if ($shipment[$column] !== null && $shipment[$column] !== ''): ?><p><b><?= e($heading) ?></b> <?= e($shipment[$column]) ?></p><?php endif; ?>
            <?php endforeach; ?>
            <?php if (!$label): ?>
                <p><b>Current status</b> <?= e($shipment['status_label']) ?></p>
                <?php if ($shipment['estimated_delivery'] !== null): ?><p><b>Estimated delivery</b> <?= e(date_label($shipment['estimated_delivery'])) ?></p><?php endif; ?>
                <p class="preserve-lines"><?= e($shipment['description']) ?></p>
            <?php endif; ?>
        </section>
        <?php
    });
}

function download_attachment(array $user, array $shipment, ?int $file): never
{
    if ($user['role'] === 'agent') {
        throw new HttpError(403, 'Your role does not have document access.');
    }
    $record = row('SELECT * FROM attachments WHERE id = ? AND shipment_id = ?', [$file, $shipment['id']]);
    if ($record === null) {
        throw new HttpError(404, 'Document not found.');
    }
    $name = $record['stored_name'];
    $path = attachment_root() . '/' . $name;
    if (!preg_match('/\A[a-f0-9]{32}\z/', $name) || !is_file($path) || !is_readable($path)) {
        throw new HttpError(404, 'Document not found.');
    }
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($record['display_name']));
    header('Content-Length: ' . filesize($path));
    session_write_close();
    ob_end_clean();
    readfile($path);
    exit;
}

function remove_attachment(array $user, int $id, int $file): void
{
    require_permission($user, 'manage-shipments');
    $name = transaction(static function () use ($user, $id, $file): string {
        shipment_for_staff($user, $id, true);
        $record = row('SELECT stored_name FROM attachments WHERE id = ? AND shipment_id = ? FOR UPDATE', [$file, $id]);
        if ($record === null) {
            throw new HttpError(404, 'Document not found.');
        }
        query('DELETE FROM attachments WHERE id = ? AND shipment_id = ?', [$file, $id]);
        record_audit($user, 'removed attachment', 'shipment', $id);
        return $record['stored_name'];
    });
    remove_stored_file($name);
}
