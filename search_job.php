<?php
include 'connection.php';

if (isset($_POST['search_term'])) {
    $search_term = "%" . $conn->real_escape_string($_POST['search_term']) . "%";
    $isArchived = isset($_POST['is_archived']) ? (int)$_POST['is_archived'] : 0;
    
    $sql = "SELECT jp.*, d.department_name 
            FROM job_position jp
            INNER JOIN department d ON jp.department_id = d.department_id
            WHERE jp.is_archived = ? AND (
                jp.job_name LIKE ? 
                OR d.department_name LIKE ?
                OR jp.description LIKE ?
                OR jp.education LIKE ?
                OR jp.skills LIKE ?
                OR jp.experience LIKE ?
                OR jp.language LIKE ?
                OR jp.others LIKE ?
            )
            ORDER BY d.department_name ASC, jp.job_name ASC;";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssssss", $isArchived, $search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();

    $job_rows_html = '';

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $dataAttrs = 'data-id="' . $row["job_id"] . '" ' .
                         'data-dept-id="' . $row["department_id"] . '" ' .
                         'data-job-name="' . htmlspecialchars($row["job_name"]) . '" ' .
                         'data-desc="' . htmlspecialchars($row["description"]) . '" ' .
                         'data-edu="' . htmlspecialchars($row["education"]) . '" ' .
                         'data-skills="' . htmlspecialchars($row["skills"]) . '" ' .
                         'data-exp="' . htmlspecialchars($row["experience"]) . '" ' .
                         'data-lang="' . htmlspecialchars($row["language"]) . '" ' .
                         'data-others="' . htmlspecialchars($row["others"]) . '"';

            $job_rows_html .= '
                <div class="table-row">
                    <div class="table-cell center-align"><input type="checkbox" name="job_check" value="' . $row["job_id"] . '"></div>
                    <div class="table-cell data">' . htmlspecialchars($row["department_name"]) . '</div>
                    <div class="table-cell data clickable-job" ' . $dataAttrs . ' onclick="openEditFromRow(this)">
                        ' . htmlspecialchars($row["job_name"]) . ' <i class="fas fa-pen" style="font-size:0.8em; color:#999; margin-left:5px;"></i>
                    </div>
                    <div class="table-cell description data">' . htmlspecialchars($row["description"]) . '</div>
                    <div class="table-cell education data">' . htmlspecialchars($row["education"]) . '</div>
                    <div class="table-cell skills data">' . htmlspecialchars($row["skills"]) . '</div>
                    <div class="table-cell experience data">' . htmlspecialchars($row["experience"]) . '</div>
                    <div class="table-cell language data">' . htmlspecialchars($row["language"]) . '</div>
                    <div class="table-cell others data">' . htmlspecialchars($row["others"]) . '</div>
                </div>';
        }
    } else {
        // UPDATED: Used grid-column: 1 / -1
        $job_rows_html = '
            <div class="table-row no-data">
                <div class="table-cell data" style="grid-column: 1 / -1; text-align: center; justify-content:center;">No jobs matched your search criteria.</div>
            </div>';
    }

    echo $job_rows_html;
    $stmt->close();
}
$conn->close();
?>