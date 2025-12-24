<?php
session_start();
require_once '../auth/db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../admin/login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: report_list.php");
    exit();
}

$report_id = intval($_GET['id']);

// Fetch report data
$stmt = $conn->prepare("SELECT * FROM event_reports WHERE id = ?");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();
$report = $result->fetch_assoc();

if (!$report) {
    header("Location: report_list.php");
    exit();
}

$images = json_decode($report['image_paths'], true);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    function clean($str) {
        return htmlspecialchars(trim($str));
    }

    // Handle image uploads
    $uploadedImages = $images ?? [];
    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = '../uploads/reports/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
            $filename = uniqid() . "_" . basename($_FILES['images']['name'][$key]);
            move_uploaded_file($tmpName, $uploadDir . $filename);
            $uploadedImages[] = $filename;
        }
    }

    // Handle image deletions
    if (!empty($_POST['delete_images'])) {
        $imagesToKeep = array_diff($uploadedImages, $_POST['delete_images']);
        foreach ($_POST['delete_images'] as $imgToDelete) {
            $filePath = "../uploads/reports/" . $imgToDelete;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        $uploadedImages = array_values($imagesToKeep);
    }

    // Prepare data
    $data = [
        'event_name' => clean($_POST['event_name']),
        'type_of_event' => clean($_POST['type_of_event']),
        'objective' => clean($_POST['objective']),
        'target_audience' => clean($_POST['target_audience']),
        'date_time' => clean($_POST['date_time']),
        'location' => clean($_POST['location']),
        'budget' => clean($_POST['budget']),
        'organised_by' => clean($_POST['organised_by']),
        'key_highlights' => clean($_POST['key_highlights']),
        'event_schedule' => clean($_POST['event_schedule']),
        'outcomes_and_impact' => clean($_POST['outcomes_and_impact']),
        'partners_and_sponsors' => clean($_POST['partners_and_sponsors']),
        'challenges_and_learning' => clean($_POST['challenges_and_learning']),
        'annexure' => clean($_POST['annexure']),
        'conclusion' => clean($_POST['conclusion']),
    ];

    // Update database
    $stmt = $conn->prepare("UPDATE event_reports SET 
        event_name = ?,
        type_of_event = ?,
        objective = ?,
        target_audience = ?,
        date_time = ?,
        location = ?,
        budget = ?,
        organised_by = ?,
        key_highlights = ?,
        event_schedule = ?,
        outcomes_and_impact = ?,
        partners_and_sponsors = ?,
        challenges_and_learning = ?,
        annexure = ?,
        image_paths = ?,
        conclusion = ?
        WHERE id = ?");
    
    $stmt->bind_param("ssssssssssssssssi",
        $data['event_name'],
        $data['type_of_event'],
        $data['objective'],
        $data['target_audience'],
        $data['date_time'],
        $data['location'],
        $data['budget'],
        $data['organised_by'],
        $data['key_highlights'],
        $data['event_schedule'],
        $data['outcomes_and_impact'],
        $data['partners_and_sponsors'],
        $data['challenges_and_learning'],
        $data['annexure'],
        json_encode($uploadedImages),
        $data['conclusion'],
        $report_id
    );
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Report updated successfully!";
        header("Location: view_report.php?report_id=" . $report_id);
        exit();
    } else {
        $_SESSION['error_message'] = "Error updating report: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Report | VMS</title>
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
      padding: 12px 24px;
      border-radius: 10px;
      font-weight: 600;
      transition: all 0.3s ease;
      cursor: pointer;
      border: 1px solid;
    }
    
    .cancel-btn {
      background: rgba(213, 216, 220, 0.3);
      color: #1C2833;
      border-color: #D5D8DC;
    }
    
    .cancel-btn:hover {
      background: rgba(213, 216, 220, 0.5);
      transform: translateY(-1px);
    }
    
    .submit-btn {
      background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
      color: white;
      border: none;
    }
    
    .submit-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(93, 173, 226, 0.3);
    }
    
    .upload-area {
      border: 2px dashed #D5D8DC;
      border-radius: 12px;
      padding: 2rem;
      text-align: center;
      transition: all 0.3s ease;
      background: rgba(93, 173, 226, 0.02);
      cursor: pointer;
    }
    
    .upload-area:hover {
      border-color: #5DADE2;
      background: rgba(93, 173, 226, 0.05);
    }
    
    .upload-area.dragover {
      border-color: #5DADE2;
      background: rgba(93, 173, 226, 0.1);
    }
    
    .image-preview {
      border: 1px solid #D5D8DC;
      border-radius: 8px;
      overflow: hidden;
      transition: all 0.3s ease;
    }
    
    .image-preview:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(93, 173, 226, 0.15);
    }
    
    .delete-checkbox {
      background: rgba(255, 255, 255, 0.9);
      border-radius: 50%;
      width: 24px;
      height: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.3s ease;
      border: 1px solid #D5D8DC;
    }
    
    .delete-checkbox.checked {
      background: #ef4444;
      color: white;
      border-color: #ef4444;
    }
    
    .error-message {
      background: rgba(239, 68, 68, 0.1);
      border: 1px solid rgba(239, 68, 68, 0.3);
      color: #991b1b;
      border-radius: 10px;
      padding: 12px 16px;
      margin-bottom: 1.5rem;
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
      
      .form-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
      }
      
      .textarea-full {
        grid-column: 1;
      }
      
      .image-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
      }
      
      .action-buttons {
        flex-direction: column;
        gap: 1rem;
        width: 100%;
      }
      
      .action-btn {
        width: 100%;
        text-align: center;
        justify-content: center;
      }
      
      .upload-area {
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
      
      .form-grid {
        grid-template-columns: 1fr;
        gap: 2rem;
      }
      
      .textarea-full {
        grid-column: 1;
      }
      
      .image-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
      }
    }
    
    @media (min-width: 769px) {
      .page-container {
        padding: 2rem;
        max-width: 1000px;
        margin: 0 auto;
      }
      
      .container-card {
        padding: 2.5rem;
      }
      
      .form-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 2rem;
      }
      
      .textarea-full {
        grid-column: 1 / -1;
      }
      
      .image-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 1.5rem;
      }
      
      .action-buttons {
        flex-direction: row;
        justify-content: space-between;
        gap: 1rem;
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
<body class="min-h-screen">
  <?php include 'sidebar.php'; ?>

 <div class="page-container">
  <!-- Header Section -->
  <div class="header-section mb-8 mt-12 sm:mt-0">
    <h1 class="text-2xl md:text-3xl font-bold text-text mb-2">Edit Event Report</h1>
    <p class="text-text/70">Update the details below and save changes</p>
  </div>


    <?php if (isset($_SESSION['error_message'])): ?>
      <div class="error-message flex items-center">
        <i class="fas fa-exclamation-circle mr-3"></i>
        <div><?= $_SESSION['error_message'] ?></div>
        <button onclick="this.parentElement.remove()" class="ml-auto">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Edit Form -->
    <div class="container-card">
      <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <div class="form-grid grid">
          <?php
          $fields = [
            'event_name' => ['label' => 'Event Name', 'type' => 'text', 'value' => $report['event_name']],
            'type_of_event' => ['label' => 'Type of Event', 'type' => 'text', 'value' => $report['type_of_event']],
            'objective' => ['label' => 'Objective', 'type' => 'textarea', 'value' => $report['objective']],
            'target_audience' => ['label' => 'Target Audience', 'type' => 'textarea', 'value' => $report['target_audience']],
            'date_time' => ['label' => 'Date & Time', 'type' => 'datetime', 'value' => date('Y-m-d\TH:i', strtotime($report['date_time']))],
            'location' => ['label' => 'Location', 'type' => 'textarea', 'value' => $report['location']],
            'budget' => ['label' => 'Budget', 'type' => 'textarea', 'value' => $report['budget']],
            'organised_by' => ['label' => 'Organised By', 'type' => 'text', 'value' => $report['organised_by']],
            'key_highlights' => ['label' => 'Key Highlights', 'type' => 'textarea', 'value' => $report['key_highlights']],
            'event_schedule' => ['label' => 'Event Schedule', 'type' => 'textarea', 'value' => $report['event_schedule']],
            'outcomes_and_impact' => ['label' => 'Outcomes and Impact', 'type' => 'textarea', 'value' => $report['outcomes_and_impact']],
            'partners_and_sponsors' => ['label' => 'Partners and Sponsors', 'type' => 'textarea', 'value' => $report['partners_and_sponsors']],
            'challenges_and_learning' => ['label' => 'Challenges and Learning', 'type' => 'textarea', 'value' => $report['challenges_and_learning']],
            'annexure' => ['label' => 'Annexure', 'type' => 'textarea', 'value' => $report['annexure']],
            'conclusion' => ['label' => 'Conclusion', 'type' => 'textarea', 'value' => $report['conclusion']],
          ];
          
          foreach ($fields as $name => $field): ?>
            <div class="<?= $field['type'] === 'textarea' ? 'textarea-full' : '' ?>">
              <label class="form-label">
                <i class="fas fa-asterisk text-red-500 text-xs mr-1"></i>
                <?= $field['label'] ?>
              </label>
              
              <?php if ($field['type'] === 'textarea'): ?>
                <textarea 
                  name="<?= $name ?>" 
                  rows="5"
                  class="form-input"
                  placeholder="Enter <?= strtolower($field['label']) ?> details..."
                  required
                ><?= htmlspecialchars($field['value']) ?></textarea>
              <?php elseif ($field['type'] === 'datetime'): ?>
                <div class="relative">
                  <input 
                    type="datetime-local" 
                    name="<?= $name ?>" 
                    value="<?= htmlspecialchars($field['value']) ?>"
                    class="form-input"
                    required
                  >
                  <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                    <i class="far fa-calendar-alt text-primary"></i>
                  </div>
                </div>
              <?php else: ?>
                <input 
                  type="text" 
                  name="<?= $name ?>" 
                  value="<?= htmlspecialchars($field['value']) ?>"
                  class="form-input"
                  required
                >
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          
          <!-- Image Upload Section -->
          <div class="textarea-full">
            <label class="form-label">
              <i class="fas fa-images text-accent mr-2"></i>
              Event Images
              <span class="text-text/60 font-normal text-sm">(Add new images or remove existing ones)</span>
            </label>
            
            <?php if (!empty($images)): ?>
              <div class="mb-6">
                <h3 class="text-sm font-medium text-text mb-3">Current Images</h3>
                <div class="image-grid grid mb-4">
                  <?php foreach ($images as $img): ?>
                    <div class="image-preview relative group">
                      <img src="../uploads/reports/<?= htmlspecialchars($img) ?>" 
                           class="w-full h-32 object-cover">
                      <label class="absolute top-2 right-2 cursor-pointer">
                        <input type="checkbox" name="delete_images[]" value="<?= htmlspecialchars($img) ?>" 
                               class="hidden peer">
                        <div class="delete-checkbox peer-checked:checked">
                          <i class="fas fa-check text-xs opacity-0 peer-checked:opacity-100"></i>
                        </div>
                      </label>
                      <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-2">
                        <p class="text-white text-xs truncate"><?= htmlspecialchars($img) ?></p>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
            
            <!-- File Upload Area -->
            <div class="upload-area" id="uploadArea">
              <input type="file" name="images[]" multiple accept="image/*" class="hidden" id="fileInput">
              <div class="text-center">
                <i class="fas fa-cloud-upload-alt text-3xl text-primary mb-3"></i>
                <p class="text-text font-medium mb-1">Click to upload new images</p>
                <p class="text-text/60 text-sm">PNG, JPG up to 10MB each</p>
                <p class="text-text/50 text-xs mt-2">or drag and drop files here</p>
              </div>
            </div>
            <div id="file-preview" class="image-grid grid mt-4 hidden"></div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="pt-6 border-t border-subtle action-buttons flex">
          <a href="view_report.php?report_id=<?= $report_id ?>" 
             class="cancel-btn action-btn inline-flex items-center">
            <i class="fas fa-times mr-2"></i>
            Cancel
          </a>
          <button type="submit" class="submit-btn action-btn inline-flex items-center">
            <i class="fas fa-save mr-2"></i>
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // File upload functionality
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('fileInput');
    const filePreview = document.getElementById('file-preview');

    // Click to upload
    uploadArea.addEventListener('click', () => fileInput.click());

    // File input change
    fileInput.addEventListener('change', updateFilePreview);

    function updateFilePreview() {
      filePreview.innerHTML = '';
      filePreview.classList.add('hidden');
      
      if (fileInput.files && fileInput.files.length > 0) {
        filePreview.classList.remove('hidden');
        
        Array.from(fileInput.files).forEach(file => {
          const reader = new FileReader();
          reader.onload = function(e) {
            const previewItem = document.createElement('div');
            previewItem.className = 'image-preview';
            previewItem.innerHTML = `
              <img src="${e.target.result}" class="w-full h-32 object-cover">
              <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-2">
                <p class="text-white text-xs truncate">${file.name}</p>
              </div>
            `;
            filePreview.appendChild(previewItem);
          };
          reader.readAsDataURL(file);
        });
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