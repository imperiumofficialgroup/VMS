<?php
session_start();
require_once '../auth/db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../admin/login.php");
    exit();
}

// Fetch all volunteers for selection
$volunteers = $conn->query("SELECT id, full_name FROM volunteers ORDER BY full_name");
$events = $conn->query("SELECT event_id, title FROM events ORDER BY title");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_name = trim($_POST['group_name']);
    $volunteer_ids = $_POST['volunteer_ids'] ?? [];
    $event_id = !empty($_POST['event_id']) ? (int)$_POST['event_id'] : null;

    if ($group_name !== "") {
        // Insert group with optional event_id
        $stmt = $conn->prepare("INSERT INTO group_chats (group_name, event_id) VALUES (?, ?)");
        $stmt->bind_param("si", $group_name, $event_id);
        $stmt->execute();

        $group_id = $conn->insert_id;

        // Insert selected volunteers into group_members
        if (!empty($volunteer_ids)) {
            $insertMember = $conn->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
            foreach ($volunteer_ids as $vol_id) {
                $insertMember->bind_param("ii", $group_id, $vol_id);
                $insertMember->execute();
            }
        }

        // Redirect to group chat page
        header("Location: group_chat.php?group_id=$group_id");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Group | VMS</title>
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
        
        .form-container {
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
        
        .form-input:hover {
            border-color: #A569BD;
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
            width: 100%;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(93, 173, 226, 0.3);
        }
        
        .volunteer-list {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e0 #f7fafc;
        }
        .volunteer-list::-webkit-scrollbar {
            width: 6px;
        }
        .volunteer-list::-webkit-scrollbar-track {
            background: #f7fafc;
        }
        .volunteer-list::-webkit-scrollbar-thumb {
            background-color: #cbd5e0;
            border-radius: 3px;
        }
        
        .checkbox-container {
            transition: all 0.2s ease;
            border-radius: 8px;
            border: 1px solid transparent;
        }
        
        .checkbox-container:hover {
            background-color: rgba(93, 173, 226, 0.05);
            border-color: #5DADE2;
            transform: translateY(-1px);
        }
        
        .checkbox-container.selected {
            background-color: rgba(93, 173, 226, 0.1);
            border-color: #5DADE2;
        }
        
        .search-input {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%231C2833'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px;
        }
        
        .select-all-btn {
            background: rgba(93, 173, 226, 0.1);
            color: #5DADE2;
            border: 1px solid #5DADE2;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .select-all-btn:hover {
            background: #5DADE2;
            color: white;
            transform: translateY(-1px);
        }
        
        .counter-badge {
            background: #5DADE2;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        /* Mobile First Responsive Design */
        @media (max-width: 640px) {
            .page-container {
                padding: 1rem;
            }
            
            .form-container {
                padding: 1.5rem;
            }
            
            .header-section {
                text-align: center;
                margin-bottom: 2rem;
            }
            
            .form-input {
                padding: 14px 16px;
                font-size: 1rem;
            }
            
            .submit-btn {
                padding: 16px 24px;
                font-size: 1.1rem;
            }
            
            .volunteer-list {
                max-height: 50vh;
            }
        }
        
        @media (min-width: 641px) and (max-width: 768px) {
            .page-container {
                padding: 1.5rem;
            }
            
            .form-container {
                padding: 2rem;
            }
        }
        
        @media (min-width: 769px) {
            .page-container {
                padding: 2rem;
                width: calc(100% - 16rem);
                margin-left: 16rem;
                max-width: 800px;
            }
            
            .form-container {
                padding: 2.5rem;
            }
            
            .submit-btn {
                width: auto;
                min-width: 200px;
            }
            
            .button-container {
                text-align: left;
            }
            
            .volunteer-list {
                max-height: 40vh;
            }
        }
        
        @media (min-width: 1024px) {
            .page-container {
                max-width: 900px;
            }
            
            .form-container {
                padding: 3rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="page-container">
        <!-- Header Section -->
        <div class="header-section mt-12 sm:mt-0 mb-8 text-center sm:text-left">
            <h1 class="text-2xl sm:text-3xl font-bold text-text mb-2">Create New Group</h1>
            <p class="text-text/70">Organize volunteers into communication groups</p>
        </div>

        <!-- Group Creation Form -->
        <div class="form-container">
            <form method="POST" class="space-y-6">
                <!-- Group Name -->
                <div class="form-section">
                    <label class="form-label">
                        <i class="fas fa-users mr-2 text-primary"></i>
                        Group Name *
                    </label>
                    <input type="text" name="group_name" required 
                           class="form-input"
                           placeholder="Enter a descriptive group name">
                </div>

                <!-- Event Selection -->
                <div class="form-section">
                    <label class="form-label">
                        <i class="fas fa-calendar mr-2 text-primary"></i>
                        Associated Event (Optional)
                    </label>
                    <select name="event_id" class="form-input">
                        <option value="">-- No event association --</option>
                        <?php while ($event = $events->fetch_assoc()): ?>
                            <option value="<?= $event['event_id'] ?>"><?= htmlspecialchars($event['title']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Volunteer Selection -->
                <div class="form-section">
                    <div class="flex justify-between items-center mb-4">
                        <label class="form-label mb-0">
                            <i class="fas fa-user-friends mr-2 text-accent"></i>
                            Select Volunteers
                        </label>
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-text/60">Selected:</span>
                            <div id="selectedCount" class="counter-badge">0</div>
                        </div>
                    </div>
                    
                    <!-- Search and Controls -->
                    <div class="flex flex-col sm:flex-row gap-3 mb-4">
                        <input type="text" id="volunteerSearch" 
                               class="form-input search-input flex-1"
                               placeholder="Search volunteers by name...">
                        <button type="button" id="selectAllBtn" class="select-all-btn whitespace-nowrap">
                            <i class="fas fa-check-double mr-2"></i>
                            <span>Select All</span>
                        </button>
                    </div>
                    
                    <!-- Volunteer List -->
                    <div class="volunteer-list border border-subtle rounded-xl p-4 overflow-y-auto bg-background/50">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <?php while ($vol = $volunteers->fetch_assoc()): ?>
                                <label class="checkbox-container flex items-center p-3 rounded-lg cursor-pointer transition-all duration-200">
                                    <input type="checkbox" name="volunteer_ids[]" value="<?= $vol['id'] ?>" 
                                           class="h-5 w-5 text-primary focus:ring-primary border-subtle rounded">
                                    <span class="ml-3 text-sm font-medium text-text"><?= htmlspecialchars($vol['full_name']) ?></span>
                                </label>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    
                    <!-- Selection Helper Text -->
                    <p class="text-xs text-text/50 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Selected volunteers will be able to communicate in this group chat
                    </p>
                </div>

                <!-- Submit Button -->
                <div class="form-section pt-4 button-container">
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-plus-circle mr-2"></i>
                        Create Group & Start Chatting
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const volunteerSearch = document.getElementById('volunteerSearch');
        const selectAllBtn = document.getElementById('selectAllBtn');
        const selectedCount = document.getElementById('selectedCount');
        const checkboxes = document.querySelectorAll('input[name="volunteer_ids[]"]');
        const checkboxContainers = document.querySelectorAll('.checkbox-container');
        
        // Update selected count and visual state
        function updateSelectionState() {
            const selected = document.querySelectorAll('input[name="volunteer_ids[]"]:checked');
            selectedCount.textContent = selected.length;
            
            // Update visual state for all checkboxes
            checkboxes.forEach((checkbox, index) => {
                const container = checkboxContainers[index];
                if (checkbox.checked) {
                    container.classList.add('selected');
                } else {
                    container.classList.remove('selected');
                }
            });
            
            // Update select all button text
            const allChecked = selected.length === checkboxes.length;
            const selectAllText = selectAllBtn.querySelector('span');
            const selectAllIcon = selectAllBtn.querySelector('i');
            
            if (allChecked) {
                selectAllText.textContent = 'Deselect All';
                selectAllIcon.className = 'fas fa-times mr-2';
            } else {
                selectAllText.textContent = 'Select All';
                selectAllIcon.className = 'fas fa-check-double mr-2';
            }
        }
        
        // Volunteer search functionality
        volunteerSearch.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            
            checkboxContainers.forEach(container => {
                const name = container.querySelector('span').textContent.toLowerCase();
                if (name.includes(searchTerm)) {
                    container.style.display = 'flex';
                } else {
                    container.style.display = 'none';
                }
            });
        });
        
        // Select all functionality
        selectAllBtn.addEventListener('click', function() {
            const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = !allChecked;
            });
            
            updateSelectionState();
        });
        
        // Update selection state when checkboxes change
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectionState);
        });
        
        // Initialize selection state
        updateSelectionState();
        
        // Add focus effects for better UX
        const inputs = document.querySelectorAll('.form-input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });
    });
    </script>
</body>
</html>