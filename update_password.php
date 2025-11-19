<?php
require_once 'connection.php';

$email = $_POST['email'];
$pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

$sql = "UPDATE user SET password='$pass' WHERE email='$email'";

if ($conn->query($sql)) {
    echo "Password updated successfully!";
} else {
    echo "Error updating password.";
}

$conn->close();
?>
