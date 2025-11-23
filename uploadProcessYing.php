<?php
session_start();
include 'connection.php';

// --- Configuration ---
// Ensure these paths are correct for your system
$python_path = 'python'; 
$script_path = 'extract_resume.py'; 
// ---------------------

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Get Parameters
    $current_email = isset($_POST['email']) ? $_POST['email'] : '';
    $process_id = isset($_POST['process_id']) ? $_POST['process_id'] : '';
    $job_id = isset($_POST['job_position']) ? intval($_POST['job_position']) : 0;

    // JSON response header
    header('Content-Type: application/json');

    if (empty($current_email)) {
        echo json_encode(['status' => 'error', 'message' => 'Email missing.']);
        exit;
    }

    if (isset($_FILES['resume_file'])) {
        $file = $_FILES['resume_file'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $upload_dir = 'uploads/';

        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $newFileName = uniqid('', true) . "_" . time() . "." . $fileExt;
        $fileDestination = $upload_dir . $newFileName;

        if (move_uploaded_file($fileTmpName, $fileDestination)) {
            
            // --- DB INSERT INITIAL ---
            $sql_insert = "INSERT INTO candidate (job_id, resume_original, email_user, status, applied_date) VALUES (?, ?, ?, 'Active', CURDATE())";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("iss", $job_id, $fileDestination, $current_email);
            
            if ($stmt_insert->execute()) {
                $candidate_id = $conn->insert_id;
                $stmt_insert->close();

                // --- CRITICAL: CLOSE SESSION WRITING ---
                // This unlocks the session file so check_progress.php can run simultaneously
                session_write_close();

                // --- RUN PYTHON (Blocking but allowed because session is closed) ---
                // Passes file path and process ID to Python
                $command = $python_path . ' ' . escapeshellarg($script_path) . ' ' . escapeshellarg($fileDestination) . ' ' . escapeshellarg($process_id) . ' 2>&1';
                $json_output = shell_exec($command);
                
                // Clean up progress file
                if(file_exists("progress_$process_id.txt")) {
                    @unlink("progress_$process_id.txt");
                }

                $extracted_data = json_decode($json_output, true);

                // Re-open connection for update (good practice)
                include 'connection.php'; 

                if ($extracted_data && !isset($extracted_data['error'])) {
                    
                    // Create Formatted File
                    $formatted_content = "--- EXTRACTED RESUME DATA ---\n\nName: " . ($extracted_data['name'] ?? 'N/A') . "\nEmail: " . ($extracted_data['email'] ?? 'N/A') . "\nContact: " . ($extracted_data['contact_number'] ?? 'N/A') . "\n\n--- SKILLS ---\n" . ($extracted_data['skills'] ?? 'N/A') . "\n\n--- EXPERIENCE ---\n" . ($extracted_data['experience'] ?? 'N/A');
                    
                    $formatted_filename = 'formatted_' . $candidate_id . '_' . time() . '.txt';
                    $formatted_filepath = $upload_dir . $formatted_filename;
                    file_put_contents($formatted_filepath, $formatted_content);

                    // Update DB
                    $sql_update = "UPDATE candidate SET name=?, gender=?, email=?, contact_number=?, address=?, objective=?, education=?, skills=?, experience=?, achievements=?, language=?, others=?, resume_formatted=? WHERE candidate_id=?";
                    
                    $stmt_update = $conn->prepare($sql_update);
                    $ft = $extracted_data['full_text'] ?? '';
                    
                    $stmt_update->bind_param("sssssssssssssi", 
                        $extracted_data['name'], $extracted_data['gender'], $extracted_data['email'], 
                        $extracted_data['contact_number'], $extracted_data['address'], $extracted_data['objective'], 
                        $extracted_data['education'], $extracted_data['skills'], $extracted_data['experience'], 
                        $extracted_data['achievements'], $extracted_data['language'], $ft, 
                        $formatted_filepath, $candidate_id
                    );
                    $stmt_update->execute();
                    
                    echo json_encode(['status' => 'success', 'candidate_id' => $candidate_id, 'email' => $current_email]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Extraction failed: ' . ($extracted_data['error'] ?? 'Unknown error')]);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'DB Insert Failed']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'File move failed']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
    }
}
?>