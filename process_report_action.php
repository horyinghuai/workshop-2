<?php
session_start();
include 'connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $candidate_id = $_POST['candidate_id'];
    $process_id = $_POST['process_id'];

    // 1. Update Data First
    // Retrieve all fields from POST. Use trim to clean up whitespace.
    $u_name = isset($_POST['name']) ? trim($_POST['name']) : null;
    $u_gender = isset($_POST['gender']) ? trim($_POST['gender']) : null;
    $u_email = isset($_POST['email']) ? trim($_POST['email']) : null;
    $u_contact = isset($_POST['contact_number']) ? trim($_POST['contact_number']) : null;
    $u_address = isset($_POST['address']) ? trim($_POST['address']) : null;
    $u_objective = isset($_POST['objective']) ? trim($_POST['objective']) : null;
    $u_education = isset($_POST['education']) ? trim($_POST['education']) : null;
    $u_skills = isset($_POST['skills']) ? trim($_POST['skills']) : null;
    $u_experience = isset($_POST['experience']) ? trim($_POST['experience']) : null;
    $u_language = isset($_POST['language']) ? trim($_POST['language']) : null;
    
    // --- FIX FOR OTHERS SECTION ---
    // If 'others' is submitted but empty, treat it as NULL or empty string.
    $u_others = isset($_POST['others']) ? trim($_POST['others']) : null;
    if ($u_others === '') {
        $u_others = null; // Convert empty string to NULL for DB if column allows NULL
    }

    // Add 'others' to the SQL Update
    $update_sql = "UPDATE candidate SET name=?, gender=?, email=?, contact_number=?, address=?, objective=?, education=?, skills=?, experience=?, language=?, others=? WHERE candidate_id=?";
    
    if ($stmt = $conn->prepare($update_sql)) {
        // Updated bind_param signature: added one 's' for 'others'
        // Types: sssssssssssi (11 strings + 1 integer)
        $stmt->bind_param("sssssssssssi", $u_name, $u_gender, $u_email, $u_contact, $u_address, $u_objective, $u_education, $u_skills, $u_experience, $u_language, $u_others, $candidate_id);
        
        if ($stmt->execute()) {
            // 2. Run Python Script with Process ID
            session_write_close(); // UNLOCK SESSION for polling

            $escaped_id = escapeshellarg($candidate_id);
            $escaped_pid = escapeshellarg($process_id);
            
            // Use 'python' or full path like 'C:\\Python311\\python.exe'
            $command = "python generate_report.py $escaped_id $escaped_pid 2>&1";
            $output = shell_exec($command);
            
            // Cleanup
            if(file_exists("progress_$process_id.txt")) {
                @unlink("progress_$process_id.txt");
            }

            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
    $conn->close();
}
?>