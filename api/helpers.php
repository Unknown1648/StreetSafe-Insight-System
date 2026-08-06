<?php
/**
 * Utility/Helper Functions for StreetSafe System
 * Common functions used across the application
 */

/**
 * Log an error to a file
 */
function logError($message, $context = []) {
    $log_dir = __DIR__ . '/logs';
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/error_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}";
    
    if (!empty($context)) {
        $log_message .= " | Context: " . json_encode($context);
    }
    
    file_put_contents($log_file, $log_message . PHP_EOL, FILE_APPEND);
}

/**
 * Create a system notification
 */
function create_notification($conn, $recipient_id, $title, $message, $category = 'response', $sender_id = null, $incident_id = null) {
    $stmt = $conn->prepare("
        INSERT INTO notifications
        (recipient_id, sender_id, incident_id, category, title, message, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
    ");

    if (!$stmt) {
        logError("Failed to prepare notification insert", [
            'error' => $conn->error,
            'recipient_id' => $recipient_id
        ]);
        return false;
    }

    $stmt->bind_param(
        "iiisss",
        $recipient_id,
        $sender_id,
        $incident_id,
        $category,
        $title,
        $message
    );

    $result = $stmt->execute();

    if (!$result) {
        logError("Failed to create notification", [
            'error' => $stmt->error,
            'recipient_id' => $recipient_id
        ]);
    }

    $stmt->close();

    return $result;
}

/**
 * Sanitize input string
 */
function sanitizeInput($input) {
    return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
}

/**
 * Send JSON response
 */
function api_json($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($data);
    exit;
}

function require_role(array $roles) {
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        api_json([
            'success' => false,
            'error' => 'Authentication required'
        ], 401);
    }

    if (!in_array($_SESSION['role'], $roles, true)) {
        api_json([
            'success' => false,
            'error' => 'Access denied'
        ], 403);
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
        'personal_details_public' => $_SESSION['personal_details_public'] ?? false,
        'profile_note' => $_SESSION['profile_note'] ?? null
    ];
}

/**
 * Validate incident data
 */
function validateIncidentData($data) {
    $errors = [];
    
    if (empty($data['type'])) {
        $errors[] = "Incident type is required";
    }
    
    if (empty($data['street_id'])) {
        $errors[] = "Street ID is required";
    }
    
    if (empty($data['severity'])) {
        $errors[] = "Severity is required";
    } elseif (!in_array($data['severity'], ['low', 'medium', 'high'])) {
        $errors[] = "Invalid severity level";
    }
    
    if (isset($data['response_time']) && !is_numeric($data['response_time'])) {
        $errors[] = "Response time must be numeric";
    }
    
    return $errors;
}

/**
 * Calculate safety score for a street
 * Mirrors the JavaScript scoring engine
 */
function calculateStreetScore($conn, $streetId) {
    $street_result = $conn->query("SELECT * FROM streets WHERE id = {$streetId}");
    if (!$street_result || $street_result->num_rows === 0) {
        return null;
    }
    $street = $street_result->fetch_assoc();

    $incidents_result = $conn->query("SELECT * FROM incidents 
                                      WHERE street_id = {$streetId}
                                      AND reporter_role = 'community'
                                      AND reported_at >= DATE_SUB(NOW(), INTERVAL 35 DAY)
                                      ORDER BY reported_at DESC");

    $score = 100;

    if ($incidents_result->num_rows === 0) {
        return applyEnvironmentalScore($street, $score);
    }

    $severity_weights = [
        'low' => 1.0,
        'medium' => 1.8,
        'high' => 2.8
    ];

    $total_penalty = 0;
    $response_times = [];

    while ($incident = $incidents_result->fetch_assoc()) {
        $timestamp = strtotime($incident['reported_at'] ?? $incident['timestamp'] ?? 'now');
        $days_ago = (time() - $timestamp) / (60 * 60 * 24);
        $decay = max(0.25, 1 - ($days_ago / 35));

        $severity = isset($severity_weights[$incident['severity']])
                    ? $severity_weights[$incident['severity']]
                    : 1.5;

        $total_penalty += 11 * $severity * $decay;

        if ((int) ($incident['response_time'] ?? 0) > 0) {
            $response_times[] = (int) $incident['response_time'];
        }
    }

    $score -= min($total_penalty, 65);

    if (!empty($response_times)) {
        $avg_response = array_sum($response_times) / count($response_times);
        $score -= min($avg_response * 0.35, 22);
    }

    return applyEnvironmentalScore($street, max(5, round($score)));
}

/**
 * Calculate an aggregate safety score for an area using the roads in it
 */
function calculateAreaScore($conn, $areaName) {
    $areaName = trim((string) $areaName);
    if ($areaName === '') {
        return null;
    }

    $escapedArea = $conn->real_escape_string($areaName);
    $street_result = $conn->query("SELECT id FROM streets WHERE area = '{$escapedArea}' ORDER BY name ASC");
    if (!$street_result || $street_result->num_rows === 0) {
        return null;
    }

    $scores = [];
    while ($street = $street_result->fetch_assoc()) {
        $score = calculateStreetScore($conn, (int) $street['id']);
        if ($score !== null) {
            $scores[] = (int) $score;
        }
    }

    if (empty($scores)) {
        return null;
    }

    $avgScore = round(array_sum($scores) / count($scores));
    return max(5, min(100, $avgScore));
}

/**
 * Return risk metadata for an area score
 */
function getAreaRiskMetadata($score) {
    $score = max(0, min(100, (int) $score));
    $label = getSafetyLabel($score);
    return [
        'score' => $score,
        'risk_label' => $label['label'],
        'risk_color' => $label['color'],
        'risk_level' => $score < 60 ? 'high' : ($score < 75 ? 'medium' : 'low')
    ];
}

/**
 * Apply environmental factors to safety score
 */
function applyEnvironmentalScore($street, $score) {
    if ($street['lighting'] === 'poor') {
        $score -= 16;
    } elseif ($street['lighting'] === 'moderate') {
        $score -= 7;
    }
    
    if (!$street['cctv']) {
        $score -= 11;
    }
    
    if (!$street['neighborhood_watch']) {
        $score -= 9;
    }
    
    return max(0, min(100, $score));
}

/**
 * Get safety label and color for a score
 */
function getSafetyLabel($score) {
    if ($score >= 75) {
        return ['label' => 'Safe', 'color' => 'green'];
    } elseif ($score >= 50) {
        return ['label' => 'Moderate Risk', 'color' => 'yellow'];
    } else {
        return ['label' => 'High Risk', 'color' => 'red'];
    }
}

/**
 * Get incident type emoji
 */
function getIncidentEmoji($type) {
    $emojis = [
        'Theft' => '🚨',
        'Burglary' => '🏠',
        'Robbery' => '💼',
        'Assault' => '✊',
        'Traffic' => '🚗',
        'Vandalism' => '🔨',
        'Others' => '⚠️'
    ];
    
    return isset($emojis[$type]) ? $emojis[$type] : '⚠️';
}

/**
 * Format date for display
 */
function formatDate($timestamp) {
    $date = new DateTime($timestamp);
    return $date->format('M d, Y • H:i');
}

/**
 * Get day-of-week from timestamp
 */
function getDayOfWeek($timestamp) {
    $date = new DateTime($timestamp);
    return $date->format('l'); // e.g., "Monday"
}

/**
 * Generate incident statistics for a street
 */
function getStreetStatistics($conn, $streetId) {
    $stats = [];
    
    // Total incidents
    $result = $conn->query("SELECT COUNT(*) as count FROM incidents WHERE street_id = {$streetId}");
    $stats['total_incidents'] = $result->fetch_assoc()['count'];
    
    // Recent incidents (35 days)
    $result = $conn->query("SELECT COUNT(*) as count FROM incidents 
                           WHERE street_id = {$streetId} 
                           AND timestamp >= DATE_SUB(NOW(), INTERVAL 35 DAY)");
    $stats['recent_incidents'] = $result->fetch_assoc()['count'];
    
    // Average response time
    $result = $conn->query("SELECT AVG(response_time) as avg FROM incidents 
                           WHERE street_id = {$streetId} AND response_time > 0");
    $row = $result->fetch_assoc();
    $stats['avg_response_time'] = round($row['avg'] ?? 0, 2);
    
    // Incident breakdown by type
    $result = $conn->query("SELECT type, COUNT(*) as count FROM incidents 
                           WHERE street_id = {$streetId} 
                           GROUP BY type ORDER BY count DESC");
    $stats['by_type'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['by_type'][] = $row;
    }
    
    // Incident breakdown by severity
    $result = $conn->query("SELECT severity, COUNT(*) as count FROM incidents 
                           WHERE street_id = {$streetId} 
                           GROUP BY severity");
    $stats['by_severity'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['by_severity'][] = $row;
    }
    
    return $stats;
}
?>
