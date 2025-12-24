<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}
require_once '../auth/db.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $position = $_POST['position'] ?? '';
    
    // Auto-generate password from email (username part before @)
    $email_username = explode('@', $email)[0];
    $password = password_hash($email_username, PASSWORD_DEFAULT);

    // Handle image upload
    $profile_image = '';  
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $targetDir = '../uploads/';
        $filename = time() . '_' . basename($_FILES['profile_image']['name']);
        $targetFilePath = $targetDir . $filename;

        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFilePath)) {
                $profile_image = 'uploads/' . $filename; // Path stored in DB
            } else {
                $error = 'Error uploading image.';
            }
        } else {
            $error = 'Only JPG, JPEG, PNG & GIF files are allowed.';
        }
    }

    if (!$error) {
        $stmt = $conn->prepare("INSERT INTO volunteers (full_name, email, phone, address, position, profile_image, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $full_name, $email, $phone, $address, $position, $profile_image, $password);

        if ($stmt->execute()) {
            $volunteer_id = $stmt->insert_id;

            // Start default 1-year subscription
            $start_date = date('Y-m-d'); 
            $end_date = date('Y-m-d', strtotime('+1 year'));
            $sub_stmt = $conn->prepare("INSERT INTO volunteer_subscriptions (volunteer_id, start_date, end_date, status) VALUES (?, ?, ?, 'Active')");
            $sub_stmt->bind_param("iss", $volunteer_id, $start_date, $end_date);
            $sub_stmt->execute();
            $sub_stmt->close();

            $success = "Volunteer added successfully. Subscription active from <strong>$start_date</strong> to <strong>$end_date</strong>.<br><strong>Auto-generated password:</strong> " . htmlspecialchars($email_username);
        } else {
            $error = "Database error: " . $stmt->error;
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Volunteer | VMS</title>
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
      position: relative;
      overflow: hidden;
    }
    
    .submit-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(93, 173, 226, 0.3);
    }
    
    .submit-btn:active {
      transform: translateY(0);
    }
    
    .file-input {
      color: #1C2833;
      padding: 10px 0;
    }
    
    .file-input::file-selector-button {
      background: #5DADE2;
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 8px;
      margin-right: 12px;
      font-family: 'Inter', sans-serif;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    
    .file-input::file-selector-button:hover {
      background: #4A9CD6;
    }

    .password-info {
      background: rgba(93, 173, 226, 0.1);
      border: 1px solid rgba(93, 173, 226, 0.3);
      color: #1C2833;
      border-radius: 8px;
      padding: 12px 16px;
      margin-top: 8px;
      font-size: 0.875rem;
    }
    
    .success-message {
      background: rgba(16, 185, 129, 0.1);
      border: 1px solid rgba(16, 185, 129, 0.3);
      color: #065f46;
      border-radius: 10px;
      padding: 12px 16px;
    }
    
    .error-message {
      background: rgba(239, 68, 68, 0.1);
      border: 1px solid rgba(239, 68, 68, 0.3);
      color: #991b1b;
      border-radius: 10px;
      padding: 12px 16px;
    }
    
    .form-section {
      margin-bottom: 24px;
    }
    
    /* Mobile responsive improvements */
    @media (max-width: 768px) {
      .form-container {
        margin: 1rem;
        padding: 1.5rem !important;
      }
      
      .form-input {
        padding: 14px 16px;
        font-size: 1rem;
      }
      
      .submit-btn {
        padding: 16px 24px;
        font-size: 1.1rem;
      }
      
      .grid-cols-2 {
        grid-template-columns: 1fr;
        gap: 0;
      }
    }
    
    @media (max-width: 480px) {
      .form-container {
        margin: 0.5rem;
        padding: 1.25rem !important;
        border-radius: 12px;
      }
      
      .form-section {
        margin-bottom: 20px;
      }
    }
    
    /* Desktop full-width layout */
    @media (min-width: 1024px) {
      .form-container {
        max-width: 800px;
        margin: 2rem auto;
      }
    }
  </style>
</head>

<body class="min-h-screen lg:ml-64 bg-background">
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main content wrapper -->
    <div class="p-4 md:p-6 lg:mt-0">
        <!-- Header Section -->
        <div class="mb-8 text-center mt-12">
            <h1 class="text-2xl md:text-4xl font-bold text-text mb-2">
                ADD NEW VOLUNTEER
            </h1>
            <p class="text-text/70 text-sm md:text-base tracking-wider">
                REGISTER A NEW VOLUNTEER TO THE VMS DATABASE
            </p>
        </div>

    <!-- Messages -->
    <?php if ($success): ?>
      <div class="success-message mb-6 flex items-center">
        <i class="fas fa-check-circle mr-3"></i>
        <div><?php echo $success; ?></div>
      </div>
    <?php elseif ($error): ?>
      <div class="error-message mb-6 flex items-center">
        <i class="fas fa-exclamation-circle mr-3"></i>
        <div><?php echo $error; ?></div>
      </div>
    <?php endif; ?>

    <!-- Volunteer Registration Form -->
    <form method="POST" enctype="multipart/form-data" class="space-y-6">
      <!-- Personal Information Section -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Full Name -->
        <div class="form-section">
          <label class="form-label">
            <i class="fas fa-user mr-2 text-primary"></i>
            Full Name
          </label>
          <input type="text" name="full_name" required 
                 class="form-input"
                 placeholder="Enter volunteer's full name">
        </div>

        <!-- Email -->
        <div class="form-section">
          <label class="form-label">
            <i class="fas fa-envelope mr-2 text-primary"></i>
            Email Address
          </label>
          <input type="email" name="email" required 
                 class="form-input"
                 placeholder="volunteer@example.com"
                 onblur="updatePasswordInfo()">
        </div>
      </div>

      <!-- Contact Information Section -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Phone -->
        <div class="form-section">
          <label class="form-label">
            <i class="fas fa-phone mr-2 text-primary"></i>
            Phone Number
          </label>
          <input type="text" name="phone" 
                 class="form-input"
                 placeholder="+1 (555) 123-4567">
        </div>

        <!-- Position -->
        <div class="form-section">
          <label class="form-label">
            <i class="fas fa-user-tag mr-2 text-accent"></i>
            Position
          </label>
          <input type="text" name="position" required
                 class="form-input"
                 placeholder="e.g., Team Lead, Coordinator">
        </div>
      </div>

      <!-- Address -->
      <div class="form-section">
        <label class="form-label">
          <i class="fas fa-map-marker-alt mr-2 text-primary"></i>
          Address
        </label>
        <textarea name="address" rows="3" 
                  class="form-input"
                  placeholder="Enter volunteer's complete address"></textarea>
      </div>

      <!-- Security Section -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Password Info -->
        <div class="form-section">
          <label class="form-label">
            <i class="fas fa-lock mr-2 text-primary"></i>
            Password Information
          </label>
          <div class="password-info">
            <i class="fas fa-info-circle mr-2 text-primary"></i>
            Password will be auto-generated from the email username
          </div>
          <div id="password-preview" class="mt-2 text-sm text-text/70 hidden">
            <strong>Preview:</strong> <span id="preview-text"></span>
          </div>
        </div>

        <!-- Profile Image -->
        <div class="form-section">
          <label class="form-label">
            <i class="fas fa-camera mr-2 text-accent"></i>
            Profile Image
          </label>
          <input type="file" name="profile_image" accept="image/*" 
                 class="form-input file-input">
          <p class="text-text/60 text-xs mt-2">JPG, PNG, GIF files accepted (Max 5MB)</p>
        </div>
      </div>

      <!-- Submit Button -->
      <div class="form-section pt-4">
        <button type="submit" class="submit-btn w-full">
          <i class="fas fa-user-plus mr-2"></i>
          Register Volunteer
        </button>
      </div>
    </form>
  </div>

  <script>
    function updatePasswordInfo() {
      const emailInput = document.querySelector('input[name="email"]');
      const passwordPreview = document.getElementById('password-preview');
      const previewText = document.getElementById('preview-text');
      
      if (emailInput.value) {
        const email = emailInput.value;
        const username = email.split('@')[0];
        
        if (username) {
          previewText.textContent = username;
          passwordPreview.classList.remove('hidden');
        } else {
          passwordPreview.classList.add('hidden');
        }
      } else {
        passwordPreview.classList.add('hidden');
      }
    }

    // Add focus effects for better UX
    document.addEventListener('DOMContentLoaded', function() {
      const inputs = document.querySelectorAll('.form-input');
      
      inputs.forEach(input => {
        // Add focus class
        input.addEventListener('focus', function() {
          this.parentElement.classList.add('focused');
        });
        
        // Remove focus class
        input.addEventListener('blur', function() {
          this.parentElement.classList.remove('focused');
        });
      });
    });
  </script>
</body>
</html>