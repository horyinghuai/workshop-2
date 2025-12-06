<?php
include 'connection.php';
header('Content-Type: application/json');

$ids = $_POST['ids'] ?? [];
$action = $_POST['action'] ?? 'archive'; // Default to archive if not specified

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'No IDs provided']);
    exit;
}

// Sanitize IDs
$idsStr = implode(',', array_map('intval', $ids));

$sql = "";
if ($action == 'restore') {
    // Restore selected
    $sql = "UPDATE candidate SET is_archived = 0 WHERE candidate_id IN ($idsStr)";
} elseif ($action == 'permanent_delete') {
    // Hard delete
    $sql = "DELETE FROM candidate WHERE candidate_id IN ($idsStr)";
} else {
    // Default: Archive (Soft Delete)
    $sql = "UPDATE candidate SET is_archived = 1 WHERE candidate_id IN ($idsStr)";
}

if ($conn->query($sql)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}

$conn->close();
?>