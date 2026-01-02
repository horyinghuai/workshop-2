<?php
// Start session to access error messages
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Reader | Register</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <link rel="stylesheet" href="register.css">
</head>
<body>

<div class="logo-column">
    <img src="image/logo.png" alt="Resume Reader Logo">
</div>

<div class="form-column">
    <h1>Register</h1>

    <?php 
    if (!empty($_SESSION['general_err'])) {
        echo '<div class="error-general">' . htmlspecialchars($_SESSION['general_err']) . '</div>';
        unset($_SESSION['general_err']);
    }
    ?>

    <form action="register_process.php" method="post" novalidate>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" 
                value="<?php echo htmlspecialchars($_SESSION['old_email'] ?? ''); ?>">
            <span class="error-message">
                <?php 
                echo htmlspecialchars($_SESSION['email_err'] ?? ''); 
                unset($_SESSION['email_err']);
                ?>
            </span>
        </div>

        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" 
                value="<?php echo htmlspecialchars($_SESSION['old_name'] ?? ''); ?>">
            <span class="error-message">
                <?php 
                echo htmlspecialchars($_SESSION['name_err'] ?? ''); 
                unset($_SESSION['name_err']);
                ?>
            </span>
        </div>

        <div class="form-group">
            <label for="company">Company Name</label>
            <input type="text" id="company" name="company" 
                value="<?php echo htmlspecialchars($_SESSION['old_company'] ?? ''); ?>">
            <span class="error-message">
                <?php 
                echo htmlspecialchars($_SESSION['company_err'] ?? ''); 
                unset($_SESSION['company_err']);
                ?>
            </span>
        </div>

        <!-- UPDATED PASSWORD SECTION -->
        <div class="form-group">
            <label for="password">Password</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password">

                <!-- NEW INFO ICON (same as login.html) -->
                <i class="fas fa-info-circle info-icon" id="toggleInfo" title="Password requirements"></i>

                <!-- NEW INFO BOX (same formatting as login.html) -->
                <div class="info-box" id="infoBox">
                    <strong>Password must meet:</strong><br>
                    - At least 6 characters long<br>
                    - At least one uppercase letter<br>
                    - At least one lowercase letter<br>
                    - At least one symbol (e.g., !@#$%^&*)
                </div>
            </div>

            <span class="error-message">
                <?php 
                echo htmlspecialchars($_SESSION['password_err'] ?? ''); 
                unset($_SESSION['password_err']);
                ?>
            </span>
        </div>
        <!-- END UPDATED PASSWORD SECTION -->

        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <div class="password-wrapper">
                <input type="password" id="confirm_password" name="confirm_password">
            </div>
            <span class="error-message">
                <?php 
                echo htmlspecialchars($_SESSION['confirm_err'] ?? ''); 
                unset($_SESSION['confirm_err']);
                ?>
            </span>
        </div>

        <button type="submit" name="register" class="register-btn">Register</button>

    </form>

    <div class="footer-links">
        <p>Have an account? <a href="login.html">Sign in here</a></p>
        <p class="copyright">Copyright 2025 | ResumeReader</p>
    </div>
</div>

<!-- NEW JAVASCRIPT (copied from login.html) -->
<script>
function toggleInfoBox() {
    const box = document.getElementById("infoBox");
    box.style.display = (box.style.display === "block") ? "none" : "block";
}

document.getElementById("toggleInfo").addEventListener("click", toggleInfoBox);

document.addEventListener("click", function(event) {
    const box = document.getElementById("infoBox");
    const icon = document.getElementById("toggleInfo");

    if (box.style.display === "block" 
        && !box.contains(event.target) 
        && !icon.contains(event.target)) 
    {
        box.style.display = "none";
    }
});
</script>

</body>
</html>
