<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
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
            <h2>Recruitment Dashboard</h2>
            <p>Overview of your recruitment pipeline and quick actions.</p>
        </section>

        <h3 class="quick-actions-title">Quick Actions</h3>

        <div class="actions-grid">
            <a href="upload_resume.php" class="action-card">
                <div class="card-icon"><i class="fas fa-upload"></i></div>
                <h4 class="card-title">Upload Resume</h4>
                <p class="card-description">Easily submit new candidate resumes for review.</p>
            </a>

            <a href="candidate_scoring.php" class="action-card">
                <div class="card-icon"><i class="fas fa-medal"></i></div>
                <h4 class="card-title">Candidate Scoring</h4>
                <p class="card-description">Evaluate and score applicants based on criteria.</p>
            </a>

            <a href="job_positions.php" class="action-card">
                <div class="card-icon"><i class="fas fa-building"></i></div>
                <h4 class="card-title">Job Positions</h4>
                <p class="card-description">Manage and view all active job listings.</p>
            </a>

            <a href="analytics.php" class="action-card">
                <div class="card-icon"><i class="fas fa-chart-line"></i></div>
                <h4 class="card-title">Analytics</h4>
                <p class="card-description">Gain insights into your recruitment pipeline.</p>
            </a>
        </div>
    </main>
</body>
</html>