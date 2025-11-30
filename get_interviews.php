<?php
header('Content-Type: application/json');
include 'connection.php'; // make sure this sets $conn

// Correct table name and columns
$sql = "
    SELECT i.interview_id, i.interview_date, i.meeting_link,
           c.name, q.questions
    FROM interview i
    JOIN candidate c ON i.candidate_id = c.candidate_id
    LEFT JOIN interview_questions q ON q.interview_id = i.interview_id
";

$result = $conn->query($sql);
if (!$result) {
    die("SQL Error: " . $conn->error);
}

$events = [];

while ($row = $result->fetch_assoc()) {
   $events[] = [
    "title" => $row["name"],        // ← only the name
    "start" => $row["interview_date"],
    "meet_link" => $row["meeting_link"],
    "questions" => $row["questions"]
   ];

}

echo json_encode($events);
$conn->close();
?>
