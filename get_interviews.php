<?php
// get_interviews.php
include 'connection.php';
header('Content-Type: application/json');

$sql = "
    SELECT i.interview_id, c.name, j.job_name, i.interview_date, i.meeting_link 
    FROM interview i
    JOIN candidate c ON i.candidate_id = c.candidate_id
    JOIN job_position j ON c.job_id = j.job_id
";

$result = $conn->query($sql);
$events = [];

if ($result) {
    while($row = $result->fetch_assoc()) {
        $events[] = [
            'title' => $row['name'] . ' (' . $row['job_name'] . ')',
            'start' => $row['interview_date'], // FullCalendar expects 'start'
            'url'   => $row['meeting_link'],   // Click to join meeting
            'color' => '#3a7c7c'
        ];
    }
}

echo json_encode($events);
$conn->close();
?>