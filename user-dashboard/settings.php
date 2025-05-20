<?php
// Start session and include database connection
session_start();
require_once '../connect.php';

// Initialize message variables
$message = '';
$messageType = '';

// Handle logout action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action']) && $data['action'] === 'logout') {
        // Clear all session variables
        $_SESSION = array();
        
        // Destroy the session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        // Destroy the session
        session_destroy();
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Logged out successfully']);
        exit();
    }
    
    // Handle password change
    if (isset($_POST['form_type']) && $_POST['form_type'] === 'password_change') {
        // Get form data
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmNewPassword = $_POST['confirm_new_password'];
        
        // Get user's current password from database
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        
        // Verify current password
        if (!password_verify($currentPassword, $user_data['password'])) {
            $response = [
                'status' => 'error',
                'message' => 'Current password is incorrect'
            ];
            
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        
        // Check if new passwords match
        if ($newPassword !== $confirmNewPassword) {
            $response = [
                'status' => 'error',
                'message' => 'New passwords do not match'
            ];
            
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        
        // Validate password strength (at least 8 characters)
        if (strlen($newPassword) < 8) {
            $response = [
                'status' => 'error',
                'message' => 'New password must be at least 8 characters long'
            ];
            
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        
        // Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update the password in the database
        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $updateStmt->bind_param("si", $hashedPassword, $_SESSION['user_id']);
        
        if ($updateStmt->execute()) {
            $response = [
                'status' => 'success',
                'message' => 'Password updated successfully'
            ];
        } else {
            $response = [
                'status' => 'error',
                'message' => 'Failed to update password: ' . $conn->error
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Handle account deletion
    if (isset($data['action']) && $data['action'] === 'delete_account') {
        // Get user info for order checking
        $userID = $_SESSION['user_id'];
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update user's orders to set user_id to NULL instead of deleting them
            $updateOrdersStmt = $conn->prepare("UPDATE orders SET user_id = NULL WHERE user_id = ?");
            $updateOrdersStmt->bind_param("i", $userID);
            $updateOrdersStmt->execute();
            
            // Delete related data - cart items, favorites, payment methods, addresses
            // These can be fully deleted since they're user-specific
            $tables = ['cart', 'favorites', 'payment_methods', 'delivery_addresses'];
            
            foreach ($tables as $table) {
                $deleteStmt = $conn->prepare("DELETE FROM $table WHERE user_id = ?");
                $deleteStmt->bind_param("i", $userID);
                $deleteStmt->execute();
            }
            
            // Finally delete the user account
            $deleteUserStmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $deleteUserStmt->bind_param("i", $userID);
            $deleteUserStmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            // Clear all session variables
            $_SESSION = array();
            
            // Destroy the session cookie
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }
            
            // Destroy the session
            session_destroy();
            
            // Return JSON response
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Account deleted successfully']);
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction if any error occurs
            $conn->rollback();
            
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Error deleting account: ' . $e->getMessage()]);
            exit();
        }
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get user's order count
$orderCount = 0;
try {
    $orderStmt = $conn->prepare("SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?");
    $orderStmt->bind_param("i", $user_id);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    $orderCount = $orderResult->fetch_assoc()['order_count'];
} catch (Exception $e) {
    error_log("Error getting order count: " . $e->getMessage());
}

// Get user's favorites count
$favoriteCount = 0;
try {
    $favoriteStmt = $conn->prepare("SELECT COUNT(*) as favorite_count FROM favorites WHERE user_id = ?");
    $favoriteStmt->bind_param("i", $user_id);
    $favoriteStmt->execute();
    $favoriteResult = $favoriteStmt->get_result();
    $favoriteCount = $favoriteResult->fetch_assoc()['favorite_count'];
} catch (Exception $e) {
    error_log("Error getting favorite count: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - FarmFresh</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/user.css">
    <link rel="stylesheet" href="../css/settings.css">
    <style>
        /* Fix position issues */
        .back-button {
            position: fixed;
            left: calc(350px + 20px); /* Sidebar width (280px) + margin + gap */
            top: 20px;
            z-index: 1000;
        }
        
        .sidebar.collapsed + .main-content .back-button {
            left: 20px;
        }
        
        .settings-container {
            transition: margin-left 0.3s ease;
            margin-top: 3rem; /* Add space for the back button */
        }
        
        /* Fix any modal issues */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1100;
        }
        
        /* Page-specific overrides to fix layout issues */
        .main-content {
            margin-left: 280px !important;
            padding: 1rem 2rem !important;
            flex: 1 1 auto !important;
            max-width: calc(100% - 280px) !important;
            min-height: 100vh !important;
            box-sizing: border-box !important;
            overflow-x: visible !important;
            overflow-y: visible !important;
        }
        
        .main-content.expanded {
            margin-left: 0 !important;
            max-width: 100% !important;
        }
        
        .settings-container {
            width: 100% !important;
            max-width: 700px !important;
            margin: 20px auto !important;
            box-sizing: border-box !important;
        }
        
        .password-input-container {
            width: 100% !important;
            position: relative !important;
            display: block !important;
        }
        
        .password-input-container input {
            width: 100% !important;
            box-sizing: border-box !important;
        }
        
        .phone-input-container {
            position: relative;
        }
        
        .phone-format-hint {
            color: #666;
            font-size: 0.8em;
            margin-top: 4px;
            display: block;
        }
        
        .phone-error-message {
            color: #dc3545;
            font-size: 0.8em;
            margin-top: 4px;
            display: none;
        }
        
        input[type="tel"].invalid {
            border-color: #dc3545;
        }
        
        input[type="tel"].valid {
            border-color: #198754;
        }

        .email-input-container {
            position: relative;
            margin-bottom: 15px;
        }

        .readonly-input {
            background-color: #f8f9fa !important;
            border: 1px solid #ced4da !important;
            cursor: not-allowed;
            color: #495057 !important;
            opacity: 0.8;
        }

        .email-readonly-badge {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background-color: #e9ecef;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            color: #6c757d;
            pointer-events: none;
        }

        .email-readonly-badge i {
            margin-right: 4px;
        }
        
        /* Toast notification styles */
        .toast-container {
            position: fixed;
            right: 20px;
            top: 20px;
            max-width: 320px;
            z-index: 9999;
        }
        
        .toast {
            background-color: #1a1a1a;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            animation: slideIn 0.3s forwards, fadeOut 0.5s 3.5s forwards;
            overflow: hidden;
        }
        
        .toast-success {
            border-left: 4px solid #3b7a57;
        }
        
        .toast-error {
            border-left: 4px solid #dc3545;
        }
        
        .toast-icon {
            margin-right: 15px;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .toast-success .toast-icon {
            color: #3b7a57;
        }
        
        .toast-error .toast-icon {
            color: #dc3545;
        }
        
        .toast-content {
            flex: 1;
        }
        
        .toast-title {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .toast-message {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .toast-close {
            color: #aaa;
            font-size: 18px;
            cursor: pointer;
            margin-left: 10px;
            transition: color 0.2s;
        }
        
        .toast-close:hover {
            color: white;
        }
        
        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            width: 100%;
            background-color: rgba(255,255,255,0.2);
        }
        
        .toast-progress-bar {
            height: 100%;
            width: 100%;
            background-color: #3b7a57;
            animation: progress 4s linear forwards;
        }
        
        .toast-error .toast-progress-bar {
            background-color: #dc3545;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }
        
        @keyframes progress {
            from {
                width: 100%;
            }
            to {
                width: 0%;
            }
        }

        /* Delete Account Modal */
        .delete-modal {
            background-color: #1a1a1a;
            max-width: 450px;
            width: 90%;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            position: relative;
            color: #f0f0f0;
            margin: 0 auto;
        }
        
        .delete-modal h2 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #dc3545;
            font-weight: 500;
        }
        
        .delete-modal p {
            font-size: 15px;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .warning-box {
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .warning-box i {
            color: #dc3545;
            font-size: 20px;
            margin-top: 2px;
        }
        
        .warning-box p {
            margin: 0;
            color: #dc3545;
            font-size: 14px;
        }
        
        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .modal-btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        
        .cancel-btn {
            background-color: #2a2a2a;
            color: #f0f0f0;
        }
        
        .cancel-btn:hover {
            background-color: #3a3a3a;
        }
        
        .delete-btn {
            background-color: #dc3545;
            color: white;
        }
        
        .delete-btn:hover {
            background-color: #c82333;
        }

        /* Toggle Switch Styles */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: #3b7a57;
        }
        
        input:focus + .toggle-slider {
            box-shadow: 0 0 1px #3b7a57;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }
        
        input:disabled + .toggle-slider {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        /* Notification Item Styles */
        .notification-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 0;
            border-bottom: 1px solid #2d2d2d;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-info {
            display: flex;
            flex-direction: column;
        }
        
        .notification-title {
            font-size: 15px;
            font-weight: 500;
            color: #f0f0f0;
            margin-bottom: 5px;
        }
        
        .maintenance-badge {
            display: inline-block;
            background-color: rgba(255, 193, 7, 0.15);
            color: #ffc107;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 4px;
            margin-top: 5px;
        }
        
        .maintenance-badge i {
            margin-right: 4px;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="toggle-container">
                <button class="toggle-btn" id="sidebarToggle">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
            <div class="user-info">
                <img src="<?php echo !empty($user['profile_image']) ? '../' . $user['profile_image'] : '../assets/images/default-avatar.jpg'; ?>" 
                     alt="User Avatar" class="user-avatar">
                <h3><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h3>
                <p><?php echo $user['email']; ?></p>
                <div class="user-stats">
                    <div class="stat">
                        <div class="stat-number"><?php echo $orderCount ?? '0'; ?></div>
                        <div class="stat-label">Orders</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number"><?php echo $favoriteCount ?? '0'; ?></div>
                        <div class="stat-label">Favorites</div>
                    </div>
                </div>
            </div>
            <ul class="menu-items">
                <li><a href="orders.php"><i class="fa-solid fa-bag-shopping"></i> My Orders</a></li>
                <li><a href="profile.php"><i class="fa-solid fa-user"></i> Profile</a></li>
                <li><a href="payment.php"><i class="fa-solid fa-credit-card"></i> Payment Methods</a></li>
                <li><a href="address.php"><i class="fa-solid fa-location-dot"></i> Delivery Address</a></li>
                <li><a href="favorite.php"><i class="fa-solid fa-heart"></i> Favorites</a></li>
                <li><a href="settings.php" class="active"><i class="fa-solid fa-gear"></i> Account Settings</a></li>
                <li><a href="contact.php"><i class="fa-solid fa-phone"></i> Contact Us</a></li>
                <li><a href="#" onclick="showLogoutModal(); return false;" class="logout-link">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <a href="user.php" class="back-button">
                <i class="fa fa-arrow-left" aria-hidden="true"></i>
            </a>

            <div class="settings-container">
                <div class="tabs">
                    <button class="tab-link active" onclick="openTab(event, 'security')">Security</button>
                    <button class="tab-link" onclick="openTab(event, 'notifications')">Notifications</button>
                </div>

                <div id="security" class="tab-content">
                    <h3>Change Password</h3>
                    
                    <form id="passwordChangeForm">
                        <input type="hidden" name="form_type" value="password_change">
                        <div class="input-group">
                            <label for="current_password">Current Password</label>
                            <div class="password-input-container">
                                <input type="password" id="current_password" name="current_password" placeholder="Enter current password" required>
                                <span class="password-toggle"><i class="fas fa-eye"></i></span>
                            </div>
                        </div>
                        <div class="input-group">
                            <label for="new_password">New Password</label>
                            <div class="password-input-container">
                                <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
                                <span class="password-toggle"><i class="fas fa-eye"></i></span>
                            </div>
                        </div>
                        <div class="input-group">
                            <label for="confirm_new_password">Confirm New Password</label>
                            <div class="password-input-container">
                                <input type="password" id="confirm_new_password" name="confirm_new_password" placeholder="Confirm new password" required>
                                <span class="password-toggle"><i class="fas fa-eye"></i></span>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </form>

                    <hr style="border: none; border-top: 1px solid #2d2d2d; margin: 40px 0;">

                    <div class="danger-zone">
                        <h3>Danger Zone</h3>
                        <button class="btn btn-danger" id="deleteAccountBtn">Delete Account</button>
                    </div>
                </div>

                <div id="notifications" class="tab-content" style="display: none;">
                    <h3>Notifications</h3>
                    <p>Configure how you would like to receive notifications from us.</p>
                    
                    <div class="notification-item">
                        <div class="notification-info">
                            <div class="notification-title">Email Notifications</div>
                            <div class="maintenance-badge">
                                <i class="fas fa-tools"></i> Under Maintenance
                            </div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked disabled>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="notification-item">
                        <div class="notification-info">
                            <div class="notification-title">SMS Notifications</div>
                            <div class="maintenance-badge">
                                <i class="fas fa-tools"></i> Under Maintenance
                            </div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked disabled>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="notification-item">
                        <div class="notification-info">
                            <div class="notification-title">Promotional Offers</div>
                            <div class="maintenance-badge">
                                <i class="fas fa-tools"></i> Under Maintenance
                            </div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" disabled>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="notification-item">
                        <div class="notification-info">
                            <div class="notification-title">Order Updates</div>
                            <div class="maintenance-badge">
                                <i class="fas fa-tools"></i> Under Maintenance
                            </div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked disabled>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="notification-item">
                        <div class="notification-info">
                            <div class="notification-title">Delivery Notifications</div>
                            <div class="maintenance-badge">
                                <i class="fas fa-tools"></i> Under Maintenance
                            </div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" disabled>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Logout Modal -->
    <div class="modal-overlay" id="logoutModal" style="display: none;">
        <div class="logout-modal">
            <h2>Logout Confirmation</h2>
            <p>Are you sure you want to logout from your account?</p>
            <div class="modal-buttons">
                <button class="modal-btn cancel-btn" onclick="hideLogoutModal()">Cancel</button>
                <button class="modal-btn confirm-btn" onclick="confirmLogout(this)">
                    <span class="btn-text">Logout</span>
                    <span class="loading-spinner" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i>
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div class="modal-overlay" id="deleteAccountModal" style="display: none;">
        <div class="delete-modal">
            <h2>Delete Account</h2>
            <div id="deleteAccountMessage">
                <p>Are you sure you want to delete your account? This action cannot be undone.</p>
                <div id="orderWarning" style="display: none;">
                    <div class="warning-box">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Warning: You have pending orders. If you delete your account, you will lose access to your order history.</p>
                    </div>
                </div>
            </div>
            <div class="modal-buttons">
                <button class="modal-btn cancel-btn" onclick="hideDeleteModal()">Cancel</button>
                <button class="modal-btn delete-btn" onclick="confirmDeleteAccount(this)">
                    <span class="btn-text">Delete My Account</span>
                    <span class="loading-spinner" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i>
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- Toast notification container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Sidebar toggle functionality
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        const toggleIcon = sidebarToggle.querySelector('i');

        function toggleSidebar() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            // Adjust settings container margin - critical fix
            const settingsContainer = document.querySelector('.settings-container');
            if (settingsContainer) {
                if (sidebar.classList.contains('collapsed')) {
                    settingsContainer.style.marginLeft = '0';
                } else {
                    settingsContainer.style.marginLeft = '280px';
                }
            }
            
            // Toggle the icon between bars and times
            if (sidebar.classList.contains('collapsed')) {
                toggleIcon.classList.remove('fa-bars');
                toggleIcon.classList.add('fa-times');
            } else {
                toggleIcon.classList.remove('fa-times');
                toggleIcon.classList.add('fa-bars');
            }
            
            // Save sidebar state to localStorage
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }

        sidebarToggle.addEventListener('click', toggleSidebar);

        // Check saved sidebar state on page load
        document.addEventListener('DOMContentLoaded', () => {
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (sidebarCollapsed) {
                toggleSidebar();
            } else {
                // Ensure proper initial margins when sidebar is expanded
                const settingsContainer = document.querySelector('.settings-container');
                if (settingsContainer) {
                    settingsContainer.style.marginLeft = '280px';
                }
            }
            
            // Handle initial state for mobile
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                const settingsContainer = document.querySelector('.settings-container');
                if (settingsContainer) {
                    settingsContainer.style.marginLeft = '0';
                }
            }
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                const settingsContainer = document.querySelector('.settings-container');
                if (settingsContainer) {
                    settingsContainer.style.marginLeft = '0';
                }
            }
        });

        // Profile image upload handling
        const profileImageContainer = document.querySelector('.profile-image-container');
        const profileImageInput = document.getElementById('profileImageInput');
        const profileImagePreview = document.getElementById('profileImagePreview');

        if (profileImageContainer && profileImageInput && profileImagePreview) {
            profileImageContainer.addEventListener('click', () => {
                profileImageInput.click();
            });

            profileImageInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        profileImagePreview.src = e.target.result;
                    };
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }

        // Toast notification functions
        function showToast(type, title, message) {
            const toastContainer = document.getElementById('toastContainer');
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = 'toast toast-' + type;
            
            // Icon based on type
            let iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="fas ${iconClass}"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <div class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </div>
                <div class="toast-progress">
                    <div class="toast-progress-bar"></div>
                </div>
            `;
            
            // Add to container
            toastContainer.appendChild(toast);
            
            // Remove after animation completes (4.5 seconds)
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 4500);
        }

        // Password change form submission
        document.getElementById('passwordChangeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Show success notification
                    showToast('success', 'Success', data.message);
                    
                    // Reset form
                    this.reset();
                } else {
                    // Show error notification
                    showToast('error', 'Error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Error', 'An unexpected error occurred. Please try again.');
            });
        });

        // Logout functionality
        function showLogoutModal() {
            const modal = document.getElementById('logoutModal');
            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }

        function hideLogoutModal() {
            const modal = document.getElementById('logoutModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        function confirmLogout(button) {
            if (!button) return;

            // Disable button and show loading state
            button.disabled = true;
            button.querySelector('.btn-text').style.display = 'none';
            button.querySelector('.loading-spinner').style.display = 'inline';

            // Get the current URL path
            const currentPath = window.location.pathname;
            // Get the directory path by removing the file name
            const directoryPath = currentPath.substring(0, currentPath.lastIndexOf('/'));
            
            fetch(currentPath, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'logout'
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    // Redirect to the index page (up one directory)
                    window.location.href = directoryPath + '/../index.php';
                } else {
                    throw new Error(data.message || 'Error during logout');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred during logout. Please try again.');
                // Reset button state
                button.disabled = false;
                button.querySelector('.btn-text').style.display = 'inline';
                button.querySelector('.loading-spinner').style.display = 'none';
            });
        }

        // Close modal if clicking outside
        document.getElementById('logoutModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideLogoutModal();
            }
        });

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideLogoutModal();
            }
        });

        // Tab functionality
        function openTab(evt, tabName) {
            // Hide all tab contents
            var tabcontent = document.getElementsByClassName("tab-content");
            for (var i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }

            // Remove active class from all tab links
            var tablinks = document.getElementsByClassName("tab-link");
            for (var i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
                
                // Remove any existing indicator spans
                var spans = tablinks[i].getElementsByTagName("span");
                while (spans.length > 0) {
                    spans[0].parentNode.removeChild(spans[0]);
                }
            }

            // Show the current tab and add active class to the button
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
            
            // Add indicator to active tab
            var indicator = document.createElement("span");
            indicator.style.position = "absolute";
            indicator.style.bottom = "-1px";
            indicator.style.left = "0";
            indicator.style.width = "100%";
            indicator.style.height = "2px";
            indicator.style.backgroundColor = "#3b7a57";
            evt.currentTarget.appendChild(indicator);
        }

        // Password visibility toggle
        const passwordToggles = document.querySelectorAll('.password-toggle');
        passwordToggles.forEach(toggle => {
            toggle.addEventListener('click', function() {
                const passwordInput = this.previousElementSibling;
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        });

        // Phone number formatting and validation
        const phoneInput = document.getElementById('phone');
        const phoneError = document.querySelector('.phone-error-message');

        if (phoneInput && phoneError) {
            phoneInput.addEventListener('input', function(e) {
                let value = e.target.value;
                
                // Ensure the number always starts with +63
                if (!value.startsWith('+63')) {
                    value = '+63' + value.replace('+63', '');
                }
                
                // Remove all non-digit characters except the plus sign
                let cleaned = value.replace(/[^\d+]/g, '');
                
                // Format the number: +63 XXX XXX XXXX
                if (cleaned.length > 1) {
                    let formatted = cleaned.substring(0, 3); // +63
                    if (cleaned.length > 3) {
                        formatted += ' ' + cleaned.substring(3, 6);
                    }
                    if (cleaned.length > 6) {
                        formatted += ' ' + cleaned.substring(6, 9);
                    }
                    if (cleaned.length > 9) {
                        formatted += ' ' + cleaned.substring(9, 13);
                    }
                    value = formatted;
                }
                
                // Update input value
                e.target.value = value;
                
                // Validate the phone number
                const isValid = /^\+63\s?\d{3}\s?\d{3}\s?\d{4}$/.test(value);
                
                if (value.length > 3) {
                    if (!isValid) {
                        phoneError.style.display = 'block';
                        phoneError.textContent = 'Please enter a valid Philippine phone number';
                        phoneInput.classList.add('invalid');
                        phoneInput.classList.remove('valid');
                    } else {
                        phoneError.style.display = 'none';
                        phoneInput.classList.remove('invalid');
                        phoneInput.classList.add('valid');
                    }
                } else {
                    phoneError.style.display = 'none';
                    phoneInput.classList.remove('invalid');
                    phoneInput.classList.remove('valid');
                }
            });

            // Prevent deletion of +63 prefix
            phoneInput.addEventListener('keydown', function(e) {
                const value = e.target.value;
                const selectionStart = this.selectionStart;
                
                // Prevent backspace from deleting +63 prefix
                if (e.key === 'Backspace' && selectionStart <= 3) {
                    e.preventDefault();
                }
            });
        }

        // Delete Account Functionality
        const deleteAccountBtn = document.getElementById('deleteAccountBtn');
        const deleteAccountModal = document.getElementById('deleteAccountModal');
        
        deleteAccountBtn.addEventListener('click', function() {
            // Check if user has orders before showing delete modal
            fetch('check_orders.php')
                .then(response => response.json())
                .then(data => {
                    const orderWarning = document.getElementById('orderWarning');
                    
                    if (data.has_orders) {
                        orderWarning.style.display = 'block';
                    } else {
                        orderWarning.style.display = 'none';
                    }
                    
                    // Show the modal
                    deleteAccountModal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                })
                .catch(error => {
                    console.error('Error checking orders:', error);
                    // Show modal without order check if there's an error
                    deleteAccountModal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                });
        });
        
        function hideDeleteModal() {
            deleteAccountModal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        function confirmDeleteAccount(button) {
            // Disable button and show loading state
            button.disabled = true;
            button.querySelector('.btn-text').style.display = 'none';
            button.querySelector('.loading-spinner').style.display = 'inline';
            
            fetch('settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete_account'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Get the directory path by removing the file name
                    const currentPath = window.location.pathname;
                    const directoryPath = currentPath.substring(0, currentPath.lastIndexOf('/'));
                    // Redirect to the index page
                    window.location.href = directoryPath + '/../index.php';
                } else {
                    // Show error notification
                    hideDeleteModal();
                    showToast('error', 'Error', data.message);
                    
                    // Reset button state
                    button.disabled = false;
                    button.querySelector('.btn-text').style.display = 'inline';
                    button.querySelector('.loading-spinner').style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                hideDeleteModal();
                showToast('error', 'Error', 'An unexpected error occurred. Please try again.');
                
                // Reset button state
                button.disabled = false;
                button.querySelector('.btn-text').style.display = 'inline';
                button.querySelector('.loading-spinner').style.display = 'none';
            });
        }
        
        // Close delete modal if clicking outside
        deleteAccountModal.addEventListener('click', function(e) {
            if (e.target === this) {
                hideDeleteModal();
            }
        });

        // Ensure modals are hidden on page load
        document.addEventListener('DOMContentLoaded', function() {
            const logoutModal = document.getElementById('logoutModal');
            const deleteAccountModal = document.getElementById('deleteAccountModal');
            
            if (logoutModal) {
                logoutModal.style.display = 'none';
            }
            
            if (deleteAccountModal) {
                deleteAccountModal.style.display = 'none';
            }
        });
    </script>
</body>
</html>