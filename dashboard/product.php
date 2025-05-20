<?php
session_start();

// Check if user is logged in and is an admin
if(!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'admin' && !isset($_SESSION['is_admin']))) {
    // Not logged in or not an admin, redirect to login page
    header("Location: ../index.php");
    exit;
}

// Include database connection
require_once '../connect.php';



// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        switch ($action) {
            case 'add':
                addProduct($conn);
                break;
            case 'update':
                updateProduct($conn);
                break;
            case 'delete':
                deleteProduct($conn);
                break;
            default:
                // Invalid action
                break;
        }
    }
}

// Function to add a new product
function addProduct($conn) {
    // Validate inputs
    if (empty($_POST['productName']) || empty($_POST['productCategory']) || 
        empty($_POST['productPrice']) || empty($_POST['productUnit']) || 
        empty($_POST['productStock'])) {
        $_SESSION['error'] = 'All required fields must be filled';
        header('Location: product.php');
        exit;
    }
    
    // Handle file upload
    $imagePath = null;
    if (isset($_FILES['productImage']) && $_FILES['productImage']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../assets/products/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Generate unique filename
        $filename = uniqid() . '_' . basename($_FILES['productImage']['name']);
        $targetFile = $uploadDir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($_FILES['productImage']['tmp_name'], $targetFile)) {
            $imagePath = 'assets/products/' . $filename;
        } else {
            $_SESSION['error'] = 'Failed to upload image';
            header('Location: product.php');
            exit;
        }
    }
    
    // Determine status based on stock quantity (0 = Out of Stock, 1-15 = Low Stock, 16+ = In Stock)
    $status = 'In Stock';
    $stock = (int)$_POST['productStock'];
    if ($stock === 0) {
        $status = 'Out of Stock';
    } else if ($stock <= 15) {
        $status = 'Low Stock';
    }
    
    // Prepare and execute query with correct column names from your schema
    $stmt = $conn->prepare("INSERT INTO products (name, category, price, unit, stock, status, description, image_path) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("ssdsisss", 
        $_POST['productName'], 
        $_POST['productCategory'], 
        $_POST['productPrice'], 
        $_POST['productUnit'],
        $_POST['productStock'], 
        $status,  // Use calculated status
        $_POST['productDescription'], 
        $imagePath
    );
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Product added successfully';
    } else {
        $_SESSION['error'] = 'Failed to add product: ' . $stmt->error;
    }
    
    $stmt->close();
    header('Location: product.php');
    exit;
}

// Function to update a product
function updateProduct($conn) {
    // Validate inputs
    if (empty($_POST['productId']) || empty($_POST['productName']) || 
        empty($_POST['productCategory']) || empty($_POST['productPrice']) || 
        empty($_POST['productUnit']) || empty($_POST['productStock'])) {
        $_SESSION['error'] = 'All required fields must be filled';
        header('Location: product.php');
        exit;
    }
    
    // Get existing product data
    $stmt = $conn->prepare("SELECT image_path FROM products WHERE id = ?");
    $stmt->bind_param("i", $_POST['productId']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error'] = 'Product not found';
        header('Location: product.php');
        exit;
    }
    
    $product = $result->fetch_assoc();
    $imagePath = $product['image_path'];
    
    // Handle file upload if a new image is provided
    if (isset($_FILES['productImage']) && $_FILES['productImage']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../assets/products/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Generate unique filename
        $filename = uniqid() . '_' . basename($_FILES['productImage']['name']);
        $targetFile = $uploadDir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($_FILES['productImage']['tmp_name'], $targetFile)) {
            // Delete old image if exists
            if ($imagePath && file_exists('../' . $imagePath)) {
                unlink('../' . $imagePath);
            }
            
            $imagePath = 'assets/products/' . $filename;
        } else {
            $_SESSION['error'] = 'Failed to upload image';
            header('Location: product.php');
            exit;
        }
    }
    
    // Determine status based on stock quantity (0 = Out of Stock, 1-15 = Low Stock, 16+ = In Stock)
    $status = 'In Stock';
    $stock = (int)$_POST['productStock'];
    if ($stock === 0) {
        $status = 'Out of Stock';
    } else if ($stock <= 15) {
        $status = 'Low Stock';
    }

    $stmt = $conn->prepare("UPDATE products SET 
                          name = ?, 
                          category = ?, 
                          price = ?, 
                          unit = ?, 
                          stock = ?, 
                          status = ?, 
                          description = ?, 
                          image_path = ? 
                          WHERE id = ?");

    $stmt->bind_param("ssdssissi", 
        $_POST['productName'], 
        $_POST['productCategory'], 
        $_POST['productPrice'], 
        $_POST['productUnit'], 
        $_POST['productStock'], 
        $status,  // Use calculated status
        $_POST['productDescription'], 
        $imagePath,
        $_POST['productId']
    );
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Product updated successfully';
    } else {
        $_SESSION['error'] = 'Failed to update product: ' . $stmt->error;
    }
    
    $stmt->close();
    header('Location: product.php');
    exit;
}

// Function to delete a product
function deleteProduct($conn) {
    // Validate input
    if (empty($_POST['productId'])) {
        $_SESSION['error'] = 'Product ID is required';
        header('Location: product.php');
        exit;
    }
    
    // Get product image path before deletion
    $stmt = $conn->prepare("SELECT image_path FROM products WHERE id = ?");
    $stmt->bind_param("i", $_POST['productId']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        $imagePath = $product['image_path'];
        
        // Delete the product
        $deleteStmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $deleteStmt->bind_param("i", $_POST['productId']);
        
        if ($deleteStmt->execute()) {
            // Delete product image if exists
            if ($imagePath && file_exists('../' . $imagePath)) {
                unlink('../' . $imagePath);
            }
            
            $_SESSION['success'] = 'Product deleted successfully';
        } else {
            $_SESSION['error'] = 'Failed to delete product: ' . $deleteStmt->error;
        }
        
        $deleteStmt->close();
    } else {
        $_SESSION['error'] = 'Product not found';
    }
    
    $stmt->close();
    header('Location: product.php');
    exit;
}

// Function to get all products
function getProducts($conn, $search = '') {
    if (!empty($search)) {
        $search = "%$search%";
        $stmt = $conn->prepare("SELECT id, name, category, price, unit, 
                              stock, status, image_path, 
                              description, created_at, updated_at
                              FROM products 
                              WHERE name LIKE ? ORDER BY id DESC");
        $stmt->bind_param("s", $search);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        $result = $conn->query("SELECT id, name, category, price, unit,
                              stock, status, image_path,
                              description, created_at, updated_at
                              FROM products ORDER BY id DESC");
    }
    
    $products = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Force recalculation of status based on stock to ensure it's always correct
            // (0 = Out of Stock, 1-15 = Low Stock, 16+ = In Stock)
            if ($row['stock'] === 0) {
                $row['status'] = 'Out of Stock';
            } else if ($row['stock'] <= 15) {
                $row['status'] = 'Low Stock';
            } else {
                $row['status'] = 'In Stock';
            }
            
            $products[] = $row;
        }
    }
    
    return $products;
}

// Get search term if provided
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get all products
$products = getProducts($conn, $search);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fresh Farm - Product Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/product.css">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3 col-xl-2 sidebar" id="sidebar">
            <div class="logo-area">
                <img src="../assets/farmfresh.png" alt="Fresh Farm Logo" width="40" height="40" />
                <h3>Fresh Farm</h3>
            </div>
            <div class="mt-4">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="product.php">
                            <i class="fas fa-carrot"></i> Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="AdminOrders.php">
                            <i class="fas fa-shopping-cart"></i> Orders
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users"></i> Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transactions.php">
                            <i class="fas fa-money-bill-wave"></i> Transactions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="delivery.php">
                            <i class="fas fa-truck"></i> Delivery
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9 col-xl-10 ms-auto content-area" id="content">
            <div class="top-bar mb-4">
                <div>
                    <span class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </span>
                    <span>Products</span>
                </div>
                <div class="user-area">
                    <div class="dropdown">
                        <a class="dropdown-toggle text-decoration-none text-dark" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="../assets/admin_avatar.jpg" alt="Admin Profile" width="36" height="36" class="rounded-circle" />
                            
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Alert messages -->
            <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <!-- Products Content -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Product Management</span>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                        <i class="fas fa-plus"></i> Add New Product
                    </button>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <form method="GET" action="product.php">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="60">Image</th>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($products)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No products found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td>
                                                <?php if ($product['image_path']): ?>
                                                    <img src="../<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-img-preview" width="60" height="60">
                                                <?php else: ?>
                                                    <img src="../assets/placeholder.png" alt="Placeholder" class="product-img-preview" width="60" height="60">
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                                            <td>₱<?php echo number_format($product['price'], 2); ?>/<?php echo htmlspecialchars($product['unit']); ?></td>
                                            <td><?php echo $product['stock']; ?></td>
                                            <td>
                                                <?php
                                                    $statusClass = 'bg-success';
                                                    if ($product['status'] === 'Low Stock') {
                                                        $statusClass = 'bg-warning';
                                                    } elseif ($product['status'] === 'Out of Stock') {
                                                        $statusClass = 'bg-danger';
                                                    }
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($product['status']); ?></span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info btn-action edit-product" data-bs-toggle="modal" data-bs-target="#editProductModal" 
                                                    data-id="<?php echo $product['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                    data-category="<?php echo htmlspecialchars($product['category']); ?>"
                                                    data-price="<?php echo $product['price']; ?>"
                                                    data-unit="<?php echo htmlspecialchars($product['unit']); ?>"
                                                    data-stock="<?php echo $product['stock']; ?>"
                                                    data-description="<?php echo htmlspecialchars($product['description'] ?? ''); ?>"
                                                    data-image="<?php echo htmlspecialchars($product['image_path'] ?? ''); ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger btn-action delete-product" data-bs-toggle="modal" data-bs-target="#deleteProductModal" data-id="<?php echo $product['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addProductModalLabel">Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="product.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="productName" class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="productName" name="productName" required>
                        </div>
                        <div class="col-md-6">
                            <label for="productCategory" class="form-label">Category</label>
                            <select class="form-select" id="productCategory" name="productCategory" required>
                                <option value="">Select Category</option>
                                <option value="Fruits">Fruits</option>
                                <option value="Vegetables">Vegetables</option>
                                <option value="Dairy">Dairy</option>
                                <option value="Meat">Meat</option>
                                <option value="Bakery">Bakery</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="productPrice" class="form-label">Price</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" class="form-control" id="productPrice" name="productPrice" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="productUnit" class="form-label">Unit</label>
                            <select class="form-select" id="productUnit" name="productUnit" required>
                                <option value="kg">kg</option>
                                <option value="pcs">pcs</option>
                                <option value="L">L</option>
                                <option value="box">box</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="productStock" class="form-label">Stock Quantity</label>
                            <input type="number" class="form-control" id="productStock" name="productStock" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <div class="form-control" id="statusDisplay">In Stock</div>
                            <input type="hidden" id="productStatus" name="productStatus" value="In Stock">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="productDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="productDescription" name="productDescription" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="productImage" class="form-label">Product Image</label>
                        <input type="file" class="form-control" id="productImage" name="productImage" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editProductModalLabel">Edit Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="product.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" id="editProductId" name="productId">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editProductName" class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="editProductName" name="productName" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editProductCategory" class="form-label">Category</label>
                            <select class="form-select" id="editProductCategory" name="productCategory" required>
                                <option value="">Select Category</option>
                                <option value="Fruits">Fruits</option>
                                <option value="Vegetables">Vegetables</option>
                                <option value="Dairy">Dairy</option>
                                <option value="Meat">Meat</option>
                                <option value="Bakery">Bakery</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editProductPrice" class="form-label">Price</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" class="form-control" id="editProductPrice" name="productPrice" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="editProductUnit" class="form-label">Unit</label>
                            <select class="form-select" id="editProductUnit" name="productUnit" required>
                                <option value="kg">kg</option>
                                <option value="pcs">pcs</option>
                                <option value="L">L</option>
                                <option value="box">box</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editProductStock" class="form-label">Stock Quantity</label>
                            <input type="number" class="form-control" id="editProductStock" name="productStock" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <div class="form-control" id="editStatusDisplay">In Stock</div>
                            <input type="hidden" id="editProductStatus" name="productStatus" value="In Stock">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="editProductDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="editProductDescription" name="productDescription" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <img id="currentProductImage" src="../assets/placeholder.png" alt="Current Product Image" class="border rounded" width="100" height="100">
                            <div>Current Image</div>
                        </div>
                        <label for="editProductImage" class="form-label">Change Product Image</label>
                        <input type="file" class="form-control" id="editProductImage" name="productImage" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Product Modal -->
<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteProductModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this product? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form action="product.php" method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="deleteProductId" name="productId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Logout Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to logout?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="../logout.php" class="btn btn-danger">Logout</a>
            </div>
        </div>
    </div>
</div>

<!-- Add JavaScript for sidebar toggle and modals -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('content');
        
        if (menuToggle) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('expanded');
            });
        }
        
        // Edit product modal
        const editButtons = document.querySelectorAll('.edit-product');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const category = this.getAttribute('data-category');
                const price = this.getAttribute('data-price');
                const unit = this.getAttribute('data-unit');
                const stock = this.getAttribute('data-stock');
                const description = this.getAttribute('data-description');
                const image = this.getAttribute('data-image');
                
                document.getElementById('editProductId').value = id;
                document.getElementById('editProductName').value = name;
                document.getElementById('editProductCategory').value = category;
                document.getElementById('editProductPrice').value = price;
                document.getElementById('editProductUnit').value = unit;
                document.getElementById('editProductStock').value = stock;
                document.getElementById('editProductDescription').value = description;
                
                // Update current image
                const currentProductImage = document.getElementById('currentProductImage');
                if (image) {
                    currentProductImage.src = '../' + image;
                } else {
                    currentProductImage.src = '../assets/placeholder.png';
                }
                
                // Calculate and display status based on stock
                updateStatusDisplay(stock, 'edit');
            });
        });
        
        // Delete product modal
        const deleteButtons = document.querySelectorAll('.delete-product');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                document.getElementById('deleteProductId').value = id;
            });
        });
        
        // Auto-update status based on stock in add form
        const stockInput = document.getElementById('productStock');
        if (stockInput) {
            stockInput.addEventListener('change', function() {
                const stock = parseInt(this.value) || 0;
                updateStatusDisplay(stock, 'add');
            });
        }
        
        // Auto-update status based on stock in edit form
        const editStockInput = document.getElementById('editProductStock');
        if (editStockInput) {
            editStockInput.addEventListener('change', function() {
                const stock = parseInt(this.value) || 0;
                updateStatusDisplay(stock, 'edit');
            });
        }
        
        // Function to update status display and hidden field (0 = Out of Stock, 1-15 = Low Stock, 16+ = In Stock)
        function updateStatusDisplay(stock, formType) {
            let status = 'In Stock';
            if (stock === 0) {
                status = 'Out of Stock';
            } else if (stock <= 15) {
                status = 'Low Stock';
            }
            
            if (formType === 'add') {
                document.getElementById('statusDisplay').textContent = status;
                document.getElementById('productStatus').value = status;
            } else {
                document.getElementById('editStatusDisplay').textContent = status;
                document.getElementById('editProductStatus').value = status;
            }
        }
        
        // Auto-dismiss alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    });
</script>
</body>
</html>
