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
// We use __DIR__ to get the absolute path to the python script to avoid "File not found" errors
$scriptPath = __DIR__ . '/compare_candidates.py';

// Check if file exists
if (!file_exists($scriptPath)) {
    echo json_encode(['status' => 'error', 'message' => 'Python script not found at: ' . $scriptPath]);
    exit;
}

// Execute
$cmd = "python " . escapeshellarg($scriptPath) . " " . escapeshellarg($ids) . " 2>&1";
$output = shell_exec($cmd);

if ($output === null || empty($output)) {
    echo json_encode(['status' => 'error', 'message' => 'AI script returned no output. Check server logs.']);
} else {
    // Attempt to decode to ensure it's valid JSON, otherwise wrap it
    $decoded = json_decode($output);
    if ($decoded === null) {
        // Output was not JSON, likely a Python error trace
        echo json_encode(['status' => 'error', 'message' => 'Raw Python Error: ' . $output]);
    } else {
        echo $output;
    }
}
?>