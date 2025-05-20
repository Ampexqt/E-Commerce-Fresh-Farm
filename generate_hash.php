<?php
// Simple utility script to generate a password hash and verify it
// For debugging admin login issues

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get password from GET parameter or use default
$password = $_GET['password'] ?? 'Admin@123';

// Generate hash
$hash = password_hash($password, PASSWORD_BCRYPT);

// Verify the hash works
$verification = password_verify($password, $hash);

// Output in browser
?>
<!DOCTYPE html>
<html>
<head>
    <title>Password Hash Generator</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .card { border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px; }
        .success { color: green; }
        .error { color: red; }
        form { margin-bottom: 20px; }
        input[type="text"] { padding: 8px; width: 300px; }
        button { padding: 8px 16px; background: #4CAF50; color: white; border: none; cursor: pointer; }
        
        code { font-family: monospace; background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>Password Hash Generator</h1>
    
    <div class="card">
        <form method="GET">
            <label for="password">Enter password to hash:</label>
            <input type="text" id="password" name="password" value="<?php echo htmlspecialchars($password); ?>">
            <button type="submit">Generate Hash</button>
        </form>
    </div>
    
    <div class="card">
        <h2>Results for password: "<?php echo htmlspecialchars($password); ?>"</h2>
        <h3>Generated Hash:</h3>
        <pre><?php echo $hash; ?></pre>
        
        <h3>Verification:</h3>
        <p class="<?php echo $verification ? 'success' : 'error'; ?>">
            Verification <?php echo $verification ? 'SUCCESSFUL ✓' : 'FAILED ✗'; ?>
        </p>
    </div>
    
    <div class="card">
        <h2>Admin Setup SQL</h2>
        <p>Use this SQL to update your admin password:</p>
        <pre>UPDATE admins SET password = '<?php echo $hash; ?>' WHERE email = 'admin@freshfarm.com';</pre>
    </div>
    
    <div class="card">
        <h2>Admin Creation SQL</h2>
        <p>Or use this SQL to create a new admin:</p>
        <pre>INSERT INTO admins 
(username, email, password, first_name, last_name, permission_level, status) 
VALUES 
('admin', 'admin@freshfarm.com', 
'<?php echo $hash; ?>', 
'Admin', 'User', 'super_admin', 1);</pre>
    </div>
</body>
</html> 