<?php
session_start();
require_once 'connection.php'; // Your database connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    // Fetch user from database
    $sql = "SELECT id, password FROM users WHERE email = '$email'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        // Assuming passwords are hashed using password_hash()
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            // Redirect to dashboard
            header('Location: dashboard.php');
            exit();
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "Email not found.";
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | ResumeReader</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>

<div class="container">
    <div class="left-box">
        <img src="logo.png" class="logo">
    </div>

    <div class="right-box">
        <h1>Login</h1>

        <form action="login.php" method="POST">
            <label>Email</label>
            <input type="email" name="email" placeholder="Email" required>

            <label>Password</label>
            <div class="password-container">
                <input type="password" name="password" id="password" required>
            </div>

            <a href="#" class="forgot" onclick="showForgot()">Forgot Password?</a>

            <button type="submit" class="login-btn">Login</button>
        </form>

        <p class="register-text">Don’t have an account? 
            <a href="register.html">Register here</a>
        </p>

        <footer>Copyright 2025 | ResumeReader</footer>
    </div>
</div>

<!-- Forgot Password Window -->
<div id="forgotModal" class="modal">
    <div class="modal-content">
        <h3>Forgot Password</h3>
        <p>Verification code sent to ***@gmail.com</p>

        <label>Verification Code</label>
        <input type="text" id="verifyCode">

        <button onclick="showUpdatePassword()" class="confirm-btn">Confirm</button>
        <button onclick="closeForgot()" class="cancel-btn">Cancel</button>
    </div>
</div>

<!-- Update Password Window -->
<div id="updateModal" class="modal">
    <div class="modal-content">
        <h3>Update New Password</h3>

        <label>New Password</label>
        <input type="password" id="newPassword">

        <label>Confirm New Password</label>
        <input type="password" id="confirmPassword">

        <button onclick="passwordUpdated()" class="confirm-btn">Confirm</button>
        <button onclick="closeUpdate()" class="cancel-btn">Cancel</button>
    </div>
</div>

<script>
function showForgot(){ document.getElementById("forgotModal").style.display="flex"; }
function closeForgot(){ document.getElementById("forgotModal").style.display="none"; }

function showUpdatePassword(){
    document.getElementById("forgotModal").style.display="none";
    document.getElementById("updateModal").style.display="flex";
}

function closeUpdate(){ document.getElementById("updateModal").style.display="none"; }

function passwordUpdated(){
    alert("Password updated successfully. Please use the new password to login.");
    closeUpdate();
}
</script>

</body>
</html>
