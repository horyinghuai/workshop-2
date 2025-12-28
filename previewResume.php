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
$redirect_url = "uploadResume.php?email=" . urlencode($email);

// --- 1. HANDLE DELETE ACTION (FROM CANCEL BUTTON) ---
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    // Fetch file paths before deleting the record
    $file_query = "SELECT resume_original, resume_formatted FROM candidate WHERE candidate_id = ?";
    if ($stmt_file = $conn->prepare($file_query)) {
        $stmt_file->bind_param("i", $candidate_id);
        if ($stmt_file->execute()) {
            $result_file = $stmt_file->get_result();
            if ($file_row = $result_file->fetch_assoc()) {
                $file_original = $file_row['resume_original'];
                $file_formatted = $file_row['resume_formatted'];

                // Delete Original File (PDF/DOCX)
                if (!empty($file_original) && file_exists($file_original)) {
                    unlink($file_original);
                }
                
                // Delete Formatted Text File (in uploads folder)
                if (!empty($file_formatted) && file_exists($file_formatted)) {
                    unlink($file_formatted);
                }
            }
        }
        $stmt_file->close();
    }

    // Delete Database Record
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
        /* --- DESIGN MATCHING report.php --- */
        body { font-family: 'Inter', sans-serif; background-color: #f0f4f4; }
        .header-bar { background-color: #457b7d; color: white; }
        .resume-col { background-color: #dbecea; color: #333; }
        .report-col { background-color: #2F3E46; color: white; }
        .report-col h2 { color: white; font-weight: 700; font-size: 1.5rem; margin-bottom: 1rem; }
        .field-row { display: grid; grid-template-columns: 140px 1fr; gap: 10px; align-items: start; margin-bottom: 12px; }
        .field-row label { padding-top: 10px; font-size: 0.95rem; font-weight: 600; color: white; }
        .preview-form-input { background-color: white; color: #333; border: 1px solid #ccc; border-radius: 6px; padding: 10px 12px; width: 100%; font-weight: normal; font-size: 0.95rem; outline: none; transition: border-color 0.2s; margin-bottom: 10px; }
        .preview-form-input:focus { border-color: #457b7d; box-shadow: 0 0 0 2px rgba(69, 123, 125, 0.2); }
        textarea.preview-form-input { min-height: 80px; resize: vertical; }
        .loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 9999; display: none; justify-content: center; align-items: center; flex-direction: column; color: white; }
        .loading-card { background: #1F2937; padding: 40px; border-radius: 20px; text-align: center; width: 450px; border: 2px solid #3B82F6; box-shadow: 0 20px 50px rgba(59, 130, 246, 0.3); transition: all 0.3s ease; }
        .progress-container { width: 100%; background: #374151; border-radius: 8px; margin-top: 25px; height: 16px; overflow: hidden; }
        .progress-bar { height: 100%; width: 0%; background: linear-gradient(90deg, #3B82F6, #9333EA); transition: width 0.3s ease; }
        .percentage { font-family: monospace; font-size: 1.2rem; margin-top: 10px; color: #60A5FA; }
        .loading-card.success { border-color: #10B981; background: #064E3B; }
        .loading-card.success .progress-bar { background: #10B981; }
        .loading-card.success .percentage { color: #34D399; }
        .header .logout-link { position: absolute; right: 2rem; font-size: 1.5rem; text-decoration: none; color: white; }
        .logout-link:hover { text-decoration: underline; }
    </style>
</head>
<body class="h-screen flex flex-col overflow-hidden">

    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-card" id="loadingCard">
            <div class="text-2xl font-bold mb-2" id="loadingTitle">AI Analysis in Progress</div>
            <div class="text-gray-300 text-sm mb-4" id="loadingSubtitle">Analyzing resume content & predicting scores...</div>
            <div class="progress-container"><div class="progress-bar" id="progressBar"></div></div>
            <div id="progressText" class="percentage">0%</div>
        </div>
    </div>

    <header class="header-bar shadow-md flex-none z-50">
        <nav class="max-w-screen-2xl mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center">
                <a href="<?php echo $redirect_url; ?>" class="text-white hover:text-gray-200 text-lg font-medium flex items-center gap-2 font-size-1.5rm"><i class="fas fa-chevron-left"></i> Back</a>
            </div>
            <h1 class="text-3xl font-bold tracking-wide">Resume Reader</h1>
            <a href="logout.php" class="logout-link">Log Out</a>
        </nav>
    </header>

    <main class="flex-1 w-full overflow-hidden">
        <div class="flex flex-col lg:flex-row h-full">
            <div class="resume-col w-full lg:w-1/2 p-6 h-full flex flex-col border-r border-gray-300 overflow-hidden">
                <h2 class="flex-none text-2xl font-bold text-gray-700 mb-4">Original Resume</h2>
                <div class="flex-1 bg-white border rounded overflow-hidden relative">
                    <?php if ($original_file_ext == 'pdf'): ?>
                        <iframe src="<?php echo e($candidate['resume_original']); ?>" class="w-full h-full border-none"></iframe>
                    <?php else: ?>
                        <div class="absolute inset-0 flex flex-col justify-center items-center text-gray-500">
                            <i class="fas fa-file-word fa-4x mb-4 text-blue-600"></i>
                            <p class="text-lg font-medium">DOCX Preview Unavailable</p>
                            <a href="<?php echo e($candidate['resume_original']); ?>" class="mt-3 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">Download File</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="formatted-container" class="report-col w-full lg:w-1/2 p-6 h-full overflow-y-auto">
                <div class="flex justify-between items-center mb-6">
                    <h2>Extracted Data (Translated)</h2>
                </div>
                
                <form id="aiForm" class="space-y-1">
                    <input type="hidden" name="candidate_id" value="<?php echo $candidate_id; ?>">
                    
                    <div class="field-row"><label>Name</label><input type="text" name="name" class="preview-form-input" value="<?php echo e($candidate['name']); ?>"></div>
                    <div class="field-row"><label>Gender</label><input type="text" name="gender" class="preview-form-input" value="<?php echo e($candidate['gender']); ?>"></div>
                    <div class="field-row"><label>Email</label><input type="email" name="email" class="preview-form-input" value="<?php echo e($candidate['email']); ?>"></div>
                    <div class="field-row"><label>Contact</label><input type="text" name="contact_number" class="preview-form-input" value="<?php echo e($candidate['contact_number']); ?>"></div>
                    <div class="field-row"><label>Address</label><textarea name="address" class="preview-form-input"><?php echo e($candidate['address']); ?></textarea></div>
                    <div class="field-row"><label>Objective</label><textarea name="objective" class="preview-form-input"><?php echo e($candidate['objective']); ?></textarea></div>
                    <div class="field-row"><label>Education</label><textarea name="education" class="preview-form-input" style="height:120px"><?php echo e($candidate['education']); ?></textarea></div>
                    <div class="field-row"><label>Skills</label><textarea name="skills" class="preview-form-input" style="height:100px"><?php echo e($candidate['skills']); ?></textarea></div>
                    <div class="field-row"><label>Experience</label><textarea name="experience" class="preview-form-input" style="height:150px"><?php echo e($candidate['experience']); ?></textarea></div>
                    <div class="field-row"><label>Language</label><input type="text" name="language" class="preview-form-input" value="<?php echo e($candidate['language']); ?>"></div>
                    <div class="field-row"><label>Others</label><textarea name="others" class="preview-form-input" style="height:100px"><?php echo e($candidate['others']); ?></textarea></div>

                    <div class="mt-8 pt-6 flex gap-4">
                        <button type="submit" class="flex-1 bg-[#28a745] hover:bg-green-600 text-white font-bold py-3 rounded shadow transition">Confirm & Run AI</button>
                        <button type="button" 
                                onclick="if(confirm('Are you sure you want to cancel? This candidate record and all associated files will be deleted.')) location.href='previewResume.php?action=delete&candidate_id=<?php echo $candidate_id; ?>&email=<?php echo urlencode($email); ?>'" 
                                class="flex-1 bg-[#dc3545] hover:bg-red-600 text-white font-bold py-3 rounded shadow transition">
                            Cancel
                        </button>
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
            overlay.style.display = 'flex';
            card.classList.remove('success');
            title.innerText = "AI Analysis in Progress";
            sub.innerText = "Analyzing resume content & predicting scores...";
            bar.style.width = '0%'; txt.innerText = '0%';
            
            const processId = Date.now() + Math.floor(Math.random() * 1000);
            const formData = new FormData(form);
            formData.append('process_id', processId); 

            const pollInterval = setInterval(() => {
                fetch('check_progress.php?id=' + processId)
                    .then(res => res.text())
                    .then(val => {
                        let p = parseInt(val);
                        if(p > 0 && p <= 100) { bar.style.width = p + '%'; txt.innerText = p + '%'; }
                    });
            }, 800);

            fetch('process_report_action.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                clearInterval(pollInterval);
                if (data.status === 'success') {
                    bar.style.width = '100%'; txt.innerText = '100%';
                    card.classList.add('success');
                    title.innerHTML = '<i class="fas fa-check-circle mb-2"></i> Report Ready!';
                    sub.innerText = "Resume successfully uploaded and report is ready!";
                    setTimeout(() => { window.location.href = "<?php echo $redirect_url; ?>"; }, 2500); 
                } else {
                    alert('Error: ' + data.message); overlay.style.display = 'none';
                }
            })
            .catch(error => { clearInterval(pollInterval); alert('System Error: ' + error); overlay.style.display = 'none'; });
        });
    </script>
</body>
</html>