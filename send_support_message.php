<?php
session_start();
include 'connection.php';
header('Content-Type: application/json');

// 1. Configuration
date_default_timezone_set('Asia/Kuala_Lumpur');
$telegram_bot_token = getenv('TELEGRAM_BOT_TOKEN');
$telegram_chat_id = getenv('TELEGRAM_CHAT_ID');

// 2. Validate Request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$message = isset($_POST['message']) ? trim($_POST['message']) : '';
if (empty($message)) {
    echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty.']);
    exit;
}

// 3. Identify User (Logged in or Guest)
$final_email = '';
$final_name = '';

if (isset($_SESSION['user_email']) && !empty($_SESSION['user_email'])) {
    // Registered User
    $final_email = $_SESSION['user_email'];
    $final_name = $_SESSION['user_name'] ?? 'User';
} else {
    // Guest User - Generate session if not exists
    if (!isset($_SESSION['guest_email'])) {
        $_SESSION['guest_email'] = 'guest_' . time() . '_' . rand(1000, 9999);
        $_SESSION['guest_name'] = 'Guest';
    }
    $final_email = $_SESSION['guest_email'];
    $final_name = $_SESSION['guest_name'];
}

// 4. Log User Message to Database
$stmt = $conn->prepare("INSERT INTO support_messages (email, name, message, sender) VALUES (?, ?, ?, 'user')");
$stmt->bind_param("sss", $final_email, $final_name, $message);
$stmt->execute();
$db_message_id = $stmt->insert_id; // Capture the ID to send back
$stmt->close();

// 5. Send Notification to Telegram
if (!empty($telegram_bot_token) && !empty($telegram_chat_id)) {
    $text = "<b>📩 New Support Request</b>\n" .
            "<b>Name:</b> " . htmlspecialchars($final_name) . "\n" .
            "<b>Email:</b> " . htmlspecialchars($final_email) . "\n" .
            "<b>Message:</b>\n" . htmlspecialchars($message) . "\n\n" .
            "<i>Reply to this message to chat with the user.</i>";

    $url = "https://api.telegram.org/bot$telegram_bot_token/sendMessage";
    $data = [ 'chat_id' => $telegram_chat_id, 'text' => $text, 'parse_mode' => 'HTML' ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    $response = curl_exec($ch);
    curl_close($ch);

    // Save Telegram Message ID for threading
    $response_data = json_decode($response, true);
    if (isset($response_data['ok']) && $response_data['ok']) {
        $tg_msg_id = $response_data['result']['message_id'];
        $upd = $conn->prepare("UPDATE support_messages SET telegram_msg_id = ? WHERE message_id = ?");
        $upd->bind_param("si", $tg_msg_id, $db_message_id);
        $upd->execute();
        $upd->close();
    }
}

// 6. Check Working Hours & Auto-Reply
$current_hour = (int)date('H'); 
$is_working_hours = ($current_hour >= 9 && $current_hour < 18);
$return_status = 'success';
$return_msg = '';
$sys_msg_id = 0;

if (!$is_working_hours) {
    $auto_msg = "Thank you for contacting us. Our operating hours are 9:00 AM - 6:00 PM. We will attend to your message on the next working day.";
    
    $sys = $conn->prepare("INSERT INTO support_messages (email, name, message, sender) VALUES (?, 'System', ?, 'system')");
    $sys->bind_param("ss", $final_email, $auto_msg);
    $sys->execute();
    $sys_msg_id = $sys->insert_id;
    $sys->close();

    $return_status = 'auto_reply';
    $return_msg = $auto_msg;
}

$conn->close();

echo json_encode([
    'status' => $return_status,
    'message' => $return_msg,
    'sender' => ($return_status == 'auto_reply') ? 'system' : 'user',
    'db_message_id' => $db_message_id, // User's message ID
    'sys_message_id' => $sys_msg_id    // Auto-reply ID (if any)
]);
?>