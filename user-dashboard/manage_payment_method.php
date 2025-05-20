<?php
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json');
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Fatal server error: ' . $error['message']]);
        exit();
    }
});
// Debug: show all errors during development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Ensure clean output
ob_start();
session_start();
require_once '../connect.php';

// Set JSON headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Function to send JSON response
function sendJSON($data) {
    if (ob_get_length()) ob_clean();
    echo json_encode($data);
    exit();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    sendJSON(['success' => false, 'message' => 'User not logged in']);
}

$user_id = $_SESSION['user_id'];

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data received');
    }

    if (empty($data['action'])) {
        throw new Exception('Action is required');
    }

    // Transaction flag
    $transaction_started = false;
    
    // Start transaction
    $conn->begin_transaction();
    $transaction_started = true;

    switch ($data['action']) {
        case 'remove':
            if (empty($data['payment_id'])) {
                throw new Exception('Payment ID is required');
            }

            // First check if payment method exists and belongs to user
            $stmt = $conn->prepare("SELECT id, is_default, type FROM payment_methods WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $data['payment_id'], $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception('Payment method not found');
            }

            $payment = $result->fetch_assoc();

            // If trying to delete default payment method
            if ($payment['is_default']) {
                // Check if there are other payment methods
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM payment_methods WHERE user_id = ? AND id != ?");
                $stmt->bind_param("ii", $user_id, $data['payment_id']);
                $stmt->execute();
                $countResult = $stmt->get_result();
                $count = $countResult->fetch_assoc()['count'];

                if ($count > 0) {
                    throw new Exception('Cannot remove default payment method. Please set another payment method as default first.');
                }
            }

            // Check if this payment method is used in orders
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE payment_method_id = ?");
            $stmt->bind_param("i", $data['payment_id']);
            $stmt->execute();
            $orderResult = $stmt->get_result();
            $orderCount = $orderResult->fetch_assoc()['count'];

            if ($orderCount > 0) {
                throw new Exception('This payment method is linked to existing orders and cannot be deleted.');
            }

            // Delete the payment method
            $stmt = $conn->prepare("DELETE FROM payment_methods WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $data['payment_id'], $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to remove payment method: ' . $stmt->error);
            }

            $conn->commit();
            $transaction_started = false;
            sendJSON(['success' => true, 'message' => 'Payment method removed successfully']);
            break;

        case 'set_default':
            if (empty($data['payment_id'])) {
                throw new Exception('Payment ID is required');
            }

            // First remove default from all payment methods
            $stmt = $conn->prepare("UPDATE payment_methods SET is_default = 0 WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            // Set new default
            $stmt = $conn->prepare("UPDATE payment_methods SET is_default = 1 WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $data['payment_id'], $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to set default payment method');
            }

            $conn->commit();
            $transaction_started = false;
            sendJSON(['success' => true, 'message' => 'Default payment method updated']);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    if ($transaction_started) {
        $conn->rollback();
    }
    sendJSON(['success' => false, 'message' => $e->getMessage()]);
}
