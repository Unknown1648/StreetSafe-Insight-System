<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';
session_start();

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? null;

$totalIncidents = $conn->query("SELECT COUNT(*) AS c FROM incidents")
    ->fetch_assoc()['c'];

$totalStreets = $conn->query("SELECT COUNT(*) AS c FROM streets")
    ->fetch_assoc()['c'];

$unsafeCount = 0;
$safeCount = 0;
$streetResult = $conn->query("SELECT id FROM streets ORDER BY name ASC");

while ($street = $streetResult->fetch_assoc()) {
    $score = calculateStreetScore($conn, (int) $street['id']);
    if ($score === null) {
        continue;
    }
    if ($score < 60) {
        $unsafeCount++;
    } else {
        $safeCount++;
    }
}

$userReports = 0;
$userNotifications = 0;

if ($userId) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM incidents WHERE reporter_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userReports = $stmt->get_result()->fetch_assoc()['c'];

    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE recipient_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userNotifications = $stmt->get_result()->fetch_assoc()['c'];
}

echo json_encode([
    "totalIncidents" => (int)$totalIncidents,
    "totalStreets" => (int)$totalStreets,
    "unsafeStreets" => (int)$unsafeCount,
    "safeStreets" => (int)$safeCount,
    "myReports" => (int)$userReports,
    "notifications" => (int)$userNotifications
]);