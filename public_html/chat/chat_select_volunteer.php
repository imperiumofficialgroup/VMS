<?php
session_start();
require_once '../auth/db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../admin/login.php");
    exit();
}

$admin_id = 1; // Single admin setup

// Pagination setup
$volunteers_per_page = 20;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $volunteers_per_page;

// Get total volunteers count
$count_query = "SELECT COUNT(*) as total FROM volunteers";
$count_result = $conn->query($count_query);
$total_volunteers = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_volunteers / $volunteers_per_page);

// Fetch volunteers with unread message count for current page
$query = "
    SELECT v.id, v.full_name, v.email, v.profile_image,
        (SELECT COUNT(*) FROM messages 
         WHERE sender_id = v.id AND sender_role = 'volunteer' 
           AND receiver_id = ? AND receiver_role = 'admin' 
           AND is_read = 0) AS unread_count
    FROM volunteers v
    ORDER BY v.full_name
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $admin_id, $volunteers_per_page, $offset);
$stmt->execute();
$volunteers = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Messages | VMS</title>
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
        
        .form-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid #D5D8DC;
        }
        
        .unread-badge {
            animation: pulse 2s infinite;
            background: linear-gradient(135deg, #A569BD 0%, #5DADE2 100%);
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .chat-btn {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.875rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .chat-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(93, 173, 226, 0.3);
        }
        
        .volunteer-card {
            transition: all 0.3s ease;
            border-radius: 12px;
            border: 1px solid #D5D8DC;
        }
        
        .volunteer-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border-color: #5DADE2;
        }
        
        .pagination-btn {
            background: white;
            color: #1C2833;
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid #D5D8DC;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .pagination-btn:hover:not(.disabled) {
            background: #5DADE2;
            color: white;
            border-color: #5DADE2;
        }
        
        .pagination-btn.active {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            border-color: transparent;
        }
        
        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .search-input {
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
        
        .search-input:focus {
            outline: none;
            border-color: #5DADE2;
            box-shadow: 0 0 0 3px rgba(93, 173, 226, 0.1);
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
                max-width: 1200px;
            }
        }
        
        @media (min-width: 1024px) {
            .page-container {
                max-width: 1400px;
            }
        }
        
        .empty-state {
            background: linear-gradient(135deg, #FBFCFC 0%, #f8fafc 100%);
            border: 2px dashed #D5D8DC;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
        }
        
        .status-unread {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
        }
    </style>
</head>
<body>
    <?php include '../admin/sidebar.php'; ?>
    
    <div class="page-container">
        <!-- Header Section -->
        <div class="header-section mt-12 sm:mt-0 mb-8 text-center sm:text-left">
            <h1 class="text-2xl sm:text-3xl font-bold text-text mb-2">Volunteer Messages</h1>
            <p class="text-text/70">Select a volunteer to start chatting</p>
        </div>

        <!-- Search and Stats Bar -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <!-- Search -->
            <div class="w-full sm:w-64">
                <div class="relative">
                    <input type="text" placeholder="Search volunteers..." 
                           class="search-input pl-10"
                           id="searchInput">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-text/40"></i>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="flex items-center gap-4 text-sm text-text/60">
                <span class="hidden sm:inline">
                    <i class="fas fa-users mr-1"></i>
                    <?= $total_volunteers ?> volunteers total
                </span>
                <span>
                    <i class="fas fa-comments mr-1"></i>
                    Page <?= $current_page ?> of <?= $total_pages ?>
                </span>
            </div>
        </div>

        <!-- Volunteers List -->
        <div class="form-container overflow-hidden">
            <!-- Desktop Table Header -->
            <div class="hidden md:grid grid-cols-12 bg-background px-6 py-4 border-b border-subtle">
                <div class="col-span-5 font-semibold text-text">
                    <i class="fas fa-user mr-2 text-primary"></i>
                    Volunteer
                </div>
                <div class="col-span-4 font-semibold text-text">
                    <i class="fas fa-envelope mr-2 text-primary"></i>
                    Contact
                </div>
                <div class="col-span-2 font-semibold text-text">
                    <i class="fas fa-bell mr-2 text-primary"></i>
                    Status
                </div>
                <div class="col-span-1 font-semibold text-text text-right">
                    Action
                </div>
            </div>

            <?php if ($volunteers->num_rows > 0): ?>
                <?php while ($vol = $volunteers->fetch_assoc()): ?>
                    <!-- Desktop Row -->
                    <div class="hidden md:grid grid-cols-12 items-center px-6 py-4 border-b border-subtle hover:bg-background/50 transition-all duration-200 volunteer-card">
                        <div class="col-span-5 flex items-center">
                            <div class="relative">
                                <?php if ($vol['profile_image']): ?>
                                    <img src="../<?= htmlspecialchars($vol['profile_image']) ?>" 
                                         alt="<?= htmlspecialchars($vol['full_name']) ?>"
                                         class="w-12 h-12 rounded-full object-cover border-2 border-white shadow-sm">
                                <?php else: ?>
                                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-primary to-accent flex items-center justify-center text-white font-semibold text-lg shadow-sm">
                                        <?= strtoupper(substr($vol['full_name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($vol['unread_count'] > 0): ?>
                                    <span class="unread-badge absolute -top-1 -right-1 w-5 h-5 rounded-full text-xs text-white flex items-center justify-center font-bold shadow-sm">
                                        <?= $vol['unread_count'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="ml-4">
                                <div class="font-semibold text-text"><?= htmlspecialchars($vol['full_name']) ?></div>
                                <div class="text-sm text-text/60 mt-1">
                                    <i class="fas fa-id-card mr-1"></i>
                                    VMS-<?= str_pad($vol['id'], 4, '0', STR_PAD_LEFT) ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-span-4">
                            <div class="text-text/80">
                                <i class="fas fa-envelope mr-2 text-text/40"></i>
                                <?= htmlspecialchars($vol['email']) ?>
                            </div>
                        </div>
                        <div class="col-span-2">
                            <?php if ($vol['unread_count'] > 0): ?>
                                <span class="status-badge status-unread">
                                    <i class="fas fa-envelope mr-1"></i>
                                    <?= $vol['unread_count'] ?> unread
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-active">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    All read
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="col-span-1 text-right">
                            <a href="chat_window.php?volunteer_id=<?= $vol['id'] ?>" 
                               class="chat-btn inline-flex items-center">
                                <i class="fas fa-comment-dots mr-2"></i>
                                Chat
                            </a>
                        </div>
                    </div>

                    <!-- Mobile Card -->
                    <div class="md:hidden p-4 border-b border-subtle volunteer-card">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex items-center">
                                <div class="relative">
                                    <?php if ($vol['profile_image']): ?>
                                        <img src="../<?= htmlspecialchars($vol['profile_image']) ?>" 
                                             alt="<?= htmlspecialchars($vol['full_name']) ?>"
                                             class="w-12 h-12 rounded-full object-cover border-2 border-white shadow-sm">
                                    <?php else: ?>
                                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-primary to-accent flex items-center justify-center text-white font-semibold text-lg shadow-sm">
                                            <?= strtoupper(substr($vol['full_name'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($vol['unread_count'] > 0): ?>
                                        <span class="unread-badge absolute -top-1 -right-1 w-5 h-5 rounded-full text-xs text-white flex items-center justify-center font-bold shadow-sm">
                                            <?= $vol['unread_count'] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="ml-3">
                                    <div class="font-semibold text-text"><?= htmlspecialchars($vol['full_name']) ?></div>
                                    <div class="text-xs text-text/60 mt-1">
                                        <i class="fas fa-id-card mr-1"></i>
                                        VMS-<?= str_pad($vol['id'], 4, '0', STR_PAD_LEFT) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mb-3">
                            <div class="text-sm text-text/80">
                                <i class="fas fa-envelope mr-2 text-text/40"></i>
                                <?= htmlspecialchars($vol['email']) ?>
                            </div>
                            <div class="text-right">
                                <?php if ($vol['unread_count'] > 0): ?>
                                    <span class="status-badge status-unread">
                                        <i class="fas fa-envelope mr-1"></i>
                                        <?= $vol['unread_count'] ?> unread
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-active">
                                        <i class="fas fa-check-circle mr-1"></i>
                                        All read
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="text-right">
                            <a href="chat_window.php?volunteer_id=<?= $vol['id'] ?>" 
                               class="chat-btn inline-flex items-center text-sm">
                                <i class="fas fa-comment-dots mr-2"></i>
                                Start Chat
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <!-- Empty State -->
                <div class="p-8 text-center empty-state rounded-lg m-4">
                    <div class="text-text/40 mb-4">
                        <i class="fas fa-comment-slash text-5xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-text mb-2">No Volunteers Available</h3>
                    <p class="text-text/60 max-w-md mx-auto">
                        There are currently no volunteers registered in the system. 
                        Volunteers will appear here once they register.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="text-sm text-text/60">
                    Showing <span class="font-semibold text-text"><?= min(($current_page - 1) * $volunteers_per_page + 1, $total_volunteers) ?></span> 
                    to <span class="font-semibold text-text"><?= min($current_page * $volunteers_per_page, $total_volunteers) ?></span> 
                    of <span class="font-semibold text-text"><?= $total_volunteers ?></span> volunteers
                </div>
                
                <div class="flex items-center space-x-1">
                    <!-- First Page -->
                    <a href="?page=1" 
                       class="pagination-btn <?= $current_page == 1 ? 'disabled' : '' ?>">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    
                    <!-- Previous Page -->
                    <a href="?page=<?= $current_page - 1 ?>" 
                       class="pagination-btn <?= $current_page == 1 ? 'disabled' : '' ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    
                    <!-- Page Numbers -->
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <a href="?page=<?= $i ?>" 
                           class="pagination-btn <?= $i == $current_page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <!-- Next Page -->
                    <a href="?page=<?= $current_page + 1 ?>" 
                       class="pagination-btn <?= $current_page == $total_pages ? 'disabled' : '' ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    
                    <!-- Last Page -->
                    <a href="?page=<?= $total_pages ?>" 
                       class="pagination-btn <?= $current_page == $total_pages ? 'disabled' : '' ?>">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.volunteer-card');
            
            cards.forEach(card => {
                const name = card.querySelector('.font-semibold')?.textContent.toLowerCase() || '';
                const email = card.querySelector('.fa-envelope')?.parentElement?.textContent.toLowerCase() || '';
                
                if (name.includes(searchTerm) || email.includes(searchTerm)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Add loading states to pagination buttons
        document.addEventListener('DOMContentLoaded', function() {
            const paginationButtons = document.querySelectorAll('.pagination-btn:not(.disabled)');
            paginationButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    if (!this.classList.contains('disabled')) {
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    }
                });
            });
        });
    </script>
</body>
</html>