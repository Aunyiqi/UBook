<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// ========== YOUR GMAIL SMTP CONFIGURATION ==========
define('MAIL_SMTP_HOST', 'smtp.gmail.com');
define('MAIL_SMTP_PORT', 587);
define('MAIL_SMTP_SECURE', 'tls');
define('MAIL_SMTP_USER', 'aunyiqi168@gmail.com');
define('MAIL_SMTP_PASS', '');  // Your Gmail app password
define('MAIL_FROM_EMAIL', 'aunyiqi168@gmail.com');
define('MAIL_FROM_NAME', 'UBook Campus Venues');
// ===================================================

// ========== MAIN RECIPIENT (REAL USER) ==========
$to_email = 'aunyiqi168@gmail.com';   // <-- CHANGE to your actual customer
$to_name  = 'Customer';
// ===================================================

// ---------- Helper function to send one email ----------
function sendEmail($to, $toName, $subject, $htmlBody, $textBody) {
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host       = MAIL_SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_SMTP_USER;
        $mail->Password   = MAIL_SMTP_PASS;
        $mail->SMTPSecure = MAIL_SMTP_SECURE;
        $mail->Port       = MAIL_SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Fix for localhost SSL (XAMPP/WAMP)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
        
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($to, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        return $mail->ErrorInfo;
    }
}

// ----- EMAIL 1: Main recipient gets a standard confirmation -----
$mainSubject = 'UBook Booking Confirmation – Your booking is confirmed';
$mainHtml = '<h2>✅ Booking Confirmed</h2>
             <p>Dear ' . htmlspecialchars($to_name) . ',</p>
             <p>Your campus venue booking has been successfully confirmed.</p>
             <p><strong>Details:</strong> This is a test message.</p>
             <p>Thank you for using UBook!</p>';
$mainText = "Booking Confirmed\n\nDear {$to_name},\nYour campus venue booking has been confirmed.\nThank you.";

// ----- EMAIL 2: Approver (you) gets a PENDING APPROVAL message -----
$approverSubject = '⚠️ PENDING APPROVAL – UBook Booking Requires Your Review';
$approverHtml = '<h2>📋 Booking Pending Your Approval</h2>
                 <p><strong style="color:red;">Status: WAITING – YOUR PENDING</strong></p>
                 <p>Dear Approver,</p>
                 <p>A new booking has been submitted and is waiting for your approval.</p>
                 <hr>
                 <h3>Booking Details:</h3>
                 <p><strong>Customer:</strong> ' . htmlspecialchars($to_email) . ' (' . htmlspecialchars($to_name) . ')</p>
                 <p><strong>Venue:</strong> Main Hall</p>
                 <p><strong>Date:</strong> ' . date('Y-m-d') . '</p>
                 <p><strong>Time:</strong> 10:00 AM – 12:00 PM</p>
                 <p><strong>Status:</strong> <span style="background:orange;color:white;padding:2px 6px;">Pending your action</span></p>
                 <hr>
                 <p><em>Please review and approve/reject this booking.</em></p>';
$approverText = "PENDING APPROVAL\n\nCustomer: {$to_email}\nVenue: Main Hall\nDate: " . date('Y-m-d') . "\nTime: 10:00-12:00\nStatus: Waiting for your pending action.\nPlease review.";

// --- Send both ---
$mainResult = sendEmail($to_email, $to_name, $mainSubject, $mainHtml, $mainText);
$approverResult = sendEmail('aunyiqi168@gmail.com', 'Approver', $approverSubject, $approverHtml, $approverText);

// --- Report ---
if ($mainResult === true && $approverResult === true) {
    echo "✅ Both emails sent successfully!\n";
    echo "   • Main recipient (customer): {$to_email}\n";
    echo "   • Approver (you): aunyiqi168@gmail.com – with PENDING APPROVAL message";
} else {
    echo "❌ One or both emails failed:\n";
    if ($mainResult !== true) echo "   • Customer email error: " . $mainResult . "\n";
    if ($approverResult !== true) echo "   • Approver email error: " . $approverResult . "\n";
}
?>