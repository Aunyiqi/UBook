<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Turn off display for production

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
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAjax) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $conn = getDB();

    // ---------- GET GROUPS ----------
    if ($action === 'get_groups') {
        $sql = "SELECT g.event_name, g.cover_url, g.description, g.created_at,
                       (SELECT COUNT(*) FROM chat_group_participants WHERE event_name = g.event_name) as member_count,
                       (SELECT COUNT(*) FROM chat_group_messages WHERE event_name = g.event_name) as message_count
                FROM chat_groups g
                ORDER BY g.created_at DESC";
        $result = $conn->query($sql);
        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'SQL error: ' . $conn->error]);
            exit();
        }
        $groups = [];
        while ($row = $result->fetch_assoc()) {
            $groups[] = $row;
        }
        echo json_encode(['success' => true, 'groups' => $groups]);
        exit();
    }

    // ---------- ADD GROUP ----------
    if ($action === 'add_group') {
        $event_name = trim($_POST['event_name'] ?? '');
        $cover_url = trim($_POST['cover_url'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($event_name)) {
            echo json_encode(['success' => false, 'message' => 'Event name is required.']);
            exit();
        }

        // Check if already exists
        $check = $conn->prepare("SELECT event_name FROM chat_groups WHERE event_name = ?");
        $check->bind_param("s", $event_name);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Group with this name already exists.']);
            exit();
        }

        if (empty($cover_url)) {
            $cover_url = "https://placehold.co/600x400/e2e8f0/64748b?text=" . urlencode(substr($event_name, 0, 20));
        }

        $stmt = $conn->prepare("INSERT INTO chat_groups (event_name, cover_url, description) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $event_name, $cover_url, $description);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Group created successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB insert error: ' . $stmt->error]);
        }
        exit();
    }

    // ---------- UPDATE GROUP ----------
    if ($action === 'update_group') {
        $original_name = trim($_POST['original_name'] ?? '');
        $cover_url = trim($_POST['cover_url'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($original_name)) {
            echo json_encode(['success' => false, 'message' => 'Original group name missing.']);
            exit();
        }

        $stmt = $conn->prepare("UPDATE chat_groups SET cover_url = ?, description = ? WHERE event_name = ?");
        $stmt->bind_param("sss", $cover_url, $description, $original_name);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Group updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB update error: ' . $stmt->error]);
        }
        exit();
    }

    // ---------- DELETE GROUP ----------
    if ($action === 'delete_group') {
        $event_name = trim($_POST['event_name'] ?? '');
        if (empty($event_name)) {
            echo json_encode(['success' => false, 'message' => 'Event name required.']);
            exit();
        }
        $conn->begin_transaction();
        try {
            $conn->prepare("DELETE FROM chat_group_participants WHERE event_name = ?")->bind_param("s", $event_name)->execute();
            $conn->prepare("DELETE FROM chat_group_messages WHERE event_name = ?")->bind_param("s", $event_name)->execute();
            $delGroup = $conn->prepare("DELETE FROM chat_groups WHERE event_name = ?");
            $delGroup->bind_param("s", $event_name);
            $delGroup->execute();
            if ($delGroup->affected_rows > 0) {
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Group deleted successfully.']);
            } else {
                throw new Exception('Group not found.');
            }
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
        }
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
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
    <title>UBook · Admin: Manage Chat Groups</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Roboto, sans-serif; }
        body { background: #0f172a; min-height: 100vh; }
        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: radial-gradient(circle at 20% 30%, #1e293b, #0f172a);
            z-index: -2;
        }
        .admin-wrapper { max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem; position: relative; z-index: 2; }
        .admin-header {
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;
            background: rgba(255,255,255,0.05); backdrop-filter: blur(12px);
            padding: 1rem 2rem; border-radius: 2rem; margin-bottom: 1rem;
            border: 1px solid rgba(249,115,22,0.3);
        }
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
        .action-bar { display: flex; justify-content: space-between; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; }
        .btn-primary { background: linear-gradient(135deg, #f97316, #ea580c); border: none; padding: 12px 28px; border-radius: 40px; font-weight: 600; color: white; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(249,115,22,0.3); transition: 0.2s; }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-outline { background: transparent; border: 1.5px solid #f97316; padding: 8px 18px; border-radius: 30px; color: #f97316; font-weight: 500; cursor: pointer; }
        .groups-table-container { background: rgba(15,23,42,0.7); backdrop-filter: blur(8px); border-radius: 28px; padding: 1rem; overflow-x: auto; border: 1px solid rgba(249,115,22,0.2); }
        table { width: 100%; border-collapse: collapse; color: #e2e8f0; }
        th, td { padding: 1rem 0.8rem; text-align: left; border-bottom: 1px solid #334155; vertical-align: middle; }
        th { background: #1e293b; color: #f97316; }
        tr:hover { background: rgba(249,115,22,0.08); }
        .group-cover { width: 60px; height: 60px; object-fit: cover; border-radius: 12px; }
        .action-icons i { font-size: 1.2rem; margin: 0 6px; cursor: pointer; transition: 0.2s; }
        .edit-icon { color: #fbbf24; }
        .delete-icon { color: #ef4444; }
        .action-icons i:hover { transform: scale(1.1); }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); align-items: center; justify-content: center; z-index: 1000; }
        .modal-content { background: #1e293b; max-width: 600px; width: 90%; border-radius: 32px; padding: 2rem; color: white; border: 1px solid #f97316; animation: fadeUp 0.2s ease; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .modal-content h3 { color: #f97316; margin-bottom: 1.5rem; }
        .modal-content input, .modal-content textarea { width: 100%; padding: 12px; margin: 8px 0 16px; background: #0f172a; border: 1px solid #475569; border-radius: 24px; color: white; outline: none; }
        .modal-content input:focus, .modal-content textarea:focus { border-color: #f97316; }
        .image-preview { max-width: 100%; max-height: 150px; margin: 10px 0; border-radius: 12px; display: none; }
        .modal-buttons { display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px; }
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
            <h1><i class="fas fa-comments"></i> Admin Dashboard</h1>
            <p>Manage groups, reviews, likes & participants</p>
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

    <div class="stats-card" id="groupCountDisplay">Loading groups...</div>
    <div class="action-bar">
        <button class="btn-primary" id="openAddModalBtn"><i class="fas fa-plus-circle"></i> Create New Group</button>
        <button class="btn-outline" id="refreshTableBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
    </div>
    <div class="groups-table-container">
        <table>
            <thead>
                <tr><th>Cover</th><th>Event Name</th><th>Description</th><th>Members</th><th>Messages</th><th>Created</th><th>Actions</th></tr>
            </thead>
            <tbody id="groupsTableBody"><tr><td colspan="7">Loading...<\/td><\/tr><\/tbody>
        <\/table>
    <\/div>
<\/div>

<div id="groupModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle">Create Group</h3>
        <form id="groupForm">
            <input type="hidden" id="originalName">
            <label>Event Name * (cannot change after creation)</label>
            <input type="text" id="eventName" required placeholder="e.g., Summer Festival 2025">
            <label>Cover Image URL</label>
            <input type="url" id="coverUrl" placeholder="https://example.com/cover.jpg">
            <img id="imagePreview" class="image-preview" alt="Preview">
            <label>Description</label>
            <textarea id="description" rows="3" placeholder="Describe the group..."></textarea>
            <div class="modal-buttons">
                <button type="button" class="btn-outline" id="closeModalBtn">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('groupModal');
const groupsBody = document.getElementById('groupsTableBody');

function showToast(msg, isError = false) {
    const toast = document.createElement('div');
    toast.className = 'toast-message' + (isError ? ' toast-error' : '');
    toast.innerText = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

async function loadGroups() {
    try {
        const resp = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ action: 'get_groups' })
        });
        const data = await resp.json();
        if (data.success) {
            renderGroups(data.groups);
            document.getElementById('groupCountDisplay').innerHTML = `<i class="fas fa-users"></i> Total groups: ${data.groups.length}`;
        } else {
            groupsBody.innerHTML = `<tr><td colspan="7">Error: ${escapeHtml(data.message || 'Unknown')}</td></tr>`;
            showToast('Failed to load groups', true);
        }
    } catch(e) {
        groupsBody.innerHTML = '<tr><td colspan="7">Network error</td></tr>';
        showToast('Network error', true);
        console.error(e);
    }
}

function escapeHtml(s) { return s ? s.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m]) : ''; }
function escapeAttr(s) { return s ? s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') : ''; }

function renderGroups(groups) {
    if (!groups.length) { groupsBody.innerHTML = '<tr><td colspan="7">No groups found. Create one!</td></tr>'; return; }
    let html = '';
    groups.forEach(g => {
        html += `<tr>
            <td>${g.cover_url ? `<img src="${escapeHtml(g.cover_url)}" class="group-cover" onerror="this.src='https://placehold.co/60x60/e2e8f0/64748b?text=No+Image'">` : '—'}</td>
            <td><strong>${escapeHtml(g.event_name)}</strong></td>
            <td>${escapeHtml(g.description || '')}</td>
            <td>${g.member_count}</td>
            <td>${g.message_count}</td>
            <td>${new Date(g.created_at).toLocaleString()}</td>
            <td class="action-icons">
                <i class="fas fa-edit edit-icon" data-name="${escapeAttr(g.event_name)}" data-cover="${escapeAttr(g.cover_url || '')}" data-desc="${escapeAttr(g.description || '')}"></i>
                <i class="fas fa-trash-alt delete-icon" data-name="${escapeAttr(g.event_name)}"></i>
            </td>
        </tr>`;
    });
    groupsBody.innerHTML = html;
    document.querySelectorAll('.edit-icon').forEach(icon => {
        icon.addEventListener('click', () => openEditModal(icon.dataset.name, icon.dataset.cover, icon.dataset.desc));
    });
    document.querySelectorAll('.delete-icon').forEach(icon => {
        icon.addEventListener('click', () => deleteGroup(icon.dataset.name));
    });
}

async function deleteGroup(eventName) {
    if (!confirm(`Delete group "${eventName}" permanently? All messages and participants will be removed.`)) return;
    try {
        const resp = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ action: 'delete_group', event_name: eventName })
        });
        const data = await resp.json();
        if (data.success) { showToast(data.message); loadGroups(); }
        else showToast(data.message, true);
    } catch(e) { showToast('Delete error', true); }
}

function openEditModal(eventName, coverUrl, description) {
    document.getElementById('originalName').value = eventName;
    document.getElementById('eventName').value = eventName;
    document.getElementById('coverUrl').value = coverUrl;
    document.getElementById('description').value = description;
    document.getElementById('eventName').disabled = true;
    updatePreview(coverUrl);
    document.getElementById('modalTitle').innerText = 'Edit Group';
    modal.style.display = 'flex';
}

function resetAddModal() {
    document.getElementById('originalName').value = '';
    document.getElementById('eventName').value = '';
    document.getElementById('coverUrl').value = '';
    document.getElementById('description').value = '';
    document.getElementById('eventName').disabled = false;
    document.getElementById('imagePreview').style.display = 'none';
    document.getElementById('modalTitle').innerText = 'Create New Group';
}

function updatePreview(url) {
    const preview = document.getElementById('imagePreview');
    if (url && url.trim()) { preview.src = url; preview.style.display = 'block'; }
    else preview.style.display = 'none';
}

document.getElementById('openAddModalBtn').onclick = () => { resetAddModal(); modal.style.display = 'flex'; };
document.getElementById('closeModalBtn').onclick = () => modal.style.display = 'none';
document.getElementById('refreshTableBtn').onclick = () => loadGroups();
document.getElementById('coverUrl').oninput = (e) => updatePreview(e.target.value);

document.getElementById('groupForm').onsubmit = async (e) => {
    e.preventDefault();
    const originalName = document.getElementById('originalName').value;
    const eventName = document.getElementById('eventName').value.trim();
    const coverUrl = document.getElementById('coverUrl').value.trim();
    const description = document.getElementById('description').value.trim();
    if (!eventName) { showToast('Event name is required', true); return; }
    const action = originalName ? 'update_group' : 'add_group';
    const fd = new URLSearchParams();
    fd.append('action', action);
    fd.append('event_name', eventName);
    fd.append('cover_url', coverUrl);
    fd.append('description', description);
    if (originalName) fd.append('original_name', originalName);
    try {
        const resp = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        const data = await resp.json();
        if (data.success) {
            showToast(data.message);
            modal.style.display = 'none';
            loadGroups();
        } else {
            showToast(data.message || 'Operation failed', true);
        }
    } catch(err) { showToast('Request error: ' + err.message, true); }
};

window.onclick = (e) => { if (e.target === modal) modal.style.display = 'none'; };
loadGroups();
</script>
</body>
</html>