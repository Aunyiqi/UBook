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

// Handle AJAX requests (same as before, unchanged)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $conn = getDB();

    // ---------- GET VENUES ----------
    if ($action === 'get_venues') {
        $result = $conn->query("SELECT id, name, description, image_url FROM venues ORDER BY id ASC");
        $venues = [];
        while ($row = $result->fetch_assoc()) {
            $venues[] = $row;
        }
        echo json_encode(['success' => true, 'venues' => $venues]);
        exit();
    }

    // ---------- ADD VENUE ----------
    if ($action === 'add_venue') {
        $id = trim($_POST['id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');

        if (empty($id) || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Venue ID and Name are required.']);
            exit();
        }
        $stmt = $conn->prepare("SELECT id FROM venues WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Venue ID already exists. Use a unique code.']);
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO venues (id, name, description, image_url) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $id, $name, $description, $image_url);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Venue added successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB error: ' . $stmt->error]);
        }
        exit();
    }

    // ---------- UPDATE VENUE ----------
    if ($action === 'update_venue') {
        $old_id = trim($_POST['old_id'] ?? '');
        $id = trim($_POST['id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');

        if (empty($old_id) || empty($id) || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Venue ID and Name are required.']);
            exit();
        }

        if ($old_id !== $id) {
            $stmt = $conn->prepare("SELECT id FROM venues WHERE id = ? AND id != ?");
            $stmt->bind_param("ss", $id, $old_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'New Venue ID already exists.']);
                exit();
            }
        }

        $stmt = $conn->prepare("UPDATE venues SET id=?, name=?, description=?, image_url=? WHERE id=?");
        $stmt->bind_param("sssss", $id, $name, $description, $image_url, $old_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Venue updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
        }
        exit();
    }

    // ---------- DELETE VENUE ----------
    if ($action === 'delete_venue') {
        $id = trim($_POST['id'] ?? '');
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Invalid venue ID.']);
            exit();
        }
        $stmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE venue_id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_row()[0];
        if ($count > 0) {
            echo json_encode(['success' => false, 'message' => "Cannot delete venue: $count booking(s) exist. Delete bookings first."]);
            exit();
        }

        $stmt = $conn->prepare("DELETE FROM venues WHERE id = ?");
        $stmt->bind_param("s", $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Venue deleted successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Delete failed or venue not found.']);
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
    <title>UBook · Manage Venues</title>
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
        .venues-table-container { background: white; border-radius: 28px; padding: 1rem; overflow-x: auto; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; color: #1e293b; }
        th, td { padding: 1rem 0.8rem; text-align: left; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        th { background: #f8fafc; color: #ea580c; font-weight: 600; }
        tr:hover { background: #fef3c7; }
        .venue-image-preview { width: 60px; height: 60px; object-fit: cover; border-radius: 12px; background: #f1f5f9; }
        .action-icons i { font-size: 1.2rem; margin: 0 6px; cursor: pointer; transition: 0.2s; }
        .edit-icon { color: #f59e0b; }
        .delete-icon { color: #ef4444; }
        .action-icons i:hover { transform: scale(1.1); }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 1000; }
        .modal-content { background: white; max-width: 600px; width: 90%; border-radius: 32px; padding: 2rem; color: #1e293b; border: 1px solid #e2e8f0; box-shadow: 0 20px 35px rgba(0,0,0,0.2); animation: fadeUp 0.2s ease; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .modal-content h3 { color: #ea580c; margin-bottom: 1.5rem; }
        .modal-content input, .modal-content textarea { width: 100%; padding: 12px; margin: 8px 0 16px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 24px; color: #1e293b; outline: none; }
        .modal-content input:focus, .modal-content textarea:focus { border-color: #ea580c; box-shadow: 0 0 0 2px rgba(234,88,12,0.2); }
        .image-preview { max-width: 100%; max-height: 150px; margin: 10px 0; border-radius: 12px; display: none; border: 1px solid #e2e8f0; }
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
            <h1><i class="fas fa-building"></i> UBook Venue Admin</h1>
            <p>Manage campus venues (CRUD)</p>
        </div>
        <div style="display: flex; gap: 1rem; align-items: center;">
            <div class="user-info"><i class="fas fa-user-tie"></i> <?= $currentUser ?> (<?= htmlspecialchars($userRole) ?>)</div>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- ===== SAME NAVIGATION AS CONFLICT MANAGER (4 LINKS) ===== -->
    <div class="admin-nav">
        <a href="venue.manage.php" class="nav-link active"><i class="fas fa-building"></i> Venue Management</a>
        <a href="user.manage.php" class="nav-link"><i class="fas fa-users"></i> User Management</a>
        <a href="booking_admin_main_menu.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Main Menu</a>
        <a href="manage.venue.comments.php" class="nav-link"><i class="fas fa-comments"></i> Venue Comments</a>
    </div>

    <div class="stats-card" id="venueCountDisplay">
        <i class="fas fa-location-dot"></i> Loading venues...
    </div>

    <div class="action-bar">
        <button class="btn-primary" id="openAddModalBtn"><i class="fas fa-plus-circle"></i> Add New Venue</button>
        <button class="btn-outline" id="refreshTableBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
    </div>

    <div class="venues-table-container">
        <table id="venuesTable">
            <thead><tr><th>ID</th><th>Image</th><th>Name</th><th>Description</th><th>Image URL</th><th>Actions</th></tr></thead>
            <tbody id="venuesTableBody"><tr><td colspan="6">Loading venues...</td></tr></tbody>
        </table>
    </div>
</div>

<!-- MODAL ADD / EDIT -->
<div id="venueModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle">Add Venue</h3>
        <form id="venueForm">
            <input type="hidden" id="oldId" name="old_id" value="">
            <label>Venue ID * (unique code)</label>
            <input type="text" id="venueId" required placeholder="e.g., mainhall">
            <label>Venue Name *</label>
            <input type="text" id="venueName" required placeholder="e.g., Main Hall">
            <label>Description</label>
            <textarea id="venueDesc" placeholder="Describe the venue..."></textarea>
            <label>Image URL</label>
            <input type="url" id="venueImage" placeholder="https://example.com/photo.jpg">
            <img id="imagePreview" class="image-preview" alt="Preview">
            <div class="modal-buttons">
                <button type="button" class="btn-outline" id="closeModalBtn">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('venueModal');
    const modalTitle = document.getElementById('modalTitle');
    const venuesTableBody = document.getElementById('venuesTableBody');
    const venueCountSpan = document.getElementById('venueCountDisplay');

    function showToast(message, isError = false) {
        const toast = document.createElement('div');
        toast.className = 'toast-message' + (isError ? ' toast-error' : '');
        toast.innerText = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    async function loadVenues() {
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ action: 'get_venues' })
            });
            const data = await response.json();
            if (data.success && data.venues) {
                renderTable(data.venues);
                venueCountSpan.innerHTML = `<i class="fas fa-location-dot"></i> Total venues: ${data.venues.length}`;
            } else {
                venuesTableBody.innerHTML = '<tr><td colspan="6">Failed to load venues.</td></tr>';
                showToast('Error loading venues', true);
            }
        } catch (err) {
            console.error(err);
            venuesTableBody.innerHTML = '<tr><td colspan="6">Network error.</td></tr>';
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

    function renderTable(venues) {
        if (!venues.length) {
            venuesTableBody.innerHTML = '<tr><td colspan="6">No venues found. Click "Add New Venue".</td></tr>';
            return;
        }
        let html = '';
        venues.forEach(venue => {
            const imageUrl = venue.image_url ? escapeHtml(venue.image_url) : '';
            html += `<tr>
                <td>${escapeHtml(venue.id)}</td>
                <td>${imageUrl ? `<img src="${imageUrl}" class="venue-image-preview" onerror="this.src='https://placehold.co/60x60?text=No+Image'">` : '—'}</td>
                <td><strong>${escapeHtml(venue.name)}</strong></td>
                <td>${escapeHtml(venue.description || '')}</td>
                <td>${imageUrl ? `<a href="${imageUrl}" target="_blank" style="color:#ea580c;">View</a>` : '—'}</td>
                <td class="action-icons">
                    <i class="fas fa-edit edit-icon" data-id="${escapeAttr(venue.id)}" data-name="${escapeAttr(venue.name)}" data-desc="${escapeAttr(venue.description || '')}" data-img="${escapeAttr(venue.image_url || '')}"></i>
                    <i class="fas fa-trash-alt delete-icon" data-id="${escapeAttr(venue.id)}" data-name="${escapeAttr(venue.name)}"></i>
                </td>
            </tr>`;
        });
        venuesTableBody.innerHTML = html;

        document.querySelectorAll('.edit-icon').forEach(icon => {
            icon.addEventListener('click', () => {
                openEditModal(icon.dataset.id, icon.dataset.name, icon.dataset.desc, icon.dataset.img);
            });
        });
        document.querySelectorAll('.delete-icon').forEach(icon => {
            icon.addEventListener('click', async () => {
                if (confirm(`Delete venue "${icon.dataset.name}"? This will also remove all related bookings and comments. Are you sure?`)) {
                    await deleteVenue(icon.dataset.id);
                }
            });
        });
    }

    async function deleteVenue(id) {
        try {
            const resp = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ action: 'delete_venue', id: id })
            });
            const data = await resp.json();
            if (data.success) {
                showToast('Venue deleted successfully');
                loadVenues();
            } else {
                showToast(data.message || 'Delete failed', true);
            }
        } catch (err) {
            showToast('Delete error', true);
        }
    }

    function openEditModal(id, name, desc, img) {
        document.getElementById('oldId').value = id;
        document.getElementById('venueId').value = id;
        document.getElementById('venueName').value = name;
        document.getElementById('venueDesc').value = desc;
        document.getElementById('venueImage').value = img;
        updateImagePreview(img);
        modalTitle.innerText = 'Edit Venue';
        modal.style.display = 'flex';
    }

    function resetAndOpenAdd() {
        document.getElementById('oldId').value = '';
        document.getElementById('venueId').value = '';
        document.getElementById('venueName').value = '';
        document.getElementById('venueDesc').value = '';
        document.getElementById('venueImage').value = '';
        document.getElementById('imagePreview').style.display = 'none';
        modalTitle.innerText = 'Add Venue';
        modal.style.display = 'flex';
    }

    function updateImagePreview(url) {
        const preview = document.getElementById('imagePreview');
        if (url && url.trim() !== '') {
            preview.src = url;
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
    }

    document.getElementById('openAddModalBtn').addEventListener('click', resetAndOpenAdd);
    document.getElementById('closeModalBtn').addEventListener('click', () => modal.style.display = 'none');
    document.getElementById('refreshTableBtn').addEventListener('click', loadVenues);
    document.getElementById('venueImage').addEventListener('input', (e) => updateImagePreview(e.target.value));

    document.getElementById('venueForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const oldId = document.getElementById('oldId').value;
        const id = document.getElementById('venueId').value.trim();
        const name = document.getElementById('venueName').value.trim();
        const description = document.getElementById('venueDesc').value.trim();
        const image_url = document.getElementById('venueImage').value.trim();

        if (!id || !name) {
            showToast('Venue ID and Name are required', true);
            return;
        }

        const action = oldId ? 'update_venue' : 'add_venue';
        const formData = new URLSearchParams();
        formData.append('action', action);
        if (oldId) formData.append('old_id', oldId);
        formData.append('id', id);
        formData.append('name', name);
        formData.append('description', description);
        formData.append('image_url', image_url);

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
                loadVenues();
            } else {
                showToast(data.message || 'Operation failed', true);
            }
        } catch (err) {
            showToast('Request error', true);
        }
    });

    window.onclick = function(event) {
        if (event.target === modal) modal.style.display = 'none';
    };

    loadVenues();
</script>
</body>
</html>