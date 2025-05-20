<?php
// Include database connection
require_once 'connect.php';

// SQL statement to create admin user
$sql = "INSERT INTO users 
(username, first_name, last_name, email, phone_number, password, role, active) 
VALUES 
('admin', 'Admin', 'User', 'admin@freshfarm.com', '+1234567890', 
'$2y$10$D9JgzEwOiJwBdtpk9Dq15.CXD6WXrX11/ybpjpkQ0ncdpb.mZdz2W', 'admin', 1)";

// Check if admin user already exists
$check_sql = "SELECT * FROM users WHERE email = 'admin@freshfarm.com' OR username = 'admin'";
$result = $conn->query($check_sql);

if ($result->num_rows > 0) {
    echo "Admin user already exists!";
} else {
    // Create admin user
    if ($conn->query($sql) === TRUE) {
        echo "Admin user created successfully!";
        echo "<br><br>";
        echo "Username: admin<br>";
        echo "Email: admin@freshfarm.com<br>";
        echo "Password: Admin@123<br>";
    } else {
        echo "Error creating admin user: " . $conn->error;
    }
}

$conn->close();
?> 