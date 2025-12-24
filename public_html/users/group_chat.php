<?php
session_start();
require_once '../auth/db.php';

if (!isset($_SESSION['volunteer_id'])) {
    header("Location: ../auth/volunteer_login.php");
    exit();
}

$volunteer_id = $_SESSION['volunteer_id'];
$group_id = $_GET['group_id'] ?? null;
if (!$group_id) die("Group ID missing.");

// Confirm this volunteer is a member
$check = $conn->prepare("SELECT * FROM group_members WHERE group_id = ? AND user_id = ?");
$check->bind_param("ii", $group_id, $volunteer_id);
$check->execute();
if ($check->get_result()->num_rows === 0) die("Access denied.");

// Mark unread messages as read
$markRead = $conn->prepare("
    UPDATE group_messages SET is_read = 1 
    WHERE group_id = ? AND sender_role = 'admin' AND is_read = 0
");
$markRead->bind_param("i", $group_id);
$markRead->execute();

// Fetch messages
$stmt = $conn->prepare("SELECT * FROM group_messages WHERE group_id = ? ORDER BY sent_at ASC");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$messages = $stmt->get_result();

// Fetch group name and event info
$groupInfo = $conn->prepare("
    SELECT gc.group_name, e.title AS event_title, e.event_date 
    FROM group_chats gc 
    LEFT JOIN events e ON gc.event_id = e.event_id 
    WHERE gc.id = ?
");
$groupInfo->bind_param("i", $group_id);
$groupInfo->execute();
$group_data = $groupInfo->get_result()->fetch_assoc();
$group_name = $group_data['group_name'];
$event_title = $group_data['event_title'] ?? 'General Group';
$event_date = $group_data['event_date'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Chat: <?= htmlspecialchars($group_name) ?> | VMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #FBFCFC;
            color: #1C2833;
        }

        .chat-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #D5D8DC;
        }

        .message-bubble {
            border-radius: 18px;
            padding: 12px 16px;
            max-width: 85%;
            word-wrap: break-word;
        }

        .user-message {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            margin-left: auto;
        }

        .admin-message {
            background: #F3F4F6;
            color: #1C2833;
            margin-right: auto;
        }

        .send-btn {
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
        }

        .send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(93, 173, 226, 0.3);
        }

        .form-input {
            border: 1px solid #D5D8DC;
            border-radius: 24px;
            padding: 12px 20px;
            transition: all 0.2s ease;
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: #5DADE2;
            box-shadow: 0 0 0 3px rgba(93, 173, 226, 0.1);
        }

        /* Mobile First Responsive Design */
        @media (max-width: 640px) {
            .page-container {
                padding: 0;
            }
            
            .chat-header {
                border-radius: 0;
            }
            
            .message-bubble {
                max-width: 90%;
            }
        }

        @media (min-width: 641px) and (max-width: 768px) {
            .page-container {
                padding: 1rem;
            }
        }

        @media (min-width: 769px) {
            .page-container {
                padding-right: 2rem;
                width: calc(100% - 16rem);
                margin-left: 16rem;
                max-width: none;
            }
        }

        /* Custom scrollbar */
        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: #5DADE2;
            border-radius: 10px;
        }

        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: #A569BD;
        }

        /* Animation for new messages */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message-animation {
            animation: slideIn 0.3s ease-out;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="page-container lg:ml-72 h-screen flex flex-col">
        <!-- Chat Header -->
<div class="chat-header bg-white border-b border-subtle p-4 md:p-6 mb-4 md:mb-6 mt-14 md:mt-0">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="group_list.php" class="text-primary hover:text-accent transition-colors md:hidden">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </a>
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 md:w-12 md:h-12 rounded-xl bg-gradient-to-r from-primary to-accent flex items-center justify-center">
                            <i class="fas fa-users text-white text-lg md:text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-lg md:text-xl font-bold text-text"><?= htmlspecialchars($group_name) ?></h1>
                            <p class="text-text/60 text-sm">
                                <i class="fas fa-calendar-alt mr-1"></i>
                                <?= htmlspecialchars($event_title) ?>
                                <?php if ($event_date): ?>
                                    • <?= date('M j, Y', strtotime($event_date)) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>

        <!-- Chat Area -->
        <div class="flex-1 flex flex-col">
            <!-- Messages Container -->
            <div id="chatBox" class="chat-messages flex-1 overflow-y-auto px-4 md:px-6 pb-4 space-y-4">
                <?php if ($messages->num_rows > 0): ?>
                    <?php while ($msg = $messages->fetch_assoc()): ?>
                        <div class="message-animation <?= $msg['sender_role'] === 'volunteer' ? 'flex justify-end' : 'flex justify-start' ?>">
                            <div class="message-bubble <?= $msg['sender_role'] === 'volunteer' ? 'user-message' : 'admin-message' ?>">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-semibold text-sm <?= $msg['sender_role'] === 'volunteer' ? 'text-blue-100' : 'text-primary' ?>">
                                        <?= $msg['sender_role'] === 'volunteer' ? 'You' : 'Admin' ?>
                                    </span>
                                    <span class="text-xs opacity-70 ml-2 <?= $msg['sender_role'] === 'volunteer' ? 'text-blue-100' : 'text-text/60' ?>">
                                        <?= date('h:i A', strtotime($msg['sent_at'])) ?>
                                    </span>
                                </div>
                                <p class="text-sm md:text-base"><?= htmlspecialchars($msg['message']) ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-12">
                        <div class="w-16 h-16 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-comments text-2xl text-text/30"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-text mb-2">No messages yet</h3>
                        <p class="text-text/60">Start the conversation by sending a message!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Message Input -->
            <div class="border-t border-subtle bg-white p-4 md:p-6">
                <form id="chatForm" class="flex items-center space-x-3">
                    <input type="hidden" name="group_id" value="<?= $group_id ?>">
                    <input type="hidden" name="sender_id" value="<?= $volunteer_id ?>">
                    <input type="hidden" name="sender_role" value="volunteer">
                    
                    <div class="flex-1 relative">
                        <input name="message" 
                               class="form-input w-full pr-12" 
                               placeholder="Type your message..." 
                               required
                               autocomplete="off">
                        <button type="button" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-text/40 hover:text-primary transition-colors">
                        </button>
                    </div>
                    
                    <button type="submit" class="send-btn flex-shrink-0">
                        <i class="fas fa-paper-plane text-lg"></i>
                    </button>
                </form>
                
            </div>
        </div>
    </div>

    <script>
        document.getElementById('chatForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const form = new FormData(this);
            const messageInput = this.querySelector('input[name="message"]');
            const messageText = messageInput.value.trim();
            
            if (!messageText) return;
            
            // Add temporary message to UI
            const tempMessage = createTempMessage(messageText);
            document.getElementById('chatBox').appendChild(tempMessage);
            scrollToBottom();
            
            // Clear input
            messageInput.value = '';
            
            // Send to server
            fetch('../admin/send_group_message.php', {
                method: 'POST',
                body: form
            }).then(response => {
                if (response.ok) {
                    // Remove temp message and reload actual messages
                    tempMessage.remove();
                    location.reload();
                }
            }).catch(error => {
                console.error('Error:', error);
                tempMessage.remove();
                alert('Failed to send message. Please try again.');
            });
        });

        function createTempMessage(text) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'flex justify-end message-animation';
            messageDiv.innerHTML = `
                <div class="message-bubble user-message opacity-80">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-semibold text-sm text-blue-100">You</span>
                        <span class="text-xs opacity-70 ml-2 text-blue-100">Sending...</span>
                    </div>
                    <p class="text-sm md:text-base">${text}</p>
                </div>
            `;
            return messageDiv;
        }

        function scrollToBottom() {
            const chatBox = document.getElementById('chatBox');
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        // Scroll to bottom initially and after load
        window.onload = function() {
            scrollToBottom();
        };

        // Auto-scroll when new messages are added
        const observer = new MutationObserver(scrollToBottom);
        observer.observe(document.getElementById('chatBox'), {
            childList: true,
            subtree: true
        });

        // Handle Enter key to submit form
        document.querySelector('input[name="message"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('chatForm').dispatchEvent(new Event('submit'));
            }
        });
    </script>
</body>
</html>