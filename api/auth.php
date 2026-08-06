<?php
/**
 * Role-based authentication endpoint
 * Community members can register and login.
 * Police accounts must be created by an admin office.
 */

require_once '../config.php';

session_start();

function jsonResponse($payload, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function getInputData() {
    $input = json_decode(file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}

function getSessionUser() {
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'role' => $_SESSION['role'],
        'full_name' => $_SESSION['full_name'] ?? null,
        'display_name' => $_SESSION['display_name'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'phone' => $_SESSION['phone'] ?? null,
        'badge_number' => $_SESSION['badge_number'] ?? null,
        'station' => $_SESSION['station'] ?? null,
        'neighborhood' => $_SESSION['neighborhood'] ?? null,
        'address' => $_SESSION['address'] ?? null,
        'personal_details_public' => isset($_SESSION['personal_details_public']) ? (bool) $_SESSION['personal_details_public'] : false,
        'profile_note' => $_SESSION['profile_note'] ?? null
    ];
}

function loadAccountById($conn, $accountId) {
    $stmt = $conn->prepare("SELECT id, role, full_name, display_name, email, phone, badge_number, station, neighborhood, address, personal_details_public, profile_note, status FROM accounts WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $result = $stmt->get_result();
    $account = $result->fetch_assoc();
    $stmt->close();
    return $account ?: null;
}

function createSessionForAccount($account) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $account['id'];
    $_SESSION['role'] = $account['role'];
    $_SESSION['full_name'] = $account['full_name'];
    $_SESSION['display_name'] = $account['display_name'];
    $_SESSION['email'] = $account['email'];
    $_SESSION['phone'] = $account['phone'];
    $_SESSION['badge_number'] = $account['badge_number'];
    $_SESSION['station'] = $account['station'];
    $_SESSION['neighborhood'] = $account['neighborhood'];
    $_SESSION['address'] = $account['address'];
    $_SESSION['personal_details_public'] = (bool) $account['personal_details_public'];
    $_SESSION['profile_note'] = $account['profile_note'];
    $_SESSION['login_time'] = time();
}

function findAccountForLogin($conn, $role, $identifier) {
    if ($role === 'police') {
        $stmt = $conn->prepare("SELECT * FROM accounts WHERE role = 'police' AND status = 'active' AND (badge_number = ? OR email = ?) LIMIT 1");
        $stmt->bind_param('ss', $identifier, $identifier);
    } else {
        $stmt = $conn->prepare("SELECT * FROM accounts WHERE role = 'community' AND status = 'active' AND (email = ? OR phone = ?) LIMIT 1");
        $stmt->bind_param('ss', $identifier, $identifier);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $account = $result->fetch_assoc();
    $stmt->close();
    return $account ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'check';

    if ($action === 'check') {
        $account = getSessionUser();
        if (!$account) {
            jsonResponse(['success' => false, 'authenticated' => false], 401);
        }

        $freshAccount = loadAccountById($conn, $account['id']);
        if (!$freshAccount || $freshAccount['status'] !== 'active') {
            session_destroy();
            jsonResponse(['success' => false, 'authenticated' => false], 401);
        }

        jsonResponse([
            'success' => true,
            'authenticated' => true,
            'user' => $freshAccount
        ]);
    }

    jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$data = array_merge($_POST, getInputData());
$action = $data['action'] ?? '';

if ($action === 'register_community') {
    $fullName = trim($data['full_name'] ?? '');
    $displayName = trim($data['display_name'] ?? '');
    $email = trim($data['email'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $password = $data['password'] ?? '';

    if ($email === '' && $phone === '') {
        jsonResponse(['success' => false, 'error' => 'Email or phone is required'], 400);
    }
    if ($password === '') {
        jsonResponse(['success' => false, 'error' => 'Password is required'], 400);
    }

    $check = $conn->prepare("SELECT id FROM accounts WHERE role = 'community' AND (email = ? OR phone = ?) LIMIT 1");
    $check->bind_param('ss', $email, $phone);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    if ($existing) {
        jsonResponse(['success' => false, 'error' => 'An account with that email or phone already exists'], 409);
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $personalDetailsPublic = !empty($data['personal_details_public']) ? 1 : 0;
    $neighborhood = trim($data['neighborhood'] ?? '');
    $address = trim($data['address'] ?? '');
    $profileNote = trim($data['profile_note'] ?? '');

    $stmt = $conn->prepare("INSERT INTO accounts (role, full_name, display_name, email, phone, password_hash, neighborhood, address, personal_details_public, profile_note) VALUES ('community', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sssssssis', $fullName, $displayName, $email, $phone, $passwordHash, $neighborhood, $address, $personalDetailsPublic, $profileNote);

    if (!$stmt->execute()) {
        jsonResponse(['success' => false, 'error' => $stmt->error], 500);
    }

    $accountId = $stmt->insert_id;
    $stmt->close();

    $account = loadAccountById($conn, $accountId);
    createSessionForAccount($account);

    jsonResponse([
        'success' => true,
        'message' => 'Registration successful',
        'user' => $account
    ], 201);
}

if ($action === 'login') {
    $role = $data['role'] ?? 'community';
    $identifier = trim($data['identifier'] ?? '');
    $password = $data['password'] ?? '';

    if ($identifier === '' || $password === '') {
        jsonResponse(['success' => false, 'error' => 'Login details are required'], 400);
    }

    if (!in_array($role, ['community', 'police'], true)) {
        jsonResponse(['success' => false, 'error' => 'Invalid role'], 400);
    }

    $account = findAccountForLogin($conn, $role, $identifier);
    if (!$account || !password_verify($password, $account['password_hash'])) {
        jsonResponse(['success' => false, 'error' => 'Invalid login details'], 401);
    }

    createSessionForAccount($account);

    jsonResponse([
        'success' => true,
        'message' => 'Login successful',
        'user' => loadAccountById($conn, $account['id'])
    ]);
}

if ($action === 'logout') {
    session_destroy();
    jsonResponse(['success' => true, 'message' => 'Logged out successfully']);
}

jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
