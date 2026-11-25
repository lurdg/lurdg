<?php
declare(strict_types=1);

function error_page(string $message): void
{
    private_response();
    public_layout('Afrilink Global Logistics', 'Afrilink Global Logistics', static function () use ($message): void {
        ?><div class="section wrap narrow"><h1 class="error-title">Afrilink Global Logistics</h1>
        <p class="notice error" role="alert"><?= e($message) ?></p><a class="text-link" href="/">Return to home</a></div><?php
    }, false, true);
}

function admin_layout(string $title, ?array $user, callable $body, string $message = '', bool $error = false): void
{
    private_response();
    $notice = $user ? take_flash() : '';
    ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | Afrilink Operations</title>
<link rel="stylesheet" href="/assets/site.css"><link rel="stylesheet" href="/assets/admin.css">
<link rel="icon" href="/assets/afrilink.png" type="image/png">
<script defer src="/assets/admin.js"></script>
</head><body class="admin-page">
<a class="skip" href="#main">Skip to content</a>
<header class="admin-header"><div class="wrap">
    <?= brand() ?>
    <?php if ($user): ?>
        <span><?= e($user['name']) ?> <span class="muted"><?= e(roles()[$user['role']]) ?></span></span>
        <form action="/admin/logout/" method="post"><?= csrf_field() ?><button type="submit" class="button secondary compact">Sign out</button></form>
    <?php endif; ?>
</div></header>
<?php if ($user): ?>
<nav class="admin-nav" aria-label="Staff navigation"><div class="wrap">
    <a href="/admin/">Overview</a><a href="/admin/shipments/">Shipments</a>
    <?php foreach (['inbox' => 'Inbox', 'customers' => 'Customers', 'catalogue' => 'Statuses', 'staff' => 'Staff', 'settings' => 'Settings', 'audit' => 'Audit log'] as $permission => $label): ?>
        <?php if (can($user, $permission)): ?><a href="/admin/<?= e($permission === 'catalogue' ? 'statuses' : $permission) ?>/"><?= e($label) ?></a><?php endif; ?>
    <?php endforeach; ?>
</div></nav>
<?php endif; ?>
<main id="main" class="wrap admin-main<?= $user ? '' : ' admin-auth' ?>">
    <h1><?= e($title) ?></h1>
    <?php if ($notice !== ''): ?><p role="status" class="notice"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($message !== ''): ?><p class="notice<?= $error ? ' error' : '' ?>" role="<?= $error ? 'alert' : 'status' ?>"><?= e($message) ?></p><?php endif; ?>
    <?php $body(); ?>
</main></body></html>
<?php
}

function setup_page(): void
{
    private_response();
    if (installed()) {
        throw new HttpError(404, 'Setup has already been completed.');
    }
    $hasConfig = configured();
    $error = '';
    if (is_post()) {
        require_csrf();
        if (!$hasConfig) {
            throw new HttpError(503, 'Add the private configuration before starting setup.');
        }
        try {
            create_first_owner(value($_POST, 'setup_key', 64, true), value($_POST, 'name', 160, true),
                email_value($_POST, 'email', true), raw_password('password'));
            flash('Your super administrator account was created. Sign in to get started.');
            redirect('/admin/login/');
        } catch (ValidationError $validation) {
            $error = $validation->getMessage();
            http_response_code(422);
        }
    }
    admin_layout('Set up Afrilink', null, static function () use ($hasConfig): void {
        ?>
        <section class="panel">
        <?php if (!$hasConfig): ?>
            <p>Set the database credentials and keys in the private configuration file. The installation guide explains the cPanel steps.</p>
        <?php else: ?>
            <form action="/setup/" method="post" class="stack">
                <?= csrf_field() ?>
                <label>Setup key<input name="setup_key" type="password" required maxlength="64" autocomplete="off"></label>
                <label>Administrator name<input name="name" required maxlength="160" autocomplete="name"></label>
                <label>Administrator email<input name="email" type="email" required maxlength="254" autocomplete="email"></label>
                <label>Password<input name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password"></label>
                <button class="button">Create administrator</button>
            </form>
        <?php endif; ?>
        </section>
        <?php
    }, $error, true);
}

function login_page(): void
{
    $error = '';
    if (is_post()) {
        require_csrf();
        $email = mb_strtolower(mb_substr(input($_POST, 'email'), 0, 254));
        if (sign_in($email, raw_password('password'))) {
            redirect('/admin/');
        }
        $error = 'The email address or password is incorrect.';
        http_response_code(401);
    } elseif (staff_user()) {
        redirect('/admin/');
    }
    $notice = take_flash();
    admin_layout('Staff sign-in', null, static function (): void {
        ?>
        <section class="panel"><form method="post" action="/admin/login/" class="stack">
            <?= csrf_field() ?>
            <label>Email<input name="email" type="email" required autocomplete="username" maxlength="254"></label>
            <label>Password<input name="password" type="password" required autocomplete="current-password" maxlength="72"></label>
            <button class="button">Sign in</button>
            <a class="text-link" href="/admin/forgot/">Forgot password?</a>
        </form></section>
        <?php
    }, $error ?: $notice, $error !== '');
}

function reset_page(bool $request): void
{
    private_response();
    header('Referrer-Policy: no-referrer');
    $message = '';
    $failure = false;
    $token = mb_substr(input(is_post() ? $_POST : $_GET, 'token'), 0, 64);
    if (is_post()) {
        require_csrf();
        try {
            if ($request) {
                issue_reset(mb_strtolower(mb_substr(input($_POST, 'email'), 0, 254)));
                $message = 'If this account can receive reset emails, you’ll receive a link shortly.';
            } else {
                use_reset($token, raw_password('password'));
                flash('Your password has been changed. Sign in with your new password.');
                redirect('/admin/login/');
            }
        } catch (ValidationError $error) {
            $message = $error->getMessage();
            $failure = true;
            http_response_code(422);
        }
    }
    admin_layout($request ? 'Request a password reset' : 'Set a new password', null, static function () use ($request, $token): void {
        ?>
        <section class="panel"><form method="post" action="<?= $request ? '/admin/forgot/' : '/admin/reset/' ?>" class="stack">
            <?= csrf_field() ?>
            <?php if ($request): ?>
                <label>Staff email<input name="email" type="email" required autocomplete="username" maxlength="254"></label>
            <?php else: ?>
                <input name="token" type="hidden" value="<?= e($token) ?>">
                <label>New password<input name="password" type="password" required autocomplete="new-password" minlength="12" maxlength="72"></label>
            <?php endif; ?>
            <button class="button"><?= $request ? 'Send reset link' : 'Change password' ?></button>
            <a class="text-link" href="/admin/login/">Back to sign-in</a>
        </form></section>
        <?php
    }, $message, $failure);
}

function dashboard(array $user): void
{
    $clauses = 's.archived_at IS NULL AND s.deleted_at IS NULL';
    $params = [];
    if ($user['role'] === 'agent') {
        $clauses .= ' AND s.agent_id = ?';
        $params[] = $user['id'];
    }
    $cutoff = (new DateTimeImmutable('today', site_zone()))->format('Y-m-d');
    $numbers = row("SELECT COUNT(*) AS total,SUM(t.bucket = 'transit') AS transit,SUM(t.bucket = 'delivered') AS delivered,
        SUM(t.bucket = 'pending') AS pending,SUM(t.bucket = 'awaiting') AS awaiting,
        SUM(t.bucket NOT IN ('delivered','cancelled') AND s.estimated_delivery < ?) AS overdue_count
        FROM shipments s JOIN statuses t ON s.status_id = t.id WHERE $clauses", [$cutoff, ...$params]);
    $recent = query("SELECT s.id,s.tracking_number,s.origin,s.destination,t.label AS status_label,t.bucket,s.updated_at AS activity
        FROM shipments s JOIN statuses t ON s.status_id = t.id WHERE $clauses ORDER BY s.updated_at DESC,s.id DESC LIMIT 12", $params)->fetchAll();
    admin_layout('Shipment overview', $user, static function () use ($numbers, $recent, $user): void {
        ?>
        <div class="admin-toolbar"><a class="button secondary" href="/admin/shipments/">Browse shipments</a>
            <?php if (can($user, 'manage-shipments')): ?><a class="button" href="/admin/shipments/new/">Create shipment</a><?php endif; ?>
        </div>
        <dl class="stats">
        <?php foreach (['total' => 'Total active shipments', 'transit' => 'In transit', 'delivered' => 'Delivered', 'pending' => 'Pending', 'awaiting' => 'Awaiting dispatch', 'overdue_count' => 'Delayed'] as $key => $label): ?>
            <div><dt><?= e($label) ?></dt><dd><?= e((int) $numbers[$key]) ?></dd></div>
        <?php endforeach; ?>
        </dl>
        <section class="panel"><h2>Recently updated shipments</h2><?php shipment_table($recent); ?></section>
        <?php
    });
}

function admin_route(string $path): void
{
    private_response();
    if ($path === '/admin/login/') {
        login_page();
        return;
    }
    if (in_array($path, ['/admin/forgot/', '/admin/reset/'], true)) {
        reset_page($path === '/admin/forgot/');
        return;
    }
    $user = require_staff();
    if (is_post()) {
        require_csrf();
    }
    if ($path === '/admin/logout/') {
        if (!is_post()) {
            throw new HttpError(405, 'Use the sign out button.');
        }
        sign_out($user);
    }
    if ($path === '/admin/' && !is_post()) {
        dashboard($user);
        return;
    }
    if (str_starts_with($path, '/admin/shipments/')) {
        admin_shipments_route($user, $path);
        return;
    }
    if (str_starts_with($path, '/admin/geocode/') && is_post()) {
        geocode_route($user);
        return;
    }
    admin_management_route($user, $path);
}
