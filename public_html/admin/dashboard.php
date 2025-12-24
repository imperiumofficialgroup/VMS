<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
  header("Location: login.php");
  exit();
}

require_once '../auth/db.php';

// Fetch Total Volunteers
$volunteer_count = 0;
$event_count = 0;

$vol_query = "SELECT COUNT(*) AS total FROM volunteers";
$event_query = "SELECT COUNT(*) AS total FROM events";

$vol_result = $conn->query($vol_query);
$event_result = $conn->query($event_query);

if ($vol_result && $row = $vol_result->fetch_assoc()) {
  $volunteer_count = $row['total'];
}

if ($event_result && $row = $event_result->fetch_assoc()) {
  $event_count = $row['total'];
}

// Fetch last 5 volunteers created by this admin, order by newest
$stmt = $conn->prepare("
    SELECT full_name, created_at 
    FROM volunteers 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute();
$result = $stmt->get_result();

$recentVolunteers = [];
while ($row = $result->fetch_assoc()) {
  $recentVolunteers[] = $row;
}

function time_elapsed_string($datetime, $full = false)
{
  $now = new DateTime;
  $ago = new DateTime($datetime);
  $diff = $now->diff($ago);

  $units = [
    'y' => 'year',
    'm' => 'month',
    'd' => 'day',
    'h' => 'hour',
    'i' => 'minute',
    's' => 'second',
  ];
  foreach ($units as $k => &$v) {
    if ($diff->$k) {
      $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
    } else {
      unset($units[$k]);
    }
  }

  if (!$full) $units = array_slice($units, 0, 1);
  return $units ? implode(', ', $units) . ' ago' : 'just now';
}

// Total volunteers
$totalVolunteers = $conn->query("SELECT COUNT(*) as total FROM volunteers")->fetch_assoc()['total'];

// Monthly joins (last 30 days)
$monthlyJoins = $conn->query("SELECT COUNT(*) as joined FROM volunteers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['joined'];

// Total events
$totalEvents = $conn->query("SELECT COUNT(*) as events FROM events")->fetch_assoc()['events'];

// Total attendance records
$totalAttendance = $conn->query("SELECT COUNT(*) as records FROM event_attendance")->fetch_assoc()['records'];

// Replace the existing $sql query with this:
$sql = "
  SELECT 
    e.event_id,
    e.title, 
    COUNT(CASE WHEN ea.status = 'Present' THEN 1 END) as attended_count,
    COUNT(ea.volunteer_id_fk) as total_volunteers
  FROM events e
  LEFT JOIN event_attendance ea ON e.event_id = ea.event_id_fk
  GROUP BY e.event_id
  ORDER BY e.event_date DESC
  LIMIT 10
";

$res = $conn->query($sql);

$labels = [];
$attendedCounts = [];
$totalVolunteers = [];

while ($row = $res->fetch_assoc()) {
  $labels[] = $row['title'];
  $attendedCounts[] = $row['attended_count'];
  $totalVolunteers[] = $row['total_volunteers'];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard | <?php echo htmlspecialchars($_SESSION['admin_username']); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['Inter', 'system-ui', 'sans-serif'],
          },
          colors: {
            // Professional indigo-blue color palette
            primary: {
              50: '#EEF2FF',
              100: '#E0E7FF',
              200: '#C7D2FE',
              300: '#A5B4FC',
              400: '#818CF8',
              500: '#6366F1', // Primary indigo
              600: '#4F46E5', // Darker indigo
              700: '#4338CA', // Deep indigo
              800: '#3730A3',
              900: '#312E81',
            },
            accent: {
              50: '#EFF6FF',
              100: '#DBEAFE',
              200: '#BFDBFE',
              300: '#93C5FD',
              400: '#60A5FA',
              500: '#3B82F6', // Primary blue
              600: '#2563EB',
              700: '#1D4ED8',
              800: '#1E40AF',
              900: '#1E3A8A',
            },
            neutral: {
              50: '#F8FAFC',
              100: '#F1F5F9',
              200: '#E2E8F0',
              300: '#CBD5E1',
              400: '#94A3B8',
              500: '#64748B',
              600: '#475569',
              700: '#334155',
              800: '#1E293B',
              900: '#0F172A',
            },
            background: '#F8FAFC',
            text: '#1E293B',
            subtle: '#CBD5E1',
          },
          animation: {
            'fade-in': 'fadeIn 0.4s ease-out',
            'slide-up': 'slideUp 0.4s ease-out',
            'pulse-subtle': 'pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
          },
          keyframes: {
            fadeIn: {
              '0%': { opacity: '0', transform: 'translateY(10px)' },
              '100%': { opacity: '1', transform: 'translateY(0)' },
            },
            slideUp: {
              '0%': { transform: 'translateY(20px)', opacity: '0' },
              '100%': { transform: 'translateY(0)', opacity: '1' },
            },
          },
          boxShadow: {
            'soft': '0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03)',
            'medium': '0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.03)',
            'glow': '0 0 0 1px rgba(99, 102, 241, 0.1), 0 4px 6px -1px rgba(99, 102, 241, 0.1), 0 2px 4px -1px rgba(99, 102, 241, 0.06)',
          }
        }
      }
    }
  </script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
      background-color: #F8FAFC;
      color: #1E293B;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }
    
    ::selection {
      background-color: rgba(99, 102, 241, 0.2);
    }
    
    .sidebar-transition {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .card-hover {
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      border: 1px solid #E2E8F0;
    }
    
    .card-hover:hover {
      transform: translateY(-3px);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
      border-color: #C7D2FE;
    }
    
    .stat-card {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      border: 1px solid #E2E8F0;
    }
    
    .stat-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
    }
    
    .activity-item {
      transition: all 0.2s ease;
    }
    
    .activity-item:hover {
      background-color: #F1F5F9;
    }
    
    .gradient-bg {
      background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);
    }
    
    .gradient-subtle {
      background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%);
    }
    
    .gradient-accent {
      background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
    }
    
    .soft-border {
      border-color: #E2E8F0;
    }
    
    .glass-effect {
      background: rgba(255, 255, 255, 0.9);
      backdrop-filter: blur(8px);
    }
    
    @media (max-width: 768px) {
      .stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }
    
    @media (max-width: 640px) {
      .stat-grid {
        grid-template-columns: repeat(1, minmax(0, 1fr));
      }
    }
    
    /* Custom scrollbar */
    ::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }
    
    ::-webkit-scrollbar-track {
      background: #F1F5F9;
      border-radius: 4px;
    }
    
    ::-webkit-scrollbar-thumb {
      background: #CBD5E1;
      border-radius: 4px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
      background: #94A3B8;
    }
  </style>
</head>

<body class="min-h-screen bg-background">
  <?php include 'sidebar.php'; ?>

  <!-- Main Content -->
  <main class="lg:ml-64 pt-16 lg:pt-0">
    <div class="p-4 md:p-6 lg:p-8 max-w-7xl mx-auto">
      <!-- Welcome Card -->
      <div class="glass-effect rounded-2xl shadow-soft overflow-hidden mb-8 card-hover animate-fade-in">
        <div class="p-6 md:p-8 flex flex-col md:flex-row items-start md:items-center gap-6">
          <div class="flex-shrink-0">
            <div class="w-16 h-16 rounded-full gradient-bg flex items-center justify-center text-white text-2xl font-semibold shadow-lg relative">
              <?php echo strtoupper(substr($_SESSION['admin_username'], 0, 1)); ?>
              <div class="absolute -inset-1 rounded-full border-2 border-primary-100/30"></div>
            </div>
          </div>
          <div class="flex-grow">
            <div class="flex items-center gap-2 mb-2">
              <h1 class="text-2xl md:text-3xl font-bold text-neutral-800">
                Welcome back, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
              </h1>
              <span class="px-2 py-1 text-xs font-medium bg-primary-50 text-primary-600 rounded-full">
                Admin
              </span>
            </div>
            <p class="text-neutral-600 mb-4 max-w-2xl leading-relaxed">
              Here's an overview of your volunteer management system. You have 
              <span class="font-semibold text-primary-600"><?php echo $volunteer_count; ?> volunteers</span> 
              and <span class="font-semibold text-accent-600"><?php echo $event_count; ?> events</span> 
              in your organization.
            </p>
            <div class="flex flex-wrap items-center gap-4 text-sm">
              <a href="logout.php" class="font-medium text-red-500 hover:text-red-700 transition-all duration-200 flex items-center gap-2 px-3 py-1.5 rounded-lg hover:bg-red-50">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
              </a>
              <div class="h-4 w-px bg-neutral-200"></div>
              <span class="text-neutral-500 flex items-center gap-2 px-3 py-1.5 rounded-lg bg-neutral-50">
                <i class="far fa-clock text-neutral-400"></i>
                <span>Last login: <?php echo date('M j, Y g:i A'); ?></span>
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="stat-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-10">
        <!-- Volunteers Card -->
        <div class="stat-card bg-white rounded-xl shadow-soft p-6 animate-slide-up relative overflow-hidden" style="animation-delay: 0.1s">
          <div class="absolute top-0 right-0 w-24 h-24 -mt-8 -mr-8 bg-primary-50 rounded-full"></div>
          <div class="relative z-10 flex items-center justify-between">
            <div>
              <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-1">Total Volunteers</p>
              <p class="text-3xl font-bold text-primary-600 mb-2"><?php echo $volunteer_count; ?></p>
              <div class="flex items-center text-sm text-neutral-500">
                <span class="flex items-center text-emerald-500 mr-2">
                  <i class="fas fa-arrow-up text-xs mr-1"></i>
                  12%
                </span>
                <span>from last month</span>
              </div>
            </div>
            <div class="p-3 rounded-xl bg-primary-50 text-primary-500 shadow-soft">
              <i class="fas fa-users text-xl"></i>
            </div>
          </div>
          <div class="relative z-10 mt-8 pt-4 border-t border-neutral-100">
            <a href="view_volunteers.php" class="text-sm font-medium text-primary-600 hover:text-primary-700 transition-all duration-200 flex items-center gap-2 group">
              <span>Manage volunteers</span>
              <i class="fas fa-arrow-right text-xs transform group-hover:translate-x-1 transition-transform duration-200"></i>
            </a>
          </div>
        </div>

        <!-- Events Card -->
        <div class="stat-card bg-white rounded-xl shadow-soft p-6 animate-slide-up relative overflow-hidden" style="animation-delay: 0.2s">
          <div class="absolute top-0 right-0 w-24 h-24 -mt-8 -mr-8 bg-accent-50 rounded-full"></div>
          <div class="relative z-10 flex items-center justify-between">
            <div>
              <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-1">Total Events</p>
              <p class="text-3xl font-bold text-accent-600 mb-2"><?php echo $event_count; ?></p>
              <div class="flex items-center text-sm text-neutral-500">
                <span class="flex items-center text-emerald-500 mr-2">
                  <i class="fas fa-calendar-check text-xs mr-1"></i>
                  5 upcoming
                </span>
              </div>
            </div>
            <div class="p-3 rounded-xl bg-accent-50 text-accent-500 shadow-soft">
              <i class="fas fa-calendar-alt text-xl"></i>
            </div>
          </div>
          <div class="relative z-10 mt-8 pt-4 border-t border-neutral-100">
            <a href="view_events.php" class="text-sm font-medium text-accent-600 hover:text-accent-700 transition-all duration-200 flex items-center gap-2 group">
              <span>View all events</span>
              <i class="fas fa-arrow-right text-xs transform group-hover:translate-x-1 transition-transform duration-200"></i>
            </a>
          </div>
        </div>

        <!-- Monthly Joins Card -->
        <div class="stat-card bg-white rounded-xl shadow-soft p-6 animate-slide-up relative overflow-hidden" style="animation-delay: 0.3s">
          <div class="absolute top-0 right-0 w-24 h-24 -mt-8 -mr-8 bg-primary-50 rounded-full"></div>
          <div class="relative z-10 flex items-center justify-between">
            <div>
              <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-1">Monthly Joins</p>
              <p class="text-3xl font-bold text-primary-700 mb-2"><?php echo $monthlyJoins; ?></p>
              <div class="flex items-center text-sm text-neutral-500">
                <span class="flex items-center text-emerald-500 mr-2">
                  <i class="fas fa-user-plus text-xs mr-1"></i>
                  8% growth
                </span>
              </div>
            </div>
            <div class="p-3 rounded-xl bg-primary-100 text-primary-600 shadow-soft">
              <i class="fas fa-user-plus text-xl"></i>
            </div>
          </div>
          <div class="relative z-10 mt-8 pt-4 border-t border-neutral-100">
            <div class="text-sm text-neutral-500">
              <span class="font-medium text-primary-600">Active growth</span> this month
            </div>
          </div>
        </div>

        <!-- Attendance Records Card -->
        <div class="stat-card bg-white rounded-xl shadow-soft p-6 animate-slide-up relative overflow-hidden" style="animation-delay: 0.4s">
          <div class="absolute top-0 right-0 w-24 h-24 -mt-8 -mr-8 bg-accent-50 rounded-full"></div>
          <div class="relative z-10 flex items-center justify-between">
            <div>
              <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-1">Attendance</p>
              <p class="text-3xl font-bold text-accent-700 mb-2"><?php echo $totalAttendance; ?></p>
              <div class="flex items-center text-sm text-neutral-500">
                <span class="flex items-center text-emerald-500 mr-2">
                  <i class="fas fa-chart-line text-xs mr-1"></i>
                  22% increase
                </span>
              </div>
            </div>
            <div class="p-3 rounded-xl bg-accent-100 text-accent-600 shadow-soft">
              <i class="fas fa-clipboard-check text-xl"></i>
            </div>
          </div>
          <div class="relative z-10 mt-8 pt-4 border-t border-neutral-100">
            <div class="text-sm text-neutral-500">
              <span class="font-medium text-accent-600">High engagement</span> rate
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions & Chart Section -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-10">
        <!-- Quick Actions -->
        <div class="lg:col-span-1 bg-white rounded-xl shadow-soft p-6 card-hover">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-bold text-neutral-800 flex items-center gap-2">
              <div class="p-2 rounded-lg bg-primary-50 text-primary-600">
                <i class="fas fa-bolt"></i>
              </div>
              <span>Quick Actions</span>
            </h2>
            <span class="text-xs font-medium text-primary-600 bg-primary-50 px-3 py-1 rounded-full">
              4 actions
            </span>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <a href="add_volunteer.php" class="flex flex-col items-center justify-center p-4 rounded-xl border border-neutral-200 hover:border-primary-300 hover:shadow-glow transition-all duration-300 group gradient-subtle">
              <div class="p-3 rounded-xl bg-white text-primary-600 mb-3 group-hover:bg-primary-50 transition-all duration-300 shadow-soft">
                <i class="fas fa-user-plus text-lg"></i>
              </div>
              <span class="text-sm font-medium text-neutral-700 group-hover:text-primary-700 transition">Add Volunteer</span>
            </a>
            <a href="add_event.php" class="flex flex-col items-center justify-center p-4 rounded-xl border border-neutral-200 hover:border-accent-300 hover:shadow-glow transition-all duration-300 group" style="background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);">
              <div class="p-3 rounded-xl bg-white text-accent-600 mb-3 group-hover:bg-accent-50 transition-all duration-300 shadow-soft">
                <i class="fas fa-calendar-plus text-lg"></i>
              </div>
              <span class="text-sm font-medium text-neutral-700 group-hover:text-accent-700 transition">Create Event</span>
            </a>
            <a href="view_volunteers.php" class="flex flex-col items-center justify-center p-4 rounded-xl border border-neutral-200 hover:border-primary-300 hover:shadow-glow transition-all duration-300 group gradient-subtle">
              <div class="p-3 rounded-xl bg-white text-primary-600 mb-3 group-hover:bg-primary-50 transition-all duration-300 shadow-soft">
                <i class="fas fa-list text-lg"></i>
              </div>
              <span class="text-sm font-medium text-neutral-700 group-hover:text-primary-700 transition">View Volunteers</span>
            </a>
            <a href="view_events.php" class="flex flex-col items-center justify-center p-4 rounded-xl border border-neutral-200 hover:border-accent-300 hover:shadow-glow transition-all duration-300 group" style="background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);">
              <div class="p-3 rounded-xl bg-white text-accent-600 mb-3 group-hover:bg-accent-50 transition-all duration-300 shadow-soft">
                <i class="fas fa-calendar-week text-lg"></i>
              </div>
              <span class="text-sm font-medium text-neutral-700 group-hover:text-accent-700 transition">View Events</span>
            </a>
          </div>
        </div>

        <!-- Attendance Chart -->
        <div class="col-span-1 lg:col-span-2 bg-white rounded-xl shadow-soft p-6 card-hover">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
              <h3 class="text-lg font-bold text-neutral-800 flex items-center gap-2 mb-1">
                <div class="p-2 rounded-lg bg-primary-50 text-primary-600">
                  <i class="fas fa-chart-bar"></i>
                </div>
                <span>Event Attendance Analytics</span>
              </h3>
              <p class="text-sm text-neutral-500">Showing total volunteers vs attended per event</p>
            </div>
            <div class="flex items-center gap-2 mt-2 sm:mt-0">
              <div class="flex items-center gap-1.5">
                <div class="w-3 h-3 rounded-full bg-primary-500"></div>
                <span class="text-xs text-neutral-600">Total Volunteers</span>
              </div>
              <div class="flex items-center gap-1.5">
                <div class="w-3 h-3 rounded-full bg-accent-500"></div>
                <span class="text-xs text-neutral-600">Attended</span>
              </div>
            </div>
          </div>

          <!-- Chart container -->
          <div class="relative w-full h-64 md:h-72 lg:h-80">
            <canvas id="attendanceChart" class="absolute inset-0 w-full h-full"></canvas>
          </div>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="bg-white rounded-xl shadow-soft p-6 card-hover">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h2 class="text-lg font-bold text-neutral-800 flex items-center gap-2 mb-1">
              <div class="p-2 rounded-lg bg-primary-50 text-primary-600">
                <i class="fas fa-history"></i>
              </div>
              <span>Recent Activity</span>
            </h2>
            <p class="text-sm text-neutral-500">Latest volunteer registrations</p>
          </div>
          <a href="view_volunteers.php" class="text-sm font-medium text-primary-600 hover:text-primary-700 transition-all duration-200 flex items-center gap-2 group px-4 py-2 rounded-lg hover:bg-primary-50">
            <span>View All</span>
            <i class="fas fa-arrow-right text-xs transform group-hover:translate-x-1 transition-transform duration-200"></i>
          </a>
        </div>
        <div class="space-y-1">
          <?php foreach ($recentVolunteers as $vol): ?>
            <div class="activity-item p-4 rounded-xl hover:bg-neutral-50 transition-all duration-200 group">
              <div class="flex items-start">
                <div class="flex-shrink-0">
                  <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-primary-100 to-primary-50 flex items-center justify-center text-primary-600 shadow-soft">
                    <i class="fas fa-user-plus text-sm"></i>
                  </div>
                </div>
                <div class="ml-4 flex-1 min-w-0">
                  <div class="flex items-start justify-between">
                    <div>
                      <p class="text-sm font-medium text-neutral-800 truncate">
                        <?= htmlspecialchars($vol['full_name']) ?> joined as volunteer
                      </p>
                      <p class="text-sm text-neutral-500 mt-0.5">
                        <i class="far fa-clock text-xs mr-1"></i>
                        <?= time_elapsed_string($vol['created_at']) ?>
                      </p>
                    </div>
                    <span class="text-xs font-medium text-primary-600 bg-primary-50 px-2.5 py-1 rounded-full">
                      New
                    </span>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </main>

  <script>
    // Professional Attendance Chart with indigo-blue theme
    const ctx = document.getElementById('attendanceChart').getContext('2d');

    // Create gradient for bars
    const createGradient = (ctx, color) => {
      const gradient = ctx.createLinearGradient(0, 0, 0, 400);
      gradient.addColorStop(0, color + 'CC');
      gradient.addColorStop(1, color + '33');
      return gradient;
    };

    const chart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [
          {
            label: 'Total Volunteers',
            data: <?= json_encode($totalVolunteers) ?>,
            backgroundColor: createGradient(ctx, '#6366F1'),
            borderColor: '#6366F1',
            borderWidth: 1.5,
            borderRadius: 8,
            borderSkipped: false,
            barPercentage: 0.7,
            categoryPercentage: 0.8
          },
          {
            label: 'Attended',
            data: <?= json_encode($attendedCounts) ?>,
            backgroundColor: createGradient(ctx, '#3B82F6'),
            borderColor: '#3B82F6',
            borderWidth: 1.5,
            borderRadius: 8,
            borderSkipped: false,
            barPercentage: 0.7,
            categoryPercentage: 0.8
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        resizeDelay: 200,
        interaction: {
          mode: 'index',
          intersect: false,
        },
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            backgroundColor: '#1E293B',
            titleFont: { 
              size: 13, 
              weight: '600',
              family: 'Inter'
            },
            bodyFont: { 
              size: 12,
              family: 'Inter'
            },
            padding: 12,
            cornerRadius: 8,
            usePointStyle: true,
            boxPadding: 6,
            callbacks: {
              label: function(context) {
                let label = context.dataset.label || '';
                if (label) {
                  label += ': ';
                }
                label += context.parsed.y;
                return label;
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              drawBorder: false,
              color: 'rgba(226, 232, 240, 0.5)',
              drawTicks: false
            },
            border: {
              display: false
            },
            ticks: {
              precision: 0,
              color: '#64748B',
              font: { 
                size: 11,
                family: 'Inter'
              },
              padding: 10
            }
          },
          x: {
            grid: { 
              display: false 
            },
            border: {
              display: false
            },
            ticks: {
              color: '#64748B',
              font: { 
                size: 11,
                family: 'Inter'
              },
              maxRotation: 45,
              padding: 10
            }
          }
        },
        animation: {
          duration: 1000,
          easing: 'easeOutQuart'
        }
      }
    });

    // Add smooth scroll behavior
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
          target.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      });
    });

  </script>
</body>

</html>