<?php
// update_password.php
header('Content-Type: application/json; charset=utf-8');
require_once 'connection.php'; 

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

// 1. Basic Email Check
if (empty($email)) {
    echo json_encode(['success' => false, 'error' => 'Invalid session. Please try again.']);
    exit;
}

// 2. STRICT PASSWORD VALIDATION (As requested)
$error_msg = "";

if (empty($password)) {
    $error_msg = "Please enter a password.";
} elseif (strlen($password) < 6) {
    $error_msg = "Password must have at least 6 characters.";
} elseif (!preg_match('/[A-Z]/', $password)) {
    $error_msg = "Password must include at least one uppercase letter.";
} elseif (!preg_match('/[a-z]/', $password)) {
    $error_msg = "Password must include at least one lowercase letter.";
} elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
    $error_msg = "Password must include at least one symbol.";
} elseif (empty($confirm_password)) {
    $error_msg = "Please confirm your password.";
} elseif ($password != $confirm_password) {
    $error_msg = "Passwords do not match.";
}

// If there is any error, stop and send it back to JS
if (!empty($error_msg)) {
    echo json_encode(['success' => false, 'error' => $error_msg]);
    exit;
}

// 3. Update Database
$hashed = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE user SET password = ? WHERE email = ?");
$stmt->bind_param("ss", $hashed, $email);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database update failed.']);
}
$stmt->close();
?>