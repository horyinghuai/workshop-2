<?php
// Start a new session. This is required to pass error messages.
session_start();

// Include the database configuration file
include 'connection.php';

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {

    // --- INITIALIZE SESSION FOR ERRORS AND OLD DATA ---
    // Clear any old errors and form data from the session
    $_SESSION['name_err'] = "";
    $_SESSION['email_err'] = "";
    $_SESSION['password_err'] = "";
    $_SESSION['confirm_err'] = "";
    $_SESSION['general_err'] = "";
    $_SESSION['old_name'] = "";
    $_SESSION['old_email'] = "";

    // A flag to track if there are any validation errors
    $has_errors = false;

    // Store posted values in session to repopulate form if there's an error
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);

    $_SESSION['old_name'] = $name;
    $_SESSION['old_email'] = $email;

    // --- VALIDATE NAME ---
    if (empty($name)) {
        $_SESSION['name_err'] = "Please enter your name.";
        $has_errors = true;
    }

    // --- VALIDATE EMAIL ---
    if (empty($email)) {
        $_SESSION['email_err'] = "Please enter your email.";
        $has_errors = true;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['email_err'] = "Please enter a valid email address.";
        $has_errors = true;
    }

    // --- VALIDATE PASSWORD ---
    // List all password requirements to the user
    $_SESSION['password_requirements'] = "Password must meet the following criteria: \n" .
        "- At least 6 characters long \n" .
        "- At least one uppercase letter \n" .
        "- At least one lowercase letter \n" .
        "- At least one symbol (e.g., !@#$%^&*)";

    // Validate password against each requirement
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

    // --- VALIDATE CONFIRM PASSWORD ---
    if (empty($confirm_password)) {
        $_SESSION['confirm_err'] = "Please confirm your password.";
        $has_errors = true;
    } elseif ($password != $confirm_password) {
        $_SESSION['confirm_err'] = "Passwords do not match.";
        $has_errors = true;
    }

    // --- IF THERE ARE VALIDATION ERRORS, REDIRECT BACK TO FORM ---
    if ($has_errors) {
        header("Location: register.php");
        exit();
    }

    // --- IF VALIDATION PASSES, PROCEED WITH DATABASE ---

    // 1. Check if email already exists
    $sql = "SELECT email FROM user WHERE email = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Email already exists
            $_SESSION['email_err'] = "This email is already registered.";
            $stmt->close();
            header("Location: register.php");
            exit();
        }
        $stmt->close();
    } else {
        $_SESSION['general_err'] = "Database error. Please try again.";
        header("Location: register.php");
        exit();
    }

    // 2. Email is new, proceed to insert new user
    // Encrypt (hash) the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO user (name, email, password) VALUES (?, ?, ?)";

    if ($stmt = $conn->prepare($sql)) {
        // Bind parameters: s = string
        $stmt->bind_param("sss", $name, $email, $hashed_password);

        // Execute the statement
        if ($stmt->execute()) {
            // Registration successful!
            
            // Clear the "old" form data from session
            unset($_SESSION['old_name']);
            unset($_SESSION['old_email']);

            // Show pop-up and redirect to dashboard.php
            echo "<script>
                    alert('Registration successful');
                    window.location.href = 'dashboard.php';
                  </script>";
            
            $stmt->close();
            $conn->close();
            exit(); // Stop script execution
            
        } else {
            // Insertion failed
            $_SESSION['general_err'] = "Something went wrong. Please try again later.";
        }
        $stmt->close();
    } else {
        $_SESSION['general_err'] = "Database error. Please try again.";
    }

    // If anything failed, redirect back to register page
    header("Location: register.php");
    exit();

} else {
    // If someone tries to access this file directly, redirect them
    header("Location: register.php");
    exit();
}
?>