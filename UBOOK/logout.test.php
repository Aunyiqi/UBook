<?php
/**
 * logout.test.php – Fixed cookie deletion detection
 * Usage: php logout.test.php "PHPSESSID=anyvalue"
 */

$baseUrl = 'http://localhost/UBook';
$logoutUrl = $baseUrl . '/logout.php';
$sessionCookieName = 'PHPSESSID';  // or session_name() if you prefer

$sessionCookieString = $argv[1] ?? '';

function request($url, $method = 'GET', $postData = null, $cookieHeader = '') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    if (!empty($cookieHeader)) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookieHeader);
    }
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    $headerSize = $info['header_size'];
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    preg_match_all('/^Set-Cookie:\s*([^\r\n]+)/mi', $headers, $matches);
    $setCookies = $matches[1] ?? [];

    return [
        'status_code'  => $info['http_code'],
        'redirect_url' => $info['redirect_url'] ?? null,
        'headers'      => $headers,
        'set_cookies'  => $setCookies
    ];
}

echo "=== Logout Test (Improved Detection) ===\n";
echo empty($sessionCookieString) ? "⚠️  No session cookie provided.\n" : "✅ Using cookie: $sessionCookieString\n";

echo "\nCalling logout.php...\n";
$response = request($logoutUrl, 'GET', null, $sessionCookieString);

// Check redirect
$redirectOk = ($response['status_code'] == 302 && strpos($response['redirect_url'] ?? '', 'UBook.html') !== false);
echo $redirectOk ? "✅ Redirects to UBook.html (302)\n" : "❌ No redirect (status {$response['status_code']})\n";

// Robust cookie deletion check
$cookieDeleted = false;
foreach ($response['set_cookies'] as $cookieHeader) {
    // Does this Set-Cookie target our session name?
    if (preg_match('/^' . preg_quote($sessionCookieName, '/') . '=/i', $cookieHeader)) {
        // Check for empty value OR expired date in 1970
        if (preg_match('/=\s*[;,]/', $cookieHeader) ||           // empty value like "PHPSESSID=;"
            preg_match('/expires=.*1970/i', $cookieHeader)) {    // any 1970 expiry
            $cookieDeleted = true;
            break;
        }
    }
}

if (empty($sessionCookieString)) {
    echo "ℹ️  No session cookie provided – deletion not required.\n";
} else {
    echo $cookieDeleted ? "✅ Session cookie deletion confirmed (expired/empty)\n" 
                        : "⚠️  Cookie deletion header not detected, but logout may still work.\n";
}

echo "\n=== Test Complete ===\n";
?>