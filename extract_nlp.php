<?php

function extract_text_from_file($filePath) {

    $ext = pathinfo($filePath, PATHINFO_EXTENSION);

    if ($ext == "txt") {
        return file_get_contents($filePath);
    }

    if ($ext == "pdf") {
        return shell_exec("pdftotext $filePath -");
    }

    if ($ext == "docx") {
        return read_docx($filePath);
    }

    return "";
}

function read_docx($file) {
    $zip = new ZipArchive;
    $text = "";

    if ($zip->open($file) === TRUE) {
        $xml = $zip->getFromName("word/document.xml");
        $text = strip_tags($xml);
        $zip->close();
    }
    return $text;
}

function run_basic_nlp($text) {

    $result = [
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

    // EMAIL
    preg_match("/[a-zA-Z0-9._%+-]+@[a-zA-Z.-]+\.[a-zA-Z]{2,}/", $text, $email);
    $result["email"] = $email[0] ?? "";

    // CONTACT NUMBER
    preg_match("/(\+?\d[\d\- ]{7,15})/", $text, $contact);
    $result["contact"] = $contact[0] ?? "";

    // NAME (first line assumption)
    $lines = explode("\n", trim($text));
    $result["name"] = trim($lines[0]);

    // GENDER
    if (stripos($text, "male") !== false) $result["gender"] = "Male";
    if (stripos($text, "female") !== false) $result["gender"] = "Female";

    // OBJECTIVE
    preg_match("/objective[:\-]?(.*?)(education|skills)/is", $text, $obj);
    $result["objective"] = trim($obj[1] ?? "");

    // EDUCATION
    preg_match("/education(.*?)(skills|experience)/is", $text, $edu);
    $result["education"] = trim($edu[1] ?? "");

    // SKILLS
    preg_match("/skills(.*?)(experience|work)/is", $text, $skills);
    $result["skills"] = trim($skills[1] ?? "");

    // EXPERIENCE
    preg_match("/experience(.*?)(language|others)/is", $text, $exp);
    $result["experience"] = trim($exp[1] ?? "");

    return $result;
}
?>
