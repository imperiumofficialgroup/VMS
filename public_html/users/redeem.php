<?php
session_start();
include '../auth/db.php';

if (!isset($_SESSION['volunteer_id'])) {
    header("Location: ../login.php");
    exit;
}

$volunteer_id = $_SESSION['volunteer_id'];

// Get volunteer details and total points
$volunteer_result = $conn->query("
    SELECT v.*, COALESCE(SUM(vp.points), 0) as total_points 
    FROM volunteers v 
    LEFT JOIN volunteer_points vp ON v.id = vp.volunteer_id 
    WHERE v.id = $volunteer_id 
    GROUP BY v.id
");
$volunteer = $volunteer_result->fetch_assoc();
$total_points = $volunteer['total_points'];

// Handle redemption
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['redeem_product'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity'] ?? 1);
    
    error_log("Redemption attempt: volunteer_id=$volunteer_id, product_id=$product_id, quantity=$quantity");
    
    // Validate product
    $product_result = $conn->query("
        SELECT * FROM products 
        WHERE id = $product_id 
        AND active = 1 
        AND stock_quantity >= $quantity
    ");
    
    if ($product_result->num_rows > 0) {
        $product = $product_result->fetch_assoc();
        $total_cost = $product['points_cost'] * $quantity;
        
        error_log("Product found: {$product['name']}, cost=$total_cost, user_points=$total_points");
        
        // Check if user has enough points
        if ($total_points >= $total_cost) {
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Deduct points (insert negative points)
                $points_query = "
                    INSERT INTO volunteer_points (volunteer_id, points, created_at, description) 
                    VALUES ($volunteer_id, -$total_cost, NOW(), 'Redeemed: {$product['name']}')
                ";
                error_log("Points query: $points_query");
                
                $points_result = $conn->query($points_query);
                if (!$points_result) {
                    throw new Exception("Points insertion failed: " . $conn->error);
                }
                error_log("Points deducted successfully");
                
                // Create order
                $shipping_address = $conn->real_escape_string($volunteer['address']);
                $order_query = "
                    INSERT INTO orders (volunteer_id, product_id, points_used, shipping_address, status) 
                    VALUES ($volunteer_id, $product_id, $total_cost, '$shipping_address', 'pending')
                ";
                error_log("Order query: $order_query");
                
                $order_result = $conn->query($order_query);
                if (!$order_result) {
                    throw new Exception("Order creation failed: " . $conn->error);
                }
                error_log("Order created successfully");
                
                // Update product stock
                $stock_query = "
                    UPDATE products 
                    SET stock_quantity = stock_quantity - $quantity 
                    WHERE id = $product_id
                ";
                error_log("Stock query: $stock_query");
                
                $stock_result = $conn->query($stock_query);
                if (!$stock_result) {
                    throw new Exception("Stock update failed: " . $conn->error);
                }
                error_log("Stock updated successfully");
                
                $conn->commit();
                
                $_SESSION['success_message'] = "Successfully redeemed {$product['name']} for {$total_cost} points!";
                error_log("Redemption completed successfully");
                header("Location: redeem.php");
                exit;
                
            } catch (Exception $e) {
                $conn->rollback();
                error_log("REDEMPTION ERROR: " . $e->getMessage());
                $_SESSION['error_message'] = "Error processing redemption: " . $e->getMessage();
            }
            
        } else {
            $_SESSION['error_message'] = "Insufficient points. You need {$total_cost} points but only have {$total_points}.";
        }
    } else {
        $_SESSION['error_message'] = "Product not available or out of stock.";
    }
}

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_order'])) {
    $order_id = intval($_POST['order_id']);
    
    // Check if order exists and belongs to the volunteer
    $order_check = $conn->query("
        SELECT o.*, p.name as product_name 
        FROM orders o 
        JOIN products p ON o.product_id = p.id 
        WHERE o.id = $order_id AND o.volunteer_id = $volunteer_id AND o.status = 'pending'
    ");
    
    if ($order_check->num_rows > 0) {
        $order = $order_check->fetch_assoc();
        
        // Start transaction for cancellation
        $conn->begin_transaction();
        
        try {
            // Return points to volunteer
            $return_points_query = "
                INSERT INTO volunteer_points (volunteer_id, points, created_at, description) 
                VALUES ($volunteer_id, {$order['points_used']}, NOW(), 'Order Cancelled: {$order['product_name']}')
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
                SET status = 'cancelled', updated_at = NOW() 
                WHERE id = $order_id
            ";
            $cancel_result = $conn->query($cancel_order_query);
            if (!$cancel_result) {
                throw new Exception("Order cancellation failed: " . $conn->error);
            }
            
            $conn->commit();
            
            $_SESSION['success_message'] = "Order cancelled successfully! {$order['points_used']} points have been returned to your account.";
            header("Location: redeem.php");
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = "Error cancelling order: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Order not found or cannot be cancelled. Only pending orders can be cancelled.";
    }
}

// Get available products
$products_result = $conn->query("
    SELECT * FROM products 
    WHERE active = 1 
    AND stock_quantity > 0 
    ORDER BY points_cost ASC
");
$products = [];
while ($product = $products_result->fetch_assoc()) {
    $products[] = $product;
}

// Get redemption history with cancellation eligibility
$history_result = $conn->query("
    SELECT o.*, p.name as product_name, p.image_path,
           CASE 
               WHEN o.status = 'pending' THEN 1 
               ELSE 0 
           END as can_cancel
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    WHERE o.volunteer_id = $volunteer_id 
    ORDER BY o.created_at DESC 
    LIMIT 10
");
$redemption_history = [];
while ($order = $history_result->fetch_assoc()) {
    $redemption_history[] = $order;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redeem Points | VMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
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
        .product-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #E5E7EB;
            transition: all 0.3s ease;
        }
        
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .points-display {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            border-radius: 16px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(93, 173, 226, 0.3);
        }
        
        .btn-primary:disabled {
            background: #D5D8DC;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: #6B7280;
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: #4B5563;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3);
        }
        
        .tab-active {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(93, 173, 226, 0.3);
        }
        
        .tab-inactive {
            background: white;
            color: #6B7280;
            border: 1px solid #E5E7EB;
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
        
        .info-note {
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }
        
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-background">
    <?php include 'sidebar.php'; ?>

    <div class="page-container lg:ml-72 pt-6 px-4" x-data="redeemPage()">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 mt-12 md:mt-0">
            <div class="text-center md:text-left mb-4 md:mb-0">
                <h1 class="text-2xl sm:text-3xl font-bold text-text mb-2">Redeem Your Points</h1>
                <p class="text-text/70">Exchange your hard-earned points for exciting rewards</p>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <p class="text-green-700"><?= $_SESSION['success_message'] ?></p>
                </div>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <p class="text-red-700"><?= $_SESSION['error_message'] ?></p>
                </div>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Points Display -->
        <div class="points-display p-6 mb-8">
            <div class="flex flex-col md:flex-row items-center justify-between">
                <div class="text-center md:text-left mb-4 md:mb-0">
                    <h2 class="text-xl font-bold mb-2">Your Available Points</h2>
                    <p class="text-3xl font-bold"><?= number_format($total_points) ?> points</p>
                    <p class="opacity-90 mt-2">Earn more points by completing tasks and attending events</p>
                </div>
                <div class="flex items-center">
                    <i class="fas fa-gift text-4xl mr-4 opacity-80"></i>
                    <div class="text-center">
                        <p class="text-sm opacity-90">Ready to redeem</p>
                        <p class="text-lg font-semibold">Choose your reward!</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Information Note -->
        <div class="info-note mb-8">
            <div class="flex items-start">
                <i class="fas fa-info-circle text-xl mr-3 mt-1"></i>
                <div>
                    <h3 class="font-bold text-lg mb-2">Important Information</h3>
                    <ul class="text-sm space-y-1 opacity-95">
                        <li>• You can cancel pending orders anytime before they are confirmed</li>
                        <li>• Once an order is confirmed, it cannot be cancelled</li>
                        <li>• Cancelled orders will automatically return your points</li>
                        <li>• Points are deducted immediately when you place an order</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- View Toggle -->
        <div class="flex space-x-2 mb-8 bg-white p-2 rounded-xl shadow-sm border border-subtle w-fit mx-auto">
            <button @click="activeTab = 'rewards'" 
                    :class="activeTab === 'rewards' ? 'tab-active' : 'tab-inactive'"
                    class="px-6 py-3 rounded-lg font-semibold transition-all duration-300 flex items-center">
                <i class="fas fa-gifts mr-2"></i>
                Available Rewards
            </button>
            <button @click="activeTab = 'history'" 
                    :class="activeTab === 'history' ? 'tab-active' : 'tab-inactive'"
                    class="px-6 py-3 rounded-lg font-semibold transition-all duration-300 flex items-center">
                <i class="fas fa-history mr-2"></i>
                Redemption History
            </button>
        </div>

        <!-- Available Rewards Section -->
        <div x-show="activeTab === 'rewards'" x-cloak x-transition>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach($products as $product): 
                    $can_afford = $total_points >= $product['points_cost'];
                    $low_stock = $product['stock_quantity'] <= 5;
                ?>
                <div class="product-card p-5">
                    <div class="relative mb-4">
                        <img src="../uploads/products/<?= htmlspecialchars($product['image_path']) ?>" 
                             alt="<?= htmlspecialchars($product['name']) ?>" 
                             class="w-full h-48 object-cover rounded-xl mb-4"
                             onerror="this.src='../uploads/products/default.jpg'">
                        
                        <?php if ($low_stock): ?>
                            <span class="absolute top-3 left-3 bg-orange-500 text-white text-xs px-3 py-1 rounded-full font-semibold">
                                <i class="fas fa-exclamation-triangle mr-1"></i>Low Stock
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <h3 class="font-bold text-lg text-text mb-2 line-clamp-2"><?= htmlspecialchars($product['name']) ?></h3>
                    <p class="text-text/70 text-sm mb-4 line-clamp-2"><?= htmlspecialchars($product['description']) ?></p>
                    
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-2xl font-bold text-primary">
                            <?= number_format($product['points_cost']) ?> pts
                        </span>
                        <span class="text-sm text-text/60 bg-subtle px-3 py-1 rounded-full">
                            Stock: <?= $product['stock_quantity'] ?>
                        </span>
                    </div>
                    
                    <!-- Redemption Button -->
                    <?php if ($can_afford): ?>
                        <button @click="openRedeemModal(<?= htmlspecialchars(json_encode($product)) ?>, <?= $total_points ?>)"
                                class="btn-primary w-full flex items-center justify-center">
                            <i class="fas fa-shopping-cart mr-2"></i> Redeem Now
                        </button>
                    <?php else: ?>
                        <button disabled class="btn-primary w-full opacity-50">
                            <i class="fas fa-lock mr-2"></i> Need <?= number_format($product['points_cost'] - $total_points) ?> more pts
                        </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($products)): ?>
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-gift text-6xl text-text/20 mb-4"></i>
                    <h3 class="text-xl font-semibold text-text/60 mb-2">No Rewards Available</h3>
                    <p class="text-text/40">Check back later for new rewards!</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Redemption History Section -->
        <div x-show="activeTab === 'history'" x-cloak x-transition>
            <?php if (!empty($redemption_history)): ?>
                <div class="space-y-4 max-w-4xl mx-auto">
                    <?php foreach($redemption_history as $order): ?>
                    <div class="product-card p-5">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between">
                            <div class="flex items-center mb-4 sm:mb-0 flex-1">
                                <img src="../uploads/products/<?= htmlspecialchars($order['image_path']) ?>" 
                                     alt="<?= htmlspecialchars($order['product_name']) ?>" 
                                     class="w-16 h-16 object-cover rounded-lg mr-4 border border-subtle"
                                     onerror="this.src='../uploads/products/default.jpg'">
                                <div class="flex-1">
                                    <h4 class="font-semibold text-text text-lg"><?= htmlspecialchars($order['product_name']) ?></h4>
                                    <p class="text-sm text-text/60 mt-1">
                                        Redeemed on <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?>
                                    </p>
                                    <?php if ($order['status'] == 'pending'): ?>
                                        <p class="text-xs text-orange-600 mt-1 font-medium">
                                            <i class="fas fa-clock mr-1"></i>Awaiting confirmation
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="flex flex-col sm:items-end space-y-3">
                                <span class="text-xl font-bold text-primary">
                                    -<?= number_format($order['points_used']) ?> pts
                                </span>
                                <span class="status-badge status-<?= $order['status'] ?>">
                                    <?= ucfirst($order['status']) ?>
                                </span>
                                
                                <!-- Cancel Button for Pending Orders -->
                                <?php if ($order['can_cancel']): ?>
                                    <button @click="openCancelModal(<?= htmlspecialchars(json_encode($order)) ?>)"
                                            class="btn-warning flex items-center justify-center text-sm py-2 px-4">
                                        <i class="fas fa-times mr-2"></i> Cancel Order
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <i class="fas fa-history text-6xl text-text/20 mb-4"></i>
                    <h3 class="text-xl font-semibold text-text/60 mb-2">No Redemption History</h3>
                    <p class="text-text/40">Your redeemed rewards will appear here</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Redeem Confirmation Modal -->
        <div x-show="showRedeemModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-xl w-full max-w-md max-h-[90vh] overflow-y-auto product-card">
                <div class="p-6 border-b border-subtle">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-bold text-text">Confirm Redemption</h3>
                        <button @click="showRedeemModal = false" class="text-text/60 hover:text-text transition-colors">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <template x-if="selectedProduct">
                        <div class="space-y-4">
                            <!-- Product Info -->
                            <div class="flex items-center space-x-4 p-4 bg-background rounded-lg">
                                <img :src="'../uploads/products/' + selectedProduct.image_path" 
                                     :alt="selectedProduct.name" 
                                     class="w-16 h-16 object-cover rounded-lg border border-subtle"
                                     onerror="this.src='../uploads/products/default.jpg'">
                                <div>
                                    <h4 class="font-semibold text-lg text-text" x-text="selectedProduct.name"></h4>
                                    <p class="text-text/60" x-text="'Stock: ' + selectedProduct.stock_quantity"></p>
                                </div>
                            </div>
                            
                            <!-- Points Info -->
                            <div class="grid grid-cols-2 gap-4 text-center">
                                <div class="bg-background p-4 rounded-lg">
                                    <p class="text-sm text-text/70">Product Cost</p>
                                    <p class="text-xl font-bold text-primary" x-text="selectedProduct.points_cost.toLocaleString() + ' pts'"></p>
                                </div>
                                <div class="bg-background p-4 rounded-lg">
                                    <p class="text-sm text-text/70">Your Points</p>
                                    <p class="text-xl font-bold text-green-600" x-text="userPoints.toLocaleString() + ' pts'"></p>
                                </div>
                            </div>
                            
                            <!-- Remaining Points -->
                            <div class="bg-background p-4 rounded-lg text-center">
                                <p class="text-sm text-text/70">Points After Redemption</p>
                                <p class="text-xl font-bold" 
                                   :class="(userPoints - selectedProduct.points_cost) >= 0 ? 'text-green-600' : 'text-red-600'"
                                   x-text="(userPoints - selectedProduct.points_cost).toLocaleString() + ' pts'">
                                </p>
                            </div>
                            
                            <!-- Warning if insufficient points -->
                            <div x-show="userPoints < selectedProduct.points_cost" class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
                                    <p class="text-red-700 text-sm">
                                        You need <span x-text="(selectedProduct.points_cost - userPoints).toLocaleString()"></span> more points to redeem this item.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                
                <div class="p-6 border-t border-subtle flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-4">
                    <button @click="showRedeemModal = false" class="btn-secondary flex-1">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </button>
                    <form method="POST" class="flex-1">
                        <input type="hidden" name="product_id" :value="selectedProduct ? selectedProduct.id : ''">
                        <input type="hidden" name="quantity" value="1">
                        <button type="submit" 
                                name="redeem_product" 
                                :disabled="!selectedProduct || userPoints < selectedProduct.points_cost"
                                class="btn-primary w-full flex items-center justify-center"
                                :class="(!selectedProduct || userPoints < selectedProduct.points_cost) ? 'opacity-50 cursor-not-allowed' : ''">
                            <i class="fas fa-check mr-2"></i> Confirm Redemption
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Cancel Order Confirmation Modal -->
        <div x-show="showCancelModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-xl w-full max-w-md max-h-[90vh] overflow-y-auto product-card">
                <div class="p-6 border-b border-subtle">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-bold text-text">Cancel Order</h3>
                        <button @click="showCancelModal = false" class="text-text/60 hover:text-text transition-colors">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <template x-if="selectedOrder">
                        <div class="space-y-4">
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <i class="fas fa-exclamation-triangle text-yellow-600 mr-3 text-xl"></i>
                                    <div>
                                        <h4 class="font-semibold text-yellow-800">Are you sure you want to cancel this order?</h4>
                                        <p class="text-yellow-700 text-sm mt-1">
                                            This action cannot be undone. Your points will be returned to your account.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-4 p-4 bg-background rounded-lg">
                                <img :src="'../uploads/products/' + selectedOrder.image_path" 
                                     :alt="selectedOrder.product_name" 
                                     class="w-16 h-16 object-cover rounded-lg border border-subtle"
                                     onerror="this.src='../uploads/products/default.jpg'">
                                <div>
                                    <h4 class="font-semibold text-lg text-text" x-text="selectedOrder.product_name"></h4>
                                    <p class="text-text/60">Ordered on <span x-text="new Date(selectedOrder.created_at).toLocaleDateString()"></span></p>
                                </div>
                            </div>
                            
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                                <p class="text-sm text-green-700">Points to be returned:</p>
                                <p class="text-xl font-bold text-green-600" x-text="selectedOrder.points_used.toLocaleString() + ' pts'"></p>
                            </div>
                        </div>
                    </template>
                </div>
                
                <div class="p-6 border-t border-subtle flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-4">
                    <button @click="showCancelModal = false" class="btn-secondary flex-1">
                        <i class="fas fa-arrow-left mr-2"></i> Keep Order
                    </button>
                    <form method="POST" class="flex-1">
                        <input type="hidden" name="order_id" :value="selectedOrder ? selectedOrder.id : ''">
                        <button type="submit" 
                                name="cancel_order" 
                                class="btn-warning w-full flex items-center justify-center">
                            <i class="fas fa-times mr-2"></i> Confirm Cancellation
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function redeemPage() {
            return {
                activeTab: 'rewards',
                showRedeemModal: false,
                showCancelModal: false,
                selectedProduct: null,
                selectedOrder: null,
                userPoints: <?= $total_points ?>,
                
                openRedeemModal(product, userPoints) {
                    this.selectedProduct = product;
                    this.userPoints = userPoints;
                    this.showRedeemModal = true;
                },
                
                openCancelModal(order) {
                    this.selectedOrder = order;
                    this.showCancelModal = true;
                }
            }
        }

        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.bg-green-50, .bg-red-50');
            messages.forEach(msg => {
                msg.style.transition = 'opacity 0.5s ease';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);

        // Prevent horizontal scrolling
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.overflowX = 'hidden';
        });
    </script>

    <style>
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Ensure no horizontal scrolling */
        html, body {
            max-width: 100%;
            overflow-x: hidden;
        }
        
        .page-container {
            max-width: 100%;
            overflow-x: hidden;
        }
        
        /* Smooth transitions */
        .product-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
    </style>
</body>
</html>