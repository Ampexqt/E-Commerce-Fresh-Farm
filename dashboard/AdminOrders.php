<!-- <?php
session_start();

// Check if user is logged in and is an admin
if(!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'admin' && !isset($_SESSION['is_admin']))) {
    // Not logged in or not an admin, redirect to login page
    header("Location: ../index.php");
    exit;
}

// Include database connection
require_once '../connect.php';

// Function to get all orders
function getOrders($conn, $search = '') {
    if (!empty($search)) {
        $search = "%$search%";
        $stmt = $conn->prepare("SELECT o.order_id, o.user_id, o.order_date, o.total_amount, o.status, 
                               o.shipping_address, o.payment_method, CONCAT(u.first_name, ' ', u.last_name) as customer_name 
                               FROM orders o 
                               LEFT JOIN users u ON o.user_id = u.user_id 
                               WHERE o.order_id LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? 
                               ORDER BY o.order_date DESC");
        $stmt->bind_param("ss", $search, $search);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        $result = $conn->query("SELECT o.order_id, o.user_id, o.order_date, o.total_amount, o.status, 
                               o.shipping_address, o.payment_method, CONCAT(u.first_name, ' ', u.last_name) as customer_name 
                               FROM orders o 
                               LEFT JOIN users u ON o.user_id = u.user_id 
                               ORDER BY o.order_date DESC");
    }
    
    $orders = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    
    return $orders;
}

// Function to get order items
function getOrderItems($conn, $orderId) {
    $stmt = $conn->prepare("SELECT oi.order_item_id, oi.product_id, oi.quantity, oi.price, 
                          p.name as product_name, p.image_path 
                          FROM order_items oi 
                          JOIN products p ON oi.product_id = p.id 
                          WHERE oi.order_id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }
    
    $stmt->close();
    return $items;
}

// Update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $orderId = $_POST['order_id'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    $stmt->bind_param("si", $status, $orderId);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Order #$orderId status updated to $status";
    } else {
        $_SESSION['error'] = "Failed to update order status: " . $stmt->error;
    }
    
    $stmt->close();
    header("Location: AdminOrders.php");
    exit;
}

// Get search term if provided
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get all orders
$orders = getOrders($conn, $search);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fresh Farm - Order Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/AdminOrder.css">
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
                        <a class="nav-link" href="product.php">
                            <i class="fas fa-carrot"></i> Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="AdminOrders.php">
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
                    <span>Orders</span>
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
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
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
            
            <!-- Orders Content -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Order Management</span>
                    <div class="d-flex">
                        <form class="me-2" method="GET" action="AdminOrders.php">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="Search orders..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($orders)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No orders found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['order_id']; ?></td>
                                            <td><?php echo htmlspecialchars($order['customer_name'] ?? 'Guest'); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                            <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td><?php echo ucfirst(htmlspecialchars($order['payment_method'])); ?></td>
                                            <td>
                                                <?php
                                                    $statusClass = 'bg-info';
                                                    if ($order['status'] === 'delivered') {
                                                        $statusClass = 'bg-success';
                                                    } elseif ($order['status'] === 'pending') {
                                                        $statusClass = 'bg-warning';
                                                    } elseif ($order['status'] === 'cancelled') {
                                                        $statusClass = 'bg-danger';
                                                    }
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst(htmlspecialchars($order['status'])); ?></span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info btn-action view-order" data-bs-toggle="modal" data-bs-target="#viewOrderModal" data-id="<?php echo $order['order_id']; ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-primary btn-action update-status" data-bs-toggle="modal" data-bs-target="#updateStatusModal" data-id="<?php echo $order['order_id']; ?>" data-status="<?php echo $order['status']; ?>">
                                                    <i class="fas fa-edit"></i>
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

<!-- View Order Modal -->
<div class="modal fade" id="viewOrderModal" tabindex="-1" aria-labelledby="viewOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewOrderModalLabel">Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="orderDetailsContent" class="p-2">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading order details...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateStatusModalLabel">Update Order Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="AdminOrders.php" method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" id="updateOrderId" name="order_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="orderStatus" class="form-label">Status</label>
                        <select class="form-select" id="orderStatus" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
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

<!-- JavaScript Libraries -->
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
        
        // Update Status Modal
        const updateStatusButtons = document.querySelectorAll('.update-status');
        updateStatusButtons.forEach(button => {
            button.addEventListener('click', function() {
                const orderId = this.getAttribute('data-id');
                const status = this.getAttribute('data-status');
                
                document.getElementById('updateOrderId').value = orderId;
                document.getElementById('orderStatus').value = status;
            });
        });
        
        // View Order Modal
        const viewButtons = document.querySelectorAll('.view-order');
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const orderId = this.getAttribute('data-id');
                const orderDetailsContent = document.getElementById('orderDetailsContent');
                
                // Load order details via AJAX
                fetch(`get_order_details.php?order_id=${orderId}`)
                    .then(response => response.text())
                    .then(data => {
                        orderDetailsContent.innerHTML = data;
                    })
                    .catch(error => {
                        orderDetailsContent.innerHTML = `<div class="alert alert-danger">Error loading order details: ${error.message}</div>`;
                    });
            });
        });
        
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
</html> -->
