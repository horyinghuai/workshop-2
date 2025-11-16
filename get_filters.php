<?php
include 'connection.php'; // Include your database configuration

header('Content-Type: application/json'); // Ensure JSON response

$response = [
    'job_positions' => [],
    'departments' => []
];

// Fetch Job Positions and Departments dynamically
$sql_jobs = "SELECT job_name FROM job_position ORDER BY job_name ASC";
$sql_departments = "SELECT department_name FROM department ORDER BY department_name ASC";

if ($result_jobs = $mysqli->query($sql_jobs)) {
    while ($row = $result_jobs->fetch_assoc()) {
        $response['job_positions'][] = $row['job_name'];
    }
    $result_jobs->free();
}

if ($result_departments = $mysqli->query($sql_departments)) {
    while ($row = $result_departments->fetch_assoc()) {
        $response['departments'][] = $row['department_name'];
    }
    $result_departments->free();
}

$mysqli->close();

echo json_encode($response);
?>