<?php
session_start();
include 'connection.php'; // Loads .env and DB connection
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

// 3. Fetch User Details
$session_email = $_SESSION['user_email'] ?? '';
$final_name = 'Guest';
$final_email = 'Guest';

if (!empty($session_email)) {
    // Verify against USER table to get real name
    $stmt = $conn->prepare("SELECT name, email FROM user WHERE email = ?");
    if ($stmt) {
        $stmt->bind_param("s", $session_email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $final_name = $row['name'];
            $final_email = $row['email'];
        }
        $stmt->close();
    }
}

// 4. Log to Database
$stmt = $conn->prepare("INSERT INTO support_messages (email, name, message) VALUES (?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("sss", $final_email, $final_name, $message);
    $stmt->execute();
    $stmt->close();
}

// 5. Send Notification to Telegram (Always sent, regardless of time)
if (!empty($telegram_bot_token) && !empty($telegram_chat_id)) {
    $time_str = date('Y-m-d H:i:s');
    
    // Prepare Email Link
    $email_display = "No email provided";
    if ($final_email !== 'Guest') {
        $mailto = "mailto:$final_email?subject=" . urlencode("Support Reply");
        $email_display = "<a href='$mailto'>$final_email</a>";
    }

    // Build HTML Message
    $text = "<b>📝 New Support Request</b>\n\n" .
            "<b>👤 Name:</b> " . htmlspecialchars($final_name) . "\n" .
            "<b>📧 Email:</b> " . $email_display . "\n" .
            "<b>🕒 Time:</b> $time_str\n\n" .
            "<b>💬 Message:</b>\n" . htmlspecialchars($message) . "\n\n" .
            "<b>Reply Options:</b>\n" .
            "1. Reply directly to this message.\n" .
            "2. Click the email above.";

    // Send via cURL
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

// 6. Check Working Hours (9 AM - 6 PM) & Return Response
$current_hour = (int)date('H'); 
$is_working_hours = ($current_hour >= 15 && $current_hour < 18);

if (!$is_working_hours) {
    // Auto-reply for out of office
    echo json_encode([
        'status' => 'auto_reply', 
        'message' => "Thank you for contacting us. Our operating hours are 9:00 AM - 6:00 PM. We will attend to your message on the next working day."
    ]);
} else {
    // Standard success message
    echo json_encode([
        'status' => 'success', 
        'message' => 'Message sent! Wait here for a reply.'
    ]);
}

$conn->close();
?>