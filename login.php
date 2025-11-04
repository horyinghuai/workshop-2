<?php
// Check if the form was submitted using POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Get and sanitize the input data
    // htmlspecialchars() is a basic security measure against XSS attacks
    $username = htmlspecialchars($_POST['username']);
    $password = htmlspecialchars($_POST['password']);

    // 2. --- In a Real Application ---
    //    - You would connect to your database here.
    //    - You would query for the user: "SELECT password_hash FROM users WHERE username = ?"
    //    - You would use password_verify($password, $hashed_password) to check.
    //    - If valid, you'd start a session (session_start(); $_SESSION['user'] = ...;)
    //    - ...and redirect to a dashboard: header("Location: dashboard.php");
    //    - If invalid, you'd redirect back with an error: header("Location: login.php?error=1");

    // 3. --- For this Demonstration ---
    //    We will just display the received data.
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <title>Login Processed</title>
        <style>
            body { font-family: 'Poppins', sans-serif; margin: 40px; background-color: #f4f6f8; }
            div { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
            h1 { color: #333; }
            p { line-height: 1.6; }
            code { background-color: #f0f0f0; padding: 2px 6px; border-radius: 4px; }
            a { color: #007bff; text-decoration: none; }
            a:hover { text-decoration: underline; }
        </style>
    </head>
    <body>
        <div>
            <h1>Login Attempt Received</h1>
            <p>This is a placeholder for your backend logic.</p>
            <p>Username entered: <code>" . $username . "</code></p>
            <p>Password entered: <code>[HIDDEN FOR SECURITY]</code></p>
            <hr style='border:0; border-top:1px solid #eee; margin: 20px 0;'>
            <p>In a real application, you would now query your database to verify these credentials.</p>
            <br>
            <a href='login.php'>&larr; Go Back to Login Page</a>
        </div>
    </body>
    </html>";

} else {
    // If someone tries to access this PHP file directly,
    // just redirect them back to the login page.
    header("Location: login.php");
    exit();
}
?>