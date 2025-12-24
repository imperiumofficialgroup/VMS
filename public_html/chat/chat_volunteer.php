<?php
session_start();
require_once '../auth/db.php';

if (!isset($_SESSION['volunteer_id'])) {
    header("Location: ../auth/volunteer_login.php");
    exit();
}

$volunteer_id = $_SESSION['volunteer_id'];
$admin_id = 1; // Assuming there's only one admin

// Fetch messages
$stmt = $conn->prepare("
    SELECT * FROM messages 
    WHERE 
      (sender_id = ? AND sender_role = 'volunteer' AND receiver_id = ? AND receiver_role = 'admin') OR
      (sender_id = ? AND sender_role = 'admin' AND receiver_id = ? AND receiver_role = 'volunteer')
    ORDER BY sent_at ASC
");
$stmt->bind_param("iiii", $volunteer_id, $admin_id, $admin_id, $volunteer_id);
$stmt->execute();
$messages = $stmt->get_result();

$markRead = $conn->prepare("
    UPDATE messages SET is_read = 1 
    WHERE receiver_id = ? AND receiver_role = 'volunteer' 
      AND sender_id = ? AND sender_role = 'admin' 
      AND is_read = 0
");
$markRead->bind_param("ii", $volunteer_id, $admin_id);
$markRead->execute();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with Admin | VMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: '#5DADE2',
                        accent: '#A569BD',
                        background: '#FBFCFC',
                        text: '#1C2833',
                        subtle: '#D5D8DC',
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #FBFCFC;
            color: #1C2833;
        }

        .chat-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        .chat-header {
            background: white;
            border-bottom: 1px solid #D5D8DC;
            flex-shrink: 0;
        }

        .chat-messages {
            background: #FBFCFC;
            overflow-y: auto;
            flex: 1;
        }

        .chat-input-area {
            background: white;
            border-top: 1px solid #D5D8DC;
            flex-shrink: 0;
        }

        .message-bubble {
            max-width: 80%;
            border-radius: 18px;
            padding: 12px 16px;
            word-wrap: break-word;
        }

        .message-out {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message-in {
            background: white;
            color: #1C2833;
            border: 1px solid #D5D8DC;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .message-input {
            background: white;
            border: 1px solid #D5D8DC;
            border-radius: 24px;
            padding: 12px 20px;
            transition: all 0.3s ease;
            width: 100%;
        }

        .message-input:focus {
            outline: none;
            border-color: #5DADE2;
            box-shadow: 0 0 0 3px rgba(93, 173, 226, 0.1);
        }

        .send-button {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            border: none;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            cursor: pointer;
            flex-shrink: 0;
        }

        .send-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(93, 173, 226, 0.3);
        }

        .send-button:active {
            transform: translateY(0);
        }

        .scrollbar-thin::-webkit-scrollbar {
            width: 4px;
        }

        .scrollbar-thin::-webkit-scrollbar-track {
            background: transparent;
        }

        .scrollbar-thin::-webkit-scrollbar-thumb {
            background-color: rgba(93, 173, 226, 0.3);
            border-radius: 2px;
        }

        .message-time {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 4px;
        }

        .read-receipt {
            color: rgba(255, 255, 255, 0.7);
            margin-left: 6px;
        }

        .empty-chat {
            background: linear-gradient(135deg, #FBFCFC 0%, #f8fafc 100%);
            border: 2px dashed #D5D8DC;
        }

        /* Mobile First Responsive Design */
        @media (max-width: 768px) {
            .mobile-chat-container {
                height: 100vh;
                width: 100vw;
                position: fixed;
                top: 0;
                left: 0;
                background: white;
                z-index: 50;
            }

            .message-bubble {
                max-width: 85%;
            }

            .chat-header {
                position: sticky;
                top: 0;
                z-index: 40;
                padding: 1rem;
            }

            .chat-input-area {
                position: sticky;
                bottom: 0;
                background: white;
                padding: 1rem;
                padding-bottom: calc(1rem + env(safe-area-inset-bottom));
            }

            .chat-messages {
                padding: 1rem;
            }
        }

        @media (min-width: 769px) {
            .desktop-container {
                display: flex;
                height: 100vh;
            }

            .sidebar-area {
                width: 18rem;
                flex-shrink: 0;
            }

            .chat-area {
                flex: 1;
                display: flex;
                flex-direction: column;
            }

            .chat-container {
                height: 100vh;
            }

            .chat-header {
                position: sticky;
                top: 0;
                z-index: 40;
            }
        }
    </style>
</head>
<body class="bg-background">
    <!-- Mobile View -->
    <div class="mobile-chat-container lg:hidden">
        <div class="chat-container">
            <!-- Chat Header -->
            <div class="chat-header">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <a href="../users/profile.php" class="text-primary hover:text-accent transition-colors">
                            <i class="fas fa-arrow-left text-lg"></i>
                        </a>
                        <div>
                            <h1 class="text-xl font-semibold text-text">Admin Support</h1>
                            <p class="text-sm text-text/60">Chat with platform administrator</p>
                        </div>
                    </div>
                    <div class="text-sm text-text/60">
                        <?= date('M j') ?>
                    </div>
                </div>
            </div>

            <!-- Messages Area -->
            <div class="chat-messages scrollbar-thin">
                <div class="space-y-4">
                    <?php if ($messages->num_rows > 0): ?>
                        <?php while ($msg = $messages->fetch_assoc()): ?>
                            <?php $isVolunteer = $msg['sender_id'] == $volunteer_id && $msg['sender_role'] === 'volunteer'; ?>
                            <div class="flex <?= $isVolunteer ? 'justify-end' : 'justify-start' ?>">
                                <div class="message-bubble <?= $isVolunteer ? 'message-out' : 'message-in' ?>">
                                    <div class="break-words text-sm leading-relaxed">
                                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                    </div>
                                    <div class="message-time <?= $isVolunteer ? 'text-right' : 'text-left' ?>">
                                        <?= date('g:i A', strtotime($msg['sent_at'])) ?>
                                        <?php if ($isVolunteer && $msg['is_read']): ?>
                                            <span class="read-receipt">
                                                <i class="fas fa-check-double text-xs"></i>
                                            </span>
                                        <?php elseif ($isVolunteer): ?>
                                            <span class="read-receipt">
                                                <i class="fas fa-check text-xs"></i>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <!-- Empty Chat State -->
                        <div class="empty-chat rounded-2xl p-8 text-center mt-8">
                            <div class="text-text/30 mb-4">
                                <i class="fas fa-comments text-4xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-text mb-2">No messages yet</h3>
                            <p class="text-text/60">
                                Start a conversation with the admin by sending your first message.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Input Area -->
            <div class="chat-input-area">
                <form id="chatFormMobile" class="flex items-center gap-3">
                    <input type="hidden" name="sender_id" value="<?= $volunteer_id ?>">
                    <input type="hidden" name="receiver_id" value="<?= $admin_id ?>">
                    <input type="hidden" name="sender_role" value="volunteer">
                    <input type="hidden" name="receiver_role" value="admin">
                    
                    <div class="flex-1">
                        <input name="message" 
                               class="message-input"
                               placeholder="Type your message..."
                               required
                               autocomplete="off">
                    </div>
                    
                    <button type="submit" class="send-button">
                        <i class="fas fa-paper-plane text-sm"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Desktop View -->
    <div class="desktop-container hidden lg:flex">
        <!-- Sidebar -->
        <div class="sidebar-area">
            <?php include '../users/sidebar.php'; ?>
        </div>

        <!-- Chat Area -->
        <div class="chat-area">
            <div class="chat-container">
                <!-- Chat Header -->
                <div class="chat-header px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div>
                                <h1 class="text-xl font-semibold text-text">Admin Support</h1>
                                <p class="text-sm text-text/60">Chat with platform administrator</p>
                            </div>
                        </div>
                        <div class="text-sm text-text/60">
                            <?= date('F j, Y') ?>
                        </div>
                    </div>
                </div>

                <!-- Messages Area -->
                <div class="chat-messages px-6 py-4 scrollbar-thin">
                    <div class="max-w-4xl mx-auto space-y-4">
                        <?php if ($messages->num_rows > 0): ?>
                            <?php while ($msg = $messages->fetch_assoc()): ?>
                                <?php $isVolunteer = $msg['sender_id'] == $volunteer_id && $msg['sender_role'] === 'volunteer'; ?>
                                <div class="flex <?= $isVolunteer ? 'justify-end' : 'justify-start' ?>">
                                    <div class="message-bubble <?= $isVolunteer ? 'message-out' : 'message-in' ?>">
                                        <div class="break-words text-sm leading-relaxed">
                                            <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                        </div>
                                        <div class="message-time <?= $isVolunteer ? 'text-right' : 'text-left' ?>">
                                            <?= date('g:i A', strtotime($msg['sent_at'])) ?>
                                            <?php if ($isVolunteer && $msg['is_read']): ?>
                                                <span class="read-receipt">
                                                    <i class="fas fa-check-double text-xs"></i>
                                                </span>
                                            <?php elseif ($isVolunteer): ?>
                                                <span class="read-receipt">
                                                    <i class="fas fa-check text-xs"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <!-- Empty Chat State -->
                            <div class="empty-chat rounded-2xl p-8 text-center mt-8">
                                <div class="text-text/30 mb-4">
                                    <i class="fas fa-comments text-4xl"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-text mb-2">No messages yet</h3>
                                <p class="text-text/60 max-w-md mx-auto">
                                    Start a conversation with the admin by sending your first message.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="chat-input-area px-6 py-4">
                    <form id="chatFormDesktop" class="max-w-4xl mx-auto flex items-center gap-3">
                        <input type="hidden" name="sender_id" value="<?= $volunteer_id ?>">
                        <input type="hidden" name="receiver_id" value="<?= $admin_id ?>">
                        <input type="hidden" name="sender_role" value="volunteer">
                        <input type="hidden" name="receiver_role" value="admin">
                        
                        <div class="flex-1">
                            <input name="message" 
                                   class="message-input"
                                   placeholder="Type your message..."
                                   required
                                   autocomplete="off">
                        </div>
                        
                        <button type="submit" class="send-button">
                            <i class="fas fa-paper-plane text-sm"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
// Function to handle form submission
async function handleChatSubmit(e, isMobile = false) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const messageInput = form.querySelector('input[name="message"]');
    const sendButton = form.querySelector('button[type="submit"]');
    const message = messageInput.value.trim();
    
    if (!message) return;
    
    try {
        // Disable form while sending
        messageInput.disabled = true;
        sendButton.disabled = true;
        sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        // Create temporary message in UI
        const messagesContainer = document.getElementById(isMobile ? 'messagesContainer' : 'messagesContainerDesktop');
        const emptyState = messagesContainer.querySelector('.empty-chat');
        
        if (emptyState) {
            emptyState.remove();
        }
        
        const tempMsg = document.createElement('div');
        tempMsg.className = 'flex justify-end';
        tempMsg.innerHTML = `
            <div class="max-w-${isMobile ? '[85%]' : '[70%]'}">
                <div class="bg-primary text-white rounded-2xl px-4 py-3 rounded-br-md shadow-sm">
                    <div class="break-words text-sm leading-relaxed">${message}</div>
                    <div class="text-xs mt-2 text-blue-100 text-right">
                        Sending...
                        <span class="ml-1">
                            <i class="fas fa-clock"></i>
                        </span>
                    </div>
                </div>
            </div>
        `;
        messagesContainer.appendChild(tempMsg);
        
        // Clear input
        messageInput.value = '';
        
        // Scroll to bottom
        scrollToBottom(isMobile);
        
        // Send to server
        const response = await fetch('send_message.php', {
            method: 'POST',
            body: formData
        });
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            const result = await response.json();
            
            if (result.success) {
                // Message sent successfully, update temporary message with actual timestamp
                tempMsg.querySelector('.text-xs').innerHTML = `
                    ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                    <span class="ml-1">
                        <i class="fas fa-check"></i>
                    </span>
                `;
                
                // Reload after a short delay to sync with other users
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                // Show error and remove temporary message
                tempMsg.remove();
                alert('Failed to send message: ' + (result.error || 'Please try again.'));
            }
        } else {
            // Handle non-JSON response (legacy support)
            const text = await response.text();
            if (response.ok) {
                tempMsg.querySelector('.text-xs').innerHTML = `
                    ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                    <span class="ml-1">
                        <i class="fas fa-check"></i>
                    </span>
                `;
                setTimeout(() => location.reload(), 2000);
            } else {
                tempMsg.remove();
                alert('Failed to send message: ' + text);
            }
        }
        
    } catch (error) {
        console.error('Error:', error);
        alert('Network error: Please check your connection and try again.');
    } finally {
        // Re-enable form
        messageInput.disabled = false;
        sendButton.disabled = false;
        sendButton.innerHTML = '<i class="fas fa-paper-plane text-sm"></i>';
    }
}

        // Auto-scroll to bottom of chat
        function scrollToBottom() {
            const chatMessages = document.querySelector('.chat-messages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }
        
        // Initialize event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile form
            const mobileForm = document.getElementById('chatFormMobile');
            if (mobileForm) {
                mobileForm.addEventListener('submit', (e) => handleChatSubmit(e, 'mobile'));
            }
            
            // Desktop form
            const desktopForm = document.getElementById('chatFormDesktop');
            if (desktopForm) {
                desktopForm.addEventListener('submit', (e) => handleChatSubmit(e, 'desktop'));
            }
            
            // Focus message input on load
            const messageInput = document.querySelector('input[name="message"]');
            if (messageInput) {
                messageInput.focus();
            }
            
            scrollToBottom();
            
            // Auto-focus input when clicking anywhere in chat area
            document.querySelectorAll('.chat-messages').forEach(area => {
                area.addEventListener('click', function() {
                    const input = document.querySelector('input[name="message"]');
                    if (input) input.focus();
                });
            });
        });

        // Handle mobile keyboard
        if (window.innerWidth < 768) {
            const input = document.querySelector('input[name="message"]');
            if (input) {
                input.addEventListener('focus', () => {
                    setTimeout(scrollToBottom, 300);
                });
            }
        }
    </script>
</body>
</html>