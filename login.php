<?php
// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Get and sanitize the input data
    // htmlspecialchars() prevents XSS attacks
    $username = htmlspecialchars($_POST['username']);
    $password = htmlspecialchars($_POST['password']);

    // 2. In a real application:
    //    - Connect to your database.
    //    - Prepare a SQL statement to find the user.
    //    - E.g., "SELECT password_hash FROM users WHERE username = ?"
    //    - Fetch the user's hashed password.
    //    - Use password_verify($password, $hashed_password) to check.
    //    - If valid, create a session and redirect to a member's page.
    //    - If invalid, redirect back to login.php with an error message.

    // 3. For this demo, we'll just display the received data
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <title>Login Processed</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; margin: 40px; }
            h1 { color: #333; }
            p { line-height: 1.6; }
            code { background-color: #f4f4f4; padding: 2px 6px; border-radius: 4px; }
            a { color: #007bff; }
        </style>
    </head>
    <body>
        <h1>Login Attempt Received</h1>
        <p>This is a placeholder for your backend logic.</p>
        <p>Username entered: <code>" . $username . "</code></p>
        <p>Password entered: <code>[HIDDEN FOR SECURITY]</code></p>
        <hr>
        <p>In a real application, you would now query your database to verify these credentials.</p>
        <br>
        <a href='login.php'>&larr; Go Back to Login Page</a>
    </body>
    </html>";

} else {
    // If someone tries to access this page directly, redirect them
    header("Location: login.php");
    exit();
}
?>