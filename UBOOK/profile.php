<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
session_start();

// ==================== LOGIN CHECK ====================
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

// ==================== DATABASE CONNECTION (PDO) ====================
$host = 'localhost';
$dbname = 'ubook';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];

// ==================== FETCH USER INFO ====================
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Invalid user – force logout
    session_destroy();
    header("Location: login.html");
    exit();
}

// ==================== UPDATE PROFILE ====================
$success = $error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $profile_image = $user['profile_image'] ?? '';

    // Image upload handling
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            if (!is_dir("uploads")) {
                mkdir("uploads", 0777, true);
            }

            $new_name = "profile_" . $user_id . "_" . time() . "." . $ext;
            $target = "uploads/" . $new_name;

            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target)) {
                // Delete old image if exists
                if (!empty($user['profile_image']) && file_exists($user['profile_image'])) {
                    unlink($user['profile_image']);
                }
                $profile_image = $target;
            }
        }
    }

    try {
        $updateStmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, profile_image = ? WHERE id = ?");
        $updateStmt->execute([$name, $email, $phone, $profile_image, $user_id]);

        $_SESSION['name'] = $name;
        $success = "Profile updated successfully.";

        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Failed to update profile: " . $e->getMessage();
    }
}

// ==================== FETCH BOOKINGS WITH VENUE DETAILS ====================
// Assumes bookings table has user_id column (added via ALTER TABLE)
$bookings = [];

try {
    $sql = "SELECT b.id, b.booking_date, b.start_time, b.duration_hours, b.status,
                   v.name AS venue_name, v.image_url
            FROM bookings b
            JOIN venues v ON b.venue_id = v.id
            WHERE b.user_id = ?
            ORDER BY b.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If user_id column doesn't exist yet, we can fallback or show error
    $bookings_error = "Unable to fetch bookings. Please ensure the database schema is updated.";
    // For now, just leave $bookings empty
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>My Profile - UBook</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Same orange/white theme as original profile.php */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #ffffff;
            color: #2c2c2c;
            line-height: 1.5;
        }
        .navbar {
            background: #ff8c42;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.6rem;
            font-weight: 700;
            color: white;
        }
        .logo i { font-size: 1.8rem; color: white; }
        .user-greeting {
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-greeting span {
            font-weight: 500;
            font-size: 0.95rem;
            color: white;
        }
        .logout-btn {
            background: white;
            color: #ff8c42;
            padding: 6px 16px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s ease;
            border: none;
        }
        .logout-btn:hover {
            background: #ffb366;
            color: white;
        }
        .container {
            max-width: 1100px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .card {
            background: white;
            border-radius: 28px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            padding: 2rem;
            margin-bottom: 2.5rem;
            border: 1px solid #ffe0c4;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card:hover {
            box-shadow: 0 15px 35px rgba(255,140,66,0.1);
            border-color: #ff8c42;
        }
        .card h2 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 1.8rem;
            color: #ff8c42;
            border-left: 5px solid #ff8c42;
            padding-left: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card h2 i { color: #ff8c42; font-size: 1.6rem; }
        .profile-pic-wrapper {
            text-align: center;
            margin-bottom: 1.8rem;
        }
        .profile-img {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #ff8c42;
            box-shadow: 0 6px 14px rgba(255,140,66,0.2);
            background: #fef3e9;
        }
        .form-group {
            margin-bottom: 1.4rem;
        }
        .form-group label {
            font-weight: 600;
            display: block;
            margin-bottom: 6px;
            color: #ff8c42;
            font-size: 0.9rem;
        }
        .form-group label i { width: 24px; color: #ff8c42; }
        input, .file-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ffe0c4;
            border-radius: 16px;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            transition: all 0.2s;
            background: white;
        }
        input:focus {
            outline: none;
            border-color: #ff8c42;
            box-shadow: 0 0 0 3px rgba(255,140,66,0.15);
        }
        .btn-primary {
            background: #ff8c42;
            color: white;
            border: none;
            padding: 12px 26px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }
        .btn-primary:hover {
            background: #e67e22;
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(255,140,66,0.3);
        }
        .alert {
            padding: 12px 18px;
            border-radius: 20px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: #fff4ea;
            color: #d45a0a;
            border-left: 5px solid #ff8c42;
        }
        .alert-error {
            background: #fff0f0;
            color: #c0392b;
            border-left: 5px solid #e67e22;
        }
        .booking-item {
            background: #ffffff;
            border-radius: 20px;
            padding: 1.2rem 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
            border: 1px solid #ffe0c4;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }
        .booking-item:hover {
            border-color: #ff8c42;
            box-shadow: 0 6px 14px rgba(255,140,66,0.08);
        }
        .booking-info { flex: 3; }
        .booking-id {
            font-weight: 700;
            color: #ff8c42;
            background: #fff4ea;
            display: inline-block;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.75rem;
            margin-bottom: 8px;
        }
        .venue-name {
            font-size: 1.2rem;
            font-weight: 700;
            margin: 6px 0 4px;
            color: #2c2c2c;
        }
        .booking-details {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            font-size: 0.85rem;
            color: #6c6c6c;
            margin-top: 6px;
        }
        .booking-details i { width: 20px; color: #ff8c42; }
        .booking-status {
            font-weight: 600;
            padding: 6px 18px;
            border-radius: 40px;
            display: inline-block;
            font-size: 0.8rem;
            background: #fff4ea;
            color: #d45a0a;
        }
        .status-confirmed { background: #fff4ea; color: #d45a0a; }
        .status-pending { background: #fff4ea; color: #d45a0a; }
        .status-cancelled { background: #ffe8e0; color: #c0392b; }
        .status-completed { background: #fff4ea; color: #d45a0a; }
        .booking-amount {
            font-weight: 700;
            color: #ff8c42;
            font-size: 1.1rem;
            background: #fff4ea;
            padding: 6px 14px;
            border-radius: 40px;
        }
        .no-bookings {
            text-align: center;
            padding: 2rem;
            color: #a0a0a0;
            background: #fefaf5;
            border-radius: 24px;
            border: 1px dashed #ff8c42;
        }
        .back-link {
            margin-top: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            border: 1px solid #ffe0c4;
            padding: 8px 18px;
            border-radius: 40px;
            text-decoration: none;
            color: #ff8c42;
            font-weight: 500;
            transition: 0.2s;
        }
        .back-link:hover {
            background: #fff4ea;
            border-color: #ff8c42;
            color: #e67e22;
        }
        footer {
            text-align: center;
            padding: 2rem 1rem 1.5rem;
            color: #a0a0a0;
            font-size: 0.8rem;
            border-top: 1px solid #ffe0c4;
            margin-top: 2rem;
        }
        @media (max-width: 700px) {
            .navbar { flex-direction: column; text-align: center; }
            .card { padding: 1.5rem; }
            .booking-item { flex-direction: column; align-items: flex-start; }
            .booking-amount { align-self: flex-start; }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<div class="navbar">
    <div class="logo">
        <i class="fas fa-calendar-check"></i>
        <span>UBook</span>
    </div>
    <div class="user-greeting">
        <span><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($user['name']); ?></span>
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="container">

    <!-- PROFILE CARD -->
    <div class="card">
        <h2><i class="fas fa-user-edit"></i> My Profile</h2>

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="profile-pic-wrapper">
            <?php if (!empty($user['profile_image'])): ?>
                <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" class="profile-img" alt="profile">
            <?php else: ?>
                <img src="https://ui-avatars.com/api/?background=ff8c42&color=fff&size=130&name=<?php echo urlencode($user['name']); ?>" class="profile-img" alt="avatar">
            <?php endif; ?>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label><i class="fas fa-camera"></i> Profile Image</label>
                <input type="file" name="profile_image" class="file-input" accept="image/*">
            </div>

            <div class="form-group">
                <label><i class="fas fa-user"></i> Full Name</label>
                <input type="text" name="name" required value="<?php echo htmlspecialchars($user['name']); ?>">
            </div>

            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email Address</label>
                <input type="email" name="email" required value="<?php echo htmlspecialchars($user['email']); ?>">
            </div>

            <div class="form-group">
                <label><i class="fas fa-phone-alt"></i> Phone Number</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
            </div>

            <button type="submit" name="update_profile" class="btn-primary">
                <i class="fas fa-save"></i> Update Profile
            </button>
            <a href="venue.php" class="back-link"><i class="fas fa-arrow-left"></i> Browse Venues</a>
        </form>
    </div>

    <!-- BOOKINGS CARD -->
    <div class="card">
        <h2><i class="fas fa-ticket-alt"></i> My Venue Bookings</h2>

        <?php if (isset($bookings_error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $bookings_error; ?></div>
        <?php elseif (empty($bookings)): ?>
            <div class="no-bookings">
                <i class="fas fa-calendar-times" style="font-size: 2rem; opacity: 0.6; color: #ff8c42;"></i>
                <p style="margin-top: 12px;">You haven't made any bookings yet.</p>
                <a href="venue.php" style="display: inline-block; margin-top: 10px; color:#ff8c42;">Explore venues →</a>
            </div>
        <?php else: ?>
            <?php foreach ($bookings as $booking): 
                $status = $booking['status'] ?? 'pending';
                $statusClass = 'status-' . strtolower($status);
                // Format time for display
                $start = $booking['start_time'] ?? 'N/A';
                $duration = $booking['duration_hours'] ?? 0;
                $endTime = '';
                if ($start && $duration) {
                    $end = new DateTime($start);
                    $end->add(new DateInterval("PT{$duration}H"));
                    $endTime = $end->format('H:i');
                }
            ?>
                <div class="booking-item">
                    <div class="booking-info">
                        <div class="booking-id">
                            <i class="fas fa-hashtag"></i> Booking #<?php echo $booking['id']; ?>
                        </div>
                        <div class="venue-name">
                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($booking['venue_name']); ?>
                        </div>
                        <div class="booking-details">
                            <span><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($booking['booking_date']); ?></span>
                            <span><i class="fas fa-clock"></i> <?php echo $start; ?> – <?php echo $endTime; ?> (<?php echo $duration; ?> hr)</span>
                            <span><i class="fas fa-info-circle"></i> Status: 
                                <span class="booking-status <?php echo $statusClass; ?>"><?php echo ucfirst($status); ?></span>
                            </span>
                        </div>
                    </div>
                    <div class="booking-amount">
                        FREE
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<footer>
    <p><i class="fas fa-map-marker-alt"></i> UBook – Orange & White | Seamless venue booking</p>
</footer>

</body>
</html>