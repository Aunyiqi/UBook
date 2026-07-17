<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --------------------------- CONFIGURATION ---------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'ubook');
define('DB_USER', 'root');
define('DB_PASS', '');

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

// Operating hours
define('OPEN_HOUR', 8);    // 8:00
define('CLOSE_HOUR', 22);  // 22:00

// --------------------------- SESSION GUEST FALLBACK -----------------------
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = '0';
    $_SESSION['name'] = 'Guest';
    $_SESSION['user_email'] = '';
}

// --------------------------- DATABASE CONNECTION -------------------------
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --------------------------- CREATE TABLES IF MISSING --------------------
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS venues (
        id VARCHAR(20) PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        image_url VARCHAR(255)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id VARCHAR(20) PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        profile_image VARCHAR(255)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(20) NOT NULL,
        student_name VARCHAR(100) NOT NULL,
        venue_id VARCHAR(20) NOT NULL,
        booking_date DATE NOT NULL,
        start_time TIME NOT NULL,
        duration_hours INT NOT NULL,
        comment TEXT,
        status ENUM('pending','confirmed','rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS venue_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        venue_id VARCHAR(20) NOT NULL,
        user_id VARCHAR(20) NOT NULL,
        comment TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS comment_likes (
        comment_id INT NOT NULL,
        user_id VARCHAR(20) NOT NULL,
        PRIMARY KEY (comment_id, user_id),
        FOREIGN KEY (comment_id) REFERENCES venue_comments(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    // ignore if already exist
}

// --------------------------- SEED VENUES ----------------------------------
$defaultVenues = [
    ['id' => 'bas101', 'name' => 'Basketball Court', 'description' => 'Outdoor concrete court with two hoops, simple and slightly worn surface. The lines may be faded and the equipment is basic. Mostly used for casual games, PE lessons, and student activities.', 'image_url' => 'https://marvel-b1-cdn.bc0a.com/f00000000213893/wausautile.com/media/slides/HoopsPark05.jpg'],
    ['id' => 'cpl616', 'name' => 'Computer Lab', 'description' => 'Modest room with around 30 aging PCs, basic monitors, and essential software.', 'image_url' => 'https://img.magnific.com/premium-photo/bright-computer-lab-with-modern-equipment-technology_889056-39214.jpg'],
    ['id' => 'exm456', 'name' => 'Exam Hall', 'description' => 'Quiet, spacious hall with orderly rows of simple desks and chairs. Lighting is adequate and ventilation is fair. Used strictly for examinations and assessments.', 'image_url' => 'https://www.teachingcollege.fse.manchester.ac.uk/wp-content/uploads/2022/07/exam-hall.jpg'],
    ['id' => 'fld112', 'name' => 'Field', 'description' => 'Wide open grassy area with uneven patches and slightly overgrown sections. Used for football, running drills, and outdoor school activities.', 'image_url' => 'https://soccer-fields.com/wp-content/uploads/2025/07/image-4.png'],
    ['id' => 'lec131', 'name' => 'Lecture Hall', 'description' => 'Medium-sized hall with tiered seating and basic audio-visual equipment. Suitable for teaching, presentations, and group lectures.', 'image_url' => 'https://i.pinimg.com/originals/fa/40/df/fa40df3d4641603432dc6cd50d29c20b.jpg'],
    ['id' => 'mme123', 'name' => 'Main Hall', 'description' => 'Large multipurpose indoor space with a simple stage and seating. Used for assemblies, events, and school gatherings.', 'image_url' => 'https://visualdisplaysltd.com/application/files/5015/7555/3801/Meeting_-_visual-displays-limited-dnp_LaserPanel_Meetingroom2.jpg'],
    ['id' => 'sml415', 'name' => 'Smart Lab', 'description' => 'Compact learning space with modern but limited technology such as computers, VR headsets, and IoT tools. Used for IT lessons and practical experiments.', 'image_url' => 'https://images.pexels.com/photos/1181467/pexels-photo-1181467.jpeg'],
    ['id' => 'vol789', 'name' => 'Volleyball Court', 'description' => 'An outdoor hard court marked with a central net and basic boundary lines. The surface shows some wear from regular use, but it remains stable and playable for student activities. It is commonly used for PE lessons, training drills, and friendly matches among students.', 'image_url' => 'https://tgctexas.com/wp-content/uploads/2024/06/IMG_1518-scaled.jpg'],
];
$stmt = $pdo->query("SELECT COUNT(*) FROM venues");
if ($stmt->fetchColumn() == 0) {
    $insert = $pdo->prepare("INSERT INTO venues (id, name, description, image_url) VALUES (?, ?, ?, ?)");
    foreach ($defaultVenues as $v) {
        $insert->execute([$v['id'], $v['name'], $v['description'], $v['image_url']]);
    }
}

// Ensure at least a guest user exists in the users table (id = '0')
$guestStmt = $pdo->prepare("SELECT id FROM users WHERE id = '0'");
$guestStmt->execute();
if (!$guestStmt->fetch()) {
    $pdo->prepare("INSERT INTO users (id, name, email) VALUES ('0', 'Guest', '')")->execute();
}

// --------------------------- HELPER FUNCTIONS ----------------------------
function timeToMinutes($timeStr) {
    $parts = explode(':', $timeStr);
    return (int)$parts[0] * 60 + (int)$parts[1];
}

function ubook_venue_display_name(PDO $pdo, string $venueId): string {
    $stmt = $pdo->prepare('SELECT name FROM venues WHERE id = ?');
    $stmt->execute([$venueId]);
    return $stmt->fetchColumn() ?: 'Venue #' . $venueId;
}

function ubook_resolve_notify_email($input, $pdo, $userId) {
    if (!empty($input['notify_email']) && filter_var($input['notify_email'], FILTER_VALIDATE_EMAIL))
        return $input['notify_email'];
    if (!empty($_SESSION['user_email']) && filter_var($_SESSION['user_email'], FILTER_VALIDATE_EMAIL))
        return $_SESSION['user_email'];
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return filter_var($stmt->fetchColumn(), FILTER_VALIDATE_EMAIL) ?: null;
}

function ubook_send_booking_confirmation($toEmail, $studentName, $items) {
    if (!MAIL_ENABLED || empty($toEmail)) return false;
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
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $studentName);
        if (defined('MAIL_BCC') && MAIL_BCC) $mail->addBCC(MAIL_BCC);

        $subject = 'UBook: Your booking request has been submitted';
        $html = "<h2>Hello $studentName</h2><p>Your booking request is pending approval.</p><ul>";
        foreach ($items as $b) {
            $html .= "<li><strong>{$b['venue']}</strong> on {$b['date']} at {$b['time']} for {$b['duration']} hour(s)";
            if (!empty($b['comment'])) $html .= "<br>Comment: {$b['comment']}";
            $html .= "</li>";
        }
        $html .= "</ul><p>You will receive another email once approved or rejected.</p><p>Thank you for using UBook!</p>";
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = strip_tags($html);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// --------------------------- AJAX HANDLERS --------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    $requireLogin = function() {
        if (empty($_SESSION['user_id']) || $_SESSION['user_id'] == '0') {
            echo json_encode(['success' => false, 'message' => 'Please log in first.']);
            exit;
        }
    };

    // Helper to check overlapping bookings (including pending)
    function checkOverlap($pdo, $venueId, $date, $start, $duration, $excludeBookingId = null) {
        $startMin = timeToMinutes($start);
        $endMin = $startMin + $duration * 60;
        $sql = "SELECT start_time, duration_hours FROM bookings 
                WHERE venue_id = ? AND booking_date = ? AND status IN ('pending','confirmed')";
        if ($excludeBookingId) {
            $sql .= " AND id != ?";
        }
        $stmt = $pdo->prepare($sql);
        if ($excludeBookingId) {
            $stmt->execute([$venueId, $date, $excludeBookingId]);
        } else {
            $stmt->execute([$venueId, $date]);
        }
        foreach ($stmt->fetchAll() as $existing) {
            $exStart = timeToMinutes($existing['start_time']);
            $exEnd = $exStart + $existing['duration_hours'] * 60;
            if ($startMin < $exEnd && $endMin > $exStart) {
                return true;
            }
        }
        return false;
    }

    // Validate booking time is within operating hours 8:00 - 22:00
    function validateOperatingHours($startTime, $duration) {
        $startMin = timeToMinutes($startTime);
        $endMin = $startMin + $duration * 60;
        $openMin = OPEN_HOUR * 60;
        $closeMin = CLOSE_HOUR * 60;
        if ($startMin < $openMin || $endMin > $closeMin) {
            return false;
        }
        return true;
    }

    // ---- Save multiple bookings (cart) ----
    if ($action === 'save_booking') {
        $requireLogin();
        $userId = (string)$_SESSION['user_id'];
        $studentName = $_SESSION['name'];
        $bookings = $input['bookings'] ?? [];
        $providedEmail = trim($input['notify_email'] ?? '');
        
        if (empty($bookings)) {
            echo json_encode(['success' => false, 'message' => 'No bookings in cart']);
            exit;
        }
        
        // Resolve email
        $toEmail = '';
        if (!empty($providedEmail) && filter_var($providedEmail, FILTER_VALIDATE_EMAIL)) {
            $toEmail = $providedEmail;
        } elseif (!empty($_SESSION['user_email']) && filter_var($_SESSION['user_email'], FILTER_VALIDATE_EMAIL)) {
            $toEmail = $_SESSION['user_email'];
        } else {
            $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $dbEmail = $stmt->fetchColumn();
            if (!empty($dbEmail) && filter_var($dbEmail, FILTER_VALIDATE_EMAIL)) {
                $toEmail = $dbEmail;
            }
        }
        
        if (empty($toEmail)) {
            echo json_encode([
                'success' => false, 
                'need_email' => true,
                'message' => 'Please provide your email address to receive booking confirmation.'
            ]);
            exit;
        }
        
        $today = date('Y-m-d');
        try {
            $pdo->beginTransaction();
            $checkVenue = $pdo->prepare("SELECT id FROM venues WHERE id = ?");
            foreach ($bookings as $b) {
                if ($b['date'] < $today) {
                    throw new Exception("Cannot book on past date: {$b['date']}");
                }
                $duration = (int)$b['duration'];
                if ($duration < 1 || $duration > 4) {
                    throw new Exception("Duration must be between 1 and 4 hours.");
                }
                if (!validateOperatingHours($b['time'], $duration)) {
                    throw new Exception("Booking time must be between " . OPEN_HOUR . ":00 and " . CLOSE_HOUR . ":00. Your slot ends at " . date('H:i', timeToMinutes($b['time']) + $duration*60) . ".");
                }
                $checkVenue->execute([(string)$b['venueId']]);
                if (!$checkVenue->fetch()) {
                    throw new Exception("Invalid venue ID: {$b['venueId']}");
                }
                if (checkOverlap($pdo, (string)$b['venueId'], $b['date'], $b['time'], $duration)) {
                    throw new Exception("Time conflict on {$b['date']} at {$b['time']} for venue {$b['venueId']}.");
                }
            }
            
            $insert = $pdo->prepare("INSERT INTO bookings (user_id, student_name, venue_id, booking_date, start_time, duration_hours, comment, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
            foreach ($bookings as $b) {
                $insert->execute([$userId, $studentName, (string)$b['venueId'], $b['date'], $b['time'], (int)$b['duration'], $b['comment'] ?? '']);
            }
            $pdo->commit();

            $items = array_map(function($b) use ($pdo) {
                return [
                    'venue' => ubook_venue_display_name($pdo, (string)$b['venueId']),
                    'date' => $b['date'],
                    'time' => $b['time'],
                    'duration' => (int)$b['duration'],
                    'comment' => $b['comment'] ?? ''
                ];
            }, $bookings);
            
            $emailSent = ubook_send_booking_confirmation($toEmail, $studentName, $items);
            
            echo json_encode([
                'success' => true,
                'email_sent' => $emailSent,
                'message' => 'Booking request submitted (pending approval).' . ($emailSent ? ' A confirmation email has been sent.' : ' Could not send email, but your booking is saved.')
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ---- Direct booking from AI chat ----
    if ($action === 'direct_booking') {
        $requireLogin();
        $userId = (string)$_SESSION['user_id'];
        $studentName = $_SESSION['name'];
        $venueId = (string)($input['venueId'] ?? '');
        $date = $input['date'] ?? '';
        $time = $input['time'] ?? '';
        $duration = (int)($input['duration'] ?? 0);
        $comment = trim($input['comment'] ?? '');
        $providedEmail = trim($input['notify_email'] ?? '');
        
        if (!$venueId || !$date || !$time || $duration < 1) {
            echo json_encode(['success' => false, 'message' => 'Missing booking details.']);
            exit;
        }
        
        $today = date('Y-m-d');
        if ($date < $today) {
            echo json_encode(['success' => false, 'message' => 'Cannot book on a past date.']);
            exit;
        }
        if ($duration < 1 || $duration > 4) {
            echo json_encode(['success' => false, 'message' => 'Duration must be between 1 and 4 hours.']);
            exit;
        }
        if (!validateOperatingHours($time, $duration)) {
            echo json_encode(['success' => false, 'message' => 'Booking time must be between ' . OPEN_HOUR . ':00 and ' . CLOSE_HOUR . ':00.']);
            exit;
        }
        
        // Resolve email
        $toEmail = '';
        if (!empty($providedEmail) && filter_var($providedEmail, FILTER_VALIDATE_EMAIL)) {
            $toEmail = $providedEmail;
        } elseif (!empty($_SESSION['user_email']) && filter_var($_SESSION['user_email'], FILTER_VALIDATE_EMAIL)) {
            $toEmail = $_SESSION['user_email'];
        } else {
            $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $dbEmail = $stmt->fetchColumn();
            if (!empty($dbEmail) && filter_var($dbEmail, FILTER_VALIDATE_EMAIL)) {
                $toEmail = $dbEmail;
            }
        }
        
        if (empty($toEmail)) {
            echo json_encode([
                'success' => false, 
                'need_email' => true,
                'message' => 'Please provide your email address to receive booking confirmation.'
            ]);
            exit;
        }
        
        try {
            $check = $pdo->prepare("SELECT id FROM venues WHERE id = ?");
            $check->execute([$venueId]);
            if (!$check->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Invalid venue.']);
                exit;
            }
            
            if (checkOverlap($pdo, $venueId, $date, $time, $duration)) {
                echo json_encode(['success' => false, 'message' => 'Time conflict with existing booking (pending or confirmed).']);
                exit;
            }
            
            $insert = $pdo->prepare("INSERT INTO bookings (user_id, student_name, venue_id, booking_date, start_time, duration_hours, comment, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
            $insert->execute([$userId, $studentName, $venueId, $date, $time, $duration, $comment]);
            
            $items = [[
                'venue' => ubook_venue_display_name($pdo, $venueId),
                'date' => $date,
                'time' => $time,
                'duration' => $duration,
                'comment' => $comment
            ]];
            $emailSent = ubook_send_booking_confirmation($toEmail, $studentName, $items);
            
            echo json_encode([
                'success' => true,
                'email_sent' => $emailSent,
                'message' => 'Booking submitted (pending approval).' . ($emailSent ? ' A confirmation email has been sent.' : ' Could not send email, but your booking is saved.')
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ---- Post a comment ----
    if ($action === 'post_comment') {
        $requireLogin();
        $venueId = (string)($input['venue_id'] ?? '');
        $comment = trim($input['comment'] ?? '');
        if (empty($venueId) || empty($comment)) {
            echo json_encode(['success' => false, 'message' => 'Comment cannot be empty']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO venue_comments (venue_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$venueId, (string)$_SESSION['user_id'], $comment]);
            echo json_encode(['success' => true, 'message' => 'Comment added']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ---- Delete a comment ----
    if ($action === 'delete_comment') {
        $requireLogin();
        $commentId = (int)($input['comment_id'] ?? 0);
        $userId = (string)$_SESSION['user_id'];
        try {
            $stmt = $pdo->prepare("SELECT user_id FROM venue_comments WHERE id = ?");
            $stmt->execute([$commentId]);
            $owner = $stmt->fetchColumn();
            if ($owner !== $userId) {
                echo json_encode(['success' => false, 'message' => 'You can only delete your own comments.']);
                exit;
            }
            $delete = $pdo->prepare("DELETE FROM venue_comments WHERE id = ?");
            $delete->execute([$commentId]);
            echo json_encode(['success' => true, 'message' => 'Comment deleted']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ---- Toggle like on a comment ----
    if ($action === 'toggle_like') {
        $requireLogin();
        $commentId = (int)($input['comment_id'] ?? 0);
        $userId = (string)$_SESSION['user_id'];
        try {
            $check = $pdo->prepare("SELECT 1 FROM comment_likes WHERE comment_id = ? AND user_id = ?");
            $check->execute([$commentId, $userId]);
            if ($check->fetch()) {
                $del = $pdo->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?");
                $del->execute([$commentId, $userId]);
                $liked = false;
            } else {
                $ins = $pdo->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
                $ins->execute([$commentId, $userId]);
                $liked = true;
            }
            $count = $pdo->prepare("SELECT COUNT(*) FROM comment_likes WHERE comment_id = ?");
            $count->execute([$commentId]);
            echo json_encode(['success' => true, 'liked' => $liked, 'like_count' => (int)$count->fetchColumn()]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ---- Fetch comments for a venue ----
    if ($action === 'get_comments') {
        $venueId = (string)($input['venue_id'] ?? '');
        $currentUserId = (string)$_SESSION['user_id'];
        try {
            $sql = "SELECT c.*, COALESCE(u.name, 'Deleted User') as user_name,
                           COALESCE(u.profile_image, '') as profile_image,
                           (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id) as like_count,
                           EXISTS(SELECT 1 FROM comment_likes WHERE comment_id = c.id AND user_id = ?) as user_liked
                    FROM venue_comments c
                    LEFT JOIN users u ON c.user_id = u.id
                    WHERE c.venue_id = ?
                    ORDER BY c.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$currentUserId, $venueId]);
            echo json_encode(['success' => true, 'comments' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
}

// --------------------------- LOAD VENUES FOR DISPLAY ----------------------
$venues = $pdo->query("SELECT id, name, description, image_url FROM venues ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$currentUserIdJs = json_encode($_SESSION['user_id'] ?? '0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>UBook · Campus Venue Booking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ========== RESET & GLOBAL ========== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --primary: #e67e22; --primary-light: #f39c12; --primary-lighter: #ffb74d; --secondary: #f9f5eb; --accent: #ff9800; --dark: #d35400; --light: #ffffff; --text: #333333; --text-light: #666666; --shadow: 0 4px 20px rgba(0, 0, 0, 0.1); }
        html { scroll-behavior: smooth; }
        body { font-family: 'Poppins', sans-serif; background: var(--secondary); margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; color: var(--text); overflow-x: hidden; }
        header { background: linear-gradient(135deg, var(--dark), var(--primary), var(--primary-light)); padding: 15px 5%; color: var(--light); display: flex; align-items: center; justify-content: space-between; box-shadow: var(--shadow); position: sticky; top: 0; z-index: 100; }
        .logo a { color: var(--light); text-decoration: none; display: flex; align-items: center; font-size: 1.5rem; font-weight: 700; }
        .logo i { margin-right: 10px; }
        .nav-links { display: flex; gap: 25px; }
        .nav-links a { color: var(--light); text-decoration: none; font-weight: 500; padding: 8px 15px; border-radius: 25px; transition: all 0.3s ease; }
        .nav-links a:hover, .nav-links a.active { background: rgba(255,255,255,0.2); }
        .icon-button { width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: 0.3s; color: var(--light); text-decoration: none; }
        .icon-button:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px); }
        .header-icons { display: flex; gap: 15px; align-items: center; }
        .logout-btn { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.3); padding: 8px 15px; border-radius: 20px; text-decoration: none; color: var(--light); font-size: 0.9rem; transition: 0.3s; }
        .logout-btn:hover { background: rgba(255,255,255,0.2); }
        .hero-video { position: relative; width: 100%; height: 85vh; min-height: 550px; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #1a1a2e; }
        .hero-video video { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0; }
        .hero-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(135deg, rgba(0,0,0,0.65), rgba(0,0,0,0.4)); z-index: 1; }
        .hero-content { position: relative; z-index: 2; text-align: center; color: white; max-width: 800px; padding: 0 20px; animation: fadeInUp 1s ease-out; }
        .hero-content h1 { font-size: 3.5rem; font-weight: 800; margin-bottom: 20px; text-shadow: 0 2px 10px rgba(0,0,0,0.3); }
        .hero-content p { font-size: 1.2rem; margin-bottom: 30px; }
        .explore-btn { background: linear-gradient(135deg, var(--primary), var(--primary-light)); border: none; padding: 14px 40px; font-size: 1.2rem; font-weight: 600; border-radius: 50px; color: white; cursor: pointer; transition: 0.3s; box-shadow: 0 8px 20px rgba(0,0,0,0.2); display: inline-flex; align-items: center; gap: 12px; }
        .explore-btn:hover { transform: translateY(-4px); background: var(--dark); box-shadow: 0 15px 30px rgba(0,0,0,0.3); }
        .explore-btn:hover i { transform: translateX(6px); }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }
        .booking-main { padding: 60px 5%; max-width: 1400px; margin: 0 auto; width: 100%; }
        .step-header { text-align: center; margin-bottom: 40px; }
        .step-header h2 { font-size: 2.5rem; color: var(--primary); margin-bottom: 10px; }
        .filter-section { background: white; border-radius: 60px; padding: 20px 30px; margin-bottom: 40px; box-shadow: var(--shadow); display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 15px; }
        .filter-search { flex: 2; min-width: 200px; display: flex; align-items: center; gap: 10px; background: #f5f5f5; border-radius: 50px; padding: 5px 15px; }
        .filter-search i { color: var(--primary); }
        .filter-search input { border: none; background: transparent; padding: 12px 0; font-size: 1rem; width: 100%; outline: none; font-family: 'Poppins', sans-serif; }
        .filter-stats { font-size: 0.9rem; color: var(--text-light); background: #fef5e8; padding: 6px 16px; border-radius: 40px; }
        .clear-filter { background: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 8px 20px; border-radius: 40px; cursor: pointer; font-weight: 500; transition: 0.2s; }
        .clear-filter:hover { background: var(--primary); color: white; }
        .venues-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 30px; margin-bottom: 60px; scroll-margin-top: 90px; }
        .venue-card { background: var(--light); border-radius: 20px; overflow: hidden; box-shadow: var(--shadow); transition: 0.3s; display: flex; flex-direction: column; }
        .venue-card:hover { transform: translateY(-6px); box-shadow: 0 20px 35px rgba(0,0,0,0.12); }
        .venue-img { height: 200px; overflow: hidden; }
        .venue-img img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s; }
        .venue-card:hover .venue-img img { transform: scale(1.03); }
        .venue-info { padding: 20px; flex: 1; }
        .venue-info h3 { font-size: 1.4rem; color: var(--primary); margin-bottom: 8px; }
        .toggle-desc-btn { background: none; border: none; color: var(--primary); font-size: 0.8rem; cursor: pointer; margin: 5px 0; padding: 0; display: inline-block; text-decoration: underline; }
        .venue-desc { color: var(--text-light); font-size: 0.9rem; margin: 10px 0; line-height: 1.5; display: none; }
        .venue-desc.show { display: block; }
        .book-btn { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; border: none; width: 100%; padding: 12px; border-radius: 40px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: 0.3s; margin-top: 10px; }
        .book-btn:hover { background: var(--dark); transform: translateY(-2px); }
        .cart-section { background: white; border-radius: 28px; padding: 30px; box-shadow: var(--shadow); margin-top: 20px; }
        .cart-header { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; margin-bottom: 25px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; }
        .cart-header h3 { font-size: 1.8rem; color: var(--dark); }
        .cart-actions { display: flex; gap: 15px; }
        .btn-outline { background: transparent; border: 2px solid var(--primary); color: var(--primary); padding: 8px 20px; border-radius: 40px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-outline:hover { background: var(--primary); color: white; }
        .btn-danger { background: #fff0f0; color: #d32f2f; border-color: #ffcdd2; }
        .btn-danger:hover { background: #d32f2f; color: white; }
        .btn-success { background: linear-gradient(135deg, #2e7d32, #43a047); color: white; border: none; }
        .cart-items-list { min-height: 150px; max-height: 400px; overflow-y: auto; }
        .cart-item { background: #fef9f0; margin: 12px 0; padding: 15px 20px; border-radius: 18px; display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-start; }
        .cart-item-info { flex: 3; }
        .cart-item-info strong { font-size: 1.1rem; color: var(--primary); }
        .cart-item-date { font-size: 0.85rem; color: var(--text-light); display: block; }
        .cart-item-comment { font-size: 0.8rem; color: var(--primary); background: rgba(230,126,34,0.1); padding: 5px 10px; border-radius: 12px; margin-top: 8px; display: inline-block; }
        .delete-item { background: #ffe6e6; color: #c62828; border: none; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; transition: 0.2s; }
        .delete-item:hover { background: #c62828; color: white; }
        .empty-cart { text-align: center; padding: 40px; color: var(--text-light); }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; align-items: center; justify-content: center; }
        .modal-content { background: white; width: 90%; max-width: 500px; border-radius: 28px; padding: 25px; animation: fadeInUp 0.3s; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 30px; font-family: inherit; }
        .modal-buttons { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; }
        .toast-msg { position: fixed; bottom: 100px; left: 50%; transform: translateX(-50%); background: #323232; color: white; padding: 12px 24px; border-radius: 50px; z-index: 1100; font-size: 0.9rem; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .toast-error { background: #c62828; }

        /* ========== ENHANCED THANK YOU PAGE ========== */
        .thankyou-container {
            background: #ffffff;
            border-radius: 30px;
            padding: 60px 40px;
            text-align: center;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.08);
            margin: 40px auto;
            max-width: 900px;
            position: relative;
            overflow: hidden;
            animation: slideUpFade 0.6s ease-out forwards;
        }
        @keyframes slideUpFade {
            0% { opacity: 0; transform: translateY(40px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        .thankyou-container::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 12px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
        }
        .thankyou-title {
            font-size: 2.8rem;
            color: var(--text);
            margin: 20px 0 10px;
            font-weight: 800;
        }
        .thankyou-subtitle {
            font-size: 1.1rem;
            color: var(--text-light);
            margin-bottom: 40px;
            line-height: 1.6;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        .next-steps {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 50px;
            flex-wrap: wrap;
        }
        .step-card {
            background: var(--secondary);
            padding: 30px 20px;
            border-radius: 24px;
            flex: 1;
            min-width: 220px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.03);
            transition: 0.3s;
            border: 1px solid rgba(230, 126, 34, 0.1);
        }
        .step-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(230, 126, 34, 0.15);
            border-color: rgba(230, 126, 34, 0.3);
        }
        .step-icon {
            width: 70px;
            height: 70px;
            background: rgba(230, 126, 34, 0.1);
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 20px;
        }
        .step-card h4 { margin-bottom: 10px; color: var(--text); font-size: 1.2rem; font-weight: 600; }
        .step-card p { font-size: 0.95rem; color: var(--text-light); line-height: 1.5; }
        
        .thankyou-actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .btn-primary-action, .btn-secondary-action {
            padding: 16px 40px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            border: none;
        }
        .btn-primary-action {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            box-shadow: 0 8px 20px rgba(230, 126, 34, 0.3);
        }
        .btn-primary-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(230, 126, 34, 0.4);
        }
        .btn-secondary-action {
            background: #2c3e50;
            color: white;
            box-shadow: 0 8px 20px rgba(44, 62, 80, 0.2);
        }
        .btn-secondary-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(44, 62, 80, 0.3);
            background: #1a252f;
        }

        /* Checkmark SVG Animation */
        .success-animation { margin: 0 auto 20px; display: flex; justify-content: center; }
        .checkmark {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            display: block;
            stroke-width: 3;
            stroke: #4CAF50;
            stroke-miterlimit: 10;
            margin: 0 auto;
            box-shadow: inset 0px 0px 0px #4CAF50;
            animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
        }
        .checkmark__circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 3;
            stroke-miterlimit: 10;
            stroke: #4CAF50;
            fill: none;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }
        .checkmark__check {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
        }
        @keyframes stroke { 100% { stroke-dashoffset: 0; } }
        @keyframes scale { 0%, 100% { transform: none; } 50% { transform: scale3d(1.1, 1.1, 1); } }
        @keyframes fill { 100% { box-shadow: inset 0px 0px 0px 50px rgba(76, 175, 80, 0.1); } }

        /* ---------- FOOTER ---------- */
        footer { background: linear-gradient(135deg, var(--dark), var(--primary)); color: var(--light); padding: 40px 5% 20px; text-align: center; margin-top: 60px; }
        .footer-content { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px,1fr)); gap: 30px; text-align: left; margin-bottom: 30px; }
        .footer-links a { color: rgba(255,255,255,0.8); text-decoration: none; display: block; margin-bottom: 8px; transition: 0.2s; }
        .footer-links a:hover { color: white; text-decoration: underline; }
        .social-links { display: flex; gap: 15px; margin-top: 15px; }
        .copyright { border-top: 1px solid rgba(255,255,255,0.2); padding-top: 20px; }
        
        @media (max-width: 1024px) { .venues-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px) { 
            .venues-grid { grid-template-columns: 1fr; } 
            .filter-section { flex-direction: column; align-items: stretch; } 
            .cart-header { flex-direction: column; gap: 12px; } 
            .hero-content h1 { font-size: 2.2rem; } 
            .hero-video { height: 70vh; } 
            .thankyou-container { padding: 40px 20px; border-radius: 30px; }
            .thankyou-title { font-size: 2.2rem; }
            .step-card { min-width: 100%; }
        }

        /* Chat UI */
        .chat-launcher { position: fixed; bottom: 30px; right: 30px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; padding: 15px 20px; border-radius: 50px; display: flex; align-items: center; gap: 10px; cursor: pointer; box-shadow: 0 4px 15px rgba(0,0,0,0.2); z-index: 999; transition: 0.3s; }
        .chat-launcher:hover { background: var(--primary-light); transform: translateY(-3px); }
        .chat-popup { position: fixed; bottom: 100px; right: 30px; width: 420px; max-width: calc(100vw - 40px); background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); overflow: hidden; display: none; flex-direction: column; z-index: 1000; }
        .chat-header { background: linear-gradient(135deg, var(--dark), var(--primary)); color: white; padding: 15px 20px; }
        .chat-header-content { display: flex; justify-content: space-between; align-items: center; }
        .bot-info { display: flex; align-items: center; gap: 10px; }
        .status { display: flex; align-items: center; gap: 5px; font-size: 12px; opacity: 0.8; }
        .status-dot { width: 8px; height: 8px; background: #4CAF50; border-radius: 50%; }
        .close-btn { font-size: 20px; cursor: pointer; }
        .chat-body { height: 400px; overflow-y: auto; padding: 15px; background: #f9f9f9; display: flex; flex-direction: column; }
        .timestamp { text-align: center; color: #999; font-size: 12px; margin: 10px 0; }
        .bot-message { background: white; padding: 12px 15px; border-radius: 12px; margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); align-self: flex-start; max-width: 85%; border-bottom-left-radius: 4px; }
        .user-message { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; padding: 12px 15px; border-radius: 12px; margin: 10px 0; align-self: flex-end; max-width: 85%; border-bottom-right-radius: 4px; }
        .quick-reply { background: #f1f1f1; border: none; border-radius: 20px; padding: 8px 15px; margin: 5px; font-size: 14px; cursor: pointer; transition: 0.3s; display: inline-block; }
        .quick-reply:hover { background: #e0e0e0; transform: translateY(-2px); }
        .typing-indicator { display: flex; align-items: center; gap: 10px; margin: 10px 0; background: #e9e9e9; padding: 10px 15px; border-radius: 20px; width: fit-content; display: none; }
        .typing-dots { display: flex; gap: 5px; }
        .typing-dot { width: 8px; height: 8px; background: #888; border-radius: 50%; animation: typing 1.4s infinite ease-in-out; }
        @keyframes typing { 0%,60%,100% { transform: translateY(0); } 30% { transform: translateY(-5px); } }
        .chat-footer { padding: 15px; background: white; border-top: 1px solid #eee; }
        .chat-input-container { display: flex; gap: 10px; }
        #user-input { flex: 1; padding: 10px 15px; border: 1px solid #ddd; border-radius: 20px; outline: none; }
        .send-btn { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; transition: 0.3s; }
        .send-btn:hover { transform: scale(1.1); }
        .chat-clear-btn { background: rgba(255,255,255,0.2); border: none; color: white; border-radius: 20px; padding: 5px 12px; font-size: 12px; cursor: pointer; margin-left: 10px; }
        .comments-section { margin-top: 15px; border-top: 1px solid #ffe0c4; padding-top: 12px; }
        .comment-list { max-height: 250px; overflow-y: auto; margin-bottom: 10px; }
        .comment-item { background: #fff8f0; border-radius: 16px; padding: 8px 12px; margin-bottom: 8px; font-size: 0.85rem; position: relative; }
        .comment-header { display: flex; align-items: center; gap: 8px; margin-bottom: 5px; }
        .comment-user { font-weight: 600; color: #e67e22; }
        .comment-time { font-size: 0.7rem; color: #999; }
        .comment-text { margin: 5px 0; }
        .comment-actions { display: flex; gap: 15px; align-items: center; }
        .like-btn { background: none; border: none; cursor: pointer; color: #e67e22; }
        .like-btn.liked { color: #d35400; font-weight: bold; }
        .delete-comment-btn { background: none; border: none; cursor: pointer; color: #c62828; font-size: 0.8rem; margin-left: auto; }
        .delete-comment-btn:hover { color: #8b0000; }
        .comment-input-area { display: flex; gap: 8px; margin-top: 10px; }
        .comment-input-area input { flex: 1; padding: 8px 12px; border-radius: 30px; border: 1px solid #ffe0c4; }
        .comment-submit { background: #e67e22; color: white; border: none; border-radius: 30px; padding: 8px 16px; cursor: pointer; }
        .toggle-comments-btn { background: none; border: none; color: #e67e22; margin-top: 10px; cursor: pointer; font-size: 0.85rem; }
        .chat-venue-btn { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; border: none; border-radius: 30px; padding: 8px 16px; margin: 5px 5px 5px 0; cursor: pointer; font-size: 0.85rem; transition: 0.2s; }
        .chat-venue-btn:hover { transform: scale(1.02); }
    </style>
</head>
<body>
    <header>
        <div class="logo"><a href="main.menu.php"><i class="fas fa-calendar-check"></i><span>UBook</span></a></div>
        <div class="nav-links">
            <a href="main.menu.php">Home</a>
            <a href="venue.php" class="active">Venues</a>
            <a href="Community.php">Community</a>
        </div>
        <div class="header-icons">
            <a href="profile.php" class="icon-button"><i class="fas fa-user"></i></a>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <section class="hero-video">
        <video autoplay muted loop playsinline poster="">
            <source src="video1.mp4" type="video/mp4">
        </video>
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <h1>Your Campus, Your Stage</h1>
            <button class="explore-btn" id="exploreMoreBtn">
                Book Now <i class="fas fa-arrow-right"></i>
            </button>
        </div>
    </section>

    <main class="booking-main" id="bookingMainArea">
        <div class="step-header">
            <h2>Venue Selection Hub</h2>
            <p>Choose from our 8 amazing venues — all free for students</p>
        </div>

        <div class="filter-section">
            <div class="filter-search">
                <i class="fas fa-search"></i>
                <input type="text" id="filterInput" placeholder="Search venue by name...">
            </div>
            <div class="filter-stats" id="filterStats">Showing all 8 venues</div>
            <button class="clear-filter" id="clearFilterBtn">Clear filter</button>
        </div>

        <div class="venues-grid" id="venuesGrid">
            <?php foreach ($venues as $venue): ?>
            <div class="venue-card" data-venue-id="<?= htmlspecialchars($venue['id']) ?>" data-venue-name="<?= htmlspecialchars($venue['name']) ?>">
                <div class="venue-img">
                    <img src="<?= htmlspecialchars($venue['image_url'] ?? 'https://placehold.co/400x200?text=Venue+Image') ?>" 
                         alt="<?= htmlspecialchars($venue['name']) ?>" 
                         onerror="this.src='https://placehold.co/400x200?text=Venue+Image'">
                </div>
                <div class="venue-info">
                    <h3><?= htmlspecialchars($venue['name']) ?></h3>
                    <button class="toggle-desc-btn" data-venue-id="<?= htmlspecialchars($venue['id']) ?>">
                        <i class="fas fa-info-circle"></i> Show More
                    </button>
                    <p class="venue-desc" id="desc-<?= htmlspecialchars($venue['id']) ?>">
                        <?= htmlspecialchars($venue['description'] ?? 'No description available') ?>
                    </p>
                    <button class="book-btn" data-id="<?= htmlspecialchars($venue['id']) ?>" data-name="<?= htmlspecialchars($venue['name']) ?>">
                        <i class="fas fa-calendar-plus"></i> Book Now
                    </button>
                    <div class="comments-wrapper" data-venue-id="<?= htmlspecialchars($venue['id']) ?>">
                        <button class="toggle-comments-btn"><i class="fas fa-comment"></i> Show Comments</button>
                        <div class="comments-section" style="display:none;">
                            <div class="comment-list"></div>
                            <div class="comment-input-area">
                                <input type="text" placeholder="Write a comment..." class="comment-input">
                                <button class="comment-submit">Post</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="cart-section" id="cartSection">
            <div class="cart-header">
                <h3><i class="fas fa-shopping-cart"></i> Your Bookings (Cart)</h3>
                <div class="cart-actions">
                    <button class="btn-outline btn-danger" id="deleteLastBtn"><i class="fas fa-undo-alt"></i> Delete Last</button>
                    <button class="btn-outline" id="clearCartBtn">Clear All</button>
                    <button class="btn-outline btn-success" id="confirmBookingBtn"><i class="fas fa-check-circle"></i> Submit Request</button>
                </div>
            </div>
            <div id="cartItemsContainer" class="cart-items-list">
                <div class="empty-cart">✨ Your cart is empty. Click "Book Now" on any venue to start.</div>
            </div>
        </div>
    </main>

    <!-- NEW REDESIGNED SUCCESS PANEL -->
    <div id="thankyouPanel" style="display: none;" class="booking-main">
        <div class="thankyou-container" id="thankyouContainer">
            <div class="success-animation">
                <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                  <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none"/>
                  <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                </svg>
            </div>
            <h2 class="thankyou-title">Booking Request Submitted!</h2>
            <p class="thankyou-subtitle" id="thankYouMessage">
                Your request has been successfully sent to the admin for approval. A confirmation email has been sent to you. All bookings are at zero cost.
            </p>

            <div class="next-steps">
                <div class="step-card">
                    <div class="step-icon"><i class="fas fa-clock"></i></div>
                    <h4>Pending Approval</h4>
                    <p>Our admin is reviewing your request securely.</p>
                </div>
                <div class="step-card">
                    <div class="step-icon"><i class="fas fa-envelope-open-text"></i></div>
                    <h4>Email Update</h4>
                    <p>You'll receive an email as soon as it's approved.</p>
                </div>
                <div class="step-card">
                    <div class="step-icon"><i class="fas fa-calendar-check"></i></div>
                    <h4>All Set!</h4>
                    <p>Your venue will be completely ready for your event.</p>
                </div>
            </div>

            <div class="thankyou-actions">
                <button class="btn-primary-action" id="newBookingBtn">
                    <i class="fas fa-plus-circle"></i> Make Another Booking
                </button>
                <a href="profile.php" class="btn-secondary-action">
                    <i class="fas fa-list-ul"></i> View My Bookings
                </a>
            </div>
        </div>
    </div>

    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <h3 id="modalVenueName">Book Venue</h3>
            <div class="form-group"><label><i class="fas fa-calendar-alt"></i> Select Date</label><input type="date" id="bookingDate" min=""></div>
            <div class="form-group"><label><i class="fas fa-clock"></i> Start Time</label><input type="time" id="bookingTime" value="10:00" min="08:00" max="22:00"></div>
            <div class="form-group"><label><i class="fas fa-hourglass-half"></i> Time Taken (Duration in hours)</label><select id="bookingDuration"><option value="1">1 hour</option><option value="2" selected>2 hours</option><option value="3">3 hours</option><option value="4">4 hours</option></select></div>
            <div class="form-group"><label><i class="fas fa-comment"></i> Special Request / Comment (optional)</label><textarea id="bookingComment" placeholder="e.g., need extra chairs, wheelchair access, etc."></textarea></div>
            <div class="modal-buttons"><button class="btn-outline" id="closeModalBtn">Cancel</button><button class="book-btn" id="confirmAddToCartBtn" style="width:auto; padding:8px 20px;">Add to Cart</button></div>
        </div>
    </div>

    <div class="chat-launcher" onclick="toggleChat()"><span class="chat-icon">🤖</span><span class="chat-text">AI ChatBox</span></div>
    <div class="chat-popup" id="chat-popup">
        <div class="chat-header">
            <div class="chat-header-content">
                <div class="bot-info">
                    <div class="chat-logo"><i class="fas fa-robot"></i></div>
                    <div><strong>AI Chat Box</strong><div class="status"><span class="status-dot"></span> Ready to help you book</div></div>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button class="chat-clear-btn" id="clearChatBtn"><i class="fas fa-trash-alt"></i> Clear</button>
                    <div class="close-btn" onclick="toggleChat()"><i class="fas fa-times"></i></div>
                </div>
            </div>
        </div>
        <div class="chat-body" id="chat-body">
            <div class="timestamp" id="current-time"></div>
            <div id="quick-replies-area" style="margin-top: 10px;"></div>
            <div class="typing-indicator" id="typing-indicator"><div class="typing-dots"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div><span>AI is thinking...</span></div>
        </div>
        <div class="chat-footer">
            <div class="chat-input-container">
                <input type="text" id="user-input" placeholder="Ask me anything, e.g., 'book Main Hall'...">
                <button class="send-btn" onclick="sendUserMessage()"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-column">
                <h3>UBook Venue System</h3>
                <p>Official venue booking platform for students – reliable & zero charges. Track your 'time taken' per booking.</p>
                <div class="social-links">
                    <a href="#" class="icon-button"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="icon-button"><i class="fab fa-facebook-f"></i></a>
                </div>
            </div>
            <div class="footer-column">
                <h3>Quick Links</h3>
                <div class="footer-links">
                    <a href="main.menu.php">Home</a>
                    <a href="venue.php">Venues</a>
                    <a href="Community.php">Community</a>
                </div>
            </div>
            <div class="footer-column">
                <h3>Contact Support</h3>
                <div class="footer-links">
                    <a href="#"><i class="fas fa-phone"></i> +60 12-345 6789</a>
                    <a href="#"><i class="fas fa-envelope"></i> booking@UBook.com</a>
                </div>
            </div>
            <div class="footer-column newsletter">
                <h3>Newsletter</h3>
                <div class="newsletter-form" style="display:flex;">
                    <input type="email" placeholder="Your email">
                    <button style="background:#ffb74d; border:none; padding:0 15px;"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        </div>
        <div class="copyright">
            <p>&copy; 2026 UBook – Venue Hub | 8 Locations | Duration-Aware Booking System | AI Assistant</p>
        </div>
    </footer>

    <script>
        // ----------------------------------- GLOBALS -----------------------------------
        let cartItems = [];
        let nextCartId = 1;
        let currentSelectedVenue = null;
        const API_KEY = "<?= DEEPSEEK_API_KEY ?>";
        const DEEPSEEK_API_URL = "https://api.deepseek.com/chat/completions";
        let chatMessages = [];
        let isDeepSeekReady = false;
        let shouldUseFallbackOnly = false;
        let activeBooking = null;
        let venuesList = [];
        const STORAGE_KEY = 'ubook_chat_history';
        const currentUserId = <?= $currentUserIdJs ?>;
        const OPEN_HOUR = 8;
        const CLOSE_HOUR = 22;

        // ----------------------------------- HELPERS -----------------------------------
        function escapeHtml(str) { if (!str) return ''; return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[m])); }
        function timeToMinutes(timeStr) { const [h,m] = timeStr.split(':').map(Number); return h*60+m; }
        function calculateEndTime(start, dur) { const total = timeToMinutes(start) + dur*60; const h = Math.floor(total/60)%24, m = total%60; return `${h.toString().padStart(2,'0')}:${m.toString().padStart(2,'0')}`; }
        function todayISO() { return new Date().toISOString().split('T')[0]; }
        function addDays(days) { const d = new Date(); d.setDate(d.getDate()+days); return d.toISOString().split('T')[0]; }
        function showToast(msg, duration=3000, isError=false) { 
            let toast = document.querySelector('.toast-msg'); if (toast) toast.remove();
            let div = document.createElement('div'); div.className = 'toast-msg' + (isError ? ' toast-error' : ''); div.innerText = msg;
            document.body.appendChild(div); setTimeout(() => div.remove(), duration);
        }
        function scrollToBottom() { const body = document.getElementById('chat-body'); setTimeout(() => body.scrollTop = body.scrollHeight, 50); }
        function saveChatHistory() { try { localStorage.setItem(STORAGE_KEY, JSON.stringify(chatMessages)); } catch(e) {} }
        function loadChatHistory() { try { const saved = localStorage.getItem(STORAGE_KEY); if(saved) { const parsed = JSON.parse(saved); if(Array.isArray(parsed) && parsed.length) { chatMessages = parsed; return true; } } } catch(e) {} return false; }
        function resetChatMessages() { chatMessages = [{ role: "system", content: buildSystemPrompt() }]; saveChatHistory(); }
        function buildSystemPrompt() { const vlist = venuesList.map(v => `${v.id}: ${v.name}`).join(' | '); return `You are UBook AI Assistant for campus venue booking.\n\nFacts: All venues are free. Cancel free up to 24h before start. Duration per booking: 1–4 hours. Operating hours: ${OPEN_HOUR}:00 to ${CLOSE_HOUR}:00. Never ask for real name or phone.\n\nAvailable venues (id: name):\n${vlist}\n\nHelp users book by collecting, in order: (1) venue name from the list, (2) date as YYYY-MM-DD or "today"/"tomorrow", (3) start time as HH:MM (24h) or like 2pm (must be within ${OPEN_HOUR}:00-${CLOSE_HOUR}:00), (4) duration 1–4. If something is missing, ask only for that piece. Keep answers short.`; }
        function matchVenueByText(text) { if(!text || !venuesList.length) return null; const lower = text.toLowerCase(); for(let v of venuesList) if(lower.includes(v.name.toLowerCase())) return v; return null; }
        function parseTimeFromText(text) { 
            let m = text.match(/\b([01]?\d|2[0-3]):([0-5]\d)\b/);
            if(m) return `${String(parseInt(m[1])).padStart(2,'0')}:${m[2]}`;
            m = text.match(/\b([1-9]|1[0-2])(?::([0-5]\d))?\s*(am|pm)\b/i);
            if(m) { let h = parseInt(m[1]), mins = m[2]?parseInt(m[2]):0, ap = m[3].toLowerCase(); if(ap==='pm' && h<12) h+=12; if(ap==='am' && h===12) h=0; return `${String(h).padStart(2,'0')}:${String(mins).padStart(2,'0')}`; }
            return null;
        }
        function parseDurationFromText(text) { let m = text.match(/(\d+)\s*(hour|hours|hrs|h)\b/i); if(m){ let n=parseInt(m[1]); return n>=1&&n<=4?n:null; } return null; }
        function parseDateFromText(text) { let t=text.trim().toLowerCase(); if(t==='today') return todayISO(); if(t==='tomorrow') return addDays(1); let m=t.match(/\d{4}-\d{2}-\d{2}/); if(m && m[0]>=todayISO()) return m[0]; return null; }
        function tryParseOneShot(message) { let venue=matchVenueByText(message); if(!venue) return null; let date=parseDateFromText(message); let time=parseTimeFromText(message); let dur=parseDurationFromText(message) || 2; if(date && time) return { venueId:venue.id, venueName:venue.name, date, time, duration:dur }; return null; }
        function findTimeConflict(venueId, date, start, dur) { let newStart=timeToMinutes(start), newEnd=newStart+dur*60; for(let item of cartItems) if(item.venueId===venueId && item.date===date) { let exStart=timeToMinutes(item.time), exEnd=exStart+item.duration*60; if(newStart<exEnd && newEnd>exStart) return true; } return false; }
        function isWithinOperatingHours(start, dur) {
            let startMin = timeToMinutes(start);
            let endMin = startMin + dur * 60;
            let openMin = OPEN_HOUR * 60;
            let closeMin = CLOSE_HOUR * 60;
            return startMin >= openMin && endMin <= closeMin;
        }

        // ----------------------------------- CART -----------------------------------
        function renderCart() {
            const container = document.getElementById('cartItemsContainer');
            if(!container) return;
            if(cartItems.length===0) { container.innerHTML='<div class="empty-cart">🛒 No bookings yet. Select a venue and add to cart.</div>'; return; }
            container.innerHTML = cartItems.map(item => `
                <div class="cart-item" data-cartid="${item.cartItemId}">
                    <div class="cart-item-info">
                        <strong>${escapeHtml(item.venueName)}</strong>
                        <span class="cart-item-date">📅 ${item.date} | 🕒 ${item.time} → ${item.endTime} | ⏱️ ${item.duration} hour${item.duration>1?'s':''}</span>
                        ${item.comment ? `<div class="cart-item-comment"><i class="fas fa-comment"></i> ${escapeHtml(item.comment)}</div>` : ''}
                    </div>
                    <div class="cart-item-actions">
                        <button class="delete-item" onclick="removeCartItem(${item.cartItemId})"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
            `).join('');
        }
        window.removeCartItem = function(cid) { cartItems = cartItems.filter(i => i.cartItemId !== cid); renderCart(); showToast("Removed from cart",1200); };
        function deleteLastBooking() { if(cartItems.length) { cartItems.pop(); renderCart(); showToast("Last item removed",1500); } else showToast("Cart empty",1200); }
        function clearCart() { if(cartItems.length) { cartItems=[]; renderCart(); showToast("Cart cleared",1200); } }

        // ----------------------------------- MODAL & BOOKING -----------------------------------
        function openBookingModal(venue) { 
            if(!venue?.id) return;
            currentSelectedVenue = venue;
            document.getElementById('modalVenueName').innerText = `📌 Book: ${venue.name}`;
            let today = todayISO();
            document.getElementById('bookingDate').min = today;
            document.getElementById('bookingDate').value = today;
            document.getElementById('bookingTime').value = "10:00";
            document.getElementById('bookingTime').min = "08:00";
            document.getElementById('bookingTime').max = "22:00";
            document.getElementById('bookingDuration').value = "2";
            document.getElementById('bookingComment').value = "";
            document.getElementById('bookingModal').style.display = 'flex';
        }
        function closeModal() { document.getElementById('bookingModal').style.display = 'none'; currentSelectedVenue = null; }
        function addToCartFromModal() {
            if(!currentSelectedVenue) return;
            let date = document.getElementById('bookingDate').value;
            let time = document.getElementById('bookingTime').value;
            let dur = parseInt(document.getElementById('bookingDuration').value);
            let comment = document.getElementById('bookingComment').value.trim();
            if(!date || !time || isNaN(dur)) return;
            if(!isWithinOperatingHours(time, dur)) {
                showToast(`Booking must be between ${OPEN_HOUR}:00 and ${CLOSE_HOUR}:00.`, 4000, true);
                return;
            }
            if(findTimeConflict(currentSelectedVenue.id, date, time, dur)) { showToast("Time conflict with existing cart item",4000,true); return; }
            let end = calculateEndTime(time, dur);
            cartItems.push({ cartItemId: nextCartId++, venueId: currentSelectedVenue.id, venueName: currentSelectedVenue.name, date, time, duration: dur, endTime: end, comment });
            renderCart();
            showToast(`✅ ${currentSelectedVenue.name} added to cart`,1800);
            closeModal();
        }
        
        // Email collection modal (if needed)
        let emailPromiseResolve = null;
        function showEmailModal() {
            return new Promise((resolve) => {
                emailPromiseResolve = resolve;
                let modalHtml = `
                    <div id="emailModal" class="modal" style="display:flex;">
                        <div class="modal-content">
                            <h3>📧 Email Required</h3>
                            <p>Please provide your email address to receive booking confirmation.</p>
                            <div class="form-group">
                                <input type="email" id="emailInput" placeholder="your@example.com" style="width:100%;">
                            </div>
                            <div class="modal-buttons">
                                <button id="submitEmailBtn" class="book-btn">Submit</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.insertAdjacentHTML('beforeend', modalHtml);
                document.getElementById('submitEmailBtn').onclick = () => {
                    let email = document.getElementById('emailInput').value.trim();
                    if(email && email.includes('@')) {
                        document.getElementById('emailModal').remove();
                        resolve(email);
                    } else {
                        alert("Please enter a valid email address.");
                    }
                };
            });
        }
        
        async function saveBookingsToDatabase(providedEmail = '') {
            let payload = cartItems.map(i => ({ venueId:i.venueId, date:i.date, time:i.time, duration:i.duration, comment:i.comment || '' }));
            try {
                let resp = await fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ action:'save_booking', bookings:payload, notify_email:providedEmail }) });
                let data = await resp.json();
                if(!data.success) {
                    if(data.need_email) {
                        let email = await showEmailModal();
                        return saveBookingsToDatabase(email);
                    }
                    showToast("❌ "+data.message,5000,true);
                    return false;
                }
                let emailMsg = data.email_sent ? " A confirmation email has been sent." : " Could not send email, but your booking is saved.";
                showToast("✅ "+data.message + emailMsg,4000);
                return true;
            } catch(e) { showToast("❌ Network error",3000,true); return false; }
        }
        
        function triggerThankYouAnimation() {
            let tyPanel = document.getElementById('thankyouPanel');
            let tyContainer = document.getElementById('thankyouContainer');
            tyPanel.style.display = 'block';
            
            // Trigger reflow to restart CSS animations reliably
            tyContainer.style.animation = 'none';
            let svgs = tyContainer.querySelectorAll('.checkmark, .checkmark__circle, .checkmark__check');
            svgs.forEach(s => s.style.animation = 'none');
            
            void tyContainer.offsetWidth; // Force Reflow
            
            tyContainer.style.animation = null;
            svgs.forEach(s => s.style.animation = null);
        }

        async function confirmBooking() {
            if(!cartItems.length) { showToast("Cart empty",1800,true); return; }
            let ok = await saveBookingsToDatabase('');
            if(!ok) return;
            
            document.getElementById('thankYouMessage').innerHTML = `Your booking request for <strong>${cartItems.length} venue(s)</strong> has been successfully submitted and is pending approval.<br>You can view the full details in your profile.`;
            document.getElementById('bookingMainArea').style.display = 'none';
            
            triggerThankYouAnimation();
            cartItems = []; renderCart();
        }

        function resetToBookingView() { 
            document.getElementById('bookingMainArea').style.display = 'block'; 
            document.getElementById('thankyouPanel').style.display = 'none'; 
            cartItems = []; 
            renderCart(); 
            showToast("Ready for new bookings",1500); 
        }

        function scrollToVenueHub() { document.getElementById('venuesGrid')?.scrollIntoView({ behavior:'smooth' }); }

        // ----------------------------------- COMMENTS & LIKES & DELETE -----------------------------------
        async function loadComments(venueId, wrapper) {
            let listDiv = wrapper.querySelector('.comment-list');
            listDiv.innerHTML = '<div style="text-align:center;padding:10px;">Loading...</div>';
            try {
                let resp = await fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ action:'get_comments', venue_id:venueId }) });
                let data = await resp.json();
                if(data.success) renderComments(listDiv, data.comments);
                else listDiv.innerHTML = '<div>Failed to load comments.</div>';
            } catch(e) { listDiv.innerHTML = '<div>Error loading comments.</div>'; }
        }
        async function deleteComment(commentId, venueId, wrapper) {
            if(!confirm("Are you sure you want to delete this comment?")) return;
            try {
                let resp = await fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ action:'delete_comment', comment_id:commentId }) });
                let data = await resp.json();
                if(data.success) {
                    showToast("Comment deleted",1500);
                    await loadComments(venueId, wrapper);
                } else {
                    showToast(data.message,3000,true);
                }
            } catch(e) {
                showToast("Error deleting comment",2000,true);
            }
        }
        function renderComments(container, comments) {
            if(!comments.length) { container.innerHTML = '<div style="padding:10px; color:#999;">No comments yet. Be the first!</div>'; return; }
            container.innerHTML = comments.map(c => `
                <div class="comment-item" data-comment-id="${c.id}">
                    <div class="comment-header">
                        <span class="comment-user">${escapeHtml(c.user_name)}</span>
                        <span class="comment-time">${new Date(c.created_at).toLocaleString()}</span>
                        ${c.user_id == currentUserId ? `<button class="delete-comment-btn" data-comment-id="${c.id}" data-venue-id="${c.venue_id}"><i class="fas fa-trash-alt"></i></button>` : ''}
                    </div>
                    <div class="comment-text">${escapeHtml(c.comment)}</div>
                    <div class="comment-actions"><button class="like-btn ${c.user_liked ? 'liked' : ''}" data-comment-id="${c.id}"><i class="fas fa-thumbs-up"></i> <span class="like-count">${c.like_count}</span></button></div>
                </div>
            `).join('');
            container.querySelectorAll('.like-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    let cid = btn.dataset.commentId;
                    try {
                        let resp = await fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ action:'toggle_like', comment_id:cid }) });
                        let data = await resp.json();
                        if(data.success) {
                            let span = btn.querySelector('.like-count'); span.textContent = data.like_count;
                            if(data.liked) btn.classList.add('liked'); else btn.classList.remove('liked');
                        }
                    } catch(err) { console.error(err); }
                });
            });
            container.querySelectorAll('.delete-comment-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    let commentId = btn.dataset.commentId;
                    let venueId = btn.dataset.venueId;
                    let wrapper = btn.closest('.comments-wrapper');
                    if(wrapper) await deleteComment(commentId, venueId, wrapper);
                });
            });
        }
        async function postComment(venueId, inputEl, wrapper) {
            let comment = inputEl.value.trim();
            if(!comment) return;
            inputEl.disabled = true;
            try {
                let resp = await fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ action:'post_comment', venue_id:venueId, comment:comment }) });
                let data = await resp.json();
                if(data.success) { inputEl.value = ''; await loadComments(venueId, wrapper); }
                else showToast(data.message,2000,true);
            } catch(e) { showToast('Error posting comment',2000,true); }
            finally { inputEl.disabled = false; }
        }
        function initCommentSections() {
            document.querySelectorAll('.comments-wrapper').forEach(wrapper => {
                let venueId = wrapper.dataset.venueId;
                let toggle = wrapper.querySelector('.toggle-comments-btn');
                let section = wrapper.querySelector('.comments-section');
                let input = wrapper.querySelector('.comment-input');
                let postBtn = wrapper.querySelector('.comment-submit');
                let listDiv = wrapper.querySelector('.comment-list');
                toggle.addEventListener('click', async () => {
                    if(section.style.display === 'none') {
                        section.style.display = 'block';
                        toggle.innerHTML = '<i class="fas fa-comment"></i> Hide Comments';
                        if(listDiv.innerHTML === '') await loadComments(venueId, wrapper);
                    } else {
                        section.style.display = 'none';
                        toggle.innerHTML = '<i class="fas fa-comment"></i> Show Comments';
                    }
                });
                postBtn.addEventListener('click', () => postComment(venueId, input, wrapper));
                input.addEventListener('keypress', e => { if(e.key === 'Enter') postComment(venueId, input, wrapper); });
            });
        }

        // ----------------------------------- DESCRIPTION TOGGLE -----------------------------------
        function initDescriptionToggles() {
            document.querySelectorAll('.toggle-desc-btn').forEach(btn => {
                const venueId = btn.dataset.venueId;
                const descElement = document.getElementById(`desc-${venueId}`);
                if (!descElement) return;
                
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (descElement.classList.contains('show')) {
                        descElement.classList.remove('show');
                        btn.innerHTML = '<i class="fas fa-info-circle"></i> Show More';
                    } else {
                        descElement.classList.add('show');
                        btn.innerHTML = '<i class="fas fa-info-circle"></i> Hide More';
                    }
                });
            });
        }

        // ----------------------------------- AI CHAT -----------------------------------
        function initVenuesList() { 
            venuesList = []; 
            document.querySelectorAll('.venue-card').forEach(card => { let id = card.dataset.venueId, name = card.dataset.venueName; if(id && name) venuesList.push({ id, name }); }); 
        }
        async function initDeepSeek() { if(!loadChatHistory() || chatMessages.length===0) resetChatMessages(); isDeepSeekReady = Boolean(API_KEY && API_KEY.length > 10); shouldUseFallbackOnly = false; }
        async function callDeepSeek(message) {
            let messages = [...chatMessages, { role:"user", content:message }];
            let resp = await fetch(DEEPSEEK_API_URL, { method:"POST", headers:{ "Content-Type":"application/json", "Authorization":`Bearer ${API_KEY}` }, body:JSON.stringify({ model:"deepseek-chat", messages, temperature:0.7, max_tokens:600 }) });
            let data = await resp.json();
            if(!resp.ok) throw new Error(data?.error?.message || "API error");
            let reply = data.choices[0].message.content.trim();
            chatMessages = [...messages, { role:"assistant", content:reply }];
            saveChatHistory();
            return reply;
        }
        function getFallbackReply(q) { let l=q.toLowerCase(); if(l.includes("list venues")) return "Venues: "+venuesList.map(v=>v.name).join(", ")+". Tap a button or say which to book."; if(l.includes("book")) return "Say \"list venues\" or \"book Main Hall\". Example: book Conference Room tomorrow 2pm 2 hours."; if(l.includes("cost")) return "All venues are free for students."; if(l.includes("duration")) return "Choose 1–4 hours per slot."; if(l.includes("cancel")) return "Cancel free up to 24h before start."; if(l.includes("hour")) return `Operating hours: ${OPEN_HOUR}:00 to ${CLOSE_HOUR}:00.`; return "Try: list venues, book [venue name], or one line: book Seminar Room A today 14:00 3 hours."; }
        async function getAssistantReply(message) { if(!isDeepSeekReady || shouldUseFallbackOnly) return getFallbackReply(message); try { return await callDeepSeek(message); } catch(err) { shouldUseFallbackOnly = true; return getFallbackReply(message); } }
        function addBotMessage(text, isHtml=false) { let div = document.createElement('div'); div.className = 'bot-message'; if(isHtml) div.innerHTML = text; else div.textContent = text; document.getElementById('chat-body').appendChild(div); scrollToBottom(); saveChatHistory(); }
        function addUserMessage(text) { let div = document.createElement('div'); div.className = 'user-message'; div.textContent = text; document.getElementById('chat-body').appendChild(div); scrollToBottom(); saveChatHistory(); }
        function showTyping() { document.getElementById('typing-indicator').style.display = 'flex'; scrollToBottom(); }
        function hideTyping() { document.getElementById('typing-indicator').style.display = 'none'; }
        function showQuickReplies(buttons) { let area = document.getElementById('quick-replies-area'); area.innerHTML = '<div style="margin:5px 0;">✨ Quick actions:</div>'; buttons.forEach(b => { let btn = document.createElement('button'); btn.className = 'quick-reply'; btn.textContent = b.label; btn.onclick = () => { area.innerHTML = ''; sendUserMessage(b.value); }; area.appendChild(btn); }); }
        function showVenueButtons() { let area = document.getElementById('quick-replies-area'); area.innerHTML = '<div style="margin:5px 0;">🏛️ Select a venue to book:</div>'; venuesList.forEach(v => { let btn = document.createElement('button'); btn.className = 'chat-venue-btn'; btn.textContent = v.name; btn.onclick = () => { area.innerHTML = ''; startBookingFlow(v); }; area.appendChild(btn); }); let back = document.createElement('button'); back.className = 'quick-reply'; back.textContent = '« Back'; back.onclick = () => { showInitialQuickReplies(); }; area.appendChild(back); }
        function showInitialQuickReplies() { showQuickReplies([ { label:'📋 List Venues', value:'list venues' }, { label:'📖 How to book?', value:'How do I book a venue?' }, { label:'⏱️ Duration info', value:'What is the duration/time taken option?' }, { label:'❌ Cancel policy', value:'Cancelation policy?' }, { label:'💰 Zero cost?', value:'Is there any cost?' }, { label:'🕒 Operating hours', value:'What are the operating hours?' } ]); }
        function showTimeQuickPicks() { let area = document.getElementById('quick-replies-area'); area.innerHTML = '<div style="margin:6px 0;">Common start times (8:00-22:00):</div>'; ['09:00','12:00','14:00','18:00'].forEach(t => { let btn = document.createElement('button'); btn.className = 'quick-reply'; btn.textContent = t; btn.onclick = () => { area.innerHTML = ''; sendUserMessage(t); }; area.appendChild(btn); }); }
        function showBookingConfirmActions() { let area = document.getElementById('quick-replies-area'); area.innerHTML = '<div style="margin:6px 0;font-weight:500;">Ready to submit?</div>'; let ok = document.createElement('button'); ok.className = 'quick-reply'; ok.textContent = '✅ Confirm submit'; ok.onclick = async () => { area.innerHTML = ''; await finalizeBookingFromFlow(); showInitialQuickReplies(); }; let cancel = document.createElement('button'); cancel.className = 'quick-reply'; cancel.textContent = '❌ Cancel'; cancel.onclick = () => { area.innerHTML = ''; resetActiveBooking(); addBotMessage('Cancelled. Need anything else?'); showInitialQuickReplies(); }; area.appendChild(ok); area.appendChild(cancel); }
        function startBookingFlow(venue) { if(!venue?.id) { addBotMessage("Sorry, that venue is not available. Please use 'list venues' to see valid options."); return; } activeBooking = { step:'date', venue, date:null, time:null, duration:null, comment:'' }; addBotMessage(`Let's book ${venue.name}. Send a date: YYYY-MM-DD, or type "today" / "tomorrow".`); }
        async function submitDirectBooking(booking, providedEmail='') { 
            try { 
                let resp = await fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ action:'direct_booking', venueId:booking.venueId, date:booking.date, time:booking.time, duration:booking.duration, comment:booking.comment || '', notify_email:providedEmail }) }); 
                let data = await resp.json(); 
                if(!data.success) {
                    if(data.need_email) {
                        let email = await showEmailModal();
                        return submitDirectBooking(booking, email);
                    }
                    showToast("❌ "+data.message,5000,true); 
                    addBotMessage("Could not book: "+data.message); 
                    return false; 
                }
                let emailMsg = data.email_sent ? " A confirmation email has been sent." : " Could not send email, but your booking is saved.";
                showToast("✅ "+data.message + emailMsg,4000); 
                addBotMessage(`🎉 Submitted (pending approval): ${booking.date} ${booking.time} · ${booking.duration}h. You can book another slot or use the cart below for multiple venues.`); 
                
                // Show the newly enhanced Thank You Panel when AI books
                document.getElementById('thankYouMessage').innerHTML = `Your booking request for <strong>${booking.venueId}</strong> has been successfully submitted and is pending approval.<br>You can view the full details in your profile.`;
                document.getElementById('bookingMainArea').style.display = 'none';
                triggerThankYouAnimation();
                
                document.getElementById('venuesGrid')?.scrollIntoView({ behavior:'smooth' }); 
                return true; 
            } catch(e) { showToast("❌ Network error",3000,true); addBotMessage("Network error — please try again."); return false; } 
        }
        async function finalizeBookingFromFlow() { if(!activeBooking) return false; let { venue, date, time, duration, comment } = activeBooking; if(!venue?.id || !date || !time || !duration) { addBotMessage("❌ Missing information. Say 'list venues' to start again."); resetActiveBooking(); return false; } if(!isWithinOperatingHours(time, duration)) { addBotMessage(`❌ Booking time must be between ${OPEN_HOUR}:00 and ${CLOSE_HOUR}:00. Please choose a valid time.`); resetActiveBooking(); return false; } addBotMessage(`⏳ Submitting ${venue.name} · ${date} ${time} · ${duration}h…`); let ok = await submitDirectBooking({ venueId:venue.id, date, time, duration, comment:comment||'' }); resetActiveBooking(); return ok; }
        function resetActiveBooking() { activeBooking = null; }
        async function processBookingStep(msg) { if(!activeBooking) return false; let lower = msg.toLowerCase().trim(); if(lower === 'cancel') { addBotMessage("Booking cancelled. How else can I help?"); resetActiveBooking(); showInitialQuickReplies(); return true; } let step = activeBooking.step; if(step === 'confirm') { if(/^(yes|y|confirm|ok|sure)$/i.test(msg.trim())) { await finalizeBookingFromFlow(); showInitialQuickReplies(); return true; } addBotMessage('Tap Confirm submit below, or type CONFIRM / CANCEL.'); return true; } if(step === 'date') { let date = msg.trim(); if(lower === 'today') date = todayISO(); else if(lower === 'tomorrow') date = addDays(1); else if(parseDateFromText(msg)) date = parseDateFromText(msg); if(!/^\d{4}-\d{2}-\d{2}$/.test(date) || date < todayISO()) { addBotMessage(`Please send a valid date on or after ${todayISO()} (YYYY-MM-DD), or say "today" / "tomorrow". Example: ${addDays(7)}`); return true; } activeBooking.date = date; activeBooking.step = 'time'; addBotMessage(`Start time? Use 24h HH:MM (e.g. 14:00) or 2pm / 3:30pm. Operating hours ${OPEN_HOUR}:00 to ${CLOSE_HOUR}:00.`); showTimeQuickPicks(); return true; } if(step === 'time') { let time = msg.trim(); let parsed = parseTimeFromText(msg); if(parsed) time = parsed; if(!/^([01]\d|2[0-3]):([0-5]\d)$/.test(time)) { addBotMessage("Please enter a valid time: 14:00 or 2pm."); showTimeQuickPicks(); return true; } activeBooking.time = time; activeBooking.step = 'duration'; document.getElementById('quick-replies-area').innerHTML = ''; addBotMessage(`Duration in hours: 1, 2, 3, or 4?`); showQuickReplies([{ label:'1 h', value:'1' }, { label:'2 h', value:'2' }, { label:'3 h', value:'3' }, { label:'4 h', value:'4' }]); return true; } if(step === 'duration') { let dur = parseInt(msg.trim(),10); if(isNaN(dur) || dur<1 || dur>4) { addBotMessage("Pick 1–4 hours (number only)."); return true; } activeBooking.duration = dur; activeBooking.step = 'confirm'; document.getElementById('quick-replies-area').innerHTML = ''; let endTime = calculateEndTime(activeBooking.time, dur); addBotMessage(`Please review:\n📌 ${activeBooking.venue.name}\n📅 ${activeBooking.date}  🕒 ${activeBooking.time} → ${endTime}\n⏱️ ${dur} hour(s)\n\nSubmit now?`); showBookingConfirmActions(); return true; } return false; }
        async function handleUserMessage(message) { if(!message.trim()) return; addUserMessage(message); if(activeBooking && await processBookingStep(message)) return; let oneShot = tryParseOneShot(message); if(oneShot && !activeBooking) { if(!isWithinOperatingHours(oneShot.time, oneShot.duration)) { addBotMessage(`⚠️ The time ${oneShot.time} for ${oneShot.duration}h ends after ${CLOSE_HOUR}:00. Please choose a start time that ends by ${CLOSE_HOUR}:00.`); return; } activeBooking = { step:'confirm', venue:{ id:oneShot.venueId, name:oneShot.venueName }, date:oneShot.date, time:oneShot.time, duration:oneShot.duration, comment:'' }; addBotMessage(`I parsed your request:\n📌 ${oneShot.venueName}\n📅 ${oneShot.date} at ${oneShot.time} (ends ${calculateEndTime(oneShot.time, oneShot.duration)})\n⏱️ ${oneShot.duration} hour(s)\n\nSubmit now?`); showBookingConfirmActions(); return; } let lower = message.toLowerCase().trim(); if(lower.includes('list venues') || lower==='venues' || lower==='show venues') { addBotMessage(`We have ${venuesList.length} venues: ${venuesList.map(v=>v.name).join(', ')}. Pick one below or say e.g. "book Main Hall tomorrow 2pm 2 hours".`); showVenueButtons(); return; } let intent = matchVenueByText(message); if(intent && /book|reserve|schedule|want\s+to|i\s*'?d\s+like|need\s+a\s+room/i.test(message)) { startBookingFlow(intent); return; } if(lower.includes('how to book') || lower==='help') { addBotMessage(`Quick ways to book:\n1) Say "book Main Hall" then follow date → time → duration.\n2) One line: "book Seminar Room A tomorrow 14:00 2 hours".\n3) Use buttons below. All free; cancel up to 24h before.\nOperating hours: ${OPEN_HOUR}:00 to ${CLOSE_HOUR}:00.`); showInitialQuickReplies(); return; } showTyping(); let reply = await getAssistantReply(message); hideTyping(); addBotMessage(reply); setTimeout(() => { if(document.getElementById('quick-replies-area').children.length===0 && !activeBooking) showInitialQuickReplies(); },500); }
        function sendUserMessage(predefined=null) { let input = document.getElementById('user-input'); let msg = predefined !== null ? predefined : input.value.trim(); if(!msg) return; if(predefined === null) input.value = ''; handleUserMessage(msg).catch(console.warn); }
        function clearChatHistory() { chatMessages = [{ role: "system", content: buildSystemPrompt() }]; saveChatHistory(); }
        function clearChat() { if(confirm("Clear all chat messages?")) { clearChatHistory(); const chatBody = document.getElementById('chat-body'); const messages = chatBody.querySelectorAll('.bot-message, .user-message'); messages.forEach(m => m.remove()); document.getElementById('quick-replies-area').innerHTML = ''; resetActiveBooking(); addBotMessage("Chat cleared. How can I help you?"); showInitialQuickReplies(); } }
        function toggleChat() { let popup = document.getElementById('chat-popup'); if(popup.style.display === 'flex') { popup.style.display = 'none'; } else { popup.style.display = 'flex'; document.getElementById('current-time').textContent = new Date().toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' }); let hasReal = chatMessages.some(m => m.role !== 'system'); if(!hasReal) { initDeepSeek(); if(!Array.from(document.querySelectorAll('.bot-message')).some(el => el.textContent.includes("Hi! I can guide you"))) { addBotMessage("👋 Hi! I can guide you step by step, or read one line like: book Main Hall tomorrow 3pm 2 hours. Try \"list venues\" or the shortcuts below."); showInitialQuickReplies(); } } else { let body = document.getElementById('chat-body'); body.querySelectorAll('.bot-message, .user-message').forEach(m => m.remove()); for(let msg of chatMessages) { if(msg.role === 'system') continue; let div = document.createElement('div'); div.className = msg.role === 'user' ? 'user-message' : 'bot-message'; div.textContent = msg.content; body.appendChild(div); } scrollToBottom(); } } }

        // ----------------------------------- FILTER -----------------------------------
        function updateFilter() { let val = document.getElementById('filterInput').value.trim().toLowerCase(); let cards = document.querySelectorAll('.venue-card'); let visible = 0; cards.forEach(card => { let name = card.dataset.venueName.toLowerCase(); if(val === '' || name.includes(val)) { card.style.display = ''; visible++; } else card.style.display = 'none'; }); let stats = document.getElementById('filterStats'); stats.textContent = val === '' ? `Showing all ${visible} venues` : `Found ${visible} venue(s) matching "${val}"`; }
        function clearFilter() { document.getElementById('filterInput').value = ''; updateFilter(); }

        // ----------------------------------- DOM READY -----------------------------------
        document.addEventListener('DOMContentLoaded', () => {
            initVenuesList(); renderCart();
            document.querySelectorAll('.book-btn').forEach(btn => btn.addEventListener('click', () => { let id = btn.dataset.id, name = btn.dataset.name; if(!id || !name) { showToast("Invalid venue data",1500,true); return; } openBookingModal({ id, name }); }));
            document.getElementById('closeModalBtn')?.addEventListener('click', closeModal);
            document.getElementById('confirmAddToCartBtn')?.addEventListener('click', addToCartFromModal);
            window.addEventListener('click', e => { if(e.target === document.getElementById('bookingModal')) closeModal(); });
            document.getElementById('deleteLastBtn')?.addEventListener('click', deleteLastBooking);
            document.getElementById('clearCartBtn')?.addEventListener('click', clearCart);
            document.getElementById('confirmBookingBtn')?.addEventListener('click', confirmBooking);
            document.getElementById('newBookingBtn')?.addEventListener('click', resetToBookingView);
            document.getElementById('exploreMoreBtn')?.addEventListener('click', scrollToVenueHub);
            initCommentSections();
            initDescriptionToggles();
            document.getElementById('user-input')?.addEventListener('keypress', e => { if(e.key === 'Enter') sendUserMessage(); });
            document.getElementById('clearChatBtn')?.addEventListener('click', clearChat);
            document.getElementById('filterInput')?.addEventListener('input', updateFilter);
            document.getElementById('clearFilterBtn')?.addEventListener('click', clearFilter);
            updateFilter();
        });
    </script>
</body>
</html>