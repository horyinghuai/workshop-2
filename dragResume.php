<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drag and Drop Resume Upload</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <a href="upload_resume.php" class="back-button">&larr; Back</a>
        <h1 class="header-title">Resume Reader</h1>
    </div>

    <div class="drag-container">
        <h2>Upload Your Resume</h2>
        <form action="upload_action.php" method="post" enctype="multipart/form-data">
            <div class="drag-area" id="dragArea">
                <p>Drag & Drop your resume here</p>
                <p>or</p>
                <input type="file" name="resume" id="fileInput" hidden>
                <label for="fileInput" class="browse-btn">Browse File</label>
            </div>
            <br>
            <button type="submit" class="confirm-btn">Upload</button>
        </form>
    </div>

    <script>
        const dragArea = document.getElementById("dragArea");
        const fileInput = document.getElementById("fileInput");

        dragArea.addEventListener("dragover", (event) => {
            event.preventDefault();
            dragArea.classList.add("active");
        });

        dragArea.addEventListener("dragleave", () => {
            dragArea.classList.remove("active");
        });

        dragArea.addEventListener("drop", (event) => {
            event.preventDefault();
            fileInput.files = event.dataTransfer.files;
            dragArea.classList.remove("active");
            dragArea.querySelector("p").textContent = event.dataTransfer.files[0].name;
        });
    </script>
</body>
</html>
