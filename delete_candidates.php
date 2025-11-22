<?php
include 'connection.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ids']) && is_array($_POST['ids'])) {
    // Sanitize IDs
    $ids = array_map('intval', $_POST['ids']);
    
    if (!empty($ids)) {
        // Prepare string for IN clause (?,?,?)
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        // --- STEP 1: FETCH AND DELETE PHYSICAL FILES ---
        $sqlSelect = "SELECT resume_original, resume_formatted FROM candidate WHERE candidate_id IN ($placeholders)";
        $stmtSelect = $conn->prepare($sqlSelect);

        if ($stmtSelect) {
            $stmtSelect->bind_param($types, ...$ids);
            if ($stmtSelect->execute()) {
                $result = $stmtSelect->get_result();
                while ($row = $result->fetch_assoc()) {
                    // Delete Original Resume
                    if (!empty($row['resume_original']) && file_exists($row['resume_original'])) {
                        unlink($row['resume_original']);
                    }
                    // Delete Formatted Resume
                    if (!empty($row['resume_formatted']) && file_exists($row['resume_formatted'])) {
                        unlink($row['resume_formatted']);
                    }
                }
            }
            $stmtSelect->close();
        }

        // --- STEP 2: DELETE DATABASE RECORDS ---
        $stmt = $conn->prepare("DELETE FROM candidate WHERE candidate_id IN ($placeholders)");

        if ($stmt) {
            $stmt->bind_param($types, ...$ids);
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = count($ids) . ' candidate(s) and associated files deleted successfully.';
            } else {
                $response['message'] = 'Failed to delete candidates from database.';
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