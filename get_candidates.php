<?php
include 'connection.php'; // Include your database configuration

header('Content-Type: application/json'); // Ensure JSON response

$where_clauses = [];
$params = [];
$param_types = '';

// --- Status Filter ---
if (isset($_GET['status']) && is_array($_GET['status'])) {
    $statuses = $_GET['status'];
    if (!empty($statuses)) {
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $where_clauses[] = "candidate.status IN ($placeholders)"; // Prefixed with candidate.
        foreach ($statuses as $status) {
            $params[] = $status;
            $param_types .= 's';
        }
    }
}

// --- Job Position Filter ---
if (isset($_GET['job_position']) && is_array($_GET['job_position'])) {
    $job_positions = $_GET['job_position'];
    if (!empty($job_positions)) {
        $placeholders = implode(',', array_fill(0, count($job_positions), '?'));
        $where_clauses[] = "job_position.job_name IN ($placeholders)"; // Prefixed with job_position.
        foreach ($job_positions as $job) {
            $params[] = $job;
            $param_types .= 's';
        }
    }
}

// --- Department Filter ---
if (isset($_GET['department']) && is_array($_GET['department'])) {
    $departments = $_GET['department'];
    if (!empty($departments)) {
        $placeholders = implode(',', array_fill(0, count($departments), '?'));
        $where_clauses[] = "department.department_name IN ($placeholders)"; // Prefixed with department.
        foreach ($departments as $dept) {
            $params[] = $dept;
            $param_types .= 's';
        }
    }
}

// --- Search Query ---
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = '%' . trim($_GET['search']) . '%';
    $where_clauses[] = "(candidate.name LIKE ? OR candidate.contact_number LIKE ?)"; // Prefixed
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= 'ss';
}

// --- Dropdown Filter (Sorting) ---
$order_by = 'candidate.applied_date DESC'; // Default sort
if (isset($_GET['sort_by']) && !empty($_GET['sort_by'])) {
    $sort_by = $_GET['sort_by'];
    switch ($sort_by) {
        case 'Education':
            $order_by = 'report.score_education DESC';
            break;
        case 'Skills':
            $order_by = 'report.score_skills DESC';
            break;
        case 'Experience':
            $order_by = 'report.score_experience DESC';
            break;
        case 'Language':
            $order_by = 'report.score_language DESC';
            break;
        case 'All': // Fall through to default overall_score
        default:
            $order_by = 'report.score_overall DESC'; // Sort by overall score for 'All'
            break;
    }
}

// --- NEW SQL QUERY ---
// Selects candidate info, job/dept info, and report scores
// Assumes report table links via `report.candidate_id = candidate.id`
$sql = "SELECT 
            candidate.candidate_id, 
            candidate.name, 
            candidate.contact_number, 
            job_position.job_name AS applied_job_position, 
            department.department_name AS department, 
            candidate.applied_date,
            candidate.status,
            report.score_overall AS overall_score,
            report.score_education AS education_score,
            report.score_skills AS skills_score,
            report.score_experience AS experience_score,
            report.score_language AS language_score,
            report.score_others AS others_score
        FROM 
            candidate 
        JOIN 
            job_position ON candidate.job_id = job_position.job_id 
        JOIN 
            department ON job_position.department_id = department.department_id
        JOIN
            report ON candidate.candidate_id = report.candidate_id";


if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY $order_by";

$candidates = [];

// Use $conn, not $mysqli
if ($stmt = $conn->prepare($sql)) {
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $candidates[] = $row;
    }
    $stmt->close();
} else {
    // Log error or handle it
    error_log("Error preparing statement: " . $conn->error);
}

$conn->close();

echo json_encode($candidates);
?>