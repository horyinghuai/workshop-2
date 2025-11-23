<?php
// check_progress.php
// Disable caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$id = isset($_GET['id']) ? preg_replace('/[^0-9]/', '', $_GET['id']) : '';
$filename = "progress_" . $id . ".txt";

if (!empty($id) && file_exists($filename)) {
    echo file_get_contents($filename);
} else {
    echo "0";
}
?>