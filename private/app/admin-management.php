<?php
declare(strict_types=1);

function admin_management_route(array $user, string $path): void
{
    if (!preg_match('~\A/admin/(inbox|customers|statuses|staff|settings|audit)/(?:([1-9][0-9]{0,17})/)?\z~', $path, $parts)) {
        throw new HttpError(404, 'Staff page not found.');
    }
    $area = $parts[1];
    $id = isset($parts[2]) ? (int) $parts[2] : null;
    require_permission($user, $area === 'statuses' ? 'catalogue' : $area);
    if ($id !== null && in_array($area, ['settings','audit'], true)) {
        throw new HttpError(404, 'Staff page not found.');
    }
    $error = '';
    if (is_post()) {
        try {
            $result = match ($area) {
                'inbox' => save_inbox($user, $id),
                'customers' => save_customer($user, $id),
                'statuses' => save_status($user, $id),
                'staff' => save_staff($user, $id),
                'settings' => save_settings($user),
                default => throw new HttpError(405, 'This page is read-only.'),
            };
            flash($result);
            redirect("/admin/$area/");
        } catch (ValidationError $validation) {
            $error = $validation->getMessage();
            http_response_code(422);
        } catch (PDOException $failure) {
            if ($failure->getCode() !== '23000') {
                throw $failure;
            }
            $error = 'A record with the same unique value already exists or is in use.';
            http_response_code(409);
        }
    }
    match ($area) {
        'inbox' => inbox_page($user, $id, $error),
        'customers' => customers_page($user, $id, $error),
        'statuses' => statuses_page($user, $id, $error),
        'staff' => staff_page($user, $id, $error),
        'settings' => settings_page($user, $error),
        'audit' => audit_page($user),
    };
}

function require_record(string $sql, ?int $id): array
{
    $record = $id ? row($sql, [$id]) : null;
    if ($record === null) {
        throw new HttpError(404, 'This record could not be found.');
    }
    return $record;
}

function inbox_page(array $user, ?int $id, string $error): void
{
    $stages = ['new' => 'New', 'in-progress' => 'In progress', 'closed' => 'Closed'];
    if ($id !== null) {
        $item = require_record('SELECT * FROM enquiries WHERE id = ?', $id);
        admin_layout($item['kind'] === 'quote' ? 'Quote request' : 'Contact enquiry', $user, static function () use ($item, $stages, $id): void {
            ?>
            <a class="text-link" href="/admin/inbox/">Back to inbox</a>
            <section class="panel"><h2><?= e($item['subject'] ?: 'Contact enquiry') ?></h2>
                <p><?= e($item['name']) ?> · <a class="text-link" href="mailto:<?= e($item['email']) ?>"><?= e($item['email']) ?></a></p>
                <p class="muted"><?= e(show_time($item['created_at'])) ?></p>
                <?php if ($item['kind'] === 'quote'): ?><p><?= e($item['origin']) ?> → <?= e($item['destination']) ?> · <?= e($item['method']) ?></p><?php endif; ?>
                <p class="preserve-lines"><?= e($item['message']) ?></p>
            </section>
            <section class="panel"><h2>Follow-up</h2><form class="stack" method="post" action="/admin/inbox/<?= e($id) ?>/">
                <?= csrf_field() ?>
                <label>Stage<select name="stage">
                <?php foreach ($stages as $value => $label): ?><option value="<?= e($value) ?>"<?= $value === (is_post() ? input($_POST,'stage') : $item['stage']) ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select></label>
                <label>Staff notes<textarea name="staff_notes" maxlength="4000"><?= e(is_post() ? input($_POST,'staff_notes') : $item['staff_notes']) ?></textarea></label>
                <button class="button" name="action" value="save">Save follow-up</button>
            </form></section>
            <?php post_button('/admin/inbox/' . $id . '/?action=delete', 'Delete enquiry', true); ?>
            <?php
        }, $error, $error !== '');
        return;
    }
    $kind = input($_GET, 'kind');
    $page = page_number();
    $offset = 25 * ($page - 1);
    $filtered = in_array($kind, ['quote','contact'], true);
    $list = query('SELECT id,kind,name,subject,stage,created_at FROM enquiries'
        . ($filtered ? ' WHERE kind = ?' : '') . " ORDER BY created_at DESC,id DESC LIMIT 26 OFFSET $offset", $filtered ? [$kind] : [])->fetchAll();
    admin_layout('Inbox', $user, static function () use ($list, $stages, $page, $kind): void {
        ?>
        <div class="admin-toolbar"><a class="text-link" href="/admin/inbox/">All enquiries</a>
            <a class="text-link" href="/admin/inbox/?kind=quote">Quotes</a><a class="text-link" href="/admin/inbox/?kind=contact">Contact messages</a></div>
        <section class="panel">
            <?php if ($list === []): ?><p class="muted">No enquiries found.</p><?php endif; ?>
            <div class="table-scroll"><table><thead><tr><th>Enquiry</th><th>Sender</th><th>Stage</th><th>Received</th></tr></thead><tbody>
            <?php foreach (array_slice($list, 0, 25) as $item): ?><tr>
                <td><a class="text-link" href="/admin/inbox/<?= e($item['id']) ?>/"><?= e($item['subject'] ?: 'Contact enquiry') ?></a></td>
                <td><?= e($item['name']) ?></td><td><?= e($stages[$item['stage']]) ?></td><td><?= e(show_time($item['created_at'])) ?></td>
            </tr><?php endforeach; ?>
            </tbody></table></div>
            <?php pagination('/admin/inbox/', $page, count($list) > 25, $kind !== '' ? http_build_query(['kind' => $kind]) : ''); ?>
        </section>
        <?php
    });
}

function save_inbox(array $user, ?int $id): string
{
    $delete = input($_GET, 'action') === 'delete';
    if ($id === null) {
        throw new HttpError(404, 'Enquiry not found.');
    }
    transaction(static function () use ($user, $id, $delete): void {
        require_record('SELECT id FROM enquiries WHERE id = ? FOR UPDATE', $id);
        if ($delete) {
            query('DELETE FROM enquiries WHERE id = ?', [$id]);
        } else {
            $stage = input($_POST, 'stage');
            if (!in_array($stage, ['new','in-progress','closed'], true)) {
                throw new ValidationError('Choose an enquiry stage.');
            }
            query('UPDATE enquiries SET stage = ?, staff_notes = ? WHERE id = ?', [$stage, value($_POST,'staff_notes',4000), $id]);
        }
        record_audit($user, $delete ? 'deleted enquiry' : 'updated enquiry', 'enquiry', $id);
    });
    return $delete ? 'Enquiry deleted.' : 'Enquiry updated.';
}

function customer_fields(): array
{
    return ['name' => ['Customer name','text',160], 'email' => ['Email','email',254],
        'phone' => ['Phone','tel',40], 'address' => ['Address','textarea',500], 'notes' => ['Staff notes','textarea',4000]];
}

function management_fields(array $fields, array $record): void
{
    foreach ($fields as $key => [$title, $type, $max]) {
        $value = is_post() ? input($_POST, $key) : ($record[$key] ?? '');
        if ($type === 'textarea') {
            ?><label class="wide"><?= e($title) ?><textarea name="<?= e($key) ?>" maxlength="<?= e($max) ?>"><?= e($value) ?></textarea></label><?php
        } else {
            ?><label><?= e($title) ?><input type="<?= e($type) ?>" name="<?= e($key) ?>" value="<?= e($value) ?>" maxlength="<?= e($max) ?>"></label><?php
        }
    }
}

function customers_page(array $user, ?int $id, string $error): void
{
    $record = $id !== null ? require_record('SELECT * FROM customers WHERE id = ?', $id) : [];
    $page = page_number();
    $offset = 25 * ($page - 1);
    $customers = $id === null ? query("SELECT id,name,email FROM customers ORDER BY name,id LIMIT 26 OFFSET $offset")->fetchAll() : [];
    admin_layout($id === null ? 'Customers' : 'Edit customer', $user, static function () use ($record, $id, $customers, $page): void {
        $url = $id === null ? '/admin/customers/' : "/admin/customers/$id/";
        ?>
        <?php if ($id === null): ?>
        <section class="panel"><h2>Customer profiles</h2>
            <?php if ($customers === []): ?><p class="muted">No customer profiles added.</p><?php endif; ?>
            <div class="table-scroll"><table><thead><tr><th>Customer</th><th>Email</th></tr></thead><tbody>
                <?php foreach (array_slice($customers, 0, 25) as $customer): ?>
                <tr><td><a class="text-link" href="/admin/customers/<?= e($customer['id']) ?>/"><?= e($customer['name']) ?></a></td><td><?= e($customer['email']) ?></td></tr>
                <?php endforeach; ?>
            </tbody></table></div><?php pagination('/admin/customers/', $page, count($customers) > 25); ?>
        </section>
        <?php endif; ?>
        <section class="panel"><h2><?= $id === null ? 'Add profile' : 'Profile details' ?></h2>
            <form class="stack" method="post" action="<?= e($url) ?>"><?= csrf_field() ?>
                <div class="fields-grid"><?php management_fields(customer_fields(), $record); ?></div>
                <button class="button">Save profile</button>
            </form>
        </section>
        <?php if ($id !== null): ?><div class="admin-toolbar"><a class="text-link" href="/admin/customers/">All profiles</a><?php post_button($url . '?action=delete', 'Delete profile', true); ?></div><?php endif; ?>
        <?php
    }, $error, $error !== '');
}

function save_customer(array $user, ?int $id): string
{
    $delete = input($_GET, 'action') === 'delete';
    transaction(static function () use ($user, $id, $delete): void {
        if ($id !== null) {
            require_record('SELECT id FROM customers WHERE id = ? FOR UPDATE', $id);
        }
        if ($delete && $id !== null) {
            query('DELETE FROM customers WHERE id = ?', [$id]);
            record_audit($user, 'deleted customer profile', 'customer', $id);
            return;
        }
        $fields = [value($_POST,'name',160,true), email_value($_POST,'email'), value($_POST,'phone',40), value($_POST,'address',500), value($_POST,'notes',4000)];
        if ($id === null) {
            query('INSERT INTO customers (name,email,phone,address,notes,created_at) VALUES (?,?,?,?,?,?)', [...$fields, now_utc()]);
            $id = (int) db()->lastInsertId();
        } else {
            query('UPDATE customers SET name = ?, email = ?, phone = ?, address = ?, notes = ? WHERE id = ?', [...$fields, $id]);
        }
        record_audit($user, 'saved customer profile', 'customer', $id);
    });
    return $delete && $id !== null ? 'Customer profile deleted.' : 'Customer profile saved.';
}

function status_buckets(): array
{
    return ['pending' => 'Pending','awaiting' => 'Awaiting dispatch','transit' => 'In transit',
        'delivered' => 'Delivered','exception' => 'Exception','cancelled' => 'Cancelled'];
}

function statuses_page(array $user, ?int $id, string $error): void
{
    $record = $id !== null ? require_record('SELECT * FROM statuses WHERE id = ?', $id) : [];
    $statuses = $id === null ? status_options() : [];
    admin_layout($id === null ? 'Shipment statuses' : 'Edit status', $user, static function () use ($id, $record, $statuses): void {
        $url = $id === null ? '/admin/statuses/' : "/admin/statuses/$id/";
        ?>
        <?php if ($id === null): ?><section class="panel"><h2>Status catalogue</h2>
            <div class="table-scroll"><table><thead><tr><th>Status</th><th>Dashboard group</th><th>State</th></tr></thead><tbody>
            <?php foreach ($statuses as $status): ?><tr><td><a class="text-link" href="/admin/statuses/<?= e($status['id']) ?>/"><?= e($status['label']) ?></a></td>
                <td><?= e(status_buckets()[$status['bucket']]) ?></td><td><?= $status['active'] ? 'Active' : 'Retired' ?></td></tr><?php endforeach; ?>
            </tbody></table></div></section><?php endif; ?>
        <section class="panel"><h2><?= $id === null ? 'Add status' : 'Status details' ?></h2>
            <form class="stack" method="post" action="<?= e($url) ?>"><?= csrf_field() ?>
                <?php if ($id === null): ?>
                    <label>Status labels
                        <textarea name="labels" rows="5" maxlength="5100" required><?= e(input($_POST, 'labels') ?: input($_POST, 'label')) ?></textarea>
                    </label>
                    <p class="muted">Enter one status per line, up to 50. The dashboard group, display order and availability apply to the batch.</p>
                <?php endif; ?>
                <div class="fields-grid">
                    <?php if ($id !== null): management_fields(['label' => ['Status label','text',100]], $record); endif; ?>
                    <label>Display order<input type="number" name="sort_order" min="0" max="65535"
                        value="<?= e(is_post() ? input($_POST, 'sort_order') : ($record['sort_order'] ?? 100)) ?>"></label>
                    <label>Dashboard group<select name="bucket"><?php foreach (status_buckets() as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $key === (is_post() ? input($_POST,'bucket') : ($record['bucket'] ?? 'pending')) ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?></select></label>
                </div>
                <label><input type="checkbox" name="active" value="1"<?= (is_post() ? input($_POST,'active') === '1' : ($record['active'] ?? true)) ? ' checked' : '' ?>>Available for new updates</label>
                <div><button class="button"><?= $id === null ? 'Add statuses' : 'Save status' ?></button></div>
            </form>
        </section>
        <?php if ($id !== null): ?><div class="admin-toolbar"><a class="text-link" href="/admin/statuses/">All statuses</a>
            <?php if ($record['code'] !== 'created'): post_button($url . '?action=delete', 'Delete or retire status', true); endif; ?></div><?php endif;
    }, $error, $error !== '');
}

function save_status(array $user, ?int $id): string
{
    $delete = input($_GET, 'action') === 'delete' && $id !== null;
    return transaction(static function () use ($user, $id, $delete): string {
        $old = $id === null ? null : require_record('SELECT * FROM statuses WHERE id = ? FOR UPDATE', $id);
        if ($delete) {
            if ($old['code'] === 'created') {
                throw new ValidationError('The initial shipment status must remain active.');
            }
            $inUse = row('SELECT id FROM shipment_events WHERE status_id = ? LIMIT 1', [$id])
                || row('SELECT id FROM shipments WHERE status_id = ? LIMIT 1', [$id]);
            if ($inUse) {
                query('UPDATE statuses SET active = 0 WHERE id = ?', [$id]);
            } else {
                query('DELETE FROM statuses WHERE id = ?', [$id]);
            }
            record_audit($user, $inUse ? 'retired status' : 'deleted status', 'status', $id, $old['label']);
            return $inUse ? 'Status retired. Existing shipment history still uses it.' : 'Status deleted.';
        }
        $bucket = input($_POST,'bucket');
        $active = input($_POST,'active') === '1' || ($old['code'] ?? '') === 'created';
        if (!isset(status_buckets()[$bucket])) {
            throw new ValidationError('Choose a dashboard group.');
        }
        $order = int_input(input($_POST,'sort_order'), 0, 65535) ?? 100;
        if ($old === null) {
            $lines = array_key_exists('labels', $_POST)
                ? preg_split('/\R/u', value($_POST, 'labels', 5100, true))
                : [value($_POST, 'label', 100, true)];
            $labels = array_filter(array_map('trim', $lines), static fn (string $label): bool => $label !== '');
            if ($labels === [] || count($labels) > 50) {
                throw new ValidationError('Enter between 1 and 50 status labels, one per line.');
            }
            $usedLabels = [];
            foreach (query('SELECT label FROM statuses ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN) as $existing) {
                $usedLabels[mb_convert_case($existing, MB_CASE_FOLD, 'UTF-8')] = true;
            }
            foreach ($labels as $label) {
                $label = value(['label' => $label], 'label', 100, true);
                $key = mb_convert_case($label, MB_CASE_FOLD, 'UTF-8');
                if (isset($usedLabels[$key])) {
                    throw new ValidationError('The status label “' . $label . '” already exists or is repeated in this batch.');
                }
                $usedLabels[$key] = true;
                query('INSERT INTO statuses (code,label,bucket,active,sort_order) VALUES (?,?,?,?,?)',
                    ['custom-' . bin2hex(random_bytes(8)), $label, $bucket, (int) $active, $order]);
                record_audit($user, 'saved status', 'status', (int) db()->lastInsertId(), $label);
            }
            return count($labels) === 1 ? 'Status saved.' : count($labels) . ' statuses added.';
        }
        $label = value($_POST, 'label', 100, true);
        query('UPDATE statuses SET label = ?, bucket = ?, active = ?, sort_order = ? WHERE id = ?',
            [$label, $bucket, (int) $active, $order, $id]);
        record_audit($user, 'saved status', 'status', $id, $label);
        return 'Status saved.';
    });
}

function manageable_roles(array $user): array
{
    return $user['role'] === 'owner' ? roles() : array_intersect_key(roles(), array_flip(['manager','operations','agent']));
}

function staff_page(array $user, ?int $id, string $error): void
{
    $record = $id !== null ? require_record('SELECT id,name,email,role,enabled FROM staff WHERE id = ?', $id) : [];
    $roles = manageable_roles($user);
    if ($record !== [] && !isset($roles[$record['role']])) {
        throw new HttpError(403, 'This staff account is managed by the super administrator.');
    }
    $page = page_number();
    $offset = ($page - 1) * 25;
    $accounts = $id === null ? query("SELECT id,name,email,role,enabled,last_login_at FROM staff ORDER BY name,id LIMIT 26 OFFSET $offset")->fetchAll() : [];
    admin_layout($id === null ? 'Staff accounts' : 'Edit staff account', $user, static function () use ($user, $id, $record, $roles, $accounts, $page): void {
        $url = $id === null ? '/admin/staff/' : "/admin/staff/$id/";
        ?>
        <?php if ($id === null): ?><section class="panel"><h2>Staff directory</h2><div class="table-scroll">
            <table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>State</th><th>Last sign-in</th></tr></thead><tbody>
            <?php foreach (array_slice($accounts,0,25) as $account): ?><tr>
                <td><?php if (isset($roles[$account['role']])): ?><a class="text-link" href="/admin/staff/<?= e($account['id']) ?>/"><?= e($account['name']) ?></a><?php else: echo e($account['name']); endif; ?></td>
                <td><?= e($account['email']) ?></td><td><?= e(roles()[$account['role']]) ?></td>
                <td><?= $account['enabled'] ? 'Active' : 'Disabled' ?></td><td><?= e(show_time($account['last_login_at'])) ?></td>
            </tr><?php endforeach; ?>
            </tbody></table></div><?php pagination('/admin/staff/', $page, count($accounts) > 25); ?></section><?php endif; ?>
        <section class="panel"><h2><?= $id === null ? 'Add staff member' : 'Account details' ?></h2>
            <form method="post" action="<?= e($url) ?>" class="stack"><?= csrf_field() ?>
                <div class="fields-grid">
                <?php management_fields(['name' => ['Name','text',160], 'email' => ['Email','email',254]], $record); ?>
                <label>Role<select name="role"><?php foreach ($roles as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= $key === (is_post() ? input($_POST,'role') : ($record['role'] ?? 'agent')) ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?></select></label>
                <label><?= $id ? 'New password (optional)' : 'Initial password' ?><input type="password" name="password" maxlength="72" minlength="12" autocomplete="new-password"<?= $id ? '' : ' required' ?>></label>
                </div>
                <label><input type="checkbox" name="enabled" value="1"<?= (is_post() ? input($_POST,'enabled') === '1' : ($record['enabled'] ?? true)) ? ' checked' : '' ?>>Sign-in enabled</label>
                <button class="button">Save account</button>
            </form>
        </section>
        <?php if ($id !== null): ?><div class="admin-toolbar"><a class="text-link" href="/admin/staff/">All staff</a>
            <?php if ($id !== (int) $user['id']): post_button($url . '?action=delete', 'Delete staff account', true); endif; ?></div><?php endif;
    }, $error, $error !== '');
}

function save_staff(array $user, ?int $id): string
{
    $delete = input($_GET,'action') === 'delete' && $id !== null;
    $roles = manageable_roles($user);
    $password = raw_password('password');
    $hash = !$delete && ($id === null || $password !== '') ? password_digest($password) : null;
    return transaction(static function () use ($user, $id, $delete, $roles, $hash): string {
        $ownerCount = count(query("SELECT id FROM staff WHERE role = 'owner' AND enabled = 1 FOR UPDATE")->fetchAll());
        $target = $id ? require_record('SELECT id,role,enabled FROM staff WHERE id = ? FOR UPDATE', $id) : null;
        if ($target && !isset($roles[$target['role']])) {
            throw new HttpError(403, 'This staff account is managed by the super administrator.');
        }
        if ($delete) {
            if ($id === (int) $user['id'] || ($target['role'] === 'owner' && $target['enabled'] && $ownerCount < 2)) {
                throw new ValidationError('Your own account and the last enabled super administrator must be kept.');
            }
            query('DELETE FROM staff WHERE id = ?', [$id]);
            record_audit($user, 'deleted staff member', 'staff', $id, $target['role']);
            return 'Staff account deleted.';
        }
        $name = value($_POST,'name',160,true);
        $email = strtolower(email_value($_POST,'email',true));
        $role = input($_POST,'role');
        $enabled = input($_POST,'enabled') === '1';
        if (!isset($roles[$role])) {
            throw new ValidationError('Choose a role you can manage.');
        }
        if ($id === (int) $user['id'] && (!$enabled || $role !== $user['role'])) {
            throw new ValidationError('Your own account must keep its role and sign-in access.');
        }
        if ($target && $target['role'] === 'owner' && $target['enabled'] && ($role !== 'owner' || !$enabled) && $ownerCount < 2) {
            throw new ValidationError('Keep at least one enabled super administrator.');
        }
        if ($target === null) {
            query('INSERT INTO staff (name,email,role,enabled,password_hash,created_at) VALUES (?,?,?,?,?,?)',
                [$name,$email,$role,(int) $enabled,$hash,now_utc()]);
            $id = (int) db()->lastInsertId();
        } else {
            query('UPDATE staff SET name = ?, email = ?, role = ?, enabled = ?, password_hash = COALESCE(?,password_hash),
                session_version = session_version + 1 WHERE id = ?', [$name,$email,$role,(int) $enabled,$hash,$id]);
            query('DELETE FROM password_resets WHERE staff_id = ?', [$id]);
            if ($role !== 'agent' && $target['role'] === 'agent') {
                query('UPDATE shipments SET agent_id = NULL WHERE agent_id = ?', [$id]);
            }
            if ($id === (int) $user['id']) {
                $_SESSION['session_version']++;
            }
        }
        record_audit($user, 'saved staff role and permissions', 'staff', $id, $role . ($enabled ? ' enabled' : ' disabled'));
        return 'Staff account saved.';
    });
}

function configurable_fields(): array
{
    return [
        'Company profile' => [
            'company' => ['Company name','text',120],
            'phone' => ['Public phone','tel',40],
            'email' => ['Public email and enquiry inbox','email',254],
            'office' => ['Office address','text',240],
            'hours' => ['Business hours','textarea',500],
            'company_history' => ['Company history','textarea',4000],
            'company_vision' => ['Company vision','textarea',2000],
        ],
        'WhatsApp and social profiles' => [
            'whatsapp' => ['WhatsApp international digits','text',15],
            'whatsapp_greeting' => ['Prefilled WhatsApp greeting','textarea',400],
            'instagram' => ['Instagram profile URL','url',250],
            'facebook' => ['Facebook profile URL','url',250],
            'linkedin' => ['LinkedIn profile URL','url',250],
        ],
        'Shipment and map settings' => [
            'tracking_prefix' => ['Tracking number prefix (2 or 3 characters)','text',3],
            'timezone' => ['Display time zone (IANA name)','text',80],
            'office_latitude' => ['Office map latitude','text',25],
            'office_longitude' => ['Office map longitude','text',25],
        ],
        'Public policies' => [
            'privacy_policy' => ['Privacy policy','textarea',12000],
            'terms' => ['Terms and conditions','textarea',12000],
            'cookie_policy' => ['Cookie policy','textarea',12000],
        ],
    ];
}

function settings_page(array $user, string $error): void
{
    admin_layout('Website settings', $user, static function (): void {
        $values = settings();
        $values['tracking_prefix'] = substr($values['tracking_prefix'], 0, 3);
        ?>
        <form method="post" action="/admin/settings/"><?= csrf_field() ?>
            <?php foreach (configurable_fields() as $heading => $fields): ?>
                <section class="panel"><h2><?= e($heading) ?></h2><div class="fields-grid"><?php management_fields($fields, $values); ?></div></section>
            <?php endforeach; ?>
            <section class="panel"><h2>Email notifications</h2>
                <p><?= smtp_ready() ? 'SMTP settings are present. Confirm delivery with a test message and cron.' : 'SMTP is disabled or incomplete. Configure it in the private configuration file.' ?></p>
                <label><input type="checkbox" name="notifications" value="1"<?= (is_post() ? input($_POST,'notifications') === '1' : setting('notifications') === '1') ? ' checked' : '' ?>>Send shipment notifications when SMTP is ready</label>
            </section>
            <button class="button">Save settings</button>
        </form>
        <?php
    }, $error, $error !== '');
}

function save_settings(array $user): string
{
    $values = [];
    foreach (configurable_fields() as $fields) {
        foreach ($fields as $name => [$title,$type,$limit]) {
            $values[$name] = $type === 'email' ? email_value($_POST,$name,true)
                : value($_POST,$name,$limit,in_array($name,['company','phone','office','tracking_prefix','timezone'],true));
            if ($type === 'url' && $values[$name] !== ''
                && (!filter_var($values[$name], FILTER_VALIDATE_URL) || parse_url($values[$name], PHP_URL_SCHEME) !== 'https')) {
                throw new ValidationError('Use full HTTPS URLs for social profiles.');
            }
        }
    }
    if (!preg_match('/\A\+[1-9][0-9 ()-]{7,30}\z/', $values['phone'])
        || ($values['whatsapp'] !== '' && !preg_match('/\A[1-9][0-9]{7,14}\z/', $values['whatsapp']))) {
        throw new ValidationError('Use an international telephone number and digits only for WhatsApp.');
    }
    if (!preg_match('/\A[A-Z0-9]{2,3}\z/', $values['tracking_prefix'])) {
        throw new ValidationError('Use 2 or 3 uppercase letters or numbers for the tracking prefix.');
    }
    if (!in_array($values['timezone'], DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
        throw new ValidationError('Enter a valid IANA timezone.');
    }
    [$latitude,$longitude] = coordinates($values['office_latitude'], $values['office_longitude']);
    $values['office_latitude'] = $latitude ?? '';
    $values['office_longitude'] = $longitude ?? '';
    $values['notifications'] = input($_POST,'notifications') === '1' ? '1' : '0';
    transaction(static function () use ($user, $values): void {
        foreach ($values as $key => $value) {
            query('INSERT INTO settings (name,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$key,$value]);
        }
        record_audit($user, 'updated site configuration', 'settings', null);
    });
    return 'Website settings saved.';
}

function audit_page(array $user): void
{
    $page = page_number();
    $offset = ($page - 1) * 50;
    $logs = query("SELECT * FROM audit_log ORDER BY created_at DESC,id DESC LIMIT 51 OFFSET $offset")->fetchAll();
    admin_layout('Audit log', $user, static function () use ($page,$logs): void {
        ?><section class="panel"><div class="table-scroll">
            <table><thead><tr><th>Time</th><th>Staff member</th><th>Action</th><th>Record</th><th>Detail</th></tr></thead><tbody>
            <?php foreach (array_slice($logs, 0, 50) as $log): ?><tr>
                <td><?= e(show_time($log['created_at'])) ?></td><td><?= e($log['actor_name'] ?: 'System') ?></td>
                <td><?= e($log['action']) ?></td><td><?= e($log['target_type']) ?> <?= e($log['target_id']) ?></td><td><?= e($log['detail']) ?></td>
            </tr><?php endforeach; ?>
            </tbody></table></div><?php pagination('/admin/audit/', $page, count($logs) > 50); ?>
        </section><?php
    });
}

function geocode_route(array $user): void
{
    header('Content-Type: application/json; charset=utf-8');
    try {
        $result = geocode_lookup($user, $_POST);
        echo json_encode($result, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (ValidationError | HttpError $error) {
        http_response_code($error instanceof HttpError ? $error->status : 422);
        echo json_encode(['error' => $error->getMessage()]);
    }
}

function geocode_lookup(array $user, array $source): array
{
    require_permission($user, 'event');
    if ((config()['geocoder_url'] ?? '') === '') {
        throw new ValidationError('Location lookup is not configured. Enter coordinates manually.');
    }
    rate_limit('geocode-user', (string) $user['id'], 15, 60);
    $place = value($source, 'location', 200, true);
    $key = keyed_digest('geocode', mb_strtolower($place));
    $cached = row('SELECT candidates FROM geocode_cache WHERE query_key = ? AND expires_at > UTC_TIMESTAMP(6)', [$key]);
    if ($cached !== null) {
        return json_decode($cached['candidates'], true, 16, JSON_THROW_ON_ERROR);
    }
    $lock = 'afrilink-geocode-' . substr(hash('sha256', config()['db_name']), 0, 32);
    if ((int) row('SELECT GET_LOCK(?,0) AS acquired', [$lock])['acquired'] !== 1) {
        throw new ValidationError('Another location lookup is running. Try again in a moment.');
    }
    try {
        $last = row("SELECT value FROM settings WHERE name = '_last_geocode'");
        if ($last !== null && microtime(true) - (float) $last['value'] < 1.05) {
            throw new ValidationError('Please wait a moment between location lookups.');
        }
        query("INSERT INTO settings (name,value) VALUES ('_last_geocode',?) ON DUPLICATE KEY UPDATE value = VALUES(value)", [(string) microtime(true)]);
        $result = ['matches' => geocode_fetch($place)];
        query('INSERT INTO geocode_cache (query_key,candidates,expires_at) VALUES (?,?,UTC_TIMESTAMP(6) + INTERVAL 7 DAY)
            ON DUPLICATE KEY UPDATE candidates = VALUES(candidates), expires_at = VALUES(expires_at)',
            [$key, json_encode($result, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)]);
        return $result;
    } finally {
        query('SELECT RELEASE_LOCK(?)', [$lock]);
    }
}

function geocode_fetch(string $place): array
{
    $endpoint = config()['geocoder_url'];
    if (parse_url($endpoint, PHP_URL_SCHEME) !== 'https' || !extension_loaded('curl')) {
        throw new ValidationError('Location lookup is unavailable. Enter coordinates manually.');
    }
    $contact = config()['geocoder_contact'] ?? '';
    $url = $endpoint . (str_contains($endpoint,'?') ? '&' : '?') . http_build_query(['q' => $place,'format' => 'jsonv2','limit' => 5]);
    $handle = curl_init($url);
    $raw = '';
    curl_setopt_array($handle, [CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 6, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT => 'AfrilinkShipmentGeocoder/1.0 ' . base_url() . ' ' . $contact,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$raw): int {
            if (strlen($raw) + strlen($chunk) > 60000) {
                return 0;
            }
            $raw .= $chunk;
            return strlen($chunk);
        }]);
    $ok = curl_exec($handle);
    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $rows = $ok && $status === 200 ? json_decode($raw,true) : null;
    if (!is_array($rows)) {
        error_log('Afrilink location lookup failed. Provider HTTP ' . $status . ', cURL ' . curl_errno($handle) . '.');
        throw new ValidationError('Location lookup is unavailable. Enter coordinates manually.');
    }
    $matches = [];
    foreach (array_slice($rows,0,5) as $place) {
        if (!is_array($place) || !is_string($place['display_name'] ?? null) || !is_numeric($place['lat'] ?? null) || !is_numeric($place['lon'] ?? null)) {
            continue;
        }
        [$latitude,$longitude] = coordinates((string) $place['lat'], (string) $place['lon']);
        $matches[] = ['name' => mb_substr($place['display_name'],0,200), 'latitude' => $latitude, 'longitude' => $longitude];
    }
    return $matches;
}
