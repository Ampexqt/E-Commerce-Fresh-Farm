<?php
session_start();
require_once '../connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Fetch user info
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT first_name, last_name, email, profile_image FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Fetch order count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$orderCount = $result->fetch_assoc()['count'];

// Get user's favorites count
$favoriteCount = 0;
try {
    $favoriteStmt = $conn->prepare("SELECT COUNT(*) as favorite_count FROM favorites WHERE user_id = ?");
    $favoriteStmt->bind_param("i", $user_id);
    $favoriteStmt->execute();
    $favoriteResult = $favoriteStmt->get_result();
    $favoriteCount = $favoriteResult->fetch_assoc()['favorite_count'];
} catch (Exception $e) {
    error_log("Error getting favorite count: " . $e->getMessage());
}

// Fetch orders and their items
$orders = [];
$stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($order = $result->fetch_assoc()) {
    $order_id = $order['order_id']; // <-- fix here
    $item_stmt = $conn->prepare("SELECT oi.quantity, p.name, p.price, p.image_path FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    $item_stmt->bind_param("i", $order_id);
    $item_stmt->execute();
    $item_result = $item_stmt->get_result();
    $items = [];
    while ($item = $item_result->fetch_assoc()) {
        $items[] = $item;
    }
    $order['items'] = $items;
    $orders[] = $order;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
      <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/user.css">
    <link rel="stylesheet" href="../css/profile.css">
    <link rel="stylesheet" href="../css/orders.css">
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="toggle-container">
                <button class="toggle-btn" id="sidebarToggle">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
            <div class="user-info">
                <img src="<?php echo !empty($user['profile_image']) ? '../' . $user['profile_image'] : '../assets/images/default-avatar.jpg'; ?>" 
                     alt="User Avatar" class="user-avatar">
                <h3><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h3>
                <p><?php echo $user['email']; ?></p>
                <div class="user-stats">
                    <div class="stat">
                        <div class="stat-number"><?php echo $orderCount ?? '0'; ?></div>
                        <div class="stat-label">Orders</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number"><?php echo $favoriteCount ?? '0'; ?></div>
                        <div class="stat-label">Favorites</div>
                    </div>
                </div>
            </div>
            <ul class="menu-items">
                <li><a href="orders.php" class="active"><i class="fa-solid fa-bag-shopping"></i> My Orders</a></li>
                <li><a href="profile.php"><i class="fa-solid fa-user"></i> Profile</a></li>
                <li><a href="payment.php"><i class="fa-solid fa-credit-card"></i> Payment Methods</a></li>
                <li><a href="address.php"><i class="fa-solid fa-location-dot"></i> Delivery Address</a></li>
                <li><a href="favorite.php"><i class="fa-solid fa-heart"></i> Favorites</a></li>
                <li><a href="settings.php"><i class="fa-solid fa-gear"></i> Account Settings</a></li>
                <li><a href="contact.php"><i class="fa-solid fa-phone"></i> Contact Us</a></li>
                <li><a href="#" id="logoutBtn" class="logout-link">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
        <a href="user.php" class="back-button">
        <i class="fa fa-arrow-left" aria-hidden="true"></i>
    </a>

            <div class="orders-container">
                <div class="orders-header">
                    <h2>My Orders</h2>
                    <p>View and manage your recent orders</p>
                </div>

                <?php if (!empty($orders)): ?>
                    <div class="orders-list">
                        <?php foreach ($orders as $order): ?>
                            <div class="order-card" data-order-id="<?php echo $order['order_id']; ?>">
                                <div class="order-card-header">
                                    <div class="order-meta">
                                        <span class="order-date-display"><?php echo date('F j, Y', strtotime($order['order_date'])); ?></span>
                                        <span class="order-id-display">Order #<?php echo htmlspecialchars($order['order_code'] ?? $order['order_id']); ?></span>
                                    </div>
                                    <span class="order-status-badge status-<?php echo strtolower(htmlspecialchars($order['status'])); ?>">
                                        <?php echo strtoupper(htmlspecialchars($order['status'])); ?>
                                    </span>
                                </div>

                                <div class="order-items-grid">
                                    <?php foreach ($order['items'] as $item): ?>
                                        <div class="order-item-card">
                                            <img src="<?php echo '../' . htmlspecialchars($item['image_path'] ?? 'assets/images/default-product.png'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="order-item-image">
                                            <div class="order-item-info">
                                                <p class="order-item-name"><?php echo htmlspecialchars($item['name']); ?></p>
                                                <p class="order-item-price">₱<?php echo number_format($item['price'], 2); ?>/unit</p> <!-- Assuming price is per unit -->
                                                <p class="order-item-qty">Qty: <?php echo (int)$item['quantity']; ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="order-card-footer">
                                    <div class="order-total-amount">
                                        <strong>Total: ₱<?php echo number_format($order['total_amount'], 2); ?></strong>
                                    </div>
                                    <div class="order-actions-new">
                                        <?php 
                                        $status_lower = strtolower($order['status']);
                                        
                                        // Reorder Button (if applicable)
                                        if ($status_lower === 'delivered' || $status_lower === 'completed'): 
                                        ?>
                                            <a href="#" class="btn-reorder"><i class="fa-solid fa-rotate-right"></i> Reorder</a>
                                        <?php endif; ?>

                                        <?php 
                                        // Cancel Order Button (if applicable)
                                        if ($status_lower === 'pending'): 
                                        ?>
                                            <button class="btn-cancel-order-new" data-order-id="<?php echo $order['order_id']; ?>">
                                                <i class="fa fa-times"></i> Cancel Order
                                            </button>
                                        <?php endif; ?>

                                        <!-- View Details Button (always shown) -->
                                        <button class="btn-view-details-new" data-order-id="<?php echo $order['order_id']; ?>">
                                            <i class="fa fa-eye"></i> View Details
                                        </button>

                                        <?php 
                                        // Order Received Button (if applicable)
                                        if ($status_lower === 'shipped'): 
                                        ?>
                                            <button class="btn-order-received" data-order-id="<?php echo $order['order_id']; ?>">
                                                <i class="fa-solid fa-check-circle"></i> Order Received
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-orders-message">
                        <i class="fa fa-box-open"></i>
                        <p>You have no orders yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Cancel Order Modal -->
    <div id="cancelOrderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Cancel Order</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this order?</p>
                <p>Order #<span id="cancelOrderId"></span></p>
                <div id="cancelErrorMsg" class="alert alert-danger" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel">No, Keep Order</button>
                <button class="btn-confirm" id="confirmCancel">
                    <span class="btn-text">Yes, Cancel Order</span>
                    <span class="loading-spinner" style="display: none;">
                        <i class="fa fa-spinner fa-spin"></i>
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- View Order Details Modal -->
    <div id="orderDetailsModal" class="modal">
        <div class="modal-content" style="width: 600px; max-width: 95%;">
            <div class="modal-header">
                <h2>Order Details</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body" id="orderDetailsContent">
                <div class="order-details-loading" style="text-align: center; padding: 30px;">
                    <i class="fa fa-spinner fa-spin fa-2x"></i>
                    <p>Loading order details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel">Close</button>
            </div>
        </div>
    </div>

    <!-- Order Received Modal -->
    <div id="orderReceivedModal" class="modal">
        <div class="modal-content" style="max-width: 450px; text-align: center;">
            <div class="modal-header" style="border: none; justify-content: flex-end; padding-bottom: 0;">
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body" style="padding-top: 0;">
                <h2 style="margin-top: 0; margin-bottom: 20px; color: #333;">Confirm Order Received</h2>
                <div class="order-received-icon" style="margin-bottom: 20px;">
                    <i class="fa-solid fa-box-open" style="font-size: 4rem; color: var(--primary-color, #3b7a57);"></i>
                </div>
                <h3 style="font-size: 1.5rem; margin: 10px 0 15px;">Package Arrived?</h3>
                <p style="color: #555; margin-bottom: 5px;">Please confirm that you have received your order.</p>
                <p style="font-weight: 500; margin: 15px 0;">Order #<span id="receivedOrderId"></span></p>
                <div id="receivedErrorMsg" class="alert alert-danger" style="display: none; margin: 15px 0; padding: 10px; border-radius: 5px; background-color: #f8d7da; color: #721c24;"></div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: center; gap: 15px; padding: 5px 20px 25px; border: none;">
                <button class="btn-cancel" style="min-width: 120px; padding: 10px 20px; font-size: 1rem;">Cancel</button>
                <button class="btn-confirm" id="confirmReceived" style="min-width: 180px; padding: 10px 20px; font-size: 1rem; background-color: var(--primary-color, #3b7a57);">
                    <span class="btn-text">Yes, I received it</span>
                    <span class="loading-spinner" style="display: none;">
                        <i class="fa fa-spinner fa-spin"></i>
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- Simple Logout Modal -->
    <div id="logoutModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="background: white; width: 350px; max-width: 90%; margin: 15% auto; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.2); font-family: 'Poppins', sans-serif; text-align: center;">
            <h3 style="margin: 0 0 15px 0; color: #333; font-size: 1.3rem; font-weight: 600;">Confirm Logout</h3>
            <p style="margin: 0 0 25px 0; font-size: 1rem; color: #555;">Are you sure you want to logout?</p>
            <div style="display: flex; justify-content: center; gap: 12px;">
                <button id="cancelLogout" style="background: #f5f5f5; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 400; color: #333; transition: all 0.2s ease;">Cancel</button>
                <button id="confirmLogout" style="background: #3b7a57; color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 400; transition: all 0.2s ease;">Logout</button>
            </div>
        </div>
    </div>
    
    <script>
        // Basic logout functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Get elements
            const logoutBtn = document.getElementById('logoutBtn');
            const logoutModal = document.getElementById('logoutModal');
            const cancelLogout = document.getElementById('cancelLogout');
            const confirmLogout = document.getElementById('confirmLogout');
            
            // Add hover effects to buttons
            if (cancelLogout) {
                cancelLogout.addEventListener('mouseenter', function() {
                    this.style.background = '#e5e5e5';
                });
                cancelLogout.addEventListener('mouseleave', function() {
                    this.style.background = '#f1f1f1';
                });
            }
            
            if (confirmLogout) {
                confirmLogout.addEventListener('mouseenter', function() {
                    this.style.background = '#2e6045';
                });
                confirmLogout.addEventListener('mouseleave', function() {
                    this.style.background = '#3b7a57';
                });
            }
            
            // Show modal when logout button is clicked
            if (logoutBtn) {
                logoutBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    logoutModal.style.display = 'block';
                });
            }
            
            // Hide modal when cancel is clicked
            if (cancelLogout) {
                cancelLogout.addEventListener('click', function() {
                    logoutModal.style.display = 'none';
                });
            }
            
            // Logout when confirm is clicked
            if (confirmLogout) {
                confirmLogout.addEventListener('click', function() {
                    window.location.href = '../logout.php';
                });
            }
            
            // Close modal when clicking outside
            window.addEventListener('click', function(e) {
                if (e.target === logoutModal) {
                    logoutModal.style.display = 'none';
                }
            });
            
            // Close modal on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && logoutModal.style.display === 'block') {
                    logoutModal.style.display = 'none';
                }
            });
        });

        // Sidebar toggle functionality
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        const toggleIcon = sidebarToggle.querySelector('i');

        // Document ready function to ensure all elements are loaded
        document.addEventListener('DOMContentLoaded', () => {
            // Check saved sidebar state on page load
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (sidebarCollapsed) {
                toggleSidebar();
            }
            
            // Handle initial state for mobile
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            }
        });

        function toggleSidebar() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            // Adjust profile container
            const profileContainer = document.querySelector('.profile-container');
            if (profileContainer) {
                if (sidebar.classList.contains('collapsed')) {
                    profileContainer.style.marginLeft = '0';
                } else {
                    profileContainer.style.marginLeft = '280px'; // or var(--sidebar-width) if using CSS variable
                }
            }
            
            // Toggle the icon between bars and times
            if (sidebar.classList.contains('collapsed')) {
                toggleIcon.classList.remove('fa-bars');
                toggleIcon.classList.add('fa-times');
            } else {
                toggleIcon.classList.remove('fa-times');
                toggleIcon.classList.add('fa-bars');
            }
            
            // Save sidebar state to localStorage
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }

        sidebarToggle.addEventListener('click', toggleSidebar);

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            }
        });

        // Profile image upload handling
        const profileImageContainer = document.querySelector('.profile-image-container');
        const profileImageInput = document.getElementById('profileImageInput');
        const profileImagePreview = document.getElementById('profileImagePreview');

        if (profileImageContainer) {
            profileImageContainer.addEventListener('click', () => {
                profileImageInput.click();
            });
        }

        if (profileImageInput) {
            profileImageInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        profileImagePreview.src = e.target.result;
                    };
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }

        // Add alert auto-hide
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }, 3000);
        });

        // Order Cancel Modal Functionality
        const cancelBtns = document.querySelectorAll('.btn-cancel-order-new');
        const cancelModal = document.getElementById('cancelOrderModal');
        const cancelOrderIdSpan = document.getElementById('cancelOrderId');
        const confirmCancelBtn = document.getElementById('confirmCancel');
        const cancelErrorMsg = document.getElementById('cancelErrorMsg');
        
        // Open Cancel Order Modal
        cancelBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const orderId = this.getAttribute('data-order-id');
                cancelOrderIdSpan.textContent = orderId;
                cancelModal.style.display = 'block';
                cancelErrorMsg.style.display = 'none';
            });
        });
        
        // Handle Order Cancellation
        confirmCancelBtn.addEventListener('click', function() {
            const orderId = cancelOrderIdSpan.textContent;
            const loadingSpinner = this.querySelector('.loading-spinner');
            const btnText = this.querySelector('.btn-text');
            
            // Show loading state
            loadingSpinner.style.display = 'inline-block';
            btnText.textContent = 'Cancelling...';
            this.disabled = true;
            
            // AJAX request to cancel order
            fetch('cancel-order-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'order_id=' + orderId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Success - reload the page to reflect changes
                    window.location.reload();
                } else {
                    // Error handling
                    loadingSpinner.style.display = 'none';
                    btnText.textContent = 'Yes, Cancel Order';
                    this.disabled = false;
                    cancelErrorMsg.textContent = data.message || 'Failed to cancel order. Please try again.';
                    cancelErrorMsg.style.display = 'block';
                }
            })
            .catch(error => {
                // Error handling
                loadingSpinner.style.display = 'none';
                btnText.textContent = 'Yes, Cancel Order';
                this.disabled = false;
                cancelErrorMsg.textContent = 'An error occurred. Please try again.';
                cancelErrorMsg.style.display = 'block';
                console.error('Error:', error);
            });
        });
        
        // Order Details Modal Functionality
        const viewDetailsBtns = document.querySelectorAll('.btn-view-details-new');
        const detailsModal = document.getElementById('orderDetailsModal');
        const orderDetailsContent = document.getElementById('orderDetailsContent');
        
        // Open View Details Modal
        viewDetailsBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const orderId = this.getAttribute('data-order-id');
                detailsModal.style.display = 'block';
                
                // Show loading state
                orderDetailsContent.innerHTML = `
                    <div class="order-details-loading" style="text-align: center; padding: 30px;">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                        <p>Loading order details...</p>
                    </div>
                `;
                
                // AJAX request to get order details
                fetch('get-order-details.php?id=' + orderId)
                    .then(response => response.text())
                    .then(data => {
                        orderDetailsContent.innerHTML = data;
                    })
                    .catch(error => {
                        orderDetailsContent.innerHTML = `
                            <div class="alert alert-danger">
                                Failed to load order details. Please try again.
                            </div>
                        `;
                        console.error('Error:', error);
                    });
            });
        });
        
        // Order Received Modal Functionality
        const receiveBtns = document.querySelectorAll('.btn-order-received');
        const receiveModal = document.getElementById('orderReceivedModal');
        const receivedOrderIdSpan = document.getElementById('receivedOrderId');
        const confirmReceivedBtn = document.getElementById('confirmReceived');
        const receivedErrorMsg = document.getElementById('receivedErrorMsg');
        
        // Open Order Received Modal
        receiveBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const orderId = this.getAttribute('data-order-id');
                receivedOrderIdSpan.textContent = orderId;
                receiveModal.style.display = 'block';
                receivedErrorMsg.style.display = 'none';
            });
        });
        
        // Handle Order Received Confirmation
        confirmReceivedBtn.addEventListener('click', function() {
            const orderId = receivedOrderIdSpan.textContent;
            const loadingSpinner = this.querySelector('.loading-spinner');
            const btnText = this.querySelector('.btn-text');
            
            // Show loading state
            loadingSpinner.style.display = 'inline-block';
            btnText.textContent = 'Processing...';
            this.disabled = true;
            
            // AJAX request to update order status
            fetch('mark-as-received-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'order_id=' + orderId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the UI to show order is delivered
                    const orderCard = document.querySelector(`.order-card[data-order-id="${orderId}"]`) || 
                                     document.querySelector(`.btn-order-received[data-order-id="${orderId}"]`).closest('.order-card');
                    
                    if (orderCard) {
                        // Update status badge
                        const statusBadge = orderCard.querySelector('.order-status-badge');
                        if (statusBadge) {
                            statusBadge.textContent = 'DELIVERED';
                            statusBadge.className = 'order-status-badge status-delivered';
                        }
                        
                        // Remove the "Order Received" button
                        const receivedBtn = orderCard.querySelector('.btn-order-received');
                        if (receivedBtn) {
                            receivedBtn.remove();
                        }
                    }
                    
                    // Close the modal
                    receiveModal.style.display = 'none';
                    
                    // Show success message
                    const successAlert = document.createElement('div');
                    successAlert.className = 'alert alert-success';
                    successAlert.style.padding = '15px';
                    successAlert.style.marginBottom = '20px';
                    successAlert.style.borderRadius = '8px';
                    successAlert.style.backgroundColor = '#e6f4ea';
                    successAlert.style.color = '#3b7a57';
                    successAlert.style.fontWeight = '500';
                    successAlert.style.textAlign = 'center';
                    successAlert.style.position = 'fixed';
                    successAlert.style.top = '20px';
                    successAlert.style.left = '50%';
                    successAlert.style.transform = 'translateX(-50%)';
                    successAlert.style.zIndex = '1000';
                    successAlert.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
                    successAlert.innerHTML = '<i class="fa-solid fa-check-circle" style="margin-right: 8px;"></i> Order marked as delivered!';
                    document.body.appendChild(successAlert);
                    
                    // Remove success message after 3 seconds
                    setTimeout(() => {
                        successAlert.style.opacity = '0';
                        successAlert.style.transition = 'opacity 0.3s ease';
                        setTimeout(() => {
                            successAlert.remove();
                        }, 300);
                    }, 3000);
                } else {
                    // Error handling
                    loadingSpinner.style.display = 'none';
                    btnText.textContent = 'Yes, I received it';
                    this.disabled = false;
                    receivedErrorMsg.textContent = data.message || 'Failed to update order status. Please try again.';
                    receivedErrorMsg.style.display = 'block';
                }
            })
            .catch(error => {
                // Error handling
                console.error('Error:', error);
                loadingSpinner.style.display = 'none';
                btnText.textContent = 'Yes, I received it';
                this.disabled = false;
                receivedErrorMsg.textContent = 'An error occurred. Please try again.';
                receivedErrorMsg.style.display = 'block';
            });
        });
        
        // Close Modal Functionality (for all modals)
        const closeModalBtns = document.querySelectorAll('.close-modal, .modal .btn-cancel');
        const modals = document.querySelectorAll('.modal');
        
        closeModalBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                modals.forEach(modal => {
                    modal.style.display = 'none';
                });
            });
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            modals.forEach(modal => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
        
        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                modals.forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        });
    </script>
</body>
</html>