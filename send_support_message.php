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

// 3. Identify User (Handle Multiple Guests)
$final_name = 'Guest';
$final_email = '';

if (!empty($_SESSION['user_email'])) {
    // Logged in user
    $final_email = $_SESSION['user_email'];
    $final_name = $_SESSION['user_name'] ?? 'User';
} else {
    // Guest User: Use Session ID to create a unique fake email for threading
    // This ensures Guest A and Guest B don't see each other's messages
    $final_email = 'guest_' . session_id() . '@temp.com';
    $final_name = 'Guest (' . substr(session_id(), 0, 5) . ')';
}

// 4. Send Notification to Telegram & Capture Message ID
$telegram_msg_id = null;

if (!empty($telegram_bot_token) && !empty($telegram_chat_id)) {
    $time_str = date('Y-m-d H:i:s');
    
    // Create a clickable email link for the Admin
    $display_email = ($final_name === 'Guest') ? "Guest Session" : $final_email;

    $text = "<b>📩 New Support Request</b>\n" .
            "<b>👤 Name:</b> " . htmlspecialchars($final_name) . "\n" .
            "<b>📧 Email:</b> " . $display_email . "\n" .
            "<b>🕒 Time:</b> $time_str\n\n" .
            "<b>💬 Message:</b>\n" . htmlspecialchars($message) . "\n\n" .
            "<i>Reply to this message to send an answer to the user.</i>";

    $url = "https://api.telegram.org/bot$telegram_bot_token/sendMessage";
    $data = [
        'chat_id' => $telegram_chat_id,
        'text' => $text,
        'parse_mode' => 'HTML' 
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    // Parse result to get Message ID for threading
    if ($result) {
        $json_res = json_decode($result, true);
        if (isset($json_res['result']['message_id'])) {
            $telegram_msg_id = $json_res['result']['message_id'];
        }
    }
}

// 5. Save User Message to Database (Persistence)
$stmt = $conn->prepare("INSERT INTO support_messages (email, name, message, sender, telegram_msg_id) VALUES (?, ?, ?, 'user', ?)");
if ($stmt) {
    $stmt->bind_param("sssi", $final_email, $final_name, $message, $telegram_msg_id);
    $stmt->execute();
    $stmt->close();
}

// 6. Check Working Hours (9 AM - 6 PM)
$current_hour = (int)date('H'); 
$is_working_hours = ($current_hour >= 9 && $current_hour < 18);

if (!$is_working_hours) {
    $auto_msg = "Thank you for contacting us. Our operating hours are 9:00 AM - 6:00 PM. We will attend to your message on the next working day.";
    
    // Save Auto-Reply to DB so it persists on restart
    $stmt = $conn->prepare("INSERT INTO support_messages (email, name, message, sender) VALUES (?, 'System', ?, 'system')");
    $stmt->bind_param("ss", $final_email, $auto_msg);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'status' => 'auto_reply', 
        'message' => $auto_msg
    ]);
} else {
    echo json_encode([
        'status' => 'success', 
        'message' => 'Message sent! Wait here for a reply.'
    ]);
}

$conn->close();
?>