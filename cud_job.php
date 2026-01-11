<?php
// Include the database connection
include 'connection.php';

// Ensure the connection is successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$emailQuery = "";
if (isset($_POST['email']) && !empty($_POST['email'])) {
    $emailQuery = "&email=" . urlencode($_POST['email']);
}

// Check the action type sent via POST
if (isset($_POST['action_type'])) {
    $action = $_POST['action_type'];
    $redirectView = isset($_POST['view_type']) && $_POST['view_type'] === 'archive' ? '&view=archive' : '';

    // --- 1. HANDLE ADD and EDIT (Single Item) ---
    if ($action === 'add' || $action === 'edit') {
        $jobId = $_POST['job_id'];
        $name = $_POST['job_name'];
        $deptId = $_POST['department_id'];
        $description = $_POST['description'];
        $education = $_POST['education'];
        $skills = $_POST['skills'];
        $experience = $_POST['experience'];
        $language = $_POST['language'];
        $others = $_POST['others'];

        if ($action === 'add') {
            // Check for duplicate job position name
            $checkSql = "SELECT COUNT(*) FROM job_position WHERE job_name = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("s", $name);
            $checkStmt->execute();
            $checkStmt->bind_result($count);
            $checkStmt->fetch();
            $checkStmt->close();

            if ($count > 0) {
                header("Location: jobPosition.php?status=error&message=" . urlencode("$name job position existed in the database.") . $emailQuery . $redirectView);
                exit();
            }

            $sql = "INSERT INTO job_position (department_id, job_name, description, education, skills, experience, language, others, is_archived) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssssss", $deptId, $name, $description, $education, $skills, $experience, $language, $others);
            $message = "Job added successfully!";
        } else { // edit
            $sql = "UPDATE job_position SET department_id = ?, job_name = ?, description = ?, education = ?, skills = ?, experience = ?, language = ?, others = ? WHERE job_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssssssi", $deptId, $name, $description, $education, $skills, $experience, $language, $others, $jobId);
            $message = "Job updated successfully!";
        }

       if ($stmt->execute()) {
            header("Location: jobPosition.php?status=success&message=" . urlencode($message) . $emailQuery . $redirectView);
        } else {
            header("Location: jobPosition.php?status=error&message=" . urlencode("Database error: " . $stmt->error) . $emailQuery . $redirectView);
        }
        $stmt->close();
    }

    // --- 2. HANDLE BULK ACTIONS ---
    else {
        // Collect IDs (support both array and single value for backward compatibility)
        $ids = [];
        if (isset($_POST['ids']) && is_array($_POST['ids'])) {
            $ids = $_POST['ids'];
        } elseif (isset($_POST['job_id'])) {
            $ids[] = $_POST['job_id'];
        }

        if (empty($ids)) {
             header("Location: jobPosition.php?status=error&message=" . urlencode("No items selected.") . $emailQuery . $redirectView);
             exit();
        }

        // Prepare the IN clause (?,?,?)
        $types = str_repeat('i', count($ids));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        if ($action === 'delete') {
            // ARCHIVE (Soft Delete)
            $sql = "UPDATE job_position SET is_archived = 1 WHERE job_id IN ($placeholders)";
            $message = "Selected job(s) moved to archive!";
        } elseif ($action === 'restore') {
            // RESTORE
            $sql = "UPDATE job_position SET is_archived = 0 WHERE job_id IN ($placeholders)";
            $message = "Selected job(s) restored successfully!";
        } elseif ($action === 'permanent_delete') {
            // PERMANENT DELETE
            $sql = "DELETE FROM job_position WHERE job_id IN ($placeholders)";
            $message = "Selected job(s) permanently deleted!";
        } else {
            header("Location: jobPosition.php?status=error&message=" . urlencode("Invalid action.") . $emailQuery . $redirectView);
            exit();
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$ids);

        if ($stmt->execute()) {
            header("Location: jobPosition.php?status=success&message=" . urlencode($message) . $emailQuery . $redirectView);
        } else {
            header("Location: jobPosition.php?status=error&message=" . urlencode("Database error: " . $stmt->error) . $emailQuery . $redirectView);
        }
        $stmt->close();
    }

} else {
   header("Location: jobPosition.php?status=error&message=" . urlencode("Invalid action request.") . $emailQuery);
}

$conn->close();
exit();
?>