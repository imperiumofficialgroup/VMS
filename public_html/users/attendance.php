<?php
session_start();
include '../auth/db.php';

if (!isset($_SESSION['volunteer_id'])) {
    header("Location: ../auth/volunteer_login.php");
    exit();
}

$volunteer_id = $_SESSION['volunteer_id'];

$query = "
    SELECT 
        e.event_id,
        e.title,
        e.event_date,
        e.location,
        ea.status,
        ea.marked_at
    FROM events e
    LEFT JOIN event_attendance ea 
        ON e.event_id = ea.event_id_fk AND ea.volunteer_id_fk = ?
    ORDER BY e.event_date DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $volunteer_id);
$stmt->execute();
$result = $stmt->get_result();

// Store all results in an array first
$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}

// --- AI-Like Attendance Insights ---
$total_events = count($events);
$present_count = 0;
$absent_count = 0;
$streak = 0;

// Track streak logic (latest first)
foreach ($events as $row) {
    if ($row['status'] == "Present") {
        $present_count++;
        if ($streak >= 0) {
            $streak++;
        }
    } elseif ($row['status'] == "Absent") {
        $absent_count++;
        $streak = -1; // break streak
    }
}

$attendance_rate = $total_events > 0 ? round(($present_count / $total_events) * 100) : 0;

// Reset streak to 0 if broken
if ($streak < 0) $streak = 0;

// Missed events
$missed_count = $absent_count;

// --- Get User Attendance Stats ---
$total_events = count($events);
$present_count = 0;
$absent_count = 0;
$streak = 0;

foreach ($events as $row) {
    if ($row['status'] == "Present") {
        $present_count++;
        if ($streak >= 0) $streak++;
    } elseif ($row['status'] == "Absent") {
        $absent_count++;
        $streak = -1;
    }
}

$attendance_rate = $total_events > 0 ? round(($present_count / $total_events) * 100) : 0;
if ($streak < 0) $streak = 0;
$missed_count = $absent_count;

// --- Compare with Others (Leaderboard Logic) ---
$all_stats = [];
$query = "SELECT a.volunteer_id_fk, 
                 SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) as presents,
                 COUNT(*) as total
          FROM event_attendance a
          GROUP BY a.volunteer_id_fk";
$res = $conn->query($query);

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $rate = $row['total'] > 0 ? round(($row['presents'] / $row['total']) * 100) : 0;
        $all_stats[$row['volunteer_id_fk']] = $rate;
    }
}

// Sort to find ranking
arsort($all_stats);
$rank = array_search($volunteer_id, array_keys($all_stats)) + 1;
$total_users = count($all_stats);

// Engagement level
if ($attendance_rate >= 80) {
    $engagement_level = "Highly Engaged";
} elseif ($attendance_rate >= 50) {
    $engagement_level = "Moderately Engaged";
} else {
    $engagement_level = "Low Engagement";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance | VMS</title>
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

        .event-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            border: 1px solid #D5D8DC;
            transition: all 0.2s ease;
        }

        .event-card:hover {
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

        .status-present {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
        }

        .status-absent {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
        }

        .status-pending {
            background: rgba(156, 163, 175, 0.1);
            color: #374151;
        }

        .certificate-btn {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .certificate-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(93, 173, 226, 0.3);
        }

        .table-row:hover {
            background: rgba(93, 173, 226, 0.02);
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

            .event-card {
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

        .empty-state {
            background: linear-gradient(135deg, #FBFCFC 0%, #f8fafc 100%);
            border: 2px dashed #D5D8DC;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="page-container lg:ml-72">
        <!-- Header Section -->
        <div class="header-section mt-12 sm:mt-0 mb-8 text-center sm:text-left">
            <h1 class="text-2xl sm:text-3xl font-bold text-text mb-2">My Event Attendance</h1>
            <p class="text-text/70">Track your participation and engagement across all events</p>
        </div>

        <!-- Statistics Grid -->
        <div class="stat-grid grid mb-8">
            <!-- Attendance Rate -->
            <div class="stat-card p-6">
                <div class="flex items-center">
                    <div class="stat-icon bg-blue-100 text-primary mr-4">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div>
                        <h4 class="text-lg font-semibold text-text">Attendance Rate</h4>
                        <p class="text-2xl font-bold text-primary"><?= $attendance_rate ?>%</p>
                        <p class="text-sm text-text/60">of total events attended</p>
                    </div>
                </div>
            </div>

            <!-- Current Streak -->
            <div class="stat-card p-6">
                <div class="flex items-center">
                    <div class="stat-icon bg-green-100 text-green-600 mr-4">
                        <i class="fas fa-fire"></i>
                    </div>
                    <div>
                        <h4 class="text-lg font-semibold text-text">Current Streak</h4>
                        <p class="text-2xl font-bold text-green-600"><?= $streak ?></p>
                        <p class="text-sm text-text/60">consecutive events</p>
                    </div>
                </div>
            </div>

            <!-- Missed Events -->
            <div class="stat-card p-6">
                <div class="flex items-center">
                    <div class="stat-icon bg-red-100 text-red-600 mr-4">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <h4 class="text-lg font-semibold text-text">Missed Events</h4>
                        <p class="text-2xl font-bold text-red-600"><?= $missed_count ?></p>
                        <p class="text-sm text-text/60">total absences</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Engagement Card -->
        <div class="engagement-card p-6 mb-8 text-white">
            <div class="flex items-center">
                <div class="bg-white/20 p-3 rounded-full mr-4">
                    <i class="fas fa-trophy text-yellow-300"></i>
                </div>
                <div class="flex-1">
                    <h4 class="text-lg font-semibold">Your Engagement Level</h4>
                    <p class="text-2xl font-bold mb-2"><?= $engagement_level ?></p>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <p class="text-sm opacity-90">
                            Ranked <span class="font-bold">#<?= $rank ?></span> out of <?= $total_users ?> volunteers
                        </p>
                        <p class="text-sm font-medium">
                            <?= ($rank <= round($total_users*0.1)) 
                                ? "🔥 Amazing! You're in the top 10%!" 
                                : (($rank <= round($total_users*0.5)) 
                                    ? "👏 Keep it up! You're above average!" 
                                    : "💡 You can climb the ranks by attending more events!") ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Event Cards -->
        <div class="lg:hidden space-y-4 mb-8">
            <?php if (!empty($events)): ?>
                <?php foreach ($events as $row): ?>
                    <div class="event-card p-4">
                        <!-- Status Badge -->
                        <div class="mb-3">
                            <?php if ($row['status']): ?>
                                <?php if ($row['status'] == "Present"): ?>
                                    <span class="status-badge status-present">
                                        <i class="fas fa-check"></i> Present
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-absent">
                                        <i class="fas fa-times"></i> Absent
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="status-badge status-pending">
                                    <i class="fas fa-question"></i> Pending
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Event Details -->
                        <div>
                            <h3 class="font-semibold text-text text-lg leading-tight mb-2">
                                <?= htmlspecialchars($row['title']) ?>
                            </h3>
                            
                            <div class="space-y-2 text-sm text-text/70">
                                <div class="flex items-center">
                                    <i class="far fa-calendar mr-2 w-4"></i>
                                    <?= date("M j, Y", strtotime($row['event_date'])) ?>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-map-marker-alt mr-2 w-4"></i>
                                    <?= htmlspecialchars($row['location']) ?>
                                </div>
                            </div>

                            <!-- Certificate Button for Present Events -->
                            <?php if ($row['status'] === "Present" && strtotime($row['event_date']) <= time()): ?>
                                <div class="mt-3">
                                    <a href="generate_certificate.php?event_id=<?= htmlspecialchars($row['event_id']); ?>" 
                                       class="certificate-btn inline-flex items-center">
                                        <i class="fas fa-file-pdf mr-2"></i> Download Certificate
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state rounded-lg p-8 text-center">
                    <i class="fas fa-calendar-times text-4xl text-text/30 mb-3"></i>
                    <h3 class="text-lg font-semibold text-text mb-2">No Events Found</h3>
                    <p class="text-text/60">You haven't attended any events yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Desktop Event Table -->
        <div class="hidden lg:block bg-white rounded-xl shadow-sm border border-subtle overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-subtle">
                    <thead class="bg-background">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-sm font-semibold text-text uppercase tracking-wider">
                                <i class="fas fa-calendar-alt mr-2"></i>Event
                            </th>
                            <th scope="col" class="px-6 py-4 text-left text-sm font-semibold text-text uppercase tracking-wider">
                                <i class="far fa-clock mr-2"></i>Date
                            </th>
                            <th scope="col" class="px-6 py-4 text-left text-sm font-semibold text-text uppercase tracking-wider">
                                <i class="fas fa-map-marker-alt mr-2"></i>Location
                            </th>
                            <th scope="col" class="px-6 py-4 text-left text-sm font-semibold text-text uppercase tracking-wider">
                                <i class="fas fa-check-circle mr-2"></i>Status
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-subtle">
                        <?php if (!empty($events)): ?>
                            <?php foreach ($events as $row): ?>
                                <tr class="table-row transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-text"><?= htmlspecialchars($row['title']) ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-text/70"><?= date("M j, Y", strtotime($row['event_date'])) ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-text/70"><?= htmlspecialchars($row['location']) ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center space-x-3">
                                            <?php if ($row['status'] === "Present"): ?>
                                                <span class="status-badge status-present">
                                                    <i class="fas fa-check"></i> Present
                                                </span>
                                                <?php if (strtotime($row['event_date']) <= time()): ?>
                                                    <a href="generate_certificate.php?event_id=<?= htmlspecialchars($row['event_id']); ?>" 
                                                       class="certificate-btn inline-flex items-center text-xs">
                                                        <i class="fas fa-file-pdf mr-1"></i> Certificate
                                                    </a>
                                                <?php endif; ?>
                                            <?php elseif ($row['status'] === "Absent"): ?>
                                                <span class="status-badge status-absent">
                                                    <i class="fas fa-times"></i> Absent
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge status-pending">
                                                    <i class="fas fa-question"></i> Pending
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center">
                                    <div class="empty-state rounded-lg p-8">
                                        <i class="fas fa-calendar-times text-4xl text-text/30 mb-3"></i>
                                        <h3 class="text-lg font-semibold text-text mb-2">No Events Found</h3>
                                        <p class="text-text/60">You haven't attended any events yet.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Close mobile sidebar when clicking on a nav item
        document.querySelectorAll('nav a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1024) {
                    Alpine.store('mobileSidebarOpen', false);
                }
            });
        });
    </script>
</body>
</html>