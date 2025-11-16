<?php
session_start();
require_once 'connection.php';

// Redirect to login if user not logged in
if (!isset($_GET['email'])) {
    header('Location: login.php');
    exit();
}

$current_email = $conn->real_escape_string($_GET['email']);

// Fetch user details based on email
$sql = "SELECT name FROM user WHERE email = '$current_email'";
$result = $conn->query($sql);

if ($result && $result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $user_name = $row['name'];
} else {
    // Redirect to login if email is invalid
    header('Location: login.php');
    exit();
}

// Fetch active jobs count
$active_jobs_count = 0;
$sql = "SELECT COUNT(*) AS count FROM job_position";
if ($result = $conn->query($sql)) {
    if ($row = $result->fetch_assoc()) {
        $active_jobs_count = (int)$row['count'];
    }
    $result->free();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recruitment Dashboard - Resume Reader</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<header class="header">
    <h1 class="logo">Resume Reader</h1>
    <a href="logout.php" class="logout-link">Log Out</a>
</header>

<main class="dashboard-container">
    <section class="dashboard-header">
        <p>Welcome, <?php echo htmlspecialchars($user_name); ?>!</p>
        <h2>Recruitment Dashboard</h2>
        <p>Overview of your recruitment pipeline and quick actions.</p>
    </section>

    <h3 class="quick-actions-title">Quick Actions</h3>

    <div class="actions-grid">
        <a href="uploadResumeYing.php?email=<?php echo urlencode($current_email); ?>" class="action-card">
            <div class="card-icon"><i class="fas fa-upload"></i></div>
            <h4 class="card-title">Upload Resume</h4>
            <p class="card-description">Easily submit new candidate resumes for review.</p>
        </a>

        <a href="candidate_scoring.php?email=<?php echo urlencode($current_email); ?>" class="action-card">
            <div class="card-icon"><i class="fas fa-medal"></i></div>
            <h4 class="card-title">Candidate Scoring</h4>
            <p class="card-description">Evaluate and score applicants based on criteria.</p>
        </a>

        <a href="job_positions.php?email=<?php echo urlencode($current_email); ?>" class="action-card">
            <div class="card-icon"><i class="fas fa-building"></i></div>
            <h4 class="card-title">Job Positions</h4>
            <p class="card-description">Manage and review job positions.</p>
        </a>

        <a href="analytics.php?email=<?php echo urlencode($current_email); ?>" class="action-card">
            <div class="card-icon"><i class="fas fa-chart-line"></i></div>
            <h4 class="card-title">Analytics</h4>
            <p class="card-description">Gain insights into your recruitment pipeline.</p>
        </a>
    </div>
</main>
</body>
</html>
