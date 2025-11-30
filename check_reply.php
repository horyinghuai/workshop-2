<?php
// check_reply.php
session_start();
include 'connection.php';
header('Content-Type: application/json');

$telegram_bot_token = getenv('TELEGRAM_BOT_TOKEN');
$allowed_chat_id = getenv('TELEGRAM_CHAT_ID'); // Only accept replies from Admin

if (empty($telegram_bot_token)) {
    echo json_encode(['status' => 'error']);
    exit;
}

// Store the last update ID in a text file to avoid re-reading old messages
$offset_file = 'telegram_offset.txt';
$offset = file_exists($offset_file) ? (int)file_get_contents($offset_file) : 0;

$url = "https://api.telegram.org/bot$telegram_bot_token/getUpdates?offset=$offset";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$replies = [];

if (isset($data['result']) && !empty($data['result'])) {
    foreach ($data['result'] as $update) {
        $update_id = $update['update_id'];
        
        // Save new offset (next message)
        file_put_contents($offset_file, $update_id + 1);

        if (isset($update['message'])) {
            $msg = $update['message'];
            // Check if message is from the Admin
            if ($msg['chat']['id'] == $allowed_chat_id) {
                $text = $msg['text'] ?? '';
                if (!empty($text)) {
                    $replies[] = $text;
                }
            }
        }
    }
}

if (!empty($replies)) {
    echo json_encode(['status' => 'success', 'replies' => $replies]);
} else {
    echo json_encode(['status' => 'no_new']);
}
?>