<?php
// Include the database connection file
require_once __DIR__ . '/../../../connect.php';

define('LOW_STOCK_THRESHOLD', 15); // Define Low Stock threshold

// Now you can use $conn (or your connection variable) to interact with the database
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organic Products</title>
    <link rel="stylesheet" href="../css/organic.css">
    <style>
        /* Custom Modal Styles */
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
            padding: 20px;
            text-align: center;
        }
        
        .modal-header {
            border-bottom: 1px solid #eeeeee;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        
        .modal-title {
            font-size: 1.2em;
            font-weight: 600;
            margin: 0;
            color: #333333;
        }
        
        .modal-content {
            margin-bottom: 20px;
            font-size: 1em;
            color: #4a4a4a;
        }
        
        .modal-btn {
            background-color: #2ecc71;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 20px;
            font-size: 0.9em;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .modal-btn:hover {
            background-color: #27ae60;
        }
    </style>
</head>
<body>
    <header>
        <a href="/fresh1/user-dashboard/user.php" class="nav-button">Back to Home</a>
        <h1 class="category-title">Organic Products</h1>
        <a href="/fresh1/user-dashboard/cart/cart.php" class="nav-button">Go to Cart</a>
    </header>

    <main class="product-grid">
        <?php
        // Fetch organic products from the database
        $sql = "SELECT id, name, description, price, image_path, stock, status FROM products WHERE category = 'Organic'";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            // Loop through organic products and display them
            while ($organic = $result->fetch_assoc()) {
                $isOutOfStock = ($organic['status'] === 'Out of Stock' || $organic['stock'] <= 0);
                
                // Determine if product should be displayed as "Low Stock"
                $isEffectivelyLowStock = !$isOutOfStock &&
                                         ($organic['status'] === 'Low Stock' ||
                                          ($organic['stock'] > 0 && $organic['stock'] <= LOW_STOCK_THRESHOLD));

                echo "<div class='product-card' data-product-id='" . htmlspecialchars($organic['id']) . "'>";
                // Assuming image_path is relative to a base images directory, e.g., /fresh1/images/
                // Adjust the base path as per your actual image storage structure.
                $imagePath = '/fresh1/' . htmlspecialchars($organic['image_path']);
                echo "<img src='" . $imagePath . "' alt='" . htmlspecialchars($organic['name']) . "'>";
                echo "<h3>" . htmlspecialchars($organic['name']) . "</h3>";
                echo "<p class='description'>" . htmlspecialchars($organic['description']) . "</p>";

                // Display Status and Stock
                echo "<div class='product-status-info'>";
                if ($isOutOfStock) {
                    echo "<span class='status-badge status-out-of-stock'>Out of Stock</span>";
                } elseif ($isEffectivelyLowStock) {
                    echo "<span class='status-badge status-low-stock'>Low Stock</span>";
                    echo "<span class='stock-left'>(" . htmlspecialchars($organic['stock']) . " left)</span>";
                } else { // In Stock (and not Out of Stock or Low Stock)
                    echo "<span class='status-badge status-in-stock'>In Stock</span>";
                    echo "<span class='stock-left'>(" . htmlspecialchars($organic['stock']) . " available)</span>";
                }
                echo "</div>";

                echo "<p class='price' data-price='" . htmlspecialchars($organic['price']) . "'>₱" . number_format($organic['price'], 2) . "</p>";
                
                echo "<div class='quantity-selector'" . ($isOutOfStock ? " style='display:none;'" : "") . ">";
                echo "<button class='quantity-btn minus-btn'>-</button>";
                echo "<span class='quantity-value'>1</span>";
                echo "<button class='quantity-btn plus-btn'>+</button>";
                echo "</div>";
                echo "<p class='total-price'" . ($isOutOfStock ? " style='display:none;'" : "") . ">Total: ₱" . number_format($organic['price'], 2) . "</p>";
                echo "<button class='add-to-cart-btn'" . ($isOutOfStock ? " disabled" : "") . ">" . ($isOutOfStock ? "Out of Stock" : "Add to Cart") . "</button>";
                echo "</div>";
            }
        } else {
            echo "<p style='text-align:center; grid-column: 1 / -1;'>No organic products found in this category.</p>";
        }
        // $conn->close(); // Consider closing connection at the end of script execution if not handled by connect.php
        ?>
    </main>

    <!-- Custom Modal -->
    <div class="modal-overlay" id="customModal">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title">Message</h3>
            </div>
            <div class="modal-content" id="modalMessage">
                <!-- Message will be inserted here -->
            </div>
            <button class="modal-btn" id="modalCloseBtn">OK</button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Modal functionality
        const modal = document.getElementById('customModal');
        const modalMessage = document.getElementById('modalMessage');
        const modalCloseBtn = document.getElementById('modalCloseBtn');
        
        // Function to show modal with message
        function showModal(message) {
            modalMessage.textContent = message;
            modal.style.display = 'flex';
        }
        
        // Close modal when OK button is clicked
        modalCloseBtn.addEventListener('click', () => {
            modal.style.display = 'none';
        });
        
        // Close modal when clicking outside of it
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });

        document.querySelectorAll('.product-card').forEach(card => {
            const minusBtn = card.querySelector('.minus-btn');
            const plusBtn = card.querySelector('.plus-btn');
            const quantityValueEl = card.querySelector('.quantity-value');
            const totalPriceEl = card.querySelector('.total-price');
            const pricePerItem = parseFloat(card.querySelector('.price')?.dataset.price);
            const addToCartBtn = card.querySelector('.add-to-cart-btn');
            const productId = card.dataset.productId;

            // Only add event listeners if elements exist (i.e., not out of stock)
            if (minusBtn && plusBtn && quantityValueEl && totalPriceEl && addToCartBtn && !addToCartBtn.disabled) {
                minusBtn.addEventListener('click', () => {
                    let quantity = parseInt(quantityValueEl.textContent);
                    if (quantity > 1) {
                        quantity--;
                        quantityValueEl.textContent = quantity;
                        updateTotalPrice(quantity);
                    }
                });

                plusBtn.addEventListener('click', () => {
                    let quantity = parseInt(quantityValueEl.textContent);
                    quantity++;
                    quantityValueEl.textContent = quantity;
                    updateTotalPrice(quantity);
                });

                function updateTotalPrice(quantity) {
                    const newTotal = pricePerItem * quantity;
                    totalPriceEl.textContent = 'Total: ₱' + newTotal.toFixed(2);
                }

                addToCartBtn.addEventListener('click', () => {
                    const quantity = parseInt(quantityValueEl.textContent);
                    console.log(`Adding to cart: Product ID ${productId}, Quantity ${quantity}, Price ${pricePerItem}`);

                    fetch('/fresh1/user-dashboard/cart/add_to_cart.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            product_id: productId,
                            quantity: quantity
                        }),
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Add to cart response:', data);
                        if(data.success) {
                            showModal(data.message || 'Item added to cart!');
                            // Optionally, update a cart icon/count on the page
                        } else {
                            showModal(data.message || 'Cannot add more of this item to cart.');
                        }
                    })
                    .catch((error) => {
                        console.error('Error adding to cart:', error);
                        showModal('Error adding item to cart.');
                    });
                });
            }
        });
    });
    </script>

</body>
</html>
