<?php
// check_reply.php
session_start();
include 'connection.php';
header('Content-Type: application/json');

$telegram_bot_token = getenv('TELEGRAM_BOT_TOKEN');
$allowed_chat_id = getenv('TELEGRAM_CHAT_ID');

// --- 1. Identify Current User ---
$current_email = '';
if (!empty($_SESSION['user_email'])) {
    $current_email = $_SESSION['user_email'];
} else {
    $current_email = 'guest_' . session_id() . '@temp.com';
}

// --- 2. SYNC: Fetch Updates from Telegram & Save to DB ---
// This runs for ANY user to ensure the DB is always up to date
if (!empty($telegram_bot_token)) {
    $offset_file = 'telegram_offset.txt';
    $offset = file_exists($offset_file) ? (int)file_get_contents($offset_file) : 0;

    $url = "https://api.telegram.org/bot$telegram_bot_token/getUpdates?offset=$offset";
    
    // Use non-blocking CURL if possible, or short timeout
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (isset($data['result']) && !empty($data['result'])) {
        foreach ($data['result'] as $update) {
            $update_id = $update['update_id'];
            file_put_contents($offset_file, $update_id + 1);

            if (isset($update['message']) && isset($update['message']['reply_to_message'])) {
                // It is a reply!
                $reply_text = $update['message']['text'] ?? '';
                $original_msg_id = $update['message']['reply_to_message']['message_id'];
                
                if (!empty($reply_text)) {
                    // Find who sent the original message
                    $stmt = $conn->prepare("SELECT email FROM support_messages WHERE telegram_msg_id = ? LIMIT 1");
                    $stmt->bind_param("i", $original_msg_id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    
                    if ($row = $res->fetch_assoc()) {
                        $recipient_email = $row['email'];
                        
                        // Insert Admin Reply into DB
                        $stmt_ins = $conn->prepare("INSERT INTO support_messages (email, name, message, sender) VALUES (?, 'Admin', ?, 'admin')");
                        $stmt_ins->bind_param("ss", $recipient_email, $reply_text);
                        $stmt_ins->execute();
                        $stmt_ins->close();
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// --- 3. FETCH: Get History for Current User ---
$history = [];
$stmt = $conn->prepare("SELECT name, message, sender, created_at FROM support_messages WHERE email = ? ORDER BY created_at ASC");
$stmt->bind_param("s", $current_email);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $history[] = [
        'sender' => $row['sender'], // 'user', 'admin', 'system'
        'message' => $row['message'],
        'time' => $row['created_at']
    ];
}
$stmt->close();
$conn->close();

echo json_encode(['status' => 'success', 'messages' => $history]);
?>