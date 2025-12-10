<?php
require_once 'connection.php';

// Redirect if no email in URL
if (!isset($_GET['email'])) {
    header('Location: login.php');
    exit();
}

$currentEmail = $conn->real_escape_string($_GET['email']);

/* ================= SUMMARY ================= */
$totalResumes = (int)$conn->query("SELECT COUNT(*) AS c FROM CANDIDATE")->fetch_assoc()['c'];
$newToday = (int)$conn->query("SELECT COUNT(*) AS c FROM CANDIDATE WHERE applied_date = CURDATE()")->fetch_assoc()['c'];

/* ================= JOB POSITION STATS ================= */
$jobLabels = [];
$jobCounts = [];

$jobQuery = $conn->query("
    SELECT j.job_name, COUNT(c.candidate_id) AS cnt
    FROM JOB_POSITION j
    LEFT JOIN CANDIDATE c ON c.job_id = j.job_id
    GROUP BY j.job_id
    ORDER BY cnt DESC
");

while ($row = $jobQuery->fetch_assoc()) {
    $jobLabels[] = $row['job_name'];
    $jobCounts[] = (int)$row['cnt'];
}

/* ================= DEPARTMENT STATS ================= */
$deptLabels = [];
$deptCounts = [];

$deptQuery = $conn->query("
    SELECT d.department_name, COUNT(c.candidate_id) AS cnt
    FROM DEPARTMENT d
    LEFT JOIN JOB_POSITION j ON j.department_id = d.department_id
    LEFT JOIN CANDIDATE c ON c.job_id = j.job_id
    GROUP BY d.department_id
    ORDER BY cnt DESC
");

while ($row = $deptQuery->fetch_assoc()) {
    $deptLabels[] = $row['department_name'];
    $deptCounts[] = (int)$row['cnt'];
}

/* ================= MONTHLY APPLICANTS STATS ================= */
$monthLabels = [];
$monthCounts = [];

$monthQuery = $conn->query("
    SELECT DATE_FORMAT(applied_date, '%M %Y') as month_label, COUNT(*) as cnt
    FROM CANDIDATE
    GROUP BY YEAR(applied_date), MONTH(applied_date)
    ORDER BY applied_date ASC
");

while ($row = $monthQuery->fetch_assoc()) {
    $monthLabels[] = $row['month_label'];
    $monthCounts[] = (int)$row['cnt'];
}

/* ================= TOP CANDIDATE PER JOB POSITION (GROUPED BY DEPT) ================= */
$topJobCandidates = []; // Keyed by Department Name

// 1. Get all Job Positions linked with their Departments
$jobListQ = $conn->query("
    SELECT j.job_id, j.job_name, d.department_name 
    FROM JOB_POSITION j
    JOIN DEPARTMENT d ON j.department_id = d.department_id
    ORDER BY d.department_name ASC, j.job_name ASC
");

while ($j = $jobListQ->fetch_assoc()) {
    // 2. Get top candidate for this Job Position based on Overall Score
    $stmt = $conn->prepare("
        SELECT c.name, r.score_overall
        FROM CANDIDATE c
        JOIN REPORT r ON c.candidate_id = r.candidate_id
        WHERE c.job_id = ?
        ORDER BY r.score_overall DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $j['job_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($cand = $res->fetch_assoc()) {
        $dept = $j['department_name'];
        if (!isset($topJobCandidates[$dept])) {
            $topJobCandidates[$dept] = [];
        }
        $topJobCandidates[$dept][] = [
            'name' => $cand['name'],
            'job_name' => $j['job_name'],
            'score' => $cand['score_overall']
        ];
    }
    $stmt->close();
}

/* ================= TOP CANDIDATE PER RESUME FIELD ================= */
$resumeFields = [
    'Education' => 'score_education',
    'Skills' => 'score_skills',
    'Experience' => 'score_experience',
    'Language' => 'score_language',
    'Others' => 'score_others'
];

$topFieldCandidates = [];

foreach ($resumeFields as $label => $col) {
    // Sort by specific field DESC, then Overall DESC (tie-breaker)
    $sql = "
        SELECT c.name, j.job_name, r.score_overall, r.$col AS field_score
        FROM CANDIDATE c
        JOIN JOB_POSITION j ON c.job_id = j.job_id
        JOIN REPORT r ON c.candidate_id = r.candidate_id
        ORDER BY r.$col DESC, r.score_overall DESC
        LIMIT 1
    ";
    
    $res = $conn->query($sql);
    if ($cand = $res->fetch_assoc()) {
        $topFieldCandidates[] = [
            'category' => $label,
            'name' => $cand['name'],
            'job_name' => $cand['job_name'],
            'field_score' => $cand['field_score'],
            'overall' => $cand['score_overall']
        ];
    }
}

/* ================= JSON FOR CHARTS ================= */
$jobLabelsJSON  = json_encode($jobLabels);
$jobCountsJSON  = json_encode($jobCounts);

$deptLabelsJSON = json_encode($deptLabels);
$deptCountsJSON = json_encode($deptCounts);

$monthLabelsJSON = json_encode($monthLabels);
$monthCountsJSON = json_encode($monthCounts);

$conn->close();
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Resume Reader — Analytics</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="analytics.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Small overrides for list display */
        .cand-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .cand-list li:last-child { border-bottom: none; }
        
        .cand-info { display: flex; flex-direction: column; }
        .cand-meta { font-size: 0.85rem; color: #666; margin-top: 2px; }
        .cand-score { text-align: right; }
        
        .score-main { 
            font-weight: bold; 
            color: #3a7c7c; 
            font-size: 1.1rem; 
        }
        
        /* Default Score Sub Color */
        .score-sub { 
            font-size: 0.8rem; 
            color: black; 
        }
        
        .badge { 
            display: inline-block; 
            padding: 2px 8px; 
            border-radius: 12px; 
            background: #e0f2f1; 
            color: #00695c; 
            font-size: 0.75rem; 
            font-weight: 600;
            margin-bottom: 4px;
        }

        .bottom-row .card {
            padding: 1rem;
        }

        /* --- Department Group Styling (Left Side Graph) --- */
        .dept-group {
            margin-bottom: 20px; /* Maintains margin between departments */
            border: 1px solid #00695c;
            border-radius: 8px;
            overflow: hidden; /* Ensures child radius doesn't leak */
            background: #fff;
        }
        
        .dept-header {
            background-color: #3a7c7c;
            color: white;
            padding: 10px 12px;
            font-weight: 600;
            font-size: 0.95rem;
        }

        /* Specific Overrides for Lists Inside Dept Group */
        .dept-group .cand-list {
            padding: 0;
            margin: 0;
            display: block; /* Removes flex gap behavior */
        }

        .dept-group .cand-list li {
            padding: 12px;
            border-bottom: 1px solid #444; /* Darker border for black background */
            margin: 0; /* No margin between items */
            border-radius: 0; /* No radius */
            background: #e0f2f1;
        }

        .dept-group .cand-list li:last-child {
            border-bottom: none;
        }

        /* Override text colors for black background to ensure readability */
        .dept-group .cand-list .name {
            color: black;
        }
        .dept-group .cand-list .cand-meta {
            color: #666;
        }
        .dept-group .cand-list .score-sub {
            color: black; /* Lighter text for black background */
        }
    </style>
</head>
<body>
    <header class="header">
      <div class="header-left">
          <a href="dashboard.php?email=<?php echo urlencode($currentEmail); ?>" class="back-link">
              <i class="fas fa-chevron-left"></i> Back
          </a>
      </div>
      <h1 class="logo">Resume Reader</h1>
      <div class="header-right">
          <a href="logout.php" class="logout">Log Out</a>
      </div>
    </header>

<main class="main">

  <section class="stats-row">
    <div class="stat-card">
      <small>Total Resumes Uploaded</small>
      <div class="stat-value"><?= number_format($totalResumes) ?></div>
      <div class="icon">📁</div>
    </div>

    <div class="stat-card">
      <small>New Applicants Today</small>
      <div class="stat-value"><?= number_format($newToday) ?></div>
      <div class="icon">👥</div>
    </div>
  </section>

  <section class="charts-row">
    <div class="card chart-card">
      <h4>Resumes by Job Position</h4>
      <canvas id="jobsChart"></canvas>
    </div>

    <div class="card chart-card">
      <h4>Resumes by Department</h4>
      <canvas id="deptsChart"></canvas>
    </div>

    <div class="card chart-card">
        <h4>Candidates Applied (Monthly)</h4>
        <canvas id="monthlyChart"></canvas>
    </div>
  </section>

  <section class="bottom-row">
    <div class="card list-card">
      <h4>Top Candidates by Job Position</h4>
      <div style="max-height: 450px; overflow-y: auto;">
        <?php if (empty($topJobCandidates)): ?>
          <div class="empty" style="text-align:center; color:#999; padding:2rem;">No candidates found.</div>
        <?php else: ?>
            <?php foreach ($topJobCandidates as $deptName => $candidates): ?>
                <div class="dept-group">
                    <div class="dept-header">
                        <?= htmlspecialchars($deptName) ?>
                    </div>
                    <ul class="cand-list">
                        <?php foreach ($candidates as $c): ?>
                            <li>
                                <div class="cand-info">
                                    <span class="name" style="font-weight:600;"><?= htmlspecialchars($c['name']) ?></span>
                                    <span class="cand-meta"><?= htmlspecialchars($c['job_name']) ?></span>
                                </div>
                                <div class="cand-score">
                                    <div class="score-main"><?= htmlspecialchars($c['score']) ?></div>
                                    <div class="score-sub">Overall Score</div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="card list-card">
      <h4>Top Candidates by Category</h4>
      <ul class="cand-list">
        <?php if (empty($topFieldCandidates)): ?>
          <li class="empty" style="text-align:center; color:#9fc2c6; padding:2rem;">No candidates found.</li>
        <?php endif; ?>

        <?php foreach ($topFieldCandidates as $c): ?>
          <li style="padding: 1rem;">
            <div class="cand-info">
              <span class="badge" style="background:#3a7c7c; color:white;"><?= htmlspecialchars($c['category']) ?> Highest</span>
              <span class="name" style="font-weight:600;"><?= htmlspecialchars($c['name']) ?></span>
              <span class="cand-meta"><?= htmlspecialchars($c['job_name']) ?></span>
            </div>
            <div class="cand-score">
              <div class="score-main"><?= htmlspecialchars($c['field_score']) ?></div>
              <div class="score-sub">Field Score</div>
              <div class="score-sub" style="font-size:0.7rem; color: #555;">Overall: <?= htmlspecialchars($c['overall']) ?></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

</main>

<script>
/* ============ SAFE CHART SETUP ============ */
let jobsChartInstance = null;
let deptsChartInstance = null;
let monthlyChartInstance = null;

/* ============ JOB POSITIONS BAR ============ */
if (jobsChartInstance) jobsChartInstance.destroy();
jobsChartInstance = new Chart(document.getElementById("jobsChart"), {
    type: "bar",
    data: {
        labels: <?= $jobLabelsJSON ?>,
        datasets: [{
            data: <?= $jobCountsJSON ?>,
            backgroundColor: "#0A8A6B"
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
    }
});

/* ============ DEPARTMENT BAR ============ */
if (deptsChartInstance) deptsChartInstance.destroy();
deptsChartInstance = new Chart(document.getElementById("deptsChart"), {
    type: "bar",
    data: {
        labels: <?= $deptLabelsJSON ?>,
        datasets: [{
            data: <?= $deptCountsJSON ?>,
            backgroundColor: "#98A3C7"
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
    }
});

/* ============ MONTHLY LINE CHART ============ */
if (monthlyChartInstance) monthlyChartInstance.destroy();
monthlyChartInstance = new Chart(document.getElementById("monthlyChart"), {
    type: "line",
    data: {
        labels: <?= $monthLabelsJSON ?>,
        datasets: [{
            label: "Total Applicants",
            data: <?= $monthCountsJSON ?>,
            borderColor: "#3a7c7c",
            backgroundColor: "rgba(58, 124, 124, 0.2)",
            borderWidth: 2,
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) { return `Applicants: ${context.raw}`; }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0 }
            }
        },
        layout: { padding: { top: 10, bottom: 10 } }
    }
});

document.querySelector('.logout').addEventListener('click', function(e) {
    if (!confirm('Are you sure you want to log out?')) { e.preventDefault(); }
});
</script>
</body>
</html>