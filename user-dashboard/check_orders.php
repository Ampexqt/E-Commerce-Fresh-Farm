<?php
// Start session and include database connection
session_start();
require_once '../connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit();
}

// Get the user ID
$user_id = $_SESSION['user_id'];

// Check if the user has any orders (especially pending or processing orders)
$stmt = $conn->prepare("
    SELECT COUNT(*) as order_count 
    FROM orders 
    WHERE user_id = ? AND status IN ('pending', 'processing')
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

// Return the result
$has_orders = ($data['order_count'] > 0);

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'has_orders' => $has_orders,
    'order_count' => $data['order_count']
]);
exit();
?> 