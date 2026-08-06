<?php
require_once '../config.php';
require_once 'helpers.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_json(['success' => false, 'error' => 'Method not allowed'], 405);
}

$publicOnly = ($_GET['scope'] ?? 'public') === 'public';

$streetsResult = $conn->query("SELECT s.*, COUNT(i.id) AS incidents_count, SUM(CASE WHEN i.status = 'resolved' THEN 1 ELSE 0 END) AS resolved_count FROM streets s LEFT JOIN incidents i ON i.street_id = s.id AND i.reporter_role = 'community' GROUP BY s.id ORDER BY s.name ASC");
$streets = [];
while ($row = $streetsResult->fetch_assoc()) {
    $row['incidents_count'] = (int) $row['incidents_count'];
    $row['resolved_count'] = (int) $row['resolved_count'];
    $row['cctv'] = (bool) $row['cctv'];
    $row['neighborhood_watch'] = (bool) $row['neighborhood_watch'];
    $streets[] = $row;
}

$reportsResult = $conn->query("SELECT i.*, s.name AS street_name, s.area AS street_area, CASE WHEN i.is_anonymous = 1 THEN 'Anonymous Source' WHEN i.reporter_role = 'police' THEN COALESCE(a.badge_number, 'Police') ELSE COALESCE(a.display_name, a.full_name, 'Community Member') END AS reporter_label FROM incidents i LEFT JOIN streets s ON s.id = i.street_id LEFT JOIN accounts a ON a.id = i.reporter_id WHERE i.reporter_role = 'community' ORDER BY i.reported_at DESC LIMIT 50");
$reports = [];
while ($row = $reportsResult->fetch_assoc()) {
    $row['is_anonymous'] = (bool) $row['is_anonymous'];
    $row['response_time'] = (int) $row['response_time'];
    $reports[] = $row;
}

$stats = [
    'total_streets' => count($streets),
    'total_reports' => count($reports),
    'unresolved_reports' => 0,
    'community_reports' => 0,
    'police_reports' => 0
];

foreach ($reports as $report) {
    if ($report['status'] !== 'resolved') {
        $stats['unresolved_reports']++;
    }
    if ($report['reporter_role'] === 'community') {
        $stats['community_reports']++;
    }
    if ($report['reporter_role'] === 'police') {
        $stats['police_reports']++;
    }
}

api_json([
    'success' => true,
    'stats' => $stats,
    'streets' => $streets,
    'reports' => $reports,
    'public_only' => $publicOnly
]);
