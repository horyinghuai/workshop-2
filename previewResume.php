<?php
include "extract_nlp.php";

$parsed = [
    "name" => "",
    "gender" => "",
    "email" => "",
    "contact" => "",
    "address" => "",
    "objective" => "",
    "education" => "",
    "skills" => "",
    "experience" => ""
];

$resumePath = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_FILES["resumeFile"])) {

        $fileName = time() . "_" . basename($_FILES["resumeFile"]["name"]);
        $target = "uploads/" . $fileName;
        move_uploaded_file($_FILES["resumeFile"]["tmp_name"], $target);

        $resumePath = $target;

        // NLP extraction
        $text = extract_text_from_file($target);
        $parsed = run_basic_nlp($text);
    }
}
?>