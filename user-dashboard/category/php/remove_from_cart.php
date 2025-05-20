<?php
require_once '../../../connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header('Location: ../../../login.php');
    exit;
}

// Check if cart_id is provided
if (!isset($_GET['cart_id']) || !is_numeric($_GET['cart_id'])) {
    // Redirect back to cart with error
    header('Location: cart.php?error=invalid_item');
    exit;
}

$cart_id = (int)$_GET['cart_id'];
$user_id = $_SESSION['user_id'];

// Verify the cart item belongs to the current user
$sql = "SELECT cart_id FROM cart WHERE cart_id = ? AND user_id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ii", $cart_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Item doesn't exist or doesn't belong to this user
        $stmt->close();
        header('Location: cart.php?error=unauthorized');
        exit;
    }
    $stmt->close();
} else {
    // SQL error
    header('Location: cart.php?error=database');
    exit;
}

// Delete the cart item
$delete_sql = "DELETE FROM cart WHERE cart_id = ? AND user_id = ?";
if ($delete_stmt = $conn->prepare($delete_sql)) {
    $delete_stmt->bind_param("ii", $cart_id, $user_id);
    $success = $delete_stmt->execute();
    $delete_stmt->close();
    
    if ($success) {
        // Success - redirect back to cart
        header('Location: cart.php?success=removed');
    } else {
        // Failed to delete
        header('Location: cart.php?error=delete_failed');
    }
} else {
    // SQL error
    header('Location: cart.php?error=database');
}

$conn->close();
exit;
?> 