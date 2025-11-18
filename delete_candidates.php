<?php
include 'connection.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = array_map('intval', $_POST['ids']);
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $stmt = $conn->prepare("DELETE FROM candidate WHERE candidate_id IN ($placeholders)");

        if ($stmt) {
            $stmt->bind_param($types, ...$ids);
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = count($ids) . ' candidate(s) deleted successfully.';
            } else {
                $response['message'] = 'Failed to delete candidates.';
            }
            $stmt->close();
        } else {
            $response['message'] = 'Database query preparation failed.';
        }
    } else {
        $response['message'] = 'No valid IDs provided.';
    }
} else {
    $response['message'] = 'Invalid request or no IDs provided.';
}

$conn->close();
echo json_encode($response);
?>
