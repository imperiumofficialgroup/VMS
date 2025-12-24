<?php
session_start();
include '../auth/db.php';

if (!isset($_SESSION['volunteer_id'])) {
    header("Location: ../login.php");
    exit;
}

$volunteer_id = $_SESSION['volunteer_id'];

// Handle task completion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['complete_task'])) {
    $task_id = intval($_POST['task_id']);
    
    // Check if task exists and is active
    $task_check = $conn->query("SELECT * FROM tasks WHERE id = $task_id AND active = 1");
    if ($task_check->num_rows > 0) {
        $task = $task_check->fetch_assoc();
        
        // Check if not already completed
        $completion_check = $conn->query("SELECT * FROM volunteer_tasks WHERE volunteer_id = $volunteer_id AND task_id = $task_id");
        if ($completion_check->num_rows == 0) {
            
            // Award points (for tasks, we don't set event_id)
            $conn->query("INSERT INTO volunteer_points (volunteer_id, points, created_at) VALUES ($volunteer_id, {$task['points_reward']}, NOW())");
            
            // Mark task as completed
            $conn->query("INSERT INTO volunteer_tasks (volunteer_id, task_id, completed_at, points_earned) VALUES ($volunteer_id, $task_id, NOW(), {$task['points_reward']})");
            
            $_SESSION['success_message'] = "Task completed! You earned {$task['points_reward']} points!";
            
            // Check for badge unlocks
            checkBadgeUnlocks($volunteer_id, $conn);
        }
    }
    header("Location: gamifyed.php");
    exit;
}

// FIXED: Award event attendance points automatically (USING YOUR ACTUAL DATABASE STRUCTURE)
$award_event_points = $conn->query("
    SELECT ea.*, e.title 
    FROM event_attendance ea
    LEFT JOIN events e ON e.event_id = ea.event_id_fk
    LEFT JOIN volunteer_points vp 
        ON vp.volunteer_id = ea.volunteer_id_fk 
        AND vp.event_id = ea.event_id_fk
    WHERE ea.volunteer_id_fk = $volunteer_id 
    AND ea.status = 'Present' 
    AND vp.id IS NULL
");

$new_event_points = 0;
$new_event_titles = [];

while ($event_attendance = $award_event_points->fetch_assoc()) {
    // Award 20 points for event attendance (only once per event)
    $conn->query("
        INSERT INTO volunteer_points (volunteer_id, event_id, points, created_at) 
        VALUES ($volunteer_id, {$event_attendance['event_id_fk']}, 20, NOW())
    ");
    
    $new_event_points += 20;
    $new_event_titles[] = $event_attendance['title'];
    
    // Check for badge unlocks after awarding event points
    checkBadgeUnlocks($volunteer_id, $conn);
}

// Show success message for newly awarded event points
if ($new_event_points > 0) {
    $event_titles_str = implode(", ", array_unique($new_event_titles));
    $_SESSION['success_message'] = "You earned {$new_event_points} points for attending: {$event_titles_str}!";
}

// KPI Data
// Total points
$points_result = $conn->query("SELECT COALESCE(SUM(points), 0) as total_points FROM volunteer_points WHERE volunteer_id = $volunteer_id");
$total_points = $points_result->fetch_assoc()['total_points'];

// Events attended
$attendance_result = $conn->query("SELECT COUNT(*) as attended_events FROM event_attendance WHERE volunteer_id_fk = $volunteer_id AND status = 'Present'");
$attended_events_count = $attendance_result->fetch_assoc()['attended_events'];

// Completed tasks
$tasks_completed_result = $conn->query("SELECT COUNT(*) as completed_tasks FROM volunteer_tasks WHERE volunteer_id = $volunteer_id");
$completed_tasks = $tasks_completed_result->fetch_assoc()['completed_tasks'];

// Calculate current streak - SIMPLIFIED
$streak_result = $conn->query("
    SELECT COUNT(DISTINCT DATE(marked_at)) as current_streak
    FROM event_attendance 
    WHERE volunteer_id_fk = $volunteer_id 
    AND status = 'Present' 
    AND marked_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
");
$current_streak = $streak_result->fetch_assoc()['current_streak'] ?? 0;

// Function to check badge unlocks - FIXED VARIABLE SCOPE ISSUE
function checkBadgeUnlocks($volunteer_id, $conn) {
    // Recalculate stats for badge checking - FIXED: Use fresh queries inside function
    $points_result = $conn->query("SELECT COALESCE(SUM(points), 0) as total_points FROM volunteer_points WHERE volunteer_id = $volunteer_id");
    $total_points = $points_result->fetch_assoc()['total_points'];
    
    $attendance_result = $conn->query("SELECT COUNT(*) as attended_events FROM event_attendance WHERE volunteer_id_fk = $volunteer_id AND status = 'Present'");
    $attended_events = $attendance_result->fetch_assoc()['attended_events']; // FIXED: Consistent variable name
    
    $tasks_completed_result = $conn->query("SELECT COUNT(*) as completed_tasks FROM volunteer_tasks WHERE volunteer_id = $volunteer_id");
    $completed_tasks = $tasks_completed_result->fetch_assoc()['completed_tasks'];
    
    // Calculate current streak
    $streak_result = $conn->query("
        SELECT COUNT(DISTINCT DATE(marked_at)) as current_streak
        FROM event_attendance 
        WHERE volunteer_id_fk = $volunteer_id 
        AND status = 'Present' 
        AND marked_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $current_streak = $streak_result->fetch_assoc()['current_streak'] ?? 0;
    
    // Check all badges
    $badges = $conn->query("SELECT * FROM badges");
    while ($badge = $badges->fetch_assoc()) {
        $unlocked = false;
        
        // DEBUG: Log badge checking
        error_log("Checking badge: {$badge['name']}, Type: {$badge['type']}, Required: {$badge[$badge['type'].'_required']}");
        
        if ($badge['type'] == 'points' && $total_points >= $badge['points_required']) {
            $unlocked = true;
            error_log("Points badge unlocked: {$badge['name']} - $total_points >= {$badge['points_required']}");
        } elseif ($badge['type'] == 'events' && $attended_events >= $badge['events_required']) {
            $unlocked = true;
            error_log("Events badge unlocked: {$badge['name']} - $attended_events >= {$badge['events_required']}");
        } elseif ($badge['type'] == 'tasks' && $completed_tasks >= $badge['tasks_required']) {
            $unlocked = true;
            error_log("Tasks badge unlocked: {$badge['name']} - $completed_tasks >= {$badge['tasks_required']}");
        } elseif ($badge['type'] == 'streak' && $current_streak >= $badge['streak_required']) {
            $unlocked = true;
            error_log("Streak badge unlocked: {$badge['name']} - $current_streak >= {$badge['streak_required']}");
        }
        
        if ($unlocked) {
            // Check if already unlocked
            $check = $conn->query("SELECT * FROM volunteer_badges WHERE volunteer_id = $volunteer_id AND badge_id = {$badge['id']}");
            if ($check->num_rows == 0) {
                $conn->query("INSERT INTO volunteer_badges (volunteer_id, badge_id, unlocked_at) VALUES ($volunteer_id, {$badge['id']}, NOW())");
                
                // Award badge points (without event_id since it's a badge reward)
                $badge_points = 25;
                $conn->query("INSERT INTO volunteer_points (volunteer_id, points, created_at) VALUES ($volunteer_id, $badge_points, NOW())");
                
                $_SESSION['badge_unlocked'] = "Congratulations! You unlocked the '{$badge['name']}' badge and earned $badge_points points!";
                error_log("BADGE UNLOCKED: {$badge['name']} for volunteer $volunteer_id");
            }
        }
    }
}

// Badges with progress calculation - FIXED: Call checkBadgeUnlocks to ensure badges are checked
checkBadgeUnlocks($volunteer_id, $conn);

$badges_result = $conn->query("
    SELECT b.*, 
           vb.unlocked_at,
           CASE 
               WHEN vb.unlocked_at IS NOT NULL THEN 1 
               ELSE 0 
           END as unlocked
    FROM badges b
    LEFT JOIN volunteer_badges vb ON b.id = vb.badge_id AND vb.volunteer_id = $volunteer_id
    ORDER BY b.points_required ASC
");
$all_badges = [];
while ($badge = $badges_result->fetch_assoc()) {
    // Calculate progress based on badge type and requirements
    $progress = 0;
    $current_value = 0;
    $required_value = 0;
    
    if ($badge['type'] == 'points') {
        $current_value = $total_points;
        $required_value = $badge['points_required'];
        $progress = $required_value > 0 ? min(100, ($current_value / $required_value) * 100) : 0;
    } elseif ($badge['type'] == 'events') {
        $current_value = $attended_events_count;
        $required_value = $badge['events_required'];
        $progress = $required_value > 0 ? min(100, ($current_value / $required_value) * 100) : 0;
    } elseif ($badge['type'] == 'streak') {
        $current_value = $current_streak;
        $required_value = $badge['streak_required'];
        $progress = $required_value > 0 ? min(100, ($current_value / $required_value) * 100) : 0;
    } elseif ($badge['type'] == 'tasks') {
        $current_value = $completed_tasks;
        $required_value = $badge['tasks_required'];
        $progress = $required_value > 0 ? min(100, ($current_value / $required_value) * 100) : 0;
    } else {
        // Default to points-based
        $current_value = $total_points;
        $required_value = $badge['points_required'];
        $progress = $required_value > 0 ? min(100, ($current_value / $required_value) * 100) : 0;
    }
    
    $badge['progress'] = round($progress);
    $badge['current_value'] = $current_value;
    $badge['required_value'] = $required_value;
    $all_badges[] = $badge;
}

// Tasks for the volunteer
$tasks_result = $conn->query("
    SELECT t.*, 
           CASE 
               WHEN vt.completed_at IS NOT NULL THEN 1 
               ELSE 0 
           END as completed,
           CASE 
               WHEN t.type = 'points' THEN ($total_points / t.requirement_value) * 100
               WHEN t.type = 'events' THEN ($attended_events_count / t.requirement_value) * 100
               WHEN t.type = 'streak' THEN ($current_streak / t.requirement_value) * 100
               WHEN t.type = 'tasks' THEN ($completed_tasks / t.requirement_value) * 100
               ELSE 0 
           END as progress
    FROM tasks t
    LEFT JOIN volunteer_tasks vt ON t.id = vt.task_id AND vt.volunteer_id = $volunteer_id
    WHERE t.active = 1
    ORDER BY t.points_reward DESC
");
$tasks = [];
while ($task = $tasks_result->fetch_assoc()) {
    $tasks[] = $task;
}

// Next badge suggestion
$next_badge = null;
foreach ($all_badges as $b) {
    if (!$b['unlocked']) {
        if (!$next_badge || $b['progress'] > $next_badge['progress']) {
            $next_badge = $b;
        }
    }
}

// Check for newly unlocked badges
if (isset($_SESSION['badge_unlocked'])) {
    $_SESSION['success_message'] = $_SESSION['badge_unlocked'];
    unset($_SESSION['badge_unlocked']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Achievements Dashboard | VMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <script>
        tailwind.config = {
            theme: {
                extend: {
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
    <style>
        * {
            box-sizing: border-box;
        }
        
        html, body {
            max-width: 100%;
            overflow-x: hidden;
            font-family: 'Inter', sans-serif;
        }
        
        .page-container {
            max-width: 100%;
            overflow-x: hidden;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #E5E7EB;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .task-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #E5E7EB;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .task-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .badge-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #E5E7EB;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .badge-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .badge-locked { 
            filter: grayscale(80%) opacity(70%); 
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(93, 173, 226, 0.3);
        }
        
        .btn-primary:disabled {
            background: #D5D8DC;
            cursor: not-allowed;
            transform: none;
        }
        
        .progress-ring {
            transform: rotate(-90deg);
        }
        
        .progress-ring-circle {
            transition: stroke-dashoffset 0.35s;
            transform: rotate(90deg);
            transform-origin: 50% 50%;
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-background">
    <?php include 'sidebar.php'; ?>

    <div class="page-container lg:ml-72 pt-6 px-4 md:px-6" x-data="achievementsPage()">
        <!-- Header Section -->
<div class="text-center mb-8 mt-12 md:mt-0">
            <h1 class="text-2xl sm:text-3xl font-bold text-text mb-3">Achievements Dashboard</h1>
            <p class="text-text/70 text-lg">Track your progress, complete tasks, and earn rewards</p>
        </div>

        <!-- Success Message -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-lg mx-auto max-w-4xl">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <p class="text-green-700"><?= $_SESSION['success_message'] ?></p>
                </div>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <!-- Statistics Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4 mb-8 max-w-6xl mx-auto">
            <!-- Total Points -->
            <div class="stat-card p-4 md:p-5 col-span-2 lg:col-span-1">
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-primary to-accent flex items-center justify-center mr-3 md:mr-4">
                        <i class="fas fa-star text-white text-lg md:text-xl"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="text-sm md:text-base font-semibold text-text truncate">Total Points</h4>
                        <p class="text-xl md:text-2xl font-bold text-primary"><?= number_format($total_points) ?></p>
                        <p class="text-xs text-text/60 truncate">Your achievement score</p>
                    </div>
                </div>
            </div>

            <!-- Events Attended -->
            <div class="stat-card p-4 md:p-5">
                <div class="flex flex-col items-center text-center">
                    <div class="w-10 h-10 md:w-12 md:h-12 rounded-xl bg-gradient-to-r from-green-500 to-green-600 flex items-center justify-center mb-2">
                        <i class="fas fa-calendar-check text-white text-base md:text-lg"></i>
                    </div>
                    <h4 class="text-sm font-semibold text-text mb-1">Events</h4>
                    <p class="text-lg md:text-xl font-bold text-green-600"><?= $attended_events_count ?></p>
                    <p class="text-xs text-text/60">+20 pts each</p>
                </div>
            </div>

            <!-- Current Streak -->
            <div class="stat-card p-4 md:p-5">
                <div class="flex flex-col items-center text-center">
                    <div class="w-10 h-10 md:w-12 md:h-12 rounded-xl bg-gradient-to-r from-orange-500 to-orange-600 flex items-center justify-center mb-2">
                        <i class="fas fa-fire text-white text-base md:text-lg"></i>
                    </div>
                    <h4 class="text-sm font-semibold text-text mb-1">Streak</h4>
                    <p class="text-lg md:text-xl font-bold text-orange-600"><?= $current_streak ?>d</p>
                    <p class="text-xs text-text/60">Keep going!</p>
                </div>
            </div>

            <!-- Tasks Completed -->
            <div class="stat-card p-4 md:p-5">
                <div class="flex flex-col items-center text-center">
                    <div class="w-10 h-10 md:w-12 md:h-12 rounded-xl bg-gradient-to-r from-purple-500 to-purple-600 flex items-center justify-center mb-2">
                        <i class="fas fa-tasks text-white text-base md:text-lg"></i>
                    </div>
                    <h4 class="text-sm font-semibold text-text mb-1">Tasks</h4>
                    <p class="text-lg md:text-xl font-bold text-purple-600"><?= $completed_tasks ?></p>
                    <p class="text-xs text-text/60">Completed</p>
                </div>
            </div>
        </div>

        <!-- Next Badge Suggestion -->
        <?php if ($next_badge): ?>
        <div class="bg-gradient-to-r from-primary to-accent text-white rounded-2xl p-5 md:p-6 mb-8 shadow-lg max-w-6xl mx-auto">
            <div class="flex flex-col md:flex-row items-center">
                <div class="mb-4 md:mb-0 md:mr-6">
                    <img src="../uploads/badges/<?= htmlspecialchars($next_badge['image_path']) ?>" 
                         alt="<?= htmlspecialchars($next_badge['name']) ?>" 
                         class="w-20 h-20 md:w-24 md:h-24 rounded-full border-4 border-white/50 <?= $next_badge['unlocked'] ? '' : 'badge-locked' ?>">
                </div>
                <div class="flex-1 text-center md:text-left">
                    <h2 class="text-lg md:text-xl font-bold mb-2 flex items-center justify-center md:justify-start">
                        <i class="fas fa-flag mr-3"></i> Next Badge to Unlock
                    </h2>
                    <p class="text-base md:text-lg font-semibold mb-2"><?= htmlspecialchars($next_badge['name']) ?></p>
                    <p class="text-sm opacity-90 mb-4 line-clamp-2"><?= htmlspecialchars($next_badge['description']) ?></p>
                    
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-sm">
                            <span>Progress</span>
                            <span class="font-semibold"><?= $next_badge['progress'] ?>%</span>
                        </div>
                        <div class="w-full bg-white/20 h-3 rounded-full">
                            <div class="bg-white h-3 rounded-full transition-all duration-500" 
                                 style="width:<?= $next_badge['progress'] ?>%"></div>
                        </div>
                        <p class="text-xs opacity-80">
                            <?php
                            $type_text = "";
                            if ($next_badge['type'] == 'points') {
                                $type_text = "Earn {$next_badge['required_value']} points ({$next_badge['current_value']}/{$next_badge['required_value']})";
                            } elseif ($next_badge['type'] == 'events') {
                                $type_text = "Attend {$next_badge['required_value']} events ({$next_badge['current_value']}/{$next_badge['required_value']})";
                            } elseif ($next_badge['type'] == 'streak') {
                                $type_text = "Maintain {$next_badge['required_value']}-day streak ({$next_badge['current_value']}/{$next_badge['required_value']})";
                            } elseif ($next_badge['type'] == 'tasks') {
                                $type_text = "Complete {$next_badge['required_value']} tasks ({$next_badge['current_value']}/{$next_badge['required_value']})";
                            }
                            echo $type_text;
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Content Tabs -->
        <div class="max-w-6xl mx-auto mb-8">
            <div class="flex space-x-1 bg-white p-1 rounded-2xl shadow-sm border border-subtle w-fit mx-auto mb-8">
                <button @click="activeTab = 'tasks'" 
                        :class="activeTab === 'tasks' ? 'bg-primary text-white shadow-lg' : 'text-text/60 hover:text-text'"
                        class="px-4 md:px-6 py-3 rounded-xl font-semibold transition-all duration-300 flex items-center text-sm md:text-base">
                    <i class="fas fa-tasks mr-2"></i>
                    Tasks
                </button>
                <button @click="activeTab = 'badges'" 
                        :class="activeTab === 'badges' ? 'bg-accent text-white shadow-lg' : 'text-text/60 hover:text-text'"
                        class="px-4 md:px-6 py-3 rounded-xl font-semibold transition-all duration-300 flex items-center text-sm md:text-base">
                    <i class="fas fa-trophy mr-2"></i>
                    Badges
                </button>
            </div>

            <!-- Tasks Section -->
            <div x-show="activeTab === 'tasks'" x-cloak x-transition>
                <div class="text-center mb-6">
                    <h2 class="text-xl md:text-2xl font-bold text-text mb-2">Available Tasks</h2>
                    <p class="text-text/60">Complete tasks to earn points and unlock achievements</p>
                    <div class="mt-2 text-primary font-semibold">
                        <?= count(array_filter($tasks, function($t) { return $t['completed']; })) ?> / <?= count($tasks) ?> Completed
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
                    <?php foreach($tasks as $task): ?>
                    <div class="task-card p-5 md:p-6">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="font-bold text-text text-base md:text-lg line-clamp-2 flex-1 mr-2"><?= htmlspecialchars($task['name']) ?></h3>
                            <span class="bg-primary/10 text-primary text-xs md:text-sm font-semibold px-2 md:px-3 py-1 rounded-full whitespace-nowrap">
                                +<?= $task['points_reward'] ?> pts
                            </span>
                        </div>
                        
                        <p class="text-text/70 text-sm mb-4 line-clamp-3"><?= htmlspecialchars($task['description']) ?></p>
                        
                        <!-- Progress Section -->
                        <div class="mb-4">
                            <div class="flex justify-between text-xs md:text-sm text-text/60 mb-2">
                                <span>Progress</span>
                                <span><?= round(min(100, $task['progress'])) ?>%</span>
                            </div>
                            <div class="w-full bg-subtle h-2 rounded-full">
                                <div class="bg-primary h-2 rounded-full transition-all duration-500" 
                                     style="width:<?= min(100, $task['progress']) ?>%"></div>
                            </div>
                        </div>
                        
                        <!-- Task Requirements -->
                        <div class="mb-4 p-3 bg-subtle/30 rounded-lg">
                            <p class="text-xs text-text/60">
                                <i class="fas fa-bullseye mr-1 text-primary"></i>
                                <?php 
                                if($task['type'] == 'points') {
                                    echo "Need {$task['requirement_value']} total points";
                                } else if($task['type'] == 'events') {
                                    echo "Attend {$task['requirement_value']} events";
                                } else if($task['type'] == 'streak') {
                                    echo "Maintain {$task['requirement_value']}-day streak";
                                } else if($task['type'] == 'tasks') {
                                    echo "Complete {$task['requirement_value']} tasks";
                                }
                                ?>
                            </p>
                        </div>
                        
                        <!-- Action Button -->
                        <div class="flex justify-between items-center">
                            <?php if($task['completed']): ?>
                                <span class="text-green-600 text-xs md:text-sm font-semibold flex items-center">
                                    <i class="fas fa-check-circle mr-2"></i> Completed
                                </span>
                            <?php else: ?>
                                <?php if($task['progress'] >= 100): ?>
                                    <form method="POST" class="flex-1">
                                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                        <button type="submit" name="complete_task" 
                                                class="btn-primary w-full flex items-center justify-center text-sm py-2">
                                            <i class="fas fa-gift mr-2"></i> Claim Reward
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-text/60 text-xs md:text-sm flex items-center">
                                        <i class="fas fa-clock mr-2"></i> In Progress
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Badges Section -->
            <div x-show="activeTab === 'badges'" x-cloak x-transition>
                <div class="text-center mb-6">
                    <h2 class="text-xl md:text-2xl font-bold text-text mb-2">Your Badge Collection</h2>
                    <p class="text-text/60">Unlock badges by achieving milestones and completing challenges</p>
                    <div class="mt-2 text-accent font-semibold">
                        <?= count(array_filter($all_badges, function($b) { return $b['unlocked']; })) ?> / <?= count($all_badges) ?> Unlocked
                    </div>
                </div>

                <!-- Badges Grid -->
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3 md:gap-4">
                    <?php foreach($all_badges as $badge):
                        $locked = !$badge['unlocked'];
                    ?>
                    <div class="badge-card p-3 md:p-4 cursor-pointer text-center" 
                         onclick="showBadgeDetails(<?= htmlspecialchars(json_encode($badge)) ?>)">
                        <div class="relative mx-auto mb-3 w-16 h-16 md:w-20 md:h-20">
                            <img src="../uploads/badges/<?= htmlspecialchars($badge['image_path']) ?>" 
                                 alt="<?= htmlspecialchars($badge['name']) ?>" 
                                 class="w-full h-full rounded-full border-2 <?= $locked ? 'badge-locked border-subtle' : 'border-accent' ?>">
                            
                            <!-- Progress Ring for Locked Badges -->
                            <?php if($locked): ?>
                            <svg class="absolute top-0 left-0 w-full h-full progress-ring" width="80" height="80">
                                <circle class="progress-ring-circle" 
                                        stroke="rgba(93, 173, 226, 0.5)" 
                                        stroke-width="3" 
                                        fill="transparent" 
                                        r="34" 
                                        cx="40" 
                                        cy="40"
                                        stroke-dasharray="226.194"
                                        stroke-dashoffset="<?= 226.194 * (1 - $badge['progress'] / 100) ?>">
                                </circle>
                            </svg>
                            <?php endif; ?>
                        </div>
                        
                        <h4 class="text-xs md:text-sm font-semibold text-text mb-2 line-clamp-2"><?= htmlspecialchars($badge['name']) ?></h4>
                        
                        <?php if ($locked): ?>
                            <div class="bg-subtle text-text/60 text-xs px-2 py-1 rounded-full">
                                <?= $badge['progress'] ?>%
                            </div>
                        <?php else: ?>
                            <div class="bg-accent/10 text-accent text-xs px-2 py-1 rounded-full">
                                <i class="fas fa-check mr-1"></i> Unlocked
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Redemption CTA Section -->
        <div class="bg-gradient-to-r from-accent to-purple-600 text-white rounded-2xl p-5 md:p-6 mb-8 shadow-lg max-w-6xl mx-auto">
            <div class="flex flex-col md:flex-row items-center justify-between">
                <div class="mb-4 md:mb-0 text-center md:text-left">
                    <h2 class="text-lg md:text-xl font-bold mb-2 flex items-center justify-center md:justify-start">
                        <i class="fas fa-gift mr-3"></i>
                        Ready to Redeem Your Points?
                    </h2>
                    <p class="opacity-90">Exchange your <?= number_format($total_points) ?> points for amazing rewards!</p>
                </div>
                <a href="redeem.php" class="bg-white text-accent px-5 md:px-6 py-2 md:py-3 rounded-lg font-semibold hover:bg-white/90 transition-colors flex items-center text-sm md:text-base whitespace-nowrap">
                    <i class="fas fa-shopping-cart mr-2"></i> Browse Rewards
                </a>
            </div>
        </div>

        <!-- Badge Details Modal -->
        <div id="badgeModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 hidden">
            <div class="bg-white rounded-2xl p-5 md:p-6 w-full max-w-md max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg md:text-xl font-bold text-text" id="badgeName"></h3>
                    <button onclick="hideBadgeDetails()" class="text-text/60 hover:text-text transition-colors">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                
                <div class="flex flex-col items-center mb-6">
                    <img id="badgeImage" src="" alt="" class="w-24 h-24 md:w-32 md:h-32 mb-4 rounded-full border-4">
                    
                    <p class="text-text/70 text-center mb-4 text-sm md:text-base" id="badgeDescription"></p>
                    
                    <div class="w-full mb-4">
                        <div class="flex justify-between text-sm text-text/60 mb-2">
                            <span>Progress</span>
                            <span id="badgeProgress">0%</span>
                        </div>
                        <div class="w-full bg-subtle h-3 rounded-full">
                            <div class="bg-primary h-3 rounded-full transition-all duration-500" id="badgeProgressBar"></div>
                        </div>
                    </div>
                    
                    <div class="w-full p-4 bg-subtle/30 rounded-lg">
                        <h4 class="font-semibold text-text mb-2 flex items-center text-sm md:text-base">
                            <i class="fas fa-bullseye mr-2 text-primary"></i>
                            Unlock Criteria
                        </h4>
                        <p class="text-xs md:text-sm text-text/70" id="unlockCriteria"></p>
                    </div>
                    
                    <div class="mt-4 text-sm text-green-600 font-semibold hidden" id="unlockDate"></div>
                </div>
                
                <div class="flex justify-center">
                    <button onclick="hideBadgeDetails()" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition text-sm md:text-base">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function achievementsPage() {
            return {
                activeTab: 'tasks'
            }
        }

        // Badge details functions
        function showBadgeDetails(badge) {
            document.getElementById('badgeName').textContent = badge.name;
            document.getElementById('badgeImage').src = '../uploads/badges/' + badge.image_path;
            document.getElementById('badgeDescription').textContent = badge.description;
            document.getElementById('badgeProgress').textContent = badge.progress + '%';
            document.getElementById('badgeProgressBar').style.width = badge.progress + '%';
            
            // Set border color based on unlock status
            document.getElementById('badgeImage').className = badge.unlocked ? 
                'w-24 h-24 md:w-32 md:h-32 mb-4 rounded-full border-4 border-accent' : 
                'w-24 h-24 md:w-32 md:h-32 mb-4 rounded-full border-4 border-subtle badge-locked';
            
            // Show unlock criteria
            let criteriaText = '';
            if (badge.type == 'points') {
                criteriaText = `Earn ${badge.required_value} total points (Current: ${badge.current_value}/${badge.required_value})`;
            } else if (badge.type == 'events') {
                criteriaText = `Attend ${badge.required_value} events (Current: ${badge.current_value}/${badge.required_value})`;
            } else if (badge.type == 'streak') {
                criteriaText = `Maintain ${badge.required_value}-day streak (Current: ${badge.current_value}/${badge.required_value})`;
            } else if (badge.type == 'tasks') {
                criteriaText = `Complete ${badge.required_value} tasks (Current: ${badge.current_value}/${badge.required_value})`;
            }
            document.getElementById('unlockCriteria').textContent = criteriaText;
            
            // Show unlock date if unlocked
            if (badge.unlocked && badge.unlocked_at) {
                const unlockDate = new Date(badge.unlocked_at).toLocaleDateString();
                document.getElementById('unlockDate').textContent = 'Unlocked on: ' + unlockDate;
                document.getElementById('unlockDate').classList.remove('hidden');
            } else {
                document.getElementById('unlockDate').classList.add('hidden');
            }
            
            document.getElementById('badgeModal').classList.remove('hidden');
        }

        function hideBadgeDetails() {
            document.getElementById('badgeModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('badgeModal').addEventListener('click', function(event) {
            if (event.target === this) {
                hideBadgeDetails();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideBadgeDetails();
            }
        });

        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.bg-green-50');
            messages.forEach(msg => {
                msg.style.transition = 'opacity 0.5s ease';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>