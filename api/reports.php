<?php
require_once '../config.php';
require_once 'helpers.php';

session_start();

function report_row($row) {
    $row['is_anonymous'] = (bool) $row['is_anonymous'];
    $row['response_time'] = (int) $row['response_time'];
    return $row;
}

function pending_report_row($notification, array $payload) {
    return [
        'id' => 'pending-' . $notification['id'],
        'record_type' => 'pending_report',
        'notification_id' => (int) $notification['id'],
        'street_id' => (int) ($payload['street_id'] ?? 0),
        'street_name' => $payload['street_name'] ?? null,
        'street_area' => $payload['street_area'] ?? null,
        'reporter_label' => $payload['source_name'] ?? 'Community Member',
        'reporter_role' => 'community',
        'source_name' => $payload['source_name'] ?? 'Community Member',
        'is_anonymous' => !empty($payload['is_anonymous']),
        'type' => $payload['type'] ?? 'Unknown',
        'severity' => $payload['severity'] ?? 'medium',
        'description' => $payload['description'] ?? '',
        'status' => 'pending_review',
        'response_time' => (int) ($payload['response_time'] ?? 0),
        'reported_at' => $notification['created_at'],
        'response_message' => null
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $scope = $_GET['scope'] ?? 'public';

    if ($scope === 'mine') {
        $user = require_role(['community', 'police']);
        $reports = [];

        $stmt = $conn->prepare("SELECT i.*, s.name AS street_name, s.area AS street_area, CASE WHEN i.is_anonymous = 1 THEN 'Anonymous Source' ELSE COALESCE(a.display_name, a.full_name, 'Community Member') END AS reporter_label FROM incidents i LEFT JOIN streets s ON s.id = i.street_id LEFT JOIN accounts a ON a.id = i.reporter_id WHERE i.reporter_id = ? ORDER BY i.reported_at DESC LIMIT 200");
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $reports[] = report_row($row);
        }
        $stmt->close();

        if ($user['role'] === 'community') {
            $pendingStmt = $conn->prepare("SELECT MIN(n.id) AS id, n.sender_id, n.message, n.created_at FROM notifications n WHERE n.sender_id = ? AND n.category = 'alert' AND n.title = 'New Community Report' AND n.incident_id IS NULL GROUP BY n.message, n.sender_id, n.created_at ORDER BY n.created_at DESC LIMIT 200");
            $pendingStmt->bind_param('i', $user['id']);
            $pendingStmt->execute();
            $pendingResult = $pendingStmt->get_result();

            while ($notification = $pendingResult->fetch_assoc()) {
                $payload = json_decode($notification['message'], true);
                if (is_array($payload)) {
                    $reports[] = pending_report_row($notification, $payload);
                }
            }

            $pendingStmt->close();
        }

        usort($reports, function ($left, $right) {
            return strtotime($right['reported_at'] ?? 'now') <=> strtotime($left['reported_at'] ?? 'now');
        });
        api_json(['success' => true, 'reports' => $reports]);
    }

    if ($scope === 'all') {
        require_role(['police']);
    }

    $stmt = $conn->prepare("SELECT i.*, s.name AS street_name, s.area AS street_area, CASE WHEN i.is_anonymous = 1 THEN 'Anonymous Source' WHEN i.reporter_role = 'police' THEN COALESCE(a.badge_number, 'Police') ELSE COALESCE(a.display_name, a.full_name, 'Community Member') END AS reporter_label FROM incidents i LEFT JOIN streets s ON s.id = i.street_id LEFT JOIN accounts a ON a.id = i.reporter_id WHERE i.reporter_role = 'community' ORDER BY i.reported_at DESC LIMIT 300");
    $stmt->execute();
    $result = $stmt->get_result();

    $reports = [];
    while ($row = $result->fetch_assoc()) {
        $reports[] = report_row($row);
    }
    $stmt->close();

    api_json(['success' => true, 'reports' => $reports]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_role(['community', 'police']);
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    if (empty($data['street_id'])) {
        api_json(['success' => false, 'error' => 'Street/location is required'], 400);
    }
    if (empty($data['type'])) {
        api_json(['success' => false, 'error' => 'Report type is required'], 400);
    }

    $streetId = (int) $data['street_id'];
    $type = trim($data['type']);
    $severity = $data['severity'] ?? 'medium';
    $description = trim($data['description'] ?? '');
    $responseTime = isset($data['response_time']) ? (int) $data['response_time'] : 0;
    $sourceName = trim($data['source_name'] ?? '');
    $isAnonymous = !empty($data['is_anonymous']) ? 1 : 0;
    $reporterRole = $user['role'];
    $reporterId = $user['id'];

    if ($isAnonymous) {
        $sourceName = 'Anonymous Source';
    } elseif ($sourceName === '') {
        $sourceName = $user['display_name'] ?: ($user['full_name'] ?: 'Community Member');
    }

    if ($reporterRole === 'community') {
        $streetStmt = $conn->prepare("SELECT name, area FROM streets WHERE id = ? LIMIT 1");
        $streetStmt->bind_param('i', $streetId);
        $streetStmt->execute();
        $street = $streetStmt->get_result()->fetch_assoc() ?: ['name' => null, 'area' => null];
        $streetStmt->close();

        $reportPayload = [
            'report_token' => bin2hex(random_bytes(12)),
            'street_id' => $streetId,
            'street_name' => $street['name'],
            'street_area' => $street['area'],
            'type' => $type,
            'severity' => $severity,
            'description' => $description,
            'response_time' => $responseTime,
            'source_name' => $sourceName,
            'is_anonymous' => (bool) $isAnonymous,
            'reporter_id' => $reporterId,
            'reporter_role' => $reporterRole,
            'created_at' => date('c')
        ];

        $policeResult = $conn->query("SELECT id FROM accounts WHERE role = 'police' AND status = 'active'");
        while ($police = $policeResult->fetch_assoc()) {
            create_notification(
                $conn,
                (int) $police['id'],
                'New Community Report',
                json_encode($reportPayload, JSON_UNESCAPED_UNICODE),
                'alert',
                $reporterId,
                null
            );
        }

        api_json(['success' => true, 'message' => 'Report submitted successfully and sent to police notifications'], 201);
    }

    $stmt = $conn->prepare("INSERT INTO incidents (street_id, reporter_id, reporter_role, source_name, is_anonymous, type, severity, description, status, response_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'reported', ?)");
    $stmt->bind_param('iississsi', $streetId, $reporterId, $reporterRole, $sourceName, $isAnonymous, $type, $severity, $description, $responseTime);

    if (!$stmt->execute()) {
        api_json(['success' => false, 'error' => $stmt->error], 500);
    }

    $incidentId = $stmt->insert_id;
    $stmt->close();

    api_json(['success' => true, 'message' => 'Report submitted successfully', 'incident_id' => $incidentId], 201);
}

api_json(['success' => false, 'error' => 'Method not allowed'], 405);
