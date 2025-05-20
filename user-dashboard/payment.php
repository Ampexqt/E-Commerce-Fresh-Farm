<?php
// Start session and include database connection
session_start();
require_once '../connect.php';

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

// Get user's default address
$default_address = null;
try {
    $stmt = $conn->prepare("SELECT * FROM delivery_addresses WHERE user_id = ? AND is_default = 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $default_address = $stmt->get_result()->fetch_assoc();
    
    // If no default address, get the first address
    if (!$default_address) {
        $stmt = $conn->prepare("SELECT * FROM delivery_addresses WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $default_address = $stmt->get_result()->fetch_assoc();
    }
} catch (Exception $e) {
    error_log("Error getting default address: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Methods - FarmFresh</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/user.css">
    <link rel="stylesheet" href="../css/payment.css">
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
            <h3><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h3>
            <p><?php echo $user['email']; ?></p>
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
            <li><a href="profile.php"><i class="fa-solid fa-user"></i> Profile</a></li>
            <li><a href="payment.php" class="active"><i class="fa-solid fa-credit-card"></i> Payment Methods</a></li>
            <li><a href="address.php"><i class="fa-solid fa-location-dot"></i> Delivery Address</a></li>
            <li><a href="favorite.php"><i class="fa-solid fa-heart"></i> Favorites</a></li>
            <li><a href="settings.php"><i class="fa-solid fa-gear"></i> Account Settings</a></li>
            <li><a href="contact.php"><i class="fa-solid fa-phone"></i> Contact Us</a></li>
            <li><a href="#" onclick="showLogoutModal(); return false;" class="logout-link">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="back-btn-container">
            <!-- Replace the existing back button with this -->
<a href="user.php" class="back-button">
    <i class="fa-solid fa-chevron-left"></i>
    
</a>
        </div>

        <div class="payment-methods-container">
            <h2>Your Linked Payment Methods</h2>
            
            <div class="payment-methods-list">
                <?php
                // Fetch payment methods from database
                $stmt = $conn->prepare("SELECT * FROM payment_methods WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    while ($payment = $result->fetch_assoc()) {
                        ?>
                        <div class="payment-method-card">
                            <div class="payment-method-info">
                                <?php
                                $imagePath = '';
                                switch($payment['type']) {
                                    case 'credit-card':
                                        $imagePath = '../assets/credit.png';
                                        break;
                                    case 'gcash':
                                        $imagePath = '../assets/gcash.png';
                                        break;
                                    case 'maya':
                                        $imagePath = '../assets/maya.png';
                                        break;
                                    case 'cod':
                                        $imagePath = '../assets/cod.png';
                                        break;
                                }
                                ?>
                                <img src="<?php echo htmlspecialchars($imagePath); ?>"
                                     alt="<?php echo htmlspecialchars($payment['type']); ?>" 
                                     class="payment-logo"
                                     onerror="this.src='../assets/default-payment.png'">
                                <div class="payment-details">
                                    <h3><?php echo htmlspecialchars($payment['type']); ?></h3>
                                    <p class="account-number">Account: <?php echo htmlspecialchars($payment['masked_number']); ?></p>
                                    <?php if ($payment['is_default']) { ?>
                                        <span class="default-badge">Default</span>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="payment-actions">
                                <?php if (!$payment['is_default']) { ?>
                                    <button class="set-default-btn" onclick="showSetDefaultModal(<?php echo $payment['id']; ?>)">
                                        Set as Default
                                    </button>
                                <?php } ?>
                                <button class="remove-btn" onclick="showDeleteModal(<?php echo $payment['id']; ?>)">Remove</button>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    ?>
                    <div class="no-payment-methods">
                        <p>No payment methods linked yet</p>
                    </div>
                    <?php
                }
                ?>

                <!-- Add New Payment Method Button -->
                <?php
$paymentCount = $result->num_rows;
if ($paymentCount < 3) {
    ?>
    <div class="add-payment-method" onclick="showPaymentModal()">
        <div class="add-payment-link">
            <i class="fas fa-plus"></i>
            Link New Payment Method
        </div>
    </div>
    <?php
} else {
    ?>
    <div class="max-payment-methods">
        <p style="color: #fff;">Maximum number of payment methods (3) reached</p>
    </div>
    <?php
}
?>
            </div>
        </div>
    </main>

    <!-- Payment Modal -->
    <div id="paymentModal" class="payment-modal-overlay">
        <div class="payment-modal">
            <div class="payment-modal-header">
                <h2>Link New Payment Method</h2>
                <button class="close-modal" onclick="hidePaymentModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="payment-modal-content">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

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

    <!-- Set Default Modal -->
<div class="action-modal-overlay" id="setDefaultModal">
    <div class="action-modal">
        <h2>Set as Default Payment Method</h2>
        <p>Do you want to set this as your default payment method?</p>
        <div class="action-modal-buttons">
            <button class="action-btn cancel" onclick="hideActionModal('setDefaultModal')">Cancel</button>
            <button class="action-btn confirm" onclick="confirmSetDefault()" id="confirmDefaultBtn">
                <span class="btn-text">Set as Default</span>
                <span class="loading-spinner" style="display: none;">
                    <i class="fas fa-spinner fa-spin"></i>
                </span>
            </button>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="action-modal-overlay" id="deleteModal">
    <div class="action-modal">
        <h2>Remove Payment Method</h2>
        <p>Are you sure you want to remove this payment method?</p>
        <div class="action-modal-buttons">
            <button class="action-btn cancel" onclick="hideActionModal('deleteModal')">Cancel</button>
            <button class="action-btn delete" onclick="confirmDelete()" id="confirmDeleteBtn">
                <span class="btn-text">Remove</span>
                <span class="loading-spinner" style="display: none;">
                    <i class="fas fa-spinner fa-spin"></i>
                </span>
            </button>
        </div>
    </div>
</div>

    <script>
            // Payment Modal Functions
function showPaymentModal() {
    const modal = document.getElementById('paymentModal');
    if (!modal) return;
    
    // Count existing payment methods
    const paymentMethods = document.querySelectorAll('.payment-method-card');
    if (paymentMethods.length >= 3) {
        alert('You can only add up to 3 payment methods');
        return;
    }
    
    // Show the modal
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Initialize payment options
    showPaymentOptions();
}

function hidePaymentModal() {
    const modal = document.getElementById('paymentModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

function showPaymentOptions() {
    const modalContent = document.querySelector('.payment-modal-content');
    if (modalContent) {
        modalContent.innerHTML = `
            <div class="payment-options">
                <div class="payment-option" onclick="selectPaymentMethod('e-wallet')">
                    <div class="payment-option-inner">
                        <img src="../assets/E-wallet.png" alt="E-Wallet" class="payment-logo">
                        <span>E-Wallet</span>
                    </div>
                </div>
                <div class="payment-option" onclick="selectPaymentMethod('credit-card')">
                    <div class="payment-option-inner">
                        <img src="../assets/credit.png" alt="Credit/Debit Card">
                        <span>Credit/Debit Card</span>
                    </div>
                </div>
                <div class="payment-option" onclick="selectPaymentMethod('cod')">
                    <div class="payment-option-inner">
                        <img src="../assets/cod.png" alt="Cash on Delivery" class="payment-logo">
                        <span>Cash on Delivery</span>
                    </div>
                </div>
            </div>
        `;
    }
}

function selectPaymentMethod(method) {
    const modalContent = document.querySelector('.payment-modal-content');
    let formHtml = '';

    switch(method) {
        case 'e-wallet':
            formHtml = `
                <form class="payment-form" onsubmit="handlePaymentSubmit(event, 'e-wallet')">
                    <div class="form-group">
                        <label>E-Wallet Type</label>
                        <select name="wallet_type" required onchange="updateMobileNumberLabel(this)">
                            <option value="">Select E-Wallet</option>
                            <option value="gcash">GCash</option>
                            <option value="maya">Maya</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label id="mobileNumberLabel">Mobile Number</label>
                        <input type="tel" 
                               name="account_number"
                               pattern="09[0-9]{9}"
                               minlength="11"
                               maxlength="11"
                               placeholder="09XX XXX XXXX"
                               oninput="this.value = this.value.replace(/[^0-9]/g, '').substring(0, 11)"
                               required>
                        <small class="input-hint">Enter 11-digit mobile number starting with 09</small>
                    </div>
                    <div class="form-action-buttons">
                        <button type="button" class="cancel-btn" onclick="showPaymentOptions()">Cancel</button>
                        <button type="submit" class="link-payment-btn">
                            <span class="btn-text">Link Payment Method</span>
                            <div class="loading-spinner" style="display: none;">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </button>
                    </div>
                </form>`;
            break;

        case 'credit-card':
            formHtml = `
                <form class="payment-form" onsubmit="handlePaymentSubmit(event, 'credit-card')">
                    <div class="form-group">
                        <label>Card Number</label>
                        <input type="text" 
                               name="card_number"
                               pattern="[0-9]{16}"
                               minlength="16"
                               maxlength="16"
                               placeholder="XXXX XXXX XXXX XXXX"
                               oninput="this.value = this.value.replace(/[^0-9]/g, '').substring(0, 16)"
                               required>
                        <small class="input-hint">Enter 16-digit card number without spaces</small>
                    </div>
                    <div class="form-group">
                        <label>Card Type</label>
                        <select name="card_type" required>
                            <option value="">Select card type</option>
                            <option value="visa">Visa</option>
                            <option value="mastercard">Mastercard</option>
                        </select>
                    </div>
                    <div class="form-action-buttons">
                        <button type="button" class="cancel-btn" onclick="showPaymentOptions()">Cancel</button>
                        <button type="submit" class="link-payment-btn">
                            <span class="btn-text">Link Payment Method</span>
                            <div class="loading-spinner" style="display: none;">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                        </button>
                    </div>
                </form>`;
            break;

        case 'cod':
            // Get default address from PHP
            const defaultAddress = <?php echo $default_address ? json_encode($default_address) : 'null'; ?>;
            
            if (!defaultAddress) {
                formHtml = `
                    <div class="no-address-warning">
                        <i class="fas fa-exclamation-circle"></i>
                        <p>Please add a delivery address first</p>
                        <a href="address.php" class="add-address-link">Add Address</a>
                    </div>`;
            } else {
                // Format address properly
                const addressValue = [
                    defaultAddress.recipient_name,
                    defaultAddress.street_address,
                    `${defaultAddress.city}, ${defaultAddress.region}`,
                    `Philippines ${defaultAddress.postal_code}`
                ].filter(Boolean).join('\n');

                // Format phone number
                let contactNumber = defaultAddress.phone_number;
                if (!contactNumber.startsWith('+63')) {
                    contactNumber = '+63' + contactNumber.replace(/^0+/, '');
                }

                formHtml = `
                    <form class="payment-form" id="codPaymentForm" onsubmit="handlePaymentSubmit(event, 'cod')">
                        <div class="form-group">
                            <label>Delivery Address</label>
                            <textarea name="delivery_address" rows="4" required 
                                    readonly
                                    style="white-space: pre-line;">${addressValue}</textarea>
                            <small class="input-hint">This is your default delivery address</small>
                        </div>
                        <div class="form-group">
                            <label>Contact Number</label>
                            <input type="tel" 
                                   name="contact_number"
                                   value="${contactNumber}"
                                   readonly
                                   required>
                            <small class="input-hint">This is your registered contact number</small>
                        </div>
                        <input type="hidden" name="payment_type" value="cod">
                        <input type="hidden" name="address_id" value="${defaultAddress.id}">
                        <div class="form-action-buttons">
                            <button type="button" class="cancel-btn" onclick="showPaymentOptions()">Cancel</button>
                            <button type="submit" class="link-payment-btn">
                                <span class="btn-text">Set as Payment Method</span>
                                <div class="loading-spinner" style="display: none;">
                                    <i class="fas fa-spinner fa-spin"></i>
                                </div>
                            </button>
                        </div>
                    </form>`;
            }
            break;
    }

    modalContent.innerHTML = formHtml;
}

// Add this new function for updating mobile number label
function updateMobileNumberLabel(select) {
    const label = document.getElementById('mobileNumberLabel');
    if (label) {
        label.textContent = select.value.charAt(0).toUpperCase() + select.value.slice(1) + ' Mobile Number';
    }
}

function handlePaymentSubmit(event, method) {
    event.preventDefault();
    
    const form = event.target;
    const submitBtn = form.querySelector('.link-payment-btn');
    const btnText = submitBtn.querySelector('.btn-text');
    const loadingSpinner = submitBtn.querySelector('.loading-spinner');
    
    // Show loading state
    submitBtn.disabled = true;
    btnText.style.display = 'none';
    loadingSpinner.style.display = 'inline-block';

    // Get form data
    const formData = new FormData(form);
    let jsonData = {};

    try {
        if (method === 'e-wallet') {
            const walletType = formData.get('wallet_type');
            const accountNumber = formData.get('account_number');
            
            if (!walletType) {
                throw new Error('Please select an E-Wallet type');
            }
            
            if (!accountNumber || !accountNumber.match(/^09[0-9]{9}$/)) {
                throw new Error('Please enter a valid mobile number starting with 09');
            }
            
            jsonData = {
                method: walletType,
                account_number: accountNumber
            };
        } else if (method === 'credit-card') {
            jsonData = {
                method: 'credit-card',
                card_number: formData.get('card_number'),
                card_type: formData.get('card_type')
            };
        } else if (method === 'cod') {
            const addressId = formData.get('address_id');
            const deliveryAddress = formData.get('delivery_address');
            const contactNumber = formData.get('contact_number');

            jsonData = {
                method: 'cod',
                delivery_address: deliveryAddress,
                contact_number: contactNumber,
                address_id: addressId
            };
        }

        // Validate that we have all required fields
        if (Object.values(jsonData).some(value => !value)) {
            throw new Error('All fields are required');
        }

        fetch('add_payment_method.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(jsonData)
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.message || 'Server error occurred');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                throw new Error(data.message || 'Failed to add payment method');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert(error.message || 'An error occurred while adding the payment method');
            // Reset button state
            submitBtn.disabled = false;
            btnText.style.display = 'inline-block';
            loadingSpinner.style.display = 'none';
        });
    } catch (error) {
        console.error('Error:', error);
        alert(error.message || 'Please fill in all required fields');
        // Reset button state
        submitBtn.disabled = false;
        btnText.style.display = 'inline-block';
        loadingSpinner.style.display = 'none';
    }
}

// Update the payment method display in the list
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethodsList = document.querySelector('.payment-methods-list');
    if (paymentMethodsList) {
        const paymentMethods = document.querySelectorAll('.payment-method-card');
        paymentMethods.forEach(card => {
            const typeSpan = card.querySelector('.payment-type');
            if (typeSpan) {
                const type = typeSpan.textContent.toLowerCase();
                if (type === 'gcash' || type === 'maya') {
                    typeSpan.innerHTML = `E-Wallet <span class="payment-type-badge e-wallet">${type.toUpperCase()}</span>`;
                } else if (type === 'cod') {
                    typeSpan.innerHTML = `Cash on Delivery <span class="payment-type-badge cod">COD</span>`;
                }
            }
        });
    }
});

// Add event listener when document loads
document.addEventListener('DOMContentLoaded', function() {
    // Add click event to payment method div
    const addPaymentMethod = document.querySelector('.add-payment-method');
    if (addPaymentMethod) {
        addPaymentMethod.addEventListener('click', function(e) {
            e.preventDefault();
            showPaymentModal();
        });
    }

    // Close modal when clicking outside
    const modal = document.getElementById('paymentModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                hidePaymentModal();
            }
        });
    }

    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hidePaymentModal();
        }
    });
});

// Payment Method Management Functions
let currentPaymentId = null;

function showSetDefaultModal(id) {
    currentPaymentId = id;
    const modal = document.getElementById('setDefaultModal');
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function showDeleteModal(id) {
    currentPaymentId = id;
    const modal = document.getElementById('deleteModal');
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function hideActionModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
    currentPaymentId = null;
}

function confirmSetDefault() {
    if (!currentPaymentId) return;
    
    const button = document.getElementById('confirmDefaultBtn');
    const btnText = button.querySelector('.btn-text');
    const loadingSpinner = button.querySelector('.loading-spinner');
    
    button.disabled = true;
    btnText.style.display = 'none';
    loadingSpinner.style.display = 'inline';

    fetch('manage_payment_method.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'set_default',
            payment_id: currentPaymentId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            throw new Error(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(error.message || 'An error occurred');
        button.disabled = false;
        btnText.style.display = 'inline';
        loadingSpinner.style.display = 'none';
        hideActionModal('setDefaultModal');
    });
}

// Replace the existing confirmDelete function
function confirmDelete() {
    if (!currentPaymentId) return;
    
    const button = document.getElementById('confirmDeleteBtn');
    const btnText = button.querySelector('.btn-text');
    const loadingSpinner = button.querySelector('.loading-spinner');
    
    // Show loading state
    button.disabled = true;
    btnText.style.display = 'none';
    loadingSpinner.style.display = 'inline';

    fetch('manage_payment_method.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            action: 'remove',
            payment_id: currentPaymentId
        })
    })
    .then(async response => {
        const text = await response.text();
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + text);
        }
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error('Server error: ' + text);
        }
    })
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            throw new Error(data.message || 'Failed to remove payment method');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(error.message || 'An error occurred while removing the payment method');
        // Reset button state
        button.disabled = false;
        btnText.style.display = 'inline';
        loadingSpinner.style.display = 'none';
        hideActionModal('deleteModal');
    });
}

// Add modal click outside listeners
document.addEventListener('DOMContentLoaded', function() {
    const modals = document.querySelectorAll('.action-modal-overlay');
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                hideActionModal(this.id);
            }
        });
    });

    // Close modals on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideActionModal('setDefaultModal');
            hideActionModal('deleteModal');
        }
    });
});

// Sidebar toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    const toggleIcon = sidebarToggle?.querySelector('i');

    // Function to toggle sidebar
    function toggleSidebar() {
        sidebar?.classList.toggle('collapsed');
        mainContent?.classList.toggle('expanded');
        
        // Toggle the icon between bars and times
        if (toggleIcon) {
            if (sidebar?.classList.contains('collapsed')) {
                toggleIcon.classList.replace('fa-bars', 'fa-times');
            } else {
                toggleIcon.classList.replace('fa-times', 'fa-bars');
            }
        }
        
        // Save sidebar state to localStorage
        localStorage.setItem('sidebarCollapsed', sidebar?.classList.contains('collapsed'));
    }

    // Initialize sidebar state
    function initializeSidebar() {
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        
        if (sidebarCollapsed) {
            sidebar?.classList.add('collapsed');
            mainContent?.classList.add('expanded');
            if (toggleIcon) {
                toggleIcon.classList.replace('fa-bars', 'fa-times');
            }
        }

        // Handle initial state for mobile
        if (window.innerWidth <= 768) {
            sidebar?.classList.add('collapsed');
            mainContent?.classList.add('expanded');
            if (toggleIcon) {
                toggleIcon.classList.replace('fa-bars', 'fa-times');
            }
        }
    }

    // Event listeners
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth <= 768) {
            sidebar?.classList.add('collapsed');
            mainContent?.classList.add('expanded');
            if (toggleIcon) {
                toggleIcon.classList.replace('fa-bars', 'fa-times');
            }
        }
    });

    // Initialize sidebar on load
    initializeSidebar();
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
    const btnText = button.querySelector('.btn-text');
    const loadingSpinner = button.querySelector('.loading-spinner');
    
    if (btnText) btnText.style.display = 'none';
    if (loadingSpinner) loadingSpinner.style.display = 'inline';

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
        if (btnText) btnText.style.display = 'inline';
        if (loadingSpinner) loadingSpinner.style.display = 'none';
    });
}

// Close modal if clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const logoutModal = document.getElementById('logoutModal');
    if (logoutModal) {
        logoutModal.addEventListener('click', function(e) {
            if (e.target === this) {
                hideLogoutModal();
            }
        });
    }

    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideLogoutModal();
        }
    });
});

// Add this CSS to the page
document.head.insertAdjacentHTML('beforeend', `
<style>
.no-address-warning {
    text-align: center;
    padding: 2rem;
    color: #fff;
}

.no-address-warning i {
    font-size: 2rem;
    color: #ffc107;
    margin-bottom: 1rem;
}

.no-address-warning p {
    margin-bottom: 1rem;
}

.add-address-link {
    display: inline-block;
    padding: 0.5rem 1rem;
    background: var(--primary-color);
    color: white;
    text-decoration: none;
    border-radius: 6px;
    transition: background 0.3s ease;
}

.add-address-link:hover {
    background: var(--secondary-color);
}

.loading-spinner {
    display: none;
    position: absolute;
    align-items: center;
    justify-content: center;
}

.form-action-buttons {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.form-action-buttons button {
    flex: 1;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.8rem;
}

.link-payment-btn {
    background: var(--primary-color);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    transition: background 0.3s ease;
}

.link-payment-btn:hover:not(:disabled) {
    background: var(--secondary-color);
}

.link-payment-btn:disabled {
    background: rgba(59, 122, 87, 0.5);
    cursor: not-allowed;
}
</style>
`);
    </script>
   
</body>
</html>