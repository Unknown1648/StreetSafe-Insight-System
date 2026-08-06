<?php
require_once __DIR__ . '/config.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(405);
    echo "This script is intended to run from the terminal.\n";
    exit(1);
}

function promptValue(string $label): string
{
    while (true) {
        $value = readline($label);
        if ($value !== false) {
            $value = trim($value);
        }

        if ($value !== '') {
            return $value;
        }

        echo "This field is required.\n";
    }
}

$fullName = $argv[1] ?? promptValue('Full name: ');
$email = $argv[2] ?? promptValue('Email: ');
$badgeNumber = $argv[3] ?? promptValue('Badge number: ');
$password = $argv[4] ?? promptValue('Temporary password: ');
$station = $argv[5] ?? promptValue('Station: ');

$passwordHash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare(
    "INSERT INTO accounts
    (role, full_name, email, badge_number, password_hash, station, status)
    VALUES
    ('police', ?, ?, ?, ?, ?, 'active')"
);

if (!$stmt) {
    fwrite(STDERR, "Error: " . $conn->error . "\n");
    exit(1);
}

$stmt->bind_param(
    'sssss',
    $fullName,
    $email,
    $badgeNumber,
    $passwordHash,
    $station
);

if ($stmt->execute()) {
    echo "Police account created successfully.\n";
} else {
    fwrite(STDERR, "Error: " . $stmt->error . "\n");
    $stmt->close();
    exit(1);
}

$stmt->close();