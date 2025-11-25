<?php
session_start();
include 'connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $candidate_id = $_POST['candidate_id'];
    $process_id = $_POST['process_id'];

    // 1. Update Data First
    $u_name = $_POST['name'];
    $u_gender = $_POST['gender'];
    $u_email = $_POST['email'];
    $u_contact = $_POST['contact_number'];
    $u_address = $_POST['address'];
    $u_objective = $_POST['objective'];
    $u_education = $_POST['education'];
    $u_skills = $_POST['skills'];
    $u_experience = $_POST['experience'];
    $u_language = $_POST['language'];

    $update_sql = "UPDATE candidate SET name=?, gender=?, email=?, contact_number=?, address=?, objective=?, education=?, skills=?, experience=?, language=? WHERE candidate_id=?";
    
    if ($stmt = $conn->prepare($update_sql)) {
        $stmt->bind_param("ssssssssssi", $u_name, $u_gender, $u_email, $u_contact, $u_address, $u_objective, $u_education, $u_skills, $u_experience, $u_language, $candidate_id);
        
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