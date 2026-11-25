<?php
declare(strict_types=1);

function tracking_key(string $number): string
{
    return keyed_digest('tracking', mb_strtoupper(trim($number), 'UTF-8'));
}

function verification_key(string $code): string
{
    return keyed_digest('verification', strtoupper(trim($code)));
}

function new_tracking_number(): string
{
    return setting('tracking_prefix') . '-' . strtoupper(bin2hex(random_bytes(12)));
}

function shipment_fields(): array
{
    return [
        'reference' => ['Shipment reference', 'text', 100],
        'shipper_name' => ['Sender name', 'text', 160],
        'shipper_phone' => ['Sender phone', 'tel', 40],
        'shipper_email' => ['Sender email', 'email', 254],
        'shipper_address' => ['Sender address', 'textarea', 500],
        'receiver_name' => ['Receiver name', 'text', 160],
        'receiver_phone' => ['Receiver phone', 'tel', 40],
        'receiver_email' => ['Receiver email', 'email', 254],
        'receiver_address' => ['Receiver address', 'textarea', 500],
        'origin' => ['Origin country', 'text', 100],
        'destination' => ['Destination country', 'text', 100],
        'description' => ['Shipment description', 'textarea', 4000],
        'category' => ['Category', 'text', 90],
        'packages' => ['Package quantity', 'integer', 999999],
        'weight' => ['Weight (kg)', 'decimal', 999999999],
        'dimensions' => ['Dimensions', 'text', 100],
        'method' => ['Shipping method', 'text', 90],
        'pickup_at' => ['Pickup date and time', 'datetime-local', 0],
        'departure_at' => ['Departure date and time', 'datetime-local', 0],
        'estimated_delivery' => ['Estimated delivery date', 'date', 0],
        'internal_notes' => ['Internal notes', 'textarea', 4000],
        'public_notes' => ['Customer-visible notes', 'textarea', 4000],
    ];
}

function int_input(string $text, int $min, int $max): ?int
{
    if ($text === '') {
        return null;
    }
    if (!ctype_digit($text) || (int) $text < $min || (int) $text > $max) {
        throw new ValidationError('Enter a whole number within the allowed range.');
    }
    return (int) $text;
}

function weight_input(string $text): ?string
{
    if ($text === '') {
        return null;
    }
    if (!preg_match('/\A\d{1,9}(?:\.\d{1,3})?\z/', $text)) {
        throw new ValidationError('Enter a weight with up to three decimal places.');
    }
    return $text;
}

function shipment_input(array $source): array
{
    $values = [];
    foreach (shipment_fields() as $name => [$label, $type, $limit]) {
        $text = input($source, $name);
        $values[$name] = match ($type) {
            'datetime-local' => input_datetime($text), 'date' => input_date($text),
            'email' => email_value($source, $name),
            'integer' => int_input($text, 1, $limit), 'decimal' => weight_input($text),
            default => value($source, $name, $limit, in_array($name, ['shipper_name','receiver_name','origin','destination','method'], true)),
        };
    }
    if ($values['departure_at'] && $values['pickup_at'] && $values['departure_at'] < $values['pickup_at']) {
        throw new ValidationError('Departure must be at or after pickup.');
    }
    return $values;
}

function shipment_for_staff(array $user, int $id, bool $lock = false): array
{
    $filters = ['s.id = ?'];
    $params = [$id];
    if ($user['role'] === 'agent') {
        $filters[] = 's.agent_id = ? AND s.deleted_at IS NULL AND s.archived_at IS NULL';
        $params[] = $user['id'];
    } elseif (!can($user, 'manage-shipments')) {
        $filters[] = 's.deleted_at IS NULL';
    }
    $sql = 'SELECT s.*, t.label AS status_label,t.bucket FROM shipments s JOIN statuses t ON s.status_id = t.id WHERE '
        . implode(' AND ', $filters) . ($lock ? ' FOR UPDATE' : '');
    $shipment = row($sql, $params);
    if ($shipment === null) {
        throw new HttpError(404, 'Shipment not found.');
    }
    return $shipment;
}

function status_options(): array
{
    return query('SELECT * FROM statuses ORDER BY sort_order,id')->fetchAll();
}

function pick_status(int $id): array
{
    $status = row('SELECT * FROM statuses WHERE id = ? AND active = 1 FOR UPDATE', [$id]);
    if ($status === null) {
        throw new ValidationError('Choose an active shipment status.');
    }
    return $status;
}

function coordinates(string $latText, string $lonText): array
{
    if ($latText === '' && $lonText === '') {
        return [null, null];
    }
    if (!is_numeric($latText) || !is_numeric($lonText) || abs((float) $latText) > 90 || abs((float) $lonText) > 180) {
        throw new ValidationError('Enter both valid latitude and longitude coordinates.');
    }
    return [sprintf('%.6F', (float) $latText), sprintf('%.6F', (float) $lonText)];
}

function append_event(int $id, ?array $status, array $fields, ?array $actor): int
{
    query('INSERT INTO shipment_events (shipment_id,kind,status_id,status_label,occurred_at,recorded_at,location,
        latitude,longitude,description,customer_remarks,actor_id,actor_name) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$id, $status === null ? 'location' : 'status', $status['id'] ?? null, $status['label'] ?? '',
         $fields['occurred_at'] ?? now_utc(), $fields['recorded_at'] ?? now_utc(), $fields['location'] ?? '',
         $fields['latitude'] ?? null, $fields['longitude'] ?? null, $fields['description'] ?? '',
         $fields['customer_remarks'] ?? '', $actor['id'] ?? null, $actor['name'] ?? ($fields['actor_name'] ?? '')]);
    return (int) db()->lastInsertId();
}

function latest_status_event(int $id): ?array
{
    return row("SELECT status_id,status_label FROM shipment_events WHERE shipment_id = ? AND kind = 'status'
        AND deleted_at IS NULL ORDER BY occurred_at DESC,id DESC LIMIT 1", [$id]);
}

function assignable_staff(): array
{
    return query("SELECT id,name FROM staff WHERE role = 'agent' AND enabled = 1 ORDER BY name,id")->fetchAll();
}

function selected_agent(?int $agentId, ?int $previous): ?int
{
    if ($agentId !== null && $agentId !== $previous
        && !row("SELECT id FROM staff WHERE id = ? AND enabled = 1 AND role = 'agent' FOR UPDATE", [$agentId])) {
        throw new ValidationError('Select an active delivery agent.');
    }
    return $agentId;
}

function save_shipment(array $user, ?int $id, array $source): int
{
    require_permission($user, 'manage-shipments');
    $fields = shipment_input($source);
    $agent = int_input(input($source, 'agent_id'), 1, PHP_INT_MAX);
    $customer = int_input(input($source, 'customer_id'), 1, PHP_INT_MAX);
    $statusId = int_input(input($source, 'status_id'), 1, 4294967295);
    if ($statusId === null) {
        throw new ValidationError('Select a shipment status.');
    }
    return transaction(static function () use ($user, $id, $fields, $agent, $customer, $statusId): int {
        $current = $id ? shipment_for_staff($user, $id, true) : null;
        if ($current && ($current['deleted_at'] !== null || $current['archived_at'] !== null)) {
            throw new ValidationError('Restore or unarchive the shipment before editing.');
        }
        $fields['agent_id'] = selected_agent($agent, isset($current['agent_id']) ? (int) $current['agent_id'] : null);
        if ($customer !== null && !row('SELECT id FROM customers WHERE id = ? FOR UPDATE', [$customer])) {
            throw new ValidationError('Choose an existing customer profile.');
        }
        $fields['customer_id'] = $customer;
        $fields['updated_at'] = now_utc();
        $changedStatus = $current === null || (int) $current['status_id'] !== $statusId;
        $status = $changedStatus ? pick_status($statusId) : null;
        if ($id === null) {
            $fields['status_id'] = $statusId;
            $fields['created_at'] = $fields['updated_at'];
            $fields['tracking_number'] = new_tracking_number();
            $fields['lookup_key'] = tracking_key($fields['tracking_number']);
            $columns = array_keys($fields);
            query('INSERT INTO shipments (' . implode(',', $columns) . ') VALUES ('
                . implode(',', array_fill(0, count($columns), '?')) . ')', array_values($fields));
            $id = (int) db()->lastInsertId();
            append_event($id, $status, ['occurred_at' => $fields['created_at']], $user);
            record_audit($user, 'created shipment', 'shipment', $id);
            notify_shipment($fields + ['id' => $id], 'Shipment created', 'We have registered your shipment. Its current status is ' . $status['label'] . '.');
            return $id;
        }
        $assignments = array_map(static fn (string $column): string => "$column = ?", array_keys($fields));
        query('UPDATE shipments SET ' . implode(',', $assignments) . ' WHERE id = ?', [...array_values($fields), $id]);
        record_audit($user, 'edited shipment', 'shipment', $id);
        if ($status !== null) {
            apply_status_event($current + ['id' => $id], $status, [], $user, $fields + $current);
        }
        return $id;
    });
}

function apply_status_event(array $shipment, array $status, array $fields, array $actor, array $notificationShipment): void
{
    append_event((int) $shipment['id'], $status, $fields, $actor);
    $latest = latest_status_event((int) $shipment['id']);
    query('UPDATE shipments SET status_id = ?, updated_at = ? WHERE id = ?', [$latest['status_id'], now_utc(), $shipment['id']]);
    record_audit($actor, 'recorded status', 'shipment', (int) $shipment['id'], $status['label']);
    $changed = (int) $latest['status_id'] !== (int) $shipment['status_id'];
    if ($changed) {
        notify_shipment($notificationShipment, $latest['status_label'], 'Your shipment status is now ' . $latest['status_label'] . '.');
    }
}

function save_event(array $user, int $id, array $source): void
{
    require_permission($user, 'event');
    $kind = input($source, 'kind');
    if (!in_array($kind, ['status', 'location'], true)) {
        throw new ValidationError('Choose the type of tracking update.');
    }
    $time = input_datetime(input($source, 'occurred_at')) ?? now_utc();
    if ($time > now_utc()) {
        throw new ValidationError('An update cannot be dated in the future.');
    }
    [$latitude, $longitude] = coordinates(input($source, 'latitude'), input($source, 'longitude'));
    if ($kind === 'location' && $latitude === null) {
        throw new ValidationError('Enter or look up coordinates for a location update.');
    }
    $fields = [
        'occurred_at' => $time, 'location' => value($source, 'location', 200, $kind === 'location'),
        'latitude' => $latitude, 'longitude' => $longitude, 'description' => value($source, 'description', 2500),
        'customer_remarks' => value($source, 'customer_remarks', 2500),
    ];
    transaction(static function () use ($user, $id, $source, $fields, $kind): void {
        $shipment = shipment_for_staff($user, $id, true);
        if ($shipment['deleted_at'] !== null || $shipment['archived_at'] !== null) {
            throw new ValidationError('Updates can only be added to active shipments.');
        }
        if ($fields['occurred_at'] < $shipment['created_at']) {
            throw new ValidationError('The update cannot predate shipment creation.');
        }
        if ($kind === 'status') {
            $status = pick_status((int) int_input(input($source, 'status_id'), 1, 4294967295));
            if ($user['role'] === 'agent' && !in_array($status['code'], ['out-for-delivery','delivered','attempted','exception'], true)) {
                throw new HttpError(403, 'Delivery agents can submit delivery statuses only.');
            }
            apply_status_event($shipment, $status, $fields, $user, $shipment);
            return;
        }
        append_event($id, null, $fields, $user);
        query('UPDATE shipments SET updated_at = ? WHERE id = ?', [now_utc(), $id]);
        record_audit($user, 'recorded location', 'shipment', $id);
        notify_shipment($shipment, 'Shipment location update', 'A new shipment location was recorded on ' . show_time($fields['occurred_at']) . '.');
    });
}

function remove_event(array $user, int $id, int $eventId): void
{
    require_permission($user, 'correction');
    transaction(static function () use ($user, $id, $eventId): void {
        $shipment = shipment_for_staff($user, $id, true);
        if ($shipment['deleted_at'] !== null) {
            throw new ValidationError('Restore the shipment before correcting its history.');
        }
        $event = row('SELECT id,kind FROM shipment_events WHERE id = ? AND shipment_id = ? AND deleted_at IS NULL FOR UPDATE', [$eventId, $id]);
        if ($event === null) {
            throw new HttpError(404, 'Tracking event not found.');
        }
        query('UPDATE shipment_events SET deleted_at = ? WHERE id = ?', [now_utc(), $eventId]);
        $latest = latest_status_event($id);
        if ($latest === null) {
            throw new ValidationError('Keep at least one status event in the shipment history.');
        }
        query('UPDATE shipments SET status_id = ?, updated_at = ? WHERE id = ?', [$latest['status_id'], now_utc(), $id]);
        record_audit($user, 'removed tracking event', 'shipment', $id, 'Event ' . $eventId);
    });
}

function lifecycle(array $user, int $id, string $action): void
{
    require_permission($user, $action === 'purge' ? 'purge' : 'manage-shipments');
    $files = transaction(static function () use ($user, $id, $action): array {
        $shipment = shipment_for_staff($user, $id, true);
        $deleted = $shipment['deleted_at'] !== null;
        $archived = $shipment['archived_at'] !== null;
        $fields = match ($action) {
            'archive' => !$deleted && !$archived ? ['archived_at' => now_utc()] : [],
            'unarchive' => !$deleted && $archived ? ['archived_at' => null] : [],
            'trash' => !$deleted ? ['deleted_at' => now_utc()] : [],
            'restore' => $deleted ? ['deleted_at' => null] : [],
            'purge' => $deleted ? ['purge' => true] : [],
            default => [],
        };
        if ($fields === []) {
            throw new ValidationError('This action is unavailable for the shipment’s current state.');
        }
        if ($action === 'purge') {
            $files = query('SELECT stored_name FROM attachments WHERE shipment_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN);
            query('DELETE FROM shipments WHERE id = ?', [$id]);
        } else {
            $column = array_key_first($fields);
            query("UPDATE shipments SET $column = ?, updated_at = ? WHERE id = ?", [$fields[$column], now_utc(), $id]);
            $files = [];
        }
        record_audit($user, $action . ' shipment', 'shipment', $id);
        return $files;
    });
    foreach ($files as $file) {
        remove_stored_file($file);
    }
}

function issue_ship_code(array $user, int $id): string
{
    require_permission($user, 'manage-shipments');
    return transaction(static function () use ($user, $id): string {
        $shipment = shipment_for_staff($user, $id, true);
        if ($shipment['deleted_at'] !== null) {
            throw new ValidationError('Restore the shipment before issuing a verification code.');
        }
        $code = strtoupper(bin2hex(random_bytes(8)));
        query('UPDATE shipments SET verification_key = ? WHERE id = ?', [verification_key($code), $id]);
        record_audit($user, 'issued verification code', 'shipment', $id);
        return $code;
    });
}

function public_shipment(string $number, string $verification, int $page = 1): ?array
{
    $shipment = row('SELECT s.id,s.tracking_number,s.verification_key,s.legacy,s.shipper_name,s.receiver_name,
        s.origin,s.destination,s.created_at,s.estimated_delivery,s.category,s.method,s.weight,s.packages,
        s.public_notes,t.label AS status_label,t.bucket FROM shipments s JOIN statuses t ON t.id = s.status_id
        WHERE s.lookup_key = ? AND s.deleted_at IS NULL', [tracking_key($number)]);
    if ($shipment === null) {
        return null;
    }
    $verified = $verification !== '' && $shipment['verification_key'] !== null
        && hash_equals($shipment['verification_key'], verification_key($verification));
    if ($shipment['legacy'] && !$verified) {
        $shipment['shipper_name'] = $shipment['receiver_name'] = '';
    } elseif (!$verified) {
        foreach (['shipper_name', 'receiver_name'] as $key) {
            $shipment[$key] = $shipment[$key] === '' ? '' : mb_substr($shipment[$key], 0, 1) . '…';
        }
    }
    $shipId = $shipment['id'];
    $offset = ($page - 1) * 50;
    $events = query("SELECT kind,status_label,occurred_at,location,customer_remarks FROM shipment_events
        WHERE shipment_id = ? AND deleted_at IS NULL ORDER BY occurred_at DESC,id DESC LIMIT 51 OFFSET $offset", [$shipId])->fetchAll();
    $shipment['events'] = array_slice($events, 0, 50);
    $shipment['history_page'] = $page;
    $shipment['older_events'] = count($events) > 50;
    $shipment['location'] = row('SELECT latitude,longitude,location,occurred_at FROM shipment_events
        WHERE shipment_id = ? AND deleted_at IS NULL AND latitude IS NOT NULL AND longitude IS NOT NULL
        ORDER BY occurred_at DESC,id DESC LIMIT 1', [$shipId]);
    $shipment['verification_failed'] = $verification !== '' && !$verified;
    unset($shipment['id'], $shipment['verification_key'], $shipment['legacy']);
    return $shipment;
}

function shipment_list_filter(array $user, array $filters): array
{
    $clauses = [];
    $params = [];
    $state = input($filters, 'state');
    if ($user['role'] === 'agent') {
        $clauses[] = 's.agent_id = ?';
        $params[] = $user['id'];
        $state = '';
    }
    if ($state === 'trash' && can($user, 'manage-shipments')) {
        $clauses[] = 's.deleted_at IS NOT NULL';
    } else {
        $clauses[] = 's.deleted_at IS NULL';
        $clauses[] = $state === 'archive' ? 's.archived_at IS NOT NULL' : 's.archived_at IS NULL';
    }
    $search = value($filters, 'q', 100);
    if ($search !== '') {
        $clauses[] = '(s.lookup_key = ? OR s.reference = ?)';
        array_push($params, tracking_key($search), $search);
    }
    $status = int_input(input($filters, 'status'), 1, 4294967295);
    if ($status !== null) {
        $clauses[] = 's.status_id = ?';
        $params[] = $status;
    }
    foreach (['origin', 'destination'] as $column) {
        $place = value($filters, $column, 100);
        if ($place !== '') {
            $clauses[] = "s.$column = ?";
            $params[] = $place;
        }
    }
    $from = input_date(input($filters, 'from'));
    $to = input_date(input($filters, 'to'));
    if ($from !== null) {
        $clauses[] = 's.created_at >= ?';
        $params[] = input_datetime($from . 'T00:00');
    }
    if ($to !== null) {
        $clauses[] = 's.created_at < ?';
        $params[] = input_datetime((new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d') . 'T00:00');
    }
    return [' WHERE ' . implode(' AND ', $clauses), $params];
}

function attachment_root(): string
{
    return PRIVATE_DIR . '/storage/files';
}

function remove_stored_file(string $name): void
{
    $path = attachment_root() . '/' . $name;
    if (preg_match('/\A[a-f0-9]{32}\z/', $name) && is_file($path) && !unlink($path)) {
        error_log('Could not remove a private Afrilink attachment.');
    }
}

function upload_attachment(array $user, int $id, array $file): void
{
    require_permission($user, 'manage-shipments');
    $size = (int) ($file['size'] ?? 0);
    $temporary = $file['tmp_name'] ?? '';
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK || $size < 1 || $size > 8 * 1024 * 1024 || !is_uploaded_file($temporary)) {
        throw new ValidationError('Choose a PDF, PNG or JPEG file up to 8 MB.');
    }
    $media = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
    $accepted = ['application/pdf' => ['pdf'], 'image/png' => ['png'], 'image/jpeg' => ['jpg','jpeg']];
    $original = $file['name'] ?? '';
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!isset($accepted[$media]) || !in_array($extension, $accepted[$media], true)) {
        throw new ValidationError('Choose a PDF, PNG or JPEG file.');
    }
    $name = mb_substr(preg_replace('/[^\p{L}\p{N}_. -]/u', '_', basename($original)) ?: 'document.' . $extension, 0, 180);
    $stored = bin2hex(random_bytes(16));
    if (!is_dir(attachment_root()) && !mkdir(attachment_root(), 0700, true) && !is_dir(attachment_root())) {
        throw new RuntimeException('Could not create the attachment directory.');
    }
    $path = attachment_root() . '/' . $stored;
    try {
        transaction(static function () use ($id, $user, $temporary, $path, $size, $stored, $media, $name): void {
            $shipment = shipment_for_staff($user, $id, true);
            if ($shipment['deleted_at'] !== null) {
                throw new ValidationError('Restore the shipment before uploading documents.');
            }
            if (!move_uploaded_file($temporary, $path)) {
                throw new RuntimeException('Could not store the document.');
            }
            chmod($path, 0600);
            query('INSERT INTO attachments (shipment_id,stored_name,display_name,media_type,byte_count,digest,created_at)
                VALUES (?,?,?,?,?,?,?)', [$id, $stored, $name, $media, $size, hash_file('sha256', $path), now_utc()]);
            record_audit($user, 'uploaded attachment', 'shipment', $id);
        });
    } catch (Throwable $error) {
        remove_stored_file($stored);
        throw $error;
    }
}
