<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$hbs_chat_user_name = $_SESSION['user_name'] ?? ($_SESSION['user']['name'] ?? 'Guest');
$is_direct_chat_page = (basename($_SERVER['PHP_SELF']) === 'chat.php');
?>
<?php if ($is_direct_chat_page): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot - Handicraft by Sonia</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body style="font-family: 'Poppins', sans-serif; background: #faf7ff; margin: 0;">
<?php endif; ?>

<!-- Bootstrap Icons fallback if not already loaded in parent page -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    .hbs-chatbot-widget {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 99999;
        font-family: 'Poppins', sans-serif;
    }

    /* Floating Launcher Button */
    .hbs-chat-launcher {
        width: 62px;
        height: 62px;
        border-radius: 50%;
        background: linear-gradient(135deg, #5b21b6, #ec4899);
        color: #ffffff;
        border: none;
        box-shadow: 0 8px 25px rgba(91, 33, 182, 0.38);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.7rem;
        transition: transform 0.25s ease, box-shadow 0.25s ease;
        position: relative;
    }

    .hbs-chat-launcher:hover {
        transform: scale(1.08);
        box-shadow: 0 12px 30px rgba(236, 72, 153, 0.45);
    }

    .hbs-chat-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: #10b981;
        color: #fff;
        font-size: 0.68rem;
        font-weight: 600;
        padding: 2px 7px;
        border-radius: 12px;
        border: 2px solid #fff;
    }

    /* Popup Chat Window */
    .hbs-chat-popup {
        position: absolute;
        bottom: 78px;
        right: 0;
        width: 360px;
        height: 500px;
        max-height: calc(100vh - 120px);
        background: #ffffff;
        border-radius: 18px;
        box-shadow: 0 15px 40px rgba(48, 36, 58, 0.22);
        border: 1px solid #ede9fe;
        display: none;
        flex-direction: column;
        overflow: hidden;
        animation: hbsChatSlideUp 0.25s ease-out;
    }

    .hbs-chat-popup.open {
        display: flex;
    }

    @keyframes hbsChatSlideUp {
        from {
            opacity: 0;
            transform: translateY(18px) scale(0.96);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    /* Header */
    .hbs-chat-header {
        background: linear-gradient(135deg, #4c1d95, #7c3aed);
        color: #ffffff;
        padding: 14px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .hbs-chat-header-left {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .hbs-bot-icon {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    .hbs-chat-title h4 {
        margin: 0;
        font-size: 0.96rem;
        font-weight: 600;
        color: #fff;
    }

    .hbs-chat-title span {
        font-size: 0.74rem;
        opacity: 0.9;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .hbs-chat-close {
        background: rgba(255, 255, 255, 0.15);
        border: none;
        color: #ffffff;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        transition: background 0.2s;
    }

    .hbs-chat-close:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    /* Messages Area */
    .hbs-chat-body {
        flex: 1;
        padding: 15px;
        overflow-y: auto;
        background: #faf7ff;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .hbs-msg {
        display: flex;
        flex-direction: column;
        max-width: 82%;
    }

    .hbs-msg.customer {
        align-self: flex-end;
        align-items: flex-end;
    }

    .hbs-msg.bot {
        align-self: flex-start;
        align-items: flex-start;
    }

    .hbs-msg-bubble {
        padding: 10px 14px;
        border-radius: 14px;
        font-size: 0.86rem;
        line-height: 1.45;
        white-space: pre-line;
        word-wrap: break-word;
    }

    .hbs-msg.customer .hbs-msg-bubble {
        background: linear-gradient(135deg, #5b21b6, #7c3aed);
        color: #ffffff;
        border-bottom-right-radius: 3px;
    }

    .hbs-msg.bot .hbs-msg-bubble {
        background: #ffffff;
        color: #30243a;
        border: 1px solid #e9d5ff;
        border-bottom-left-radius: 3px;
        box-shadow: 0 2px 6px rgba(91, 33, 182, 0.04);
    }

    .hbs-msg-time {
        font-size: 0.68rem;
        color: #9ca3af;
        margin-top: 3px;
        padding: 0 3px;
    }

    /* Quick Prompt Chips */
    .hbs-chat-chips {
        padding: 8px 12px;
        background: #f3e8ff;
        display: flex;
        gap: 6px;
        overflow-x: auto;
        scrollbar-width: none;
    }

    .hbs-chat-chips::-webkit-scrollbar {
        display: none;
    }

    .hbs-chip {
        background: #ffffff;
        color: #5b21b6;
        border: 1px solid #d8b4fe;
        border-radius: 15px;
        padding: 4px 10px;
        font-size: 0.74rem;
        font-family: 'Poppins', sans-serif;
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.2s;
    }

    .hbs-chip:hover {
        background: #5b21b6;
        color: #ffffff;
    }

    /* Input Area */
    .hbs-chat-footer {
        padding: 10px 12px;
        background: #ffffff;
        border-top: 1px solid #ede9fe;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .hbs-chat-input {
        flex: 1;
        padding: 9px 14px;
        border: 1.5px solid #ddd6fe;
        border-radius: 22px;
        font-family: 'Poppins', sans-serif;
        font-size: 0.86rem;
        color: #30243a;
        outline: none;
    }

    .hbs-chat-input:focus {
        border-color: #7c3aed;
    }

    .hbs-chat-send {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: linear-gradient(135deg, #5b21b6, #ec4899);
        color: #ffffff;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
        flex-shrink: 0;
    }

    @media (max-width: 480px) {
        .hbs-chat-popup {
            width: calc(100vw - 32px);
            right: -8px;
            height: 440px;
        }
    }
</style>

<div class="hbs-chatbot-widget" id="hbsChatbotWidget">
    <!-- Popup Chat Box -->
    <div class="hbs-chat-popup <?= $is_direct_chat_page ? 'open' : '' ?>" id="hbsChatPopup">
        <div class="hbs-chat-header">
            <div class="hbs-chat-header-left">
                <div class="hbs-bot-icon"><i class="bi bi-chat-heart-fill"></i></div>
                <div class="hbs-chat-title">
                    <h4>Handicraft by Sonia</h4>
                    <span><i class="bi bi-circle-fill" style="color: #34d399; font-size: 0.5rem;"></i> Online Assistant</span>
                </div>
            </div>
            <button type="button" class="hbs-chat-close" id="hbsChatCloseBtn" title="Minimize">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="hbs-chat-body" id="hbsChatBody">
            <div class="hbs-msg bot">
                <div class="hbs-msg-bubble">Assalamu Alaikum, <?= htmlspecialchars($hbs_chat_user_name) ?>! 💜 Welcome to Handicraft by Sonia. How can I help you today?</div>
                <span class="hbs-msg-time">Bot</span>
            </div>
        </div>

        <div class="hbs-chat-chips">
            <button type="button" class="hbs-chip" onclick="hbsSendQuickMsg('What products do you have?')">Products & Prices</button>
            <button type="button" class="hbs-chip" onclick="hbsSendQuickMsg('What is the delivery charge?')">Delivery Charge</button>
            <button type="button" class="hbs-chip" onclick="hbsSendQuickMsg('How can I request a custom order?')">Custom Order</button>
            <button type="button" class="hbs-chip" onclick="hbsSendQuickMsg('How can I contact you?')">Contact Info</button>
        </div>

        <form class="hbs-chat-footer" id="hbsChatForm">
            <input type="text" class="hbs-chat-input" id="hbsChatInput" placeholder="Write a message..." autocomplete="off" required>
            <button type="submit" class="hbs-chat-send" title="Send">
                <i class="bi bi-send-fill"></i>
            </button>
        </form>
    </div>

    <!-- Floating Bottom-Right Button -->
    <button type="button" class="hbs-chat-launcher" id="hbsChatLauncher" title="Chat with us">
        <span class="hbs-chat-badge">Live</span>
        <i class="bi bi-chat-dots-fill" id="hbsLauncherIcon"></i>
    </button>
</div>

<script>
(function() {
    const popup = document.getElementById('hbsChatPopup');
    const launcher = document.getElementById('hbsChatLauncher');
    const closeBtn = document.getElementById('hbsChatCloseBtn');
    const launcherIcon = document.getElementById('hbsLauncherIcon');
    const chatBody = document.getElementById('hbsChatBody');
    const chatForm = document.getElementById('hbsChatForm');
    const chatInput = document.getElementById('hbsChatInput');
    let historyLoaded = false;

    function toggleChat() {
        const isOpen = popup.classList.toggle('open');
        launcherIcon.className = isOpen ? 'bi bi-x-lg' : 'bi bi-chat-dots-fill';
        if (isOpen) {
            if (!historyLoaded) {
                loadHbsChatHistory();
                historyLoaded = true;
            }
            setTimeout(() => chatInput.focus(), 150);
            chatBody.scrollTop = chatBody.scrollHeight;
        }
    }

    launcher.addEventListener('click', toggleChat);
    closeBtn.addEventListener('click', toggleChat);

    function appendHbsMsg(sender, message, timeLabel) {
        const wrapper = document.createElement('div');
        wrapper.className = 'hbs-msg ' + (sender === 'customer' ? 'customer' : 'bot');

        const bubble = document.createElement('div');
        bubble.className = 'hbs-msg-bubble';
        bubble.textContent = message;

        const time = document.createElement('span');
        time.className = 'hbs-msg-time';
        time.textContent = (sender === 'customer' ? 'You' : 'Bot') + (timeLabel ? ' • ' + timeLabel : '');

        wrapper.appendChild(bubble);
        wrapper.appendChild(time);
        chatBody.appendChild(wrapper);
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    async function loadHbsChatHistory() {
        try {
            const res = await fetch('api/chat.php');
            const data = await res.json();
            if (data.success && Array.isArray(data.messages) && data.messages.length > 0) {
                chatBody.innerHTML = '';
                data.messages.forEach(item => {
                    appendHbsMsg(item.sender, item.message, item.created_at);
                });
            }
        } catch (err) {
            console.error('Chat history error:', err);
        }
    }

    async function sendHbsChatMessage(text) {
        const msg = text.trim();
        if (!msg) return;

        appendHbsMsg('customer', msg, 'Just now');
        chatInput.value = '';

        try {
            const formData = new FormData();
            formData.append('message', msg);

            const res = await fetch('api/chat.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success && data.bot_message) {
                appendHbsMsg('bot', data.bot_message.message, data.bot_message.created_at);
            } else {
                appendHbsMsg('bot', 'Sorry, I could not process that right now.');
            }
        } catch (err) {
            appendHbsMsg('bot', 'Network error! Please check your connection.');
        }
    }

    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        sendHbsChatMessage(chatInput.value);
    });

    window.hbsSendQuickMsg = function(text) {
        if (!popup.classList.contains('open')) {
            popup.classList.add('open');
        }
        sendHbsChatMessage(text);
    };

    // যদি সরাসরি chat.php পেজে ওপেন করা হয় তবে শুরুতেই হিস্ট্রি লোড হবে
    if (popup.classList.contains('open')) {
        loadHbsChatHistory();
        historyLoaded = true;
    }
})();
</script>

<?php if ($is_direct_chat_page): ?>
</body>
</html>
<?php endif; ?>