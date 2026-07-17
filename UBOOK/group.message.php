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

$isAjax = isset($_POST['action']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAjax) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $conn = getDB();

    // ----- GET MESSAGES -----
    if ($action === 'get_messages') {
        $event_filter = trim($_POST['event_name'] ?? '');
        $sql = "SELECT m.*, 
                       CASE 
                           WHEN m.user_id = 0 THEN 'AI Assistant'
                           ELSE COALESCE(u.name, 'Deleted User')
                       END as user_name
                FROM chat_group_messages m
                LEFT JOIN users u ON m.user_id = u.id";
        if (!empty($event_filter)) {
            $sql .= " WHERE m.event_name = ?";
            $stmt = $conn->prepare($sql . " ORDER BY m.created_at DESC");
            $stmt->bind_param("s", $event_filter);
        } else {
            $sql .= " ORDER BY m.created_at DESC";
            $stmt = $conn->prepare($sql);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        echo json_encode(['success' => true, 'messages' => $messages]);
        exit();
    }

    // ----- UPDATE MESSAGE -----
    if ($action === 'update_message') {
        $id = (int)($_POST['id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        
        if ($id <= 0 || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID or empty message']);
            exit();
        }
        
        // Check if message exists
        $check = $conn->prepare("SELECT id FROM chat_group_messages WHERE id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Message ID not found']);
            exit();
        }
        
        $stmt = $conn->prepare("UPDATE chat_group_messages SET message = ? WHERE id = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
            exit();
        }
        $stmt->bind_param("si", $message, $id);
        if ($stmt->execute()) {
            // Verify the update
            $verify = $conn->prepare("SELECT message FROM chat_group_messages WHERE id = ?");
            $verify->bind_param("i", $id);
            $verify->execute();
            $result = $verify->get_result();
            $updatedMsg = $result->fetch_assoc()['message'] ?? '';
            if ($updatedMsg === $message) {
                echo json_encode(['success' => true, 'message' => 'Message updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Update verification failed – possible column length limit. Run: ALTER TABLE chat_group_messages MODIFY message LONGTEXT;']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'DB error: ' . $stmt->error]);
        }
        $stmt->close();
        exit();
    }

    // ----- DELETE MESSAGE -----
    if ($action === 'delete_message') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit();
        }
        $stmt = $conn->prepare("DELETE FROM chat_group_messages WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $stmt->error]);
        }
        exit();
    }

    // ----- GET EVENTS -----
    if ($action === 'get_events') {
        $result = $conn->query("SELECT DISTINCT event_name FROM chat_group_messages ORDER BY event_name");
        $events = [];
        while ($row = $result->fetch_assoc()) {
            $events[] = $row['event_name'];
        }
        echo json_encode(['success' => true, 'events' => $events]);
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
    <title>UBook · Admin: Manage Group Messages</title>
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
        .filter-bar select { background: #0f172a; }
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
        .edit-icon { color: #fbbf24; }
        .delete-icon { color: #ef4444; }
        .action-icons i:hover { transform: scale(1.1); }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); align-items: center; justify-content: center; z-index: 1000; }
        .modal-content { background: #1e293b; max-width: 600px; width: 90%; border-radius: 32px; padding: 2rem; color: white; border: 1px solid #f97316; animation: fadeUp 0.2s ease; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .modal-content h3 { color: #f97316; margin-bottom: 1.5rem; }
        .modal-content textarea { width: 100%; padding: 12px; margin: 8px 0 16px; background: #0f172a; border: 1px solid #475569; border-radius: 24px; color: white; min-height: 120px; outline: none; }
        .modal-content textarea:focus { border-color: #f97316; }
        .modal-buttons { display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px; }
        .toast-message { position: fixed; bottom: 30px; right: 30px; background: #10b981; color: white; padding: 12px 24px; border-radius: 60px; font-weight: bold; z-index: 1100; box-shadow: 0 4px 12px black; animation: fadeInOut 3s forwards; }
        .toast-error { background: #dc2626; }
        @keyframes fadeInOut { 0% { opacity: 0; transform: translateX(30px); } 15% { opacity: 1; transform: translateX(0); } 85% { opacity: 1; } 100% { opacity: 0; visibility: hidden; } }
        .ai-badge { background: #8b5cf6; color: white; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; margin-left: 8px; }
        @media (max-width: 700px) { .admin-wrapper { padding: 1rem; } th, td { font-size: 0.8rem; padding: 0.6rem; } .nav-link { padding: 0.5rem 1rem; font-size: 0.8rem; } }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <div class="admin-header">
        <div class="logo-area">
            <h1><i class="fas fa-comment-dots"></i> Admin Dashboard</h1>
            <p>Manage messages, groups, reviews & likes</p>
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

    <div class="stats-card" id="msgCountDisplay"><i class="fas fa-envelope"></i> Loading messages...</div>

    <div class="filter-bar">
        <select id="eventFilter">
            <option value="">All Events</option>
        </select>
        <button class="btn-primary" id="applyFilterBtn">Filter</button>
        <button class="btn-outline" id="refreshBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
    </div>

    <div class="table-container">
        <table id="messagesTable">
            <thead>
                <tr><th>ID</th><th>Event</th><th>User</th><th>Message</th><th>Created At</th><th>Actions</th></tr>
            </thead>
            <tbody id="messagesTableBody">
                <tr><td colspan="6" style="text-align:center">Loading...<\/td><\/tr>
            <\/tbody>
        <\/table>
    <\/div>
<\/div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <h3>Edit Message</h3>
        <textarea id="editMessage" rows="5"></textarea>
        <input type="hidden" id="editId">
        <div class="modal-buttons">
            <button class="btn-outline" id="closeEditBtn">Cancel</button>
            <button class="btn-primary" id="saveEditBtn">Save</button>
        </div>
    </div>
</div>

<script>
    const messagesBody = document.getElementById('messagesTableBody');
    const msgCountSpan = document.getElementById('msgCountDisplay');
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
            const formData = new URLSearchParams();
            formData.append('action', 'get_events');
            const resp = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            });
            const data = await resp.json();
            if (data.success && data.events) {
                let html = '<option value="">All Events</option>';
                data.events.forEach(ev => {
                    html += `<option value="${escapeHtml(ev)}">${escapeHtml(ev)}</option>`;
                });
                eventFilter.innerHTML = html;
            }
        } catch(e) { console.error(e); }
    }

    async function loadMessages() {
        const eventName = eventFilter.value;
        try {
            const formData = new URLSearchParams();
            formData.append('action', 'get_messages');
            if (eventName) formData.append('event_name', eventName);
            const resp = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            });
            const data = await resp.json();
            if (data.success && data.messages) {
                renderMessages(data.messages);
                msgCountSpan.innerHTML = `<i class="fas fa-envelope"></i> Total messages: ${data.messages.length}`;
            } else {
                messagesBody.innerHTML = '<tr><td colspan="6">Failed to load messages.<\/td><\/tr>';
                showToast('Error loading messages', true);
            }
        } catch(e) {
            messagesBody.innerHTML = '<tr><td colspan="6">Network error.<\/td><\/tr>';
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
        const d = new Date(dateStr);
        return d.toLocaleString();
    }

    function renderMessages(messages) {
        if (!messages.length) {
            messagesBody.innerHTML = '<tr><td colspan="6">No messages found.<\/td><\/tr>';
            return;
        }
        let html = '';
        messages.forEach(msg => {
            const isAi = (msg.user_id == 0);
            const userDisplay = isAi ? `${escapeHtml(msg.user_name)} <span class="ai-badge">AI</span>` : escapeHtml(msg.user_name);
            html += `<tr>
                <td>${msg.id}<\/td>
                <td><strong>${escapeHtml(msg.event_name)}<\/strong><\/td>
                <td>${userDisplay}<\/td>
                <td style="max-width:400px; word-break:break-word;">${escapeHtml(msg.message)}<\/td>
                <td>${formatDate(msg.created_at)}<\/td>
                <td class="action-icons">
                    <i class="fas fa-edit edit-icon" data-id="${msg.id}" data-msg="${escapeAttr(msg.message)}"><\/i>
                    <i class="fas fa-trash-alt delete-icon" data-id="${msg.id}" data-event="${escapeAttr(msg.event_name)}"><\/i>
                <\/td>
            <\/tr>`;
        }); 
        messagesBody.innerHTML = html;

        document.querySelectorAll('.edit-icon').forEach(icon => {
            icon.addEventListener('click', () => {
                const id = icon.getAttribute('data-id');
                const msg = icon.getAttribute('data-msg');
                openEditModal(id, msg);
            });
        });
        document.querySelectorAll('.delete-icon').forEach(icon => {
            icon.addEventListener('click', async () => {
                const id = icon.getAttribute('data-id');
                const eventName = icon.getAttribute('data-event');
                if (confirm(`Delete message from "${eventName}"?`)) {
                    await deleteMessage(id);
                }
            });
        });
    }

    async function deleteMessage(id) {
        try {
            const formData = new URLSearchParams();
            formData.append('action', 'delete_message');
            formData.append('id', id);
            const resp = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            });
            const data = await resp.json();
            if (data.success) {
                showToast(data.message);
                loadMessages();
                loadEvents();
            } else {
                showToast(data.message || 'Delete failed', true);
            }
        } catch(e) { showToast('Delete error', true); }
    }

    function openEditModal(id, currentMsg) {
        document.getElementById('editId').value = id;
        document.getElementById('editMessage').value = currentMsg;
        document.getElementById('editModal').style.display = 'flex';
    }

    document.getElementById('closeEditBtn').onclick = () => {
        document.getElementById('editModal').style.display = 'none';
    };
    document.getElementById('saveEditBtn').onclick = async () => {
        const id = document.getElementById('editId').value;
        const newMessage = document.getElementById('editMessage').value.trim();
        if (!newMessage) {
            showToast('Message cannot be empty', true);
            return;
        }
        try {
            const formData = new URLSearchParams();
            formData.append('action', 'update_message');
            formData.append('id', id);
            formData.append('message', newMessage);
            const resp = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            });
            const data = await resp.json();
            if (data.success) {
                showToast(data.message);
                document.getElementById('editModal').style.display = 'none';
                loadMessages();
            } else {
                showToast(data.message || 'Update failed', true);
            }
        } catch(e) { showToast('Update error', true); }
    };

    document.getElementById('applyFilterBtn').onclick = () => loadMessages();
    document.getElementById('refreshBtn').onclick = () => loadMessages();

    window.onclick = (e) => { if (e.target === document.getElementById('editModal')) document.getElementById('editModal').style.display = 'none'; };

    loadEvents().then(() => loadMessages());
</script>
</body>
</html>