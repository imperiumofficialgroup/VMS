<?php
session_start();
require_once '../auth/db.php';

if (!isset($_SESSION['volunteer_id'])) {
    header("Location: ../auth/volunteer_login.php");
    exit();
}

$volunteer_id = $_SESSION['volunteer_id'];

// Get groups with unread message counts
$groups = [];
$stmt = $conn->prepare("
    SELECT gc.id, gc.group_name, e.title AS event_title,
           (SELECT COUNT(*) FROM group_messages 
            WHERE group_id = gc.id AND sender_role = 'admin' AND is_read = 0) AS unread_count
    FROM group_members gm
    INNER JOIN group_chats gc ON gm.group_id = gc.id
    LEFT JOIN events e ON gc.event_id = e.event_id
    WHERE gm.user_id = ?
    ORDER BY gc.id DESC
");

if ($stmt) {
    $stmt->bind_param("i", $volunteer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $groups = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    die("Database query failed.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Group Chats | VMS</title>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #FBFCFC;
            color: #1C2833;
        }

        @media (max-width: 1023px) {
            .content-area {
                padding-top: 4rem;
            }
        }

        .group-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #D5D8DC;
            transition: all 0.3s ease;
        }

        .group-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border-color: #5DADE2;
        }

        .open-btn {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .open-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(93, 173, 226, 0.3);
        }

        .unread-badge {
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }

        .empty-state {
            background: linear-gradient(135deg, #FBFCFC 0%, #f8fafc 100%);
            border: 2px dashed #D5D8DC;
        }

        .group-icon {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Mobile First Responsive Design */
        @media (max-width: 640px) {
            .page-container {
                padding: 1rem;
            }
        }

        @media (min-width: 641px) and (max-width: 768px) {
            .page-container {
                padding: 1.5rem;
            }
        }

        @media (min-width: 769px) {
            .page-container {
                padding: 2rem;
                width: calc(100% - 16rem);
                margin-left: 16rem;
                max-width: none;
            }
            
            .groups-grid {
                grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            }
        }

        @media (min-width: 1024px) {
            .groups-grid {
                grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
            }
        }

        @media (min-width: 1280px) {
            .groups-grid {
                grid-template-columns: repeat(auto-fill, minmax(500px, 1fr));
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="page-container lg:ml-72">
        <!-- Header Section -->
        <div class="header-section mt-12 sm:mt-0 mb-8 text-center sm:text-left">
            <h1 class="text-2xl sm:text-3xl font-bold text-text mb-2">My Group Chats</h1>
            <p class="text-text/70">Connect and collaborate with your event teams</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6 mb-8">
            <div class="group-card p-6">
                <div class="flex items-center">
                    <div class="group-icon w-12 h-12 mr-4">
                        <i class="fas fa-users text-lg"></i>
                    </div>
                    <div>
                        <h4 class="text-lg font-semibold text-text">Total Groups</h4>
                        <p class="text-2xl font-bold text-primary"><?= count($groups) ?></p>
                        <p class="text-sm text-text/60">Active group chats</p>
                    </div>
                </div>
            </div>

            <div class="group-card p-6">
                <div class="flex items-center">
                    <div class="group-icon w-12 h-12 mr-4">
                        <i class="fas fa-comments text-lg"></i>
                    </div>
                    <div>
                        <h4 class="text-lg font-semibold text-text">Unread Messages</h4>
                        <p class="text-2xl font-bold text-accent">
                            <?php
                                $total_unread = 0;
                                foreach ($groups as $group) {
                                    $total_unread += $group['unread_count'];
                                }
                                echo $total_unread;
                            ?>
                        </p>
                        <p class="text-sm text-text/60">Messages waiting</p>
                    </div>
                </div>
            </div>

            <div class="group-card p-6">
                <div class="flex items-center">
                    <div class="group-icon w-12 h-12 mr-4">
                        <i class="fas fa-calendar-check text-lg"></i>
                    </div>
                    <div>
                        <h4 class="text-lg font-semibold text-text">Active Events</h4>
                        <p class="text-2xl font-bold text-green-600">
                            <?php
                                $unique_events = [];
                                foreach ($groups as $group) {
                                    if ($group['event_title']) {
                                        $unique_events[$group['event_title']] = true;
                                    }
                                }
                                echo count($unique_events);
                            ?>
                        </p>
                        <p class="text-sm text-text/60">With active groups</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Groups Section -->
        <div class="mb-6">
            <h2 class="text-xl font-bold text-text mb-4 flex items-center">
                <i class="fas fa-list mr-3 text-primary"></i>
                Your Groups
            </h2>
            
            <?php if (count($groups) > 0): ?>
                <div class="groups-grid grid gap-4 md:gap-6">
                    <?php foreach ($groups as $row): ?>
                    <div class="group-card p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-start space-x-4 flex-1 min-w-0">
                                <div class="group-icon w-14 h-14 flex-shrink-0">
                                    <i class="fas fa-users text-xl"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-lg font-semibold text-text truncate">
                                        <?= htmlspecialchars($row['group_name']) ?>
                                    </h3>
                                    <p class="text-text/70 text-sm mt-1 truncate">
                                        <i class="fas fa-calendar-alt mr-2 text-primary"></i>
                                        <?= htmlspecialchars($row['event_title'] ?? 'General Group') ?>
                                    </p>
                                </div>
                            </div>
                            
                            <?php if ($row['unread_count'] > 0): ?>
                                <div class="unread-badge w-8 h-8 text-xs ml-2 flex-shrink-0">
                                    <?= $row['unread_count'] ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex items-center justify-between pt-4 border-t border-subtle">
                            <div class="flex items-center text-text/60 text-sm">
                                <i class="fas fa-clock mr-2"></i>
                                <span>Active now</span>
                            </div>
                            <a href="group_chat.php?group_id=<?= urlencode($row['id']) ?>" 
                               class="open-btn flex items-center justify-center">
                                <span>Open Chat</span>
                                <i class="fas fa-arrow-right ml-2 text-sm"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state rounded-2xl p-8 md:p-12 text-center">
                    <div class="mx-auto w-20 h-20 md:w-24 md:h-24 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                        <i class="fas fa-users text-3xl md:text-4xl text-text/30"></i>
                    </div>
                    <h3 class="text-lg md:text-xl font-semibold text-text mb-2">No groups yet</h3>
                    <p class="text-text/60 mb-6 max-w-md mx-auto">
                        You haven't been added to any group chats yet. Groups are automatically created for events you're participating in.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-3 justify-center">
                        <a href="events.php" class="open-btn inline-flex items-center justify-center">
                            <i class="fas fa-calendar-plus mr-2"></i>
                            Browse Events
                        </a>
                        <a href="profile.php" class="group-card inline-flex items-center justify-center px-6 py-3 text-text font-medium hover:text-primary transition-colors">
                            <i class="fas fa-user-check mr-2"></i>
                            Update Profile
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Tips Section -->
        <div class="group-card p-6 mt-8">
            <div class="flex items-start">
                <div class="bg-primary/10 text-primary p-3 rounded-xl mr-4">
                    <i class="fas fa-lightbulb text-lg"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-text mb-2">Group Chat Tips</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-text/70">
                        <div class="flex items-start">
                            <i class="fas fa-check text-primary mr-2 mt-1 flex-shrink-0"></i>
                            <span>Check groups regularly for event updates and announcements</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-check text-primary mr-2 mt-1 flex-shrink-0"></i>
                            <span>Respond to admin messages to stay coordinated</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-check text-primary mr-2 mt-1 flex-shrink-0"></i>
                            <span>Use groups to coordinate with your team members</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-check text-primary mr-2 mt-1 flex-shrink-0"></i>
                            <span>Mark messages as read to keep track of new content</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add subtle animation to group cards on load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.group-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>