<?php
// Start session and include database connection
session_start();
require_once '../connect.php';

// Handle logout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the raw POST data
    $json_data = file_get_contents('php://input');
    $post_data = json_decode($json_data, true);
    
    if (isset($post_data['action']) && $post_data['action'] === 'logout') {
        session_destroy();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
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

// Check if favorites table exists, if not create it
try {
    $conn->query("SELECT 1 FROM favorites LIMIT 1");
} catch (Exception $e) {
    $createTableSQL = "CREATE TABLE IF NOT EXISTS favorites (
        favorite_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )";
    $conn->query($createTableSQL);
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

// Get user's favorited products
$favoritedProducts = [];
try {
    $favoriteProductsStmt = $conn->prepare("SELECT product_id FROM favorites WHERE user_id = ?");
    $favoriteProductsStmt->bind_param("i", $user_id);
    $favoriteProductsStmt->execute();
    $favoriteResult = $favoriteProductsStmt->get_result();
    while ($row = $favoriteResult->fetch_assoc()) {
        $favoritedProducts[] = $row['product_id'];
    }
} catch (Exception $e) {
    error_log("Error getting favorited products: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FarmFresh Dashboard</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/user.css">
    <script src="js/search.js" defer></script>
    <style>
        /* Logout Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            backdrop-filter: blur(5px);
        }

        .logout-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            text-align: center;
            max-width: 400px;
            width: 90%;
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translate(-50%, -48%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        .logout-modal h2 {
            margin-bottom: 1rem;
            color: #333;
            font-size: 1.5rem;
        }

        .logout-modal p {
            margin-bottom: 2rem;
            color: #666;
            font-size: 1rem;
            line-height: 1.5;
        }

        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        .modal-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            min-width: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .cancel-btn {
            background: #e0e0e0;
            color: #333;
        }

        .confirm-btn {
            background: #ff4444;
            color: white;
        }

        .cancel-btn:hover:not(:disabled) {
            background: #d0d0d0;
        }

        .confirm-btn:hover:not(:disabled) {
            background: #ff2020;
        }

        .loading-spinner {
            margin-left: 8px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .fa-spinner {
            animation: spin 1s linear infinite;
        }

        /* Add this to your existing styles */
        .menu-items .logout-link i {
            margin-right: 10px;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            transition: all 0.3s ease;
            z-index: 999;
            padding-top: 15px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            overflow-y: auto;
        }

        .sidebar.collapsed {
            left: -280px;
        }

        .toggle-container {
            position: fixed;
            left: 280px;
            top: 20px;
            z-index: 1002;
            transition: all 0.3s ease;
        }

        .sidebar.collapsed .toggle-container {
            left: 0;
        }

        .toggle-btn {
            background: var(--primary-color, #3b7a57);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 0 8px 8px 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .toggle-btn:hover {
            background: var(--secondary-color, #2c5a3f);
            width: 45px;
        }

        .toggle-btn i {
            font-size: 20px;
            transition: transform 0.3s ease;
        }

        /* User Info Styles */
        .user-info {
            text-align: center;
            padding: 1.5rem 1rem;
            border-bottom: 1px solid #eee;
            margin-top: 20px;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: 0.75rem;
            object-fit: cover;
            border: 3px solid var(--primary-color, #3b7a57);
            padding: 2px;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .user-info h3 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
            color: var(--text-color, #333);
        }

        .user-info p {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 1rem;
        }

        .user-stats {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-top: 0.75rem;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .stat {
            text-align: center;
        }

        .stat-number {
            font-weight: 600;
            color: var(--primary-color, #3b7a57);
            font-size: 1.1rem;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #666;
            margin-top: 0.25rem;
        }

        /* Menu Items Styles */
        .menu-items {
            list-style: none;
            padding: 0.75rem;
        }

        .menu-items li {
            margin-bottom: 0.25rem;
        }

        .menu-items a {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.6rem 0.75rem;
            color: var(--text-color, #333);
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .menu-items a:hover {
            background-color: rgba(59, 122, 87, 0.1);
            color: var(--primary-color, #3b7a57);
        }

        .menu-items i {
            width: 16px;
            text-align: center;
            font-size: 0.95rem;
        }

        .menu-items a.active {
            background-color: rgba(59, 122, 87, 0.1);
            color: var(--primary-color, #3b7a57);
            font-weight: 500;
        }

        .menu-items .logout-link {
            color: #ff4444;
            margin-top: 0.5rem;
        }

        .menu-items .logout-link:hover {
            background-color: rgba(255, 68, 68, 0.1);
        }

        /* Adjust main content margin */
        .main-content {
            margin-left: 280px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        /* Responsive sidebar */
        @media (max-width: 768px) {
            .sidebar {
                left: -250px;
            }
            .sidebar.active,
            .sidebar.collapsed {
                left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Glass effect header -->
    <header class="header">
        <div class="logo">
            <i class="fa-solid fa-leaf"></i>
            FarmFresh
        </div>
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Search products...">
            <div class="search-suggestions" id="searchSuggestions"></div>
        </div>
        <nav class="nav-menu">
            <a href="user.php"><i class="fa-solid fa-house"></i> Home</a>
            <a href="category/php/fruits.php"><i class="fa-solid fa-apple-whole"></i> Fruits</a>
            <a href="category/php/vegetables.php"><i class="fa-solid fa-carrot"></i> Vegetables</a>
            <a href="category/php/dairy.php"><i class="fa-solid fa-cheese"></i> Dairy</a>
            <a href="category/php/meat.php"><i class="fa-solid fa-drumstick-bite"></i> Meat</a>
            <a href="category/php/organic.php"><i class="fa-solid fa-seedling"></i> Organic</a>
            <a href="category/php/bakery.php"><i class="fa-solid fa-bread-slice"></i> Bakery</a>
            <a href="category/php/cart.php"><i class="fa-solid fa-cart-shopping"></i></a>
        </nav>
    </header>

    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="user-info">
                <img src="<?php echo !empty($user['profile_image']) ? '../' . $user['profile_image'] : '../assets/images/default-avatar.jpg'; ?>" 
                     alt="User Avatar" class="user-avatar">
                <h3><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h3>
                <p><?php echo $user['email']; ?></p>
                <div class="user-stats">
                    <div class="stat">
                        <div class="stat-number"><?php echo $orderCount; ?></div>
                        <div class="stat-label">Orders</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number"><?php echo $favoriteCount; ?></div>
                        <div class="stat-label">Favorites</div>
                    </div>
                </div>
            </div>
            <ul class="menu-items">
                <li><a href="orders.php"><i class="fa-solid fa-bag-shopping"></i> My Orders</a></li>
                <li><a href="profile.php"><i class="fa-solid fa-user"></i> Profile</a></li>
                <li><a href="payment.php"><i class="fa-solid fa-credit-card"></i> Payment Methods</a></li>
                <li><a href="address.php"><i class="fa-solid fa-location-dot"></i> Delivery Address</a></li>
                <li><a href="favorite.php"><i class="fa-solid fa-heart"></i> Favorites</a></li>
                <li><a href="settings.php"><i class="fa-solid fa-gear"></i> Account Settings</a></li>
                <li><a href="contact.php"><i class="fa-solid fa-phone"></i> Contact Us</a></li>
                <li><a href="#" onclick="showLogoutModal(); return false;" class="logout-link">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="section-header">
                <button class="toggle-btn" id="sidebarToggle">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h2 class="section-title">All Products</h2>
            </div>
            <div class="products-grid">
                <?php
                $products_per_page = 12;
                $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $offset = ($current_page - 1) * $products_per_page;
                $total_query = "SELECT COUNT(*) as total FROM products";
                $total_result = $conn->query($total_query);
                $total_products = $total_result->fetch_assoc()['total'];
                $total_pages = ceil($total_products / $products_per_page);
                $query = "SELECT * FROM products ORDER BY RAND() LIMIT ? OFFSET ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ii", $products_per_page, $offset);
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
                        $unit = $product['unit'];
                        $plural_units = ['pcs', 'kg', 'g', 'l', 'ml', 'boxes', 'packs'];
                        $display_unit = in_array(strtolower($unit), $plural_units) ? $unit : $unit . 's';
                        ?>
                        <div class="product-card <?php echo $statusClass; ?>" 
                             data-product-id="<?php echo htmlspecialchars($product['id']); ?>" 
                             data-stock="<?php echo htmlspecialchars($product['stock']); ?>" 
                             data-unit="<?php echo htmlspecialchars($product['unit']); ?>">
                            <span class="stock-badge"><?php echo $badgeText; ?></span>
                            <img src="../<?php echo $product['image_path']; ?>" alt="<?php echo $product['name']; ?>" class="product-image">
                            <div class="product-info">
                                <span class="product-category"><?php echo $product['category']; ?></span>
                                <h3 class="product-title"><?php echo $product['name']; ?></h3>
                                <p class="product-price">
                                    ₱<?php echo number_format($product['price'], 2); ?>
                                    <span class="product-unit">/ <?php echo $product['unit']; ?></span>
                                </p>
                                <p class="product-description"><?php echo $product['description']; ?></p>
                                <p class="stock-status">
                                    <span class="stock-indicator"></span>
                                    <?php 
                                    if ($product['stock'] > 0) {
                                        echo '<span class="product-availability" style="color:#1e824c;font-weight:700;">' . $product['stock'] . 
                                            ' <span class="unit" style="color:#ff5252;">' . htmlspecialchars($display_unit) . '</span> available</span>';
                                    } else {
                                        echo '<span class="product-availability" style="color:#ff4444;font-weight:700;">Currently unavailable</span>';
                                    }
                                    ?>
                                </p>
                                <div class="product-actions">
                                    <button class="cart-btn" 
                                        data-product-id="<?php echo $product['id']; ?>" 
                                        <?php echo ($product['stock'] <= 0) ? 'disabled' : ''; ?>>
                                        <i class="fa-solid fa-cart-plus"></i>
                                        <?php echo ($product['stock'] > 0) ? 'Add to Cart' : 'Out of Stock'; ?>
                                    </button>
                                    <button class="favorite-btn <?php echo in_array($product['id'], $favoritedProducts) ? 'active' : ''; ?>" 
                                        data-product-id="<?php echo $product['id']; ?>"
                                        title="<?php echo in_array($product['id'], $favoritedProducts) ? 'Remove from Favorites' : 'Add to Favorites'; ?>">
                                        <i class="fa-solid fa-heart"></i>
                                    </button>
                                    <button 
                                        class="buy-btn" 
                                        data-product-id="<?php echo $product['id']; ?>" 
                                        data-stock="<?php echo $product['stock']; ?>" 
                                        data-name="<?php echo htmlspecialchars($product['name']); ?>"
                                        data-price="<?php echo htmlspecialchars($product['price']); ?>"
                                        data-image="../<?php echo htmlspecialchars($product['image_path']); ?>"
                                        data-unit="<?php echo htmlspecialchars($product['unit']); ?>"
                                        <?php echo ($product['stock'] <= 0) ? 'disabled' : ''; ?>
                                    >
                                        <i class="fa-solid fa-bolt"></i> Buy Now
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                    <!-- Pagination controls here if needed -->
                    <?php
                } else {
                    echo '<p class="no-products">No products available at this time.</p>';
                }
                ?>
            </div>
        </main>
    </div>

    <!-- Logout Modal -->
    <div class="modal-overlay" id="logoutModal">
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

    <!-- Buy Now Modal -->
    <div class="buy-modal-overlay" id="buyModal" style="display:none;">
        <div class="buy-modal">
            <div class="buy-modal-header">
                <h2 class="buy-modal-title">Buy Now</h2>
                <button class="buy-modal-close">&times;</button>
            </div>
            <div class="buy-modal-content">
                <div class="product-preview">
                    <img src="" alt="" class="preview-image" id="previewImage">
                    <div class="preview-details">
                        <h3 class="preview-title" id="previewTitle"></h3>
                        <p class="preview-price" id="previewPrice"></p>
                    </div>
                </div>
                <div class="quantity-selector">
                    <button class="quantity-btn" id="decreaseQuantity">-</button>
                    <input type="number" class="quantity-input" id="quantityInput" value="1" min="1">
                    <button class="quantity-btn" id="increaseQuantity">+</button>
                </div>
                <p class="stock-info" id="stockInfo"></p>
            </div>
            <div class="buy-modal-actions">
                <button class="modal-btn cancel-buy-btn">Cancel</button>
                <button class="modal-btn confirm-buy-btn">Confirm Purchase</button>
            </div>
        </div>
    </div>

    <!-- Add Cart Modal -->
    <div class="cart-modal-overlay" id="cartModal" style="display:none;">
        <div class="cart-modal">
            <div class="cart-modal-header">
                <h2 class="cart-modal-title">Add to Cart</h2>
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
                <p class="stock-info" id="cartStockInfo"></p>
            </div>
            <div class="cart-modal-actions">
                <button class="modal-btn cancel-cart-btn">Cancel</button>
                <button class="modal-btn confirm-cart-btn">Add to Cart</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar toggle functionality
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        const toggleIcon = sidebarToggle.querySelector('i');

        function toggleSidebar() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
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

        // Check saved sidebar state on page load
        document.addEventListener('DOMContentLoaded', () => {
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

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            }
        });

        // Buy Now Modal
        const buyModal = document.getElementById('buyModal');
        const buyModalClose = buyModal.querySelector('.buy-modal-close');
        const cancelBuyBtn = buyModal.querySelector('.cancel-buy-btn');
        const confirmBuyBtn = buyModal.querySelector('.confirm-buy-btn');
        const quantityInput = document.getElementById('quantityInput');
        const decreaseQuantityBtn = document.getElementById('decreaseQuantity');
        const increaseQuantityBtn = document.getElementById('increaseQuantity');
        let currentBuyProductId = null;

        function showBuyModal(productData) {
            buyModal.style.display = 'block';
            setTimeout(() => {
                buyModal.querySelector('.buy-modal').classList.add('active');
            }, 10);
            document.getElementById('previewImage').src = productData.image;
            document.getElementById('previewTitle').textContent = productData.name;
            document.getElementById('previewPrice').textContent = `₱${parseFloat(productData.price).toFixed(2)}`;
            document.getElementById('stockInfo').textContent = `Available: ${productData.stock} ${productData.unit}${productData.stock > 1 ? 's' : ''}`;
            quantityInput.value = 1;
            quantityInput.max = productData.stock;
            currentBuyProductId = productData.id;
        }

        function hideBuyModal() {
            buyModal.querySelector('.buy-modal').classList.remove('active');
            setTimeout(() => {
                buyModal.style.display = 'none';
            }, 300);
        }

        decreaseQuantityBtn.addEventListener('click', () => {
            let value = parseInt(quantityInput.value);
            if (value > 1) quantityInput.value = value - 1;
        });
        increaseQuantityBtn.addEventListener('click', () => {
            let value = parseInt(quantityInput.value);
            let max = parseInt(quantityInput.max);
            if (value < max) quantityInput.value = value + 1;
        });
        quantityInput.addEventListener('change', () => {
            let value = parseInt(quantityInput.value);
            let max = parseInt(quantityInput.max);
            if (isNaN(value) || value < 1) value = 1;
            if (value > max) value = max;
            quantityInput.value = value;
        });
        buyModalClose.addEventListener('click', hideBuyModal);
        cancelBuyBtn.addEventListener('click', hideBuyModal);
        buyModal.addEventListener('click', (e) => {
            if (e.target === buyModal) hideBuyModal();
        });
        confirmBuyBtn.addEventListener('click', function() {
            const quantity = quantityInput.value;
            window.location.href = `checkout.php?product_id=${currentBuyProductId}&quantity=${quantity}&buy_now=true`;
        });

        // Add to Cart Modal
        const cartModal = document.getElementById('cartModal');
        const cancelCartBtn = cartModal.querySelector('.cancel-cart-btn');
        const confirmCartBtn = cartModal.querySelector('.confirm-cart-btn');
        const cartQuantityInput = document.getElementById('cartQuantityInput');
        const cartDecreaseQuantityBtn = document.getElementById('cartDecreaseQuantity');
        const cartIncreaseQuantityBtn = document.getElementById('cartIncreaseQuantity');
        let currentCartProductId = null;

        function showCartModal(productData) {
            cartModal.style.display = 'block';
            setTimeout(() => {
                cartModal.querySelector('.cart-modal').classList.add('active');
            }, 10);
            document.getElementById('cartPreviewImage').src = productData.image;
            document.getElementById('cartPreviewTitle').textContent = productData.name;
            document.getElementById('cartPreviewPrice').textContent = `₱${parseFloat(productData.price).toFixed(2)}`;
            document.getElementById('cartStockInfo').textContent = `Available: ${productData.stock} ${productData.unit}${productData.stock > 1 ? 's' : ''}`;
            cartQuantityInput.value = 1;
            cartQuantityInput.max = productData.stock;
            currentCartProductId = productData.id;
        }

        function hideCartModal() {
            cartModal.querySelector('.cart-modal').classList.remove('active');
            setTimeout(() => {
                cartModal.style.display = 'none';
            }, 300);
        }

        cartDecreaseQuantityBtn.addEventListener('click', () => {
            let value = parseInt(cartQuantityInput.value);
            if (value > 1) cartQuantityInput.value = value - 1;
        });
        cartIncreaseQuantityBtn.addEventListener('click', () => {
            let value = parseInt(cartQuantityInput.value);
            let max = parseInt(cartQuantityInput.max);
            if (value < max) cartQuantityInput.value = value + 1;
        });
        cartQuantityInput.addEventListener('change', () => {
            let value = parseInt(cartQuantityInput.value);
            let max = parseInt(cartQuantityInput.max);
            if (isNaN(value) || value < 1) value = 1;
            if (value > max) value = max;
            cartQuantityInput.value = value;
        });
        cancelCartBtn.addEventListener('click', hideCartModal);
        cartModal.addEventListener('click', (e) => {
            if (e.target === cartModal) hideCartModal();
        });
        confirmCartBtn.addEventListener('click', function() {
            const quantity = parseInt(cartQuantityInput.value);
            
            // Send AJAX request to add to cart
            fetch('category/php/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    product_id: currentCartProductId,
                    quantity: quantity
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Response from server:', data);
                if (data.status === true || data.success === true) {
                    showNotification(data.message || 'Added to cart!', 'success');
                } else {
                    showNotification(data.message || 'Failed to add to cart: ' + data.message, 'error');
                }
                hideCartModal();
            })
            .catch(error => {
                console.error('Error adding to cart:', error);
                showNotification('An error occurred. Please try again. Check console for details.', 'error');
                hideCartModal();
            });
        });

        // Product action buttons
        document.querySelectorAll('.buy-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const productCard = this.closest('.product-card');
                const productData = {
                    id: productCard.dataset.productId,
                    name: productCard.querySelector('.product-title').textContent,
                    price: productCard.querySelector('.product-price').textContent.replace(/[^\d.]/g, ''),
                    image: productCard.querySelector('.product-image').src,
                    stock: parseInt(productCard.dataset.stock),
                    unit: productCard.dataset.unit
                };
                showBuyModal(productData);
            });
        });
        document.querySelectorAll('.cart-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const productCard = this.closest('.product-card');
                const productData = {
                    id: productCard.dataset.productId,
                    name: productCard.querySelector('.product-title').textContent,
                    price: productCard.querySelector('.product-price').textContent.replace(/[^\d.]/g, ''),
                    image: productCard.querySelector('.product-image').src,
                    stock: parseInt(productCard.dataset.stock),
                    unit: productCard.dataset.unit
                };
                showCartModal(productData);
            });
        });
        document.querySelectorAll('.favorite-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const productCard = this.closest('.product-card');
                const productId = productCard.dataset.productId;
                fetch('../toggle_favorite.php', {
                    method: 'POST',
                    body: JSON.stringify({ product_id: productId }),
                    headers: { 'Content-Type': 'application/json' }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        this.classList.toggle('active', data.is_favorite);
                        const favoriteCountElement = document.querySelector('.user-stats .stat:nth-child(2) .stat-number');
                        if (favoriteCountElement) favoriteCountElement.textContent = data.favorite_count;
                        showNotification(data.message, 'success');
                    } else {
                        showNotification(data.message || 'Error updating favorites', 'error');
                    }
                })
                .catch(() => showNotification('Error updating favorites', 'error'));
            });
        });

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
            container.appendChild(notification); // stack upward
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(40px) scale(0.95)';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Logout modal
        window.showLogoutModal = function() {
            const modal = document.getElementById('logoutModal');
            if (modal) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
                const logoutBtn = modal.querySelector('.confirm-btn');
                if (logoutBtn) {
                    logoutBtn.disabled = false;
                    logoutBtn.querySelector('.btn-text').style.display = 'inline';
                    logoutBtn.querySelector('.loading-spinner').style.display = 'none';
                }
            }
        }
        window.hideLogoutModal = function() {
            const modal = document.getElementById('logoutModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        window.confirmLogout = function(button) {
            if (!button) return;
            button.disabled = true;
            button.querySelector('.btn-text').style.display = 'none';
            button.querySelector('.loading-spinner').style.display = 'inline';
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
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
                    throw new Error(data.message || 'Error during logout');
                }
            })
            .catch(() => {
                showNotification('Error logging out. Please try again.', 'error');
                button.disabled = false;
                button.querySelector('.btn-text').style.display = 'inline';
                button.querySelector('.loading-spinner').style.display = 'none';
            });
        }
        document.getElementById('logoutModal').addEventListener('click', function(e) {
            if (e.target === this) window.hideLogoutModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') window.hideLogoutModal();
        });
    });
    </script>
</body>
</html>