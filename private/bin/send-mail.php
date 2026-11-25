<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/app/core.php';
require PRIVATE_DIR . '/app/notifications.php';

if (!installed()) {
    fwrite(STDERR, "Install Afrilink before running mail delivery.\n");
    exit(1);
}
$lockName = 'afrilink-mail-' . substr(hash('sha256', config()['db_name']), 0, 36);
if ((int) row('SELECT GET_LOCK(?,0) AS acquired', [$lockName])['acquired'] !== 1) {
    exit;
}
try {
    query('DELETE FROM rate_limits WHERE expires_at < ? LIMIT 1000', [time()]);
    query('DELETE FROM password_resets WHERE expires_at < UTC_TIMESTAMP(6) LIMIT 1000');
    query('DELETE FROM geocode_cache WHERE expires_at < UTC_TIMESTAMP(6) LIMIT 1000');
    query('DELETE FROM notification_queue WHERE sent_at < UTC_TIMESTAMP(6) - INTERVAL 7 DAY LIMIT 1000');
    query("UPDATE notification_queue SET failed_at = UTC_TIMESTAMP(6), body = '' WHERE subject = 'Reset your Afrilink staff password'
        AND sent_at IS NULL AND failed_at IS NULL AND available_at <= UTC_TIMESTAMP(6) - INTERVAL 1 HOUR");
    if (!smtp_ready()) {
        exit;
    }
    $due = query('SELECT id,recipient,reply_to,subject,body,attempts FROM notification_queue
        WHERE sent_at IS NULL AND failed_at IS NULL AND available_at <= UTC_TIMESTAMP(6)
        ORDER BY available_at,id LIMIT 12')->fetchAll();
    $stop = time() + 45;
    foreach ($due as $email) {
        if (time() >= $stop) {
            break;
        }
        try {
            $mailer = mailer();
            $mailer->addAddress($email['recipient']);
            if ($email['reply_to'] !== '') {
                $mailer->addReplyTo($email['reply_to']);
            }
            $mailer->Subject = $email['subject'];
            $mailer->Body = $email['body'];
            $mailer->send();
            query("UPDATE notification_queue SET sent_at = ?, body = '' WHERE id = ?", [now_utc(), $email['id']]);
        } catch (Throwable $error) {
            $attempts = (int) $email['attempts'] + 1;
            $delayMinutes = 5 * (2 ** min($attempts - 1, 5));
            $retry = gmdate('Y-m-d H:i:s', time() + 60 * $delayMinutes);
            query('UPDATE notification_queue SET attempts = ?, available_at = ?, failed_at = ? WHERE id = ?',
                [$attempts, $retry, $attempts >= 5 ? now_utc() : null, $email['id']]);
            fwrite(STDERR, 'Delivery attempt failed for queue item ' . $email['id'] . ".\n");
        }
    }
} finally {
    query('SELECT RELEASE_LOCK(?)', [$lockName]);
}
