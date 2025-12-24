<?php
include '../auth/db.php';
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}
$response = "";

// Check if a specific event is selected
if (isset($_GET['event_id']) && is_numeric($_GET['event_id'])) {
    $event_id = $_GET['event_id'];

    // Fetch event details
    $eventStmt = $conn->prepare("SELECT * FROM events WHERE event_id = ?");
    $eventStmt->bind_param("i", $event_id);
    $eventStmt->execute();
    $eventResult = $eventStmt->get_result();

    if ($eventResult->num_rows === 0) {
        die("Event not found.");
    }

    $event = $eventResult->fetch_assoc();

    // Fetch volunteers
    $volunteers = $conn->query("SELECT * FROM volunteers ORDER BY full_name");

    // Process form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['attendance'])) {
        $submitted = $_POST['attendance'];

        foreach ($submitted as $volunteer_id_fk => $status) {
            $stmt = $conn->prepare("
                INSERT INTO event_attendance (event_id_fk, volunteer_id_fk, status)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), marked_at = CURRENT_TIMESTAMP
            ");
            $stmt->bind_param("iis", $event_id, $volunteer_id_fk, $status);
            $stmt->execute();
        }

        $response = '<div class="success-message">
                        <i class="fas fa-check-circle mr-2"></i>
                        Attendance records updated successfully
                    </div>';
    }

    // Fetch existing attendance data
    $attendanceData = [];
    $result = $conn->query("SELECT volunteer_id_fk, status FROM event_attendance WHERE event_id_fk = $event_id");
    while ($row = $result->fetch_assoc()) {
        $attendanceData[$row['volunteer_id_fk']] = $row['status'];
    }
} else {
    // No event selected: fetch all events
    $allEvents = $conn->query("SELECT * FROM events ORDER BY event_date DESC");
}
?>
<?php include 'sidebar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mark Attendance | VMS</title>
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
      background: white;
      border-radius: 12px;
      border: 1px solid #D5D8DC;
      transition: all 0.3s ease;
      padding: 1rem;
    }
    
    .attendance-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(93, 173, 226, 0.15);
    }
    
    .present-row {
      background: rgba(93, 173, 226, 0.05);
      border-left: 4px solid #5DADE2;
    }
    
    .absent-row {
      background: rgba(165, 105, 189, 0.05);
      border-left: 4px solid #A569BD;
    }
    
    .status-btn {
      padding: 8px 16px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.875rem;
      transition: all 0.3s ease;
      cursor: pointer;
      border: 1px solid #D5D8DC;
      min-width: 100px;
    }
    
    .present-btn {
      background: #5DADE2;
      color: white;
      border-color: #5DADE2;
    }
    
    .present-btn:hover {
      background: #4A9CD6;
      transform: translateY(-1px);
    }
    
    .present-btn.inactive {
      background: rgba(93, 173, 226, 0.1);
      color: #5DADE2;
      border-color: #5DADE2;
    }
    
    .absent-btn {
      background: #A569BD;
      color: white;
      border-color: #A569BD;
    }
    
    .absent-btn:hover {
      background: #9457A8;
      transform: translateY(-1px);
    }
    
    .absent-btn.inactive {
      background: rgba(165, 105, 189, 0.1);
      color: #A569BD;
      border-color: #A569BD;
    }
    
    .success-message {
      background: rgba(16, 185, 129, 0.1);
      border: 1px solid rgba(16, 185, 129, 0.3);
      color: #065f46;
      border-radius: 10px;
      padding: 12px 16px;
      margin-bottom: 1.5rem;
    }
    
    .back-btn {
      background: white;
      color: #1C2833;
      border: 1px solid #D5D8DC;
      padding: 10px 20px;
      border-radius: 10px;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    
    .back-btn:hover {
      background: rgba(213, 216, 220, 0.3);
      transform: translateY(-1px);
    }
    
    .submit-btn {
      background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
      color: white;
      padding: 12px 24px;
      border-radius: 12px;
      font-weight: 600;
      border: none;
      cursor: pointer;
      transition: all 0.3s ease;
      width: 100%;
    }
    
    .submit-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(93, 173, 226, 0.3);
    }
    
    .event-link {
      color: #5DADE2;
      font-weight: 600;
      padding: 8px 16px;
      border-radius: 8px;
      transition: all 0.3s ease;
    }
    
    .event-link:hover {
      background: rgba(93, 173, 226, 0.1);
      color: #4A9CD6;
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
      
      .volunteer-info {
        flex-direction: column;
        align-items: flex-start;
        margin-bottom: 1rem;
      }
      
      .volunteer-image {
        margin-bottom: 0.75rem;
      }
      
      .status-btns {
        flex-direction: row;
        justify-content: space-between;
        width: 100%;
      }
      
      .status-btn {
        flex: 1;
        margin: 0 0.25rem;
        text-align: center;
      }
      
      .event-table {
        overflow-x: auto;
      }
    }
    
    @media (min-width: 641px) and (max-width: 768px) {
      .page-container {
        padding: 1.5rem;
      }
      
      .container-card {
        padding: 2rem;
      }
      
      .volunteer-info {
        flex-direction: row;
        align-items: center;
      }
      
      .status-btns {
        flex-direction: row;
        gap: 0.5rem;
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
      
      .submit-btn {
        width: auto;
        min-width: 200px;
      }
      
      .button-container {
        text-align: right;
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
    <?php if (!isset($event)): ?>
      <!-- Event Selection View -->
      <div class="container-card">
        <div class="header-section mb-8 mt-8 sm:mt-0 text-center sm:text-left">
  <div>
    <h1 class="text-2xl sm:text-3xl font-bold text-text mb-2">Mark Attendance</h1>
    <p class="text-text/70">Select an event to mark volunteer attendance</p>
  </div>
</div>


        <?php if ($allEvents->num_rows > 0): ?>
          <div class="event-table overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="border-b border-subtle">
                  <th class="text-left py-3 px-4 text-text/70 font-semibold">Event Title</th>
                  <th class="text-left py-3 px-4 text-text/70 font-semibold">Date</th>
                  <th class="text-right py-3 px-4 text-text/70 font-semibold">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($ev = $allEvents->fetch_assoc()): ?>
                  <tr class="border-b border-subtle hover:bg-gray-50/50 transition-colors">
                    <td class="py-4 px-4">
                      <div class="font-medium text-text"><?= htmlspecialchars($ev['title']) ?></div>
                    </td>
                    <td class="py-4 px-4 text-text/70">
                      <?= date('d M Y', strtotime($ev['event_date'])) ?>
                    </td>
                   <td class="py-4 px-4 text-right">
  <a href="attendance_mark.php?event_id=<?= $ev['event_id'] ?>" class="event-link inline-flex items-center">
    <i class="fas fa-clipboard-check"></i>
    <span class="ml-2 hidden sm:inline">Mark Attendance</span>
  </a>
</td>

                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <!-- Empty State -->
          <div class="text-center py-12">
            <i class="fas fa-calendar-times text-6xl text-subtle mb-4"></i>
            <h3 class="text-xl font-bold text-text mb-2">No Events Available</h3>
            <p class="text-text/70 mb-6 max-w-md mx-auto">Create an event first to start marking attendance for volunteers.</p>
            <a href="add_event.php" class="submit-btn inline-flex items-center justify-center">
              <i class="fas fa-plus mr-2"></i>
              Create Event
            </a>
          </div>
        <?php endif; ?>
      </div>

    <?php else: ?>
      <!-- Attendance Marking View -->
<div class="container-card">
  <div class="header-section mb-8 mt-6 sm:mt-0 text-center sm:text-left">
    <div>
      <h1 class="text-2xl sm:text-3xl font-bold text-text mb-2">Mark Attendance</h1>
      <div class="space-y-1">
        <p class="text-text"><span class="font-semibold">Event:</span> <?= htmlspecialchars($event['title']) ?></p>
        <p class="text-text"><span class="font-semibold">Date:</span> <?= date('d M Y', strtotime($event['event_date'])) ?></p>
      </div>
    </div>
          <div class="mt-4 md:mt-0">
            <a href="attendance_mark.php" class="back-btn inline-flex items-center">
              <i class="fas fa-arrow-left mr-2"></i>
              Back to Events
            </a>
          </div>
        </div>

        <?= $response ?>

        <form method="POST">
          <div class="space-y-4">
            <?php while ($vol = $volunteers->fetch_assoc()): 
              $status = isset($attendanceData[$vol['id']]) ? $attendanceData[$vol['id']] : '';
              $rowClass = $status === 'Present' ? 'present-row' : ($status === 'Absent' ? 'absent-row' : '');
            ?>
              <div class="attendance-card <?= $rowClass ?>">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                  <!-- Volunteer Info -->
                  <div class="flex items-center volunteer-info">
                    <div class="volunteer-image h-12 w-12 flex-shrink-0">
                      <img class="h-12 w-12 rounded-full object-cover border-2 border-white shadow-sm" 
                           src="../<?= htmlspecialchars($vol['profile_image'] ?: 'uploads/default.jpg') ?>" 
                           alt="<?= htmlspecialchars($vol['full_name']) ?>">
                    </div>
                    <div class="ml-4 min-w-0">
                      <div class="font-semibold text-text"><?= htmlspecialchars($vol['full_name']) ?></div>
                      <div class="text-text/60 text-sm"><?= htmlspecialchars($vol['email']) ?></div>
                      <?php if (!empty($vol['position'])): ?>
                        <div class="text-xs text-accent font-medium mt-1"><?= htmlspecialchars($vol['position']) ?></div>
                      <?php endif; ?>
                    </div>
                  </div>
                  
                  <!-- Attendance Buttons -->
                  <div class="flex status-btns mt-3 sm:mt-0">
                    <input type="hidden" name="attendance[<?= $vol['id'] ?>]" id="attendance_<?= $vol['id'] ?>" value="<?= $status ?>">
                    
                    <button type="button" 
                      onclick="setAttendance(<?= $vol['id'] ?>, 'Present')"
                      class="status-btn present-btn mr-2 <?= $status === 'Present' ? '' : 'inactive' ?>"
                    >
                      <i class="fas fa-check mr-1"></i> Present
                    </button>
                    
                    <button type="button" 
                      onclick="setAttendance(<?= $vol['id'] ?>, 'Absent')"
                      class="status-btn absent-btn <?= $status === 'Absent' ? '' : 'inactive' ?>"
                    >
                      <i class="fas fa-times mr-1"></i> Absent
                    </button>
                  </div>
                </div>
              </div>
            <?php endwhile; ?>
          </div>

          <!-- Submit Button -->
          <div class="mt-8 pt-6 border-t border-subtle button-container">
            <button type="submit" class="submit-btn inline-flex items-center justify-center">
              <i class="fas fa-save mr-2"></i>
              Save Attendance Records
            </button>
          </div>
        </form>

        <script>
          function setAttendance(volunteerId, status) {
            // Update the hidden input value
            document.getElementById('attendance_' + volunteerId).value = status;
            
            // Update button styles
            const presentBtn = document.querySelector(`button[onclick="setAttendance(${volunteerId}, 'Present')"]`);
            const absentBtn = document.querySelector(`button[onclick="setAttendance(${volunteerId}, 'Absent')"]`);
            const row = presentBtn.closest('.attendance-card');
            
            // Reset all buttons and row styles first
            presentBtn.classList.remove('present-btn', 'inactive');
            absentBtn.classList.remove('absent-btn', 'inactive');
            row.classList.remove('present-row', 'absent-row');
            
            if (status === 'Present') {
              presentBtn.classList.add('present-btn');
              absentBtn.classList.add('inactive');
              row.classList.add('present-row');
            } else if (status === 'Absent') {
              absentBtn.classList.add('absent-btn');
              presentBtn.classList.add('inactive');
              row.classList.add('absent-row');
            }
          }
        </script>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>