
<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Only super_admin or review_admin can access
$allowedRoles = ['review_admin', 'super_admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowedRoles)) {
    header('Location: login.php');
    exit();
}

// Note: In a production environment, it is highly recommended to store API keys in an environment variable (.env) rather than hardcoding them.
define('DEEPSEEK_API_KEY', '');

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

// ==================== AI CONTEXT ====================
function buildReviewContext($conn) {
    $sql = "SELECT id, name, rating, review, created_at FROM reviews ORDER BY created_at DESC";
    $result = $conn->query($sql);
    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }

    $total = count($reviews);
    $avg = $total ? array_sum(array_column($reviews, 'rating')) / $total : 0;
    $dist = [1=>0,2=>0,3=>0,4=>0,5=>0];
    foreach ($reviews as $r) $dist[(int)$r['rating']]++;

    $context = "All reviews (most recent first):\n";
    foreach ($reviews as $r) {
        $context .= sprintf("ID:%d | Name:%s | Rating:%d/5 | Review:\"%s\" | Date:%s\n",
            $r['id'], $r['name'], $r['rating'], $r['review'], $r['created_at']);
    }

    $context .= "\n--- STATISTICS ---\n";
    $context .= "Total reviews: $total\n";
    $context .= "Average rating: " . number_format($avg, 2) . "/5\n";
    $context .= "Distribution: 1★: {$dist[1]}, 2★: {$dist[2]}, 3★: {$dist[3]}, 4★: {$dist[4]}, 5★: {$dist[5]}\n";

    $recent = array_slice($reviews, 0, 10);
    $context .= "\n--- RECENT REVIEWS (latest 10) ---\n";
    foreach ($recent as $r) {
        $context .= sprintf("ID:%d | %s (%d★) - \"%s\"\n",
            $r['id'], $r['name'], $r['rating'], $r['review']);
    }

    return $context;
}

function askDeepSeek($userQuestion, $context) {
    $apiKey = DEEPSEEK_API_KEY;
    $url = 'https://api.deepseek.com/v1/chat/completions';
    
    // Explicit instructions for table and markdown formatting
    $systemPrompt = "You are an AI assistant for UBook, helping review administrators analyze customer feedback. 
    You have data about all reviews, including rating, reviewer name, comment text, and date.
    Your task is to provide insightful analysis, detect trends, highlight common praises and complaints, and suggest actionable improvements.
    When summarizing data, categorizing feedback, or comparing ratings, ALWAYS output the information using well-formatted Markdown tables.
    Be concise but thorough, and use the data provided; do not make up facts.";

    $messages = [
        ['role' => 'system', 'content' => $systemPrompt . "\n\n" . $context],
        ['role' => 'user', 'content' => $userQuestion]
    ];
    
    $payload = ['model' => 'deepseek-chat', 'messages' => $messages, 'temperature' => 0.7, 'max_tokens' => 1500];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($response === false) return "AI service connection error: " . $curlError;
    if ($httpCode !== 200) return "AI service temporarily unavailable. Error Code: " . $httpCode;
    
    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? "I couldn't understand that.";
}

// ==================== AJAX HANDLERS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $conn = getDB();

    if ($action === 'get_reviews') {
        $result = $conn->query("SELECT id, name, rating, review, created_at FROM reviews ORDER BY created_at DESC");
        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
        $countResult = $conn->query("SELECT rating, COUNT(*) as count FROM reviews GROUP BY rating ORDER BY rating");
        $ratingCounts = [1=>0,2=>0,3=>0,4=>0,5=>0];
        while ($row = $countResult->fetch_assoc()) {
            $ratingCounts[(int)$row['rating']] = (int)$row['count'];
        }
        echo json_encode(['success' => true, 'reviews' => $reviews, 'ratingCounts' => $ratingCounts]);
        exit();
    }

    if ($action === 'ai_query') {
        $question = trim($_POST['question'] ?? '');
        if (empty($question)) {
            echo json_encode(['success' => false, 'message' => 'Empty question']);
            exit();
        }
        $context = buildReviewContext($conn);
        $answer = askDeepSeek($question, $context);
        echo json_encode(['success' => true, 'answer' => $answer]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

$currentUser = htmlspecialchars($_SESSION['name'] ?? $_SESSION['username'] ?? 'Admin');
$userRole = $_SESSION['role'] ?? 'Super Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UBook · Admin: Review Analytics</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js"></script>

    <style>
        /* ==================== GLOBAL & THEME ==================== */
        :root {
            --bg-color: #0f172a;
            --bg-secondary: #1e293b;
            --primary: #f97316;
            --primary-hover: #ea580c;
            --ai-color: #3b82f6; /* Distinct blue for the AI */
            --text-main: #e2e8f0;
            --text-muted: #94a3b8;
            --border-color: rgba(249, 115, 22, 0.2);
            --glass-bg: rgba(30, 41, 59, 0.7);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', 'Segoe UI', Roboto, sans-serif; }
        
        body { 
            background: var(--bg-color); 
            color: var(--text-main);
            min-height: 100vh; 
        }

        body::before { 
            content: ""; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: radial-gradient(circle at 20% 30%, #1e293b, #0f172a); 
            z-index: -2; 
        }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary); }

        .admin-wrapper { max-width: 1500px; margin: 0 auto; padding: 2rem 1.5rem; position: relative; z-index: 2; }
        
        /* ==================== HEADER & NAV ==================== */
        .admin-header { 
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; 
            background: rgba(255,255,255,0.03); backdrop-filter: blur(12px); 
            padding: 1.2rem 2.5rem; border-radius: 1.5rem; margin-bottom: 1.5rem; 
            border: 1px solid var(--border-color); box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .logo-area h1 { color: var(--primary); font-size: 1.8rem; font-weight: 700; display: flex; align-items: center; gap: 10px;}
        .logo-area p { color: var(--text-muted); font-size: 0.9rem; margin-top: 4px; }
        
        .user-info { background: var(--bg-secondary); padding: 0.6rem 1.2rem; border-radius: 2rem; display: flex; align-items: center; gap: 12px; font-weight: 500;}
        .logout-btn { background: var(--primary); border: none; padding: 0.6rem 1.5rem; border-radius: 40px; color: white; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; transition: 0.3s;}
        .logout-btn:hover { background: var(--primary-hover); transform: translateY(-2px);}
        
        .admin-nav { background: var(--glass-bg); backdrop-filter: blur(12px); border-radius: 1.5rem; padding: 0.8rem; margin-bottom: 2rem; border: 1px solid var(--border-color); }
        .nav-menu { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.5rem; list-style: none; }
        .nav-link { display: flex; align-items: center; gap: 8px; padding: 0.8rem 1.5rem; border-radius: 2rem; color: #cbd5e1; text-decoration: none; font-weight: 500; transition: all 0.3s ease; }
        .nav-link i { font-size: 1.2rem; }
        .nav-link:hover { background: rgba(249,115,22,0.1); color: var(--primary); }
        .nav-link.active { background: linear-gradient(135deg, var(--primary), var(--primary-hover)); color: white; box-shadow: 0 4px 15px rgba(249,115,22,0.3); }

        /* ==================== DASHBOARD CONTENT ==================== */
        .dashboard-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; margin-bottom: 2rem; }
        @media (max-width: 1024px) { .dashboard-grid { grid-template-columns: 1fr; } }

        .glass-card { background: var(--glass-bg); backdrop-filter: blur(10px); border-radius: 1.5rem; padding: 1.5rem; border: 1px solid var(--border-color); box-shadow: 0 8px 32px rgba(0,0,0,0.15); }
        .stats-list { display: flex; flex-direction: column; gap: 1rem; }
        .stat-item { background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 1rem; display: flex; justify-content: space-between; align-items: center; border-left: 4px solid var(--primary); }
        .stat-item.critical { border-left-color: #ef4444; }
        .stat-item.success { border-left-color: #10b981; }
        .stat-item h4 { color: var(--text-muted); font-weight: 500; }
        .stat-item .val { font-size: 1.5rem; font-weight: 700; color: var(--text-main); }
        .stat-item .val.highlight { color: var(--primary); }

        .chart-container canvas { width: 100% !important; max-height: 350px !important; }
        
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .btn-outline { background: transparent; border: 1.5px solid var(--primary); padding: 0.6rem 1.5rem; border-radius: 30px; color: var(--primary); font-weight: 600; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 8px;}
        .btn-outline:hover { background: rgba(249,115,22,0.1); transform: translateY(-2px); }
        
        .table-responsive { overflow-x: auto; border-radius: 1rem; border: 1px solid rgba(255,255,255,0.05); }
        table { width: 100%; border-collapse: collapse; background: rgba(0,0,0,0.2); }
        th, td { padding: 1.2rem 1rem; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; }
        th { background: #0f172a; color: var(--primary); font-weight: 600; position: sticky; top: 0; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; }
        tr:hover { background: rgba(249,115,22,0.05); }
        .stars { color: #fbbf24; letter-spacing: 2px; }
        .review-text { max-width: 400px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* ==================== TOASTS ==================== */
        .toast-message { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: #10b981; color: white; padding: 12px 24px; border-radius: 60px; font-weight: bold; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.5); animation: fadeUpOut 3s forwards; }
        .toast-error { background: #ef4444; }
        @keyframes fadeUpOut { 0% { opacity: 0; transform: translate(-50%, 20px); } 15% { opacity: 1; transform: translate(-50%, 0); } 85% { opacity: 1; } 100% { opacity: 0; visibility: hidden; } }

        /* ==================== ENHANCED AI CHAT ==================== */
        .ai-chat-btn { 
            position: fixed; bottom: 40px; right: 40px; 
            background: linear-gradient(135deg, var(--ai-color), #2563eb); 
            width: 70px; height: 70px; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; 
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4), 0 0 0 4px rgba(15, 23, 42, 0.5); 
            cursor: pointer; z-index: 1000; border: none; color: white; font-size: 32px; 
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
        }
        .ai-chat-btn:hover { transform: scale(1.1) translateY(-5px); box-shadow: 0 15px 35px rgba(59, 130, 246, 0.6), 0 0 0 4px rgba(15, 23, 42, 0.5); }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); backdrop-filter: blur(8px); z-index: 2000; justify-content: center; align-items: center; padding: 20px; opacity: 0; transition: opacity 0.3s ease; }
        .modal.show { display: flex; opacity: 1; }
        
        .modal-content { 
            background: var(--bg-color); border-radius: 24px; width: 1000px; max-width: 95%; height: 85vh; 
            border: 1px solid rgba(59, 130, 246, 0.3); display: flex; flex-direction: column; 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8); overflow: hidden;
            transform: scale(0.95); transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            background-image: radial-gradient(circle at top right, rgba(59, 130, 246, 0.05), transparent 40%);
        }
        .modal.show .modal-content { transform: scale(1); }
        
        .modal-header { 
            padding: 1.5rem 2rem; border-bottom: 1px solid var(--bg-secondary); 
            background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(10px);
            display: flex; justify-content: space-between; align-items: center; z-index: 10;
        }
        .modal-header h3 { color: var(--ai-color); font-size: 1.4rem; display: flex; align-items: center; gap: 12px; }
        .modal-header h3 i { font-size: 1.8rem; filter: drop-shadow(0 0 8px rgba(59,130,246,0.5)); }
        
        .close-modal { background: var(--bg-secondary); border: none; font-size: 1.2rem; width: 40px; height: 40px; border-radius: 50%; display: flex; justify-content: center; align-items: center; cursor: pointer; color: var(--text-muted); transition: 0.3s; }
        .close-modal:hover { background: #ef4444; color: white; transform: rotate(90deg); }
        
        .chat-messages { flex: 1; padding: 2rem; display: flex; flex-direction: column; gap: 1.5rem; overflow-y: auto; scroll-behavior: smooth; }
        
        /* Message Layout with Avatars */
        .msg-row { display: flex; gap: 16px; align-items: flex-end; width: 100%; opacity: 0; animation: fadeInUp 0.4s forwards; }
        .msg-row.user { flex-direction: row-reverse; }
        
        @keyframes fadeInUp { to { opacity: 1; transform: translateY(0); } from { opacity: 0; transform: translateY(20px); } }

        .msg-avatar { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; color: white; }
        .ai-avatar { background: linear-gradient(135deg, var(--ai-color), #2563eb); box-shadow: 0 0 15px rgba(59, 130, 246, 0.4); }
        .user-avatar { background: linear-gradient(135deg, var(--primary), var(--primary-hover)); box-shadow: 0 0 15px rgba(249, 115, 22, 0.4); }
        
        .message-bubble { padding: 1.2rem 1.5rem; border-radius: 20px; max-width: 80%; line-height: 1.6; font-size: 1rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); word-wrap: break-word; }
        
        .user-message { background: var(--bg-secondary); color: var(--text-main); border-bottom-right-radius: 4px; border: 1px solid rgba(255,255,255,0.05); }
        .ai-message { background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); color: var(--text-main); border-bottom-left-radius: 4px; }
        
        /* Markdown Styling inside AI Message */
        .ai-message p { margin-bottom: 1rem; }
        .ai-message p:last-child { margin-bottom: 0; }
        .ai-message ul, .ai-message ol { margin-bottom: 1rem; padding-left: 2rem; }
        .ai-message li { margin-bottom: 0.5rem; }
        .ai-message strong { color: #60a5fa; }
        .ai-message h1, .ai-message h2, .ai-message h3, .ai-message h4 { color: var(--ai-color); margin: 1.5rem 0 1rem 0; font-weight: 600; }
        .ai-message h1:first-child, .ai-message h2:first-child, .ai-message h3:first-child { margin-top: 0; }
        
        /* Markdown Tables - Extremely Important */
        .ai-message table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; background: var(--bg-color); border-radius: 12px; overflow: hidden; border-style: hidden; box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.2); }
        .ai-message th, .ai-message td { border: 1px solid rgba(255,255,255,0.05); padding: 1rem; text-align: left; }
        .ai-message th { background-color: rgba(59, 130, 246, 0.15); color: #60a5fa; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; }
        .ai-message tr:nth-child(even) { background-color: rgba(255,255,255,0.02); }
        .ai-message tr:hover { background-color: rgba(59, 130, 246, 0.05); }

        .chat-input-area { display: flex; padding: 1.5rem 2rem; gap: 16px; border-top: 1px solid var(--bg-secondary); background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(10px); align-items: center; z-index: 10; }
        
        .input-wrapper { flex: 1; position: relative; display: flex; align-items: center; }
        .input-wrapper input { width: 100%; padding: 1.2rem 1.5rem; padding-right: 4rem; border-radius: 50px; border: 1px solid #334155; background: var(--bg-color); color: white; outline: none; font-size: 1rem; transition: 0.3s; box-shadow: inset 0 2px 4px rgba(0,0,0,0.2); }
        .input-wrapper input:focus { border-color: var(--ai-color); box-shadow: inset 0 2px 4px rgba(0,0,0,0.2), 0 0 0 4px rgba(59, 130, 246, 0.15); }
        
        .send-btn { position: absolute; right: 8px; background: var(--ai-color); border: none; width: 44px; height: 44px; border-radius: 50%; color: white; font-size: 1.2rem; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; }
        .send-btn:hover { background: #2563eb; transform: scale(1.05); }
        .send-btn:disabled { background: #475569; cursor: not-allowed; transform: none; }

        /* Typing indicator */
        .typing-indicator { display: inline-flex; align-items: center; gap: 5px; padding: 0.5rem 0; }
        .dot { width: 8px; height: 8px; background: #60a5fa; border-radius: 50%; animation: typingBounce 1.4s infinite ease-in-out both; }
        .dot:nth-child(1) { animation-delay: -0.32s; }
        .dot:nth-child(2) { animation-delay: -0.16s; }
        @keyframes typingBounce { 0%, 80%, 100% { transform: scale(0); opacity: 0.4; } 40% { transform: scale(1); opacity: 1; } }
    </style>
</head>
<body>
<div class="admin-wrapper">
    
    <div class="admin-header">
        <div class="logo-area">
            <h1><i class="fas fa-chart-pie"></i> Review Analytics</h1>
            <p>Advanced Dashboard & AI Insights</p>
        </div>
        <div style="display: flex; gap: 1rem; align-items: center;">
            <div class="user-info"><i class="fas fa-user-shield"></i> <?= $currentUser ?> (<?= htmlspecialchars($userRole) ?>)</div>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="admin-nav">
        <ul class="nav-menu">
            <li><a href="review_admin_main_menu.php" class="nav-link active"><i class="fas fa-star"></i> Reviews</a></li>
            <li><a href="group.manage.php" class="nav-link"><i class="fas fa-users"></i> Groups</a></li>
            <li><a href="like.manage.php" class="nav-link"><i class="fas fa-heart"></i> Likes</a></li>
            <li><a href="manage.group.participants.php" class="nav-link"><i class="fas fa-user-plus"></i> Participants</a></li>
            <li><a href="group.message.php" class="nav-link"><i class="fas fa-comment-dots"></i> Messages</a></li>
        </ul>
    </div>

    <div class="dashboard-grid">
        <div class="glass-card">
            <h3 style="margin-bottom: 1.5rem; color: var(--primary);"><i class="fas fa-chart-line"></i> Overview Stats</h3>
            <div class="stats-list" id="reviewStats">
                <div class="stat-item"><h4>Total Reviews</h4> <span class="val">--</span></div>
                <div class="stat-item"><h4>Average Rating</h4> <span class="val highlight">-- ★</span></div>
                <div class="stat-item"><h4>5-Star Feedback</h4> <span class="val">--</span></div>
            </div>
        </div>

        <div class="glass-card chart-container">
            <h3 style="margin-bottom: 1rem; color: var(--primary);"><i class="fas fa-chart-bar"></i> Rating Distribution</h3>
            <canvas id="ratingChart"></canvas>
        </div>
    </div>

    <div class="glass-card" style="padding-top: 1rem;">
        <div class="table-header">
            <h3 style="color: var(--primary);"><i class="fas fa-list-alt"></i> Recent Reviews</h3>
            <button class="btn-outline" id="refreshBtn"><i class="fas fa-sync-alt"></i> Refresh Data</button>
        </div>
        <div class="table-responsive">
            <table id="reviewsTable">
                <thead>
                    <tr><th>ID</th><th>User</th><th>Rating</th><th>Review Content</th><th>Date</th></tr>
                </thead>
                <tbody id="reviewsTableBody">
                    <tr><td colspan="5" style="text-align:center; padding: 3rem;"><i class="fas fa-circle-notch fa-spin fa-2x" style="color: var(--primary);"></i><br><br>Loading reviews...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<button class="ai-chat-btn" id="openAIChatBtn" title="Ask AI for Insights"><i class="fas fa-robot"></i></button>

<div id="aiChatModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-robot"></i> DeepSeek AI Analyst</h3>
            <button class="close-modal" id="closeAIChatBtn" title="Close Chat"><i class="fas fa-times"></i></button>
        </div>
        
        <div class="chat-messages" id="chatMessages">
            <div class="msg-row ai" style="animation-delay: 0.1s;">
                <div class="msg-avatar ai-avatar"><i class="fas fa-robot"></i></div>
                <div class="message-bubble ai-message">
                    <p>👋 Hello! I am your AI Review Analyst.</p>
                    <p>I have analyzed all the customer feedback in the database. I can summarize trends, pinpoint issues, and generate comparison tables.</p>
                    <p>Try asking me:</p>
                    <ul>
                        <li><em>"Generate a table summarizing the top complaints from 1 and 2-star reviews."</em></li>
                        <li><em>"What are the most praised features in our 5-star reviews?"</em></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="chat-input-area">
            <div class="input-wrapper">
                <input type="text" id="chatInput" placeholder="Ask your AI analyst about review trends, statistics, or summaries..." autocomplete="off">
                <button id="sendChatBtn" class="send-btn" title="Send Message"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize Markdown Parser settings
    marked.setOptions({ breaks: true, gfm: true });

    let ratingChart = null;
    const reviewsBody = document.getElementById('reviewsTableBody');
    const reviewStats = document.getElementById('reviewStats');

    function showToast(message, isError = false) {
        const toast = document.createElement('div');
        toast.className = 'toast-message' + (isError ? ' toast-error' : '');
        toast.innerText = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    async function loadData() {
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ action: 'get_reviews' })
            });
            const data = await response.json();
            if (data.success && data.reviews) {
                renderTable(data.reviews);
                updateStats(data.reviews, data.ratingCounts);
                updateChart(data.ratingCounts);
            } else {
                reviewsBody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:2rem;">Failed to load reviews.</td></tr>';
                showToast('Error loading data', true);
            }
        } catch (err) {
            console.error(err);
            reviewsBody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:2rem;">Network error.</td></tr>';
            showToast('Network error', true);
        }
    }

    function updateStats(reviews, ratingCounts) {
        const total = reviews.length;
        const avg = reviews.reduce((sum, r) => sum + parseInt(r.rating), 0) / (total || 1);
        const criticalCount = ratingCounts[1] + ratingCounts[2];
        
        reviewStats.innerHTML = `
            <div class="stat-item"><h4>Total Reviews</h4> <span class="val">${total}</span></div>
            <div class="stat-item"><h4>Average Rating</h4> <span class="val highlight">${avg.toFixed(2)} ★</span></div>
            <div class="stat-item success"><h4>5-Star Feedback</h4> <span class="val" style="color:#10b981">${ratingCounts[5]}</span></div>
            <div class="stat-item critical"><h4>Critical (1-2 Star)</h4> <span class="val" style="color:#ef4444">${criticalCount}</span></div>
        `;
    }

    function updateChart(ratingCounts) {
        const ctx = document.getElementById('ratingChart').getContext('2d');
        if (ratingChart) ratingChart.destroy();
        
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.font.family = 'Inter, sans-serif';

        ratingChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'],
                datasets: [{
                    label: 'Reviews',
                    data: [ratingCounts[1], ratingCounts[2], ratingCounts[3], ratingCounts[4], ratingCounts[5]],
                    backgroundColor: ['#ef4444', '#f97316', '#eab308', '#22c55e', '#10b981'],
                    borderRadius: 6,
                    barThickness: 40
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { backgroundColor: '#1e293b', titleColor: '#f97316', bodyColor: '#e2e8f0', padding: 12, cornerRadius: 8 }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(255,255,255,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m]);
    }

    function renderStars(rating) {
        let full = Math.floor(rating);
        let stars = '';
        for (let i = 1; i <= 5; i++) {
            if (i <= full) stars += '<i class="fas fa-star"></i>';
            else stars += '<i class="far fa-star"></i>';
        }
        return `<span class="stars">${stars}</span>`;
    }

    function renderTable(reviews) {
        if (!reviews.length) {
            reviewsBody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:2rem;">No reviews found.</td></tr>';
            return;
        }
        let html = '';
        reviews.forEach(review => {
            const created = new Date(review.created_at).toLocaleString();
            html += `<tr>
                <td>#${review.id}</td>
                <td style="font-weight:600">${escapeHtml(review.name)}</td>
                <td>${renderStars(review.rating)}</td>
                <td class="review-text" title="${escapeHtml(review.review)}">${escapeHtml(review.review)}</td>
                <td style="color:var(--text-muted); font-size:0.9rem">${created}</td>
            </tr>`;
        });
        reviewsBody.innerHTML = html;
    }

    document.getElementById('refreshBtn').addEventListener('click', loadData);

    // ==================== AI CHAT LOGIC ====================
    const aiModal = document.getElementById('aiChatModal');
    const chatInput = document.getElementById('chatInput');
    const chatMessages = document.getElementById('chatMessages');
    const sendBtn = document.getElementById('sendChatBtn');

    function openChatModal() { aiModal.classList.add('show'); chatInput.focus(); }
    function closeChatModal() { aiModal.classList.remove('show'); }
    
    document.getElementById('openAIChatBtn').onclick = openChatModal;
    document.getElementById('closeAIChatBtn').onclick = closeChatModal;
    
    // Close on outside click
    aiModal.addEventListener('click', e => {
        if (e.target === aiModal) closeChatModal();
    });

    // Helper function to append message to chat
    function appendMessage(role, contentHTML) {
        const row = document.createElement('div');
        row.className = `msg-row ${role}`;
        
        const avatarIcon = role === 'ai' ? '<i class="fas fa-robot"></i>' : '<i class="fas fa-user"></i>';
        
        row.innerHTML = `
            <div class="msg-avatar ${role}-avatar">${avatarIcon}</div>
            <div class="message-bubble ${role}-message">${contentHTML}</div>
        `;
        
        chatMessages.appendChild(row);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        return row;
    }

    async function sendAIQuery() {
        let q = chatInput.value.trim();
        if (!q) return;
        
        // Disable input while processing
        chatInput.value = '';
        chatInput.disabled = true;
        sendBtn.disabled = true;
        
        // User Message
        appendMessage('user', escapeHtml(q));
        
        // Typing Indicator
        const typingRow = appendMessage('ai', '<div class="typing-indicator"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div>');

        try {
            let resp = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ action: 'ai_query', question: q })
            });
            let data = await resp.json();
            
            // Remove typing indicator
            chatMessages.removeChild(typingRow);
            
            if (data.success) {
                // Parse markdown and sanitize HTML
                const rawHtml = marked.parse(data.answer);
                const safeHtml = DOMPurify.sanitize(rawHtml);
                appendMessage('ai', safeHtml);
            } else {
                appendMessage('ai', '<span style="color:#ef4444">Error: ' + escapeHtml(data.message) + '</span>');
            }
            
        } catch (e) {
            chatMessages.removeChild(typingRow);
            appendMessage('ai', '<span style="color:#ef4444"><i class="fas fa-exclamation-triangle"></i> Network error occurred while contacting the AI server.</span>');
        } finally {
            // Re-enable input
            chatInput.disabled = false;
            sendBtn.disabled = false;
            chatInput.focus();
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }

    sendBtn.addEventListener('click', sendAIQuery);
    chatInput.addEventListener('keypress', e => { if (e.key === 'Enter') sendAIQuery(); });

    // Initial Load
    loadData();
</script>
</body>
</html>
