<?php
    // --- LOAD .ENV FILE (Robust Version) ---
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments and lines without equals sign
            if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
            
            list($name, $value) = explode('=', $line, 2);
            
            $name = trim($name);        // Remove spaces around key
            $value = trim($value);      // Remove spaces around value
            $value = trim($value, "\"'"); // Remove quotes around value
            
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
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

