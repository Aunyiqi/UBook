<?php

session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

$allowedRoles = ['booking_admin', 'super_admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowedRoles)) {
    header('Location: login.php');
    exit();
}

// Autoload PHPMailer
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define('DEEPSEEK_API_KEY', '');
define('VENUE_OPEN_HOUR', 8);
define('VENUE_CLOSE_HOUR', 22);
define('MAX_DURATION_HOURS', 4);
define('MAX_USER_DAILY_BOOKINGS', 3);

// ==================== MALAYSIAN PUBLIC HOLIDAYS ====================
function getPublicHolidays($year) {
    $holidays = [
        2026 => ['2026-01-01' => 'New Year\'s Day', '2026-02-17' => 'Chinese New Year', '2026-02-18' => 'Chinese New Year', '2026-03-21' => 'Hari Raya Puasa', '2026-03-22' => 'Hari Raya Puasa', '2026-05-01' => 'Labour Day', '2026-05-27' => 'Hari Raya Haji', '2026-05-28' => 'Hari Raya Haji', '2026-05-31' => 'Wesak Day', '2026-06-01' => 'Agong\'s Birthday', '2026-06-17' => 'Awal Muharram', '2026-08-25' => 'Prophet Muhammad\'s Birthday', '2026-08-31' => 'National Day', '2026-09-16' => 'Malaysia Day', '2026-11-08' => 'Deepavali', '2026-12-25' => 'Christmas Day'],
        2027 => ['2027-01-01' => 'New Year\'s Day', '2027-02-07' => 'Chinese New Year', '2027-02-08' => 'Chinese New Year', '2027-03-11' => 'Hari Raya Puasa', '2027-03-12' => 'Hari Raya Puasa', '2027-05-01' => 'Labour Day', '2027-05-20' => 'Wesak Day', '2027-05-27' => 'Hari Raya Haji', '2027-06-07' => 'Agong\'s Birthday', '2027-06-16' => 'Awal Muharram', '2027-08-24' => 'Prophet Muhammad\'s Birthday', '2027-08-31' => 'National Day', '2027-09-16' => 'Malaysia Day', '2027-11-08' => 'Deepavali', '2027-12-25' => 'Christmas Day'],
        2028 => ['2028-01-01' => 'New Year\'s Day', '2028-01-27' => 'Chinese New Year', '2028-01-28' => 'Chinese New Year', '2028-02-28' => 'Hari Raya Puasa', '2028-02-29' => 'Hari Raya Puasa', '2028-05-01' => 'Labour Day', '2028-05-10' => 'Wesak Day', '2028-05-15' => 'Hari Raya Haji', '2028-06-05' => 'Agong\'s Birthday', '2028-06-04' => 'Awal Muharram', '2028-08-13' => 'Prophet Muhammad\'s Birthday', '2028-08-31' => 'National Day', '2028-09-16' => 'Malaysia Day', '2028-10-27' => 'Deepavali', '2028-12-25' => 'Christmas Day'],
        2029 => ['2029-01-01' => 'New Year\'s Day', '2029-02-13' => 'Chinese New Year', '2029-02-14' => 'Chinese New Year', '2029-02-18' => 'Hari Raya Puasa', '2029-02-19' => 'Hari Raya Puasa', '2029-05-01' => 'Labour Day', '2029-04-29' => 'Wesak Day', '2029-05-04' => 'Hari Raya Haji', '2029-05-25' => 'Awal Muharram', '2029-06-04' => 'Agong\'s Birthday', '2029-08-03' => 'Prophet Muhammad\'s Birthday', '2029-08-31' => 'National Day', '2029-09-16' => 'Malaysia Day', '2029-11-15' => 'Deepavali', '2029-12-25' => 'Christmas Day'],
        2030 => ['2030-01-01' => 'New Year\'s Day', '2030-02-03' => 'Chinese New Year', '2030-02-04' => 'Chinese New Year', '2030-02-07' => 'Hari Raya Puasa', '2030-02-08' => 'Hari Raya Puasa', '2030-05-01' => 'Labour Day', '2030-05-18' => 'Wesak Day', '2030-04-23' => 'Hari Raya Haji', '2030-06-03' => 'Agong\'s Birthday', '2030-05-14' => 'Awal Muharram', '2030-07-23' => 'Prophet Muhammad\'s Birthday', '2030-08-31' => 'National Day', '2030-09-16' => 'Malaysia Day', '2030-11-04' => 'Deepavali', '2030-12-25' => 'Christmas Day'],
    ];
    return $holidays[$year] ?? [];
}

function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli("localhost", "root", "", "ubook");
        if ($conn->connect_error) {
            die(json_encode(['error' => 'DB connection failed: ' . $conn->connect_error]));
        }
        $conn->set_charset("utf8mb4");
    }
    return $conn;
}

// ==================== EXPORT CSV (GET Request) ====================
if (isset($_GET['export_csv'])) {
    $conn = getDB();
    $statusFilter = $_GET['status'] ?? 'all';
    $search = trim($_GET['search'] ?? '');
    
    $sql = "SELECT b.id, u.name as student_name, u.email, v.name as venue_name, b.booking_date, b.start_time, b.duration_hours, b.comment, b.status 
            FROM bookings b 
            LEFT JOIN venues v ON b.venue_id = v.id 
            LEFT JOIN users u ON b.user_id = u.id 
            WHERE 1=1";
             
    $params = [];
    $types = "";

    if ($statusFilter !== 'all') {
        $sql .= " AND b.status = ?";
        $params[] = $statusFilter;
        $types .= "s";
    }
    
    if (!empty($search)) {
        $searchTerm = "%$search%";
        $sql .= " AND (u.name LIKE ? OR v.name LIKE ? OR b.booking_date LIKE ?)";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "sss";
    }
    
    $sql .= " ORDER BY b.booking_date DESC, b.start_time ASC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Clear any output buffers
    while (ob_get_level()) ob_end_clean();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bookings_export.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM
    
    fputcsv($output, ['ID', 'Student', 'Email', 'Venue', 'Date', 'Time', 'Duration (h)', 'Comment', 'Status']);
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['student_name'],
            $row['email'],
            $row['venue_name'],
            $row['booking_date'],
            date("g:i A", strtotime($row['start_time'])),
            $row['duration_hours'],
            $row['comment'],
            $row['status']
        ]);
    }
    fclose($output);
    exit();
}

// ==================== EMAIL FUNCTIONS ====================
function sendBookingStatusEmail($userEmail, $userName, $bookingDetails, $newStatus) {
    if (empty($userEmail) || !class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return false;
    }
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'aunyiqi168@gmail.com';
        $mail->Password = '';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
        $mail->CharSet = 'UTF-8';
        $mail->setFrom('aunyiqi168@gmail.com', 'UBook Campus Venues');
        $mail->addAddress($userEmail, $userName);
        $mail->addBCC('aunyiqi168@gmail.com');
        $subject = ($newStatus === 'confirmed') ? '✅ Booking Confirmed - UBook' : '❌ Booking Rejected - UBook';
        $html = "<h2>Hello $userName,</h2>
                 <p>Your booking for <strong>{$bookingDetails['venue_name']}</strong> on <strong>{$bookingDetails['date']}</strong> at <strong>{$bookingDetails['time']}</strong> for <strong>{$bookingDetails['duration']} hour(s)</strong> has been <strong>$newStatus</strong>.</p>";
        if ($newStatus === 'rejected') {
            $html .= "<p>If you have questions, contact the venue admin.</p>";
        } else {
            $html .= "<p>Enjoy your event! Please arrive on time.</p>";
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = strip_tags($html);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email failed: " . $mail->ErrorInfo);
        return false;
    }
}

function sendCustomEmail($toEmail, $toName, $subject, $body) {
    if (empty($toEmail) || !class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return false;
    }
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'aunyiqi168@gmail.com';
        $mail->Password = 'cvskoyhsisybpsnf';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
        $mail->CharSet = 'UTF-8';
        $mail->setFrom('aunyiqi168@gmail.com', 'UBook Admin');
        $mail->addAddress($toEmail, $toName);
        $mail->addBCC('aunyiqi168@gmail.com');
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = nl2br($body);
        $mail->AltBody = strip_tags($body);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Custom email failed: " . $mail->ErrorInfo);
        return false;
    }
}

// ==================== AUDIT LOG ====================
function logAudit($conn, $adminId, $bookingId, $action, $oldStatus = null, $newStatus = null) {
    $sql = "INSERT INTO audit_log (admin_id, booking_id, action, old_status, new_status) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisss", $adminId, $bookingId, $action, $oldStatus, $newStatus);
    return $stmt->execute();
}

// ==================== CORE FUNCTIONS ====================
function getTrueStats($conn, $search = '') {
    $stats = ['pending' => 0, 'confirmed' => 0, 'rejected' => 0, 'all' => 0];
    $sql = "SELECT b.status, COUNT(*) as cnt FROM bookings b LEFT JOIN users u ON b.user_id = u.id LEFT JOIN venues v ON b.venue_id = v.id WHERE 1=1";
    
    if (!empty($search)) {
        $searchTerm = "%$search%";
        $sql .= " AND (u.name LIKE ? OR v.name LIKE ? OR b.booking_date LIKE ?)";
        $sql .= " GROUP BY b.status";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (isset($stats[$row['status']])) {
                $stats[$row['status']] = (int)$row['cnt'];
            }
            $stats['all'] += (int)$row['cnt'];
        }
        $stmt->close();
    } else {
        $sql .= " GROUP BY b.status";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if (isset($stats[$row['status']])) {
                    $stats[$row['status']] = (int)$row['cnt'];
                }
                $stats['all'] += (int)$row['cnt'];
            }
        }
    }
    return $stats;
}

function checkBookingConflicts($conn, $bookingId, $venueId, $bookingDate, $startTime, $durationHours, $userId = null) {
    $conflicts = [];
    $newStart = strtotime($startTime);
    if (!$newStart) return $conflicts;
    $newEnd = $newStart + ($durationHours * 3600);
    $today = strtotime(date('Y-m-d'));
    $bookingTimestamp = strtotime($bookingDate);
    if (!$bookingTimestamp) return $conflicts;
    $year = date('Y', $bookingTimestamp);
    $holidays = getPublicHolidays($year);
    if (isset($holidays[$bookingDate])) {
        $conflicts[] = "Venue closed on public holiday: " . $holidays[$bookingDate];
    }
    if ($bookingTimestamp < $today) {
        $conflicts[] = "Cannot book a past date";
    }
    $hour = (int)date('H', $newStart);
    if ($hour < VENUE_OPEN_HOUR || $hour >= VENUE_CLOSE_HOUR) {
        $conflicts[] = "Outside operating hours (" . VENUE_OPEN_HOUR . ":00 – " . VENUE_CLOSE_HOUR . ":00)";
    }
    if ($durationHours > MAX_DURATION_HOURS) {
        $conflicts[] = "Duration exceeds " . MAX_DURATION_HOURS . " hours maximum";
    }
    $overlapSql = "SELECT b.id, b.start_time, b.duration_hours, u.name as student_name FROM bookings b LEFT JOIN users u ON b.user_id = u.id WHERE b.venue_id = ? AND b.booking_date = ? AND b.status = 'confirmed' AND b.id != ?";
    $stmt = $conn->prepare($overlapSql);
    if ($stmt) {
        $stmt->bind_param("isi", $venueId, $bookingDate, $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $exStart = strtotime($row['start_time']);
            if (!$exStart) continue;
            $exEnd = $exStart + ($row['duration_hours'] * 3600);
            if ($newStart < $exEnd && $newEnd > $exStart) {
                $conflicts[] = "Time overlap with confirmed booking #{$row['id']} ({$row['student_name']})";
            }
        }
        $stmt->close();
    }
    if ($userId) {
        $dailySql = "SELECT COUNT(*) as cnt FROM bookings WHERE user_id = ? AND booking_date = ? AND status != 'rejected' AND id != ?";
        $dailyStmt = $conn->prepare($dailySql);
        if ($dailyStmt) {
            $dailyStmt->bind_param("isi", $userId, $bookingDate, $bookingId);
            $dailyStmt->execute();
            $dailyCount = $dailyStmt->get_result()->fetch_assoc()['cnt'];
            if ($dailyCount >= MAX_USER_DAILY_BOOKINGS) {
                $conflicts[] = "User exceeds daily booking quota (" . MAX_USER_DAILY_BOOKINGS . " per day)";
            }
            $dailyStmt->close();
        }
    }
    return $conflicts;
}

function getAllBookingsForAI($conn) {
    $sql = "SELECT b.*, v.name as venue_name, u.name as student_name FROM bookings b LEFT JOIN venues v ON b.venue_id = v.id LEFT JOIN users u ON b.user_id = u.id ORDER BY b.booking_date ASC, b.start_time ASC";
    $result = $conn->query($sql);
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $conflicts = checkBookingConflicts($conn, $row['id'], $row['venue_id'], $row['booking_date'], $row['start_time'], $row['duration_hours'], $row['user_id']);
        $row['has_conflicts'] = !empty($conflicts);
        $row['conflict_reasons'] = $conflicts;
        $bookings[] = $row;
    }
    return $bookings;
}

function getVenueUsageStats($conn) {
    $sql = "SELECT v.id, v.name, COUNT(b.id) as booking_count 
            FROM venues v 
            LEFT JOIN bookings b ON v.id = b.venue_id AND b.status = 'confirmed' 
            GROUP BY v.id 
            ORDER BY booking_count DESC";
    $result = $conn->query($sql);
    if (!$result) return "No venue data available.";
    $stats = [];
    while ($row = $result->fetch_assoc()) {
        $stats[] = $row['name'] . ": " . $row['booking_count'] . " confirmed booking(s)";
    }
    return implode("; ", $stats);
}

function getVenueCommentsSummary($conn) {
    $check = $conn->query("SHOW TABLES LIKE 'venue_comments'");
    if ($check->num_rows == 0) return "No comments table found.";
    $sql = "SELECT v.name, c.comment, u.name as user_name, c.created_at 
            FROM venue_comments c 
            JOIN venues v ON c.venue_id = v.id 
            LEFT JOIN users u ON c.user_id = u.id 
            ORDER BY c.created_at DESC 
            LIMIT 50";
    $result = $conn->query($sql);
    if (!$result || $result->num_rows == 0) return "No comments available.";
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = $row['name'] . ": '" . $row['comment'] . "' by " . ($row['user_name'] ?? 'Anonymous') . " on " . $row['created_at'];
    }
    return implode("; ", $comments);
}

function buildAIContext($conn) {
    $bookings = getAllBookingsForAI($conn);
    $context = "Booking list with conflicts:\n";
    foreach ($bookings as $b) {
        $conflictStatus = $b['has_conflicts'] ? 'HAS CONFLICTS' : 'OK';
        $reason = $b['has_conflicts'] ? ' | Reasons: ' . implode('; ', $b['conflict_reasons']) : '';
        $context .= sprintf("ID:%d | %s | %s | %s | %s | %sh | Status:%s | %s%s\n", 
            $b['id'], 
            $b['student_name'] ?? 'Guest', 
            $b['venue_name'], 
            $b['booking_date'], 
            date("g:i A", strtotime($b['start_time'])), 
            $b['duration_hours'], 
            $b['status'], 
            $conflictStatus, 
            $reason
        );
    }
    $context .= "\nVenue usage stats (confirmed bookings):\n";
    $context .= getVenueUsageStats($conn) . "\n";
    $context .= "\nVenue comments (latest 50):\n";
    $context .= getVenueCommentsSummary($conn) . "\n";
    return $context;
}

// ==== ENHANCED: DeepSeek Function Now Accepts Conversation History ====
function askDeepSeek($userQuestion, $context, $history = []) {
    $apiKey = DEEPSEEK_API_KEY;
    $url = 'https://api.deepseek.com/v1/chat/completions';
    $systemPrompt = "You are an AI assistant for UBook, the campus venue booking system. You have data about bookings (including conflicts), venue usage statistics, and venue comments. 
    Answer admin's questions about conflicts, public holidays, venue usage, and venue comments. Be concise and helpful. Use Markdown to format your answers cleanly (bold, lists, etc.).

    You have two special capabilities:
    1. Send custom emails: If the admin asks to send an email, respond with a JSON block: {\"action\":\"send_email\",\"to\":\"email\",\"name\":\"Name\",\"subject\":\"Subject\",\"body\":\"Body\"}.
    2. Update booking status (confirm/reject) and automatically send the corresponding email: If the admin asks to confirm or reject a booking (by ID or by student/venue), respond with a JSON block: {\"action\":\"update_status\",\"booking_id\":123,\"status\":\"confirmed\"} or \"rejected\". Only use valid booking IDs from the context.

    Do not include any other JSON or code blocks. If the request doesn't match these actions, just answer normally.

    For status updates, always include the booking_id from the list provided.";

    $messages = [
        ['role' => 'system', 'content' => $systemPrompt . "\n\n" . $context]
    ];
    
    // Inject chat history
    foreach ($history as $msg) {
        if (isset($msg['role']) && isset($msg['content'])) {
            if (in_array($msg['role'], ['user', 'assistant'])) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }
    }
    $messages[] = ['role' => 'user', 'content' => $userQuestion];

    $payload = ['model' => 'deepseek-chat', 'messages' => $messages, 'temperature' => 0.7, 'max_tokens' => 1500];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) return "AI service temporarily unavailable.";
    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? "I couldn't understand that.";
}

// ==================== AJAX HANDLERS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // Clear any previous output buffers to ensure clean JSON
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $conn = getDB();

    // ---------- GET BOOKINGS (with search & pagination) ----------
    if ($action === 'get_bookings') {
        $statusFilter = $_POST['status'] ?? 'all';
        $search = trim($_POST['search'] ?? '');
        $page = (int)($_POST['page'] ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $sql = "SELECT b.*, v.name as venue_name, u.email as user_email, u.name as student_name 
                FROM bookings b 
                LEFT JOIN venues v ON b.venue_id = v.id 
                LEFT JOIN users u ON b.user_id = u.id 
                WHERE 1=1";
        $params = [];
        $types = "";
        if ($statusFilter !== 'all') {
            $sql .= " AND b.status = ?";
            $params[] = $statusFilter;
            $types .= "s";
        }
        if (!empty($search)) {
            $searchTerm = "%$search%";
            $sql .= " AND (u.name LIKE ? OR v.name LIKE ? OR b.booking_date LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "sss";
        }
        $sql .= " ORDER BY b.booking_date DESC, b.start_time ASC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        $stmt = $conn->prepare($sql);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $bookings = [];
        while ($row = $result->fetch_assoc()) {
            $row['start_time'] = date("g:i A", strtotime($row['start_time']));
            if ($row['status'] === 'confirmed') {
                $row['has_conflicts'] = false;
                $row['conflict_reasons'] = [];
            } else {
                $conflicts = checkBookingConflicts($conn, $row['id'], $row['venue_id'], $row['booking_date'], $row['start_time'], $row['duration_hours'], $row['user_id']);
                $row['has_conflicts'] = !empty($conflicts);
                $row['conflict_reasons'] = $conflicts;
            }
            $bookings[] = $row;
        }
        
        $trueStats = getTrueStats($conn, $search);
        $total = ($statusFilter !== 'all') ? ($trueStats[$statusFilter] ?? 0) : $trueStats['all'];

        echo json_encode(['success' => true, 'bookings' => $bookings, 'total' => $total, 'page' => $page, 'limit' => $limit, 'stats' => $trueStats]);
        exit();
    }

    // ---------- CALENDAR EVENTS ----------
    if ($action === 'get_calendar_events') {
        $sql = "SELECT b.id, b.venue_id, b.booking_date, b.start_time, b.duration_hours, b.status, b.comment, v.name as venue_name, u.name as student_name, u.id as user_id FROM bookings b LEFT JOIN venues v ON b.venue_id = v.id LEFT JOIN users u ON b.user_id = u.id ORDER BY b.booking_date ASC, b.start_time ASC";
        $result = $conn->query($sql);
        $events = [];
        while ($row = $result->fetch_assoc()) {
            if (empty($row['booking_date']) || empty($row['start_time'])) continue;
            if ($row['status'] === 'confirmed') {
                $conflicts = [];
            } else {
                $conflicts = checkBookingConflicts($conn, $row['id'], $row['venue_id'], $row['booking_date'], $row['start_time'], $row['duration_hours'], $row['user_id']);
            }
            $hasConflict = !empty($conflicts);
            $startDateTime = $row['booking_date'] . ' ' . $row['start_time'];
            $startTimestamp = strtotime($startDateTime);
            if (!$startTimestamp) continue;
            $endTimestamp = $startTimestamp + ($row['duration_hours'] * 3600);
            $endDateTime = date('Y-m-d H:i:s', $endTimestamp);
            if ($hasConflict) {
                $color = '#9b59b6';
            } else if ($row['status'] == 'pending') {
                $color = '#f39c12';
            } else if ($row['status'] == 'confirmed') {
                $color = '#10b981';
            } else if ($row['status'] == 'rejected') {
                $color = '#ef4444';
            } else {
                $color = '#6b7280';
            }
            $title = ($hasConflict ? '⚠️ ' : ($row['status'] == 'pending' ? '⏳ ' : '')) . ($row['student_name'] ?? 'Guest') . ' @ ' . ($row['venue_name'] ?? 'Unknown');
            $events[] = [
                'id' => $row['id'], 'title' => $title, 'start' => $startDateTime, 'end' => $endDateTime, 'color' => $color, 'textColor' => '#fff',
                'extendedProps' => ['status' => $row['status'], 'venue' => $row['venue_name'] ?? '', 'student' => $row['student_name'] ?? '', 'duration' => $row['duration_hours'], 'comment' => $row['comment'], 'date' => $row['booking_date'], 'time' => date("g:i A", strtotime($row['start_time'])), 'hasConflict' => $hasConflict, 'conflictReasons' => $conflicts]
            ];
        }
        $trueStats = getTrueStats($conn, '');
        echo json_encode(['success' => true, 'events' => $events, 'stats' => $trueStats]);
        exit();
    }

    // ---------- CHECK CONFLICTS ----------
    if ($action === 'check_conflicts') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $stmt = $conn->prepare("SELECT b.*, v.name as venue_name FROM bookings b LEFT JOIN venues v ON b.venue_id = v.id WHERE b.id = ?");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Booking not found']);
        } else {
            if ($booking['status'] === 'confirmed') {
                echo json_encode(['success' => true, 'conflicts' => []]);
            } else {
                $conflicts = checkBookingConflicts($conn, $booking['id'], $booking['venue_id'], $booking['booking_date'], $booking['start_time'], $booking['duration_hours'], $booking['user_id']);
                echo json_encode(['success' => true, 'conflicts' => $conflicts]);
            }
        }
        exit();
    }

    // ---------- UPDATE STATUS (confirm/reject) ----------
    if ($action === 'update_status') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        if (!in_array($newStatus, ['confirmed', 'rejected'])) {
            exit(json_encode(['success' => false, 'message' => 'Invalid status']));
        }
        $stmt = $conn->prepare("SELECT b.*, v.name as venue_name, u.email as user_email, u.name as user_name FROM bookings b LEFT JOIN venues v ON b.venue_id = v.id LEFT JOIN users u ON b.user_id = u.id WHERE b.id = ?");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        if (!$booking) {
            exit(json_encode(['success' => false, 'message' => 'Booking not found']));
        }
        $oldStatus = $booking['status'];
        if ($newStatus === 'confirmed') {
            $conflicts = checkBookingConflicts($conn, $booking['id'], $booking['venue_id'], $booking['booking_date'], $booking['start_time'], $booking['duration_hours'], $booking['user_id']);
            if (!empty($conflicts)) {
                exit(json_encode(['success' => false, 'message' => 'Cannot confirm: ' . implode(', ', $conflicts), 'conflicts' => $conflicts]));
            }
        }
        $update = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $update->bind_param("si", $newStatus, $bookingId);
        if ($update->execute()) {
            logAudit($conn, $_SESSION['user_id'], $bookingId, 'status_change', $oldStatus, $newStatus);
            if (!empty($booking['user_email'])) {
                $details = ['venue_name' => $booking['venue_name'], 'date' => $booking['booking_date'], 'time' => date("g:i A", strtotime($booking['start_time'])), 'duration' => $booking['duration_hours']];
                sendBookingStatusEmail($booking['user_email'], $booking['user_name'], $details, $newStatus);
            }
            echo json_encode(['success' => true, 'message' => "Booking {$newStatus}."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB error']);
        }
        exit();
    }

    // ---------- DELETE BOOKING ----------
    if ($action === 'delete_booking') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $stmt = $conn->prepare("SELECT status FROM bookings WHERE id = ?");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $oldStatus = $stmt->get_result()->fetch_assoc()['status'] ?? null;
        $stmt->close();

        $delStmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
        $delStmt->bind_param("i", $bookingId);
        if ($delStmt->execute() && $delStmt->affected_rows > 0) {
            logAudit($conn, $_SESSION['user_id'], $bookingId, 'delete', $oldStatus, null);
            echo json_encode(['success' => true, 'message' => 'Booking deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Delete failed']);
        }
        exit();
    }

    // ---------- AUTO-RESOLVE CONFLICTS ----------
    if ($action === 'auto_resolve_conflicts') {
        $sql = "SELECT b.id, b.venue_id, b.booking_date, b.start_time, b.duration_hours, b.user_id, v.name as venue_name, u.email as user_email, u.name as user_name FROM bookings b LEFT JOIN venues v ON b.venue_id = v.id LEFT JOIN users u ON b.user_id = u.id WHERE b.status = 'pending'";
        $result = $conn->query($sql);
        $rejectedCount = 0;
        while ($row = $result->fetch_assoc()) {
            $conflicts = checkBookingConflicts($conn, $row['id'], $row['venue_id'], $row['booking_date'], $row['start_time'], $row['duration_hours'], $row['user_id']);
            if (!empty($conflicts)) {
                $update = $conn->prepare("UPDATE bookings SET status = 'rejected' WHERE id = ?");
                $update->bind_param("i", $row['id']);
                if ($update->execute()) {
                    $rejectedCount++;
                    logAudit($conn, $_SESSION['user_id'], $row['id'], 'auto_reject', 'pending', 'rejected');
                    if (!empty($row['user_email'])) {
                        $details = ['venue_name' => $row['venue_name'], 'date' => $row['booking_date'], 'time' => date("g:i A", strtotime($row['start_time'])), 'duration' => $row['duration_hours']];
                        sendBookingStatusEmail($row['user_email'], $row['user_name'], $details, 'rejected');
                    }
                }
            }
        }
        echo json_encode(['success' => true, 'rejected' => $rejectedCount]);
        exit();
    }

    // ---------- AUTO-REJECT EXPIRED ----------
    if ($action === 'auto_reject_expired') {
        $now = date('Y-m-d H:i:s');
        $sql = "SELECT b.id, b.venue_id, b.booking_date, b.start_time, b.duration_hours, b.user_id, v.name as venue_name, u.email as user_email, u.name as user_name FROM bookings b LEFT JOIN venues v ON b.venue_id = v.id LEFT JOIN users u ON b.user_id = u.id WHERE b.status = 'pending' AND CONCAT(b.booking_date, ' ', b.start_time) < ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $now);
        $stmt->execute();
        $result = $stmt->get_result();
        $rejectedCount = 0;
        while ($row = $result->fetch_assoc()) {
            $update = $conn->prepare("UPDATE bookings SET status = 'rejected' WHERE id = ?");
            $update->bind_param("i", $row['id']);
            if ($update->execute()) {
                $rejectedCount++;
                logAudit($conn, $_SESSION['user_id'], $row['id'], 'auto_reject_expired', 'pending', 'rejected');
                if (!empty($row['user_email'])) {
                    $details = ['venue_name' => $row['venue_name'], 'date' => $row['booking_date'], 'time' => date("g:i A", strtotime($row['start_time'])), 'duration' => $row['duration_hours']];
                    sendBookingStatusEmail($row['user_email'], $row['user_name'], $details, 'rejected');
                }
            }
        }
        echo json_encode(['success' => true, 'rejected' => $rejectedCount]);
        exit();
    }

    // ---------- AI QUERY (ENHANCED with History) ----------
    if ($action === 'ai_query') {
        $question = trim($_POST['question'] ?? '');
        $historyData = json_decode($_POST['history'] ?? '[]', true);
        if (!is_array($historyData)) $historyData = [];
        
        if (empty($question)) {
            echo json_encode(['success' => false, 'message' => 'Empty question']);
        } else {
            $context = buildAIContext($conn);
            $answer = askDeepSeek($question, $context, $historyData);
            echo json_encode(['success' => true, 'answer' => $answer]);
        }
        exit();
    }

    // ---------- SEND CUSTOM EMAIL ----------
    if ($action === 'send_custom_email') {
        $to = trim($_POST['to'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');
        if (empty($to) || empty($subject) || empty($body)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit();
        }
        $sent = sendCustomEmail($to, $name, $subject, $body);
        if ($sent) {
            echo json_encode(['success' => true, 'message' => "Email sent to $to"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send email. Check error log.']);
        }
        exit();
    }

    // ---------- NEW: CREATE BOOKING ----------
    if ($action === 'create_booking') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $venueId = (int)($_POST['venue_id'] ?? 0);
        $bookingDate = trim($_POST['booking_date'] ?? '');
        $startTime = trim($_POST['start_time'] ?? '');
        $durationHours = (int)($_POST['duration_hours'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if (!$userId || !$venueId || !$bookingDate || !$startTime || $durationHours <= 0) {
            echo json_encode(['success' => false, 'message' => 'All fields except comment are required.']);
            exit();
        }

        if (!strtotime($bookingDate . ' ' . $startTime)) {
            echo json_encode(['success' => false, 'message' => 'Invalid date or time format.']);
            exit();
        }

        $conflicts = checkBookingConflicts($conn, 0, $venueId, $bookingDate, $startTime, $durationHours, $userId);
        if (!empty($conflicts)) {
            echo json_encode(['success' => false, 'message' => 'Conflicts found: ' . implode(', ', $conflicts), 'conflicts' => $conflicts]);
            exit();
        }

        $insert = $conn->prepare("INSERT INTO bookings (user_id, venue_id, booking_date, start_time, duration_hours, comment, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $insert->bind_param("iissis", $userId, $venueId, $bookingDate, $startTime, $durationHours, $comment);
        if ($insert->execute()) {
            $newId = $insert->insert_id;
            logAudit($conn, $_SESSION['user_id'], $newId, 'create', null, 'pending');
            echo json_encode(['success' => true, 'message' => "Booking #{$newId} created successfully.", 'booking_id' => $newId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        exit();
    }

    // ---------- NEW: UPDATE BOOKING ----------
    if ($action === 'update_booking') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        $venueId = (int)($_POST['venue_id'] ?? 0);
        $bookingDate = trim($_POST['booking_date'] ?? '');
        $startTime = trim($_POST['start_time'] ?? '');
        $durationHours = (int)($_POST['duration_hours'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if (!$bookingId || !$userId || !$venueId || !$bookingDate || !$startTime || $durationHours <= 0) {
            echo json_encode(['success' => false, 'message' => 'All fields except comment are required.']);
            exit();
        }

        $stmt = $conn->prepare("SELECT status FROM bookings WHERE id = ?");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        if (!$existing) {
            echo json_encode(['success' => false, 'message' => 'Booking not found.']);
            exit();
        }
        $oldStatus = $existing['status'];

        $conflicts = checkBookingConflicts($conn, $bookingId, $venueId, $bookingDate, $startTime, $durationHours, $userId);
        if (!empty($conflicts)) {
            echo json_encode(['success' => false, 'message' => 'Conflicts found: ' . implode(', ', $conflicts), 'conflicts' => $conflicts]);
            exit();
        }

        $update = $conn->prepare("UPDATE bookings SET user_id = ?, venue_id = ?, booking_date = ?, start_time = ?, duration_hours = ?, comment = ? WHERE id = ?");
        $update->bind_param("iissisi", $userId, $venueId, $bookingDate, $startTime, $durationHours, $comment, $bookingId);
        if ($update->execute()) {
            logAudit($conn, $_SESSION['user_id'], $bookingId, 'update', $oldStatus, $oldStatus);
            echo json_encode(['success' => true, 'message' => "Booking #{$bookingId} updated successfully."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        exit();
    }

    // ---------- NEW: GET BOOKING DATA FOR EDIT ----------
    if ($action === 'get_booking') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $stmt = $conn->prepare("SELECT b.*, u.name as student_name, v.name as venue_name FROM bookings b LEFT JOIN users u ON b.user_id = u.id LEFT JOIN venues v ON b.venue_id = v.id WHERE b.id = ?");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        if ($booking) {
            echo json_encode(['success' => true, 'booking' => $booking]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Booking not found']);
        }
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

$currentUser = htmlspecialchars($_SESSION['name'] ?? $_SESSION['username'] ?? 'Admin');
$userRole = $_SESSION['role'] ?? '';

$conn = getDB();
$usersRes = $conn->query("SELECT id, name, email FROM users ORDER BY name");
$users = [];
while ($u = $usersRes->fetch_assoc()) $users[] = $u;
$venuesRes = $conn->query("SELECT id, name FROM venues ORDER BY name");
$venues = [];
while ($v = $venuesRes->fetch_assoc()) $venues[] = $v;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UBook · Booking Conflict Manager</title>
    
    <!-- External Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'></script>
    
    <!-- New Dependencies for Enhanced AI Markdown Chat -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js"></script>
    
    <style>
        :root {
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text: #1e293b;
            --border: #e2e8f0;
            --nav-bg: #ffffff;
        }
        .dark-mode {
            --bg: #0f172a;
            --card-bg: #1e293b;
            --text: #e2e8f0;
            --border: #334155;
            --nav-bg: #1e293b;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', 'Segoe UI', Roboto, sans-serif; }
        body { background: var(--bg); min-height: 100vh; color: var(--text); transition: background 0.3s, color 0.3s; }
        .admin-wrapper { max-width: 1600px; margin: 0 auto; padding: 2rem 1.5rem; }
        .admin-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; background: var(--card-bg); padding: 1rem 2rem; border-radius: 2rem; margin-bottom: 1rem; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid var(--border); }
        .logo-area h1 { color: #ea580c; font-size: 1.8rem; }
        .logo-area p { color: var(--text); font-size: 0.85rem; }
        .user-info { background: var(--bg); padding: 0.5rem 1.2rem; border-radius: 2rem; color: var(--text); display: flex; align-items: center; gap: 12px; }
        .logout-btn { background: #ea580c; border: none; padding: 8px 18px; border-radius: 40px; color: white; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
        .logout-btn:hover { background: #c2410c; transform: scale(1.02); }
        .admin-nav { background: var(--card-bg); border-radius: 2rem; margin-bottom: 2rem; padding: 0.75rem 1.5rem; border: 1px solid var(--border); display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
        .nav-link { display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 40px; background: var(--bg); color: var(--text); text-decoration: none; font-weight: 500; transition: all 0.2s; border: 1px solid var(--border); }
        .nav-link i { color: #ea580c; }
        .nav-link:hover { background: #ea580c; color: white; border-color: #ea580c; transform: translateY(-2px); }
        .nav-link:hover i { color: white; }
        .stats-grid { display: flex; gap: 1.2rem; flex-wrap: wrap; margin-bottom: 2rem; }
        .stat-card { background: var(--card-bg); padding: 1rem 1.8rem; border-radius: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border-left: 4px solid #ea580c; color: var(--text); font-weight: 500; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 10px; border: 1px solid var(--border); }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); border-left-color: #c2410c; }
        .stat-card i { font-size: 1.5rem; }
        .stat-card .stat-number { font-size: 1.5rem; font-weight: 700; margin-right: 5px; }
        .view-toggle { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .view-btn { background: var(--card-bg); border: 1px solid var(--border); padding: 8px 24px; border-radius: 40px; color: var(--text); cursor: pointer; transition: 0.2s; }
        .view-btn.active { background: #ea580c; color: white; border-color: #ea580c; }
        .calendar-container { background: var(--card-bg); border-radius: 28px; padding: 1rem; border: 1px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .table-container { background: var(--card-bg); border-radius: 28px; padding: 1rem; overflow-x: auto; border: 1px solid var(--border); display: none; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .filter-bar { display: flex; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; align-items: center; }
        .filter-bar select, .filter-bar input { background: var(--card-bg); color: var(--text); border: 1px solid var(--border); border-radius: 40px; padding: 8px 20px; }
        .btn-primary { background: #ea580c; border: none; padding: 8px 20px; border-radius: 40px; font-weight: 600; color: white; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-primary:hover { background: #c2410c; transform: translateY(-1px); }
        .btn-success { background: #10b981; border: none; padding: 8px 20px; border-radius: 40px; font-weight: 600; color: white; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-success:hover { background: #059669; transform: translateY(-1px); }
        .btn-secondary { background: var(--bg); border: 1px solid var(--border); padding: 8px 20px; border-radius: 40px; color: var(--text); cursor: pointer; }
        table { width: 100%; border-collapse: collapse; color: var(--text); }
        th, td { padding: 1rem 0.8rem; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: var(--bg); color: #ea580c; font-weight: 600; }
        tr:hover { background: #fef3c7; }
        .dark-mode tr:hover { background: #334155; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 40px; font-size: 0.75rem; font-weight: bold; }
        .badge.pending { background: #fef3c7; color: #b45309; }
        .badge.confirmed { background: #d1fae5; color: #065f46; }
        .badge.rejected { background: #fee2e2; color: #991b1b; }
        .badge.conflict { background: #f3e8ff; color: #6b21a5; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { opacity: 0.8; } 50% { opacity: 1; background: #e9d5ff; } 100% { opacity: 0.8; } }
        .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
        .action-buttons button { background: var(--bg); border: none; width: 32px; height: 32px; border-radius: 12px; cursor: pointer; font-size: 1rem; transition: 0.2s; color: var(--text); }
        .confirm-btn { color: #10b981; }
        .reject-btn { color: #ea580c; }
        .delete-btn { color: #ef4444; }
        .edit-btn { color: #3b82f6; }
        .conflict-btn { color: #8b5cf6; background: #ede9fe; }
        .action-buttons button:hover { transform: scale(1.1); background: var(--border); }
        
        /* Floating Chat Button */
        .ai-chat-btn { position: fixed; bottom: 30px; right: 30px; background: #ea580c; width: 65px; height: 65px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 25px rgba(234, 88, 12, 0.4); cursor: pointer; z-index: 1000; border: none; color: white; font-size: 28px; transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .ai-chat-btn:hover { transform: scale(1.1); background: #c2410c; }
        
        /* General Modals */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 2000; justify-content: center; align-items: center; }
        .modal-content { background: var(--card-bg); border-radius: 24px; width: 600px; max-width: 94%; max-height: 90vh; overflow-y: auto; border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.25); color: var(--text); transition: all 0.3s ease;}
        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { color: #ea580c; margin: 0; font-size: 1.4rem;}
        .close-modal { background: none; border: none; font-size: 1.8rem; cursor: pointer; color: #94a3b8; line-height: 1; transition: 0.2s;}
        .close-modal:hover { color: #ea580c; transform: scale(1.1); }

        /* ===== MASSIVE ENHANCED AI CHAT UI ===== */
        #aiChatModal .modal-content { 
            width: 1000px; 
            height: 85vh; 
            max-width: 95vw; 
            display: flex; 
            flex-direction: column; 
            overflow: hidden; 
            padding: 0; 
            background: var(--bg);
            border: 1px solid var(--border);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        #aiChatModal .modal-content.fullscreen {
            width: 100vw;
            height: 100vh;
            max-width: 100vw;
            border-radius: 0;
        }
        #aiChatModal .modal-header { 
            flex-shrink: 0; 
            padding: 1.2rem 1.5rem; 
            background: var(--card-bg);
            border-bottom: 1px solid var(--border);
        }
        .chat-header-actions { display: flex; gap: 12px; align-items: center; }
        .chat-header-actions button { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #94a3b8; transition: 0.2s; }
        .chat-header-actions button:hover { color: #ea580c; transform: scale(1.1); }
        .chat-header-actions .clear-chat-btn { font-size: 0.95rem; background: var(--bg); border: 1px solid var(--border); padding: 6px 12px; border-radius: 20px; display: flex; align-items: center; gap: 6px; }
        .chat-header-actions .clear-chat-btn:hover { background: #ef4444; color: white; border-color: #ef4444; }

        #aiChatModal .chat-messages { 
            flex: 1; 
            padding: 1.5rem; 
            display: flex; flex-direction: column; gap: 20px; 
            overflow-y: auto; 
            background: var(--bg);
            scroll-behavior: smooth; 
        }

        .chat-msg-wrapper { 
            display: flex; gap: 14px; margin-bottom: 10px; 
            animation: slideUpFade 0.3s ease forwards; 
            max-width: 85%;
        }
        @keyframes slideUpFade { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .chat-msg-wrapper.user-wrapper { flex-direction: row-reverse; align-self: flex-end; }
        .chat-msg-wrapper.ai-wrapper { align-self: flex-start; }
        
        .chat-avatar { 
            width: 40px; height: 40px; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 1.1rem; color: white; flex-shrink: 0; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
        }
        .ai-avatar { background: linear-gradient(135deg, #ea580c, #c2410c); }
        .user-avatar { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        
        .chat-bubble { 
            padding: 14px 20px; border-radius: 20px; 
            font-size: 1.05rem; line-height: 1.6; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.05); position: relative; 
            word-wrap: break-word;
        }
        .chat-msg-wrapper.user-wrapper .chat-bubble { background: #ea580c; color: white; border-top-right-radius: 4px; border: none; }
        .chat-msg-wrapper.ai-wrapper .chat-bubble { background: var(--card-bg); color: var(--text); border: 1px solid var(--border); border-top-left-radius: 4px; width: 100%; }

        .chat-msg-wrapper.ai-wrapper .chat-bubble h1, 
        .chat-msg-wrapper.ai-wrapper .chat-bubble h2, 
        .chat-msg-wrapper.ai-wrapper .chat-bubble h3 { color: #ea580c; margin-top: 10px; margin-bottom: 8px; font-weight: 700; font-size: 1.15em; }
        .chat-msg-wrapper.ai-wrapper .chat-bubble h1:first-child, 
        .chat-msg-wrapper.ai-wrapper .chat-bubble h2:first-child, 
        .chat-msg-wrapper.ai-wrapper .chat-bubble h3:first-child { margin-top: 0; }
        .chat-msg-wrapper.ai-wrapper .chat-bubble p { margin-bottom: 10px; }
        .chat-msg-wrapper.ai-wrapper .chat-bubble p:last-child { margin-bottom: 0; }
        .chat-msg-wrapper.ai-wrapper .chat-bubble ul, 
        .chat-msg-wrapper.ai-wrapper .chat-bubble ol { margin-left: 20px; margin-bottom: 10px; }
        .chat-msg-wrapper.ai-wrapper .chat-bubble li { margin-bottom: 4px; }
        .chat-msg-wrapper.ai-wrapper .chat-bubble a { color: #3b82f6; text-decoration: underline; }
        .chat-msg-wrapper.ai-wrapper .chat-bubble strong { color: #ea580c; font-weight: bold; }
        .chat-msg-wrapper.ai-wrapper .chat-bubble code { background: rgba(128,128,128,0.1); padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 0.9em; border: 1px solid var(--border); }
        .chat-msg-wrapper.ai-wrapper .chat-bubble pre { background: #1e293b; color: #f8fafc; padding: 12px; border-radius: 8px; overflow-x: auto; margin-bottom: 10px; border: 1px solid #334155;}
        .chat-msg-wrapper.ai-wrapper .chat-bubble pre code { background: transparent; padding: 0; border: none; color: inherit; }
        .chat-msg-wrapper.ai-wrapper .chat-bubble table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .chat-msg-wrapper.ai-wrapper .chat-bubble th, .chat-msg-wrapper.ai-wrapper .chat-bubble td { border: 1px solid var(--border); padding: 8px; }
        .chat-msg-wrapper.ai-wrapper .chat-bubble th { background: rgba(234, 88, 12, 0.1); color: #ea580c; }

        .chat-time { font-size: 0.75rem; color: #94a3b8; margin-top: 6px; font-weight: 500; }
        .chat-msg-wrapper.user-wrapper .chat-time { text-align: right; }
        
        .copy-btn { margin-top: 8px; display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; background: var(--bg); border: 1px solid var(--border); padding: 6px 12px; border-radius: 8px; cursor: pointer; color: var(--text); transition: 0.2s; font-weight: 500; }
        .copy-btn:hover { background: #ea580c; color: white; border-color: #ea580c; }

        .typing-dots { display: inline-flex; gap: 5px; align-items: center; height: 24px; padding: 0 5px; }
        .typing-dots span { width: 8px; height: 8px; background: var(--text); border-radius: 50%; opacity: 0.4; animation: blink 1.4s infinite both; }
        .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
        .typing-dots span:nth-child(3) { animation-delay: 0.4s; }

        #aiChatModal .chat-input-area { 
            display: flex; padding: 1.2rem 1.5rem; gap: 12px; 
            border-top: 1px solid var(--border); background: var(--card-bg); flex-shrink: 0; 
            align-items: flex-end;
        }
        #aiChatModal .chat-input-area textarea { 
            flex: 1; padding: 14px 20px; border-radius: 20px; border: 1px solid var(--border); 
            background: var(--bg); color: var(--text); outline: none; font-size: 1.05rem; transition: border-color 0.2s;
            resize: none; overflow-y: hidden; min-height: 52px; max-height: 200px; font-family: inherit; line-height: 1.5;
        }
        #aiChatModal .chat-input-area textarea:focus { border-color: #ea580c; box-shadow: 0 0 0 3px rgba(234,88,12,0.1); }
        #aiChatModal .chat-input-area textarea:disabled { opacity: 0.6; cursor: not-allowed; }
        #aiChatModal .chat-input-area button { 
            background: #ea580c; border: none; padding: 0 28px; border-radius: 20px; 
            color: white; cursor: pointer; font-size: 1.05rem; font-weight: bold; transition: 0.2s; 
            display: flex; align-items: center; gap: 8px; height: 52px;
        }
        #aiChatModal .chat-input-area button:hover:not(:disabled) { background: #c2410c; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(234,88,12,0.3); }
        #aiChatModal .chat-input-area button:disabled { background: #94a3b8; cursor: not-allowed; transform: none; box-shadow: none; }
        
        #aiChatModal .chat-messages::-webkit-scrollbar { width: 8px; }
        #aiChatModal .chat-messages::-webkit-scrollbar-track { background: transparent; }
        #aiChatModal .chat-messages::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .dark-mode #aiChatModal .chat-messages::-webkit-scrollbar-thumb { background: #475569; }

        .conflict-list { list-style: none; padding: 1rem; }
        .conflict-list li { background: var(--bg); padding: 0.75rem; margin-bottom: 0.5rem; border-radius: 16px; border-left: 3px solid #8b5cf6; color: var(--text); }
        
        .toast-message { position: fixed; bottom: 30px; right: 30px; background: #10b981; color: white; padding: 14px 28px; border-radius: 60px; font-weight: bold; z-index: 3000; animation: fadeInOut 3s forwards; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .toast-error { background: #dc2626; }
        @keyframes fadeInOut { 0% { opacity: 0; transform: translateX(30px); } 15% { opacity: 1; transform: translateX(0); } 85% { opacity: 1; transform: translateX(0); } 100% { opacity: 0; visibility: hidden; transform: translateX(30px); } }
        
        .pagination { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 1.5rem; }
        .pagination button { background: var(--card-bg); border: 1px solid var(--border); padding: 8px 16px; border-radius: 40px; color: var(--text); cursor: pointer; transition: 0.2s;}
        .pagination button:hover:not(:disabled) { background: #ea580c; color: white; border-color: #ea580c; }
        .pagination button:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 6px; color: var(--text); }
        .form-group select, .form-group input, .form-group textarea { width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 1rem;}
        .form-group textarea { resize: vertical; }
        .conflict-warning { background: #fef3c7; border: 1px solid #f59e0b; padding: 12px; border-radius: 12px; color: #92400e; margin-bottom: 1rem; }
        .dark-mode .conflict-warning { background: rgba(245,158,11,0.1); color: #fbbf24; border-color: #f59e0b; }
        
        @media (max-width: 768px) {
            .filter-bar { flex-direction: column; align-items: stretch; }
            .action-buttons button { width: 32px; height: 32px; font-size: 0.9rem; }
            #aiChatModal .modal-content { height: 100vh; width: 100vw; border-radius: 0; max-height: none; }
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <div class="admin-header">
        <div class="logo-area">
            <h1><i class="fas fa-calendar-alt"></i> Booking Conflict Manager</h1>
            <p>🟣 Purple = Conflict · 🤖 Auto-resolve · ⏰ Auto-reject expired</p>
        </div>
        <div style="display: flex; gap: 1rem; align-items: center;">
            <button class="dark-toggle" id="darkModeToggle" title="Toggle dark mode" style="background:var(--bg); border:1px solid var(--border); padding:8px 14px; border-radius:20px; color:var(--text); cursor:pointer;"><i class="fas fa-moon"></i></button>
            <div class="user-info"><i class="fas fa-user-tie"></i> <?= $currentUser ?> (<?= htmlspecialchars($userRole) ?>)</div>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="admin-nav">
        <a href="venue.manage.php" class="nav-link"><i class="fas fa-building"></i> Venue Management</a>
        <a href="user.manage.php" class="nav-link"><i class="fas fa-users"></i> User Management</a>
        <a href="booking_admin_main_menu.php" class="nav-link active"><i class="fas fa-tachometer-alt"></i> Main Menu</a>
        <a href="manage.venue.comments.php" class="nav-link"><i class="fas fa-comments"></i> Venue Comments</a>
    </div>

    <div class="stats-grid">
        <div class="stat-card" data-filter="pending"><i class="fas fa-spinner"></i><div><span class="stat-number" id="pendingCount">0</span> Pending</div></div>
        <div class="stat-card" data-filter="confirmed"><i class="fas fa-check-circle"></i><div><span class="stat-number" id="confirmedCount">0</span> Confirmed</div></div>
        <div class="stat-card" data-filter="rejected"><i class="fas fa-times-circle"></i><div><span class="stat-number" id="rejectedCount">0</span> Rejected</div></div>
        <div class="stat-card" data-filter="all"><i class="fas fa-chart-line"></i><div><span class="stat-number" id="totalCount">0</span> Total</div></div>
    </div>

    <div class="view-toggle">
        <button class="view-btn active" id="calendarViewBtn"><i class="fas fa-calendar-week"></i> Calendar</button>
        <button class="view-btn" id="tableViewBtn"><i class="fas fa-table"></i> Table</button>
    </div>

    <div class="filter-bar">
        <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <select id="statusFilter">
                <option value="all">All</option>
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
                <option value="rejected">Rejected</option>
            </select>
            <input type="text" id="searchInput" placeholder="Search by student, venue, date..." style="padding:10px 18px; border-radius:40px; border:1px solid var(--border); background:var(--card-bg); color:var(--text); outline:none;">
            <button class="btn-secondary" id="searchBtn"><i class="fas fa-search"></i></button>
            <button class="btn-secondary" id="clearSearchBtn"><i class="fas fa-times"></i></button>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button class="btn-success" id="newBookingBtn"><i class="fas fa-plus-circle"></i> New Booking</button>
            <!-- Export CSV now uses GET with query params -->
            <a href="#" id="exportCsvLink" class="btn-primary" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px; padding:8px 20px; border-radius:40px; background:#ea580c; color:white; font-weight:600;"><i class="fas fa-file-csv"></i> Export CSV</a>
            <button class="btn-primary" id="autoResolveBtn"><i class="fas fa-robot"></i> Auto-resolve</button>
            <button class="btn-primary" id="refreshBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
        </div>
    </div>

    <div id="calendarContainer" class="calendar-container"></div>
    <div id="tableContainer" class="table-container">
        <div style="overflow-x: auto;">
            <table id="bookingsTable">
                <thead><tr><th>ID</th><th>Student</th><th>Email</th><th>Venue</th><th>Date</th><th>Time</th><th>Duration</th><th>Comment</th><th>Status</th><th>Conflicts</th><th>Actions</th></tr></thead>
                <tbody id="bookingsTableBody"><tr><td colspan="11">Loading...</td></tr></tbody>
            </table>
        </div>
        <div class="pagination" id="paginationControls">
            <button id="prevPageBtn" disabled><i class="fas fa-chevron-left"></i> Prev</button>
            <span id="pageInfo" style="font-weight: 600;">Page 1</span>
            <button id="nextPageBtn">Next <i class="fas fa-chevron-right"></i></button>
        </div>
    </div>
</div>

<!-- Booking Form Modal (Create / Edit) -->
<div id="bookingFormModal" class="modal">
    <div class="modal-content" style="width: 500px; height: auto;">
        <div class="modal-header">
            <h3 id="bookingFormTitle"><i class="fas fa-calendar-plus"></i> New Booking</h3>
            <button class="close-modal" id="closeBookingFormBtn">&times;</button>
        </div>
        <div style="padding: 1.5rem;">
            <div id="formConflicts" class="conflict-warning" style="display:none;"></div>
            <form id="bookingForm">
                <input type="hidden" name="booking_id" id="editBookingId" value="0">
                <div class="form-group">
                    <label for="user_id">Student</label>
                    <select id="user_id" name="user_id" required>
                        <option value="">Select Student</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="venue_id">Venue</label>
                    <select id="venue_id" name="venue_id" required>
                        <option value="">Select Venue</option>
                        <?php foreach ($venues as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="booking_date">Date</label>
                    <input type="date" id="booking_date" name="booking_date" required>
                </div>
                <div class="form-group">
                    <label for="start_time">Start Time</label>
                    <input type="time" id="start_time" name="start_time" required>
                </div>
                <div class="form-group">
                    <label for="duration_hours">Duration (hours)</label>
                    <input type="number" id="duration_hours" name="duration_hours" min="0.5" max="4" step="0.5" required>
                </div>
                <div class="form-group">
                    <label for="comment">Comment</label>
                    <textarea id="comment" name="comment" rows="3"></textarea>
                </div>
                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:1.5rem;">
                    <button type="button" class="btn-secondary" id="cancelBookingFormBtn">Cancel</button>
                    <button type="submit" class="btn-success" id="submitBookingBtn"><i class="fas fa-save"></i> Save Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Floating AI Chat Button -->
<button class="ai-chat-btn" id="openAIChatBtn" title="Ask AI Assistant"><i class="fas fa-robot"></i></button>

<!-- Enhanced AI Chat Box Modal (no recommendations) -->
<div id="aiChatModal" class="modal">
    <div class="modal-content" id="aiChatModalContent">
        <div class="modal-header">
            <h3><i class="fas fa-robot" style="color: #ea580c;"></i> UBook AI Assistant</h3>
            <div class="chat-header-actions">
                <button class="clear-chat-btn" id="clearChatBtn" title="Clear Chat History"><i class="fas fa-trash"></i> Clear Chat</button>
                <button id="toggleChatFullscreenBtn" title="Toggle Fullscreen"><i class="fas fa-expand"></i></button>
                <button class="close-modal" id="closeAIChatBtn" title="Close"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="chat-messages" id="chatMessages">
            <!-- Messages rendered dynamically here via JS -->
        </div>
        <div class="chat-input-area">
            <textarea id="chatInput" placeholder="Type your instruction or question here... (Shift+Enter for new line)" autocomplete="off"></textarea>
            <button id="sendChatBtn">Send <i class="fas fa-paper-plane"></i></button>
        </div>
    </div>
</div>

<!-- Conflict Detail Modal -->
<div id="conflictModal" class="modal">
    <div class="modal-content" style="width: 500px; height: auto;">
        <div class="modal-header">
            <h3>⚠️ Conflict Details</h3>
            <button class="close-modal" id="closeConflictModalBtn">&times;</button>
        </div>
        <ul id="conflictList" class="conflict-list"></ul>
        <div style="padding: 1rem; text-align: right;">
            <button class="btn-secondary" id="modalCloseBtn">Close</button>
        </div>
    </div>
</div>

<script>
// ===== Set up Markdown parser =====
if (typeof marked !== 'undefined') {
    marked.setOptions({ breaks: true, gfm: true });
}

// ===== Dark Mode =====
const darkToggle = document.getElementById('darkModeToggle');
if (localStorage.getItem('darkMode') === 'true') {
    document.body.classList.add('dark-mode');
    darkToggle.innerHTML = '<i class="fas fa-sun"></i>';
}
darkToggle.addEventListener('click', function() {
    document.body.classList.toggle('dark-mode');
    const isDark = document.body.classList.contains('dark-mode');
    localStorage.setItem('darkMode', isDark);
    this.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
});

// ===== Toast & Helpers =====
function showToast(msg, isError = false) { 
    let t = document.createElement('div'); 
    t.className = 'toast-message' + (isError ? ' toast-error' : ''); 
    t.innerHTML = isError ? `<i class="fas fa-exclamation-circle"></i> ${msg}` : `<i class="fas fa-check-circle"></i> ${msg}`; 
    document.body.appendChild(t); 
    setTimeout(() => t.remove(), 4000); 
}

function escapeHtml(s) { 
    if(!s) return ''; 
    return s.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m]); 
}

function closeModals() { 
    document.getElementById('conflictModal').style.display='none'; 
    document.getElementById('aiChatModal').style.display='none'; 
    document.getElementById('bookingFormModal').style.display='none';
}
document.getElementById('closeConflictModalBtn')?.addEventListener('click',closeModals);
document.getElementById('modalCloseBtn')?.addEventListener('click',closeModals);
document.getElementById('closeBookingFormBtn')?.addEventListener('click',closeModals);
document.getElementById('cancelBookingFormBtn')?.addEventListener('click',closeModals);
window.addEventListener('click', e => { 
    if(e.target === document.getElementById('conflictModal')) closeModals(); 
    if(e.target === document.getElementById('aiChatModal')) closeModals();
    if(e.target === document.getElementById('bookingFormModal')) closeModals();
});

// ===== AI CHAT LOGIC, MARKDOWN, AND HISTORY PERSISTENCE =====
function parseMarkdown(text) {
    if (typeof marked !== 'undefined' && typeof DOMPurify !== 'undefined') {
        return DOMPurify.sanitize(marked.parse(text));
    }
    let html = escapeHtml(text);
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
    html = html.replace(/`(.*?)`/g, '<code style="background:rgba(128,128,128,0.2);padding:2px 6px;border-radius:4px;font-family:monospace;">$1</code>');
    return html.replace(/\n/g, '<br>');
}

function getCurrentTimeFormatted() {
    return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

let aiChatHistoryAPI = [];

function loadChatHistory() {
    const chatDiv = document.getElementById('chatMessages');
    chatDiv.innerHTML = '';
    aiChatHistoryAPI = [];
    
    const saved = sessionStorage.getItem('ubookAIChatHistory');
    let loadedHistory = [];
    
    if (saved) {
        try { loadedHistory = JSON.parse(saved); } catch (e) { loadedHistory = []; }
    }
    
    if (loadedHistory.length === 0) {
        const welcomeMsg = "👋 **Hello Admin! I am your UBook AI Assistant.**\n\nI can help you with tasks like:\n- Identify and analyze booking conflicts.\n- View venue statistics and user comments.\n- Execute commands (e.g., _\"Confirm booking #5\"_ or _\"Reject booking #12\"_).\n- Automatically send custom emails.\n\nHow can I assist you today?";
        appendMessage('ai', welcomeMsg, false, true);
    } else {
        loadedHistory.forEach(msg => {
            appendMessage(msg.role === 'assistant' ? 'ai' : 'user', msg.content, false, false);
            aiChatHistoryAPI.push({role: msg.role, content: msg.content});
        });
    }
}

function saveChatHistory() {
    sessionStorage.setItem('ubookAIChatHistory', JSON.stringify(aiChatHistoryAPI));
}

function appendMessage(role, text, isTyping = false, saveToHistory = true) {
    const chatDiv = document.getElementById('chatMessages');
    
    const wrapper = document.createElement('div');
    wrapper.className = `chat-msg-wrapper ${role === 'user' ? 'user-wrapper' : 'ai-wrapper'}`;
    if (isTyping) wrapper.id = 'typingIndicator';
    
    const avatar = document.createElement('div');
    avatar.className = `chat-avatar ${role === 'user' ? 'user-avatar' : 'ai-avatar'}`;
    avatar.innerHTML = role === 'user' ? '<i class="fas fa-user"></i>' : '<i class="fas fa-robot"></i>';
    
    const bubbleContainer = document.createElement('div');
    bubbleContainer.style.display = 'flex';
    bubbleContainer.style.flexDirection = 'column';
    bubbleContainer.style.maxWidth = '100%';
    bubbleContainer.style.alignItems = (role === 'user') ? 'flex-end' : 'flex-start';
    
    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble';
    
    if (isTyping) {
        bubble.innerHTML = '<div class="typing-dots"><span></span><span></span><span></span></div>';
    } else {
        if (role === 'ai') {
            bubble.innerHTML = parseMarkdown(text);
        } else {
            bubble.innerText = text;
        }
    }
    
    bubbleContainer.appendChild(bubble);
    
    if (!isTyping) {
        const timeEl = document.createElement('div');
        timeEl.className = 'chat-time';
        timeEl.innerText = getCurrentTimeFormatted();
        bubbleContainer.appendChild(timeEl);
        
        if (role === 'ai') {
            const copyBtn = document.createElement('button');
            copyBtn.className = 'copy-btn';
            copyBtn.innerHTML = '<i class="far fa-copy"></i> Copy';
            copyBtn.onclick = () => {
                navigator.clipboard.writeText(text).then(() => {
                    copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                    copyBtn.style.color = '#10b981';
                    copyBtn.style.borderColor = '#10b981';
                    setTimeout(() => { 
                        copyBtn.innerHTML = '<i class="far fa-copy"></i> Copy'; 
                        copyBtn.style.color = '';
                        copyBtn.style.borderColor = '';
                    }, 2000);
                });
            };
            bubbleContainer.appendChild(copyBtn);
        }
    }
    
    wrapper.appendChild(avatar);
    wrapper.appendChild(bubbleContainer);
    chatDiv.appendChild(wrapper);
    chatDiv.scrollTop = chatDiv.scrollHeight;
    
    if (saveToHistory && !isTyping) {
        aiChatHistoryAPI.push({ role: role === 'ai' ? 'assistant' : 'user', content: text });
        saveChatHistory();
    }
}

document.getElementById('openAIChatBtn').onclick = () => {
    document.getElementById('aiChatModal').style.display='flex';
    document.getElementById('chatInput').focus();
    loadChatHistory();
    let chatDiv = document.getElementById('chatMessages');
    chatDiv.scrollTop = chatDiv.scrollHeight;
};

document.getElementById('closeAIChatBtn').onclick = closeModals;

document.getElementById('clearChatBtn').onclick = () => {
    if(confirm('Clear chat history?')) {
        sessionStorage.removeItem('ubookAIChatHistory');
        loadChatHistory();
    }
};

document.getElementById('toggleChatFullscreenBtn').onclick = function() {
    const modal = document.getElementById('aiChatModalContent');
    modal.classList.toggle('fullscreen');
    this.innerHTML = modal.classList.contains('fullscreen') ? '<i class="fas fa-compress"></i>' : '<i class="fas fa-expand"></i>';
};

const chatInput = document.getElementById('chatInput');
chatInput.addEventListener('input', function() {
    this.style.height = '52px';
    this.style.height = (this.scrollHeight) + 'px';
});
chatInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendAIQuery();
    }
});

async function sendAIQuery(text) {
    let q = text || chatInput.value.trim();
    if(!q) return;
    
    chatInput.value = '';
    chatInput.style.height = '52px';
    chatInput.disabled = true;
    document.getElementById('sendChatBtn').disabled = true;
    
    appendMessage('user', q, false, true);
    appendMessage('ai', '', true, false);
    
    try {
        let reqBody = new URLSearchParams({
            action: 'ai_query', 
            question: q,
            history: JSON.stringify(aiChatHistoryAPI.slice(0, -1))
        });
        
        let resp = await fetch(window.location.href, {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body: reqBody
        });
        let data = await resp.json();
        
        let typingIndicator = document.getElementById('typingIndicator');
        if (typingIndicator) typingIndicator.remove();
        
        let aiText = data.success ? data.answer : '**Error:** ' + data.message;
        let actionExecuted = false;
        
        let jsonMatches = aiText.match(/\{[\s\S]*?"action"\s*:\s*"(send_email|update_status)"[\s\S]*?\}/g);
        if(jsonMatches) {
            for(let jsonStr of jsonMatches) {
                try {
                    let cmd = JSON.parse(jsonStr);
                    if(cmd.action === 'send_email' && cmd.to && cmd.subject && cmd.body) {
                        let formData = new URLSearchParams();
                        formData.append('action','send_custom_email');
                        formData.append('to', cmd.to);
                        formData.append('name', cmd.name || '');
                        formData.append('subject', cmd.subject);
                        formData.append('body', cmd.body);
                        let emailResp = await fetch(window.location.href, {
                            method:'POST',
                            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
                            body: formData
                        });
                        let emailData = await emailResp.json();
                        if(emailData.success) {
                            showToast('✅ Email automatically sent to ' + cmd.to);
                            actionExecuted = true;
                        } else {
                            showToast('❌ Auto-email failed: ' + emailData.message, true);
                        }
                    } else if(cmd.action === 'update_status' && cmd.booking_id && cmd.status) {
                        await updateStatus(cmd.booking_id, cmd.status);
                        actionExecuted = true;
                    }
                } catch(e) {}
            }
        }
        
        let displayText = aiText.replace(/\{[\s\S]*?"action"\s*:\s*"(send_email|update_status)"[\s\S]*?\}/g, '').trim();
        if(!displayText) {
            displayText = actionExecuted ? '✅ **Action executed successfully as requested.**' : aiText;
        }
        
        appendMessage('ai', displayText, false, true);
        
    } catch(e) { 
        let typingIndicator = document.getElementById('typingIndicator');
        if (typingIndicator) typingIndicator.remove();
        appendMessage('ai', '⚠️ **Network error communicating with AI.**', false, true);
    }
    
    chatInput.disabled = false;
    document.getElementById('sendChatBtn').disabled = false;
    chatInput.focus();
}

document.getElementById('sendChatBtn').addEventListener('click', () => sendAIQuery());

let currentPage = 1;
let totalPages = 1;
let currentSearch = '';
let currentStatus = 'all';

async function updateStatus(id, newStatus) {
    if(!confirm(`Are you sure to ${newStatus} this booking? An email will automatically be sent to the student.`)) return;
    try {
        let resp = await fetch(window.location.href, {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body:new URLSearchParams({action:'update_status', booking_id:id, status:newStatus})
        });
        let data = await resp.json();
        if(data.success) { 
            showToast(data.message); 
            if(currentView==='calendar') { loadCalendarEvents(); loadTableBookings(); } 
            else loadTableBookings(); 
        } else { 
            showToast(data.message, true); 
            if(data.conflicts) showConflictModal(data.conflicts); 
        }
    } catch(e) { showToast('Network error', true); }
}

// ===== Calendar =====
let calendar = null, currentView = 'calendar';
async function loadCalendarEvents() {
    try {
        let resp = await fetch(window.location.href, {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body:new URLSearchParams({action:'get_calendar_events'})
        });
        let data = await resp.json();
        if(data.success && calendar) { 
            calendar.removeAllEvents(); 
            calendar.addEventSource(data.events); 
            if(data.stats) updateStats(data.stats);
        } else {
            showToast('Failed to load calendar data', true);
        }
    } catch(e) { showToast('Network error loading calendar', true); }
}

function initCalendar() {
    let calEl = document.getElementById('calendarContainer');
    calendar = new FullCalendar.Calendar(calEl, {
        initialView:'dayGridMonth', 
        headerToolbar:{left:'prev,next today',center:'title',right:'dayGridMonth,timeGridWeek,timeGridDay'},
        events:[], 
        eventClick:function(info) { showBookingActions(info.event.id, info.event.extendedProps); },
        eventDidMount:function(info) { if(info.event.extendedProps.hasConflict) info.el.style.animation='pulse 1.5s infinite'; },
        height:'auto', slotMinTime:'08:00:00', slotMaxTime:'22:00:00', nowIndicator:true
    });
    calendar.render(); 
    loadCalendarEvents();
}

function showBookingActions(id, props) {
    let status = props.status, hasConflict = props.hasConflict, reasons = props.conflictReasons || [];
    let modalDiv = document.createElement('div'); 
    modalDiv.className = 'modal'; 
    modalDiv.style.display = 'flex';
    modalDiv.innerHTML = `<div class="modal-content" style="width:500px; height:auto;">
        <div class="modal-header">
            <h3>Booking #${id}</h3>
            <button class="close-modal" id="quickCloseModal">&times;</button>
        </div>
        <div style="padding:1.5rem; font-size:1.05rem;">
            <p style="margin-bottom:8px;"><strong>${escapeHtml(props.student)}</strong> @ ${escapeHtml(props.venue)}</p>
            <p style="margin-bottom:8px; color:#64748b;">📅 ${props.date} · ⏰ ${props.time} (${props.duration}h)</p>
            <p style="margin-bottom:12px;">💬 ${escapeHtml(props.comment) || '<em>No comment</em>'}</p>
            <p style="margin-bottom:16px;">Status: <span class="badge ${status}">${status.toUpperCase()}</span></p>
            ${hasConflict ? `<div style="margin-bottom:16px; padding:12px; background:#f3e8ff; border-radius:8px; border-left:4px solid #8b5cf6;"><strong>⚠️ Conflicts Found:</strong><ul style="margin-left:20px; margin-top:5px;">${reasons.map(r=>`<li>${escapeHtml(r)}</li>`).join('')}</ul></div>` : ''}
            <div style="display:flex; gap:10px; margin-top:1.5rem; flex-wrap:wrap; justify-content: flex-end;">
                ${status !== 'confirmed' ? `<button class="btn-success" id="quickConfirmBtn"><i class="fas fa-check"></i> Confirm</button>` : ''}
                ${status !== 'rejected' ? `<button class="btn-primary" id="quickRejectBtn" style="background:#ef4444;"><i class="fas fa-times"></i> Reject</button>` : ''}
                <button class="btn-primary" id="quickEditBtn" style="background:#3b82f6;"><i class="fas fa-edit"></i> Edit</button>
                <button class="btn-primary" id="quickDeleteBtn" style="background:#64748b;"><i class="fas fa-trash"></i> Delete</button>
            </div>
        </div>
    </div>`;
    document.body.appendChild(modalDiv);
    let closeModal = () => document.body.removeChild(modalDiv);
    modalDiv.querySelector('#quickCloseModal').onclick = closeModal;
    modalDiv.addEventListener('click', (e) => { if(e.target === modalDiv) closeModal(); });

    if(modalDiv.querySelector('#quickConfirmBtn')) modalDiv.querySelector('#quickConfirmBtn').onclick=()=>{ updateStatus(id,'confirmed'); closeModal(); };
    if(modalDiv.querySelector('#quickRejectBtn')) modalDiv.querySelector('#quickRejectBtn').onclick=()=>{ updateStatus(id,'rejected'); closeModal(); };
    if(modalDiv.querySelector('#quickEditBtn')) modalDiv.querySelector('#quickEditBtn').onclick=()=>{ closeModal(); openEditBooking(id); };
    if(modalDiv.querySelector('#quickDeleteBtn')) modalDiv.querySelector('#quickDeleteBtn').onclick=()=>{ deleteBooking(id); closeModal(); };
}

// ===== Table with Pagination & Search =====
async function loadTableBookings(page = currentPage) {
    let status = document.getElementById('statusFilter').value;
    let search = document.getElementById('searchInput').value.trim();
    currentStatus = status;
    currentSearch = search;
    try {
        let resp = await fetch(window.location.href, {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body:new URLSearchParams({action:'get_bookings', status:status, search:search, page:page})
        });
        let data = await resp.json();
        if(data.success) { 
            renderTable(data.bookings); 
            updateStats(data.stats);
            totalPages = Math.ceil(data.total / data.limit) || 1;
            currentPage = data.page;
            document.getElementById('pageInfo').innerText = `Page ${currentPage} of ${totalPages}`;
            document.getElementById('prevPageBtn').disabled = (currentPage <= 1);
            document.getElementById('nextPageBtn').disabled = (currentPage >= totalPages);
        } else showToast('Error loading table', true);
    } catch(e) { showToast('Network error loading table', true); }
}

function updateStats(stats) {
    if (!stats) return;
    document.getElementById('pendingCount').innerText = stats.pending || 0;
    document.getElementById('confirmedCount').innerText = stats.confirmed || 0;
    document.getElementById('rejectedCount').innerText = stats.rejected || 0;
    document.getElementById('totalCount').innerText = stats.all || 0;
}

function renderTable(bookings) {
    let tbody = document.getElementById('bookingsTableBody');
    if(!bookings.length) { tbody.innerHTML='<tr><td colspan="11" style="text-align:center; padding:2rem;">No bookings found.</td></tr>'; return; }
    let html = '';
    bookings.forEach(b => {
        let conflictBadge = b.has_conflicts ? '<span class="badge conflict" title="'+escapeHtml(b.conflict_reasons.join('; '))+'"><i class="fas fa-exclamation-triangle"></i> Conflict</span>' : '<span class="badge" style="background:#e2e8f0;color:#475569;">OK</span>';
        html += `<tr>
            <td>${b.id}</td>
            <td><strong>${escapeHtml(b.student_name)}</strong></td>
            <td>${escapeHtml(b.user_email || '—')}</td>
            <td style="font-weight:500; color:#ea580c;">${escapeHtml(b.venue_name)}</td>
            <td>${b.booking_date}</td>
            <td>${b.start_time}</td>
            <td>${b.duration_hours}h</td>
            <td>${escapeHtml(b.comment) || '—'}</td>
            <td><span class="badge ${b.status}">${b.status}</span></td>
            <td>${conflictBadge}</td>
            <td class="action-buttons">
                <button class="conflict-btn" data-id="${b.id}" title="Check conflicts"><i class="fas fa-search"></i></button>
                ${b.status !== 'confirmed' ? `<button class="confirm-btn" data-id="${b.id}" title="Confirm"><i class="fas fa-check-circle"></i></button>` : ''}
                ${b.status !== 'rejected' ? `<button class="reject-btn" data-id="${b.id}" title="Reject"><i class="fas fa-times-circle"></i></button>` : ''}
                <button class="edit-btn" data-id="${b.id}" title="Edit"><i class="fas fa-pencil-alt"></i></button>
                <button class="delete-btn" data-id="${b.id}" title="Delete"><i class="fas fa-trash-alt"></i></button>
            </td>
        </tr>`;
    });
    tbody.innerHTML = html;
    
    document.querySelectorAll('.confirm-btn').forEach(btn => btn.addEventListener('click',()=>updateStatus(btn.dataset.id,'confirmed')));
    document.querySelectorAll('.reject-btn').forEach(btn => btn.addEventListener('click',()=>updateStatus(btn.dataset.id,'rejected')));
    document.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click',()=>openEditBooking(btn.dataset.id)));
    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click',()=>deleteBooking(btn.dataset.id)));
    document.querySelectorAll('.conflict-btn').forEach(btn => btn.addEventListener('click',()=>checkConflicts(btn.dataset.id)));
}

async function deleteBooking(id) {
    if(!confirm('Permanently delete this booking record? This action cannot be undone.')) return;
    try {
        let resp = await fetch(window.location.href, {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body:new URLSearchParams({action:'delete_booking', booking_id:id})
        });
        let data = await resp.json();
        if(data.success) { 
            showToast('Booking deleted'); 
            if(currentView==='calendar') { loadCalendarEvents(); loadTableBookings(); } else loadTableBookings(); 
        } else showToast(data.message, true);
    } catch(e) { showToast('Network error', true); }
}

async function checkConflicts(id) {
    try {
        let resp = await fetch(window.location.href, {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body:new URLSearchParams({action:'check_conflicts', booking_id:id})
        });
        let data = await resp.json();
        if(data.success) { 
            if(data.conflicts && data.conflicts.length) showConflictModal(data.conflicts); 
            else showToast('No conflicts detected for this booking.', false); 
        } else showToast(data.message, true);
    } catch(e) { showToast('Error checking conflicts', true); }
}

function showConflictModal(conflicts) {
    let list = document.getElementById('conflictList');
    list.innerHTML = conflicts.map(c => `<li>⚠️ ${escapeHtml(c)}</li>`).join('');
    document.getElementById('conflictModal').style.display='flex';
}

async function autoResolveConflicts() {
    if(!confirm('Auto-reject all pending bookings that currently have conflicts? Automatic emails will be sent to the affected students.')) return;
    try {
        let resp = await fetch(window.location.href, {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body:new URLSearchParams({action:'auto_resolve_conflicts'})
        });
        let data = await resp.json();
        if(data.success) showToast(`Successfully auto-rejected ${data.rejected} conflicting bookings`);
        else showToast('Auto-resolve failed', true);
        
        loadTableBookings(); 
        if(calendar) loadCalendarEvents();
    } catch(e) { showToast('Network error during auto-resolve', true); }
}

async function autoRejectExpired() {
    try {
        let resp = await fetch(window.location.href, {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body:new URLSearchParams({action:'auto_reject_expired'})
        });
        let data = await resp.json();
        if(data.success && data.rejected > 0) {
            showToast(`✅ System automatically rejected ${data.rejected} expired pending booking(s).`);
            loadTableBookings(); 
            if(calendar) loadCalendarEvents();
        }
    } catch(e) { console.error('Auto-reject error:', e); }
}

// ===== Booking Form (Create/Edit) =====
document.getElementById('newBookingBtn').addEventListener('click', function() {
    document.getElementById('bookingForm').reset();
    document.getElementById('editBookingId').value = '0';
    document.getElementById('bookingFormTitle').innerHTML = '<i class="fas fa-calendar-plus"></i> New Manual Booking';
    document.getElementById('submitBookingBtn').innerHTML = '<i class="fas fa-save"></i> Create Booking';
    document.getElementById('formConflicts').style.display = 'none';
    document.getElementById('formConflicts').innerHTML = '';
    
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('booking_date').value = today;
    document.getElementById('bookingFormModal').style.display='flex';
});

async function openEditBooking(id) {
    try {
        let resp = await fetch(window.location.href, {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body: new URLSearchParams({action:'get_booking', booking_id:id})
        });
        let data = await resp.json();
        if(data.success) {
            let b = data.booking;
            document.getElementById('editBookingId').value = b.id;
            document.getElementById('user_id').value = b.user_id;
            document.getElementById('venue_id').value = b.venue_id;
            document.getElementById('booking_date').value = b.booking_date;
            document.getElementById('start_time').value = b.start_time;
            document.getElementById('duration_hours').value = b.duration_hours;
            document.getElementById('comment').value = b.comment || '';
            
            document.getElementById('bookingFormTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Booking #' + b.id;
            document.getElementById('submitBookingBtn').innerHTML = '<i class="fas fa-save"></i> Update Booking';
            document.getElementById('formConflicts').style.display = 'none';
            document.getElementById('formConflicts').innerHTML = '';
            
            document.getElementById('bookingFormModal').style.display='flex';
        } else {
            showToast('Could not load booking data', true);
        }
    } catch(e) {
        showToast('Network error loading booking', true);
    }
}

document.getElementById('bookingForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const formData = new FormData(form);
    const bookingId = document.getElementById('editBookingId').value;
    const action = bookingId == 0 ? 'create_booking' : 'update_booking';
    
    if (bookingId != 0) formData.append('booking_id', bookingId);
    formData.append('action', action);

    try {
        let resp = await fetch(window.location.href, {
            method:'POST',
            headers:{'X-Requested-With':'XMLHttpRequest'},
            body: formData
        });
        let data = await resp.json();
        if(data.success) {
            showToast(data.message);
            closeModals();
            if(currentView === 'calendar') { loadCalendarEvents(); loadTableBookings(); } else loadTableBookings();
        } else {
            if(data.conflicts) {
                let conflictDiv = document.getElementById('formConflicts');
                conflictDiv.style.display = 'block';
                conflictDiv.innerHTML = '<strong>⚠️ Conflicts Detected:</strong><ul style="margin-left:20px;margin-top:5px;">' + data.conflicts.map(c => '<li>'+escapeHtml(c)+'</li>').join('') + '</ul>';
            } else {
                showToast(data.message, true);
            }
        }
    } catch(e) {
        showToast('Network error saving booking', true);
    }
});

// ===== Export CSV via GET =====
document.getElementById('exportCsvLink').addEventListener('click', function(e) {
    e.preventDefault();
    let status = document.getElementById('statusFilter').value;
    let search = document.getElementById('searchInput').value.trim();
    let url = window.location.pathname + '?export_csv=1&status=' + encodeURIComponent(status) + '&search=' + encodeURIComponent(search);
    window.location.href = url;
});

// ===== Pagination =====
document.getElementById('prevPageBtn').addEventListener('click', () => {
    if (currentPage > 1) loadTableBookings(currentPage - 1);
});
document.getElementById('nextPageBtn').addEventListener('click', () => {
    if (currentPage < totalPages) loadTableBookings(currentPage + 1);
});

// ===== Search =====
document.getElementById('searchBtn').addEventListener('click', () => { currentPage = 1; loadTableBookings(1); });
document.getElementById('searchInput').addEventListener('keypress', e => { if(e.key === 'Enter') { currentPage=1; loadTableBookings(1); } });
document.getElementById('clearSearchBtn').addEventListener('click', () => {
    document.getElementById('searchInput').value = '';
    currentPage = 1;
    loadTableBookings(1);
});

// ===== Stat Cards =====
document.querySelectorAll('.stat-card').forEach(card => {
    card.addEventListener('click', function() {
        let filter = this.getAttribute('data-filter');
        if(filter) {
            document.getElementById('statusFilter').value = filter;
            currentPage = 1;
            if(currentView === 'table') loadTableBookings(1);
            else document.getElementById('tableViewBtn').click();
        }
    });
});

// ===== View Toggle & Actions =====
document.getElementById('autoResolveBtn').addEventListener('click', autoResolveConflicts);

document.getElementById('calendarViewBtn').onclick = () => {
    document.getElementById('calendarContainer').style.display='block';
    document.getElementById('tableContainer').style.display='none';
    document.getElementById('calendarViewBtn').classList.add('active');
    document.getElementById('tableViewBtn').classList.remove('active');
    currentView = 'calendar';
    if(calendar) loadCalendarEvents(); else initCalendar();
};

document.getElementById('tableViewBtn').onclick = () => {
    document.getElementById('calendarContainer').style.display='none';
    document.getElementById('tableContainer').style.display='block';
    document.getElementById('tableViewBtn').classList.add('active');
    document.getElementById('calendarViewBtn').classList.remove('active');
    currentView = 'table';
    loadTableBookings(1);
};

document.getElementById('refreshBtn').onclick = () => {
    if(currentView === 'calendar') {
        loadCalendarEvents();
        loadTableBookings(currentPage);
    } else {
        loadTableBookings(currentPage);
    }
    autoRejectExpired();
};

document.getElementById('statusFilter').addEventListener('change', () => { 
    if(currentView === 'table') { currentPage = 1; loadTableBookings(1); } 
});

// ===== Initialise =====
document.addEventListener('DOMContentLoaded', () => {
    initCalendar();
    loadTableBookings(1);
    document.getElementById('calendarContainer').style.display='block';
    document.getElementById('tableContainer').style.display='none';
    currentView = 'calendar';
    autoRejectExpired();
});
</script>

</body>
</html>