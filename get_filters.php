<?php
include 'connection.php';

header('Content-Type: application/json');

$response = [
    'job_positions' => [],
    'departments' => [],
    'outreach_statuses' => [], // New
    'staff_in_charge' => []    // New
];

// 1. Fetch Job Positions
$sql_jobs = "SELECT job_name FROM job_position ORDER BY job_name ASC";
if ($result_jobs = $conn->query($sql_jobs)) {
    while ($row = $result_jobs->fetch_assoc()) {
        $response['job_positions'][] = $row['job_name'];
    }
    $result_jobs->free();
}

// 2. Fetch Departments
$sql_departments = "SELECT department_name FROM department ORDER BY department_name ASC";
if ($result_departments = $conn->query($sql_departments)) {
    while ($row = $result_departments->fetch_assoc()) {
        $response['departments'][] = $row['department_name'];
    }
    $result_departments->free();
}

// 3. Fetch Outreach Statuses (from candidate table)
$sql_outreach = "SELECT DISTINCT outreach FROM candidate WHERE outreach IS NOT NULL AND outreach != '' ORDER BY outreach ASC";
if ($result_outreach = $conn->query($sql_outreach)) {
    while ($row = $result_outreach->fetch_assoc()) {
        $response['outreach_statuses'][] = $row['outreach'];
    }
    $result_outreach->free();
}

// 4. Fetch Staff In Charge (from user table)
$sql_staff = "SELECT DISTINCT name FROM user WHERE name IS NOT NULL ORDER BY name ASC";
if ($result_staff = $conn->query($sql_staff)) {
    while ($row = $result_staff->fetch_assoc()) {
        $response['staff_in_charge'][] = $row['name'];
    }
    $result_staff->free();
}

$conn->close();

echo json_encode($response);
?>