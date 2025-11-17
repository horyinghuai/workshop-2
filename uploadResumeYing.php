<!-- Suppress PHP error messages -->
<?php error_reporting(0); ?>
<?php
// Start session to handle success/error messages
session_start();

// Include database connection
include 'connection.php';

// Fetch job positions from the database
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
    <title>Upload Resume</title>
    <!-- Google Fonts - Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Link to the CSS file -->
    <link rel="stylesheet" href="uploadResumeYing.css">
</head>
<body>

    <header class="header">
        <!-- Pass the current email to dashboard.php in the back link -->
        <a href="dashboard.php?email=<?php echo urlencode($_GET['email']); ?>" class="back-link">
            <i class="fas fa-chevron-left"></i> Back
        </a>
        <h1 class="header-title">Resume Reader</h1>
    </header>

    <main class="main-container">
        <div class="upload-container">
            
            <!-- Pass the current email to uploadProcessYing.php -->
            <form action="uploadProcessYing.php?email=<?php echo urlencode($_GET['email']); ?>" method="post" enctype="multipart/form-data" id="uploadForm">
                <h2>Upload Resume</h2>

                <!-- Display Success/Error Alerts -->
                <?php if (isset($_SESSION['upload_message'])): ?>
                    <div class="alert <?php echo (isset($_SESSION['upload_error']) && $_SESSION['upload_error']) ? 'alert-error' : 'alert-success'; ?>">
                        <?php 
                            echo htmlspecialchars($_SESSION['upload_message']); 
                            unset($_SESSION['upload_message']);
                            unset($_SESSION['upload_error']);
                        ?>
                    </div>
                <?php endif; ?>

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
                    
                    <!-- This container holds the button -->
                    <div id="file-input-container">
                        <button type="button" class="file-choose-btn" id="chooseFileBtn">
                            <i class="fas fa-upload"></i> Choose Resume File
                        </button>
                    </div>
                    
                    <!-- This is the drag & drop zone (initially hidden) -->
                    <div class="drop-zone hidden" id="dropZone">
                        <p>Drag & drop the resume here<br>PDF, DOCX up to 5MB</p>
                    </div>

                    <!-- The actual file input is always hidden -->
                    <input type="file" name="resume_file" id="resumeFile" class="hidden" required accept=".pdf,.docx">
                </div>

                <div class="button-container">
                    <button type="submit" name="submit" class="confirm-btn">
                        Confirm
                    </button>
                    
                    <!-- This button just goes back in browser history -->
                    <button type="button" class="cancel-btn" onclick="window.history.back();">Cancel</button>
                </div>

            </form>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const chooseFileBtn = document.getElementById('chooseFileBtn');
            const fileInputContainer = document.getElementById('file-input-container');
            const dropZone = document.getElementById('dropZone');
            const resumeFile = document.getElementById('resumeFile');
            const uploadForm = document.getElementById('uploadForm');

            // 1. When "Choose Resume File" is clicked, show the drop zone
            chooseFileBtn.addEventListener('click', () => {
                fileInputContainer.classList.add('hidden');
                dropZone.classList.remove('hidden');
            });

            // 2. When the drop zone is clicked, trigger the hidden file input
            dropZone.addEventListener('click', () => {
                resumeFile.click();
            });

            // 3. Handle file selection from the dialog
            resumeFile.addEventListener('change', () => {
                if (resumeFile.files.length > 0) {
                    handleFile(resumeFile.files[0]);
                }
            });

            // 4. Handle drag-and-drop events
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
                    // Set the file input's files property to the dropped file
                    resumeFile.files = e.dataTransfer.files;
                    handleFile(e.dataTransfer.files[0]);
                }
            });

            // 5. Shared function to handle the file once selected or dropped
            function handleFile(file) {
                if (file) {
                    // Update the "Choose File" button text to show the file name
                    chooseFileBtn.innerHTML = `<i class="fas fa-check"></i> ${file.name}`;
                    // Show the button container again
                    fileInputContainer.classList.remove('hidden');
                    // Hide the drop zone
                    dropZone.classList.add('hidden');
                }
            }

            // 6. Reset form on cancel (simple way)
            const cancelBtn = document.querySelector('.cancel-btn');
            cancelBtn.addEventListener('click', () => {
                uploadForm.reset();
                chooseFileBtn.innerHTML = `<i class="fas fa-upload"></i> Choose Resume File`;
                fileInputContainer.classList.remove('hidden');
                dropZone.classList.add('hidden');
            });
        });
    </script>

</body>
</html>