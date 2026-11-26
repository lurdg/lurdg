<?php
declare(strict_types=1);

function map_view(string $name, string|float $latitude, string|float $longitude): void
{
    $url = 'https://www.openstreetmap.org/?' . http_build_query(['mlat' => $latitude, 'mlon' => $longitude])
        . '#map=12/' . rawurlencode((string) $latitude) . '/' . rawurlencode((string) $longitude);
    ?>
    <div class="map" aria-label="<?= e('Map showing ' . $name) ?>" role="region"
        data-latitude="<?= e($latitude) ?>" data-longitude="<?= e($longitude) ?>" data-location="<?= e($name) ?>"></div>
    <a class="text-link map-fallback" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer">Open location in OpenStreetMap</a>
    <?php
}

function tracking_page(): void
{
    if (is_post()) {
        private_response();
    } else {
        header('Cache-Control: no-store, private');
    }
    $number = '';
    $shipment = null;
    $message = '';
    if (is_post()) {
        require_csrf();
        rate_limit('track-ip', client_ip(), 15, 60);
        $number = input($_POST, 'tracking_number');
        try {
            $number = value($_POST, 'tracking_number', 96, true);
            if (!preg_match('/\A[\p{L}\p{N}][\p{L}\p{N} ._\/-]*\z/u', $number) || input($_POST, 'office_fax') !== '') {
                throw new ValidationError('Enter the tracking number exactly as provided.');
            }
            $historyPage = max(1, min(10000, (int) input($_POST, 'history_page')));
            $shipment = public_shipment($number, $historyPage);
            if ($shipment === null) {
                http_response_code(404);
                $message = 'We couldn’t find that shipment. Check your tracking number and try again.';
            }
        } catch (ValidationError $error) {
            http_response_code(422);
            $message = $error->getMessage();
            $number = mb_substr($number, 0, 96);
        }
    }
    public_layout('Track a shipment', 'Check your shipment’s current status and recorded updates with your Afrilink tracking number.',
        static function () use ($number, $shipment, $message): void {
        page_hero('Shipment tracking', 'Track a shipment', 'Enter the tracking number Afrilink provided for your shipment.', 'container');
        ?>
        <div class="section wrap tracking-screen">
            <section class="panel lookup-panel" aria-labelledby="lookup-title">
                <h2 id="lookup-title">Shipment lookup</h2>
                <?php if ($message !== ''): ?><p class="notice error" role="alert"><?= e($message) ?></p><?php endif; ?>
                <?php tracking_form($number); ?>
            </section>
            <?php if ($shipment !== null): shipment_result($shipment); endif; ?>
        </div>
        <?php
    }, $shipment !== null && $shipment['location'] !== null, is_post());
}

function shipment_result(array $shipment): void
{
    $facts = [
        'Origin' => $shipment['origin'], 'Destination' => $shipment['destination'],
        'Shipment created' => show_time($shipment['created_at'], 'j M Y'),
        'Estimated delivery' => date_label($shipment['estimated_delivery']),
        'Sender' => $shipment['shipper_name'], 'Sender address' => $shipment['shipper_address'],
        'Receiver' => $shipment['receiver_name'], 'Receiver address' => $shipment['receiver_address'],
        'Shipment category' => $shipment['category'], 'Shipping method' => $shipment['method'],
        'Weight' => $shipment['weight'] === null ? '' : rtrim(rtrim((string) $shipment['weight'], '0'), '.') . ' kg',
        'Packages' => $shipment['packages'] === null ? '' : (string) $shipment['packages'],
    ];
    ?>
    <section class="panel" aria-labelledby="shipment-result">
        <div class="result-title"><div><p class="eyebrow accent">Tracking number</p><h2 id="shipment-result"><?= e($shipment['tracking_number']) ?></h2></div>
            <span class="badge <?= e($shipment['bucket']) ?>"><?= e($shipment['status_label']) ?></span></div>
        <dl class="facts">
        <?php foreach ($facts as $label => $detail): ?>
            <?php if ($detail !== ''): ?><div><dt><?= e($label) ?></dt><dd class="preserve-lines"><?= e($detail) ?></dd></div><?php endif; ?>
        <?php endforeach; ?>
        </dl>
        <?php if ($shipment['public_notes'] !== ''): ?><div class="preserve-lines notice info"><?= e($shipment['public_notes']) ?></div><?php endif; ?>
    </section>
    <section class="panel" aria-labelledby="location-result">
        <h2 id="location-result">Last recorded location</h2>
        <?php $place = $shipment['location']; if ($place === null): ?>
            <p class="muted">A location has not yet been recorded for this shipment.</p>
        <?php else: ?>
            <p><strong><?= e($place['location'] ?: 'Recorded coordinates') ?></strong><br>
                <time class="muted" datetime="<?= e(iso_datetime($place['occurred_at'])) ?>"><?= e(show_time($place['occurred_at'])) ?></time>
            </p>
            <?php map_view($place['location'] ?: 'Recorded coordinates', $place['latitude'], $place['longitude']); ?>
        <?php endif; ?>
    </section>
    <section class="panel" aria-labelledby="tracking-history"><h2 id="tracking-history">Shipment progress</h2>
        <?php if ($shipment['events'] === []): ?><p>Tracking updates will appear as they are recorded.</p><?php else: ?>
            <ol class="timeline">
            <?php foreach ($shipment['events'] as $event): ?>
                <li><strong><?= e($event['kind'] === 'status' ? $event['status_label'] : 'Location recorded') ?></strong>
                    <time datetime="<?= e(iso_datetime($event['occurred_at'])) ?>"><?= e(show_time($event['occurred_at'])) ?></time>
                    <?php if ($event['location'] !== ''): ?><p><?= e($event['location']) ?></p><?php endif; ?>
                    <?php if ($event['customer_remarks'] !== ''): ?><p class="preserve-lines"><?= e($event['customer_remarks']) ?></p><?php endif; ?>
                </li>
            <?php endforeach; ?>
            </ol>
        <?php endif; ?>
        <?php if ($shipment['history_page'] > 1 || $shipment['older_events']): ?>
            <form class="actions" method="post" action="/track/">
                <?= csrf_field() ?>
                <input type="hidden" name="tracking_number" value="<?= e($shipment['tracking_number']) ?>">
                <?php if ($shipment['history_page'] > 1): ?><button class="button secondary" name="history_page" value="<?= e($shipment['history_page'] - 1) ?>">Newer updates</button><?php endif; ?>
                <?php if ($shipment['older_events']): ?><button class="button secondary" name="history_page" value="<?= e($shipment['history_page'] + 1) ?>">Older updates</button><?php endif; ?>
            </form>
        <?php endif; ?>
    </section>
    <?php
}

function enquiry_page(string $kind): void
{
    header('Cache-Control: no-store, private');
    $quote = $kind === 'quote';
    $url = $quote ? '/quote/' : '/contact/';
    $title = $quote ? 'Request a quote' : 'Contact Afrilink';
    $error = '';
    $form = is_post() ? $_POST : [];
    if (is_post()) {
        require_csrf();
        rate_limit('enquiry', client_ip(), 8, 3600);
        try {
            if (input($_POST, 'office_fax') !== '') {
                throw new ValidationError('Please check the form and try again.');
            }
            $name = value($_POST, 'name', 160, true);
            $email = email_value($_POST, 'email', true);
            $subject = $quote ? 'Shipment quote request' : value($_POST, 'subject', 160);
            $origin = $quote ? value($_POST, 'origin', 100, true) : '';
            $destination = $quote ? value($_POST, 'destination', 100, true) : '';
            $method = $quote ? value($_POST, 'method', 90, true) : '';
            if ($quote && !array_key_exists($method, services()) && $method !== 'unsure') {
                throw new ValidationError('Select a shipping service.');
            }
            $method = services()[$method][0] ?? ($method === 'unsure' ? 'Advice requested' : '');
            $message = value($_POST, 'message', 4000, true);
            transaction(static function () use ($kind, $name, $email, $subject, $origin, $destination, $method, $message): void {
                query('INSERT INTO enquiries (kind,name,email,subject,origin,destination,method,message,created_at) VALUES (?,?,?,?,?,?,?,?,?)',
                    [$kind, $name, $email, $subject, $origin, $destination, $method, $message, now_utc()]);
                $route = $kind === 'quote' ? "\nRoute: $origin to $destination\nService: $method" : '';
                enqueue_email(setting('email'), 'New Afrilink ' . ($kind === 'quote' ? 'quote request' : 'contact enquiry'),
                    "$name\n$email\n$subject" . $route . "\n\n$message", $email);
            });
            flash($quote ? 'Your quote request has been received.' : 'Your message has been received.');
            redirect($url);
        } catch (ValidationError $validation) {
            $error = $validation->getMessage();
            http_response_code(422);
        }
    }
    $success = take_flash();
    $map = !$quote && setting('office_latitude') !== '' && setting('office_longitude') !== '';
    public_layout($title, $quote ? 'Request a shipping or warehousing quote from Afrilink Global Logistics.' : 'Contact Afrilink Global Logistics in Freetown, Sierra Leone.',
        static function () use ($quote, $title, $url, $map, $form, $error, $success): void {
        page_hero($quote ? 'Shipping enquiry' : 'Get in touch', $title, $quote
            ? 'Share your route and cargo details so our team can advise on the next steps.'
            : 'Talk to us about freight, warehousing or an existing shipment.', 'terminal');
        ?>
        <div class="section wrap form-layout">
            <section class="panel">
                <h2><?= $quote ? 'Shipment details' : 'Send a message' ?></h2>
                <?php if ($success !== ''): ?><p role="status" class="notice"><?= e($success) ?></p><?php endif; ?>
                <?php if ($error !== ''): ?><p role="alert" class="notice error"><?= e($error) ?></p><?php endif; ?>
                <form method="post" action="<?= e($url) ?>" class="stack" data-loading>
                    <?= csrf_field(), anti_spam() ?>
                    <div class="fields-grid">
                        <label>Name<input name="name" required maxlength="160" autocomplete="name" value="<?= e(input($form, 'name')) ?>"></label>
                        <label>Email<input type="email" name="email" required maxlength="254" autocomplete="email" value="<?= e(input($form, 'email')) ?>"></label>
                        <?php if ($quote): ?>
                            <label>Origin<input name="origin" required maxlength="100" value="<?= e(input($form, 'origin')) ?>"></label>
                            <label>Destination<input name="destination" required maxlength="100" value="<?= e(input($form, 'destination')) ?>"></label>
                            <label class="wide">Service<select name="method" required><option value="">Choose a service</option>
                                <?php foreach (services() + ['unsure' => ['I’d like advice']] as $key => [$label]): ?>
                                    <option value="<?= e($key) ?>"<?= $key === input($form, 'method') ? ' selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select></label>
                        <?php else: ?>
                            <label class="wide">Subject<input name="subject" maxlength="160" value="<?= e(input($form, 'subject')) ?>"></label>
                        <?php endif; ?>
                        <label class="wide"><?= $quote ? 'Cargo and handling requirements' : 'Message' ?><textarea name="message" maxlength="4000" required><?= e(input($form, 'message')) ?></textarea></label>
                    </div>
                    <button class="button" type="submit" data-loading-label="Sending"><?= $quote ? 'Send quote request' : 'Send message' ?></button>
                </form>
            </section>
            <aside class="panel">
                <h2>Freetown office</h2>
                <ul class="contact-list">
                    <li><?= icon('pin', 19) ?> <?= e(setting('office')) ?></li>
                    <li><?= icon('phone', 19) ?> <a href="<?= e(tel_url()) ?>"><?= e(setting('phone')) ?></a></li>
                    <li><?= icon('mail', 19) ?> <a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></li>
                    <?php if (setting('hours') !== ''): ?><li><?= icon('clock', 19) ?> <span class="preserve-lines"><?= e(setting('hours')) ?></span></li><?php endif; ?>
                    <?php if (setting('whatsapp') !== ''): ?><li><?= icon('whatsapp', 19) ?> <a href="<?= e(whatsapp_url()) ?>" target="_blank" rel="noopener noreferrer">Chat on WhatsApp</a></li><?php endif; ?>
                </ul>
                <?php if ($map): map_view(setting('office'), setting('office_latitude'), setting('office_longitude')); endif; ?>
            </aside>
        </div>
        <?php
    }, $map);
}

function policy_page(string $title, string $key): void
{
    if (setting($key) === '') {
        throw new HttpError(404, 'This page is not available.');
    }
    public_layout($title, $title . ' for Afrilink Global Logistics.', static function () use ($title, $key): void {
        page_hero('Afrilink Global Logistics', $title, '');
        ?><section class="section wrap narrow"><div class="preserve-lines"><?= e(setting($key)) ?></div></section><?php
    });
}

function sitemap(): never
{
    header('Content-Type: application/xml; charset=UTF-8');
    $urls = ['/', '/about/', '/services/', '/service-area/', '/contact/', '/quote/', '/track/'];
    foreach (array_keys(services()) as $service) {
        $urls[] = "/services/$service/";
    }
    foreach (policies() as $url => [$title, $setting]) {
        if (setting($setting) !== '') {
            $urls[] = $url;
        }
    }
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($urls as $url) {
        echo '<url><loc>' . htmlspecialchars(base_url() . $url, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc></url>';
    }
    echo '</urlset>';
    exit;
}
