<?php error_reporting(0); ?>
<?php
session_start();
include 'connection.php';
$jobs = [];
$sql = "SELECT job_id, job_name FROM job_position ORDER BY job_name ASC";
if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
    $result->free();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Reader | Upload Resume</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="uploadResume.css">
    <style>
        /* Loading Overlay Style */
        .loading-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85);
            z-index: 9999;
            display: none; /* Hidden by default */
            justify-content: center;
            align-items: center;
            flex-direction: column;
            color: white;
            font-family: 'Poppins', sans-serif;
        }
        .loading-card {
            background: #2C3E50;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            width: 400px;
        }
        .progress-container {
            width: 100%;
            background: #444;
            border-radius: 10px;
            margin-top: 20px;
            height: 14px;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            width: 0%;
            background: #00c04b;
            transition: width 0.3s ease;
        }
        .percentage { font-size: 1.5rem; font-weight: 700; color: #00c04b; margin-top: 10px; }
    </style>
</head>
<body>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-card">
            <i class="fas fa-file-import fa-3x" style="color: #00c04b; margin-bottom: 15px;"></i>
            <h3>Extracting Data...</h3>
            <p>Analyzing resume content</p>
            <div class="progress-container">
                <div class="progress-bar" id="progressBar"></div>
            </div>
            <div class="percentage" id="progressText">0%</div>
        </div>
    </div>

    <header class="header">
        <a href="dashboard.php?email=<?php echo urlencode($_GET['email']); ?>" class="back-link">
            <i class="fas fa-chevron-left"></i> Back
        </a>
        <h1 class="header-title">Resume Reader</h1>
        <a href="logout.php" class="logout-link">Log Out</a>
    </header>

    <main class="main-container">
        <div class="upload-container">
            
            <form id="uploadForm" enctype="multipart/form-data">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['email']); ?>">
                <h2>Upload Resume</h2>

                <div class="form-group">
                    <label for="job_position">Applied Job Position</label>
                    <select name="job_position" id="job_position" required>
                        <option value="" disabled selected>Select a job position</option>
                        <?php foreach ($jobs as $job): ?>
                            <option value="<?php echo htmlspecialchars($job['job_id']); ?>">
                                <?php echo htmlspecialchars($job['job_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Upload File</label>
                    <div id="file-input-container" class="file-btn-row">
                        <button type="button" class="file-choose-btn" id="chooseFileBtn">
                            <i class="fas fa-upload"></i> Choose Resume File
                        </button>

                        <button type="button" id="removeFileBtn" class="remove-file-btn hidden">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="drop-zone hidden" id="dropZone">
                        <p>Drag & drop the resume here<br>PDF up to 5MB</p>
                    </div>
                    <input type="file" name="resume_file" id="resumeFile" class="hidden" required accept=".pdf,.docx">
                    <!-- Add this right below the drop zone -->
                    <button type="button" id="removeFileBtn" class="remove-file-btn hidden">
                        <i class="fas fa-times"></i> Remove File
                    </button>
                </div>

                <div class="button-container">
                    <button type="submit" class="confirm-btn">Confirm</button>
                    <button type="button" class="cancel-btn" onclick="window.history.back();">Cancel</button>
                </div>
            </form>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('uploadForm');
        const chooseFileBtn = document.getElementById('chooseFileBtn');
        const fileInputContainer = document.getElementById('file-input-container');
        const dropZone = document.getElementById('dropZone');
        const resumeFile = document.getElementById('resumeFile');
        const removeFileBtn = document.getElementById('removeFileBtn');

        const overlay = document.getElementById('loadingOverlay');
        const bar = document.getElementById('progressBar');
        const txt = document.getElementById('progressText');

        /* -------------------------
           REAL-TIME UPLOAD LOGIC
        -------------------------- */
        form.addEventListener('submit', (e) => {
            e.preventDefault();

            if (resumeFile.files.length === 0 || document.getElementById('job_position').value === "") {
                alert("Please select a job and a resume file.");
                return;
            }

            const file = resumeFile.files[0];
            if (file.type !== "application/pdf") {
                alert("Only PDF format is supported. Please upload a PDF file.");
                return;
            }

            overlay.style.display = 'flex';
            bar.style.width = '0%';
            txt.innerText = '0%';

            const processId = Date.now() + Math.floor(Math.random() * 1000);
            const formData = new FormData(form);
            formData.append('process_id', processId);

            const pollInterval = setInterval(() => {
                fetch('check_progress.php?id=' + processId)
                    .then(res => res.text())
                    .then(val => {
                        let p = parseInt(val);
                        if (p > 0 && p <= 100) {
                            bar.style.width = p + '%';
                            txt.innerText = p + '%';
                        }
                    });
            }, 800);

            fetch('uploadProcess.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                clearInterval(pollInterval);
                if (data.status === 'success') {
                    bar.style.width = '100%';
                    txt.innerText = '100%';
                    setTimeout(() => {
                        window.location.href = `previewResume.php?candidate_id=${data.candidate_id}&email=${encodeURIComponent(data.email)}`;
                    }, 500);
                } else {
                    alert("Error: " + data.message);
                    overlay.style.display = 'none';
                }
            })
            .catch(err => {
                clearInterval(pollInterval);
                alert("System Error: " + err);
                overlay.style.display = 'none';
            });
        });

        /* -------------------------
           FILE UI LOGIC
        -------------------------- */
        chooseFileBtn.addEventListener('click', () => {
            fileInputContainer.classList.add('hidden');
            dropZone.classList.remove('hidden');
        });

        dropZone.addEventListener('click', () => resumeFile.click());

        resumeFile.addEventListener('change', () => {
            if (resumeFile.files.length > 0) {
                handleFile(resumeFile.files[0]);
            }
        });

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('active');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('active');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('active');
            if (e.dataTransfer.files.length > 0) {
                resumeFile.files = e.dataTransfer.files;
                handleFile(e.dataTransfer.files[0]);
            }
        });

        function handleFile(file) {
            chooseFileBtn.innerHTML = `<i class="fas fa-check"></i> ${file.name}`;
            fileInputContainer.classList.remove('hidden');
            dropZone.classList.add('hidden');
            removeFileBtn.classList.remove('hidden');
        }

        /* -------------------------
           REMOVE FILE BUTTON
        -------------------------- */
        removeFileBtn.addEventListener('click', () => {
            resumeFile.value = "";
            chooseFileBtn.innerHTML = `<i class="fas fa-upload"></i> Choose Resume File`;

            fileInputContainer.classList.remove('hidden');
            dropZone.classList.add('hidden');

            removeFileBtn.classList.add('hidden');
        });

    });
</script>

</body>
</html>