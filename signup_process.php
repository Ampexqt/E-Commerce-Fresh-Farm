<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
require_once 'connect.php';

try {
    // Log start of registration
    error_log("User registration started");
    
    // Get and sanitize input
    $first_name = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $last_name = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Generate a username
    $username = strtolower(str_replace(' ', '', $first_name) . substr(uniqid(), -5));

    // Log registration data (exclude password for security)
    error_log("Registration data: Name: $first_name $last_name, Email: $email");

    // Validate input
    if(empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password)) {
        error_log("Registration failed: Empty required fields");
        echo json_encode([
            'status' => false,
            'message' => 'Please fill in all required fields'
        ]);
        exit;
    }

    // Validate email format
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("Registration failed: Invalid email format - $email");
        echo json_encode([
            'status' => false,
            'message' => 'Please enter a valid email address'
        ]);
        exit;
    }

    // Check if passwords match
    if($password !== $confirm_password) {
        error_log("Registration failed: Passwords don't match");
        echo json_encode([
            'status' => false,
            'message' => 'Passwords do not match'
        ]);
        exit;
    }

    // Password strength validation
    if (strlen($password) < 8) {
        error_log("Registration failed: Password too short");
        echo json_encode([
            'status' => false,
            'message' => 'Password must be at least 8 characters long'
        ]);
        exit;
    }

    // Use the existing connection from connect.php
    global $conn;
    
    if (!$conn) {
        error_log("Registration failed: No database connection");
        echo json_encode([
            'status' => false,
            'message' => 'Database connection error'
        ]);
        exit;
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    if (!$stmt) {
        error_log("Registration failed: Prepare statement error - " . $conn->error);
        echo json_encode([
            'status' => false,
            'message' => 'Registration system error. Please try again later.'
        ]);
        exit;
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        error_log("Registration failed: Email already exists - $email");
        echo json_encode([
            'status' => false,
            'message' => 'Email already registered'
        ]);
        exit;
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user
    $insert_stmt = $conn->prepare("INSERT INTO users (username, first_name, last_name, email, password) VALUES (?, ?, ?, ?, ?)");
    if (!$insert_stmt) {
        error_log("Registration failed: Prepare statement error - " . $conn->error);
        echo json_encode([
            'status' => false,
            'message' => 'Registration system error. Please try again later.'
        ]);
        exit;
    }
    
    $insert_stmt->bind_param("sssss", $username, $first_name, $last_name, $email, $hashed_password);
    
    $insert_success = $insert_stmt->execute();
    
    if(!$insert_success) {
        error_log("Registration failed: Database error - " . $conn->error);
        echo json_encode([
            'status' => false,
            'message' => 'Registration failed: ' . $conn->error
        ]);
        exit;
    }

    // Get the new user's ID
    $user_id = $conn->insert_id;
    error_log("User successfully registered with ID: $user_id");

    // Create session
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_name'] = $first_name . ' ' . $last_name;
    $_SESSION['user_email'] = $email;
    
    // Success response
    echo json_encode([
        'status' => true,
        'message' => 'Registration successful! Redirecting...',
        'user' => [
            'id' => $user_id,
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email']
        ]
    ]);
    
    error_log("Registration complete and session created for user: $user_id");

} catch(Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    echo json_encode([
        'status' => false,
        'message' => 'Registration failed: ' . $e->getMessage()
    ]);
}
?>