<?php
// Include the database connection
include 'connection.php';

// Ensure the connection is successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
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
            $id = $_POST['department_id'];
            $sql = "UPDATE department SET department_name = ?, description = ? WHERE department_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $name, $description, $id); // "ssi" = two strings, one integer
            $message = "Department updated successfully!";
        }
        
        if ($stmt->execute()) {
            // Redirect back to the main page on success
            header("Location: jobDepartment.php?status=success&message=" . urlencode($message));
        } else {
            header("Location: jobDepartment.php?status=error&message=" . urlencode("Database error: " . $stmt->error));
        }
        
        $stmt->close();
    } 

    // --- 2. HANDLE DELETE (from the delete modal) ---
    elseif ($action === 'delete' && isset($_POST['department_id'])) {
        $id = $_POST['department_id'];
        
        // DELETE Department
        $sql = "DELETE FROM department WHERE department_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id); // "i" = one integer

        if ($stmt->execute()) {
            $message = "Department deleted successfully!";
            header("Location: jobDepartment.php?status=success&message=" . urlencode($message));
        } else {
            header("Location: jobDepartment.php?status=error&message=" . urlencode("Database error: " . $stmt->error));
        }
        
        $stmt->close();
    }
} else {
    // Handle direct access to the script without POST data
    header("Location: jobDepartment.php?status=error&message=" . urlencode("Invalid action request."));
}

$conn->close();
exit();
?>