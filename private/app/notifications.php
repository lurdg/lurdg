<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

function smtp_ready(): bool
{
    $smtp = config()['smtp'];
    return $smtp['enabled'] && $smtp['host'] !== '' && (int) $smtp['port'] > 0
        && in_array($smtp['encryption'], ['tls', 'ssl'], true) && $smtp['username'] !== ''
        && $smtp['password'] !== '' && filter_var($smtp['from_email'], FILTER_VALIDATE_EMAIL) !== false;
}

function enqueue_email(string $to, string $subject, string $body, string $replyTo = '', ?int $shipment = null): void
{
    if (!smtp_ready() || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    query('INSERT INTO notification_queue (shipment_id,recipient,reply_to,subject,body,available_at) VALUES (?,?,?,?,?,?)',
        [$shipment, $to, $replyTo, mb_substr($subject, 0, 200), $body, now_utc()]);
}

function notify_shipment(array $shipment, string $subject, string $message): void
{
    if (setting('notifications') !== '1' || !smtp_ready()) {
        return;
    }
    $recipients = [];
    foreach (['shipper_email', 'receiver_email'] as $column) {
        if (filter_var($shipment[$column], FILTER_VALIDATE_EMAIL)) {
            $recipients[strtolower($shipment[$column])] = $shipment[$column];
        }
    }
    foreach ($recipients as $recipient) {
        enqueue_email($recipient, $subject . ' | Afrilink Global Logistics',
            "Hello,\n\n" . $message . "\n\nTracking number: " . $shipment['tracking_number']
            . "\nTrack your shipment: " . base_url() . "/track/\n\nAfrilink Global Logistics\n" . setting('email'),
            '', (int) $shipment['id']);
    }
}

function mailer(): PHPMailer
{
    foreach (['Exception', 'PHPMailer', 'SMTP'] as $name) {
        require_once PRIVATE_DIR . '/vendor/PHPMailer/' . $name . '.php';
    }
    $smtp = config()['smtp'];
    $mailer = new PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $smtp['host'];
    $mailer->Port = (int) $smtp['port'];
    $mailer->SMTPAuth = true;
    $mailer->Username = $smtp['username'];
    $mailer->Password = $smtp['password'];
    $mailer->SMTPSecure = $smtp['encryption'];
    $mailer->Timeout = 8;
    $mailer->Timelimit = 12;
    $mailer->CharSet = 'UTF-8';
    $mailer->setFrom($smtp['from_email'], $smtp['from_name']);
    return $mailer;
}
