<?php
session_start();
require_once '../connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">User not logged in</div>';
    exit();
}

// Check if order ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="alert alert-danger">Order ID is required</div>';
    exit();
}

$order_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Verify that the order belongs to the user
$stmt = $conn->prepare("SELECT o.*, DATE_FORMAT(o.order_date, '%M %d, %Y %h:%i %p') as formatted_date FROM orders o WHERE o.order_id = ? AND o.user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-danger">Order not found or does not belong to this user</div>';
    exit();
}

$order = $result->fetch_assoc();

// Get order items
$item_stmt = $conn->prepare("
    SELECT oi.quantity, oi.price as item_price, p.name, p.image_path, p.unit 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$item_stmt->bind_param("i", $order_id);
$item_stmt->execute();
$item_result = $item_stmt->get_result();
$items = [];
while ($item = $item_result->fetch_assoc()) {
    $items[] = $item;
}

// Get delivery information if available
$delivery_info = null;
$delivery_stmt = $conn->prepare("SELECT * FROM deliveries WHERE order_id = ?");
$delivery_stmt->bind_param("i", $order_id);
$delivery_stmt->execute();
$delivery_result = $delivery_stmt->get_result();
if ($delivery_result->num_rows > 0) {
    $delivery_info = $delivery_result->fetch_assoc();
}
?>

<div class="order-detail-container">
    <!-- Order Header -->
    <div class="order-detail-header">
        <div class="order-basic-info">
            <h3>Order #<?php echo htmlspecialchars($order['order_id']); ?></h3>
            <p class="order-date"><?php echo htmlspecialchars($order['formatted_date']); ?></p>
            <div class="order-status">
                <span class="status-badge status-<?php echo strtolower(htmlspecialchars($order['status'])); ?>">
                    <?php echo strtoupper(htmlspecialchars($order['status'])); ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Order Items -->
    <div class="order-items-section">
        <h4>Items Ordered</h4>
        <div class="order-item-list">
            <?php foreach ($items as $item): ?>
                <div class="order-detail-item">
                    <div class="item-image">
                        <img src="<?php echo '../' . htmlspecialchars($item['image_path'] ?? 'assets/images/default-product.png'); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>">
                    </div>
                    <div class="item-details">
                        <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <div class="item-price">₱<?php echo number_format($item['item_price'], 2); ?>/<?php echo htmlspecialchars($item['unit']); ?></div>
                        <div class="item-quantity">Quantity: <?php echo (int)$item['quantity']; ?></div>
                        <div class="item-subtotal">Subtotal: ₱<?php echo number_format($item['item_price'] * $item['quantity'], 2); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Order Summary -->
    <div class="order-summary-section">
        <h4>Order Summary</h4>
        <div class="summary-table">
            <div class="summary-row">
                <div class="summary-label">Subtotal</div>
                <div class="summary-value">₱<?php echo number_format($order['total_amount'], 2); ?></div>
            </div>
            <div class="summary-row">
                <div class="summary-label">Shipping Fee</div>
                <div class="summary-value">₱0.00</div>
            </div>
            <div class="summary-row total">
                <div class="summary-label">Total</div>
                <div class="summary-value">₱<?php echo number_format($order['total_amount'], 2); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Shipping Information -->
    <div class="shipping-info-section">
        <h4>Shipping Information</h4>
        <div class="shipping-address">
            <p><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
        </div>
        
        <?php if ($delivery_info): ?>
        <div class="delivery-details">
            <h5>Delivery Details</h5>
            <p><strong>Status:</strong> <?php echo ucfirst(htmlspecialchars($delivery_info['status'])); ?></p>
            <?php if ($delivery_info['driver_name']): ?>
                <p><strong>Driver:</strong> <?php echo htmlspecialchars($delivery_info['driver_name']); ?></p>
            <?php endif; ?>
            <?php if ($delivery_info['tracking_number']): ?>
                <p><strong>Tracking Number:</strong> <?php echo htmlspecialchars($delivery_info['tracking_number']); ?></p>
            <?php endif; ?>
            <?php if ($delivery_info['delivery_date']): ?>
                <p><strong>Estimated Delivery:</strong> <?php echo date('F j, Y', strtotime($delivery_info['delivery_date'])); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Payment Information -->
    <div class="payment-info-section">
        <h4>Payment Information</h4>
        <div class="payment-method">
            <p><strong>Payment Method:</strong> <?php echo htmlspecialchars(ucfirst($order['payment_method'])); ?></p>
        </div>
    </div>
</div>

<style>
    .order-detail-container {
        padding: 0 10px;
    }
    
    .order-detail-header {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }
    
    .order-basic-info h3 {
        margin: 0 0 5px 0;
        font-size: 1.4rem;
        color: #333;
    }
    
    .order-date {
        color: #666;
        margin: 0 0 10px 0;
        font-size: 0.9rem;
    }
    
    .order-status {
        margin-top: 8px;
    }
    
    .status-badge {
        padding: 5px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        display: inline-block;
    }
    
    .order-items-section, 
    .order-summary-section, 
    .shipping-info-section,
    .payment-info-section {
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid #eee;
    }
    
    .order-items-section h4, 
    .order-summary-section h4, 
    .shipping-info-section h4,
    .payment-info-section h4 {
        font-size: 1.1rem;
        margin: 0 0 15px 0;
        color: #333;
    }
    
    .order-detail-item {
        display: flex;
        margin-bottom: 15px;
        background: #f9f9f9;
        border-radius: 8px;
        padding: 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .item-image {
        width: 70px;
        flex-shrink: 0;
        margin-right: 15px;
    }
    
    .item-image img {
        width: 100%;
        height: 70px;
        object-fit: cover;
        border-radius: 6px;
    }
    
    .item-details {
        flex-grow: 1;
    }
    
    .item-name {
        font-weight: 600;
        margin-bottom: 5px;
        color: #333;
    }
    
    .item-price, .item-quantity {
        font-size: 0.9rem;
        color: #666;
        margin-bottom: 3px;
    }
    
    .item-subtotal {
        font-weight: 600;
        color: var(--primary-color, #3b7a57);
        margin-top: 8px;
    }
    
    .summary-table {
        background: #f9f9f9;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #eee;
    }
    
    .summary-row.total {
        padding-top: 12px;
        margin-top: 5px;
        border-top: 2px solid #ddd;
        border-bottom: none;
        font-weight: 700;
        font-size: 1.1rem;
        color: var(--primary-color, #3b7a57);
    }
    
    .shipping-address, .payment-method {
        background: #f9f9f9;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .shipping-address p, .payment-method p {
        margin: 0;
        line-height: 1.5;
    }
    
    .delivery-details {
        margin-top: 15px;
        background: #f9f9f9;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .delivery-details h5 {
        margin: 0 0 10px 0;
        font-size: 1rem;
    }
    
    .delivery-details p {
        margin: 5px 0;
        font-size: 0.9rem;
    }
</style> 