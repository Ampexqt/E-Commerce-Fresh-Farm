<?php
session_start();
require_once '../connect.php';
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Check if order ID is provided
if (!isset($_POST['order_id']) || empty($_POST['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit();
}

$order_id = intval($_POST['order_id']);
$user_id = $_SESSION['user_id'];

// Verify that the order belongs to the user
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found or does not belong to this user']);
    exit();
}

$order = $result->fetch_assoc();

// Check if the order is in a status that can be cancelled (typically only "pending" orders)
if (strtolower($order['status']) !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'Only pending orders can be cancelled']);
    exit();
}

// Update order status to cancelled
try {
    $conn->begin_transaction();
    
    $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    
    // Add any additional logic here (e.g., restore product stock, log cancellation)
    
    // Get order items to restore stock
    $items_stmt = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    $items_stmt->bind_param("i", $order_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    // Restore stock for each item
    while ($item = $items_result->fetch_assoc()) {
        $product_id = $item['product_id'];
        $quantity = $item['quantity'];
        
        $update_stock = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        $update_stock->bind_param("ii", $quantity, $product_id);
        $update_stock->execute();
    }
    
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed to cancel order: ' . $e->getMessage()]);
} 