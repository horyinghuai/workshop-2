<?php
session_start();
include 'connection.php';

// 1. Validate Input
if (!isset($_GET['candidate_id']) || empty($_GET['candidate_id'])) {
    die("Error: Candidate ID is missing.");
}

$candidate_id = intval($_GET['candidate_id']);
$email = isset($_GET['email']) ? $_GET['email'] : ''; // Current logged in user email for navigation

// 2. Fetch Data (Join CANDIDATE and REPORT tables)
$sql = "SELECT c.*, r.* FROM CANDIDATE c 
        LEFT JOIN REPORT r ON c.candidate_id = r.candidate_id 
        WHERE c.candidate_id = ?";

$data = null;

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $candidate_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $data = $result->fetch_assoc();
        } else {
            die("Error: Candidate not found.");
        }
    } else {
        die("Database Error: " . $stmt->error);
    }
    $stmt->close();
}

$conn->close();

// Helper to safely display text
function e($val) {
    return htmlspecialchars($val ?? '');
}

// Helper to format score (if null, show N/A)
function showScore($val) {
    return ($val !== null) ? number_format($val, 2) : "N/A";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<<<<<<< HEAD
    <title>Candidate Report</title>
=======
    <title>Resume Reader | Candidate Report</title>
>>>>>>> 43cf7b0f09f54ba665eb0705918e6ca6f0ee6d4f
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f0f4f4; }
        
        /* Header Style */
        .header-bar {
            background-color: #457b7d; /* Teal color matching image */
            color: white;
        }

        /* Left Column (Resume) Styles */
        .resume-col {
            background-color: #dbecea; /* Light teal/blue */
            color: #333;
        }
        .resume-col label { color: #333; font-weight: 700; }
        
        /* Right Column (Report) Styles */
        .report-col {
            background-color: #2F3E46; /* Dark Slate/Teal */
            color: white;
        }
        .report-col label { color: white; font-weight: 700; }
        .report-col .download-btn {
            background-color: #9dbeb9; 
            color: #2F3E46;
        }

        /* Common Input Styles */
        input[readonly], textarea[readonly] {
            background-color: white;
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 8px 12px;
            width: 100%;
            color: #333;
            font-size: 0.95rem;
            outline: none;
        }
        
        textarea { resize: none; height: 80px; }
        
        /* Label alignment */
        .field-row {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 10px;
            align-items: start;
            margin-bottom: 12px;
        }
        .field-row label {
            padding-top: 8px; /* Align text with input */
        }
    </style>
</head>
<body>

    <header class="header-bar flex justify-between items-center px-6 py-4 shadow-md sticky top-0 z-50">
        <div class="flex items-center">
            <a href="candidateScoring.php?email=<?php echo urlencode($email); ?>" class="text-white hover:text-gray-200 text-lg font-medium flex items-center gap-2">
                <i class="fas fa-chevron-left"></i> Back
            </a>
        </div>
        <h1 class="text-3xl font-bold tracking-wide">Resume Reader</h1>
        <div>
            <a href="logout.php" class="text-white hover:text-gray-200 font-medium text-lg underline">Log Out</a>
        </div>
    </header>

    <div class="flex flex-col md:flex-row h-[calc(100vh-72px)]">
        
        <div class="resume-col w-full md:w-1/2 p-6 overflow-y-auto border-r border-gray-300">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-3xl font-bold text-gray-700">Resume</h2>
                <a href="<?php echo e($data['resume_original']); ?>" download class="bg-[#37474F] text-white px-4 py-1.5 rounded shadow hover:bg-opacity-90 text-sm font-semibold">
                    Download
                </a>
            </div>

            <div class="field-row">
                <label>Name</label>
                <input type="text" readonly value="<?php echo e($data['name']); ?>">
            </div>
            <div class="field-row">
                <label>Gender</label>
                <input type="text" readonly value="<?php echo e($data['gender']); ?>">
            </div>
            <div class="field-row">
                <label>Email</label>
                <input type="text" readonly value="<?php echo e($data['email']); ?>">
            </div>
            <div class="field-row">
                <label>Contact Number</label>
                <input type="text" readonly value="<?php echo e($data['contact_number']); ?>">
            </div>
            <div class="field-row">
                <label>Address</label>
                <textarea readonly><?php echo e($data['address']); ?></textarea>
            </div>
            <div class="field-row">
                <label>Objective</label>
                <textarea readonly><?php echo e($data['objective']); ?></textarea>
            </div>
            <div class="field-row">
                <label>Education</label>
                <textarea readonly style="height:120px"><?php echo e($data['education']); ?></textarea>
            </div>
            <div class="field-row">
                <label>Skills</label>
                <textarea readonly><?php echo e($data['skills']); ?></textarea>
            </div>
            <div class="field-row">
                <label>Experience</label>
                <textarea readonly style="height:120px"><?php echo e($data['experience']); ?></textarea>
            </div>
            <div class="field-row">
                <label>Achievements</label>
                <textarea readonly><?php echo e($data['achievements']); ?></textarea>
            </div>
            <div class="field-row">
                <label>Language</label>
                <textarea readonly><?php echo e($data['language']); ?></textarea>
            </div>
        </div>

        <div class="report-col w-full md:w-1/2 p-6 overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-3xl font-bold">Report</h2>
                <button class="download-btn px-4 py-1.5 rounded shadow hover:bg-opacity-90 text-sm font-bold" onclick="window.print()">
                    Download
                </button>
            </div>

            <div class="field-row">
                <label>Name</label>
                <input type="text" readonly value="<?php echo e($data['name']); ?>">
            </div>
            <div class="field-row">
                <label>Gender</label>
                <input type="text" readonly value="<?php echo e($data['gender']); ?>">
            </div>
            <div class="field-row">
                <label>Email</label>
                <input type="text" readonly value="<?php echo e($data['email']); ?>">
            </div>
            <div class="field-row">
                <label>Contact Number</label>
                <input type="text" readonly value="<?php echo e($data['contact_number']); ?>">
            </div>
            <div class="field-row">
                <label>Address</label>
                <textarea readonly><?php echo e($data['address']); ?></textarea>
            </div>
            <div class="field-row">
                <label>Objective</label>
                <textarea readonly><?php echo e($data['objective']); ?></textarea>
            </div>

            <hr class="border-gray-500 my-6">

            <div class="field-row">
                <label class="text-yellow-300">Overall Score</label>
                <input type="text" readonly value="<?php echo showScore($data['score_overall']); ?>" style="font-weight:bold;">
            </div>
            <div class="field-row">
                <label>Overall Comment</label>
                <textarea readonly><?php echo e($data['ai_comments_overall'] ?? 'No comment generated'); ?></textarea>
            </div>

            <div class="field-row mt-4">
                <label>Education Score</label>
                <input type="text" readonly value="<?php echo showScore($data['score_education']); ?>">
            </div>
            <div class="field-row">
                <label>Edu. Comment</label>
                <textarea readonly><?php echo e($data['ai_comments_education']); ?></textarea>
            </div>

            <div class="field-row mt-4">
                <label>Skills Score</label>
                <input type="text" readonly value="<?php echo showScore($data['score_skills']); ?>">
            </div>
            <div class="field-row">
                <label>Skills Comment</label>
                <textarea readonly><?php echo e($data['ai_comments_skills']); ?></textarea>
            </div>

            <div class="field-row mt-4">
                <label>Experience Score</label>
                <input type="text" readonly value="<?php echo showScore($data['score_experience']); ?>">
            </div>
            <div class="field-row">
                <label>Exp. Comment</label>
                <textarea readonly><?php echo e($data['ai_comments_experience']); ?></textarea>
            </div>

            <div class="field-row mt-4">
                <label>Language Score</label>
                <input type="text" readonly value="<?php echo showScore($data['score_language']); ?>">
            </div>
            <div class="field-row">
                <label>Lang. Comment</label>
                <textarea readonly><?php echo e($data['ai_comments_language']); ?></textarea>
            </div>

            <div class="mt-6 p-3 bg-gray-700 rounded-lg border border-gray-600 text-center">
                <span class="text-gray-300 text-sm">AI Confidence Level:</span>
                <strong class="text-green-400 text-lg ml-2"><?php echo showScore($data['ai_confidence_level']); ?>%</strong>
            </div>

        </div>
    </div>

</body>
</html>