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
        <div class="logo-placeholder">
            <img src="image/logo.jpg" class="logo" alt="Resume Reader Logo">
        </div>
    </div>

    <div class="right-box">
        <h1>Login</h1>

        <form action="login.php" method="POST">
            <label>Email</label>
            <input type="email" name="email" placeholder="Email" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <!-- Added 'return false;' to prevent default link action -->
            <a href="#" class="forgot" onclick="openEmailModal(); return false;">Forgot Password?</a>

            <button type="submit" class="login-btn">Login</button>
        </form>

        <div class="footer-links">
            <p>Don’t have an account? <a href="register.php">Register here</a></p>
            <p class="copyright">Copyright 2025 | ResumeReader</p>
        </div>
    </div>
</div>

<!-- Forgot Password Modal -->
<div id="emailModal" class="modal" style="display:none;">
    <div class="modal-content">
        <h3>Forgot Password</h3>

        <div id="emailError" class="error-msg"></div>
        <label>Email</label>
        <input type="email" id="fpEmail" placeholder="Enter your email">

        <button onclick="sendCode()" class="confirm-btn">Confirm</button>
        <button onclick="closeEmailModal()" class="cancel-btn">Cancel</button>
    </div>
</div>

<!-- Verification Code Modal -->
<div id="codeModal" class="modal" style="display:none;">
    <div class="modal-content">
        <h3>Verification Code</h3>

        <p id="emailDisplay" class="sent-to-text">A verification code has been sent to your email.</p>
        <div id="codeError" class="error-msg"></div>

        <label>Verification Code</label>
        <input type="text" id="verifyCode" maxlength="6" placeholder="6-digit code">

        <button onclick="checkCode()" class="confirm-btn">Confirm</button>
        <button onclick="closeCodeModal()" class="cancel-btn">Cancel</button>
        <a href="#" onclick="resendCode(); return false;" class="resend-link">Resend Code</a>
    </div>
</div>

<!-- Update Password Modal -->
<div id="updateModal" class="modal" style="display:none;">
    <div class="modal-content">
        <h3>Update New Password</h3>

        <div id="updateError" class="error-msg"></div>

        <label>New Password</label>
        <input type="password" id="newPassword">

        <label>Confirm New Password</label>
        <input type="password" id="confirmPassword">

        <button onclick="updatePassword()" class="confirm-btn">Confirm</button>
        <button onclick="closeUpdateModal()" class="cancel-btn">Cancel</button>
    </div>
</div>

<script>
// --- Step 1: Open Email Input Modal ---
function openEmailModal() {
    alert("Forgot Password clicked"); // Debug alert to verify function call
    document.getElementById("fpEmail").value = '';
    document.getElementById("emailError").innerHTML = '';
    document.getElementById("emailModal").style.display = "flex";
}

function closeEmailModal() {
    document.getElementById("emailModal").style.display = "none";
}

// --- Step 2: Send Verification Code ---
function sendCode() {
    const currentEmail = document.getElementById("fpEmail").value.trim();
    document.getElementById("emailError").innerHTML = '';

    if (currentEmail === "" || !currentEmail.includes('@')) {
        document.getElementById("emailError").innerHTML = "Please enter a valid email address.";
        return;
    }

    const sentCode = Math.floor(100000 + Math.random() * 900000);
    sessionStorage.setItem("code", sentCode);

    console.log("DEMO CODE SENT (Check Console): " + sentCode);

    const maskedEmail = currentEmail.replace(/^(.)(.*)(@.*)$/, (_, a, b, c) => a + '*'.repeat(b.length - 2) + b.slice(-2) + c);
    document.getElementById("emailDisplay").innerHTML = `A verification code has been sent to <b>${maskedEmail}</b>`;

    document.getElementById("verifyCode").value = '';
    document.getElementById("codeError").innerHTML = '';
    closeEmailModal();
    document.getElementById("codeModal").style.display = "flex";
}

// --- Step 3: Check Verification Code ---
function checkCode() {
    const userCode = document.getElementById("verifyCode").value.trim();
    const correctCode = sessionStorage.getItem("code");
    document.getElementById("codeError").innerHTML = '';

    if (userCode !== correctCode) {
        document.getElementById("codeError").innerHTML = "Incorrect verification code!";
        return;
    }

    document.getElementById("newPassword").value = '';
    document.getElementById("confirmPassword").value = '';
    document.getElementById("updateError").innerHTML = '';
    closeCodeModal();
    document.getElementById("updateModal").style.display = "flex";
}

function closeCodeModal() {
    document.getElementById("codeModal").style.display = "none";
}

function resendCode() {
    sendCode();
    alert("A new code has been sent. Check your console for the demo code!");
}

// --- Step 4: Update Password ---
function updatePassword() {
    const pass1 = document.getElementById("newPassword").value;
    const pass2 = document.getElementById("confirmPassword").value;
    document.getElementById("updateError").innerHTML = '';

    if (pass1.length < 6) {
        document.getElementById("updateError").innerHTML = "Password must be at least 6 characters long.";
        return;
    }

    if (pass1 !== pass2) {
        document.getElementById("updateError").innerHTML = "Passwords do not match!";
        return;
    }

    // Call PHP backend securely to update password
    fetch("update_password.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `email=${encodeURIComponent(currentEmail)}&password=${encodeURIComponent(pass1)}`
    })
    .then(res => res.text())
    .then(data => {
        alert(data);
        closeUpdateModal();
        sessionStorage.removeItem("code");
    })
    .catch(error => {
        document.getElementById("updateError").innerHTML = "An error occurred during password update.";
        console.error('Error:', error);
    });
}

function closeUpdateModal() {
    document.getElementById("updateModal").style.display = "none";
}
</script>

</body>
</html>
