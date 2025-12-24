<?php
include '../auth/db.php';
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Fetch all events
$result = $conn->query("SELECT * FROM events ORDER BY event_date DESC");
?>
<?php include 'sidebar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Events | VMS</title>
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
    
    .event-card {
      background: white;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
      border: 1px solid #D5D8DC;
      transition: all 0.3s ease;
    }
    
    .event-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 30px rgba(93, 173, 226, 0.15);
    }
    
    .event-image {
      width: 100%;
      height: 200px;
      object-fit: cover;
    }
    
    .date-badge {
      background: rgba(255, 255, 255, 0.95);
      color: #1C2833;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .action-btn {
      padding: 8px 16px;
      border-radius: 8px;
      font-weight: 500;
      font-size: 0.875rem;
      transition: all 0.3s ease;
      cursor: pointer;
    }
    
    .btn-edit {
      color: #5DADE2;
      background: rgba(93, 173, 226, 0.1);
    }
    
    .btn-edit:hover {
      background: rgba(93, 173, 226, 0.2);
      color: #4A9CD6;
    }
    
    .btn-delete {
      color: #EF4444;
      background: rgba(239, 68, 68, 0.1);
    }
    
    .btn-delete:hover {
      background: rgba(239, 68, 68, 0.2);
      color: #DC2626;
    }
    
    .empty-state {
      background: white;
      border-radius: 16px;
      padding: 3rem 1.5rem;
      text-align: center;
      border: 1px solid #D5D8DC;
    }
    
    .empty-state i {
      font-size: 3rem;
      color: #D5D8DC;
      margin-bottom: 1rem;
    }
    
    .add-event-btn {
      background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
      color: white;
      padding: 12px 24px;
      border-radius: 12px;
      font-weight: 600;
      transition: all 0.3s ease;
      border: none;
      cursor: pointer;
    }
    
    .add-event-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(93, 173, 226, 0.3);
    }
    
    .info-item {
      display: flex;
      align-items: center;
      color: #64748B;
      font-size: 0.875rem;
      margin-bottom: 8px;
    }
    
    .info-item i {
      width: 16px;
      margin-right: 8px;
      color: #5DADE2;
    }
    
    /* Mobile First Responsive Design */
    @media (max-width: 640px) {
      .container {
        padding: 1rem;
      }
      
      .header-section {
        padding-top: 1rem;
        text-align: center;
      }
      
      .event-image {
        height: 350px;
      }
      
      .event-card {
        margin-bottom: 1.5rem;
      }
      
      .action-buttons {
        display: flex;
        justify-content: space-between;
        gap: 0.5rem;
      }
      
      .action-btn {
        flex: 1;
        text-align: center;
        padding: 10px 12px;
      }
    }
    
    @media (min-width: 641px) and (max-width: 768px) {
      .container {
        padding: 1.5rem;
      }
      
      .grid-cols-1 {
        grid-template-columns: repeat(2, 1fr);
      }
      
      .event-image {
        height: 180px;
      }
    }
    
    @media (min-width: 769px) {
      .container {
        padding: 2rem;
      }
      
      .grid-cols-1 {
        grid-template-columns: repeat(3, 1fr);
      }
      
      .event-image {
        height: 300px;
      }
      
      .header-section {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
      }
    }
    
    @media (min-width: 1024px) {
      .container {
        max-width: 1200px;
        margin: 0 auto;
      }
      
      .event-image {
        height: 350px;
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
  <div class="container">
<!-- Header Section -->
<div class="header-section flex flex-col sm:flex-row justify-between items-center sm:items-start mb-8">
  <div class="mb-4 sm:mb-0 mt-12 sm:mt-0 text-center sm:text-left w-full sm:w-auto">
    <h1 class="text-2xl sm:text-3xl font-bold text-text">Event Management</h1>
    <p class="text-text/70 mt-2">Manage all upcoming and past events</p>
  </div>
  <a href="add_event.php" class="add-event-btn inline-flex items-center justify-center sm:justify-start mt-4 sm:mt-0">
    <i class="fas fa-plus mr-2"></i>
    Add New Event
  </a>
</div>


    <?php if ($result->num_rows === 0): ?>
      <!-- Empty State -->
      <div class="empty-state">
        <i class="fas fa-calendar-times"></i>
        <h3 class="text-xl font-bold text-text mb-2">No Events Found</h3>
        <p class="text-text/70 mb-6 max-w-md mx-auto">Get started by creating your first event to engage with volunteers.</p>
        <a href="add_event.php" class="add-event-btn inline-flex items-center">
          <i class="fas fa-plus mr-2"></i>
          Create First Event
        </a>
      </div>
    <?php else: ?>
      <!-- Events Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php while($row = $result->fetch_assoc()): ?>
          <div class="event-card">
            <!-- Event Image -->
            <div class="relative">
              <img src="../uploads/<?= htmlspecialchars($row['image_path']) ?>" 
                   alt="<?= htmlspecialchars($row['title']) ?>" 
                   class="event-image">
              <div class="absolute bottom-3 left-3">
                <span class="date-badge">
                  <i class="fas fa-calendar mr-1 text-primary"></i>
                  <?= date('M j, Y', strtotime($row['event_date'])) ?>
                </span>
              </div>
            </div>
            
            <!-- Event Content -->
            <div class="p-5">
              <h3 class="text-lg font-semibold text-text mb-3"><?= htmlspecialchars($row['title']) ?></h3>
              
              <p class="text-text/70 text-sm mb-4 line-clamp-2"><?= nl2br(htmlspecialchars($row['description'])) ?></p>
              
<!-- Event Details -->
<div class="space-y-2 mb-4">
    <?php if (!empty($row['location'])): ?>
        <div class="info-item">
            <i class="fas fa-map-marker-alt"></i>
            <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($row['location']) ?>" 
               target="_blank" 
               class="text-accent hover:text-accent/80 transition-colors">
                <?= htmlspecialchars($row['location']) ?>
            </a>
        </div>
    <?php endif; ?>

    <?php if (!empty($row['registration_link'])): ?>
        <div class="info-item">
            <i class="fas fa-link text-accent"></i>
            <a href="<?= htmlspecialchars($row['registration_link']) ?>" 
               target="_blank" 
               class="text-accent hover:text-accent/80 transition-colors">
                Registration Available
            </a>
        </div>
    <?php endif; ?>
</div>


              <!-- Action Buttons -->
              <div class="action-buttons flex justify-between border-t border-subtle pt-4">
                <a href="edit_event.php?id=<?= $row['event_id'] ?>" 
                   class="action-btn btn-edit">
                  <i class="fas fa-edit mr-2"></i>
                  Edit
                </a>
                <a href="delete_event.php?id=<?= $row['event_id'] ?>" 
                   onclick="return confirm('Are you sure you want to delete this event?');" 
                   class="action-btn btn-delete">
                  <i class="fas fa-trash mr-2"></i>
                  Delete
                </a>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>