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
        $where_clauses[] = "status IN ($placeholders)";
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
        $where_clauses[] = "applied_job_position IN ($placeholders)";
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
        $where_clauses[] = "department IN ($placeholders)";
        foreach ($departments as $dept) {
            $params[] = $dept;
            $param_types .= 's';
        }
    }
}

// --- Search Query ---
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = '%' . trim($_GET['search']) . '%';
    $where_clauses[] = "(name LIKE ? OR contact_number LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= 'ss';
}

// --- Dropdown Filter (Sorting) ---
$order_by = 'applied_date DESC'; // Default sort
if (isset($_GET['sort_by']) && !empty($_GET['sort_by'])) {
    $sort_by = $_GET['sort_by'];
    switch ($sort_by) {
        case 'Education':
            $order_by = 'education_score DESC';
            break;
        case 'Skills':
            $order_by = 'skills_score DESC';
            break;
        case 'Experience':
            $order_by = 'experience_score DESC';
            break;
        case 'Achievements':
            $order_by = 'achievements_score DESC';
            break;
        case 'Language':
            $order_by = 'language_score DESC';
            break;
        case 'All': // Fall through to default overall_score
        default:
            $order_by = 'overall_score DESC'; // Sort by overall score for 'All'
            break;
    }
}


$sql = "SELECT id, name, contact_number, applied_job_position, department, applied_date, 
               overall_score, education_score, skills_score, experience_score, 
               achievements_score, language_score, status 
        FROM candidate";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY $order_by";

$candidates = [];

if ($stmt = $mysqli->prepare($sql)) {
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
    error_log("Error preparing statement: " . $mysqli->error);
}

$mysqli->close();

echo json_encode($candidates);
?>