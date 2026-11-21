<?php
session_start();

require_once "config/database.php";

/* ==========================================
   LOGIN SECURITY
========================================== */
if (
    empty($_SESSION['user_id']) ||
    !filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) ||
    (int) $_SESSION['user_id'] < 1
) {
    $_SESSION['login_error'] = 'Please login before placing an order.';
    header("Location: auth/login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$userName = $_SESSION['user_name']
    ?? $_SESSION['username']
    ?? $_SESSION['name']
    ?? 'Customer';

/* ==========================================
   CART CHECK
========================================== */
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

/* ==========================================
   CSRF TOKEN
========================================== */
if (empty($_SESSION['checkout_csrf_token'])) {
    $_SESSION['checkout_csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['checkout_csrf_token'];

$account_link = file_exists(__DIR__ . "/my-account.php")
    ? "my-account.php"
    : "index.php";

$is_logged_in = !empty($_SESSION['user_id']);

$wishlistCount = 0;
try {
    $wStmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = :uid");
    $wStmt->execute([':uid' => $user_id]);
    $wishlistCount = (int)$wStmt->fetchColumn();
} catch (Exception $e) {
    $wishlistCount = isset($_SESSION['wishlist']) ? count($_SESSION['wishlist']) : 0;
}

/* ==========================================
   DELIVERY SETTINGS
========================================== */
$chattogram_delivery = 70;
$outside_delivery = 120;

$cities = [
    "Chattogram",
    "Dhaka",
    "Cox's Bazar",
    "Cumilla",
    "Feni",
    "Noakhali",
    "Sylhet",
    "Rajshahi",
    "Khulna",
    "Barishal",
    "Rangpur",
    "Mymensingh"
];

$allowed_payment_methods = [
    'cash_on_delivery',
    'bkash',
    'nagad',
    'rocket'
];

$delivery = $outside_delivery;
$subtotal = 0;
$total = 0;
$cartCount = 0;
$order_error = '';
$display_items = [];
$quantities = [];

/* ==========================================
   VERIFY CART ITEMS FOR DISPLAY
========================================== */
try {
    $display_stmt = $pdo->prepare(
        "SELECT id, name, price, stock
         FROM products
         WHERE id = :id"
    );

    foreach ($_SESSION['cart'] as $item) {
        $product_id = filter_var(
            $item['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $quantity = filter_var(
            $item['quantity'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $product_id === false ||
            $product_id === null ||
            $product_id < 1 ||
            $quantity === false ||
            $quantity === null ||
            $quantity < 1 ||
            $quantity > 100
        ) {
            $order_error = 'Your cart contains invalid product information. Please review your cart.';
            break;
        }

        $display_stmt->execute(['id' => $product_id]);
        $product = $display_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product || !is_numeric($product['price'])) {
            $order_error = 'One of the products in your cart is no longer available. Please review your cart.';
            break;
        }

        if (!isset($quantities[$product_id])) {
            $quantities[$product_id] = 0;
        }

        $quantities[$product_id] += $quantity;

        if ($quantities[$product_id] > 100) {
            $order_error = 'The quantity of a product cannot exceed 100.';
            break;
        }

        $price = (float) $product['price'];
        $line_subtotal = round($price * $quantity, 2);

        $subtotal += $line_subtotal;
        $cartCount += $quantity;

        $display_items[] = [
            'id' => (int) $product['id'],
            'name' => $product['name'],
            'price' => $price,
            'quantity' => $quantity,
            'image' => $item['image'] ?? '',
            'subtotal' => $line_subtotal
        ];
    }
} catch (Throwable $e) {
    error_log('Checkout cart verification failed: ' . $e->getMessage());
    $order_error = 'We could not load your cart right now. Please try again.';
}

$subtotal = round($subtotal, 2);
$total = round($subtotal + $delivery, 2);

/* ==========================================
   HANDLE ORDER SUBMISSION
========================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $order_error === '') {

    $customer_name = trim((string) ($_POST['customer_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $city = trim((string) ($_POST['city'] ?? ''));
    $area = trim((string) ($_POST['area'] ?? ''));
    $payment_method = (string) ($_POST['payment_method'] ?? '');
    $payment_number = trim((string) ($_POST['payment_number'] ?? ''));
    $transaction_id = trim((string) ($_POST['transaction_id'] ?? ''));
    $posted_token = $_POST['csrf_token'] ?? '';

    /* CSRF validation */
    if (
        !is_string($posted_token) ||
        !hash_equals($csrf_token, $posted_token)
    ) {
        $order_error = 'Your session has expired. Please refresh the page and try again.';
    }

    /* Customer validation */
    if ($order_error === '') {
        if (
            $customer_name === '' ||
            mb_strlen($customer_name) > 150 ||
            $phone === '' ||
            strlen($phone) > 25 ||
            !preg_match('/^[0-9+\-\s()]{8,25}$/', $phone) ||
            $address === '' ||
            mb_strlen($address) > 1000 ||
            $area === '' ||
            mb_strlen($area) > 150 ||
            !in_array($city, $cities, true)
        ) {
            $order_error = 'Please enter valid delivery information.';
        }
    }

    /* Payment method validation */
    if (
        $order_error === '' &&
        !in_array($payment_method, $allowed_payment_methods, true)
    ) {
        $order_error = 'Please select a valid payment method.';
    }

    if ($order_error === '' && $payment_method !== 'cash_on_delivery') {
        if (
            $payment_number === '' ||
            strlen($payment_number) > 30 ||
            !preg_match('/^[0-9+\-\s]{8,30}$/', $payment_number) ||
            $transaction_id === '' ||
            mb_strlen($transaction_id) > 100
        ) {
            $order_error = 'Please enter valid payment details and transaction ID.';
        }
    }

    /* Server-side delivery charge */
    $delivery = ($city === 'Chattogram')
        ? $chattogram_delivery
        : $outside_delivery;

    /* ==========================================
       SAVE ORDER IN A DATABASE TRANSACTION
    ========================================== */
    if ($order_error === '') {
        try {
            $pdo->beginTransaction();

            $verified_items = [];
            $verified_subtotal = 0;

            $product_stmt = $pdo->prepare(
                "SELECT id, name, price, stock
                 FROM products
                 WHERE id = :id
                 FOR UPDATE"
            );

            foreach ($quantities as $product_id => $quantity) {
                $product_stmt->execute(['id' => $product_id]);
                $product = $product_stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new RuntimeException(
                        'One of the products is no longer available.'
                    );
                }

                if (
                    !is_numeric($product['price']) ||
                    !is_numeric($product['stock']) ||
                    (int) $product['stock'] < $quantity
                ) {
                    throw new RuntimeException(
                        $product['name'] . ' does not have enough stock.'
                    );
                }

                $price = (float) $product['price'];
                $item_subtotal = round($price * $quantity, 2);
                $verified_subtotal += $item_subtotal;

                $verified_items[] = [
                    'id' => (int) $product['id'],
                    'name' => $product['name'],
                    'price' => $price,
                    'quantity' => $quantity,
                    'subtotal' => $item_subtotal
                ];
            }

            $verified_subtotal = round($verified_subtotal, 2);
            $verified_total = round($verified_subtotal + $delivery, 2);

            /* Create order */
            $order_stmt = $pdo->prepare(
                "INSERT INTO orders
                (
                    user_id, customer_name, phone, address, city, area,
                    payment_method, payment_number, transaction_id,
                    subtotal, delivery_charge, total_amount, order_status
                )
                VALUES
                (
                    :user_id, :customer_name, :phone, :address, :city, :area,
                    :payment_method, :payment_number, :transaction_id,
                    :subtotal, :delivery_charge, :total_amount, 'Pending'
                )"
            );

            $order_stmt->execute([
                'user_id' => $user_id,
                'customer_name' => $customer_name,
                'phone' => $phone,
                'address' => $address,
                'city' => $city,
                'area' => $area,
                'payment_method' => $payment_method,
                'payment_number' => $payment_method === 'cash_on_delivery'
                    ? null
                    : $payment_number,
                'transaction_id' => $payment_method === 'cash_on_delivery'
                    ? null
                    : $transaction_id,
                'subtotal' => $verified_subtotal,
                'delivery_charge' => $delivery,
                'total_amount' => $verified_total
            ]);

            $order_id = (int) $pdo->lastInsertId();

            /* Save order items */
            $item_stmt = $pdo->prepare(
                "INSERT INTO order_items
                (
                    order_id, product_id, product_name,
                    price, quantity, subtotal
                )
                VALUES
                (
                    :order_id, :product_id, :product_name,
                    :price, :quantity, :subtotal
                )"
            );

            /* Update stock */
            $stock_stmt = $pdo->prepare(
                "UPDATE products
                 SET stock = stock - :quantity
                 WHERE id = :id
                   AND stock >= :required_quantity"
            );

            foreach ($verified_items as $item) {
                $item_stmt->execute([
                    'order_id' => $order_id,
                    'product_id' => $item['id'],
                    'product_name' => $item['name'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal']
                ]);

                $stock_stmt->execute([
                    'quantity' => $item['quantity'],
                    'id' => $item['id'],
                    'required_quantity' => $item['quantity']
                ]);

                if ($stock_stmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Stock changed while processing your order. Please try again.'
                    );
                }
            }

            $pdo->commit();

            unset($_SESSION['cart']);
            $_SESSION['checkout_csrf_token'] = bin2hex(random_bytes(32));

            header("Location: order-success.php?order_id=" . $order_id);
            exit;

        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $order_error = $e->getMessage();

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Checkout processing failed: ' . $e->getMessage());
            $order_error = 'We could not process your order right now. Please try again.';
        }
    }
}

/* Recalculate display total for selected city after validation errors */
$selected_city = (string) ($_POST['city'] ?? '');

if (in_array($selected_city, $cities, true)) {
    $delivery = ($selected_city === 'Chattogram')
        ? $chattogram_delivery
        : $outside_delivery;
}

$total = round($subtotal + $delivery, 2);

function checkout_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Checkout - Handicraft by Sonia</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .form-group select {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #ddd0f4;
            border-radius: 9px;
            padding: 12px 14px;
            background: #fff;
            color: #444;
            font-family: "Poppins", sans-serif;
            font-size: 13px;
            outline: none;
        }
        .checkout-error {
            padding: 13px 16px;
            margin-bottom: 18px;
            border-radius: 10px;
            background: #ffe7e7;
            color: #d33030;
            font-size: 13px;
            font-weight: 500;
        }
    </style>
</head>

<body>

<header class="main-header">
    <div class="logo-area">
        <img src="assets/images/images.jpeg" alt="Handicraft by Sonia">

        <div class="logo-text">
            <h2>Handicraft</h2>
            <span>by Sonia</span>
        </div>
    </div>

    <nav class="navbar">
        <a href="index.php">Home</a>
        <a href="shop.php">Shop</a>
        <a href="categories.php">Categories</a>
        <a href="custom-order.php">Custom Order</a>
        <a href="about.php">About Us</a>
        <a href="faq.php">FAQ</a>
    </nav>

    <div class="header-actions">
        <button type="button" aria-label="Search" onclick="window.location.href='shop.php'">
            <i class="bi bi-search"></i>
        </button>

        <a href="wishlist.php" class="icon-link notification" aria-label="Wishlist">
            <i class="bi bi-heart"></i>
            <span><?php echo (int) $wishlistCount; ?></span>
        </a>

        <a href="cart.php" class="icon-link notification" aria-label="Cart">
            <i class="bi bi-cart3"></i>
            <span><?php echo (int) $cartCount; ?></span>
        </a>

        <?php if ($is_logged_in): ?>
            <a href="<?php echo checkout_escape($account_link); ?>" class="welcome-user" title="My Account">
                <i class="bi bi-person-circle"></i>
                <span>Hi, <?php echo checkout_escape($userName); ?></span>
            </a>
            <a href="auth/logout.php" class="login-btn">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        <?php else: ?>
            <a href="auth/login.php" class="login-btn">
                <i class="bi bi-person"></i>
                Login
            </a>
        <?php endif; ?>
    </div>
</header>

<section class="checkout-header">
    <span>COMPLETE YOUR ORDER</span>
    <h1>Checkout</h1>
    <p>Enter your delivery information to place your order.</p>
</section>

<section class="checkout-section">
    <div class="checkout-container">

        <div class="checkout-form-box">
            <div class="checkout-box-title">
                <i class="bi bi-person-circle"></i>
                <div>
                    <h2>Delivery Information</h2>
                    <p>Please enter your information.</p>
                </div>
            </div>

            <?php if ($order_error !== ''): ?>
                <div class="checkout-error">
                    <i class="bi bi-exclamation-circle"></i>
                    <?php echo checkout_escape($order_error); ?>
                </div>
            <?php endif; ?>

            <form action="checkout.php" method="POST" class="checkout-form">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo checkout_escape($csrf_token); ?>">

                <div class="form-group">
                    <label for="customer_name">Full Name</label>
                    <input
                        type="text"
                        id="customer_name"
                        name="customer_name"
                        maxlength="150"
                        placeholder="Enter your full name"
                        value="<?php echo checkout_escape($_POST['customer_name'] ?? ''); ?>"
                        required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        maxlength="25"
                        placeholder="01XXXXXXXXX"
                        value="<?php echo checkout_escape($_POST['phone'] ?? ''); ?>"
                        required>
                </div>

                <div class="form-group">
                    <label for="address">Delivery Address</label>
                    <textarea
                        id="address"
                        name="address"
                        rows="5"
                        maxlength="1000"
                        placeholder="Enter your complete delivery address"
                        required><?php echo checkout_escape($_POST['address'] ?? ''); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="city">City</label>
                        <select id="city" name="city" onchange="updateDeliveryCharge()" required>
                            <option value="">Select City</option>

                            <?php foreach ($cities as $city_name): ?>
                                <option
                                    value="<?php echo checkout_escape($city_name); ?>"
                                    <?php echo (($_POST['city'] ?? '') === $city_name) ? 'selected' : ''; ?>>
                                    <?php echo checkout_escape($city_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="area">Area</label>
                        <input
                            type="text"
                            id="area"
                            name="area"
                            maxlength="150"
                            placeholder="Enter your area"
                            value="<?php echo checkout_escape($_POST['area'] ?? ''); ?>"
                            required>
                    </div>
                </div>

                <div class="payment-method-box">
                    <h3>Payment Method</h3>

                    <label class="payment-option">
                        <input
                            type="radio"
                            name="payment_method"
                            value="cash_on_delivery"
                            <?php echo (($_POST['payment_method'] ?? 'cash_on_delivery') === 'cash_on_delivery') ? 'checked' : ''; ?>
                            onchange="showPaymentInfo('cod')"
                            required>
                        <span><i class="bi bi-cash-stack"></i> Cash on Delivery</span>
                    </label>

                    <label class="payment-option">
                        <input
                            type="radio"
                            name="payment_method"
                            value="bkash"
                            <?php echo (($_POST['payment_method'] ?? '') === 'bkash') ? 'checked' : ''; ?>
                            onchange="showPaymentInfo('bkash')">
                        <span><i class="bi bi-phone"></i> bKash</span>
                    </label>

                    <label class="payment-option">
                        <input
                            type="radio"
                            name="payment_method"
                            value="nagad"
                            <?php echo (($_POST['payment_method'] ?? '') === 'nagad') ? 'checked' : ''; ?>
                            onchange="showPaymentInfo('nagad')">
                        <span><i class="bi bi-phone"></i> Nagad</span>
                    </label>

                    <label class="payment-option">
                        <input
                            type="radio"
                            name="payment_method"
                            value="rocket"
                            <?php echo (($_POST['payment_method'] ?? '') === 'rocket') ? 'checked' : ''; ?>
                            onchange="showPaymentInfo('rocket')">
                        <span><i class="bi bi-phone"></i> Rocket</span>
                    </label>

                    <div id="paymentInfo" class="payment-info" style="display:none;">
                        <div class="form-group">
                            <label for="payment_number">Payment Number</label>
                            <input
                                type="tel"
                                id="payment_number"
                                name="payment_number"
                                maxlength="30"
                                placeholder="01XXXXXXXXX"
                                value="<?php echo checkout_escape($_POST['payment_number'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="transaction_id">Transaction ID</label>
                            <input
                                type="text"
                                id="transaction_id"
                                name="transaction_id"
                                maxlength="100"
                                placeholder="Enter Transaction ID"
                                value="<?php echo checkout_escape($_POST['transaction_id'] ?? ''); ?>">
                        </div>
                    </div>

                    <p>Select your preferred payment method.</p>
                </div>

                <button type="submit" class="place-order-btn">
                    <i class="bi bi-check-circle"></i>
                    Place Order
                </button>
            </form>
        </div>

        <div class="checkout-summary">
            <h2>Your Order</h2>

            <div class="checkout-products">
                <?php foreach ($display_items as $item): ?>
                    <div class="checkout-product">
                        <div class="checkout-product-image">
                            <?php if (!empty($item['image'])): ?>
                                <img
                                    src="assets/images/<?php echo checkout_escape(basename($item['image'])); ?>"
                                    alt="<?php echo checkout_escape($item['name']); ?>">
                            <?php endif; ?>
                        </div>

                        <div class="checkout-product-info">
                            <h3><?php echo checkout_escape($item['name']); ?></h3>
                            <p>Qty: <?php echo (int) $item['quantity']; ?></p>
                        </div>

                        <strong>
                            ৳<?php echo number_format($item['subtotal'], 2); ?>
                        </strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="checkout-summary-details">
                <div class="checkout-summary-row">
                    <span>Subtotal</span>
                    <strong>৳<?php echo number_format($subtotal, 2); ?></strong>
                </div>

                <div class="checkout-summary-row">
                    <span>Delivery</span>
                    <strong id="deliveryCharge">৳<?php echo number_format($delivery, 2); ?></strong>
                </div>

                <div class="checkout-divider"></div>

                <div class="checkout-total">
                    <span>Total</span>
                    <strong id="checkoutTotal">৳<?php echo number_format($total, 2); ?></strong>
                </div>
            </div>

            <div class="checkout-security">
                <i class="bi bi-shield-check"></i>
                <div>
                    <strong>Secure Checkout</strong>
                    <p>Your information is kept safe.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<footer>
    <p>© 2026 Handicraft by Sonia | All Rights Reserved By Team Triple Pixel</p>
</footer>

<script>
function showPaymentInfo(method) {
    const paymentInfo = document.getElementById("paymentInfo");
    const paymentNumber = document.getElementById("payment_number");
    const transactionId = document.getElementById("transaction_id");

    if (method === "cod") {
        paymentInfo.style.display = "none";
        paymentNumber.required = false;
        transactionId.required = false;
    } else {
        paymentInfo.style.display = "block";
        paymentNumber.required = true;
        transactionId.required = true;
    }
}

function updateDeliveryCharge() {
    const city = document.getElementById("city").value;
    const deliveryCharge = document.getElementById("deliveryCharge");
    const checkoutTotal = document.getElementById("checkoutTotal");

    const subtotal = <?php echo json_encode((float) $subtotal); ?>;
    const chattogramDelivery = <?php echo json_encode((float) $chattogram_delivery); ?>;
    const outsideDelivery = <?php echo json_encode((float) $outside_delivery); ?>;

    const delivery = city === "Chattogram"
        ? chattogramDelivery
        : outsideDelivery;

    deliveryCharge.textContent =
        "৳" + new Intl.NumberFormat("en-US", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(delivery);

    checkoutTotal.textContent =
        "৳" + new Intl.NumberFormat("en-US", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(subtotal + delivery);
}

document.addEventListener("DOMContentLoaded", function () {
    const selectedPayment = document.querySelector(
        'input[name="payment_method"]:checked'
    );

    if (selectedPayment) {
        showPaymentInfo(
            selectedPayment.value === "cash_on_delivery"
                ? "cod"
                : selectedPayment.value
        );
    }

    updateDeliveryCharge();
});
</script>

<?php include_once __DIR__ . '/chat.php'; ?>

</body>
</html>