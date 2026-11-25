<?php
declare(strict_types=1);

define('PRIVATE_DIR', dirname(__DIR__));
date_default_timezone_set('UTC');
ini_set('display_errors', '0');

function config(): array
{
    static $config;
    if ($config === null) {
        $file = PRIVATE_DIR . '/config.php';
        $config = is_file($file) ? require $file : require PRIVATE_DIR . '/config.example.php';
    }
    return $config;
}

function configured(): bool
{
    $cfg = config();
    return $cfg['db_name'] !== '' && $cfg['db_user'] !== ''
        && preg_match('/\A[a-f0-9]{64}\z/i', $cfg['app_key']) === 1;
}

function db(): PDO
{
    static $pdo;
    if ($pdo === null) {
        $cfg = config();
        if (!configured()) {
            throw new RuntimeException('Private configuration is required.');
        }
        $dsn = 'mysql:host=' . $cfg['db_host'] . ';port=' . (int) $cfg['db_port']
            . ';dbname=' . $cfg['db_name'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

function query(string $sql, array $params = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function row(string $sql, array $params = []): ?array
{
    return query($sql, $params)->fetch() ?: null;
}

function transaction(callable $work): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $work();
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function installed(): bool
{
    if (!configured()) {
        return false;
    }
    try {
        return row("SELECT value FROM settings WHERE name = 'installed'") !== null;
    } catch (PDOException $error) {
        if ($error->getCode() === '42S02') {
            return false;
        }
        throw $error;
    }
}

function defaults(): array
{
    return [
        'company' => 'Afrilink Global Logistics',
        'phone' => '+232 72 603 254',
        'whatsapp' => '23272603254',
        'whatsapp_greeting' => 'Hello Afrilink Global Logistics. I would like to enquire about a shipment.',
        'email' => 'customerservice@afrilinksl.com',
        'office' => '144 New England Ville, Freetown, Sierra Leone',
        'hours' => '',
        'office_latitude' => '',
        'office_longitude' => '',
        'instagram' => '',
        'facebook' => '',
        'linkedin' => '',
        'tracking_prefix' => 'AGL',
        'timezone' => 'Africa/Freetown',
        'company_history' => '',
        'company_vision' => '',
        'privacy_policy' => '',
        'terms' => '',
        'cookie_policy' => '',
        'notifications' => '0',
    ];
}

function settings(): array
{
    static $cache;
    if ($cache === null) {
        $cache = defaults();
        if (installed()) {
            foreach (query('SELECT name,value FROM settings')->fetchAll() as $setting) {
                $cache[$setting['name']] = $setting['value'];
            }
        }
    }
    return $cache;
}

function setting(string $key): string
{
    return settings()[$key] ?? '';
}

function base_url(): string
{
    return rtrim(config()['base_url'], '/');
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(array $source, string $key): string
{
    return isset($source[$key]) && is_string($source[$key]) ? trim($source[$key]) : '';
}

function value(array $source, string $key, int $limit, bool $required = false): string
{
    $text = input($source, $key);
    if (($required && $text === '') || !mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > $limit
        || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text)) {
        throw new ValidationError('Check ' . str_replace('_', ' ', $key) . '.');
    }
    return $text;
}

function email_value(array $source, string $key, bool $required = false): string
{
    $email = value($source, $key, 254, $required);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new ValidationError('Enter a valid email address.');
    }
    return $email;
}

function redirect(string $path, int $status = 303): never
{
    header('Location: ' . $path, true, $status);
    exit;
}

function session_ready(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('afrilink_session');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => !config()['allow_http'],
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    $directory = PRIVATE_DIR . '/storage/sessions';
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create the session directory.');
    }
    session_save_path($directory);
    if (!session_start()) {
        throw new RuntimeException('Could not initialize the session.');
    }
}

function csrf_field(): string
{
    session_ready();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function require_csrf(): void
{
    session_ready();
    $token = $_SESSION['csrf'] ?? '';
    if ($token === '' || !hash_equals($token, input($_POST, 'csrf'))) {
        throw new HttpError(403, 'This form has expired. Reload the page and try again.');
    }
}

function flash(string $message): void
{
    session_ready();
    $_SESSION['flash'] = $message;
}

function take_flash(): string
{
    session_ready();
    $message = $_SESSION['flash'] ?? '';
    unset($_SESSION['flash']);
    return $message;
}

function anti_spam(): string
{
    return '<div class="honey" aria-hidden="true"><label>Leave this field empty<input name="office_fax" tabindex="-1" autocomplete="off"></label></div>';
}

function private_response(): void
{
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow');
}

function rate_limit(string $purpose, string $key, int $limit, int $window): void
{
    $now = time();
    $bucket = keyed_digest('rate:' . $purpose, $key . ':' . intdiv($now, $window));
    $count = transaction(static function () use ($bucket, $now, $window): int {
        query('INSERT INTO rate_limits (bucket,hits,expires_at) VALUES (?,1,?)
            ON DUPLICATE KEY UPDATE hits = LEAST(hits + 1, 4294967295)', [$bucket, $now + $window]);
        return (int) row('SELECT hits FROM rate_limits WHERE bucket = ?', [$bucket])['hits'];
    });
    if ($count > $limit) {
        header('Retry-After: ' . ($window - $now % $window));
        throw new HttpError(429, 'Too many attempts. Please wait and try again.');
    }
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function keyed_digest(string $purpose, string $value): string
{
    return hash_hmac('sha256', $purpose . chr(0) . $value, config()['app_key'], true);
}

function now_utc(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
}

function site_zone(): DateTimeZone
{
    return new DateTimeZone(setting('timezone'));
}

function iso_datetime(string $value): string
{
    return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
}

function show_time(?string $value, string $format = 'j M Y, H:i T'): string
{
    return $value === null || $value === ''
        ? ''
        : (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(site_zone())->format($format);
}

function input_datetime(string $value): ?string
{
    if ($value === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, site_zone());
    if (!$date || $date->format('Y-m-d\TH:i') !== $value || (int) $date->format('Y') < 1000) {
        throw new ValidationError('Enter a valid date and time.');
    }
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
}

function input_date(string $value): ?string
{
    if ($value === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value || (int) $date->format('Y') < 1000) {
        throw new ValidationError('Enter a valid date.');
    }
    return $value;
}

function date_label(?string $date): string
{
    return $date ? (new DateTimeImmutable($date))->format('j M Y') : '';
}

function record_audit(?array $actor, string $action, string $target, ?int $id, string $detail = ''): void
{
    query('INSERT INTO audit_log (actor_id,actor_name,action,target_type,target_id,detail,created_at) VALUES (?,?,?,?,?,?,?)',
        [$actor['id'] ?? null, $actor['name'] ?? '', $action, $target, $id, $detail, now_utc()]);
}

function csp_nonce(): string
{
    static $nonce;
    return $nonce ??= base64_encode(random_bytes(18));
}

function secure_headers(): void
{
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . csp_nonce() . "'; style-src 'self'; "
        . "img-src 'self' data: https://tile.openstreetmap.org; connect-src 'self'; "
        . "base-uri 'none'; frame-ancestors 'none'; form-action 'self'; object-src 'none'");
    if (!config()['allow_http']) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

final class HttpError extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}

final class ValidationError extends RuntimeException {}
