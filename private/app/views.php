<?php
declare(strict_types=1);

function icon(string $name, int $size = 24): string
{
    $paths = [
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7l.5 3a2 2 0 0 1-.5 2L7.8 10a16 16 0 0 0 6.2 6.2l1.3-1.3a2 2 0 0 1 2-.5l3 .5a2 2 0 0 1 1.7 2Z"/>',
        'pin' => '<path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6"/><path d="m15 15 5 5"/>',
        'box' => '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z M4 7.5l8 4.5 8-4.5M12 12v9M8 5.25l8 4.5"/>',
        'plane' => '<path d="M17 21v-8l4-3V7l-4 1V5c0-2-1-3-2-3s-2 1-2 3v4L3 12v3l10-1v4l-3 2v2l5-1 3 1v-2Z"/>',
        'ship' => '<path d="M4 13V7h16v6M8 7V3h8v4M12 11l9 4-3 5H6l-3-5 9-4Zm0 0v7M2 21c2 0 2-1 4-1s2 1 4 1 2-1 4-1 2 1 4 1 2-1 4-1"/>',
        'truck' => '<path d="M1 5h14v12H1zM15 9h4l4 4v4h-8"/><circle cx="5.5" cy="18.2" r="2.2" fill="white"/><circle cx="19" cy="18.2" r="2.2" fill="white"/>',
        'warehouse' => '<path d="M2 21V9l10-6 10 6v12H2ZM7 21v-9h10v9M7 15h10M7 18h10"/>',
        'check' => '<path d="m5 12 5 5L20 7"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'whatsapp' => '<path fill="currentColor" stroke="none" d="M17.47 14.38c-.3-.15-1.73-.85-2-.95-.27-.1-.47-.15-.66.15-.2.29-.76.95-.93 1.14-.17.2-.34.22-.63.07-.3-.15-1.24-.46-2.36-1.46a8.9 8.9 0 0 1-1.63-2.03c-.17-.3-.02-.45.13-.6.13-.13.3-.34.44-.51.15-.17.2-.3.3-.49.1-.2.05-.37-.02-.52-.08-.15-.66-1.6-.91-2.19-.24-.57-.48-.5-.66-.51h-.56c-.2 0-.51.07-.78.37-.27.29-1.03 1.01-1.03 2.46s1.05 2.85 1.2 3.04c.14.2 2.06 3.15 5 4.41.7.3 1.24.48 1.67.61.7.23 1.34.2 1.84.12.56-.09 1.73-.71 1.97-1.4.25-.69.25-1.28.17-1.4-.07-.12-.26-.2-.55-.34zM12 2a10 10 0 0 0-8.64 15.04L2 22l5.1-1.34A10 10 0 1 0 12 2zm0 18.3a8.3 8.3 0 0 1-4.23-1.16l-.3-.18-3.02.8.8-2.94-.2-.3A8.3 8.3 0 1 1 12 20.3z"/>',
    ];
    return '<svg class="icon" aria-hidden="true" width="' . $size . '" height="' . $size
        . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round">'
        . ($paths[$name] ?? '') . '</svg>';
}

function brand(): string
{
    return '<a class="brand" href="/" aria-label="Afrilink Global Logistics home">'
        . '<img src="/assets/afrilink.png" width="105" height="46" alt="Afrilink">'
        . '<span>GLOBAL<br>LOGISTICS</span></a>';
}

function tel_url(): string
{
    return 'tel:+' . preg_replace('/\D+/', '', setting('phone'));
}

function whatsapp_url(): string
{
    $url = 'https://wa.me/' . setting('whatsapp');
    return setting('whatsapp_greeting') === '' ? $url : $url . '?text=' . rawurlencode(setting('whatsapp_greeting'));
}

function public_layout(string $title, string $description, callable $body, bool $map = false, bool $noindex = false): void
{
    global $path;
    $canonical = base_url() . $path;
    $company = setting('company');
    $pageTitle = $path === '/' ? $company . ' | Shipping from Freetown' : $title . ' | ' . $company;
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta property="og:type" content="website"><meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($description) ?>"><meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:image" content="<?= e(base_url() . '/assets/hero.webp') ?>">
    <?php if ($noindex): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
    <link rel="icon" href="/assets/afrilink.png" type="image/png">
    <link rel="stylesheet" href="/assets/site.css">
    <?php if ($map): ?>
        <link rel="stylesheet" href="/assets/leaflet/leaflet.css">
        <script defer src="/assets/leaflet/leaflet.js"></script>
    <?php endif; ?>
    <script defer src="/assets/site.js"></script>
    <?php if ($path === '/'): ?>
        <script type="application/ld+json" nonce="<?= e(csp_nonce()) ?>"><?= json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $company,
            'url' => base_url(),
            'logo' => base_url() . '/assets/afrilink.png',
            'telephone' => setting('phone'),
            'email' => setting('email'),
            'address' => setting('office'),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <?php endif; ?>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<div class="utility"><div class="wrap utility-inner">
    <span><?= icon('pin', 15) ?> Freetown, Sierra Leone</span>
    <a href="<?= e(tel_url()) ?>"><?= icon('phone', 14) ?> <?= e(setting('phone')) ?></a>
</div></div>
<header class="site-header"><div class="wrap navigation">
    <?= brand() ?>
    <button class="nav-toggle" type="button" aria-controls="site-nav" aria-expanded="false" aria-label="Open navigation"><?= icon('menu') ?></button>
    <nav id="site-nav" aria-label="Main navigation">
        <?php foreach (['/' => 'Home', '/services/' => 'Services', '/about/' => 'About', '/track/' => 'Track shipment', '/contact/' => 'Contact'] as $url => $name): ?>
            <a href="<?= e($url) ?>"<?= ($url === $path || ($url === '/services/' && str_starts_with($path, $url))) ? ' aria-current="page"' : '' ?>><?= e($name) ?></a>
        <?php endforeach; ?>
        <a class="button compact" href="/quote/">Request a quote</a>
    </nav>
</div></header>
<main id="main"><?php $body(); ?></main>
<footer class="site-footer">
    <div class="wrap footer-grid">
        <div><?= brand() ?><p>Shipping and logistics from Freetown, Sierra Leone.</p></div>
        <div><h2>Explore</h2><a href="/services/">Services</a><a href="/about/">About Afrilink</a><a href="/service-area/">Service area</a><a href="/track/">Track shipment</a></div>
        <div><h2>Get in touch</h2><p><?= e(setting('office')) ?></p><a href="<?= e(tel_url()) ?>"><?= e(setting('phone')) ?></a><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a>
            <?php foreach (['instagram' => 'Instagram', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn'] as $key => $name): ?>
                <?php if (setting($key) !== ''): ?><a href="<?= e(setting($key)) ?>" rel="noopener noreferrer"><?= e($name) ?></a><?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="wrap footer-bottom"><span>&copy; <?= e(date('Y')) ?> <?= e($company) ?></span><span>
        <?php foreach (policies() as $url => [$heading, $key]): ?>
            <?php if (setting($key) !== ''): ?><a href="<?= e($url) ?>"><?= e($heading) ?></a><?php endif; ?>
        <?php endforeach; ?>
        <a href="/admin/">Staff sign-in</a>
    </span></div>
</footer>
<?php if (setting('whatsapp') !== ''): ?><a class="whatsapp" href="<?= e(whatsapp_url()) ?>" target="_blank" rel="noopener noreferrer" aria-label="Chat with Afrilink on WhatsApp"><?= icon('whatsapp', 29) ?></a><?php endif; ?>
</body></html>
<?php
}

function policies(): array
{
    return [
        '/privacy-policy/' => ['Privacy policy', 'privacy_policy'],
        '/terms-and-conditions/' => ['Terms and conditions', 'terms'],
        '/cookie-policy/' => ['Cookie policy', 'cookie_policy'],
    ];
}

function services(): array
{
    return [
        'air-freight' => ['Air freight', 'plane', 'air', 'Aircraft wing above the clouds',
            'Air transport for time-sensitive cargo.',
            'Speak to our team about air transport for your shipment. Share the cargo dimensions, weight, origin, destination and the intended arrival date so we can discuss the available options.'],
        'container-shipping' => ['Container shipping', 'ship', 'port', 'Container ship carrying stacked freight',
            'Containerised ocean transport for larger consignments.',
            'Afrilink arranges container handling and transport for sea freight. Tell us the type and size of the cargo, the route and any handling requirements when requesting a quote.'],
        'trucking' => ['Trucking', 'truck', 'truck', 'Freight truck on the road',
            'Road transport for cargo moving across Sierra Leone.',
            'Our road transport services support local cargo movement and delivery. Contact us with the pickup and delivery locations and any site access or handling requirements.'],
        'warehousing' => ['Warehousing', 'warehouse', 'warehouse', 'Storage and handling facility',
            'Storage and handling support for your cargo.',
            'Afrilink provides warehousing alongside its transport services. Ask our team about availability, expected storage duration, shipment volume and handling needs.'],
    ];
}

function service_cards(): void
{
    ?><div class="service-grid">
    <?php foreach (services() as $slug => [$title, $icon, $photo, $alt, $summary]): ?>
        <a class="service-card" href="/services/<?= e($slug) ?>/">
            <img src="/assets/<?= e($photo) ?>.webp" loading="lazy" width="400" height="245" alt="<?= e($alt) ?>">
            <div class="service-copy"><span class="service-icon"><?= icon($icon, 24) ?></span><h3><?= e($title) ?></h3><p><?= e($summary) ?></p><span class="text-link">Explore service</span></div>
        </a>
    <?php endforeach; ?>
    </div><?php
}

function tracking_form(string $number = '', bool $withCode = false): void
{
    ?>
    <form class="tracking-form" method="post" action="/track/" data-loading>
        <?= csrf_field(), anti_spam() ?>
        <div class="track-fields">
            <label for="tracking-number">Tracking number<input id="tracking-number" name="tracking_number" value="<?= e($number) ?>" placeholder="Enter your tracking number" maxlength="96" autocomplete="off" spellcheck="false" required></label>
            <button class="button" type="submit" data-loading-label="Checking shipment"><?= icon('search', 19) ?> Track shipment</button>
        </div>
        <?php if ($withCode): ?><label class="check-code" for="verification-code">Verification code <span>(optional)</span><input id="verification-code" name="verification_code" maxlength="64" autocomplete="off" spellcheck="false" placeholder="Enter code to view party names"></label><?php endif; ?>
    </form>
    <?php
}

function page_hero(string $tag, string $title, string $description, string $image = 'ocean'): void
{
    ?>
    <section class="page-hero"><img src="/assets/<?= e($image) ?>.webp" alt="" width="1250" height="650" fetchpriority="high"><div class="wrap">
        <p class="eyebrow"><?= e($tag) ?></p><h1><?= e($title) ?></h1><p><?= e($description) ?></p>
    </div></section>
    <?php
}

function home_page(): void
{
    public_layout('Home', 'Air freight, container shipping, trucking and warehousing from Freetown, Sierra Leone. Track a shipment or request a quote.', static function (): void {
        ?>
<section class="home-hero">
    <picture><source media="(max-width: 680px)" srcset="/assets/hero-small.webp"><img src="/assets/hero.webp" width="1700" height="1030" alt="" fetchpriority="high"></picture>
    <div class="wrap hero-inner">
        <p class="eyebrow"><?= icon('pin', 17) ?> Freetown, Sierra Leone</p>
        <h1>From Freetown<br>to the world<span>.</span></h1>
        <p class="hero-description">Air freight, container shipping, trucking and warehousing. Coordinate your cargo with the Afrilink team.</p>
        <div class="actions"><a class="button" href="/quote/">Request a quote</a><a class="button ghost" href="/services/">Explore our services</a></div>
    </div>
</section>
<div class="wrap tracking-dock">
    <section class="tracking-panel" aria-labelledby="track-home">
        <div class="tracking-title"><span class="track-icon"><?= icon('box', 26) ?></span><div><h2 id="track-home">Track a shipment</h2><p>Check your latest shipment update.</p></div></div>
        <?php if (installed()): tracking_form(); else: ?><a class="button" href="/track/">Open shipment tracking</a><?php endif; ?>
    </section>
</div>
<section class="section wrap" id="services">
    <div class="section-heading"><div><p class="eyebrow accent">Shipping services</p><h2>How we move your cargo</h2></div><a class="text-link" href="/services/">View all services</a></div>
    <?php service_cards(); ?>
</section>
<section class="about-band"><div class="wrap about-grid">
    <img src="/assets/ocean.webp" width="620" height="400" alt="Rows of cargo containers at a terminal" loading="lazy">
    <div>
        <p class="eyebrow accent">About Afrilink</p><h2>Local knowledge.<br>International reach.</h2>
        <p>Based in Freetown, Afrilink connects Sierra Leone with global markets through air and sea freight, road transport and warehousing.</p>
        <ul class="checks"><li><?= icon('check', 16) ?> Freetown-based logistics team</li><li><?= icon('check', 16) ?> Transport and storage support</li><li><?= icon('check', 16) ?> Recorded shipment updates online</li></ul>
        <a class="text-link" href="/about/">Get to know Afrilink</a>
    </div>
</div></section>
<section class="section wrap process"><p class="eyebrow accent">How it works</p><h2>Plan and follow your shipment</h2>
    <ol>
        <li><h3>Tell us what you’re shipping</h3><p>Share the route, shipment size and any handling requirements.</p></li>
        <li><h3>Coordinate the transport</h3><p>Work with our team on the shipping method and next steps.</p></li>
        <li><h3>Follow your cargo</h3><p>Use the tracking number provided to view recorded updates.</p></li>
    </ol>
</section>
<section class="contact-band"><div class="wrap"><div><h2>Planning a shipment?</h2><p>Talk to the team about your route and cargo.</p></div><a class="button" href="/quote/">Request a quote</a></div></section>
        <?php
    });
}

function about_page(): void
{
    public_layout('About', 'Meet Afrilink Global Logistics, a shipping and logistics company based in Freetown, Sierra Leone.', static function (): void {
        page_hero('About Afrilink', 'Shipping from Sierra Leone', 'We work with customers to move and store cargo through Freetown and beyond.', 'port');
        ?>
        <section class="section wrap about-grid">
            <div><p class="eyebrow accent">Our mission</p><h2>Connect Sierra Leone with global markets</h2>
            <p>From Freetown, we provide air freight, container shipping and road transport supported by warehousing. Our team coordinates transport and cargo handling around the needs of each shipment.</p>
            <p>Integrity, efficiency and innovation guide the way we work. Our aim is to give customers dependable support from the first enquiry through delivery.</p>
            <?php if (setting('company_history') !== ''): ?><h3>Our background</h3><p class="preserve-lines"><?= e(setting('company_history')) ?></p><?php endif; ?>
            <?php if (setting('company_vision') !== ''): ?><h3>Our vision</h3><p class="preserve-lines"><?= e(setting('company_vision')) ?></p><?php endif; ?>
            <a class="text-link" href="/service-area/">Explore our service area</a></div>
            <img src="/assets/ocean.webp" width="620" height="400" alt="Containers stacked in a freight terminal" loading="lazy">
        </section>
        <?php
    });
}

function services_page(?string $slug = null): void
{
    if ($slug === null) {
        public_layout('Services', 'Explore air freight, container shipping, trucking and warehousing from Afrilink Global Logistics.', static function (): void {
            page_hero('Our services', 'Freight and logistics services', 'Explore transport and storage options for your cargo.');
            ?><section class="section wrap"><?php service_cards(); ?></section><?php
        });
        return;
    }
    if (!isset(services()[$slug])) {
        throw new HttpError(404, 'This service could not be found.');
    }
    [$title, $icon, $photo, $alt, $summary, $details] = services()[$slug];
    public_layout($title, $summary, static function () use ($title, $summary, $photo, $details): void {
        page_hero('Our services', $title, $summary, $photo);
        ?><section class="section wrap narrow"><p class="lead"><?= e($details) ?></p><div class="actions"><a class="button" href="/quote/">Request a quote</a><a class="text-link" href="/services/">All services</a></div></section><?php
    });
}

function coverage_page(): void
{
    public_layout('Service area', 'Explore Afrilink’s shipping and logistics service coverage in Sierra Leone.', static function (): void {
        page_hero('Service area', 'Our logistics network', 'Afrilink supports cargo movement and storage across Sierra Leone.', 'terminal');
        ?><section class="section wrap coverage-grid">
        <article><h2>Freetown</h2><p>Our main hub supports air freight and container shipping.</p><a class="text-link" href="/contact/">Freetown office</a></article>
        <article><h2>Bo</h2><p>Our operations in Bo support road transport and warehousing.</p></article>
        <article><h2>Kenema</h2><p>Our Kenema operations support cargo handling and storage in eastern Sierra Leone.</p></article>
        </section><?php
    });
}
