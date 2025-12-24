<?php
session_start();
include '../auth/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

// Initialize variables
$name = $description = $unlock_criteria = $points_required = $type = "";
$events_required = $streak_required = $tasks_required = 0;
$error = $success = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_badge'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $unlock_criteria = trim($_POST['unlock_criteria']);
        $type = $_POST['type'];
        
        // Set requirement values based on type
        $points_required = ($type === 'points') ? intval($_POST['points_required']) : 0;
        $events_required = ($type === 'events') ? intval($_POST['events_required']) : 0;
        $streak_required = ($type === 'streak') ? intval($_POST['streak_required']) : 0;
        $tasks_required = ($type === 'tasks') ? intval($_POST['tasks_required']) : 0;

        // Handle image upload
        if (isset($_FILES['badge_image']) && $_FILES['badge_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $file_name = $_FILES['badge_image']['name'];
            $file_tmp = $_FILES['badge_image']['tmp_name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (in_array($file_ext, $allowed)) {
                $new_file_name = uniqid('badge_', true) . "." . $file_ext;
                $upload_dir = '../uploads/badges/';
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $upload_path = $upload_dir . $new_file_name;
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Insert into database with ALL fields
                    $stmt = $conn->prepare("INSERT INTO badges 
                        (name, image_path, description, unlock_criteria, points_required, 
                         type, events_required, streak_required, tasks_required, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    
                    $stmt->bind_param("ssssisiii", 
                        $name, 
                        $new_file_name, 
                        $description, 
                        $unlock_criteria, 
                        $points_required,
                        $type,
                        $events_required,
                        $streak_required,
                        $tasks_required
                    );

                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = "Badge created successfully!";
                        header("Location: create_badge.php");
                        exit();
                    } else {
                        $error = "Database error: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = "Failed to upload image. Please try again.";
                }
            } else {
                $error = "Invalid file type. Only JPG, PNG, GIF files are allowed.";
            }
        } else {
            $error = "Please select a badge image to upload.";
        }
    }
    
    // Handle badge update
    if (isset($_POST['update_badge'])) {
        $badge_id = intval($_POST['badge_id']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $unlock_criteria = trim($_POST['unlock_criteria']);
        $type = $_POST['type'];
        
        // Set requirement values based on type
        $points_required = ($type === 'points') ? intval($_POST['points_required']) : 0;
        $events_required = ($type === 'events') ? intval($_POST['events_required']) : 0;
        $streak_required = ($type === 'streak') ? intval($_POST['streak_required']) : 0;
        $tasks_required = ($type === 'tasks') ? intval($_POST['tasks_required']) : 0;

        // Handle image upload if new image is provided
        $image_update = "";
        if (isset($_FILES['badge_image']) && $_FILES['badge_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $file_name = $_FILES['badge_image']['name'];
            $file_tmp = $_FILES['badge_image']['tmp_name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (in_array($file_ext, $allowed)) {
                $new_file_name = uniqid('badge_', true) . "." . $file_ext;
                $upload_dir = '../uploads/badges/';
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $upload_path = $upload_dir . $new_file_name;
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $image_update = ", image_path = ?";
                }
            }
        }

        if ($image_update) {
            $stmt = $conn->prepare("UPDATE badges SET name=?, description=?, unlock_criteria=?, points_required=?, type=?, events_required=?, streak_required=?, tasks_required=?, image_path=? WHERE id=?");
            $stmt->bind_param("sssisiiisi", $name, $description, $unlock_criteria, $points_required, $type, $events_required, $streak_required, $tasks_required, $new_file_name, $badge_id);
        } else {
            $stmt = $conn->prepare("UPDATE badges SET name=?, description=?, unlock_criteria=?, points_required=?, type=?, events_required=?, streak_required=?, tasks_required=? WHERE id=?");
            $stmt->bind_param("sssisiiii", $name, $description, $unlock_criteria, $points_required, $type, $events_required, $streak_required, $tasks_required, $badge_id);
        }

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Badge updated successfully!";
            header("Location: create_badge.php");
            exit();
        } else {
            $error = "Error updating badge: " . $stmt->error;
        }
        $stmt->close();
    }
    
    // Handle badge deletion
    if (isset($_POST['delete_badge'])) {
        $badge_id = intval($_POST['badge_id']);
        
        $stmt = $conn->prepare("DELETE FROM badges WHERE id = ?");
        $stmt->bind_param("i", $badge_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Badge deleted successfully!";
            header("Location: create_badge.php");
            exit();
        } else {
            $error = "Error deleting badge: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get all badges with completion counts
$badges_result = $conn->query("
    SELECT b.*, 
           COUNT(vb.id) as completion_count
    FROM badges b 
    LEFT JOIN volunteer_badges vb ON b.id = vb.badge_id 
    GROUP BY b.id 
    ORDER BY b.created_at DESC
");
$badges = [];
while ($badge = $badges_result->fetch_assoc()) {
    $badges[] = $badge;
}

// Get badge statistics
$stats_result = $conn->query("
    SELECT 
        COUNT(*) as total_badges,
        SUM(points_required) as total_points_required,
        (SELECT COUNT(*) FROM volunteer_badges) as total_earnings,
        (SELECT COUNT(DISTINCT volunteer_id) FROM volunteer_badges) as unique_earners
    FROM badges
");
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Badge | VMS</title>
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
        
        .submit-btn {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(93, 173, 226, 0.3);
        }
        
        .cancel-btn {
            background: rgba(213, 216, 220, 0.3);
            color: #1C2833;
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            border: 1px solid #D5D8DC;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .cancel-btn:hover {
            background: rgba(213, 216, 220, 0.5);
            transform: translateY(-1px);
        }
        
        .file-upload-area {
            border: 2px dashed #D5D8DC;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            background: rgba(93, 173, 226, 0.02);
            cursor: pointer;
        }
        
        .file-upload-area:hover {
            border-color: #5DADE2;
            background: rgba(93, 173, 226, 0.05);
        }
        
        .file-upload-area.dragover {
            border-color: #5DADE2;
            background: rgba(93, 173, 226, 0.1);
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
        
        .hidden {
            display: none;
        }
        
        .badge-card {
            transition: all 0.3s ease;
            border: 1px solid #D5D8DC;
        }
        
        .badge-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
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
        
        .completions-modal {
            max-width: 600px;
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
            
            .form-section {
                margin-bottom: 1.5rem;
            }
            
            .form-input {
                padding: 14px 16px;
                font-size: 1rem;
            }
            
            .file-upload-area {
                padding: 1.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 1rem;
            }
            
            .submit-btn, .cancel-btn {
                padding: 16px 24px;
                font-size: 1.1rem;
                width: 100%;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
        
        @media (min-width: 641px) and (max-width: 768px) {
            .page-container {
                padding: 1.5rem;
            }
            
            .container-card {
                padding: 2rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.5rem;
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
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .action-buttons {
                flex-direction: row;
                gap: 1rem;
            }
            
            .submit-btn, .cancel-btn {
                width: auto;
                min-width: 150px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 2rem;
            }
            
            .badges-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
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
                <h1 class="text-2xl md:text-3xl font-bold text-text mb-2">Badge Management</h1>
                <p class="text-text/70">Create and manage recognition badges for volunteers</p>
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

        <?php if ($error): ?>
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
                <div class="text-2xl font-bold text-primary mb-2"><?= $stats['total_badges'] ?></div>
                <div class="text-text/70 text-sm">Total Badges</div>
            </div>
            <div class="stat-card p-6 text-center">
                <div class="text-2xl font-bold text-accent mb-2"><?= $stats['total_earnings'] ?></div>
                <div class="text-text/70 text-sm">Total Earnings</div>
            </div>
            <div class="stat-card p-6 text-center">
                <div class="text-2xl font-bold text-text mb-2"><?= $stats['unique_earners'] ?></div>
                <div class="text-text/70 text-sm">Unique Earners</div>
            </div>
            <div class="stat-card p-6 text-center">
                <div class="text-2xl font-bold text-green-600 mb-2"><?= $stats['total_points_required'] ?></div>
                <div class="text-text/70 text-sm">Total Points Required</div>
            </div>
        </div>

        <!-- Create Badge Form -->
        <div class="container-card mb-8">
            <h2 class="text-xl font-bold text-text mb-6">Create New Badge</h2>
            <form method="POST" enctype="multipart/form-data" class="space-y-6" name="add_badge_form">
                <div class="form-grid grid">
                    <!-- Badge Name -->
                    <div class="form-section">
                        <label class="form-label">
                            <i class="fas fa-tag text-primary mr-2"></i>
                            Badge Name
                        </label>
                        <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" required
                               class="form-input"
                               placeholder="Enter badge name">
                    </div>

                    <!-- Badge Type -->
                    <div class="form-section">
                        <label class="form-label">
                            <i class="fas fa-tag text-primary mr-2"></i>
                            Badge Type
                        </label>
                        <select name="type" required class="form-input" onchange="toggleRequirementFields(this.value)">
                            <option value="points">Points Based</option>
                            <option value="events">Events Based</option>
                            <option value="streak">Streak Based</option>
                            <option value="tasks">Task Completion Based</option>
                        </select>
                    </div>

                    <!-- Points Required Field -->
                    <div id="points_required_field" class="form-section">
                        <label class="form-label">
                            <i class="fas fa-star text-accent mr-2"></i>
                            Points Required
                        </label>
                        <input type="number" name="points_required" value="0" min="0" class="form-input">
                    </div>

                    <!-- Events Required Field -->
                    <div id="events_required_field" class="form-section hidden">
                        <label class="form-label">
                            <i class="fas fa-calendar text-accent mr-2"></i>
                            Events Required
                        </label>
                        <input type="number" name="events_required" value="0" min="0" class="form-input">
                    </div>

                    <!-- Streak Required Field -->
                    <div id="streak_required_field" class="form-section hidden">
                        <label class="form-label">
                            <i class="fas fa-fire text-accent mr-2"></i>
                            Streak Required (days)
                        </label>
                        <input type="number" name="streak_required" value="0" min="0" class="form-input">
                    </div>

                    <!-- Tasks Required Field -->
                    <div id="tasks_required_field" class="form-section hidden">
                        <label class="form-label">
                            <i class="fas fa-tasks text-accent mr-2"></i>
                            Tasks Required
                        </label>
                        <input type="number" name="tasks_required" value="0" min="0" class="form-input">
                    </div>

                    <!-- Description -->
                    <div class="form-section">
                        <label class="form-label">
                            <i class="fas fa-align-left text-primary mr-2"></i>
                            Description
                        </label>
                        <textarea name="description" required rows="3"
                                  class="form-input"
                                  placeholder="Describe what this badge represents"><?= htmlspecialchars($description) ?></textarea>
                    </div>

                    <!-- Unlock Criteria -->
                    <div class="form-section">
                        <label class="form-label">
                            <i class="fas fa-unlock text-accent mr-2"></i>
                            Unlock Criteria
                        </label>
                        <textarea name="unlock_criteria" required rows="3"
                                  class="form-input"
                                  placeholder="Explain how volunteers can earn this badge"><?= htmlspecialchars($unlock_criteria) ?></textarea>
                    </div>

                    <!-- Badge Image Upload -->
                    <div class="form-section">
                        <label class="form-label">
                            <i class="fas fa-image text-primary mr-2"></i>
                            Badge Image
                        </label>
                        <div class="file-upload-area" id="uploadArea">
                            <input type="file" name="badge_image" accept="image/*" required class="hidden" id="fileInput">
                            <div class="text-center">
                                <i class="fas fa-cloud-upload-alt text-3xl text-primary mb-3"></i>
                                <p class="text-text font-medium mb-1">Click to upload badge image</p>
                                <p class="text-text/60 text-sm">PNG, JPG, GIF files up to 5MB</p>
                                <p class="text-text/50 text-xs mt-2">or drag and drop files here</p>
                            </div>
                        </div>
                        <div id="file-preview" class="mt-4 hidden text-center">
                            <img id="previewImage" class="max-w-full h-32 object-contain mx-auto rounded-lg border border-subtle">
                            <p id="fileName" class="text-text/70 text-sm mt-2"></p>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="pt-6 border-t border-subtle action-buttons flex">
                    <button type="button" onclick="resetForm()" class="cancel-btn inline-flex items-center justify-center">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </button>
                    <button type="submit" name="add_badge" class="submit-btn inline-flex items-center justify-center">
                        <i class="fas fa-plus mr-2"></i>
                        Create Badge
                    </button>
                </div>
            </form>
        </div>

        <!-- Existing Badges Section -->
        <div class="container-card">
            <h2 class="text-xl font-bold text-text mb-6">Existing Badges</h2>
            
            <?php if (count($badges) > 0): ?>
                <div class="badges-grid grid gap-6">
                    <?php foreach($badges as $badge): 
                        // Get volunteers who earned this badge
                        $earners_query = $conn->query("
                            SELECT vb.*, v.full_name, v.email, v.profile_image 
                            FROM volunteer_badges vb 
                            JOIN volunteers v ON vb.volunteer_id = v.id 
                            WHERE vb.badge_id = ".$badge['id']."
                            ORDER BY vb.unlocked_at DESC
                        ");
                        $earners = [];
                        while ($earner = $earners_query->fetch_assoc()) {
                            $earners[] = $earner;
                        }
                    ?>
                        <div class="badge-card container-card p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-center space-x-4">
                                    <img src="../uploads/badges/<?= $badge['image_path'] ?>" 
                                         alt="<?= htmlspecialchars($badge['name']) ?>" 
                                         class="w-16 h-16 object-contain rounded-lg">
                                    <div>
                                        <h3 class="font-bold text-text"><?= htmlspecialchars($badge['name']) ?></h3>
                                        <p class="text-text/60 text-sm"><?= htmlspecialchars($badge['description']) ?></p>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <?php if($badge['completion_count'] > 0): ?>
                                        <button onclick="showEarners(<?= htmlspecialchars(json_encode([
                                            'badge_id' => $badge['id'],
                                            'badge_name' => $badge['name'],
                                            'earners' => $earners
                                        ])) ?>)" 
                                                class="completions-btn inline-flex items-center">
                                            <i class="fas fa-users mr-1"></i>
                                            <?= $badge['completion_count'] ?>
                                        </button>
                                    <?php endif; ?>
                                    <button onclick="editBadge(<?= htmlspecialchars(json_encode($badge)) ?>)" 
                                            class="edit-btn inline-flex items-center">
                                        <i class="fas fa-edit mr-1"></i>
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this badge?')">
                                        <input type="hidden" name="badge_id" value="<?= $badge['id'] ?>">
                                        <button type="submit" name="delete_badge" class="delete-btn inline-flex items-center">
                                            <i class="fas fa-trash mr-1"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-text/70">Type:</span>
                                    <span class="font-medium ml-2 capitalize"><?= $badge['type'] ?></span>
                                </div>
                                <div>
                                    <span class="text-text/70">Requirement:</span>
                                    <span class="font-medium ml-2">
                                        <?= $badge[$badge['type'] . '_required'] ?>
                                        <?= $badge['type'] == 'points' ? 'points' : '' ?>
                                        <?= $badge['type'] == 'events' ? 'events' : '' ?>
                                        <?= $badge['type'] == 'streak' ? 'days streak' : '' ?>
                                        <?= $badge['type'] == 'tasks' ? 'tasks' : '' ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mt-4 pt-4 border-t border-subtle">
                                <p class="text-text/70 text-sm"><strong>Unlock Criteria:</strong> <?= htmlspecialchars($badge['unlock_criteria']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <i class="fas fa-award text-4xl text-subtle mb-4"></i>
                    <h3 class="text-xl font-bold text-text mb-2">No Badges Created</h3>
                    <p class="text-text/70">Create your first badge to get started.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Badge Modal -->
    <div id="editBadgeModal" class="modal-overlay hidden">
        <div class="modal-container">
            <div class="modal-content">
                <h3 class="text-lg font-semibold text-text mb-4">Edit Badge</h3>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="badge_id" id="edit_badge_id">
                    <div>
                        <label class="form-label">Badge Name</label>
                        <input type="text" name="name" id="edit_name" required class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" required class="form-input" rows="3"></textarea>
                    </div>
                    <div>
                        <label class="form-label">Type</label>
                        <select name="type" id="edit_type" required class="form-input" onchange="toggleEditRequirementFields(this.value)">
                            <option value="points">Points Based</option>
                            <option value="events">Events Based</option>
                            <option value="streak">Streak Based</option>
                            <option value="tasks">Task Completion Based</option>
                        </select>
                    </div>
                    
                    <div id="edit_points_required_field" class="form-section">
                        <label class="form-label">Points Required</label>
                        <input type="number" name="points_required" id="edit_points_required" min="0" class="form-input">
                    </div>
                    
                    <div id="edit_events_required_field" class="form-section hidden">
                        <label class="form-label">Events Required</label>
                        <input type="number" name="events_required" id="edit_events_required" min="0" class="form-input">
                    </div>
                    
                    <div id="edit_streak_required_field" class="form-section hidden">
                        <label class="form-label">Streak Required (days)</label>
                        <input type="number" name="streak_required" id="edit_streak_required" min="0" class="form-input">
                    </div>
                    
                    <div id="edit_tasks_required_field" class="form-section hidden">
                        <label class="form-label">Tasks Required</label>
                        <input type="number" name="tasks_required" id="edit_tasks_required" min="0" class="form-input">
                    </div>
                    
                    <div>
                        <label class="form-label">Unlock Criteria</label>
                        <textarea name="unlock_criteria" id="edit_unlock_criteria" required class="form-input" rows="3"></textarea>
                    </div>
                    <div>
                        <label class="form-label">Badge Image (Leave empty to keep current)</label>
                        <input type="file" name="badge_image" accept="image/*" class="form-input">
                    </div>
                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="hideModal('editBadgeModal')" class="cancel-btn">
                            Cancel
                        </button>
                        <button type="submit" name="update_badge" class="submit-btn">
                            Update Badge
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Badge Earners Modal -->
    <div id="earnersModal" class="modal-overlay hidden">
        <div class="modal-container completions-modal">
            <div class="modal-content">
                <h3 class="text-lg font-semibold text-text mb-4" id="earners-title">Badge Earners</h3>
                <div id="earners-list" class="max-h-96 overflow-y-auto">
                    <!-- Earners will be loaded here -->
                </div>
                <div class="flex justify-end pt-4">
                    <button type="button" onclick="hideModal('earnersModal')" class="cancel-btn">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to toggle requirement fields based on badge type
        function toggleRequirementFields(type) {
            // Hide all requirement fields
            document.getElementById('points_required_field').classList.add('hidden');
            document.getElementById('events_required_field').classList.add('hidden');
            document.getElementById('streak_required_field').classList.add('hidden');
            document.getElementById('tasks_required_field').classList.add('hidden');
            
            // Show the relevant field
            if (type === 'points') {
                document.getElementById('points_required_field').classList.remove('hidden');
            } else if (type === 'events') {
                document.getElementById('events_required_field').classList.remove('hidden');
            } else if (type === 'streak') {
                document.getElementById('streak_required_field').classList.remove('hidden');
            } else if (type === 'tasks') {
                document.getElementById('tasks_required_field').classList.remove('hidden');
            }
        }

        // Function to toggle edit requirement fields
        function toggleEditRequirementFields(type) {
            // Hide all requirement fields
            document.getElementById('edit_points_required_field').classList.add('hidden');
            document.getElementById('edit_events_required_field').classList.add('hidden');
            document.getElementById('edit_streak_required_field').classList.add('hidden');
            document.getElementById('edit_tasks_required_field').classList.add('hidden');
            
            // Show the relevant field
            if (type === 'points') {
                document.getElementById('edit_points_required_field').classList.remove('hidden');
            } else if (type === 'events') {
                document.getElementById('edit_events_required_field').classList.remove('hidden');
            } else if (type === 'streak') {
                document.getElementById('edit_streak_required_field').classList.remove('hidden');
            } else if (type === 'tasks') {
                document.getElementById('edit_tasks_required_field').classList.remove('hidden');
            }
        }

        // Initialize with points field visible
        document.addEventListener('DOMContentLoaded', function() {
            toggleRequirementFields('points');
        });

        function editBadge(badge) {
            document.getElementById('edit_badge_id').value = badge.id;
            document.getElementById('edit_name').value = badge.name;
            document.getElementById('edit_description').value = badge.description;
            document.getElementById('edit_type').value = badge.type;
            document.getElementById('edit_unlock_criteria').value = badge.unlock_criteria;
            
            // Set requirement values
            document.getElementById('edit_points_required').value = badge.points_required;
            document.getElementById('edit_events_required').value = badge.events_required;
            document.getElementById('edit_streak_required').value = badge.streak_required;
            document.getElementById('edit_tasks_required').value = badge.tasks_required;
            
            // Show the correct requirement field
            toggleEditRequirementFields(badge.type);
            
            showModal('editBadgeModal');
        }

        function showEarners(badgeData) {
            const title = document.getElementById('earners-title');
            const list = document.getElementById('earners-list');
            
            title.textContent = `Earners: ${badgeData.badge_name}`;
            
            if (badgeData.earners.length === 0) {
                list.innerHTML = `
                    <div class="text-center py-8 text-text/60">
                        <i class="fas fa-users text-3xl mb-3"></i>
                        <p>No volunteers have earned this badge yet.</p>
                    </div>
                `;
            } else {
                let html = '';
                badgeData.earners.forEach(earner => {
                    const earnedDate = new Date(earner.earned_at).toLocaleDateString();
                    html += `
                        <div class="volunteer-item">
                            <div class="flex items-center flex-1">
                                <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center mr-3">
                                    ${earner.profile_image ? 
                                        `<img src="../${earner.profile_image}" class="w-10 h-10 rounded-full object-cover">` : 
                                        `<i class="fas fa-user text-primary"></i>`
                                    }
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold text-text">${earner.full_name}</div>
                                    <div class="text-text/60 text-sm">${earner.email}</div>
                                </div>
                            </div>
                            <div class="text-text/60 text-sm">
                                ${earnedDate}
                            </div>
                        </div>
                    `;
                });
                list.innerHTML = html;
            }
            
            showModal('earnersModal');
        }

        function showModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
        }

        function hideModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        function resetForm() {
            document.forms['add_badge_form'].reset();
            toggleRequirementFields('points');
            document.getElementById('file-preview').classList.add('hidden');
        }

        // File upload functionality
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const filePreview = document.getElementById('file-preview');
        const previewImage = document.getElementById('previewImage');
        const fileName = document.getElementById('fileName');

        // Click to upload
        uploadArea.addEventListener('click', () => fileInput.click());

        // File input change
        fileInput.addEventListener('change', updateFilePreview);

        function updateFilePreview() {
            if (fileInput.files && fileInput.files[0]) {
                const file = fileInput.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    fileName.textContent = file.name;
                    filePreview.classList.remove('hidden');
                };
                
                reader.readAsDataURL(file);
            } else {
                filePreview.classList.add('hidden');
            }
        }

        // Drag and drop functionality
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => uploadArea.classList.add('dragover'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => uploadArea.classList.remove('dragover'), false);
        });

        uploadArea.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const files = e.dataTransfer.files;
            fileInput.files = files;
            updateFilePreview();
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