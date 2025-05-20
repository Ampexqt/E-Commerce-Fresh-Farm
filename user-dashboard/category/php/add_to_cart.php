<?php

require_once '../../../connect.php';

// Add content type header right away
header('Content-Type: application/json');

// Start error logging
error_log("Add to cart request received");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error', 
        'success' => false,
        'message' => 'You must be logged in to add items to the cart.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST request received");
    
    // Verify user_id is set
    if (!isset($_SESSION['user_id'])) {
        error_log("Session user_id not found");
        echo json_encode([
            'status' => 'error', 
            'success' => false,
            'message' => 'User ID not found in session. Please log in again.'
        ]);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    error_log("Processing request for user ID: " . $user_id);
    
    // Get and validate input data
    $input = file_get_contents('php://input');
    error_log("Raw input: " . $input);
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        echo json_encode([
            'status' => 'error', 
            'success' => false,
            'message' => 'Invalid JSON data: ' . json_last_error_msg()
        ]);
        exit;
    }

    if (!isset($data['product_id']) || !isset($data['quantity'])) {
        echo json_encode([
            'status' => 'error', 
            'success' => false,
            'message' => 'Invalid request.'
        ]);
        exit;
    }

    $product_id = intval($data['product_id']);
    $quantity = intval($data['quantity']);

    if ($quantity <= 0) {
        echo json_encode([
            'status' => 'error', 
            'success' => false,
            'message' => 'Quantity must be greater than 0.'
        ]);
        exit;
    }

    // Check if the product exists and has enough stock
    error_log("Checking product ID: " . $product_id);
    try {
        $stmt = $conn->prepare("SELECT stock, name FROM products WHERE id = ?");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $stmt->bind_param("i", $product_id);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            throw new Exception("Database execute error: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        
        if ($product) {
            error_log("Product found: " . $product['name'] . ", Stock: " . $product['stock']);
        } else {
            error_log("Product not found");
            echo json_encode([
                'status' => 'error', 
                'success' => false,
                'message' => 'Product not found.'
            ]);
            exit;
        }
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
        exit;
    }

    if ($product['stock'] < $quantity) {
        error_log("Not enough stock. Requested: " . $quantity . ", Available: " . $product['stock']);
        echo json_encode([
            'status' => 'error', 
            'success' => false,
            'message' => 'Not enough stock available.'
        ]);
        exit;
    }

    // Check if the product is already in the cart
    $stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cart_item = $result->fetch_assoc();

    if ($cart_item) {
        // Update the quantity if the product is already in the cart
        $new_quantity = $cart_item['quantity'] + $quantity;
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("iii", $new_quantity, $user_id, $product_id);
    } else {
        // Insert the product into the cart
        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $user_id, $product_id, $quantity);
    }

    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success', 
            'success' => true,
            'message' => 'Product added to cart successfully.'
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'success' => false,
            'message' => 'Failed to add product to cart.'
        ]);
    }
    exit;
}

echo json_encode([
    'status' => 'error', 
    'success' => false,
    'message' => 'Invalid request method.'
]);

// Log connection status
if (!$conn) {
    error_log("Database connection failed");
}

?>