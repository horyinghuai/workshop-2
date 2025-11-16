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
    <title>Login | ResumeReader</title>
</head>
<body>
<?php if (!empty($error)) { echo "<p style='color:red;'>$error</p>"; } ?>

</body>
</html>
