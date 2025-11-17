<?php
include 'connection.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $candidate_id = isset($_POST['candidate_id']) ? intval($_POST['candidate_id']) : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';

    if ($candidate_id > 0 && $status !== '') {
        $stmt = $conn->prepare("UPDATE candidate SET status = ? WHERE candidate_id = ?");
        $stmt->bind_param('si', $status, $candidate_id);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Status updated successfully.';
        } else {
            $response['message'] = 'Failed to update status.';
        }
        $stmt->close();
    } else {
        $response['message'] = 'Invalid data provided.';
    }
} else {
    $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
?>
