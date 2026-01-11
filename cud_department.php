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
        $name = $_POST['department_name'];
        $description = $_POST['description'];

        if ($action === 'add') {
            // Check for duplicate department name
            $checkSql = "SELECT COUNT(*) FROM department WHERE department_name = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("s", $name);
            $checkStmt->execute();
            $checkStmt->bind_result($count);
            $checkStmt->fetch();
            $checkStmt->close();

            if ($count > 0) {
                header("Location: jobDepartment.php?status=error&message=" . urlencode("$name department existed in the database.") . $emailQuery . $redirectView);
                exit();
            }

            // INSERT (is_archived default 0)
            $sql = "INSERT INTO department (department_name, description, is_archived) VALUES (?, ?, 0)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $name, $description);
            $message = "Department added successfully!";
        } else { // edit
            $id = $_POST['department_id'];
            $sql = "UPDATE department SET department_name = ?, description = ? WHERE department_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $name, $description, $id); 
            $message = "Department updated successfully!";
        }
        
       if ($stmt->execute()) {
            header("Location: jobDepartment.php?status=success&message=" . urlencode($message) . $emailQuery . $redirectView);
        } else {
            header("Location: jobDepartment.php?status=error&message=" . urlencode("Database error: " . $stmt->error) . $emailQuery . $redirectView);
        }
        $stmt->close();
    } 

    // --- 2. HANDLE BULK ACTIONS ---
    else {
        // Collect IDs
        $ids = [];
        if (isset($_POST['ids']) && is_array($_POST['ids'])) {
            $ids = $_POST['ids'];
        } elseif (isset($_POST['department_id'])) {
            $ids[] = $_POST['department_id'];
        }

        if (empty($ids)) {
             header("Location: jobDepartment.php?status=error&message=" . urlencode("No items selected.") . $emailQuery . $redirectView);
             exit();
        }

        // Prepare IN clause
        $types = str_repeat('i', count($ids));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        if ($action === 'delete') {
            // ARCHIVE (Soft Delete)
            $sql = "UPDATE department SET is_archived = 1 WHERE department_id IN ($placeholders)";
            $message = "Selected department(s) moved to archive!";
        } elseif ($action === 'restore') {
            // RESTORE
            $sql = "UPDATE department SET is_archived = 0 WHERE department_id IN ($placeholders)";
            $message = "Selected department(s) restored successfully!";
        } elseif ($action === 'permanent_delete') {
            // PERMANENT DELETE
            $sql = "DELETE FROM department WHERE department_id IN ($placeholders)";
            $message = "Selected department(s) permanently deleted!";
        } else {
            header("Location: jobDepartment.php?status=error&message=" . urlencode("Invalid action.") . $emailQuery . $redirectView);
            exit();
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$ids);

        if ($stmt->execute()) {
            header("Location: jobDepartment.php?status=success&message=" . urlencode($message) . $emailQuery . $redirectView);
        } else {
            header("Location: jobDepartment.php?status=error&message=" . urlencode("Database error: " . $stmt->error) . $emailQuery . $redirectView);
        }
        $stmt->close();
    }

} else {
    header("Location: jobDepartment.php?status=error&message=" . urlencode("Invalid action request.") . $emailQuery);
}

$conn->close();
exit();
?>