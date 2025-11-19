<?php
// update_password.php
session_start();
header('Content-Type: text/plain');

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "resume_reader";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$email = $_SESSION['reset_email'] ?? '';
$code = $_SESSION['reset_code'] ?? '';
$expiry = $_SESSION['reset_expiry'] ?? 0;

$inputPassword = $_POST['password'] ?? '';
$inputCode = $_POST['code'] ?? '';

if (!$email || !$code || time() > $expiry) {
    echo "Verification code expired or not valid.";
    exit;
}

// Optional: Check if code matches (if you sent the code to frontend)
if (isset($_POST['code']) && $inputCode != $code) {
    echo "Incorrect verification code.";
    exit;
}

// Hash password
$hashedPassword = password_hash($inputPassword, PASSWORD_DEFAULT);

// Update password in DB
$stmt = $conn->prepare("UPDATE users SET password=? WHERE email=?");
$stmt->bind_param("ss", $hashedPassword, $email);

if ($stmt->execute()) {
    echo "Password updated successfully! You can now log in.";
    // Clear session
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_code']);
    unset($_SESSION['reset_expiry']);
} else {
    echo "Failed to update password. Try again.";
}

$stmt->close();
$conn->close();
?>
