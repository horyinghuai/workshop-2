<?php
session_start();
include 'connection.php'; // Loads .env and DB connection
header('Content-Type: application/json');

// Set Timezone to Malaysia (GMT+8)
date_default_timezone_set('Asia/Kuala_Lumpur');

// --- CONFIGURATION FROM .ENV ---
$telegram_bot_token = getenv('TELEGRAM_BOT_TOKEN');
$telegram_chat_id = getenv('TELEGRAM_CHAT_ID');
// ------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    
    // 1. FETCH CURRENT USER DETAILS (Corrected Session Keys)
    // We check 'user_email' because that is what login.php sets
    $session_email = $_SESSION['user_email'] ?? '';
    
    $final_name = 'Guest';
    $final_email = 'Guest';

    if (!empty($session_email)) {
        // Verify against USER table to get real name
        $stmt = $conn->prepare("SELECT name, email FROM user WHERE email = ?");
        $stmt->bind_param("s", $session_email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $final_name = $row['name'];
            $final_email = $row['email'];
        }
        $stmt->close();
    }

    if (empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty.']);
        exit;
    }

    // 2. CHECK WORKING HOURS (9 AM - 6 PM)
    $current_hour = (int)date('H'); // 24-hour format (0-23)
    // 9:00 is 9, 18:00 is 6PM. So >= 9 and < 18 covers 09:00 to 17:59
    $is_working_hours = ($current_hour >= 9 && $current_hour < 18);

    // Save to Database (Log everything)
    $stmt = $conn->prepare("INSERT INTO support_messages (email, name, message) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $final_email, $final_name, $message);
    $stmt->execute();
    $stmt->close();

    // 3. HANDLE LOGIC BASED ON TIME
    if (!$is_working_hours) {
        echo json_encode([
            'status' => 'auto_reply', 
            'message' => "We are currently closed (Working Hours: 9am - 6pm). Your message has been received, and we will reply when we are back online."
        ]);
        exit;
    }

    // INSIDE WORKING HOURS: Send to Telegram
    if (!empty($telegram_bot_token) && !empty($telegram_chat_id)) {
        
        $time_str = date('Y-m-d H:i:s');
        
        // Generate a clickable Mailto Link
        if ($final_email !== 'Guest') {
            $reply_subject = urlencode("Support Reply: " . substr($message, 0, 20) . "...");
            // Only the raw link as requested? Or a clean HTML link? 
            // HTML is safer for Telegram parsing.
            $mailto = "mailto:$final_email?subject=$reply_subject";
            $email_display = "<a href='$mailto'>$mailto</a>";
        } else {
            $email_display = "No email provided";
        }

        // HTML Message for Telegram
        $text = "<b>🚨 New Support Request</b>\n\n" .
                "<b>👤 Name:</b> $final_name\n" .
                "<b>📧 Email:</b> $final_email\n" .
                "<b>🕒 Time:</b> $time_str\n\n" .
                "<b>💬 Message:</b>\n" . htmlspecialchars($message) . "\n\n" .
                "<b>👇 Reply Options:</b>\n" .
                "1. Reply directly to this message to send a chat response.\n" .
                "2. Click to Email: $email_display";

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
        curl_exec($ch);
        curl_close($ch);
    }

    echo json_encode(['status' => 'success', 'message' => 'Message sent! Wait here for a reply.']);
    
    $conn->close();
}
?>