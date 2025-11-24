<?php
// dashboard.php
require 'config.php';

// --- Total Resumes Uploaded ---
$totalResumes = 0;
$res = $mysqli->query("SELECT COUNT(*) as cnt FROM resumes");
if ($res) {
    $row = $res->fetch_assoc();
    $totalResumes = (int)$row['cnt'];
    $res->free();
}

// --- New Applicants Today ---
$newToday = 0;
$stmt = $mysqli->prepare("SELECT COUNT(*) as cnt FROM resumes WHERE DATE(uploaded_at) = CURDATE()");
$stmt->execute();
$stmt->bind_result($cnt);
if ($stmt->fetch()) $newToday = (int)$cnt;
$stmt->close();

// --- Resumes by Job Position ---
$jobLabels = [];
$jobCounts = [];
$q = $mysqli->query("SELECT job_position, COUNT(*) as cnt FROM resumes GROUP BY job_position ORDER BY cnt DESC");
if ($q) {
    while ($r = $q->fetch_assoc()) {
        $jobLabels[] = $r['job_position'];
        $jobCounts[] = (int)$r['cnt'];
    }
    $q->free();
}

// --- Resumes by Department ---
$deptLabels = [];
$deptCounts = [];
$q = $mysqli->query("SELECT department, COUNT(*) as cnt FROM resumes GROUP BY department ORDER BY cnt DESC");
if ($q) {
    while ($r = $q->fetch_assoc()) {
        $deptLabels[] = $r['department'];
        $deptCounts[] = (int)$r['cnt'];
    }
    $q->free();
}

// --- AI Confidence Level Distribution ---
// Assuming ai_confidence_score is numeric between 0 and 1 (or 0-100). We'll treat >1 as 0-100 scale.
$high = $medium = $low = 0;
$q = $mysqli->query("SELECT ai_confidence_score FROM resumes WHERE ai_confidence_score IS NOT NULL");
if ($q) {
    while ($r = $q->fetch_assoc()) {
        $score = (float)$r['ai_confidence_score'];
        // normalize if stored 0-100
        if ($score > 1) $score = $score / 100.0;
        if ($score >= 0.75) $high++;
        elseif ($score >= 0.4) $medium++;
        else $low++;
    }
    $q->free();
}

// --- Top Candidates (Software Engineer) ---
$topSoftware = [];
$stmt = $mysqli->prepare("SELECT candidate_name, role, score FROM resumes WHERE role = ? ORDER BY score DESC LIMIT 3");
$role1 = 'Software Engineer';
$stmt->bind_param('s', $role1);
$stmt->execute();
$stmt->bind_result($cname, $crole, $cscore);
while ($stmt->fetch()) {
    $topSoftware[] = ['name'=>$cname, 'role'=>$crole, 'score'=>$cscore];
}
$stmt->close();

// --- Top Candidates (Data Scientist) ---
$topData = [];
$stmt = $mysqli->prepare("SELECT candidate_name, role, score FROM resumes WHERE role = ? ORDER BY score DESC LIMIT 3");
$role2 = 'Data Scientist';
$stmt->bind_param('s', $role2);
$stmt->execute();
$stmt->bind_result($cname, $crole, $cscore);
while ($stmt->fetch()) {
    $topData[] = ['name'=>$cname, 'role'=>$crole, 'score'=>$cscore];
}
$stmt->close();

// Prepare JSON for JS
$jobLabelsJSON = json_encode($jobLabels);
$jobCountsJSON = json_encode($jobCounts);
$deptLabelsJSON = json_encode($deptLabels);
$deptCountsJSON = json_encode($deptCounts);
$confidenceJSON = json_encode([$high, $medium, $low]);

// Close DB
$mysqli->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Resume Reader - Analytics</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Chart.js CDN -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <header class="site-header">
    <div class="left-link"><a href="#" class="back">‹ Back</a></div>
    <div class="brand">
      <!-- using your uploaded image as requested -->
      <img src="/mnt/data/24c09001-d5e9-41bf-9e08-e1314006da0b.png" alt="Resume Reader Logo" class="logo-img">
      <h1>Resume Reader</h1>
    </div>
    <div class="right-link"><a href="#" class="logout">Log Out</a></div>
  </header>

  <main class="container">
    <section class="stats-row">
      <div class="stat-card">
        <small>Total Resumes Uploaded</small>
        <div class="stat-value"><?php echo number_format($totalResumes); ?></div>
      </div>
      <div class="stat-card">
        <small>New Applicants Today</small>
        <div class="stat-value"><?php echo number_format($newToday); ?></div>
      </div>
    </section>

    <section class="charts-grid">
      <div class="chart-card">
        <h3>Resumes by Job Position</h3>
        <canvas id="jobsChart" height="220"></canvas>
      </div>

      <div class="chart-card">
        <h3>Resumes by Department</h3>
        <canvas id="deptChart" height="220"></canvas>
      </div>

      <div class="chart-card donut-card">
        <h3>AI Confidence Level Distribution</h3>
        <canvas id="confidenceChart" height="220"></canvas>
        <div class="legend-small">
          <div><span class="dot high"></span>High Confidence</div>
          <div><span class="dot medium"></span>Medium Confidence</div>
          <div><span class="dot low"></span>Low Confidence</div>
        </div>
      </div>
    </section>

    <section class="candidates-row">
      <div class="candidates-card">
        <h3>Top Candidates: Software Engineer</h3>
        <ul class="candidate-list">
          <?php foreach ($topSoftware as $cand): ?>
            <li>
              <div>
                <strong><?php echo htmlspecialchars($cand['name']); ?></strong>
                <div class="muted"><?php echo htmlspecialchars($cand['role']); ?></div>
              </div>
              <div class="score-pill">Score: <?php echo htmlspecialchars($cand['score']); ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="candidates-card">
        <h3>Top Candidates: Data Scientist</h3>
        <ul class="candidate-list">
          <?php foreach ($topData as $cand): ?>
            <li>
              <div>
                <strong><?php echo htmlspecialchars($cand['name']); ?></strong>
                <div class="muted"><?php echo htmlspecialchars($cand['role']); ?></div>
              </div>
              <div class="score-pill">Score: <?php echo htmlspecialchars($cand['score']); ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>

  </main>

  <script>
    // Data passed from PHP
    const jobLabels = <?php echo $jobLabelsJSON; ?>;
    const jobCounts = <?php echo $jobCountsJSON; ?>;
    const deptLabels = <?php echo $deptLabelsJSON; ?>;
    const deptCounts = <?php echo $deptCountsJSON; ?>;
    const confidenceData = <?php echo $confidenceJSON; ?>;
  </script>
  <script src="assets/dashboard.js"></script>
</body>
</html>
