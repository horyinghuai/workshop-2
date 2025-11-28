<?php
include 'connection.php';
echo "Database Host: " . getenv('DB_HOST') . "<br>";
// Do NOT print the full API key for security, just the first few chars
$key = getenv('GEMINI_API_KEY');
echo "Gemini Key: " . ($key ? substr($key, 0, 5) . "..." : "NOT FOUND");
?>