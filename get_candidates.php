<?php
include 'connection.php';

header('Content-Type: application/json');

// --- 1. Capture Parameters ---
$status = isset($_GET['status']) ? $_GET['status'] : [];
$job_positions = isset($_GET['job_position']) ? $_GET['job_position'] : [];
$departments = isset($_GET['department']) ? $_GET['department'] : [];
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'All';
$is_archived = isset($_GET['is_archived']) ? intval($_GET['is_archived']) : 0;

// Initialize ID arrays
$rag_ids = [];
$keyword_ids = [];
$final_ids = [];
$search_active = !empty($search);

// --- 2. Perform RAG & Keyword Search (If search is active) ---
if ($search_active) {
    // A. AI (RAG) Search
    // Execute the Python script and capture JSON output
    $python_command = "python rag_search_candidate.py " . escapeshellarg($search);
    $json_output = shell_exec($python_command);
    $python_result = json_decode($json_output, true);

    if ($python_result && isset($python_result['success']) && $python_result['success']) {
        $rag_ids = $python_result['ids'];
    }

    // B. Keyword Fallback Search (SQL LIKE)
    // Matches logic from execute_rag_query_candidate.php to ensure robustness
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
    // We merge them, keeping RAG results first implicitly by order of merge if we were just listing them,
    // but for SQL IN clause, order doesn't matter until the ORDER BY clause.
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
        c.resume_formatted, /* Ensure we fetch this for the frontend */
        c.resume_original,
        c.outreach as outreach_status, /* Renamed in frontend JS, fetching raw column here */
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
    // Sanitize IDs
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

// --- 5. Sorting Logic ---
$orderBy = "";

if ($search_active && !empty($final_ids) && $sort_by === 'All') {
    // If searching and no specific sort selected, prioritize Search Relevance
    // RAG IDs come first.
    // Logic: ORDER BY FIELD(candidate_id, [rag_ids...], [keyword_ids...])
    // Note: FIELD returns 0 if not found, so we list all valid IDs.
    
    // Create a prioritized list: RAG IDs first, then Keywords
    $prioritized_ids = array_unique(array_merge($rag_ids, $keyword_ids));
    $priority_str = implode(",", array_map('intval', $prioritized_ids));
    
    $orderBy = "ORDER BY FIELD(c.candidate_id, $priority_str)"; 
} else {
    // Standard Sorting
    switch ($sort_by) {
        case 'Education': $orderBy = "ORDER BY r.score_education DESC"; break;
        case 'Skills': $orderBy = "ORDER BY r.score_skills DESC"; break;
        case 'Experience': $orderBy = "ORDER BY r.score_experience DESC"; break;
        case 'Language': $orderBy = "ORDER BY r.score_language DESC"; break;
        default: $orderBy = "ORDER BY c.applied_date DESC"; break;
    }
}

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