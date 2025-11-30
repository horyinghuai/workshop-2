<?php
// send_outreach.php
session_start();
include 'connection.php';
header('Content-Type: application/json');

// --- 1. GET INPUTS ---
$candidate_id = $_POST['candidate_id'] ?? '';
$action = $_POST['action'] ?? '';
$email_to = $_POST['email'] ?? '';
$subject = $_POST['subject'] ?? '';
$body = $_POST['body'] ?? '';
$meet_link = $_POST['meet_link'] ?? ''; 
$interview_date = $_POST['interview_date'] ?? '';

// --- 2. EXTRACT LINK FROM BODY IF MISSING ---
if (empty($meet_link) && !empty($body)) {
    // Looks for https://meet.google.com/xxx-xxxx-xxx
    if (preg_match('/https:\/\/meet\.google\.com\/[a-z]{3}-[a-z]{4}-[a-z]{3}/', $body, $matches)) {
        $meet_link = $matches[0];
    }
}

// --- 3. RETRIEVE EMAIL IF MISSING ---
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

// --- 4. UPDATE DATABASE (Perform this FIRST) ---
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

    // Update BOTH outreach_status and status
    $stmt = $conn->prepare("UPDATE candidate SET status = ?, outreach = ? WHERE candidate_id = ?");
    $stmt->bind_param("ssi", $new_status, $outreach_status, $candidate_id);
    if ($stmt->execute()) {
        $db_success = true;
    }
    $stmt->close();
}

// --- 5. SEND EMAIL (Perform this SECOND) ---
$sender_email = getenv('MAIL_SENDER') ?: "yinghuai180704@gmail.com";
$headers = "From: " . $sender_email . "\r\n"; 
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// We capture the result but don't let it stop the success response
$email_sent = mail($email_to, $subject, $body, $headers);

// --- 6. RETURN RESPONSE ---
if ($db_success) {
    echo json_encode([
        'status' => 'success',
        'email_sent' => $email_sent,
        'message' => $email_sent ? 'Action saved and email sent.' : 'Action saved, but email failed to send (check logs).'
    ]);
} else {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database update failed.'
    ]);
}

$conn->close();
?>