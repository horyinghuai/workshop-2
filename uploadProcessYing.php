<?php
session_start();
include 'connection.php';

// --- Configuration ---
$python_path = 'python'; 
$script_path = 'extract_resume.py'; 
// ---------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $current_email = isset($_POST['email']) ? $_POST['email'] : '';
    $process_id = isset($_POST['process_id']) ? $_POST['process_id'] : '';
    $job_id = isset($_POST['job_position']) ? intval($_POST['job_position']) : 0;

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
            
            // --- DB INSERT INITIAL (Placeholder) ---
            $sql_insert = "INSERT INTO candidate (job_id, resume_original, email_user, status, applied_date) VALUES (?, ?, ?, 'Active', CURDATE())";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("iss", $job_id, $fileDestination, $current_email);
            
            if ($stmt_insert->execute()) {
                $candidate_id = $conn->insert_id;
                $stmt_insert->close();

                // --- CLOSE SESSION ---
                session_write_close();

                // --- RUN PYTHON EXTRACTION ---
                $command = $python_path . ' ' . escapeshellarg($script_path) . ' ' . escapeshellarg($fileDestination) . ' ' . escapeshellarg($process_id) . ' 2>&1';
                $json_output = shell_exec($command);
                
                if(file_exists("progress_$process_id.txt")) {
                    @unlink("progress_$process_id.txt");
                }

                $extracted_data = json_decode($json_output, true);

                include 'connection.php'; 

                if ($extracted_data && !isset($extracted_data['error'])) {
                    
                    // --- DUPLICATE CHECK LOGIC ---
                    $ext_email = $extracted_data['email'] ?? '';

                    // Check if this candidate already applied for THIS job position
                    // We check against OTHER candidates (not the one we just inserted with $candidate_id)
                    $dup_sql = "SELECT candidate_id FROM candidate 
                                WHERE job_id = ? 
                                AND candidate_id != ? 
                                AND (email IS NOT NULL AND email != '' AND email = ?) LIMIT 1";
                                
                    $stmt_dup = $conn->prepare($dup_sql);
                    $stmt_dup->bind_param("iis", $job_id, $candidate_id, $ext_email);
                    $stmt_dup->execute();
                    $stmt_dup->store_result();
                    
                    if ($stmt_dup->num_rows > 0) {
                        // --- DUPLICATE FOUND ---
                        $stmt_dup->close();
                        
                        // 1. Delete the file
                        if (file_exists($fileDestination)) unlink($fileDestination);
                        
                        // 2. Delete the placeholder DB record
                        $conn->query("DELETE FROM candidate WHERE candidate_id = $candidate_id");
                        
                        echo json_encode([
                            'status' => 'error', 
                            'message' => 'This candidate has already applied for this job position. Duplicate application rejected.'
                        ]);
                        exit();
                    }
                    $stmt_dup->close();
                    
                    // --- NO DUPLICATE: PROCEED TO SAVE ---

                    $formatted_content = "--- EXTRACTED RESUME DATA ---\n\nName: " . ($extracted_data['name'] ?? 'N/A') . "\nEmail: " . ($extracted_data['email'] ?? 'N/A') . "\nContact: " . ($extracted_data['contact_number'] ?? 'N/A') . "\n\n--- SKILLS ---\n" . ($extracted_data['skills'] ?? 'N/A') . "\n\n--- EXPERIENCE ---\n" . ($extracted_data['experience'] ?? 'N/A');
                    
                    $formatted_filename = 'formatted_' . $candidate_id . '_' . time() . '.txt';
                    $formatted_filepath = $upload_dir . $formatted_filename;
                    file_put_contents($formatted_filepath, $formatted_content);

                    // --- UPDATE DB WITH EXTRACTED DATA ---
                    $sql_update = "UPDATE candidate SET name=?, gender=?, email=?, contact_number=?, address=?, objective=?, education=?, skills=?, experience=?, language=?, others=?, resume_formatted=? WHERE candidate_id=?";
                    
                    $stmt_update = $conn->prepare($sql_update);
                    
                    // Map variables
                    $val_name = $extracted_data['name'] ?? null;
                    $val_gender = $extracted_data['gender'] ?? null; 
                    $val_email = $extracted_data['email'] ?? null;
                    $val_contact = $extracted_data['contact_number'] ?? null;
                    $val_address = $extracted_data['address'] ?? null;
                    $val_objective = $extracted_data['objective'] ?? null;
                    $val_education = $extracted_data['education'] ?? null;
                    $val_skills = $extracted_data['skills'] ?? null;
                    $val_experience = $extracted_data['experience'] ?? null;
                    $val_language = $extracted_data['language'] ?? null;
                    
                    // Explicitly handle Others as NULL if empty
                    $val_others = $extracted_data['others'];
                    if (empty($val_others) || trim($val_others) === '') {
                        $val_others = null;
                    }

                    // Bind Params: 12 strings + 1 integer = 13 total
                    $stmt_update->bind_param("ssssssssssssi", 
                        $val_name, 
                        $val_gender, 
                        $val_email, 
                        $val_contact, 
                        $val_address, 
                        $val_objective, 
                        $val_education, 
                        $val_skills, 
                        $val_experience, 
                        $val_language, 
                        $val_others,       
                        $formatted_filepath, 
                        $candidate_id
                    );
                    
                    if ($stmt_update->execute()) {
                        echo json_encode(['status' => 'success', 'candidate_id' => $candidate_id, 'email' => $current_email]);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'Database Update Failed: ' . $stmt_update->error]);
                    }
                } else {
                    // Extraction failed - cleanup
                    $conn->query("DELETE FROM candidate WHERE candidate_id = $candidate_id");
                    if (file_exists($fileDestination)) unlink($fileDestination);
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