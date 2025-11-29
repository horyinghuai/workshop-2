<?php
session_start();
require 'connection.php'; // your database connection

header('Content-Type: application/json');

$sql = "SELECT id, candidate_id, interview_date, meet_link, interview_questions 
        FROM interview_schedules";
$result = $conn->query($sql);

$events = [];

while ($row = $result->fetch_assoc()) {

    // Build event title
    $title = "Interview (Candidate ID: " . $row['candidate_id'] . ")";

    // Build description to show in popup
    $description = "<b>Candidate ID:</b> " . $row['candidate_id'] . "<br>"
                 . "<b>Meet Link:</b> " . ($row['meet_link'] ?: 'Not provided') . "<br><br>"
                 . "<b>Questions:</b><br>" . nl2br($row['interview_questions']);

    $events[] = [
        "id" => $row['id'],
        "title" => $title,
        "start" => $row['interview_date'],
        "extendedProps" => [
            "description" => $description
        ]
    ];
}

echo json_encode($events);
?>
