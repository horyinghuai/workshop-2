<?php
session_start();
include 'connection.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("No candidate ID provided.");
}

$candidate_id = intval($_GET['id']);

// Fetch candidate data
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
    return htmlspecialchars($value ?? 'N/A');
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
    <title>Resume Preview</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F5F7FA;
        }
        .preview-form-label {
            font-size: 0.875rem; /* 14px */
            font-weight: 500;
            color: #E0E0E0;
            margin-bottom: 4px;
        }
        .preview-form-value {
            background-color: white;
            color: #111827;
            border-radius: 0.5rem; /* 8px */
            padding: 0.75rem 1rem; /* 12px 16px */
            width: 100%;
            font-weight: 500;
            min-height: 46px;
            white-space: pre-wrap; /* This will respect newlines in TEXT fields */
            word-wrap: break-word;
        }
    </style>
</head>
<body class="text-gray-900">

    <!-- Header -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center">
                <!-- Back Button (Points to upload page) -->
                <a href="uploadResumeYing.php" class="flex items-center text-gray-600 hover:text-gray-900 transition-colors">
                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                    <span class="font-semibold">Back</span>
                </a>
                <h1 class="text-2xl font-bold text-center flex-grow text-[#37474F]">
                    Resume Reader
                </h1>
            </div>
        </nav>
    </header>

    <!-- Main Content Area -->
    <main class="max-w-screen-2xl mx-auto p-4 sm:p-6 lg:p-8">

        <!-- Success Message -->
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

            <!-- Left Column: Original Resume -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="flex justify-between items-center p-4 sm:p-6 bg-gray-50 border-b border-gray-200">
                    <h2 class="text-2xl font-bold text-gray-800">Original Resume</h2>
                    <a href="<?php echo e($candidate['resume_original']); ?>" download class="bg-[#37474F] text-white px-5 py-2 rounded-lg font-semibold hover:bg-opacity-90 transition-all shadow-sm">
                        Download
                    </a>
                </div>
                
                <!-- Resume Content (Dynamic) -->
                <div class="p-4 sm:p-6 h-full min-h-[1000px]">
                    <?php if ($original_file_ext == 'pdf'): ?>
                        <!-- Embed PDF directly -->
                        <iframe src="<?php echo e($candidate['resume_original']); ?>" class="w-full h-full min-h-[1000px] border rounded-lg">
                            Your browser does not support PDFs. <a href="<?php echo e($candidate['resume_original']); ?>">Download PDF</a>
                        </iframe>
                    <?php elseif ($original_file_ext == 'docx'): ?>
                        <!-- DOCX cannot be embedded reliably. Show a message. -->
                        <div class="flex flex-col items-center justify-center text-center h-full min-h-[500px] bg-gray-50 rounded-lg p-8">
                            <svg class="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <h3 class="text-xl font-semibold text-gray-700">DOCX Preview Not Available</h3>
                            <p class="text-gray-500 mt-2">Microsoft Word files (.docx) cannot be previewed directly in the browser.</p>
                            <a href="<?php echo e($candidate['resume_original']); ?>" download class="mt-6 bg-blue-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-blue-700 transition-all shadow-sm">
                                Download .docx File
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-red-500">Unsupported file type for preview.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Formatted Resume Data -->
            <div class="bg-[#37474F] text-white rounded-xl shadow-lg p-4 sm:p-6 lg:p-8">
                <div class="flex justify-between items-center pb-4 border-b border-gray-500">
                    <h2 class="text-2xl font-bold">Extracted Data</h2>
                    <a href="<?php echo e($candidate['resume_formatted']); ?>" download class="bg-white text-[#37474F] px-5 py-2 rounded-lg font-semibold hover:bg-gray-200 transition-all shadow-sm">
                        Download (.txt)
                    </a>
                </div>
                
                <!-- Form Data (Dynamic) -->
                <div class="space-y-5 mt-6">
                    <div>
                        <label class="preview-form-label">Name</label>
                        <div class="preview-form-value"><?php echo e($candidate['name']); ?></div>
                    </div>
                    
                    <div>
                        <label class="preview-form-label">Gender</label>
                        <div class="preview-form-value"><?php echo e($candidate['gender']); ?></div>
                    </div>

                    <div>
                        <label class="preview-form-label">Email</label>
                        <div class="preview-form-value"><?php echo e($candidate['email']); ?></div>
                    </div>

                    <div>
                        <label class="preview-form-label">Contact Number</label>
                        <div class="preview-form-value"><?php echo e($candidate['contact_number']); ?></div>
                    </div>

                    <div>
                        <label class="preview-form-label">Address</label>
                        <div class="preview-form-value"><?php echo e($candidate['address']); ?></div>
                    </div>

                    <div>
                        <label class="preview-form-label">Objective</label>
                        <div class="preview-form-value"><?php echo e($candidate['objective']); ?></div>
                    </div>

                    <div>
                        <label class="preview-form-label">Education</label>
                        <div class="preview-form-value"><?php echo e($candidate['education']); ?></div>
                    </div>
                    
                    <div>
                        <label class="preview-form-label">Skills</label>
                        <div class="preview-form-value"><?php echo e($candidate['skills']); ?></div>
                    </div>
                    
                    <div>
                        <label class="preview-form-label">Experience</label>
                        <div class="preview-form-value"><?php echo e($candidate['experience']); ?></div>
                    </div>
                    
                    <div>
                        <label class="preview-form-label">Achievements</label>
                        <div class="preview-form-value"><?php echo e($candidate['achievements']); ?></div>
                    </div>
                    
                    <div>
                        <label class="preview-form-label">Language</label>
                        <div class="preview-form-value"><?php echo e($candidate['language']); ?></div>
                    </div>
                    
                </div>
            </div>

        </div>
    </main>

</body>
</html>