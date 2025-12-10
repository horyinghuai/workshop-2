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

// Dynamic Sorting Parameters (Arrays)
$sort_cols = isset($_GET['sort_cols']) ? $_GET['sort_cols'] : [];
$sort_orders = isset($_GET['sort_orders']) ? $_GET['sort_orders'] : [];

// Initialize ID arrays
$rag_ids = [];
$keyword_ids = [];
$final_ids = [];
$search_active = !empty($search);

// --- 2. Perform RAG & Keyword Search (If search is active) ---
if ($search_active) {
    // A. AI (RAG) Search
    $python_command = "python rag_search_candidate.py " . escapeshellarg($search);
    $json_output = shell_exec($python_command);
    $python_result = json_decode($json_output, true);

    if ($python_result && isset($python_result['success']) && $python_result['success']) {
        $rag_ids = $python_result['ids'];
    }

    // B. Keyword Fallback Search (SQL LIKE)
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

if ($search_active && !empty($final_ids)) {
    $id_list = implode(",", array_map('intval', $final_ids));
    $sql .= " AND c.candidate_id IN ($id_list)";
}

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

if ($filter_year > 0) {
    $sql .= " AND YEAR(c.applied_date) = $filter_year";
}
if ($filter_month > 0) {
    $sql .= " AND MONTH(c.applied_date) = $filter_month";
}

// --- 5. Sorting Logic (Dynamic Multi-Level) ---

// Helper function to map frontend columns to SQL fields
function mapSortColumn($col) {
    switch ($col) {
        case 'name': return "c.name";
        case 'job_position': return "jp.job_name";
        case 'department': return "d.department_name";
        case 'applied_date': return "c.applied_date";
        case 'score_overall': return "r.score_overall";
        case 'score_education': return "r.score_education";
        case 'score_skills': return "r.score_skills";
        case 'score_experience': return "r.score_experience";
        case 'score_language': return "r.score_language";
        case 'score_others': return "r.score_others";
        default: return "c.name";
    }
}

$orderByClauses = [];

// Handle dynamic sort arrays
if (!empty($sort_cols) && is_array($sort_cols)) {
    foreach ($sort_cols as $index => $col) {
        if (empty($col)) continue;
        $field = mapSortColumn($col);
        
        // Get corresponding order, default to ASC if missing
        $order = (isset($sort_orders[$index]) && strtoupper($sort_orders[$index]) === 'DESC') ? 'DESC' : 'ASC';
        
        $orderByClauses[] = "$field $order";
    }
}

// Fallback if no valid sort provided
if (empty($orderByClauses)) {
    $orderByClauses[] = "c.name ASC";
}

$sql .= " ORDER BY " . implode(", ", $orderByClauses);

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