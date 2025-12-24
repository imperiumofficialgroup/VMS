<?php
session_start();
require_once '../auth/db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../admin/login.php");
    exit();
}

$group_id = $_GET['group_id'] ?? null;
if (!$group_id) {
    die("Group ID missing.");
}

// Fetch group name
$groupQuery = $conn->prepare("SELECT group_name FROM group_chats WHERE id = ?");
$groupQuery->bind_param("i", $group_id);
$groupQuery->execute();
$groupResult = $groupQuery->get_result();
$group = $groupResult->fetch_assoc();
$group_name = $group['group_name'] ?? 'Unnamed Group';

// Mark messages as read for admin
$markRead = $conn->prepare("
    UPDATE group_messages SET is_read = 1 
    WHERE group_id = ? AND is_read = 0 AND sender_role = 'volunteer'
");
$markRead->bind_param("i", $group_id);
$markRead->execute();

// Fetch all messages
$stmt = $conn->prepare("
    SELECT * FROM group_messages 
    WHERE group_id = ? 
    ORDER BY sent_at ASC
");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$messages = $stmt->get_result();

// Fetch all volunteers
$volunteerList = $conn->query("SELECT id, full_name FROM volunteers ORDER BY full_name");

// Fetch current group members
$currentMembers = [];
$memberResult = $conn->prepare("SELECT user_id FROM group_members WHERE group_id = ?");
$memberResult->bind_param("i", $group_id);
$memberResult->execute();
$memberRes = $memberResult->get_result();
while ($row = $memberRes->fetch_assoc()) {
    $currentMembers[] = $row['user_id'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Chat - <?= htmlspecialchars($group_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #FBFCFC;
            color: #1C2833;
        }
        
        .chat-container {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e0 #f7fafc;
        }
        .chat-container::-webkit-scrollbar {
            width: 6px;
        }
        .chat-container::-webkit-scrollbar-track {
            background: #f7fafc;
        }
        .chat-container::-webkit-scrollbar-thumb {
            background-color: #cbd5e0;
            border-radius: 3px;
        }
        .message-animation {
            animation: fadeIn 0.3s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid #D5D8DC;
            background-color: white;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            color: #1C2833;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #5DADE2;
            box-shadow: 0 0 0 3px rgba(93, 173, 226, 0.1);
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(93, 173, 226, 0.3);
        }
        
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid #D5D8DC;
        }
        
        /* Mobile First Responsive Design */
        @media (max-width: 640px) {
            .page-container {
                padding: 1rem;
            }
            
            .chat-container {
                height: 60vh;
            }
        }
        
        @media (min-width: 641px) and (max-width: 768px) {
            .page-container {
                padding: 1.5rem;
            }
            
            .chat-container {
                height: 65vh;
            }
        }
        
        @media (min-width: 769px) {
            .page-container {
                padding: 2rem;
                width: calc(100% - 16rem);
                margin-left: 16rem;
            }
            
            .chat-container {
                height: 70vh;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="page-container">
        <!-- Header Section -->
       <div class="header-section mb-8 mt-14 sm:mt-0">
  <h1 class="text-2xl sm:text-3xl font-bold text-text mb-2">
    Group Chat - <?= htmlspecialchars($group_name) ?>
  </h1>
  <p class="text-text/70">Communicate with volunteers in this group</p>
</div>


        <!-- Chat Container -->
        <div class="card mb-6">
            <!-- Chat Messages -->
            <div id="chatBox" class="chat-container p-4 overflow-y-auto bg-background">
                <?php while ($msg = $messages->fetch_assoc()): ?>
                    <div class="message-animation mb-4 <?= $msg['sender_role'] === 'admin' ? 'pl-10' : 'pr-10' ?>">
                        <div class="flex <?= $msg['sender_role'] === 'admin' ? 'flex-row-reverse' : '' ?>">
                            <div class="flex-shrink-0 h-8 w-8 rounded-full bg-<?= $msg['sender_role'] === 'admin' ? 'primary' : 'accent' ?> flex items-center justify-center text-white text-sm font-medium">
                                <?= strtoupper(substr($msg['sender_role'], 0, 1)) ?>
                            </div>
                            <div class="ml-3 mr-3 <?= $msg['sender_role'] === 'admin' ? 'text-right' : '' ?>">
                                <div class="text-sm font-medium text-text">
                                    <?= $msg['sender_role'] === 'admin' ? 'You' : 'Volunteer' ?>
                                </div>
                                <div class="mt-1 text-sm text-text">
                                    <div class="inline-block px-4 py-2 rounded-lg 
                                        <?= $msg['sender_role'] === 'admin' ? 'bg-primary text-white' : 'bg-subtle text-text' ?>">
                                        <?= htmlspecialchars($msg['message']) ?>
                                    </div>
                                </div>
                                <div class="mt-1 text-xs text-text/60">
                                    <?= date('h:i A', strtotime($msg['sent_at'])) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- Message Input -->
            <div class="border-t border-subtle px-4 py-3 bg-white">
                <form id="chatForm" class="flex items-center">
                    <input type="hidden" name="group_id" value="<?= $group_id ?>">
                    <input type="hidden" name="sender_id" value="1">
                    <input type="hidden" name="sender_role" value="admin">
                    <div class="flex-1">
                        <input name="message" class="form-input" placeholder="Type your message..." required>
                    </div>
                    <button type="submit" class="ml-3 submit-btn">
                        <i class="fas fa-paper-plane mr-2"></i> Send
                    </button>
                </form>
            </div>
        </div>

        <!-- Group Members Section -->
        <div class="card">
            <div class="px-6 py-4 border-b border-subtle">
                <h3 class="text-lg font-medium text-text">Manage Group Volunteers</h3>
                <p class="mt-1 text-sm text-text/60">Select volunteers to include in this group</p>
            </div>
            <div class="p-6">
                <form method="POST" action="update_group_members_inline.php">
                    <input type="hidden" name="group_id" value="<?= $group_id ?>">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mb-6">
                        <?php while ($vol = $volunteerList->fetch_assoc()): ?>
                            <label class="flex items-center space-x-3 p-3 rounded-lg border border-subtle hover:bg-background cursor-pointer">
                                <input type="checkbox" name="volunteer_ids[]" value="<?= $vol['id'] ?>"
                                    class="h-4 w-4 text-primary focus:ring-primary border-subtle rounded"
                                    <?= in_array($vol['id'], $currentMembers) ? 'checked' : '' ?>>
                                <span class="text-sm font-medium text-text"><?= htmlspecialchars($vol['full_name']) ?></span>
                            </label>
                        <?php endwhile; ?>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="submit-btn">
                            <i class="fas fa-save mr-2"></i> Update Members
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('chatForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const form = new FormData(this);
        const messageInput = this.querySelector('input[name="message"]');
        const message = messageInput.value.trim();
        
        if (!message) return;
        
        fetch('send_group_message.php', {
            method: 'POST',
            body: form
        }).then(response => {
            if (response.ok) {
                // Create a temporary message in the UI while waiting for refresh
                const chatBox = document.getElementById('chatBox');
                const now = new Date();
                const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                
                const tempMsg = document.createElement('div');
                tempMsg.className = 'message-animation mb-4 pl-10';
                tempMsg.innerHTML = `
                    <div class="flex flex-row-reverse">
                        <div class="flex-shrink-0 h-8 w-8 rounded-full bg-primary flex items-center justify-center text-white text-sm font-medium">
                            A
                        </div>
                        <div class="ml-3 mr-3 text-right">
                            <div class="text-sm font-medium text-text">
                                You
                            </div>
                            <div class="mt-1 text-sm text-text">
                                <div class="inline-block px-4 py-2 rounded-lg bg-primary text-white">
                                    ${message}
                                </div>
                            </div>
                            <div class="mt-1 text-xs text-text/60">
                                ${timeString}
                            </div>
                        </div>
                    </div>
                `;
                chatBox.appendChild(tempMsg);
                chatBox.scrollTop = chatBox.scrollHeight;
                
                // Clear input and refresh messages
                messageInput.value = '';
                setTimeout(() => location.reload(), 500);
            }
        });
    });

    // Auto-scroll to bottom of chat
    window.addEventListener('load', () => {
        const chatBox = document.getElementById('chatBox');
        chatBox.scrollTop = chatBox.scrollHeight;
    });
    </script>
</body>
</html>