<?php
include 'connection.php';

header('Content-Type: application/json');

// --- 1. Capture Parameters ---
$status = isset($_GET['status']) ? $_GET['status'] : [];
$job_positions = isset($_GET['job_position']) ? $_GET['job_position'] : [];
$departments = isset($_GET['department']) ? $_GET['department'] : [];
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$is_archived = isset($_GET['is_archived']) ? intval($_GET['is_archived']) : 0;

// New Filters
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : 0;
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : 0;

// Sorting
$sort_column = isset($_GET['sort_column']) ? $_GET['sort_column'] : 'name';
$sort_order = isset($_GET['sort_order']) && strtoupper($_GET['sort_order']) === 'DESC' ? 'DESC' : 'ASC';

// Initialize ID arrays
$rag_ids = [];
$keyword_ids = [];
$final_ids = [];
$search_active = !empty($search);

// --- 2. Perform RAG & Keyword Search (If search is active) ---
if ($search_active) {
    $search_term = "%{$search}%";
    $keyword_sql = "
        SELECT c.candidate_id 
        FROM candidate c
        LEFT JOIN job_position jp ON c.job_id = jp.job_id
        LEFT JOIN department d ON jp.department_id = d.department_id
        WHERE 
            c.name LIKE ? OR 
            c.email LIKE ? OR 
            c.contact_number LIKE ? OR 
            c.skills LIKE ? OR 
            jp.job_name LIKE ? OR 
            d.department_name LIKE ?
    ";

    if ($stmt = $conn->prepare($keyword_sql)) {
        $stmt->bind_param("ssssss", $search_term, $search_term, $search_term, $search_term, $search_term, $search_term);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $keyword_ids[] = $row['candidate_id'];
        }
        $stmt->close();
    }

    // C. Merge Results
    $final_ids = array_unique(array_merge($rag_ids, $keyword_ids));

    // If search was run but found nothing, return empty immediately
    if (empty($final_ids)) {
        echo json_encode([]);
        $conn->close();
        exit;
    }
}

// --- 3. Build Main SQL Query ---
$sql = "SELECT c.*, 
        jp.job_name as applied_job_position, 
        d.department_name as department, 
        c.resume_formatted, 
        c.resume_original,
        c.outreach as outreach_status, 
        r.score_overall, r.score_education, r.score_skills, r.score_experience, r.score_language, r.score_others,
        u.name as staff_in_charge
        FROM candidate c 
        LEFT JOIN job_position jp ON c.job_id = jp.job_id 
        LEFT JOIN department d ON jp.department_id = d.department_id 
        LEFT JOIN report r ON c.candidate_id = r.candidate_id
        LEFT JOIN user u ON c.email_user = u.email
        WHERE c.is_archived = $is_archived"; 

// --- 4. Apply Filters ---

// A. Search ID Filter
if ($search_active && !empty($final_ids)) {
    $id_list = implode(",", array_map('intval', $final_ids));
    $sql .= " AND c.candidate_id IN ($id_list)";
}

// B. Standard Filters
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

// C. Year/Month Filters
if ($filter_year > 0) {
    $sql .= " AND YEAR(c.applied_date) = $filter_year";
}
if ($filter_month > 0) {
    $sql .= " AND MONTH(c.applied_date) = $filter_month";
}

// --- 5. Sorting Logic ---
$orderBy = "";

// Map sort_column to SQL field
$sortField = "c.name"; // Default
switch ($sort_column) {
    case 'name': $sortField = "c.name"; break;
    case 'job_position': $sortField = "jp.job_name"; break;
    case 'department': $sortField = "d.department_name"; break;
    case 'applied_date': $sortField = "c.applied_date"; break;
    case 'score_overall': $sortField = "r.score_overall"; break;
    case 'score_education': $sortField = "r.score_education"; break;
    case 'score_skills': $sortField = "r.score_skills"; break;
    case 'score_experience': $sortField = "r.score_experience"; break;
    case 'score_language': $sortField = "r.score_language"; break;
    case 'score_others': $sortField = "r.score_others"; break;
    default: $sortField = "c.name"; break;
}

// Apply Sort
$orderBy = "ORDER BY $sortField $sort_order";

// NOTE: If user explicitly clicked a header, we prioritize that sort over the 'search relevance' logic.
// If you want search relevance to persist UNLESS a sort is triggered, checking if 'sort_column' was passed from defaults vs user action is needed.
// Here, we assume if the table is loaded, it sorts by Name ASC (default) or whatever the user clicks.
// RAG relevance sorting is usually only desired if the user JUST searched and didn't click a column yet.
// However, the current logic in candidateScoring.php defaults 'sort_column' to 'name' on load.
// If you want to keep RAG relevance on search, you would need to handle "no sort param" logic.
// For now, consistent column sorting is usually preferred in tables.

$sql .= " " . $orderBy;

// --- 6. Execute & Return ---
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