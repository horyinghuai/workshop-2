<?php
// update_password.php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once 'connection.php'; // adjust path if necessary

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email.']);
    exit;
}

// SERVER-SIDE PASSWORD VALIDATION (mirror requirements)
$errors = [];

// Example rules (adapt if you have different rules)
if (strlen($password) < 6) {
    $errors[] = "Password must be at least 6 characters long.";
}
// Add more checks if required (uppercase, digit, symbol, etc.)
// if (!preg_match('/[A-Z]/', $password)) { $errors[] = "At least one uppercase letter required."; }

if (!empty($errors)) {
    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
    exit;
}

// Ensure user exists
$stmt = $conn->prepare("SELECT email FROM user WHERE email = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error (prepare).']);
    exit;
}
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'No user found with that email.']);
    exit;
}
$stmt->close();

// Hash the password and update
$hashed = password_hash($password, PASSWORD_DEFAULT);
$u = $conn->prepare("UPDATE user SET password = ? WHERE email = ?");
if (!$u) {
    echo json_encode(['success' => false, 'error' => 'Database error (prepare update).']);
    exit;
}
$u->bind_param("ss", $hashed, $email);
$ok = $u->execute();
$u->close();

if ($ok) {
    echo json_encode(['success' => true]);
    exit;
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update password.']);
    exit;
}
