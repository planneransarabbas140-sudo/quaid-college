/**
 * Quaid-e-Azam Group of Colleges
 * AI Chatbot Frontend Logic
 */

document.addEventListener('DOMContentLoaded', function() {
    const scriptSrc = document.currentScript ? document.currentScript.src : '';
    const appRoot = scriptSrc.includes('/modules/chatbot/')
        ? scriptSrc.split('/modules/chatbot/')[0]
        : window.location.origin + '/quaid-college-system-main';
    const bubble = document.getElementById('chatbot-bubble');
    const window = document.getElementById('chatbot-window');
    const closeBtn = document.getElementById('chatbot-close');
    const sendBtn = document.getElementById('chatbot-send');
    const input = document.getElementById('chatbot-input');
    const messagesContainer = document.getElementById('chatbot-messages');

    let isProcessing = false;
    let chatHistory = [];

    // Toggle Chat Window
    bubble.addEventListener('click', () => {
        const isVisible = window.style.display === 'flex';
        window.style.display = isVisible ? 'none' : 'flex';
        if (!isVisible) input.focus();
    });

    closeBtn.addEventListener('click', () => {
        window.style.display = 'none';
    });

    // Send Message Function
    async function sendMessage() {
        const message = input.value.trim();
        if (!message || isProcessing) return;

        // Add user message to UI
        appendMessage('user', message);
        input.value = '';
        
        // Show typing indicator
        const typingId = showTypingIndicator();
        isProcessing = true;

        try {
            const response = await fetch(appRoot + '/modules/chatbot/chat.php', {
                method: 'POST',
                headers: { 'Content-Type: application/json' },
                body: JSON.stringify({ message: message })
            });

            const data = await response.json();
            removeTypingIndicator(typingId);

            if (data.reply) {
                appendMessage('ai', data.reply);
                updateHistory('user', message);
                updateHistory('assistant', data.reply);
            } else if (data.error) {
                appendMessage('ai', data.error, true);
            }
        } catch (error) {
            removeTypingIndicator(typingId);
            appendMessage('ai', 'Connection error. Please check your internet.', true);
            console.error('Chatbot Error:', error);
        } finally {
            isProcessing = false;
        }
    }

    // Append Message to UI
    function appendMessage(role, text, isError = false) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `cb-message cb-${role} ${isError ? 'cb-error' : ''}`;
        msgDiv.textContent = text;
        messagesContainer.appendChild(msgDiv);
        
        // Auto scroll
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Show/Hide Typing Indicator
    function showTypingIndicator() {
        const id = 'typing-' + Date.now();
        const typingDiv = document.createElement('div');
        typingDiv.id = id;
        typingDiv.className = 'cb-typing';
        typingDiv.innerHTML = '<div class="dot"></div><div class="dot"></div><div class="dot"></div>';
        messagesContainer.appendChild(typingDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        return id;
    }

    function removeTypingIndicator(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    // Update History (Last 10 messages)
    function updateHistory(role, content) {
        chatHistory.push({ role, content });
        if (chatHistory.length > 10) chatHistory.shift();
    }

    // Event Listeners
    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });

    // Initial greeting
    setTimeout(() => {
        if (messagesContainer.children.length === 0) {
            appendMessage('ai', 'Assalam-o-Alaikum! Welcome to Quaid-e-Azam Group of Colleges. How can I assist you today?');
        }
    }, 1000);
});
