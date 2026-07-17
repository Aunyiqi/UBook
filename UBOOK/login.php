<?php
// Set timezone to match your server (change if needed)
date_default_timezone_set('Asia/Kuala_Lumpur');

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

// SMTP configuration
define('MAIL_SMTP_HOST', 'smtp.gmail.com');
define('MAIL_SMTP_PORT', 587);
define('MAIL_SMTP_SECURE', 'tls');
define('MAIL_SMTP_USER', 'aunyiqi168@gmail.com');
define('MAIL_SMTP_PASS', '');
define('MAIL_FROM_EMAIL', 'aunyiqi168@gmail.com');
define('MAIL_FROM_NAME', 'UBook Campus Venues');

function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli("localhost", "root", "", "ubook");
        if ($conn->connect_error) {
            die(json_encode(['error' => "DB connection failed: " . $conn->connect_error]));
        }
        // Login attempts table
        $conn->query("
            CREATE TABLE IF NOT EXISTS login_attempts (
                username VARCHAR(100) PRIMARY KEY,
                attempts INT NOT NULL DEFAULT 0,
                last_attempt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                locked_until TIMESTAMP NULL DEFAULT NULL
            )
        ");
        
        // OTP table
        $result = $conn->query("SHOW TABLES LIKE 'password_reset_otp'");
        if ($result->num_rows == 0) {
            $conn->query("
                CREATE TABLE password_reset_otp (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(255) NOT NULL,
                    otp VARCHAR(6) NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL,
                    used BOOLEAN DEFAULT FALSE,
                    INDEX (email, otp)
                )
            ");
        } else {
            $colCheck = $conn->query("SHOW COLUMNS FROM password_reset_otp LIKE 'id'");
            if ($colCheck->num_rows == 0) {
                $conn->query("ALTER TABLE password_reset_otp ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST");
            }
        }
    }
    return $conn;
}

// ---------- Login attempt tracking (case‑insensitive, raw queries for reliability) ----------
function normalizeUsername(string $username): string {
    return strtolower(trim($username));
}

function check_login_lock(string $username): ?int {
    $username = normalizeUsername($username);
    $conn = getDB();
    $esc = $conn->real_escape_string($username);
    $result = $conn->query("SELECT locked_until FROM login_attempts WHERE username = '$esc'");
    if ($result && $row = $result->fetch_assoc()) {
        $lockedUntil = $row['locked_until'];
        if ($lockedUntil !== null) {
            $now = time();
            $lockTime = strtotime($lockedUntil);
            if ($now < $lockTime) {
                $minutes = ceil(($lockTime - $now) / 60);
                return max(1, $minutes);
            } else {
                // expired – delete the record
                $conn->query("DELETE FROM login_attempts WHERE username = '$esc'");
            }
        }
    }
    return null;
}

function record_failed_attempt(string $username): void {
    $username = normalizeUsername($username);
    $conn = getDB();
    $esc = $conn->real_escape_string($username);
    $result = $conn->query("SELECT attempts FROM login_attempts WHERE username = '$esc'");
    if ($result && $row = $result->fetch_assoc()) {
        $attempts = $row['attempts'] + 1;
        if ($attempts >= 3) {
            $lockedUntil = date('Y-m-d H:i:s', strtotime('+3 minutes'));
            $conn->query("UPDATE login_attempts SET attempts = $attempts, last_attempt = NOW(), locked_until = '$lockedUntil' WHERE username = '$esc'");
        } else {
            $conn->query("UPDATE login_attempts SET attempts = $attempts, last_attempt = NOW(), locked_until = NULL WHERE username = '$esc'");
        }
    } else {
        $conn->query("INSERT INTO login_attempts (username, attempts, last_attempt, locked_until) VALUES ('$esc', 1, NOW(), NULL)");
    }
}

function clear_login_attempts(string $username): void {
    $username = normalizeUsername($username);
    $conn = getDB();
    $esc = $conn->real_escape_string($username);
    $conn->query("DELETE FROM login_attempts WHERE username = '$esc'");
}

// ---------- Email helpers (unchanged) ----------
function sendMail($toEmail, $toName, $subject, $htmlBody, $altBody = '') {
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
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $altBody ?: strip_tags($htmlBody);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

function sendPasswordChangeNotification($toEmail, $toName) {
    $subject = 'Your UBook password has been changed';
    $html = "
        <h2>Password Changed Successfully</h2>
        <p>Dear {$toName},</p>
        <p>Your UBook account password was just changed.</p>
        <p>If you did this, you can ignore this message and continue using your account.</p>
        <p><strong>If you did NOT request this change, please contact UBook support immediately.</strong></p>
        <hr>
        <p>UBook Campus Venues</p>
    ";
    return sendMail($toEmail, $toName, $subject, $html);
}

function sendOtpEmail($toEmail, $toName, $otp) {
    $subject = 'Your OTP for password reset - UBook';
    $html = "
        <h2>Password Reset Request</h2>
        <p>Dear {$toName},</p>
        <p>You requested to reset your UBook password. Use the following One‑Time Password (OTP):</p>
        <h1 style='background:#f4f4f4; padding:10px; border-radius:8px; font-family: monospace;'>$otp</h1>
        <p>This OTP is valid for <strong>10 minutes</strong>. Enter it on the password reset page to set a new password.</p>
        <p>If you did not request this, please ignore this email.</p>
        <hr>
        <p>UBook Campus Venues</p>
    ";
    return sendMail($toEmail, $toName, $subject, $html);
}

// ---------- OTP helpers (unchanged) ----------
function generateOtp(): string {
    try {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}

function storeOtp(string $email, string $otp, int $ttlSeconds = 600): bool {
    $conn = getDB();
    $clean = $conn->prepare("DELETE FROM password_reset_otp WHERE email = ? AND used = FALSE");
    $clean->bind_param("s", $email);
    $clean->execute();
    
    $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
    $stmt = $conn->prepare("INSERT INTO password_reset_otp (email, otp, expires_at) VALUES (?, ?, ?)");
    if (!$stmt) {
        error_log("storeOtp prepare failed: " . $conn->error);
        return false;
    }
    $stmt->bind_param("sss", $email, $otp, $expiresAt);
    return $stmt->execute();
}

function verifyOtpDetailed(string $email, string $otp): array {
    $conn = getDB();
    $now = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("
        SELECT id, otp, expires_at, used 
        FROM password_reset_otp 
        WHERE email = ? AND used = FALSE
        ORDER BY created_at DESC
        LIMIT 1
    ");
    if (!$stmt) {
        return ['success' => false, 'message' => 'Invalid or expired OTP.'];
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if ($row['otp'] !== $otp) {
            return ['success' => false, 'message' => 'Invalid or expired OTP.'];
        }
        if ($row['expires_at'] <= $now) {
            return ['success' => false, 'message' => 'Invalid or expired OTP.'];
        }
        $upd = $conn->prepare("UPDATE password_reset_otp SET used = TRUE WHERE id = ?");
        $upd->bind_param("i", $row['id']);
        $upd->execute();
        return ['success' => true, 'message' => 'OTP verified'];
    } else {
        return ['success' => false, 'message' => 'Invalid or expired OTP.'];
    }
}

// ---------- Handle POST actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // --- verify_password ---
    if ($action === 'verify_password') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            echo json_encode(['error' => 'Please enter both username and password']);
            exit();
        }

        $lockMinutes = check_login_lock($username);
        if ($lockMinutes !== null) {
            echo json_encode(['error' => "Too many failed attempts. Please wait {$lockMinutes} minute(s) before trying again."]);
            exit();
        }

        $conn = getDB();
        $stmt = $conn->prepare("SELECT id, username, name, email, phone, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            record_failed_attempt($username);
            usleep(rand(200000, 500000));
            echo json_encode(['error' => 'Invalid username or password']);
            exit();
        }

        $user = $result->fetch_assoc();

        $passwordOk = password_verify($password, $user['password']);
        if (!$passwordOk && $user['password'] === $password && strpos($user['password'], '$2y$') !== 0) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->bind_param("si", $newHash, $user['id']);
            $upd->execute();
            $passwordOk = true;
        }

        if (!$passwordOk) {
            record_failed_attempt($username);
            echo json_encode(['error' => 'Invalid username or password']);
            exit();
        }

        // ========== AGGRESSIVE LOCKOUT CLEARING ==========
        clear_login_attempts($username);                // normalized submitted username
        clear_login_attempts($user['username']);        // stored username (will be normalized)
        // Extra safety: direct delete using the submitted raw username (trimmed but not lowercased)
        $raw = trim($_POST['username']);
        $conn->query("DELETE FROM login_attempts WHERE LOWER(username) = LOWER('" . $conn->real_escape_string($raw) . "')");
        
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['phone'] = $user['phone'];
        $_SESSION['role'] = $user['role'];

        $role = $user['role'];
        if ($role === 'super_admin') {
            $redirect = 'super_admin_main_menu.php';
        } elseif ($role === 'review_admin') {
            $redirect = 'review_admin_main_menu.php';
        } elseif ($role === 'booking_admin') {
            $redirect = 'booking_admin_main_menu.php';
        } else {
            $redirect = 'main.menu.php';
        }
        echo json_encode(['success' => true, 'redirect' => $redirect]);
        exit();
    }

    // --- request OTP (unchanged) ---
    if ($action === 'request_otp') {
        try {
            $email = trim($_POST['email'] ?? '');
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['error' => 'Please enter a valid email address.']);
                exit();
            }

            $conn = getDB();
            $userStmt = $conn->prepare("SELECT id, name, email FROM users WHERE email = ?");
            $userStmt->bind_param("s", $email);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            if ($userResult->num_rows === 0) {
                echo json_encode(['error' => 'No account found with that email address.']);
                exit();
            }
            $user = $userResult->fetch_assoc();

            $otp = generateOtp();
            if (storeOtp($email, $otp)) {
                $sent = sendOtpEmail($email, $user['name'], $otp);
                if ($sent) {
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_name'] = $user['name'];
                    echo json_encode(['success' => true, 'message' => 'OTP sent to your email.']);
                } else {
                    echo json_encode(['error' => 'Failed to send OTP email. Please try again later.']);
                }
            } else {
                echo json_encode(['error' => 'Could not store OTP. Please try again.']);
            }
        } catch (Throwable $e) {
            error_log("request_otp error: " . $e->getMessage());
            echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
        }
        exit();
    }

    // --- reset with OTP (unchanged) ---
    if ($action === 'reset_with_otp') {
        try {
            $email = trim($_POST['email'] ?? '');
            $otp = trim($_POST['otp'] ?? '');
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($email) || empty($otp)) {
                echo json_encode(['error' => 'Email and OTP are required.']);
                exit();
            }
            if (empty($newPassword) || empty($confirmPassword)) {
                echo json_encode(['error' => 'Please fill in both password fields.']);
                exit();
            }
            if ($newPassword !== $confirmPassword) {
                echo json_encode(['error' => 'Passwords do not match.']);
                exit();
            }
            if (strlen($newPassword) < 6) {
                echo json_encode(['error' => 'Password must be at least 6 characters.']);
                exit();
            }

            $verify = verifyOtpDetailed($email, $otp);
            if (!$verify['success']) {
                echo json_encode(['error' => $verify['message']]);
                exit();
            }

            $conn = getDB();
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            if (!$updateStmt) {
                echo json_encode(['error' => 'Database error.']);
                exit();
            }
            $updateStmt->bind_param("ss", $newHash, $email);
            if (!$updateStmt->execute()) {
                echo json_encode(['error' => 'Database error.']);
                exit();
            }

            if ($updateStmt->affected_rows === 0) {
                echo json_encode(['error' => 'User not found or password unchanged.']);
                exit();
            }

            // Clear login attempts for this user
            $userStmt = $conn->prepare("SELECT username FROM users WHERE email = ?");
            $userStmt->bind_param("s", $email);
            $userStmt->execute();
            $userRes = $userStmt->get_result();
            if ($userRow = $userRes->fetch_assoc()) {
                clear_login_attempts($userRow['username']);
            }

            $name = $_SESSION['reset_name'] ?? 'User';
            sendPasswordChangeNotification($email, $name);

            unset($_SESSION['reset_email'], $_SESSION['reset_name']);

            echo json_encode(['success' => true, 'message' => 'Password updated successfully. Please log in with your new password.']);
        } catch (Throwable $e) {
            error_log("reset_with_otp error: " . $e->getMessage());
            echo json_encode(['error' => 'Server error. Please try again later.']);
        }
        exit();
    }
}
?>
<!-- The HTML/JS section is identical to the original – no changes needed -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UBook · Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
        }
        body, html { height: 100%; overflow: hidden; }
        .video-bg {
            position: fixed;
            top: 0; left: 0;
            min-width: 100%; min-height: 100%;
            z-index: -1;
            object-fit: cover;
            filter: brightness(0.85) contrast(1.05);
        }
        .login-page {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
            padding: 40px 35px;
            border-radius: 28px;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.2);
            text-align: center;
            width: 450px;
            position: relative;
            transition: all 0.3s ease;
            border: 1px solid rgba(249,115,22,0.3);
            animation: fadeSlideUp 0.6s ease-out;
        }
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-container h2 {
            color: #f97316;
            margin-bottom: 25px;
            font-size: 32px;
            font-weight: 700;
        }
        .input-group {
            position: relative;
            margin: 25px 0;
        }
        .input-group i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #fb923c;
            font-size: 18px;
        }
        .login-container input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border-radius: 14px;
            border: 1.5px solid #ffe0b2;
            font-size: 16px;
            background: white;
            transition: all 0.2s;
        }
        .login-container input:focus {
            outline: none;
            border-color: #f97316;
            box-shadow: 0 0 0 3px rgba(249,115,22,0.2);
        }
        .options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 15px 0 28px;
            font-size: 14px;
        }
        .remember {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        .remember input { width: 18px; height: 18px; accent-color: #f97316; margin:0; }
        .forgot-link {
            color: #f97316;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
        }
        .forgot-link:hover { text-decoration: underline; }
        .login-btn {
            background: linear-gradient(145deg, #f97316, #ea580c);
            color: white;
            border: none;
            padding: 14px 0;
            border-radius: 40px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: 0.25s;
            box-shadow: 0 8px 18px rgba(249,115,22,0.3);
        }
        .login-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 22px rgba(249,115,22,0.4); }
        .error-message {
            color: #e53935;
            margin-top: 18px;
            font-size: 14px;
            background: #ffebee;
            padding: 10px;
            border-radius: 40px;
            display: none;
        }
        .back-btn {
            position: absolute;
            top: 20px; left: 20px;
            background: rgba(255,255,255,0.9);
            border: none;
            width: 44px; height: 44px;
            border-radius: 50%;
            color: #f97316;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .back-btn:hover { background: #f97316; color: white; transform: translateX(-5px); }
        .logo-badge {
            position: absolute;
            top: 20px; right: 20px;
            color: white;
            font-weight: bold;
            font-size: 22px;
            text-shadow: 0 2px 5px rgba(0,0,0,0.3);
            background: rgba(0,0,0,0.3);
            padding: 6px 14px;
            border-radius: 40px;
            backdrop-filter: blur(5px);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 28px;
            width: 400px;
            max-width: 90%;
            text-align: center;
            position: relative;
            box-shadow: 0 20px 35px rgba(0,0,0,0.3);
        }
        .modal-content h3 {
            color: #f97316;
            margin-bottom: 20px;
            font-size: 24px;
        }
        .modal-content input {
            width: 100%;
            padding: 12px 16px;
            margin: 12px 0;
            border: 1.5px solid #ffe0b2;
            border-radius: 14px;
            font-size: 16px;
        }
        .modal-content button {
            background: #f97316;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 40px;
            width: 100%;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
        }
        .modal-content button:hover { background: #ea580c; }
        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 28px;
            cursor: pointer;
            color: #888;
        }
        .close-modal:hover { color: #f97316; }
        .modal-error {
            color: #e53935;
            font-size: 13px;
            margin-top: 8px;
        }
        .modal-success {
            color: #2e7d32;
            font-size: 13px;
            margin-top: 8px;
        }
        .resend-link {
            color: #f97316;
            cursor: pointer;
            font-size: 13px;
            text-decoration: underline;
            margin-top: 8px;
            display: inline-block;
        }
        .resend-link:hover { color: #ea580c; }
    </style>
</head>
<body class="login-page">
    <video autoplay muted loop playsinline class="video-bg">
        <source src="login.mp4" type="video/mp4">
    </video>
    <div class="logo-badge"><i class="fas fa-book-open"></i> UBook</div>
    <div class="login-container" id="loginContainer">
        <button class="back-btn" onclick="window.location.href='UBook.html'"><i class="fas fa-arrow-left"></i></button>

        <h2>Welcome Back</h2>
        <form id="loginForm">
            <div class="input-group">
                <i class="fas fa-user-circle"></i>
                <input type="text" id="username" name="username" placeholder="Username" autocomplete="username" required>
            </div>
            <div class="input-group">
                <i class="fas fa-key"></i>
                <input type="password" id="password" name="password" placeholder="Password" autocomplete="current-password" required>
            </div>
            <div class="options">
                <label class="remember">
                    <input type="checkbox" id="rememberCheckbox"> <span>Remember me</span>
                </label>
                <a href="#" id="forgotPasswordLink" class="forgot-link">Forgot Password?</a>
            </div>
            <button type="submit" class="login-btn"><i class="fas fa-sign-in-alt" style="margin-right:8px;"></i> Login</button>
            <div id="errorMsg" class="error-message"></div>
        </form>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" id="closeModalBtn">&times;</span>
            <div id="modalStep1">
                <h3><i class="fas fa-envelope"></i> Reset Password</h3>
                <p style="margin-bottom: 15px;">Enter your registered email address. We'll send you a one‑time password (OTP).</p>
                <input type="email" id="resetEmail" placeholder="Email address" autocomplete="off">
                <button id="requestOtpBtn">Send OTP</button>
                <div id="step1Error" class="modal-error"></div>
                <div id="step1Success" class="modal-success"></div>
            </div>
            <div id="modalStep2" style="display:none;">
                <h3><i class="fas fa-key"></i> Verify OTP & Set New Password</h3>
                <input type="email" id="verifyEmail" placeholder="Email address" readonly style="background:#f5f5f5;">
                <input type="text" id="otpCode" placeholder="6-digit OTP" maxlength="6" autocomplete="off">
                <input type="password" id="newPassword" placeholder="New password (min 6 chars)">
                <input type="password" id="confirmPassword" placeholder="Confirm new password">
                <button id="resetPasswordBtn">Reset Password</button>
                <div>
                    <span id="resendOtpLink" class="resend-link">⟳ Resend OTP</span>
                </div>
                <div id="step2Error" class="modal-error"></div>
                <div id="step2Success" class="modal-success"></div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            const loginForm = document.getElementById('loginForm');
            const errorDiv = document.getElementById('errorMsg');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const rememberCheck = document.getElementById('rememberCheckbox');

            const modal = document.getElementById('forgotModal');
            const forgotLink = document.getElementById('forgotPasswordLink');
            const closeBtn = document.getElementById('closeModalBtn');
            const step1Div = document.getElementById('modalStep1');
            const step2Div = document.getElementById('modalStep2');
            const requestOtpBtn = document.getElementById('requestOtpBtn');
            const resetEmailInput = document.getElementById('resetEmail');
            const verifyEmailInput = document.getElementById('verifyEmail');
            const otpCodeInput = document.getElementById('otpCode');
            const newPasswordInput = document.getElementById('newPassword');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const resetPasswordBtn = document.getElementById('resetPasswordBtn');
            const resendLink = document.getElementById('resendOtpLink');
            const step1Error = document.getElementById('step1Error');
            const step1Success = document.getElementById('step1Success');
            const step2Error = document.getElementById('step2Error');
            const step2Success = document.getElementById('step2Success');

            const savedUser = localStorage.getItem('ubook_username');
            const savedPass = localStorage.getItem('ubook_password');
            if (savedUser && savedPass) {
                usernameInput.value = savedUser;
                passwordInput.value = savedPass;
                rememberCheck.checked = true;
            }

            function showError(msg) {
                errorDiv.textContent = msg;
                errorDiv.style.display = 'block';
            }

            function shakeContainer() {
                const container = document.getElementById('loginContainer');
                container.style.animation = 'none';
                container.offsetHeight;
                container.style.animation = 'fadeSlideUp 0.6s ease-out';
                container.animate([{transform:'translateX(0px)'},{transform:'translateX(-8px)'},{transform:'translateX(8px)'},{transform:'translateX(-5px)'},{transform:'translateX(5px)'},{transform:'translateX(0px)'}],{duration:350});
            }

            loginForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                errorDiv.style.display = 'none';
                const username = usernameInput.value.trim();
                const password = passwordInput.value;
                if (!username || !password) {
                    showError('Please fill in both fields.');
                    return;
                }
                const submitBtn = loginForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Verifying...';
                submitBtn.disabled = true;
                try {
                    const resp = await fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'verify_password', username, password })
                    });
                    const data = await resp.json();
                    if (data.success) {
                        if (rememberCheck.checked) {
                            localStorage.setItem('ubook_username', username);
                            localStorage.setItem('ubook_password', password);
                        } else {
                            localStorage.removeItem('ubook_username');
                            localStorage.removeItem('ubook_password');
                        }
                        window.location.href = data.redirect;
                    } else {
                        showError(data.error || 'Login failed.');
                        shakeContainer();
                        if (data.error && data.error.toLowerCase().includes('wait')) {
                            passwordInput.value = '';
                        }
                    }
                } catch (err) {
                    console.error(err);
                    showError('Network error. Please check your connection.');
                    shakeContainer();
                } finally {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            });

            function resetModal(show = true) {
                modal.style.display = show ? 'flex' : 'none';
                if (!show) {
                    step1Div.style.display = 'block';
                    step2Div.style.display = 'none';
                    resetEmailInput.value = '';
                    verifyEmailInput.value = '';
                    otpCodeInput.value = '';
                    newPasswordInput.value = '';
                    confirmPasswordInput.value = '';
                    step1Error.innerHTML = '';
                    step1Success.innerHTML = '';
                    step2Error.innerHTML = '';
                    step2Success.innerHTML = '';
                }
            }

            forgotLink.addEventListener('click', (e) => {
                e.preventDefault();
                resetModal(true);
            });

            closeBtn.addEventListener('click', () => resetModal(false));
            window.addEventListener('click', (e) => {
                if (e.target === modal) resetModal(false);
            });

            requestOtpBtn.addEventListener('click', async () => {
                const email = resetEmailInput.value.trim();
                step1Error.innerHTML = '';
                step1Success.innerHTML = '';

                if (!email || !/^\S+@\S+\.\S+$/.test(email)) {
                    step1Error.innerHTML = 'Please enter a valid email address.';
                    return;
                }

                requestOtpBtn.disabled = true;
                requestOtpBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Sending...';
                try {
                    const resp = await fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'request_otp', email })
                    });
                    const data = await resp.json();
                    if (data.success) {
                        step1Success.innerHTML = data.message;
                        step1Div.style.display = 'none';
                        step2Div.style.display = 'block';
                        verifyEmailInput.value = email;
                        otpCodeInput.focus();
                    } else {
                        step1Error.innerHTML = data.error || 'Failed to send OTP.';
                    }
                } catch (err) {
                    console.error(err);
                    step1Error.innerHTML = 'Network error. Please try again.';
                } finally {
                    requestOtpBtn.disabled = false;
                    requestOtpBtn.innerHTML = 'Send OTP';
                }
            });

            resendLink.addEventListener('click', async () => {
                const email = verifyEmailInput.value.trim();
                if (!email) return;
                step2Error.innerHTML = '';
                step2Success.innerHTML = '';
                resendLink.style.pointerEvents = 'none';
                resendLink.style.opacity = '0.6';
                try {
                    const resp = await fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'request_otp', email })
                    });
                    const data = await resp.json();
                    if (data.success) {
                        step2Success.innerHTML = 'New OTP sent to your email.';
                        setTimeout(() => step2Success.innerHTML = '', 4000);
                    } else {
                        step2Error.innerHTML = data.error || 'Resend failed.';
                    }
                } catch (err) {
                    console.error(err);
                    step2Error.innerHTML = 'Network error.';
                } finally {
                    resendLink.style.pointerEvents = 'auto';
                    resendLink.style.opacity = '1';
                }
            });

            resetPasswordBtn.addEventListener('click', async () => {
                const email = verifyEmailInput.value.trim();
                const otp = otpCodeInput.value.trim();
                const newPass = newPasswordInput.value;
                const confirmPass = confirmPasswordInput.value;

                step2Error.innerHTML = '';
                step2Success.innerHTML = '';

                if (!email || !otp) {
                    step2Error.innerHTML = 'Email and OTP are required.';
                    return;
                }
                if (!newPass || !confirmPass) {
                    step2Error.innerHTML = 'Please fill in both password fields.';
                    return;
                }
                if (newPass !== confirmPass) {
                    step2Error.innerHTML = 'Passwords do not match.';
                    return;
                }
                if (newPass.length < 6) {
                    step2Error.innerHTML = 'Password must be at least 6 characters.';
                    return;
                }

                resetPasswordBtn.disabled = true;
                resetPasswordBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Updating...';
                try {
                    const resp = await fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ 
                            action: 'reset_with_otp', 
                            email, 
                            otp, 
                            new_password: newPass, 
                            confirm_password: confirmPass 
                        })
                    });
                    const data = await resp.json();
                    if (data.success) {
                        step2Success.innerHTML = data.message;
                        setTimeout(() => {
                            resetModal(false);
                        }, 3000);
                    } else {
                        step2Error.innerHTML = data.error || 'Reset failed.';
                    }
                } catch (err) {
                    console.error(err);
                    step2Error.innerHTML = 'Network error. Please try again.';
                } finally {
                    resetPasswordBtn.disabled = false;
                    resetPasswordBtn.innerHTML = 'Reset Password';
                }
            });
        })();
    </script>
</body> 
</html>