<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/app/core.php';
require PRIVATE_DIR . '/app/shipments.php';

function historical_timestamp(string $text, string $field): string
{
    if (!preg_match('/\A[1-9]\d{3}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})\z/', $text)) {
        throw new ValidationError($field . ' must be an RFC 3339 timestamp with an explicit offset.');
    }
    try {
        $date = new DateTimeImmutable($text);
        if (DateTimeImmutable::getLastErrors() !== false || $date->format('Y-m-d\TH:i:s') !== substr($text,0,19)) {
            throw new ValidationError($field . ' is not a valid calendar time.');
        }
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    } catch (DateMalformedStringException) {
        throw new ValidationError($field . ' is not a valid calendar time.');
    }
}

function historical_fields(array $record): array
{
    $shipment = [];
    foreach (shipment_fields() as $name => [$label,$type,$limit]) {
        if (isset($record[$name]) && !is_string($record[$name])) {
            throw new ValidationError($name . ' must be a string.');
        }
        $text = input($record,$name);
        $shipment[$name] = match ($type) {
            'datetime-local' => $text === '' ? null : historical_timestamp($text,$name),
            'date' => input_date($text),
            'email' => email_value($record,$name),
            'integer' => int_input($text,1,$limit),
            'decimal' => weight_input($text),
            default => value($record,$name,$limit),
        };
    }
    $createdAt = historical_timestamp(value($record,'created_at',40,true),'created_at');
    $updated = value($record,'updated_at',40);
    $shipment['created_at'] = $createdAt;
    $shipment['updated_at'] = $updated === '' ? $createdAt : historical_timestamp($updated,'updated_at');
    $shipment['tracking_number'] = value($record,'tracking_number',96,true);
    if (!preg_match('/\A[\p{L}\p{N}][\p{L}\p{N} ._\/-]*\z/u',$shipment['tracking_number'])) {
        throw new ValidationError('Tracking number contains unsupported characters.');
    }
    return $shipment;
}

function import_status(string $code): array
{
    $status = row('SELECT id,label FROM statuses WHERE code = ?',[$code]);
    if ($status === null) {
        throw new ValidationError('A status code is missing from the reviewed mapping.');
    }
    return $status;
}

function imported_events(array $record, array $current, int $shipmentId, string $created): int
{
    $items = $record['events'] ?? [];
    if (!is_array($items) || !array_is_list($items) || count($items) > 2000) {
        throw new ValidationError('Use a list of up to 2,000 ordered events per shipment.');
    }
    $statusSnapshot = $record['current_status_snapshot'] ?? false;
    if (!is_bool($statusSnapshot)) {
        throw new ValidationError('current_status_snapshot must be true or false.');
    }
    $latest = null;
    $count = 0;
    foreach ($items as $event) {
        if (!is_array($event)) {
            throw new ValidationError('Each event must be an object.');
        }
        $kind = value($event,'kind',10,true);
        if (!in_array($kind,['status','location'],true)) {
            throw new ValidationError('Event kind must be status or location.');
        }
        $status = $kind === 'status' ? import_status(value($event,'status_code',48,true)) : null;
        [$latitude,$longitude] = coordinates(input($event,'latitude'),input($event,'longitude'));
        if ($kind === 'location' && $latitude === null) {
            throw new ValidationError('A location event needs coordinates. Preserve text-only locations in their status events.');
        }
        $occurred = historical_timestamp(value($event,'occurred_at',40,true),'event occurred_at');
        $recorded = value($event,'recorded_at',40);
        $legacyLabel = value($event,'status_label',100);
        if ($status !== null) {
            $status['label'] = $legacyLabel === '' ? $status['label'] : $legacyLabel;
        }
        append_event($shipmentId,$status,[
            'occurred_at' => $occurred,
            'recorded_at' => $recorded === '' ? $occurred : historical_timestamp($recorded,'event recorded_at'),
            'location' => value($event,'location',200),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'description' => value($event,'description',2500),
            'customer_remarks' => value($event,'customer_remarks',2500),
            'actor_name' => value($event,'actor_name',160),
        ],null);
        $latest = $status !== null && ($latest === null || $occurred >= $latest['time'])
            ? ['id' => (int) $status['id'], 'time' => $occurred] : $latest;
        $count++;
    }
    $statusDiffers = $latest !== null && $latest['id'] !== (int) $current['id'];
    if ($statusDiffers && !$statusSnapshot) {
        throw new ValidationError('The latest historical status differs from the mapped current status.');
    }
    if ($latest === null || $statusDiffers) {
        $knownTime = value($record,'status_at',40);
        $recordedAt = now_utc();
        $statusTime = $knownTime === '' ? $recordedAt : historical_timestamp($knownTime,'status_at');
        if ($latest !== null && $statusTime < $latest['time']) {
            throw new ValidationError('The current status record cannot be earlier than the latest historical status.');
        }
        $remarks = $knownTime === ''
            ? 'The displayed time is when this status was copied from the previous tracking system. The original status time was unavailable.'
            : '';
        append_event($shipmentId,$current,[
            'occurred_at' => $statusTime,
            'recorded_at' => $recordedAt,
            'description' => 'Imported current status. Original shipment creation was ' . $created . ' UTC.',
            'customer_remarks' => $remarks,
        ],null);
        $count++;
    }
    return $count;
}

function import_documents(array $record, int $shipmentId, ?string $root, bool $apply, array &$copied): int
{
    $list = $record['attachments'] ?? [];
    if (!is_array($list) || !array_is_list($list) || count($list) > 100) {
        throw new ValidationError('Use a list of up to 100 attachments per shipment.');
    }
    foreach ($list as $item) {
        if (!is_array($item) || $root === null) {
            throw new ValidationError('Attachment import requires the reviewed files directory.');
        }
        $relative = value($item,'path',500,true);
        $path = realpath($root . '/' . $relative);
        $hash = strtolower(value($item,'sha256',64,true));
        if ($path === false || !str_starts_with($path,$root . '/') || !is_file($path) || !is_readable($path)) {
            throw new ValidationError('An attachment is missing from the reviewed files directory.');
        }
        $size = filesize($path);
        $media = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $expected = ['application/pdf'=>['pdf'],'image/jpeg'=>['jpg','jpeg'],'image/png'=>['png']];
        $name = value($item,'name',180);
        $name = $name === '' ? basename($relative) : $name;
        if ($size < 1 || $size > 8 * 1024 * 1024 || !isset($expected[$media])
            || !in_array(strtolower(pathinfo($name,PATHINFO_EXTENSION)),$expected[$media],true)
            || !hash_equals($hash,hash_file('sha256',$path))) {
            throw new ValidationError('An attachment failed type, size or checksum verification.');
        }
        if ($apply) {
            $stored = bin2hex(random_bytes(16));
            if (!is_dir(attachment_root()) && !@mkdir(attachment_root(),0700,true) && !is_dir(attachment_root())) {
                throw new RuntimeException('Could not create private document storage.');
            }
            $destination = attachment_root() . '/' . $stored;
            $copied[] = $stored;
            if (!copy($path,$destination) || !chmod($destination,0600)) {
                throw new RuntimeException('Could not copy an attachment.');
            }
            query('INSERT INTO attachments (shipment_id,stored_name,display_name,media_type,byte_count,digest,created_at)
                VALUES (?,?,?,?,?,?,?)',[$shipmentId,$stored,$name,$media,$size,$hash,now_utc()]);
        }
    }
    return count($list);
}

$options = getopt('', ['file:', 'attachments:', 'apply']);
$filename = $options['file'] ?? '';
$attachments = isset($options['attachments']) ? realpath((string) $options['attachments']) : null;
$apply = array_key_exists('apply',$options);
if (!is_string($filename) || !is_file($filename) || !is_readable($filename)
    || ($attachments !== null && ($attachments === false || !is_dir($attachments)))) {
    fwrite(STDERR,"Usage: php import-history.php --file=/private/path/shipments.jsonl [--attachments=/private/path/reviewed-files] [--apply]\n");
    exit(1);
}
if (!installed()) {
    fwrite(STDERR,"Install Afrilink before importing historical records.\n");
    exit(1);
}
$handle = fopen($filename,'rb');
$lock = 'afrilink-import-' . substr(hash('sha256',config()['db_name']),0,32);
if ((int) row('SELECT GET_LOCK(?,0) AS acquired',[$lock])['acquired'] !== 1) {
    fwrite(STDERR,"An import is already running.\n");
    exit(1);
}
$copied = [];
$counts = ['shipments'=>0,'skipped'=>0,'events'=>0,'attachments'=>0];
$lineNumber = 0;
$db = db();
$db->beginTransaction();
try {
    while (($line = fgets($handle,1048578)) !== false) {
        $lineNumber++;
        if ($lineNumber > 500 || strlen($line) > 1048576) {
            throw new ValidationError('Use batches of at most 500 lines and 1 MB per record.');
        }
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $record = json_decode($line,true,64,JSON_THROW_ON_ERROR);
        if (!is_array($record) || array_is_list($record)) {
            throw new ValidationError('Each line must contain one shipment object.');
        }
        $sourceKey = value($record,'source_key',191,true);
        $digest = hash('sha256',$line,true);
        $old = row('SELECT id,source_digest FROM shipments WHERE source_key = ?',[$sourceKey]);
        if ($old !== null) {
            if (!hash_equals($old['source_digest'] ?? '',$digest)) {
                throw new ValidationError('A previously imported source record changed. Reconcile it before continuing.');
            }
            $counts['skipped']++;
            continue;
        }
        $shipment = historical_fields($record);
        $current = import_status(value($record,'status_code',48,true));
        $shipment['source_key'] = $sourceKey;
        $shipment['source_digest'] = $digest;
        $shipment['lookup_key'] = tracking_key($shipment['tracking_number']);
        $shipment['legacy'] = 1;
        $shipment['status_id'] = (int) $current['id'];
        $columns = array_keys($shipment);
        query('INSERT INTO shipments (' . implode(',',$columns) . ') VALUES ('
            . implode(',',array_fill(0,count($columns),'?')) . ')',array_values($shipment));
        $id = (int) $db->lastInsertId();
        $counts['events'] += imported_events($record,$current,$id,$shipment['created_at']);
        $counts['attachments'] += import_documents($record,$id,$attachments,$apply,$copied);
        $counts['shipments']++;
        record_audit(null,'imported shipment','shipment',$id);
    }
    if ($apply) {
        $db->commit();
    } else {
        $db->rollBack();
    }
    echo json_encode(['mode' => $apply ? 'applied' : 'dry run', ...$counts],JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),"\n";
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    foreach ($copied as $stored) {
        remove_stored_file($stored);
    }
    $description = match (true) {
        $error instanceof ValidationError => $error->getMessage(),
        $error instanceof JsonException => 'Invalid JSON.',
        $error instanceof PDOException && $error->getCode() === '23000' => 'Duplicate or conflicting source or tracking number.',
        default => 'Import could not be completed.',
    };
    fwrite(STDERR,'Line ' . $lineNumber . ': ' . $description . " No records from this batch were committed.\n");
    exit(1);
} finally {
    query('SELECT RELEASE_LOCK(?)',[$lock]);
    fclose($handle);
}
