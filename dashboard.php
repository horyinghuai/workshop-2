<?php
session_start();
require_once 'connection.php';

// Redirect to login if user not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch active jobs count
$active_jobs_count = 0;
$sql = "SELECT COUNT(*) AS count FROM job_positions WHERE status = 'active'";
if ($result = $conn->query($sql)) {
    if ($row = $result->fetch_assoc()) {
        $active_jobs_count = (int)$row['count'];
    }
    $result->free();
}

$conn->close();

// Include the HTML template
include 'dashboard_template.php';
?>
