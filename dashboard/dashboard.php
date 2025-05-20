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

// Function to get count of products
function getProductCount($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM products");
    return ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;
}

// Function to get count of active users
function getUserCount($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    return ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;
}

// Function to get count of orders
function getOrderCount($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM orders");
    return ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;
}

// Function to get total revenue
function getTotalRevenue($conn) {
    $result = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE LOWER(status) = 'delivered'");
    return ($result && $result->num_rows > 0) ? $result->fetch_assoc()['total'] : 0;
}

// Function to get sales data for different time periods
function getSalesData($conn, $timeRange = '7days') {
    $salesData = [];
    $today = date('Y-m-d');
    
    switch ($timeRange) {
        case '30days':
            $days = 30;
            break;
        case 'month':
            // Current month
            $days = date('t'); // Number of days in current month
            $today = date('Y-m-') . date('t'); // Last day of current month
            $startDay = date('Y-m-01'); // First day of current month
            break;
        case '7days':
        default:
            $days = 7;
            break;
    }
    
    if ($timeRange == 'month') {
        // For current month, we want to show all days of the month
        $currentDay = 1;
        $daysInMonth = date('t');
        
        for ($i = 0; $i < $daysInMonth; $i++) {
            $date = date('Y-m-') . str_pad($currentDay, 2, '0', STR_PAD_LEFT);
            $result = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE DATE(order_date) = '$date' AND LOWER(status) = 'delivered'");
            $row = $result->fetch_assoc();
            $total = $row['total'] ? $row['total'] : 0;
            
            $salesData[] = [
                'date' => $currentDay, // Just the day number for month view
                'total' => $total
            ];
            
            $currentDay++;
        }
    } else {
        // For 7 or 30 days
        for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
            $result = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE DATE(order_date) = '$date' AND LOWER(status) = 'delivered'");
        $row = $result->fetch_assoc();
        $total = $row['total'] ? $row['total'] : 0;
        
        $salesData[] = [
                'date' => $timeRange == '30days' ? date('M d', strtotime($date)) : date('D', strtotime($date)),
            'total' => $total
        ];
        }
    }
    
    return $salesData;
}

// In your PHP code (replace the getCategoryDistribution function)
// Fixed getCategoryDistribution function
function getCategoryDistribution($conn) {
    $categories = [];
    
    $result = $conn->query("SELECT category, COUNT(*) as count FROM products GROUP BY category");
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[$row['category']] = $row['count'];
        }
    }
    
    return $categories;
}

// Fixed getRecentOrders function with parameter sanitization
function getRecentOrders($conn, $limit = 5) {
    $orders = [];
    $limit = (int)$limit; // Ensure limit is an integer
    
    $result = $conn->query("SELECT o.order_id, o.order_date, o.total_amount, o.status, 
                          CONCAT(u.first_name, ' ', u.last_name) as customer_name 
                          FROM orders o 
                          JOIN users u ON o.user_id = u.user_id 
                          ORDER BY o.order_date DESC 
                          LIMIT $limit");
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    
    return $orders;
}

// Fixed getLowStockProducts function with parameter sanitization
function getLowStockProducts($conn, $limit = 5) {
    $products = [];
    $limit = (int)$limit; // Ensure limit is an integer
    
    $result = $conn->query("SELECT id, name, stock, category 
                          FROM products 
                          WHERE stock <= 15 
                          ORDER BY stock ASC 
                          LIMIT $limit");
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    
    return $products;
}

// Get all required data
$productCount = getProductCount($conn);
$userCount = getUserCount($conn);
$orderCount = getOrderCount($conn);
$totalRevenue = getTotalRevenue($conn);

// Get the time range from GET parameter or use default
$timeRange = isset($_GET['timeRange']) ? $_GET['timeRange'] : '7days';

// Validate time range
if(!in_array($timeRange, ['7days', '30days', 'month'])) {
    $timeRange = '7days'; // Default to 7 days if invalid
}

// Get sales data for the selected time range
$salesData = getSalesData($conn, $timeRange);

$categoryDistribution = getCategoryDistribution($conn);
$recentOrders = getRecentOrders($conn);
$lowStockProducts = getLowStockProducts($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fresh Farm - Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/product.css">
    <style>
        .stats-card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            text-align: center;
            transition: transform 0.3s;
            background-color: #fff;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
            display: inline-block;
            padding: 15px;
            border-radius: 50%;
        }
        
        .stats-number {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stats-text {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .chart-container:hover {
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
            transform: translateY(-3px);
        }
        
        .chart-container h5 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 25px;
            color: #333;
        }

        .chart-container .badge {
            font-weight: 500;
            padding: 0.3em 0.6em;
            font-size: 0.75em;
        }

        .chart-content {
            position: relative;
            height: calc(100% - 40px);
            width: 100%;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .table-container {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .table-container:hover {
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
            transform: translateY(-3px);
        }
        
        .icon-orders { background-color: #e3f2fd; color: #0d6efd; }
        .icon-revenue { background-color: #e0f7fa; color: #00bcd4; }
        .icon-users { background-color: #f0f4c3; color: #cddc39; }
        .icon-products { background-color: #ffebee; color: #f44336; }
        
        /* Button styles */
        .btn-outline-secondary {
            border-color: #dee2e6;
            color: #495057;
            background-color: #fff;
            transition: all 0.2s ease;
        }
        
        .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            border-color: #ced4da;
            color: #212529;
        }
        
        /* Dropdown menu styling */
        .dropdown-menu {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            padding: 0.5rem 0;
        }
        
        .dropdown-item {
            padding: 0.5rem 1.2rem;
            font-size: 0.9rem;
            transition: all 0.15s ease;
        }
        
        .dropdown-item:hover {
            background-color: #f0f7ff;
        }
    </style>
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
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="product.php">
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
                    <span>Dashboard</span>
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
            
            <!-- Stats Cards Row -->
            <div class="row mb-4">
                <div class="col-md-6 col-xl-3 mb-4">
                    <div class="stats-card">
                        <div class="stats-icon icon-orders">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stats-number"><?php echo $orderCount; ?></div>
                        <div class="stats-text">Total Orders</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3 mb-4">
                    <div class="stats-card">
                        <div class="stats-icon icon-revenue">
                            <i class="fas fa-peso-sign"></i>
                        </div>
                        <div class="stats-number">₱<?php echo number_format($totalRevenue, 2); ?></div>
                        <div class="stats-text">Total Revenue</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3 mb-4">
                    <div class="stats-card">
                        <div class="stats-icon icon-users">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stats-number"><?php echo $userCount; ?></div>
                        <div class="stats-text">Active Users</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3 mb-4">
                    <div class="stats-card">
                        <div class="stats-icon icon-products">
                            <i class="fas fa-carrot"></i>
                        </div>
                        <div class="stats-number"><?php echo $productCount; ?></div>
                        <div class="stats-text">Total Products</div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-lg-8 mb-4">
                    <div class="chart-container">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="m-0">Sales Overview <span class="badge bg-success ms-2 fs-6">Delivered Orders</span></h5>
                            <div class="dropdown">
                                <form id="timeRangeForm" method="GET" action="">
                                    <select name="timeRange" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="7days" <?php echo (!isset($_GET['timeRange']) || $_GET['timeRange'] == '7days') ? 'selected' : ''; ?>>Last 7 days</option>
                                        <option value="30days" <?php echo (isset($_GET['timeRange']) && $_GET['timeRange'] == '30days') ? 'selected' : ''; ?>>Last 30 days</option>
                                        <option value="month" <?php echo (isset($_GET['timeRange']) && $_GET['timeRange'] == 'month') ? 'selected' : ''; ?>>This Month</option>
                                    </select>
                                </form>
                            </div>
                        </div>
                        <div class="chart-wrapper position-relative">
                        <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mb-4">
                    <div class="chart-container">
                        <h5 class="mb-4">Product Categories</h5>
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Tables Row -->
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="table-container">
                        <h5 class="mb-4">Recent Orders</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentOrders)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No recent orders</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentOrders as $order): ?>
                                            <tr>
                                                <td>#<?php echo $order['order_id']; ?></td>
                                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                                <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                                <td>
                                                    <?php
                                                        $statusClass = 'bg-success';
                                                        if ($order['status'] === 'Pending') {
                                                            $statusClass = 'bg-warning';
                                                        } elseif ($order['status'] === 'Cancelled') {
                                                            $statusClass = 'bg-danger';
                                                        }
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($order['status']); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end">
                            <a href="AdminOrders.php" class="btn btn-sm btn-outline-primary">View All Orders</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 mb-4">
                    <div class="table-container">
                        <h5 class="mb-4">Low Stock Products</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Product Name</th>
                                        <th>Category</th>
                                        <th>Stock</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($lowStockProducts)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No low stock products</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($lowStockProducts as $product): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                <td><?php echo htmlspecialchars($product['category']); ?></td>
                                                <td><?php echo $product['stock']; ?></td>
                                                <td>
                                                    <?php
                                                        $statusClass = 'bg-success';
                                                        $status = 'In Stock';
                                                        if ($product['stock'] === 0) {
                                                            $statusClass = 'bg-danger';
                                                            $status = 'Out of Stock';
                                                        } elseif ($product['stock'] <= 15) {
                                                            $statusClass = 'bg-warning';
                                                            $status = 'Low Stock';
                                                        }
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>"><?php echo $status; ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end">
                            <a href="product.php" class="btn btn-sm btn-outline-primary">View All Products</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>

<!-- Custom Scripts -->
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
        
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        let salesChart;
        
        // Initialize chart with data
        function initSalesChart() {
            // Create new chart
            salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach ($salesData as $data): ?>
                        '<?php echo $data['date']; ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Sales',
                    data: [
                        <?php foreach ($salesData as $data): ?>
                            <?php echo $data['total']; ?>,
                        <?php endforeach; ?>
                    ],
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderColor: 'rgba(59, 130, 246, 0.8)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                        pointBorderColor: 'rgba(255, 255, 255, 1)',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgba(59, 130, 246, 1)',
                        pointHoverBorderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 10,
                            right: 25,
                            bottom: 10,
                            left: 25
                        }
                    },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                                drawBorder: false,
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                padding: 10,
                                font: {
                                    size: 12,
                                    weight: '500'
                                },
                                color: 'rgba(0, 0, 0, 0.6)',
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                        },
                        ticks: {
                                padding: 10,
                                font: {
                                    size: 12,
                                    weight: '500'
                                },
                                color: 'rgba(0, 0, 0, 0.6)'
                            }
                        }
                    },
                    elements: {
                        line: {
                            tension: 0.4
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleFont: {
                                size: 14,
                                weight: 'bold'
                            },
                            bodyFont: {
                                size: 13
                            },
                            padding: 15,
                            cornerRadius: 8,
                            displayColors: false,
                        callbacks: {
                            label: function(context) {
                                    let value = context.parsed.y;
                                    return '₱' + value.toLocaleString();
                                },
                                title: function(context) {
                                    return context[0].label + ' - Sales Revenue';
                        }
                    }
                        }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                }
            }
        });
        }
        
        // Initialize chart on page load
        initSalesChart();

        // Category Distribution Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: ['Bakery', 'Dairy', 'Fruits', 'Meat', 'Vegetables'],
                datasets: [{
                    data: [
                        <?php foreach ($categoryDistribution as $count): ?>
                            <?php echo $count; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#4ade80',  // Bakery
                        '#60a5fa',  // Dairy
                        '#fb923c',  // Fruits
                        '#f87171',  // Meat
                        '#94a3b8'   // Vegetables
                    ],
                    borderWidth: 0,
                    spacing: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                layout: {
                    padding: {
                        top: 0,
                        bottom: 20,
                        left: 0,
                        right: 0
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'center',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            boxWidth: 8,
                            boxHeight: 8,
                            font: {
                                size: 13,
                                family: '-apple-system, system-ui, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
                            },
                            color: '#333'
                        }
                    },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 10,
                        titleFont: {
                            size: 13
                        },
                        bodyFont: {
                            size: 13
                        },
                        displayColors: true,
                        boxWidth: 8,
                        boxHeight: 8,
                        usePointStyle: true,
                        callbacks: {
                            label: function(context) {
                                const value = context.raw || 0;
                                return ` ${context.label}: ${value}`;
                            }
                        }
                    }
                }
            }
        });
    });
</script>

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

</body>
</html>