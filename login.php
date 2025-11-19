<?php
session_start();
require_once 'connection.php'; // Your database connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    // Use email as the primary key and handle incorrect login
    $sql = "SELECT name, email, password FROM user WHERE email = '$email'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_name'] = $row['name'];
            $_SESSION['user_email'] = $row['email'];

            // Show welcome message and redirect
            echo "<script>
                    alert('Welcome {$row['name']}');
                    window.location.href = 'dashboard.php?email={$row['email']}';
                  </script>";
            exit();
        } else {
            echo "<script>
                    alert('Incorrect email or password. Please try again.');
                    window.location.href = 'login.html';
                  </script>";
            exit();
        }
    } else {
        echo "<script>
                alert('Incorrect email or password. Please try again.');
                window.location.href = 'login.html';
              </script>";
        exit();
    }
}
$conn->close();
?>

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
