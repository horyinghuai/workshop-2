<?php
// execute_rag_query_candidate.php

include 'connection.php';
$output_html = '';
$user_query = isset($_POST['nl_query']) ? $_POST['nl_query'] : '';

if (empty($user_query)) {
    echo '';
    exit;
}

// 1. Execute Python RAG search script for Candidates
$python_command = "python rag_search_candidate.py " . escapeshellarg($user_query);
$json_output = shell_exec($python_command);
$python_result = json_decode($json_output, true);

if ($python_result && $python_result['success']) {
    $matching_ids = $python_result['ids'];

    if (empty($matching_ids)) {
        $output_html = '<tr><td colspan="17" style="text-align: center; padding: 2rem;">No candidates found matching the intent of your query.</td></tr>';
    } else {
        // 2. Convert ID array into string for SQL
        $id_list = implode(",", array_map('intval', $matching_ids));

        // 3. Execute filtered SQL query
        // Note: We use FIELD() in ORDER BY to preserve the relevance ranking from the AI
        $sql = "SELECT * FROM candidate 
                WHERE candidate_id IN ({$id_list})
                ORDER BY FIELD(candidate_id, {$id_list})";

        $result = $conn->query($sql);

        if ($result === false || $result->num_rows == 0) {
            $output_html = '<tr><td colspan="17" style="text-align: center; color: red;">Error retrieving candidates from database.</td></tr>';
        } else {
            // 4. Generate HTML rows (Matches candidateScoring.php structure)
            while ($row = $result->fetch_assoc()) {
                $id = htmlspecialchars($row['candidate_id']);
                $name = htmlspecialchars($row['name']);
                $job = htmlspecialchars($row['applied_job_position'] ?? '');
                $dept = htmlspecialchars($row['department'] ?? '');
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
                        <td>" . htmlspecialchars($row['overall_score'] ?? '') . "</td>
                        <td>" . htmlspecialchars($row['education_score'] ?? '') . "</td>
                        <td>" . htmlspecialchars($row['skills_score'] ?? '') . "</td>
                        <td>" . htmlspecialchars($row['experience_score'] ?? '') . "</td>
                        <td>" . htmlspecialchars($row['language_score'] ?? '') . "</td>
                        <td>" . htmlspecialchars($row['others_score'] ?? '') . "</td>
                        <td>
                            <button class='status-btn' data-candidate-id='{$id}' data-current-status='{$status}'>{$status}</button>
                        </td>
                        <td>{$outreachHtml}</td>
                        <td><button class='btn-original' data-src='" . htmlspecialchars($row['resume_original'] ?? '') . "'>Original Resume</button></td>
                        <td><button class='btn-formatted' data-id='{$id}'>Formatted Resume</button></td>
                        <td><button class='btn-report' data-candidate-id='{$id}'>Report</button></td>
                        <td>" . htmlspecialchars($row['staff_in_charge'] ?? '') . "</td>
                    </tr>";
            }
        }
    }
} else {
    $error_msg = $python_result['error'] ?? "AI Processing Failed.";
    $output_html = '<tr><td colspan="17" style="text-align: center; color: orange;">RAG Error: ' . htmlspecialchars($error_msg) . '</td></tr>';
}

echo $output_html;
$conn->close();
?>