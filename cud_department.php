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

    // --- 1. HANDLE ADD and EDIT ---
    if ($action === 'add' || $action === 'edit') {
        $name = $_POST['department_name'];
        $description = $_POST['description'];

        if ($action === 'add') {
            // INSERT (is_archived default 0)
            $sql = "INSERT INTO department (department_name, description, is_archived) VALUES (?, ?, 0)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $name, $description);
            $message = "Department added successfully!";
        } else { // action === 'edit'
            // UPDATE
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

    // --- 2. HANDLE ARCHIVE (Soft Delete) ---
    elseif ($action === 'delete' && isset($_POST['department_id'])) {
        $id = $_POST['department_id'];
        
        $sql = "UPDATE department SET is_archived = 1 WHERE department_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

      if ($stmt->execute()) {
            $message = "Department moved to archive!";
            header("Location: jobDepartment.php?status=success&message=" . urlencode($message) . $emailQuery . $redirectView);
        } else {
            header("Location: jobDepartment.php?status=error&message=" . urlencode("Database error: " . $stmt->error) . $emailQuery . $redirectView);
        }
        $stmt->close();
    }

    // --- 3. HANDLE RESTORE ---
    elseif ($action === 'restore' && isset($_POST['department_id'])) {
        $id = $_POST['department_id'];
        
        $sql = "UPDATE department SET is_archived = 0 WHERE department_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

      if ($stmt->execute()) {
            $message = "Department restored successfully!";
            header("Location: jobDepartment.php?status=success&message=" . urlencode($message) . $emailQuery . "&view=archive");
        } else {
            header("Location: jobDepartment.php?status=error&message=" . urlencode("Database error: " . $stmt->error) . $emailQuery . "&view=archive");
        }
        $stmt->close();
    }

    // --- 4. HANDLE PERMANENT DELETE ---
    elseif ($action === 'permanent_delete' && isset($_POST['department_id'])) {
        $id = $_POST['department_id'];
        
        $sql = "DELETE FROM department WHERE department_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

      if ($stmt->execute()) {
            $message = "Department permanently deleted!";
            header("Location: jobDepartment.php?status=success&message=" . urlencode($message) . $emailQuery . "&view=archive");
        } else {
            header("Location: jobDepartment.php?status=error&message=" . urlencode("Database error: " . $stmt->error) . $emailQuery . "&view=archive");
        }
        $stmt->close();
    }

} else {
    header("Location: jobDepartment.php?status=error&message=" . urlencode("Invalid action request.") . $emailQuery);
}

$conn->close();
exit();
?>