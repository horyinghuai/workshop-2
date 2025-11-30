<?php
session_start();
require_once 'connection.php';

// Check if user is logged in via Session
$is_logged_in = isset($_SESSION['user_email']);
$user_name = "Guest";
$current_email = "";

if ($is_logged_in) {
    $current_email = $_SESSION['user_email'];
    $safe_email = $conn->real_escape_string($current_email);

    // Fetch user details based on email
    $sql = "SELECT name FROM user WHERE email = '$safe_email'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $user_name = $row['name'];
    }
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
    <title>Resume Reader | Dashboard</title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        // Function to enforce login before accessing tools
        function requireLogin() {
            alert("Please login or register to access this feature.");
            window.location.href = 'login.html';
        }
    </script>
</head>
<body>

    <header class="header">
        <h1 class="header-title">Resume Reader</h1>
        
        <div class="header-right">
            <?php if ($is_logged_in): ?>
                <a href="logout.php" class="nav-btn logout-link">Log Out</a>
            <?php else: ?>
                <a href="login.html" class="nav-btn login-link">Login</a>
                <a href="register.php" class="nav-btn register-link">Register</a>
            <?php endif; ?>
        </div>
    </header>

<main class="dashboard-container">
    <section class="dashboard-header">
        <p>Welcome, <?php echo htmlspecialchars($user_name); ?>!</p>
        <h2>Recruitment Dashboard</h2>
        <p>Overview of your recruitment pipeline and quick actions.</p>
    </section>

    <h3 class="quick-actions-title">Quick Actions</h3>

    <div class="actions-grid">
        <a href="<?php echo $is_logged_in ? "uploadResumeYing.php?email=" . urlencode($current_email) : "javascript:requireLogin()"; ?>" class="action-card">
            <div class="card-icon"><i class="fas fa-upload"></i></div>
            <h4 class="card-title">Upload Resume</h4>
            <p class="card-description">Easily submit new candidate resumes for review.</p>
        </a>

        <a href="<?php echo $is_logged_in ? "candidateScoring.php?email=" . urlencode($current_email) : "javascript:requireLogin()"; ?>" class="action-card">
            <div class="card-icon"><i class="fas fa-medal"></i></div>
            <h4 class="card-title">Candidates and Scoring Management</h4>
            <p class="card-description">Manage and evaluate applicants based on criteria.</p>
        </a>

        <a href="<?php echo $is_logged_in ? "jobPosition.php?email=" . urlencode($current_email) : "javascript:requireLogin()"; ?>" class="action-card">
            <div class="card-icon"><i class="fas fa-building"></i></div>
            <h4 class="card-title">Job Positions & Departments</h4>
            <p class="card-description">Manage and review positions and departments.</p>
        </a>

        <a href="<?php echo $is_logged_in ? "analytics.php?email=" . urlencode($current_email) : "javascript:requireLogin()"; ?>" class="action-card">
            <div class="card-icon"><i class="fas fa-chart-line"></i></div>
            <h4 class="card-title">Analytics</h4>
            <p class="card-description">Gain insights into your recruitment pipeline.</p>
        </a>

        <a href="<?php echo $is_logged_in ? "calendar.php?email=" . urlencode($current_email) : "javascript:requireLogin()"; ?>" class="action-card">
            <div class="card-icon"><i class="fas fa-calendar-alt"></i></div>
            <h4 class="card-title">Interview Calendar</h4>
            <p class="card-description">View scheduled interviews.</p>
        </a>
    </div>
</main>
    <?php include 'chatbot_widget.php'; ?>
</body>
</html>