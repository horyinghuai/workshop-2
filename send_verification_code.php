<?php
// send_verification_code.php
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

$email = trim($_POST['email']);

// Check if email exists in users table
$stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo "This email is not registered.";
    exit;
}

// Generate 6-digit verification code
$code = random_int(100000, 999999);

// Store code in session (or database for production)
$_SESSION['reset_email'] = $email;
$_SESSION['reset_code'] = $code;
$_SESSION['reset_expiry'] = time() + 10*60; // expires in 10 minutes

// Send email
$subject = "Your Verification Code for ResumeReader";
$message = "Your verification code is: $code\nThis code expires in 10 minutes.";
$headers = "From: no-reply@yourdomain.com";

if(mail($email, $subject, $message, $headers)){
    echo "success:$code"; // return code for demo, remove in production
}else{
    echo "Failed to send verification code. Try again later.";
}

$stmt->close();
$conn->close();
?>
