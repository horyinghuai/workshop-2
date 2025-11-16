<?php
session_start();
include 'connection.php';

// Fetch the current email from the URL
if (isset($_GET['email'])) {
    $current_email = $conn->real_escape_string($_GET['email']);
} else {
    // Redirect to uploadResumeYing.php if email is not provided
    $_SESSION['upload_message'] = "Email is required to upload resume.";
    $_SESSION['upload_error'] = true;
    header("Location: uploadResumeYing.php");
    exit();
}

// Check if form was submitted
if (isset($_POST['submit']) && isset($_FILES['resume_file'])) {

    // --- 1. Get Form Data ---
    // Use intval to ensure job_id is an integer
    $job_id = intval($_POST['job_position']);

    // --- 2. Get File Details ---
    $file = $_FILES['resume_file'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    $fileType = $file['type'];

    // Get file extension
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Allowed extensions
    $allowed = ['pdf', 'docx'];

    // --- 3. Define Upload Directory ---
    // We use a relative path. Ensure this directory exists and is writable!
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        // Create the directory if it doesn't exist
        // 0755 permissions are common, and `true` allows recursive creation
        if (!mkdir($upload_dir, 0755, true)) {
            $_SESSION['upload_message'] = "Failed to create upload directory. Please contact admin.";
            $_SESSION['upload_error'] = true;
            header("Location: uploadResumeYing.php");
            exit();
        }
    }

    // --- 4. File Validation ---
    if (in_array($fileExt, $allowed)) {
        if ($fileError === 0) {
            // 5MB limit
            if ($fileSize <= 5 * 1024 * 1024) {
                
                // --- 5. Create Unique File Name & Path ---
                // This prevents files with the same name from overwriting each other
                $newFileName = uniqid('', true) . "_" . time() . "." . $fileExt;
                $fileDestination = $upload_dir . $newFileName;

                // --- 6. Move File ---
                if (move_uploaded_file($fileTmpName, $fileDestination)) {
                    
                    // --- 7. Save to Database ---
                    // The file was moved successfully, now save the *path* to the DB
                    // We assume the 'candidate' table has these columns.
                    // 'status' is set to 'Active' as a default for new uploads.
                    $sql = "INSERT INTO candidate (job_id, resume_original, email_user, status, applied_date) VALUES (?, ?, ?, 'Active', CURDATE())";

                    if ($stmt = $conn->prepare($sql)) {
                        // 'iss' means integer for job_id, string for fileDestination, string for email_user
                        $stmt->bind_param("iss", $job_id, $fileDestination, $current_email);

                        if ($stmt->execute()) {
                            // Success!
                            $_SESSION['upload_message'] = "Resume uploaded successfully!";
                            $_SESSION['upload_error'] = false;
                        } else {
                            // Database insertion failed
                            $_SESSION['upload_message'] = "File uploaded, but database record failed: " . $stmt->error;
                            $_SESSION['upload_error'] = true;
                        }
                        $stmt->close();
                    } else {
                        // SQL statement preparation failed
                        $_SESSION['upload_message'] = "Database error: " . $conn->error;
                        $_SESSION['upload_error'] = true;
                    }

                } else {
                    // File move failed
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

    // --- 8. Redirect Back ---
    $conn->close();
    header("Location: uploadResumeYing.php");
    exit();

} else {
    // If accessed directly or without a file
    $_SESSION['upload_message'] = "Invalid request.";
    $_SESSION['upload_error'] = true;
    header("Location: uploadResumeYing.php");
    exit();
}
?>