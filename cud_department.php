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

    // --- 1. HANDLE ADD and EDIT (from the main form modal) ---
    if ($action === 'add' || $action === 'edit') {
        $name = $_POST['department_name'];
        $description = $_POST['description'];

        if ($action === 'add') {
            // INSERT (Add New Department)
            $sql = "INSERT INTO department (department_name, description) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $name, $description);
            $message = "Department added successfully!";
        } else { // action === 'edit'
            // UPDATE (Edit Existing Department)
            // 🛑 FIX: Reset embedding to NULL so AI regenerates it
            $id = $_POST['department_id'];
            $sql = "UPDATE department SET embedding = NULL, department_name = ?, description = ? WHERE department_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $name, $description, $id); 
            $message = "Department updated successfully!";
        }
        
       if ($stmt->execute()) {
            // 🛑 FIX: Trigger Python script to generate embeddings
            $command = "python generate_embeddings_dept.py";
            shell_exec($command);
            // --------------------------------------------------

            $message = $action === 'add' ? "Department added successfully!" : "Department updated successfully!";
            header("Location: jobDepartment.php?status=success&message=" . urlencode($message) . $emailQuery);
        } else {
            header("Location: jobDepartment.php?status=error&message=" . urlencode("Database error: " . $stmt->error) . $emailQuery);
        }
        
        $stmt->close();
    } 

    // --- 2. HANDLE DELETE (from the delete modal) ---
    elseif ($action === 'delete' && isset($_POST['department_id'])) {
        $id = $_POST['department_id'];
        
        $sql = "DELETE FROM department WHERE department_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

      if ($stmt->execute()) {
            $message = "Department deleted successfully!";
            header("Location: jobDepartment.php?status=success&message=" . urlencode($message) . $emailQuery);
        } else {
            header("Location: jobDepartment.php?status=error&message=" . urlencode("Database error: " . $stmt->error) . $emailQuery);
        }
        
        $stmt->close();
    }
} else {
    header("Location: jobDepartment.php?status=error&message=" . urlencode("Invalid action request.") . $emailQuery);
}

$conn->close();
exit();
?>