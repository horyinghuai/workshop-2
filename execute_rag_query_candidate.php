<?php
// execute_rag_query_candidate.php

include 'connection.php';
$output_html = '';
$user_query = isset($_POST['nl_query']) ? trim($_POST['nl_query']) : '';

if (empty($user_query)) {
    echo '';
    exit;
}

// ---------------------------------------------------------
// 1. Perform AI (RAG) Search
// ---------------------------------------------------------
$rag_ids = [];
$python_command = "python rag_search_candidate.py " . escapeshellarg($user_query);
$json_output = shell_exec($python_command);
$python_result = json_decode($json_output, true);

if ($python_result && $python_result['success']) {
    $rag_ids = $python_result['ids']; // IDs from Semantic Search
}

// ---------------------------------------------------------
// 2. Perform Keyword Search (Fallback to find Exact Matches)
//    This ensures "everything" is found even if AI score is low.
// ---------------------------------------------------------
$keyword_ids = [];
$search_term = "%{$user_query}%";

// Simple keyword match across key text fields
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

// ---------------------------------------------------------
// 3. Merge Results & Fetch Full Data
// ---------------------------------------------------------
// Combine AI results + Keyword results
$final_ids = array_unique(array_merge($rag_ids, $keyword_ids));

if (empty($final_ids)) {
    $output_html = '<tr><td colspan="17" style="text-align: center; padding: 2rem;">No candidates found matching the intent of your query.</td></tr>';
} else {
    // Convert IDs to string for SQL
    $id_list_str = implode(",", array_map('intval', $final_ids));

    // ORDER BY logic: Put AI matches at the top, followed by keyword matches
    $order_by_clause = "";
    if (!empty($rag_ids)) {
        $rag_ids_str = implode(",", array_map('intval', $rag_ids));
        $order_by_clause = "FIELD(c.candidate_id, {$rag_ids_str}) DESC, ";
    }

    // MAIN QUERY: Includes JOINs to job_position, department, and report tables
    // so that ALL columns (Job, Dept, Scores) are populated.
    $sql = "
        SELECT 
            c.candidate_id,
            c.name,
            c.gender,
            c.email,
            c.contact_number,
            c.address,
            c.applied_date,
            c.status,
            c.outreach AS outreach_status,
            c.resume_original,
            c.resume_formatted,
            jp.job_name AS applied_job_position,
            d.department_name AS department,
            r.score_overall,
            r.score_education,
            r.score_skills,
            r.score_experience,
            r.score_language,
            r.score_others,
            u.name AS staff_in_charge
        FROM candidate c
        JOIN job_position jp ON c.job_id = jp.job_id
        JOIN department d ON jp.department_id = d.department_id
        LEFT JOIN report r ON r.candidate_id = c.candidate_id
        LEFT JOIN user u ON c.email_user = u.email
        WHERE c.candidate_id IN ({$id_list_str})
        ORDER BY {$order_by_clause} c.applied_date DESC
    ";

    $result = $conn->query($sql);

    if ($result === false || $result->num_rows == 0) {
        $output_html = '<tr><td colspan="17" style="text-align: center; color: red;">Error retrieving candidates from database.</td></tr>';
    } else {
        // Generate HTML Rows
        while ($row = $result->fetch_assoc()) {
            $id = htmlspecialchars($row['candidate_id']);
            $name = htmlspecialchars($row['name']);
            $job = htmlspecialchars($row['applied_job_position'] ?? 'N/A');
            $dept = htmlspecialchars($row['department'] ?? 'N/A');
            $date = htmlspecialchars($row['applied_date'] ?? '');
            $status = htmlspecialchars($row['status'] ?? 'Active');
            
            // Outreach Logic
            $outreachStatus = $row['outreach_status'] ?? null;
            $outreachHtml = '';
            if (!$outreachStatus) {
                $outreachHtml = "
                    <div style='display:flex; gap:5px;' id='outreach-btns-{$id}'>
                        <button class='btn-accept' title='Schedule Interview' onclick=\"openOutreach('{$id}', '', 'accept')\"><i class='fas fa-check'></i></button>
                        <button class='btn-reject' title='Reject Candidate' onclick=\"openOutreach('{$id}', '', 'reject')\"><i class='fas fa-times'></i></button>
                    </div>
                    <span id='outreach-label-{$id}' style='display:none; font-weight:600; font-size:0.9rem;'></span>";
            } else {
                $color = 'color: #333;';
                if (strpos($outreachStatus, 'Scheduled') !== false) $color = 'color: #28a745;';
                else if (strpos($outreachStatus, 'Rejected') !== false) $color = 'color: #dc3545;';
                $outreachHtml = "<span style='font-weight:600; font-size:0.9rem; {$color}'>" . htmlspecialchars($outreachStatus) . "</span>";
            }

            $output_html .= "
                <tr>
                    <td><input type='checkbox' name='candidate_check' value='{$id}'></td>
                    <td><span class='clickable-name' onclick='openEditCandidate({$id})'>{$name}</span></td>
                    <td>{$job}</td>
                    <td>{$dept}</td>
                    <td>{$date}</td>
                    <td>" . htmlspecialchars($row['score_overall'] ?? '-') . "</td>
                    <td>" . htmlspecialchars($row['score_education'] ?? '-') . "</td>
                    <td>" . htmlspecialchars($row['score_skills'] ?? '-') . "</td>
                    <td>" . htmlspecialchars($row['score_experience'] ?? '-') . "</td>
                    <td>" . htmlspecialchars($row['score_language'] ?? '-') . "</td>
                    <td>" . htmlspecialchars($row['score_others'] ?? '-') . "</td>
                    <td>
                        <button class='status-btn' data-candidate-id='{$id}' data-current-status='{$status}'>{$status}</button>
                    </td>
                    <td>{$outreachHtml}</td>
                    <td><button class='btn-original' data-src='" . htmlspecialchars($row['resume_original'] ?? '') . "'>Original Resume</button></td>
                    <td><button class='btn-formatted' data-id='{$id}'>Formatted Resume</button></td>
                    <td><button class='btn-report' data-candidate-id='{$id}'>Report</button></td>
                    <td>" . htmlspecialchars($row['staff_in_charge'] ?? '-') . "</td>
                </tr>";
        }
    }
}

echo $output_html;
$conn->close();
?>