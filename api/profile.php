<?php
require_once '../config.php';
require_once 'helpers.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = require_role(['community', 'police']);

    $stmt = $conn->prepare("SELECT id, role, full_name, display_name, email, phone, badge_number, neighborhood, address, personal_details_public, profile_note, station, status FROM accounts WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    api_json(['success' => true, 'profile' => $profile]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_role(['community', 'police']);
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    $fullName = trim($data['full_name'] ?? '');
    $displayName = trim($data['display_name'] ?? '');
    $email = trim($data['email'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $neighborhood = trim($data['neighborhood'] ?? '');
    $address = trim($data['address'] ?? '');
    $personalDetailsPublic = !empty($data['personal_details_public']) ? 1 : 0;
    $profileNote = trim($data['profile_note'] ?? '');

    if ($user['role'] === 'police') {
        $station = trim($data['station'] ?? '');
        $stmt = $conn->prepare("UPDATE accounts SET full_name = ?, display_name = ?, email = ?, phone = ?, neighborhood = ?, address = ?, personal_details_public = ?, profile_note = ?, station = ? WHERE id = ?");
        $stmt->bind_param('ssssssissi', $fullName, $displayName, $email, $phone, $neighborhood, $address, $personalDetailsPublic, $profileNote, $station, $user['id']);
    } else {
        $stmt = $conn->prepare("UPDATE accounts SET full_name = ?, display_name = ?, email = ?, phone = ?, neighborhood = ?, address = ?, personal_details_public = ?, profile_note = ? WHERE id = ?");
        $stmt->bind_param('ssssssisi', $fullName, $displayName, $email, $phone, $neighborhood, $address, $personalDetailsPublic, $profileNote, $user['id']);
    }

    if (!$stmt->execute()) {
        api_json(['success' => false, 'error' => $stmt->error], 500);
    }

    $stmt->close();
    api_json(['success' => true, 'message' => 'Profile updated successfully']);
}

api_json(['success' => false, 'error' => 'Method not allowed'], 405);
