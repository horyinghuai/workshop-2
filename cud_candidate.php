<?php
include 'connection.php';
header('Content-Type: application/json');

$action = $_POST['action_type'] ?? '';
$id = $_POST['candidate_id'] ?? 0;

$response = ['success' => false, 'message' => 'Invalid Request'];

if ($action == 'update') {
    $name = $_POST['name'];
    $gender = $_POST['gender'];
    $email = $_POST['email'];
    $contact = $_POST['contact_number'];
    $address = $_POST['address'];

    $stmt = $conn->prepare("UPDATE candidate SET name=?, gender=?, email=?, contact_number=?, address=? WHERE candidate_id=?");
    $stmt->bind_param("sssssi", $name, $gender, $email, $contact, $address, $id);
    
    if ($stmt->execute()) {
        $response = ['success' => true, 'message' => 'Candidate updated successfully'];
    } else {
        $response = ['success' => false, 'message' => 'Update failed: ' . $conn->error];
    }

} elseif ($action == 'delete') {
    // Soft Delete (Archive)
    $stmt = $conn->prepare("UPDATE candidate SET is_archived = 1 WHERE candidate_id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $response = ['success' => true, 'message' => 'Candidate moved to Archive'];
    } else {
        $response = ['success' => false, 'message' => 'Archive failed: ' . $conn->error];
    }

} elseif ($action == 'restore') {
    // Restore from Archive
    $stmt = $conn->prepare("UPDATE candidate SET is_archived = 0 WHERE candidate_id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $response = ['success' => true, 'message' => 'Candidate restored successfully'];
    } else {
        $response = ['success' => false, 'message' => 'Restore failed: ' . $conn->error];
    }

} elseif ($action == 'permanent_delete') {
    // Permanent Delete
    $stmt = $conn->prepare("DELETE FROM candidate WHERE candidate_id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $response = ['success' => true, 'message' => 'Candidate permanently deleted'];
    } else {
        $response = ['success' => false, 'message' => 'Delete failed: ' . $conn->error];
    }
}

echo json_encode($response);
$conn->close();
?>