<?php
include 'connection.php';

if (isset($_POST['search_term'])) {
    $search_term = "%" . $conn->real_escape_string($_POST['search_term']) . "%";
    $isArchived = isset($_POST['is_archived']) ? (int)$_POST['is_archived'] : 0;
    
    $sql = "SELECT * FROM department 
            WHERE is_archived = ? AND (department_name LIKE ? OR description LIKE ?) 
            ORDER BY department_name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $isArchived, $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();

    $department_rows_html = '';

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $dataAttrs = 'data-id="' . $row["department_id"] . '" ' .
                         'data-name="' . htmlspecialchars($row["department_name"]) . '" ' .
                         'data-desc="' . htmlspecialchars($row["description"]) . '"';

            $department_rows_html .= '
                <div class="table-row">
                    <div class="table-cell center-align"><input type="checkbox" name="dept_check" value="' . $row["department_id"] . '"></div>
                    <div class="table-cell data clickable-dept" ' . $dataAttrs . ' onclick="openEditFromRow(this)">
                        ' . htmlspecialchars($row["department_name"]) . ' <i class="fas fa-pen" style="font-size:0.8em; color:#999; margin-left:5px;"></i>
                    </div>
                    <div class="table-cell description data">' . htmlspecialchars($row["description"]) . '</div>
                </div>';
        }
    } else {
        // Spans all 3 grid columns
        $department_rows_html = '
            <div class="table-row no-data">
                <div class="table-cell data" style="grid-column: 1 / -1; text-align: center; justify-content:center;">No departments matched your search criteria.</div>
            </div>';
    }

    echo $department_rows_html;
    $stmt->close();
}
$conn->close();
?>