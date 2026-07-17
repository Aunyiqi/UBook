<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;


function ubook_resolve_notify_email(array $input, ?PDO $pdo, int $userId): ?string {
    $raw = trim((string) ($input['notify_email'] ?? ''));
    if ($raw !== '' && filter_var($raw, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['user_email'] = $raw;
        return $raw;
    }
    $sess = trim((string) ($_SESSION['user_email'] ?? ''));
    if ($sess !== '' && filter_var($sess, FILTER_VALIDATE_EMAIL)) {
        return $sess;
    }
    if ($pdo !== null && $userId > 0) {
        $st = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
        $st->execute([$userId]);
        $em = $st->fetchColumn();
        if ($em && filter_var((string) $em, FILTER_VALIDATE_EMAIL)) {
            return (string) $em;
        }
    }
    return null;
}

/**
 * @param array<int, array{venue:string,date:string,time:string,duration:int,comment:string}> $items
 */
function ubook_send_booking_confirmation(?string $to, string $studentName, array $items): bool {
    if (!$to || !defined('MAIL_ENABLED') || !MAIL_ENABLED || !class_exists(PHPMailer::class)) {
        return false;
    }
    if ($items === []) {
        return false;
    }

    $subject = 'UBook — booking request received (pending approval)';
    $lines = [];
    $htmlRows = '';
    foreach ($items as $row) {
        $v = htmlspecialchars($row['venue'], ENT_QUOTES, 'UTF-8');
        $d = htmlspecialchars($row['date'], ENT_QUOTES, 'UTF-8');
        $t = htmlspecialchars($row['time'], ENT_QUOTES, 'UTF-8');
        $dur = (int) $row['duration'];
        $c = htmlspecialchars($row['comment'] ?? '', ENT_QUOTES, 'UTF-8');
        $lines[] = "- {$row['venue']} | {$row['date']} {$row['time']} | {$dur}h" . ($c !== '' ? " | Note: {$row['comment']}" : '');
        $htmlRows .= '<tr><td style="padding:8px;border:1px solid #eee;">' . $v . '</td><td style="padding:8px;border:1px solid #eee;">' . $d . '</td><td style="padding:8px;border:1px solid #eee;">' . $t . '</td><td style="padding:8px;border:1px solid #eee;">' . $dur . 'h</td><td style="padding:8px;border:1px solid #eee;">' . ($c !== '' ? $c : '—') . '</td></tr>';
    }
    $name = htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8');
    $bodyText = "Hi {$studentName},\n\nYour booking request was submitted and is pending approval.\n\n" . implode("\n", $lines) . "\n\n— UBook";

    $bodyHtml = '<p>Hi ' . $name . ',</p><p>Your booking request was submitted and is <strong>pending approval</strong>.</p>'
        . '<table style="border-collapse:collapse;width:100%;max-width:640px;"><thead><tr>'
        . '<th style="padding:8px;border:1px solid #eee;text-align:left;">Venue</th>'
        . '<th style="padding:8px;border:1px solid #eee;text-align:left;">Date</th>'
        . '<th style="padding:8px;border:1px solid #eee;text-align:left;">Start</th>'
        . '<th style="padding:8px;border:1px solid #eee;text-align:left;">Hours</th>'
        . '<th style="padding:8px;border:1px solid #eee;text-align:left;">Note</th>'
        . '</tr></thead><tbody>' . $htmlRows . '</tbody></table><p>— UBook</p>';

    $mail = new PHPMailer(true);
    try {
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = MAIL_SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_SMTP_USER;
        $mail->Password = MAIL_SMTP_PASS;
        $mail->Port = (int) MAIL_SMTP_PORT;
        $sec = defined('MAIL_SMTP_SECURE') ? MAIL_SMTP_SECURE : 'tls';
        if ($sec === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($sec === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
        }

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $bodyHtml;
        $mail->AltBody = $bodyText;
        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('UBook mailer: ' . $e->getMessage());
        return false;
    }
}
