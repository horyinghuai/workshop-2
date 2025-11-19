<?php
// update_password.php

// Ensure this file is only accessed via POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed.";
    exit();
}

// 1. Check for required data
if (empty($_POST['email']) || empty($_POST['password'])) {
    http_response_code(400);
    echo "Error: Missing email or password.";
    exit();
}

// 2. Database Connection
require_once 'connection.php'; // Ensure this path is correct

$email = $conn->real_escape_string($_POST['email']);
$new_password = $_POST['password'];

// 3. Hash the new password securely
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// 4. Update the password in the database
// NOTE: In a complete system, you'd also check the temporary token here
// (The JS demo skips this, but it's vital for security).
$sql = "UPDATE user SET password = ? WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $hashed_password, $email);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // Success
        echo "Password updated successfully! You can now log in with your new password.";
    } else {
        // Email not found
        http_response_code(404);
        echo "Error: The email address provided was not found.";
    }
} else {
    // Database error
    http_response_code(500);
    echo "Error updating password. Please try again later.";
}

$stmt->close();
$conn->close();
?>