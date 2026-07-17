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
        $conn->set_charset("utf8mb4");
    }
    return $conn;
}

// Handle AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $conn = getDB();

    // ---------- GET PARTICIPANTS (with user details) ----------
    if ($action === 'get_participants') {
        $event_filter = trim($_POST['event_name'] ?? '');
        $sql = "SELECT p.id, p.event_name, p.user_id, p.joined_at,
                       u.name as user_name, u.username, u.email, u.role
                FROM chat_group_participants p
                LEFT JOIN users u ON p.user_id = u.id";
        if (!empty($event_filter)) {
            $sql .= " WHERE p.event_name = ?";
            $stmt = $conn->prepare($sql . " ORDER BY p.event_name, p.joined_at DESC");
            $stmt->bind_param("s", $event_filter);
        } else {
            $sql .= " ORDER BY p.event_name, p.joined_at DESC";
            $stmt = $conn->prepare($sql);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $participants = [];
        while ($row = $result->fetch_assoc()) {
            $participants[] = $row;
        }
        echo json_encode(['success' => true, 'participants' => $participants]);
        exit();
    }

    // ---------- DELETE PARTICIPANT (kick user from group) ----------
    if ($action === 'delete_participant') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid participant ID']);
            exit();
        }
        $stmt = $conn->prepare("DELETE FROM chat_group_participants WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'User removed from group successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Delete failed or participant not found']);
        }
        exit();
    }

    // ---------- GET DISTINCT EVENT NAMES FOR FILTER ----------
    if ($action === 'get_events') {
        $result = $conn->query("SELECT DISTINCT event_name FROM chat_group_participants ORDER BY event_name");
        $events = [];
        while ($row = $result->fetch_assoc()) {
            $events[] = $row['event_name'];
        }
        echo json_encode(['success' => true, 'events' => $events]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

$currentUser = htmlspecialchars($_SESSION['name'] ?? $_SESSION['username']);
$userRole = $_SESSION['role'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UBook · Admin: Manage Group Participants</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Roboto, sans-serif; }
        body { background: #0f172a; min-height: 100vh; }
        .admin-wrapper { max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem; position: relative; z-index: 2; }
        body::before { content: ""; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: radial-gradient(circle at 20% 30%, #1e293b, #0f172a); z-index: -2; }
        .admin-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; background: rgba(255,255,255,0.05); backdrop-filter: blur(12px); padding: 1rem 2rem; border-radius: 2rem; margin-bottom: 1rem; border: 1px solid rgba(249,115,22,0.3); }
        .logo-area h1 { color: #f97316; font-size: 1.8rem; }
        .logo-area p { color: #94a3b8; font-size: 0.85rem; }
        .user-info { background: #1e293b; padding: 0.5rem 1.2rem; border-radius: 2rem; color: white; display: flex; align-items: center; gap: 12px; }
        .logout-btn { background: #f97316; border: none; padding: 8px 18px; border-radius: 40px; color: white; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
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
        .nav-link i { font-size: 1.1rem; }
        .nav-link:hover { background: rgba(249,115,22,0.15); color: #f97316; }
        .nav-link.active {
            background: linear-gradient(135deg, #f97316, #ea580c);
            color: white;
            box-shadow: 0 4px 12px rgba(249,115,22,0.3);
        }

        .stats-card { background: rgba(30,41,59,0.7); backdrop-filter: blur(8px); padding: 12px 20px; border-radius: 1.5rem; display: inline-block; margin-bottom: 1.5rem; color: #cbd5e1; }
        .filter-bar { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; align-items: center; }
        .filter-bar select, .filter-bar button { padding: 8px 16px; border-radius: 40px; border: 1px solid #f97316; background: #1e293b; color: white; cursor: pointer; }
        .btn-primary { background: linear-gradient(135deg, #f97316, #ea580c); border: none; padding: 8px 20px; border-radius: 40px; color: white; cursor: pointer; transition: 0.2s; }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-outline { background: transparent; border: 1.5px solid #f97316; padding: 6px 16px; border-radius: 30px; color: #f97316; cursor: pointer; }
        .btn-outline:hover { background: rgba(249,115,22,0.1); }
        .table-container { background: rgba(15,23,42,0.7); backdrop-filter: blur(8px); border-radius: 28px; padding: 1rem; overflow-x: auto; border: 1px solid rgba(249,115,22,0.2); }
        table { width: 100%; border-collapse: collapse; color: #e2e8f0; }
        th, td { padding: 1rem 0.8rem; text-align: left; border-bottom: 1px solid #334155; vertical-align: middle; }
        th { background: #1e293b; color: #f97316; }
        tr:hover { background: rgba(249,115,22,0.08); }
        .action-icons i { font-size: 1.2rem; margin: 0 6px; cursor: pointer; transition: 0.2s; }
        .delete-icon { color: #ef4444; }
        .delete-icon:hover { transform: scale(1.1); }
        .toast-message { position: fixed; bottom: 30px; right: 30px; background: #10b981; color: white; padding: 12px 24px; border-radius: 60px; font-weight: bold; z-index: 1100; box-shadow: 0 4px 12px black; animation: fadeInOut 3s forwards; }
        .toast-error { background: #dc2626; }
        @keyframes fadeInOut { 0% { opacity: 0; transform: translateX(30px); } 15% { opacity: 1; transform: translateX(0); } 85% { opacity: 1; } 100% { opacity: 0; visibility: hidden; } }
        @media (max-width: 700px) { .admin-wrapper { padding: 1rem; } th, td { font-size: 0.8rem; padding: 0.6rem; } .nav-link { padding: 0.5rem 1rem; font-size: 0.8rem; } }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <div class="admin-header">
        <div class="logo-area">
            <h1><i class="fas fa-users-slash"></i> Admin Dashboard</h1>
            <p>Manage participants, groups, reviews & likes</p>
        </div>
        <div style="display: flex; gap: 1rem;">
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

    <div class="stats-card" id="participantCountDisplay"><i class="fas fa-users"></i> Loading participants...</div>

    <div class="filter-bar">
        <select id="eventFilter">
            <option value="">All Groups</option>
        </select>
        <button class="btn-primary" id="applyFilterBtn">Filter</button>
        <button class="btn-outline" id="refreshBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
    </div>

    <div class="table-container">
        <table id="participantsTable">
            <thead>
                <tr><th>ID</th><th>Group</th><th>User ID</th><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Joined At</th><th>Actions</th></tr>
            </thead>
            <tbody id="participantsTableBody">
                <tr><td colspan="9" style="text-align:center">Loading...<\/td><\/tr>
            <\/tbody>
        <\/table>
    <\/div>
<\/div>

<script>
    const participantsBody = document.getElementById('participantsTableBody');
    const countSpan = document.getElementById('participantCountDisplay');
    const eventFilter = document.getElementById('eventFilter');

    function showToast(msg, isError = false) {
        const toast = document.createElement('div');
        toast.className = 'toast-message' + (isError ? ' toast-error' : '');
        toast.innerText = msg;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    async function loadEvents() {
        try {
            const resp = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ action: 'get_events' })
            });
            const data = await resp.json();
            if (data.success && data.events) {
                let html = '<option value="">All Groups</option>';
                data.events.forEach(ev => {
                    html += `<option value="${escapeHtml(ev)}">${escapeHtml(ev)}</option>`;
                });
                eventFilter.innerHTML = html;
            }
        } catch(e) { console.error(e); }
    }

    async function loadParticipants() {
        const eventName = eventFilter.value;
        try {
            const resp = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ action: 'get_participants', event_name: eventName })
            });
            const data = await resp.json();
            if (data.success && data.participants) {
                renderParticipants(data.participants);
                countSpan.innerHTML = `<i class="fas fa-users"></i> Total members: ${data.participants.length}`;
            } else {
                participantsBody.innerHTML = '<tr><td colspan="9">Failed to load participants.<\/td><\/tr>';
                showToast('Error loading participants', true);
            }
        } catch(e) {
            participantsBody.innerHTML = '<tr><td colspan="9">Network error.<\/td><\/tr>';
            showToast('Network error', true);
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m]);
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleString();
    }

    function renderParticipants(participants) {
        if (!participants.length) {
            participantsBody.innerHTML = '<tr><td colspan="9">No members found.<\/td><\/tr>';
            return;
        }
        let html = '';
        participants.forEach(p => {
            html += `<tr>
                <td>${p.id}<\/td>
                <td><strong>${escapeHtml(p.event_name)}<\/strong><\/td>
                <td>${p.user_id}<\/td>
                <td>${escapeHtml(p.user_name || 'Deleted User')}<\/td>
                <td>${escapeHtml(p.username || '-')}<\/td>
                <td>${escapeHtml(p.email || '-')}<\/td>
                <td>${escapeHtml(p.role || 'user')}<\/td>
                <td>${formatDate(p.joined_at)}<\/td>
                <td class="action-icons">
                    <i class="fas fa-trash-alt delete-icon" data-id="${p.id}" data-name="${escapeHtml(p.user_name || 'User')}" data-group="${escapeHtml(p.event_name)}"><\/i>
                <\/td>
            <\/tr>`;
        });
        participantsBody.innerHTML = html;

        document.querySelectorAll('.delete-icon').forEach(icon => {
            icon.addEventListener('click', async () => {
                const id = icon.getAttribute('data-id');
                const userName = icon.getAttribute('data-name');
                const groupName = icon.getAttribute('data-group');
                if (confirm(`Remove "${userName}" from group "${groupName}"?`)) {
                    await deleteParticipant(id);
                }
            });
        });
    }

    async function deleteParticipant(id) {
        try {
            const resp = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ action: 'delete_participant', id: id })
            });
            const data = await resp.json();
            if (data.success) {
                showToast(data.message);
                loadParticipants();
                loadEvents(); // refresh filter list in case a group becomes empty
            } else {
                showToast(data.message || 'Delete failed', true);
            }
        } catch(e) { showToast('Delete error', true); }
    }

    document.getElementById('applyFilterBtn').onclick = () => loadParticipants();
    document.getElementById('refreshBtn').onclick = () => loadParticipants();

    loadEvents().then(() => loadParticipants());
</script>
</body>
</html>