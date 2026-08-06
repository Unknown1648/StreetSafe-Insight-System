<?php
require_once '../config.php';

session_start();

function renderPage($message = '', $error = '') {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Police Account Provisioning</title><style>body{font-family:Arial,sans-serif;max-width:820px;margin:40px auto;padding:0 20px;line-height:1.5}input,textarea,select,button{width:100%;padding:10px;margin:6px 0 14px;border:1px solid #ccc;border-radius:8px}button{background:#1e3c72;color:#fff;border:none;cursor:pointer} .card{background:#f8fafc;padding:24px;border-radius:14px;border:1px solid #e2e8f0} .ok{color:#166534} .err{color:#b91c1c}</style></head><body>';
    echo '<div class="card">';
    echo '<h1>Police Account Provisioning</h1>';
    echo '<p>This page is for admin office staff to create police accounts. Police officers should visit the nearest admin office for access credentials.</p>';
    if ($message) {
        echo '<p class="ok">' . htmlspecialchars($message) . '</p>';
    }
    if ($error) {
        echo '<p class="err">' . htmlspecialchars($error) . '</p>';
    }
    echo '<form method="post">';
    echo '<input type="hidden" name="action" value="create">';
    echo '<label>Full Name</label><input name="full_name" required>'; 
    echo '<label>Badge Number</label><input name="badge_number" required>'; 
    echo '<label>Email</label><input name="email" type="email" required>'; 
    echo '<label>Station</label><input name="station" required>'; 
    echo '<label>Temporary Password</label><input name="password" type="password" required>'; 
    echo '<button type="submit">Create Police Account</button></form>';
    echo '<p><a href="../login.html">Go to login</a> | <a href="../index.html">Public dashboard</a></p>';
    echo '</div></body></html>';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    renderPage();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action !== 'create') {
        renderPage('', 'Invalid action');
        exit;
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $badgeNumber = trim($_POST['badge_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $station = trim($_POST['station'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($fullName === '' || $badgeNumber === '' || $email === '' || $station === '' || $password === '') {
        renderPage('', 'All fields are required');
        exit;
    }

    $check = $conn->prepare("SELECT id FROM accounts WHERE badge_number = ? OR email = ? LIMIT 1");
    $check->bind_param('ss', $badgeNumber, $email);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    if ($existing) {
        renderPage('', 'An account with that badge number or email already exists');
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO accounts (role, full_name, email, badge_number, password_hash, station, status) VALUES ('police', ?, ?, ?, ?, ?, 'active')");
    $stmt->bind_param('sssss', $fullName, $email, $badgeNumber, $passwordHash, $station);

    if ($stmt->execute()) {
        renderPage('Police account created successfully. Share the badge number and temporary password securely with the officer.');
    } else {
        renderPage('', 'Failed to create account: ' . $stmt->error);
    }

    $stmt->close();
    exit;
}

http_response_code(405);
echo 'Method not allowed';
