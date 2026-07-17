<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

$allowedRoles = ['super_admin', 'booking_admin'];
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
        $conn->query("ALTER TABLE venue_comments MODIFY comment LONGTEXT");
    }
    return $conn;
}

// Handle AJAX requests (same as before)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $conn = getDB();

    if ($action === 'get_comments') {
        $venue_filter = trim($_POST['venue_id'] ?? '');
        $user_filter = trim($_POST['user_id'] ?? '');
        $sql = "SELECT c.*, v.name as venue_name, u.name as user_name, u.username 
                FROM venue_comments c
                LEFT JOIN venues v ON c.venue_id = v.id
                LEFT JOIN users u ON c.user_id = u.id
                WHERE 1=1";
        $params = [];
        $types = "";
        if (!empty($venue_filter)) {
            $sql .= " AND (c.venue_id LIKE ? OR v.name LIKE ?)";
            $like = "%$venue_filter%";
            $params[] = $like; $params[] = $like;
            $types .= "ss";
        }
        if (!empty($user_filter)) {
            $sql .= " AND (c.user_id LIKE ? OR u.name LIKE ? OR u.username LIKE ?)";
            $like = "%$user_filter%";
            $params[] = $like; $params[] = $like; $params[] = $like;
            $types .= "sss";
        }
        $sql .= " ORDER BY c.created_at DESC";
        $stmt = $conn->prepare($sql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $comments = [];
        while ($row = $result->fetch_assoc()) $comments[] = $row;
        echo json_encode(['success' => true, 'comments' => $comments]);
        exit();
    }

    if ($action === 'update_comment') {
        $id = (int)($_POST['id'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if ($id <= 0 || empty($comment)) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID or empty comment']);
            exit();
        }
        $escaped = $conn->real_escape_string($comment);
        $sql = "UPDATE venue_comments SET comment = '$escaped' WHERE id = $id";
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Comment updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
        }
        exit();
    }

    if ($action === 'delete_comment') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit(); }
        $stmt = $conn->prepare("DELETE FROM venue_comments WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Comment deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Delete failed']);
        }
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

$currentUser = htmlspecialchars($_SESSION['name'] ?? $_SESSION['username'] ?? 'Admin');
$userRole = $_SESSION['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UBook · Manage Venue Comments</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Same style block as previous pages – identical to venue.manage.php but with comment-specific classes */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', 'Segoe UI', Roboto, sans-serif; }
        body { background: #f8fafc; min-height: 100vh; }
        .admin-wrapper { max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem; }
        .admin-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; background: white; padding: 1rem 2rem; border-radius: 2rem; margin-bottom: 1rem; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .logo-area h1 { color: #ea580c; font-size: 1.8rem; }
        .logo-area p { color: #475569; font-size: 0.85rem; }
        .user-info { background: #f1f5f9; padding: 0.5rem 1.2rem; border-radius: 2rem; color: #1e293b; display: flex; align-items: center; gap: 12px; }
        .logout-btn { background: #ea580c; border: none; padding: 8px 18px; border-radius: 40px; color: white; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
        .logout-btn:hover { background: #c2410c; transform: scale(1.02); }
        .admin-nav { background: white; border-radius: 2rem; margin-bottom: 2rem; padding: 0.75rem 1.5rem; border: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
        .nav-link { display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 40px; background: #f8fafc; color: #1e293b; text-decoration: none; font-weight: 500; transition: all 0.2s; border: 1px solid #e2e8f0; }
        .nav-link i { color: #ea580c; }
        .nav-link:hover { background: #ea580c; color: white; border-color: #ea580c; transform: translateY(-2px); }
        .nav-link:hover i { color: white; }
        .nav-link.active { background: #ea580c; color: white; border-color: #ea580c; }
        .nav-link.active i { color: white; }
        .stats-card { background: white; padding: 0.8rem 1.5rem; border-radius: 1.5rem; display: inline-block; margin-bottom: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border-left: 4px solid #ea580c; font-weight: 500; }
        .filter-bar { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; align-items: center; }
        .filter-bar input { flex: 1; min-width: 180px; padding: 10px 16px; border-radius: 40px; border: 1px solid #cbd5e1; background: white; color: #1e293b; font-size: 0.9rem; outline: none; }
        .filter-bar input:focus { border-color: #ea580c; box-shadow: 0 0 0 2px rgba(234,88,12,0.2); }
        .btn-primary { background: #ea580c; border: none; padding: 10px 24px; border-radius: 40px; font-weight: 600; color: white; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-primary:hover { background: #c2410c; transform: translateY(-1px); }
        .btn-outline { background: transparent; border: 1px solid #ea580c; padding: 8px 20px; border-radius: 40px; color: #ea580c; font-weight: 500; cursor: pointer; transition: 0.2s; }
        .btn-outline:hover { background: #fef3c7; }
        .table-container { background: white; border-radius: 28px; padding: 1rem; overflow-x: auto; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; color: #1e293b; }
        th, td { padding: 1rem 0.8rem; text-align: left; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        th { background: #f8fafc; color: #ea580c; font-weight: 600; }
        tr:hover { background: #fef3c7; }
        .action-icons i { font-size: 1.2rem; margin: 0 6px; cursor: pointer; transition: 0.2s; }
        .edit-icon { color: #f59e0b; }
        .delete-icon { color: #ef4444; }
        .action-icons i:hover { transform: scale(1.1); }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 1000; }
        .modal-content { background: white; max-width: 600px; width: 90%; border-radius: 32px; padding: 2rem; color: #1e293b; border: 1px solid #e2e8f0; box-shadow: 0 20px 35px rgba(0,0,0,0.2); animation: fadeUp 0.2s ease; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .modal-content h3 { color: #ea580c; margin-bottom: 1.5rem; }
        .modal-content textarea { width: 100%; padding: 12px; margin: 8px 0 16px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 24px; color: #1e293b; font-family: monospace; min-height: 120px; resize: vertical; outline: none; }
        .modal-content textarea:focus { border-color: #ea580c; box-shadow: 0 0 0 2px rgba(234,88,12,0.2); }
        .modal-buttons { display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px; }
        .toast-message { position: fixed; bottom: 30px; right: 30px; background: #10b981; color: white; padding: 12px 24px; border-radius: 60px; font-weight: bold; z-index: 1100; animation: fadeInOut 3s forwards; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .toast-error { background: #dc2626; }
        @keyframes fadeInOut { 0% { opacity: 0; transform: translateX(30px); } 15% { opacity: 1; transform: translateX(0); } 85% { opacity: 1; transform: translateX(0); } 100% { opacity: 0; visibility: hidden; } }
        @media (max-width: 768px) { .admin-wrapper { padding: 1rem; } th, td { font-size: 0.8rem; padding: 0.6rem; } .admin-nav { justify-content: center; } }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <div class="admin-header">
        <div class="logo-area">
            <h1><i class="fas fa-comment-dots"></i> Venue Comments</h1>
            <p>Manage user feedback and ratings</p>
        </div>
        <div style="display: flex; gap: 1rem; align-items: center;">
            <div class="user-info"><i class="fas fa-user-tie"></i> <?= $currentUser ?> (<?= htmlspecialchars($userRole) ?>)</div>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- ===== SAME NAVIGATION (4 LINKS) ===== -->
    <div class="admin-nav">
        <a href="venue.manage.php" class="nav-link"><i class="fas fa-building"></i> Venue Management</a>
        <a href="user.manage.php" class="nav-link"><i class="fas fa-users"></i> User Management</a>
        <a href="booking_admin_main_menu.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Main Menu</a>
        <a href="manage.venue.comments.php" class="nav-link active"><i class="fas fa-comments"></i> Venue Comments</a>
    </div>

    <div class="stats-card" id="commentCountDisplay">
        <i class="fas fa-comments"></i> Loading comments...
    </div>

    <div class="filter-bar">
        <input type="text" id="venueFilter" placeholder="Filter by Venue ID or Name">
        <input type="text" id="userFilter" placeholder="Filter by User ID, Name or Username">
        <button class="btn-primary" id="applyFilterBtn"><i class="fas fa-search"></i> Filter</button>
        <button class="btn-outline" id="refreshBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
    </div>

    <div class="table-container">
        <table id="commentsTable">
            <thead><tr><th>ID</th><th>Venue</th><th>User</th><th>Comment</th><th>Created At</th><th>Actions</th></tr></thead>
            <tbody id="commentsTableBody"><tr><td colspan="6">Loading comments...</td></tr></tbody>
        </table>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-edit"></i> Edit Comment</h3>
        <textarea id="editComment" rows="5" placeholder="Edit the comment text..."></textarea>
        <input type="hidden" id="editId">
        <div class="modal-buttons">
            <button class="btn-outline" id="closeEditBtn">Cancel</button>
            <button class="btn-primary" id="saveEditBtn">Save Changes</button>
        </div>
    </div>
</div>

<script>
    const commentsBody = document.getElementById('commentsTableBody');
    const countSpan = document.getElementById('commentCountDisplay');
    const venueFilter = document.getElementById('venueFilter');
    const userFilter = document.getElementById('userFilter');

    function showToast(msg, isError = false) {
        const toast = document.createElement('div');
        toast.className = 'toast-message' + (isError ? ' toast-error' : '');
        toast.innerText = msg;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    async function apiRequest(action, data = {}) {
        const formData = new URLSearchParams();
        formData.append('action', action);
        for (let [k, v] of Object.entries(data)) formData.append(k, v);
        const resp = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        return await resp.json();
    }

    async function loadComments() {
        const venue = venueFilter.value.trim();
        const user = userFilter.value.trim();
        try {
            const data = await apiRequest('get_comments', { venue_id: venue, user_id: user });
            if (data.success && data.comments) {
                renderComments(data.comments);
                countSpan.innerHTML = `<i class="fas fa-comments"></i> Total comments: ${data.comments.length}`;
            } else {
                commentsBody.innerHTML = '<tr><td colspan="6">Failed to load comments.</td></tr>';
                showToast('Error loading comments', true);
            }
        } catch(e) {
            commentsBody.innerHTML = '<tr><td colspan="6">Network error.</td></tr>';
            showToast('Network error', true);
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m]);
    }
    function escapeAttr(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function formatDate(dateStr) {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleString();
    }

    function renderComments(comments) {
        if (!comments.length) {
            commentsBody.innerHTML = '<tr><td colspan="6">No comments found.</td></tr>';
            return;
        }
        let html = '';
        comments.forEach(c => {
            const userDisplay = c.user_name ? `${escapeHtml(c.user_name)} (${escapeHtml(c.username)})` : 'Deleted User';
            html += `<tr>
                <td>${c.id}</td>
                <td><strong>${escapeHtml(c.venue_name)}</strong><br><small>${escapeHtml(c.venue_id)}</small></td>
                <td>${userDisplay}<br><small>ID: ${c.user_id}</small></td>
                <td style="max-width:400px; word-break:break-word;">${escapeHtml(c.comment)}</td>
                <td>${formatDate(c.created_at)}</td>
                <td class="action-icons">
                    <i class="fas fa-edit edit-icon" data-id="${c.id}" data-comment="${escapeAttr(c.comment)}"></i>
                    <i class="fas fa-trash-alt delete-icon" data-id="${c.id}" data-venue="${escapeHtml(c.venue_name)}"></i>
                </td>
            </tr>`;
        });
        commentsBody.innerHTML = html;

        document.querySelectorAll('.edit-icon').forEach(icon => {
            icon.removeEventListener('click', handleEditClick);
            icon.addEventListener('click', handleEditClick);
        });
        document.querySelectorAll('.delete-icon').forEach(icon => {
            icon.removeEventListener('click', handleDeleteClick);
            icon.addEventListener('click', handleDeleteClick);
        });
    }

    function handleEditClick(e) {
        const icon = e.currentTarget;
        const id = icon.getAttribute('data-id');
        const comment = icon.getAttribute('data-comment');
        openEditModal(id, comment);
    }
    function handleDeleteClick(e) {
        const icon = e.currentTarget;
        const id = icon.getAttribute('data-id');
        const venue = icon.getAttribute('data-venue');
        if (confirm(`Delete comment from venue "${venue}"? This action cannot be undone.`)) {
            deleteComment(id);
        }
    }
    async function deleteComment(id) {
        try {
            const data = await apiRequest('delete_comment', { id: id });
            if (data.success) {
                showToast('Comment deleted');
                loadComments();
            } else {
                showToast(data.message || 'Delete failed', true);
            }
        } catch(e) {
            showToast('Delete error', true);
        }
    }
    function openEditModal(id, currentComment) {
        document.getElementById('editId').value = id;
        document.getElementById('editComment').value = currentComment;
        document.getElementById('editModal').style.display = 'flex';
    }
    document.getElementById('closeEditBtn').onclick = () => {
        document.getElementById('editModal').style.display = 'none';
    };
    document.getElementById('saveEditBtn').onclick = async () => {
        const id = document.getElementById('editId').value;
        const newComment = document.getElementById('editComment').value.trim();
        if (newComment === '') {
            showToast('Comment cannot be empty', true);
            return;
        }
        try {
            const data = await apiRequest('update_comment', { id: id, comment: newComment });
            if (data.success) {
                showToast('Comment updated');
                document.getElementById('editModal').style.display = 'none';
                loadComments();
            } else {
                showToast(data.message || 'Update failed', true);
            }
        } catch(e) {
            showToast('Update error', true);
        }
    };
    document.getElementById('applyFilterBtn').onclick = () => loadComments();
    document.getElementById('refreshBtn').onclick = () => loadComments();
    window.onclick = (e) => { if (e.target === document.getElementById('editModal')) document.getElementById('editModal').style.display = 'none'; };
    loadComments();
</script>
</body>
</html>