<?php
$volunteer_id = $_SESSION['volunteer_id'] ?? null;
$unread_count = 0;
$volunteer_position = 'Volunteer'; // Default value

if ($volunteer_id) {
    // Fetch unread messages count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as unread 
        FROM messages 
        WHERE receiver_id = ? 
          AND receiver_role = 'volunteer' 
          AND sender_role = 'admin' 
          AND is_read = 0
    ");
    $stmt->bind_param("i", $volunteer_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $unread_count = $result['unread'] ?? 0;
    
    // Fetch volunteer position
    $position_stmt = $conn->prepare("
        SELECT position 
        FROM volunteers 
        WHERE id = ?
    ");
    $position_stmt->bind_param("i", $volunteer_id);
    $position_stmt->execute();
    $position_result = $position_stmt->get_result()->fetch_assoc();
    $volunteer_position = $position_result['position'] ?? 'Volunteer';
}
?>

<!-- Modern Sidebar Component -->
<section x-data="{ mobileSidebarOpen: false, activeSubmenu: '' }" class="font-sans">
  <!-- Mobile Header -->
  <div class="lg:hidden bg-white border-b border-gray-100 px-4 py-3 shadow-sm flex items-center justify-between w-full fixed top-0 z-40 backdrop-blur-sm bg-white/90">
    <div class="flex items-center">
      <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600 font-bold text-xl tracking-tight">IMPERIUM</span>
    </div>
    
    <!-- Modern Hamburger Button -->
    <button 
      @click="mobileSidebarOpen = !mobileSidebarOpen" 
      class="p-2 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all"
      :aria-expanded="mobileSidebarOpen"
      aria-label="Toggle navigation"
    >
      <svg class="w-6 h-6 text-gray-700 transition-transform duration-300" 
           :class="{ 'transform rotate-90': mobileSidebarOpen }" 
           fill="none" 
           viewBox="0 0 24 24" 
           stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
      </svg>
    </button>
  </div>

  <!-- Overlay -->
  <div x-show="mobileSidebarOpen"
       @click="mobileSidebarOpen = false"
       x-transition.opacity.duration.300ms
       class="fixed inset-0 bg-black/30 backdrop-blur-sm z-30 lg:hidden">
  </div>

  <!-- Sidebar -->
  <aside x-show="mobileSidebarOpen || window.innerWidth >= 1024"
         x-transition:enter="transition ease-in-out duration-300 transform"
         x-transition:enter-start="-translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in-out duration-300 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="-translate-x-full"
         @keydown.escape="mobileSidebarOpen = false"
         class="fixed top-0 left-0 z-40 w-72 h-screen bg-gradient-to-b from-white to-gray-50 shadow-xl lg:shadow-lg flex flex-col overflow-y-auto border-r border-gray-100">

    <!-- Brand Section -->
    <div class="px-6 py-5 border-b border-gray-100">
      <div class="flex items-center space-x-3">
        <img src="../chat/admin.jpg" alt="IMPERIUM Logo" class="w-10 h-10 rounded-lg object-cover shadow-sm">
        <div>
          <h1 class="text-xl font-bold text-gray-900">IMPERIUM</h1>
          <p class="text-xs text-gray-500">Volunteer Portal</p>
        </div>
      </div>
    </div>

    <!-- User Profile Quick View -->
    <div class="px-6 py-4 border-b border-gray-100 bg-blue-50/50">
      <div class="flex items-center space-x-3">
        <div class="relative">
          <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-indigo-500 flex items-center justify-center text-white font-semibold">
            <?= substr($_SESSION['volunteer_name'] ?? 'U', 0, 1) ?>
          </div>
          <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full border-2 border-white"></span>
        </div>
        <div>
          <h3 class="font-medium text-gray-900"><?= $_SESSION['volunteer_name'] ?? 'User' ?></h3>
          <p class="text-xs text-gray-500"><?= htmlspecialchars($volunteer_position) ?></p>
        </div>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
      <!-- Profile -->
      <a href="../users/profile.php"
         class="flex items-center px-4 py-3 text-sm font-medium rounded-lg mx-1 transition-all duration-200
                <?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 
                   'bg-blue-600/10 text-blue-700 border-l-4 border-blue-600 font-semibold' : 
                   'text-gray-700 hover:bg-gray-100/70' ?>">
        <div class="p-2 mr-3 rounded-lg <?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600' ?>">
          <i class="fas fa-user-circle text-lg"></i>
        </div>
        <span>My Profile</span>
      </a>
      
      <a href="../users/edit_profile.php"
         class="flex items-center px-4 py-3 text-sm font-medium rounded-lg mx-1 transition-all duration-200
                <?= basename($_SERVER['PHP_SELF']) === 'edit_profile.php' ? 
                   'bg-blue-600/10 text-blue-700 border-l-4 border-blue-600 font-semibold' : 
                   'text-gray-700 hover:bg-gray-100/70' ?>">
        <div class="p-2 mr-3 rounded-lg <?= basename($_SERVER['PHP_SELF']) === 'edit_profile.php' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600' ?>">
          <i class="fas fa-user-edit text-lg"></i>
        </div>
        <span>Edit Profile</span>
      </a>

      <!-- Attendance -->
      <a href="../users/attendance.php"
         class="flex items-center px-4 py-3 text-sm font-medium rounded-lg mx-1 transition-all duration-200
                <?= basename($_SERVER['PHP_SELF']) === 'attendance.php' ? 
                   'bg-blue-600/10 text-blue-700 border-l-4 border-blue-600 font-semibold' : 
                   'text-gray-700 hover:bg-gray-100/70' ?>">
        <div class="p-2 mr-3 rounded-lg <?= basename($_SERVER['PHP_SELF']) === 'attendance.php' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600' ?>">
          <i class="fas fa-calendar-check text-lg"></i>
        </div>
        <span>My Attendance</span>
      </a>

      <!-- Events -->
      <a href="../users/events.php" 
         class="flex items-center px-4 py-3 text-sm font-medium rounded-lg mx-1 transition-all duration-200
                <?= basename($_SERVER['PHP_SELF']) === 'events.php' ? 
                   'bg-blue-600/10 text-blue-700 border-l-4 border-blue-600 font-semibold' : 
                   'text-gray-700 hover:bg-gray-100/70' ?>">
        <div class="p-2 mr-3 rounded-lg <?= basename($_SERVER['PHP_SELF']) === 'events.php' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600' ?>">
          <i class="far fa-calendar-alt text-lg"></i>
        </div>
        <span>Events</span>
      </a>

      <!-- Chat -->
      <a href="../chat/chat_volunteer.php"
         class="flex items-center px-4 py-3 text-sm font-medium rounded-lg mx-1 transition-all duration-200
                <?= basename($_SERVER['PHP_SELF']) === 'chat_volunteer.php' ? 
                   'bg-blue-600/10 text-blue-700 border-l-4 border-blue-600 font-semibold' : 
                   'text-gray-700 hover:bg-gray-100/70' ?>">
        <div class="p-2 mr-3 rounded-lg <?= basename($_SERVER['PHP_SELF']) === 'chat_volunteer.php' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600' ?>">
          <i class="far fa-comment-dots text-lg"></i>
        </div>
        <span class="relative">
          Chat with Admin
          <?php if ($unread_count > 0): ?>
            <span class="absolute -top-2 -right-6 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full animate-pulse flex items-center justify-center min-w-[20px]">
              <?= $unread_count ?>
            </span>
          <?php endif; ?>
        </span>
      </a>

      <!-- Group List -->
      <a href="../users/group_list.php" 
         class="flex items-center px-4 py-3 text-sm font-medium rounded-lg mx-1 transition-all duration-200
                <?= basename($_SERVER['PHP_SELF']) === 'group_list.php' ? 
                   'bg-blue-600/10 text-blue-700 border-l-4 border-blue-600 font-semibold' : 
                   'text-gray-700 hover:bg-gray-100/70' ?>">
        <div class="p-2 mr-3 rounded-lg <?= basename($_SERVER['PHP_SELF']) === 'group_list.php' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600' ?>">
          <i class="fas fa-users text-lg"></i>
        </div>
        <span>Group List</span>
      </a>

      <!-- Leave Request -->
      <a href="../users/leave_request.php" 
         class="flex items-center px-4 py-3 text-sm font-medium rounded-lg mx-1 transition-all duration-200
                <?= basename($_SERVER['PHP_SELF']) === 'leave_request.php' ? 
                   'bg-blue-600/10 text-blue-700 border-l-4 border-blue-600 font-semibold' : 
                   'text-gray-700 hover:bg-gray-100/70' ?>">
        <div class="p-2 mr-3 rounded-lg <?= basename($_SERVER['PHP_SELF']) === 'leave_request.php' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600' ?>">
          <i class="far fa-calendar-check text-lg"></i>
        </div>
        <span>Apply Leaves</span>
      </a>
      
      <!-- Gamify -->
      <a href="../users/gamifyed.php" 
         class="flex items-center px-4 py-3 text-sm font-medium rounded-lg mx-1 transition-all duration-200
                <?= basename($_SERVER['PHP_SELF']) === 'gamifyed.php' ? 
                   'bg-blue-600/10 text-blue-700 border-l-4 border-blue-600 font-semibold' : 
                   'text-gray-700 hover:bg-gray-100/70' ?>">
        <div class="p-2 mr-3 rounded-lg <?= basename($_SERVER['PHP_SELF']) === 'gamifyed.php' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600' ?>">
          <i class="fas fa-trophy text-lg"></i>
        </div>
        <span>Achivements</span>
      </a>
      
      
      
      <a href="../users/redeem.php" 
         class="flex items-center px-4 py-3 text-sm font-medium rounded-lg mx-1 transition-all duration-200
                <?= basename($_SERVER['PHP_SELF']) === 'redeem.php' ? 
                   'bg-blue-600/10 text-blue-700 border-l-4 border-blue-600 font-semibold' : 
                   'text-gray-700 hover:bg-gray-100/70' ?>">
        <div class="p-2 mr-3 rounded-lg <?= basename($_SERVER['PHP_SELF']) === 'redeem.php' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600' ?>">
<i class="fas fa-gift text-lg"></i>
        </div>
        <span>Redeem</span>
      </a>
      
      <!-- Suggestions or Query -->
      <a href="../users/add_suggestion.php" 
         class="flex items-center px-4 py-3 text-sm font-medium rounded-lg mx-1 transition-all duration-200
                <?= basename($_SERVER['PHP_SELF']) === 'add_suggestion.php' ? 
                   'bg-blue-600/10 text-blue-700 border-l-4 border-blue-600 font-semibold' : 
                   'text-gray-700 hover:bg-gray-100/70' ?>">
        <div class="p-2 mr-3 rounded-lg <?= basename($_SERVER['PHP_SELF']) === 'add_suggestion.php' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600' ?>">
          <i class="far fa-question-circle text-lg"></i>
        </div>
        <span>Suggestions or Query</span>
      </a>
      
      
    </nav>

    <!-- Footer Section -->
    <div class="px-6 py-4 border-t border-gray-100 mt-auto bg-white">
      <a href="../users/logout.php"
         class="flex items-center justify-center px-4 py-2.5 text-sm font-medium rounded-lg bg-gradient-to-r from-red-50 to-red-100 text-red-600 hover:from-red-100 hover:to-red-200 transition-all duration-200 shadow-sm hover:shadow-md">
        <i class="fas fa-sign-out-alt mr-2"></i>
        Logout
      </a>
      <div class="mt-3 text-center text-xs text-gray-500">
        <p>IMPERIUM &copy; <?= date('Y') ?></p>
        <p class="mt-1">v2.1.0</p>
      </div>
    </div>
  </aside>

  <!-- Close sidebar when clicking outside (for mobile) -->
  <div x-show="mobileSidebarOpen" 
       @click="mobileSidebarOpen = false"
       class="fixed inset-0 z-30 lg:hidden"></div>
</section>