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
$meet_link = $_POST['meet_link'] ?? ''; // Might be empty now
$interview_date = $_POST['interview_date'] ?? '';

// --- NEW: EXTRACT LINK FROM BODY IF MISSING ---
if (empty($meet_link) && !empty($body)) {
    // Looks for https://meet.google.com/xxx-xxxx-xxx
    if (preg_match('/https:\/\/meet\.google\.com\/[a-z]{3}-[a-z]{4}-[a-z]{3}/', $body, $matches)) {
        $meet_link = $matches[0];
    }
}

if (empty($email_to) && !empty($candidate_id)) {
    $stmt = $conn->prepare("SELECT email FROM candidate WHERE candidate_id = ?");
    $stmt->bind_param("i", $candidate_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $email_to = $row['email'];
    }
    $stmt->close();
}

if (empty($email_to)) {
    echo json_encode(['status' => 'error', 'message' => 'Recipient email is missing and could not be found.']);
    exit;
}

// 1. Send Email
$headers = "From: yinghuai180704@gmail.com\r\n"; 
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

if (mail($email_to, $subject, $body, $headers)) {
    
    // 2. Update DB based on Action
    if ($action === 'accept') {
        $outreach_status = 'Outreached - Scheduled Interview';
        
        // Insert into calendar table
        $stmt = $conn->prepare("INSERT INTO interview (candidate_id, interview_date, meeting_link) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $candidate_id, $interview_date, $meet_link);
        $stmt->execute();
        $stmt->close();
        
        // Update candidate status
        $stmt = $conn->prepare("UPDATE candidate SET outreach = ? WHERE candidate_id = ?");
        $stmt->bind_param("si", $outreach_status, $candidate_id);
        $stmt->execute();
        $stmt->close();

    } else {
        // Reject Action
        $outreach_status = 'Outreached - Rejected';
        // For reject, update BOTH outreach_status and status (to Rejected)
        $new_status = 'Rejected';
        $stmt = $conn->prepare("UPDATE candidate SET status = ?, outreach = ? WHERE candidate_id = ?");
        $stmt->bind_param("ssi", $new_status, $outreach_status, $candidate_id);
        $stmt->execute();
        $stmt->close();
    }
    
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to send email via mail(). Check server logs.']);
}
$conn->close();
?>