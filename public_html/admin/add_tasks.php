<?php
session_start();
include '../auth/db.php';

// Check if user is admin
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_task'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $description = $conn->real_escape_string($_POST['description']);
        $type = $conn->real_escape_string($_POST['type']);
        $requirement_value = intval($_POST['requirement_value']);
        $points_reward = intval($_POST['points_reward']);
        $active = isset($_POST['active']) ? 1 : 0;
        
        $stmt = $conn->prepare("INSERT INTO tasks (name, description, type, requirement_value, points_reward, active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssiii", $name, $description, $type, $requirement_value, $points_reward, $active);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Task added successfully!";
            header("Location: add_tasks.php");
            exit();
        } else {
            $error = "Error adding task: " . $conn->error;
        }
    }
    
    if (isset($_POST['update_task'])) {
        $task_id = intval($_POST['task_id']);
        $name = $conn->real_escape_string($_POST['name']);
        $description = $conn->real_escape_string($_POST['description']);
        $type = $conn->real_escape_string($_POST['type']);
        $requirement_value = intval($_POST['requirement_value']);
        $points_reward = intval($_POST['points_reward']);
        $active = isset($_POST['active']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE tasks SET name=?, description=?, type=?, requirement_value=?, points_reward=?, active=? WHERE id=?");
        $stmt->bind_param("sssiiii", $name, $description, $type, $requirement_value, $points_reward, $active, $task_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Task updated successfully!";
            header("Location: tasks.php");
            exit();
        } else {
            $error = "Error updating task: " . $conn->error;
        }
    }
    
    if (isset($_POST['delete_task'])) {
    $task_id = intval($_POST['task_id']);

    // Delete dependencies first
    $stmt = $conn->prepare("DELETE FROM volunteer_tasks WHERE task_id = ?");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();

    // Now delete task
    $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
    $stmt->bind_param("i", $task_id);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Task deleted successfully!";
        header("Location: add_tasks.php");
        exit();
    } else {
        $error = "Error deleting task: " . $conn->error;
    }
}

}

// Get all tasks
$tasks_result = $conn->query("SELECT * FROM tasks ORDER BY created_at DESC");
$tasks = [];
while ($task = $tasks_result->fetch_assoc()) {
    $tasks[] = $task;
}

// Get task completion statistics
$stats_result = $conn->query("
    SELECT 
        COUNT(*) as total_tasks,
        COUNT(CASE WHEN active=1 THEN 1 END) as active_tasks,
        AVG(points_reward) as avg_points,
        (SELECT COUNT(DISTINCT volunteer_id) FROM volunteer_tasks) as total_completions
    FROM tasks
");
$stats = $stats_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Management | VMS</title>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
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
            transform: translateY(-1px);
        }
        
        .form-label {
            color: #1C2833;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 6px;
            display: block;
        }
        
        .action-btn {
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid;
        }
        
        .add-task-btn {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            border: none;
        }
        
        .add-task-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(93, 173, 226, 0.3);
        }
        
        .modal-btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .cancel-btn {
            background: rgba(213, 216, 220, 0.3);
            color: #1C2833;
            border: 1px solid #D5D8DC;
        }
        
        .cancel-btn:hover {
            background: rgba(213, 216, 220, 0.5);
        }
        
        .submit-btn {
            background: #5DADE2;
            color: white;
            border: 1px solid #5DADE2;
        }
        
        .submit-btn:hover {
            background: #4A9CD6;
        }
        
        .edit-btn {
            background: rgba(93, 173, 226, 0.1);
            color: #5DADE2;
            border: 1px solid rgba(93, 173, 226, 0.3);
            padding: 6px 12px;
            border-radius: 6px;
        }
        
        .edit-btn:hover {
            background: rgba(93, 173, 226, 0.2);
        }
        
        .delete-btn {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 6px 12px;
            border-radius: 6px;
        }
        
        .delete-btn:hover {
            background: rgba(239, 68, 68, 0.2);
        }
        
        .completions-btn {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .completions-btn:hover {
            background: rgba(16, 185, 129, 0.2);
            transform: translateY(-1px);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .status-inactive {
            background: rgba(107, 114, 128, 0.1);
            color: #374151;
            border: 1px solid rgba(107, 114, 128, 0.3);
        }
        
        .type-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .type-points {
            background: rgba(93, 173, 226, 0.1);
            color: #5DADE2;
        }
        
        .type-events {
            background: rgba(165, 105, 189, 0.1);
            color: #A569BD;
        }
        
        .type-streak {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }
        
        .success-message {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #065f46;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 1.5rem;
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #991b1b;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #D5D8DC;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(93, 173, 226, 0.15);
        }
        
        .volunteer-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid #D5D8DC;
            transition: all 0.3s ease;
        }
        
        .volunteer-item:hover {
            background: rgba(93, 173, 226, 0.05);
        }
        
        .volunteer-item:last-child {
            border-bottom: none;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
                margin-bottom: 2rem;
            }
            
            .task-card {
                padding: 1.25rem;
                margin-bottom: 1rem;
                border: 1px solid #D5D8DC;
                border-radius: 12px;
                background: white;
            }
            
            .task-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
                margin-bottom: 1rem;
            }
            
            .task-details {
                display: grid;
                gap: 0.75rem;
            }
            
            .detail-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .action-buttons {
                display: flex;
                gap: 0.5rem;
                margin-top: 1rem;
            }
            
            .modal-content {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
        
        @media (min-width: 641px) and (max-width: 768px) {
            .page-container {
                padding: 1.5rem;
            }
            
            .container-card {
                padding: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 1.5rem;
            }
            
            .task-card {
                padding: 1.5rem;
            }
            
            .task-details {
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
            
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 2rem;
            }
            
            .tasks-table {
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
            
            .modal-content {
                max-width: 500px;
                margin: 2rem auto;
                padding: 2rem;
            }
            
            .completions-modal {
                max-width: 600px;
            }
        }
        
        /* Ensure proper sidebar spacing */
        @media (min-width: 768px) {
            body {
                margin-left: 16rem;
            }
        }
        
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 90vw;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .completion-badge {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .completion-badge:hover {
            background: rgba(16, 185, 129, 0.2);
            transform: translateY(-1px);
        }
    </style>
</head>
<body class="min-h-screen lg:ml-64">
    <?php include 'sidebar.php'; ?>

    <div class="page-container">
        <!-- Header Section -->
        <div class="header-section mb-8 mt-12 sm:mt-0 flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div class="mb-4 sm:mb-0">
                <h1 class="text-2xl md:text-3xl font-bold text-text mb-2">Task Management</h1>
                <p class="text-text/70">Create and manage volunteer tasks and rewards</p>
            </div>
            <button onclick="showModal('addTaskModal')" 
                    class="add-task-btn action-btn inline-flex items-center">
                <i class="fas fa-plus mr-2"></i>
                Add New Task
            </button>
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

        <?php if (isset($error)): ?>
            <div class="error-message flex items-center">
                <i class="fas fa-exclamation-circle mr-3"></i>
                <div><?= $error ?></div>
                <button onclick="this.parentElement.remove()" class="ml-auto">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid grid mb-8">
            <div class="stat-card p-6 text-center">
                <div class="text-2xl font-bold text-primary mb-2"><?= $stats['total_tasks'] ?></div>
                <div class="text-text/70 text-sm">Total Tasks</div>
            </div>
            <div class="stat-card p-6 text-center">
                <div class="text-2xl font-bold text-accent mb-2"><?= $stats['active_tasks'] ?></div>
                <div class="text-text/70 text-sm">Active Tasks</div>
            </div>
            <div class="stat-card p-6 text-center">
                <div class="text-2xl font-bold text-text mb-2"><?= round($stats['avg_points']) ?></div>
                <div class="text-text/70 text-sm">Average Points</div>
            </div>
            <div class="stat-card p-6 text-center">
                <div class="text-2xl font-bold text-green-600 mb-2"><?= $stats['total_completions'] ?></div>
                <div class="text-text/70 text-sm">Total Completions</div>
            </div>
        </div>

        <!-- Tasks List -->
        <?php if (count($tasks) > 0): ?>
            <!-- Desktop Table View -->
            <div class="tasks-table hidden md:block">
                <div class="container-card">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-subtle">
                                <th class="text-left py-4 px-6 text-text/70 font-semibold">Task</th>
                                <th class="text-left py-4 px-6 text-text/70 font-semibold">Type</th>
                                <th class="text-left py-4 px-6 text-text/70 font-semibold">Requirement</th>
                                <th class="text-left py-4 px-6 text-text/70 font-semibold">Points</th>
                                <th class="text-left py-4 px-6 text-text/70 font-semibold">Completions</th>
                                <th class="text-left py-4 px-6 text-text/70 font-semibold">Status</th>
                                <th class="text-right py-4 px-6 text-text/70 font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($tasks as $task): 
                                // Get completion count and volunteer details for this task
                                $completion_query = $conn->query("
                                    SELECT vt.*, v.full_name, v.email, v.profile_image 
                                    FROM volunteer_tasks vt 
                                    JOIN volunteers v ON vt.volunteer_id = v.id 
                                    WHERE vt.task_id = ".$task['id']."
                                    ORDER BY vt.completed_at DESC
                                ");
                                $completions = [];
                                while ($completion = $completion_query->fetch_assoc()) {
                                    $completions[] = $completion;
                                }
                                $completion_count = count($completions);
                            ?>
                                <tr class="table-row">
                                    <td class="table-cell">
                                        <div>
                                            <div class="font-semibold text-text"><?= htmlspecialchars($task['name']) ?></div>
                                            <div class="text-text/60 text-sm mt-1"><?= htmlspecialchars($task['description']) ?></div>
                                        </div>
                                    </td>
                                    <td class="table-cell">
                                        <span class="type-badge type-<?= $task['type'] ?>">
                                            <?= ucfirst($task['type']) ?>
                                        </span>
                                    </td>
                                    <td class="table-cell text-text/70">
                                        <?= $task['requirement_value'] ?>
                                        <?= $task['type'] == 'points' ? 'points' : '' ?>
                                        <?= $task['type'] == 'events' ? 'events' : '' ?>
                                        <?= $task['type'] == 'streak' ? 'days' : '' ?>
                                        <?= $task['type'] == 'tasks' ? 'tasks' : '' ?>
                                    </td>
                                    <td class="table-cell">
                                        <span class="font-bold text-primary">+<?= $task['points_reward'] ?></span>
                                    </td>
                                    <td class="table-cell">
                                        <?php if($completion_count > 0): ?>
                                            <button onclick="showCompletions(<?= htmlspecialchars(json_encode([
                                                'task_id' => $task['id'],
                                                'task_name' => $task['name'],
                                                'completions' => $completions
                                            ])) ?>)" 
                                                    class="completions-btn inline-flex items-center">
                                                <i class="fas fa-users mr-1"></i>
                                                <?= $completion_count ?> completions
                                            </button>
                                        <?php else: ?>
                                            <span class="text-text/40 text-sm">No completions</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="table-cell">
                                        <span class="status-badge <?= $task['active'] ? 'status-active' : 'status-inactive' ?>">
                                            <?= $task['active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td class="table-cell text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button onclick="editTask(<?= htmlspecialchars(json_encode($task)) ?>)" 
                                                    class="edit-btn inline-flex items-center">
                                                <i class="fas fa-edit mr-1"></i>
                                                Edit
                                            </button>
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this task?')">
                                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                <button type="submit" name="delete_task" class="delete-btn inline-flex items-center">
                                                    <i class="fas fa-trash mr-1"></i>
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Card View -->
            <div class="tasks-grid md:hidden space-y-4">
                <?php foreach($tasks as $task): 
                    $completion_query = $conn->query("
                        SELECT vt.*, v.full_name, v.email, v.profile_image 
                        FROM volunteer_tasks vt 
                        JOIN volunteers v ON vt.volunteer_id = v.id 
                        WHERE vt.task_id = ".$task['id']."
                        ORDER BY vt.completed_at DESC
                    ");
                    $completions = [];
                    while ($completion = $completion_query->fetch_assoc()) {
                        $completions[] = $completion;
                    }
                    $completion_count = count($completions);
                ?>
                    <div class="task-card container-card">
                        <div class="task-header flex">
                            <div class="flex-1">
                                <h3 class="font-semibold text-text mb-1"><?= htmlspecialchars($task['name']) ?></h3>
                                <p class="text-text/60 text-sm mb-2"><?= htmlspecialchars($task['description']) ?></p>
                                <?php if($completion_count > 0): ?>
                                    <button onclick="showCompletions(<?= htmlspecialchars(json_encode([
                                        'task_id' => $task['id'],
                                        'task_name' => $task['name'],
                                        'completions' => $completions
                                    ])) ?>)" 
                                            class="completion-badge inline-flex items-center">
                                        <i class="fas fa-users mr-1"></i>
                                        <?= $completion_count ?> completions
                                    </button>
                                <?php endif; ?>
                            </div>
                            <span class="status-badge <?= $task['active'] ? 'status-active' : 'status-inactive' ?>">
                                <?= $task['active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>

                        <div class="task-details">
                            <div class="detail-item">
                                <span class="text-text/70">Type:</span>
                                <span class="type-badge type-<?= $task['type'] ?>">
                                    <?= ucfirst($task['type']) ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="text-text/70">Requirement:</span>
                                <span class="text-text font-medium">
                                    <?= $task['requirement_value'] ?>
                                    <?= $task['type'] == 'points' ? 'points' : '' ?>
                                    <?= $task['type'] == 'events' ? 'events' : '' ?>
                                    <?= $task['type'] == 'streak' ? 'days' : '' ?>
                                    <?= $task['type'] == 'tasks' ? 'tasks' : '' ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="text-text/70">Points Reward:</span>
                                <span class="font-bold text-primary">+<?= $task['points_reward'] ?></span>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button onclick="editTask(<?= htmlspecialchars(json_encode($task)) ?>)" 
                                    class="edit-btn action-btn flex-1 inline-flex items-center justify-center">
                                <i class="fas fa-edit mr-1"></i>
                                Edit
                            </button>
                            <form method="POST" class="flex-1" onsubmit="return confirm('Are you sure you want to delete this task?')">
                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                <button type="submit" name="delete_task" class="delete-btn action-btn w-full inline-flex items-center justify-center">
                                    <i class="fas fa-trash mr-1"></i>
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="container-card text-center py-12">
                <i class="fas fa-tasks text-4xl text-subtle mb-4"></i>
                <h3 class="text-xl font-bold text-text mb-2">No Tasks Created</h3>
                <p class="text-text/70 mb-6">Get started by creating your first task for volunteers.</p>
                <button onclick="showModal('addTaskModal')" 
                        class="add-task-btn action-btn inline-flex items-center">
                    <i class="fas fa-plus mr-2"></i>
                    Create First Task
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Task Modal -->
    <div id="addTaskModal" class="modal-overlay hidden">
        <div class="modal-container">
            <div class="modal-content">
                <h3 class="text-lg font-semibold text-text mb-4">Add New Task</h3>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="form-label">Task Name</label>
                        <input type="text" name="name" required class="form-input" placeholder="Enter task name">
                    </div>
                    <div>
                        <label class="form-label">Description</label>
                        <textarea name="description" required class="form-input" rows="3" placeholder="Enter task description"></textarea>
                    </div>
                    <div>
                        <label class="form-label">Type</label>
                        <select name="type" required class="form-input">
                            <option value="points">Points Based</option>
                            <option value="events">Events Based</option>
                            <option value="streak">Streak Based</option>
                            <option value="tasks">Task Completion Based</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Requirement Value</label>
                        <input type="number" name="requirement_value" required min="1" class="form-input" placeholder="e.g., 100">
                    </div>
                    <div>
                        <label class="form-label">Points Reward</label>
                        <input type="number" name="points_reward" required min="1" class="form-input" placeholder="e.g., 50">
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" name="active" checked class="rounded border-subtle text-primary focus:ring-primary">
                        <label class="ml-2 text-text">Active Task</label>
                    </div>
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="hideModal('addTaskModal')" class="cancel-btn modal-btn">
                            Cancel
                        </button>
                        <button type="submit" name="add_task" class="submit-btn modal-btn">
                            Add Task
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Task Modal -->
    <div id="editTaskModal" class="modal-overlay hidden">
        <div class="modal-container">
            <div class="modal-content">
                <h3 class="text-lg font-semibold text-text mb-4">Edit Task</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="task_id" id="edit_task_id">
                    <div>
                        <label class="form-label">Task Name</label>
                        <input type="text" name="name" id="edit_name" required class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" required class="form-input" rows="3"></textarea>
                    </div>
                    <div>
                        <label class="form-label">Type</label>
                        <select name="type" id="edit_type" required class="form-input">
                            <option value="points">Points Based</option>
                            <option value="events">Events Based</option>
                            <option value="streak">Streak Based</option>
                            <option value="tasks">Task Completion Based</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Requirement Value</label>
                        <input type="number" name="requirement_value" id="edit_requirement_value" required min="1" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Points Reward</label>
                        <input type="number" name="points_reward" id="edit_points_reward" required min="1" class="form-input">
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" name="active" id="edit_active" class="rounded border-subtle text-primary focus:ring-primary">
                        <label class="ml-2 text-text">Active Task</label>
                    </div>
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="hideModal('editTaskModal')" class="cancel-btn modal-btn">
                            Cancel
                        </button>
                        <button type="submit" name="update_task" class="submit-btn modal-btn">
                            Update Task
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Task Completions Modal -->
    <div id="completionsModal" class="modal-overlay hidden">
        <div class="modal-container completions-modal">
            <div class="modal-content">
                <h3 class="text-lg font-semibold text-text mb-4" id="completions-title">Task Completions</h3>
                <div id="completions-list" class="max-h-96 overflow-y-auto">
                    <!-- Completions will be loaded here -->
                </div>
                <div class="flex justify-end pt-4">
                    <button type="button" onclick="hideModal('completionsModal')" class="cancel-btn modal-btn">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    function showModal(modalId) {
        document.getElementById(modalId).classList.remove('hidden');
    }

    function hideModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }

    function editTask(task) {
        document.getElementById('edit_task_id').value = task.id;
        document.getElementById('edit_name').value = task.name;
        document.getElementById('edit_description').value = task.description;
        document.getElementById('edit_type').value = task.type;
        document.getElementById('edit_requirement_value').value = task.requirement_value;
        document.getElementById('edit_points_reward').value = task.points_reward;
        document.getElementById('edit_active').checked = task.active == 1;
        
        showModal('editTaskModal');
    }

    function showCompletions(taskData) {
        const title = document.getElementById('completions-title');
        const list = document.getElementById('completions-list');
        
        title.textContent = `Completions: ${taskData.task_name}`;
        
        if (taskData.completions.length === 0) {
            list.innerHTML = `
                <div class="text-center py-8 text-text/60">
                    <i class="fas fa-users text-3xl mb-3"></i>
                    <p>No volunteers have completed this task yet.</p>
                </div>
            `;
        } else {
            let html = '';
            taskData.completions.forEach(completion => {
                const completedDate = new Date(completion.completed_at).toLocaleDateString();
                html += `
                    <div class="volunteer-item">
                        <div class="flex items-center flex-1">
                            <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center mr-3">
                                ${completion.profile_image ? 
                                    `<img src="../${completion.profile_image}" class="w-10 h-10 rounded-full object-cover">` : 
                                    `<i class="fas fa-user text-primary"></i>`
                                }
                            </div>
                            <div class="flex-1">
                                <div class="font-semibold text-text">${completion.full_name}</div>
                                <div class="text-text/60 text-sm">${completion.email}</div>
                            </div>
                        </div>
                        <div class="text-text/60 text-sm">
                            ${completedDate}
                        </div>
                    </div>
                `;
            });
            list.innerHTML = html;
        }
        
        showModal('completionsModal');
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.classList.add('hidden');
        }
    });
    </script>
</body>
</html>