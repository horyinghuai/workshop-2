<?php
include 'connection.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action_type'] ?? '';
    $candidate_id = isset($_POST['candidate_id']) ? intval($_POST['candidate_id']) : 0;

    if ($candidate_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Candidate ID.']);
        exit;
    }

    if ($action === 'update') {
        $name = $_POST['name'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $email = $_POST['email'] ?? '';
        $contact = $_POST['contact_number'] ?? '';
        $address = $_POST['address'] ?? '';

        $sql = "UPDATE candidate SET name=?, gender=?, email=?, contact_number=?, address=? WHERE candidate_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $name, $gender, $email, $contact, $address, $candidate_id);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Candidate updated successfully.';
        } else {
            $response['message'] = 'Database error: ' . $stmt->error;
        }
        $stmt->close();

    } elseif ($action === 'delete') {
        // 1. Delete associated files
        $sqlFiles = "SELECT resume_original, resume_formatted FROM candidate WHERE candidate_id = ?";
        if ($stmt = $conn->prepare($sqlFiles)) {
            $stmt->bind_param("i", $candidate_id);
            $stmt->execute();
            $stmt->bind_result($orig, $fmt);
            if ($stmt->fetch()) {
                if (!empty($orig) && file_exists($orig)) @unlink($orig);
                if (!empty($fmt) && file_exists($fmt)) @unlink($fmt);
            }
            $stmt->close();
        }

        // 2. Delete Record
        $sql = "DELETE FROM candidate WHERE candidate_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $candidate_id);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Candidate deleted successfully.';
        } else {
            $response['message'] = 'Database error: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $response['message'] = 'Invalid action type.';
    }
} else {
    $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
?>