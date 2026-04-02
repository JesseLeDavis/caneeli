<?php
header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

// Parse JSON body
$body  = file_get_contents('php://input');
$data  = json_decode($body, true);
$email = trim($data['email'] ?? '');

// Validate
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}

// Persist to DB (INSERT IGNORE handles duplicates gracefully)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare('INSERT IGNORE INTO email_signups (email) VALUES (?)');
    $stmt->execute([$email]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error. Please try again.']);
    exit;
}

echo json_encode(['ok' => true]);
