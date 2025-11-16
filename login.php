<?php
session_start();

// Dummy login validation (replace with DB later)
$validEmail = "admin@resumereader.com";
$validPassword = "12345678";

$email = $_POST["email"];
$password = $_POST["password"];

if ($email === $validEmail && $password === $validPassword) {
    $_SESSION["user"] = $email;
    header("Location: dashboard.php");
    exit();
} else {
    echo "<script>alert('Invalid email or password'); window.location.href='login.html';</script>";
}
?>
