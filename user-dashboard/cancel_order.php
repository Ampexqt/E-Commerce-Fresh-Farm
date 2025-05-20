<?php
// Start session and include database connection
session_start();
require_once '../connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

// Get data from POST request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['order_id']) || empty($data['order_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Order ID is required']);
    exit();
}

$orderId = intval($data['order_id']);
$userId = $_SESSION['user_id'];

// First verify that the order belongs to the user and is in a cancellable state
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->bind_param("ii", $orderId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Order not found or does not belong to you']);
    exit();
}

$order = $result->fetch_assoc();

// Check if order is in a state that can be cancelled (e.g., "Pending")
if (strtolower($order['status']) !== 'pending') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'This order cannot be cancelled because it is already ' . $order['status']]);
    exit();
}

// Update order status to "Cancelled"
$updateStmt = $conn->prepare("UPDATE orders SET status = 'Cancelled' WHERE order_id = ?");
$updateStmt->bind_param("i", $orderId);

if ($updateStmt->execute()) {
    // Optional: Update product inventory by returning quantities back to stock
    try {
        // Get order items
        $itemStmt = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
        $itemStmt->bind_param("i", $orderId);
        $itemStmt->execute();
        $itemsResult = $itemStmt->get_result();
        
        // Return quantities back to inventory
        while ($item = $itemsResult->fetch_assoc()) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'];
            
            $stockStmt = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            $stockStmt->bind_param("ii", $quantity, $productId);
            $stockStmt->execute();
        }
    } catch (Exception $e) {
        error_log("Error updating inventory after order cancellation: " . $e->getMessage());
        // Continue with the cancellation even if inventory update fails
    }
    
    // Success response
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Order cancelled successfully']);
} else {
    // Error response
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Failed to cancel order: ' . $conn->error]);
} 