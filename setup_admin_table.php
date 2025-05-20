<?php
// Include database connection
require_once 'connect.php';

// SQL to create the admin table
$createTableSQL = "
CREATE TABLE IF NOT EXISTS `admins` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `permission_level` enum('super_admin','admin','editor') NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

// Execute the create table SQL
if ($conn->query($createTableSQL) === TRUE) {
    echo "Admin table created successfully!<br>";
} else {
    echo "Error creating admin table: " . $conn->error . "<br>";
    exit;
}

// Check if admin user already exists
$checkAdminSQL = "SELECT * FROM `admins` WHERE username = 'admin' OR email = 'admin@freshfarm.com'";
$result = $conn->query($checkAdminSQL);

if ($result->num_rows > 0) {
    echo "Admin user already exists in the admins table!<br>";
} else {
    // Insert default admin user
    $insertAdminSQL = "
    INSERT INTO `admins` 
    (`username`, `email`, `password`, `first_name`, `last_name`, `permission_level`, `status`) 
    VALUES 
    ('admin', 'admin@freshfarm.com', 
    '$2y$10$D9JgzEwOiJwBdtpk9Dq15.CXD6WXrX11/ybpjpkQ0ncdpb.mZdz2W', 
    'Admin', 'User', 'super_admin', 1);
    ";

    if ($conn->query($insertAdminSQL) === TRUE) {
        echo "Default admin user created successfully!<br><br>";
        echo "Login credentials:<br>";
        echo "Username: admin<br>";
        echo "Email: admin@freshfarm.com<br>";
        echo "Password: Admin@123<br>";
    } else {
        echo "Error creating admin user: " . $conn->error . "<br>";
    }
}

$conn->close();
?> 