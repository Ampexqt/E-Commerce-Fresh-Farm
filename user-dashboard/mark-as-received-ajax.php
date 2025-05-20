<?php
session_start();
require_once '../connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get and validate order ID
$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

// Verify the order belongs to the logged-in user
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found or not authorized']);
    exit();
}

$order = $result->fetch_assoc();

// Check if the order is in "shipped" status
if (strtolower($order['status']) !== 'shipped') {
    echo json_encode(['success' => false, 'message' => 'Order cannot be marked as delivered']);
    exit();
}

// Update the order status to "delivered"
$new_status = 'delivered';
$update_stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
$update_stmt->bind_param("si", $new_status, $order_id);

if ($update_stmt->execute()) {
    // Success - log the status change
    $log_stmt = $conn->prepare("INSERT INTO order_status_history (order_id, status, notes, created_at) VALUES (?, ?, ?, NOW())");
    $notes = "Order marked as delivered by customer";
    
    if ($log_stmt) {
        $log_stmt->bind_param("iss", $order_id, $new_status, $notes);
        $log_stmt->execute();
    }
    
    echo json_encode(['success' => true, 'message' => 'Order marked as delivered']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update order status']);
}

$conn->close(); 