
<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Only super_admin can access this combined dashboard
$allowedRoles = ['super_admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowedRoles)) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define('DEEPSEEK_API_KEY', '');
define('VENUE_OPEN_HOUR', 8);
define('VENUE_CLOSE_HOUR', 22);
define('MAX_DURATION_HOURS', 4);
define('MAX_USER_DAILY_BOOKINGS', 3);

// ==================== MALAYSIAN PUBLIC HOLIDAYS 2026-2030 ====================
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
        if ($conn->connect_error) die(json_encode(['error' => 'DB connection failed: ' . $conn->connect_error]));
        $conn->set_charset("utf8mb4");
    }
    return $conn;
}

function sendBookingStatusEmail($userEmail, $userName, $bookingDetails, $newStatus) {
    if (empty($userEmail)) return false;
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
        $html = "<h2>Hello $userName,</h2><p>Your booking for <strong>{$bookingDetails['venue_name']}</strong> on <strong>{$bookingDetails['date']}</strong> at <strong>{$bookingDetails['time']}</strong> for <strong>{$bookingDetails['duration']} hour(s)</strong> has been <strong>$newStatus</strong>.</p>";
        if ($newStatus === 'rejected') $html .= "<p>If you have questions, contact venue admin.</p>";
        else $html .= "<p>Enjoy your event! Please arrive on time.</p>";
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = strip_tags($html);
        $mail->send();
        return true;
    } catch (Exception $e) { error_log("Email failed: " . $mail->ErrorInfo); return false; }
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
    if (isset($holidays[$bookingDate])) $conflicts[] = "Venue closed on public holiday: " . $holidays[$bookingDate];
    if ($bookingTimestamp < $today) $conflicts[] = "Cannot book a past date";
    $hour = (int)date('H', $newStart);
    if ($hour < VENUE_OPEN_HOUR || $hour >= VENUE_CLOSE_HOUR) $conflicts[] = "Outside operating hours (" . VENUE_OPEN_HOUR . ":00 – " . VENUE_CLOSE_HOUR . ":00)";
    if ($durationHours > MAX_DURATION_HOURS) $conflicts[] = "Duration exceeds " . MAX_DURATION_HOURS . " hours maximum";
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
            if ($newStart < $exEnd && $newEnd > $exStart) $conflicts[] = "Time overlap with confirmed booking #{$row['id']} ({$row['student_name']})";
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
            if ($dailyCount >= MAX_USER_DAILY_BOOKINGS) $conflicts[] = "User exceeds daily booking quota (" . MAX_USER_DAILY_BOOKINGS . " per day)";
            $dailyStmt->close();
        }
    }
    return $conflicts;
}

// ========== AI ENHANCEMENTS WITH CONVERSATION MEMORY ==========
function getAllBookingsForAI($conn) {
    $sql = "SELECT b.*, v.name as venue_name, u.name as student_name, u.email as student_email 
            FROM bookings b 
            LEFT JOIN venues v ON b.venue_id = v.id 
            LEFT JOIN users u ON b.user_id = u.id 
            ORDER BY b.booking_date ASC, b.start_time ASC";
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

function askDeepSeek($userQuestion, $bookingsData, $history = []) {
    $apiKey = DEEPSEEK_API_KEY;
    $url = 'https://api.deepseek.com/v1/chat/completions';
    
    $context = "You are an AI assistant for campus venue booking (UBook). You can answer questions and perform actions on bookings.\n";
    $context .= "If the user asks to confirm/reject/delete/send email about a booking, respond with a JSON object ONLY on the last line, formatted like:\n";
    $context .= "{\"action\":\"confirm\",\"booking_id\":123}\n";
    $context .= "{\"action\":\"reject\",\"booking_id\":123}\n";
    $context .= "{\"action\":\"delete\",\"booking_id\":123}\n";
    $context .= "For other questions, respond clearly using Markdown. Use **bolding**, lists (-), and neat structure to make it highly readable for the admin.\n";
    $context .= "Below is the current booking list with conflict reasons.\n";
    
    foreach ($bookingsData as $b) {
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
    $context .= "\nAlways include the booking ID when referring to specific bookings.";

    // Initialize messages array with the system context
    $messages = [
        ['role' => 'system', 'content' => $context]
    ];

    // Append the last few messages for memory context (limit to last 6 to save tokens)
    $recentHistory = array_slice($history, -6);
    foreach ($recentHistory as $msg) {
        if (isset($msg['role']) && isset($msg['content'])) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
    }

    // Add the current user question
    $messages[] = ['role' => 'user', 'content' => $userQuestion];

    $payload = [
        'model' => 'deepseek-chat',
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 2000
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return "AI service temporarily unavailable.";
    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? "I couldn't understand that.";
}

function sendCustomEmail($bookingId, $subject, $body, $conn) {
    $stmt = $conn->prepare("SELECT b.*, v.name as venue_name, u.email as user_email, u.name as user_name 
                            FROM bookings b 
                            LEFT JOIN venues v ON b.venue_id = v.id 
                            LEFT JOIN users u ON b.user_id = u.id 
                            WHERE b.id = ?");
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    if (!$booking || empty($booking['user_email'])) return false;

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
        $mail->addAddress($booking['user_email'], $booking['user_name']);
        $mail->addBCC('aunyiqi168@gmail.com');
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Custom email failed: " . $mail->ErrorInfo);
        return false;
    }
}

function executeAIAction($actionData, $conn) {
    $action = $actionData['action'] ?? '';
    $bookingId = (int)($actionData['booking_id'] ?? 0);
    if (!$bookingId) return "Invalid booking ID.";

    $stmt = $conn->prepare("SELECT b.*, v.name as venue_name, u.email as user_email, u.name as user_name FROM bookings b LEFT JOIN venues v ON b.venue_id = v.id LEFT JOIN users u ON b.user_id = u.id WHERE b.id = ?");
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    if (!$booking) return "Booking #$bookingId not found.";

    switch ($action) {
        case 'confirm':
            $conflicts = checkBookingConflicts($conn, $booking['id'], $booking['venue_id'], $booking['booking_date'], $booking['start_time'], $booking['duration_hours'], $booking['user_id']);
            if (!empty($conflicts)) {
                return "Cannot confirm: " . implode(', ', $conflicts);
            }
            $update = $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?");
            $update->bind_param("i", $bookingId);
            if ($update->execute()) {
                $details = [
                    'venue_name' => $booking['venue_name'],
                    'date' => $booking['booking_date'],
                    'time' => date("g:i A", strtotime($booking['start_time'])),
                    'duration' => $booking['duration_hours']
                ];
                sendBookingStatusEmail($booking['user_email'], $booking['user_name'], $details, 'confirmed');
                return "Booking #$bookingId confirmed. Email sent to user.";
            }
            return "Failed to confirm booking.";
        case 'reject':
            $update = $conn->prepare("UPDATE bookings SET status = 'rejected' WHERE id = ?");
            $update->bind_param("i", $bookingId);
            if ($update->execute()) {
                $details = [
                    'venue_name' => $booking['venue_name'],
                    'date' => $booking['booking_date'],
                    'time' => date("g:i A", strtotime($booking['start_time'])),
                    'duration' => $booking['duration_hours']
                ];
                sendBookingStatusEmail($booking['user_email'], $booking['user_name'], $details, 'rejected');
                return "Booking #$bookingId rejected. Email sent to user.";
            }
            return "Failed to reject booking.";
        case 'delete':
            $del = $conn->prepare("DELETE FROM bookings WHERE id = ?");
            $del->bind_param("i", $bookingId);
            if ($del->execute() && $del->affected_rows > 0) {
                return "Booking #$bookingId deleted successfully.";
            }
            return "Failed to delete booking.";
        case 'email':
            $customMsg = $actionData['message'] ?? 'Your booking has been updated.';
            $subject = "Message from UBook Admin";
            $body = "<p>Dear {$booking['user_name']},</p><p>$customMsg</p><p>For booking #{$bookingId} at {$booking['venue_name']} on {$booking['booking_date']}.</p><p>Regards,<br>UBook Team</p>";
            if (sendCustomEmail($bookingId, $subject, $body, $conn)) {
                return "Email sent to user for booking #$bookingId.";
            }
            return "Failed to send email.";
        default:
            return "Unknown action.";
    }
}
// ========== END AI ENHANCEMENTS ==========

// ==================== AJAX HANDLERS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $conn = getDB();

    // ---- REVIEWS ----
    if ($action === 'get_reviews') {
        $result = $conn->query("SELECT id, name, rating, review, created_at FROM reviews ORDER BY created_at DESC");
        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $row['rating'] = (float)$row['rating']; 
            $reviews[] = $row;
        }
        $countResult = $conn->query("SELECT rating, COUNT(*) as count FROM reviews GROUP BY rating ORDER BY rating");
        $ratingCounts = [1=>0,2=>0,3=>0,4=>0,5=>0];
        while ($row = $countResult->fetch_assoc()) $ratingCounts[(int)$row['rating']] = (int)$row['count'];
        echo json_encode(['success' => true, 'reviews' => $reviews, 'ratingCounts' => $ratingCounts]);
        exit();
    }

    // ---- BOOKINGS ----
    if ($action === 'get_bookings') {
        $statusFilter = $_POST['status'] ?? 'all';
        $sql = "SELECT b.*, v.name as venue_name, u.email as user_email, u.name as student_name 
                FROM bookings b 
                LEFT JOIN venues v ON b.venue_id = v.id 
                LEFT JOIN users u ON b.user_id = u.id 
                ORDER BY b.booking_date DESC, b.start_time ASC";
        $result = $conn->query($sql);
        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit();
        }
        
        $bookings = [];
        $stats = ['pending' => 0, 'confirmed' => 0, 'rejected' => 0, 'total' => 0];
        
        while ($row = $result->fetch_assoc()) {
            if ($statusFilter !== 'all' && $row['status'] !== $statusFilter) continue;
            
            $row['start_time'] = date("g:i A", strtotime($row['start_time']));
            
            if ($row['status'] === 'confirmed') {
                $row['has_conflicts'] = false;
                $row['conflict_reasons'] = [];
                $stats['confirmed']++;
            } elseif ($row['status'] === 'rejected') {
                $row['has_conflicts'] = false;
                $row['conflict_reasons'] = [];
                $stats['rejected']++;
            } else {
                $conflicts = checkBookingConflicts($conn, $row['id'], $row['venue_id'], $row['booking_date'], $row['start_time'], $row['duration_hours'], $row['user_id']);
                $row['has_conflicts'] = !empty($conflicts);
                $row['conflict_reasons'] = $conflicts;
                if ($row['status'] === 'pending') $stats['pending']++;
                else $stats['total']++;
            }
            $stats['total']++;
            $bookings[] = $row;
        }
        
        echo json_encode(['success' => true, 'bookings' => $bookings, 'stats' => $stats]);
        exit();
    }

    // ---- CALENDAR EVENTS ----
    if ($action === 'get_calendar_events') {
        $sql = "SELECT b.id, b.venue_id, b.booking_date, b.start_time, b.duration_hours, b.status, b.comment, v.name as venue_name, u.name as student_name, u.id as user_id 
                FROM bookings b 
                LEFT JOIN venues v ON b.venue_id = v.id 
                LEFT JOIN users u ON b.user_id = u.id 
                ORDER BY b.booking_date ASC, b.start_time ASC";
        $result = $conn->query($sql);
        if (!$result) {
            echo json_encode(['success' => false, 'error' => $conn->error]);
            exit();
        }
        $events = [];
        while ($row = $result->fetch_assoc()) {
            if (empty($row['booking_date']) || empty($row['start_time'])) continue;
            
            if ($row['status'] === 'confirmed') $conflicts = [];
            else $conflicts = checkBookingConflicts($conn, $row['id'], $row['venue_id'], $row['booking_date'], $row['start_time'], $row['duration_hours'], $row['user_id']);
            $hasConflict = !empty($conflicts);
            
            $startDateTime = $row['booking_date'] . ' ' . $row['start_time'];
            $startTimestamp = strtotime($startDateTime);
            if (!$startTimestamp) continue;
            $endTimestamp = $startTimestamp + ($row['duration_hours'] * 3600);
            $endDateTime = date('Y-m-d H:i:s', $endTimestamp);
            
            if ($hasConflict) $color = '#a855f7'; 
            else if ($row['status'] == 'pending') $color = '#f59e0b';
            else if ($row['status'] == 'confirmed') $color = '#10b981';
            else if ($row['status'] == 'rejected') $color = '#ef4444';
            else $color = '#64748b';
            
            $title = ($hasConflict ? '⚠️ ' : ($row['status'] == 'pending' ? '⏳ ' : '')) . ($row['student_name'] ?? 'Guest') . ' @ ' . ($row['venue_name'] ?? 'Unknown');
            $events[] = [
                'id' => $row['id'],
                'title' => $title,
                'start' => $startDateTime,
                'end' => $endDateTime,
                'color' => $color,
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'status' => $row['status'],
                    'venue' => $row['venue_name'] ?? '',
                    'student' => $row['student_name'] ?? '',
                    'duration' => $row['duration_hours'],
                    'comment' => $row['comment'],
                    'date' => $row['booking_date'],
                    'time' => date("g:i A", strtotime($row['start_time'])),
                    'hasConflict' => $hasConflict,
                    'conflictReasons' => $conflicts
                ]
            ];
        }
        echo json_encode(['success' => true, 'events' => $events]);
        exit();
    }

    // ---- CHECK CONFLICTS ----
    if ($action === 'check_conflicts') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $stmt = $conn->prepare("SELECT b.*, v.name as venue_name FROM bookings b LEFT JOIN venues v ON b.venue_id = v.id WHERE b.id = ?");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        if (!$booking) echo json_encode(['success' => false, 'message' => 'Booking not found']);
        else {
            if ($booking['status'] === 'confirmed') echo json_encode(['success' => true, 'conflicts' => []]);
            else {
                $conflicts = checkBookingConflicts($conn, $booking['id'], $booking['venue_id'], $booking['booking_date'], $booking['start_time'], $booking['duration_hours'], $booking['user_id']);
                echo json_encode(['success' => true, 'conflicts' => $conflicts]);
            }
        }
        exit();
    }

    // ---- UPDATE STATUS ----
    if ($action === 'update_status') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        if (!in_array($newStatus, ['confirmed', 'rejected'])) exit(json_encode(['success' => false, 'message' => 'Invalid status']));
        
        $stmt = $conn->prepare("SELECT b.*, v.name as venue_name, u.email as user_email, u.name as user_name FROM bookings b LEFT JOIN venues v ON b.venue_id = v.id LEFT JOIN users u ON b.user_id = u.id WHERE b.id = ?");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        if (!$booking) exit(json_encode(['success' => false, 'message' => 'Booking not found']));
        
        if ($newStatus === 'confirmed') {
            $conflicts = checkBookingConflicts($conn, $booking['id'], $booking['venue_id'], $booking['booking_date'], $booking['start_time'], $booking['duration_hours'], $booking['user_id']);
            if (!empty($conflicts)) exit(json_encode(['success' => false, 'message' => 'Cannot confirm: ' . implode(', ', $conflicts), 'conflicts' => $conflicts]));
        }
        
        $update = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $update->bind_param("si", $newStatus, $bookingId);
        if ($update->execute()) {
            if (!empty($booking['user_email'])) {
                $details = [
                    'venue_name' => $booking['venue_name'],
                    'date' => $booking['booking_date'],
                    'time' => date("g:i A", strtotime($booking['start_time'])),
                    'duration' => $booking['duration_hours']
                ];
                sendBookingStatusEmail($booking['user_email'], $booking['user_name'], $details, $newStatus);
            }
            echo json_encode(['success' => true, 'message' => "Booking {$newStatus}."]);
        } else echo json_encode(['success' => false, 'message' => 'DB error']);
        exit();
    }

    // ---- DELETE BOOKING ----
    if ($action === 'delete_booking') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
        $stmt->bind_param("i", $bookingId);
        if ($stmt->execute() && $stmt->affected_rows > 0) echo json_encode(['success' => true, 'message' => 'Booking deleted']);
        else echo json_encode(['success' => false, 'message' => 'Delete failed']);
        exit();
    }

    // ---- AUTO RESOLVE CONFLICTS ----
    if ($action === 'auto_resolve_conflicts') {
        $sql = "SELECT b.id, b.venue_id, b.booking_date, b.start_time, b.duration_hours, b.user_id, v.name as venue_name, u.email as user_email, u.name as user_name 
                FROM bookings b 
                LEFT JOIN venues v ON b.venue_id = v.id 
                LEFT JOIN users u ON b.user_id = u.id 
                WHERE b.status = 'pending'";
        $result = $conn->query($sql);
        $rejectedCount = 0;
        while ($row = $result->fetch_assoc()) {
            $conflicts = checkBookingConflicts($conn, $row['id'], $row['venue_id'], $row['booking_date'], $row['start_time'], $row['duration_hours'], $row['user_id']);
            if (!empty($conflicts)) {
                $update = $conn->prepare("UPDATE bookings SET status = 'rejected' WHERE id = ?");
                $update->bind_param("i", $row['id']);
                if ($update->execute()) {
                    $rejectedCount++;
                    if (!empty($row['user_email'])) {
                        $details = [
                            'venue_name' => $row['venue_name'],
                            'date' => $row['booking_date'],
                            'time' => date("g:i A", strtotime($row['start_time'])),
                            'duration' => $row['duration_hours']
                        ];
                        sendBookingStatusEmail($row['user_email'], $row['user_name'], $details, 'rejected');
                    }
                }
            }
        }
        echo json_encode(['success' => true, 'rejected' => $rejectedCount]);
        exit();
    }

    // ---- AUTO REJECT EXPIRED ----
    if ($action === 'auto_reject_expired') {
        $today = date('Y-m-d');
        $sql = "SELECT b.id, b.venue_id, b.booking_date, b.start_time, b.duration_hours, b.user_id, v.name as venue_name, u.email as user_email, u.name as user_name 
                FROM bookings b 
                LEFT JOIN venues v ON b.venue_id = v.id 
                LEFT JOIN users u ON b.user_id = u.id 
                WHERE b.status = 'pending' AND b.booking_date < ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $rejectedCount = 0;
        while ($row = $result->fetch_assoc()) {
            $update = $conn->prepare("UPDATE bookings SET status = 'rejected' WHERE id = ?");
            $update->bind_param("i", $row['id']);
            if ($update->execute()) {
                $rejectedCount++;
                if (!empty($row['user_email'])) {
                    $details = [
                        'venue_name' => $row['venue_name'],
                        'date' => $row['booking_date'],
                        'time' => date("g:i A", strtotime($row['start_time'])),
                        'duration' => $row['duration_hours']
                    ];
                    sendBookingStatusEmail($row['user_email'], $row['user_name'], $details, 'rejected');
                }
            }
        }
        echo json_encode(['success' => true, 'rejected' => $rejectedCount]);
        exit();
    }

    // ---- AI QUERY WITH MEMORY & ROBUST MARKDOWN ----
    if ($action === 'ai_query') {
        $question = trim($_POST['question'] ?? '');
        $historyStr = $_POST['history'] ?? '[]';
        $history = json_decode($historyStr, true);
        if (!is_array($history)) $history = [];

        if (empty($question)) {
            echo json_encode(['success' => false, 'message' => 'Empty question']);
            exit();
        }
        
        $bookings = getAllBookingsForAI($conn);
        $aiResponse = askDeepSeek($question, $bookings, $history);
        
        $actionData = null;
        if (preg_match('/\{[^{}]*\}/', $aiResponse, $matches)) {
            $json = $matches[0];
            $parsed = json_decode($json, true);
            if (isset($parsed['action']) && isset($parsed['booking_id'])) {
                $actionData = $parsed;
            }
        }
        
        if ($actionData) {
            $result = executeAIAction($actionData, $conn);
            $cleanedResponse = trim(preg_replace('/\{[^{}]*\}/', '', $aiResponse));
            echo json_encode([
                'success' => true, 
                'answer' => $cleanedResponse, 
                'raw_answer' => $aiResponse, 
                'action_result' => $result
            ]);
        } else {
            echo json_encode([
                'success' => true, 
                'answer' => $aiResponse,
                'raw_answer' => $aiResponse
            ]);
        }
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

$currentUser = htmlspecialchars($_SESSION['name'] ?? $_SESSION['username'] ?? 'Admin');
$userRole = $_SESSION['role'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UBook · Super Admin Dashboard</title>
    
    <!-- Modern Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- External Libraries -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- PREMIUM AI CHAT LIBRARIES: Marked.js + DOMPurify -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js"></script>

    <style>
        /* CSS Variables */
        :root {
            --bg-dark: #0b1120;
            --bg-card: rgba(30, 41, 59, 0.6);
            --bg-card-hover: rgba(30, 41, 59, 0.8);
            --primary: #10b981;
            --primary-hover: #059669;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --purple: #a855f7;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: rgba(255, 255, 255, 0.08);
            
            /* FullCalendar Overrides */
            --fc-border-color: rgba(255, 255, 255, 0.08);
            --fc-button-bg-color: #1e293b;
            --fc-button-border-color: #334155;
            --fc-button-hover-bg-color: #334155;
            --fc-button-active-bg-color: var(--primary);
            --fc-button-active-border-color: var(--primary);
            --fc-today-bg-color: rgba(16, 185, 129, 0.05);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        
        body { 
            background-color: var(--bg-dark); 
            color: var(--text-main); 
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Beautiful Mesh Gradient Background */
        body::before { 
            content: ""; position: fixed; top: -50%; left: -50%; width: 200%; height: 200%; 
            background: radial-gradient(circle at 50% 50%, rgba(16, 185, 129, 0.05) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(168, 85, 247, 0.05) 0%, transparent 40%);
            z-index: -2; pointer-events: none;
        }

        /* Custom Webkit Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }

        .admin-wrapper { max-width: 1600px; margin: 0 auto; padding: 2rem; position: relative; z-index: 1; }

        /* HEADER & NAVIGATION REDESIGN */
        .admin-header {
            display: flex; flex-direction: column; gap: 1.5rem;
            background: var(--bg-card); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            padding: 2rem; border-radius: 24px; border: 1px solid var(--border);
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5); margin-bottom: 2rem;
        }
        .header-top { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        .logo-area h1 { color: var(--text-main); font-size: 2rem; font-weight: 800; display: flex; align-items: center; gap: 12px; }
        .logo-area h1 i { color: var(--primary); font-size: 2.2rem; }
        .logo-area p { color: var(--text-muted); font-size: 1rem; margin-top: 6px; font-weight: 500;}
        
        .user-controls { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
        .user-info { background: rgba(255,255,255,0.03); padding: 0.8rem 1.5rem; border-radius: 99px; display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 1.1rem; border: 1px solid var(--border); cursor: default; }
        .user-info i { color: var(--primary); }
        .logout-btn { 
            background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2);
            padding: 0.8rem 1.5rem; border-radius: 99px; font-weight: 600; font-size: 1.1rem; text-decoration: none; 
            display: flex; align-items: center; gap: 8px; transition: all 0.3s; 
        }
        .logout-btn:hover { background: var(--danger); color: white; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(239, 68, 68, 0.3); }

        .admin-nav {
            display: flex; flex-wrap: wrap; gap: 1rem; width: 100%; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1.5rem;
        }
        .nav-link { 
            display: flex; align-items: center; justify-content: center; gap: 12px; padding: 1.2rem 1.5rem; border-radius: 16px; 
            color: var(--text-muted); text-decoration: none; font-weight: 600; font-size: 1.1rem; transition: 0.3s; 
            background: rgba(0,0,0,0.2); flex: 1; min-width: 180px; border: 1px solid transparent;
        }
        .nav-link:hover { background: rgba(255,255,255,0.05); color: var(--text-main); transform: translateY(-3px); border-color: rgba(255,255,255,0.1); box-shadow: 0 6px 15px rgba(0,0,0,0.2); }
        .nav-link.active { background: var(--primary); color: white; box-shadow: 0 6px 20px rgba(16,185,129,0.3); border-color: var(--primary-hover); }

        /* TABS */
        .dashboard-tabs { display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 0px; }
        .tab-btn { 
            background: transparent; border: none; padding: 12px 24px; font-size: 1.05rem; font-weight: 600; 
            color: var(--text-muted); cursor: pointer; transition: 0.3s; position: relative;
            display: flex; align-items: center; gap: 8px;
        }
        .tab-btn:hover { color: var(--text-main); }
        .tab-btn.active { color: var(--primary); }
        .tab-btn.active::after { 
            content: ''; position: absolute; bottom: -1px; left: 0; width: 100%; height: 3px; 
            background: var(--primary); border-radius: 3px 3px 0 0; 
        }
        
        .tab-content { display: none; animation: fadeIn 0.4s ease forwards; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* STATS GRID */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { 
            background: var(--bg-card); backdrop-filter: blur(12px); border: 1px solid var(--border); 
            padding: 1.5rem; border-radius: 20px; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            display: flex; align-items: center; gap: 1.25rem; overflow: hidden; position: relative;
        }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; transition: 0.3s; }
        .stat-card[data-filter="pending"]::before { background: var(--warning); }
        .stat-card[data-filter="confirmed"]::before { background: var(--primary); }
        .stat-card[data-filter="rejected"]::before { background: var(--danger); }
        .stat-card[data-filter="all"]::before { background: var(--info); }
        
        .stat-card:hover { transform: translateY(-5px); background: var(--bg-card-hover); border-color: rgba(255,255,255,0.2); box-shadow: 0 10px 30px rgba(0,0,0,0.4); }
        .stat-card:hover::before { width: 8px; }
        
        .stat-icon { width: 54px; height: 54px; border-radius: 16px; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .stat-card[data-filter="pending"] .stat-icon { color: var(--warning); background: rgba(245, 158, 11, 0.1); }
        .stat-card[data-filter="confirmed"] .stat-icon { color: var(--primary); background: rgba(16, 185, 129, 0.1); }
        .stat-card[data-filter="rejected"] .stat-icon { color: var(--danger); background: rgba(239, 68, 68, 0.1); }
        .stat-card[data-filter="all"] .stat-icon { color: var(--info); background: rgba(59, 130, 246, 0.1); }
        
        .stat-info { display: flex; flex-direction: column; }
        .stat-number { font-size: 2.2rem; font-weight: 800; color: var(--text-main); line-height: 1.2; }
        .stat-label { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }

        /* ACTION BAR & FILTERS */
        .action-bar { 
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; 
            background: rgba(15, 23, 42, 0.5); padding: 1rem 1.5rem; border-radius: 20px; border: 1px solid var(--border); margin-bottom: 1.5rem; 
        }
        .view-toggle { display: flex; background: rgba(0,0,0,0.2); padding: 5px; border-radius: 99px; }
        .view-btn { background: transparent; border: none; padding: 8px 20px; border-radius: 99px; color: var(--text-muted); cursor: pointer; font-weight: 600; transition: 0.3s; display: flex; align-items: center; gap: 8px; }
        .view-btn.active { background: #334155; color: var(--text-main); box-shadow: 0 2px 8px rgba(0,0,0,0.3); }
        
        .filter-controls { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .status-select { background: #0f172a; color: var(--text-main); border: 1px solid #334155; border-radius: 12px; padding: 10px 16px; font-weight: 500; outline: none; cursor: pointer; transition: 0.3s; }
        .status-select:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(16,185,129,0.2); }
        
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-hover)); border: none; padding: 10px 20px; border-radius: 12px; color: white; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.3s; box-shadow: 0 4px 15px rgba(16,185,129,0.3); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(16,185,129,0.4); }
        .btn-outline { background: transparent; border: 1px solid var(--border); padding: 10px 20px; border-radius: 12px; color: var(--text-muted); font-weight: 600; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 8px; }
        .btn-outline:hover { background: rgba(255,255,255,0.05); color: var(--text-main); border-color: rgba(255,255,255,0.2); }

        /* CONTAINERS & TABLES */
        .data-container { background: var(--bg-card); backdrop-filter: blur(16px); border-radius: 24px; padding: 1.5rem; border: 1px solid var(--border); box-shadow: 0 10px 30px rgba(0,0,0,0.2); overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        th { background: rgba(15, 23, 42, 0.8); color: var(--text-muted); font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 1.2rem 1rem; text-align: left; }
        th:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
        th:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; }
        td { padding: 1.2rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; font-size: 0.95rem; }
        tr { transition: 0.2s; }
        tr:hover td { background: rgba(255,255,255,0.02); }
        tr:last-child td { border-bottom: none; }

        /* BADGES & ACTIONS */
        .badge { display: inline-flex; align-items: center; justify-content: center; padding: 6px 12px; border-radius: 99px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge.pending { background: rgba(245, 158, 11, 0.15); color: var(--warning); border: 1px solid rgba(245, 158, 11, 0.3); }
        .badge.confirmed { background: rgba(16, 185, 129, 0.15); color: var(--primary); border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge.rejected { background: rgba(239, 68, 68, 0.15); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.3); }
        .badge.conflict { background: rgba(168, 85, 247, 0.15); color: var(--purple); border: 1px solid rgba(168, 85, 247, 0.4); animation: pulseAlert 2s infinite; }
        @keyframes pulseAlert { 0% { box-shadow: 0 0 0 0 rgba(168, 85, 247, 0.4); } 70% { box-shadow: 0 0 0 6px rgba(168, 85, 247, 0); } 100% { box-shadow: 0 0 0 0 rgba(168, 85, 247, 0); } }

        .action-group { display: flex; gap: 8px; }
        .icon-btn { width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--border); background: rgba(15,23,42,0.5); color: var(--text-muted); cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
        .icon-btn:hover { transform: scale(1.1); color: white; }
        .btn-confirm:hover { background: rgba(16,185,129,0.2); border-color: var(--primary); color: var(--primary); }
        .btn-reject:hover { background: rgba(245,158,11,0.2); border-color: var(--warning); color: var(--warning); }
        .btn-delete:hover { background: rgba(239,68,68,0.2); border-color: var(--danger); color: var(--danger); }
        .btn-conflict { color: var(--purple); }
        .btn-conflict:hover { background: rgba(168,85,247,0.2); border-color: var(--purple); }

        /* ==================== PREMIUM AI CHAT WIDGET ==================== */
        .ai-fab {
            position: fixed; bottom: 30px; right: 30px; width: 68px; height: 68px; border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover)); color: white; font-size: 1.8rem;
            border: none; cursor: pointer; z-index: 1000; box-shadow: 0 10px 25px rgba(16, 185, 129, 0.5);
            display: flex; align-items: center; justify-content: center; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .ai-fab:hover { transform: scale(1.1) rotate(5deg); box-shadow: 0 15px 35px rgba(16, 185, 129, 0.6); }

        .chat-widget {
            display: none; flex-direction: column; position: fixed; bottom: 115px; right: 30px; 
            width: 600px; height: 85vh; max-height: 900px;
            background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 24px; box-shadow: 0 25px 50px rgba(0,0,0,0.6);
            z-index: 2000; overflow: hidden; opacity: 0; transform: translateY(20px) scale(0.95); transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .chat-widget.active { display: flex; opacity: 1; transform: translateY(0) scale(1); }

        .chat-header {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), transparent); padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;
        }
        .chat-header-left { display: flex; align-items: center; gap: 12px; }
        .chat-avatar { width: 42px; height: 42px; border-radius: 12px; background: rgba(16,185,129,0.2); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .chat-title h4 { margin: 0; color: var(--text-main); font-size: 1.05rem; }
        .chat-title span { font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 4px; }
        .status-dot { width: 8px; height: 8px; background: var(--primary); border-radius: 50%; display: inline-block; box-shadow: 0 0 8px var(--primary); }
        
        .header-actions { display: flex; gap: 8px; }
        .action-icon-btn { background: rgba(255,255,255,0.05); border: 1px solid transparent; color: var(--text-muted); font-size: 1.1rem; cursor: pointer; transition: 0.2s; padding: 6px; border-radius: 8px; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; }
        .action-icon-btn:hover { background: rgba(255,255,255,0.1); color: white; }
        #clearChatBtn:hover { background: rgba(239, 68, 68, 0.2); color: var(--danger); border-color: rgba(239, 68, 68, 0.3); }

        .chat-body { flex: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; scroll-behavior: smooth; }
        .chat-body::-webkit-scrollbar { width: 6px; }
        .chat-body::-webkit-scrollbar-track { background: transparent; }
        .chat-body::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }
        
        @keyframes slideUpMsg { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        .message-wrapper { display: flex; flex-direction: column; width: 100%; margin-bottom: 1.5rem; animation: slideUpMsg 0.4s ease forwards; }
        .message-wrapper.user { align-items: flex-end; }
        .message-wrapper.ai { align-items: flex-start; }

        .message { display: flex; gap: 12px; align-items: flex-start; max-width: 100%; }
        .message-wrapper.user .message { flex-direction: row-reverse; }
        
        .msg-avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; margin-top: 4px; }
        .message-wrapper.ai .msg-avatar { background: rgba(16, 185, 129, 0.2); color: var(--primary); border: 1px solid rgba(16, 185, 129, 0.3); }
        .message-wrapper.user .msg-avatar { background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; }
        
        .msg-content-container { display: flex; flex-direction: column; max-width: 85%; }
        .message-wrapper.user .msg-content-container { align-items: flex-end; }
        .message-wrapper.ai .msg-content-container { align-items: flex-start; }

        .msg-content { 
            padding: 14px 18px; border-radius: 18px; font-size: 1.05rem; 
            line-height: 1.6; word-wrap: break-word; overflow-wrap: break-word; box-shadow: 0 4px 15px rgba(0,0,0,0.15); 
        }
        .message-wrapper.ai .msg-content { background: #1e293b; color: var(--text-main); border-bottom-left-radius: 4px; border: 1px solid rgba(255,255,255,0.05); }
        .message-wrapper.user .msg-content { background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; border-bottom-right-radius: 4px; }
        
        .msg-time { display: block; font-size: 0.75rem; color: #64748b; margin-top: 6px; padding: 0 6px; font-weight: 500;}

        /* PREMIUM MARKDOWN STYLING INSIDE CHAT */
        .msg-content p { margin-bottom: 10px; margin-top: 0;}
        .msg-content p:last-child { margin-bottom: 0; }
        .msg-content ul, .msg-content ol { margin: 10px 0 10px 24px; padding-left: 0; }
        .msg-content li { margin-bottom: 6px; }
        .msg-content strong { color: #fff; font-weight: 700; }
        .msg-content em { font-style: italic; color: #cbd5e1; }
        .msg-content pre { background: #0f172a; padding: 12px; border-radius: 8px; overflow-x: auto; margin: 12px 0; border: 1px solid rgba(255,255,255,0.1); }
        .msg-content code { font-family: 'Courier New', Courier, monospace; background: rgba(0, 0, 0, 0.3); padding: 2px 6px; border-radius: 4px; font-size: 0.9em; color: #34d399; }
        .msg-content pre code { background: transparent; padding: 0; color: #e2e8f0; border: none;}
        .msg-content table { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 0.9rem; background: rgba(0,0,0,0.2); border-radius: 8px; overflow: hidden; display: block; overflow-x: auto; }
        .msg-content th { background: rgba(255,255,255,0.05); padding: 10px; text-align: left; border: 1px solid #334155; font-weight: 600; color: #fff;}
        .msg-content td { padding: 10px; border: 1px solid #334155; color: #cbd5e1; }
        .msg-content tr:nth-child(even) { background: rgba(255,255,255,0.02); }

        /* QUICK ACTIONS */
        .chat-quick-actions { 
            display: flex; gap: 8px; padding: 12px 1.5rem; background: rgba(15, 23, 42, 0.98); 
            border-top: 1px solid var(--border); overflow-x: auto; scrollbar-width: none; 
        }
        .chat-quick-actions::-webkit-scrollbar { display: none; }
        .quick-chip { 
            white-space: nowrap; background: rgba(16, 185, 129, 0.1); color: var(--primary); 
            border: 1px solid rgba(16, 185, 129, 0.2); padding: 8px 14px; border-radius: 99px; 
            font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: 0.2s; 
        }
        .quick-chip:hover { background: var(--primary); color: #fff; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(16,185,129,0.2); }

        /* Typing Indicator CSS */
        .typing-indicator { display: flex; gap: 4px; align-items: center; padding: 4px; }
        .typing-indicator span { width: 6px; height: 6px; background: var(--text-muted); border-radius: 50%; animation: bounce 1.4s infinite ease-in-out both; }
        .typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
        .typing-indicator span:nth-child(2) { animation-delay: -0.16s; }
        @keyframes bounce { 0%, 80%, 100% { transform: scale(0); opacity: 0.5; } 40% { transform: scale(1); opacity: 1; } }

        .chat-footer { padding: 1rem 1.5rem; background: #1e293b; border-top: 1px solid var(--border); display: flex; gap: 10px; align-items: flex-end; }
        .chat-footer textarea { 
            flex: 1; padding: 14px 20px; border-radius: 20px; border: 1px solid #334155; background: #0f172a; color: white; 
            outline: none; font-size: 1rem; transition: 0.3s; resize: none; overflow-y: hidden; min-height: 48px; max-height: 150px; 
            line-height: 1.4; font-family: 'Inter', sans-serif;
        }
        .chat-footer textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(16,185,129,0.15); background: #141e33; }
        .chat-footer textarea:disabled { opacity: 0.6; cursor: not-allowed; }
        .chat-footer button { 
            width: 48px; height: 48px; border-radius: 50%; background: var(--primary); border: none; color: white; 
            cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; font-size: 1.2rem; flex-shrink: 0;
            margin-bottom: 2px;
        }
        .chat-footer button:hover:not(:disabled) { background: var(--primary-hover); transform: scale(1.05); }
        .chat-footer button:disabled { background: #334155; color: #64748b; cursor: not-allowed; box-shadow: none; }

        /* MODALS & TOASTS */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); align-items: center; justify-content: center; z-index: 3000; }
        .modal-box { background: #1e293b; max-width: 500px; width: 90%; border-radius: 24px; border: 1px solid var(--border); box-shadow: 0 25px 50px rgba(0,0,0,0.5); overflow: hidden; animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes popIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        
        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-size: 1.2rem; display: flex; align-items: center; gap: 10px; margin: 0;}
        .modal-body { padding: 1.5rem; }
        
        .conflict-list { list-style: none; display: flex; flex-direction: column; gap: 10px; margin-bottom: 1.5rem; }
        .conflict-list li { background: rgba(168, 85, 247, 0.1); padding: 1rem; border-radius: 12px; border-left: 4px solid var(--purple); font-size: 0.95rem; color: #e2e8f0; }

        .toast { position: fixed; top: 30px; right: 30px; background: var(--bg-card); backdrop-filter: blur(10px); color: white; padding: 1rem 1.5rem; border-radius: 12px; font-weight: 500; border-left: 4px solid var(--primary); box-shadow: 0 10px 30px rgba(0,0,0,0.3); z-index: 4000; transform: translateX(150%); transition: transform 0.4s cubic-bezier(0.2, 0.8, 0.2, 1); display: flex; align-items: center; gap: 12px; }
        .toast.show { transform: translateX(0); }
        .toast.error { border-left-color: var(--danger); }
        
        /* Mobile Resets */
        @media (max-width: 768px) {
            .admin-wrapper { padding: 1rem; }
            .header-top { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .nav-link { min-width: 100%; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .chat-widget { width: calc(100vw - 20px); right: 10px; bottom: 100px; height: 85vh; max-height: none;}
        }
    </style>
</head>
<body>

<div class="admin-wrapper">
    <!-- Header -->
    <header class="admin-header">
        <div class="header-top">
            <div class="logo-area">
                <h1><i class="fas fa-layer-group"></i> UBook Admin</h1>
                <p>Intelligent Campus Venue Management</p>
            </div>
            <div class="user-controls">
                <div class="user-info"><i class="fas fa-shield-alt"></i> <?= $currentUser ?> <span style="opacity:0.6; font-size:0.85em;">(<?= htmlspecialchars($userRole) ?>)</span></div>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
        <nav class="admin-nav">
            <a href="group.manage.php" class="nav-link"><i class="fas fa-users"></i> Groups</a>
            <a href="like.manage.php" class="nav-link"><i class="fas fa-heart"></i> Likes</a>
            <a href="manage.group.participants.php" class="nav-link"><i class="fas fa-user-plus"></i> Participants</a>
            <a href="group.message.php" class="nav-link"><i class="fas fa-comment-dots"></i> Messages</a>
        </nav>
    </header>

    <!-- Tabs -->
    <div class="dashboard-tabs">
        <button class="tab-btn active" data-tab="bookings"><i class="fas fa-calendar-check" style="margin-right: 6px;"></i> Bookings</button>
        <button class="tab-btn" data-tab="reviews"><i class="fas fa-star" style="margin-right: 6px;"></i> Review Analytics</button>
    </div>

    <!-- BOOKINGS TAB -->
    <div id="bookingsTab" class="tab-content active">
        <!-- Stats Grid -->
        <div class="stats-grid" id="bookingStatsGrid">
            <div class="stat-card" data-filter="pending">
                <div class="stat-info"><div class="stat-number" id="pendingCount">0</div><div class="stat-label">Pending Requests</div></div>
                <div class="stat-icon" style="margin-left:auto;"><i class="fas fa-clock"></i></div>
            </div>
            <div class="stat-card" data-filter="confirmed">
                <div class="stat-info"><div class="stat-number" id="confirmedCount">0</div><div class="stat-label">Confirmed</div></div>
                <div class="stat-icon" style="margin-left:auto;"><i class="fas fa-check-circle"></i></div>
            </div>
            <div class="stat-card" data-filter="rejected">
                <div class="stat-info"><div class="stat-number" id="rejectedCount">0</div><div class="stat-label">Rejected</div></div>
                <div class="stat-icon" style="margin-left:auto;"><i class="fas fa-times-circle"></i></div>
            </div>
            <div class="stat-card" data-filter="all">
                <div class="stat-info"><div class="stat-number" id="totalCount">0</div><div class="stat-label">Total Bookings</div></div>
                <div class="stat-icon" style="margin-left:auto;"><i class="fas fa-chart-line"></i></div>
            </div>
        </div>
        
        <!-- Action & Filter Bar -->
        <div class="action-bar">
            <div class="view-toggle">
                <button class="view-btn active" id="calendarViewBtn"><i class="fas fa-calendar-alt"></i> Calendar</button>
                <button class="view-btn" id="tableViewBtn"><i class="fas fa-list"></i> List View</button>
            </div>
            <div class="filter-controls">
                <select id="statusFilter" class="status-select">
                    <option value="all">Filter: All Statuses</option>
                    <option value="pending">Status: Pending</option>
                    <option value="confirmed">Status: Confirmed</option>
                    <option value="rejected">Status: Rejected</option>
                </select>
                <button class="btn-primary" id="autoResolveBtn"><i class="fas fa-magic"></i> Auto-Resolve</button>
                <button class="btn-outline" id="refreshBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
            </div>
        </div>
        
        <!-- Content Containers -->
        <div id="calendarContainer" class="data-container"></div>
        <div id="tableContainer" class="data-container" style="display:none; padding: 0;">
            <table id="bookingsTable">
                <thead><tr><th>ID</th><th>Student Details</th><th>Venue</th><th>Schedule</th><th>Dur.</th><th>Status</th><th>Conflicts</th><th>Actions</th></tr></thead>
                <tbody id="bookingsTableBody"><tr><td colspan="8" style="text-align:center; padding:3rem;">Loading data...</td></tr></tbody>
            </table>
        </div>
    </div>

    <!-- REVIEWS TAB -->
    <div id="reviewsTab" class="tab-content">
        <div class="action-bar" style="margin-bottom: 2rem;">
            <div id="reviewStats" style="font-size: 1.1rem; font-weight: 600; display:flex; align-items:center; gap:10px;">
                <i class="fas fa-chart-pie" style="color:var(--primary); font-size:1.5rem;"></i> Loading statistics...
            </div>
            <button class="btn-primary" id="refreshReviewsBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
        </div>
        <div class="data-container chart-container">
            <canvas id="ratingChart" style="max-height: 300px;"></canvas>
        </div>
        <div class="data-container" style="padding: 0;">
            <table id="reviewsTable">
                <thead><tr><th>ID</th><th>Reviewer</th><th>Rating</th><th>Feedback</th><th>Date</th></tr></thead>
                <tbody id="reviewsTableBody"><tr><td colspan="5" style="text-align:center; padding:3rem;">Loading reviews...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<button class="ai-fab" id="openAIChatBtn" title="Ask AI Assistant"><i class="fas fa-robot"></i></button>

<div class="chat-widget" id="chatWidget">
    <div class="chat-header">
        <div class="chat-header-left">
            <div class="chat-avatar"><i class="fas fa-robot"></i></div>
            <div class="chat-title">
                <h4>UBook Assistant</h4>
                <span><span class="status-dot"></span> Online</span>
            </div>
        </div>
        <div class="header-actions">
            <button class="action-icon-btn" id="clearChatBtn" title="Clear Context/Memory"><i class="fas fa-trash-alt"></i></button>
            <button class="action-icon-btn" id="closeChatBtn" title="Close Chat"><i class="fas fa-times"></i></button>
        </div>
    </div>
    
    <div class="chat-body" id="chatMessages">
        <!-- Messages generated dynamically by JS -->
    </div>
    
    <!-- Quick Prompts Area -->
    <div class="chat-quick-actions" id="chatQuickActions">
        <button class="quick-chip">Show pending requests</button>
        <button class="quick-chip">Check for conflicts</button>
        <button class="quick-chip">Any public holidays soon?</button>
        <button class="quick-chip">Summarize today's bookings</button>
    </div>
    
    <div class="chat-footer">
        <textarea id="chatInput" placeholder="Message UBook AI... (Shift+Enter for new line)" autocomplete="off"></textarea>
        <button id="sendChatBtn"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

<!-- Modals -->
<div id="conflictModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3 style="color: var(--purple);"><i class="fas fa-exclamation-triangle"></i> Conflict Details</h3>
            <button class="action-icon-btn close" id="closeConflictModalBtn" style="border:none;"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <ul id="conflictList" class="conflict-list"></ul>
            <div style="text-align: right; border-top: 1px solid var(--border); padding-top: 1.5rem;">
                <button class="btn-primary" id="modalCloseBtn" style="width: 100%; justify-content: center;">Acknowledge</button>
            </div>
        </div>
    </div>
</div>

<div id="actionModal" class="modal-overlay">
    <!-- Populated dynamically via JS for Calendar Clicks -->
</div>

<!-- Toast Container -->
<div id="toastEl" class="toast">
    <i class="fas fa-info-circle" style="font-size:1.2rem;"></i> <span id="toastMsg"></span>
</div>

<script>
let ratingChart = null, calendar = null, currentView = 'calendar';
const adminName = <?= json_encode($currentUser) ?>;
let chatHistory = []; // Stores context for the AI memory

// Configure marked.js to allow line breaks naturally
if(typeof marked !== 'undefined'){
    marked.setOptions({ breaks: true });
}

function showToast(msg, isError = false) {
    const t = document.getElementById('toastEl');
    document.getElementById('toastMsg').innerText = msg;
    t.className = 'toast show ' + (isError ? 'error' : '');
    t.querySelector('i').className = isError ? 'fas fa-exclamation-circle' : 'fas fa-check-circle';
    setTimeout(() => { t.classList.remove('show'); }, 3500);
}

function escapeHtml(s) {
    if (!s) return '';
    return String(s).replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m]);
}

function renderStars(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        stars += i <= rating ? '<i class="fas fa-star" style="color:#fbbf24;"></i> ' : '<i class="far fa-star" style="color:#475569;"></i> ';
    }
    return stars;
}

// ==================== BOOKINGS & CALENDAR ====================
async function loadCalendarEvents() {
    try {
        let resp = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ action: 'get_calendar_events' })
        });
        let data = await resp.json();
        if (data.success && calendar) {
            calendar.removeAllEvents();
            calendar.addEventSource(data.events);
        } else showToast('Failed to load calendar', true);
    } catch(e) { showToast('Network error', true); }
}

function initCalendar() {
    let calEl = document.getElementById('calendarContainer');
    calendar = new FullCalendar.Calendar(calEl, {
        initialView: 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
        events: [],
        eventClick: function(info) { showBookingActions(info.event.id, info.event.extendedProps); },
        eventDidMount: function(info) { 
            info.el.style.border = 'none'; info.el.style.borderRadius = '4px'; info.el.style.padding = '2px 4px';
            if (info.event.extendedProps.hasConflict) info.el.style.animation = 'pulseAlert 2s infinite'; 
        },
        height: 'auto', slotMinTime: '08:00:00', slotMaxTime: '22:00:00', nowIndicator: true
    });
    calendar.render();
    loadCalendarEvents();
}

function showBookingActions(id, props) {
    let status = props.status, hasConflict = props.hasConflict, reasons = props.conflictReasons || [];
    let modal = document.getElementById('actionModal');
    
    let conflictHTML = hasConflict ? 
        `<div style="margin-top:1rem; padding:1rem; background:rgba(168, 85, 247, 0.1); border-left:4px solid var(--purple); border-radius:8px;">
            <strong style="color:var(--purple);"><i class="fas fa-exclamation-triangle"></i> Conflicts Found:</strong>
            <ul style="margin-top:8px; margin-left:20px; color:#e2e8f0; font-size:0.9rem;">
                ${reasons.map(r => `<li>${escapeHtml(r)}</li>`).join('')}
            </ul>
        </div>` : '';

    modal.innerHTML = `
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fas fa-ticket-alt" style="color:var(--primary);"></i> Booking #${id}</h3>
            <button class="action-icon-btn close" id="quickCloseModal" style="border:none;"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div style="background:rgba(255,255,255,0.02); border: 1px solid var(--border); padding: 1.2rem; border-radius: 12px; margin-bottom: 1rem;">
                <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                    <div><span style="color:var(--text-muted); font-size:0.8rem; text-transform:uppercase;">Student</span><br><strong style="font-size:1.1rem;">${escapeHtml(props.student)}</strong></div>
                    <div style="text-align:right;"><span style="color:var(--text-muted); font-size:0.8rem; text-transform:uppercase;">Status</span><br><span class="badge ${status}" style="margin-top:4px;">${status}</span></div>
                </div>
                <div style="margin-bottom:10px;"><i class="fas fa-map-marker-alt text-muted" style="width:20px;"></i> ${escapeHtml(props.venue)}</div>
                <div style="margin-bottom:10px;"><i class="far fa-calendar-alt text-muted" style="width:20px;"></i> ${props.date} &middot; ${props.time} (${props.duration}h)</div>
                ${props.comment ? `<div style="border-top: 1px dashed var(--border); padding-top:10px; margin-top:10px;"><i class="far fa-comment-dots text-muted" style="width:20px;"></i> <em>${escapeHtml(props.comment)}</em></div>` : ''}
            </div>
            ${conflictHTML}
            <div style="display:flex; gap:12px; margin-top:1.5rem; justify-content:flex-end;">
                ${status !== 'confirmed' ? `<button class="btn-primary" id="quickConfirmBtn" style="flex:1; justify-content:center;"><i class="fas fa-check"></i> Confirm</button>` : ''}
                ${status !== 'rejected' ? `<button class="btn-primary" id="quickRejectBtn" style="flex:1; justify-content:center; background:linear-gradient(135deg, #ef4444, #dc2626);"><i class="fas fa-times"></i> Reject</button>` : ''}
                <button class="btn-outline" id="quickDeleteBtn" style="color:var(--text-muted); border-color:var(--border);" title="Delete"><i class="fas fa-trash-alt"></i></button>
            </div>
        </div>
    </div>`;
    
    modal.style.display = 'flex';
    document.getElementById('quickCloseModal').onclick = () => { modal.style.display = 'none'; };
    if(document.getElementById('quickConfirmBtn')) document.getElementById('quickConfirmBtn').onclick = () => { updateStatus(id, 'confirmed'); modal.style.display='none'; };
    if(document.getElementById('quickRejectBtn')) document.getElementById('quickRejectBtn').onclick = () => { updateStatus(id, 'rejected'); modal.style.display='none'; };
    if(document.getElementById('quickDeleteBtn')) document.getElementById('quickDeleteBtn').onclick = () => { deleteBooking(id); modal.style.display='none'; };
}

async function loadTableBookings() {
    let status = document.getElementById('statusFilter').value;
    try {
        let resp = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ action: 'get_bookings', status: status })
        });
        let data = await resp.json();
        if (data.success) {
            renderTable(data.bookings);
            if (data.stats) {
                document.getElementById('pendingCount').innerText = data.stats.pending || 0;
                document.getElementById('confirmedCount').innerText = data.stats.confirmed || 0;
                document.getElementById('rejectedCount').innerText = data.stats.rejected || 0;
                document.getElementById('totalCount').innerText = data.stats.total || 0;
            }
        } else showToast('Error loading table', true);
    } catch(e) { showToast('Network error', true); }
}

function renderTable(bookings) {
    let tbody = document.getElementById('bookingsTableBody');
    if (!bookings.length) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:3rem; color:var(--text-muted);"><i class="fas fa-inbox" style="font-size:2rem; display:block; margin-bottom:10px;"></i>No bookings match your filter.</td></tr>';
        return;
    }
    let html = '';
    bookings.forEach(b => {
        let conflictBadge = b.has_conflicts ? '<span class="badge conflict" title="Conflicts Detected"><i class="fas fa-exclamation-triangle"></i></span>' : '<span style="color:#34d399;"><i class="fas fa-check-circle"></i></span>';
        html += `<tr>
            <td style="color:var(--text-muted); font-weight:600;">#${b.id}</td>
            <td><strong>${escapeHtml(b.student_name)}</strong><br><span style="font-size:0.8rem; color:var(--text-muted);">${escapeHtml(b.user_email || '—')}</span></td>
            <td style="font-weight:600; color:var(--text-main);">${escapeHtml(b.venue_name)}</td>
            <td><div style="display:flex; align-items:center; gap:6px;"><i class="far fa-calendar-alt text-muted"></i> ${b.booking_date}</div><div style="font-size:0.85rem; color:var(--text-muted); margin-top:4px;"><i class="far fa-clock"></i> ${b.start_time}</div></td>
            <td>${b.duration_hours}h</td>
            <td><span class="badge ${b.status}">${b.status}</span></td>
            <td style="text-align:center;">${conflictBadge}</td>
            <td>
                <div class="action-group">
                    <button class="icon-btn btn-conflict conflict-btn" data-id="${b.id}" title="View Details/Conflicts"><i class="fas fa-search"></i></button>
                    ${b.status !== 'confirmed' ? `<button class="icon-btn btn-confirm confirm-btn" data-id="${b.id}" title="Confirm"><i class="fas fa-check"></i></button>` : ''}
                    ${b.status !== 'rejected' ? `<button class="icon-btn btn-reject reject-btn" data-id="${b.id}" title="Reject"><i class="fas fa-times"></i></button>` : ''}
                    <button class="icon-btn btn-delete delete-btn" data-id="${b.id}" title="Delete"><i class="fas fa-trash-alt"></i></button>
                </div>
            </td>
        </tr>`;
    });
    tbody.innerHTML = html;
    
    document.querySelectorAll('.confirm-btn').forEach(btn => btn.addEventListener('click', () => updateStatus(btn.dataset.id, 'confirmed')));
    document.querySelectorAll('.reject-btn').forEach(btn => btn.addEventListener('click', () => updateStatus(btn.dataset.id, 'rejected')));
    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', () => deleteBooking(btn.dataset.id)));
    document.querySelectorAll('.conflict-btn').forEach(btn => btn.addEventListener('click', () => checkConflicts(btn.dataset.id)));
}

async function updateStatus(id, newStatus) {
    if (!confirm(`Confirm marking as ${newStatus}? An email will be dispatched.`)) return;
    try {
        let resp = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ action: 'update_status', booking_id: id, status: newStatus })
        });
        let data = await resp.json();
        if (data.success) {
            showToast(data.message);
            if (currentView === 'calendar') { loadCalendarEvents(); loadTableBookings(); }
            else loadTableBookings();
        } else {
            showToast(data.message, true);
            if (data.conflicts) showConflictModal(data.conflicts);
        }
    } catch(e) { showToast('Network error', true); }
}

async function deleteBooking(id) {
    if (!confirm('Permanently delete this booking? This action cannot be undone.')) return;
    try {
        let resp = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ action: 'delete_booking', booking_id: id })
        });
        let data = await resp.json();
        if (data.success) {
            showToast('Booking deleted');
            if (currentView === 'calendar') { loadCalendarEvents(); loadTableBookings(); }
            else loadTableBookings();
        } else showToast(data.message, true);
    } catch(e) { showToast('Network error', true); }
}

async function checkConflicts(id) {
    try {
        let resp = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ action: 'check_conflicts', booking_id: id })
        });
        let data = await resp.json();
        if (data.success) {
            if (data.conflicts && data.conflicts.length) showConflictModal(data.conflicts);
            else showToast('No scheduling conflicts found.', false);
        } else showToast(data.message, true);
    } catch(e) { showToast('Error checking conflicts', true); }
}

async function autoResolveConflicts() {
    if (!confirm('Auto-reject ALL pending bookings with conflicts? Warning: Emails will be sent.')) return;
    try {
        let resp = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ action: 'auto_resolve_conflicts' })
        });
        let data = await resp.json();
        if (data.success) showToast(`Successfully auto-rejected ${data.rejected} conflicting bookings`);
        else showToast('Auto-resolve failed', true);
        loadTableBookings();
        if (calendar) loadCalendarEvents();
    } catch(e) { showToast('Network error', true); }
}

async function autoRejectExpired() {
    try {
        let resp = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ action: 'auto_reject_expired' })
        });
        let data = await resp.json();
    } catch(e) {}
}

function showConflictModal(conflicts) {
    let list = document.getElementById('conflictList');
    list.innerHTML = conflicts.map(c => `<li><i class="fas fa-ban" style="color:var(--purple); margin-right:10px;"></i> ${escapeHtml(c)}</li>`).join('');
    document.getElementById('conflictModal').style.display = 'flex';
}

function closeModals() {
    document.getElementById('conflictModal').style.display = 'none';
    document.getElementById('actionModal').style.display = 'none';
}
document.getElementById('closeConflictModalBtn')?.addEventListener('click', closeModals);
document.getElementById('modalCloseBtn')?.addEventListener('click', closeModals);
window.addEventListener('click', e => {
    if (e.target === document.getElementById('conflictModal') || e.target === document.getElementById('actionModal')) closeModals();
});

// ==================== REVIEWS ====================
async function loadReviews() {
    try {
        let resp = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ action: 'get_reviews' })
        });
        let data = await resp.json();
        if (data.success) {
            renderReviewsTable(data.reviews);
            let total = data.reviews.length;
            let avg = data.reviews.reduce((s, r) => s + parseFloat(r.rating || 0), 0) / (total || 1);
            
            document.getElementById('reviewStats').innerHTML = `
                <div style="display:flex; gap: 2rem; align-items:center;">
                    <div style="text-align:center;"><span style="display:block; font-size:2.2rem; font-weight:800; color:var(--primary); line-height:1;">${avg.toFixed(1)}</span><span style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Avg Rating</span></div>
                    <div style="text-align:center;"><span style="display:block; font-size:2.2rem; font-weight:800; color:#fff; line-height:1;">${total}</span><span style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Total Reviews</span></div>
                </div>`;
            updateRatingChart(data.ratingCounts);
        } else showToast('Error loading reviews', true);
    } catch(e) { showToast('Network error', true); }
}

function updateRatingChart(counts) {
    let ctx = document.getElementById('ratingChart').getContext('2d');
    if (ratingChart) ratingChart.destroy();
    
    let gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, '#10b981'); gradient.addColorStop(1, '#059669');

    ratingChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'],
            datasets: [{
                label: ' Reviews',
                data: [counts[1], counts[2], counts[3], counts[4], counts[5]],
                backgroundColor: ['#ef4444', '#f97316', '#eab308', '#84cc16', gradient],
                borderRadius: 6, borderWidth: 0, barPercentage: 0.5
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { backgroundColor: 'rgba(15, 23, 42, 0.9)', titleColor: '#fff', bodyColor: '#cbd5e1', padding: 12, cornerRadius: 8 } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, color: '#64748b', font: {family: 'Inter'} }, grid: { color: 'rgba(255,255,255,0.05)' }, border: {display: false} },
                x: { ticks: { color: '#94a3b8', font: {family: 'Inter', weight: 500} }, grid: { display: false }, border: {display: false} }
            }
        }
    });
}

function renderReviewsTable(reviews) {
    let tbody = document.getElementById('reviewsTableBody');
    if (!reviews.length) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:3rem; color:#64748b;">No reviews available.</td></tr>';
        return;
    }
    let html = '';
    reviews.forEach(r => {
        html += `<tr>
            <td style="font-weight:600; color:var(--text-muted);">#${r.id}</td>
            <td style="font-weight:600;">${escapeHtml(r.name)}</td>
            <td>${renderStars(r.rating)}</td>
            <td style="line-height:1.6; max-width:400px;">"${escapeHtml(r.review)}"</td>
            <td style="font-size:0.85rem; color:var(--text-muted);"><i class="far fa-clock text-muted"></i> ${new Date(r.created_at).toLocaleDateString()}</td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

// ==================== AI CHAT WIDGET & MEMORY ====================
const chatWidget = document.getElementById('chatWidget');
const chatInput = document.getElementById('chatInput');
const chatMessages = document.getElementById('chatMessages');
const sendChatBtn = document.getElementById('sendChatBtn');

// Initialize greeting
function renderGreeting() {
    chatMessages.innerHTML = '';
    appendMessage('ai', `👋 Hi **${adminName}**! I'm your Intelligent UBook Assistant.<br><br>I remember our conversation context. Ask me to check conflicts, show pending bookings, or confirm requests!`, null, true);
}

document.getElementById('openAIChatBtn').onclick = () => {
    if(chatMessages.children.length === 0) renderGreeting();
    chatWidget.classList.toggle('active');
    if(chatWidget.classList.contains('active')) {
        setTimeout(() => chatInput.focus(), 300);
        scrollChatToBottom();
    }
};

document.getElementById('closeChatBtn').onclick = () => { chatWidget.classList.remove('active'); };

document.getElementById('clearChatBtn').onclick = () => {
    if(confirm("Clear chat history and reset the AI's memory?")) {
        chatHistory = [];
        renderGreeting();
        showToast("AI Memory Cleared");
    }
};

// Listen to Quick Chips
document.querySelectorAll('.quick-chip').forEach(chip => {
    chip.addEventListener('click', () => {
        chatInput.value = chip.innerText;
        chatInput.style.height = 'auto'; // reset textarea
        sendAIQuery();
    });
});

// Auto-expand textarea
chatInput.addEventListener('input', function() {
    this.style.height = '48px'; 
    this.style.height = (this.scrollHeight) + 'px';
});

function scrollChatToBottom() {
    setTimeout(() => { chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' }); }, 50);
}

function appendMessage(sender, text, id = null, isHtml = false) {
    let wrapperDiv = document.createElement('div');
    wrapperDiv.className = `message-wrapper ${sender}`;
    if (id) wrapperDiv.id = id;
    
    let icon = sender === 'ai' ? '<i class="fas fa-robot"></i>' : '<i class="fas fa-user"></i>';
    let timeStr = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

    let content = isHtml ? text : escapeHtml(text).replace(/\n/g, '<br>');

    wrapperDiv.innerHTML = `
        <div class="message">
            <div class="msg-avatar">${icon}</div>
            <div class="msg-content-container">
                <div class="msg-content">${content}</div>
                <div class="msg-time">${timeStr}</div>
            </div>
        </div>
    `;
    chatMessages.appendChild(wrapperDiv);
    scrollChatToBottom();
}

async function sendAIQuery() {
    let q = chatInput.value.trim();
    if (!q) return;
    
    // Append to UI and save to Memory
    appendMessage('user', q, null, false);
    chatHistory.push({ role: 'user', content: q });
    
    // Reset Input
    chatInput.value = '';
    chatInput.style.height = '48px';
    
    // Disable inputs and show loading state
    chatInput.disabled = true;
    sendChatBtn.disabled = true;
    sendChatBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i>';
    
    let typingId = 'typing-' + Date.now();
    let typingWrapper = document.createElement('div');
    typingWrapper.className = 'message-wrapper ai';
    typingWrapper.id = typingId;
    typingWrapper.innerHTML = `
        <div class="message">
            <div class="msg-avatar"><i class="fas fa-robot"></i></div>
            <div class="msg-content-container">
                <div class="msg-content" style="padding: 10px 18px;">
                    <div class="typing-indicator"><span></span><span></span><span></span></div>
                </div>
            </div>
        </div>
    `;
    chatMessages.appendChild(typingWrapper);
    scrollChatToBottom();
    
    try {
        let resp = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ 
                action: 'ai_query', 
                question: q,
                history: JSON.stringify(chatHistory)
            })
        });
        let data = await resp.json();
        
        document.getElementById(typingId).remove();
        
        if (data.success) {
            let answerStr = data.answer;
            
            // Render beautiful markdown if available and sanitize it
            let formattedAnswer = answerStr;
            if (typeof marked !== 'undefined') {
                formattedAnswer = marked.parse(answerStr);
                if (typeof DOMPurify !== 'undefined') {
                    formattedAnswer = DOMPurify.sanitize(formattedAnswer);
                }
            } else {
                formattedAnswer = escapeHtml(answerStr).replace(/\n/g, '<br>');
            }
            
            // Highlight action result if one occurred
            if (data.action_result) {
                formattedAnswer += `<div style="margin-top: 14px; padding: 12px 14px; background: rgba(16, 185, 129, 0.1); border-left: 4px solid var(--primary); border-radius: 8px; font-size: 0.95rem;">
                    <strong style="color:var(--primary); font-size:1.05em;"><i class="fas fa-check-circle"></i> Action Executed Successfully</strong><br>
                    <span style="color: var(--text-main); display:inline-block; margin-top:4px;">${escapeHtml(data.action_result)}</span>
                </div>`;
            }
            
            appendMessage('ai', formattedAnswer, null, true);

            // Save to context memory (limit to last 10 messages)
            chatHistory.push({ role: 'assistant', content: data.raw_answer || answerStr });
            if (chatHistory.length > 10) chatHistory = chatHistory.slice(-10);

        } else {
            appendMessage('ai', `<span style="color:var(--danger);"><i class="fas fa-exclamation-triangle"></i> Error: ${escapeHtml(data.message)}</span>`, null, true);
        }
        
        // Refresh tables silently if AI performed an action
        loadTableBookings();
        if(calendar) loadCalendarEvents();
        
    } catch(e) {
        if(document.getElementById(typingId)) document.getElementById(typingId).remove();
        appendMessage('ai', `<span style="color:var(--danger);"><i class="fas fa-wifi"></i> Network error. Please try again.</span>`, null, true);
    } finally {
        // Re-enable inputs
        chatInput.disabled = false;
        sendChatBtn.disabled = false;
        sendChatBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
        chatInput.focus();
    }
}

sendChatBtn.addEventListener('click', sendAIQuery);
chatInput.addEventListener('keydown', e => { 
    if (e.key === 'Enter' && !e.shiftKey) { // Allow shift+enter for new lines
        e.preventDefault();
        if (!sendChatBtn.disabled) sendAIQuery(); 
    } 
});

// ==================== UI SWITCHING & INIT ====================
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        let tab = btn.getAttribute('data-tab');
        document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));
        document.getElementById(tab + 'Tab').classList.add('active');
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        if (tab === 'bookings') {
            if (!calendar) initCalendar();
            else setTimeout(() => calendar.render(), 50); // Fix rendering bug on unhide
        }
        if (tab === 'reviews') loadReviews();
    });
});

document.getElementById('calendarViewBtn').onclick = () => {
    document.getElementById('calendarContainer').style.display = 'block';
    document.getElementById('tableContainer').style.display = 'none';
    document.getElementById('calendarViewBtn').classList.add('active');
    document.getElementById('tableViewBtn').classList.remove('active');
    currentView = 'calendar';
    if (calendar) { setTimeout(() => calendar.render(), 50); loadCalendarEvents(); }
    else initCalendar();
};

document.getElementById('tableViewBtn').onclick = () => {
    document.getElementById('calendarContainer').style.display = 'none';
    document.getElementById('tableContainer').style.display = 'block';
    document.getElementById('tableViewBtn').classList.add('active');
    document.getElementById('calendarViewBtn').classList.remove('active');
    currentView = 'table';
    loadTableBookings();
};

document.getElementById('refreshBtn').onclick = () => {
    if (currentView === 'calendar') loadCalendarEvents();
    else loadTableBookings();
    showToast("Data Refreshed", false);
};

document.getElementById('refreshReviewsBtn').onclick = () => {
    loadReviews();
    showToast("Reviews Refreshed", false);
};
document.getElementById('autoResolveBtn').addEventListener('click', autoResolveConflicts);
document.getElementById('statusFilter').addEventListener('change', () => {
    if (currentView === 'table') loadTableBookings();
});

document.querySelectorAll('#bookingStatsGrid .stat-card').forEach(card => {
    card.addEventListener('click', () => {
        let filter = card.getAttribute('data-filter');
        if (filter) {
            document.getElementById('statusFilter').value = filter;
            if (currentView === 'table') loadTableBookings();
            else document.getElementById('tableViewBtn').click();
        }
    });
});

// Initialization
document.addEventListener("DOMContentLoaded", () => {
    initCalendar();
    loadTableBookings();
    autoRejectExpired();
    loadReviews();
});
</script>
</body>
</html>

