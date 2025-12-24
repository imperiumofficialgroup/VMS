<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}
require_once '../auth/db.php';

// Fetch volunteers with their latest subscription info
$query = "
    SELECT v.*, s.start_date, s.end_date, s.status
    FROM volunteers v
    LEFT JOIN volunteer_subscriptions s 
        ON v.id = s.volunteer_id
    ORDER BY v.created_at DESC
";
$result = $conn->query($query);

// Process data for filtering
$volunteers = [];
$statusCounts = ['all' => 0, 'active' => 0, 'inactive' => 0, 'pending' => 0];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $status = !empty($row['status']) ? strtolower($row['status']) : 'pending';
        $row['display_status'] = $status;

        $volunteers[] = $row;
        $statusCounts['all']++;
        $statusCounts[$status]++;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Volunteer Database | VMS</title>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
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
    
    .volunteer-card {
      position: relative;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      min-height: 400px;
      background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
    }
    
    .volunteer-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 15px 35px rgba(93, 173, 226, 0.2);
    }
    
    .card-image {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      z-index: 1;
    }
    
    .card-overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(
        to bottom,
        rgba(28, 40, 51, 0.1) 0%,
        rgba(28, 40, 51, 0.3) 30%,
        rgba(28, 40, 51, 0.7) 70%,
        rgba(28, 40, 51, 0.9) 100%
      );
      z-index: 2;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 20px;
    }
    
    .card-content {
      position: relative;
      z-index: 3;
      color: white;
    }
    
    .id-badge {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 20px;
      padding: 6px 12px;
      font-size: 0.75rem;
      font-weight: 600;
      color: #5DADE2;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .status-badge {
      display: inline-flex;
      align-items: center;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .status-active {
      background: rgba(93, 173, 226, 0.9);
      color: white;
    }
    
    .status-inactive {
      background: rgba(165, 105, 189, 0.9);
      color: white;
    }
    
    .status-pending {
      background: rgba(255, 255, 255, 0.9);
      color: #1C2833;
    }
    
    .action-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 10px 16px;
      border-radius: 10px;
      font-weight: 600;
      font-size: 0.875rem;
      transition: all 0.3s ease;
      cursor: pointer;
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      flex: 1;
    }
    
    .btn-primary {
      background: rgba(93, 173, 226, 0.9);
      color: white;
    }
    
    .btn-primary:hover {
      background: rgba(93, 173, 226, 1);
      transform: translateY(-2px);
    }
    
    .btn-danger {
      background: rgba(239, 68, 68, 0.9);
      color: white;
    }
    
    .btn-danger:hover {
      background: rgba(239, 68, 68, 1);
      transform: translateY(-2px);
    }
    
    .search-container {
      position: relative;
      max-width: 500px;
      margin: 0 auto 1.5rem;
    }
    
    .search-input {
      width: 100%;
      padding: 14px 20px 14px 50px;
      border-radius: 50px;
      border: 1px solid #D5D8DC;
      background-color: white;
      font-size: 1rem;
      transition: all 0.3s ease;
      font-family: 'Inter', sans-serif;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }
    
    .search-input:focus {
      outline: none;
      border-color: #5DADE2;
      box-shadow: 0 0 0 3px rgba(93, 173, 226, 0.1), 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    .search-icon {
      position: absolute;
      left: 18px;
      top: 50%;
      transform: translateY(-50%);
      color: #64748B;
      z-index: 10;
    }
    
    .filter-container {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 2rem;
      justify-content: center;
      align-items: center;
    }
    
    .filter-btn {
      padding: 10px 20px;
      border-radius: 25px;
      background-color: white;
      border: 2px solid #D5D8DC;
      font-size: 0.875rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      font-family: 'Inter', sans-serif;
      display: flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }
    
    .filter-btn.active {
      background-color: #5DADE2;
      color: white;
      border-color: #5DADE2;
      box-shadow: 0 4px 15px rgba(93, 173, 226, 0.3);
    }
    
    .filter-btn:hover:not(.active) {
      background-color: rgba(93, 173, 226, 0.1);
      border-color: #5DADE2;
      transform: translateY(-2px);
    }
    
    .count-badge {
      background: rgba(255, 255, 255, 0.2);
      color: inherit;
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 0.75rem;
      font-weight: 700;
    }
    
    .filter-btn.active .count-badge {
      background: rgba(255, 255, 255, 0.3);
    }
    
    .empty-state {
      text-align: center;
      padding: 4rem 1rem;
      color: #64748B;
      grid-column: 1 / -1;
    }
    
    .empty-state i {
      font-size: 4rem;
      margin-bottom: 1.5rem;
      color: #D5D8DC;
    }
    
    .info-item {
      display: flex;
      align-items: center;
      margin-bottom: 8px;
      font-size: 0.9rem;
      opacity: 0.9;
    }
    
    .info-item i {
      width: 20px;
      margin-right: 10px;
      text-align: center;
    }
    
    .action-buttons {
      display: flex;
      gap: 10px;
      margin-top: 15px;
      width: 100%;
    }
    
    /* Mobile responsive improvements */
    @media (max-width: 768px) {
      .volunteer-card {
        min-height: 350px;
      }
      
      .card-overlay {
        padding: 15px;
      }
      
      .filter-container {
        gap: 6px;
        margin-bottom: 1.5rem;
      }
      
      .filter-btn {
        padding: 8px 16px;
        font-size: 0.8rem;
        flex: 1;
        min-width: calc(50% - 12px);
        justify-content: center;
      }
      
      .search-input {
        padding: 12px 20px 12px 45px;
        font-size: 0.9rem;
      }
      
      .action-buttons {
        gap: 8px;
      }
      
      .action-btn {
        padding: 12px 16px;
        font-size: 0.9rem;
      }
    }
    
    @media (max-width: 480px) {
      .volunteer-card {
        min-height: 320px;
      }
      
      .filter-btn {
        min-width: calc(100% - 12px);
        font-size: 0.85rem;
      }
      
      .card-overlay {
        padding: 12px;
      }
      
      .info-item {
        font-size: 0.85rem;
        margin-bottom: 6px;
      }
    }
    
    @media (max-width: 640px) {
      .grid-cols-1 {
        grid-template-columns: 1fr;
      }
      
      .grid-cols-2 {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body class="min-h-screen lg:ml-64 bg-background ">
  <!-- Include Sidebar -->
  <?php include 'sidebar.php'; ?>
  
  <div class="p-4 md:p-6">
    <!-- Header Section -->
<div class="mb-8 mt-16 sm:mt-0">
      <h1 class="text-2xl md:text-4xl font-bold text-center text-text mb-2">
        VOLUNTEER DATABASE
      </h1>
      <p class="text-text/70 text-center text-sm md:text-base tracking-wider">
        ACTIVE VOLUNTEER RECORDS | IMP SYSTEM
      </p>
    </div>
    
    <!-- Success Message -->
    <?php if (isset($_SESSION['message'])): ?>
      <div class="max-w-4xl mx-auto mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo $_SESSION['message']; ?>
          </div>
          <button onclick="this.parentElement.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
      <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    
    <!-- Search and Filter Section -->
    <div class="max-w-6xl mx-auto mb-8">
      <div class="search-container">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="searchInput" class="search-input" placeholder="Search volunteers by name, email, or position...">
      </div>
      
      <div class="filter-container">
        <button class="filter-btn active" data-filter="all">
          <i class="fas fa-users"></i>
          All Volunteers
          <span class="count-badge"><?php echo $statusCounts['all']; ?></span>
        </button>
        <button class="filter-btn" data-filter="active">
          <i class="fas fa-check-circle"></i>
          Active
          <span class="count-badge"><?php echo $statusCounts['active']; ?></span>
        </button>
        <button class="filter-btn" data-filter="inactive">
          <i class="fas fa-times-circle"></i>
          Inactive
          <span class="count-badge"><?php echo $statusCounts['inactive']; ?></span>
        </button>
        <button class="filter-btn" data-filter="pending">
          <i class="fas fa-clock"></i>
          Pending
          <span class="count-badge"><?php echo $statusCounts['pending']; ?></span>
        </button>
      </div>
    </div>
    
    <!-- Volunteer Cards Grid -->
    <div class="max-w-7xl mx-auto">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="volunteerGrid">
        <?php if (!empty($volunteers)): ?>
          <?php foreach ($volunteers as $row): ?>
          <div class="volunteer-card" 
               data-status="<?php echo $row['display_status']; ?>"
               data-name="<?php echo htmlspecialchars(strtolower($row['full_name'])); ?>"
               data-email="<?php echo htmlspecialchars(strtolower($row['email'])); ?>"
               data-position="<?php echo !empty($row['position']) ? htmlspecialchars(strtolower($row['position'])) : ''; ?>">
            
            <img src="../<?php echo htmlspecialchars($row['profile_image'] ?: 'uploads/default.jpg'); ?>" 
                 alt="Volunteer Profile" class="card-image">
            
            <div class="card-overlay">
              <!-- Top Section -->
              <div class="card-content">
                <div class="flex justify-between items-start mb-4">
                  <div class="id-badge">
                    ID: IMP-<?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?>
                  </div>
                  <div class="status-badge <?php echo 'status-' . $row['display_status']; ?>">
                    <?php echo ucfirst($row['display_status']); ?>
                  </div>
                </div>
                
                <h3 class="text-xl font-bold text-white mb-3">
                  <?php echo htmlspecialchars($row['full_name']); ?>
                </h3>
                
                <div class="space-y-2">
                  <div class="info-item">
                    <i class="fas fa-envelope text-primary"></i>
                    <?php echo htmlspecialchars($row['email']); ?>
                  </div>
                  
                  <div class="info-item">
                    <i class="fas fa-phone text-primary"></i>
                    <?php echo htmlspecialchars($row['phone'] ?: 'Not provided'); ?>
                  </div>
                  
                  <div class="info-item">
                    <i class="fas fa-map-marker-alt text-primary"></i>
                    <span class="truncate"><?php echo htmlspecialchars($row['address'] ?: 'Address not specified'); ?></span>
                  </div>

                  <?php if (!empty($row['position'])): ?>
                    <div class="info-item">
                      <i class="fas fa-user-tag text-accent"></i>
                      <span class="font-medium"><?php echo htmlspecialchars($row['position']); ?></span>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($row['start_date']) && !empty($row['end_date'])): ?>
                    <div class="info-item text-xs opacity-75">
                      <i class="fas fa-calendar text-primary"></i>
                      <?php echo date('d/m/Y', strtotime($row['start_date'])); ?> - <?php echo date('d/m/Y', strtotime($row['end_date'])); ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
              
              <!-- Bottom Action Buttons -->
              <div class="card-content">
               <div class="action-buttons flex items-center justify-between gap-3 mt-4">
  <!-- Edit Button -->
  <a href="edit_volunteer.php?id=<?php echo $row['id']; ?>" 
     class="flex items-center justify-center gap-2 action-btn btn-primary 
            flex-1 text-sm sm:text-base px-3 py-2 sm:px-4 sm:py-2 rounded-lg transition-all duration-200">
    <i class="fas fa-edit text-white"></i>
    <span class="hidden sm:inline">Edit</span>
  </a>

  <!-- Delete Button -->
  <form action="delete_volunteer.php" method="POST" 
        onsubmit="return confirm('Are you sure you want to delete this volunteer?');" 
        class="flex-1">
    <input type="hidden" name="volunteer_id" value="<?php echo $row['id']; ?>">
    <button type="submit" 
            class="w-full flex items-center justify-center gap-2 action-btn btn-danger 
                   text-sm sm:text-base px-3 py-2 sm:px-4 sm:py-2 rounded-lg transition-all duration-200">
      <i class="fas fa-trash text-white"></i>
      <span class="hidden sm:inline">Delete</span>
    </button>
  </form>
</div>

              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-state">
            <i class="fas fa-users"></i>
            <h3 class="text-2xl font-bold mb-3">No Volunteers Found</h3>
            <p class="max-w-md mx-auto text-lg">There are currently no volunteers in the database. Add some volunteers to get started.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    // Filter and Search Logic
    document.addEventListener('DOMContentLoaded', function() {
      const searchInput = document.getElementById('searchInput');
      const filterButtons = document.querySelectorAll('.filter-btn');
      const volunteerCards = document.querySelectorAll('.volunteer-card');
      
      // Filter functionality
      filterButtons.forEach(button => {
        button.addEventListener('click', function() {
          // Update active button
          filterButtons.forEach(btn => btn.classList.remove('active'));
          this.classList.add('active');
          
          const filter = this.getAttribute('data-filter');
          filterVolunteers(filter, searchInput.value.toLowerCase());
        });
      });
      
      // Search functionality
      searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const activeFilter = document.querySelector('.filter-btn.active').getAttribute('data-filter');
        filterVolunteers(activeFilter, searchTerm);
      });
      
      function filterVolunteers(statusFilter, searchTerm) {
        let visibleCount = 0;
        
        volunteerCards.forEach(card => {
          const status = card.getAttribute('data-status');
          const name = card.getAttribute('data-name');
          const email = card.getAttribute('data-email');
          const position = card.getAttribute('data-position');
          
          const statusMatch = statusFilter === 'all' || status === statusFilter;
          const searchMatch = !searchTerm || 
                            name.includes(searchTerm) || 
                            email.includes(searchTerm) || 
                            position.includes(searchTerm);
          
          if (statusMatch && searchMatch) {
            card.style.display = 'block';
            visibleCount++;
          } else {
            card.style.display = 'none';
          }
        });
        
        // Show empty state if no cards visible
        const emptyState = document.querySelector('.empty-state');
        if (emptyState) {
          emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
        }
      }
      
      // Initialize with all volunteers visible
      filterVolunteers('all', '');
    });
    
    // Mobile sidebar toggle
    function toggleSidebar() {
      const sidebar = document.querySelector('#sidebar');
      if (sidebar) {
        sidebar.classList.toggle('hidden');
        sidebar.classList.toggle('block');
      }
    }
  </script>
</body>
</html>