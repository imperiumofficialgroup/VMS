<?php
session_start();
require_once '../auth/db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../admin/login.php");
    exit();
}

$admin_id = 1; // only one admin
$volunteer_id = $_GET['volunteer_id'] ?? null;

if (!$volunteer_id) {
    die("Volunteer ID missing.");
}

// Fetch volunteer details
$volunteer_stmt = $conn->prepare("SELECT full_name, email, profile_image FROM volunteers WHERE id = ?");
$volunteer_stmt->bind_param("i", $volunteer_id);
$volunteer_stmt->execute();
$volunteer_result = $volunteer_stmt->get_result();
$volunteer = $volunteer_result->fetch_assoc();

if (!$volunteer) {
    die("Volunteer not found.");
}
$volunteer_name = $volunteer['full_name'];
$volunteer_email = $volunteer['email'];
$profile_image = $volunteer['profile_image'];

// Fetch messages
$stmt = $conn->prepare("
    SELECT * FROM messages 
    WHERE 
      (sender_id = ? AND sender_role = 'admin' AND receiver_id = ? AND receiver_role = 'volunteer') OR
      (sender_id = ? AND sender_role = 'volunteer' AND receiver_id = ? AND receiver_role = 'admin')
    ORDER BY sent_at ASC
");
$stmt->bind_param("iiii", $admin_id, $volunteer_id, $volunteer_id, $admin_id);
$stmt->execute();
$messages = $stmt->get_result();

// Mark messages as read
$markRead = $conn->prepare("
    UPDATE messages SET is_read = 1 
    WHERE receiver_id = ? AND receiver_role = 'admin' 
    AND sender_id = ? AND sender_role = 'volunteer' AND is_read = 0
");
$markRead->bind_param("ii", $admin_id, $volunteer_id);
$markRead->execute();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with <?= htmlspecialchars($volunteer_name) ?> | VMS</title>
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #FBFCFC;
            color: #1C2833;
        }
        
        .chat-container {
            height: calc(100vh - 200px);
        }
        
        @media (min-width: 768px) {
            .chat-container {
                height: calc(100vh - 180px);
            }
        }
        
        .message-animation {
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .scrollbar-thin::-webkit-scrollbar {
            width: 6px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-track {
            background: #f7fafc;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background-color: rgba(156, 163, 175, 0.5);
            border-radius: 3px;
        }
        
        .message-input {
            width: 100%;
            padding: 12px 16px;
            border-radius: 24px;
            border: 1px solid #D5D8DC;
            background-color: white;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            color: #1C2833;
        }
        
        .message-input:focus {
            outline: none;
            border-color: #5DADE2;
            box-shadow: 0 0 0 3px rgba(93, 173, 226, 0.1);
        }
        
        .send-btn {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            padding: 12px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(93, 173, 226, 0.3);
        }
        
        .admin-message {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            border-radius: 18px 18px 4px 18px;
        }
        
        .volunteer-message {
            background: white;
            color: #1C2833;
            border: 1px solid #D5D8DC;
            border-radius: 18px 18px 18px 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .chat-header {
            background: white;
            border-bottom: 1px solid #D5D8DC;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        /* Mobile First Responsive Design */
        @media (max-width: 640px) {
            .page-container {
                padding-top: 0;
            }
            
            .chat-header {
                position: sticky;
                top: 0;
                z-index: 40;
            }
            
            .chat-container {
                height: calc(100vh - 160px);
            }
        }
        
        @media (min-width: 641px) and (max-width: 768px) {
            .page-container {
                padding-top: 0;
            }
            
            .chat-container {
                height: calc(100vh - 170px);
            }
        }
        
        @media (min-width: 769px) {
            .page-container {
                width: calc(100% - 16rem);
                margin-left: 16rem;
                padding-top: 0;
            }
            
            .chat-header {
                position: sticky;
                top: 0;
                z-index: 40;
            }
        }
        
        .back-btn {
            background: rgba(93, 173, 226, 0.1);
            color: #5DADE2;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid rgba(93, 173, 226, 0.2);
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: #5DADE2;
            color: white;
        }
        
        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .typing-indicator {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background: white;
            border: 1px solid #D5D8DC;
            border-radius: 18px;
            color: #1C2833/60;
            font-size: 0.875rem;
        }
        
        .typing-dots {
            display: inline-flex;
            margin-left: 8px;
        }
        
        .typing-dots span {
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background-color: #1C2833/60;
            margin: 0 1px;
            animation: typing 1.4s infinite ease-in-out;
        }
        
        .typing-dots span:nth-child(1) { animation-delay: -0.32s; }
        .typing-dots span:nth-child(2) { animation-delay: -0.16s; }
        
        @keyframes typing {
            0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; }
            40% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>
    <?php include '../admin/sidebar.php'; ?>
    
    <div class="page-container flex flex-col h-screen">
        <!-- Chat Header - Fixed at top -->
        <div class="chat-header px-4 py-3">
            <div class="max-w-4xl mx-auto">
                <div class="flex items-center justify-between">
                    <!-- Back button and Volunteer Info -->
                    <div class="flex items-center space-x-4">
                        <!-- Back Button -->
                        <a href="chat_select_volunteer.php" class="back-btn flex items-center">
                            <i class="fas fa-arrow-left mr-2"></i>
                            <span class="hidden sm:inline">Back</span>
                        </a>
                        
                        <!-- Volunteer Info -->
                        <div class="flex items-center space-x-3">
                            <?php if ($profile_image): ?>
                                <img src="../<?= htmlspecialchars($profile_image) ?>" 
                                     alt="<?= htmlspecialchars($volunteer_name) ?>"
                                     class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm">
                            <?php else: ?>
                                <div class="profile-avatar">
                                    <?= strtoupper(substr($volunteer_name, 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div>
                                <h1 class="font-semibold text-text text-lg"><?= htmlspecialchars($volunteer_name) ?></h1>
                                <p class="text-text/60 text-sm flex items-center">
                                    <i class="fas fa-circle text-xs mr-1 text-green-500"></i>
                                    <span class="hidden sm:inline mr-1">Online •</span>
                                    <?= htmlspecialchars($volunteer_email) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                  
                </div>
            </div>
        </div>

        <!-- Chat Messages Container -->
        <div class="chat-container overflow-y-auto scrollbar-thin bg-background flex-1">
            <div class="max-w-4xl mx-auto p-4 space-y-4">
                <?php if ($messages->num_rows > 0): ?>
                    <?php while ($msg = $messages->fetch_assoc()): ?>
                        <div class="message-animation flex <?= $msg['sender_role'] === 'admin' ? 'justify-end' : 'justify-start' ?>">
                            <div class="max-w-xs sm:max-w-sm md:max-w-md lg:max-w-lg">
                                <div class="<?= $msg['sender_role'] === 'admin' ? 'admin-message' : 'volunteer-message' ?> px-4 py-3 shadow-sm">
                                    <p class="break-words text-sm md:text-base"><?= htmlspecialchars($msg['message']) ?></p>
                                </div>
                                <div class="text-xs text-text/60 mt-1 flex items-center <?= $msg['sender_role'] === 'admin' ? 'justify-end' : 'justify-start' ?> space-x-1">
                                    <span><?= date('g:i A', strtotime($msg['sent_at'])) ?></span>
                                    <?php if ($msg['sender_role'] === 'admin' && $msg['is_read']): ?>
                                        <span class="text-primary"><i class="fas fa-check-double"></i></span>
                                    <?php elseif ($msg['sender_role'] === 'admin'): ?>
                                        <span class="text-text/40"><i class="fas fa-check"></i></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    
                    <!-- Typing Indicator (Example - can be dynamic) -->
                    <!--
                    <div class="flex justify-start">
                        <div class="typing-indicator">
                            <span>typing</span>
                            <div class="typing-dots">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>
                    </div>
                    -->
                <?php else: ?>
                    <!-- Empty Chat State -->
                    <div class="flex flex-col items-center justify-center h-full text-center py-12">
                        <div class="text-text/30 mb-4">
                            <i class="fas fa-comments text-5xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-text mb-2">No messages yet</h3>
                        <p class="text-text/60 max-w-md">
                            Start a conversation with <?= htmlspecialchars($volunteer_name) ?> by sending your first message.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Message Input Area -->
        <div class="bg-white border-t border-subtle p-4">
            <form id="chatForm" class="max-w-4xl mx-auto flex items-center space-x-3">
                <input type="hidden" name="receiver_id" value="<?= $volunteer_id ?>">
                <input type="hidden" name="sender_role" value="admin">
                <input type="hidden" name="receiver_role" value="volunteer">
                
                
                <!-- Message Input -->
                <div class="flex-1 relative">
                    <input name="message" 
                           class="message-input pr-12" 
                           placeholder="Type your message..." 
                           required
                           autocomplete="off">
                    <button type="button" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-text/40 hover:text-primary transition-colors">
                    </button>
                </div>
                
                <!-- Send Button -->
                <button type="submit" class="send-btn">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('chatForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const form = new FormData(this);
            const messageInput = this.querySelector('input[name="message"]');
            const message = messageInput.value.trim();
            
            if (!message) return;
            
            try {
                // Create temporary message in UI
                const chatContainer = document.querySelector('.chat-container > div');
                const tempMsg = document.createElement('div');
                tempMsg.className = 'message-animation flex justify-end';
                tempMsg.innerHTML = `
                    <div class="max-w-xs sm:max-w-sm md:max-w-md lg:max-w-lg">
                        <div class="admin-message px-4 py-3 shadow-sm">
                            <p class="break-words text-sm md:text-base">${message}</p>
                        </div>
                        <div class="text-xs text-text/60 mt-1 flex items-center justify-end space-x-1">
                            <span>Just now</span>
                            <span class="text-text/40"><i class="fas fa-check"></i></span>
                        </div>
                    </div>
                `;
                chatContainer.appendChild(tempMsg);
                
                // Clear input
                messageInput.value = '';
                
                // Scroll to bottom
                scrollToBottom();
                
                // Send to server
                const response = await fetch('send_message.php', {
                    method: 'POST',
                    body: form
                });
                
                if (response.ok) {
                    // Message sent successfully, reload to get actual timestamps
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                } else {
                    // Show error and remove temporary message
                    tempMsg.remove();
                    alert('Failed to send message. Please try again.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while sending the message');
            }
        });

        // Auto-scroll to bottom of chat
        function scrollToBottom() {
            const chatContainer = document.querySelector('.chat-container');
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }
        
        // Focus message input on load
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('input[name="message"]').focus();
            scrollToBottom();
        });
        
        // Auto-focus input when clicking anywhere in chat area
        document.querySelector('.chat-container').addEventListener('click', function() {
            document.querySelector('input[name="message"]').focus();
        });
    </script>
</body>
</html>