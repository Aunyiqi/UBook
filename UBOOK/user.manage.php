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
    }
    return $conn;
}

// Handle AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $conn = getDB();

    // ---------- GET USERS ----------
    if ($action === 'get_users') {
        $result = $conn->query("SELECT id, username, name, email, phone, role, created_at FROM users ORDER BY username ASC");
        $users = [];
        while ($row = $result->fetch_assoc()) $users[] = $row;
        echo json_encode(['success' => true, 'users' => $users]);
        exit();
    }

    // ---------- ADD USER ----------
    if ($action === 'add_user') {
        $id = trim($_POST['id'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';

        if (empty($id) || empty($username) || empty($name) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'ID, Username, Name and Password are required.']);
            exit();
        }
        if (!in_array($role, ['user', 'super_admin', 'review_admin', 'booking_admin'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid role.']);
            exit();
        }

        // Check if ID already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'User ID already exists. Use a unique ID.']);
            exit();
        }

        // Check if username already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists.']);
            exit();
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $email = $email ?: null;
        $phone = $phone ?: null;
        $stmt = $conn->prepare("INSERT INTO users (id, username, name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $id, $username, $name, $email, $phone, $hashed, $role);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User added successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB error: ' . $stmt->error]);
        }
        exit();
    }

    // ---------- UPDATE USER ----------
    if ($action === 'update_user') {
        $id = trim($_POST['id'] ?? '');
        $old_id = trim($_POST['old_id'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';

        if (empty($id) || empty($old_id) || empty($username) || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'ID, Username and Name are required.']);
            exit();
        }
        if (!in_array($role, ['user', 'super_admin', 'review_admin', 'booking_admin'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid role.']);
            exit();
        }

        // If ID changed, check new ID is not taken by another user
        if ($id !== $old_id) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND id != ?");
            $stmt->bind_param("ss", $id, $old_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'New User ID already exists.']);
                exit();
            }
        }

        // Check username uniqueness excluding current user
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param("ss", $username, $old_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already taken by another user.']);
            exit();
        }

        $email = $email ?: null;
        $phone = $phone ?: null;

        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET id=?, username=?, name=?, email=?, phone=?, password=?, role=? WHERE id=?");
            $stmt->bind_param("ssssssss", $id, $username, $name, $email, $phone, $hashed, $role, $old_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET id=?, username=?, name=?, email=?, phone=?, role=? WHERE id=?");
            $stmt->bind_param("sssssss", $id, $username, $name, $email, $phone, $role, $old_id);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
        }
        exit();
    }

    // ---------- DELETE USER with admin protection ----------
    if ($action === 'delete_user') {
        $id = trim($_POST['id'] ?? '');
        $currentUserRole = $_SESSION['role'] ?? '';
        $currentUserId = $_SESSION['user_id'];

        // Cannot delete yourself
        if ($id == $currentUserId) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own account.']);
            exit();
        }

        // Get the role of the target user
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit();
        }
        $targetRole = $result->fetch_assoc()['role'];

        // Protect super_admin accounts: only super_admin can delete other super_admin
        if ($targetRole === 'super_admin' && $currentUserRole !== 'super_admin') {
            echo json_encode(['success' => false, 'message' => 'You are not allowed to delete a Super Admin account.']);
            exit();
        }

        // Proceed with deletion
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("s", $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Delete failed or user not found.']);
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
    <title>UBook · Manage Users</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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
        .action-bar { display: flex; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .btn-primary { background: #ea580c; border: none; padding: 10px 24px; border-radius: 40px; font-weight: 600; color: white; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-primary:hover { background: #c2410c; transform: translateY(-1px); }
        .btn-outline { background: transparent; border: 1px solid #ea580c; padding: 8px 20px; border-radius: 40px; color: #ea580c; font-weight: 500; cursor: pointer; transition: 0.2s; }
        .btn-outline:hover { background: #fef3c7; }
        .users-table-container { background: white; border-radius: 28px; padding: 1rem; overflow-x: auto; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; color: #1e293b; }
        th, td { padding: 1rem 0.8rem; text-align: left; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        th { background: #f8fafc; color: #ea580c; font-weight: 600; }
        tr:hover { background: #fef3c7; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 40px; font-size: 0.75rem; font-weight: bold; background: #e2e8f0; color: #1e293b; }
        .badge.super_admin { background: #dc2626; color: white; }
        .badge.review_admin { background: #8b5cf6; color: white; }
        .badge.booking_admin { background: #06b6d4; color: white; }
        .badge.user { background: #10b981; color: white; }
        .action-icons i { font-size: 1.2rem; margin: 0 6px; cursor: pointer; transition: 0.2s; }
        .edit-icon { color: #f59e0b; }
        .delete-icon { color: #ef4444; }
        .action-icons i:hover { transform: scale(1.1); }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 1000; }
        .modal-content { background: white; max-width: 500px; width: 90%; border-radius: 32px; padding: 2rem; color: #1e293b; border: 1px solid #e2e8f0; box-shadow: 0 20px 35px rgba(0,0,0,0.2); animation: fadeUp 0.2s ease; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .modal-content h3 { color: #ea580c; margin-bottom: 1.5rem; }
        .modal-content input, .modal-content select { width: 100%; padding: 12px; margin: 8px 0 16px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 24px; color: #1e293b; outline: none; }
        .modal-content input:focus, .modal-content select:focus { border-color: #ea580c; box-shadow: 0 0 0 2px rgba(234,88,12,0.2); }
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
            <h1><i class="fas fa-users-cog"></i> UBook User Admin</h1>
            <p>Manage system users and roles</p>
        </div>
        <div style="display: flex; gap: 1rem; align-items: center;">
            <div class="user-info"><i class="fas fa-user-tie"></i> <?= $currentUser ?> (<?= htmlspecialchars($userRole) ?>)</div>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Navigation (4 links) -->
    <div class="admin-nav">
        <a href="venue.manage.php" class="nav-link"><i class="fas fa-building"></i> Venue Management</a>
        <a href="user.manage.php" class="nav-link active"><i class="fas fa-users"></i> User Management</a>
        <a href="booking_admin_main_menu.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Main Menu</a>
        <a href="manage.venue.comments.php" class="nav-link"><i class="fas fa-comments"></i> Venue Comments</a>
    </div>

    <div class="stats-card" id="userCountDisplay">
        <i class="fas fa-users"></i> Loading users...
    </div>

    <div class="action-bar">
        <button class="btn-primary" id="openAddModalBtn"><i class="fas fa-plus-circle"></i> Add New User</button>
        <button class="btn-outline" id="refreshTableBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
    </div>

    <div class="users-table-container">
        <table id="usersTable">
            <thead><tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody id="usersTableBody"><tr><td colspan="8">Loading users......</td></tr></tbody>
        </table>
    </div>
</div>

<!-- Modal Add/Edit User -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle">Add User</h3>
        <form id="userForm">
            <input type="hidden" id="oldId" name="old_id" value="">
            <label>User ID * (unique, e.g., student number)</label>
            <input type="text" id="userId" required placeholder="e.g., 1221109999">
            <label>Username *</label>
            <input type="text" id="username" required placeholder="Unique username">
            <label>Full Name *</label>
            <input type="text" id="fullname" required placeholder="Full name">
            <label>Email</label>
            <input type="email" id="email" placeholder="user@example.com">
            <label>Phone</label>
            <input type="text" id="phone" placeholder="0123456789">
            <label>Password</label>
            <input type="password" id="password" placeholder="Leave blank to keep unchanged (edit)">
            <label>Role</label>
            <select id="role">
                <option value="user">User</option>
                <option value="review_admin">Review Admin</option>
                <option value="booking_admin">Booking Admin</option>
                <option value="super_admin">Super Admin</option>
            </select>
            <div class="modal-buttons">
                <button type="button" class="btn-outline" id="closeModalBtn">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('userModal');
    const modalTitle = document.getElementById('modalTitle');
    const usersTableBody = document.getElementById('usersTableBody');
    const userCountSpan = document.getElementById('userCountDisplay');

    function showToast(message, isError = false) {
        const toast = document.createElement('div');
        toast.className = 'toast-message' + (isError ? ' toast-error' : '');
        toast.innerText = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    async function loadUsers() {
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ action: 'get_users' })
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            if (data.success && data.users) {
                renderTable(data.users);
                userCountSpan.innerHTML = `<i class="fas fa-users"></i> Total users: ${data.users.length}`;
            } else {
                usersTableBody.innerHTML = '<tr><td colspan="8">Failed to load users.</td></tr>';
                showToast('Error loading users', true);
            }
        } catch (err) {
            console.error(err);
            usersTableBody.innerHTML = '<tr><td colspan="8">Network error.</td></tr>';
            showToast('Network error: ' + err.message, true);
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

    function renderTable(users) {
        if (!users.length) {
            usersTableBody.innerHTML = '<tr><td colspan="8">No users found. Click "Add New User".</td></tr>';
            return;
        }
        let html = '';
        users.forEach(user => {
            const roleClass = user.role;
            const created = user.created_at ? new Date(user.created_at).toLocaleDateString() : '-';
            html += `<tr>
                <td>${escapeHtml(user.id)}</td>
                <td>${escapeHtml(user.username)}</td>
                <td>${escapeHtml(user.name)}</td>
                <td>${escapeHtml(user.email || '-')}</td>
                <td>${escapeHtml(user.phone || '-')}</td>
                <td><span class="badge ${roleClass}">${user.role}</span></td>
                <td>${created}</td>
                <td class="action-icons">
                    <i class="fas fa-edit edit-icon" data-id="${escapeAttr(user.id)}" data-username="${escapeAttr(user.username)}" data-name="${escapeAttr(user.name)}" data-email="${escapeAttr(user.email || '')}" data-phone="${escapeAttr(user.phone || '')}" data-role="${user.role}"></i>
                    <i class="fas fa-trash-alt delete-icon" data-id="${escapeAttr(user.id)}" data-name="${escapeAttr(user.name)}"></i>
                </td>
            </tr>`;
        });
        usersTableBody.innerHTML = html;

        document.querySelectorAll('.edit-icon').forEach(icon => {
            icon.addEventListener('click', () => {
                openEditModal(icon.dataset.id, icon.dataset.username, icon.dataset.name, icon.dataset.email, icon.dataset.phone, icon.dataset.role);
            });
        });
        document.querySelectorAll('.delete-icon').forEach(icon => {
            icon.addEventListener('click', async () => {
                if (confirm(`Delete user "${icon.dataset.name}"?`)) await deleteUser(icon.dataset.id);
            });
        });
    }

    async function deleteUser(id) {
        try {
            const resp = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ action: 'delete_user', id: id })
            });
            const data = await resp.json();
            if (data.success) {
                showToast('User deleted');
                loadUsers();
            } else {
                showToast(data.message || 'Delete failed', true);
            }
        } catch (err) {
            showToast('Delete error: ' + err.message, true);
        }
    }

    function openEditModal(id, username, name, email, phone, role) {
        document.getElementById('oldId').value = id;
        document.getElementById('userId').value = id;
        document.getElementById('username').value = username;
        document.getElementById('fullname').value = name;
        document.getElementById('email').value = (email === '-' ? '' : email);
        document.getElementById('phone').value = (phone === '-' ? '' : phone);
        document.getElementById('password').value = '';
        document.getElementById('role').value = role;
        modalTitle.innerText = 'Edit User';
        modal.style.display = 'flex';
    }

    function resetAndOpenAdd() {
        document.getElementById('oldId').value = '';
        document.getElementById('userId').value = '';
        document.getElementById('username').value = '';
        document.getElementById('fullname').value = '';
        document.getElementById('email').value = '';
        document.getElementById('phone').value = '';
        document.getElementById('password').value = '';
        document.getElementById('role').value = 'user';
        modalTitle.innerText = 'Add User';
        modal.style.display = 'flex';
    }

    document.getElementById('openAddModalBtn').addEventListener('click', resetAndOpenAdd);
    document.getElementById('closeModalBtn').addEventListener('click', () => modal.style.display = 'none');
    document.getElementById('refreshTableBtn').addEventListener('click', loadUsers);

    document.getElementById('userForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const oldId = document.getElementById('oldId').value;
        const id = document.getElementById('userId').value.trim();
        const username = document.getElementById('username').value.trim();
        const fullname = document.getElementById('fullname').value.trim();
        const email = document.getElementById('email').value.trim();
        const phone = document.getElementById('phone').value.trim();
        const password = document.getElementById('password').value;
        const role = document.getElementById('role').value;

        if (!id || !username || !fullname) {
            showToast('User ID, Username and Full name are required', true);
            return;
        }
        if (!oldId && !password) {
            showToast('Password required for new user', true);
            return;
        }

        const action = oldId ? 'update_user' : 'add_user';
        const formData = new URLSearchParams();
        formData.append('action', action);
        if (oldId) formData.append('old_id', oldId);
        formData.append('id', id);
        formData.append('username', username);
        formData.append('name', fullname);
        formData.append('email', email);
        formData.append('phone', phone);
        formData.append('role', role);
        if (password) formData.append('password', password);

        try {
            const resp = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const data = await resp.json();
            if (data.success) {
                showToast(data.message);
                modal.style.display = 'none';
                loadUsers();
            } else {
                showToast(data.message || 'Operation failed', true);
            }
        } catch (err) {
            showToast('Request error: ' + err.message, true);
        }
    });

    window.onclick = event => { if (event.target === modal) modal.style.display = 'none'; };
    loadUsers();
</script>
</body>
</html>