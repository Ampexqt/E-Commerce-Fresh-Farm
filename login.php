<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'connect.php';

try {
    // Get and sanitize input
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $user_password = $_POST['password'] ?? ''; // Renamed to avoid confusion with DB password

    // Debug log
    error_log("Login attempt started - Email: " . $email);

    // Validate input
    if(empty($email) || empty($user_password)) {
        error_log("Login failed: Empty email or password");
        echo json_encode([
            'status' => false,
            'message' => 'Please enter both email and password'
        ]);
        exit;
    }

    // Create PDO connection using credentials from connect.php
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("Database connection successful");

    // Check if admins table exists
    $tablesStmt = $pdo->query("SHOW TABLES LIKE 'admins'");
    $adminsTableExists = ($tablesStmt->rowCount() > 0);
    error_log("Admins table exists: " . ($adminsTableExists ? 'Yes' : 'No'));

    if ($adminsTableExists) {
        // First check in admin table
        $adminStmt = $pdo->prepare("SELECT * FROM admins WHERE (email = ? OR username = ?)");
        $adminStmt->execute([$email, $email]); // Check both email and username
        $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            error_log("Admin found with ID: " . $admin['admin_id']);
            
            // Check if password column exists in admin record
            if (!isset($admin['password'])) {
                error_log("ERROR: Password column missing in admin record");
            } else {
                $passVerification = password_verify($user_password, $admin['password']);
                error_log("Password verification result: " . ($passVerification ? 'success' : 'failed'));
                
                if ($passVerification) {
                    // Set admin session
                    $_SESSION['user_id'] = $admin['admin_id'];
                    $_SESSION['user_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
                    $_SESSION['user_email'] = $admin['email'];
                    $_SESSION['user_role'] = 'admin';
                    $_SESSION['admin_id'] = $admin['admin_id']; 
                    $_SESSION['admin_permission'] = $admin['permission_level'] ?? 'admin';
                    $_SESSION['is_admin'] = true;
                    
                    // Update last login for admin
                    $updateStmt = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE admin_id = ?");
                    $updateStmt->execute([$admin['admin_id']]);
                    
                    error_log("Admin login successful for: " . $email);

                    echo json_encode([
                        'status' => true,
                        'message' => 'Admin Login successful! Redirecting...',
                        'user' => [
                            'name' => $_SESSION['user_name'],
                            'email' => $_SESSION['user_email'],
                            'role' => $_SESSION['user_role']
                        ],
                        'redirect' => 'dashboard/dashboard.php'
                    ]);
                    exit;
                } else {
                    error_log("Admin password verification failed");
                }
            }
        } else {
            error_log("No admin found with email/username: " . $email);
        }
    }

    // If not admin or admin password failed, check regular users table
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    error_log("Checking regular user - Query prepared: SELECT * FROM users WHERE email = " . $email);
    
    $stmt->execute([$email]);
    error_log("Query executed");
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("User data fetched: " . ($user ? "User found" : "No user found"));

    if ($user) {
        error_log("Attempting password verification for user: " . $email);
        $userPassVerification = password_verify($user_password, $user['password']);
        error_log("User password verification result: " . ($userPassVerification ? 'success' : 'failed'));
        
        if ($userPassVerification) {
            // Create session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['is_admin'] = false;
            
            error_log("Login successful for user: " . $email);

            echo json_encode([
                'status' => true,
                'message' => 'Login successful! Redirecting...',
                'user' => [
                    'name' => $_SESSION['user_name'],
                    'email' => $_SESSION['user_email'],
                    'role' => $_SESSION['user_role']
                ],
                'redirect' => $_SESSION['user_role'] === 'admin' ? 'dashboard/dashboard.php' : 'user-dashboard/user.php'
            ]);
        } else {
            error_log("Password verification failed for user: " . $email);
            echo json_encode([
                'status' => false,
                'message' => 'Invalid email or password'
            ]);
        }
    } else {
        error_log("No user found with email: " . $email);
        echo json_encode([
            'status' => false,
            'message' => 'Invalid email or password'
        ]);
    }
} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'status' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    error_log("General error: " . $e->getMessage());
    echo json_encode([
        'status' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
}
?>