<?php
session_start();
include '../auth/db.php';

if (!isset($_SESSION['volunteer_id'])) {
    header("Location: ../auth/volunteer_login.php");
    exit();
}

$volunteer_id = $_SESSION['volunteer_id'];

// Fetch upcoming events
$events = $conn->query("SELECT event_id, title, event_date FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_id = $_POST['event_id'];
    $leave_date = $_POST['leave_date'];
    $reason = trim($_POST['reason']);
    $note = trim($_POST['note']);

    if ($event_id && $leave_date && $reason) {
        $stmt = $conn->prepare("INSERT INTO volunteer_leaves (volunteer_id, event_id, leave_date, reason, additional_note) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $volunteer_id, $event_id, $leave_date, $reason, $note);
        $stmt->execute();
        $success = "Leave request submitted successfully.";
    } else {
        $error = "Please fill all required fields.";
    }
}

// Fetch leave history
$leaves = $conn->query("
    SELECT vl.*, e.title, e.event_date
    FROM volunteer_leaves vl
    JOIN events e ON vl.event_id = e.event_id
    WHERE vl.volunteer_id = $volunteer_id
    ORDER BY vl.created_at DESC
");

// Get leave statistics
$total_leaves = $leaves->num_rows;
$approved_count = 0;
$pending_count = 0;
$rejected_count = 0;

if ($leaves) {
    $leaves_data = $leaves->fetch_all(MYSQLI_ASSOC);
    foreach($leaves_data as $leave) {
        if ($leave['status'] == 'Approved') $approved_count++;
        elseif ($leave['status'] == 'Rejected') $rejected_count++;
        else $pending_count++;
    }
    // Reset pointer for later use
    $leaves->data_seek(0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Request | VMS</title>
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

        .stat-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #D5D8DC;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .engagement-card {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(93, 173, 226, 0.3);
        }

        .leave-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            border: 1px solid #D5D8DC;
            transition: all 0.2s ease;
        }

        .leave-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-color: #5DADE2;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .status-approved {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
        }

        .status-rejected {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: #92400e;
        }

        .submit-btn {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(93, 173, 226, 0.3);
        }

        .form-input {
            border: 1px solid #D5D8DC;
            border-radius: 12px;
            padding: 12px 16px;
            transition: all 0.2s ease;
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: #5DADE2;
            box-shadow: 0 0 0 3px rgba(93, 173, 226, 0.1);
        }

        .empty-state {
            background: linear-gradient(135deg, #FBFCFC 0%, #f8fafc 100%);
            border: 2px dashed #D5D8DC;
        }

        /* Mobile First Responsive Design */
        @media (max-width: 640px) {
            .page-container {
                padding: 1rem;
            }

            .stat-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .leave-card {
                padding: 1rem;
            }
        }

        @media (min-width: 641px) and (max-width: 768px) {
            .page-container {
                padding: 1.5rem;
            }

            .stat-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 1rem;
            }
        }

        @media (min-width: 769px) {
            .page-container {
                padding: 2rem;
                width: calc(100% - 16rem);
                margin-left: 16rem;
                max-width: 1200px;
            }

            .stat-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 1.5rem;
            }
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="page-container lg:ml-72">
        <!-- Header Section -->
        <div class="header-section mt-12 sm:mt-0 mb-8 text-center sm:text-left">
            <h1 class="text-2xl sm:text-3xl font-bold text-text mb-2">Leave Request</h1>
            <p class="text-text/70">Submit and manage your leave requests for upcoming events</p>
        </div>

        <!-- Statistics Grid -->
        <div class="stat-grid grid mb-8">
            <!-- Upcoming Events -->
            <div class="stat-card p-6">
                <div class="flex items-center">
                    <div class="stat-icon bg-blue-100 text-primary mr-4">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div>
                        <h4 class="text-lg font-semibold text-text">Upcoming Events</h4>
                        <p class="text-2xl font-bold text-primary"><?= $events->num_rows ?? 0 ?></p>
                        <p class="text-sm text-text/60">available for leave requests</p>
                    </div>
                </div>
            </div>

            <!-- Approved Leaves -->
            <div class="stat-card p-6">
                <div class="flex items-center">
                    <div class="stat-icon bg-green-100 text-green-600 mr-4">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <h4 class="text-lg font-semibold text-text">Approved Leaves</h4>
                        <p class="text-2xl font-bold text-green-600"><?= $approved_count ?></p>
                        <p class="text-sm text-text/60">approved requests</p>
                    </div>
                </div>
            </div>

            <!-- Pending Requests -->
            <div class="stat-card p-6">
                <div class="flex items-center">
                    <div class="stat-icon bg-yellow-100 text-yellow-600 mr-4">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <h4 class="text-lg font-semibold text-text">Pending Requests</h4>
                        <p class="text-2xl font-bold text-yellow-600"><?= $pending_count ?></p>
                        <p class="text-sm text-text/60">awaiting approval</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Leave Request Form -->
            <div class="leave-card p-6">
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-text flex items-center">
                        <i class="fas fa-paper-plane mr-3 text-primary"></i>
                        Submit Leave Request
                    </h2>
                    <p class="text-text/60 mt-1">Fill out the form to request leave for an upcoming event</p>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                            <p class="text-red-700"><?= $error ?></p>
                        </div>
                    </div>
                <?php elseif (!empty($success)): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            <p class="text-green-700"><?= $success ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block mb-2 font-semibold text-text">Select Event <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="event_id" required class="form-input w-full pl-12 appearance-none">
                                <option value="">-- Select Event --</option>
                                <?php while ($e = $events->fetch_assoc()): ?>
                                    <option value="<?= $e['event_id'] ?>"><?= htmlspecialchars($e['title']) ?> (<?= $e['event_date'] ?>)</option>
                                <?php endwhile; ?>
                            </select>
                            <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
                                <i class="fas fa-calendar-alt text-text/40"></i>
                            </div>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                                <i class="fas fa-chevron-down text-text/40"></i>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block mb-2 font-semibold text-text">Leave Date <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="date" name="leave_date" required class="form-input w-full pl-12">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
                                <i class="fas fa-calendar-day text-text/40"></i>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block mb-2 font-semibold text-text">Reason <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="text" name="reason" required class="form-input w-full pl-12" placeholder="Reason for leave">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
                                <i class="fas fa-comment-dots text-text/40"></i>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block mb-2 font-semibold text-text">Additional Note (optional)</label>
                        <div class="relative">
                            <textarea name="note" rows="3" class="form-input w-full pl-12" placeholder="Any additional information..."></textarea>
                            <div class="absolute top-4 left-4 pointer-events-none">
                                <i class="fas fa-sticky-note text-text/40"></i>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn w-full flex items-center justify-center">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Submit Request
                    </button>
                </form>
            </div>

            <!-- Leave History -->
            <div class="leave-card p-6">
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-text flex items-center">
                        <i class="fas fa-history mr-3 text-accent"></i>
                        My Leave Requests
                    </h2>
                    <p class="text-text/60 mt-1">Review your submitted leave requests and their status</p>
                </div>
                
                <?php if ($leaves && $leaves->num_rows > 0): ?>
                    <div class="space-y-4 max-h-96 overflow-y-auto pr-2">
                        <?php while ($leave = $leaves->fetch_assoc()): ?>
                        <div class="leave-card p-4">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h3 class="font-semibold text-text"><?= htmlspecialchars($leave['title']) ?></h3>
                                    <p class="text-sm text-text/60 mt-1">
                                        <i class="far fa-calendar mr-1"></i>
                                        <?= $leave['leave_date'] ?>
                                    </p>
                                    <p class="text-sm text-text mt-2">
                                        <i class="far fa-comment mr-1"></i>
                                        <?= htmlspecialchars($leave['reason']) ?>
                                    </p>
                                </div>
                                <div>
                                    <?php if ($leave['status'] == 'Approved'): ?>
                                        <span class="status-badge status-approved">
                                            <i class="fas fa-check-circle mr-1"></i> <?= $leave['status'] ?>
                                        </span>
                                    <?php elseif ($leave['status'] == 'Rejected'): ?>
                                        <span class="status-badge status-rejected">
                                            <i class="fas fa-times-circle mr-1"></i> <?= $leave['status'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">
                                            <i class="fas fa-clock mr-1"></i> <?= $leave['status'] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($leave['additional_note'])): ?>
                                <div class="mt-3 p-3 bg-blue-50 rounded-lg">
                                    <p class="text-sm text-text">
                                        <i class="fas fa-sticky-note mr-1 text-primary"></i>
                                        <?= htmlspecialchars($leave['additional_note']) ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state rounded-lg p-8 text-center">
                        <i class="fas fa-inbox text-4xl text-text/30 mb-3"></i>
                        <h3 class="text-lg font-semibold text-text mb-2">No Leave Requests</h3>
                        <p class="text-text/60">Submit your first leave request using the form</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Form validation enhancement
        document.querySelector('form').addEventListener('submit', function(e) {
            const eventId = document.querySelector('select[name="event_id"]').value;
            const leaveDate = document.querySelector('input[name="leave_date"]').value;
            const reason = document.querySelector('input[name="reason"]').value;
            
            if (!eventId || !leaveDate || !reason) {
                e.preventDefault();
                // Add shake animation to empty fields
                const emptyFields = document.querySelectorAll('select[name="event_id"], input[name="leave_date"], input[name="reason"]');
                emptyFields.forEach(field => {
                    if (!field.value) {
                        field.classList.add('animate-pulse', 'border-red-500');
                        setTimeout(() => {
                            field.classList.remove('animate-pulse', 'border-red-500');
                        }, 2000);
                    }
                });
            }
        });
    </script>
</body>
</html>