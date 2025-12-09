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
            // INSERT (Add New Department)
            $sql = "INSERT INTO job_position (department_id, job_name, description, education, skills, experience, language, others) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssssss", $deptId, $name, $description, $education, $skills, $experience, $language, $others);
            $message = "Job added successfully!";
        } else { // action === 'edit'
            // UPDATE (Edit Existing Department)
            $id = $_POST['job_id'];
            $sql = "UPDATE job_position
            SET 
                department_id = ?, 
                job_name = ?, 
                description = ?, 
                education = ?, 
                skills = ?, 
                experience = ?, 
                language = ?, 
                others = ?
            WHERE 
                job_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssssssi", 
                    $deptId,
                    $name,
                    $description,
                    $education, 
                    $skills,
                    $experience,
                    $language,
                    $others,
                    $jobId); // "ssi" = two strings, one integer
            $message = "Job updated successfully!";
        }

       if ($stmt->execute()) {
            $message = $action === 'add' ? "Job added successfully!" : "Job updated successfully!";
            header("Location: jobPosition.php?status=success&message=" . urlencode($message) . $emailQuery);
        } else {
            // Append error and email query string
            header("Location: jobPosition.php?status=error&message=" . urlencode("Database error: " . $stmt->error) . $emailQuery);
        }
        $stmt->close();
    }

    // --- 2. HANDLE DELETE (from the delete modal) ---
    elseif ($action === 'delete' && isset($_POST['job_id'])) {
        $id = $_POST['job_id'];

        // DELETE Department
        $sql = "DELETE FROM job_position WHERE job_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id); // "i" = one integer

       if ($stmt->execute()) {
            $message = "Job deleted successfully!";
            // 🛑 FIX 4: Append status and email query string
            header("Location: jobPosition.php?status=success&message=" . urlencode($message) . $emailQuery);
        } else {
            // 🛑 FIX 5: Append error and email query string
            header("Location: jobPosition.php?status=error&message=" . urlencode("Database error: " . $stmt->error) . $emailQuery);
        }

        $stmt->close();
    }
} else {
    // Handle direct access to the script without POST data
   header("Location: jobPosition.php?status=error&message=" . urlencode("Invalid action request.") . $emailQuery);
}

$conn->close();
exit();
?>