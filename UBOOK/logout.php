<?php
// logout.php – corrected and hardened

// Start session (only if not already active)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset all session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        1,  // Expired (any timestamp in the past works)
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session data on the server
session_destroy();

// Redirect to landing page (use absolute URL for reliability)
$redirectUrl = (isset($_SERVER['HTTPS']) ? "https://" : "http://")
               . $_SERVER['HTTP_HOST']
               . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\')
               . "/UBook.html";
header("Location: $redirectUrl");
exit();
?>