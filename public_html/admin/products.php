<?php
session_start();
include '../auth/db.php';

// Admin authentication check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Define upload directory
$upload_dir = dirname(__DIR__) . '/uploads/products/';

// Create directory if it doesn't exist
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        $_SESSION['error'] = "Failed to create upload directory. Please create manually: " . $upload_dir;
    }
}

// Check if directory is writable
if (!is_writable($upload_dir)) {
    $_SESSION['error'] = "Upload directory is not writable. Please check permissions for: " . $upload_dir;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_product'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $description = $conn->real_escape_string($_POST['description']);
        $points_cost = intval($_POST['points_cost']);
        $stock_quantity = intval($_POST['stock_quantity']);
        
        // Handle image upload with better error handling
        $image_path = 'default.jpg';
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            $file_type = $_FILES['image']['type'];
            $file_size = $_FILES['image']['size'];
            $file_name = basename($_FILES['image']['name']);
            
            // Validate file type
            if (!in_array($file_type, $allowed_types)) {
                $_SESSION['error'] = "Only JPG, JPEG, PNG & GIF files are allowed.";
                header("Location: products.php");
                exit;
            }
            
            // Validate file size
            if ($file_size > $max_size) {
                $_SESSION['error'] = "File size must be less than 5MB.";
                header("Location: products.php");
                exit;
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $image_path = time() . '_' . uniqid() . '.' . $file_extension;
            $target_file = $upload_dir . $image_path;
            
            // Check if upload directory exists and is writable
            if (!is_dir($upload_dir)) {
                $_SESSION['error'] = "Upload directory does not exist: " . $upload_dir;
                header("Location: products.php");
                exit;
            }
            
            if (!is_writable($upload_dir)) {
                $_SESSION['error'] = "Upload directory is not writable. Please check permissions.";
                header("Location: products.php");
                exit;
            }
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                // File uploaded successfully
                $_SESSION['success'] = "Product added successfully with image!";
            } else {
                // If file upload fails, use default image and show warning
                $image_path = 'default.jpg';
                $_SESSION['warning'] = "Product added but image upload failed. Using default image.";
                error_log("File upload failed. Temp: " . $_FILES['image']['tmp_name'] . " -> Target: " . $target_file);
            }
        }
        
        // Insert product into database
        $conn->query("
            INSERT INTO products (name, description, points_cost, image_path, stock_quantity) 
            VALUES ('$name', '$description', $points_cost, '$image_path', $stock_quantity)
        ");
        
        if (empty($_SESSION['warning']) && empty($_SESSION['error'])) {
            $_SESSION['success'] = "Product added successfully!";
        }
        
        header("Location: products.php");
        exit;
    }
    
    if (isset($_POST['update_product'])) {
        $id = intval($_POST['id']);
        $name = $conn->real_escape_string($_POST['name']);
        $description = $conn->real_escape_string($_POST['description']);
        $points_cost = intval($_POST['points_cost']);
        $stock_quantity = intval($_POST['stock_quantity']);
        $active = isset($_POST['active']) ? 1 : 0;
        
        // Handle image update
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024;
            
            $file_type = $_FILES['image']['type'];
            $file_size = $_FILES['image']['size'];
            $file_name = basename($_FILES['image']['name']);
            
            if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $image_path = time() . '_' . uniqid() . '.' . $file_extension;
                $target_file = $upload_dir . $image_path;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    $conn->query("UPDATE products SET image_path = '$image_path' WHERE id = $id");
                }
            }
        }
        
        $conn->query("
            UPDATE products 
            SET name = '$name', description = '$description', points_cost = $points_cost, 
                stock_quantity = $stock_quantity, active = $active 
            WHERE id = $id
        ");
        
        $_SESSION['success'] = "Product updated successfully!";
        header("Location: products.php");
        exit;
    }
    
    if (isset($_POST['delete_product'])) {
        $id = intval($_POST['id']);
        
        // Check if product has any orders
        $order_check = $conn->query("SELECT COUNT(*) as order_count FROM orders WHERE product_id = $id");
        $order_data = $order_check->fetch_assoc();
        
        if ($order_data['order_count'] > 0) {
            $_SESSION['error'] = "Cannot delete product that has existing orders. You can deactivate it instead.";
        } else {
            $conn->query("DELETE FROM products WHERE id = $id");
            $_SESSION['success'] = "Product deleted successfully!";
        }
        
        header("Location: products.php");
        exit;
    }
}

// Fetch all products
$products_result = $conn->query("SELECT * FROM products ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products | Admin</title>
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
        .product-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid #E5E7EB;
            transition: all 0.3s ease;
        }
        
        .product-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
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
            background: #EF4444;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-danger:hover {
            background: #DC2626;
        }
        
        [x-cloak] { display: none !important; }
        
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>
<body class="bg-background">
    <?php include 'sidebar.php'; ?>

    <div class="page-container lg:ml-72 pt-4 px-4" x-data="productForm()">
        <!-- Header Section -->
<div class="header-section mb-8 mt-12 text-center sm:text-left sm:mt-0">
            <h1 class="text-2xl sm:text-3xl font-bold text-text mb-2">Manage Products</h1>
            <p class="text-text/70">Add and manage redeemable products for volunteers</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <p class="text-red-700"><?= $_SESSION['error'] ?></p>
                </div>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['warning'])): ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-yellow-500 mr-3"></i>
                    <p class="text-yellow-700"><?= $_SESSION['warning'] ?></p>
                </div>
            </div>
            <?php unset($_SESSION['warning']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <p class="text-green-700"><?= $_SESSION['success'] ?></p>
                </div>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- Toggle Button for Add Product Form -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-text flex items-center">
                <i class="fas fa-gifts mr-3 text-accent"></i>
                Products Inventory
            </h2>
            <button @click="openAddForm()" class="btn-primary flex items-center">
                <i class="fas fa-plus mr-2"></i>
                <span x-text="showForm && !editMode ? 'Cancel' : 'Add New Product'"></span>
            </button>
        </div>

        <!-- Add/Edit Product Form -->
        <div x-show="showForm" x-cloak x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform -translate-y-4"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             class="product-card p-6 mb-8" id="productForm">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-text" x-text="formTitle"></h3>
                <button @click="closeForm()" class="text-text/60 hover:text-text transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <!-- Hidden input for product ID in edit mode -->
                <template x-if="editMode">
                    <input type="hidden" name="id" x-bind:value="currentProduct.id">
                </template>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Product Name -->
                    <div>
                        <label class="block text-sm font-medium text-text mb-2">Product Name</label>
                        <input type="text" name="name" x-model="currentProduct.name" 
                               placeholder="Enter product name" required 
                               class="w-full p-3 border border-subtle rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    
                    <!-- Points Cost -->
                    <div>
                        <label class="block text-sm font-medium text-text mb-2">Points Cost</label>
                        <input type="number" name="points_cost" x-model="currentProduct.points_cost" 
                               placeholder="Points required" required min="1"
                               class="w-full p-3 border border-subtle rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    
                    <!-- Stock Quantity -->
                    <div>
                        <label class="block text-sm font-medium text-text mb-2">Stock Quantity</label>
                        <input type="number" name="stock_quantity" x-model="currentProduct.stock_quantity" 
                               placeholder="Available quantity" required min="0"
                               class="w-full p-3 border border-subtle rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    
                    <!-- Product Image -->
                    <div>
                        <label class="block text-sm font-medium text-text mb-2">Product Image</label>
                        <input type="file" name="image" accept="image/*" 
                               class="w-full p-3 border border-subtle rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                        <p class="text-xs text-text/60 mt-2">Max size: 5MB. Allowed: JPG, PNG, GIF, WEBP</p>
                        
                        <!-- Show current image in edit mode -->
                        <template x-if="editMode && currentProduct.image_path">
                            <div class="mt-3">
                                <p class="text-sm text-text/60 mb-2">Current Image:</p>
                                <img x-bind:src="'../uploads/products/' + currentProduct.image_path" 
                                     x-bind:alt="currentProduct.name" 
                                     class="w-20 h-20 object-cover rounded-lg border border-subtle">
                            </div>
                        </template>
                    </div>
                </div>
                
                <!-- Description -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-text mb-2">Description</label>
                    <textarea name="description" x-model="currentProduct.description" 
                              placeholder="Enter product description" required rows="4"
                              class="w-full p-3 border border-subtle rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                </div>
                
                <!-- Active Status (Edit Mode Only) -->
                <template x-if="editMode">
                    <div class="mb-6">
                        <label class="flex items-center space-x-3">
                            <input type="checkbox" name="active" x-model="currentProduct.active"
                                   class="w-4 h-4 text-primary border-subtle rounded focus:ring-primary">
                            <span class="text-sm font-medium text-text">Active Product</span>
                        </label>
                        <p class="text-xs text-text/60 mt-1">Inactive products won't be visible to volunteers</p>
                    </div>
                </template>
                
                <!-- Form Actions -->
                <div class="flex space-x-4">
                    <template x-if="editMode">
                        <button type="submit" name="update_product" class="btn-primary flex items-center">
                            <i class="fas fa-save mr-2"></i>
                            <span x-text="submitButtonText"></span>
                        </button>
                    </template>
                    
                    <template x-if="!editMode">
                        <button type="submit" name="add_product" class="btn-primary flex items-center">
                            <i class="fas fa-plus mr-2"></i>
                            <span x-text="submitButtonText"></span>
                        </button>
                    </template>
                    
                    <button type="button" @click="closeForm()" class="btn-secondary">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </button>
                </div>
            </form>
        </div>

        <!-- Products Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php while($product = $products_result->fetch_assoc()): ?>
            <div class="product-card p-6">
                <div class="relative mb-4">
                    <img src="../uploads/products/<?= $product['image_path'] ?>" 
                         alt="<?= htmlspecialchars($product['name']) ?>" 
                         class="w-full h-48 object-cover rounded-lg mb-4"
                         onerror="this.src='../uploads/products/default.jpg'">
                    
                    <!-- Status Badge -->
                    <div class="absolute top-3 right-3">
                        <span class="<?= $product['active'] ? 'bg-green-500' : 'bg-red-500' ?> text-white text-xs px-2 py-1 rounded-full">
                            <?= $product['active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                    
                    <!-- Stock Badge -->
                    <?php if ($product['stock_quantity'] <= 5): ?>
                        <div class="absolute top-3 left-3 bg-orange-500 text-white text-xs px-2 py-1 rounded-full">
                            Low Stock
                        </div>
                    <?php endif; ?>
                </div>
                
                <h3 class="font-bold text-lg text-text mb-2"><?= htmlspecialchars($product['name']) ?></h3>
                <p class="text-text/70 text-sm mb-4 line-clamp-2"><?= htmlspecialchars($product['description']) ?></p>
                
                <div class="flex justify-between items-center mb-4">
                    <span class="text-2xl font-bold text-primary">
                        <?= number_format($product['points_cost']) ?> pts
                    </span>
                    <span class="text-sm text-text/60 bg-subtle px-3 py-1 rounded-full">
                        Stock: <?= $product['stock_quantity'] ?>
                    </span>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex space-x-2">
                    <button @click="openEditForm(<?= htmlspecialchars(json_encode($product)) ?>)" 
                            class="flex-1 bg-primary text-white p-2 rounded-lg hover:bg-primary/90 transition flex items-center justify-center">
                        <i class="fas fa-edit mr-2"></i> Edit
                    </button>
                    
                    <form method="POST" class="flex-1" onsubmit="return confirmDelete()">
                        <input type="hidden" name="id" value="<?= $product['id'] ?>">
                        <button type="submit" name="delete_product" 
                                class="w-full btn-danger flex items-center justify-center">
                            <i class="fas fa-trash mr-2"></i> Delete
                        </button>
                    </form>
                </div>
                
                <!-- Created Date -->
                <div class="mt-4 pt-4 border-t border-subtle">
                    <p class="text-xs text-text/60">
                        Created: <?= date('M j, Y', strtotime($product['created_at'])) ?>
                    </p>
                </div>
            </div>
            <?php endwhile; ?>
            
            <?php if ($products_result->num_rows == 0): ?>
            <div class="col-span-full text-center py-12">
                <i class="fas fa-gift text-6xl text-text/20 mb-4"></i>
                <h3 class="text-xl font-semibold text-text/60 mb-2">No Products Found</h3>
                <p class="text-text/40 mb-6">Get started by adding your first product</p>
                <button @click="openAddForm()" class="btn-primary">
                    <i class="fas fa-plus mr-2"></i> Add First Product
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Product Form Alpine.js Component
        function productForm() {
            return {
                showForm: false,
                editMode: false,
                currentProduct: {
                    id: '',
                    name: '',
                    description: '',
                    points_cost: '',
                    stock_quantity: '',
                    image_path: '',
                    active: true
                },
                formTitle: 'Add New Product',
                submitButtonText: 'Add Product',
                
                openAddForm() {
                    if (this.showForm && !this.editMode) {
                        this.closeForm();
                        return;
                    }
                    
                    this.editMode = false;
                    this.showForm = true;
                    this.currentProduct = {
                        id: '',
                        name: '',
                        description: '',
                        points_cost: '',
                        stock_quantity: '',
                        image_path: '',
                        active: true
                    };
                    this.formTitle = 'Add New Product';
                    this.submitButtonText = 'Add Product';
                    
                    // Scroll to form
                    setTimeout(() => {
                        document.getElementById('productForm').scrollIntoView({ 
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }, 100);
                },
                
                openEditForm(product) {
                    this.editMode = true;
                    this.showForm = true;
                    this.currentProduct = { ...product };
                    this.formTitle = 'Edit Product';
                    this.submitButtonText = 'Update Product';
                    
                    // Convert active to boolean for checkbox
                    this.currentProduct.active = product.active == 1;
                    
                    // Scroll to form
                    setTimeout(() => {
                        document.getElementById('productForm').scrollIntoView({ 
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }, 100);
                },
                
                closeForm() {
                    this.showForm = false;
                    this.editMode = false;
                    this.currentProduct = {
                        id: '',
                        name: '',
                        description: '',
                        points_cost: '',
                        stock_quantity: '',
                        image_path: '',
                        active: true
                    };
                    this.formTitle = 'Add New Product';
                    this.submitButtonText = 'Add Product';
                }
            }
        }

        // Delete confirmation function
        function confirmDelete() {
            return confirm('Are you sure you want to delete this product?');
        }

        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.bg-green-50, .bg-red-50, .bg-yellow-50');
            messages.forEach(msg => {
                msg.style.transition = 'opacity 0.5s ease';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>