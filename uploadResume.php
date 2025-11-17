<?php
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['resume_file'])) {
        $error = "No file chosen, Please choose the file";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Resume Reader</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">

<style>
    body {
        margin: 0;
        background: #f7f7f7;
        font-family: 'Inter', sans-serif;
    }

    /* Top Bar */
    .top-bar {
        background: #4a8a8a;
        color: white;
        padding: 18px 30px;
        display: flex;
        align-items: center;
        font-size: 22px;
        font-weight: 600;
    }

    .top-bar a {
        text-decoration: none;
        color: white;
        font-size: 22px;
        margin-right: 20px;
    }

    /* Main Container */
    .center-box {
        width: 450px;
        margin: 60px auto;
        background: #ffffff;
        border-radius: 15px;
        padding: 40px;
        border: 12px solid #8fbcbc;
        text-align: center;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    h2 {
        margin-top: 0;
        font-size: 28px;
        font-weight: 700;
    }

    .label {
        text-align: left;
        margin-bottom: 6px;
        font-weight: 600;
        margin-top: 18px;
    }

    select {
        width: 100%;
        padding: 12px;
        border-radius: 10px;
        border: 1px solid #c3d8d8;
        background: #d4e7e7;
        font-size: 15px;
        outline: none;
    }

    .choose-btn {
        margin-top: 18px;
        width: 100%;
        padding: 14px;
        background: #3f7c7c;
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 16px;
        cursor: pointer;
        font-weight: 500;
    }

    .choose-btn:hover {
        background: #356868;
    }

    .file-text {
        margin-top: 6px;
        font-size: 14px;
        color: #777;
    }

    .btn-row {
        display: flex;
        justify-content: space-between;
        margin-top: 30px;
    }

    .confirm-btn {
        background: #1ec748;
        padding: 12px 35px;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 17px;
        cursor: pointer;
        font-weight: 600;
    }

    .cancel-btn {
        background: #d33;
        padding: 12px 35px;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 17px;
        cursor: pointer;
        font-weight: 600;
    }

    .error {
        margin-top: 10px;
        color: red;
        font-weight: 600;
    }

</style>
</head>

<body>

<!-- Top Bar -->
<div class="top-bar">
    <a href="#">← Back</a> Resume Reader
</div>

<!-- Form Container -->
<div class="center-box">
    <h2>Upload Resume</h2>

    <form method="POST">
        
        <div class="label">Applied Job Position</div>
        <select required>
            <option value="" disabled selected>Select a job position</option>
            <option value="Software Engineer">Software Engineer</option>
            <option value="Data Analyst">Data Analyst</option>
            <option value="Designer">Designer</option>
        </select>

        <div class="label">Upload File</div>

        <!-- Choose Resume File button -->
        <button type="button" class="choose-btn" onclick="window.location='dragdropResume.php'">
            📂 Choose Resume File
        </button>

        <div class="file-text">No file chosen</div>

        <input type="hidden" name="resume_file" id="resume_input" value="">

        <div class="btn-row">
            <button type="submit" class="confirm-btn">Confirm</button>
            <button type="button" class="cancel-btn" onclick="window.location='index.php'">Cancel</button>
        </div>

        <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
        <?php endif; ?>

    </form>
</div>

</body>
</html>
