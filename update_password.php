<?php
session_start();
require_once 'connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    
    // Check if code was verified
    if (!isset($_SESSION['code_verified']) || !$_SESSION['code_verified'] || 
        !isset($_SESSION['reset_email']) || $_SESSION['reset_email'] !== $email) {
        
        echo json_encode([
            'success' => false,
            'message' => 'Password reset not authorized. Please restart the process.'
        ]);
        exit;
    }
    
    // Hash the new password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Update password in database
    $sql = "UPDATE user SET password = '$hashed_password' WHERE email = '$email'";
    
    if ($conn->query($sql) === TRUE) {
        // Clear reset session data
        unset($_SESSION['reset_code']);
        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_expires']);
        unset($_SESSION['code_verified']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Password updated successfully.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error updating password: ' . $conn->error
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
}

$conn->close();
?>