<?php
// send_outreach.php
session_start();
include 'connection.php';
header('Content-Type: application/json');

$candidate_id = $_POST['candidate_id'] ?? '';
$action = $_POST['action'] ?? '';
$email_to = $_POST['email'] ?? '';
$subject = $_POST['subject'] ?? '';
$body = $_POST['body'] ?? '';
$meet_link = $_POST['meet_link'] ?? ''; 
$interview_date = $_POST['interview_date'] ?? '';

// ... (Input validation and link extraction logic from original file) ...
// (Assuming checks for empty email/candidate_id are passed)

if (empty($meet_link) && !empty($body)) {
    if (preg_match('/https:\/\/meet\.google\.com\/[a-z]{3}-[a-z]{4}-[a-z]{3}/', $body, $matches)) {
        $meet_link = $matches[0];
    }
}

$db_success = false;

if ($action === 'accept') {
    $outreach_status = 'Outreached - Scheduled Interview';
    // Insert into calendar table
    $stmt = $conn->prepare("INSERT INTO interview (candidate_id, interview_date, meeting_link) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $candidate_id, $interview_date, $meet_link);
    if ($stmt->execute()) {
        $db_success = true;
    }
    $stmt->close();
    
    // Update candidate status
    $stmt = $conn->prepare("UPDATE candidate SET outreach = ? WHERE candidate_id = ?");
    $stmt->bind_param("si", $outreach_status, $candidate_id);
    $stmt->execute();
    $stmt->close();

} else {
    // Reject Action
    $outreach_status = 'Outreached - Rejected';
    $new_status = 'Rejected';
    $stmt = $conn->prepare("UPDATE candidate SET status = ?, outreach = ? WHERE candidate_id = ?");
    $stmt->bind_param("ssi", $new_status, $outreach_status, $candidate_id);
    if ($stmt->execute()) {
        $db_success = true;
    }
    $stmt->close();
}

// Send Email
$sender_email = getenv('MAIL_SENDER') ?: "yinghuai180704@gmail.com";
$headers = "From: " . $sender_email . "\r\nContent-Type: text/plain; charset=UTF-8\r\n";
$email_sent = mail($email_to, $subject, $body, $headers);

// --- TRIGGER AI QUESTION GENERATOR ---
if ($db_success && $action === 'accept') {
    $cmd = "python generate_questions.py " . escapeshellarg($candidate_id);
    exec($cmd); 
}

if ($db_success) {
    echo json_encode([
        'status' => 'success',
        'email_sent' => $email_sent,
        'message' => 'Action saved and interview questions generated.'
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
}
$conn->close();
?>