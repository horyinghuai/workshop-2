<?php
// get_interviews.php
include 'connection.php';
header('Content-Type: application/json');

// Select 'questions' directly from the interview table
$sql = "
    SELECT 
        i.interview_id, 
        c.name, 
        j.job_name, 
        i.interview_date, 
        i.meeting_link,
        i.questions 
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
            'start' => $row['interview_date'], 
            'url'   => $row['meeting_link'],   
            'color' => '#3a7c7c',
            'extendedProps' => [
                'meet_link' => $row['meeting_link'],
                'questions' => $row['questions'] // Directly from interview table
            ]
        ];
    }
}

echo json_encode($events);
$conn->close();
?>