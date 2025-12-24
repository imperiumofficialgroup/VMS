<?php
include '../auth/db.php';
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid event ID.");
}

$event_id = $_GET['id'];
$response = '';

// Fetch event data
$stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();
$location_link = isset($event['location_link']) ? $event['location_link'] : '';

if ($result->num_rows == 0) {
    die("Event not found.");
}

$event = $result->fetch_assoc();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $event_date = $_POST['event_date'];
    $location = trim($_POST['location']);
    $location_link = trim($_POST['location_link']);
    $registration_link = trim($_POST['registration_link']);

    $image_name = $event['image_path'];

    // Check if new image uploaded
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $image_name = time() . '_' . basename($_FILES['image']['name']);
        $image_path = '../uploads/' . $image_name;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
            $response = '<div class="error-message">
                            <i class="fas fa-exclamation-circle mr-2"></i>Failed to upload new image.
                        </div>';
        }
    }

    // Update event
    $stmt = $conn->prepare("UPDATE events SET title=?, description=?, event_date=?, location=?, location_link=?, registration_link=?, image_path=? WHERE event_id=?");
$stmt->bind_param("sssssssi", $title, $description, $event_date, $location, $location_link, $registration_link, $image_name, $event_id);

    if ($stmt->execute()) {
        $response = '<div class="success-message">
                        <i class="fas fa-check-circle mr-2"></i>Event updated successfully.
                    </div>';
        // Refresh event data
$event = [
    'title' => $title,
    'description' => $description,
    'event_date' => $event_date,
    'location' => $location,
    'location_link' => $location_link,
    'registration_link' => $registration_link,
    'image_path' => $image_name
];

    } else {
        $response = '<div class="error-message">
                        <i class="fas fa-exclamation-circle mr-2"></i>Update failed. Please try again.
                    </div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Event | VMS</title>
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
    
    .file-upload-area {
      border: 2px dashed #D5D8DC;
      border-radius: 12px;
      padding: 2rem;
      text-align: center;
      transition: all 0.3s ease;
      cursor: pointer;
      background: rgba(93, 173, 226, 0.02);
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
    
    .form-section {
      margin-bottom: 1.5rem;
    }
    
    .current-image {
      border-radius: 12px;
      border: 1px solid #D5D8DC;
      overflow: hidden;
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
      
      .file-upload-area {
        padding: 1.5rem;
      }
    }
    
    @media (min-width: 641px) and (max-width: 768px) {
      .page-container {
        padding: 1.5rem;
      }
      
      .form-container {
        padding: 2rem;
      }
      
      .grid-cols-1 {
        grid-template-columns: 1fr;
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
      <h1 class="text-2xl sm:text-3xl font-bold text-text mb-2">Edit Event</h1>
      <p class="text-text/70">Update event details and information</p>
    </div>

    <!-- Response Messages -->
    <?= $response ?>

    <!-- Event Edit Form -->
    <div class="form-container">
      <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <!-- Event Title -->
        <div class="form-section">
          <label class="form-label">
            <i class="fas fa-heading mr-2 text-primary"></i>
            Event Title *
          </label>
          <input type="text" name="title" value="<?= htmlspecialchars($event['title']) ?>" required 
                 class="form-input"
                 placeholder="Enter event title">
        </div>

        <!-- Event Description -->
        <div class="form-section">
          <label class="form-label">
            <i class="fas fa-align-left mr-2 text-primary"></i>
            Description *
          </label>
          <textarea name="description" rows="4" required
                    class="form-input"
                    placeholder="Describe the event purpose, activities, and goals"><?= htmlspecialchars($event['description']) ?></textarea>
        </div>

        <!-- Date and Location -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="form-section">
            <label class="form-label">
              <i class="fas fa-calendar mr-2 text-primary"></i>
              Event Date *
            </label>
            <input type="date" name="event_date" value="<?= $event['event_date'] ?>" required
                   class="form-input">
          </div>
<!-- Location Link (Google Maps) -->
<div class="form-section">
  <label class="form-label">
    <i class="fas fa-map-marker-alt mr-2 text-accent"></i>
    Location Link (Google Maps)
  </label>
  <input type="url" name="location_link" 
         value="<?= htmlspecialchars($event['location_link'] ?? '') ?>"
         class="form-input"
         placeholder="https://www.google.com/maps/place/...">
</div>

          <div class="form-section">
            <label class="form-label">
              <i class="fas fa-map-marker-alt mr-2 text-primary"></i>
              Location *
            </label>
            <input type="text" name="location" value="<?= htmlspecialchars($event['location']) ?>" required
                   class="form-input"
                   placeholder="Event venue or address">
          </div>
        </div>

        <!-- Registration Link -->
        <div class="form-section">
          <label class="form-label">
            <i class="fas fa-link mr-2 text-accent"></i>
            Registration Link
          </label>
          <input type="url" name="registration_link" value="<?= htmlspecialchars($event['registration_link']) ?>"
                 class="form-input"
                 placeholder="https://example.com/register">
        </div>

        <!-- Current Image -->
        <div class="form-section">
          <label class="form-label">
            <i class="fas fa-image mr-2 text-accent"></i>
            Current Event Banner
          </label>
          <div class="current-image mt-1">
            <img src="../uploads/<?= htmlspecialchars($event['image_path']) ?>" 
                 alt="Current event image" 
                 class="h-48 w-full object-cover">
          </div>
        </div>

        <!-- Image Upload -->
        <div class="form-section">
          <label class="form-label">
            <i class="fas fa-sync-alt mr-2 text-accent"></i>
            Change Event Banner
          </label>
          <div class="file-upload-area" id="uploadArea">
            <input type="file" id="image" name="image" accept="image/*" class="hidden">
            <div class="text-center">
              <i class="fas fa-cloud-upload-alt text-4xl text-primary mb-3"></i>
              <p class="text-text font-medium mb-1">Click to upload new event banner</p>
              <p class="text-text/60 text-sm">PNG, JPG, GIF up to 5MB</p>
              <div id="image-name" class="text-sm text-primary font-medium mt-2"></div>
            </div>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="form-section pt-4 button-container">
          <button type="submit" class="submit-btn">
            <i class="fas fa-save mr-2"></i>
            Update Event
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // File upload functionality
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('image');
    const imageName = document.getElementById('image-name');

    // Click to upload
    uploadArea.addEventListener('click', () => fileInput.click());

    // Drag and drop functionality
    uploadArea.addEventListener('dragover', (e) => {
      e.preventDefault();
      uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', () => {
      uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', (e) => {
      e.preventDefault();
      uploadArea.classList.remove('dragover');
      if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        updateFileName();
      }
    });

    // File input change
    fileInput.addEventListener('change', updateFileName);

    function updateFileName() {
      if (fileInput.files.length > 0) {
        const fileName = fileInput.files[0].name;
        imageName.textContent = `Selected: ${fileName}`;
        uploadArea.style.borderColor = '#5DADE2';
        uploadArea.style.background = 'rgba(93, 173, 226, 0.05)';
      }
    }

    // Set minimum date to today
    const dateInput = document.querySelector('input[type="date"]');
    dateInput.min = new Date().toISOString().split('T')[0];

    // Add focus effects for better UX
    document.addEventListener('DOMContentLoaded', function() {
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