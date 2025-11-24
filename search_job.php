<?php
// Include the database connection
include 'connection.php';

// Check if the search term is provided via POST
if (isset($_POST['search_term'])) {
    // Sanitize the user input to prevent SQL injection
    $search_term = "%" . $conn->real_escape_string($_POST['search_term']) . "%";
    
    // SQL Query to fetch departments matching the search term
    // We use LIKE and the % wildcard for partial matching
    $sql = "SELECT 
                jp.*, 
                d.department_name 
            FROM 
                job_position jp
            INNER JOIN 
                department d ON jp.department_id = d.department_id
            WHERE 
                jp.job_name LIKE ? 
                OR d.department_name LIKE ?
                OR jp.description LIKE ?
                OR jp.education LIKE ?
                OR jp.skills LIKE ?
                OR jp.experience LIKE ?
                OR jp.language LIKE ?
                OR jp.others LIKE ?
            ORDER BY 
                d.department_name ASC, jp.job_name ASC;";

    // Use prepared statements for better security
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", 
    $search_term, 
    $search_term, 
    $search_term, 
    $search_term, 
    $search_term, 
    $search_term, 
    $search_term, 
    $search_term
    ); // a lot string parameters
    $stmt->execute();
    $result = $stmt->get_result();

    $job_rows_html = '';

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Generate the HTML row for each found department
            $job_rows_html .= '
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
                    <button class="edit-btn" data-id="' . $row["job_id"] . '"data-dept-id="' . $row["department_id"] . '"><i class="fas fa-edit"></i> Edit</button>
                    <button class="delete-btn" data-id="' . $row["job_id"] . '"><i class="fas fa-trash-alt"></i> Delete</button>
                    </div>
                </div>';
        }
    } else {
        // HTML to display if no results are found
        $job_rows_html = '
            <div class="table-row no-data">
                <div class="table-cell data" style="grid-column: 1 / span 9; justify-self: center;">No jobs matched your search criteria.</div>
            </div>';
    }

    // Echo the generated HTML back to the AJAX request
    echo $job_rows_html;

    $stmt->close();
}

$conn->close();
?>