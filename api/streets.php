<?php
require_once '../config.php';
require_once 'helpers.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $conn->query("SELECT s.*, COUNT(i.id) AS incidents_count FROM streets s LEFT JOIN incidents i ON i.street_id = s.id GROUP BY s.id ORDER BY s.name ASC");

    if (!$result) {
        api_json(['success' => false, 'error' => $conn->error], 500);
        exit;
    }

    $streetRows = [];
    while ($row = $result->fetch_assoc()) {
        $streetRows[] = $row;
    }

    $areaStreetCounts = [];
    foreach ($streetRows as $row) {
        $areaName = trim((string) ($row['area'] ?? ''));
        if ($areaName !== '') {
            $areaStreetCounts[$areaName] = ($areaStreetCounts[$areaName] ?? 0) + 1;
        }
    }

    $areaScoreCache = [];
    $streets = [];
    foreach ($streetRows as $row) {
        $row['cctv'] = (bool) $row['cctv'];
        $row['neighborhood_watch'] = (bool) $row['neighborhood_watch'];
        $row['incidents_count'] = (int) $row['incidents_count'];

        $score = calculateStreetScore($conn, (int) $row['id']);
        $row['score'] = $score === null ? 100 : (int) $score;

        $label = getSafetyLabel($row['score']);
        $row['risk_label'] = $label['label'];
        $row['risk_color'] = $label['color'];
        $row['risk_level'] = $row['score'] < 60 ? 'high' : ($row['score'] < 75 ? 'medium' : 'low');

        $areaName = trim((string) ($row['area'] ?? ''));
        if ($areaName !== '') {
            if (!isset($areaScoreCache[$areaName])) {
                $areaScoreCache[$areaName] = calculateAreaScore($conn, $areaName);
            }

            $areaMetadata = getAreaRiskMetadata($areaScoreCache[$areaName] ?? 100);
            $row['area_score'] = $areaMetadata['score'];
            $row['area_risk_label'] = $areaMetadata['risk_label'];
            $row['area_risk_color'] = $areaMetadata['risk_color'];
            $row['area_risk_level'] = $areaMetadata['risk_level'];
            $row['area_street_count'] = $areaStreetCounts[$areaName] ?? 1;
        }

        $streets[] = $row;
    }

    usort($streets, function ($left, $right) {
        return ($left['score'] ?? 100) <=> ($right['score'] ?? 100);
    });

    $areas = [];
    foreach ($areaScoreCache as $areaName => $areaScore) {
        if ($areaScore === null) {
            continue;
        }

        $areaMetadata = getAreaRiskMetadata($areaScore);
        $areas[] = [
            'name' => $areaName,
            'score' => (int) $areaScore,
            'risk_label' => $areaMetadata['risk_label'],
            'risk_color' => $areaMetadata['risk_color'],
            'risk_level' => $areaMetadata['risk_level'],
            'street_count' => $areaStreetCounts[$areaName] ?? 0
        ];
    }

    usort($areas, function ($left, $right) {
        return ($left['score'] ?? 100) <=> ($right['score'] ?? 100);
    });

    api_json(['success' => true, 'streets' => $streets, 'areas' => $areas]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_role(['police']);
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    if (empty($data['name']) || empty($data['area'])) {
        api_json(['success' => false, 'error' => 'Street name and area are required'], 400);
    }

    $name = trim($data['name']);
    $area = trim($data['area']);
    $lat = isset($data['lat']) && $data['lat'] !== '' ? (float) $data['lat'] : null;
    $lng = isset($data['lng']) && $data['lng'] !== '' ? (float) $data['lng'] : null;
    $lighting = $data['lighting'] ?? 'moderate';
    $cctv = !empty($data['cctv']) ? 1 : 0;
    $watch = !empty($data['neighborhood_watch']) ? 1 : 0;
    $notes = trim($data['notes'] ?? '');
    $streetId = isset($data['street_id']) ? (int) $data['street_id'] : 0;

    if ($streetId > 0) {
        $stmt = $conn->prepare("UPDATE streets SET name = ?, area = ?, lat = ?, lng = ?, lighting = ?, cctv = ?, neighborhood_watch = ?, notes = ? WHERE id = ?");
        $stmt->bind_param('ssddsiisi', $name, $area, $lat, $lng, $lighting, $cctv, $watch, $notes, $streetId);

        if (!$stmt->execute()) {
            api_json(['success' => false, 'error' => $stmt->error], 500);
        }

        api_json(['success' => true, 'message' => 'Location profile updated', 'street_id' => $streetId], 200);
    }

    $stmt = $conn->prepare("INSERT INTO streets (name, area, lat, lng, lighting, cctv, neighborhood_watch, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssddsiisi', $name, $area, $lat, $lng, $lighting, $cctv, $watch, $notes, $user['id']);

    if (!$stmt->execute()) {
        api_json(['success' => false, 'error' => $stmt->error], 500);
    }

    api_json(['success' => true, 'message' => 'Location profile saved', 'street_id' => $stmt->insert_id], 201);
}

api_json(['success' => false, 'error' => 'Method not allowed'], 405);
