<?php
require_once '../config.php';
require_once 'helpers.php';

session_start();

function decode_notification_payload($message) {
    $payload = json_decode($message, true);
    return is_array($payload) ? $payload : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = require_role(['community', 'police']);

    $stmt = $conn->prepare("SELECT n.*, i.type AS incident_type, i.status AS incident_status, i.police_action AS incident_action, s.name AS street_name FROM notifications n LEFT JOIN incidents i ON i.id = n.incident_id LEFT JOIN streets s ON s.id = i.street_id WHERE n.recipient_id = ? ORDER BY n.created_at DESC LIMIT 200");
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_read'] = (bool) $row['is_read'];
        $row['payload'] = decode_notification_payload($row['message']);
        $notifications[] = $row;
    }
    $stmt->close();

    api_json(['success' => true, 'notifications' => $notifications]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_role(['community', 'police']);
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $data['action'] ?? '';

    if ($action === 'mark_read') {
        $notificationId = (int) ($data['notification_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND recipient_id = ?");
        $stmt->bind_param('ii', $notificationId, $user['id']);
        $stmt->execute();
        $stmt->close();
        api_json(['success' => true, 'message' => 'Notification marked as read']);
    }

    if ($action === 'assign' && $user['role'] === 'police') {
        $notificationId = (int) ($data['notification_id'] ?? 0);
        $status = $data['status'] ?? 'reported';

        if ($notificationId <= 0) {
            api_json(['success' => false, 'error' => 'notification_id is required'], 400);
        }

        $notificationStmt = $conn->prepare("SELECT * FROM notifications WHERE id = ? AND recipient_id = ? AND category = 'alert' LIMIT 1");
        $notificationStmt->bind_param('ii', $notificationId, $user['id']);
        $notificationStmt->execute();
        $notification = $notificationStmt->get_result()->fetch_assoc();
        $notificationStmt->close();

        if (!$notification) {
            api_json(['success' => false, 'error' => 'Notification not found'], 404);
        }

        $payload = decode_notification_payload($notification['message']);
        if (!$payload || empty($payload['street_id']) || empty($payload['type'])) {
            api_json(['success' => false, 'error' => 'Notification payload is invalid'], 400);
        }

        if (!empty($notification['incident_id'])) {
            api_json(['success' => true, 'message' => 'Notification is already linked to an incident', 'incident_id' => (int) $notification['incident_id']]);
        }

        $sourceName = $payload['source_name'] ?? 'Community Member';
        $isAnonymous = !empty($payload['is_anonymous']) ? 1 : 0;
        $reportedBy = (int) ($payload['reporter_id'] ?? $notification['sender_id'] ?? 0);
        $responseTime = (int) ($payload['response_time'] ?? 0);
        $severity = $payload['severity'] ?? 'medium';
        $description = $payload['description'] ?? '';
        $streetId = (int) $payload['street_id'];
        $type = $payload['type'];

        $incidentStmt = $conn->prepare("INSERT INTO incidents (street_id, reporter_id, reporter_role, source_name, is_anonymous, type, severity, description, status, response_time, responded_by) VALUES (?, ?, 'community', ?, ?, ?, ?, ?, ?, ?, ?)");
        $incidentStmt->bind_param('iisissssii', $streetId, $reportedBy, $sourceName, $isAnonymous, $type, $severity, $description, $status, $responseTime, $user['id']);

        if (!$incidentStmt->execute()) {
            api_json(['success' => false, 'error' => $incidentStmt->error], 500);
        }

        $incidentId = $incidentStmt->insert_id;
        $incidentStmt->close();

        $linkedMessage = $notification['message'];
        $linkStmt = $conn->prepare("UPDATE notifications SET incident_id = ?, is_read = 1, read_at = NOW() WHERE title = ? AND category = 'alert' AND sender_id = ? AND message = ? AND incident_id IS NULL");
        $linkStmt->bind_param('isis', $incidentId, $notification['title'], $notification['sender_id'], $linkedMessage);
        $linkStmt->execute();
        $linkStmt->close();

        if (!empty($reportedBy)) {
            create_notification(
                $conn,
                $reportedBy,
                'Incident Reported',
                'Your incident has been reported and added to the map.',
                'status_update',
                $user['id'],
                $incidentId
            );
        }

        api_json(['success' => true, 'message' => 'Notification assigned to incidents successfully', 'incident_id' => $incidentId]);
    }

    if ($action === 'respond' && $user['role'] === 'police') {
        $incidentId = (int) ($data['incident_id'] ?? 0);
        $message = trim($data['message'] ?? '');
        $status = $data['status'] ?? 'received';
        $policeAction = trim($data['police_action'] ?? '');

        if ($incidentId <= 0 || $message === '') {
            api_json(['success' => false, 'error' => 'incident_id and message are required'], 400);
        }

        $incidentStmt = $conn->prepare("SELECT reporter_id, type FROM incidents WHERE id = ? LIMIT 1");
        $incidentStmt->bind_param('i', $incidentId);
        $incidentStmt->execute();
        $incident = $incidentStmt->get_result()->fetch_assoc();
        $incidentStmt->close();

        if (!$incident) {
            api_json(['success' => false, 'error' => 'Incident not found'], 404);
        }

        $updateStmt = $conn->prepare("UPDATE incidents SET response_message = ?, status = ?, police_action = ?, responded_by = ? WHERE id = ?");
        $updateStmt->bind_param('sssii', $message, $status, $policeAction, $user['id'], $incidentId);
        $updateStmt->execute();
        $updateStmt->close();

        if (!empty($incident['reporter_id'])) {
            $title = $status === 'resolved' ? 'Incident Resolved' : ($status === 'in_progress' ? 'Incident In Progress' : 'Police Update on Your Report');
            create_notification(
                $conn,
                (int) $incident['reporter_id'],
                $title,
                $message,
                'response',
                $user['id'],
                $incidentId
            );
        }

        api_json(['success' => true, 'message' => 'Response sent successfully']);
    }

    api_json(['success' => false, 'error' => 'Unknown action'], 400);
}

api_json(['success' => false, 'error' => 'Method not allowed'], 405);
