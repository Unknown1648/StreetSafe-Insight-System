<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';
header('Content-Type: application/json');

$active = $conn->query("SELECT COUNT(*) AS c FROM incidents WHERE status='in_progress'")
    ->fetch_assoc()['c'];

$pending = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE category='alert' AND incident_id IS NULL")
    ->fetch_assoc()['c'];

$resolved = $conn->query("SELECT COUNT(*) AS c FROM incidents WHERE status='resolved'")
    ->fetch_assoc()['c'];

$streetResult = $conn->query("SELECT id FROM streets ORDER BY name ASC");
$streetScores = [];

while ($row = $streetResult->fetch_assoc()) {
    $score = calculateStreetScore($conn, (int) $row['id']);
    if ($score !== null) {
        $streetScores[] = (int) $score;
    }
}

$totalScore = array_sum($streetScores);
$count = count($streetScores);
$alertLevel = $count ? round($totalScore / $count) : 100;

echo json_encode([
    "active" => (int)$active,
    "pending" => (int)$pending,
    "resolved" => (int)$resolved,
    "alert" => $alertLevel,
    "alert_label" => $alertLevel >= 75 ? 'LOW' : ($alertLevel >= 50 ? 'MEDIUM' : 'HIGH')
]);