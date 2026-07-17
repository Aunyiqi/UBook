<?php
// ==================== INITIAL SETUP ====================
ob_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

if (!function_exists('ajaxErrorHandler')) {
    function ajaxErrorHandler($errno, $errstr, $errfile, $errline) {
        if (isset($_GET['ajax']) || isset($_POST['ajax'])) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['error' => "PHP Error: $errstr in $errfile on line $errline"]);
            exit();
        }
        return false;
    }
}
set_error_handler('ajaxErrorHandler');

session_start();

if (!isset($_SESSION['user_id']) && !(isset($_GET['ajax']) || isset($_POST['ajax']))) {
    header('Location: login.php');
    exit();
}

$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$current_user_name = $_SESSION['name'] ?? $_SESSION['username'] ?? 'User';
$current_user_email = $_SESSION['email'] ?? '';

if (isset($_GET['ajax']) || isset($_POST['ajax'])) {
    session_write_close();
}

require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define('MAIL_ENABLED', true);
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_SECURE', 'tls');
define('MAIL_USER', 'aunyiqi168@gmail.com');
define('MAIL_PASS', '');
define('MAIL_FROM', 'aunyiqi168@gmail.com');
define('MAIL_FROM_NAME', 'UBook Campus Venues');
define('MAIL_BCC', 'aunyiqi168@gmail.com');

define('DEEPSEEK_API_KEY', '');
define('DEEPSEEK_API_URL', 'https://api.deepseek.com/chat/completions');

if (!function_exists('getDB')) {
    function getDB() {
        static $conn = null;
        if ($conn === null) {
            $conn = new mysqli("localhost", "root", "", "ubook");
            if ($conn->connect_error) {
                if (isset($_GET['ajax']) || isset($_POST['ajax'])) {
                    ob_clean();
                    header('Content-Type: application/json');
                    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
                } else {
                    die("DB connection failed: " . $conn->connect_error);
                }
            }
            $conn->set_charset("utf8mb4");
        }
        return $conn;
    }
}

if (!function_exists('ensureTables')) {
    function ensureTables() {
        $conn = getDB();
        $conn->query("CREATE TABLE IF NOT EXISTS `users` (
            `id` INT PRIMARY KEY,
            `username` VARCHAR(100) UNIQUE,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100),
            `profile_image` VARCHAR(255),
            `role` VARCHAR(50) DEFAULT 'user'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'username'");
        if ($colCheck->num_rows == 0) {
            $conn->query("ALTER TABLE users ADD COLUMN username VARCHAR(100) UNIQUE");
            $conn->query("UPDATE users SET username = LOWER(REPLACE(name, ' ', '_')) WHERE username IS NULL");
            $dupCheck = $conn->query("SELECT username, COUNT(*) as cnt FROM users WHERE id != 0 GROUP BY username HAVING cnt > 1");
            while ($dup = $dupCheck->fetch_assoc()) {
                $badName = $dup['username'];
                $conn->query("UPDATE users SET username = CONCAT(username, '_', id) WHERE username = '$badName' AND id != 0");
            }
        }

        $roleCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
        if ($roleCheck->num_rows == 0) {
            $conn->query("ALTER TABLE users ADD COLUMN role VARCHAR(50) DEFAULT 'user'");
        }

        $conn->query("CREATE TABLE IF NOT EXISTS `venues` (
            `id` VARCHAR(20) PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT,
            `image_url` VARCHAR(255)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS `bookings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `student_name` VARCHAR(100) NOT NULL,
            `venue_id` VARCHAR(20) NOT NULL,
            `booking_date` DATE NOT NULL,
            `start_time` TIME NOT NULL,
            `duration_hours` INT NOT NULL,
            `comment` TEXT,
            `status` ENUM('pending','confirmed','rejected') DEFAULT 'pending',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`venue_id`) REFERENCES `venues`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS `chat_groups` (
            `event_name` varchar(255) NOT NULL,
            `category` varchar(50) DEFAULT 'General',
            `cover_url` text,
            `description` text,
            `created_by` int(11) DEFAULT 0,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`event_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS `chat_group_participants` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `event_name` varchar(255) NOT NULL,
            `user_id` int(11) NOT NULL,
            `joined_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `event_user` (`event_name`,`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS `chat_group_messages` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `event_name` varchar(255) NOT NULL,
            `user_id` int(11) NOT NULL,
            `message` text NOT NULL,
            `is_deleted` tinyint(1) DEFAULT 0,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `event_name` (`event_name`),
            KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS `chat_group_typing` (
            `event_name` varchar(255) NOT NULL,
            `user_id` int(11) NOT NULL,
            `updated_at` int(11) NOT NULL,
            PRIMARY KEY (`event_name`, `user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Backward compatibility
        $delCheck = $conn->query("SHOW COLUMNS FROM chat_group_messages LIKE 'is_deleted'");
        if ($delCheck->num_rows == 0) $conn->query("ALTER TABLE chat_group_messages ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");

        $catCheck = $conn->query("SHOW COLUMNS FROM chat_groups LIKE 'category'");
        if ($catCheck->num_rows == 0) $conn->query("ALTER TABLE chat_groups ADD COLUMN category VARCHAR(50) DEFAULT 'General'");

        $creatorCheck = $conn->query("SHOW COLUMNS FROM chat_groups LIKE 'created_by'");
        if ($creatorCheck->num_rows == 0) $conn->query("ALTER TABLE chat_groups ADD COLUMN created_by INT DEFAULT 0");

        // Ensure AI user exists
        $aiCheck = $conn->query("SELECT id FROM users WHERE id = 0");
        if (!$aiCheck || $aiCheck->num_rows == 0) {
            $conn->query("INSERT INTO users (id, username, name, email, role) VALUES (0, 'ai_assistant', 'AI Assistant', 'ai@ubook.com', 'bot')");
        } else {
            $conn->query("UPDATE users SET username = 'ai_assistant', name = 'AI Assistant', role = 'bot' WHERE id = 0 AND (username IS NULL OR username = '')");
        }

        // Seed default venues if empty
        $venueCheck = $conn->query("SELECT COUNT(*) FROM venues");
        if ($venueCheck && $venueCheck->fetch_row()[0] == 0) {
            $defaultVenues = [
                ['id' => 'bas101', 'name' => 'Basketball Court', 'description' => 'Indoor hardwood court.', 'image_url' => 'https://images.pexels.com/photos/260447/pexels-photo-260447.jpeg'],
                ['id' => 'cpl616', 'name' => 'Computer Lab', 'description' => '30 PCs with dual monitors.', 'image_url' => 'https://images.pexels.com/photos/590493/pexels-photo-590493.jpeg'],
                ['id' => 'exm456', 'name' => 'Exam Hall', 'description' => 'Quiet hall with individual desks.', 'image_url' => 'https://images.pexels.com/photos/256514/pexels-photo-256514.jpeg'],
                ['id' => 'fld112', 'name' => 'Field', 'description' => 'Large grassy field.', 'image_url' => 'https://images.pexels.com/photos/164530/pexels-photo-164530.jpeg'],
                ['id' => 'lec131', 'name' => 'Lecture Hall', 'description' => 'Tiered seating, smart board.', 'image_url' => 'https://images.pexels.com/photos/1181406/pexels-photo-1181406.jpeg'],
                ['id' => 'mme123', 'name' => 'Main Hall', 'description' => 'Spacious hall with stage.', 'image_url' => 'https://images.pexels.com/photos/2774556/pexels-photo-2774556.jpeg'],
                ['id' => 'sml415', 'name' => 'Smart Lab', 'description' => 'IoT devices, VR headsets.', 'image_url' => 'https://images.pexels.com/photos/1181467/pexels-photo-1181467.jpeg'],
                ['id' => 'vol789', 'name' => 'Volleyball Court', 'description' => 'Outdoor sand court.', 'image_url' => 'https://images.pexels.com/photos/6994664/pexels-photo-6994664.jpeg'],
            ];
            $stmt = $conn->prepare("INSERT INTO venues (id, name, description, image_url) VALUES (?, ?, ?, ?)");
            foreach ($defaultVenues as $v) {
                $stmt->bind_param("ssss", $v['id'], $v['name'], $v['description'], $v['image_url']);
                $stmt->execute();
            }
        }
    }
}
ensureTables();

if (!function_exists('timeToMinutes')) {
    function timeToMinutes($timeStr) {
        $parts = explode(':', $timeStr);
        return (int)$parts[0] * 60 + (int)$parts[1];
    }
}

if (!function_exists('getVenueList')) {
    function getVenueList($conn) {
        static $venues = null;
        if ($venues === null) {
            $result = $conn->query("SELECT id, name FROM venues");
            $venues = [];
            while ($row = $result->fetch_assoc()) $venues[] = $row;
        }
        return $venues;
    }
}

if (!function_exists('callDeepSeek')) {
    function callDeepSeek($messages) {
        $ch = curl_init(DEEPSEEK_API_URL);
        $payload = [
            'model' => 'deepseek-chat',
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 600
        ];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . DEEPSEEK_API_KEY
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) throw new Exception("DeepSeek API error (HTTP $httpCode)");
        $data = json_decode($response, true);
        if (!isset($data['choices'][0]['message']['content'])) throw new Exception("Invalid API response structure");
        return trim($data['choices'][0]['message']['content']);
    }
}

if (!function_exists('sendBookingConfirmation')) {
    function sendBookingConfirmation($toEmail, $studentName, $bookingDetails) {
        if (!MAIL_ENABLED || empty($toEmail) || empty(MAIL_USER) || empty(MAIL_PASS)) return false;
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USER;
            $mail->Password = MAIL_PASS;
            $mail->SMTPSecure = MAIL_SECURE;
            $mail->Port = MAIL_PORT;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($toEmail, $studentName);
            if (defined('MAIL_BCC') && MAIL_BCC) $mail->addBCC(MAIL_BCC);
            $subject = 'UBook: Your AI-assisted booking request';
            $html = "<h2>Hello " . htmlspecialchars($studentName) . "</h2>
                     <p>Your booking request via AI Assistant has been submitted and is pending approval.</p>
                     <ul>
                         <li><strong>Venue:</strong> " . htmlspecialchars($bookingDetails['venue_name']) . "</li>
                         <li><strong>Date:</strong> " . htmlspecialchars($bookingDetails['date']) . "</li>
                         <li><strong>Time:</strong> " . htmlspecialchars($bookingDetails['time']) . "</li>
                         <li><strong>Duration:</strong> " . (int)$bookingDetails['duration'] . " hour(s)</li>
                         " . (!empty($bookingDetails['comment']) ? "<li><strong>Comment:</strong> " . htmlspecialchars($bookingDetails['comment']) . "</li>" : "") . "
                     </ul>
                     <p>You will receive another email once approved or rejected.</p>
                     <p>Thank you for using UBook!</p>";
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = strip_tags($html);
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mail error: " . $mail->ErrorInfo);
            return false;
        }
    }
}

if (!function_exists('attemptBooking')) {
    function attemptBooking($conn, $userId, $userName, $venueId, $date, $time, $duration, $comment, $notifyEmail) {
        $duration = max(1, min(4, (int)$duration));
        $stmt = $conn->prepare("SELECT name FROM venues WHERE id = ?");
        $stmt->bind_param("s", $venueId);
        $stmt->execute();
        $venue = $stmt->get_result()->fetch_assoc();
        if (!$venue) return ['success' => false, 'message' => 'Invalid venue ID'];

        $conflictStmt = $conn->prepare("SELECT start_time, duration_hours FROM bookings WHERE venue_id = ? AND booking_date = ? AND status = 'confirmed'");
        $conflictStmt->bind_param("ss", $venueId, $date);
        $conflictStmt->execute();
        $newStart = timeToMinutes($time);
        $newEnd = $newStart + $duration * 60;
        $conflicts = $conflictStmt->get_result();
        while ($row = $conflicts->fetch_assoc()) {
            $exStart = timeToMinutes($row['start_time']);
            $exEnd = $exStart + $row['duration_hours'] * 60;
            if ($newStart < $exEnd && $newEnd > $exStart) {
                return ['success' => false, 'message' => "Time conflict: {$row['start_time']} for {$row['duration_hours']}h already booked."];
            }
        }

        $insert = $conn->prepare("INSERT INTO bookings (user_id, student_name, venue_id, booking_date, start_time, duration_hours, comment, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        $insert->bind_param("issssis", $userId, $userName, $venueId, $date, $time, $duration, $comment);
        if (!$insert->execute()) {
            return ['success' => false, 'message' => 'Database insert failed: ' . $conn->error];
        }

        // Send email confirmation if email provided
        if (!empty($notifyEmail)) {
            sendBookingConfirmation($notifyEmail, $userName, [
                'venue_name' => $venue['name'],
                'date' => $date,
                'time' => $time,
                'duration' => $duration,
                'comment' => $comment
            ]);
        }
        return ['success' => true, 'message' => "Booking request submitted for {$venue['name']} on {$date} at {$time} for {$duration} hour(s)."];
    }
}

// ==================== AJAX HANDLER ====================
if (isset($_GET['ajax']) || isset($_POST['ajax'])) {
    ob_clean();
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $conn = getDB();

    if (!function_exists('sendJsonError')) {
        function sendJsonError($msg) { echo json_encode(['error' => $msg]); exit(); }
    }

    if ($action === 'get_my_groups') {
        $category = $_GET['category'] ?? '';
        $sql = "SELECT DISTINCT p.event_name, p.joined_at, g.cover_url, g.description, g.category, g.created_by,
                (SELECT COUNT(*) FROM chat_group_participants WHERE event_name = g.event_name) as member_count
                FROM chat_group_participants p 
                LEFT JOIN chat_groups g ON p.event_name = g.event_name 
                WHERE p.user_id = ?";
        if (!empty($category) && $category !== 'All') {
            $sql .= " AND g.category = ?";
        }
        $sql .= " ORDER BY p.joined_at DESC";
        $stmt = $conn->prepare($sql);
        if (!empty($category) && $category !== 'All') {
            $stmt->bind_param("is", $current_user_id, $category);
        } else {
            $stmt->bind_param("i", $current_user_id);
        }
        $stmt->execute();
        $groups = [];
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $msgStmt = $conn->prepare("SELECT message, is_deleted, created_at, id FROM chat_group_messages WHERE event_name = ? AND user_id != -1 ORDER BY created_at DESC LIMIT 1");
            $msgStmt->bind_param("s", $row['event_name']);
            $msgStmt->execute();
            $lastMsg = $msgStmt->get_result()->fetch_assoc();
            
            if ($lastMsg) {
                $preview = strip_tags(preg_replace('/(\*\*|\*)/', '', $lastMsg['message']));
                $row['last_message'] = $lastMsg['is_deleted'] ? '🚫 Deleted message' : htmlspecialchars(mb_substr($preview, 0, 50)) . (mb_strlen($preview) > 50 ? '...' : '');
                $row['last_time'] = $lastMsg['created_at'];
                $row['last_message_id'] = $lastMsg['id'];
            } else {
                $row['last_message'] = 'No messages yet';
                $row['last_time'] = $row['joined_at'];
                $row['last_message_id'] = 0;
            }
            if (empty($row['cover_url'])) {
                $row['cover_url'] = "https://ui-avatars.com/api/?name=" . urlencode($row['event_name']) . "&background=e67e22&color=fff";
            }
            $groups[] = $row;
        }
        
        usort($groups, function($a, $b) { return strtotime($b['last_time']) - strtotime($a['last_time']); });
        
        echo json_encode(['success' => true, 'groups' => $groups]);
        exit();
    }

    if ($action === 'join_group') {
        $event_name = trim($_POST['event_name'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        if (empty($event_name)) sendJsonError('Event name required');
        
        $checkGroup = $conn->prepare("SELECT event_name, created_by FROM chat_groups WHERE event_name = ?");
        $checkGroup->bind_param("s", $event_name);
        $checkGroup->execute();
        $existing = $checkGroup->get_result()->fetch_assoc();
        
        if (!$existing) {
            $defaultCover = "https://ui-avatars.com/api/?name=" . urlencode($event_name) . "&background=random&color=fff";
            $desc = "Group chat for " . htmlspecialchars($event_name);
            $insMeta = $conn->prepare("INSERT INTO chat_groups (event_name, category, cover_url, description, created_by) VALUES (?, ?, ?, ?, ?)");
            $insMeta->bind_param("ssssi", $event_name, $category, $defaultCover, $desc, $current_user_id);
            $insMeta->execute();
        }
        
        $stmt = $conn->prepare("INSERT IGNORE INTO chat_group_participants (event_name, user_id) VALUES (?, ?)");
        $stmt->bind_param("si", $event_name, $current_user_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $sysMsg = $current_user_name . " joined the group.";
            $sysStmt = $conn->prepare("INSERT INTO chat_group_messages (event_name, user_id, message, created_at) VALUES (?, -1, ?, NOW())");
            $sysStmt->bind_param("ss", $event_name, $sysMsg);
            $sysStmt->execute();
        }
        
        echo json_encode(['success' => true, 'event_name' => $event_name, 'is_creator' => ($existing && $existing['created_by'] == $current_user_id)]);
        exit();
    }

    if ($action === 'leave_group') {
        $event_name = trim($_POST['event_name'] ?? '');
        if (empty($event_name)) sendJsonError('Event name required');
        $stmt = $conn->prepare("DELETE FROM chat_group_participants WHERE event_name = ? AND user_id = ?");
        $stmt->bind_param("si", $event_name, $current_user_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $check = $conn->prepare("SELECT COUNT(*) as cnt FROM chat_group_participants WHERE event_name = ?");
            $check->bind_param("s", $event_name);
            $check->execute();
            $cnt = $check->get_result()->fetch_assoc()['cnt'];
            if ($cnt == 0) {
                $del = $conn->prepare("DELETE FROM chat_groups WHERE event_name = ?");
                $del->bind_param("s", $event_name);
                $del->execute();
            } else {
                $sysMsg = $current_user_name . " left the group.";
                $sysStmt = $conn->prepare("INSERT INTO chat_group_messages (event_name, user_id, message, created_at) VALUES (?, -1, ?, NOW())");
                $sysStmt->bind_param("ss", $event_name, $sysMsg);
                $sysStmt->execute();
            }
        }
        echo json_encode(['success' => true]);
        exit();
    }

    if ($action === 'delete_message') {
        $msg_id = (int)($_POST['msg_id'] ?? 0);
        if ($msg_id > 0) {
            $check = $conn->prepare("SELECT m.user_id, g.created_by FROM chat_group_messages m LEFT JOIN chat_groups g ON m.event_name = g.event_name WHERE m.id = ?");
            $check->bind_param("i", $msg_id);
            $check->execute();
            $res = $check->get_result()->fetch_assoc();
            
            if ($res && ($res['user_id'] == $current_user_id || $res['created_by'] == $current_user_id)) {
                $stmt = $conn->prepare("UPDATE chat_group_messages SET is_deleted = 1 WHERE id = ?");
                $stmt->bind_param("i", $msg_id);
                $stmt->execute();
                echo json_encode(['success' => true]);
            } else {
                sendJsonError('Permission denied');
            }
        } else {
            sendJsonError('Invalid message ID');
        }
        exit();
    }

    if ($action === 'edit_group') {
        $event_name = trim($_POST['event_name'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $cover_url = trim($_POST['cover_url'] ?? '');
        $check = $conn->prepare("SELECT created_by FROM chat_groups WHERE event_name = ?");
        $check->bind_param("s", $event_name);
        $check->execute();
        $creator = $check->get_result()->fetch_assoc()['created_by'] ?? 0;
        if ($creator != $current_user_id) sendJsonError('Only the group creator can edit');
        
        if ($name !== $event_name) {
            $dup = $conn->prepare("SELECT 1 FROM chat_groups WHERE event_name = ?");
            $dup->bind_param("s", $name);
            $dup->execute();
            if ($dup->get_result()->num_rows > 0) sendJsonError('Group name already exists');
            $conn->begin_transaction();
            try {
                $conn->prepare("UPDATE chat_groups SET event_name = ?, description = ?, cover_url = ? WHERE event_name = ?")->execute([$name, $description, $cover_url, $event_name]);
                $conn->prepare("UPDATE chat_group_participants SET event_name = ? WHERE event_name = ?")->execute([$name, $event_name]);
                $conn->prepare("UPDATE chat_group_messages SET event_name = ? WHERE event_name = ?")->execute([$name, $event_name]);
                $conn->commit();
                echo json_encode(['success' => true, 'new_name' => $name]);
            } catch (Exception $e) {
                $conn->rollback();
                sendJsonError('Failed to rename group');
            }
        } else {
            $upd = $conn->prepare("UPDATE chat_groups SET description = ?, cover_url = ? WHERE event_name = ?");
            $upd->bind_param("sss", $description, $cover_url, $event_name);
            $upd->execute();
            echo json_encode(['success' => true, 'new_name' => $name]);
        }
        exit();
    }

    if ($action === 'get_members') {
        $event_name = $_GET['event_name'] ?? '';
        if (empty($event_name)) sendJsonError('Event name required');
        $stmt = $conn->prepare("SELECT u.id, u.name, u.username, u.profile_image, 
                                (SELECT 1 FROM chat_groups WHERE event_name = ? AND created_by = u.id) as is_creator
                                FROM chat_group_participants p
                                JOIN users u ON u.id = p.user_id
                                WHERE p.event_name = ?");
        $stmt->bind_param("ss", $event_name, $event_name);
        $stmt->execute();
        $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'members' => $members]);
        exit();
    }

    if ($action === 'search_messages') {
        $event_name = $_GET['event_name'] ?? '';
        $query = $_GET['query'] ?? '';
        $like = "%$query%";
        $stmt = $conn->prepare("SELECT m.id, m.user_id, m.message, m.created_at, u.name as user_name
                                FROM chat_group_messages m
                                JOIN users u ON u.id = m.user_id
                                WHERE m.event_name = ? AND m.message LIKE ? AND m.user_id != -1 AND m.is_deleted = 0
                                ORDER BY m.created_at DESC LIMIT 50");
        $stmt->bind_param("ss", $event_name, $like);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($results as &$r) {
            $r['message'] = htmlspecialchars($r['message']);
            $r['user_name'] = htmlspecialchars($r['user_name']);
        }
        echo json_encode(['success' => true, 'results' => $results]);
        exit();
    }

    if ($action === 'typing') {
        $event_name = $_POST['event_name'] ?? '';
        if (!empty($event_name)) {
            $stmt = $conn->prepare("INSERT INTO chat_group_typing (event_name, user_id, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE updated_at = ?");
            $now = time();
            $stmt->bind_param("siii", $event_name, $current_user_id, $now, $now);
            $stmt->execute();
        }
        echo json_encode(['success' => true]);
        exit();
    }
    
    if ($action === 'get_typing') {
        $event_name = $_GET['event_name'] ?? '';
        $typing = [];
        if (!empty($event_name)) {
            $limit = time() - 4; 
            $stmt = $conn->prepare("SELECT u.name FROM chat_group_typing t JOIN users u ON u.id = t.user_id WHERE t.event_name = ? AND t.user_id != ? AND t.updated_at > ?");
            $stmt->bind_param("sii", $event_name, $current_user_id, $limit);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $typing[] = explode(' ', $row['name'])[0];
            }
        }
        echo json_encode(['success' => true, 'typing' => $typing]);
        exit();
    }

    if ($action === 'send_group_message') {
        $event_name = trim($_POST['event_name'] ?? '');
        $message = trim($_POST['message'] ?? '');
        if (empty($event_name) || empty($message)) sendJsonError('Event name and message required');
        
        $join = $conn->prepare("INSERT IGNORE INTO chat_group_participants (event_name, user_id) VALUES (?, ?)");
        $join->bind_param("si", $event_name, $current_user_id);
        $join->execute();
        
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("INSERT INTO chat_group_messages (event_name, user_id, message, created_at) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("siss", $event_name, $current_user_id, $message, $now);
        
        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            $ai_reply = null;
            
            // Trigger DeepSeek AI for generic questions (if not a booking command)
            if (preg_match('/^@AI\b/i', $message) && !preg_match('/^@AI\s*(book|booking)/i', $message)) {
                $prompt = trim(preg_replace('/^@AI\b/i', '', $message));
                if (!empty($prompt)) {
                    $histStmt = $conn->prepare("SELECT u.name, m.message, m.user_id FROM chat_group_messages m LEFT JOIN users u ON u.id = m.user_id WHERE m.event_name = ? AND m.is_deleted = 0 AND m.user_id != -1 ORDER BY m.created_at DESC LIMIT 10");
                    $histStmt->bind_param("s", $event_name);
                    $histStmt->execute();
                    $histRes = array_reverse($histStmt->get_result()->fetch_all(MYSQLI_ASSOC));
                    
                    $messages_ai = [
                        ['role' => 'system', 'content' => "You are UBook AI Assistant inside a university campus group chat named '$event_name'. Keep your answers concise, friendly, and helpful. You can format with Markdown."]
                    ];
                    foreach ($histRes as $h) {
                        if (trim($h['message']) === '') continue;
                        $role = ($h['user_id'] == 0) ? 'assistant' : 'user';
                        $prefix = ($h['user_id'] == 0) ? '' : ($h['name'] ?? 'User') . ': ';
                        $messages_ai[] = ['role' => $role, 'content' => $prefix . $h['message']];
                    }
                    
                    try {
                        $ai_reply_text = callDeepSeek($messages_ai);
                        if ($ai_reply_text) {
                            $ai_now = date('Y-m-d H:i:s');
                            $stmtAi = $conn->prepare("INSERT INTO chat_group_messages (event_name, user_id, message, created_at) VALUES (?, 0, ?, ?)");
                            $stmtAi->bind_param("sss", $event_name, $ai_reply_text, $ai_now);
                            $stmtAi->execute();
                            $ai_msg_id = $stmtAi->insert_id;
                            
                            $ai_reply = [
                                'id' => $ai_msg_id,
                                'user_id' => 0,
                                'message' => $ai_reply_text,
                                'created_at' => $ai_now,
                                'sender_name' => 'AI Assistant',
                                'is_mine' => false,
                                'is_deleted' => false
                            ];
                        }
                    } catch (Exception $e) {
                        error_log("DeepSeek Error: " . $e->getMessage());
                    }
                }
            }
            echo json_encode(['success' => true, 'message_id' => $new_id, 'ai_reply' => $ai_reply]);
        } else {
            sendJsonError('Failed to send group message.');
        }
        exit();
    }

    if ($action === 'get_group_messages') {
        $event_name = $_GET['event_name'] ?? '';
        $since_id = intval($_GET['since_id'] ?? 0);
        $limit = intval($_GET['limit'] ?? 40);
        $offset = intval($_GET['offset'] ?? 0);
        
        if ($since_id > 0) {
            $sql = "SELECT m.*, u.username, u.name FROM chat_group_messages m LEFT JOIN users u ON u.id = m.user_id WHERE m.event_name = ? AND m.id > ? ORDER BY m.created_at ASC LIMIT ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $event_name, $since_id, $limit);
        } else {
            $sql = "SELECT * FROM (SELECT m.*, u.username, u.name FROM chat_group_messages m LEFT JOIN users u ON u.id = m.user_id WHERE m.event_name = ? ORDER BY m.created_at DESC LIMIT ? OFFSET ?) AS sub ORDER BY id ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $event_name, $limit, $offset);
        }
        $stmt->execute();
        $messages = [];
        $res = $stmt->get_result();
        
        while ($row = $res->fetch_assoc()) {
            $sender_name = 'Unknown';
            if ($row['user_id'] == 0) $sender_name = 'AI Assistant';
            elseif ($row['user_id'] == -1) $sender_name = 'System';
            elseif (!empty($row['name'])) $sender_name = $row['name'];
            
            $messages[] = [
                'id' => $row['id'],
                'user_id' => $row['user_id'],
                'message' => $row['message'], 
                'is_deleted' => (bool)$row['is_deleted'],
                'created_at' => $row['created_at'],
                'sender_name' => $sender_name,
                'is_mine' => ($row['user_id'] == $current_user_id)
            ];
        }
        echo json_encode(['success' => true, 'messages' => $messages]);
        exit();
    }

    if ($action === 'search_events') {
        $search = $_GET['search'] ?? '';
        $stmt = $conn->prepare("SELECT DISTINCT event_name FROM chat_groups WHERE event_name LIKE ? LIMIT 20");
        $searchParam = "%$search%";
        $stmt->bind_param("s", $searchParam);
        $stmt->execute();
        $events = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'event_name');
        echo json_encode(['success' => true, 'events' => $events]);
        exit();
    }

    if ($action === 'submit_booking_form') {
        $chat_type = $_POST['chat_type'] ?? '';
        $chat_id = $_POST['chat_id'] ?? '';
        $venue_id = $_POST['venue_id'] ?? '';
        $booking_date = $_POST['booking_date'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $duration = intval($_POST['duration'] ?? 2);
        $comment = trim($_POST['comment'] ?? '');

        if (empty($chat_id) || empty($venue_id) || empty($booking_date) || empty($start_time)) sendJsonError('Missing fields');

        $stmt = $conn->prepare("SELECT name FROM venues WHERE id = ?");
        $stmt->bind_param("s", $venue_id);
        $stmt->execute();
        $venue = $stmt->get_result()->fetch_assoc();
        
        $bookingResult = attemptBooking($conn, $current_user_id, $current_user_name, $venue_id, $booking_date, $start_time, $duration, $comment, $current_user_email);

        $userMessage = "@AI Booking request:\n**Venue:** {$venue['name']}\n**Date:** $booking_date\n**Time:** $start_time\n**Duration:** {$duration}h" . ($comment ? "\n**Comment:** $comment" : "");
        $aiReply = $bookingResult['success'] 
            ? "✅ " . $bookingResult['message'] . " A confirmation email will be sent to you."
            : "❌ Booking failed: " . $bookingResult['message'] . " Please try another time.";

        $now = date('Y-m-d H:i:s');
        $stmtUser = $conn->prepare("INSERT INTO chat_group_messages (event_name, user_id, message, created_at) VALUES (?, ?, ?, ?)");
        $stmtUser->bind_param("siss", $chat_id, $current_user_id, $userMessage, $now);
        $stmtUser->execute();
        $newUserMsgId = $stmtUser->insert_id;
        
        $stmtAi = $conn->prepare("INSERT INTO chat_group_messages (event_name, user_id, message, created_at) VALUES (?, 0, ?, ?)");
        $stmtAi->bind_param("sss", $chat_id, $aiReply, $now);
        $stmtAi->execute();
        $newAiMsgId = $stmtAi->insert_id;

        echo json_encode([
            'success' => true,
            'user_message_id' => $newUserMsgId,
            'ai_message_id' => $newAiMsgId,
            'ai_message' => $aiReply
        ]);
        exit();
    }
    exit();
}
ob_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>UBook · Community Chat</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ===== RESET & BASE ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        :root { --primary: #e67e22; --primary-light: #f39c12; --dark: #d35400; --light: #ffffff; --text: #333; --bg: #f8fafc; }
        html, body { height: 100%; height: 100dvh; margin: 0; padding: 0; overflow: hidden; }
        body { background: linear-gradient(135deg, #fef9e6 0%, #f9f5eb 100%); display: flex; flex-direction: column; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* ===== HEADER ===== */
        header { flex-shrink: 0; background: linear-gradient(135deg, var(--dark), var(--primary)); padding: 15px 5%; color: white; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1); position: relative; z-index: 100; }
        .logo a { color: white; text-decoration: none; font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .nav-links { display: flex; gap: 20px; }
        .nav-links a { color: white; text-decoration: none; font-weight: 500; padding: 6px 15px; border-radius: 20px; transition: 0.3s; }
        .nav-links a:hover, .nav-links a.active { background: rgba(255,255,255,0.2); }
        .header-icons { display: flex; gap: 15px; align-items: center; }
        .header-icons a { color: white; text-decoration: none; display: flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 50%; background: rgba(255,255,255,0.15); transition: 0.2s; }
        .header-icons a:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px); }
        .logout-btn { padding: 0 15px !important; width: auto !important; border-radius: 20px !important; gap: 8px; font-size: 0.9rem; font-weight: 600; }

        /* ===== CHAT WRAPPER ===== */
        .chat-wrapper { flex: 1; min-height: 0; max-width: 1500px; width: 95%; margin: 15px auto; background: white; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); display: flex; overflow: hidden; border: 1px solid #e2e8f0; }

        /* ===== SIDEBAR ===== */
        .sidebar { width: 360px; background: #fff; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; z-index: 10; flex-shrink: 0; min-height: 0; }
        .sidebar-header { flex-shrink: 0; padding: 20px; border-bottom: 1px solid #e2e8f0; }
        .sidebar-header h2 { font-size: 1.4rem; color: #1e293b; display: flex; align-items: center; gap: 10px; font-weight: 700; margin: 0; }
        .user-profile-bar { flex-shrink: 0; padding: 15px 20px; background: #fef9f0; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #fee2e2; }
        .user-profile-bar .avatar { width: 36px; height: 36px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .user-profile-bar .name { font-weight: 600; color: var(--dark); font-size: 0.95rem; }

        .category-filter { flex-shrink: 0; padding: 12px 20px; display: flex; gap: 8px; overflow-x: auto; border-bottom: 1px solid #e2e8f0; background: #fafafa; scrollbar-width: none; }
        .category-filter::-webkit-scrollbar { display: none; }
        .cat-btn { background: white; border: 1px solid #cbd5e1; padding: 6px 14px; border-radius: 20px; cursor: pointer; font-size: 0.85rem; font-weight: 500; color: #475569; white-space: nowrap; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .cat-btn.active { background: var(--primary); border-color: var(--primary); color: white; }
        .cat-badge { background: rgba(0,0,0,0.1); border-radius: 20px; padding: 2px 6px; font-size: 0.7rem; font-weight: 700; }
        .cat-btn.active .cat-badge { background: rgba(255,255,255,0.3); }

        .group-list { flex: 1; min-height: 0; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 6px; }
        .group-item { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 16px; cursor: pointer; transition: 0.2s; border: 1px solid transparent; }
        .group-item:hover { background: #f8fafc; border-color: #e2e8f0; }
        .group-item.active { background: #fff7ed; border-color: #fdba74; }
        .group-avatar { width: 48px; height: 48px; border-radius: 14px; object-fit: cover; flex-shrink: 0; background: #e2e8f0; }
        .group-info { flex: 1; min-width: 0; }
        .group-name { font-weight: 600; color: #1e293b; font-size: 0.95rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px; }
        .group-time { font-size: 0.7rem; color: #94a3b8; font-weight: 500; margin-left: 4px; }
        .group-last-msg { font-size: 0.8rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .unread-badge { width: 10px; height: 10px; background: #ef4444; border-radius: 50%; box-shadow: 0 0 0 2px white; flex-shrink: 0; display: inline-block; margin-left: 6px;}

        .join-btn-container { flex-shrink: 0; padding: 16px; border-top: 1px solid #e2e8f0; background: white; }
        .join-btn { width: 100%; padding: 12px; border-radius: 12px; background: #f1f5f9; border: 1px dashed #cbd5e1; color: #475569; font-weight: 600; cursor: pointer; transition: 0.2s; display: flex; justify-content: center; gap: 8px; }
        .join-btn:hover { background: #e2e8f0; color: var(--primary); border-color: var(--primary); }

        /* ===== MAIN CHAT ===== */
        .chat-main { flex: 1; min-width: 0; min-height: 0; display: flex; flex-direction: column; background: #f8fafc; position: relative; }
        .chat-header { flex-shrink: 0; padding: 16px 24px; background: white; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; z-index: 5; }
        .chat-header-info { display: flex; align-items: center; gap: 12px; }
        .mobile-back { display: none; background: none; border: none; font-size: 1.2rem; color: #64748b; cursor: pointer; padding-right: 8px; }
        .chat-title { font-size: 1.25rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px; }
        .chat-badge { background: #eef2ff; color: #4f46e5; font-size: 0.75rem; padding: 2px 8px; border-radius: 12px; font-weight: 600; }
        .chat-actions { display: flex; gap: 10px; }
        .action-icon { width: 38px; height: 38px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #64748b; cursor: pointer; transition: 0.2s; border: none; }
        .action-icon:hover { background: var(--primary); color: white; }

        .messages-container { flex: 1; min-height: 0; overflow-y: auto; padding: 24px; display: flex; flex-direction: column; gap: 6px; scroll-behavior: smooth; }
        .sys-message { align-self: center; background: rgba(0,0,0,0.06); color: #64748b; font-size: 0.75rem; padding: 6px 16px; border-radius: 20px; margin: 12px 0; font-weight: 500; }

        .message-row { display: flex; gap: 12px; max-width: 80%; align-items: flex-end; position: relative; }
        .message-row.mine { align-self: flex-end; flex-direction: row-reverse; }
        .message-row.other { align-self: flex-start; }
        .message-row.ai { align-self: flex-start; max-width: 90%; }

        .msg-avatar { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700; color: white; flex-shrink: 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); background: #94a3b8; }
        .message-row.mine .msg-avatar { background: var(--dark); }
        .msg-avatar.ai { background: #fde68a; font-size: 1.1rem; border: 1px solid #fcd34d; }

        .msg-content { display: flex; flex-direction: column; min-width: 0; }
        .message-row.mine .msg-content { align-items: flex-end; }
        .msg-sender { font-size: 0.75rem; color: #64748b; margin-bottom: 4px; padding: 0 4px; font-weight: 600; }
        .message-row.mine .msg-sender { display: none; }

        .msg-bubble { padding: 10px 16px; border-radius: 18px; font-size: 0.95rem; line-height: 1.5; word-wrap: break-word; position: relative; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .message-row.mine .msg-bubble { background: linear-gradient(135deg, var(--primary), var(--dark)); color: white; border-bottom-right-radius: 4px; }
        .message-row.other .msg-bubble { background: white; border: 1px solid #e2e8f0; color: #334155; border-bottom-left-radius: 4px; }
        .message-row.ai .msg-bubble { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; border-bottom-left-radius: 4px; }

        .msg-time { font-size: 0.65rem; color: #94a3b8; margin-top: 4px; padding: 0 4px; display: flex; align-items: center; gap: 8px; }
        .message-row.mine .msg-time { justify-content: flex-end; }
        .message-row.grouped { margin-top: -4px; }
        .message-row.grouped .msg-avatar { visibility: hidden; }
        .message-row.grouped .msg-sender { display: none; }
        .message-row.grouped.mine .msg-bubble { border-top-right-radius: 18px; }
        .message-row.grouped.other .msg-bubble { border-top-left-radius: 18px; }
        .message-row.deleted .msg-bubble { background: transparent; border: 1px dashed #cbd5e1; color: #94a3b8; font-style: italic; box-shadow: none; }

        .msg-delete { cursor: pointer; color: #cbd5e1; transition: 0.2s; opacity: 0; background: white; padding: 4px; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .message-row:hover .msg-delete { opacity: 1; }
        .msg-delete:hover { color: #ef4444; background: #fee2e2; }

        .msg-bubble a { color: inherit; text-decoration: underline; font-weight: 500; }
        .msg-bubble strong { font-weight: 700; }
        .msg-bubble em { font-style: italic; }
        .mention { color: #3b82f6; font-weight: 600; background: rgba(59,130,246,0.1); padding: 0 4px; border-radius: 4px; }
        .message-row.mine .mention { color: #fff; background: rgba(255,255,255,0.25); }

        .typing-container { flex-shrink: 0; padding: 8px 24px; font-size: 0.8rem; color: #64748b; font-weight: 500; display: none; align-items: center; gap: 8px; background: #f8fafc; border-top: 1px solid #f1f5f9;}
        .typing-dots span { display: inline-block; width: 5px; height: 5px; background: var(--primary); border-radius: 50%; animation: bounce 1.4s infinite; margin: 0 1px; }
        .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
        .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes bounce { 0%, 80%, 100% { transform: translateY(0); } 40% { transform: translateY(-4px); } }

        .chat-input-area { flex-shrink: 0; padding: 16px 24px; background: white; border-top: 1px solid #e2e8f0; display: flex; align-items: flex-end; gap: 12px; position: relative; }
        .chat-input-area textarea { flex: 1; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 24px; padding: 12px 20px; outline: none; font-family: inherit; font-size: 0.95rem; resize: none; max-height: 120px; transition: 0.2s; line-height: 1.4; }
        .chat-input-area textarea:focus { background: white; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(230,126,34,0.1); }
        .send-btn { width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--dark)); color: white; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; transition: 0.2s; box-shadow: 0 4px 10px rgba(211,84,0,0.2); }
        .send-btn:hover { transform: scale(1.05); }

        #emojiBtn { background: none; font-size: 1.4rem; padding: 10px; color: #94a3b8; border-radius: 50%; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; cursor: pointer; border: none; transition: 0.2s; margin-bottom: 2px;}
        #emojiBtn:hover { background: #f8fafc; color: var(--primary); }
        .emoji-toolbar { position: absolute; bottom: 100%; left: 24px; background: white; border: 1px solid #eef2f6; padding: 10px 14px; border-radius: 30px; display: none; gap: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); margin-bottom: 10px; z-index: 10; }
        .emoji-toolbar.show { display: flex; animation: popUp 0.2s ease-out; }
        @keyframes popUp { from { opacity: 0; transform: translateY(10px) scale(0.9); } to { opacity: 1; transform: translateY(0) scale(1); } }
        .emoji-toolbar span { cursor: pointer; font-size: 1.3rem; transition: 0.2s; }
        .emoji-toolbar span:hover { transform: scale(1.25); }

        .empty-state { height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #94a3b8; text-align: center; gap: 12px; padding: 40px; margin: auto; }
        .empty-state i { font-size: 4rem; color: #e2e8f0; margin-bottom: 10px; }
        .empty-state h3 { color: #475569; font-size: 1.2rem; }

        /* ===== MODALS ===== */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); backdrop-filter: blur(4px); z-index: 1000; justify-content: center; align-items: center; animation: fadeIn 0.2s; }
        .modal-box { background: white; width: 480px; max-width: 90%; border-radius: 24px; padding: 30px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); animation: slideUp 0.3s; max-height: 90vh; overflow-y: auto; }
        @keyframes fadeIn { from{opacity:0;} to{opacity:1;} }
        @keyframes slideUp { from{opacity:0; transform:translateY(20px) scale(0.95);} to{opacity:1; transform:translateY(0) scale(1);} }
        .modal-box h3 { font-size: 1.4rem; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .modal-box h3 i { color: var(--primary); }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 600; color: #475569; margin-bottom: 6px; font-size: 0.9rem; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 12px; font-family: inherit; font-size: 0.95rem; background: #f8fafc; outline: none; transition: 0.2s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 3px rgba(230,126,34,0.1);}
        .modal-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; }
        .btn { padding: 10px 20px; border-radius: 12px; font-weight: 600; cursor: pointer; border: none; transition: 0.2s; font-size: 0.95rem; }
        .btn-cancel { background: #f1f5f9; color: #475569; }
        .btn-cancel:hover { background: #e2e8f0; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--dark); box-shadow: 0 4px 12px rgba(230,126,34,0.3);}

        @media (max-width: 768px) {
            .nav-links { display: none; }
            .chat-wrapper { flex-direction: column; margin: 0; width: 100%; border-radius: 0; border: none; }
            .sidebar { width: 100%; min-height: 0; flex: 1; border-right: none; display: flex; }
            .chat-main { display: none; width: 100%; position: absolute; top: 0; left: 0; bottom: 0; z-index: 20; }
            .chat-active .sidebar { display: none; }
            .chat-active .chat-main { display: flex; }
            .mobile-back { display: block; }
            .message-row { max-width: 90%; }
            .chat-input-area { padding: 12px; }
        }
    </style>
</head>
<body>
<header>
    <div class="logo"><a href="main.menu.php"><i class="fas fa-calendar-check"></i> <span>UBook</span></a></div>
    <div class="nav-links">
        <a href="main.menu.php">Home</a>
        <a href="venue.php">Venues</a>
        <a href="Community.php" class="active">Community</a>
    </div>
    <div class="header-icons">
        <a href="profile.php" class="icon-button" title="Profile"><i class="fas fa-user"></i></a>
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</header>

<div class="chat-wrapper" id="chatContainer">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-comments"></i> Community</h2>
        </div>
        <div class="user-profile-bar">
            <div class="avatar"><?php echo strtoupper(substr($current_user_name, 0, 1)); ?></div>
            <div class="name"><?php echo htmlspecialchars($current_user_name); ?></div>
        </div>
        <div class="category-filter" id="categoryTabs"></div>
        <div class="group-list" id="group-list">
            <div class="empty-state"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>
        <div class="join-btn-container">
            <button class="join-btn" onclick="openModal('joinGroupModal')"><i class="fas fa-plus"></i> Join or Create Group</button>
        </div>
    </div>

    <div class="chat-main" id="chat-main">
        <div class="chat-header">
            <div class="chat-header-info">
                <button class="mobile-back" onclick="closeChatMobile()"><i class="fas fa-arrow-left"></i></button>
                <div class="chat-title"><i class="fas fa-hashtag" style="color: #cbd5e1;"></i> <span id="chat-title">Select a chat</span> <span class="chat-badge" id="chat-badge" style="display:none;"></span></div>
            </div>
            <div class="chat-actions" id="chat-actions" style="display:none;">
                <button class="action-icon" id="searchBtn" title="Search"><i class="fas fa-search"></i></button>
                <button class="action-icon" id="membersBtn" title="Members"><i class="fas fa-users"></i></button>
                <button class="action-icon" id="editGroupBtn" title="Settings" style="display:none;"><i class="fas fa-cog"></i></button>
            </div>
        </div>
        
        <div class="messages-container" id="messages-area">
            <div class="empty-state">
                <i class="far fa-paper-plane"></i>
                <h3>Welcome to UBook Community</h3>
                <p>Select a group on the left to start chatting.<br><small>Tip: Type <strong style="color:var(--primary);">@AI</strong> to book a venue or ask for help!</small></p>
            </div>
        </div>
        
        <div class="typing-container" id="typing-indicator">
            <div class="typing-dots"><span></span><span></span><span></span></div>
            <span id="typing-users"></span> &nbsp;typing...
        </div>
        
        <div class="chat-input-area" id="input-area" style="display:none;">
            <div class="emoji-toolbar" id="emoji-toolbar">
                <span onclick="insertEmoji('👍')">👍</span>
                <span onclick="insertEmoji('❤️')">❤️</span>
                <span onclick="insertEmoji('😂')">😂</span>
                <span onclick="insertEmoji('😮')">😮</span>
                <span onclick="insertEmoji('🔥')">🔥</span>
                <span onclick="insertEmoji('✨')">✨</span>
            </div>
            <button id="emojiBtn" title="Add Emoji" onclick="toggleEmoji()"><i class="far fa-smile"></i></button>
            <textarea id="message-input" rows="1" placeholder="Type a message or use @AI..." autocomplete="off"></textarea>
            <button class="send-btn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>
</div>

<!-- Modals -->
<div class="modal-overlay" id="joinGroupModal">
    <div class="modal-box">
        <h3><i class="fas fa-compass"></i> Discover or Create</h3>
        <div class="form-group">
            <label>Search Existing Groups</label>
            <input type="text" id="event-search" placeholder="Search public groups..." onkeyup="searchEvents()">
            <div id="event-search-results" style="max-height: 120px; overflow-y: auto; margin-top: 8px;"></div>
        </div>
        <div style="text-align:center; color:#94a3b8; font-size:0.85rem; margin:16px 0; font-weight:600;">— OR CREATE NEW —</div>
        <div class="form-group">
            <label>Group Name</label>
            <input type="text" id="event-name" placeholder="e.g., Study Jam, Futsal Team">
        </div>
        <div class="form-group">
            <label>Category</label>
            <select id="event-category">
                <option value="Venues">🏟️ Venues</option>
                <option value="Design">🎨 Design</option>
                <option value="Study">📚 Study</option>
                <option value="Sports">⚽ Sports</option>
                <option value="General" selected>💬 General</option>
            </select>
        </div>
        <div class="modal-actions">
            <button class="btn btn-cancel" onclick="closeModal('joinGroupModal')">Cancel</button>
            <button class="btn btn-primary" onclick="joinGroup()">Continue</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="bookingModal">
    <div class="modal-box">
        <h3><i class="fas fa-robot"></i> AI Booking</h3>
        <p style="color:#64748b; font-size:0.9rem; margin-bottom:16px;">Verify the details extracted from your prompt.</p>
        <form id="bookingForm">
            <div class="form-group">
                <label>Venue *</label>
                <select id="booking_venue" required>
                    <option value="">-- Select Venue --</option>
                    <?php foreach (getVenueList(getDB()) as $v) echo "<option value='" . htmlspecialchars($v['id']) . "'>" . htmlspecialchars($v['name']) . "</option>"; ?>
                </select>
            </div>
            <div style="display:flex; gap:12px;">
                <div class="form-group" style="flex:1;">
                    <label>Date *</label>
                    <input type="date" id="booking_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Start Time *</label>
                    <input type="time" id="booking_time" required>
                </div>
            </div>
            <div class="form-group">
                <label>Duration (hours) *</label>
                <select id="booking_duration">
                    <option value="1">1 hour</option><option value="2" selected>2 hours</option>
                    <option value="3">3 hours</option><option value="4">4 hours</option>
                </select>
            </div>
            <div class="form-group">
                <label>Comment (optional)</label>
                <textarea id="booking_comment" rows="2" placeholder="Special requirements..."></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeModal('bookingModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm Request</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="membersModal">
    <div class="modal-box">
        <h3><i class="fas fa-users"></i> Chat Members</h3>
        <div id="members-list" style="display:flex; flex-direction:column; gap:8px; max-height:300px; overflow-y:auto; margin-bottom:20px;"></div>
        <div class="modal-actions">
            <button id="leaveGroupBtn" class="btn btn-cancel" style="margin-right:auto; color:#ef4444; background:#fee2e2;">Leave Group</button>
            <button class="btn btn-primary" onclick="closeModal('membersModal')">Close</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="editGroupModal">
    <div class="modal-box">
        <h3><i class="fas fa-cog"></i> Group Settings</h3>
        <div class="form-group">
            <label>Group Name</label>
            <input type="text" id="edit-group-name">
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea id="edit-group-desc" rows="3"></textarea>
        </div>
        <div class="form-group">
            <label>Cover Image URL (Optional)</label>
            <input type="text" id="edit-group-cover" placeholder="https://...">
        </div>
        <div class="modal-actions">
            <button class="btn btn-cancel" onclick="closeModal('editGroupModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveGroupEdit()">Save Changes</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="searchModal">
    <div class="modal-box">
        <h3><i class="fas fa-search"></i> Search Messages</h3>
        <div class="form-group">
            <input type="text" id="search-query" placeholder="Type keyword to search...">
        </div>
        <div id="search-results" style="max-height:300px; overflow-y:auto; display:flex; flex-direction:column; gap:6px;"></div>
        <div class="modal-actions">
            <button class="btn btn-cancel" onclick="closeModal('searchModal')">Close</button>
        </div>
    </div>
</div>

<script>
// ==================== FRONTEND CHAT LOGIC ====================
const currentUserId = <?php echo $current_user_id; ?>;
let currentChatId = null, pollInterval = null, typingPollInterval = null, lastMessageId = 0;
let currentCategory = 'All', typingTimer = null, isCreator = false;

// API Helper
async function api(action, data = {}, isPost = true) {
    if (!isPost) {
        const url = new URL(window.location.href); url.searchParams.set('ajax', '1'); url.searchParams.set('action', action);
        for (let [k,v] of Object.entries(data)) url.searchParams.set(k, v);
        return (await fetch(url)).json();
    }
    const fd = new URLSearchParams({ ajax: '1', action, ...data });
    return (await fetch(window.location.href, { method: 'POST', body: fd })).json();
}

// Formatters
function escapeHtml(str) { return (str||'').replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m]); }
function formatText(text) { 
    let t = escapeHtml(text);
    t = t.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank">$1</a>');
    t = t.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\*(.*?)\*/g, '<em>$1</em>').replace(/\n/g, '<br>');
    return t.replace(/(@[a-zA-Z0-9_]+)/gi, '<span class="mention">$1</span>');
}
function formatTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr.replace(/-/g, '/')); if(isNaN(d.getTime())) return dateStr;
    const now = new Date();
    if (now - d < 86400000 && now.getDate() === d.getDate()) return d.toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });
    return d.toLocaleDateString([], { month:'short', day:'numeric' }) + ' ' + d.toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });
}

function scrollToBottom(smooth = false) {
    setTimeout(() => {
        const c = document.getElementById('messages-area');
        if (c) c.scrollTo({ top: c.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
    }, 50);
}

function renderMessage(msg, prevMsg = null) {
    if (msg.user_id == -1) {
        return `<div class="sys-message" data-msg-id="${msg.id}">${escapeHtml(msg.message)}</div>`;
    }
    
    const isAi = (msg.sender_name === 'AI Assistant') || (msg.user_id == 0);
    const sClass = isAi ? 'ai' : (msg.is_mine ? 'mine' : 'other');
    const sName = isAi ? 'AI Assistant' : (msg.is_mine ? 'You' : escapeHtml(msg.sender_name));
    const av = isAi ? '🤖' : sName.charAt(0).toUpperCase();
    
    let isGrouped = false;
    if (prevMsg && prevMsg.user_id === msg.user_id && prevMsg.user_id !== -1 && !prevMsg.is_deleted && !msg.is_deleted) {
        const timeDiff = new Date(msg.created_at.replace(/-/g, '/')) - new Date(prevMsg.created_at.replace(/-/g, '/'));
        if (timeDiff < 300000) isGrouped = true;
    }
    
    const txt = msg.is_deleted ? '<em>🚫 This message was deleted</em>' : formatText(msg.message);
    const del = (msg.is_mine || isCreator) && !msg.is_deleted ? `<i class="fas fa-trash msg-delete" onclick="deleteMessage(${msg.id})"></i>` : '';

    return `
    <div class="message-row ${sClass} ${isGrouped ? 'grouped' : ''} ${msg.is_deleted ? 'deleted' : ''}" data-msg-id="${msg.id}" data-uid="${msg.user_id}">
        <div class="msg-avatar">${av}</div>
        <div class="msg-content">
            <div class="msg-sender">${sName}</div>
            <div class="msg-bubble">${txt}</div>
            <div class="msg-time">${formatTime(msg.created_at)} ${del}</div>
        </div>
    </div>`;
}

function processGrouping() {
    const msgs = document.querySelectorAll('.message-row:not(.sys-message)');
    let pUid = null;
    msgs.forEach(m => {
        const u = m.getAttribute('data-uid');
        if (pUid === u && !m.classList.contains('deleted')) m.classList.add('grouped'); 
        else m.classList.remove('grouped');
        pUid = u;
    });
}

function appendMessage(msg, scroll = false) {
    const c = document.getElementById('messages-area');
    const empty = c.querySelector('.empty-state'); if (empty) empty.remove();
    const wasBottom = c.scrollHeight - c.scrollTop - c.clientHeight < 50;
    c.insertAdjacentHTML('beforeend', renderMessage(msg));
    processGrouping();
    if (wasBottom || msg.is_mine || scroll) scrollToBottom(msg.is_mine);
}

// Sidebars & Loading
async function loadCategories() {
    const d = await api('get_my_groups', { category: '' }, false);
    if (!d.groups) return;
    const cats = { All: d.groups.length };
    d.groups.forEach(g => { cats[g.category] = (cats[g.category] || 0) + 1; });
    document.getElementById('categoryTabs').innerHTML = Object.entries(cats).map(([c, count]) => `
        <button class="cat-btn ${c === currentCategory ? 'active' : ''}" data-cat="${c}">
            ${c==='All'?'🌐':(c==='Venues'?'🏟️':(c==='Design'?'🎨':(c==='Study'?'📚':(c==='Sports'?'⚽':'💬'))))} ${c} <span class="cat-badge">${count}</span>
        </button>`).join('');
    
    document.querySelectorAll('.cat-btn').forEach(b => {
        b.addEventListener('click', () => { 
            currentCategory = b.dataset.cat; loadCategories(); loadGroups(); 
        });
    });
}

async function loadGroups() {
    const d = await api('get_my_groups', { category: currentCategory === 'All' ? '' : currentCategory }, false);
    const c = document.getElementById('group-list');
    if (!d.groups?.length) { c.innerHTML = '<div style="text-align:center;color:#94a3b8;padding:20px;">No groups found. Create one!</div>'; return; }
    
    c.innerHTML = d.groups.map(g => {
        const lr = parseInt(localStorage.getItem('read_' + g.event_name) || '0', 10);
        const unread = (g.last_message_id > lr && g.event_name !== currentChatId) ? '<div class="unread-badge"></div>' : '';
        return `
        <div class="group-item ${g.event_name === currentChatId ? 'active' : ''}" onclick="selectGroupChat('${escapeHtml(g.event_name).replace(/'/g, "\\'")}', ${g.created_by})">
            <img src="${g.cover_url}" class="group-avatar" onerror="this.src='https://ui-avatars.com/api/?name=G&background=cbd5e1'">
            <div class="group-info">
                <div class="group-name"><span>${escapeHtml(g.event_name)}</span> ${unread}</div>
                <div class="group-last-msg">${g.last_message}</div>
                <div class="group-time">${formatTime(g.last_time).split(',')[0]}</div>
            </div>
        </div>`;
    }).join('');
}

async function selectGroupChat(eventName, creatorId) {
    if (pollInterval) clearInterval(pollInterval);
    if (typingPollInterval) clearInterval(typingPollInterval);
    currentChatId = eventName;
    isCreator = (creatorId == currentUserId);
    
    document.getElementById('chatContainer').classList.add('chat-active');
    document.getElementById('chat-title').innerText = eventName;
    document.getElementById('chat-badge').style.display = 'inline-block';
    document.getElementById('chat-badge').innerText = 'Group';
    document.getElementById('input-area').style.display = 'flex';
    document.getElementById('chat-actions').style.display = 'flex';
    document.getElementById('editGroupBtn').style.display = isCreator ? 'inline-flex' : 'none';
    
    setTimeout(() => {
        const input = document.getElementById('message-input');
        if (input && window.innerWidth > 768) { 
            input.focus();
        }
    }, 100);
    
    loadGroups();
    await loadMessages();
    startPolling(); startTypingPolling();
}

function closeChatMobile() {
    document.getElementById('chatContainer').classList.remove('chat-active');
    if (pollInterval) clearInterval(pollInterval);
    if (typingPollInterval) clearInterval(typingPollInterval);
    currentChatId = null; loadGroups();
}

async function loadMessages() {
    const c = document.getElementById('messages-area');
    c.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i></div>'; 
    lastMessageId = 0;
    
    const d = await api('get_group_messages', { event_name: currentChatId, offset: 0, limit: 100 }, false);
    if (!d.success) return;
    
    if (!d.messages.length) { 
        c.innerHTML = '<div class="empty-state"><i class="far fa-paper-plane"></i><h3>No messages yet</h3><p>Type @AI to book a venue or start a conversation.</p></div>'; 
        return; 
    }
    
    c.innerHTML = d.messages.map((m, i) => renderMessage(m, i>0?d.messages[i-1]:null)).join('');
    processGrouping();
    lastMessageId = Math.max(...d.messages.map(m => m.id), 0);
    localStorage.setItem('read_' + currentChatId, lastMessageId);
    scrollToBottom(false);
}

async function pollMessages() {
    if (!currentChatId) return;
    const d = await api('get_group_messages', { event_name: currentChatId, since_id: lastMessageId }, false);
    if (d.success && d.messages.length) {
        let added = false;
        d.messages.forEach(m => { if (!document.querySelector(`.message-row[data-msg-id="${m.id}"]`)) { appendMessage(m); added = true; } });
        lastMessageId = Math.max(...d.messages.map(m => m.id), lastMessageId);
        localStorage.setItem('read_' + currentChatId, lastMessageId);
        if (added) loadGroups();
    }
}

async function sendMessage() {
    const input = document.getElementById('message-input');
    const msg = input.value.trim();
    if (!msg || !currentChatId) return;
    
    // If message starts with @AI (booking or general), handle accordingly
    if (msg.match(/^@AI\b/i)) {
        // If it's a booking command, open the booking modal
        if (msg.match(/^@AI\s*(book|booking)/i) || msg.match(/^@book/i)) {
            openBookingModal(msg);
            input.value = ''; input.style.height = 'auto'; return;
        } else {
            // Otherwise, send as normal message; the backend will trigger DeepSeek AI
        }
    }
    
    const tmpId = 't_'+Date.now();
    appendMessage({ id: tmpId, user_id: currentUserId, message: msg, created_at: new Date().toISOString().replace('T',' ').substring(0,19), sender_name: 'You', is_mine: true }, true);
    
    input.value = ''; input.style.height = 'auto';
    if(window.innerWidth > 768) input.focus(); 
    
    try {
        const res = await api('send_group_message', { event_name: currentChatId, message: msg });
        if (res.success) {
            document.querySelector(`.message-row[data-msg-id="${tmpId}"]`)?.setAttribute('data-msg-id', res.message_id);
            if (res.ai_reply) appendMessage(res.ai_reply, true);
            lastMessageId = Math.max(lastMessageId, res.message_id, res.ai_reply?.id||0);
            localStorage.setItem('read_'+currentChatId, lastMessageId);
            loadGroups();
        } else alert(res.error);
    } catch(e) { console.error(e); }
}

async function deleteMessage(id) {
    if(!confirm("Delete this message for everyone?")) return;
    const res = await api('delete_message', { msg_id: id });
    if(res.success) { 
        const el = document.querySelector(`.message-row[data-msg-id="${id}"]`);
        if (el) { el.classList.add('deleted'); el.querySelector('.msg-bubble').innerHTML = '<em>🚫 This message was deleted</em>'; el.querySelector('.msg-delete')?.remove(); processGrouping(); }
    } else alert(res.error);
}

function startPolling() { if (pollInterval) clearInterval(pollInterval); pollInterval = setInterval(pollMessages, 3000); }
function startTypingPolling() {
    if (typingPollInterval) clearInterval(typingPollInterval);
    typingPollInterval = setInterval(async () => {
        if (!currentChatId) return;
        const d = await api('get_typing', { event_name: currentChatId }, false);
        const ind = document.getElementById('typing-indicator');
        if (d.typing?.length) { document.getElementById('typing-users').innerText = d.typing.join(', '); ind.style.display = 'flex'; }
        else ind.style.display = 'none';
    }, 2000);
}

const msgInput = document.getElementById('message-input');
msgInput.addEventListener('input', function() {
    this.style.height = 'auto'; 
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    scrollToBottom(false);
    
    if(typingTimer) clearTimeout(typingTimer);
    api('typing', { event_name: currentChatId }).catch(()=>{});
    typingTimer = setTimeout(()=>{}, 2000);
});
msgInput.addEventListener('keydown', e => { 
    if (e.key === 'Enter' && !e.shiftKey) { 
        e.preventDefault(); 
        sendMessage(); 
    } 
});

function toggleEmoji() {
    const tb = document.getElementById('emoji-toolbar');
    if(tb.classList.contains('show')) { tb.classList.remove('show'); setTimeout(()=>tb.style.display='none',200); }
    else { tb.style.display='flex'; tb.classList.add('show'); }
}
function insertEmoji(e) { msgInput.value += e; msgInput.focus(); toggleEmoji(); }

// Modals
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
window.onclick = e => { if (e.target.classList.contains('modal-overlay')) e.target.style.display = 'none'; };

// Members
document.getElementById('membersBtn').onclick = async () => {
    const d = await api('get_members', { event_name: currentChatId }, false);
    if (!d.success) return;
    document.getElementById('members-list').innerHTML = d.members.map(m => `
        <div style="display:flex; align-items:center; gap:12px; padding:10px; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0;">
            <div class="msg-avatar" style="background:var(--primary);">${escapeHtml(m.name.charAt(0).toUpperCase())}</div>
            <div><strong style="color:#1e293b;">${escapeHtml(m.name)}</strong> <br><small style="color:#64748b;">${m.is_creator?'🌟 Group Creator':'Member'}</small></div>
        </div>
    `).join('');
    openModal('membersModal');
};
document.getElementById('leaveGroupBtn').onclick = async () => {
    if(!confirm('Leave this group?')) return;
    await api('leave_group', { event_name: currentChatId });
    closeModal('membersModal'); closeChatMobile(); 
};

// Edit Group
document.getElementById('editGroupBtn').onclick = async () => {
    const gd = await api('get_my_groups', { category: '' }, false);
    const g = gd.groups?.find(x => x.event_name === currentChatId);
    if (g) { document.getElementById('edit-group-name').value = g.event_name; document.getElementById('edit-group-desc').value = g.description||''; document.getElementById('edit-group-cover').value = g.cover_url||''; openModal('editGroupModal'); }
};
async function saveGroupEdit() {
    const name = document.getElementById('edit-group-name').value.trim();
    if(!name) return;
    const res = await api('edit_group', { event_name: currentChatId, name, description: document.getElementById('edit-group-desc').value, cover_url: document.getElementById('edit-group-cover').value });
    if(res.success) { closeModal('editGroupModal'); if(res.new_name !== currentChatId) selectGroupChat(res.new_name, currentUserId); else { loadGroups(); loadMessages(true); } }
    else alert(res.error);
}

// Search & Join
async function searchEvents() {
    const s = document.getElementById('event-search').value;
    if(s.length < 2) { document.getElementById('event-search-results').innerHTML = ''; return; }
    const d = await api('search_events', { search: s }, false);
    const c = document.getElementById('event-search-results');
    if(d.events?.length) c.innerHTML = d.events.map(e => `<div style="padding:10px; border-bottom:1px solid #e2e8f0; cursor:pointer;" onclick="document.getElementById('event-name').value='${escapeHtml(e).replace(/'/g,"\\'")}'"><i class="fas fa-hashtag" style="color:var(--primary);"></i> ${escapeHtml(e)}</div>`).join('');
    else c.innerHTML = '<div style="color:#94a3b8; padding:10px; text-align:center;">No results. You can create it below!</div>';
}
async function joinGroup() {
    const name = document.getElementById('event-name').value.trim();
    const cat = document.getElementById('event-category').value;
    if(!name) return alert('Enter group name');
    const res = await api('join_group', { event_name: name, category: cat });
    if(res.success) { closeModal('joinGroupModal'); await loadCategories(); await loadGroups(); selectGroupChat(name, res.is_creator ? currentUserId : 0); }
    else alert(res.error);
}

document.getElementById('searchBtn').onclick = () => { document.getElementById('search-query').value=''; document.getElementById('search-results').innerHTML=''; openModal('searchModal'); };
document.getElementById('search-query').addEventListener('input', async function() {
    const q = this.value.trim(); if(q.length < 2) return;
    const d = await api('search_messages', { event_name: currentChatId, query: q }, false);
    if(d.success) document.getElementById('search-results').innerHTML = d.results.map(r => `
        <div style="padding:10px; background:#f8fafc; border-radius:12px; cursor:pointer; border:1px solid #e2e8f0;" onclick="closeModal('searchModal'); document.querySelector('.message-row[data-msg-id=\\'${r.id}\\']')?.scrollIntoView({behavior:'smooth',block:'center'})">
            <div style="font-size:0.8rem; color:var(--primary); font-weight:600; margin-bottom:4px;">${escapeHtml(r.user_name)} <span style="float:right; color:#94a3b8; font-weight:400;">${formatTime(r.created_at)}</span></div>
            <div style="font-size:0.9rem;">${escapeHtml(r.message.substring(0,80))}...</div>
        </div>
    `).join('') || '<div style="text-align:center;color:#94a3b8;">No matches found</div>';
});

// AI Booking Form – open modal with pre-filled data
function openBookingModal(initText) {
    const txt = initText.toLowerCase();
    const vSel = document.getElementById('booking_venue');
    for(let i=0; i<vSel.options.length; i++) if(vSel.options[i].value && txt.includes(vSel.options[i].text.toLowerCase())) { vSel.value = vSel.options[i].value; break; }
    
    let dt = null;
    if(txt.includes('tomorrow')) { const t = new Date(); t.setDate(t.getDate()+1); dt = t.toISOString().split('T')[0]; }
    else if(txt.includes('today')) dt = new Date().toISOString().split('T')[0];
    else { const m = initText.match(/\b(\d{4}-\d{2}-\d{2})\b/); if(m) dt = m[1]; }
    if(dt) document.getElementById('booking_date').value = dt;
    
    let tm = null; const tmM = initText.match(/\b([01]?\d|2[0-3]):([0-5]\d)\b/);
    if(tmM) tm = tmM[0]; else {
        const apM = initText.match(/\b([1-9]|1[0-2])(?::([0-5]\d))?\s*(am|pm)\b/i);
        if(apM) { let h=parseInt(apM[1]); if(apM[3].toLowerCase()==='pm'&&h<12)h+=12; if(apM[3].toLowerCase()==='am'&&h===12)h=0; tm=`${h.toString().padStart(2,'0')}:${(apM[2]||'0').padStart(2,'0')}`; }
    }
    if(tm) document.getElementById('booking_time').value = tm;
    
    const dM = initText.match(/(\d+)\s*(hour|hrs|h)/i);
    if(dM) { const d=parseInt(dM[1]); if(d>=1&&d<=4) document.getElementById('booking_duration').value = d; }
    openModal('bookingModal');
}

// Booking form submission
document.getElementById('bookingForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const v = document.getElementById('booking_venue').value, d = document.getElementById('booking_date').value, t = document.getElementById('booking_time').value, dur = document.getElementById('booking_duration').value, c = document.getElementById('booking_comment').value;
    if(!v||!d||!t) return;
    closeModal('bookingModal');
    
    const vName = document.getElementById('booking_venue').options[document.getElementById('booking_venue').selectedIndex]?.text;
    const msg = `@AI Booking request:\n**Venue:** ${vName}\n**Date:** ${d}\n**Time:** ${t}\n**Duration:** ${dur}h` + (c?`\n**Comment:** ${c}`:'');
    const tmpId = 't_'+Date.now();
    appendMessage({ id: tmpId, user_id: currentUserId, message: msg, created_at: new Date().toISOString().replace('T',' ').substring(0,19), sender_name: 'You', is_mine: true }, true);
    
    try {
        const res = await api('submit_booking_form', { chat_type: 'group', chat_id: currentChatId, venue_id: v, booking_date: d, start_time: t, duration: dur, comment: c });
        if(res.success) {
            // Update user message ID
            const userEl = document.querySelector(`.message-row[data-msg-id="${tmpId}"]`);
            if(userEl) userEl.setAttribute('data-msg-id', res.user_message_id);
            
            // Append AI reply
            const aiMsg = {
                id: res.ai_message_id,
                user_id: 0,
                message: res.ai_message,
                created_at: new Date().toISOString().replace('T',' ').substring(0,19),
                sender_name: 'AI Assistant',
                is_mine: false,
                is_deleted: false
            };
            appendMessage(aiMsg, true);
            lastMessageId = Math.max(lastMessageId, res.ai_message_id);
            localStorage.setItem('read_'+currentChatId, lastMessageId);
            loadGroups();
        } else alert(res.error);
    } catch(err) { alert('Error: '+err.message); }
    this.reset();
});

document.addEventListener('DOMContentLoaded', async () => { await loadCategories(); await loadGroups(); });
</script>
</body>
</html>