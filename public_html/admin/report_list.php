<?php
session_start();
require_once '../auth/db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Fetch all reports
$reports = $conn->query("SELECT * FROM event_reports ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Event Reports | VMS</title>
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
</head>
<body class="bg-background font-sans antialiased">
  <?php include 'sidebar.php'; ?>

  <div class="min-h-screen">
    <div class="page-container">
      <!-- Header Section -->
     <div class="header-section mb-8 mt-12 sm:mt-0">
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
      <h1 class="text-2xl md:text-3xl font-bold text-text mb-2">Event Reports</h1>
      <p class="text-text/70">Manage all event reports in one place</p>
    </div>
    <a href="create_report.php" class="create-report-btn inline-flex items-center justify-center">
      <i class="fas fa-plus mr-2"></i>
      New Report
    </a>
  </div>
</div>


      <?php if ($reports->num_rows > 0): ?>
        <!-- Desktop Table View -->
        <div class="reports-table hidden md:block">
          <div class="container-card">
            <table class="w-full">
              <thead>
                <tr class="border-b border-subtle">
                  <th class="text-left py-4 px-6 text-text/70 font-semibold">Event Name</th>
                  <th class="text-left py-4 px-6 text-text/70 font-semibold">Date & Time</th>
                  <th class="text-left py-4 px-6 text-text/70 font-semibold">Created</th>
                  <th class="text-right py-4 px-6 text-text/70 font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($row = $reports->fetch_assoc()): ?>
                  <tr class="report-row border-b border-subtle hover:bg-gray-50/50 transition-colors">
                    <td class="py-4 px-6">
                      <div class="font-semibold text-text"><?= htmlspecialchars($row['event_name']) ?></div>
                    </td>
                    <td class="py-4 px-6 text-text/70">
                      <?= date('M d, Y H:i', strtotime($row['date_time'])) ?>
                    </td>
                    <td class="py-4 px-6 text-text/60">
                      <?= date('M d, Y', strtotime($row['created_at'])) ?>
                    </td>
                    <td class="py-4 px-6 text-right">
                      <div class="flex items-center justify-end gap-3">
                        <a href="view_report.php?report_id=<?= $row['id'] ?>" class="action-btn view-btn" title="View Report">
                          <i class="fas fa-eye"></i>
                        </a>
                        <a href="export_pdf.php?id=<?= $row['id'] ?>" class="action-btn download-btn" title="Download PDF">
                          <i class="fas fa-download"></i>
                        </a>
                        <a href="delete_report.php?id=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete this report?')" class="action-btn delete-btn" title="Delete Report">
                          <i class="fas fa-trash-alt"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Mobile Card View -->
        <div class="reports-grid md:hidden space-y-4">
          <?php 
          // Reset pointer for mobile view
          $reports->data_seek(0);
          while ($row = $reports->fetch_assoc()): ?>
            <div class="report-card container-card">
              <div class="report-header mb-4">
                <h3 class="text-lg font-semibold text-text mb-2"><?= htmlspecialchars($row['event_name']) ?></h3>
                <div class="space-y-1 text-sm">
                  <div class="flex items-center text-text/70">
                    <i class="fas fa-calendar mr-2 text-primary"></i>
                    <?= date('M d, Y H:i', strtotime($row['date_time'])) ?>
                  </div>
                  <div class="flex items-center text-text/60">
                    <i class="fas fa-clock mr-2 text-accent"></i>
                    Created: <?= date('M d, Y', strtotime($row['created_at'])) ?>
                  </div>
                </div>
              </div>
              
              <div class="action-buttons flex justify-between pt-4 border-t border-subtle">
                <a href="view_report.php?report_id=<?= $row['id'] ?>" class="mobile-action-btn view-btn">
                  <i class="fas fa-eye mr-1"></i>
                  View
                </a>
                <a href="export_pdf.php?id=<?= $row['id'] ?>" class="mobile-action-btn download-btn">
                  <i class="fas fa-download mr-1"></i>
                  PDF
                </a>
                <a href="delete_report.php?id=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete this report?')" class="mobile-action-btn delete-btn">
                  <i class="fas fa-trash-alt mr-1"></i>
                  Delete
                </a>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
      <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state container-card text-center">
          <div class="empty-icon mb-4">
            <i class="fas fa-file-alt text-4xl text-subtle"></i>
          </div>
          <h3 class="text-xl font-bold text-text mb-2">No Reports Available</h3>
          <p class="text-text/70 mb-6 max-w-md mx-auto">Get started by creating your first event report to track and analyze event data.</p>
          <a href="create_report.php" class="create-report-btn inline-flex items-center justify-center">
            <i class="fas fa-plus mr-2"></i>
            Create First Report
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>

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
      padding: 1.5rem;
    }
    
    .report-card {
      transition: all 0.3s ease;
    }
    
    .report-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 30px rgba(93, 173, 226, 0.15);
    }
    
    .report-row:hover {
      background: rgba(93, 173, 226, 0.03) !important;
    }
    
    .create-report-btn {
      background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
      color: white;
      padding: 12px 24px;
      border-radius: 12px;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    
    .create-report-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(93, 173, 226, 0.3);
    }
    
    .action-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 8px;
      transition: all 0.3s ease;
    }
    
    .view-btn {
      background: rgba(93, 173, 226, 0.1);
      color: #5DADE2;
    }
    
    .view-btn:hover {
      background: rgba(93, 173, 226, 0.2);
      transform: translateY(-1px);
    }
    
    .download-btn {
      background: rgba(16, 185, 129, 0.1);
      color: #10b981;
    }
    
    .download-btn:hover {
      background: rgba(16, 185, 129, 0.2);
      transform: translateY(-1px);
    }
    
    .delete-btn {
      background: rgba(239, 68, 68, 0.1);
      color: #ef4444;
    }
    
    .delete-btn:hover {
      background: rgba(239, 68, 68, 0.2);
      transform: translateY(-1px);
    }
    
    .mobile-action-btn {
      display: inline-flex;
      align-items: center;
      padding: 8px 16px;
      border-radius: 8px;
      font-weight: 500;
      font-size: 0.875rem;
      transition: all 0.3s ease;
    }
    
    .empty-state {
      padding: 3rem 1.5rem;
    }
    
    .empty-icon {
      color: #D5D8DC;
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
      }
      
      .reports-grid {
        display: grid;
        gap: 1rem;
      }
      
      .report-header {
        margin-bottom: 1rem;
      }
      
      .action-buttons {
        display: flex;
        justify-content: space-between;
        gap: 0.5rem;
      }
      
      .mobile-action-btn {
        flex: 1;
        text-align: center;
        justify-content: center;
        font-size: 0.8rem;
        padding: 8px 12px;
      }
    }
    
    @media (min-width: 641px) and (max-width: 768px) {
      .page-container {
        padding: 1.5rem;
      }
      
      .container-card {
        padding: 2rem;
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
    }
    
    /* Ensure proper sidebar spacing */
    @media (min-width: 768px) {
      body {
        margin-left: 16rem;
      }
    }
  </style>
</body>
</html>