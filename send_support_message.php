<?php
session_start();
include 'connection.php'; // This automatically loads your .env variables
header('Content-Type: application/json');

// --- CONFIGURATION FETCHED FROM .ENV ---
$telegram_bot_token = getenv('TELEGRAM_BOT_TOKEN');
$telegram_chat_id = getenv('TELEGRAM_CHAT_ID');
$developer_email = getenv('DEVELOPER_EMAIL');
// -------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    
    // Get user info from session
    $email = $_SESSION['email'] ?? 'Guest';
    $name = $_SESSION['name'] ?? 'Guest';

    if (empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty.']);
        exit;
    }

    // 1. Save to Database
    $stmt = $conn->prepare("INSERT INTO support_messages (email, name, message) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $name, $message);
    
    if ($stmt->execute()) {
        
        // 2. Send Notification via Telegram (Only if keys exist in .env)
        if (!empty($telegram_bot_token) && !empty($telegram_chat_id)) {
            $text = "🚨 *New Support Request*\n\n" .
                    "👤 *User:* $name\n" .
                    "📧 *Email:* $email\n" .
                    "💬 *Message:* $message";

            $url = "https://api.telegram.org/bot$telegram_bot_token/sendMessage";
            $data = [
                'chat_id' => $telegram_chat_id,
                'text' => $text,
                'parse_mode' => 'Markdown'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
            $result = curl_exec($ch);
            curl_close($ch);
        }

        // 3. Send Notification via Email (Fallback)
        if (!empty($developer_email)) {
            $subject = "Support Request from $user_name";
            $body = "User: $user_name ($user_email)\n\nMessage:\n$message";
            $headers = "From: noreply@resumereader.com";
            
            @mail($developer_email, $subject, $body, $headers);
        }

        echo json_encode(['status' => 'success', 'message' => 'Message sent to developer!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    $stmt->close();
    $conn->close();
}
?>