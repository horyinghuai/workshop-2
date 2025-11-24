<?php
// Start a new session.
session_start();

// Include the database configuration file
include 'connection.php';

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {

    // --- INITIALIZE SESSION ---
    $_SESSION['name_err'] = "";
    $_SESSION['email_err'] = "";
    $_SESSION['password_err'] = "";
    $_SESSION['confirm_err'] = "";
    $_SESSION['general_err'] = "";
    
    $has_errors = false;

    // Get Data
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);

    // Store old input to repopulate form on error
    $_SESSION['old_name'] = $name;
    $_SESSION['old_email'] = $email;

    // --- VALIDATION ---
    if (empty($name)) { $_SESSION['name_err'] = "Please enter your name."; $has_errors = true; }
    
    if (empty($email)) { $_SESSION['email_err'] = "Please enter your email."; $has_errors = true; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $_SESSION['email_err'] = "Invalid email format."; $has_errors = true; }

    // Password Validation (updated to match update_password.php)
    if (empty($password)) {
        $_SESSION['password_err'] = "Please enter a password.";
        $has_errors = true;
    } elseif (strlen($password) < 6) {
        $_SESSION['password_err'] = "Password must have at least 6 characters.";
        $has_errors = true;
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $_SESSION['password_err'] = "Password must include at least one uppercase letter.";
        $has_errors = true;
    } elseif (!preg_match('/[a-z]/', $password)) {
        $_SESSION['password_err'] = "Password must include at least one lowercase letter.";
        $has_errors = true;
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $_SESSION['password_err'] = "Password must include at least one symbol.";
        $has_errors = true;
    }

    if ($password != $confirm_password) {
        $_SESSION['confirm_err'] = "Passwords do not match.";
        $has_errors = true;
    }

    if ($password != $confirm_password) { $_SESSION['confirm_err'] = "Passwords do not match."; $has_errors = true; }

    // Redirect if errors
    if ($has_errors) {
        header("Location: register.php");
        exit();
    }

    // --- DATABASE CHECK ---
    $sql = "SELECT email FROM user WHERE email = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $_SESSION['email_err'] = "Email already registered.";
            $stmt->close();
            header("Location: register.php");
            exit();
        }
        $stmt->close();
    }

    // --- INSERT NEW USER ---
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO user (name, email, password) VALUES (?, ?, ?)";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sss", $name, $email, $hashed_password);

        if ($stmt->execute()) {
            
            // --- CRITICAL FIX: AUTO-LOGIN ---
            // We must set these exactly as login.php does
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email; 

            // Clear old form data
            unset($_SESSION['old_name']);
            unset($_SESSION['old_email']);

            // Force session to write to disk immediately
            session_write_close();

            // Redirect to Dashboard
            echo "<script>
                    alert('Registration successful');
                    window.location.href = 'dashboard.php?email=" . $email . "';
                  </script>";
            exit();
            
        } else {
            $_SESSION['general_err'] = "Registration failed. Try again.";
        }
        $stmt->close();
    } else {
        $_SESSION['general_err'] = "Database error.";
    }

    header("Location: register.php");
    exit();
}
?>