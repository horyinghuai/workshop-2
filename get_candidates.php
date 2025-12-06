<?php
include 'connection.php';

header('Content-Type: application/json');

// Filters
$status = isset($_GET['status']) ? $_GET['status'] : [];
$job_positions = isset($_GET['job_position']) ? $_GET['job_position'] : [];
$departments = isset($_GET['department']) ? $_GET['department'] : [];
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'All';

// New Filter: Archive status (Default 0: Active)
$is_archived = isset($_GET['is_archived']) ? intval($_GET['is_archived']) : 0;

// FIXED SQL QUERY with updated joins and column names
$sql = "SELECT c.*, 
        jp.job_name as applied_job_position, 
        d.department_name as department, 
        r.score_overall, r.score_education, r.score_skills, r.score_experience, r.score_language, r.score_others,
        u.name as staff_in_charge
        FROM candidate c 
        LEFT JOIN job_position jp ON c.job_id = jp.job_id 
        LEFT JOIN department d ON jp.department_id = d.department_id 
        LEFT JOIN report r ON c.candidate_id = r.candidate_id
        LEFT JOIN user u ON c.email_user = u.email
        WHERE c.is_archived = $is_archived"; 

// --- Apply Filters ---
if (!empty($status)) {
    $statusList = "'" . implode("','", array_map([$conn, 'real_escape_string'], $status)) . "'";
    $sql .= " AND c.status IN ($statusList)";
}

if (!empty($job_positions)) {
    $jobList = "'" . implode("','", array_map([$conn, 'real_escape_string'], $job_positions)) . "'";
    $sql .= " AND jp.job_name IN ($jobList)";
}

if (!empty($departments)) {
    $deptList = "'" . implode("','", array_map([$conn, 'real_escape_string'], $departments)) . "'";
    $sql .= " AND d.department_name IN ($deptList)";
}

if ($search) {
    $s = $conn->real_escape_string($search);
    $sql .= " AND (c.name LIKE '%$s%' OR c.email LIKE '%$s%')";
}

// --- Sorting ---
$orderBy = "ORDER BY c.applied_date DESC"; // Default
if ($sort_by == 'Education') $orderBy = "ORDER BY r.score_education DESC";
elseif ($sort_by == 'Skills') $orderBy = "ORDER BY r.score_skills DESC";
elseif ($sort_by == 'Experience') $orderBy = "ORDER BY r.score_experience DESC";
elseif ($sort_by == 'Language') $orderBy = "ORDER BY r.score_language DESC";

$sql .= " " . $orderBy;

$result = $conn->query($sql);

$candidates = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        $candidates[] = $row;
    }
}

echo json_encode($candidates);
$conn->close();
?>