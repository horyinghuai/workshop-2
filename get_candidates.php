<?php
include 'connection.php';

header('Content-Type: application/json');

$where_clauses = [];
$params = [];
$param_types = '';

/* ---------------------------------------------------------------------------
   STATUS FILTER
--------------------------------------------------------------------------- */
if (!empty($_GET['status']) && is_array($_GET['status'])) {
    $statuses = $_GET['status'];
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $where_clauses[] = "c.status IN ($placeholders)";
    foreach ($statuses as $s) {
        $params[] = $s;
        $param_types .= 's';
    }
}

/* ---------------------------------------------------------------------------
   JOB POSITION FILTER
--------------------------------------------------------------------------- */
if (!empty($_GET['job_position']) && is_array($_GET['job_position'])) {
    $jobs = $_GET['job_position'];
    $placeholders = implode(',', array_fill(0, count($jobs), '?'));
    $where_clauses[] = "jp.job_name IN ($placeholders)";
    foreach ($jobs as $j) {
        $params[] = $j;
        $param_types .= 's';
    }
}

/* ---------------------------------------------------------------------------
   DEPARTMENT FILTER
--------------------------------------------------------------------------- */
if (!empty($_GET['department']) && is_array($_GET['department'])) {
    $depts = $_GET['department'];
    $placeholders = implode(',', array_fill(0, count($depts), '?'));
    $where_clauses[] = "d.department_name IN ($placeholders)";
    foreach ($depts as $dpt) {
        $params[] = $dpt;
        $param_types .= 's';
    }
}

/* ---------------------------------------------------------------------------
   SEARCH FILTER
--------------------------------------------------------------------------- */
if (!empty($_GET['search'])) {
    $search = '%' . trim($_GET['search']) . '%';
    $where_clauses[] = "(c.name LIKE ? OR c.contact_number LIKE ? OR jp.job_name LIKE ? OR d.department_name LIKE ? OR c.status LIKE ? OR c.applied_date LIKE ? OR c.outreach LIKE ? OR u.name LIKE ?)";
    $params[] = $search; $params[] = $search; $params[] = $search; $params[] = $search;
    $params[] = $search; $params[] = $search; $params[] = $search; $params[] = $search;
    $param_types .= 'ssssssss';
}

/* ---------------------------------------------------------------------------
   SORTING
--------------------------------------------------------------------------- */
$order_by = 'c.applied_date DESC'; 
if (!empty($_GET['sort_by'])) {
    switch ($_GET['sort_by']) {
        case 'Education': $order_by = 'r.score_education DESC'; break;
        case 'Skills': $order_by = 'r.score_skills DESC'; break;
        case 'Experience': $order_by = 'r.score_experience DESC'; break;
        case 'Language': $order_by = 'r.score_language DESC'; break;
        default: $order_by = 'r.score_overall DESC';
    }
}

/* ---------------------------------------------------------------------------
   SQL QUERY
--------------------------------------------------------------------------- */
$sql = "
SELECT 
    c.candidate_id AS id,
    c.name,
    c.gender,
    c.email,
    c.contact_number,
    c.address,
    /* Resume Text Fields */
    c.objective,
    c.education,
    c.skills,
    c.experience,
    c.language,
    c.others,
    
    jp.job_name AS applied_job_position,
    d.department_name AS department,
    c.applied_date,
    c.status,
    c.outreach AS outreach_status,
    
    r.score_overall AS overall_score,
    r.score_education AS education_score,
    r.score_skills AS skills_score,
    r.score_experience AS experience_score,
    r.score_language AS language_score,
    r.score_others AS others_score,

    c.resume_original,
    c.resume_formatted,
    u.name AS staff_in_charge

FROM candidate c
JOIN job_position jp ON c.job_id = jp.job_id
JOIN department d ON jp.department_id = d.department_id
LEFT JOIN report r ON r.candidate_id = c.candidate_id
LEFT JOIN user u ON c.email_user = u.email
";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY $order_by";

$candidates = [];

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
    error_log("SQL ERROR: " . $conn->error);
}

$conn->close();
echo json_encode($candidates);
?>