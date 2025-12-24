<?php
session_start();
require_once '../auth/db.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    function clean($str) {
        return htmlspecialchars(trim($str));
    }

    // Upload images
    $uploadedImages = [];
    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = '../uploads/reports/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
            $filename = uniqid() . "_" . basename($_FILES['images']['name'][$key]);
            move_uploaded_file($tmpName, $uploadDir . $filename);
            $uploadedImages[] = $filename;
        }
    }

    // Gather form data
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

    // Generate HTML for PDF
    ob_start();
    include 'report_template.php';
    $pdfHtml = ob_get_clean();

    // Setup dompdf
    require_once '../vendor/autoload.php';
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($pdfHtml);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Save PDF
    $pdfName = 'report_' . time() . '.pdf';
    file_put_contents("../uploads/reports/" . $pdfName, $dompdf->output());

    // Store in DB
    $stmt = $conn->prepare("INSERT INTO event_reports 
        (event_name, type_of_event, objective, target_audience, date_time, location, budget, 
         organised_by, key_highlights, event_schedule, outcomes_and_impact, partners_and_sponsors, 
         challenges_and_learning, annexure, image_paths, pdf_file, conclusion) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("sssssssssssssssss",
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
        $pdfName,
        $data['conclusion']
    );
    $stmt->execute();

    header("Location: report_list.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Event Report</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            blue: {
              50: '#eff6ff',
              100: '#dbeafe',
              200: '#bfdbfe',
              300: '#93c5fd',
              400: '#60a5fa',
              500: '#3b82f6',
              600: '#2563eb',
              700: '#1d4ed8',
              800: '#1e40af',
              900: '#1e3a8a',
            }
          }
        }
      }
    }
  </script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 font-sans antialiased">
  <?php include 'sidebar.php'; ?>

  <div class="lg:ml-64">
    <div class="px-4 py-8 sm:px-6 lg:px-8">
      <div class="max-w-4xl mx-auto">
        <div class="mb-8 text-center">
          <h1 class="text-3xl font-bold text-blue-700">Create New Event Report</h1>
          <p class="text-gray-600 mt-2">Fill in all sections below to generate a comprehensive event report</p>
        </div>

        <form method="POST" enctype="multipart/form-data" class="bg-white rounded-xl shadow-md border border-blue-100 overflow-hidden">
          <div class="p-6 md:p-8 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <?php
              $fields = [
                'event_name' => ['label' => 'Event Name', 'type' => 'text'],
                'type_of_event' => ['label' => 'Type of Event', 'type' => 'text'],
                'objective' => ['label' => 'Objective', 'type' => 'textarea'],
                'target_audience' => ['label' => 'Target Audience', 'type' => 'textarea'],
                'date_time' => ['label' => 'Date & Time', 'type' => 'datetime'],
                'location' => ['label' => 'Location', 'type' => 'textarea'],
                'budget' => ['label' => 'Budget', 'type' => 'textarea'],
                'organised_by' => ['label' => 'Organised By', 'type' => 'text'],
                'key_highlights' => ['label' => 'Key Highlights', 'type' => 'textarea'],
                'event_schedule' => ['label' => 'Event Schedule', 'type' => 'textarea'],
                'outcomes_and_impact' => ['label' => 'Outcomes and Impact', 'type' => 'textarea'],
                'partners_and_sponsors' => ['label' => 'Partners and Sponsors', 'type' => 'textarea'],
                'challenges_and_learning' => ['label' => 'Challenges and Learning', 'type' => 'textarea'],
                'annexure' => ['label' => 'Annexure', 'type' => 'textarea'],
                'conclusion' => ['label' => 'Conclusion', 'type' => 'textarea']
              ];
              
              foreach ($fields as $name => $field): ?>
                <div class="<?= $field['type'] === 'textarea' ? 'md:col-span-2' : '' ?>">
                  <label class="block text-sm font-semibold text-blue-800 mb-2">
                    <?= $field['label'] ?>
                    <span class="text-red-500">*</span>
                  </label>
                  
                  <?php if ($field['type'] === 'textarea'): ?>
                    <textarea 
                      name="<?= $name ?>" 
                      rows="5"
                      class="w-full px-4 py-3 rounded-lg border-2 border-blue-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-gray-700 transition-all duration-200"
                      placeholder="Enter <?= strtolower($field['label']) ?> details..."
                      required
                    ></textarea>
                  <?php elseif ($field['type'] === 'datetime'): ?>
                    <div class="relative">
                      <input 
                        type="datetime-local" 
                        name="<?= $name ?>" 
                        class="w-full px-4 py-3 rounded-lg border-2 border-blue-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-gray-700 transition-all duration-200"
                        required
                      >
                      <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                        <i class="far fa-calendar-alt text-blue-400"></i>
                      </div>
                    </div>
                  <?php else: ?>
                    <input 
                      type="text" 
                      name="<?= $name ?>" 
                      class="w-full px-4 py-3 rounded-lg border-2 border-blue-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-gray-700 transition-all duration-200"
                      required
                    >
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
              
              <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-blue-800 mb-2">
                  Event Images
                  <span class="text-gray-500 font-normal">(Multiple files allowed)</span>
                </label>
                <div class="mt-2 flex justify-center px-6 pt-8 pb-8 border-2 border-blue-100 border-dashed rounded-lg bg-blue-50 hover:bg-blue-100 transition-colors duration-200">
                  <div class="space-y-2 text-center">
                    <div class="flex justify-center text-blue-600">
                      <i class="fas fa-cloud-upload-alt text-3xl"></i>
                    </div>
                    <div class="flex text-sm text-blue-600">
                      <label class="relative cursor-pointer rounded-md font-medium focus-within:outline-none">
                        <span>Click to upload</span>
                        <input type="file" name="images[]" multiple accept="image/*" class="sr-only">
                      </label>
                      <p class="pl-1">or drag and drop</p>
                    </div>
                    <p class="text-xs text-blue-500">
                      PNG, JPG up to 10MB each
                    </p>
                  </div>
                </div>
                <div id="file-preview" class="mt-4 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 hidden"></div>
              </div>
            </div>

            <div class="pt-6 border-t border-blue-100">
              <button 
                type="submit" 
                class="w-full md:w-auto px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-md transition-all duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50"
              >
                <i class="fas fa-file-pdf mr-2"></i>
                Generate Professional Report
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Enhanced file upload preview with blue theme
    const fileInput = document.querySelector('input[type="file"]');
    const filePreview = document.getElementById('file-preview');
    
    fileInput.addEventListener('change', function() {
      filePreview.innerHTML = '';
      filePreview.classList.add('hidden');
      
      if (this.files && this.files.length > 0) {
        filePreview.classList.remove('hidden');
        
        Array.from(this.files).forEach(file => {
          const reader = new FileReader();
          reader.onload = function(e) {
            const previewItem = document.createElement('div');
            previewItem.className = 'relative group';
            previewItem.innerHTML = `
              <div class="aspect-w-1 aspect-h-1">
                <img src="${e.target.result}" class="object-cover rounded-lg border-2 border-blue-100 w-full h-full">
              </div>
              <div class="absolute inset-0 bg-blue-800 bg-opacity-0 group-hover:bg-opacity-20 transition-all duration-200 rounded-lg"></div>
              <div class="absolute bottom-1 left-1 right-1 bg-white bg-opacity-90 rounded px-2 py-1 truncate text-xs font-medium text-blue-800">
                ${file.name.length > 20 ? file.name.substring(0, 17) + '...' : file.name}
              </div>
            `;
            filePreview.appendChild(previewItem);
          };
          reader.readAsDataURL(file);
        });
      }
    });

    // Make the file upload area a drop zone
    const dropZone = document.querySelector('.border-dashed');
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
      dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
      e.preventDefault();
      e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
      dropZone.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
      dropZone.addEventListener(eventName, unhighlight, false);
    });

    function highlight() {
      dropZone.classList.add('border-blue-400', 'bg-blue-200');
    }

    function unhighlight() {
      dropZone.classList.remove('border-blue-400', 'bg-blue-200');
    }

    dropZone.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
      const dt = e.dataTransfer;
      const files = dt.files;
      fileInput.files = files;
      const event = new Event('change');
      fileInput.dispatchEvent(event);
    }
  </script>
</body>
</html>