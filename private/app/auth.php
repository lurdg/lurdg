<?php
declare(strict_types=1);

function roles(): array
{
    return [
        'owner' => 'Super administrator', 'administrator' => 'Administrator',
        'manager' => 'Shipment manager', 'operations' => 'Operations officer', 'agent' => 'Delivery agent',
    ];
}

function can(array $user, string $permission): bool
{
    $roles = [
        'manage-shipments' => ['owner', 'administrator', 'manager'],
        'event' => ['owner', 'administrator', 'manager', 'operations', 'agent'],
        'correction' => ['owner', 'administrator', 'manager'],
        'export' => ['owner', 'administrator', 'manager'],
        'inbox' => ['owner', 'administrator', 'manager'],
        'customers' => ['owner', 'administrator', 'manager'],
        'catalogue' => ['owner', 'administrator'],
        'staff' => ['owner', 'administrator'],
        'settings' => ['owner', 'administrator'],
        'audit' => ['owner', 'administrator'],
        'purge' => ['owner'],
    ];
    return in_array($user['role'], $roles[$permission] ?? [], true);
}

function require_permission(array $user, string $permission): void
{
    if (!can($user, $permission)) {
        throw new HttpError(403, 'Your role does not have access to this action.');
    }
}

function staff_user(): ?array
{
    session_ready();
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $user = row('SELECT id,name,email,role,session_version FROM staff WHERE id = ? AND enabled = 1', [$_SESSION['user_id']]);
    if ($user === null || (int) $user['session_version'] !== ($_SESSION['session_version'] ?? 0)
        || time() - ($_SESSION['last_seen'] ?? 0) > 1800 || time() - ($_SESSION['signed_in_at'] ?? 0) > 43200) {
        $_SESSION = [];
        session_regenerate_id(true);
        return null;
    }
    $_SESSION['last_seen'] = time();
    return $user;
}

function require_staff(): array
{
    $user = staff_user();
    if ($user === null) {
        redirect('/admin/login/');
    }
    return $user;
}

function raw_password(string $name): string
{
    return isset($_POST[$name]) && is_string($_POST[$name]) ? $_POST[$name] : '';
}

function password_digest(string $password): string
{
    if (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, chr(0))) {
        throw new ValidationError('Use a password between 12 and 72 bytes.');
    }
    return password_hash($password, PASSWORD_DEFAULT);
}

function sign_in(string $email, string $password): bool
{
    rate_limit('login-ip', client_ip(), 25, 900);
    rate_limit('login-account-ip', strtolower($email) . ':' . client_ip(), 6, 900);
    $user = row('SELECT * FROM staff WHERE email = ? AND enabled = 1', [$email]);
    $hash = $user['password_hash'] ?? '$2y$12$FJG9s6G/BSk6b8KEPFc2GO98xJdZ/D2iTkOkq638Yp5JMqA2AQCze';
    if (strlen($password) > 72 || !password_verify($password, $hash) || $user === null) {
        return false;
    }
    transaction(static function () use ($user, $password): void {
        query('UPDATE staff SET last_login_at = ? WHERE id = ?', [now_utc(), $user['id']]);
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            query('UPDATE staff SET password_hash = ? WHERE id = ? AND password_hash = ?',
                [password_digest($password), $user['id'], $user['password_hash']]);
        }
        record_audit($user, 'signed in', 'staff', (int) $user['id']);
    });
    session_ready();
    session_regenerate_id(true);
    $_SESSION = ['user_id' => (int) $user['id'], 'session_version' => (int) $user['session_version'],
        'last_seen' => time(), 'signed_in_at' => time()];
    return true;
}

function sign_out(array $user): never
{
    record_audit($user, 'signed out', 'staff', (int) $user['id']);
    $_SESSION = [];
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => 1, 'path' => $cookie['path'], 'secure' => $cookie['secure'],
        'httponly' => true, 'samesite' => 'Lax']);
    session_destroy();
    redirect('/admin/login/');
}

function create_first_owner(string $setupKey, string $name, string $email, string $password): void
{
    $cfg = config();
    if (preg_match('/\A[a-f0-9]{64}\z/i', $cfg['setup_key']) !== 1 || !hash_equals($cfg['setup_key'], $setupKey)) {
        throw new ValidationError('The setup key is incorrect.');
    }
    $hash = password_digest($password);
    $lockName = 'afrilink-install-' . substr(hash('sha256', $cfg['db_name']), 0, 36);
    if ((int) row('SELECT GET_LOCK(?, 5) AS acquired', [$lockName])['acquired'] !== 1) {
        throw new HttpError(503, 'Setup is already in progress.');
    }
    try {
        if (installed()) {
            throw new HttpError(404, 'Setup has already been completed.');
        }
        $schema = file_get_contents(PRIVATE_DIR . '/schema/install.sql');
        foreach (explode(';', $schema) as $command) {
            if (trim($command) !== '') {
                db()->exec($command);
            }
        }
        transaction(static function () use ($name, $email, $hash): void {
            query("INSERT INTO staff (name,email,password_hash,role,created_at) VALUES (?,?,?,'owner',?)",
                [$name, strtolower($email), $hash, now_utc()]);
            $id = (int) db()->lastInsertId();
            query("INSERT INTO settings (name,value) VALUES ('installed','1')");
            record_audit(['id' => $id, 'name' => $name], 'installed platform', 'staff', $id);
        });
    } finally {
        query('SELECT RELEASE_LOCK(?)', [$lockName]);
    }
}

function issue_reset(string $email): void
{
    rate_limit('reset-ip', client_ip(), 8, 900);
    rate_limit('reset-email', strtolower($email), 3, 3600);
    if (!smtp_ready() || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    transaction(static function () use ($email): void {
        $user = row('SELECT id,name,email FROM staff WHERE email = ? AND enabled = 1 FOR UPDATE', [$email]);
        if ($user === null) {
            return;
        }
        $token = bin2hex(random_bytes(32));
        query('DELETE FROM password_resets WHERE staff_id = ?', [$user['id']]);
        query('INSERT INTO password_resets (token_hash,staff_id,expires_at) VALUES (?,?,UTC_TIMESTAMP(6) + INTERVAL 1 HOUR)',
            [keyed_digest('reset', $token), $user['id']]);
        $url = base_url() . '/admin/reset/?token=' . $token;
        enqueue_email($user['email'], 'Reset your Afrilink staff password',
            "A password reset was requested for your Afrilink staff account.\n\nOpen this link within one hour:\n" . $url
            . "\n\nIf you did not request this, you can ignore this email.");
    });
}

function use_reset(string $token, string $password): void
{
    if (preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) {
        throw new ValidationError('This reset link has expired or has already been used.');
    }
    rate_limit('reset-use', client_ip(), 8, 900);
    $hash = password_digest($password);
    transaction(static function () use ($token, $hash): void {
        $key = keyed_digest('reset', $token);
        $reset = row('SELECT staff_id FROM password_resets WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP(6)', [$key]);
        $user = $reset ? row('SELECT id,name FROM staff WHERE id = ? AND enabled = 1 FOR UPDATE', [$reset['staff_id']]) : null;
        if ($user === null || !query('DELETE FROM password_resets WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP(6)', [$key])->rowCount()) {
            throw new ValidationError('This reset link has expired or has already been used.');
        }
        query('DELETE FROM password_resets WHERE staff_id = ?', [$user['id']]);
        query('UPDATE staff SET password_hash = ?, session_version = session_version + 1 WHERE id = ?', [$hash, $user['id']]);
        record_audit($user, 'reset password', 'staff', (int) $user['id']);
    });
}
