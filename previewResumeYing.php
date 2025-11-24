<?php
session_start();
include 'connection.php';

// Check if ID is provided
if (!isset($_GET['candidate_id']) && isset($_GET['id'])) {
    $_GET['candidate_id'] = $_GET['id'];
}

if (!isset($_GET['candidate_id']) || empty($_GET['candidate_id'])) {
    die("No candidate ID provided.");
}

if (!isset($_GET['email'])) {
    die("No email provided.");
}

$candidate_id = intval($_GET['candidate_id']);
$email = $_GET['email'];
$redirect_url = "uploadResumeYing.php?email=" . urlencode($email);

// --- 1. HANDLE DELETE ACTION (FROM CANCEL BUTTON) ---
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $file_query = "SELECT resume_original, resume_formatted FROM candidate WHERE candidate_id = ?";
    if ($stmt_file = $conn->prepare($file_query)) {
        $stmt_file->bind_param("i", $candidate_id);
        if ($stmt_file->execute()) {
            $result_file = $stmt_file->get_result();
            if ($file_row = $result_file->fetch_assoc()) {
                $file_original = $file_row['resume_original'];
                $file_formatted = $file_row['resume_formatted'];

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
    <title>Resume Reader | Resume Preview & Edit</title>
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
        }
    </style>
</head>
<body class="text-gray-900">

    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-card" id="loadingCard">
            <div class="text-2xl font-bold mb-2" id="loadingTitle">AI Analysis in Progress</div>
            <div class="text-gray-300 text-sm mb-4" id="loadingSubtitle">Analyzing resume content & predicting scores...</div>
            
            <div class="progress-container">
                <div class="progress-bar" id="progressBar"></div>
            </div>
            <div id="progressText" class="percentage">0%</div>
        </div>
    </div>

    <header class="bg-white shadow-sm sticky top-0 z-50">
        <nav class="max-w-7xl mx-auto px-4 py-4">
            <a href="<?php echo $redirect_url; ?>" class="text-gray-600 font-bold">Back</a>
        </nav>
    </header>

    <main class="max-w-screen-2xl mx-auto p-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <div class="bg-white rounded-xl shadow h-[1000px] p-4">
                <h2 class="font-bold mb-4 text-gray-800">Original Resume</h2>
                <?php if ($original_file_ext == 'pdf'): ?>
                    <iframe src="<?php echo e($candidate['resume_original']); ?>" class="w-full h-full border rounded"></iframe>
                <?php else: ?>
                    <div class="text-center pt-20 text-gray-500">DOCX Preview Unavailable. <a href="<?php echo e($candidate['resume_original']); ?>" class="text-blue-500 underline">Download</a></div>
                <?php endif; ?>
            </div>

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
        </div>
    </main>

    <script>
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
</body>
</html>