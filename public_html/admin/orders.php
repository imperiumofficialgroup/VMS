<?php
session_start();
include '../auth/db.php';

// Admin authentication check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Handle status updates with points management
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_status'])) {
        $order_id = intval($_POST['order_id']);
        $new_status = $conn->real_escape_string($_POST['status']);
        $old_status = $conn->real_escape_string($_POST['old_status'] ?? '');
        
        // If status is being changed to cancelled by admin, return points
        if ($new_status == 'cancelled' && $old_status != 'cancelled') {
            // Get order details
            $order_result = $conn->query("
                SELECT o.*, p.name as product_name 
                FROM orders o 
                JOIN products p ON o.product_id = p.id 
                WHERE o.id = $order_id
            ");
            
            if ($order_result->num_rows > 0) {
                $order = $order_result->fetch_assoc();
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Return points to volunteer
                    $return_points_query = "
                        INSERT INTO volunteer_points (volunteer_id, points, created_at, description) 
                        VALUES ({$order['volunteer_id']}, {$order['points_used']}, NOW(), 'Order Cancelled by Admin: {$order['product_name']}')
                    ";
                    $points_result = $conn->query($return_points_query);
                    if (!$points_result) {
                        throw new Exception("Points return failed: " . $conn->error);
                    }
                    
                    // Restore product stock
                    $restore_stock_query = "
                        UPDATE products 
                        SET stock_quantity = stock_quantity + 1 
                        WHERE id = {$order['product_id']}
                    ";
                    $stock_result = $conn->query($restore_stock_query);
                    if (!$stock_result) {
                        throw new Exception("Stock restoration failed: " . $conn->error);
                    }
                    
                    // Update order status to cancelled
                    $cancel_order_query = "
                        UPDATE orders 
                        SET status = 'cancelled', updated_at = NOW(), cancelled_by = 'admin' 
                        WHERE id = $order_id
                    ";
                    $cancel_result = $conn->query($cancel_order_query);
                    if (!$cancel_result) {
                        throw new Exception("Order cancellation failed: " . $conn->error);
                    }
                    
                    $conn->commit();
                    
                    $_SESSION['success'] = "Order cancelled successfully! {$order['points_used']} points have been returned to the volunteer.";
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = "Error cancelling order: " . $e->getMessage();
                }
            }
        } else {
            // Regular status update (not cancellation)
            $conn->query("
                UPDATE orders 
                SET status = '$new_status', updated_at = NOW() 
                WHERE id = $order_id
            ");
            
            if ($conn->affected_rows > 0) {
                $_SESSION['success'] = "Order status updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update order status.";
            }
        }
        
        header("Location: orders.php");
        exit;
    }
    
    // Bulk status update with points management
    if (isset($_POST['bulk_update'])) {
        $new_status = $conn->real_escape_string($_POST['bulk_status']);
        $selected_orders = $_POST['selected_orders'] ?? [];
        
        if (!empty($selected_orders)) {
            $order_ids = implode(',', array_map('intval', $selected_orders));
            
            // If bulk cancelling, handle points return
            if ($new_status == 'cancelled') {
                // Get all orders to be cancelled
                $orders_result = $conn->query("
                    SELECT o.*, p.name as product_name 
                    FROM orders o 
                    JOIN products p ON o.product_id = p.id 
                    WHERE o.id IN ($order_ids) AND o.status != 'cancelled'
                ");
                
                $conn->begin_transaction();
                $cancelled_count = 0;
                
                try {
                    while ($order = $orders_result->fetch_assoc()) {
                        // Return points for each order
                        $conn->query("
                            INSERT INTO volunteer_points (volunteer_id, points, created_at, description) 
                            VALUES ({$order['volunteer_id']}, {$order['points_used']}, NOW(), 'Order Cancelled by Admin: {$order['product_name']}')
                        ");
                        
                        // Restore stock for each order
                        $conn->query("
                            UPDATE products 
                            SET stock_quantity = stock_quantity + 1 
                            WHERE id = {$order['product_id']}
                        ");
                        
                        $cancelled_count++;
                    }
                    
                    // Update all orders status
                    $conn->query("
                        UPDATE orders 
                        SET status = '$new_status', updated_at = NOW(), cancelled_by = 'admin' 
                        WHERE id IN ($order_ids)
                    ");
                    
                    $conn->commit();
                    $_SESSION['success'] = "Successfully cancelled $cancelled_count orders and returned points!";
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = "Error cancelling orders: " . $e->getMessage();
                }
            } else {
                // Regular bulk status update
                $conn->query("
                    UPDATE orders 
                    SET status = '$new_status', updated_at = NOW() 
                    WHERE id IN ($order_ids)
                ");
                
                $_SESSION['success'] = "Updated " . $conn->affected_rows . " orders to " . $new_status . " status!";
            }
        } else {
            $_SESSION['error'] = "No orders selected for bulk update.";
        }
        
        header("Location: orders.php");
        exit;
    }
    
    // Handle direct cancellation with reason (from cancel button)
    if (isset($_POST['cancel_order'])) {
        $order_id = intval($_POST['order_id']);
        $cancellation_reason = $conn->real_escape_string($_POST['cancellation_reason'] ?? 'No reason provided');
        
        // Get order details
        $order_result = $conn->query("
            SELECT o.*, p.name as product_name 
            FROM orders o 
            JOIN products p ON o.product_id = p.id 
            WHERE o.id = $order_id AND o.status != 'cancelled'
        ");
        
        if ($order_result->num_rows > 0) {
            $order = $order_result->fetch_assoc();
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Return points to volunteer
                $return_points_query = "
                    INSERT INTO volunteer_points (volunteer_id, points, created_at, description) 
                    VALUES ({$order['volunteer_id']}, {$order['points_used']}, NOW(), 'Order Cancelled by Admin: {$order['product_name']} - Reason: $cancellation_reason')
                ";
                $points_result = $conn->query($return_points_query);
                if (!$points_result) {
                    throw new Exception("Points return failed: " . $conn->error);
                }
                
                // Restore product stock
                $restore_stock_query = "
                    UPDATE products 
                    SET stock_quantity = stock_quantity + 1 
                    WHERE id = {$order['product_id']}
                ";
                $stock_result = $conn->query($restore_stock_query);
                if (!$stock_result) {
                    throw new Exception("Stock restoration failed: " . $conn->error);
                }
                
                // Update order status to cancelled
                $cancel_order_query = "
                    UPDATE orders 
                    SET status = 'cancelled', updated_at = NOW(), cancelled_by = 'admin', cancellation_reason = '$cancellation_reason' 
                    WHERE id = $order_id
                ";
                $cancel_result = $conn->query($cancel_order_query);
                if (!$cancel_result) {
                    throw new Exception("Order cancellation failed: " . $conn->error);
                }
                
                $conn->commit();
                
                $_SESSION['success'] = "Order cancelled successfully! {$order['points_used']} points have been returned to the volunteer.";
                
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "Error cancelling order: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Order not found or already cancelled.";
        }
        
        header("Location: orders.php");
        exit;
    }
}

// First, let's alter the orders table to add cancelled_by and cancellation_reason columns if they don't exist
$check_columns = $conn->query("SHOW COLUMNS FROM orders LIKE 'cancelled_by'");
if ($check_columns->num_rows == 0) {
    $conn->query("ALTER TABLE orders ADD COLUMN cancelled_by ENUM('user', 'admin') DEFAULT NULL AFTER status");
    $conn->query("ALTER TABLE orders ADD COLUMN cancellation_reason TEXT DEFAULT NULL AFTER cancelled_by");
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query with filters
$where_conditions = ["1=1"];
if ($status_filter != 'all') {
    $where_conditions[] = "o.status = '" . $conn->real_escape_string($status_filter) . "'";
}
if (!empty($date_from)) {
    $where_conditions[] = "DATE(o.created_at) >= '" . $conn->real_escape_string($date_from) . "'";
}
if (!empty($date_to)) {
    $where_conditions[] = "DATE(o.created_at) <= '" . $conn->real_escape_string($date_to) . "'";
}

$where_clause = implode(' AND ', $where_conditions);

// Get orders with volunteer and product details
$orders_result = $conn->query("
    SELECT 
        o.*,
        v.full_name as volunteer_name,
        v.email as volunteer_email,
        v.phone as volunteer_phone,
        p.name as product_name,
        p.points_cost,
        p.image_path,
        CASE 
            WHEN o.cancelled_by = 'user' THEN 'Cancelled by User'
            WHEN o.cancelled_by = 'admin' THEN 'Cancelled by Admin'
            ELSE ''
        END as cancellation_info
    FROM orders o
    JOIN volunteers v ON o.volunteer_id = v.id
    JOIN products p ON o.product_id = p.id
    WHERE $where_clause
    ORDER BY o.created_at DESC
");

$orders = [];
while ($order = $orders_result->fetch_assoc()) {
    $orders[] = $order;
}

// Get statistics
$stats_result = $conn->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(points_used) as total_points_redeemed,
        COUNT(DISTINCT volunteer_id) as unique_volunteers,
        status,
        COUNT(*) as status_count
    FROM orders 
    GROUP BY status WITH ROLLUP
");

$stats = [];
$status_counts = [];
while ($stat = $stats_result->fetch_assoc()) {
    if ($stat['status'] === null) {
        $stats['total_orders'] = $stat['total_orders'];
        $stats['total_points_redeemed'] = $stat['total_points_redeemed'];
        $stats['unique_volunteers'] = $stat['unique_volunteers'];
    } else {
        $status_counts[$stat['status']] = $stat['status_count'];
    }
}
?>
<?php include 'sidebar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <script>
        tailwind.config = {
            theme: {
                extend: {
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
    <style>
        .order-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #E5E7EB;
            transition: all 0.3s ease;
        }
        
        .order-card:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(93, 173, 226, 0.3);
        }
        
        .btn-secondary {
            background: #6B7280;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: #4B5563;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending { 
            background: linear-gradient(135deg, #FEF3CD 0%, #FDE68A 100%);
            color: #92400E;
            border: 1px solid #FCD34D;
        }
        
        .status-confirmed { 
            background: linear-gradient(135deg, #CCE5FF 0%, #93C5FD 100%);
            color: #1E40AF;
            border: 1px solid #60A5FA;
        }
        
        .status-shipped { 
            background: linear-gradient(135deg, #E0C8F9 0%, #C4B5FD 100%);
            color: #5B21B6;
            border: 1px solid #8B5CF6;
        }
        
        .status-delivered { 
            background: linear-gradient(135deg, #D4EDDA 0%, #86EFAC 100%);
            color: #065F46;
            border: 1px solid #10B981;
        }
        
        .status-cancelled { 
            background: linear-gradient(135deg, #F8D7DA 0%, #FCA5A5 100%);
            color: #991B1B;
            border: 1px solid #EF4444;
        }
        
        .cancellation-info {
            font-size: 0.7rem;
            color: #6B7280;
            font-style: italic;
            margin-top: 2px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #E5E7EB;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
        }
        
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-background">
    <div class="page-container lg:ml-72 pt-6 px-4">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mt-12 md:mt-0 mb-8">
            <div class="text-center md:text-left mb-4 md:mb-0">
                <h1 class="text-2xl sm:text-3xl font-bold text-text mb-2">Order Management</h1>
                <p class="text-text/70">Manage and track all product redemptions</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="products.php" class="btn-primary flex items-center">
                    <i class="fas fa-box mr-2"></i>Manage Products
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <p class="text-green-700"><?= $_SESSION['success'] ?></p>
                </div>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <p class="text-red-700"><?= $_SESSION['error'] ?></p>
                </div>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                        <i class="fas fa-shopping-cart text-primary text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-text/70">Total Orders</p>
                        <p class="text-2xl font-bold text-text"><?= $stats['total_orders'] ?? 0 ?></p>
                    </div>
                </div>
            </div>

            <div class="stat-card p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mr-4">
                        <i class="fas fa-star text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-text/70">Points Redeemed</p>
                        <p class="text-2xl font-bold text-text"><?= number_format($stats['total_points_redeemed'] ?? 0) ?></p>
                    </div>
                </div>
            </div>

            <div class="stat-card p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center mr-4">
                        <i class="fas fa-users text-accent text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-text/70">Unique Volunteers</p>
                        <p class="text-2xl font-bold text-text"><?= $stats['unique_volunteers'] ?? 0 ?></p>
                    </div>
                </div>
            </div>

            <div class="stat-card p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center mr-4">
                        <i class="fas fa-clock text-orange-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-text/70">Pending Orders</p>
                        <p class="text-2xl font-bold text-text"><?= $status_counts['pending'] ?? 0 ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Bulk Actions -->
        <div class="order-card p-6 mb-6">
            <h3 class="text-lg font-semibold text-text mb-4 flex items-center">
                <i class="fas fa-filter mr-2 text-accent"></i>
                Filters & Bulk Actions
            </h3>
            
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-text mb-2">Status Filter</label>
                    <select name="status" class="w-full p-3 border border-subtle rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="confirmed" <?= $status_filter == 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="shipped" <?= $status_filter == 'shipped' ? 'selected' : '' ?>>Shipped</option>
                        <option value="delivered" <?= $status_filter == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="cancelled" <?= $status_filter == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-text mb-2">Date From</label>
                    <input type="date" name="date_from" value="<?= $date_from ?>" 
                           class="w-full p-3 border border-subtle rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-text mb-2">Date To</label>
                    <input type="date" name="date_to" value="<?= $date_to ?>" 
                           class="w-full p-3 border border-subtle rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="btn-primary w-full flex items-center justify-center">
                        <i class="fas fa-filter mr-2"></i>Apply Filters
                    </button>
                </div>
            </form>

            <!-- Bulk Actions -->
            <form method="POST" class="flex flex-col sm:flex-row items-start sm:items-center space-y-3 sm:space-y-0 sm:space-x-4">
                <select name="bulk_status" class="flex-1 p-3 border border-subtle rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="shipped">Shipped</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancel Selected</option>
                </select>
                <button type="submit" name="bulk_update" class="btn-primary flex items-center">
                    <i class="fas fa-sync mr-2"></i>Update Selected
                </button>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="order-card overflow-hidden">
            <div class="p-6 border-b border-subtle">
                <h3 class="text-lg font-semibold text-text flex items-center">
                    <i class="fas fa-list mr-2 text-accent"></i>
                    Orders List
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-background border-b border-subtle">
                            <th class="p-4 text-left">
                                <input type="checkbox" id="selectAll" class="rounded border-subtle">
                            </th>
                            <th class="p-4 text-left text-sm font-semibold text-text">Order Details</th>
                            <th class="p-4 text-left text-sm font-semibold text-text">Volunteer</th>
                            <th class="p-4 text-left text-sm font-semibold text-text">Product</th>
                            <th class="p-4 text-left text-sm font-semibold text-text">Points</th>
                            <th class="p-4 text-left text-sm font-semibold text-text">Date</th>
                            <th class="p-4 text-left text-sm font-semibold text-text">Status</th>
                            <th class="p-4 text-left text-sm font-semibold text-text">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8" class="p-8 text-center text-text/50">
                                    <i class="fas fa-inbox text-4xl mb-3 text-text/20"></i>
                                    <p class="text-lg">No orders found</p>
                                    <p class="text-sm mt-1">Try adjusting your filters</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($orders as $order): ?>
                            <tr class="border-b border-subtle hover:bg-background/50 transition-colors">
                                <td class="p-4">
                                    <input type="checkbox" name="selected_orders[]" value="<?= $order['id'] ?>" class="order-checkbox rounded border-subtle">
                                </td>
                                <td class="p-4">
                                    <div class="font-semibold text-text">#<?= $order['id'] ?></div>
                                </td>
                                <td class="p-4">
                                    <div class="font-medium text-text"><?= htmlspecialchars($order['volunteer_name']) ?></div>
                                    <div class="text-sm text-text/60"><?= htmlspecialchars($order['volunteer_email']) ?></div>
                                    <div class="text-sm text-text/60"><?= htmlspecialchars($order['volunteer_phone']) ?></div>
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center">
                                        <img src="../uploads/products/<?= htmlspecialchars($order['image_path']) ?>" 
                                             alt="<?= htmlspecialchars($order['product_name']) ?>" 
                                             class="w-12 h-12 rounded-lg object-cover mr-3 border border-subtle"
                                             onerror="this.src='../uploads/products/default.jpg'">
                                        <div>
                                            <div class="font-medium text-text"><?= htmlspecialchars($order['product_name']) ?></div>
                                            <div class="text-sm text-text/60">Cost: <?= number_format($order['points_cost']) ?> pts</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <span class="font-bold text-red-600">-<?= number_format($order['points_used']) ?></span>
                                </td>
                                <td class="p-4">
                                    <div class="text-sm text-text"><?= date('M j, Y', strtotime($order['created_at'])) ?></div>
                                    <div class="text-xs text-text/60"><?= date('g:i A', strtotime($order['created_at'])) ?></div>
                                </td>
                                <td class="p-4">
                                    <span class="status-badge status-<?= $order['status'] ?>">
                                        <?= ucfirst($order['status']) ?>
                                    </span>
                                    <?php if ($order['status'] == 'cancelled' && !empty($order['cancellation_info'])): ?>
                                        <div class="cancellation-info">
                                            <?= htmlspecialchars($order['cancellation_info']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <div class="flex space-x-2">
                                        <!-- Quick Status Update Form -->
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                            <input type="hidden" name="old_status" value="<?= $order['status'] ?>">
                                            <select name="status" onchange="this.form.submit()" 
                                                    class="text-xs border border-subtle rounded p-1 focus:ring-2 focus:ring-primary">
                                                <option value="pending" <?= $order['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="confirmed" <?= $order['status'] == 'confirmed' ? 'selected' : '' ?>>Confirm</option>
                                                <option value="shipped" <?= $order['status'] == 'shipped' ? 'selected' : '' ?>>Ship</option>
                                                <option value="delivered" <?= $order['status'] == 'delivered' ? 'selected' : '' ?>>Deliver</option>
                                                <option value="cancelled" <?= $order['status'] == 'cancelled' ? 'selected' : '' ?>>Cancel</option>
                                            </select>
                                            <button type="submit" name="update_status" class="hidden">Update</button>
                                        </form>
                                        
                                        <!-- Cancel Button for non-cancelled orders -->
                                        <?php if ($order['status'] != 'cancelled'): ?>
                                            <button onclick="openCancelModal(<?= htmlspecialchars(json_encode($order)) ?>)" 
                                                    class="btn-danger flex items-center text-xs p-2">
                                                <i class="fas fa-ban mr-1"></i> Cancel
                                            </button>
                                        <?php endif; ?>
                                        
                                        <!-- View Details Button -->
                                        <button onclick="viewOrderDetails(<?= htmlspecialchars(json_encode($order)) ?>)" 
                                                class="bg-blue-100 text-blue-600 p-2 rounded-lg hover:bg-blue-200 transition flex items-center">
                                            <i class="fas fa-eye text-sm"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Order Details Modal -->
        <div id="orderModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4 hidden">
            <div class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto order-card">
                <div class="p-6 border-b border-subtle">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-bold text-text">Order Details</h3>
                        <button onclick="closeOrderModal()" class="text-text/60 hover:text-text transition-colors">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <div id="orderDetailsContent">
                        <!-- Content will be loaded by JavaScript -->
                    </div>
                </div>
                
                <div class="p-6 border-t border-subtle flex justify-end">
                    <button onclick="closeOrderModal()" class="btn-secondary">
                        <i class="fas fa-times mr-2"></i>Close
                    </button>
                </div>
            </div>
        </div>

        <!-- Cancel Order Modal -->
        <div id="cancelModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4 hidden">
            <div class="bg-white rounded-xl w-full max-w-md max-h-[90vh] overflow-y-auto order-card">
                <div class="p-6 border-b border-subtle">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-bold text-text">Cancel Order</h3>
                        <button onclick="closeCancelModal()" class="text-text/60 hover:text-text transition-colors">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <div id="cancelOrderContent">
                        <!-- Content will be loaded by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Select All functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // View Order Details
        function viewOrderDetails(order) {
            const modal = document.getElementById('orderModal');
            const content = document.getElementById('orderDetailsContent');
            
            const statusColors = {
                pending: 'status-pending',
                confirmed: 'status-confirmed', 
                shipped: 'status-shipped',
                delivered: 'status-delivered',
                cancelled: 'status-cancelled'
            };
            
            content.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Order Information -->
                    <div>
                        <h4 class="font-semibold text-text mb-3 flex items-center">
                            <i class="fas fa-receipt mr-2 text-accent"></i>
                            Order Information
                        </h4>
                        <div class="space-y-3 bg-background p-4 rounded-lg">
                            <div class="flex justify-between items-center">
                                <span class="text-text/70">Order ID:</span>
                                <span class="font-semibold text-text">#${order.id}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-text/70">Date:</span>
                                <span class="text-text">${new Date(order.created_at).toLocaleDateString()}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-text/70">Status:</span>
                                <span class="status-badge ${statusColors[order.status]}">
                                    ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-text/70">Points Used:</span>
                                <span class="font-bold text-red-600">-${order.points_used.toLocaleString()}</span>
                            </div>
                            ${order.cancellation_info ? `
                                <div class="flex justify-between items-center">
                                    <span class="text-text/70">Cancellation:</span>
                                    <span class="text-text text-sm">${order.cancellation_info}</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                    
                    <!-- Volunteer Information -->
                    <div>
                        <h4 class="font-semibold text-text mb-3 flex items-center">
                            <i class="fas fa-user mr-2 text-accent"></i>
                            Volunteer Details
                        </h4>
                        <div class="space-y-3 bg-background p-4 rounded-lg">
                            <div class="flex justify-between items-center">
                                <span class="text-text/70">Name:</span>
                                <span class="text-text">${order.volunteer_name}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-text/70">Email:</span>
                                <span class="text-text">${order.volunteer_email}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-text/70">Phone:</span>
                                <span class="text-text">${order.volunteer_phone || 'N/A'}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Product Information -->
                    <div class="md:col-span-2">
                        <h4 class="font-semibold text-text mb-3 flex items-center">
                            <i class="fas fa-gift mr-2 text-accent"></i>
                            Product Details
                        </h4>
                        <div class="flex items-center space-x-4 p-4 bg-background rounded-lg">
                            <img src="../uploads/products/${order.image_path}" 
                                 alt="${order.product_name}" 
                                 class="w-16 h-16 rounded-lg object-cover border border-subtle"
                                 onerror="this.src='../uploads/products/default.jpg'">
                            <div>
                                <h5 class="font-semibold text-lg text-text">${order.product_name}</h5>
                                <p class="text-text/60">Points Cost: ${order.points_cost.toLocaleString()} pts</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Shipping Address -->
                    <div class="md:col-span-2">
                        <h4 class="font-semibold text-text mb-3 flex items-center">
                            <i class="fas fa-truck mr-2 text-accent"></i>
                            Shipping Address
                        </h4>
                        <div class="p-4 bg-background rounded-lg">
                            <p class="text-text">${order.shipping_address || 'No shipping address provided'}</p>
                        </div>
                    </div>
                </div>
                
                <!-- Status Update Form -->
                <div class="mt-6 pt-6 border-t border-subtle">
                    <h4 class="font-semibold text-text mb-3 flex items-center">
                        <i class="fas fa-edit mr-2 text-accent"></i>
                        Update Status
                    </h4>
                    <form method="POST" class="flex flex-col sm:flex-row items-start sm:items-center space-y-3 sm:space-y-0 sm:space-x-4">
                        <input type="hidden" name="order_id" value="${order.id}">
                        <input type="hidden" name="old_status" value="${order.status}">
                        <select name="status" class="flex-1 p-3 border border-subtle rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="pending" ${order.status === 'pending' ? 'selected' : ''}>Pending</option>
                            <option value="confirmed" ${order.status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                            <option value="shipped" ${order.status === 'shipped' ? 'selected' : ''}>Shipped</option>
                            <option value="delivered" ${order.status === 'delivered' ? 'selected' : ''}>Delivered</option>
                            <option value="cancelled" ${order.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                        </select>
                        <button type="submit" name="update_status" class="btn-primary">
                            <i class="fas fa-save mr-2"></i>Update Status
                        </button>
                    </form>
                </div>
            `;
            
            modal.classList.remove('hidden');
        }

        function closeOrderModal() {
            document.getElementById('orderModal').classList.add('hidden');
        }

        // Cancel Order Modal Functions
        function openCancelModal(order) {
            const modal = document.getElementById('cancelModal');
            const content = document.getElementById('cancelOrderContent');
            
            content.innerHTML = `
                <div class="space-y-4">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-yellow-600 mr-3 text-xl"></i>
                            <div>
                                <h4 class="font-semibold text-yellow-800">Cancel Order #${order.id}</h4>
                                <p class="text-yellow-700 text-sm mt-1">
                                    This will cancel the order and return ${order.points_used} points to the volunteer.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4 p-4 bg-background rounded-lg">
                        <img src="../uploads/products/${order.image_path}" 
                             alt="${order.product_name}" 
                             class="w-12 h-12 rounded-lg object-cover border border-subtle"
                             onerror="this.src='../uploads/products/default.jpg'">
                        <div>
                            <h4 class="font-semibold text-text">${order.product_name}</h4>
                            <p class="text-text/60 text-sm">Volunteer: ${order.volunteer_name}</p>
                            <p class="text-text/60 text-sm">Points: ${order.points_used.toLocaleString()}</p>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-text">Cancellation Reason (Optional)</label>
                        <textarea id="cancellationReason" 
                                  placeholder="Enter reason for cancellation..."
                                  class="w-full p-3 border border-subtle rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                  rows="3"></textarea>
                    </div>
                </div>
                
                <div class="mt-6 pt-6 border-t border-subtle flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-4">
                    <button onclick="closeCancelModal()" class="btn-secondary flex-1">
                        <i class="fas fa-arrow-left mr-2"></i> Keep Order
                    </button>
                    <form method="POST" class="flex-1">
                        <input type="hidden" name="order_id" value="${order.id}">
                        <input type="hidden" name="cancellation_reason" id="reasonInput">
                        <button type="submit" 
                                name="cancel_order" 
                                onclick="document.getElementById('reasonInput').value = document.getElementById('cancellationReason').value"
                                class="btn-danger w-full flex items-center justify-center">
                            <i class="fas fa-ban mr-2"></i> Confirm Cancellation
                        </button>
                    </form>
                </div>
            `;
            
            modal.classList.remove('hidden');
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        document.getElementById('orderModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeOrderModal();
            }
        });

        document.getElementById('cancelModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCancelModal();
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeOrderModal();
                closeCancelModal();
            }
        });

        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.bg-green-50, .bg-red-50');
            messages.forEach(msg => {
                msg.style.transition = 'opacity 0.5s ease';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>