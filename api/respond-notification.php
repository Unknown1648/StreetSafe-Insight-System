<?php
require_once __DIR__ . '/../config.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $notifId = $data['notification_id'] ?? null;
    $response = $data['response'] ?? '';
    $newStatus = $data['status'] ?? 'In Progress';

    if (!$notifId || empty($response)) {
        echo json_encode(['success' => false, 'message' => 'Missing data']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE notifications SET response = ?, status = ?, responded_by = ? WHERE id = ?");
    $policeId = $_SESSION['user_id'];
    $stmt->bind_param("ssii", $response, $newStatus, $policeId, $notifId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
}