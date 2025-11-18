<?php
// Include the database connection
include 'connection.php';

// Check if the search term is provided via POST
if (isset($_POST['search_term'])) {
    // Sanitize the user input to prevent SQL injection
    $search_term = "%" . $conn->real_escape_string($_POST['search_term']) . "%";
    
    // SQL Query to fetch departments matching the search term
    // We use LIKE and the % wildcard for partial matching
    $sql = "SELECT * 
            FROM department 
            WHERE department_name LIKE ? OR description LIKE ? 
            ORDER BY department_name ASC";

    // Use prepared statements for better security
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $search_term, $search_term); // "ss" means two string parameters
    $stmt->execute();
    $result = $stmt->get_result();

    $department_rows_html = '';

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Generate the HTML row for each found department
            $department_rows_html .= '
                <div class="table-row">
                    <div class="table-cell data">' . htmlspecialchars($row["department_name"]) . '</div>
                    <div class="table-cell description data">' . htmlspecialchars($row["description"]) . '</div>
                    <div class="table-cell action data">
                        <button class="edit-btn" data-id="' . $row["department_id"] . '"><i class="fas fa-edit"></i> Edit</button>
                    <button class="delete-btn" data-id="' . $row["department_id"] . '"><i class="fas fa-trash-alt"></i> Delete</button>
                    </div>
                </div>';
        }
    } else {
        // HTML to display if no results are found
        $department_rows_html = '
            <div class="table-row no-data">
                <div class="table-cell data" style="grid-column: 1 / span 3; justify-self: center;">No departments matched your search criteria.</div>
            </div>';
    }

    // Echo the generated HTML back to the AJAX request
    echo $department_rows_html;

    $stmt->close();
}

$conn->close();
?>