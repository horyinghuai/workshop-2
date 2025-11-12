<?php
// Start session to access error messages
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ResumeReader</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <link rel="stylesheet" href="register.css">

</head>
<body>

<div class="logo-column">
            <img src="image/logo.jpg" alt="Resume Reader Logo">
        </div>

        <div class="form-column">
            <h1>Register</h1>

            <?php 
            // Display general errors (if any)
            if(!empty($_SESSION['general_err'])) {
                echo '<div class="error-general">' . htmlspecialchars($_SESSION['general_err']) . '</div>';
                unset($_SESSION['general_err']); // Clear message after displaying
            }
            ?>

            <form action="register_process.php" method="post" novalidate>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_SESSION['old_email'] ?? ''); ?>">
                    <span class="error-message">
                        <?php 
                        echo htmlspecialchars($_SESSION['email_err'] ?? ''); 
                        unset($_SESSION['email_err']); // Clear message
                        ?>
                    </span>
                </div>
                
                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_SESSION['old_name'] ?? ''); ?>">
                    <span class="error-message">
                        <?php 
                        echo htmlspecialchars($_SESSION['name_err'] ?? ''); 
                        unset($_SESSION['name_err']); // Clear message
                        ?>
                    </span>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password">
                        <i class="fas fa-info-circle info-icon" id="toggleInfo" title="Password requirements"></i>
                        <div class="info-box" id="infoBox">
                            <p><?php echo nl2br($_SESSION['password_requirements'] ?? ''); ?></p>
                        </div>
                    </div>
                    <span class="error-message">
                        <?php 
                        echo htmlspecialchars($_SESSION['password_err'] ?? ''); 
                        unset($_SESSION['password_err']); // Clear message
                        ?>
                    </span>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password">
                    </div>
                    <span class="error-message">
                        <?php 
                        echo htmlspecialchars($_SESSION['confirm_err'] ?? ''); 
                        unset($_SESSION['confirm_err']); // Clear message
                        ?>
                    </span>
                </div>

                <button type="submit" name="register" class="register-btn">Register</button>

            </form>

            <div class="footer-links">
                <p>Have an account? <a href="login.php">Sign in here</a></p>
                <p class="copyright">Copyright 2025 | ResumeReader</p>
            </div>
        </div>

    <script>
        const toggleInfo = document.getElementById('toggleInfo');
        const infoBox = document.getElementById('infoBox');

        toggleInfo.addEventListener('click', function () {
            infoBox.style.display = infoBox.style.display === 'block' ? 'none' : 'block';
        });
    </script>

</body>
</html>