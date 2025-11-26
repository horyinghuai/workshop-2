<?php
// get_email_draft.php
header('Content-Type: application/json');

$candidate_id = $_POST['candidate_id'] ?? '';
$action = $_POST['action'] ?? ''; // 'accept' or 'reject'
$date = $_POST['date'] ?? '';

if (!$candidate_id || !$action) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

// Escape arguments for safety
// Note: Ensure 'python' is in your system PATH or use full path e.g., 'C:\\Python39\\python.exe'
$cmd = "python generate_email.py " . escapeshellarg($candidate_id) . " " . escapeshellarg($action) . " " . escapeshellarg($date);
$output = shell_exec($cmd);

if ($output === null) {
    echo json_encode(['status' => 'error', 'message' => 'Python script execution failed']);
} else {
    echo $output; // Output is already JSON from Python
}
?>