<?php
// get_departments.php

include 'connection.php';

header('Content-Type: application/json');

$departments = [];
$sql = "SELECT department_id, department_name FROM department ORDER BY department_name ASC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Store both ID and Name
        $departments[] = [
            'id' => $row['department_id'],
            'name' => $row['department_name']
        ];
    }
}

$conn->close();
echo json_encode($departments);
?>