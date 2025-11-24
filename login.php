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