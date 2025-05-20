<?php
session_start();

// Check if user is logged in and is an admin
if(!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'admin' && !isset($_SESSION['is_admin']))) {
    // Not logged in or not an admin, return error
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit;
}

// Include database connection
require_once '../connect.php';

// Check if order_id is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    echo '<div class="alert alert-danger">Order ID is required</div>';
    exit;
}

$orderId = (int)$_GET['order_id'];

// Get order details
$stmt = $conn->prepare("SELECT o.*, CONCAT(u.first_name, ' ', u.last_name) as customer_name, u.email, u.phone_number 
                      FROM orders o 
                      LEFT JOIN users u ON o.user_id = u.user_id 
                      WHERE o.order_id = ?");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$orderResult = $stmt->get_result();

if ($orderResult->num_rows === 0) {
    echo '<div class="alert alert-danger">Order not found</div>';
    exit;
}

$order = $orderResult->fetch_assoc();
$stmt->close();

// Get order items
$stmt = $conn->prepare("SELECT oi.*, p.name as product_name, p.image_path 
                      FROM order_items oi 
                      JOIN products p ON oi.product_id = p.id 
                      WHERE oi.order_id = ?");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$itemsResult = $stmt->get_result();
$orderItems = [];

while ($item = $itemsResult->fetch_assoc()) {
    $orderItems[] = $item;
}

$stmt->close();

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'delivered':
            return 'bg-success';
        case 'pending':
            return 'bg-warning';
        case 'cancelled':
            return 'bg-danger';
        case 'shipped':
            return 'bg-info';
        case 'processing':
            return 'bg-primary';
        default:
            return 'bg-secondary';
    }
}
?>

<div class="order-details">
    <div class="row mb-4">
        <div class="col-md-6">
            <h6 class="text-muted">Order Information</h6>
            <ul class="list-group">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Order ID
                    <span class="fw-bold">#<?php echo $order['order_id']; ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Date
                    <span><?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Status
                    <span class="badge <?php echo getStatusBadgeClass($order['status']); ?>"><?php echo ucfirst($order['status']); ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Payment Method
                    <span><?php echo ucfirst($order['payment_method']); ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Total
                    <span class="fw-bold">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                </li>
            </ul>
        </div>
        <div class="col-md-6">
            <h6 class="text-muted">Customer Information</h6>
            <ul class="list-group">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Name
                    <span><?php echo htmlspecialchars($order['customer_name'] ?? 'Guest'); ?></span>
                </li>
                <?php if (isset($order['email']) && !empty($order['email'])): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Email
                    <span><?php echo htmlspecialchars($order['email']); ?></span>
                </li>
                <?php endif; ?>
                <?php if (isset($order['phone_number']) && !empty($order['phone_number'])): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Phone
                    <span><?php echo htmlspecialchars($order['phone_number']); ?></span>
                </li>
                <?php endif; ?>
                <li class="list-group-item">
                    <div class="fw-bold mb-1">Shipping Address</div>
                    <div class="small"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></div>
                </li>
            </ul>
        </div>
    </div>
    
    <h6 class="text-muted mb-3">Order Items</h6>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Item</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orderItems as $item): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <?php if ($item['image_path']): ?>
                            <img src="../<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" width="50" height="50" class="me-3" style="object-fit: cover; border-radius: 4px;">
                            <?php else: ?>
                            <div class="bg-light me-3" style="width: 50px; height: 50px; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-image text-muted"></i>
                            </div>
                            <?php endif; ?>
                            <div>
                                <?php echo htmlspecialchars($item['product_name']); ?>
                            </div>
                        </div>
                    </td>
                    <td>₱<?php echo number_format($item['price'], 2); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td class="text-end">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-end fw-bold">Total</td>
                    <td class="text-end fw-bold">₱<?php echo number_format($order['total_amount'], 2); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div> 