<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Allow super_admin and review_admin
$allowedRoles = ['super_admin', 'review_admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowedRoles)) {
    header('Location: login.php');
    exit();
}

function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli("localhost", "root", "", "ubook");
        if ($conn->connect_error) {
            die(json_encode(['error' => 'DB connection failed: ' . $conn->connect_error]));
        }
    }
    return $conn;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $conn = getDB();

    // ---------- GET ALL LIKES with comment & user info ----------
    if ($action === 'get_likes') {
        $sql = "SELECT cl.comment_id, cl.user_id, 
                       u.name as user_name, 
                       c.comment, c.venue_id, v.name as venue_name,
                       c.created_at as comment_created_at
                FROM comment_likes cl
                LEFT JOIN users u ON cl.user_id = u.id
                LEFT JOIN venue_comments c ON cl.comment_id = c.id
                LEFT JOIN venues v ON c.venue_id = v.id
                ORDER BY cl.comment_id DESC, cl.user_id";
        $result = $conn->query($sql);
        $likes = [];
        while ($row = $result->fetch_assoc()) {
            $likes[] = $row;
        }
        echo json_encode(['success' => true, 'likes' => $likes]);
        exit();
    }

    // ---------- DELETE A LIKE (remove user's like from a comment) ----------
    if ($action === 'delete_like') {
        $comment_id = (int)($_POST['comment_id'] ?? 0);
        $user_id = trim($_POST['user_id'] ?? '');
        if ($comment_id <= 0 || empty($user_id)) {
            echo json_encode(['success' => false, 'message' => 'Invalid like record.']);
            exit();
        }
        $stmt = $conn->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?");
        $stmt->bind_param("is", $comment_id, $user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Like removed successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Like not found or could not be deleted.']);
        }
        exit();
    }

    // ---------- GET LIKE STATS (top liked comments) ----------
    if ($action === 'get_stats') {
        $sql = "SELECT c.id, c.comment, v.name as venue_name, 
                       COUNT(cl.user_id) as like_count
                FROM venue_comments c
                LEFT JOIN comment_likes cl ON c.id = cl.comment_id
                LEFT JOIN venues v ON c.venue_id = v.id
                GROUP BY c.id
                ORDER BY like_count DESC
                LIMIT 20";
        $result = $conn->query($sql);
        $stats = [];
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
        echo json_encode(['success' => true, 'stats' => $stats]);
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
    <title>UBook · Admin: Manage Comment Likes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Roboto, sans-serif;
        }
        body {
            background: #0f172a;
            min-height: 100vh;
        }
        .admin-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
            position: relative;
            z-index: 2;
        }
        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: radial-gradient(circle at 20% 30%, #1e293b, #0f172a);
            z-index: -2;
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(12px);
            padding: 1rem 2rem;
            border-radius: 2rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(249,115,22,0.3);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        .logo-area h1 {
            color: #f97316;
            font-size: 1.8rem;
            font-weight: 700;
        }
        .logo-area p {
            color: #94a3b8;
            font-size: 0.85rem;
        }
        .user-info {
            background: #1e293b;
            padding: 0.5rem 1.2rem;
            border-radius: 2rem;
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logout-btn {
            background: #f97316;
            border: none;
            padding: 8px 18px;
            border-radius: 40px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .logout-btn:hover { background: #ea580c; }

        /* Navigation Menu */
        .admin-nav {
            background: rgba(30,41,59,0.6);
            backdrop-filter: blur(12px);
            border-radius: 2rem;
            padding: 0.5rem 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(249,115,22,0.2);
        }
        .nav-menu {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.5rem;
            list-style: none;
        }
        .nav-item {
            display: inline-block;
        }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0.7rem 1.4rem;
            border-radius: 2rem;
            color: #cbd5e1;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            background: transparent;
        }
        .nav-link i {
            font-size: 1.1rem;
        }
        .nav-link:hover {
            background: rgba(249,115,22,0.15);
            color: #f97316;
        }
        .nav-link.active {
            background: linear-gradient(135deg, #f97316, #ea580c);
            color: white;
            box-shadow: 0 4px 12px rgba(249,115,22,0.3);
        }

        .stats-card {
            background: rgba(30,41,59,0.7);
            backdrop-filter: blur(8px);
            padding: 12px 20px;
            border-radius: 1.5rem;
            display: inline-block;
            margin-bottom: 1.5rem;
            color: #cbd5e1;
        }
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid #334155;
        }
        .tab-btn {
            background: none;
            border: none;
            padding: 12px 24px;
            font-size: 1rem;
            font-weight: 600;
            color: #94a3b8;
            cursor: pointer;
            transition: 0.2s;
            border-bottom: 2px solid transparent;
        }
        .tab-btn.active {
            color: #f97316;
            border-bottom-color: #f97316;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .action-bar {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 2rem;
            gap: 1rem;
        }
        .btn-outline {
            background: transparent;
            border: 1.5px solid #f97316;
            padding: 8px 18px;
            border-radius: 30px;
            color: #f97316;
            font-weight: 500;
            cursor: pointer;
        }
        .btn-outline:hover { background: rgba(249,115,22,0.1); }
        .table-container {
            background: rgba(15,23,42,0.7);
            backdrop-filter: blur(8px);
            border-radius: 28px;
            padding: 1rem;
            overflow-x: auto;
            border: 1px solid rgba(249,115,22,0.2);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            color: #e2e8f0;
        }
        th, td {
            padding: 1rem 0.8rem;
            text-align: left;
            border-bottom: 1px solid #334155;
            vertical-align: middle;
        }
        th {
            background: #1e293b;
            color: #f97316;
            font-weight: 600;
        }
        tr:hover { background: rgba(249,115,22,0.08); }
        .like-badge {
            background: #f97316;
            color: white;
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .delete-icon {
            color: #ef4444;
            cursor: pointer;
            font-size: 1.2rem;
            transition: 0.2s;
        }
        .delete-icon:hover { transform: scale(1.1); }
        .comment-preview {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .toast-message {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #10b981;
            color: white;
            padding: 12px 24px;
            border-radius: 60px;
            font-weight: bold;
            z-index: 1100;
            box-shadow: 0 4px 12px black;
            animation: fadeInOut 3s forwards;
        }
        .toast-error {
            background: #dc2626;
        }
        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateX(30px);}
            15% { opacity: 1; transform: translateX(0);}
            85% { opacity: 1; transform: translateX(0);}
            100% { opacity: 0; visibility: hidden; }
        }
        @media (max-width: 700px) {
            .admin-wrapper { padding: 1rem; }
            th, td { font-size: 0.8rem; padding: 0.6rem; }
            .nav-link { padding: 0.5rem 1rem; font-size: 0.8rem; }
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <div class="admin-header">
        <div class="logo-area">
            <h1><i class="fas fa-thumbs-up"></i> Admin Dashboard</h1>
            <p>Manage likes, reviews, groups & participants</p>
        </div>
        <div style="display: flex; gap: 1rem; align-items: center;">
            <div class="user-info"><i class="fas fa-user-tie"></i> <?= $currentUser ?> (<?= htmlspecialchars($userRole) ?>)</div>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Navigation Menu with 5 sections -->
    <div class="admin-nav">
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="review_admin_main_menu.php" class="nav-link <?= $currentPage == 'review_admin_main_menu.php' ? 'active' : '' ?>">
                    <i class="fas fa-star"></i> <span>Reviews</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="group.manage.php" class="nav-link <?= $currentPage == 'group.manage.php' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i> <span>Groups</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="like.manage.php" class="nav-link <?= $currentPage == 'like.manage.php' ? 'active' : '' ?>">
                    <i class="fas fa-heart"></i> <span>Likes</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="manage.group.participants.php" class="nav-link <?= $currentPage == 'manage.group.participants.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-plus"></i> <span>Participants</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="group.message.php" class="nav-link <?= $currentPage == 'group.message.php' ? 'active' : '' ?>">
                    <i class="fas fa-comment-dots"></i> <span>Messages</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="stats-card" id="likeStatsDisplay">
        <i class="fas fa-heart"></i> Loading like statistics...
    </div>

    <div class="tabs">
        <button class="tab-btn active" data-tab="likesTab">📋 All Likes</button>
        <button class="tab-btn" data-tab="topTab">🏆 Top Liked Comments</button>
    </div>

    <div class="action-bar">
        <button class="btn-outline" id="refreshBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
    </div>

    <!-- Tab 1: All likes -->
    <div id="likesTab" class="tab-content active">
        <div class="table-container">
            <table id="likesTable">
                <thead>
                    <tr><th>Comment ID</th><th>Venue</th><th>Comment Preview</th><th>User ID</th><th>User Name</th><th>Action</th></tr>
                </thead>
                <tbody id="likesTableBody">
                    <tr><td colspan="6" style="text-align:center">Loading likes...<\/td><\/tr>
                </tbody>
            \d+
        </div>
    </div>

    <!-- Tab 2: Top liked comments -->
    <div id="topTab" class="tab-content">
        <div class="table-container">
            <table id="topTable">
                <thead>
                    <tr><th>Comment ID</th><th>Venue</th><th>Comment</th><th>Like Count</th></tr>
                </thead>
                <tbody id="topTableBody">
                    <tr><td colspan="4" style="text-align:center">Loading stats...<\/td><\/tr>
                </tbody>
            \d+
        </div>
    </div>
</div>

<script>
    let currentLikes = [];

    function showToast(message, isError = false) {
        const toast = document.createElement('div');
        toast.className = 'toast-message' + (isError ? ' toast-error' : '');
        toast.innerText = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    async function loadLikes() {
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({ action: 'get_likes' })
            });
            const data = await response.json();
            if (data.success && data.likes) {
                currentLikes = data.likes;
                renderLikesTable(data.likes);
                document.getElementById('likeStatsDisplay').innerHTML = `<i class="fas fa-heart"></i> Total likes: ${data.likes.length}`;
            } else {
                document.getElementById('likesTableBody').innerHTML = '<tr><td colspan="6">Failed to load likes.<\/td><\/tr>';
                showToast('Error loading likes', true);
            }
        } catch (err) {
            console.error(err);
            document.getElementById('likesTableBody').innerHTML = '<tr><td colspan="6">Network error.<\/td><\/tr>';
            showToast('Network error', true);
        }
    }

    async function loadTopStats() {
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({ action: 'get_stats' })
            });
            const data = await response.json();
            if (data.success && data.stats) {
                renderTopTable(data.stats);
            } else {
                document.getElementById('topTableBody').innerHTML = '<tr><td colspan="4">Failed to load stats.<\/td><\/tr>';
            }
        } catch (err) {
            document.getElementById('topTableBody').innerHTML = '<tr><td colspan="4">Network error.<\/td><\/tr>';
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    function truncate(str, len = 80) {
        if (!str) return '';
        if (str.length <= len) return escapeHtml(str);
        return escapeHtml(str.substring(0, len)) + '...';
    }

    function renderLikesTable(likes) {
        const tbody = document.getElementById('likesTableBody');
        if (!likes.length) {
            tbody.innerHTML = '<tr><td colspan="6">No likes found.<\/td><\/tr>';
            return;
        }
        let html = '';
        likes.forEach(like => {
            const commentPreview = truncate(like.comment || '[Comment deleted]', 70);
            const venueName = like.venue_name || 'Unknown Venue';
            const userName = like.user_name || 'Unknown User';
            html += `<tr>
                <td>${like.comment_id}</td>
                <td>${escapeHtml(venueName)}</td>
                <td class="comment-preview" title="${escapeHtml(like.comment || '')}">${commentPreview}</td>
                <td>${escapeHtml(like.user_id)}</td>
                <td>${escapeHtml(userName)}</td>
                <td><i class="fas fa-trash-alt delete-icon" data-comment-id="${like.comment_id}" data-user-id="${escapeHtml(like.user_id)}" data-user-name="${escapeHtml(userName)}"></i></td>
            </tr>`;
        });
        tbody.innerHTML = html;

        // Attach delete events
        document.querySelectorAll('.delete-icon').forEach(icon => {
            icon.addEventListener('click', async () => {
                const commentId = icon.getAttribute('data-comment-id');
                const userId = icon.getAttribute('data-user-id');
                const userName = icon.getAttribute('data-user-name');
                if (confirm(`Remove like by "${userName}" (User ID: ${userId}) from comment #${commentId}?`)) {
                    await deleteLike(commentId, userId);
                }
            });
        });
    }

    async function deleteLike(commentId, userId) {
        try {
            const formData = new URLSearchParams();
            formData.append('action', 'delete_like');
            formData.append('comment_id', commentId);
            formData.append('user_id', userId);
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                showToast(data.message);
                loadLikes();      // refresh likes table
                loadTopStats();   // refresh top stats as counts changed
            } else {
                showToast(data.message || 'Delete failed', true);
            }
        } catch (err) {
            showToast('Request error', true);
        }
    }

    function renderTopTable(stats) {
        const tbody = document.getElementById('topTableBody');
        if (!stats.length) {
            tbody.innerHTML = '<tr><td colspan="4">No comments found.<\/td><\/tr>';
            return;
        }
        let html = '';
        stats.forEach(stat => {
            html += `<tr>
                <td>${stat.id}</td>
                <td>${escapeHtml(stat.venue_name || 'Unknown')}</td>
                <td>${truncate(stat.comment, 100)}</td>
                <td><span class="like-badge">${stat.like_count} 👍</span></td>
            </tr>`;
        });
        tbody.innerHTML = html;
    }

    // Tab switching
    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelector(`.tab-btn[data-tab="${tabId}"]`).classList.add('active');
        
        if (tabId === 'topTab' && document.getElementById('topTableBody').innerHTML.includes('Loading')) {
            loadTopStats();
        }
    }

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tabId = btn.getAttribute('data-tab');
            switchTab(tabId);
        });
    });

    document.getElementById('refreshBtn').addEventListener('click', () => {
        loadLikes();
        if (document.getElementById('topTab').classList.contains('active')) {
            loadTopStats();
        }
    });

    // Initialize
    loadLikes();
    loadTopStats();
</script>
</body>
</html>