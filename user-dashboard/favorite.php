<?php
// Start session and include database connection
session_start();
require_once '../connect.php';

// Handle logout action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action']) && $data['action'] === 'logout') {
        // Clear all session variables
        $_SESSION = array();
        
        // Destroy the session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        // Destroy the session
        session_destroy();
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Logged out successfully']);
        exit();
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get user's order count
$orderCount = 0;
try {
    $orderStmt = $conn->prepare("SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?");
    $orderStmt->bind_param("i", $user_id);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    $orderCount = $orderResult->fetch_assoc()['order_count'];
} catch (Exception $e) {
    error_log("Error getting order count: " . $e->getMessage());
}

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

// Handle profile update
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = $_POST['first_name'];
    $lastName = $_POST['last_name'];
    $phone = !empty($_POST['phone']) ? $_POST['phone'] : null;
    $birthDate = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;

    // Handle file upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_image']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($filetype), $allowed)) {
            $tempname = $_FILES['profile_image']['tmp_name'];
            $folder = "../uploads/profile_images/";
            
            // Create directory if it doesn't exist
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            // Delete previous profile image if exists
            if (!empty($user['profile_image']) && file_exists('../' . $user['profile_image'])) {
                unlink('../' . $user['profile_image']);
            }
            
            // Generate unique filename
            $new_filename = "profile_" . $user_id . "_" . time() . "." . $filetype;
            $filepath = $folder . $new_filename;
            
            if (move_uploaded_file($tempname, $filepath)) {
                // Update database with new image path
                $relative_path = "uploads/profile_images/" . $new_filename;
                $updateStmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE user_id = ?");
                $updateStmt->bind_param("si", $relative_path, $user_id);
                $updateStmt->execute();
            }
        }
    }

    // Update user information - update both phone and phone_number fields for compatibility
    $updateStmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, phone_number = ?, birth_date = ? WHERE user_id = ?");
    $updateStmt->bind_param("sssssi", $firstName, $lastName, $phone, $phone, $birthDate, $user_id);
    
    if ($updateStmt->execute()) {
        $message = "Profile updated successfully!";
        $messageType = "success";
        
        // Refresh user data
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
    } else {
        $message = "Error updating profile. Please try again.";
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favorites - FarmFresh</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/user.css">
    <link rel="stylesheet" href="../css/favorite.css">
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="toggle-container">
                <button class="toggle-btn" id="sidebarToggle">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <a href="user.php" class="back-button">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
            </div>
            <div class="user-info">
                <img src="<?php echo !empty($user['profile_image']) ? '../' . $user['profile_image'] : '../assets/images/default-avatar.jpg'; ?>" 
                     alt="User Avatar" class="user-avatar">
                <h3><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h3>
                <p><?php echo $user['email']; ?></p>
                <div class="user-stats">
                    <div class="stat">
                        <div class="stat-number"><?php echo isset($orderCount) ? $orderCount : '0'; ?></div>
                        <div class="stat-label">Orders</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number"><?php echo isset($favoriteCount) ? $favoriteCount : '0'; ?></div>
                        <div class="stat-label">Favorites</div>
                    </div>
                </div>
            </div>
            <ul class="menu-items">
                <li><a href="orders.php"><i class="fa-solid fa-bag-shopping"></i> My Orders</a></li>
                <li><a href="profile.php"><i class="fa-solid fa-user"></i> Profile</a></li>
                <li><a href="payment.php"><i class="fa-solid fa-credit-card"></i> Payment Methods</a></li>
                <li><a href="address.php"><i class="fa-solid fa-location-dot"></i> Delivery Address</a></li>
                <li><a href="favorite.php" class="active"><i class="fa-solid fa-heart"></i> Favorites</a></li>
                <li><a href="settings.php"><i class="fa-solid fa-gear"></i> Account Settings</a></li>
                <li><a href="contact.php"><i class="fa-solid fa-phone"></i> Contact Us</a></li>
                <li><a href="#" onclick="showLogoutModal(); return false;" class="logout-link">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-wrapper">
                <h1 class="page-title">My Favorites</h1>
                <div class="products-grid">
                    <?php
                    // Get user's favorite products with product details
                    $query = "SELECT p.*, f.favorite_id 
                             FROM favorites f 
                             JOIN products p ON f.product_id = p.id 
                             WHERE f.user_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result && $result->num_rows > 0) {
                        while ($product = $result->fetch_assoc()) {
                            $statusClass = '';
                            $badgeText = '';
                            if ($product['stock'] <= 0) {
                                $statusClass = 'out-of-stock';
                                $badgeText = 'Out of Stock';
                            } elseif ($product['stock'] <= 15) {
                                $statusClass = 'low-stock';
                                $badgeText = 'Low Stock';
                            } else {
                                $statusClass = 'in-stock';
                                $badgeText = 'In Stock';
                            }
                            ?>
                            <div class="product-card <?php echo $statusClass; ?>" 
                                 data-product-id="<?php echo $product['id']; ?>"
                                 data-stock="<?php echo $product['stock']; ?>"
                                 data-unit="<?php echo $product['unit']; ?>">
                                <button class="remove-favorite" onclick="removeFavorite(<?php echo $product['id']; ?>)">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                                <div class="product-image-container">
                                    <img src="../<?php echo $product['image_path']; ?>" alt="<?php echo $product['name']; ?>" class="product-image">
                                    <span class="stock-badge"><?php echo $badgeText; ?></span>
                                    <span class="category-badge"><?php echo strtoupper($product['category']); ?></span>
                                </div>
                                <div class="product-info">
                                    <h3 class="product-title"><?php echo $product['name']; ?></h3>
                                    <div class="price-container">
                                        <span class="price-symbol">₱</span>
                                        <span class="price-amount"><?php echo number_format($product['price'], 2); ?></span>
                                        <span class="price-unit">/ <?php echo $product['unit']; ?></span>
                                    </div>
                                    <p class="product-description"><?php echo $product['description']; ?></p>
                                    <div class="stock-info">
                                        <?php echo $product['stock']; ?> <?php echo $product['unit'] . ($product['stock'] > 1 ? 's' : ''); ?> available
                                    </div>
                                    <div class="product-actions">
                                        <button class="add-to-cart-btn" onclick="showProductCartModal(<?php echo $product['id']; ?>)" <?php echo ($product['stock'] <= 0) ? 'disabled' : ''; ?>>
                                            <i class="fa-solid fa-cart-shopping"></i>
                                            ADD TO CART
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        ?>
                        <div class="empty-favorites">
                            <i class="fa-solid fa-heart"></i>
                            <h3>No favorites yet</h3>
                            <p>Start adding your favorite products to your collection!</p>
                            <a href="user.php" class="shop-now-btn">
                                <i class="fa-solid fa-store"></i>
                                Shop Now
                            </a>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </main>
    </div>



    <!-- Add to Cart Modal -->
    <div class="cart-modal-overlay" id="cartModal" style="display:none;">
        <div class="cart-modal">
            <div class="cart-modal-header">
                <h2 class="cart-modal-title">Add to Cart</h2>
                <button class="cart-modal-close">&times;</button>
            </div>
            <div class="cart-modal-content">
                <div class="product-preview">
                    <img src="" alt="" class="preview-image" id="cartPreviewImage">
                    <div class="preview-details">
                        <h3 class="preview-title" id="cartPreviewTitle"></h3>
                        <p class="preview-price" id="cartPreviewPrice"></p>
                    </div>
                </div>
                <div class="quantity-selector">
                    <button class="quantity-btn" id="cartDecreaseQuantity">-</button>
                    <input type="number" class="quantity-input" id="cartQuantityInput" value="1" min="1">
                    <button class="quantity-btn" id="cartIncreaseQuantity">+</button>
                </div>
                <p class="availability-info" id="cartStockInfo"></p>
                <div class="total-price-container">
                    <p class="total-price-label">Total:</p>
                    <p class="total-price-value" id="cartTotalPrice">₱0.00</p>
                </div>
            </div>
            <div class="cart-modal-actions">
                <button class="modal-btn cancel-btn" id="cancelCartBtn">CANCEL</button>
                <button class="modal-btn confirm-btn" id="addToCartBtn">ADD TO CART</button>
            </div>
        </div>
    </div>

    <!-- Address Selection Modal -->
    <div class="selection-modal-overlay" id="addressSelectionModal" style="display:none;">
        <div class="selection-modal">
            <div class="selection-modal-header">
                <h2>Select Delivery Address</h2>
                <button class="close-modal" onclick="hideAddressSelection()">&times;</button>
            </div>
            <div class="selection-modal-content" id="addressList">
                <!-- Address list will be populated here -->
            </div>
        </div>
    </div>

    <!-- Payment Selection Modal -->
    <div class="selection-modal-overlay" id="paymentSelectionModal" style="display:none;">
        <div class="selection-modal">
            <div class="selection-modal-header">
                <h2>Select Payment Method</h2>
                <button class="close-modal" onclick="hidePaymentSelection()">&times;</button>
            </div>
            <div class="selection-modal-content" id="paymentList">
                <!-- Payment method list will be populated here -->
            </div>
        </div>
    </div>

    <!-- Logout Modal -->
    <div class="modal-overlay" id="logoutModal" style="display:none;">
        <div class="logout-modal">
            <h2>Logout Confirmation</h2>
            <p>Are you sure you want to logout from your account?</p>
            <div class="modal-buttons">
                <button class="modal-btn cancel-btn" onclick="hideLogoutModal()">Cancel</button>
                <button class="modal-btn confirm-btn" onclick="confirmLogout(this)">
                    <span class="btn-text">Logout</span>
                    <span class="loading-spinner" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i>
                    </span>
                </button>
            </div>
        </div>
    </div>

    <script>
        // State variables that need global scope
        let currentCartProductId = null;
        let currentCartProductPrice = 0;

        // Modal Control Functions
        function showModal(modalId) {
            console.log(`Showing modal: ${modalId}`);
            const modalOverlay = document.getElementById(modalId);
            
            if (!modalOverlay) {
                console.error(`Modal overlay not found: ${modalId}`);
                return;
            }
            
            // Make sure display is flex for proper centering and display
            modalOverlay.style.display = 'flex';
            
            // Find the modal content element
            let modalContent;
            if (modalId === 'cartModal') {
                modalContent = modalOverlay.querySelector('.cart-modal');
            } else if (modalId.includes('Selection')) {
                modalContent = modalOverlay.querySelector('.selection-modal');
            } else {
                modalContent = modalOverlay.querySelector('.logout-modal');
            }
            
            if (!modalContent) {
                console.error(`Modal content not found in ${modalId}`);
                return;
            }
            
            // Add the active class to trigger animation
            setTimeout(() => {
                modalContent.classList.add('active');
            }, 10);
            
            // Prevent background scrolling
            document.body.style.overflow = 'hidden';
            
            console.log(`Modal shown successfully: ${modalId}`);
        }

        function hideModal(modalId) {
            console.log(`Hiding modal: ${modalId}`);
            const modalOverlay = document.getElementById(modalId);
            
            if (!modalOverlay) {
                console.error(`Modal overlay not found: ${modalId}`);
                return;
            }
            
            // Find the modal content element
            let modalContent;
            if (modalId === 'cartModal') {
                modalContent = modalOverlay.querySelector('.cart-modal');
            } else if (modalId.includes('Selection')) {
                modalContent = modalOverlay.querySelector('.selection-modal');
            } else {
                modalContent = modalOverlay.querySelector('.logout-modal');
            }
            
            if (!modalContent) {
                console.error(`Modal content not found in ${modalId}`);
                return;
            }
            
            // Remove the active class to trigger animation
            modalContent.classList.remove('active');
            
            // Wait for animation to complete before hiding
            setTimeout(() => {
                modalOverlay.style.display = 'none';
                // Restore background scrolling
                document.body.style.overflow = '';
            }, 250);
            
            console.log(`Modal hidden successfully: ${modalId}`);
        }



        // Cart Modal Functions
        function showCartModal(productData) {
            // Clear any previous data
            const cartPreviewImage = document.getElementById('cartPreviewImage');
            const cartPreviewTitle = document.getElementById('cartPreviewTitle');
            const cartPreviewPrice = document.getElementById('cartPreviewPrice');
            const cartStockInfo = document.getElementById('cartStockInfo');
            const cartQuantityInput = document.getElementById('cartQuantityInput');
            
            if (!cartPreviewImage || !cartPreviewTitle || !cartPreviewPrice || !cartStockInfo || !cartQuantityInput) {
                console.error('Cart modal elements not found');
                return;
            }
            
            // Load new product data
            cartPreviewImage.src = productData.image;
            cartPreviewTitle.textContent = productData.name;
            cartPreviewPrice.textContent = `₱${parseFloat(productData.price).toFixed(2)}`;
            cartStockInfo.textContent = `Available: ${productData.stock} ${productData.unit}${productData.stock > 1 ? 's' : ''}`;
            
            // Initialize quantity and price
            cartQuantityInput.value = 1;
            cartQuantityInput.max = productData.stock;
            currentCartProductId = productData.id;
            currentCartProductPrice = parseFloat(productData.price);
            updateCartTotal();
            
            // Show modal
            showModal('cartModal');
        }

        function hideCartModal() {
            hideModal('cartModal');
        }

        // Helper Functions
        function updateCartTotal() {
            const cartQuantityInput = document.getElementById('cartQuantityInput');
            const cartTotalPrice = document.getElementById('cartTotalPrice');
            
            if (!cartQuantityInput || !cartTotalPrice) return;
            
            const quantity = parseInt(cartQuantityInput.value) || 1;
            const total = quantity * currentCartProductPrice;
            cartTotalPrice.textContent = `₱${total.toFixed(2)}`;
        }

        // Selection Modal Functions
        function showAddressSelection() { 
            showModal('addressSelectionModal');
        }

        function hideAddressSelection() {
            hideModal('addressSelectionModal');
        }

        function showPaymentSelection() { 
            showModal('paymentSelectionModal');
        }

        function hidePaymentSelection() {
            hideModal('paymentSelectionModal');
        }

        // Logout Modal Functions
        function showLogoutModal() {
            showModal('logoutModal');
        }

        function hideLogoutModal() {
            hideModal('logoutModal');
        }

        // Enhanced default address loading
        function loadDefaultAddress() {
            fetch('get_addresses.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                const selectedAddressDiv = document.getElementById('selectedAddress');
                const noAddressMsg = document.querySelector('.no-address-msg');
                
                if (!selectedAddressDiv) return;
                
                if (data.status === 'success' && data.addresses && data.addresses.length > 0) {
                    const defaultAddress = data.addresses.find(addr => addr.is_default) || data.addresses[0];
                    selectedAddressId = defaultAddress.id;
                    
                    selectedAddressDiv.innerHTML = `
                        <div class="address-info">
                            <p class="address-type">${defaultAddress.address_type} Address</p>
                            <p class="recipient">${defaultAddress.recipient_name}</p>
                            <p class="address-line">${defaultAddress.street}</p>
                            <p class="address-line">${defaultAddress.city}, ${defaultAddress.region}</p>
                            <p class="address-line">Philippines ${defaultAddress.postal_code}</p>
                            <p class="phone">Phone: ${defaultAddress.phone_number}</p>
                        </div>
                    `;
                    
                    if (noAddressMsg) {
                        noAddressMsg.style.display = 'none';
                    }
                } else {
                    selectedAddressId = null;
                    if (noAddressMsg) {
                        noAddressMsg.style.display = 'block';
                    }
                }
            })
            .catch(error => {
                console.error('Error loading addresses:', error);
                selectedAddressId = null;
                const noAddressMsg = document.querySelector('.no-address-msg');
                if (noAddressMsg) {
                    noAddressMsg.style.display = 'block';
                }
                showNotification('Could not load delivery addresses. Please try again.', 'error');
            });
        }

        // Function to show product cart modal
        function showProductCartModal(productId) {
            // Find the product card with this ID
            const productCard = document.querySelector(`.product-card[data-product-id="${productId}"]`);
            if (!productCard) {
                console.error('Product card not found for ID:', productId);
                return;
            }
            
            try {
                const productName = productCard.querySelector('.product-title').textContent;
                const productPrice = productCard.querySelector('.price-amount').textContent;
                const productImage = productCard.querySelector('.product-image').src;
                const productStock = productCard.dataset.stock;
                const productUnit = productCard.dataset.unit;
                
                const productData = {
                    id: productId,
                    name: productName,
                    price: productPrice.replace(/[^\d.]/g, ''),
                    image: productImage,
                    stock: parseInt(productStock),
                    unit: productUnit
                };
                
                showCartModal(productData);
            } catch (error) {
                console.error('Error preparing product data:', error);
                showNotification('Error opening product modal. Please try again.', 'error');
            }
        }

        // Set up event listeners when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM fully loaded - setting up modal event listeners');
            

            
            const cartModalOverlay = document.getElementById('cartModal');
            if (cartModalOverlay) {
                cartModalOverlay.addEventListener('click', function(e) {
                    if (e.target === cartModalOverlay) {
                        hideCartModal();
                    }
                });
            }
            
            const addressSelectionModal = document.getElementById('addressSelectionModal');
            if (addressSelectionModal) {
                addressSelectionModal.addEventListener('click', function(e) {
                    if (e.target === addressSelectionModal) {
                        hideAddressSelection();
                    }
                });
            }
            
            const paymentSelectionModal = document.getElementById('paymentSelectionModal');
            if (paymentSelectionModal) {
                paymentSelectionModal.addEventListener('click', function(e) {
                    if (e.target === paymentSelectionModal) {
                        hidePaymentSelection();
                    }
                });
            }
            
            const logoutModal = document.getElementById('logoutModal');
            if (logoutModal) {
                logoutModal.addEventListener('click', function(e) {
                    if (e.target === logoutModal) {
                        hideLogoutModal();
                    }
                });
            }
            

            
            // Set up Cart Modal Events
            const cartModal = cartModalOverlay ? cartModalOverlay.querySelector('.cart-modal') : null;
            const cartModalClose = cartModal ? cartModal.querySelector('.cart-modal-close') : null;
            const cancelCartBtn = document.getElementById('cancelCartBtn');
            const addToCartBtn = document.getElementById('addToCartBtn');
            const cartQuantityInput = document.getElementById('cartQuantityInput');
            const cartDecreaseQuantityBtn = document.getElementById('cartDecreaseQuantity');
            const cartIncreaseQuantityBtn = document.getElementById('cartIncreaseQuantity');
            
            if (cartModalClose) cartModalClose.addEventListener('click', hideCartModal);
            if (cancelCartBtn) cancelCartBtn.addEventListener('click', hideCartModal);
            
            if (addToCartBtn) {
                addToCartBtn.addEventListener('click', function() {
                    if (!cartQuantityInput) return;
                    
                    const quantity = parseInt(cartQuantityInput.value) || 1;
                    addToCart(currentCartProductId, quantity);
                    hideCartModal();
                });
            }
            
            if (cartDecreaseQuantityBtn && cartQuantityInput) {
                cartDecreaseQuantityBtn.addEventListener('click', () => {
                    let value = parseInt(cartQuantityInput.value);
                    if (value > 1) {
                        cartQuantityInput.value = value - 1;
                        updateCartTotal();
                    }
                });
            }
            
            if (cartIncreaseQuantityBtn && cartQuantityInput) {
                cartIncreaseQuantityBtn.addEventListener('click', () => {
                    let value = parseInt(cartQuantityInput.value);
                    let max = parseInt(cartQuantityInput.max);
                    if (value < max) {
                        cartQuantityInput.value = value + 1;
                        updateCartTotal();
                    }
                });
            }
            
            if (cartQuantityInput) {
                cartQuantityInput.addEventListener('change', () => {
                    let value = parseInt(cartQuantityInput.value);
                    let max = parseInt(cartQuantityInput.max);
                    if (isNaN(value) || value < 1) value = 1;
                    if (value > max) value = max;
                    cartQuantityInput.value = value;
                    updateCartTotal();
                });
            }
            
            console.log('Modal event listeners setup complete');
        });

        // Add to Cart Function
        function addToCart(productId, quantity = 1) {
            fetch('../add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    quantity: quantity
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.status) {
                    // Show success notification
                    showNotification(data.message, 'success');
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error adding to cart:', error);
                showNotification('An error occurred while adding to cart. Please try again.', 'error');
            });
        }

        // Notification function
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            let container = document.getElementById('notification-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'notification-container';
                container.style.cssText = `
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    z-index: 1000;
                    display: flex;
                    flex-direction: column;
                    align-items: flex-end;
                    pointer-events: none;
                `;
                document.body.appendChild(container);
            }
            notification.style.opacity = '0';
            notification.style.transform = 'translateY(20px)';
            container.appendChild(notification);
            
            // Force a reflow
            notification.offsetHeight;
            
            notification.style.opacity = '1';
            notification.style.transform = 'translateY(0)';
            
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(20px)';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Existing sidebar toggle functionality
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        const toggleIcon = sidebarToggle.querySelector('i');

        function toggleSidebar() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            if (sidebar.classList.contains('collapsed')) {
                toggleIcon.classList.remove('fa-bars');
                toggleIcon.classList.add('fa-times');
            } else {
                toggleIcon.classList.remove('fa-times');
                toggleIcon.classList.add('fa-bars');
            }
            
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }

        sidebarToggle.addEventListener('click', toggleSidebar);

        // Check saved sidebar state
        document.addEventListener('DOMContentLoaded', () => {
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (sidebarCollapsed) {
                toggleSidebar();
            }
        });

        // Remove favorite functionality
        function removeFavorite(productId) {
            if (confirm('Are you sure you want to remove this item from your favorites?')) {
                fetch('../toggle_favorite.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        product_id: productId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        const productCard = document.querySelector(`.product-card[data-product-id="${productId}"]`);
                        productCard.remove();
                        
                        const favoriteCountElement = document.querySelector('.user-stats .stat:nth-child(2) .stat-number');
                        if (favoriteCountElement) {
                            favoriteCountElement.textContent = data.favorite_count;
                        }

                        if (data.favorite_count === 0) {
                            const productsGrid = document.querySelector('.products-grid');
                            productsGrid.innerHTML = `
                                <div class="empty-favorites">
                                    <i class="fa-solid fa-heart"></i>
                                    <h3>No favorites yet</h3>
                                    <p>Start adding your favorite products to your collection!</p>
                                    <a href="user.php" class="shop-now-btn">
                                        <i class="fa-solid fa-store"></i>
                                        Shop Now
                                    </a>
                                </div>
                            `;
                        }
                        
                        showNotification('Item removed from favorites', 'success');
                    }
                })
    .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred while removing the item from favorites', 'error');
                });
            }
        }

        function confirmLogout(button) {
            const btnText = button.querySelector('.btn-text');
            const spinner = button.querySelector('.loading-spinner');

            btnText.style.display = 'none';
            spinner.style.display = 'inline-block';
            button.disabled = true;

            fetch('', { // POST to the same page
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'logout' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showNotification('Logging out...', 'success');
                    setTimeout(() => {
                    window.location.href = '../index.php';
                    }, 500);
                } else {
                    showNotification(data.message || 'Logout failed', 'error');
                    btnText.style.display = 'inline-block';
                    spinner.style.display = 'none';
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Logout error:', error);
                showNotification('An error occurred during logout', 'error');
                btnText.style.display = 'inline-block';
                spinner.style.display = 'none';
                button.disabled = false;
            });
        }
    </script>

    <style>
        /* Notification Styles */
        #notification-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 2500;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
            pointer-events: none;
        }

        .notification {
            background: white;
            color: #333;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            pointer-events: auto;
            transform: translateY(0);
            opacity: 1;
            transition: all 0.3s ease;
        }

        .notification.success {
            border-left: 4px solid #4caf50;
        }

        .notification.error {
            border-left: 4px solid #f44336;
        }

        .notification i {
            font-size: 1.2rem;
        }

        .notification.success i {
            color: #4caf50;
        }

        .notification.error i {
            color: #f44336;
        }

        /* Modal Improvements */
        .modal-overlay,
        .cart-modal-overlay,
        .selection-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
        }

        .cart-modal,
        .logout-modal,
        .selection-modal {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.25);
            transition: all 0.3s ease;
            transform: scale(0.95);
            opacity: 0;
            margin: 0;
        }

        .cart-modal.active,
        .logout-modal.active,
        .selection-modal.active {
            transform: scale(1);
            opacity: 1;
        }

        /* Cart Modal Styles (Modern Style) */
        .cart-modal {
            width: 350px;
            max-width: 90vw;
        }

        .cart-modal-header {
            position: relative;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }

        .cart-modal-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin: 0;
            text-align: center;
        }

        .cart-modal-close {
            position: absolute;
            right: 15px;
            top: 15px;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #999;
            cursor: pointer;
        }

        .cart-modal-content {
            padding: 20px;
        }

        .product-preview {
            display: flex;
            align-items: center;
            padding: 15px;
            margin-bottom: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }

        .preview-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            margin-right: 15px;
        }

        .preview-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin: 0 0 5px 0;
        }

        .preview-price {
            font-size: 1.1rem;
            font-weight: 600;
            color: #45935b;
            margin: 0;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 25px 0;
        }

        .quantity-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1px solid #ddd;
            background: white;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .quantity-btn:hover {
            background: #f5f5f5;
        }

        .quantity-input {
            width: 60px;
            height: 36px;
            margin: 0 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
            font-size: 1rem;
        }

        .availability-info {
            text-align: center;
            color: #777;
            margin-bottom: 25px;
            font-size: 0.9rem;
        }

        .cart-modal-actions {
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
            border-top: 1px solid #eee;
        }

        .cart-modal-actions .modal-btn {
            flex: 1;
            padding: 12px;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            text-transform: uppercase;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
        }

        .cart-modal-actions .cancel-btn {
            background: #f5f5f5;
            color: #333;
        }

        .cart-modal-actions .confirm-btn {
            background: #45935b;
            color: white;
        }

        .cart-modal-actions .cancel-btn:hover {
            background: #eee;
        }

        .cart-modal-actions .confirm-btn:hover {
            background: #367347;
        }



        /* Ensure product images display correctly */
        .product-image {
            max-width: 100%;
            height: auto;
            display: block;
            filter: none !important;
            -webkit-filter: none !important;
        }

        /* Total Price Container Styles */
        .total-price-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            background-color: #f9f9f9;
            border-radius: 8px;
        }

        .total-price-label {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .total-price-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: #45935b;
            margin: 0;
        }

        /* Fix any text rendering issues */        
        body {            
            text-rendering: optimizeLegibility;            
            -webkit-font-smoothing: antialiased;            
            -moz-osx-font-smoothing: grayscale;        
        }    
    </style>
</body>
</html> 