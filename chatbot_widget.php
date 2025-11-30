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
    
    /* Styles for different senders */
    .chat-message.ai p { background: #e0e0e0; color: #333; border-radius: 10px 10px 10px 0; }
    .chat-message.user p { background: #3a7c7c; color: #fff; border-radius: 10px 10px 0 10px; }
    .chat-message.system p { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 10px; font-size: 0.85rem; }

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
            <p>Hi! 👋<br>How can we help you today?</p>
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
    
    let lastMessageId = 0; 
    let pollingInterval = null;
    
    // NEW: Track IDs to prevent ANY duplicates (from backend or self)
    const displayedMessageIds = new Set();

    const createChatLi = (message, className) => {
        const chatLi = document.createElement("li");
        chatLi.classList.add("chat-message", className);
        chatLi.innerHTML = `<p>${message}</p>`;
        return chatLi;
    }

    const handleChat = () => {
        const userMessage = chatInput.value.trim();
        if (!userMessage) return;

        // 1. Optimistic UI Update (Show immediately)
        const tempLi = createChatLi(userMessage, "user");
        chatbox.appendChild(tempLi);
        chatbox.scrollTo(0, chatbox.scrollHeight);
        chatInput.value = "";

        const formData = new FormData();
        formData.append('message', userMessage);

        fetch('send_support_message.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            // 2. Register the real ID from DB so poller doesn't duplicate it
            if(data.db_message_id) {
                displayedMessageIds.add(parseInt(data.db_message_id));
                lastMessageId = Math.max(lastMessageId, parseInt(data.db_message_id));
            }

            // 3. Handle Auto-reply
            if(data.status === 'auto_reply') {
                // Check if we haven't already shown this auto-reply (rare but safe)
                if(data.sys_message_id && !displayedMessageIds.has(parseInt(data.sys_message_id))) {
                    chatbox.appendChild(createChatLi(data.message, "system"));
                    chatbox.scrollTo(0, chatbox.scrollHeight);
                    
                    displayedMessageIds.add(parseInt(data.sys_message_id));
                    lastMessageId = Math.max(lastMessageId, parseInt(data.sys_message_id));
                }
            }
            // Ensure polling is running
            startPolling(); 
        })
        .catch(() => {
            chatbox.appendChild(createChatLi("❌ Network error. Message may not have sent.", "ai"));
        });
    }

    function startPolling() {
        if (pollingInterval) return; 
        
        pollingInterval = setInterval(() => {
            fetch(`check_reply.php?last_id=${lastMessageId}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' && data.messages) {
                    let scrolled = false;
                    
                    data.messages.forEach(msg => {
                        const mId = parseInt(msg.id);
                        
                        // STRICT DEDUPLICATION CHECK
                        if (displayedMessageIds.has(mId)) {
                            return; // Skip if already shown
                        }

                        let msgClass = 'ai'; // Default Admin
                        if (msg.sender === 'system') msgClass = 'system';
                        if (msg.sender === 'user') msgClass = 'user';

                        chatbox.appendChild(createChatLi(msg.message, msgClass));
                        
                        // Mark as shown
                        displayedMessageIds.add(mId);
                        lastMessageId = Math.max(lastMessageId, mId);
                        scrolled = true;
                    });

                    if(scrolled) {
                        chatbox.scrollTo(0, chatbox.scrollHeight);
                    }
                }
            })
            .catch(err => console.error("Polling error:", err));
        }, 3000); 
    }

    // Initial Poll
    startPolling();

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