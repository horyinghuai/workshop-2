<?php
// execute_rag_query_dept.php

include 'connection.php';
$output_html = '';
$user_query = isset($_POST['nl_query']) ? $_POST['nl_query'] : '';

if (empty($user_query)) {
    echo '';
    exit;
}

// 1. Execute Python RAG search script for Departments
$python_command = "python rag_search_dept.py " . escapeshellarg($user_query);
$json_output = shell_exec($python_command);
$python_result = json_decode($json_output, true);

if ($python_result && $python_result['success']) {
    $matching_ids = $python_result['ids'];

    if (empty($matching_ids)) {
        $output_html = '<div class="table-row"><div class="table-cell data" style="grid-column: 1 / span 3; text-align: center;">No departments found matching the intent of your query.</div></div>';
    } else {
        // 2. Convert ID array into string for SQL
        $id_list = "'" . implode("','", $matching_ids) . "'";

        // 3. Execute filtered SQL query
        $sql = "SELECT * FROM department 
                WHERE department_id IN ({$id_list})
                ORDER BY department_name ASC";

        $result = $conn->query($sql);

        if ($result === false || $result->num_rows == 0) {
            $output_html = '<div class="table-row"><div class="table-cell data" style="grid-column: 1 / span 3; text-align: center; color: red;">Error retrieving departments.</div></div>';
        } else {
            // 4. Generate HTML rows (Matches jobDepartment.php structure)
            while ($row = $result->fetch_assoc()) {
                $output_html .= '
                    <div class="table-row">
                        <div class="table-cell data">' . htmlspecialchars($row["department_name"]) . '</div>
                        <div class="table-cell description data">' . htmlspecialchars($row["description"]) . '</div>
                        <div class="table-cell action data">
                            <button class="edit-btn" data-id="' . $row["department_id"] . '"><i class="fas fa-edit"></i> Edit</button>
                            <button class="delete-btn" data-id="' . $row["department_id"] . '"><i class="fas fa-trash-alt"></i> Delete</button>
                        </div>
                    </div>';
            }
        }
    }
} else {
    $error_msg = $python_result['error'] ?? "AI Processing Failed.";
    $output_html = '<div class="table-row"><div class="table-cell data" style="grid-column: 1 / span 3; text-align: center; color: orange;">RAG Error: ' . htmlspecialchars($error_msg) . '</div></div>';
}

echo $output_html;
$conn->close();
?>