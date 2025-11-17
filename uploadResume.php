<?php
// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === 0) {

        // Save uploaded file
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $targetFile = $targetDir . basename($_FILES["resume"]["name"]);
        move_uploaded_file($_FILES["resume"]["tmp_name"], $targetFile);

        // Redirect to preview page
        header("Location: preview.php?file=" . urlencode($targetFile));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Resume Reader</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="top-bar">
    <a href="#" class="back-btn">← Back</a>
    <h1>Resume Reader</h1>
</div>

<div class="upload-wrapper">
    <div class="upload-card">

        <h2>Upload Resume</h2>

        <form action="" method="POST" enctype="multipart/form-data">

            <label class="label-title">Applied Job Position</label>
            <select name="job_position" class="dropdown">
                <option>Data Engineer</option>
                <option>Data Analyst</option>
                <option>Software Engineer</option>
                <option>AI Engineer</option>
            </select>

            <label class="label-title">Upload File</label>

            <div class="drag-area" id="dragArea">
                <p>Drag & drop the resume here<br>PDF, DOCX up to 5MB</p>
                <input type="file" name="resume" id="resumeInput" hidden>
            </div>

            <div class="buttons">
                <button type="submit" class="confirm-btn">Confirm</button>
                <button type="button" class="cancel-btn" onclick="window.history.back();">Cancel</button>
            </div>

        </form>

    </div>
</div>

<script>
// Click to open file explorer
document.getElementById("dragArea").addEventListener("click", () => {
    document.getElementById("resumeInput").click();
});

// Drag and Drop handling
const dragArea = document.getElementById("dragArea");

dragArea.addEventListener("dragover", (e) => {
    e.preventDefault();
    dragArea.classList.add("drag-over");
});

dragArea.addEventListener("dragleave", () => {
    dragArea.classList.remove("drag-over");
});

dragArea.addEventListener("drop", (e) => {
    e.preventDefault();
    dragArea.classList.remove("drag-over");

    const file = e.dataTransfer.files[0];
    document.getElementById("resumeInput").files = e.dataTransfer.files;
});
</script>

</body>
</html>
