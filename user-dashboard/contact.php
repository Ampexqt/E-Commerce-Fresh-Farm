<?php
// Start session and include database connection
session_start();
require_once '../connect.php';

// Check if user is logged in first
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Get user information before using $user
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Create a default user array with empty values
$defaultUser = [
    'user_id' => $user_id,
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'profile_image' => '',
    'phone' => '',
    'phone_number' => '',
    'birth_date' => null
];

// Merge with actual user data if available
if ($user) {
    $user = array_merge($defaultUser, $user);
} else {
    $user = $defaultUser;
    error_log("Failed to retrieve user data for user_id: {$user_id}");
}

// Include the Mailer class
require_once '../includes/mailer.php';

// Handle email sending
$emailSuccess = false;
$emailError = "";

// Process contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    try {
        // Replace deprecated FILTER_SANITIZE_STRING with htmlspecialchars
        $name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $subject = htmlspecialchars(trim($_POST['subject'] ?? ''), ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars(trim($_POST['message'] ?? ''), ENT_QUOTES, 'UTF-8');
        
        // Use user's email if available, or default to empty string
        $email = isset($user['email']) ? $user['email'] : '';
        
        // Basic validation
        if (empty($name) || empty($message)) {
            $emailError = "Please fill in all required fields.";
        } else {
            // Skip PHP mailer for now and show success message
            // We'll rely on the JavaScript EmailJS implementation
            $emailSuccess = true;
        }
    } catch (Exception $e) {
        $emailError = "An error occurred while processing your message.";
        error_log("Contact form error: " . $e->getMessage());
    }
}

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
}

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

// Handle profile update
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_submit'])) {
    $firstName = isset($_POST['first_name']) ? htmlspecialchars(trim($_POST['first_name']), ENT_QUOTES, 'UTF-8') : '';
    $lastName = isset($_POST['last_name']) ? htmlspecialchars(trim($_POST['last_name']), ENT_QUOTES, 'UTF-8') : '';
    $phone = !empty($_POST['phone']) ? $_POST['phone'] : null;
    $birthDate = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;

    // Handle file upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_image']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($filetype), $allowed)) {
            $tempname = $_FILES['profile_image']['tmp_name'];
            $folder = "../uploads/profile_images/";
            
            // Create directory if it doesn't exist
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            // Delete previous profile image if exists
            if (!empty($user['profile_image']) && file_exists('../' . $user['profile_image'])) {
                unlink('../' . $user['profile_image']);
            }
            
            // Generate unique filename
            $new_filename = "profile_" . $user_id . "_" . time() . "." . $filetype;
            $filepath = $folder . $new_filename;
            
            if (move_uploaded_file($tempname, $filepath)) {
                // Update database with new image path
                $relative_path = "uploads/profile_images/" . $new_filename;
                $updateStmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE user_id = ?");
                $updateStmt->bind_param("si", $relative_path, $user_id);
                $updateStmt->execute();
            }
        }
    }

    // Update user information - update both phone and phone_number fields for compatibility
    $updateStmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, phone_number = ?, birth_date = ? WHERE user_id = ?");
    $updateStmt->bind_param("sssssi", $firstName, $lastName, $phone, $phone, $birthDate, $user_id);
    
    if ($updateStmt->execute()) {
        $message = "Profile updated successfully!";
        $messageType = "success";
        
        // Refresh user data
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
    } else {
        $message = "Error updating profile. Please try again.";
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - FarmFresh</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/user.css">
    <link rel="stylesheet" href="../css/profile.css">
    <link rel="stylesheet" href="../css/contact.css">
    <!-- EmailJS configuration -->
    <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@3/dist/email.min.js"></script>
    <script type="text/javascript">
        // EmailJS initialization with public key
        (function() {
            try {
                emailjs.init("uUeyuPksg41mYhaNb");
                console.log("EmailJS initialized successfully");
            } catch (error) {
                console.error("EmailJS initialization error:", error);
                // Show error on page load if initialization fails
                window.addEventListener('DOMContentLoaded', function() {
                    const errorMessage = document.getElementById('errorMessage');
                    const errorText = document.getElementById('errorText');
                    if (errorMessage && errorText) {
                        errorText.textContent = 'EmailJS failed to initialize. Please reload the page or try again later.';
                        errorMessage.style.display = 'block';
                    }
                });
            }
        })();
        
        // Function to send contact message (for direct onclick use)
        function sendContactMessage() {
            try {
                console.log("Send Message function called");
                const sendMessageBtn = document.getElementById('sendMessageBtn');
                
                if (!sendMessageBtn) {
                    console.error("Send Message button not found!");
                    alert("Error: Send Message button not found.");
                    return;
                }
                
                // Show loading state
                sendMessageBtn.querySelector('.btn-text').style.display = 'none';
                sendMessageBtn.querySelector('.loading-icon').style.display = 'inline-block';
                sendMessageBtn.disabled = true;
                
                // Get form data - directly from DOM elements
                const name = document.getElementById('name').value;
                const email = document.getElementById('email').value;
                const subject = document.getElementById('subject').value || 'New Contact Message';
                const message = document.getElementById('message').value;
                
                console.log("Form data:", { name, email, subject, message });
                
                // Validate form
                if (!name || !message) {
                    // Show error message
                    const errorMessage = document.getElementById('errorMessage');
                    const errorText = document.getElementById('errorText');
                    errorText.textContent = 'Please fill in all required fields.';
                    errorMessage.style.display = 'block';
                    
                    // Reset button state
                    sendMessageBtn.querySelector('.btn-text').style.display = 'inline-block';
                    sendMessageBtn.querySelector('.loading-icon').style.display = 'none';
                    sendMessageBtn.disabled = false;
                    
                    // Auto-hide error after 5 seconds
                    setTimeout(function() {
                        errorMessage.style.display = 'none';
                    }, 5000);
                    
                    return;
                }
                
                console.log("Sending form with data:", { name, email, subject, message });
                
                // Hide any existing messages
                document.getElementById('successMessage').style.display = 'none';
                document.getElementById('errorMessage').style.display = 'none';
                
                // Prepare EmailJS template parameters
                const templateParams = {
                    from_name: name,
                    reply_to: email,
                    subject: subject,
                    message: message,
                    to_email: "quinonesjames650@gmail.com",
                    recipient_email: "quinonesjames650@gmail.com"
                };
                
                console.log("Sending with params:", templateParams);
                
                // Send the email
                emailjs.send('service_46f9sur', 'template_1lgbrhn', templateParams)
                    .then(function(response) {
                        console.log('Email sent successfully!', response);
                        
                        // Show success message using the permanent container
                        const successMessage = document.getElementById('successMessage');
                        successMessage.style.display = 'block';
                        
                        // Reset form
                        document.getElementById('subject').value = '';
                        document.getElementById('message').value = '';
                        
                        // Reset button state
                        sendMessageBtn.querySelector('.btn-text').style.display = 'inline-block';
                        sendMessageBtn.querySelector('.loading-icon').style.display = 'none';
                        sendMessageBtn.disabled = false;
                        
                        // Auto-hide the success message after 5 seconds
                        setTimeout(function() {
                            successMessage.style.display = 'none';
                        }, 5000);
                    })
                    .catch(function(error) {
                        console.error('Email failed to send:', error);
                        
                        // Show error message using the permanent container
                        const errorMessage = document.getElementById('errorMessage');
                        const errorText = document.getElementById('errorText');
                        errorText.textContent = 'There was an error sending your message: ' + 
                                              (error.text || error.message || JSON.stringify(error));
                        errorMessage.style.display = 'block';
                        
                        // Reset button state
                        sendMessageBtn.querySelector('.btn-text').style.display = 'inline-block';
                        sendMessageBtn.querySelector('.loading-icon').style.display = 'none';
                        sendMessageBtn.disabled = false;
                        
                        // Auto-hide the error message after 10 seconds
                        setTimeout(function() {
                            errorMessage.style.display = 'none';
                        }, 10000);
                    });
            } catch (error) {
                console.error("Error in send message function:", error);
                alert("An error occurred. Please check the console for details.");
                
                // Reset button state
                const sendMessageBtn = document.getElementById('sendMessageBtn');
                if (sendMessageBtn) {
                    sendMessageBtn.querySelector('.btn-text').style.display = 'inline-block';
                    sendMessageBtn.querySelector('.loading-icon').style.display = 'none';
                    sendMessageBtn.disabled = false;
                }
            }
        }
        
        // Global test function
        function runEmailTest() {
            console.log("Test function called directly");
            const testEmailBtn = document.getElementById('testEmailBtn');
            const testResults = document.getElementById('testResults');
            
            if (!testEmailBtn || !testResults) {
                alert("Test elements not found on page!");
                return;
            }
            
            testEmailBtn.disabled = true;
            testEmailBtn.textContent = 'Testing...';
            testResults.style.display = 'block';
            testResults.innerHTML = 'Running test...<br>';
            
            // Test step 1: Verify EmailJS is loaded
            testResults.innerHTML += '<b>Step 1:</b> Checking if EmailJS is loaded... ';
            if (typeof emailjs !== 'undefined') {
                testResults.innerHTML += '<span style="color: green;">SUCCESS</span><br>';
            } else {
                testResults.innerHTML += '<span style="color: red;">FAILED (EmailJS not loaded)</span><br>';
                testEmailBtn.disabled = false;
                testEmailBtn.textContent = 'Test Email Service';
                return;
            }
            
            // Test step 2: Send a test email
            testResults.innerHTML += '<b>Step 2:</b> Sending test email... ';
            
            const testParams = {
                from_name: 'Test User',
                reply_to: 'test@example.com',
                subject: 'Test Email',
                message: 'This is a test email from your website to verify EmailJS is working correctly.',
                to_email: 'quinonesjames650@gmail.com',
                recipient_email: 'quinonesjames650@gmail.com'
            };
            
            emailjs.send('service_46f9sur', 'template_1lgbrhn', testParams)
                .then(function(response) {
                    testResults.innerHTML += '<span style="color: green;">SUCCESS</span><br>';
                    testResults.innerHTML += '<b>Response:</b> Status ' + response.status + ', Text: ' + response.text + '<br>';
                    testResults.innerHTML += '<b>Next steps:</b><br>1. Check your email inbox (and spam folder)<br>2. Verify your EmailJS dashboard for sent messages<br>';
                    testEmailBtn.disabled = false;
                    testEmailBtn.textContent = 'Test Email Service';
                })
                .catch(function(error) {
                    testResults.innerHTML += '<span style="color: red;">FAILED</span><br>';
                    testResults.innerHTML += '<b>Error:</b> ' + (error.text || error.message || JSON.stringify(error)) + '<br>';
                    testResults.innerHTML += '<b>Check:</b><br>1. Your EmailJS API keys<br>2. Template ID<br>3. Service ID<br>';
                    
                    // Check template parameters
                    testResults.innerHTML += '<b>Template review:</b><br>Your template should include these variables:<br>{{from_name}}, {{reply_to}}, {{subject}}, {{message}}<br>';
                    
                    testEmailBtn.disabled = false;
                    testEmailBtn.textContent = 'Test Email Service';
                });
        }
    </script>
    <style>
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
    </style>
</head>
<body>
   
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
                <h3><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></h3>
                <p><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                <div class="user-stats">
                    <div class="stat">
                        <div class="stat-number"><?php echo isset($orderCount) ? $orderCount : '0'; ?></div>
                        <div class="stat-label">Orders</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number"><?php echo isset($favoriteCount) ? $favoriteCount : '0'; ?></div>
                        <div class="stat-label">Favorites</div>
                    </div>
                </div>
            </div>
            <ul class="menu-items">
                <li><a href="orders.php"><i class="fa-solid fa-bag-shopping"></i> My Orders</a></li>
                <li><a href="profile.php" ><i class="fa-solid fa-user"></i> Profile</a></li>
                <li><a href="payment.php"><i class="fa-solid fa-credit-card"></i> Payment Methods</a></li>
                <li><a href="address.php"><i class="fa-solid fa-location-dot"></i> Delivery Address</a></li>
                <li><a href="favorite.php"><i class="fa-solid fa-heart"></i> Favorites</a></li>
                <li><a href="settings.php"><i class="fa-solid fa-gear"></i> Account Settings</a></li>
                <li><a href="#" class="active"><i class="fa-solid fa-phone"></i> Contact Us</a></li>
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

        <h2 class="contact-title"> Contact Us</h2>
           
        <div class="contact-container">
            <div class="contact-header">
                <h2>Contact Information</h2>
            </div>
            
            <div class="contact-info-section">
                <div class="contact-info-item">
                    <div class="icon-container">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="info-text">
                        Email: quinonesjames650@gmail.com
                    </div>
                </div>
                
                <div class="contact-info-item">
                    <div class="icon-container">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <div class="info-text">
                        Phone: +63 9659450046
                    </div>
                </div>
                
                <div class="contact-info-item">
                    <div class="icon-container">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="info-text">
                        Address: Sea Breeze, Maasin, Zamboanga City, Philippines 7000
                    </div>
                </div>
            </div>
            
            <?php if ($emailSuccess): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Your message has been sent successfully! We'll get back to you soon.
            </div>
            <?php endif; ?>
            
            <?php if (!empty($emailError)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $emailError; ?>
            </div>
            <?php endif; ?>
            
            <form class="contact-form" method="POST" action="" id="contactForm">
                <div class="form-group">
                    <label for="name">Your Name</label>
                    <div class="email-readonly-container">
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>" class="readonly-input" readonly>
                        <span class="readonly-badge"><i class="fas fa-lock"></i></span>
                    </div>
                    <span class="field-hint">Name field is pre-filled with your account name</span>
                </div>
                
                <div class="form-group">
                    <label for="email">Your Email</label>
                    <div class="email-readonly-container">
                        <input type="email" id="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" class="readonly-input" readonly>
                        <span class="readonly-badge"><i class="fas fa-lock"></i></span>
                    </div>
                    <span class="field-hint">Email field is pre-filled with your account email</span>
                </div>
                
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" placeholder="Enter subject">
                </div>
                
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" placeholder="Enter your message" required></textarea>
                </div>
                
                <button type="button" id="sendMessageBtn" class="submit-button" onclick="sendContactMessage()" style="cursor:pointer; display:flex; align-items:center; justify-content:center; width:auto; min-width:150px; padding:0.75rem 1.5rem; margin-bottom:15px;">
                    <span class="btn-text">Send Message</span>
                    <span class="loading-icon" style="display:none; margin-left:8px;"><i class="fas fa-spinner fa-spin"></i></span>
                </button>
                
                <!-- Success message container -->
                <div id="successMessage" class="alert alert-success" style="display:none; margin-top:15px;">
                    <i class="fas fa-check-circle"></i> Your message has been sent successfully! We'll get back to you soon.
                </div>
                
                <!-- Error message container -->
                <div id="errorMessage" class="alert alert-danger" style="display:none; margin-top:15px;">
                    <i class="fas fa-exclamation-circle"></i> <span id="errorText">There was an error sending your message.</span>
                </div>
            </form>
            
            <!-- Debug Testing Section (Hidden in Production) -->
           
        </div>
            
        </main>
    

    <!-- Logout Modal -->
    <div class="modal-overlay" id="logoutModal">
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

    <script>
        // Sidebar toggle functionality
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        const toggleIcon = sidebarToggle.querySelector('i');

        function toggleSidebar() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
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
            }
            
            // Handle initial state for mobile
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            }
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            }
        });

        // Profile image upload handling
        const profileImageContainer = document.querySelector('.profile-image-container');
        const profileImageInput = document.getElementById('profileImageInput');
        const profileImagePreview = document.getElementById('profileImagePreview');

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

        // Add alert auto-hide
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }, 3000);
        });

        // Logout functionality
        function showLogoutModal() {
            const modal = document.getElementById('logoutModal');
            if (modal) {
                modal.style.display = 'block';
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

        // Phone number formatting and validation
        const phoneInput = document.getElementById('phone');
        const phoneError = document.querySelector('.phone-error-message');

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

        // Handle form submission and loading state
        document.addEventListener('DOMContentLoaded', function() {
            // EmailJS is already initialized in the head section
            
            const contactForm = document.querySelector('.contact-form');
            const sendMessageBtn = document.getElementById('sendMessageBtn');
            const successMessage = document.getElementById('successMessage');
            const errorMessage = document.getElementById('errorMessage');
            
            // Debug logging
            console.log("Form elements found:", {
                contactForm: !!contactForm,
                sendMessageBtn: !!sendMessageBtn,
                successMessage: !!successMessage,
                errorMessage: !!errorMessage
            });
            
            if (contactForm && sendMessageBtn) {
                // Use button click instead of form submit
                sendMessageBtn.addEventListener('click', function(e) {
                    try {
                        console.log("Send Message button clicked");
                        
                        // Show loading state
                        sendMessageBtn.querySelector('.btn-text').style.display = 'none';
                        sendMessageBtn.querySelector('.loading-icon').style.display = 'inline-block';
                        sendMessageBtn.disabled = true;
                        
                        // Get form data - directly from DOM elements to ensure we have the values
                        const name = document.getElementById('name').value;
                        const email = document.getElementById('email').value;
                        const subject = document.getElementById('subject').value || 'New Contact Message';
                        const message = document.getElementById('message').value;
                        
                        console.log("Form data:", { name, email, subject, message });
                        
                        // Validate form
                        if (!name || !message) {
                            // Show error message
                            const errorMessage = document.getElementById('errorMessage');
                            const errorText = document.getElementById('errorText');
                            errorText.textContent = 'Please fill in all required fields.';
                            errorMessage.style.display = 'block';
                            
                            // Reset button state
                            sendMessageBtn.querySelector('.btn-text').style.display = 'inline-block';
                            sendMessageBtn.querySelector('.loading-icon').style.display = 'none';
                            sendMessageBtn.disabled = false;
                            
                            // Auto-hide error after 5 seconds
                            setTimeout(function() {
                                errorMessage.style.display = 'none';
                            }, 5000);
                            
                            return;
                        }
                        
                        console.log("Sending form with data:", { name, email, subject, message });
                        
                        // Hide any existing messages
                        document.getElementById('successMessage').style.display = 'none';
                        document.getElementById('errorMessage').style.display = 'none';
                        
                        // Prepare EmailJS template parameters - EXACTLY like the test function
                        const templateParams = {
                            from_name: name,
                            reply_to: email,
                            subject: subject,
                            message: message,
                            to_email: "quinonesjames650@gmail.com",
                            recipient_email: "quinonesjames650@gmail.com"
                        };
                        
                        console.log("Sending with params:", templateParams);
                        
                        // Use the EXACT same EmailJS send approach as the test function
                        emailjs.send('service_46f9sur', 'template_1lgbrhn', templateParams)
                            .then(function(response) {
                                console.log('Email sent successfully!', response);
                                
                                // Show success message using the permanent container
                                const successMessage = document.getElementById('successMessage');
                                successMessage.style.display = 'block';
                                
                                // Reset form
                                document.getElementById('subject').value = '';
                                document.getElementById('message').value = '';
                                
                                // Reset button state
                                sendMessageBtn.querySelector('.btn-text').style.display = 'inline-block';
                                sendMessageBtn.querySelector('.loading-icon').style.display = 'none';
                                sendMessageBtn.disabled = false;
                                
                                // Auto-hide the success message after 5 seconds
                                setTimeout(function() {
                                    successMessage.style.display = 'none';
                                }, 5000);
                            })
                            .catch(function(error) {
                                console.error('Email failed to send:', error);
                                
                                // Show error message using the permanent container
                                const errorMessage = document.getElementById('errorMessage');
                                const errorText = document.getElementById('errorText');
                                errorText.textContent = 'There was an error sending your message: ' + 
                                                      (error.text || error.message || JSON.stringify(error));
                                errorMessage.style.display = 'block';
                                
                                // Reset button state
                                sendMessageBtn.querySelector('.btn-text').style.display = 'inline-block';
                                sendMessageBtn.querySelector('.loading-icon').style.display = 'none';
                                sendMessageBtn.disabled = false;
                                
                                // Auto-hide the error message after 10 seconds
                                setTimeout(function() {
                                    errorMessage.style.display = 'none';
                                }, 10000);
                            });
                    } catch (error) {
                        console.error("Error in send message function:", error);
                        alert("An error occurred. Please check the console for details.");
                        
                        // Reset button state
                        sendMessageBtn.querySelector('.btn-text').style.display = 'inline-block';
                        sendMessageBtn.querySelector('.loading-icon').style.display = 'none';
                        sendMessageBtn.disabled = false;
                    }
                });
            } else {
                console.error("Form elements not found! Contact form may not work correctly.");
            }
            
            // Auto-hide alerts after 5 seconds
            if (alerts.length > 0) {
                setTimeout(function() {
                    alerts.forEach(alert => {
                        alert.style.opacity = '0';
                        alert.style.transition = 'opacity 0.5s ease';
                        setTimeout(() => {
                            alert.style.display = 'none';
                        }, 500);
                    });
                }, 5000);
            }
            
            // For the test email button
            const testEmailBtn = document.getElementById('testEmailBtn');
            const testResults = document.getElementById('testResults');
            
            console.log("Test button exists:", !!testEmailBtn); // Debug log
            
            if (testEmailBtn) {
                testEmailBtn.addEventListener('click', function() {
                    console.log("Test button clicked!"); // Debug log
                    testEmailBtn.disabled = true;
                    testEmailBtn.textContent = 'Testing...';
                    testResults.style.display = 'block';
                    testResults.innerHTML = 'Running test...<br>';
                    
                    // Test step 1: Verify EmailJS is loaded
                    testResults.innerHTML += '<b>Step 1:</b> Checking if EmailJS is loaded... ';
                    if (typeof emailjs !== 'undefined') {
                        testResults.innerHTML += '<span style="color: green;">SUCCESS</span><br>';
                    } else {
                        testResults.innerHTML += '<span style="color: red;">FAILED (EmailJS not loaded)</span><br>';
                        testEmailBtn.disabled = false;
                        testEmailBtn.textContent = 'Test Email Service';
                        return;
                    }
                    
                    // Test step 2: Send a test email
                    testResults.innerHTML += '<b>Step 2:</b> Sending test email... ';
                    
                    const testParams = {
                        from_name: 'Test User',
                        reply_to: 'test@example.com',
                        subject: 'Test Email',
                        message: 'This is a test email from your website to verify EmailJS is working correctly.',
                        to_email: 'quinonesjames650@gmail.com',
                        recipient_email: 'quinonesjames650@gmail.com'
                    };
                    
                    emailjs.send('service_46f9sur', 'template_1lgbrhn', testParams)
                        .then(function(response) {
                            testResults.innerHTML += '<span style="color: green;">SUCCESS</span><br>';
                            testResults.innerHTML += '<b>Response:</b> Status ' + response.status + ', Text: ' + response.text + '<br>';
                            testResults.innerHTML += '<b>Next steps:</b><br>1. Check your email inbox (and spam folder)<br>2. Verify your EmailJS dashboard for sent messages<br>';
                            testEmailBtn.disabled = false;
                            testEmailBtn.textContent = 'Test Email Service';
                        })
                        .catch(function(error) {
                            testResults.innerHTML += '<span style="color: red;">FAILED</span><br>';
                            testResults.innerHTML += '<b>Error:</b> ' + (error.text || error.message || JSON.stringify(error)) + '<br>';
                            testResults.innerHTML += '<b>Check:</b><br>1. Your EmailJS API keys<br>2. Template ID<br>3. Service ID<br>';
                            
                            // Check template parameters
                            testResults.innerHTML += '<b>Template review:</b><br>Your template should include these variables:<br>{{from_name}}, {{reply_to}}, {{subject}}, {{message}}<br>';
                            
                            testEmailBtn.disabled = false;
                            testEmailBtn.textContent = 'Test Email Service';
                        });
                });
            } else {
                console.error("Test button not found in the DOM!");
            }
        });
    </script>
</body>
</html> 