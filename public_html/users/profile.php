<?php
session_start();
require_once '../auth/db.php';

if (!isset($_SESSION['volunteer_id'])) {
  header('Location: ../auth/volunteer_login.php');
  exit();
}

$volunteer_id = $_SESSION['volunteer_id'];

$stmt = $conn->prepare("SELECT * FROM volunteers WHERE id = ?");
$stmt->bind_param("i", $volunteer_id);
$stmt->execute();
$result = $stmt->get_result();
$volunteer = $result->fetch_assoc();
$stmt->close();

// Calculate subscription validity (1 year from created_at)
$created_at = new DateTime($volunteer['created_at']);
$valid_until = clone $created_at;
$valid_until->modify('+365 days');
$today = new DateTime();
$is_active = $today <= $valid_until;
$days_remaining = $today->diff($valid_until)->days;

// Get subscription status from database if available
$subscription_stmt = $conn->prepare("SELECT status, end_date FROM volunteer_subscriptions WHERE volunteer_id = ? ORDER BY id DESC LIMIT 1");
$subscription_stmt->bind_param("i", $volunteer_id);
$subscription_stmt->execute();
$subscription_result = $subscription_stmt->get_result();
$subscription = $subscription_result->fetch_assoc();
$subscription_stmt->close();

// Use database subscription data if available, otherwise calculate
if ($subscription) {
    $valid_until = new DateTime($subscription['end_date']);
    $is_active = $subscription['status'] === 'Active' && $today <= $valid_until;
    $days_remaining = $today->diff($valid_until)->days;
    $subscription_status = $subscription['status'];
} else {
    $subscription_status = $is_active ? 'Active' : 'Expired';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Volunteer Profile - <?php echo htmlspecialchars($volunteer['full_name']); ?> | VMS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #FBFCFC;
      color: #1C2833;
    }

    @media (max-width: 1023px) {
      .content-area {
        padding-top: 4rem;
      }
    }

    @media print {
      body * {
        visibility: hidden;
      }

      #idCard,
      #idCard * {
        visibility: visible;
      }

      #idCard {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        width: 90%;
        margin: 0 auto;
        border: none;
        box-shadow: none;
        background: white !important;
      }
    }

    .id-card {
      position: relative;
      overflow: hidden;
      border-radius: 20px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
      width: 100%;
      max-width: 700px;
      margin: 0 auto;
      border: 1px solid #D5D8DC;
      height: auto;
      min-height: 300px;
      background: white;
    }

    .id-card::before {
      content: "";
      position: absolute;
      bottom: 20px;
      right: 8px;
      width: 180px;
      height: 180px;
      background-image: url('../admin/admin.jpg');
      background-size: contain;
      background-repeat: no-repeat;
      opacity: 0.08;
      z-index: 0;
    }

    .id-card::after {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 8px;
      background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
    }

    .id-card-content {
      position: relative;
      z-index: 1;
      padding: 1.5rem;
    }

    .profile-image {
      border: 4px solid #ffffff;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      width: 120px;
      height: 120px;
      object-fit: cover;
    }

    .profile-image:hover {
      transform: scale(1.03);
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
    }

    .org-logo {
      filter: grayscale(100%) opacity(80%);
      height: 48px;
      transition: all 0.3s ease;
    }

    .org-logo:hover {
      filter: grayscale(50%) opacity(100%);
    }

    /* For PDF generation */
    .pdf-id-card {
      width: 100%;
      max-width: 700px;
      margin: 0 auto;
      padding: 20px;
      box-sizing: border-box;
    }

    .volunteer-name {
      color: #1C2833;
      display: inline;
      font-weight: 600;
    }

    .action-btn {
      transition: all 0.3s ease;
      transform: translateY(0);
    }

    .action-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .id-number {
      background: rgba(93, 173, 226, 0.1);
      padding: 4px 10px;
      border-radius: 4px;
      color: #5DADE2;
      font-weight: 500;
      font-size: 0.875rem;
      margin-left: 12px;
      vertical-align: middle;
    }

    .detail-icon {
      background: rgba(93, 173, 226, 0.1);
      width: 28px;
      height: 28px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 8px;
      color: #5DADE2;
    }

    .footer-icon {
      background-color: rgba(93, 173, 226, 0.1);
      color: #5DADE2;
    }

    .name-id-container {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 8px;
    }

    .name-container {
      display: flex;
      align-items: center;
      border-bottom: 1px solid #D5D8DC;
      padding-bottom: 8px;
      width: 100%;
    }

    .status-badge {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .status-active {
      background: rgba(16, 185, 129, 0.1);
      color: #065f46;
    }

    .status-expired {
      background: rgba(239, 68, 68, 0.1);
      color: #991b1b;
    }

    .status-warning {
      background: rgba(245, 158, 11, 0.1);
      color: #92400e;
    }

    .download-btn {
      background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
      color: white;
      padding: 12px 24px;
      border-radius: 12px;
      font-weight: 600;
      border: none;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .download-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(93, 173, 226, 0.3);
    }

    .print-btn {
      background: white;
      color: #1C2833;
      padding: 12px 24px;
      border-radius: 12px;
      font-weight: 600;
      border: 1px solid #D5D8DC;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .print-btn:hover {
      background: #FBFCFC;
      border-color: #5DADE2;
      transform: translateY(-1px);
    }

    /* Mobile First Responsive Design */
    @media (max-width: 640px) {
      .page-container {
        padding: 1rem;
      }

      .id-card-content {
        padding: 1rem;
      }

      .profile-image {
        width: 100px;
        height: 100px;
      }

      .action-buttons {
        flex-direction: column;
      }
    }

    @media (min-width: 641px) and (max-width: 768px) {
      .page-container {
        padding: 1.5rem;
      }
    }

    @media (min-width: 769px) {
      .page-container {
        padding: 2rem;
        width: calc(100% - 16rem);
        margin-left: 16rem;
        max-width: 900px;
      }
    }

    .validity-info {
      background: linear-gradient(135deg, #FBFCFC 0%, #f8fafc 100%);
      border: 1px solid #D5D8DC;
      border-radius: 12px;
      padding: 16px;
      margin-top: 1rem;
    }
  </style>
</head>

<body>
  <?php include 'sidebar.php'; ?>

  <div class="page-container lg:ml-96">
    <!-- Header Section -->
    <div class="header-section mt-12 sm:mt-0 mb-8 text-center sm:text-left">
      <h1 class="text-2xl sm:text-3xl font-bold text-text mb-2">My Volunteer Profile</h1>
      <p class="text-text/70">View and manage your volunteer identity card</p>
    </div>

    <!-- ID Card Container -->
    <div id="idCard" class="id-card">
      <div class="id-card-content">
        <!-- Header with Organization Logo -->
        <div class="flex justify-between items-start mb-4">
          <div>
            <h2 class="text-xl font-bold text-text">VOLUNTEER ID CARD</h2>
            <p class="text-xs text-text/60 mt-1">IMPERIUM TRUST</p>
          </div>
          <img src="../admin/admin.jpg" class="org-logo rounded-lg" alt="Organization Logo">
        </div>

        <!-- Main Content -->
        <div class="flex flex-col md:flex-row gap-6 items-center md:items-start">
          <!-- Left Side - Photo -->
          <div class="flex-shrink-0">
            <?php if (!empty($volunteer['profile_image'])): ?>
              <img src="../<?php echo htmlspecialchars($volunteer['profile_image']); ?>"
                   class="profile-image rounded-xl mx-auto md:mx-0"
                   alt="Profile">
            <?php else: ?>
              <div class="profile-image rounded-xl mx-auto md:mx-0 bg-gradient-to-br from-primary to-accent flex items-center justify-center text-white text-4xl font-bold">
                <?= strtoupper(substr($volunteer['full_name'], 0, 1)) ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Right Side - Details -->
          <div class="flex-grow mt-2 md:mt-0">
            <div class="mb-4">
              <div class="name-container">
                <h3 class="text-2xl font-bold text-text">
                  <?php echo htmlspecialchars($volunteer['full_name']); ?>
                </h3>
                <span class="id-number">ID: IMP-<?php echo str_pad($volunteer['id'], 4, '0', STR_PAD_LEFT); ?></span>
              </div>
              
              <?php if (!empty($volunteer['position'])): ?>
                <div class="flex items-center mt-3 text-sm font-medium text-primary">
                  <div class="detail-icon mr-2">
                    <i class="fas fa-user-tie text-sm"></i>
                  </div>
                  <?php echo htmlspecialchars($volunteer['position']); ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="space-y-3">
              <div class="flex items-center">
                <div class="detail-icon mr-3">
                  <i class="fas fa-envelope text-sm"></i>
                </div>
                <span class="text-sm text-text"><?php echo htmlspecialchars($volunteer['email']); ?></span>
              </div>

              <div class="flex items-center">
                <div class="detail-icon mr-3">
                  <i class="fas fa-phone text-sm"></i>
                </div>
                <span class="text-sm text-text"><?php echo htmlspecialchars($volunteer['phone']); ?></span>
              </div>

              <?php if (!empty($volunteer['address'])): ?>
                <div class="flex items-start">
                  <div class="detail-icon mr-3 mt-1">
                    <i class="fas fa-map-marker-alt text-sm"></i>
                  </div>
                  <span class="text-sm text-text"><?php echo nl2br(htmlspecialchars($volunteer['address'])); ?></span>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Subscription Validity Information -->
        <div class="validity-info">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
              <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-text">Status:</span>
                <span class="status-badge <?php 
                  echo $is_active ? 'status-active' : 'status-expired';
                  echo ($is_active && $days_remaining <= 30) ? ' status-warning' : '';
                ?>">
                  <i class="fas <?php echo $is_active ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                  <?php echo $subscription_status; ?>
                </span>
              </div>
              
              <?php if ($is_active): ?>
                <div class="flex items-center gap-2">
                  <span class="text-sm font-medium text-text">Expires in:</span>
                  <span class="text-sm <?php echo $days_remaining <= 30 ? 'text-amber-600 font-semibold' : 'text-text'; ?>">
                    <?php echo $days_remaining; ?> days
                  </span>
                </div>
              <?php endif; ?>
            </div>
            
            <div class="text-sm text-text/60">
              Valid until: <?php echo $valid_until->format('M d, Y'); ?>
            </div>
          </div>
          
          <?php if (!$is_active): ?>
            <div class="mt-2 text-sm text-red-600 font-medium">
              <i class="fas fa-exclamation-triangle mr-1"></i>
              Your subscription has expired. Please contact administrator to renew.
            </div>
          <?php elseif ($days_remaining <= 30): ?>
            <div class="mt-2 text-sm text-amber-600 font-medium">
              <i class="fas fa-clock mr-1"></i>
              Your subscription will expire soon. Please contact administrator to renew.
            </div>
          <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="mt-4 pt-3 border-t border-subtle flex flex-col md:flex-row justify-between items-center">
          <div class="text-xs text-text/60 mb-2 md:mb-0">
            <p class="font-medium">Issued: <?php echo date('M d, Y'); ?></p>
            <p>Member since: <?php echo date('M d, Y', strtotime($volunteer['created_at'])); ?></p>
          </div>
          <div class="flex items-center">
            <div class="w-8 h-8 footer-icon rounded-full flex items-center justify-center mr-2">
              <i class="fas fa-check text-xs"></i>
            </div>
            <span class="text-xs font-medium text-text/60">OFFICIAL VOLUNTEER ID</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="mt-6 flex flex-col sm:flex-row gap-3 action-buttons">
      <button onclick="downloadID()" class="download-btn flex items-center justify-center">
        <i class="fas fa-download mr-2"></i>
        Download ID Card
      </button>

      <button onclick="window.print()" class="print-btn flex items-center justify-center">
        <i class="fas fa-print mr-2"></i>
        Print ID Card
      </button>
    </div>
  </div>

  <script>
    function downloadID() {
      // Create a clone of the ID card for PDF generation
      const element = document.getElementById('idCard');
      const clone = element.cloneNode(true);

      // Add specific styling for PDF
      clone.classList.add('pdf-id-card');
      clone.style.width = '100%';
      clone.style.maxWidth = '700px';
      clone.style.margin = '0 auto';
      clone.style.padding = '20px';
      clone.style.boxSizing = 'border-box';

      // Temporarily add to body
      document.body.appendChild(clone);

      const opt = {
        margin: 10,
        filename: 'Volunteer_ID_<?php echo $volunteer["id"]; ?>_<?php echo str_replace(' ', '_', $volunteer["full_name"]); ?>.pdf',
        image: {
          type: 'jpeg',
          quality: 1.0
        },
        html2canvas: {
          scale: 2,
          useCORS: true,
          logging: true,
          letterRendering: true
        },
        jsPDF: {
          unit: 'mm',
          format: 'a4',
          orientation: 'portrait'
        },
        pagebreak: {
          mode: 'avoid-all'
        }
      };

      // Generate PDF
      html2pdf().set(opt).from(clone).save().then(() => {
        // Remove the clone after PDF generation
        document.body.removeChild(clone);
      });
    }

    // Close mobile sidebar when clicking on a nav item
    document.querySelectorAll('nav a').forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth < 1024) {
          Alpine.store('mobileSidebarOpen', false);
        }
      });
    });
  </script>
</body>

</html>