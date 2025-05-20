<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is an admin
if(!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'admin' && !isset($_SESSION['is_admin']))) {
    // Return error JSON
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Include database connection
try {
    require_once '../connect.php';
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection error: ' . $e->getMessage()]);
    exit;
}

// Get time range parameter
$timeRange = isset($_GET['timeRange']) ? $_GET['timeRange'] : '7days';

// Validate time range
if(!in_array($timeRange, ['7days', '30days', 'month'])) {
    $timeRange = '7days'; // Default to 7 days if invalid
}

// Function to get sales data for different time periods
function getSalesData($conn, $timeRange = '7days') {
    $salesData = [];
    $today = date('Y-m-d');
    
    switch ($timeRange) {
        case '30days':
            $days = 30;
            break;
        case 'month':
            // Current month
            $days = date('t'); // Number of days in current month
            $today = date('Y-m-') . date('t'); // Last day of current month
            $startDay = date('Y-m-01'); // First day of current month
            break;
        case '7days':
        default:
            $days = 7;
            break;
    }
    
    try {
        if ($timeRange == 'month') {
            // For current month, we want to show all days of the month
            $currentDay = 1;
            $daysInMonth = date('t');
            
            for ($i = 0; $i < $daysInMonth; $i++) {
                $date = date('Y-m-') . str_pad($currentDay, 2, '0', STR_PAD_LEFT);
                $query = "SELECT SUM(total_amount) as total FROM orders WHERE DATE(order_date) = '$date' AND LOWER(status) = 'delivered'";
                $result = $conn->query($query);
                
                if (!$result) {
                    throw new Exception("Query failed: " . $conn->error . " - SQL: $query");
                }
                
                $row = $result->fetch_assoc();
                $total = $row['total'] ? $row['total'] : 0;
                
                $salesData[] = [
                    'date' => $currentDay, // Just the day number for month view
                    'total' => $total
                ];
                
                $currentDay++;
            }
        } else {
            // For 7 or 30 days
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $query = "SELECT SUM(total_amount) as total FROM orders WHERE DATE(order_date) = '$date' AND LOWER(status) = 'delivered'";
                $result = $conn->query($query);
                
                if (!$result) {
                    throw new Exception("Query failed: " . $conn->error . " - SQL: $query");
                }
                
                $row = $result->fetch_assoc();
                $total = $row['total'] ? $row['total'] : 0;
                
                $salesData[] = [
                    'date' => $timeRange == '30days' ? date('M d', strtotime($date)) : date('D', strtotime($date)),
                    'total' => $total
                ];
            }
        }
        
        return $salesData;
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

try {
    // Get sales data for the requested time range
    $salesData = getSalesData($conn, $timeRange);

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'timeRange' => $timeRange,
        'data' => $salesData,
        'status' => 'success'
    ]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error getting sales data: ' . $e->getMessage()]);
}

// Close connection
$conn->close(); 