<?php
include 'connection.php'; // Include your database configuration

header('Content-Type: application/json'); // Ensure JSON response

$response = [
    'job_positions' => [],
    'departments' => []
];

// Fetch Job Positions
$sql_jobs = "SELECT job_name FROM job_position ORDER BY job_name ASC";
if ($result_jobs = $mysqli->query($sql_jobs)) {
    while ($row = $result_jobs->fetch_assoc()) {
        $response['job_positions'][] = $row['job_name'];
    }
    $result_jobs->free();
} else {
    // Log error or handle it
    error_log("Error fetching job positions: " . $mysqli->error);
}

// Fetch Departments
$sql_departments = "SELECT department_name FROM department ORDER BY department_name ASC";
if ($result_departments = $mysqli->query($sql_departments)) {
    while ($row = $result_departments->fetch_assoc()) {
        $response['departments'][] = $row['department_name'];
    }
    $result_departments->free();
} else {
    // Log error or handle it
    error_log("Error fetching departments: " . $mysqli->error);
}

$mysqli->close();

echo json_encode($response);
?>