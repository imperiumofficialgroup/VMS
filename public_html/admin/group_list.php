<?php
session_start();
require_once '../auth/db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Fetch all group chats and count unread messages per group
$sql = "
    SELECT gc.id, gc.group_name, gc.event_id, e.title AS event_title,
        (SELECT COUNT(*) FROM group_messages gm 
         WHERE gm.group_id = gc.id AND gm.sender_role = 'volunteer' AND gm.is_read = 0) AS unread_count,
        (SELECT MAX(sent_at) FROM group_messages WHERE group_id = gc.id) AS last_activity
    FROM group_chats gc
    LEFT JOIN events e ON gc.event_id = e.event_id
    ORDER BY last_activity DESC
";

$groups = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Chats | VMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
          <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>  
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #FBFCFC;
            color: #1C2833;
        }
        
        .page-container {
            padding: 1rem;
        }
        
        .container-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid #D5D8DC;
        }
        
        .group-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #D5D8DC;
            transition: all 0.3s ease;
        }
        
        .group-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(93, 173, 226, 0.15);
        }
        
        .unread-badge {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .search-input {
            width: 100%;
            padding: 12px 16px 12px 40px;
            border-radius: 10px;
            border: 1px solid #D5D8DC;
            background-color: white;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            color: #1C2833;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #5DADE2;
            box-shadow: 0 0 0 3px rgba(93, 173, 226, 0.1);
        }
        
        .filter-select {
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid #D5D8DC;
            background-color: white;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            color: #1C2833;
            transition: all 0.3s ease;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: #5DADE2;
            box-shadow: 0 0 0 3px rgba(93, 173, 226, 0.1);
        }
        
        .chat-btn {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .chat-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(93, 173, 226, 0.3);
        }
        
        .new-group-btn {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .new-group-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(93, 173, 226, 0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #D5D8DC;
            margin-bottom: 1rem;
        }
        
        /* Mobile First Responsive Design */
        @media (max-width: 640px) {
            .page-container {
                padding: 1rem;
            }
            
            .header-section {
                text-align: center;
                margin-bottom: 2rem;
            }
            
            .search-filter-section {
                flex-direction: column;
                gap: 1rem;
                margin-bottom: 2rem;
            }
            
            .search-container {
                position: relative;
                width: 100%;
            }
            
            .search-icon {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                color: #64748B;
                z-index: 10;
            }
            
            .filter-container {
                width: 100%;
            }
            
            .group-card {
                padding: 1.25rem;
                margin-bottom: 1rem;
            }
            
            .group-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
                margin-bottom: 1rem;
            }
            
            .group-info {
                width: 100%;
            }
            
            .group-details {
                display: grid;
                gap: 0.75rem;
                margin-top: 0.75rem;
            }
            
            .detail-item {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.875rem;
            }
            
            .action-container {
                width: 100%;
                text-align: center;
            }
            
            .chat-btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (min-width: 641px) and (max-width: 768px) {
            .page-container {
                padding: 1.5rem;
            }
            
            .search-filter-section {
                flex-direction: row;
                gap: 1rem;
            }
            
            .search-container {
                flex: 2;
            }
            
            .filter-container {
                flex: 1;
            }
            
            .group-card {
                padding: 1.5rem;
            }
            
            .group-header {
                flex-direction: row;
                align-items: flex-start;
                justify-content: space-between;
            }
            
            .group-info {
                flex: 1;
            }
            
            .action-container {
                flex-shrink: 0;
            }
        }
        
        @media (min-width: 769px) {
            .page-container {
                padding: 2rem;
                max-width: 1200px;
                margin: 0 auto;
            }
            
            .header-section {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }
            
            .search-filter-section {
                flex-direction: row;
                gap: 1.5rem;
                margin-bottom: 2rem;
            }
            
            .search-container {
                flex: 2;
                position: relative;
            }
            
            .search-icon {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                color: #64748B;
            }
            
            .filter-container {
                flex: 1;
            }
            
            .group-card {
                padding: 1.75rem;
            }
            
            .group-header {
                flex-direction: row;
                align-items: flex-start;
                justify-content: space-between;
            }
            
            .group-info {
                flex: 1;
                min-width: 0;
            }
            
            .action-container {
                flex-shrink: 0;
                margin-left: 1.5rem;
            }
            
            .groups-grid {
                display: grid;
                gap: 1.5rem;
            }
        }
        
        /* Ensure proper sidebar spacing */
        @media (min-width: 768px) {
            body {
                margin-left: 16rem;
            }
        }
    </style>
</head>
<body class="min-h-screen lg:ml-64">
    <?php include 'sidebar.php'; ?>

    <div class="page-container">
        <!-- Header Section -->
        <div class="header-section mb-8 mt-12 sm:mt-0 flex flex-col sm:flex-row sm:items-center sm:justify-between">
  <div class="mb-4 sm:mb-0">
    <h1 class="text-2xl md:text-3xl font-bold text-text mb-2">Group Chats</h1>
    <p class="text-text/70">Manage and participate in volunteer group discussions</p>
  </div>
  <a href="create_group.php" class="new-group-btn inline-flex items-center">
    <i class="fas fa-plus mr-2"></i>
    New Group
  </a>
</div>


        <!-- Search and Filter Section -->
        <div class="search-filter-section flex mb-6">
            <div class="search-container">
                <div class="search-icon">
                    <i class="fas fa-search"></i>
                </div>
                <input type="text" class="search-input" placeholder="Search groups by name or event...">
            </div>
            <div class="filter-container">
                <select class="filter-select w-full">
                    <option value="all">All Groups</option>
                    <option value="unread">With Unread Messages</option>
                    <option value="recent">Recently Active</option>
                    <option value="event">By Event</option>
                </select>
            </div>
        </div>

        <!-- Groups List -->
        <?php if ($groups->num_rows > 0): ?>
            <div class="groups-grid">
                <?php while ($group = $groups->fetch_assoc()): 
                    $hasUnread = (int)$group['unread_count'] > 0;
                    $lastActivity = $group['last_activity'] ? date('M j, g:i a', strtotime($group['last_activity'])) : 'No activity yet';
                ?>
                    <div class="group-card container-card">
                        <div class="group-header flex">
                            <!-- Group Info -->
                            <div class="group-info">
                                <div class="flex items-center gap-2 mb-2">
                                    <h3 class="text-lg font-semibold text-text truncate">
                                        <?= htmlspecialchars($group['group_name']) ?>
                                    </h3>
                                    <?php if ($hasUnread): ?>
                                        <span class="unread-badge inline-flex items-center">
                                            <i class="fas fa-circle text-xs mr-1"></i>
                                            <?= $group['unread_count'] ?> unread
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="group-details">
                                    <?php if ($group['event_title']): ?>
                                        <div class="detail-item text-text/70">
                                            <i class="fas fa-calendar-alt text-primary"></i>
                                            <span><?= htmlspecialchars($group['event_title']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="detail-item text-text/70">
                                        <i class="fas fa-clock text-accent"></i>
                                        <span>Last activity: <?= $lastActivity ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Button -->
                            <div class="action-container">
                                <a href="group_chat.php?group_id=<?= $group['id'] ?>" 
                                   class="chat-btn inline-flex items-center">
                                    <i class="fas fa-comments mr-2"></i>
                                    Open Chat
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state container-card">
                <i class="fas fa-comments"></i>
                <h3 class="text-xl font-bold text-text mb-2">No Group Chats Yet</h3>
                <p class="text-text/70 mb-6 max-w-md mx-auto">
                    Start building your volunteer community by creating the first group chat for better coordination and communication.
                </p>
                <a href="create_group.php" class="new-group-btn inline-flex items-center">
                    <i class="fas fa-plus mr-2"></i>
                    Create First Group
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Search functionality
        const searchInput = document.querySelector('.search-input');
        const groupCards = document.querySelectorAll('.group-card');
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            groupCards.forEach(card => {
                const groupName = card.querySelector('h3').textContent.toLowerCase();
                const eventTitle = card.querySelector('.fa-calendar-alt')?.nextElementSibling?.textContent.toLowerCase() || '';
                
                if (groupName.includes(searchTerm) || eventTitle.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Filter functionality
        const filterSelect = document.querySelector('.filter-select');
        
        filterSelect.addEventListener('change', function() {
            const filterValue = this.value;
            
            groupCards.forEach(card => {
                const hasUnread = card.querySelector('.unread-badge') !== null;
                
                switch(filterValue) {
                    case 'unread':
                        card.style.display = hasUnread ? 'block' : 'none';
                        break;
                    case 'all':
                    default:
                        card.style.display = 'block';
                        break;
                }
            });
        });

        // Add hover effects
        document.addEventListener('DOMContentLoaded', function() {
            groupCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>