<?php
    // --- LOAD .ENV FILE (Native PHP Implementation) ---
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue; // Skip comments
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                // Set environment variables
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
            }
        }
    }

    // --- DB CONNECTION ---
    $servername = getenv('DB_HOST') ?: "localhost";
    $username = getenv('DB_USER') ?: "root";
    $password = getenv('DB_PASSWORD') ?: "";
    $dbname = getenv('DB_NAME') ?: "resume_reader";

    /*$servername = "localhost";
    $username = "d032210233";
    $password = "390";
    $dbname = "student_d032210233";*/

    $conn = new mysqli($servername, $username, $password, $dbname);

    if($conn->connect_error){
        die("Connection failed: ".$conn->connect_error);
    }
    else{
        echo "";
    }
?>

