<?php
session_start();
header('Content-Type: application/json');
require_once '../../connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => false,
        'message' => 'Please login to add items to cart'
    ]);
    exit;
}

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);
$product_id = $data['product_id'] ?? null;
$quantity = $data['quantity'] ?? 1;
$user_id = $_SESSION['user_id'];

if (!$product_id) {
    echo json_encode([
        'status' => false,
        'message' => 'Product ID is required'
    ]);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Check if product exists and has enough stock
    $checkStmt = $conn->prepare("SELECT stock, name, price FROM products WHERE id = ?");
    $checkStmt->bind_param("i", $product_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $product = $result->fetch_assoc();

    if (!$product) {
        throw new Exception('Product not found');
    }

    if ($product['stock'] < $quantity) {
        throw new Exception('Not enough stock available');
    }

    // Check if cart table exists, if not create it
    $conn->query("CREATE TABLE IF NOT EXISTS cart (
        cart_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");

    // Check if product already exists in cart
    $cartStmt = $conn->prepare("SELECT cart_id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $cartStmt->bind_param("ii", $user_id, $product_id);
    $cartStmt->execute();
    $cartResult = $cartStmt->get_result();
    $cartItem = $cartResult->fetch_assoc();

    if ($cartItem) {
        // Update quantity if product already in cart
        $newQuantity = $cartItem['quantity'] + $quantity;
        if ($newQuantity > $product['stock']) {
            throw new Exception('Cannot add more of this item to cart');
        }

        $updateStmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ?");
        $updateStmt->bind_param("ii", $newQuantity, $cartItem['cart_id']);
        $updateStmt->execute();
        
        $message = "Updated quantity in cart";
    } else {
        // Add new item to cart
        $insertStmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $insertStmt->bind_param("iii", $user_id, $product_id, $quantity);
        $insertStmt->execute();
        
        $message = "Added to cart";
    }

    // Get updated cart count
    $countStmt = $conn->prepare("SELECT SUM(quantity) as cart_count FROM cart WHERE user_id = ?");
    $countStmt->bind_param("i", $user_id);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $cartCount = $countResult->fetch_assoc()['cart_count'] ?? 0;

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'status' => true, 
        'message' => $message,
        'cart_count' => $cartCount
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'status' => false,
        'message' => $e->getMessage()
    ]);
} 