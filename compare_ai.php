<?php
// compare_ai.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid method']);
    exit;
}

$ids = $_POST['ids'] ?? '';

if (empty($ids)) {
    echo json_encode(['status' => 'error', 'message' => 'No IDs provided']);
    exit;
}

// Security: Ensure IDs are just comma separated integers
if (!preg_match('/^[\d,]+$/', $ids)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID format']);
    exit;
}

// Call Python Script
// Escaping the argument is crucial
$cmd = "python compare_candidates.py " . escapeshellarg($ids) . " 2>&1";
$output = shell_exec($cmd);

if ($output === null || empty($output)) {
    echo json_encode(['status' => 'error', 'message' => 'AI script returned no output']);
} else {
    // Python script prints JSON directly
    echo $output;
}
?>