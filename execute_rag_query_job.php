<?php
// execute_rag_query.php

include 'connection.php';
$output_html = '';
$user_query = isset($_POST['nl_query']) ? $_POST['nl_query'] : '';

if (empty($user_query)) {
    echo '';
    exit;
}

// 1. Execute Python RAG search script
$python_command = "python rag_search_job.py " . escapeshellarg($user_query);
$json_output = shell_exec($python_command);
$python_result = json_decode($json_output, true);

if ($python_result && $python_result['success']) {
    $matching_ids = $python_result['job_ids'];

    if (empty($matching_ids)) {
        $output_html = '<div class="table-row"><div class="table-cell data" style="grid-column: 1 / span 10; text-align: center;">No jobs found matching the intent of your query.</div></div>';
    } else {
        // 2. Convert ID array into a comma-separated string for the SQL WHERE IN clause
        $id_list = "'" . implode("','", $matching_ids) . "'";

        // 3. Execute the final filtered SQL query
        $sql = "SELECT jp.*, d.department_name 
                FROM job_position jp
                INNER JOIN department d ON jp.department_id = d.department_id
                WHERE jp.job_id IN ({$id_list})
                ORDER BY d.department_name ASC;";

        $result = $conn->query($sql);

        if ($result === false || $result->num_rows == 0) {
            $output_html = '<div class="table-row"><div class="table-cell data" style="grid-column: 1 / span 10; text-align: center; color: red;">Error retrieving jobs from database.</div></div>';
        } else {
            // 4. Generate HTML rows (Reuse your jobPosition.php row logic)
            while ($row = $result->fetch_assoc()) {
                // NOTE: Use your existing complex $job_rows_html generation logic here
                $output_html .= '
                    <div class="table-row">
                        <div class="table-cell data">' . htmlspecialchars($row["department_name"]) . '</div>
                        <div class="table-cell data">' . htmlspecialchars($row["job_name"]) . '</div>
                        <div class="table-cell description data">' . htmlspecialchars($row["description"]) . '</div>
                    <div class="table-cell education data">' . htmlspecialchars($row["education"]) . '</div>
                    <div class="table-cell skills data">' . htmlspecialchars($row["skills"]) . '</div>
                    <div class="table-cell experience data">' . htmlspecialchars($row["experience"]) . '</div>
                    <div class="table-cell language data">' . htmlspecialchars($row["language"]) . '</div>
                    <div class="table-cell others data">' . htmlspecialchars($row["others"]) . '</div>
                        <div class="table-cell action data">
                            <button class="edit-btn" data-id="' . $row["job_id"] . '" data-dept-id="' . $row["department_id"] . '">Edit</button>
                            <button class="delete-btn" data-id="' . $row["job_id"] . '">Delete</button>
                        </div>
                    </div>';
            }
        }
    }
} else {
    $error_msg = $python_result['error'] ?? "AI Processing Failed.";
    $output_html = '<div class="table-row"><div class="table-cell data" style="grid-column: 1 / span 10; text-align: center; color: orange;">RAG Error: ' . htmlspecialchars($error_msg) . '</div></div>';
}

echo $output_html;
$conn->close();
