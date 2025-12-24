<?php
session_start();
include '../auth/db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Update leave status (if admin action)
if (isset($_GET['action'], $_GET['id'])) {
    $id = intval($_GET['id']);
    $status = $_GET['action'] === 'approve' ? 'Approved' : 'Rejected';
    $conn->query("UPDATE volunteer_leaves SET status='$status' WHERE id=$id");
    
    $_SESSION['success_message'] = "Leave request {$status} successfully!";
    header("Location: leaves.php");
    exit();
}

// Fetch all leave requests
$leaves = $conn->query("
    SELECT vl.*, v.full_name, v.profile_image, e.title 
    FROM volunteer_leaves vl
    JOIN volunteers v ON vl.volunteer_id = v.id
    JOIN events e ON vl.event_id = e.event_id
    ORDER BY vl.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Requests | VMS</title>
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
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        
        .status-approved {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .status-rejected {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .action-btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid;
        }
        
        .approve-btn {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border-color: rgba(16, 185, 129, 0.3);
        }
        
        .approve-btn:hover {
            background: rgba(16, 185, 129, 0.2);
            transform: translateY(-1px);
        }
        
        .reject-btn {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.3);
        }
        
        .reject-btn:hover {
            background: rgba(239, 68, 68, 0.2);
            transform: translateY(-1px);
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
        
        .success-message {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #065f46;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 1.5rem;
        }
        
        /* Mobile First Responsive Design */
        @media (max-width: 640px) {
            .page-container {
                padding: 1rem;
            }
            
            .container-card {
                padding: 1.25rem;
            }
            
            .header-section {
                text-align: center;
                margin-bottom: 2rem;
            }
            
            .leave-card {
                padding: 1.25rem;
                margin-bottom: 1rem;
                border: 1px solid #D5D8DC;
                border-radius: 12px;
                background: white;
            }
            
            .volunteer-info {
                flex-direction: row;
                align-items: center;
                margin-bottom: 1rem;
            }
            
            .volunteer-image {
                width: 3rem;
                height: 3rem;
                margin-right: 1rem;
            }
            
            .leave-details {
                display: grid;
                gap: 0.75rem;
            }
            
            .detail-item {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
            }
            
            .action-buttons {
                display: flex;
                gap: 0.5rem;
                margin-top: 1rem;
            }
            
            .action-btn {
                flex: 1;
                text-align: center;
                justify-content: center;
            }
        }
        
        @media (min-width: 641px) and (max-width: 768px) {
            .page-container {
                padding: 1.5rem;
            }
            
            .container-card {
                padding: 2rem;
            }
            
            .leave-card {
                padding: 1.5rem;
            }
            
            .leave-details {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
        }
        
        @media (min-width: 769px) {
            .page-container {
                padding: 2rem;
                max-width: 1200px;
                margin: 0 auto;
            }
            
            .container-card {
                padding: 2.5rem;
            }
            
            .header-section {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }
            
            .leaves-table {
                display: table;
                width: 100%;
                border-collapse: collapse;
            }
            
            .table-row {
                display: table-row;
                border-bottom: 1px solid #D5D8DC;
            }
            
            .table-row:hover {
                background: rgba(93, 173, 226, 0.03);
            }
            
            .table-cell {
                display: table-cell;
                padding: 1rem 1.5rem;
                vertical-align: middle;
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
    <h1 class="text-2xl md:text-3xl font-bold text-text mb-2">Leave Requests</h1>
    <p class="text-text/70">Manage volunteer leave applications</p>
  </div>
</div>


        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-message flex items-center">
                <i class="fas fa-check-circle mr-3"></i>
                <div><?= $_SESSION['success_message'] ?></div>
                <button onclick="this.parentElement.remove()" class="ml-auto">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if ($leaves->num_rows > 0): ?>
            <!-- Desktop Table View -->
            <div class="leaves-table hidden md:block">
                <div class="container-card">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-subtle">
                                <th class="text-left py-4 px-6 text-text/70 font-semibold">Volunteer</th>
                                <th class="text-left py-4 px-6 text-text/70 font-semibold">Event</th>
                                <th class="text-left py-4 px-6 text-text/70 font-semibold">Leave Date</th>
                                <th class="text-left py-4 px-6 text-text/70 font-semibold">Reason</th>
                                <th class="text-left py-4 px-6 text-text/70 font-semibold">Status</th>
                                <th class="text-right py-4 px-6 text-text/70 font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $leaves->fetch_assoc()): 
                                $statusClass = 'status-' . strtolower($row['status']);
                            ?>
                                <tr class="table-row">
                                    <td class="table-cell">
                                        <div class="flex items-center">
                                            <img src="../<?= htmlspecialchars($row['profile_image'] ?: 'uploads/default.jpg') ?>" 
                                                 class="w-10 h-10 rounded-full object-cover mr-3 border-2 border-white shadow-sm"
                                                 alt="<?= htmlspecialchars($row['full_name']) ?>">
                                            <span class="font-medium text-text"><?= htmlspecialchars($row['full_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="table-cell text-text/70">
                                        <?= htmlspecialchars($row['title']) ?>
                                    </td>
                                    <td class="table-cell text-text/70">
                                        <?= date('M d, Y', strtotime($row['leave_date'])) ?>
                                    </td>
                                    <td class="table-cell text-text/70 max-w-xs">
                                        <div class="truncate" title="<?= htmlspecialchars($row['reason']) ?>">
                                            <?= htmlspecialchars($row['reason']) ?>
                                        </div>
                                    </td>
                                    <td class="table-cell">
                                        <span class="status-badge <?= $statusClass ?>">
                                            <?= $row['status'] ?>
                                        </span>
                                    </td>
                                    <td class="table-cell text-right">
                                        <?php if ($row['status'] === 'Pending'): ?>
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="?action=approve&id=<?= $row['id'] ?>" 
                                                   class="action-btn approve-btn inline-flex items-center"
                                                   onclick="return confirm('Approve this leave request?')">
                                                    <i class="fas fa-check mr-1"></i>
                                                    Approve
                                                </a>
                                                <a href="?action=reject&id=<?= $row['id'] ?>" 
                                                   class="action-btn reject-btn inline-flex items-center"
                                                   onclick="return confirm('Reject this leave request?')">
                                                    <i class="fas fa-times mr-1"></i>
                                                    Reject
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-text/40 text-sm">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Card View -->
            <div class="leaves-grid md:hidden space-y-4">
                <?php 
                // Reset pointer for mobile view
                $leaves->data_seek(0);
                while ($row = $leaves->fetch_assoc()): 
                    $statusClass = 'status-' . strtolower($row['status']);
                ?>
                    <div class="leave-card container-card">
                        <!-- Volunteer Info -->
                        <div class="volunteer-info flex">
                            <img src="../<?= htmlspecialchars($row['profile_image'] ?: 'uploads/default.jpg') ?>" 
                                 class="volunteer-image rounded-full object-cover border-2 border-white shadow-sm"
                                 alt="<?= htmlspecialchars($row['full_name']) ?>">
                            <div class="flex-1">
                                <h3 class="font-semibold text-text"><?= htmlspecialchars($row['full_name']) ?></h3>
                                <span class="status-badge <?= $statusClass ?> text-xs">
                                    <?= $row['status'] ?>
                                </span>
                            </div>
                        </div>

                        <!-- Leave Details -->
                        <div class="leave-details">
                            <div class="detail-item">
                                <span class="text-text/70 font-medium">Event:</span>
                                <span class="text-text text-right"><?= htmlspecialchars($row['title']) ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="text-text/70 font-medium">Leave Date:</span>
                                <span class="text-text"><?= date('M d, Y', strtotime($row['leave_date'])) ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="text-text/70 font-medium">Reason:</span>
                                <span class="text-text text-right"><?= htmlspecialchars($row['reason']) ?></span>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <?php if ($row['status'] === 'Pending'): ?>
                            <div class="action-buttons">
                                <a href="?action=approve&id=<?= $row['id'] ?>" 
                                   class="action-btn approve-btn inline-flex items-center"
                                   onclick="return confirm('Approve this leave request?')">
                                    <i class="fas fa-check mr-1"></i>
                                    Approve
                                </a>
                                <a href="?action=reject&id=<?= $row['id'] ?>" 
                                   class="action-btn reject-btn inline-flex items-center"
                                   onclick="return confirm('Reject this leave request?')">
                                    <i class="fas fa-times mr-1"></i>
                                    Reject
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state container-card">
                <i class="fas fa-calendar-times"></i>
                <h3 class="text-xl font-bold text-text mb-2">No Leave Requests</h3>
                <p class="text-text/70 mb-4">All leave requests have been processed.</p>
                <div class="text-text/50 text-sm">
                    <i class="fas fa-info-circle mr-2"></i>
                    New leave requests will appear here when submitted by volunteers
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Add confirmation for actions
        document.addEventListener('DOMContentLoaded', function() {
            const actionLinks = document.querySelectorAll('a[href*="action="]');
            
            actionLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    const action = this.href.includes('approve') ? 'approve' : 'reject';
                    const message = `Are you sure you want to ${action} this leave request?`;
                    
                    if (!confirm(message)) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>