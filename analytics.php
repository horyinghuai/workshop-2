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

/* ================= AI CONFIDENCE STATS ================= */
$high = $mid = $low = 0;

$confQuery = $conn->query("SELECT ai_confidence_level FROM REPORT WHERE ai_confidence_level IS NOT NULL");

while ($row = $confQuery->fetch_assoc()) {
    $value = (float)$row['ai_confidence_level'];

    if ($value >= 75) $high++;
    else if ($value >= 40) $mid++;
    else $low++;
}

/* ================= TOP CANDIDATES FUNCTION ================= */
function topCandidatesFor($role, $conn) {
    $list = [];

    $stmt = $conn->prepare("
        SELECT c.name, j.job_name, r.score_overall, r.ai_confidence_level
        FROM CANDIDATE c
        JOIN JOB_POSITION j ON c.job_id = j.job_id
        JOIN REPORT r ON r.candidate_id = c.candidate_id
        WHERE j.job_name = ?
        ORDER BY r.ai_confidence_level DESC, r.score_overall DESC
        LIMIT 3
    ");

    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $list[] = $row;
    }

    $stmt->close();
    return $list;
}

$topDataAnalyst = topCandidatesFor("Data Analyst", $conn);
$topHRAssistant = topCandidatesFor("HR Assistant", $conn);

/* ================= JSON FOR CHARTS ================= */
$jobLabelsJSON  = json_encode($jobLabels);
$jobCountsJSON  = json_encode($jobCounts);

$deptLabelsJSON = json_encode($deptLabels);
$deptCountsJSON = json_encode($deptCounts);

$confJSON       = json_encode([$high, $mid, $low]);

// Close DB
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
    <h4>AI Confidence Level Distribution</h4>
    <canvas id="confChart"></canvas>
    
</div>
  </section>

  <section class="bottom-row">
    <div class="card list-card">
      <h4>Top Candidates: Data Analyst</h4>
      <ul class="cand-list">
        <?php if (empty($topDataAnalyst)): ?>
          <li class="empty">No candidates</li>
        <?php endif; ?>

        <?php foreach ($topDataAnalyst as $c): ?>
          <li>
            <div>
              <div class="name"><?= htmlspecialchars($c['name']) ?></div>
              <div class="muted"><?= htmlspecialchars($c['job_name']) ?></div>
            </div>
            <div class="score">
              <div>Score: <?= htmlspecialchars($c['score_overall']) ?></div>
              <div class="confidence">AI Confidence: <?= htmlspecialchars(number_format($c['ai_confidence_level'], 1)) ?>%</div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="card list-card">
      <h4>Top Candidates: HR Assistant</h4>
      <ul class="cand-list">
        <?php if (empty($topHRAssistant)): ?>
          <li class="empty">No candidates</li>
        <?php endif; ?>

        <?php foreach ($topHRAssistant as $c): ?>
          <li>
            <div>
              <div class="name"><?= htmlspecialchars($c['name']) ?></div>
              <div class="muted"><?= htmlspecialchars($c['job_name']) ?></div>
            </div>
            <div class="score">
              <div>Score: <?= htmlspecialchars($c['score_overall']) ?></div>
              <div class="confidence">AI Confidence: <?= htmlspecialchars(number_format($c['ai_confidence_level'], 1)) ?>%</div>
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
let confChartInstance = null;

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
        plugins: {
            legend: {
                display: false
            }
        }
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
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

/* ============ CONFIDENCE PIE ============ */
if (confChartInstance) confChartInstance.destroy();
confChartInstance = new Chart(document.getElementById("confChart"), {
    type: "pie",
    data: {
        labels: ["High Confidence", "Medium Confidence", "Low Confidence"],
        datasets: [{
            data: <?= $confJSON ?>,
            backgroundColor: ["#3a7c7c", "#C4C6CC", "#CE3A3A"],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    padding: 20,
                    font: {
                        size: 14
                    }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.raw || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = Math.round((value / total) * 100);
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        },
        layout: {
            padding: {
                top: 10,
                bottom: 10
            }
        }
    }
});
// Add navigation confirmation for logout
document.querySelector('.logout').addEventListener('click', function(e) {
    if (!confirm('Are you sure you want to log out?')) {
        e.preventDefault();
    }
});
</script>
</body>
</html>