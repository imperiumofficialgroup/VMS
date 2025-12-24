<?php
$admin_id = 1; // assuming single admin
$unreadQuery = "SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND receiver_role = 'admin' AND is_read = 0";
$stmt = $conn->prepare($unreadQuery);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$unreadResult = $stmt->get_result()->fetch_assoc();
$unreadCount = $unreadResult['unread_count'] ?? 0;
?>
<script>
// Alpine store initialization for sidebar state
document.addEventListener('alpine:init', () => {
  Alpine.store('sidebar', {
    open: window.innerWidth >= 1024,
    toggle() { this.open = !this.open; }
  });

  // Close sidebar when clicking outside on mobile
  document.addEventListener('click', (e) => {
    if (window.innerWidth >= 1024) return;
    if (!e.target.closest('#sidebar') && !e.target.closest('[data-sidebar-toggle]')) {
      Alpine.store('sidebar').open = false;
    }
  });
});
</script>

<style>
.sidebar-container {
  transition: transform 0.3s ease-in-out;
}
.sidebar-link {
  transition: all 0.2s ease;
}
.sidebar-link:hover {
  background-color: #f9fafb;
  color: #2563eb;
}
.sidebar-link-icon {
  transition: color 0.2s ease;
}
.sidebar-section-title {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  color: #9ca3af;
  margin-top: 1rem;
  margin-bottom: 0.25rem;
  padding-left: 0.75rem;
}
</style>

<!-- Mobile Header -->
<div class="lg:hidden fixed w-full bg-white shadow-sm z-10" x-data>
  <div class="flex items-center justify-between p-4">
    <div class="flex items-center space-x-2">
      <button data-sidebar-toggle @click="$store.sidebar.toggle()" class="text-gray-500 focus:outline-none">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
      </button>
      <h1 class="text-lg font-semibold text-gray-800">Dashboard</h1>
    </div>
    <div class="flex items-center space-x-2">
      <span class="text-sm text-gray-600"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? ''); ?></span>
      <img src="admin.jpg" alt="Admin" class="w-8 h-8 rounded-full">
    </div>
  </div>
</div>

<!-- Sidebar -->
<aside 
  x-data 
  class="fixed inset-0 z-50 lg:z-20 lg:inset-y-0 lg:left-0 bg-white shadow-xl transform sidebar-container lg:w-64"
  :class="{'-translate-x-full': !$store.sidebar.open, 'translate-x-0': $store.sidebar.open}"
  style="height: 100vh;"
  id="sidebar"
>
  <div class="flex flex-col h-full">
    <!-- Header -->
    <div class="flex items-center justify-between p-4 border-b border-gray-100">
      <div class="flex items-center space-x-3">
        <div class="w-10 h-10 rounded-lg flex items-center justify-center shadow-sm">
          <img src="admin.jpg">
        </div>
        <h1 class="text-xl font-bold text-gray-800">Admin Panel</h1>
      </div>
      <button @click="$store.sidebar.open = false"
              class="lg:hidden p-1 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd"
                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293
                4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293
                4.293a1 1 0 01-1.414-1.414L8.586 10 4.293
                5.707a1 1 0 010-1.414z"
                clip-rule="evenodd"/>
        </svg>
      </button>
    </div>

    <!-- Menu -->
    <nav class="flex-1 px-4 overflow-y-auto">
  <ul class="space-y-1">

    <!-- Dashboard -->
    <p class="sidebar-section-title">Dashboard</p>
    <li>
      <a href="../admin/dashboard.php" class="flex items-center p-3 rounded-lg text-gray-700 sidebar-link group">
        <i class="fas fa-tachometer-alt text-gray-400 group-hover:text-blue-500"></i>
        <span class="ml-3">Overview</span>
      </a>
    </li>

    <!-- Divider -->
    <hr class="my-3 border-gray-200">

    <!-- Volunteers -->
    <p class="sidebar-section-title">Volunteers</p>
    <li>
      <a href="../admin/view_volunteers.php" class="flex items-center p-3 rounded-lg text-gray-700 sidebar-link group">
        <i class="fas fa-users text-gray-400 group-hover:text-blue-500"></i>
        <span class="ml-3">All Volunteers</span>
      </a>
    </li>
    <li>
      <a href="../admin/add_volunteer.php" class="flex items-center p-3 rounded-lg text-gray-700 sidebar-link group">
        <i class="fas fa-user-plus text-gray-400 group-hover:text-blue-500"></i>
        <span class="ml-3">Add Volunteer</span>
      </a>
    </li>
    <li>
      <a href="../admin/admin_leaves.php" class="flex items-center p-3 rounded-lg text-gray-700 sidebar-link group">
        <i class="fas fa-calendar-alt text-gray-400 group-hover:text-blue-500"></i>
        <span class="ml-3">Leave Requests</span>
      </a>
    </li>

    <!-- Divider -->
    <hr class="my-3 border-gray-200">

    <!-- Events -->
    <p class="sidebar-section-title">Events</p>
    <li>
      <a href="../admin/view_events.php" class="flex items-center p-3 rounded-lg text-gray-700 sidebar-link group">
        <i class="fas fa-calendar-check text-gray-400 group-hover:text-blue-500"></i>
        <span class="ml-3">Manage Events</span>
      </a>
    </li>
    <li>
      <a href="../admin/add_event.php" class="flex items-center p-3 rounded-lg text-gray-700 sidebar-link group">
        <i class="fas fa-plus-circle text-gray-400 group-hover:text-blue-500"></i>
        <span class="ml-3">Add Event</span>
      </a>
    </li>
    <li>
      <a href="../admin/attendance_mark.php" class="flex items-center p-3 rounded-lg text-gray-700 sidebar-link group">
        <i class="fas fa-user-check text-gray-400 group-hover:text-blue-500"></i>
        <span class="ml-3">Mark Attendance</span>
      </a>
    </li>
    <li>
      <a href="../admin/view_attendance_summary.php" class="flex items-center p-3 rounded-lg text-gray-700 sidebar-link group">
        <i class="fas fa-clipboard-list text-gray-400 group-hover:text-blue-500"></i>
        <span class="ml-3">Attendance Summary</span>
      </a>
    </li>

    <!-- Divider -->
    <hr class="my-3 border-gray-200">

    <!-- Communication -->
    <p class="sidebar-section-title">Communication</p>
    <li class="relative">
      <a href="../chat/chat_select_volunteer.php" class="flex items-center p-3 rounded-lg text-gray-700 sidebar-link group">
        <i class="fas fa-comments text-gray-400 group-hover:text-blue-500"></i>
        <span class="ml-3">Messages</span>
        <?php if ($unreadCount > 0): ?>
          <span class="absolute top-1 right-1 bg-red-500 text-white text-xs px-2 rounded-full">
            <?= $unreadCount ?>
          </span>
        <?php endif; ?>
      </a>
    </li>
    <li>
      <a href="../admin/create_group.php" class="flex items-center p-3 rounded-lg text-gray-700 sidebar-link group">
        <i class="fas fa-users-cog text-gray-400 group-hover:text-blue-500"></i>
        <span class="ml-3">Create Group</span>
      </a>
    </li>
    <li>
      <a href="../admin/group_list.php" class="flex items-center p-3 rounded-lg text-gray-700 sidebar-link group">
        <i class="fas fa-layer-group text-gray-400 group-hover:text-blue-500"></i>
        <span class="ml-3">Groups List</span>
      </a>
    </li>
    <li>
      <a href="../admin/view_suggestions.php" class="flex items-center p-3 rounded-lg text-gray-700 sidebar-link group">
        <i class="fas fa-lightbulb text-gray-400 group-hover:text-blue-500"></i>
        <span class="ml-3">Volunteer Suggestions</span>
      </a>
    </li>

    <!-- Divider -->
    <hr class="my-3 border-gray-200">

    <!-- Reports -->
    <p class="sidebar-section-title">Reports</p>
    <li>
      <a href="../admin/report_list.php" class="flex items-center p-3 rounded-lg text-gray-700 sidebar-link group">
        <i class="fas fa-file-alt text-gray-400 group-hover:text-blue-500"></i>
        <span class="ml-3">Event Reports</span>
      </a>
    </li>

    <!-- Divider -->
    <hr class="my-3 border-gray-200">

    <!-- Gamified System -->
    <p class="sidebar-section-title">Gamified System</p>
    <li>
      <a href="../admin/add_tasks.php" class="flex items-center p-3 rounded-lg text-gray-700 sidebar-link group">
        <i class="fas fa-tasks text-gray-400 group-hover:text-blue-500"></i>
        <span class="ml-3">Tasks</span>
      </a>
    </li>
    <li>
      <a href="../admin/create_badge.php" class="flex items-center p-3 rounded-lg text-gray-700 sidebar-link group">
        <i class="fas fa-award text-gray-400 group-hover:text-blue-500"></i>
        <span class="ml-3">Add Badge</span>
      </a>
    </li>
    <li>
      <a href="../admin/products.php" class="flex items-center p-3 rounded-lg text-gray-700 sidebar-link group">
        <i class="fas fa-tshirt text-gray-400 group-hover:text-blue-500"></i>
        <span class="ml-3">Add Product</span>
      </a>
    </li>
     <li>
      <a href="../admin/orders.php" class="flex items-center p-3 rounded-lg text-gray-700 sidebar-link group">
        <i class="fas fa-hand text-gray-400 group-hover:text-blue-500"></i>
        <span class="ml-3">View Order</span>
      </a>
    </li>
     
  </ul>
</nav>


    <!-- Footer -->
    <div class="p-4 border-t border-gray-100">
      <a href="logout.php"
         class="flex items-center p-3 rounded-lg text-red-600 hover:bg-red-50 hover:text-red-700 transition group">
        <i class="fas fa-sign-out-alt text-red-500"></i>
        <span class="ml-3 font-medium">Logout</span>
      </a>
    </div>
  </div>
</aside>
