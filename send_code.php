<?php
// send_code.php
header('Content-Type: application/json');
require_once 'connection.php'; 

$email = isset($_POST['email']) ? trim($_POST['email']) : '';

if (empty($email)) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter an email address.']);
    exit;
}

// 1. Check DB
$stmt = $conn->prepare("SELECT email FROM user WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email. Please try again.']);
    exit;
}
$stmt->close();

// 2. Generate Code
$code = rand(100000, 999999);

// 3. Send Email
$subject = "Password Reset Verification Code";
$message = "Your verification code is: " . $code;

// UPDATED: Use environment variable for sender
$sender_email = getenv('MAIL_SENDER') ?: "yinghuai180704@gmail.com";
$headers = "From: " . $sender_email; 

if (mail($email, $subject, $message, $headers)) {
    echo json_encode(['status' => 'success', 'code' => $code]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to send email. Check sendmail config.']);
}
?>