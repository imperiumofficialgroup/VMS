<?php
session_start();
require_once '../auth/db.php';
date_default_timezone_set('Asia/Kolkata'); // Or your specific timezone

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Fetch suggestions with volunteer details
$sql = "
    SELECT s.*, v.full_name AS name, v.phone, v.profile_image
    FROM suggestions s
    JOIN volunteers v ON s.volunteer_id = v.id
    ORDER BY s.created_at DESC
";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Suggestions & Queries | VMS</title>
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
    
    .suggestion-card {
      background: white;
      border-radius: 12px;
      border: 1px solid #D5D8DC;
      transition: all 0.3s ease;
    }
    
    .suggestion-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(93, 173, 226, 0.15);
    }
    
    .message-bubble {
      background: rgba(93, 173, 226, 0.05);
      border: 1px solid rgba(93, 173, 226, 0.2);
      border-radius: 12px;
    }
    
    .action-btn {
      padding: 8px 16px;
      border-radius: 8px;
      font-weight: 500;
      font-size: 0.875rem;
      transition: all 0.3s ease;
      cursor: pointer;
      border: 1px solid;
    }
    
    .chat-btn {
      background: rgba(93, 173, 226, 0.1);
      color: #5DADE2;
      border-color: rgba(93, 173, 226, 0.3);
    }
    
    .chat-btn:hover {
      background: rgba(93, 173, 226, 0.2);
      transform: translateY(-1px);
    }
    
    .call-btn {
      background: rgba(165, 105, 189, 0.1);
      color: #A569BD;
      border-color: rgba(165, 105, 189, 0.3);
    }
    
    .call-btn:hover {
      background: rgba(165, 105, 189, 0.2);
      transform: translateY(-1px);
    }
    
    .whatsapp-btn {
      background: rgba(16, 185, 129, 0.1);
      color: #10b981;
      border-color: rgba(16, 185, 129, 0.3);
    }
    
    .whatsapp-btn:hover {
      background: rgba(16, 185, 129, 0.2);
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
    
    .subject-badge {
      background: rgba(93, 173, 226, 0.1);
      color: #5DADE2;
      padding: 4px 8px;
      border-radius: 6px;
      font-size: 0.75rem;
      font-weight: 600;
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
      
      .suggestion-card {
        padding: 1.25rem;
        margin-bottom: 1.5rem;
      }
      
      .volunteer-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1rem;
      }
      
      .volunteer-info {
        flex-direction: row;
        align-items: center;
        gap: 0.75rem;
      }
      
      .volunteer-image {
        width: 3rem;
        height: 3rem;
      }
      
      .action-buttons {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.75rem;
        width: 100%;
      }
      
      .action-btn {
        width: 100%;
        text-align: center;
        justify-content: center;
      }
    }
    
    @media (min-width: 641px) and (max-width: 768px) {
      .page-container {
        padding: 1.5rem;
      }
      
      .container-card {
        padding: 2rem;
      }
      
      .suggestion-card {
        padding: 1.5rem;
      }
      
      .volunteer-header {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
      }
      
      .action-buttons {
        display: flex;
        flex-wrap: wrap;
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
      
      .suggestion-card {
        padding: 1.75rem;
      }
      
      .volunteer-header {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
      }
      
      .action-buttons {
        display: flex;
        gap: 0.75rem;
      }
      
      .suggestions-grid {
        display: grid;
        gap: 1.5rem;
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
<body class="min-h-screen md:ml-64">
  <!-- Sidebar -->
  <?php include 'sidebar.php'; ?>

  <!-- Main Content -->
  <div class="page-container">
    <div class="container-card">
      <!-- Header Section -->
      <div class="header-section mb-8 mt-6 md:mt-0">
  <h1 class="text-2xl md:text-3xl font-bold text-text mb-2">Volunteer Suggestions & Queries</h1>
  <p class="text-text/70">Review and respond to volunteer feedback and questions</p>
</div>


      <?php if ($result->num_rows > 0): ?>
        <!-- Suggestions Grid -->
        <div class="suggestions-grid">
          <?php while ($row = $result->fetch_assoc()): ?>
            <div class="suggestion-card">
              <!-- Volunteer Header -->
              <div class="volunteer-header mb-4">
                <div class="flex items-center volunteer-info">
                  <div class="volunteer-image w-12 h-12 flex-shrink-0">
                    <img class="w-12 h-12 rounded-full object-cover border-2 border-white shadow-sm" 
                         src="../<?= htmlspecialchars($row['profile_image'] ?: 'uploads/default.jpg') ?>" 
                         alt="<?= htmlspecialchars($row['name']) ?>">
                  </div>
                  <div>
                    <div class="flex items-center gap-2">
                      <h3 class="font-semibold text-text"><?= htmlspecialchars($row['name']) ?></h3>
                      <span class="subject-badge"><?= htmlspecialchars($row['subject']) ?></span>
                    </div>
                    <p class="text-text/60 text-sm mt-1">
                      <i class="far fa-clock mr-1"></i>
                      <?= date("d M Y, h:i A", strtotime($row['created_at'])) ?>
                    </p>
                  </div>
                </div>
              </div>

              <!-- Message Content -->
              <div class="message-bubble p-4 mb-4">
                <p class="text-text leading-relaxed"><?= nl2br(htmlspecialchars($row['message'])) ?></p>
              </div>

              <!-- Action Buttons -->
              <div class="action-buttons">
                <!-- Chat -->
                <a href="../chat/chat_window.php?volunteer_id=<?= $row['volunteer_id'] ?>"
                   class="action-btn chat-btn inline-flex items-center">
                  <i class="fas fa-comments mr-2"></i>
                  Chat Response
                </a>

                <!-- Call -->
                <?php if (!empty($row['phone'])): ?>
                  <a href="tel:<?= $row['phone'] ?>"
                     class="action-btn call-btn inline-flex items-center">
                    <i class="fas fa-phone mr-2"></i>
                    Call Volunteer
                  </a>
                <?php endif; ?>

                <!-- WhatsApp -->
                <?php if (!empty($row['phone'])): ?>
                  <a href="https://wa.me/91<?= $row['phone'] ?>?text=Hello%20<?= urlencode($row['name']) ?>%2C%20this%20is%20regarding%20your%20<?= strtolower($row['subject']) ?>."
                     target="_blank"
                     class="action-btn whatsapp-btn inline-flex items-center">
                    <i class="fab fa-whatsapp mr-2"></i>
                    WhatsApp
                  </a>
                <?php endif; ?>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
      <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state">
          <i class="fas fa-comments"></i>
          <h3 class="text-xl font-bold text-text mb-2">No Suggestions Yet</h3>
          <p class="text-text/70 mb-6 max-w-md mx-auto">
            When volunteers submit suggestions or queries, they will appear here for your review and response.
          </p>
          <div class="text-text/50 text-sm">
            <i class="fas fa-info-circle mr-2"></i>
            Volunteers can submit feedback through their dashboard
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    // Add interactive effects
    document.addEventListener('DOMContentLoaded', function() {
      const cards = document.querySelectorAll('.suggestion-card');
      
      cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
          this.style.transform = 'translateY(-4px)';
        });
        
        card.addEventListener('mouseleave', function() {
          this.style.transform = 'translateY(0)';
        });
      });
    });
  </script>
</body>
</html>