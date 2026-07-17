<?php

session_start();
// Set timezone to Malaysia (UTC+8)
date_default_timezone_set('Asia/Kuala_Lumpur');

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

// ---------------------- PHPMailer autoload (check if Composer installed) -----
$phpmailerLoaded = false;
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $phpmailerLoaded = true;
} else {
    // Fallback: try to require PHPMailer manually if placed in includes folder
    if (file_exists(__DIR__ . '/includes/PHPMailer/PHPMailer.php')) {
        require_once __DIR__ . '/includes/PHPMailer/PHPMailer.php';
        require_once __DIR__ . '/includes/PHPMailer/SMTP.php';
        require_once __DIR__ . '/includes/PHPMailer/Exception.php';
        $phpmailerLoaded = true;
    }
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ==================== DATABASE CONNECTION ====================
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ==================== CREATE TABLES IF NOT EXISTS ====================
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

    $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        rating INT NOT NULL,
        review TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    // ignore if already exist
}

// ==================== SEED VENUES IF EMPTY ====================
$stmt = $pdo->query("SELECT COUNT(*) FROM venues");
if ($stmt->fetchColumn() == 0) {
    $defaultVenues = [
        ['id' => 'bas101', 'name' => 'Basketball Court', 'description' => 'Outdoor concrete court with two hoops.', 'image_url' => 'https://marvel-b1-cdn.bc0a.com/f00000000213893/wausautile.com/media/slides/HoopsPark05.jpg'],
        ['id' => 'cpl616', 'name' => 'Computer Lab', 'description' => 'Modest room with around 30 aging PCs.', 'image_url' => 'https://img.magnific.com/premium-photo/bright-computer-lab-with-modern-equipment-technology_889056-39214.jpg'],
        ['id' => 'exm456', 'name' => 'Exam Hall', 'description' => 'Quiet, spacious hall for examinations.', 'image_url' => 'https://www.teachingcollege.fse.manchester.ac.uk/wp-content/uploads/2022/07/exam-hall.jpg'],
        ['id' => 'lec131', 'name' => 'Lecture Hall', 'description' => 'Medium-sized hall with tiered seating.', 'image_url' => 'https://i.pinimg.com/originals/fa/40/df/fa40df3d4641603432dc6cd50d29c20b.jpg'],
        ['id' => 'mme123', 'name' => 'Main Hall', 'description' => 'Large multipurpose indoor space.', 'image_url' => 'https://visualdisplaysltd.com/application/files/5015/7555/3801/Meeting_-_visual-displays-limited-dnp_LaserPanel_Meetingroom2.jpg'],
        ['id' => 'vol789', 'name' => 'Volleyball Court', 'description' => 'Outdoor hard court with a central net.', 'image_url' => 'https://tgctexas.com/wp-content/uploads/2024/06/IMG_1518-scaled.jpg'],
    ];
    $insert = $pdo->prepare("INSERT INTO venues (id, name, description, image_url) VALUES (?, ?, ?, ?)");
    foreach ($defaultVenues as $v) {
        $insert->execute([$v['id'], $v['name'], $v['description'], $v['image_url']]);
    }
}

// Ensure guest user exists
$guestStmt = $pdo->prepare("SELECT id FROM users WHERE id = '0'");
$guestStmt->execute();
if (!$guestStmt->fetch()) {
    $pdo->prepare("INSERT INTO users (id, name, email) VALUES ('0', 'Guest', '')")->execute();
}

// ==================== HELPER FUNCTIONS ====================
function timeToMinutes($timeStr) {
    $parts = explode(':', $timeStr);
    return (int)$parts[0] * 60 + (int)$parts[1];
}

function isBookingTimeValid($startMinutes, $durationHours) {
    $open = 8 * 60;
    $close = 22 * 60;
    $end = $startMinutes + $durationHours * 60;
    return $startMinutes >= $open && $end <= $close;
}

function calculateEndTime($startTimeStr, $durationHours) {
    $minutes = timeToMinutes($startTimeStr) + $durationHours * 60;
    $minutes = $minutes % 1440;
    return sprintf("%02d:%02d", floor($minutes / 60), $minutes % 60);
}

// ==================== EMAIL SENDING (using configuration) ====================
function sendBookingConfirmation($userId, $studentName, $items) {
    global $pdo, $phpmailerLoaded;
    
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $toEmail = $stmt->fetchColumn();
    if (empty($toEmail)) return false;
    if (!MAIL_ENABLED) return false;
    if (!$phpmailerLoaded) return false;
    
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = MAIL_SECURE;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $studentName);
        if (defined('MAIL_BCC') && MAIL_BCC) {
            $mail->addBCC(MAIL_BCC);
        }
        
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
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("PHPMailer Error: " . $e->getMessage());
        return false;
    }
}

// ==================== AJAX HANDLERS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    // --- Submit review ---
    if ($action === 'submit_review') {
        $name = trim($input['name'] ?? '');
        $rating = (int)($input['rating'] ?? 0);
        $review = trim($input['review'] ?? '');
        if ($name === '' || $rating < 1 || $rating > 5 || $review === '') {
            echo json_encode(['success' => false, 'message' => 'Invalid review data']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO reviews (name, rating, review) VALUES (?, ?, ?)");
        $stmt->execute([$name, $rating, $review]);
        echo json_encode(['success' => true, 'message' => 'Review saved']);
        exit;
    }

    // --- Get all reviews ---
    if ($action === 'get_reviews') {
        $stmt = $pdo->query("SELECT id, name, rating, review, DATE_FORMAT(created_at, '%Y-%m-%d') as date FROM reviews ORDER BY created_at DESC");
        echo json_encode(['success' => true, 'reviews' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // --- Post a comment on a venue ---
    if ($action === 'post_comment') {
        if (empty($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Please log in']);
            exit;
        }
        $venueId = (string)($input['venue_id'] ?? '');
        $comment = trim($input['comment'] ?? '');
        if (empty($venueId) || empty($comment)) {
            echo json_encode(['success' => false, 'message' => 'Comment cannot be empty']);
            exit;
        }
        if (strlen($comment) > 500) {
            echo json_encode(['success' => false, 'message' => 'Comment must be 500 characters or less']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO venue_comments (venue_id, user_id, comment) VALUES (?, ?, ?)");
        $stmt->execute([$venueId, $_SESSION['user_id'], $comment]);
        echo json_encode(['success' => true, 'message' => 'Comment added']);
        exit;
    }

    // --- Delete a comment (own or admin) ---
    if ($action === 'delete_comment') {
        if (empty($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Please log in']);
            exit;
        }
        $commentId = (int)($input['comment_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT user_id FROM venue_comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $commentOwner = $stmt->fetchColumn();
        if ($commentOwner == $_SESSION['user_id'] || $_SESSION['user_id'] == '1') {
            $del = $pdo->prepare("DELETE FROM venue_comments WHERE id = ?");
            $del->execute([$commentId]);
            echo json_encode(['success' => true, 'message' => 'Comment deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        }
        exit;
    }

    // --- Toggle like on a comment ---
    if ($action === 'toggle_like') {
        if (empty($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Please log in']);
            exit;
        }
        $commentId = (int)($input['comment_id'] ?? 0);
        $userId = $_SESSION['user_id'];
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
        exit;
    }

    // --- Get comments for a venue ---
    if ($action === 'get_comments') {
        $venueId = (string)($input['venue_id'] ?? '');
        $currentUserId = (int)$_SESSION['user_id'];
        $sql = "SELECT c.*, u.name as user_name, u.profile_image,
                       (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id) as like_count,
                       EXISTS(SELECT 1 FROM comment_likes WHERE comment_id = c.id AND user_id = ?) as user_liked
                FROM venue_comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.venue_id = ?
                ORDER BY c.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentUserId, $venueId]);
        echo json_encode(['success' => true, 'comments' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // --- Save multiple bookings (cart) ---
    if ($action === 'save_booking') {
        if (empty($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Please log in']);
            exit;
        }
        $userId = (string)$_SESSION['user_id'];
        $studentName = $_SESSION['name'];
        $bookings = $input['bookings'] ?? [];
        if (empty($bookings)) {
            echo json_encode(['success' => false, 'message' => 'No bookings in cart']);
            exit;
        }
        try {
            $pdo->beginTransaction();
            $checkVenue = $pdo->prepare("SELECT id FROM venues WHERE id = ?");
            foreach ($bookings as $b) {
                $checkVenue->execute([(string)$b['venueId']]);
                if (!$checkVenue->fetch()) throw new Exception("Invalid venue ID: {$b['venueId']}");
            }
            $conflictStmt = $pdo->prepare("SELECT start_time, duration_hours FROM bookings WHERE venue_id = ? AND booking_date = ? AND status = 'confirmed'");
            foreach ($bookings as $b) {
                $venueId = (string)$b['venueId'];
                $date = $b['date'];
                $newStartMin = timeToMinutes($b['time']);
                $newEndMin = $newStartMin + ((int)$b['duration']) * 60;
                $conflictStmt->execute([$venueId, $date]);
                foreach ($conflictStmt->fetchAll() as $ex) {
                    $exStart = timeToMinutes($ex['start_time']);
                    $exEnd = $exStart + $ex['duration_hours'] * 60;
                    if ($newStartMin < $exEnd && $newEndMin > $exStart) {
                        throw new Exception("Time conflict on $date with existing booking from {$ex['start_time']} for {$ex['duration_hours']}h.");
                    }
                }
            }
            $insert = $pdo->prepare("INSERT INTO bookings (user_id, student_name, venue_id, booking_date, start_time, duration_hours, comment, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
            foreach ($bookings as $b) {
                $insert->execute([$userId, $studentName, (string)$b['venueId'], $b['date'], $b['time'], (int)$b['duration'], $b['comment'] ?? '']);
            }
            $pdo->commit();

            $items = [];
            foreach ($bookings as $b) {
                $items[] = [
                    'venue' => ubook_venue_display_name($pdo, (string)$b['venueId']),
                    'date' => $b['date'],
                    'time' => $b['time'],
                    'duration' => (int)$b['duration'],
                    'comment' => (string)($b['comment'] ?? '')
                ];
            }
            sendBookingConfirmation($userId, $studentName, $items);
            echo json_encode(['success' => true, 'message' => 'Booking request submitted (pending approval).']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // --- Direct booking from AI chat ---
    if ($action === 'direct_booking') {
        if (empty($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Please log in']);
            exit;
        }
        $userId = (string)$_SESSION['user_id'];
        $studentName = $_SESSION['name'];
        $venueId = (string)($input['venueId'] ?? '');
        $date = $input['date'] ?? '';
        $time = $input['time'] ?? '';
        $duration = (int)($input['duration'] ?? 0);
        $comment = trim($input['comment'] ?? '');
        if (!$venueId || !$date || !$time || $duration < 1) {
            echo json_encode(['success' => false, 'message' => 'Missing booking details.']);
            exit;
        }
        $todayMalaysia = date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < $todayMalaysia) {
            echo json_encode(['success' => false, 'message' => 'Date must be today or future (Malaysia time)']);
            exit;
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            echo json_encode(['success' => false, 'message' => 'Invalid time format']);
            exit;
        }
        $startMin = timeToMinutes($time);
        if (!isBookingTimeValid($startMin, $duration)) {
            echo json_encode(['success' => false, 'message' => 'Booking outside operating hours (08:00–22:00)']);
            exit;
        }
        try {
            $check = $pdo->prepare("SELECT id FROM venues WHERE id = ?");
            $check->execute([$venueId]);
            if (!$check->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Invalid venue.']);
                exit;
            }
            $conflict = $pdo->prepare("SELECT start_time, duration_hours FROM bookings WHERE venue_id = ? AND booking_date = ? AND status = 'confirmed'");
            $conflict->execute([$venueId, $date]);
            $newStart = timeToMinutes($time);
            $newEnd = $newStart + $duration * 60;
            foreach ($conflict->fetchAll() as $ex) {
                $exStart = timeToMinutes($ex['start_time']);
                $exEnd = $exStart + $ex['duration_hours'] * 60;
                if ($newStart < $exEnd && $newEnd > $exStart) {
                    echo json_encode(['success' => false, 'message' => 'Time conflict with existing booking.']);
                    exit;
                }
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
            sendBookingConfirmation($userId, $studentName, $items);
            echo json_encode(['success' => true, 'message' => 'Booking submitted (pending approval).']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

function ubook_venue_display_name(PDO $pdo, string $venueId): string {
    $stmt = $pdo->prepare('SELECT name FROM venues WHERE id = ?');
    $stmt->execute([$venueId]);
    return $stmt->fetchColumn() ?: 'Venue #' . $venueId;
}

// ==================== FETCH DATA FOR PAGE ====================
$allVenues = $pdo->query("SELECT id, name FROM venues ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$popularVenues = $pdo->query("
    SELECT v.id, v.name, v.description, v.image_url, COUNT(b.id) as booking_count
    FROM venues v
    LEFT JOIN bookings b ON v.id = b.venue_id AND b.status = 'confirmed'
    GROUP BY v.id
    ORDER BY booking_count DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);
if (empty($popularVenues)) {
    $popularVenues = $pdo->query("SELECT id, name, description, image_url FROM venues LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>UBook | AI Venue Booking Assistant</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ========== GLOBAL STYLES ========== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --primary: #e67e22; --primary-light: #f39c12; --primary-lighter: #ffb74d; --secondary: #f9f5eb; --accent: #ff9800; --dark: #d35400; --light: #ffffff; --text: #333333; --text-light: #666666; --shadow: 0 4px 20px rgba(0, 0, 0, 0.1); }
        body { font-family: 'Inter', sans-serif; background: var(--secondary); margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; }
        header { background: linear-gradient(135deg, var(--dark), var(--primary), var(--primary-light)); padding: 15px 5%; color: var(--light); display: flex; align-items: center; justify-content: space-between; box-shadow: var(--shadow); position: sticky; top: 0; z-index: 100; }
        .logo { font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .nav-links { display: flex; gap: 25px; }
        .nav-links a { color: var(--light); text-decoration: none; font-weight: 500; padding: 8px 15px; border-radius: 25px; transition: all 0.3s ease; }
        .nav-links a:hover, .nav-links a.active { background: rgba(255, 255, 255, 0.2); }
        .icon-button { width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s; color: var(--light); text-decoration: none; }
        .icon-button:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px); }
        .header-icons { display: flex; gap: 15px; align-items: center; }
        .logout-btn { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.3); padding: 8px 15px; border-radius: 20px; text-decoration: none; color: var(--light); }
        .hero { position: relative; height: 100vh; min-height: 600px; overflow: hidden; display: flex; align-items: center; justify-content: center; color: var(--light); text-align: center; }
        .hero-video { position: absolute; top: 50%; left: 50%; min-width: 100%; min-height: 100%; transform: translate(-50%, -50%); z-index: -1; object-fit: cover; }
        .hero::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.4)); z-index: 0; }
        .hero-content { max-width: 800px; padding: 0 20px; z-index: 1; }
        .hero h1 { font-size: 3.5rem; font-weight: 800; margin-bottom: 20px; }
        .hero p { font-size: 1.4rem; margin-bottom: 30px; }
        .btn { padding: 15px 30px; border-radius: 50px; font-weight: 600; text-decoration: none; font-size: 1.1rem; display: inline-block; cursor: pointer; border: none; }
        .btn-primary { background: linear-gradient(135deg, var(--primary-light), var(--primary-lighter)); color: var(--light); box-shadow: 0 4px 15px rgba(230,126,34,0.4); }
        .menu-section { padding: 80px 5%; background: linear-gradient(to bottom, var(--secondary) 0%, var(--light) 100%); }
        .section-title { text-align: center; margin-bottom: 60px; }
        .section-title h2 { font-size: 2.8rem; color: var(--primary); margin-bottom: 15px; display: inline-block; position: relative; }
        .section-title h2::after { content: ''; position: absolute; bottom: -10px; left: 50%; transform: translateX(-50%); width: 80px; height: 4px; background: var(--primary-lighter); border-radius: 2px; }
        .menu-items { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 35px; margin-bottom: 40px; }
        .menu-item { background: var(--light); border-radius: 20px; overflow: hidden; box-shadow: var(--shadow); transition: all 0.4s ease; }
        .menu-item:hover { transform: translateY(-10px); }
        .menu-image { height: 220px; overflow: hidden; }
        .menu-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .menu-item:hover .menu-image img { transform: scale(1.05); }
        .menu-content { padding: 25px; }
        .menu-item h3 { color: var(--primary); font-size: 1.5rem; }
        .toggle-desc-btn { background: none; border: none; color: var(--primary); font-size: 0.8rem; cursor: pointer; margin: 5px 0; padding: 0; display: inline-block; text-decoration: underline; }
        .venue-desc { color: var(--text-light); font-size: 0.9rem; margin: 10px 0; line-height: 1.5; display: none; }
        .venue-desc.show { display: block; }
        .review-section { padding: 60px 5%; background-color: #f5f9f5; }
        .review-container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        #rating-stars i { font-size: 24px; cursor: pointer; color: #ddd; transition: color 0.2s; }
        .review-card { background: white; border-radius: 20px; padding: 20px; margin-bottom: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); width: 320px; display: inline-block; vertical-align: top; }
        #user-reviews { display: flex; flex-wrap: wrap; justify-content: center; gap: 25px; margin-top: 30px; }
        .about-section { position: relative; height: 100vh; display: flex; justify-content: center; align-items: center; overflow: hidden; text-align: center; color: white; padding: 20px; }
        .bg-video { position: absolute; top: 50%; left: 50%; min-width: 100%; min-height: 100%; transform: translate(-50%, -50%); object-fit: cover; z-index: 0; filter: brightness(0.5); }
        .overlay { position: absolute; inset: 0; background: rgba(0, 0, 0, 0.35); z-index: 1; }
        .about-content { position: relative; z-index: 2; max-width: 800px; }
        .about-content h2 { font-size: 42px; margin-bottom: 15px; text-shadow: 0 3px 10px rgba(0,0,0,0.8); }
        .about-content p { font-size: 16px; line-height: 1.7; text-shadow: 0 2px 8px rgba(0,0,0,0.8); }
        .about-features { display: flex; justify-content: center; gap: 20px; margin-top: 25px; flex-wrap: wrap; }
        .feature { background: rgba(255, 255, 255, 0.12); padding: 15px; border-radius: 12px; min-width: 180px; backdrop-filter: blur(6px); }
        .feature i { font-size: 20px; margin-bottom: 8px; color: #ffcc70; }
        .feature h4 { margin: 5px 0; }
        .feature p { font-size: 13px; color: #eee; }
        footer { background: linear-gradient(135deg, var(--dark), var(--primary)); color: var(--light); padding: 40px 5% 20px; text-align: center; }
        .footer-content { max-width:1200px; margin:0 auto; display:grid; grid-template-columns:repeat(auto-fit, minmax(250px,1fr)); gap:30px; text-align:left; margin-bottom:30px; }
        .footer-links a { display: block; color: var(--light); text-decoration: none; margin-bottom: 10px; }
        
        /* ========== ENHANCED CHAT BOX STYLES ========== */
        .chat-launcher {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 15px 20px;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 999;
            transition: all 0.3s ease;
        }
        .chat-launcher:hover {
            background: var(--primary-light);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
        }
        .chat-popup {
            position: fixed;
            bottom: 100px;
            right: 30px;
            width: 420px;
            max-width: calc(100vw - 40px);
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
            display: none;
            flex-direction: column;
            z-index: 1000;
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .chat-header {
            background: linear-gradient(135deg, var(--dark), var(--primary));
            color: white;
            padding: 15px 20px;
        }
        .chat-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .bot-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            background: #4CAF50;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .close-btn, .chat-clear-btn {
            cursor: pointer;
            transition: all 0.2s;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .close-btn:hover, .chat-clear-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.02);
        }
        .chat-body {
            height: 400px;
            overflow-y: auto;
            padding: 15px;
            background: #f9f9f9;
            display: flex;
            flex-direction: column;
        }
        .bot-message, .user-message {
            position: relative;
            margin-bottom: 12px;
            max-width: 85%;
            word-wrap: break-word;
        }
        .bot-message {
            background: white;
            padding: 12px 15px;
            border-radius: 12px;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .user-message {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 12px 15px;
            border-radius: 12px;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }
        .message-time {
            font-size: 0.65rem;
            opacity: 0.7;
            margin-top: 5px;
            text-align: right;
            display: block;
        }
        .bot-message .message-time {
            color: #888;
            text-align: left;
        }
        .user-message .message-time {
            color: rgba(255,255,255,0.8);
        }
        .quick-reply {
            background: #f1f1f1;
            border: none;
            border-radius: 20px;
            padding: 8px 15px;
            margin: 5px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
        }
        .quick-reply:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }
        .chat-venue-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border: none;
            border-radius: 30px;
            padding: 8px 16px;
            margin: 5px 5px 5px 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: 0.2s;
        }
        .chat-venue-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .typing-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
            background: #e9e9e9;
            padding: 10px 15px;
            border-radius: 20px;
            width: fit-content;
            display: none;
        }
        .typing-dots {
            display: flex;
            gap: 5px;
        }
        .typing-dot {
            width: 8px;
            height: 8px;
            background: #888;
            border-radius: 50%;
            animation: typing 1.4s infinite ease-in-out;
        }
        @keyframes typing {
            0%,60%,100% { transform: translateY(0); }
            30% { transform: translateY(-5px); }
        }
        .chat-footer {
            padding: 15px;
            background: white;
            border-top: 1px solid #eee;
        }
        .chat-input-container {
            display: flex;
            gap: 10px;
        }
        #user-input {
            flex: 1;
            padding: 10px 15px;
            border-radius: 20px;
            border: 1px solid #ddd;
            outline: none;
            font-family: inherit;
        }
        #user-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(230,126,34,0.2);
        }
        .send-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s;
        }
        .send-btn:hover {
            transform: scale(1.05);
            background: var(--dark);
        }
        .toast-msg {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            background: #323232;
            color: white;
            padding: 12px 24px;
            border-radius: 50px;
            z-index: 1100;
            font-size: 0.9rem;
            white-space: nowrap;
        }
        .toast-error {
            background: #c62828;
        }
        
        /* ========== ENHANCED COMMENTS STYLES ========== */
        .comments-section {
            margin-top: 20px;
            border-top: 2px solid #ffe0c4;
            padding-top: 15px;
        }
        .comment-list {
            max-height: 350px;
            overflow-y: auto;
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .comment-card {
            background: #fff8f0;
            border-radius: 18px;
            padding: 12px 15px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .comment-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }
        .comment-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        .comment-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--primary-lighter);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .comment-user-info {
            flex: 1;
        }
        .comment-user-name {
            font-weight: 700;
            color: var(--primary);
            font-size: 0.95rem;
        }
        .comment-time {
            font-size: 0.7rem;
            color: #999;
            margin-left: 5px;
        }
        .comment-text {
            color: var(--text);
            font-size: 0.9rem;
            line-height: 1.4;
            margin: 8px 0 8px 48px;
            word-break: break-word;
        }
        .comment-actions {
            display: flex;
            gap: 15px;
            margin-left: 48px;
            align-items: center;
        }
        .like-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #e67e22;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: 0.2s;
        }
        .like-btn.liked {
            color: #d35400;
            font-weight: bold;
        }
        .like-btn:hover {
            transform: scale(1.1);
        }
        .delete-comment {
            background: none;
            border: none;
            cursor: pointer;
            color: #e74c3c;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
            opacity: 0.6;
            transition: 0.2s;
        }
        .delete-comment:hover {
            opacity: 1;
            transform: scale(1.05);
        }
        .comment-input-area {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 15px;
        }
        .comment-input-wrapper {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .comment-input {
            flex: 1;
            padding: 10px 15px;
            border-radius: 30px;
            border: 1px solid #ffe0c4;
            background: white;
            font-family: inherit;
            resize: vertical;
        }
        .comment-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(230,126,34,0.2);
        }
        .comment-submit {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 30px;
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
        }
        .comment-submit:hover:not(:disabled) {
            background: var(--dark);
            transform: translateY(-1px);
        }
        .comment-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .char-counter {
            font-size: 0.7rem;
            text-align: right;
            color: #999;
            margin-top: 4px;
        }
        .char-counter.warning {
            color: #e67e22;
        }
        .char-counter.danger {
            color: #e74c3c;
        }
        .toggle-comments-btn {
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            border: none;
            color: white;
            padding: 8px 18px;
            border-radius: 40px;
            font-size: 0.85rem;
            cursor: pointer;
            margin-top: 12px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            font-weight: 500;
        }
        .toggle-comments-btn i {
            font-size: 0.9rem;
            transition: transform 0.2s;
        }
        .toggle-comments-btn:hover {
            background: linear-gradient(135deg, var(--primary), var(--dark));
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }
        .toggle-comments-btn:active {
            transform: translateY(0);
        }
        @media (max-width: 480px) {
            .chat-popup { width: 95%; right: 2.5%; bottom: 80px; }
            .hero h1 { font-size: 2rem; }
            .toast-msg { white-space: normal; text-align: center; }
        }
    </style>
</head>
<body>
<header>
    <div class="logo"><i class="fas fa-calendar-check"></i><span>UBook</span></div>
    <div class="nav-links">
        <a href="main.menu.php" class="active">Home</a>
        <a href="venue.php">Venues</a>
        <a href="Community.php">Community</a>
    </div>
    <div class="header-icons">
        <a href="profile.php" class="icon-button"><i class="fas fa-user"></i></a>
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</header>

<section class="hero">
    <video autoplay muted loop playsinline class="hero-video"><source src="UBook.mp4" type="video/mp4"></video>
    <div class="hero-content">
        <h1>UBook Venue Booking</h1>
        <p>Your Perfect Space, Just a Click Away</p>
    </div>
</section>

<section class="menu-section">
    <div class="section-title"><h2>Popular Venues on Campus</h2><p>Hand-picked spaces for study, sports, and events – for students</p></div>
    <div class="menu-items">
        <?php foreach ($popularVenues as $venue): ?>
        <div class="menu-item" data-venue-id="<?= htmlspecialchars($venue['id']) ?>" data-venue-name="<?= htmlspecialchars($venue['name']) ?>">
            <div class="menu-image"><img src="<?= htmlspecialchars($venue['image_url'] ?? 'https://placehold.co/400x200?text=Venue') ?>" alt="<?= htmlspecialchars($venue['name']) ?>"></div>
            <div class="menu-content">
                <h3><?= htmlspecialchars($venue['name']) ?></h3>
                <button class="toggle-desc-btn" data-venue-id="<?= htmlspecialchars($venue['id']) ?>">
                    <i class="fas fa-info-circle"></i> Show description
                </button>
                <div class="venue-desc" id="desc-<?= htmlspecialchars($venue['id']) ?>">
                    <?= htmlspecialchars($venue['description'] ?? 'No description available') ?>
                </div>
                <div class="comments-wrapper" data-venue-id="<?= htmlspecialchars($venue['id']) ?>">
                    <button class="toggle-comments-btn">
                        <i class="fas fa-comment"></i>
                        <span class="btn-text">Show Comments</span>
                    </button>
                    <div class="comments-section" style="display:none;">
                        <div class="comment-list"></div>
                        <div class="comment-input-area">
                            <div class="comment-input-wrapper">
                                <input type="text" placeholder="Write a comment... (max 500 chars)" class="comment-input" maxlength="500">
                                <button class="comment-submit" disabled>Post</button>
                            </div>
                            <div class="char-counter">0 / 500</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div style="text-align:center; margin-top:20px"><a href="venue.php" class="btn btn-primary">Explore more venues</a></div>
</section>

<!-- ABOUT US SECTION -->
<section class="about-section">
    <video class="bg-video" autoplay muted loop playsinline><source src="About.us.mp4" type="video/mp4"></video>
    <div class="overlay"></div>
    <div class="about-content">
        <h2>What is <span style="color:var(--primary-light);">UBook</span>?</h2>
        <p>UBook is the ultimate campus venue booking platform built <strong>by students, for students</strong>.</p>
        <p>Browse, book, and manage venues in seconds with AI support and real-time availability. <strong>All venues are 100% free</strong> for students.</p>
        <div class="about-features">
            <div class="feature"><i class="fas fa-bolt"></i><h4>Lightning Fast</h4><p>Book in under 30 seconds</p></div>
            <div class="feature"><i class="fas fa-robot"></i><h4>AI Assistant</h4><p>Smart booking help</p></div>
            <div class="feature"><i class="fas fa-users"></i><h4>Community Driven</h4><p>Trusted by students</p></div>
        </div>
    </div>
</section>

<!-- REVIEW SECTION -->
<section class="review-section">
    <div class="section-title"><h2>Share Your Venue Experience</h2><p>Help fellow students choose the best spaces</p></div>
    <div class="review-container">
        <form id="review-form" onsubmit="return false;">
            <input type="text" id="reviewer-name" placeholder="Your Name" required style="width:100%; padding:12px; margin-bottom:15px; border-radius:8px; border:1px solid #ddd;">
            <div id="rating-stars" style="margin-bottom:15px">
                <i class="far fa-star" data-rating="1"></i><i class="far fa-star" data-rating="2"></i><i class="far fa-star" data-rating="3"></i><i class="far fa-star" data-rating="4"></i><i class="far fa-star" data-rating="5"></i>
                <span id="rating-text" style="margin-left: 10px;"></span>
            </div>
            <textarea id="review-text" placeholder="Your review..." required style="width:100%; height:100px; padding:12px; border-radius:8px;"></textarea>
            <button type="button" id="submitReviewBtn" style="background:var(--primary); color:white; padding:12px; border:none; border-radius:50px; margin-top:15px; cursor:pointer;">Submit Review</button>
            <div id="review-success" style="display:none; text-align:center; color:green; margin-top:10px;">✅ Thank you! Your review has been saved.</div>
            <div id="review-error-msg" style="display:none; color:red; text-align:center; margin-top:10px;"></div>
        </form>
    </div>
    <div id="user-reviews-container"><h3 style="text-align:center; color:var(--primary); margin-top:40px;">What Students Say</h3><div id="user-reviews"><div class="review-loading"><i class="fas fa-spinner fa-pulse"></i> Loading reviews...</div></div></div>
</section>

<!-- AI Chat Box -->
<div class="chat-launcher" onclick="toggleChat()"><span class="chat-icon">🤖</span><span class="chat-text">AI ChatBox</span></div>
<div class="chat-popup" id="chat-popup">
    <div class="chat-header">
        <div class="chat-header-content">
            <div class="bot-info">
                <div class="chat-logo"><i class="fas fa-robot"></i></div>
                <div>
                    <strong>AI Chat Box</strong>
                    <div class="status"><span class="status-dot"></span> Ready to help you book</div>
                </div>
            </div>
            <div style="display: flex; gap: 8px;">
                <button class="chat-clear-btn" id="clearChatBtn"><i class="fas fa-trash-alt"></i> Clear</button>
                <div class="close-btn" onclick="toggleChat()"><i class="fas fa-times"></i></div>
            </div>
        </div>
    </div>
    <div class="chat-body" id="chat-body">
        <div id="quick-replies-area" style="margin-top: 10px;"></div>
        <div class="typing-indicator" id="typing-indicator">
            <div class="typing-dots">
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
            </div>
            <span>AI is thinking...</span>
        </div>
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
        <div class="footer-column"><h3>UBook Venue System</h3><p>The official venue booking platform for students – fast & reliable.</p><div class="social-links"><a href="#" class="icon-button"><i class="fab fa-instagram"></i></a><a href="#" class="icon-button"><i class="fab fa-facebook-f"></i></a></div></div>
        <div class="footer-column"><h3>Quick Links</h3><div class="footer-links"><a href="main.menu.php">Home</a><a href="venue.php">Venues</a><a href="Community.php">Community</a></div></div>
        <div class="footer-column"><h3>Contact Support</h3><div class="footer-links"><a href="#"><i class="fas fa-phone"></i> +60 12-345 6789</a><a href="#"><i class="fas fa-envelope"></i> booking@UBook.com</a></div></div>
    </div>
    <div class="copyright"><p>&copy; 2026 UBook – Venue Booking System. AI Assistant powered by DeepSeek</p></div>
</footer>

<script>
// ==================== GLOBAL VARIABLES ====================
let currentRating = 0;
let hoverRating = 0;
let chatMessages = [];
let isDeepSeekReady = false;
let shouldUseFallbackOnly = false;
let activeBooking = null;
let venuesList = [];
const API_KEY = "";
const DEEPSEEK_API_URL = "https://api.deepseek.com/chat/completions";
const STORAGE_KEY = 'ubook_chat_history';

// ==================== MALAYSIA TIME UTILITIES ====================
function getMalaysiaDate() {
    const now = new Date();
    const malaysiaTime = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Kuala_Lumpur',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    }).format(now);
    return malaysiaTime; // returns YYYY-MM-DD
}
function getMalaysiaDateTime() {
    return new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Asia/Kuala_Lumpur',
        hour12: false,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    }).format(new Date());
}
function addMalaysiaDays(days) {
    const msPerDay = 86400000;
    const now = new Date();
    // Get current Malaysia time as a timestamp in UTC (we'll adjust)
    const malaysiaOffset = 8 * 60; // minutes
    const localOffset = now.getTimezoneOffset();
    const offsetDiff = (malaysiaOffset + localOffset) * 60000;
    const malaysiaMs = now.getTime() + offsetDiff;
    const targetMs = malaysiaMs + days * msPerDay;
    const targetDate = new Date(targetMs);
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Kuala_Lumpur',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    }).format(targetDate);
}
function todayISO() {
    return getMalaysiaDate();
}
function addDaysFromToday(days) {
    return addMalaysiaDays(days);
}
function getMalaysiaTime() {
    return getMalaysiaDateTime();
}

// ==================== HELPER FUNCTIONS ====================
function escapeHtml(str) { if(!str) return ''; return str.replace(/[&<>]/g, function(m){ if(m==='&') return '&amp;'; if(m==='<') return '&lt;'; if(m==='>') return '&gt;'; return m;}); }
function showToast(msg, duration = 3000, isError = false) { let toast = document.querySelector('.toast-msg'); if (toast) toast.remove(); let div = document.createElement('div'); div.className = 'toast-msg' + (isError ? ' toast-error' : ''); div.innerText = msg; document.body.appendChild(div); setTimeout(() => div.remove(), duration); }
function saveChatHistory() { try { localStorage.setItem(STORAGE_KEY, JSON.stringify(chatMessages)); } catch(e) {} }
function loadChatHistory() { try { const saved = localStorage.getItem(STORAGE_KEY); if (saved) { const parsed = JSON.parse(saved); if (Array.isArray(parsed) && parsed.length > 0) { chatMessages = parsed; return true; } } } catch(e) {} return false; }
function clearChatHistory() { localStorage.removeItem(STORAGE_KEY); chatMessages = []; resetChatMessages(); }
function resetChatMessages() { chatMessages = [{ role: "system", content: buildSystemPrompt() }]; saveChatHistory(); }
function buildSystemPrompt() { 
    const vlist = venuesList.length ? venuesList.map(v => `${v.id}: ${v.name}`).join(' | ') : '(loading venues)'; 
    return `You are UBook AI Assistant for campus venue booking. All venues are free. Operating hours: 08:00–22:00. Duration per booking: 1–4 hours. Available venues (id: name): ${vlist}. Help users book by collecting: (1) venue name, (2) date YYYY-MM-DD or today/tomorrow, (3) start time HH:MM or like 2pm, (4) duration 1–4. Keep answers short.`; 
}
function matchVenueByText(text) { if (!text || !venuesList.length) return null; const lower = text.toLowerCase(); const sorted = [...venuesList].sort((a,b)=>b.name.length-a.name.length); for (const v of sorted) if (lower.includes(v.name.toLowerCase())) return v; return null; }
function parseTimeFromText(text) { const lower = text.toLowerCase(); let m = lower.match(/\b([01]?\d|2[0-3]):([0-5]\d)\b/); if (m) return `${String(Math.min(23,parseInt(m[1],10))).padStart(2,'0')}:${m[2]}`; m = lower.match(/\b([1-9]|1[0-2])(?::([0-5]\d))?\s*(am|pm)\b/); if (m) { let h = parseInt(m[1],10); const mins = m[2] ? parseInt(m[2],10) : 0; const ap = m[3]; if (ap === 'pm' && h < 12) h += 12; if (ap === 'am' && h === 12) h = 0; return `${String(h).padStart(2,'0')}:${String(mins).padStart(2,'0')}`; } return null; }
function parseDurationFromText(text) { const lower = text.toLowerCase(); let m = lower.match(/(\d+)\s*(hour|hours|hrs|h)\b/); if (m) { const n = parseInt(m[1],10); return (n>=1 && n<=4) ? n : null; } return null; }
function parseDateFromText(text) { const lower = text.trim().toLowerCase(); if (/\btoday\b/.test(lower)) return todayISO(); if (/\btomorrow\b/.test(lower)) return addDaysFromToday(1); const iso = text.match(/\d{4}-\d{2}-\d{2}/); if (iso && iso[0] >= todayISO()) return iso[0]; return null; }
function tryParseOneShotBooking(message) { const venue = matchVenueByText(message); if (!venue) return null; const date = parseDateFromText(message); const time = parseTimeFromText(message); const duration = parseDurationFromText(message) || 2; if (!date || !time) return null; return { venueId: venue.id, venueName: venue.name, date, time, duration }; }
function scrollToBottom() { const body = document.getElementById('chat-body'); setTimeout(() => body.scrollTop = body.scrollHeight, 50); }
function addBotMessage(text, isHtml = false) {
    const chatBody = document.getElementById('chat-body');
    const msgDiv = document.createElement('div');
    msgDiv.classList.add('bot-message');
    if (isHtml) msgDiv.innerHTML = text;
    else msgDiv.textContent = text;
    const timeSpan = document.createElement('div');
    timeSpan.className = 'message-time';
    timeSpan.textContent = getMalaysiaTime();
    msgDiv.appendChild(timeSpan);
    chatBody.appendChild(msgDiv);
    scrollToBottom();
    saveChatHistory();
}
function addUserMessage(text) {
    const chatBody = document.getElementById('chat-body');
    const msgDiv = document.createElement('div');
    msgDiv.classList.add('user-message');
    msgDiv.textContent = text;
    const timeSpan = document.createElement('div');
    timeSpan.className = 'message-time';
    timeSpan.textContent = getMalaysiaTime();
    msgDiv.appendChild(timeSpan);
    chatBody.appendChild(msgDiv);
    scrollToBottom();
    saveChatHistory();
}
function showTyping() { document.getElementById('typing-indicator').style.display = 'flex'; scrollToBottom(); }
function hideTyping() { document.getElementById('typing-indicator').style.display = 'none'; }

// ==================== DEEPSEEK API ====================
async function initDeepSeek() { if (!loadChatHistory() || chatMessages.length === 0) resetChatMessages(); isDeepSeekReady = Boolean(API_KEY && API_KEY.length > 10); shouldUseFallbackOnly = false; }
async function callDeepSeek(message) { const messages = [...chatMessages, { role: "user", content: message }]; const response = await fetch(DEEPSEEK_API_URL, { method: "POST", headers: { "Content-Type": "application/json", "Authorization": `Bearer ${API_KEY}` }, body: JSON.stringify({ model: "deepseek-chat", messages, temperature: 0.7, max_tokens: 600 }) }); if (!response.ok) throw new Error("API error"); const data = await response.json(); const reply = data.choices[0].message.content.trim(); chatMessages = [...messages, { role: "assistant", content: reply }]; saveChatHistory(); return reply; }
function getFallbackReply(question) { const lower = (question || "").toLowerCase(); if (lower.includes("book")) return "Say \"list venues\" or \"book Main Hall tomorrow 2pm 2 hours\"."; if (lower.includes("list venues")) return "Venues: " + venuesList.map(v => v.name).join(", ") + ". Tap a button to book."; return "Try: list venues or book [venue name]."; }
async function getAssistantReply(message) { if (!isDeepSeekReady || shouldUseFallbackOnly) return getFallbackReply(message); try { return await callDeepSeek(message); } catch(err) { shouldUseFallbackOnly = true; return getFallbackReply(message); } }

// ==================== BOOKING FLOW ====================
async function submitDirectBooking(booking) { 
    try { 
        const response = await fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'direct_booking', venueId: booking.venueId, date: booking.date, time: booking.time, duration: booking.duration, comment: booking.comment || '' }) }); 
        const result = await response.json(); 
        if (result.success) { showToast("✅ " + result.message, 2500); addBotMessage(`🎉 Submitted (pending approval): ${booking.date} ${booking.time} · ${booking.duration}h.`); return true; } 
        showToast("❌ " + result.message, 5000, true); addBotMessage("Could not book: " + result.message); return false; 
    } catch (error) { showToast("❌ Network error", 3000, true); addBotMessage("Network error — please try again."); return false; } 
}
function showBookingConfirmActions() { const quickArea = document.getElementById('quick-replies-area'); quickArea.innerHTML = '<div style="margin:6px 0;">Ready to submit?</div>'; const ok = document.createElement('button'); ok.className = 'quick-reply'; ok.textContent = '✅ Confirm submit'; ok.onclick = async () => { quickArea.innerHTML = ''; await finalizeBookingFromFlow(); showInitialQuickReplies(); }; const cancel = document.createElement('button'); cancel.className = 'quick-reply'; cancel.textContent = '❌ Cancel'; cancel.onclick = () => { quickArea.innerHTML = ''; resetActiveBooking(); addBotMessage('Cancelled.'); showInitialQuickReplies(); }; quickArea.appendChild(ok); quickArea.appendChild(cancel); }
function resetActiveBooking() { activeBooking = null; }
function startBookingFlow(venue) { if (!venue || !venue.id) { addBotMessage("Sorry, that venue is not available."); return; } activeBooking = { step: 'date', venue: venue, date: null, time: null, duration: null, comment: '' }; addBotMessage(`Let's book ${venue.name}. Send a date: YYYY-MM-DD, or "today" / "tomorrow".`); }
function showTimeQuickPicks() { const quickArea = document.getElementById('quick-replies-area'); quickArea.innerHTML = '<div>Common start times:</div>'; ['09:00','12:00','14:00','18:00'].forEach(t => { const b = document.createElement('button'); b.className = 'quick-reply'; b.textContent = t; b.onclick = () => { quickArea.innerHTML = ''; sendUserMessage(t); }; quickArea.appendChild(b); }); }
async function finalizeBookingFromFlow() { if (!activeBooking) return false; const { venue, date, time, duration, comment } = activeBooking; if (!venue || !date || !time || !duration) { addBotMessage("Missing info. Start over with 'list venues'."); resetActiveBooking(); return false; } addBotMessage(`⏳ Submitting ${venue.name} · ${date} ${time} · ${duration}h…`); const ok = await submitDirectBooking({ venueId: venue.id, date, time, duration, comment: comment || '' }); resetActiveBooking(); return ok; }
async function processBookingStep(userMessage) { if (!activeBooking) return false; const lowerMsg = userMessage.toLowerCase().trim(); if (lowerMsg === 'cancel') { addBotMessage("Booking cancelled."); resetActiveBooking(); showInitialQuickReplies(); return true; } const step = activeBooking.step; if (step === 'confirm') { if (/^(yes|y|confirm|ok)$/i.test(userMessage.trim())) { await finalizeBookingFromFlow(); showInitialQuickReplies(); return true; } addBotMessage('Tap Confirm submit or type CONFIRM / CANCEL.'); return true; } if (step === 'date') { let date = userMessage.trim(); if (lowerMsg === 'today') date = todayISO(); else if (lowerMsg === 'tomorrow') date = addDaysFromToday(1); else if (parseDateFromText(userMessage)) date = parseDateFromText(userMessage); const today = todayISO(); if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || date < today) { addBotMessage(`Please send a valid date on or after ${today} (YYYY-MM-DD), or "today"/"tomorrow".`); return true; } activeBooking.date = date; activeBooking.step = 'time'; addBotMessage(`Start time? Use 24h HH:MM (e.g. 14:00) or 2pm.`); showTimeQuickPicks(); return true; } if (step === 'time') { let time = userMessage.trim(); const parsed = parseTimeFromText(userMessage); if (parsed) time = parsed; if (!/^([01]\d|2[0-3]):([0-5]\d)$/.test(time)) { addBotMessage("Invalid time. Use 14:00 or 2pm."); return true; } activeBooking.time = time; activeBooking.step = 'duration'; document.getElementById('quick-replies-area').innerHTML = ''; addBotMessage(`Duration in hours: 1, 2, 3, or 4?`); const quickArea = document.getElementById('quick-replies-area'); [1,2,3,4].forEach(d => { const btn = document.createElement('button'); btn.className = 'quick-reply'; btn.textContent = `${d} h`; btn.onclick = () => { sendUserMessage(d.toString()); }; quickArea.appendChild(btn); }); return true; } if (step === 'duration') { let dur = parseInt(userMessage.trim(), 10); if (isNaN(dur) || dur < 1 || dur > 4) { addBotMessage("Pick 1–4 hours (number only)."); return true; } activeBooking.duration = dur; activeBooking.step = 'confirm'; document.getElementById('quick-replies-area').innerHTML = ''; addBotMessage(`Please review:\n📌 ${activeBooking.venue.name}\n📅 ${activeBooking.date}  🕒 ${activeBooking.time}\n⏱️ ${dur} hour(s)\n\nSubmit now?`); showBookingConfirmActions(); return true; } return false; }

// ==================== CHAT UI ====================
function clearChat() {
    clearChatHistory();
    const chatBody = document.getElementById('chat-body');
    const messages = chatBody.querySelectorAll('.bot-message, .user-message');
    messages.forEach(m => m.remove());
    document.getElementById('quick-replies-area').innerHTML = '';
    addBotMessage("Chat cleared. How can I help you?");
    showInitialQuickReplies();
    resetActiveBooking();
}
function showQuickReplies(buttons) { const quickArea = document.getElementById('quick-replies-area'); quickArea.innerHTML = '<div style="margin: 5px 0;">✨ Quick actions:</div>'; buttons.forEach(btn => { const btnEl = document.createElement('button'); btnEl.className = 'quick-reply'; btnEl.textContent = btn.label; btnEl.onclick = () => { quickArea.innerHTML = ''; sendUserMessage(btn.value); }; quickArea.appendChild(btnEl); }); }
function showVenueButtons() { const quickArea = document.getElementById('quick-replies-area'); quickArea.innerHTML = '<div>🏛️ Select a venue to book:</div>'; venuesList.forEach(venue => { const btn = document.createElement('button'); btn.className = 'chat-venue-btn'; btn.textContent = venue.name; btn.onclick = () => { quickArea.innerHTML = ''; startBookingFlow(venue); }; quickArea.appendChild(btn); }); const backBtn = document.createElement('button'); backBtn.className = 'quick-reply'; backBtn.textContent = '« Back'; backBtn.onclick = () => { showInitialQuickReplies(); }; quickArea.appendChild(backBtn); }
function showInitialQuickReplies() { showQuickReplies([ { label: '📋 List Venues', value: 'list venues' }, { label: '📖 How to book?', value: 'How do I book a venue?' }, { label: '⏱️ Duration info', value: 'What is the duration?' }, { label: '❌ Cancel policy', value: 'Cancelation policy?' }, { label: '💰 Zero cost?', value: 'Is there any cost?' } ]); }
async function handleUserMessage(message) { if (!message.trim()) return; addUserMessage(message); if (activeBooking && await processBookingStep(message)) return; const oneShot = tryParseOneShotBooking(message); if (oneShot && !activeBooking) { activeBooking = { step: 'confirm', venue: { id: oneShot.venueId, name: oneShot.venueName }, date: oneShot.date, time: oneShot.time, duration: oneShot.duration, comment: '' }; addBotMessage(`I parsed: ${oneShot.venueName} on ${oneShot.date} at ${oneShot.time} for ${oneShot.duration} hour(s). Submit now?`); showBookingConfirmActions(); return; } const lowerMsg = message.toLowerCase(); if (lowerMsg.includes('list venues')) { let venueNames = venuesList.map(v => v.name).join(', '); addBotMessage(`We have ${venuesList.length} venues: ${venueNames}. Pick one on the top of chat.`); showVenueButtons(); return; } const intentVenue = matchVenueByText(message); if (intentVenue && /book|reserve|schedule/i.test(message)) { startBookingFlow(intentVenue); return; } if (lowerMsg.includes('how to book') || lowerMsg === 'help') { addBotMessage(`Quick ways to book:\n1) Say "book Main Hall" then follow date → time → duration.\n2) One line: "book Seminar Room A tomorrow 14:00 2 hours".\n3) Use buttons below. All free; cancel up to 24h before.`); showInitialQuickReplies(); return; } showTyping(); let reply = await getAssistantReply(message); hideTyping(); addBotMessage(reply); setTimeout(() => { if (document.getElementById('quick-replies-area').children.length === 0 && !activeBooking) showInitialQuickReplies(); }, 500); }
function sendUserMessage(predefinedMsg = null) { const input = document.getElementById('user-input'); const message = predefinedMsg !== null ? predefinedMsg : input.value.trim(); if (!message) return; if (predefinedMsg === null) input.value = ''; handleUserMessage(message).catch(console.warn); }
function toggleChat() { 
    const popup = document.getElementById('chat-popup'); 
    if (popup.style.display === 'flex') { 
        popup.style.display = 'none'; 
    } else { 
        popup.style.display = 'flex'; 
        if (chatMessages.length === 0 || (chatMessages.length === 1 && chatMessages[0].role === 'system')) { 
            initDeepSeek(); 
            const hasWelcome = Array.from(document.querySelectorAll('.bot-message')).some(el => el.textContent.includes("Hi!")); 
            if (!hasWelcome) { 
                addBotMessage("👋 Hi! I can guide you to book venues. Try \"list venues\" or a one-liner like: book Main Hall tomorrow 3pm 2 hours."); 
                showInitialQuickReplies(); 
            } 
        } else { 
            renderChatHistoryFromMessages(); 
        } 
    } 
}
function renderChatHistoryFromMessages() { 
    const chatBody = document.getElementById('chat-body'); 
    const existing = chatBody.querySelectorAll('.bot-message, .user-message'); 
    existing.forEach(m => m.remove()); 
    for (let msg of chatMessages) { 
        if (msg.role === 'system') continue; 
        const div = document.createElement('div'); 
        div.classList.add(msg.role === 'user' ? 'user-message' : 'bot-message'); 
        div.textContent = msg.content; 
        const timeSpan = document.createElement('div');
        timeSpan.className = 'message-time';
        timeSpan.textContent = getMalaysiaTime();
        div.appendChild(timeSpan);
        chatBody.appendChild(div); 
    } 
    scrollToBottom(); 
}

// ==================== REVIEWS ====================
async function renderReviews() {
    const container = document.getElementById('user-reviews');
    if (!container) return;
    container.innerHTML = '<div class="review-loading">Loading reviews...</div>';
    try {
        const response = await fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'get_reviews' }) });
        const data = await response.json();
        if (!data.success) throw new Error(data.message);
        container.innerHTML = '';
        if (data.reviews.length === 0) { container.innerHTML = '<div>✨ No reviews yet. Be the first!</div>'; return; }
        data.reviews.forEach(review => {
            const card = document.createElement('div'); card.className = 'review-card';
            let stars = ''; let full = Math.floor(review.rating); let half = (review.rating % 1) !== 0;
            for (let i = 1; i <= 5; i++) {
                if (i <= full) stars += '<i class="fas fa-star"></i>';
                else if (i === full + 1 && half) stars += '<i class="fas fa-star-half-alt"></i>';
                else stars += '<i class="far fa-star"></i>';
            }
            card.innerHTML = `<div class="review-header"><span class="reviewer-name">${escapeHtml(review.name)}</span><span class="review-date">${escapeHtml(review.date)}</span></div><div class="review-stars">${stars}</div><div class="review-text">"${escapeHtml(review.review)}"</div>`;
            container.appendChild(card);
        });
    } catch (err) { container.innerHTML = '<div class="review-error">⚠️ Could not load reviews.</div>'; console.error(err); }
}
async function submitReview() {
    const name = document.getElementById('reviewer-name').value.trim();
    const reviewText = document.getElementById('review-text').value.trim();
    const rating = currentRating;
    const successDiv = document.getElementById('review-success');
    const errorDiv = document.getElementById('review-error-msg');
    successDiv.style.display = 'none'; errorDiv.style.display = 'none';
    if (!name) { errorDiv.innerText = 'Please enter your name.'; errorDiv.style.display = 'block'; return; }
    if (rating === 0) { errorDiv.innerText = 'Select a rating.'; errorDiv.style.display = 'block'; return; }
    if (!reviewText) { errorDiv.innerText = 'Write your review.'; errorDiv.style.display = 'block'; return; }
    try {
        const response = await fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'submit_review', name, rating, review: reviewText }) });
        const data = await response.json();
        if (data.success) {
            document.getElementById('reviewer-name').value = '';
            document.getElementById('review-text').value = '';
            setRating(0);
            successDiv.style.display = 'block';
            setTimeout(() => successDiv.style.display = 'none', 3000);
            renderReviews();
        } else { errorDiv.innerText = data.message || 'Failed to submit.'; errorDiv.style.display = 'block'; }
    } catch (err) { errorDiv.innerText = 'Network error.'; errorDiv.style.display = 'block'; }
}
function updateStars() { const stars = document.querySelectorAll('#rating-stars i'); const ratingToShow = hoverRating || currentRating; stars.forEach(star => { const val = parseInt(star.getAttribute('data-rating')); if (val <= ratingToShow) { star.className = 'fas fa-star'; star.style.color = '#FFC107'; } else { star.className = 'far fa-star'; star.style.color = '#ddd'; } }); }
function setRating(rating) { currentRating = rating; updateStars(); const texts = ['','Poor','Fair','Good','Very Good','Excellent']; document.getElementById('rating-text').innerText = texts[rating] || ''; }
function hoverStar(rating) { hoverRating = rating; updateStars(); }
function resetStars() { hoverRating = 0; updateStars(); }

// ==================== ENHANCED COMMENTS & LIKES ====================
function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    if (seconds < 60) return 'just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    const days = Math.floor(hours / 24);
    if (days === 1) return 'yesterday';
    if (days < 7) return `${days} days ago`;
    return date.toLocaleDateString();
}

async function loadComments(venueId, wrapper) { 
    const commentList = wrapper.querySelector('.comment-list'); 
    commentList.innerHTML = '<div class="loading-comments" style="text-align:center;padding:10px;"><i class="fas fa-spinner fa-pulse"></i> Loading comments...</div>'; 
    try { 
        const resp = await fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'get_comments', venue_id: venueId }) }); 
        const data = await resp.json(); 
        if (data.success) {
            renderComments(commentList, data.comments, venueId, wrapper);
        } else {
            commentList.innerHTML = '<div style="text-align:center;padding:10px;color:#999;">Failed to load comments.</div>'; 
        }
    } catch (err) { 
        console.error("Error loading comments:", err);
        commentList.innerHTML = '<div style="text-align:center;padding:10px;color:#999;">Error loading comments.</div>'; 
    } 
}

function renderComments(container, comments, venueId, wrapper) { 
    if (!comments.length) { 
        container.innerHTML = '<div style="text-align:center;padding:15px;color:#999;">💬 No comments yet. Be the first to share your thoughts!</div>'; 
        return; 
    } 
    const currentUserId = <?php echo json_encode($_SESSION['user_id']); ?>;
    container.innerHTML = comments.map(c => {
        const avatarUrl = c.profile_image ? escapeHtml(c.profile_image) : 'https://ui-avatars.com/api/?background=e67e22&color=fff&name=' + encodeURIComponent(c.user_name);
        const isOwner = (c.user_id == currentUserId);
        const timeText = timeAgo(c.created_at);
        return `
            <div class="comment-card" data-comment-id="${c.id}">
                <div class="comment-header">
                    <img src="${avatarUrl}" class="comment-avatar" onerror="this.src='https://ui-avatars.com/api/?background=e67e22&color=fff&name=${escapeHtml(c.user_name)}'">
                    <div class="comment-user-info">
                        <span class="comment-user-name">${escapeHtml(c.user_name)}</span>
                        <span class="comment-time">${timeText}</span>
                    </div>
                </div>
                <div class="comment-text">${escapeHtml(c.comment)}</div>
                <div class="comment-actions">
                    <button class="like-btn ${c.user_liked ? 'liked' : ''}" data-comment-id="${c.id}">
                        <i class="fas fa-thumbs-up"></i> <span class="like-count">${c.like_count}</span>
                    </button>
                    ${isOwner ? `<button class="delete-comment" data-comment-id="${c.id}"><i class="fas fa-trash-alt"></i> Delete</button>` : ''}
                </div>
            </div>
        `;
    }).join(''); 
}

async function postComment(venueId, inputEl, wrapper) { 
    let comment = inputEl.value.trim(); 
    if (!comment) return; 
    if (comment.length > 500) { showToast('Comment too long (max 500 characters)', 3000, true); return; }
    inputEl.disabled = true; 
    const submitBtn = wrapper.querySelector('.comment-submit');
    submitBtn.disabled = true;
    try { 
        const resp = await fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'post_comment', venue_id: venueId, comment: comment }) }); 
        const data = await resp.json(); 
        if (data.success) { 
            inputEl.value = ''; 
            updateCharCounter(inputEl);
            await loadComments(venueId, wrapper); 
        } else { 
            showToast(data.message, 2000, true); 
        } 
    } catch (err) { 
        showToast('Error posting comment', 2000, true); 
    } finally { 
        inputEl.disabled = false; 
        submitBtn.disabled = !inputEl.value.trim() || inputEl.value.length > 500;
    } 
}

function updateCharCounter(inputEl) {
    const wrapper = inputEl.closest('.comments-wrapper');
    const counterDiv = wrapper.querySelector('.char-counter');
    if (!counterDiv) return;
    const len = inputEl.value.length;
    const max = 500;
    counterDiv.textContent = `${len} / ${max}`;
    if (len > max) {
        counterDiv.classList.add('danger');
    } else if (len > max * 0.8) {
        counterDiv.classList.add('warning');
        counterDiv.classList.remove('danger');
    } else {
        counterDiv.classList.remove('warning', 'danger');
    }
    const submitBtn = wrapper.querySelector('.comment-submit');
    if (submitBtn) submitBtn.disabled = (len === 0 || len > max);
}

function initCommentSections() { 
    document.querySelectorAll('.comments-wrapper').forEach(wrapper => { 
        const venueId = wrapper.dataset.venueId; 
        const toggleBtn = wrapper.querySelector('.toggle-comments-btn'); 
        const btnTextSpan = toggleBtn.querySelector('.btn-text');
        const btnIcon = toggleBtn.querySelector('i');
        const commentsSection = wrapper.querySelector('.comments-section'); 
        const commentInput = wrapper.querySelector('.comment-input'); 
        const postBtn = wrapper.querySelector('.comment-submit'); 
        const commentList = wrapper.querySelector('.comment-list');
        
        if (commentInput) {
            commentInput.addEventListener('input', () => updateCharCounter(commentInput));
            updateCharCounter(commentInput);
        }
        
        toggleBtn.addEventListener('click', async () => { 
            if (commentsSection.style.display === 'none') { 
                commentsSection.style.display = 'block'; 
                btnTextSpan.textContent = 'Hide Comments';
                btnIcon.className = 'fas fa-comment-dots';
                if (commentList.innerHTML === '' || commentList.innerHTML.includes('Loading')) { 
                    await loadComments(venueId, wrapper); 
                } 
            } else { 
                commentsSection.style.display = 'none'; 
                btnTextSpan.textContent = 'Show Comments';
                btnIcon.className = 'fas fa-comment';
            } 
        }); 
        
        postBtn.addEventListener('click', () => postComment(venueId, commentInput, wrapper)); 
        if (commentInput) {
            commentInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') postComment(venueId, commentInput, wrapper); });
        }
    }); 
}

// ==================== DESCRIPTION TOGGLE ====================
function initDescriptionToggles() {
    document.querySelectorAll('.toggle-desc-btn').forEach(btn => {
        const venueId = btn.dataset.venueId;
        const descElement = document.getElementById(`desc-${venueId}`);
        if (!descElement) return;
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (descElement.classList.contains('show')) {
                descElement.classList.remove('show');
                btn.innerHTML = '<i class="fas fa-info-circle"></i> Show description';
            } else {
                descElement.classList.add('show');
                btn.innerHTML = '<i class="fas fa-info-circle"></i> Hide description';
            }
        });
    });
}

// ==================== LIKE BUTTON HANDLER ====================
async function handleLikeClick(likeBtn) {
    let commentId = likeBtn.getAttribute('data-comment-id');
    if (!commentId) {
        const card = likeBtn.closest('.comment-card');
        if (card) commentId = card.getAttribute('data-comment-id');
    }
    if (!commentId) {
        showToast('Unable to identify comment', 2000, true);
        return;
    }
    try {
        const resp = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_like', comment_id: commentId })
        });
        const data = await resp.json();
        if (data.success) {
            const span = likeBtn.querySelector('.like-count');
            if (span) span.textContent = data.like_count;
            if (data.liked) {
                likeBtn.classList.add('liked');
            } else {
                likeBtn.classList.remove('liked');
            }
        } else {
            showToast(data.message || 'Error toggling like', 3000, true);
        }
    } catch (err) {
        showToast('Network error. Please try again.', 3000, true);
    }
}

async function handleDeleteClick(deleteBtn) {
    if (!confirm('Delete this comment?')) return;
    let commentId = deleteBtn.getAttribute('data-comment-id');
    if (!commentId) {
        const card = deleteBtn.closest('.comment-card');
        if (card) commentId = card.getAttribute('data-comment-id');
    }
    if (!commentId) return;
    try {
        const resp = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_comment', comment_id: commentId })
        });
        const data = await resp.json();
        if (data.success) {
            showToast('Comment deleted', 2000);
            const commentCard = deleteBtn.closest('.comment-card');
            if (commentCard) {
                const wrapper = commentCard.closest('.comments-wrapper');
                const venueId = wrapper ? wrapper.dataset.venueId : null;
                if (venueId) await loadComments(venueId, wrapper);
            }
        } else {
            showToast(data.message || 'Error deleting comment', 3000, true);
        }
    } catch (err) {
        showToast('Network error', 3000, true);
    }
}

// ==================== INITIALIZATION ====================
document.addEventListener('DOMContentLoaded', async function() {
    const stars = document.querySelectorAll('#rating-stars i');
    stars.forEach(star => { star.onmouseover = () => hoverStar(parseInt(star.getAttribute('data-rating'))); star.onmouseout = resetStars; star.onclick = () => setRating(parseInt(star.getAttribute('data-rating'))); });
    document.getElementById('submitReviewBtn').addEventListener('click', submitReview);
    await renderReviews();
    
    venuesList = <?php echo json_encode($allVenues); ?>;
    if (!venuesList.length) venuesList = [{ id: 'exm456', name: 'Grand Exam Hall' }, { id: 'vol789', name: 'Volleyball Court' }];
    initCommentSections();
    initDescriptionToggles();
    document.getElementById('user-input').addEventListener('keypress', (e) => { if(e.key === 'Enter') sendUserMessage(); });
    
    document.body.addEventListener('click', async function(e) {
        const likeBtn = e.target.closest('.like-btn');
        if (likeBtn) {
            e.preventDefault();
            e.stopPropagation();
            await handleLikeClick(likeBtn);
            return;
        }
        const deleteBtn = e.target.closest('.delete-comment');
        if (deleteBtn) {
            e.preventDefault();
            e.stopPropagation();
            await handleDeleteClick(deleteBtn);
            return;
        }
    });
    
    const clearChatBtn = document.getElementById('clearChatBtn');
    if (clearChatBtn) clearChatBtn.addEventListener('click', (e) => { e.preventDefault(); clearChat(); });
    
    loadChatHistory();
    if (chatMessages.length === 0) resetChatMessages();
});
</script>
</body>
</html>