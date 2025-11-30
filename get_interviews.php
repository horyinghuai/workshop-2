<?php
header('Content-Type: application/json');
include 'connection.php'; // make sure this sets $conn

// Correct table name and columns
$sql = "SELECT interview_id AS id, candidate_id, interview_date, meeting_link, interview_questions 
        FROM interview";

$result = $conn->query($sql);
if (!$result) {
    die("SQL Error: " . $conn->error);
}

$events = [];

while ($row = $result->fetch_assoc()) {
    $events[] = [
        "id" => $row["id"],
        "title" => "Interview with Candidate " . $row["candidate_id"],
        "start" => $row["interview_date"],
        "meet_link" => $row["meeting_link"],
        "questions" => $row["interview_questions"]
    ];
}

echo json_encode($events);
$conn->close();
?>
