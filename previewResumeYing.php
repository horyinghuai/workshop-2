<?php
session_start();
include 'connection.php';

// Check if ID is provided
<<<<<<< HEAD
=======
if (!isset($_GET['candidate_id']) && isset($_GET['id'])) {
    $_GET['candidate_id'] = $_GET['id'];
}

>>>>>>> 43cf7b0f09f54ba665eb0705918e6ca6f0ee6d4f
if (!isset($_GET['candidate_id']) || empty($_GET['candidate_id'])) {
    die("No candidate ID provided.");
}

if (!isset($_GET['email'])) {
    die("No email provided.");
}

$candidate_id = intval($_GET['candidate_id']);
<<<<<<< HEAD
$redirect_url = "uploadResumeYing.php?email=" . urlencode($email) . "&candidate_id=" . urlencode($candidate_id);

// --- 1. HANDLE DELETE ACTION (FROM CANCEL BUTTON) ---
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    
    // A. First, fetch the file paths to delete them physically from the server
=======
$email = $_GET['email'];
$redirect_url = "uploadResumeYing.php?email=" . urlencode($email);

// --- 1. HANDLE DELETE ACTION (FROM CANCEL BUTTON) ---
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
>>>>>>> 43cf7b0f09f54ba665eb0705918e6ca6f0ee6d4f
    $file_query = "SELECT resume_original, resume_formatted FROM candidate WHERE candidate_id = ?";
    if ($stmt_file = $conn->prepare($file_query)) {
        $stmt_file->bind_param("i", $candidate_id);
        if ($stmt_file->execute()) {
            $result_file = $stmt_file->get_result();
            if ($file_row = $result_file->fetch_assoc()) {
                $file_original = $file_row['resume_original'];
                $file_formatted = $file_row['resume_formatted'];
<<<<<<< HEAD

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
=======
>>>>>>> 43cf7b0f09f54ba665eb0705918e6ca6f0ee6d4f

                if (!empty($file_original) && file_exists($file_original)) {
                    unlink($file_original);
                }
                if (!empty($file_formatted) && file_exists($file_formatted)) {
                    unlink($file_formatted);
                }
            }
        }
        $stmt_file->close();
    }

    $del_sql = "DELETE FROM candidate WHERE candidate_id = ?";
    if ($stmt = $conn->prepare($del_sql)) {
        $stmt->bind_param("i", $candidate_id);
        $stmt->execute();
        $_SESSION['upload_message'] = "Candidate record deleted successfully.";
        header("Location: $redirect_url");
        exit();
    }
}

// --- 2. FETCH CANDIDATE DATA ---
$sql = "SELECT * FROM candidate WHERE candidate_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $candidate_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Candidate not found.");
}

$candidate = $result->fetch_assoc();
$conn->close();

function e($value) {
    return htmlspecialchars($value ?? '');
}

$original_file_path = $candidate['resume_original'];
$original_file_ext = strtolower(pathinfo($original_file_path, PATHINFO_EXTENSION));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<<<<<<< HEAD
    <title>Resume Preview & Edit</title>
=======
    <title>Resume Reader | Resume Preview & Edit</title>
>>>>>>> 43cf7b0f09f54ba665eb0705918e6ca6f0ee6d4f
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #F5F7FA; }
        .preview-form-label { font-size: 0.875rem; font-weight: 500; color: #E0E0E0; margin-bottom: 4px; display: block; }
        .preview-form-input { background-color: white; color: #111827; border: 1px solid #4B5563; border-radius: 0.5rem; padding: 0.75rem 1rem; width: 100%; font-weight: 500; }
        textarea.preview-form-input { min-height: 100px; resize: vertical; }
        
        /* LOADING OVERLAY */
        .loading-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.9); z-index: 9999;
            display: none; justify-content: center; align-items: center;
            flex-direction: column; color: white;
        }
<<<<<<< HEAD
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
=======
        .loading-card {
            background: #1F2937; padding: 40px; border-radius: 20px;
            text-align: center; width: 450px; border: 2px solid #3B82F6;
            box-shadow: 0 20px 50px rgba(59, 130, 246, 0.3);
            transition: all 0.3s ease;
        }
        .progress-container {
            width: 100%; background: #374151; border-radius: 8px;
            margin-top: 25px; height: 16px; overflow: hidden;
        }
        .progress-bar {
            height: 100%; width: 0%; background: linear-gradient(90deg, #3B82F6, #9333EA);
            transition: width 0.3s ease;
        }
        .percentage { font-family: monospace; font-size: 1.2rem; margin-top: 10px; color: #60A5FA; }
        
        /* Success Animation State */
        .loading-card.success {
            border-color: #10B981; /* Green Border */
            background: #064E3B; /* Darker Green BG */
        }
        .loading-card.success .progress-bar {
            background: #10B981; /* Solid Green Bar */
        }
        .loading-card.success .percentage {
            color: #34D399;
>>>>>>> 43cf7b0f09f54ba665eb0705918e6ca6f0ee6d4f
        }
    </style>
</head>
<body class="text-gray-900">

<<<<<<< HEAD
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
=======
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-card" id="loadingCard">
            <div class="text-2xl font-bold mb-2" id="loadingTitle">AI Analysis in Progress</div>
            <div class="text-gray-300 text-sm mb-4" id="loadingSubtitle">Analyzing resume content & predicting scores...</div>
            
            <div class="progress-container">
                <div class="progress-bar" id="progressBar"></div>
>>>>>>> 43cf7b0f09f54ba665eb0705918e6ca6f0ee6d4f
            </div>
            <div id="progressText" class="percentage">0%</div>
        </div>
    </div>

    <header class="bg-white shadow-sm sticky top-0 z-50">
        <nav class="max-w-7xl mx-auto px-4 py-4">
            <a href="<?php echo $redirect_url; ?>" class="text-gray-600 font-bold">Back</a>
        </nav>
    </header>

<<<<<<< HEAD
    <main class="max-w-screen-2xl mx-auto p-4 sm:p-6 lg:p-8">

        <?php if (isset($_SESSION['upload_message'])): ?>
            <div class="mb-4 p-4 rounded-lg <?php echo (isset($_SESSION['upload_error']) && $_SESSION['upload_error']) ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
                <?php 
                echo htmlspecialchars($_SESSION['upload_message']); 
                unset($_SESSION['upload_message']);
                unset($_SESSION['upload_error']);
                ?>
=======
    <main class="max-w-screen-2xl mx-auto p-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <div class="bg-white rounded-xl shadow h-[1000px] p-4">
                <h2 class="font-bold mb-4 text-gray-800">Original Resume</h2>
                <?php if ($original_file_ext == 'pdf'): ?>
                    <iframe src="<?php echo e($candidate['resume_original']); ?>" class="w-full h-full border rounded"></iframe>
                <?php else: ?>
                    <div class="text-center pt-20 text-gray-500">DOCX Preview Unavailable. <a href="<?php echo e($candidate['resume_original']); ?>" class="text-blue-500 underline">Download</a></div>
                <?php endif; ?>
>>>>>>> 43cf7b0f09f54ba665eb0705918e6ca6f0ee6d4f
            </div>

<<<<<<< HEAD
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

=======
            <div class="bg-[#37474F] text-white rounded-xl shadow p-6">
                <h2 class="text-2xl font-bold mb-6">Extracted Data</h2>
                
                <form id="aiForm">
                    <input type="hidden" name="candidate_id" value="<?php echo $candidate_id; ?>">
                    
                    <div class="space-y-5">
                        <div><label class="preview-form-label">Name</label><input type="text" name="name" class="preview-form-input" value="<?php echo e($candidate['name']); ?>"></div>
                        <div><label class="preview-form-label">Gender</label><input type="text" name="gender" class="preview-form-input" value="<?php echo e($candidate['gender']); ?>"></div>
                        <div><label class="preview-form-label">Email</label><input type="email" name="email" class="preview-form-input" value="<?php echo e($candidate['email']); ?>"></div>
                        <div><label class="preview-form-label">Contact</label><input type="text" name="contact_number" class="preview-form-input" value="<?php echo e($candidate['contact_number']); ?>"></div>
                        <div><label class="preview-form-label">Address</label><textarea name="address" class="preview-form-input"><?php echo e($candidate['address']); ?></textarea></div>
                        <div><label class="preview-form-label">Objective</label><textarea name="objective" class="preview-form-input"><?php echo e($candidate['objective']); ?></textarea></div>
                        <div><label class="preview-form-label">Education</label><textarea name="education" rows="5" class="preview-form-input"><?php echo e($candidate['education']); ?></textarea></div>
                        <div><label class="preview-form-label">Skills</label><textarea name="skills" rows="4" class="preview-form-input"><?php echo e($candidate['skills']); ?></textarea></div>
                        <div><label class="preview-form-label">Experience</label><textarea name="experience" rows="6" class="preview-form-input"><?php echo e($candidate['experience']); ?></textarea></div>
                        <div><label class="preview-form-label">Achievements</label><textarea name="achievements" rows="4" class="preview-form-input"><?php echo e($candidate['achievements']); ?></textarea></div>
                        <div><label class="preview-form-label">Language</label><input type="text" name="language" class="preview-form-input" value="<?php echo e($candidate['language']); ?>"></div>
                    </div>

                    <div class="mt-8 pt-6 border-t border-gray-500 flex gap-4">
                        <button type="submit" class="flex-1 bg-green-500 hover:bg-green-600 text-white font-bold py-3 rounded">Confirm & Run AI</button>
                        <button type="button" onclick="location.href='<?php echo $redirect_url; ?>'" class="flex-1 bg-red-500 hover:bg-red-600 text-white font-bold py-3 rounded">Cancel</button>
                    </div>
                </form>
            </div>
>>>>>>> 43cf7b0f09f54ba665eb0705918e6ca6f0ee6d4f
        </div>
    </main>

    <script>
<<<<<<< HEAD
        function confirmDelete() {
            if (confirm("Are you sure? This will delete this candidate's record permanently.")) {
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('action', 'delete');
                window.location.href = currentUrl.toString();
            }
        }
    </script>

=======
        const form = document.getElementById('aiForm');
        const overlay = document.getElementById('loadingOverlay');
        const card = document.getElementById('loadingCard');
        const bar = document.getElementById('progressBar');
        const txt = document.getElementById('progressText');
        const title = document.getElementById('loadingTitle');
        const sub = document.getElementById('loadingSubtitle');

        form.addEventListener('submit', function(e) {
            e.preventDefault(); 
            
            // 1. Show Overlay (Reset State)
            overlay.style.display = 'flex';
            card.classList.remove('success');
            title.innerText = "AI Analysis in Progress";
            sub.innerText = "Analyzing resume content & predicting scores...";
            bar.style.width = '0%';
            txt.innerText = '0%';
            
            // 2. Generate Process ID
            const processId = Date.now() + Math.floor(Math.random() * 1000);
            
            // 3. Form Data
            const formData = new FormData(form);
            formData.append('process_id', processId); 

            // 4. Polling
            const pollInterval = setInterval(() => {
                fetch('check_progress.php?id=' + processId)
                    .then(res => res.text())
                    .then(val => {
                        let p = parseInt(val);
                        if(p > 0 && p <= 100) {
                            bar.style.width = p + '%';
                            txt.innerText = p + '%';
                        }
                    });
            }, 800);

            // 5. Send Action
            fetch('process_report_action.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                clearInterval(pollInterval);
                
                if (data.status === 'success') {
                    // Set 100%
                    bar.style.width = '100%';
                    txt.innerText = '100%';
                    
                    // --- SUCCESS NOTIFICATION STATE ---
                    card.classList.add('success');
                    title.innerHTML = '<i class="fas fa-check-circle mb-2"></i> Report Ready!';
                    sub.innerText = "Resume successfully uploaded and report is ready!";
                    
                    // Delay redirect so user can see the success message
                    setTimeout(() => {
                        window.location.href = "<?php echo $redirect_url; ?>";
                    }, 2500); // 2.5 seconds delay
                    
                } else {
                    alert('Error: ' + data.message);
                    overlay.style.display = 'none';
                }
            })
            .catch(error => {
                clearInterval(pollInterval);
                alert('System Error: ' + error);
                overlay.style.display = 'none';
            });
        });
    </script>
>>>>>>> 43cf7b0f09f54ba665eb0705918e6ca6f0ee6d4f
</body>
</html>