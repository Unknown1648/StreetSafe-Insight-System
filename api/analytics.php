<?php
require_once '../config.php';
require_once 'helpers.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_json(['success' => false, 'error' => 'Method not allowed'], 405);
}

require_role(['police']);

$action = $_GET['action'] ?? 'dashboard';

if ($action === 'dashboard') {
    $stats = [];

    $result = $conn->query("SELECT COUNT(*) as count FROM incidents WHERE reporter_role = 'community'");
    $stats['total_incidents'] = (int) $result->fetch_assoc()['count'];

    $result = $conn->query("SELECT COUNT(*) as count FROM incidents WHERE reporter_role = 'community' AND reported_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['incidents_this_week'] = (int) $result->fetch_assoc()['count'];

    $result = $conn->query("SELECT AVG(response_time) as avg FROM incidents WHERE reporter_role = 'community' AND response_time > 0");
    $row = $result->fetch_assoc();
    $stats['avg_response_time'] = round($row['avg'] ?? 0, 2);

    $result = $conn->query("SELECT street_id, COUNT(*) as count FROM incidents WHERE reporter_role = 'community' AND reported_at >= DATE_SUB(NOW(), INTERVAL 35 DAY) GROUP BY street_id HAVING count > 2");
    $stats['high_risk_streets'] = $result->num_rows;

    $result = $conn->query("SELECT type, COUNT(*) as count FROM incidents WHERE reporter_role = 'community' GROUP BY type ORDER BY count DESC");
    $stats['by_type'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['by_type'][] = $row;
    }

    $result = $conn->query("SELECT severity, COUNT(*) as count FROM incidents WHERE reporter_role = 'community' GROUP BY severity");
    $stats['by_severity'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['by_severity'][] = $row;
    }

    api_json(['success' => true, 'stats' => $stats]);
}

if ($action === 'street_details') {
    $street_id = isset($_GET['street_id']) ? (int) $_GET['street_id'] : 0;

    if (!$street_id) {
        api_json(['error' => 'street_id parameter required'], 400);
    }

    $details = [];
    $result = $conn->query("SELECT * FROM streets WHERE id = {$street_id}");
    $details['street'] = $result->fetch_assoc();

    $result = $conn->query("SELECT * FROM incidents WHERE reporter_role = 'community' AND street_id = {$street_id} ORDER BY reported_at DESC LIMIT 10");
    $details['recent_incidents'] = [];
    while ($row = $result->fetch_assoc()) {
        $details['recent_incidents'][] = $row;
    }

    $result = $conn->query("SELECT COUNT(*) as total FROM incidents WHERE reporter_role = 'community' AND street_id = {$street_id}");
    $details['total_incidents'] = (int) $result->fetch_assoc()['total'];

    $result = $conn->query("SELECT COUNT(*) as total FROM incidents WHERE reporter_role = 'community' AND street_id = {$street_id} AND reported_at >= DATE_SUB(NOW(), INTERVAL 35 DAY)");
    $details['recent_incidents_count'] = (int) $result->fetch_assoc()['total'];

    $result = $conn->query("SELECT AVG(response_time) as avg FROM incidents WHERE reporter_role = 'community' AND street_id = {$street_id} AND response_time > 0");
    $details['avg_response_time'] = round($result->fetch_assoc()['avg'] ?? 0, 2);

    api_json(['success' => true, 'details' => $details]);
}

if ($action === 'street_reports') {
    $groupBy = $_GET['group_by'] ?? 'type';
    $startDate = trim($_GET['start_date'] ?? '');
    $endDate = trim($_GET['end_date'] ?? '');

    $where = "reporter_role = 'community'";
    if ($startDate !== '') {
        $where .= " AND DATE(reported_at) >= '" . $conn->real_escape_string($startDate) . "'";
    }
    if ($endDate !== '') {
        $where .= " AND DATE(reported_at) <= '" . $conn->real_escape_string($endDate) . "'";
    }

    if ($groupBy === 'type') {
        $result = $conn->query("SELECT type AS bucket, COUNT(*) AS count, AVG(response_time) AS avg_response_time, MAX(reported_at) AS latest_reported_at FROM incidents WHERE {$where} GROUP BY type ORDER BY count DESC");
    } elseif ($groupBy === 'date') {
        $result = $conn->query("SELECT DATE(reported_at) AS bucket, COUNT(*) AS count, AVG(response_time) AS avg_response_time FROM incidents WHERE {$where} GROUP BY DATE(reported_at) ORDER BY bucket DESC");
    } else {
        $result = $conn->query("SELECT s.id AS street_id, s.name AS street_name, s.area AS street_area, COUNT(i.id) AS count, ROUND(AVG(i.response_time), 2) AS avg_response_time FROM incidents i JOIN streets s ON s.id = i.street_id WHERE {$where} GROUP BY s.id, s.name, s.area ORDER BY count DESC");
    }

    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    if ($groupBy === 'score') {
        $scoreRows = [];
        $streetsResult = $conn->query("SELECT id, name, area FROM streets ORDER BY name ASC");
        while ($street = $streetsResult->fetch_assoc()) {
            $score = calculateStreetScore($conn, (int) $street['id']);
            if ($score === null) {
                continue;
            }
            $bucket = $score >= 75 ? 'Safe' : ($score >= 50 ? 'Moderate Risk' : 'High Risk');
            $scoreRows[] = [
                'bucket' => $bucket,
                'street_id' => (int) $street['id'],
                'street_name' => $street['name'],
                'street_area' => $street['area'],
                'score' => $score,
                'count' => 0
            ];
        }
        $rows = $scoreRows;
    }

    api_json(['success' => true, 'report' => ['group_by' => $groupBy, 'rows' => $rows]]);
}

api_json(['error' => 'Unknown action'], 400);
