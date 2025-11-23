<?php
session_start();
include 'connection.php';

// Check if ID is provided
if (!isset($_GET['candidate_id']) || empty($_GET['candidate_id'])) {
    die("No candidate ID provided.");
}

// Ensure 'email' key exists in the GET array
$email = isset($_GET['email']) ? $_GET['email'] : null;
if ($email === null) {
    die("No email provided.");
}

$candidate_id = intval($_GET['candidate_id']);
$redirect_url = "uploadResumeYing.php?email=" . urlencode($email) . "&candidate_id=" . urlencode($candidate_id);

// --- 1. HANDLE DELETE ACTION (FROM CANCEL BUTTON) ---
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    
    // A. First, fetch the file paths to delete them physically from the server
    $file_query = "SELECT resume_original, resume_formatted FROM candidate WHERE candidate_id = ?";
    if ($stmt_file = $conn->prepare($file_query)) {
        $stmt_file->bind_param("i", $candidate_id);
        if ($stmt_file->execute()) {
            $result_file = $stmt_file->get_result();
            if ($file_row = $result_file->fetch_assoc()) {
                $file_original = $file_row['resume_original'];
                $file_formatted = $file_row['resume_formatted'];

                // Delete Original File if it exists
                if (!empty($file_original) && file_exists($file_original)) {
                    unlink($file_original);
                }

                // Delete Formatted Text File if it exists
                if (!empty($file_formatted) && file_exists($file_formatted)) {
                    unlink($file_formatted);
                }
            }
        }
        $stmt_file->close();
    }

    // B. Now, delete the record from the database
    $del_sql = "DELETE FROM candidate WHERE candidate_id = ?";
    if ($stmt = $conn->prepare($del_sql)) {
        $stmt->bind_param("i", $candidate_id);
        if ($stmt->execute()) {
            $_SESSION['upload_message'] = "Candidate record and associated files deleted successfully.";
            $_SESSION['upload_error'] = false;
            // Redirect back to upload page
            header("Location: uploadResumeYing.php?email=" . urlencode($email));
            exit();
        } else {
            die("Error deleting record: " . $stmt->error);
        }
        $stmt->close();
    }
}

// --- 2. HANDLE UPDATE ACTION & TRIGGER ML/AI ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_update'])) {
    // Collect data from form
    $u_name = $_POST['name'];
    $u_gender = $_POST['gender'];
    $u_email = $_POST['email'];
    $u_contact = $_POST['contact_number'];
    $u_address = $_POST['address'];
    $u_objective = $_POST['objective'];
    $u_education = $_POST['education'];
    $u_skills = $_POST['skills'];
    $u_experience = $_POST['experience'];
    $u_achievements = $_POST['achievements'];
    $u_language = $_POST['language'];

    $update_sql = "UPDATE candidate SET 
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
        language = ? 
        WHERE candidate_id = ?";

    if ($stmt = $conn->prepare($update_sql)) {
        $stmt->bind_param("sssssssssssi", 
            $u_name, $u_gender, $u_email, $u_contact, $u_address, 
            $u_objective, $u_education, $u_skills, $u_experience, 
            $u_achievements, $u_language, $candidate_id
        );
        
        if ($stmt->execute()) {
            
            // --- NEW: TRIGGER MACHINE LEARNING & LLM SCRIPT ---
            // Escape the argument to prevent command injection
            $escaped_id = escapeshellarg($candidate_id);
            
            // Execute python script. 2>&1 redirects errors to output
            // NOTE: Use your full python path if 'python' command fails in XAMPP
            // e.g. $command = "C:\\Python311\\python.exe generate_report.py $escaped_id 2>&1";
            $command = "python generate_report.py $escaped_id 2>&1";
            $output = shell_exec($command);

            // Optional: Log output for debugging
            // error_log("ML Output: " . $output);

            $_SESSION['upload_message'] = "Data Updated. ML Scoring & AI Analysis Completed!";
            $_SESSION['upload_error'] = false;
            
            // Redirect to uploadResumeYing.php
            header("Location: uploadResumeYing.php?email=" . urlencode($email));
            exit();
            
        } else {
            $_SESSION['upload_message'] = "Error updating data: " . $stmt->error;
            $_SESSION['upload_error'] = true;
        }
        $stmt->close();
    }
}

// --- 3. FETCH CANDIDATE DATA ---
$sql = "SELECT * FROM candidate WHERE candidate_id = ?";
$candidate = null;

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $candidate_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $candidate = $result->fetch_assoc();
        } else {
            die("Candidate not found.");
        }
    } else {
        die("Error executing query: " . $stmt->error);
    }
    $stmt->close();
} else {
    die("Database error: " . $conn->error);
}
$conn->close();

// Helper function to safely display data
function e($value) {
    return htmlspecialchars($value ?? '');
}

// Determine file extension for the original resume
$original_file_path = $candidate['resume_original'];
$original_file_ext = strtolower(pathinfo($original_file_path, PATHINFO_EXTENSION));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Preview & Edit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F5F7FA;
        }
        .preview-form-label {
            font-size: 0.875rem; 
            font-weight: 500;
            color: #E0E0E0;
            margin-bottom: 4px;
            display: block;
        }
        .preview-form-input {
            background-color: white;
            color: #111827;
            border: 1px solid #4B5563;
            border-radius: 0.5rem; 
            padding: 0.75rem 1rem;
            width: 100%;
            font-weight: 500;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        .preview-form-input:focus {
            outline: none;
            border-color: #3B82F6;
            ring: 2px solid #3B82F6;
        }
        textarea.preview-form-input {
            min-height: 100px;
            resize: vertical;
        }
    </style>
</head>
<body class="text-gray-900">

    <header class="bg-white shadow-sm sticky top-0 z-50">
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center">
                <a href="<?php echo $redirect_url; ?>" class="flex items-center text-gray-600 hover:text-gray-900 transition-colors">
                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                    <span class="font-semibold">Back</span>
                </a>
                <h1 class="text-2xl font-bold text-center flex-grow text-[#37474F]">
                    Resume Reader & Editor
                </h1>
            </div>
        </nav>
    </header>

    <main class="max-w-screen-2xl mx-auto p-4 sm:p-6 lg:p-8">

        <?php if (isset($_SESSION['upload_message'])): ?>
            <div class="mb-4 p-4 rounded-lg <?php echo (isset($_SESSION['upload_error']) && $_SESSION['upload_error']) ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
                <?php 
                echo htmlspecialchars($_SESSION['upload_message']); 
                unset($_SESSION['upload_message']);
                unset($_SESSION['upload_error']);
                ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8">

            <div class="bg-white rounded-xl shadow-lg overflow-hidden h-fit">
                <div class="flex justify-between items-center p-4 sm:p-6 bg-gray-50 border-b border-gray-200">
                    <h2 class="text-2xl font-bold text-gray-800">Original Resume</h2>
                    <a href="<?php echo e($candidate['resume_original']); ?>" download class="bg-[#37474F] text-white px-5 py-2 rounded-lg font-semibold hover:bg-opacity-90 transition-all shadow-sm">
                        Download
                    </a>
                </div>
                
                <div class="p-4 sm:p-6 h-[1000px]"> 
                    <?php if ($original_file_ext == 'pdf'): ?>
                        <iframe src="<?php echo e($candidate['resume_original']); ?>" class="w-full h-full border rounded-lg">
                            Your browser does not support PDFs. <a href="<?php echo e($candidate['resume_original']); ?>">Download PDF</a>
                        </iframe>
                    <?php elseif ($original_file_ext == 'docx'): ?>
                        <div class="flex flex-col items-center justify-center text-center h-full bg-gray-50 rounded-lg p-8">
                            <svg class="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <h3 class="text-xl font-semibold text-gray-700">DOCX Preview Not Available</h3>
                            <a href="<?php echo e($candidate['resume_original']); ?>" download class="mt-6 bg-blue-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-blue-700 transition-all shadow-sm">
                                Download .docx File
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-red-500">Unsupported file type for preview.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-[#37474F] text-white rounded-xl shadow-lg p-4 sm:p-6 lg:p-8">
                <div class="flex justify-between items-center pb-4 border-b border-gray-500">
                    <h2 class="text-2xl font-bold">Extracted Data</h2>
                    <a href="<?php echo e($candidate['resume_formatted']); ?>" download class="bg-white text-[#37474F] px-5 py-2 rounded-lg font-semibold hover:bg-gray-200 transition-all shadow-sm">
                        Download (.txt)
                    </a>
                </div>
                
                <form method="POST" action="" class="mt-6">
                    <div class="space-y-5">
                        <div>
                            <label class="preview-form-label" for="name">Name</label>
                            <input type="text" name="name" id="name" class="preview-form-input" value="<?php echo e($candidate['name']); ?>">
                        </div>
                        
                        <div>
                            <label class="preview-form-label" for="gender">Gender</label>
                            <input type="text" name="gender" id="gender" class="preview-form-input" value="<?php echo e($candidate['gender']); ?>">
                        </div>

                        <div>
                            <label class="preview-form-label" for="email">Email</label>
                            <input type="email" name="email" id="email" class="preview-form-input" value="<?php echo e($candidate['email']); ?>">
                        </div>

                        <div>
                            <label class="preview-form-label" for="contact_number">Contact Number</label>
                            <input type="text" name="contact_number" id="contact_number" class="preview-form-input" value="<?php echo e($candidate['contact_number']); ?>">
                        </div>

                        <div>
                            <label class="preview-form-label" for="address">Address</label>
                            <textarea name="address" id="address" class="preview-form-input"><?php echo e($candidate['address']); ?></textarea>
                        </div>

                        <div>
                            <label class="preview-form-label" for="objective">Objective</label>
                            <textarea name="objective" id="objective" class="preview-form-input"><?php echo e($candidate['objective']); ?></textarea>
                        </div>

                        <div>
                            <label class="preview-form-label" for="education">Education</label>
                            <textarea name="education" id="education" rows="5" class="preview-form-input"><?php echo e($candidate['education']); ?></textarea>
                        </div>
                        
                        <div>
                            <label class="preview-form-label" for="skills">Skills</label>
                            <textarea name="skills" id="skills" rows="4" class="preview-form-input"><?php echo e($candidate['skills']); ?></textarea>
                        </div>
                        
                        <div>
                            <label class="preview-form-label" for="experience">Experience</label>
                            <textarea name="experience" id="experience" rows="6" class="preview-form-input"><?php echo e($candidate['experience']); ?></textarea>
                        </div>
                        
                        <div>
                            <label class="preview-form-label" for="achievements">Achievements</label>
                            <textarea name="achievements" id="achievements" rows="4" class="preview-form-input"><?php echo e($candidate['achievements']); ?></textarea>
                        </div>
                        
                        <div>
                            <label class="preview-form-label" for="language">Language</label>
                            <input type="text" name="language" id="language" class="preview-form-input" value="<?php echo e($candidate['language']); ?>">
                        </div>
                    </div>

                    <div class="mt-8 pt-6 border-t border-gray-500 flex flex-col sm:flex-row gap-4">
                        <button type="submit" name="confirm_update" class="flex-1 bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-6 rounded-lg transition-colors shadow-md">
                            Confirm Changes
                        </button>
                        
                        <button type="button" onclick="confirmDelete()" class="flex-1 bg-red-500 hover:bg-red-600 text-white font-bold py-3 px-6 rounded-lg transition-colors shadow-md">
                            Cancel & Delete
                        </button>
                    </div>
                </form>
                </div>

        </div>
    </main>

    <script>
        function confirmDelete() {
            if (confirm("Are you sure? This will delete this candidate's record permanently.")) {
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('action', 'delete');
                window.location.href = currentUrl.toString();
            }
        }
    </script>

</body>
</html>