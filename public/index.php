<?php
declare(strict_types=1);

$private = dirname(__DIR__) . '/afrilink-private';
if (!is_file($private . '/app/core.php')) {
    $private = dirname(__DIR__) . '/private';
}
if (!is_file($private . '/app/core.php')) {
    http_response_code(503);
    exit('Afrilink setup requires the private application directory.');
}
require $private . '/app/core.php';
foreach (['views', 'auth', 'notifications', 'shipments', 'public', 'admin', 'admin-shipments', 'admin-management'] as $module) {
    require PRIVATE_DIR . '/app/' . $module . '.php';
}

$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/' . trim($rawPath, '/');
if ($path !== '/' && !str_contains(basename($path), '.')) {
    $path .= '/';
}

ob_start();
secure_headers();
try {
    if (is_file(PRIVATE_DIR . '/config.php') && !config()['allow_http'] && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
        if (is_post()) {
            throw new HttpError(400, 'Open the HTTPS site and submit the form again.');
        }
        redirect(base_url() . $path, 308);
    }
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD', 'POST'], true)) {
        header('Allow: GET, HEAD, POST');
        throw new HttpError(405, 'This request method is unavailable.');
    }
    if ($path === '/robots.txt') {
        header('Content-Type: text/plain; charset=UTF-8');
        echo "User-agent: *\nDisallow: /admin/\nDisallow: /setup/\nSitemap: " . base_url() . "/sitemap.xml\n";
        exit;
    }
    if ($path === '/sitemap.xml') {
        sitemap();
    }
    if ($path === '/setup/') {
        setup_page();
        exit;
    }
    if ($path === '/track-form/') {
        redirect('/track/', is_post() ? 307 : 301);
    }
    if (str_starts_with($path, '/admin/')) {
        if (!installed()) {
            redirect('/setup/');
        }
        admin_route($path);
        exit;
    }
    if (is_post() && !in_array($path, ['/track/','/contact/','/quote/'], true)) {
        header('Allow: GET, HEAD');
        throw new HttpError(405, 'This request method is unavailable.');
    }
    if (strlen($path) <= 180 && preg_match('~\A/services/([a-z-]+)/\z~', $path, $parts)) {
        services_page($parts[1]);
        exit;
    }
    if (isset(policies()[$path])) {
        policy_page(...policies()[$path]);
        exit;
    }
    if (in_array($path, ['/track/','/contact/','/quote/'], true) && !installed()) {
        throw new HttpError(503, 'This service will be available after Afrilink setup is complete.');
    }
    match ($path) {
        '/' => home_page(), '/about/' => about_page(), '/services/' => services_page(),
        '/service-area/' => coverage_page(), '/contact/' => enquiry_page('contact'),
        '/quote/' => enquiry_page('quote'), '/track/' => tracking_page(),
        default => throw new HttpError(404, 'The page you requested could not be found.'),
    };
} catch (HttpError | ValidationError $error) {
    if (ob_get_level() > 0) { ob_clean(); }
    http_response_code($error instanceof HttpError ? $error->status : 422);
    error_page($error->getMessage());
} catch (Throwable $error) {
    if (ob_get_level() > 0) { ob_clean(); }
    error_log('Afrilink request failed: ' . $error::class . ' ' . $error->getCode()
        . ' in ' . basename($error->getFile()) . ':' . $error->getLine());
    http_response_code(503);
    private_response();
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Afrilink Global Logistics</title>'
        . '<h1>Service temporarily unavailable</h1><p>Try again shortly or contact Afrilink Global Logistics.</p></html>';
}
