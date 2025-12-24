<?php
session_start();
require_once '../auth/db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../admin/login.php");
    exit();
}

if (!isset($_GET['report_id'])) {
    echo "Report ID not specified.";
    exit();
}

$report_id = intval($_GET['report_id']);

// Fetch report
$stmt = $conn->prepare("SELECT * FROM event_reports WHERE id = ?");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();
$report = $result->fetch_assoc();

if (!$report) {
    echo "Report not found.";
    exit();
}

$images = json_decode($report['image_paths'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Report | VMS</title>
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
    
    .section-card {
      transition: all 0.3s ease;
      border-left: 4px solid transparent;
    }
    
    .section-card:hover {
      border-left-color: #5DADE2;
      background: rgba(93, 173, 226, 0.02);
      transform: translateY(-2px);
    }
    
    .image-card {
      transition: all 0.3s ease;
      border: 1px solid #D5D8DC;
      border-radius: 12px;
      overflow: hidden;
    }
    
    .image-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(93, 173, 226, 0.15);
    }
    
    .image-hover {
      transition: transform 0.3s ease;
    }
    
    .image-hover:hover {
      transform: scale(1.05);
    }
    
    .action-btn {
      padding: 10px 20px;
      border-radius: 10px;
      font-weight: 600;
      transition: all 0.3s ease;
      border: 1px solid;
    }
    
    .pdf-btn {
      background: rgba(239, 68, 68, 0.1);
      color: #ef4444;
      border-color: rgba(239, 68, 68, 0.3);
    }
    
    .pdf-btn:hover {
      background: rgba(239, 68, 68, 0.2);
      transform: translateY(-1px);
    }
    
    .edit-btn {
      background: rgba(93, 173, 226, 0.1);
      color: #5DADE2;
      border-color: rgba(93, 173, 226, 0.3);
    }
    
    .edit-btn:hover {
      background: rgba(93, 173, 226, 0.2);
      transform: translateY(-1px);
    }
    
    .info-badge {
      background: rgba(93, 173, 226, 0.1);
      color: #5DADE2;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 500;
      border: 1px solid rgba(93, 173, 226, 0.2);
    }
    
    .header-gradient {
      background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
    }
    
    /* Mobile First Responsive Design */
    @media (max-width: 640px) {
      .page-container {
        padding: 1rem;
      }
      
      .container-card {
        padding: 1.25rem;
        margin-bottom: 1.5rem;
      }
      
      .report-header {
        text-align: center;
        padding: 1.5rem 1rem;
      }
      
      .header-content {
        flex-direction: column;
        gap: 1.5rem;
      }
      
      .header-info {
        align-items: center;
      }
      
      .badges-container {
        justify-content: center;
        flex-wrap: wrap;
        gap: 0.5rem;
      }
      
      .action-buttons {
        flex-direction: column;
        gap: 0.75rem;
        width: 100%;
      }
      
      .action-btn {
        width: 100%;
        text-align: center;
        justify-content: center;
      }
      
      .section-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
      }
      
      .image-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
      }
      
      .section-content {
        padding-left: 0;
        margin-top: 0.5rem;
      }
      
      .section-icon {
        margin-bottom: 0.5rem;
      }
    }
    
    @media (min-width: 641px) and (max-width: 768px) {
      .page-container {
        padding: 1.5rem;
      }
      
      .container-card {
        padding: 2rem;
      }
      
      .section-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
      }
      
      .image-grid {
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
      
      .header-content {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
      }
      
      .section-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 2rem;
      }
      
      .image-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 2rem;
      }
      
      .action-buttons {
        flex-direction: row;
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
<body class="min-h-screen lg:ml-64">
  <?php include 'sidebar.php'; ?>

  <div class="page-container">
<!-- Report Header -->
<div class="container-card mb-6 mt-12 sm:mt-0">
  <div class="header-gradient text-white rounded-2xl p-6">
    <div class="header-content flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
      <div class="header-info">
        <h1 class="text-2xl md:text-3xl font-bold mb-3"><?= htmlspecialchars($report['event_name']) ?></h1>
        <div class="badges-container flex flex-wrap gap-2">
          <span class="info-badge bg-white/20 text-white border-white/30">
            <i class="fas fa-tag mr-1"></i>
            <?= htmlspecialchars($report['type_of_event']) ?>
          </span>
          <span class="info-badge bg-white/20 text-white border-white/30">
            <i class="far fa-calendar-alt mr-1"></i>
            <?= date("M j, Y", strtotime($report['date_time'])) ?>
          </span>
          <span class="info-badge bg-white/20 text-white border-white/30">
            <i class="fas fa-map-marker-alt mr-1"></i>
            <?= htmlspecialchars($report['location']) ?>
          </span>
          <span class="info-badge bg-white/20 text-white border-white/30">
            <i class="fas fa-user-tie mr-1"></i>
            <?= htmlspecialchars($report['organised_by']) ?>
          </span>
        </div>
      </div>
      <div class="action-buttons flex mt-4 sm:mt-0">
        <a href="export_pdf.php?id=<?= $report_id ?>" class="action-btn pdf-btn inline-flex items-center mr-2">
          <i class="fas fa-file-pdf mr-2"></i>
          Export PDF
        </a>
        <a href="edit_report.php?id=<?= $report_id ?>" class="action-btn edit-btn inline-flex items-center">
          <i class="fas fa-edit mr-2"></i>
          Edit Report
        </a>
      </div>
    </div>
  </div>
</div>


    <!-- Report Content -->
    <div class="space-y-6">
      <!-- Key Information Section -->
      <div class="section-grid grid">
        <!-- Objective -->
        <div class="section-card container-card">
          <div class="flex items-start">
            <div class="section-icon text-primary mr-4">
              <i class="fas fa-bullseye text-xl"></i>
            </div>
            <div class="flex-1">
              <h2 class="text-lg font-semibold text-text mb-3">Objective</h2>
              <div class="section-content text-text/70 leading-relaxed">
                <?= nl2br(htmlspecialchars($report['objective'])) ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Target Audience -->
        <div class="section-card container-card">
          <div class="flex items-start">
            <div class="section-icon text-primary mr-4">
              <i class="fas fa-users text-xl"></i>
            </div>
            <div class="flex-1">
              <h2 class="text-lg font-semibold text-text mb-3">Target Audience</h2>
              <div class="section-content text-text/70 leading-relaxed">
                <?= nl2br(htmlspecialchars($report['target_audience'])) ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Event Schedule -->
      <div class="section-card container-card">
        <div class="flex items-start">
          <div class="section-icon text-primary mr-4">
            <i class="fas fa-calendar-day text-xl"></i>
          </div>
          <div class="flex-1">
            <h2 class="text-lg font-semibold text-text mb-3">Event Schedule</h2>
            <div class="section-content text-text/70 leading-relaxed">
              <?= nl2br(htmlspecialchars($report['event_schedule'])) ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Key Highlights -->
      <div class="section-card container-card">
        <div class="flex items-start">
          <div class="section-icon text-primary mr-4">
            <i class="fas fa-star text-xl"></i>
          </div>
          <div class="flex-1">
            <h2 class="text-lg font-semibold text-text mb-3">Key Highlights</h2>
            <div class="section-content text-text/70 leading-relaxed">
              <?= nl2br(htmlspecialchars($report['key_highlights'])) ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Outcomes and Impact -->
      <div class="section-card container-card">
        <div class="flex items-start">
          <div class="section-icon text-primary mr-4">
            <i class="fas fa-chart-line text-xl"></i>
          </div>
          <div class="flex-1">
            <h2 class="text-lg font-semibold text-text mb-3">Outcomes and Impact</h2>
            <div class="section-content text-text/70 leading-relaxed">
              <?= nl2br(htmlspecialchars($report['outcomes_and_impact'])) ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Partners and Sponsors -->
      <?php if (!empty($report['partners_and_sponsors'])): ?>
      <div class="section-card container-card">
        <div class="flex items-start">
          <div class="section-icon text-primary mr-4">
            <i class="fas fa-handshake text-xl"></i>
          </div>
          <div class="flex-1">
            <h2 class="text-lg font-semibold text-text mb-3">Partners & Sponsors</h2>
            <div class="section-content text-text/70 leading-relaxed">
              <?= nl2br(htmlspecialchars($report['partners_and_sponsors'])) ?>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Challenges and Learning -->
      <div class="section-card container-card">
        <div class="flex items-start">
          <div class="section-icon text-primary mr-4">
            <i class="fas fa-lightbulb text-xl"></i>
          </div>
          <div class="flex-1">
            <h2 class="text-lg font-semibold text-text mb-3">Challenges and Learning</h2>
            <div class="section-content text-text/70 leading-relaxed">
              <?= nl2br(htmlspecialchars($report['challenges_and_learning'])) ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Budget -->
      <div class="section-card container-card">
        <div class="flex items-start">
          <div class="section-icon text-primary mr-4">
            <i class="fas fa-money-bill-wave text-xl"></i>
          </div>
          <div class="flex-1">
            <h2 class="text-lg font-semibold text-text mb-3">Budget</h2>
            <div class="section-content text-text/70 leading-relaxed">
              <?= nl2br(htmlspecialchars($report['budget'])) ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Annexure -->
      <?php if (!empty($report['annexure'])): ?>
      <div class="section-card container-card">
        <div class="flex items-start">
          <div class="section-icon text-primary mr-4">
            <i class="fas fa-paperclip text-xl"></i>
          </div>
          <div class="flex-1">
            <h2 class="text-lg font-semibold text-text mb-3">Annexure</h2>
            <div class="section-content text-text/70 leading-relaxed">
              <?= nl2br(htmlspecialchars($report['annexure'])) ?>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Conclusion -->
      <div class="section-card container-card">
        <div class="flex items-start">
          <div class="section-icon text-primary mr-4">
            <i class="fas fa-flag-checkered text-xl"></i>
          </div>
          <div class="flex-1">
            <h2 class="text-lg font-semibold text-text mb-3">Conclusion</h2>
            <div class="section-content text-text/70 leading-relaxed">
              <?= nl2br(htmlspecialchars($report['conclusion'])) ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Event Images -->
      <?php if (!empty($images)): ?>
      <div class="container-card">
        <div class="flex items-center mb-6">
          <div class="section-icon text-primary mr-4">
            <i class="fas fa-images text-xl"></i>
          </div>
          <h2 class="text-xl font-semibold text-text">Event Gallery</h2>
        </div>
        <div class="image-grid grid">
          <?php foreach ($images as $img): ?>
            <div class="image-card">
              <div class="overflow-hidden">
                <img src="../uploads/reports/<?= htmlspecialchars($img) ?>" 
                     class="w-full h-48 object-cover image-hover cursor-pointer" 
                     onclick="window.open('../uploads/reports/<?= htmlspecialchars($img) ?>', '_blank')" 
                     alt="Event Image" />
              </div>
              <div class="p-4 bg-gray-50 border-t border-subtle">
                <button onclick="window.open('../uploads/reports/<?= htmlspecialchars($img) ?>', '_blank')"
                        class="text-primary text-sm font-medium hover:underline flex items-center gap-2">
                  <i class="fas fa-expand"></i>
                  View Full Image
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    // Simple animation for section cards when they come into view
    document.addEventListener('DOMContentLoaded', function() {
      const sections = document.querySelectorAll('.section-card');
      
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.style.opacity = 1;
            entry.target.style.transform = 'translateY(0)';
          }
        });
      }, { threshold: 0.1 });

      sections.forEach(section => {
        section.style.opacity = 0;
        section.style.transform = 'translateY(20px)';
        section.style.transition = 'all 0.4s ease-out';
        observer.observe(section);
      });
    });
  </script>
</body>
</html>