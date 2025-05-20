<?php
session_start();

// Check if already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard/dashboard.php');
    exit;
}

$error = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'connect.php';
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Check credentials against admin table
        $stmt = $conn->prepare("SELECT * FROM admins WHERE (username = ? OR email = ?) AND status = 1");
        $stmt->bind_param("ss", $username, $username); // Allow login with either username or email
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $admin['password'])) {
                // Set session variables
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_permission'] = $admin['permission_level'];
                
                // Update last login time
                $updateStmt = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE admin_id = ?");
                $updateStmt->bind_param("i", $admin['admin_id']);
                $updateStmt->execute();
                
                // Redirect to dashboard
                header('Location: dashboard/dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Fresh Farm</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f7f9fc;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        
        .login-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 400px;
            max-width: 90%;
        }
        
        .login-header {
            background-color: #4CAF50;
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .input-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .input-group input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .input-group input:focus {
            border-color: #4CAF50;
            outline: none;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 13px;
            color: #666;
        }
        
        .submit-button {
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 12px;
            width: 100%;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .submit-button:hover {
            background-color: #3e8e41;
        }
        
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .error-message i {
            margin-right: 10px;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #4CAF50;
            text-decoration: none;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Admin Login</h1>
            <p>FarmFresh Control Panel</p>
        </div>
        
        <div class="login-body">
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <div class="input-group">
                    <span class="input-icon"><i class="fas fa-user"></i></span>
                    <input type="text" name="username" placeholder="Username or Email" required>
                </div>
                
                <div class="input-group">
                    <span class="input-icon"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                
                <button type="submit" class="submit-button">Login</button>
            </form>
            
            <div class="back-link">
                <a href="index.php"><i class="fas fa-arrow-left"></i> Back to Website</a>
            </div>
        </div>
    </div>
</body>
</html> 
 