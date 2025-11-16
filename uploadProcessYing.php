<?php
session_start();
include 'connection.php';

// --- (Configuration) ---
// IMPORTANT: Update this path to your Python interpreter
$python_path = 'python3'; 
// IMPORTANT: Update this path to where you saved the python script
$script_path = '/path/to/your/extract_resume.py'; 
// --- (End Configuration) ---


if (isset($_GET['email'])) {
    $current_email = $conn->real_escape_string($_GET['email']);
} else {
    $_SESSION['upload_message'] = "Email is required to upload resume.";
    $_SESSION['upload_error'] = true;
    header("Location: uploadResumeYing.php");
    exit();
}

if (isset($_POST['submit']) && isset($_FILES['resume_file'])) {

    $job_id = intval($_POST['job_position']);
    $file = $_FILES['resume_file'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowed = ['pdf', 'docx'];
    $upload_dir = 'uploads/';

    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            $_SESSION['upload_message'] = "Failed to create upload directory.";
            $_SESSION['upload_error'] = true;
            header("Location: uploadResumeYing.php");
            exit();
        }
    }

    if (in_array($fileExt, $allowed)) {
        if ($fileError === 0) {
            if ($fileSize <= 5 * 1024 * 1024) {
                
                $newFileName = uniqid('', true) . "_" . time() . "." . $fileExt;
                $fileDestination = $upload_dir . $newFileName;

                if (move_uploaded_file($fileTmpName, $fileDestination)) {
                    
                    // --- 1. Initial Insert ---
                    // Insert the basic record to get a candidate_id
                    $sql_insert = "INSERT INTO candidate (job_id, resume_original, email_user, status, applied_date) VALUES (?, ?, ?, 'Active', CURDATE())";
                    
                    if ($stmt_insert = $conn->prepare($sql_insert)) {
                        $stmt_insert->bind_param("iss", $job_id, $fileDestination, $current_email);

                        if ($stmt_insert->execute()) {
                            // --- 2. Get the new candidate_id ---
                            $candidate_id = $conn->insert_id;
                            $stmt_insert->close();

                            // --- 3. Call Python NLP Script ---
                            // Use escapeshellarg to prevent command injection
                            $command = $python_path . ' ' . escapeshellarg($script_path) . ' ' . escapeshellarg($fileDestination);
                            $json_output = shell_exec($command);
                            
                            $extracted_data = null;
                            if ($json_output) {
                                $extracted_data = json_decode($json_output, true);
                            }

                            if ($extracted_data && !isset($extracted_data['error'])) {
                                // --- 4. Create Formatted Resume File ---
                                $formatted_content = "--- EXTRACTED RESUME DATA ---\n\n";
                                $formatted_content .= "Name: " . ($extracted_data['name'] ?? 'N/A') . "\n";
                                $formatted_content .= "Email: " . ($extracted_data['email'] ?? 'N/A') . "\n";
                                $formatted_content .= "Contact: " . ($extracted_data['contact_number'] ?? 'N/A') . "\n\n";
                                $formatted_content .= "--- OBJECTIVE ---\n" . ($extracted_data['objective'] ?? 'N/A') . "\n\n";
                                $formatted_content .= "--- EDUCATION ---\n" . ($extracted_data['education'] ?? 'N/A') . "\n\n";
                                $formatted_content .= "--- SKILLS ---\n" . ($extracted_data['skills'] ?? 'N/A') . "\n\n";
                                $formatted_content .= "--- EXPERIENCE ---\n" . ($extracted_data['experience'] ?? 'N/A') . "\n\n";
                                $formatted_content .= "--- ACHIEVEMENTS ---\n" . ($extracted_data['achievements'] ?? 'N/A') . "\n\n";
                                $formatted_content .= "--- LANGUAGES ---\n" . ($extracted_data['language'] ?? 'N/A') . "\n\n";

                                $formatted_filename = 'formatted_' . $candidate_id . '_' . time() . '.txt';
                                $formatted_filepath = $upload_dir . $formatted_filename;
                                file_put_contents($formatted_filepath, $formatted_content);

                                // --- 5. Update the Database Record ---
                                $sql_update = "UPDATE candidate SET 
                                    name = ?, 
                                    gender = ?, 
                                    email = ?, 
                                    contact_number = ?, 
                                    address = ?, 
                                    objective = ?, 
                                    education = ?, 
                                    skills = ?, 
                                    experience = ?, 
                                    achievements = ?, 
                                    language = ?, 
                                    others = ?, 
                                    resume_formatted = ?
                                WHERE candidate_id = ?";
                                
                                if($stmt_update = $conn->prepare($sql_update)) {
                                    $gender = $extracted_data['gender'] ?? null; // Python script doesn't extract this yet
                                    $address = $extracted_data['address'] ?? null; // Python script doesn't extract this yet
                                    $full_text = $extracted_data['full_text'] ?? null;

                                    $stmt_update->bind_param("sssssssssssssi",
                                        $extracted_data['name'],
                                        $gender,
                                        $extracted_data['email'],
                                        $extracted_data['contact_number'],
                                        $address,
                                        $extracted_data['objective'],
                                        $extracted_data['education'],
                                        $extracted_data['skills'],
                                        $extracted_data['experience'],
                                        $extracted_data['achievements'],
                                        $extracted_data['language'],
                                        $full_text, // Storing full text in 'others'
                                        $formatted_filepath,
                                        $candidate_id
                                    );
                                    $stmt_update->execute();
                                    $stmt_update->close();
                                }
                                
                                // --- 6. Redirect to Preview Page ---
                                $_SESSION['upload_message'] = "Resume uploaded and processed successfully!";
                                $_SESSION['upload_error'] = false;
                                header("Location: previewResumeYing.php?id=" . $candidate_id);
                                $conn->close();
                                exit();

                            } else {
                                // NLP failed, but file is uploaded. Redirect anyway.
                                $_SESSION['upload_message'] = "Resume uploaded, but text extraction failed. Please review manually.";
                                $_SESSION['upload_error'] = true;
                                header("Location: previewResumeYing.php?id=" . $candidate_id);
                                $conn->close();
                                exit();
                            }
                        }
                    }
                    // Handle INSERT fail
                    $_SESSION['upload_message'] = "File uploaded, but database record failed: " . $conn->error;
                    $_SESSION['upload_error'] = true;

                } else {
                    $_SESSION['upload_message'] = "Failed to move uploaded file.";
                    $_SESSION['upload_error'] = true;
                }
            } else {
                $_SESSION['upload_message'] = "Your file is too large! (Max 5MB)";
                $_SESSION['upload_error'] = true;
            }
        } else {
            $_SESSION['upload_message'] = "There was an error uploading your file. Error code: " . $fileError;
            $_SESSION['upload_error'] = true;
        }
    } else {
        $_SESSION['upload_message'] = "You cannot upload files of this type! (Allowed: PDF, DOCX)";
        $_SESSION['upload_error'] = true;
    }

    // Redirect back to upload page on failure
    $conn->close();
    header("Location: uploadResumeYing.php");
    exit();

} else {
    $_SESSION['upload_message'] = "Invalid request.";
    $_SESSION['upload_error'] = true;
    header("Location: uploadResumeYing.php");
    exit();
}
?>