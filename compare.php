<?php
session_start();
include 'connection.php';

if (!isset($_GET['ids']) || empty($_GET['ids'])) {
    die("No candidates selected.");
}
if (!isset($_GET['email'])) {
    die("Unauthorized.");
}

$currentEmail = $_GET['email'];
$ids_raw = explode(',', $_GET['ids']);
$ids = array_map('intval', $ids_raw);

if (count($ids) < 2 || count($ids) > 3) {
    die("Please select 2 or 3 candidates.");
}

// Fetch Candidates
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "SELECT c.*, jp.job_name, r.score_overall, r.score_education, r.score_skills, r.score_experience, r.ai_confidence_level 
        FROM candidate c
        LEFT JOIN job_position jp ON c.job_id = jp.job_id
        LEFT JOIN report r ON c.candidate_id = r.candidate_id
        WHERE c.candidate_id IN ($placeholders)";

$stmt = $conn->prepare($sql);
$stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
$stmt->execute();
$result = $stmt->get_result();
$candidates = [];
while ($row = $result->fetch_assoc()) {
    $candidates[] = $row;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resume Reader | Comparison Matrix</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f6; margin: 0; padding-bottom: 50px; }
        .header { background: #3a7c7c; padding: 1.25rem 2rem; display: flex; align-items: center; color: white; margin-bottom: 30px; }
        .back-link { color: white; text-decoration: none; font-size: 1.5rem; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .back-link:hover { text-decoration: underline; }
        .header h1 { flex-grow: 1; text-align: center; margin: 0; font-size: 1.75rem; }
        .header .logout-link {position: absolute; right: 2rem; font-size: 1.5rem; text-decoration: none; color: white;}
        .logout-link:hover {text-decoration: underline;}
        
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        
        /* Matrix Table */
        .matrix-table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .matrix-table th, .matrix-table td { padding: 20px; text-align: left; border-bottom: 1px solid #929292ff; vertical-align: top; }
        
        .matrix-table th { background: #2F3E46; color: white; text-transform: uppercase; font-size: 0.9rem; letter-spacing: 1px; width: 20%; text-align: center; vertical-align: middle;}
        .matrix-table td { width: <?php echo 80 / count($candidates); ?>%; line-height: 1.6; color: #333; border-left: 2px solid #929292ff; vertical-align: middle;}

        .cand-header { text-align: center; }
        .cand-name { font-size: 1.4rem; font-weight: 700; color: #3a7c7c; margin-bottom: 5px; }
        .cand-role { font-size: 0.95rem; color: #666; font-weight: 600; }
        
        .score-badge { display: inline-block; background: #3a7c7c; color: white; padding: 5px 12px; border-radius: 15px; font-weight: bold; font-size: 0.9rem; }
        .sub-score { display: flex; justify-content: space-between; border-bottom: 1px dashed #ddd; padding: 8px 0; font-size: 0.95rem; }
        
        /* AI Section */
        .ai-section { margin-top: 40px; background: white; border-radius: 12px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 2px solid #3a7c7c; position: relative; }
        .ai-header { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
        .ai-icon { font-size: 2rem; color: #3a7c7c; }
        .ai-title { font-size: 1.5rem; font-weight: 700; color: #2F3E46; }
        
        .ai-content { white-space: pre-wrap; color: #444; line-height: 1.7; min-height: 100px; }
        
        .loading-pulse { display: flex; align-items: center; gap: 10px; color: #666; font-style: italic; }
        .spinner { border: 3px solid #f3f3f3; border-top: 3px solid #3a7c7c; border-radius: 50%; width: 20px; height: 20px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

<div class="header">
    <a href="candidateScoring.php?email=<?php echo urlencode($currentEmail); ?>" class="back-link">
        <i class="fas fa-chevron-left"></i> Back
    </a>
    <h1>Comparison Matrix</h1>
    <a href="logout.php" class="logout-link">Log Out</a>
    <div style="width: 80px;"></div> </div>

<div class="container">
    <table class="matrix-table">
        <tr style="background: #f0f7f7;">
            <th>Candidate</th>
            <?php foreach ($candidates as $c): ?>
                <td class="cand-header">
                    <div class="cand-name"><?php echo htmlspecialchars($c['name']); ?></div>
                    <div class="cand-role"><?php echo htmlspecialchars($c['job_name']); ?></div>
                </td>
            <?php endforeach; ?>
        </tr>

        <tr>
            <th>Scores</th>
            <?php foreach ($candidates as $c): ?>
                <td>
                    <div style="margin-bottom:15px; text-align:center;">
                        <span class="score-badge">Overall: <?php echo $c['score_overall']; ?></span>
                    </div>
                    <div class="sub-score"><span>Education</span> <b><?php echo $c['score_education']; ?></b></div>
                    <div class="sub-score"><span>Skills</span> <b><?php echo $c['score_skills']; ?></b></div>
                    <div class="sub-score"><span>Experience</span> <b><?php echo $c['score_experience']; ?></b></div>
                    <div class="sub-score" style="border:none;"><span>AI Confidence</span> <b><?php echo $c['ai_confidence_level']; ?>%</b></div>
                </td>
            <?php endforeach; ?>
        </tr>

        <tr>
            <th>Education</th>
            <?php foreach ($candidates as $c): ?>
                <td><?php echo nl2br(htmlspecialchars(substr($c['education'], 0, 300))) . (strlen($c['education'])>300 ? '...' : ''); ?></td>
            <?php endforeach; ?>
        </tr>
        <tr>
            <th>Skills</th>
            <?php foreach ($candidates as $c): ?>
                <td><?php echo nl2br(htmlspecialchars(substr($c['skills'], 0, 300))) . (strlen($c['skills'])>300 ? '...' : ''); ?></td>
            <?php endforeach; ?>
        </tr>
        <tr>
            <th>Experience</th>
            <?php foreach ($candidates as $c): ?>
                <td><?php echo nl2br(htmlspecialchars(substr($c['experience'], 0, 400))) . (strlen($c['experience'])>400 ? '...' : ''); ?></td>
            <?php endforeach; ?>
        </tr>
    </table>

    <div class="ai-section">
        <div class="ai-header">
            <i class="fas fa-robot ai-icon"></i>
            <div class="ai-title">AI Comparative Analysis</div>
        </div>
        <div id="aiResult" class="ai-content">
            <div class="loading-pulse">
                <div class="spinner"></div>
                <span>Gemini is analyzing and comparing candidates...</span>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ids = "<?php echo implode(',', $ids); ?>";
    
    const formData = new FormData();
    formData.append('ids', ids);

    fetch('compare_ai.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        const resultDiv = document.getElementById('aiResult');
        if (data.status === 'success') {
            // Convert simple markdown-like bolding to HTML
            let html = data.analysis.replace(/\*\*(.*?)\*\*/g, '<b>$1</b>');
            html = html.replace(/\n/g, '<br>');
            resultDiv.innerHTML = html;
        } else {
            resultDiv.innerHTML = `<span style="color:red;">Error: ${data.message}</span>`;
        }
    })
    .catch(err => {
        document.getElementById('aiResult').innerHTML = `<span style="color:red;">Network Error. Please try again later.</span>`;
    });
});
</script>

</body>
</html>