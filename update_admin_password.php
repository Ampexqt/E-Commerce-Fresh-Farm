<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'connect.php';

// The admin credentials to set
$admin_email = 'admin@freshfarm.com';
$admin_password = 'Admin@123'; // This will be hashed

// Create a proper bcrypt hash
$hashed_password = password_hash($admin_password, PASSWORD_BCRYPT);

try {
    // Create PDO connection using credentials from connect.php
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Update the admin password
    $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE email = ?");
    $result = $stmt->execute([$hashed_password, $admin_email]);
    
    if ($result) {
        echo "Admin password updated successfully!<br>";
        echo "Email: $admin_email<br>";
        echo "Password: $admin_password (unhashed)<br>";
        echo "Hashed Password: $hashed_password<br>";
    } else {
        echo "Failed to update admin password.";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 