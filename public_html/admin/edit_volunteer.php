<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}
require_once '../auth/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid volunteer ID.');
}

$volunteer_id = intval($_GET['id']);
$success = '';
$error = '';

// Fetch existing volunteer data
$stmt = $conn->prepare("SELECT * FROM volunteers WHERE id = ?");
$stmt->bind_param("i", $volunteer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die('Volunteer not found.');
}

$volunteer = $result->fetch_assoc();
$stmt->close();

// Fetch subscription data (latest if exists)
$stmt = $conn->prepare("SELECT * FROM volunteer_subscriptions WHERE volunteer_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $volunteer_id);
$stmt->execute();
$sub_result = $stmt->get_result();
$subscription = $sub_result->fetch_assoc(); // No default array
$stmt->close();

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $position = trim($_POST['position']);
    $subscription_status = $_POST['subscription_status'];

    $profile_image = $volunteer['profile_image']; // default to existing

    // Handle profile image upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = realpath(__DIR__ . '/../uploads');
        if ($uploadDir === false) {
            $uploadDir = __DIR__ . '/../uploads';
            mkdir($uploadDir, 0755, true);
        }

        $filename = time() . '_' . basename($_FILES['profile_image']['name']);
        $targetFilePath = $uploadDir . '/' . $filename;

        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFilePath)) {
                $profile_image = 'uploads/' . $filename;
            } else {
                $error = 'Error uploading image.';
            }
        } else {
            $error = 'Only JPG, JPEG, PNG & GIF files are allowed.';
        }
    }

    if (!$error) {
        // Update volunteer info
        $stmt = $conn->prepare("UPDATE volunteers SET full_name = ?, email = ?, phone = ?, address = ?, profile_image = ?, position = ? WHERE id = ?");
        $stmt->bind_param("ssssssi", $full_name, $email, $phone, $address, $profile_image, $position, $volunteer_id);

        if (!$stmt->execute()) {
            $error = "Database error (volunteer): " . $stmt->error;
        }
        $stmt->close();

        // Handle subscription dates
        if ($subscription_status === 'Active') {
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d', strtotime('+1 year'));
        } else {
            // If no existing subscription, still set default dates to avoid NULL
            $start_date = $subscription['start_date'] ?? date('Y-m-d');
            $end_date = $subscription['end_date'] ?? date('Y-m-d', strtotime('+1 year'));
        }

        if ($subscription && isset($subscription['id'])) {
            // Update existing subscription
            $stmt = $conn->prepare("UPDATE volunteer_subscriptions SET status = ?, start_date = ?, end_date = ? WHERE id = ?");
            $stmt->bind_param("sssi", $subscription_status, $start_date, $end_date, $subscription['id']);
            if (!$stmt->execute()) {
                $error = "Database error (subscription): " . $stmt->error;
            }
            $stmt->close();
        } else {
            // Insert new subscription
            $stmt = $conn->prepare("INSERT INTO volunteer_subscriptions (volunteer_id, status, start_date, end_date) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $volunteer_id, $subscription_status, $start_date, $end_date);
            if (!$stmt->execute()) {
                $error = "Database error (subscription insert): " . $stmt->error;
            }
            $stmt->close();
        }

        if (!$error) {
            $success = "Volunteer updated successfully.";
            header("Location: view_volunteers.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Volunteer | VMS</title>
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
      width: 100%;
    }
    
    .submit-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(93, 173, 226, 0.3);
    }
    
    .file-input {
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
    
    .file-input:focus {
      outline: none;
      border-color: #5DADE2;
      box-shadow: 0 0 0 3px rgba(93, 173, 226, 0.1);
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
    
    .form-section {
      margin-bottom: 1.5rem;
    }
    
    .profile-image-container {
      transition: all 0.3s ease;
      border-radius: 12px;
      border: 1px solid #D5D8DC;
      overflow: hidden;
    }
    
    .profile-image-container:hover {
      transform: scale(1.02);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .volunteer-id {
      background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
      color: white;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.875rem;
      font-weight: 500;
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
      
      .profile-image-section {
        flex-direction: column;
        align-items: flex-start;
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
      
      .grid-cols-1 {
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
      }
      
      .submit-btn {
        width: auto;
        min-width: 200px;
      }
      
      .button-container {
        text-align: left;
      }
      
      .profile-image-section {
        flex-direction: row;
        align-items: center;
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
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-2xl sm:text-3xl font-bold text-text mb-2">Edit Volunteer</h1>
          <div class="volunteer-id inline-block">
            <i class="fas fa-id-card mr-2"></i>
            VMS-<?php echo str_pad($volunteer['id'], 4, '0', STR_PAD_LEFT); ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Response Messages -->
    <?php if ($error): ?>
      <div class="error-message">
        <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
      </div>
    <?php endif; ?>

    <!-- Volunteer Edit Form -->
    <div class="form-container">
      <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <!-- Personal Information Section -->
        <div class="border-b border-subtle pb-4">
          <h2 class="text-lg font-semibold text-text mb-4">
            <i class="fas fa-user-circle mr-2 text-primary"></i>
            Personal Information
          </h2>
          
          <!-- Full Name -->
          <div class="form-section">
            <label class="form-label">
              <i class="fas fa-user mr-2 text-primary"></i>
              Full Name *
            </label>
            <input type="text" name="full_name" value="<?php echo htmlspecialchars($volunteer['full_name']); ?>" required 
                   class="form-input"
                   placeholder="Enter volunteer's full name">
          </div>

          <!-- Email -->
          <div class="form-section">
            <label class="form-label">
              <i class="fas fa-envelope mr-2 text-primary"></i>
              Email Address *
            </label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($volunteer['email']); ?>" required 
                   class="form-input"
                   placeholder="volunteer@example.com">
          </div>

          <!-- Phone -->
          <div class="form-section">
            <label class="form-label">
              <i class="fas fa-phone mr-2 text-primary"></i>
              Phone Number
            </label>
            <input type="text" name="phone" value="<?php echo htmlspecialchars($volunteer['phone']); ?>" 
                   class="form-input"
                   placeholder="+1 (555) 123-4567">
          </div>

          <!-- Address -->
          <div class="form-section">
            <label class="form-label">
              <i class="fas fa-map-marker-alt mr-2 text-primary"></i>
              Address
            </label>
            <textarea name="address" rows="3"
                      class="form-input"
                      placeholder="Enter complete address"><?php echo htmlspecialchars($volunteer['address']); ?></textarea>
          </div>
        </div>

        <!-- Professional Information Section -->
        <div class="border-b border-subtle pb-4">
          <h2 class="text-lg font-semibold text-text mb-4">
            <i class="fas fa-briefcase mr-2 text-accent"></i>
            Professional Information
          </h2>
          
          <!-- Position -->
          <div class="form-section">
            <label class="form-label">
              <i class="fas fa-user-tie mr-2 text-accent"></i>
              Position / Role
            </label>
            <input type="text" name="position" value="<?php echo htmlspecialchars($volunteer['position'] ?? ''); ?>" 
                   class="form-input"
                   placeholder="e.g. Team Leader, Coordinator, Field Volunteer">
          </div>

          <!-- Subscription Status -->
          <div class="form-section">
            <label class="form-label">
              <i class="fas fa-calendar-check mr-2 text-accent"></i>
              Subscription Status
            </label>
            <select name="subscription_status" class="form-input">
              <option value="Active" <?php if($subscription['status']=='Active') echo 'selected'; ?>>Active</option>
              <option value="Paused" <?php if($subscription['status']=='Paused') echo 'selected'; ?>>Paused</option>
              <option value="Expired" <?php if($subscription['status']=='Expired') echo 'selected'; ?>>Expired</option>
            </select>
            <p class="mt-2 text-xs text-text/60">
              <i class="fas fa-info-circle mr-1"></i>
              Setting to Active will reset the subscription for 1 year from today.
            </p>
          </div>
        </div>

        <!-- Profile Image Section -->
        <div>
          <h2 class="text-lg font-semibold text-text mb-4">
            <i class="fas fa-camera mr-2 text-accent"></i>
            Profile Image
          </h2>
          
          <div class="form-section profile-image-section flex flex-col sm:flex-row gap-6 items-start">
            <!-- Current Image -->
            <div class="flex-shrink-0">
              <label class="form-label">Current Image</label>
              <div class="profile-image-container mt-1">
                <img src="../<?php echo htmlspecialchars($volunteer['profile_image']); ?>" 
                     alt="Current Profile" 
                     class="h-32 w-32 object-cover">
              </div>
            </div>

            <!-- File Upload -->
            <div class="flex-1 w-full">
              <label class="form-label">
                <i class="fas fa-upload mr-2 text-accent"></i>
                Update Profile Image
              </label>
              <input type="file" name="profile_image" accept="image/*" 
                     class="file-input">
              <p class="mt-2 text-xs text-text/60">
                <i class="fas fa-info-circle mr-1"></i>
                PNG, JPG, GIF files up to 5MB. Leave empty to keep current image.
              </p>
            </div>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="form-section pt-4 button-container">
          <button type="submit" class="submit-btn">
            <i class="fas fa-save mr-2"></i>
            Update Volunteer
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Add focus effects for better UX
    document.addEventListener('DOMContentLoaded', function() {
      const inputs = document.querySelectorAll('.form-input, .file-input');
      
      inputs.forEach(input => {
        input.addEventListener('focus', function() {
          this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
          this.parentElement.classList.remove('focused');
        });
      });
      
      // File input styling
      const fileInput = document.querySelector('input[type="file"]');
      fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
          this.style.borderColor = '#5DADE2';
          this.style.background = 'rgba(93, 173, 226, 0.05)';
        }
      });
    });
  </script>
</body>
</html>