<?php
require_once '../../../connect.php'; // Adjusted path to connect.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p>Please <a href='../../../login.php'>login</a> to view your cart.</p>"; // Adjusted link
    exit;
}

// Success and error messages
$message = '';
$messageType = '';

if (isset($_GET['success']) && $_GET['success'] === 'removed') {
    $message = 'Item has been successfully removed from your cart.';
    $messageType = 'success';
} elseif (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid_item':
            $message = 'Invalid item selected.';
            break;
        case 'unauthorized':
            $message = 'You are not authorized to remove this item.';
            break;
        case 'database':
            $message = 'A database error occurred. Please try again later.';
            break;
        case 'delete_failed':
            $message = 'Failed to remove the item. Please try again.';
            break;
        default:
            $message = 'An error occurred.';
    }
    $messageType = 'error';
}

$user_id = $_SESSION['user_id'];
$cart_items = [];
$grand_total = 0; 

// Fetch cart items for the user, including product stock
$sql = "SELECT c.cart_id, c.quantity, p.id as product_id, p.name, p.price, p.image_path, p.stock
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $cart_items[] = $row;
        $grand_total += $row['price'] * $row['quantity']; 
    }
    $stmt->close();
} else {
    error_log("Error preparing statement: " . $conn->error);
    echo "<p>An error occurred while fetching your cart. Please try again later.</p>";
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Shopping Cart</title>
    <link rel="stylesheet" href="../css/cart.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Delete Confirmation Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1001;
            justify-content: center;
            align-items: center;
        }
        
        .modal-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 400px;
            padding: 25px;
            text-align: center;
        }
        
        .modal-header {
            margin-bottom: 15px;
        }
        
        .modal-title {
            font-size: 1.3em;
            font-weight: 600;
            margin: 0;
            color: #2c3e50;
        }
        
        .modal-content {
            margin-bottom: 25px;
            font-size: 1.1em;
            color: #4a4a4a;
            line-height: 1.5;
        }
        
        .modal-item-name {
            font-weight: 600;
            color: #e74c3c;
        }
        
        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        .modal-btn {
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
            border: none;
        }
        
        .modal-cancel-btn {
            background-color: #ecf0f1;
            color: #2c3e50;
        }
        
        .modal-cancel-btn:hover {
            background-color: #dadedf;
        }
        
        .modal-confirm-btn {
            background-color: #e74c3c;
            color: white;
        }
        
        .modal-confirm-btn:hover {
            background-color: #c0392b;
        }
        
        /* Status message styles */
        .status-message {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
            font-weight: 500;
            animation: fadeOut 5s forwards;
            animation-delay: 3s;
        }
        
        .status-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; visibility: hidden; }
        }
    </style>
</head>
<body>
    <div class="cart-container">
        <h1><i class="fas fa-shopping-cart"></i> Your Shopping Cart</h1>
        
        <?php if (!empty($message)): ?>
            <div class="status-message status-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>
            <p class="empty-cart-message">Your cart is currently empty. <a href="../../user.php">Continue shopping!</a></p>
        <?php else: ?>
            <form id="cartForm" action="../../checkout.php" method="POST">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th class="select-all-header"><input type="checkbox" id="selectAllCheckbox"></th>
                            <th>Product</th>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Available Stock</th> 
                            <th>Total</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): ?>
                            <tr data-item-price="<?php echo htmlspecialchars($item['price']); ?>" data-item-quantity="<?php echo htmlspecialchars($item['quantity']); ?>" data-cart-id="<?php echo $item['cart_id']; ?>" data-item-name="<?php echo htmlspecialchars($item['name']); ?>">
                                <td>
                                    <input type="checkbox" name="selected_items[<?php echo $item['cart_id']; ?>]" value="<?php echo $item['quantity']; ?>" class="item-checkbox">
                                </td>
                                <td class="product-image-cell">
                                    <?php
                                    $image_url = '../../../' . htmlspecialchars($item['image_path']);
                                    if (empty($item['image_path']) || !file_exists($_SERVER['DOCUMENT_ROOT'] . '/fresh1/' . $item['image_path'] )) {
                                        $image_url = '../../../assets/images/placeholder.jpg';
                                    }
                                    ?>
                                    <img src="<?php echo $image_url; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="product-thumbnail">
                                </td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td><?php echo htmlspecialchars($item['stock']); ?></td>
                                <td class="item-total-price">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                <td class="action-cell">
                                    <button type="button" class="remove-btn" title="Remove item" data-cart-id="<?php echo $item['cart_id']; ?>">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" style="text-align: right;"><strong>Selected Total:</strong></td>
                            <td colspan="2"><strong id="selectedTotalPriceValue">₱0.00</strong></td>
                        </tr>
                    </tfoot>
                </table>

                <div class="cart-actions">
                    <a href="../../user.php" class="continue-shopping-btn"><i class="fas fa-arrow-left"></i> Continue Shopping</a>
                    <button type="submit" id="checkoutBtn" class="checkout-btn" disabled>
                        Proceed to Checkout <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title">Remove Item</h3>
            </div>
            <div class="modal-content">
                Are you sure you want to remove <span class="modal-item-name" id="deleteItemName"></span> from your cart?
            </div>
            <div class="modal-buttons">
                <button class="modal-btn modal-cancel-btn" id="cancelDeleteBtn">Cancel</button>
                <button class="modal-btn modal-confirm-btn" id="confirmDeleteBtn">Remove</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const itemCheckboxes = document.querySelectorAll('.item-checkbox');
        const selectedTotalPriceElement = document.getElementById('selectedTotalPriceValue');
        const checkoutBtn = document.getElementById('checkoutBtn');
        const cartForm = document.getElementById('cartForm');
        
        // Delete modal elements
        const deleteModal = document.getElementById('deleteModal');
        const deleteItemName = document.getElementById('deleteItemName');
        const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        let currentCartId = null;

        console.log('Cart JS Loaded. Items:', itemCheckboxes.length, 'SelectAll:', selectAllCheckbox);
        
        // Setup delete button functionality
        document.querySelectorAll('.remove-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const row = btn.closest('tr');
                const cartId = btn.dataset.cartId;
                const itemName = row.dataset.itemName;
                
                // Set modal content and show it
                deleteItemName.textContent = itemName;
                currentCartId = cartId;
                deleteModal.style.display = 'flex';
            });
        });
        
        // Cancel delete
        cancelDeleteBtn.addEventListener('click', () => {
            deleteModal.style.display = 'none';
            currentCartId = null;
        });
        
        // Close modal when clicking outside
        deleteModal.addEventListener('click', (e) => {
            if (e.target === deleteModal) {
                deleteModal.style.display = 'none';
                currentCartId = null;
            }
        });
        
        // Confirm delete
        confirmDeleteBtn.addEventListener('click', () => {
            if (currentCartId) {
                // Redirect to remove_from_cart.php with the cart_id
                window.location.href = `remove_from_cart.php?cart_id=${currentCartId}`;
            }
        });

        function updateSelectedTotalAndButtonState() {
            let currentSelectedTotal = 0;
            let anythingSelected = false;
            console.log('Updating total...');

            itemCheckboxes.forEach((checkbox, index) => {
                if (checkbox.checked) {
                    anythingSelected = true;
                    const row = checkbox.closest('tr');
                    const priceString = row.dataset.itemPrice;
                    const quantityString = checkbox.value; // Quantity is stored in the checkbox value
                    
                    const price = parseFloat(priceString);
                    const quantity = parseInt(quantityString);

                    console.log(`Item ${index + 1}: Checked. Price: ${price}, Quantity: ${quantity}`);

                    if (!isNaN(price) && !isNaN(quantity)) {
                        currentSelectedTotal += price * quantity;
                    } else {
                        console.error(`Item ${index + 1}: Invalid price or quantity. PriceStr: ${priceString}, QtyStr: ${quantityString}`);
                    }
                }
            });
            
            console.log('Total Selected:', currentSelectedTotal, 'Anything Selected:', anythingSelected);
            selectedTotalPriceElement.textContent = '₱' + currentSelectedTotal.toFixed(2);
            checkoutBtn.disabled = !anythingSelected;
        }

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (event) => {
                console.log('Select All changed:', event.target.checked);
                itemCheckboxes.forEach(checkbox => {
                    checkbox.checked = event.target.checked;
                });
                updateSelectedTotalAndButtonState();
            });
        }

        itemCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                console.log('Item checkbox changed. ID:', checkbox.name, 'Checked:', checkbox.checked);
                if (!checkbox.checked && selectAllCheckbox) {
                    selectAllCheckbox.checked = false;
                }
                
                let allChecked = true;
                if(itemCheckboxes.length > 0){
                    itemCheckboxes.forEach(cb => {
                        if (!cb.checked) {
                            allChecked = false;
                        }
                    });
                } else {
                    allChecked = false; 
                }

                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = allChecked;
                }
                updateSelectedTotalAndButtonState();
            });
        });

        if (cartForm) {
            cartForm.addEventListener('submit', (event) => {
                console.log('Cart form submitted');
                let anythingSelected = false;
                itemCheckboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        anythingSelected = true;
                    }
                });
                if (!anythingSelected) {
                    console.log('Submission prevented: No items selected');
                    event.preventDefault();
                    alert('Please select at least one item to proceed to checkout.');
                }
            });
        }

        // Auto-hide status message after 5 seconds
        const statusMessage = document.querySelector('.status-message');
        if (statusMessage) {
            setTimeout(() => {
                statusMessage.style.display = 'none';
            }, 8000);
        }

        // Initial state update on page load
        console.log('Initial call to update total.');
        updateSelectedTotalAndButtonState();
    });
    </script>
</body>
</html>
