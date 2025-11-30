<style>
    /* Floating Button */
    .chatbot-toggler {
        position: fixed; bottom: 30px; right: 35px; outline: none; border: none;
        height: 50px; width: 50px; display: flex; cursor: pointer;
        align-items: center; justify-content: center; border-radius: 50%;
        background: #3a7c7c; transition: all 0.2s ease; z-index: 9999;
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    }
    .chatbot-toggler:hover { transform: scale(1.1); background: #2d6d6d; }
    .chatbot-toggler span { color: #fff; font-size: 1.5rem; position: absolute; }
    
    /* Chat Window */
    .chatbot-window {
        position: fixed; right: 35px; bottom: 90px; width: 340px;
        background: #fff; border-radius: 15px; overflow: hidden; opacity: 0;
        pointer-events: none; transform: scale(0.5); transform-origin: bottom right;
        box-shadow: 0 5px 20px rgba(0,0,0,0.2); transition: all 0.3s ease;
        z-index: 9999; font-family: 'Arial', sans-serif;
    }
    .show-chatbot .chatbot-window { opacity: 1; pointer-events: auto; transform: scale(1); }

    /* Header */
    .chatbot-header { background: #3a7c7c; padding: 15px; text-align: center; position: relative; }
    .chatbot-header h2 { color: #fff; font-size: 1.1rem; margin: 0; font-weight: 600; }
    .chatbot-header .close-btn { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #fff; cursor: pointer; font-size: 1.2rem; }

    /* Chat Box Area */
    .chatbox { height: 300px; overflow-y: auto; padding: 15px; background: #f9f9f9; list-style: none; margin: 0; }
    .chat-message { display: flex; margin-bottom: 15px; }
    .chat-message.ai { justify-content: flex-start; }
    .chat-message.user { justify-content: flex-end; }
    .chat-message p { padding: 10px 14px; max-width: 80%; font-size: 0.9rem; line-height: 1.4; word-wrap: break-word; }
    .chat-message.ai p { background: #e0e0e0; color: #333; border-radius: 10px 10px 10px 0; }
    .chat-message.user p { background: #3a7c7c; color: #fff; border-radius: 10px 10px 0 10px; }

    /* Input Area */
    .chat-input { display: flex; padding: 10px; border-top: 1px solid #ddd; background: #fff; }
    .chat-input textarea { flex: 1; height: 45px; border: 1px solid #ccc; border-radius: 20px; padding: 12px 15px; font-size: 0.9rem; resize: none; outline: none; font-family: inherit; }
    .chat-input span { align-self: center; color: #3a7c7c; cursor: pointer; margin-left: 12px; font-size: 1.3rem; }
</style>

<button class="chatbot-toggler" onclick="toggleChat()">
    <span><i class="fas fa-comment-dots"></i></span>
</button>

<div class="chatbot-window">
    <div class="chatbot-header">
        <h2>Support Assistant</h2>
        <span class="close-btn" onclick="toggleChat()">&times;</span>
    </div>
    <ul class="chatbox" id="chatbox">
        <li class="chat-message ai">
            <p>Hi! 👋<br>Our working hours are 9 AM - 6 PM. How can we help?</p>
        </li>
    </ul>
    <div class="chat-input">
        <textarea id="userMessage" placeholder="Type a message..." required></textarea>
        <span id="sendBtn"><i class="fas fa-paper-plane"></i></span>
    </div>
</div>

<script>
    const chatInput = document.getElementById("userMessage");
    const sendChatBtn = document.getElementById("sendBtn");
    const chatbox = document.getElementById("chatbox");
    let pollingInterval = null;

    const createChatLi = (message, className) => {
        const chatLi = document.createElement("li");
        chatLi.classList.add("chat-message", className);
        chatLi.innerHTML = `<p>${message}</p>`;
        return chatLi;
    }

    const handleChat = () => {
        const userMessage = chatInput.value.trim();
        if (!userMessage) return;

        chatbox.appendChild(createChatLi(userMessage, "user"));
        chatbox.scrollTo(0, chatbox.scrollHeight);
        chatInput.value = "";

        const loadingLi = createChatLi("Sending...", "ai");
        chatbox.appendChild(loadingLi);
        chatbox.scrollTo(0, chatbox.scrollHeight);

        const formData = new FormData();
        formData.append('message', userMessage);

        fetch('send_support_message.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            chatbox.removeChild(loadingLi);
            
            if(data.status === 'success') {
                chatbox.appendChild(createChatLi(data.message, "ai"));
                startPolling(); // Start listening for Admin reply
            } 
            else if(data.status === 'auto_reply') {
                chatbox.appendChild(createChatLi(data.message, "ai"));
            }
            else {
                chatbox.appendChild(createChatLi("❌ Error: " + data.message, "ai"));
            }
            chatbox.scrollTo(0, chatbox.scrollHeight);
        })
        .catch(() => {
            chatbox.removeChild(loadingLi);
            chatbox.appendChild(createChatLi("❌ Network error.", "ai"));
        });
    }

    // POLL FOR REPLIES FROM TELEGRAM
    function startPolling() {
        if (pollingInterval) return; // Already polling
        
        pollingInterval = setInterval(() => {
            fetch('check_reply.php')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' && data.replies) {
                    data.replies.forEach(reply => {
                        chatbox.appendChild(createChatLi("Admin: " + reply, "ai"));
                        chatbox.scrollTo(0, chatbox.scrollHeight);
                    });
                }
            })
            .catch(err => console.error("Polling error:", err));
        }, 3000); // Check every 3 seconds
    }

    sendChatBtn.addEventListener("click", handleChat);
    chatInput.addEventListener("keydown", (e) => {
        if(e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            handleChat();
        }
    });

    function toggleChat() {
        document.body.classList.toggle("show-chatbot");
    }
</script>