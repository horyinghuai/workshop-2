<?php
$job = isset($_GET['job']) ? $_GET['job'] : "Not specified";
$file = isset($_GET['file']) ? $_GET['file'] : "";

// Dummy extracted resume data
$resumeData = [
    "name" => "Arun",
    "gender" => "Male",
    "email" => "arun@gmail.com",
    "contact" => "012-3456789",
    "address" => "1, Jalan Bunga 5, Taman Matahari, 76540 Merlimau, Melaka.",
    "objective" => "Seeking an AI Engineer position to apply experience in machine learning to help build real-world AI solutions.",
    "education" => "Graduated from UTeM in Bachelor of Computer Science (Artificial Intelligence)"
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Preview Resume</title>
<style>
body {
    font-family: Arial, sans-serif;
    background-color: #f5f7f7;
    margin: 0;
    padding: 0;
}
.header {
    background-color: #3a7c7c;
    color: white;
    padding: 15px 30px;
    font-size: 24px;
    font-weight: bold;
}
.header a {
    color: white;
    text-decoration: underline;
    font-size: 16px;
}
.main-container {
    display: flex;
    justify-content: center;
    margin-top: 30px;
    gap: 25px;
}
.resume-box {
    background-color: white;
    padding: 20px;
    border-radius: 15px;
    width: 40%;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
.preview {
    text-align: center;
}
.right-box input, .right-box textarea {
    width: 100%;
    padding: 8px;
    margin-top: 6px;
    margin-bottom: 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
}
.download-btn {
    background-color: #3a7c7c;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    float: right;
}
</style>
</head>
<body>

<div class="header">
    <a href="UploadResume.php">Back</a> &nbsp;&nbsp; Resume Reader
</div>

<div class="main-container">
    <div class="resume-box preview">
        <h3>Resume</h3>
        <img src="https://via.placeholder.com/150" alt="Profile Photo" style="border-radius:50%;"><br><br>
        <p><b>RICHARD SANCHEZ</b><br>MARKETING MANAGER</p>
        <hr>
        <p><b>Profile:</b><br>Lorem ipsum dolor sit amet, consectetur adipiscing elit...</p>
        <p><b>Education:</b><br>UTeM - Bachelor of Computer Science</p>
    </div>

    <div class="resume-box right-box">
        <button class="download-btn">Download</button>
        <h3>Resume Details</h3>
        <label><b>Name</b></label>
        <input type="text" value="<?php echo $resumeData['name']; ?>">
        <label><b>Gender</b></label>
        <input type="text" value="<?php echo $resumeData['gender']; ?>">
        <label><b>Email</b></label>
        <input type="email" value="<?php echo $resumeData['email']; ?>">
        <label><b>Contact Number</b></label>
        <input type="text" value="<?php echo $resumeData['contact']; ?>">
        <label><b>Address</b></label>
        <textarea rows="3"><?php echo $resumeData['address']; ?></textarea>
        <label><b>Objective</b></label>
        <textarea rows="3"><?php echo $resumeData['objective']; ?></textarea>
        <label><b>Education</b></label>
        <textarea rows="2"><?php echo $resumeData['education']; ?></textarea>
    </div>
</div>

</body>
</html>
