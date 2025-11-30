<?php
session_start();
include 'connection.php';
header('Content-Type: application/json');

$telegram_bot_token = getenv('TELEGRAM_BOT_TOKEN');

// --- 1. Identify User Type & Email ---
$current_email = '';
$is_guest = false;

if (isset($_SESSION['user_email'])) {
    $current_email = $_SESSION['user_email'];
} elseif (isset($_SESSION['guest_email'])) {
    $current_email = $_SESSION['guest_email'];
    $is_guest = true;
}

if (empty($telegram_bot_token)) {
    echo json_encode(['status' => 'error', 'message' => 'Bot token missing']);
    exit;
}

// --- 2. SYNC TELEGRAM UPDATES (Global) ---
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

if (isset($data['result']) && !empty($data['result'])) {
    foreach ($data['result'] as $update) {
        $update_id = $update['update_id'];
        file_put_contents($offset_file, $update_id + 1);

        if (isset($update['message']['reply_to_message']) && isset($update['message']['text'])) {
            $msg = $update['message'];
            $original_msg_id = $msg['reply_to_message']['message_id'];
            $admin_tg_id = $msg['message_id']; // Unique ID of this specific admin reply
            $admin_text = $msg['text'];

            // DEDUPLICATION: Check if we already saved this specific Telegram message
            $check = $conn->prepare("SELECT message_id FROM support_messages WHERE telegram_msg_id = ?");
            $check->bind_param("s", $admin_tg_id);
            $check->execute();
            $check->store_result();

            if ($check->num_rows == 0) {
                // Find original sender
                $find = $conn->prepare("SELECT email FROM support_messages WHERE telegram_msg_id = ? LIMIT 1");
                $find->bind_param("s", $original_msg_id);
                $find->execute();
                $res = $find->get_result();
                
                if ($row = $res->fetch_assoc()) {
                    $target_email = $row['email'];
                    // Insert Reply
                    $ins = $conn->prepare("INSERT INTO support_messages (email, name, message, sender, telegram_msg_id) VALUES (?, 'Admin', ?, 'admin', ?)");
                    $ins->bind_param("sss", $target_email, $admin_text, $admin_tg_id);
                    $ins->execute();
                    $ins->close();
                }
                $find->close();
            }
            $check->close();
        }
    }
}

// --- 3. FETCH MESSAGES FOR CLIENT ---

if (empty($current_email)) {
    echo json_encode(['status' => 'no_user']);
    exit;
}

$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

// LOGIC FIX:
// If Guest AND last_id == 0 (Fresh Load/Refresh), return NO history.
// If Registered, always return history based on last_id.
if ($is_guest && $last_id === 0) {
    echo json_encode(['status' => 'success', 'messages' => []]);
    exit;
}

$sql = "SELECT message_id, message, sender, created_at FROM support_messages 
        WHERE email = ? AND message_id > ? 
        ORDER BY message_id ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $current_email, $last_id);
$stmt->execute();
$result = $stmt->get_result();

$new_messages = [];
while ($row = $result->fetch_assoc()) {
    $new_messages[] = [
        'id' => $row['message_id'],
        'message' => $row['message'],
        'sender' => $row['sender'],
        'time' => $row['created_at']
    ];
}
$stmt->close();
$conn->close();

if (!empty($new_messages)) {
    echo json_encode(['status' => 'success', 'messages' => $new_messages]);
} else {
    echo json_encode(['status' => 'no_new']);
}
?>