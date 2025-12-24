<?php
session_start();
require_once '../auth/db.php';

if (!isset($_SESSION['volunteer_id'])) {
    header('Location: ../auth/volunteer_login.php');
    exit();
}

$volunteer_id = $_SESSION['volunteer_id'];

// Fetch current details
$stmt = $conn->prepare("SELECT * FROM volunteers WHERE id = ?");
$stmt->bind_param("i", $volunteer_id);
$stmt->execute();
$result = $stmt->get_result();
$volunteer = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $birth_date = $_POST['birth_date'];
    $whatsapp_number = trim($_POST['whatsapp_number']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $profile_image = $volunteer['profile_image']; // keep old if not updated

    // Profile image upload
    if (!empty($_FILES['profile_image']['name'])) {
        $target_dir = "../uploads/volunteers/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $target_file = $target_dir . basename($_FILES["profile_image"]["name"]);
        move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file);
        $profile_image = "uploads/volunteers/" . basename($_FILES["profile_image"]["name"]);
    }

    // Password handling
    if (!empty($password)) {
        if ($password !== $confirm_password) {
            $error = "Passwords do not match!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $update = $conn->prepare("UPDATE volunteers 
                SET email=?, phone=?, address=?, birth_date=?, whatsapp_number=?, password=?, profile_image=?, updated_at=NOW() 
                WHERE id=?");
            $update->bind_param("sssssssi", $email, $phone, $address, $birth_date, $whatsapp_number, $hashed_password, $profile_image, $volunteer_id);
        }
    } else {
        $update = $conn->prepare("UPDATE volunteers 
            SET email=?, phone=?, address=?, birth_date=?, whatsapp_number=?, profile_image=?, updated_at=NOW() 
            WHERE id=?");
        $update->bind_param("ssssssi", $email, $phone, $address, $birth_date, $whatsapp_number, $profile_image, $volunteer_id);
    }

    if (isset($update) && $update->execute()) {
        $_SESSION['success'] = "Profile updated successfully!";
        header("Location: edit_profile.php");
        exit();
    } else {
        $error = "Error updating profile.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Profile - <?php echo htmlspecialchars($volunteer['full_name']); ?> | VMS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    
    .form-container {
      background: white;
      border-radius: 20px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
      border: 1px solid #D5D8DC;
    }
    
    .form-input {
      width: 100%;
      padding: 12px 16px;
      border-radius: 12px;
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
    
    .form-input:disabled {
      background-color: #f8fafc;
      color: #64748b;
      cursor: not-allowed;
      border-color: #e2e8f0;
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
      border-radius: 12px;
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
    
    .profile-image {
      border-radius: 16px;
      border: 3px solid #D5D8DC;
      transition: all 0.3s ease;
    }
    
    .profile-image:hover {
      transform: scale(1.02);
      border-color: #5DADE2;
    }
    
    .section-title {
      color: #1C2833;
      font-weight: 700;
      font-size: 1.5rem;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
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
      
      .grid-cols-1 {
        grid-template-columns: 1fr;
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
        max-width: 900px;
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
    }
    
    @media (min-width: 1024px) {
      .page-container {
        max-width: 1000px;
      }
      
      .form-container {
        padding: 3rem;
      }
    }
    
    .locked-field {
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
      border: 1px solid #e2e8f0;
      color: #64748b;
    }
    
    .form-section {
      margin-bottom: 1.5rem;
    }
  </style>
</head>
<body>
  <?php include 'sidebar.php'; ?>
  
  <div class="page-container lg:ml-96">
    <!-- Header Section -->
    <div class="header-section mt-12 sm:mt-0 mb-8 text-center sm:text-left">
      <h1 class="text-2xl sm:text-3xl font-bold text-text mb-2">Edit Profile</h1>
      <p class="text-text/70">Update your personal information and preferences</p>
    </div>

    <!-- Response Messages -->
    <?php if (!empty($error)): ?>
      <div class="error-message">
        <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
      </div>
    <?php elseif (!empty($_SESSION['success'])): ?>
      <div class="success-message">
        <i class="fas fa-check-circle mr-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
      </div>
    <?php endif; ?>

    <!-- Profile Edit Form -->
    <div class="form-container">
      <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <!-- Personal Information Section -->
        <div class="border-b border-subtle pb-6">
          <h2 class="section-title">
            <i class="fas fa-user-circle text-primary"></i>
            Personal Information
          </h2>
          
          <div class="grid grid-cols-1 gap-6">
            <!-- Full Name (Locked) -->
            <div class="form-section">
              <label class="form-label">
                <i class="fas fa-user mr-2 text-primary"></i>
                Full Name
              </label>
              <input type="text" value="<?= htmlspecialchars($volunteer['full_name']); ?>" disabled 
                     class="form-input locked-field">
              <p class="text-xs text-text/60 mt-1">Contact administrator to change name</p>
            </div>

            <!-- Position (Locked) -->
            <div class="form-section">
              <label class="form-label">
                <i class="fas fa-briefcase mr-2 text-primary"></i>
                Position
              </label>
              <input type="text" value="<?= htmlspecialchars($volunteer['position']); ?>" disabled 
                     class="form-input locked-field">
              <p class="text-xs text-text/60 mt-1">Position assigned by organization</p>
            </div>
          </div>
        </div>

        <!-- Contact Information Section -->
        <div class="border-b border-subtle pb-6">
          <h2 class="section-title">
            <i class="fas fa-address-book text-accent"></i>
            Contact Information
          </h2>
          
          <div class="grid grid-cols-1 gap-6">
            <!-- Email -->
            <div class="form-section">
              <label class="form-label">
                <i class="fas fa-envelope mr-2 text-accent"></i>
                Email Address *
              </label>
              <input type="email" name="email" value="<?= htmlspecialchars($volunteer['email']); ?>" required 
                     class="form-input"
                     placeholder="your.email@example.com">
            </div>

            <!-- Phone -->
            <div class="form-section">
              <label class="form-label">
                <i class="fas fa-phone mr-2 text-accent"></i>
                Phone Number *
              </label>
              <input type="text" name="phone" value="<?= htmlspecialchars($volunteer['phone']); ?>" required 
                     class="form-input"
                     placeholder="+1 (555) 123-4567">
            </div>

            <!-- WhatsApp Number -->
            <div class="form-section">
              <label class="form-label">
                <i class="fab fa-whatsapp mr-2 text-green-500"></i>
                WhatsApp Number
              </label>
              <input type="text" name="whatsapp_number" value="<?= htmlspecialchars($volunteer['whatsapp_number']); ?>" 
                     class="form-input"
                     placeholder="+91XXXXXXXXXX">
            </div>
          </div>
        </div>

        <!-- Additional Information Section -->
        <div class="border-b border-subtle pb-6">
          <h2 class="section-title">
            <i class="fas fa-info-circle text-primary"></i>
            Additional Information
          </h2>
          
          <div class="grid grid-cols-1 gap-6">
            <!-- Date of Birth -->
            <div class="form-section">
              <label class="form-label">
                <i class="fas fa-calendar-alt mr-2 text-primary"></i>
                Date of Birth
              </label>
              <input type="date" name="birth_date" value="<?= htmlspecialchars($volunteer['birth_date']); ?>" 
                     class="form-input">
            </div>

            <!-- Address -->
            <div class="form-section">
              <label class="form-label">
                <i class="fas fa-map-marker-alt mr-2 text-primary"></i>
                Address
              </label>
              <textarea name="address" rows="3"
                        class="form-input"
                        placeholder="Enter your complete address"><?= htmlspecialchars($volunteer['address']); ?></textarea>
            </div>
          </div>
        </div>

        <!-- Profile Image Section -->
        <div class="border-b border-subtle pb-6">
          <h2 class="section-title">
            <i class="fas fa-camera text-accent"></i>
            Profile Image
          </h2>
          
          <div class="flex flex-col sm:flex-row gap-6 items-start">
            <!-- Current Image -->
            <div class="flex-shrink-0">
              <label class="form-label">Current Profile Image</label>
              <?php if (!empty($volunteer['profile_image'])): ?>
                <img src="../<?= $volunteer['profile_image']; ?>" alt="Profile" 
                     class="profile-image mt-1 h-32 w-32 object-cover">
              <?php else: ?>
                <div class="profile-image mt-1 h-32 w-32 bg-gradient-to-br from-primary to-accent rounded-2xl flex items-center justify-center text-white text-4xl font-bold">
                  <?= strtoupper(substr($volunteer['full_name'], 0, 1)) ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- File Upload -->
            <div class="flex-1 w-full">
              <label class="form-label">
                <i class="fas fa-upload mr-2 text-accent"></i>
                Update Profile Image
              </label>
              <input type="file" name="profile_image" 
                     class="file-input">
              <p class="text-xs text-text/60 mt-2">
                <i class="fas fa-info-circle mr-1"></i>
                Recommended: Square image, max 2MB. JPG, PNG, or GIF formats.
              </p>
            </div>
          </div>
        </div>

        <!-- Password Section -->
        <div>
          <h2 class="section-title">
            <i class="fas fa-lock text-accent"></i>
            Change Password
          </h2>
          
          <div class="grid grid-cols-1 gap-6">
            <!-- New Password -->
            <div class="form-section">
              <label class="form-label">
                <i class="fas fa-key mr-2 text-accent"></i>
                New Password
              </label>
              <input type="password" name="password" 
                     class="form-input"
                     placeholder="Leave blank to keep current password">
            </div>

            <!-- Confirm Password -->
            <div class="form-section">
              <label class="form-label">
                <i class="fas fa-key mr-2 text-accent"></i>
                Confirm Password
              </label>
              <input type="password" name="confirm_password" 
                     class="form-input"
                     placeholder="Confirm your new password">
            </div>
          </div>
          
          <p class="text-xs text-text/60 mt-2">
            <i class="fas fa-info-circle mr-1"></i>
            Password must be at least 8 characters long with letters and numbers.
          </p>
        </div>

        <!-- Submit Button -->
        <div class="form-section pt-4 button-container">
          <button type="submit" class="submit-btn">
            <i class="fas fa-save mr-2"></i>
            Save Changes
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
    });
  </script>
</body>
</html>