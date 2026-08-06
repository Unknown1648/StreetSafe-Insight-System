<?php
$baseDir = dirname(__DIR__);
require_once $baseDir . '/config.php';
require_once __DIR__ . '/helpers.php';

$conn->query('SET FOREIGN_KEY_CHECKS = 0');
$conn->query('TRUNCATE TABLE report_audit_logs');
$conn->query('TRUNCATE TABLE notifications');
$conn->query('TRUNCATE TABLE incidents');
$conn->query('TRUNCATE TABLE accounts');
$conn->query('SET FOREIGN_KEY_CHECKS = 1');

$firstNames = ['Amina','Brian','Cynthia','Daniel','Esther','Felix','Grace','Hassan','Ivy','James','Khadija','Lenny','Moses','Naomi','Oscar','Penny','Quincy','Ruth','Sam','Tina','Uche','Vera','Wes','Xena','Yusuf','Zuri'];
$lastNames = ['Kariuki','Mwangi','Otieno','Njeri','Achieng','Wambua','Mutua','Kimani','Awuor','Mumo','Maina','Omollo','Omondi','Kiptoo','Kinyanjui','Mugambi','Kahiga','Ndegwa','Wangari','Odhiambo'];
$neighborhoods = ['Ongata Rongai Central','Imani Estate','Amani Estate','Ongata Rongai Town','Ongata Rongai South','Ongata Rongai East','Ongata Rongai West','Riverside Estate','Umoja Estate','Gataka'];
$streets = [];
$streetResult = $conn->query('SELECT id FROM streets ORDER BY id ASC');
while ($row = $streetResult->fetch_assoc()) {
    $streets[] = (int) $row['id'];
}
if (empty($streets)) {
    fwrite(STDERR, "No streets found. Seed aborted.\n");
    exit(1);
}

$policeIds = [];
$policeNames = ['Officer Kelvin Oduor','Officer Mercy Wanjiku','Officer Daniel Kibet','Officer Ann Wangari','Officer Peter Kilonzo','Officer Faith Mugo','Officer Samwel Omondi','Officer Sarah Kariuki','Officer Yusuf Barasa','Officer Irene Mureithi','Officer Martin Wekesa','Officer Esther Njeri',"Officer Joseph Ndung'u",'Officer Hellen Akinyi','Officer Collins Kiprop'];
$stations = ['Ongata Rongai Station','Nairobi Road Station','Kisaju Station','Kware Station','Magadi Road Station'];
for ($i = 0; $i < 15; $i++) {
    $fullName = $policeNames[$i] ?? 'Officer Demo ' . ($i + 1);
    $badgeNumber = sprintf('PO-%02d', $i + 1);
    $email = 'police' . ($i + 1) . '@streetsafe.test';
    $passwordHash = password_hash('Police123!', PASSWORD_BCRYPT);
    $station = $stations[$i % count($stations)];
    $stmt = $conn->prepare("INSERT INTO accounts (role, full_name, email, badge_number, password_hash, station, status) VALUES ('police', ?, ?, ?, ?, ?, 'active')");
    $stmt->bind_param('sssss', $fullName, $email, $badgeNumber, $passwordHash, $station);
    $stmt->execute();
    $policeIds[] = $stmt->insert_id;
    $stmt->close();
}

$communityIds = [];
$communityCount = random_int(18, 24);
for ($i = 0; $i < $communityCount; $i++) {
    $first = $firstNames[array_rand($firstNames)];
    $last = $lastNames[array_rand($lastNames)];
    $fullName = $first . ' ' . $last;
    $displayName = $first . ' ' . $last;
    $email = strtolower($first . '.' . $last . ($i + 1) . '@streetsafe.test');
    $phone = '+2547' . str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT);
    $neighborhood = $neighborhoods[array_rand($neighborhoods)];
    $address = $neighborhood . ' #' . random_int(1, 40);
    $passwordHash = password_hash('Community123!', PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO accounts (role, full_name, display_name, email, phone, password_hash, neighborhood, address, personal_details_public, profile_note) VALUES ('community', ?, ?, ?, ?, ?, ?, ?, 1, ?)");
    $profileNote = 'Auto-generated resident profile for demo data.';
    $stmt->bind_param('ssssssss', $fullName, $displayName, $email, $phone, $passwordHash, $neighborhood, $address, $profileNote);
    $stmt->execute();
    $communityIds[] = $stmt->insert_id;
    $stmt->close();
}

$incidentTypes = ['Theft','Burglary','Robbery','Assault','Traffic','Vandalism','Others'];
$incidentDescs = [
    'Reported suspicious movement near the roadside at night.',
    'Community member flagged repeat loitering around the gate.',
    'Possible break-in observed after dark.',
    'Vehicle obstruction and speeding reported near the junction.',
    'Property damage reported in the morning hours.',
    'Reported aggressive confrontation and shouting.',
    'Unknown activity noted around the public walkway.'
];
$statuses = ['reported','received','in_progress','follow_up','resolved'];
$incidentCount = random_int(40, 60);
$incidentIds = [];
for ($i = 0; $i < $incidentCount; $i++) {
    $streetId = $streets[array_rand($streets)];
    $reporterId = $communityIds[array_rand($communityIds)];
    $reporterRole = 'community';
    $sourceName = 'Demo Community Reporter';
    $isAnonymous = random_int(0, 1);
    $type = $incidentTypes[array_rand($incidentTypes)];
    $severity = ['low','medium','high'][array_rand([0,1,2])];
    $description = $incidentDescs[array_rand($incidentDescs)] . ' #' . ($i + 1);
    $status = $statuses[array_rand($statuses)];
    $responseTime = random_int(5, 45);
    $reportedAt = date('Y-m-d H:i:s', strtotime('-' . random_int(1, 35) . ' days'));
    $stmt = $conn->prepare("INSERT INTO incidents (street_id, reporter_id, reporter_role, source_name, is_anonymous, type, severity, description, status, response_time, reported_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iississssiss', $streetId, $reporterId, $reporterRole, $sourceName, $isAnonymous, $type, $severity, $description, $status, $responseTime, $reportedAt, $reportedAt);
    $stmt->execute();
    $incidentIds[] = $stmt->insert_id;
    $stmt->close();
}

$notificationCount = 0;
foreach ($incidentIds as $incidentId) {
    $incidentResult = $conn->query("SELECT i.*, s.name AS street_name, s.area AS street_area FROM incidents i LEFT JOIN streets s ON s.id = i.street_id WHERE i.id = {$incidentId} LIMIT 1");
    $incident = $incidentResult->fetch_assoc();
    $recipientId = $policeIds[array_rand($policeIds)];
    $payload = [
        'street_id' => (int) $incident['street_id'],
        'street_name' => $incident['street_name'],
        'street_area' => $incident['street_area'],
        'type' => $incident['type'],
        'severity' => $incident['severity'],
        'description' => $incident['description'],
        'response_time' => (int) $incident['response_time'],
        'source_name' => $incident['source_name'] ?: 'Demo Reporter',
        'is_anonymous' => (bool) $incident['is_anonymous'],
        'reporter_id' => (int) $incident['reporter_id'],
        'reporter_role' => $incident['reporter_role'],
        'created_at' => $incident['reported_at']
    ];
    $title = $incident['severity'] === 'high' ? 'High Priority Incident' : 'New Community Report';
    $message = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare("INSERT INTO notifications (recipient_id, sender_id, incident_id, category, title, message, is_read, created_at) VALUES (?, ?, ?, 'alert', ?, ?, 0, NOW())");
    $stmt->bind_param('iiiss', $recipientId, $incident['reporter_id'], $incidentId, $title, $message);
    $stmt->execute();
    $stmt->close();
    $notificationCount++;
}

$auditCount = 0;
foreach ($incidentIds as $incidentId) {
    $incidentResult = $conn->query("SELECT type, severity, status FROM incidents WHERE id = {$incidentId} LIMIT 1");
    $incident = $incidentResult->fetch_assoc();
    $actions = [
        ['generated_report', 'Generated demo report for ' . $incident['type'] . ' activity.'],
        ['reviewed', 'Review checklist assigned for ' . $incident['severity'] . ' severity case.']
    ];
    foreach ($actions as $action) {
        $stmt = $conn->prepare("INSERT INTO report_audit_logs (incident_id, action_type, actor_id, details, created_at) VALUES (?, ?, ?, ?, NOW())");
        $actorId = $policeIds[array_rand($policeIds)];
        $stmt->bind_param('iiss', $incidentId, $action[0], $actorId, $action[1]);
        $stmt->execute();
        $stmt->close();
        $auditCount++;
    }
}

echo json_encode([
    'success' => true,
    'police_officers' => count($policeIds),
    'community_accounts' => count($communityIds),
    'incidents' => count($incidentIds),
    'notifications' => $notificationCount,
    'audit_logs' => $auditCount
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
