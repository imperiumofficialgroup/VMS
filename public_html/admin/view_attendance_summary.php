<?php
include '../auth/db.php';
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Fetch all events with attendance counts in a single query
$query = "SELECT 
            e.event_id, 
            e.title, 
            e.event_date,
            COALESCE(SUM(CASE WHEN ea.status = 'Present' THEN 1 ELSE 0 END), 0) AS present_count,
            COALESCE(SUM(CASE WHEN ea.status = 'Absent' THEN 1 ELSE 0 END), 0) AS absent_count,
            COUNT(ea.status) AS total_marked
          FROM events e
          LEFT JOIN event_attendance ea ON e.event_id = ea.event_id_fk
          GROUP BY e.event_id
          ORDER BY e.event_date DESC";
$events = $conn->query($query);
?>
<?php include 'sidebar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Attendance Summary | VMS</title>
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
    
    .container-card {
      background: white;
      border-radius: 16px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
      border: 1px solid #D5D8DC;
    }
    
    .attendance-card {
      transition: all 0.3s ease;
      border-bottom: 1px solid #D5D8DC;
    }
    
    .attendance-card:hover {
      background: rgba(93, 173, 226, 0.03);
      transform: translateY(-1px);
    }
    
    .progress-bar {
      height: 8px;
      border-radius: 4px;
      background: rgba(213, 216, 220, 0.5);
      overflow: hidden;
    }
    
    .progress-fill {
      height: 100%;
      transition: width 0.6s ease;
      background: linear-gradient(90deg, #5DADE2 0%, #A569BD 100%);
    }
    
    .present-badge {
      background: rgba(93, 173, 226, 0.1);
      color: #5DADE2;
      border: 1px solid rgba(93, 173, 226, 0.3);
    }
    
    .absent-badge {
      background: rgba(165, 105, 189, 0.1);
      color: #A569BD;
      border: 1px solid rgba(165, 105, 189, 0.3);
    }
    
    .action-btn {
      background: #5DADE2;
      color: white;
      padding: 6px 12px;
      border-radius: 8px;
      font-weight: 500;
      font-size: 0.875rem;
      transition: all 0.3s ease;
    }
    
    .action-btn:hover {
      background: #4A9CD6;
      transform: translateY(-1px);
    }
    
    .empty-state {
      text-align: center;
      padding: 3rem 1.5rem;
    }
    
    .empty-state i {
      font-size: 4rem;
      color: #D5D8DC;
      margin-bottom: 1rem;
    }
    
    .mark-attendance-btn {
      background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
      color: white;
      padding: 12px 24px;
      border-radius: 12px;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    
    .mark-attendance-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(93, 173, 226, 0.3);
    }
    
    /* Mobile First Responsive Design */
    @media (max-width: 640px) {
      .page-container {
        padding: 1rem;
      }
      
      .container-card {
        padding: 1.5rem;
      }
      
      .header-section {
        text-align: center;
        margin-bottom: 2rem;
      }
      
      .attendance-table {
        overflow-x: auto;
      }
      
      .table-row {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
        padding: 1.5rem 0;
        border-bottom: 1px solid #D5D8DC;
      }
      
      .table-row:last-child {
        border-bottom: none;
      }
      
      .event-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: #1C2833;
      }
      
      .event-date {
        color: #64748B;
        font-size: 0.875rem;
      }
      
      .attendance-stats {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
      }
      
      .progress-container {
        width: 100%;
      }
      
      .badges-container {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
      }
      
      .action-container {
        text-align: left;
      }
    }
    
    @media (min-width: 641px) and (max-width: 768px) {
      .page-container {
        padding: 1.5rem;
      }
      
      .container-card {
        padding: 2rem;
      }
      
      .table-row {
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
      }
      
      .event-info {
        grid-column: 1 / -1;
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
      
      .attendance-table {
        display: table;
        width: 100%;
        border-collapse: collapse;
      }
      
      .table-row {
        display: table-row;
      }
      
      .table-cell {
        display: table-cell;
        padding: 1rem 1.5rem;
        vertical-align: middle;
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
  <div class="page-container">
    <div class="container-card">
      <!-- Header Section -->
      <div class="header-section mb-8 flex flex-col md:flex-row md:items-center md:justify-between">
    <div class="mb-4 md:mb-0 mt-12 md:mt-0 text-center md:text-left">
        <h1 class="text-2xl md:text-3xl font-bold text-text mb-2">Attendance Summary</h1>
        <p class="text-text/70">Overview of volunteer attendance across all events</p>
    </div>
    <a href="attendance_mark.php" class="mark-attendance-btn inline-flex items-center">
        <i class="fas fa-clipboard-check mr-2"></i>
        Mark Attendance
    </a>
</div>


      <?php if ($events->num_rows > 0): ?>
        <!-- Desktop Table View -->
        <div class="attendance-table hidden md:block">
          <table class="w-full">
            <thead>
              <tr class="border-b border-subtle">
                <th class="text-left py-4 px-6 text-text/70 font-semibold">Event</th>
                <th class="text-left py-4 px-6 text-text/70 font-semibold">Date</th>
                <th class="text-left py-4 px-6 text-text/70 font-semibold">Attendance Rate</th>
                <th class="text-left py-4 px-6 text-text/70 font-semibold">Details</th>
                <th class="text-right py-4 px-6 text-text/70 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($event = $events->fetch_assoc()): 
                $present = $event['present_count'];
                $absent = $event['absent_count'];
                $total = $event['total_marked'];
                $attendance_rate = $total > 0 ? round(($present / $total) * 100) : 0;
              ?>
                <tr class="attendance-card">
                  <td class="table-cell">
                    <div class="font-semibold text-text"><?= htmlspecialchars($event['title']) ?></div>
                  </td>
                  <td class="table-cell text-text/70">
                    <?= date('d M Y', strtotime($event['event_date'])) ?>
                  </td>
                  <td class="table-cell">
                    <div class="flex items-center space-x-3">
                      <div class="progress-container w-24">
                        <div class="progress-bar">
                          <div class="progress-fill" style="width: <?= $attendance_rate ?>%"></div>
                        </div>
                      </div>
                      <span class="text-sm font-semibold text-text"><?= $attendance_rate ?>%</span>
                    </div>
                    <div class="text-xs text-text/60 mt-1">
                      <?= $present ?> present • <?= $absent ?> absent
                    </div>
                  </td>
                  <td class="table-cell">
                    <div class="flex space-x-2">
                      <span class="present-badge inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium">
                        <i class="fas fa-check mr-1"></i>
                        <?= $present ?>
                      </span>
                      <span class="absent-badge inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium">
                        <i class="fas fa-times mr-1"></i>
                        <?= $absent ?>
                      </span>
                    </div>
                  </td>
                  <td class="table-cell text-right">
                    <a href="attendance_mark.php?event_id=<?= $event['event_id'] ?>" class="action-btn inline-flex items-center">
                      <i class="fas fa-edit mr-1"></i>
                      Mark
                    </a>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>

        <!-- Mobile Card View -->
        <div class="space-y-4 md:hidden">
          <?php 
          // Reset pointer for mobile view
          $events->data_seek(0);
          while ($event = $events->fetch_assoc()): 
            $present = $event['present_count'];
            $absent = $event['absent_count'];
            $total = $event['total_marked'];
            $attendance_rate = $total > 0 ? round(($present / $total) * 100) : 0;
          ?>
            <div class="table-row">
              <div class="event-info">
                <div class="event-title"><?= htmlspecialchars($event['title']) ?></div>
                <div class="event-date"><?= date('d M Y', strtotime($event['event_date'])) ?></div>
              </div>
              
              <div class="attendance-stats">
                <div class="progress-container">
                  <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-semibold text-text">Attendance: <?= $attendance_rate ?>%</span>
                    <span class="text-xs text-text/60"><?= $present ?>/<?= $total ?></span>
                  </div>
                  <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $attendance_rate ?>%"></div>
                  </div>
                </div>
                
                <div class="badges-container">
                  <span class="present-badge inline-flex items-center px-3 py-1 rounded-full text-sm font-medium">
                    <i class="fas fa-check mr-1"></i>
                    <?= $present ?> Present
                  </span>
                  <span class="absent-badge inline-flex items-center px-3 py-1 rounded-full text-sm font-medium">
                    <i class="fas fa-times mr-1"></i>
                    <?= $absent ?> Absent
                  </span>
                </div>
              </div>
              
              <div class="action-container">
                <a href="attendance_mark.php?event_id=<?= $event['event_id'] ?>" class="action-btn inline-flex items-center">
                  <i class="fas fa-edit mr-2"></i>
                  Update Attendance
                </a>
              </div>
            </div>
          <?php endwhile; ?>
        </div>

      <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state">
          <i class="fas fa-clipboard-list"></i>
          <h3 class="text-xl font-bold text-text mb-2">No Attendance Records</h3>
          <p class="text-text/70 mb-6 max-w-md mx-auto">Start marking attendance for events to see the summary data here.</p>
          <a href="attendance_mark.php" class="mark-attendance-btn inline-flex items-center">
            <i class="fas fa-clipboard-check mr-2"></i>
            Mark First Attendance
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>