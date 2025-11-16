<?php
session_start();
include 'connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'];
    $password = $_POST['password'];

    // Query user table
    $sql = "SELECT * FROM user WHERE email = '$email' AND password = '$password'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {

        $row = $result->fetch_assoc();
        
        // Store session values
        $_SESSION['email'] = $row['email'];
        $_SESSION['name'] = $row['name'];

        // Redirect to dashboard
        header("Location: dashboard.php");
        exit();

    } else {
        echo "<script>
            alert('Invalid email or password!');
            window.location.href='login.html';
        </script>";
    }
}
?>
