<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/config/database.php";

/* ==========================================
   LOGIN STATUS
========================================== */

$isLoggedIn = !empty($_SESSION['user_id']);

$userName = $_SESSION['user_name']
    ?? $_SESSION['username']
    ?? $_SESSION['name']
    ?? 'Customer';


/* ==========================================
   INITIALIZE CART
========================================== */

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}


/* ==========================================
   REMOVE ITEM
========================================== */

if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {

    $remove_id = (int) $_GET['remove'];

    if (isset($_SESSION['cart'][$remove_id])) {
        unset($_SESSION['cart'][$remove_id]);
    }

    header("Location: cart.php");
    exit;
}


/* ==========================================
   UPDATE CART
========================================== */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_cart'])
) {

    if (isset($_POST['quantity']) && is_array($_POST['quantity'])) {

        foreach ($_POST['quantity'] as $product_id => $quantity) {

            $product_id = (int) $product_id;
            $quantity = (int) $quantity;

            if (isset($_SESSION['cart'][$product_id])) {

                $stockStmt = $pdo->prepare(
                    "SELECT stock FROM products WHERE id = :id LIMIT 1"
                );

                $stockStmt->execute([
                    'id' => $product_id
                ]);

                $stock = $stockStmt->fetchColumn();

                if ($stock !== false && (int) $stock > 0) {

                    if ($quantity < 1) {
                        $quantity = 1;
                    }

                    if ($quantity > (int) $stock) {
                        $quantity = (int) $stock;
                    }

                    $_SESSION['cart'][$product_id]['quantity'] = $quantity;

                } else {

                    unset($_SESSION['cart'][$product_id]);

                }
            }
        }
    }

    header("Location: cart.php");
    exit;
}


/* ==========================================
   CART & WISHLIST COUNT
========================================== */

$cartCount = 0;

foreach ($_SESSION['cart'] as $item) {
    $cartCount += (int) ($item['quantity'] ?? 0);
}

$wishlistCount = 0;
if ($isLoggedIn) {
    try {
        $wStmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = :uid");
        $wStmt->execute([':uid' => $_SESSION['user_id']]);
        $wishlistCount = (int)$wStmt->fetchColumn();
    } catch (Exception $e) {
        $wishlistCount = isset($_SESSION['wishlist']) ? count($_SESSION['wishlist']) : 0;
    }
} else {
    $wishlistCount = isset($_SESSION['wishlist']) ? count($_SESSION['wishlist']) : 0;
}


/* ==========================================
   CALCULATE SUBTOTAL
========================================== */

$subtotal = 0;

foreach ($_SESSION['cart'] as $item) {

    $price = (float) ($item['price'] ?? 0);
    $quantity = (int) ($item['quantity'] ?? 0);

    $subtotal += $price * $quantity;
}


/* ==========================================
   DELIVERY CHARGE
========================================== */

$delivery = ($subtotal > 0) ? 70 : 0;


/* ==========================================
   FINAL TOTAL
========================================== */

$total = $subtotal + $delivery;

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Shopping Cart - Handicraft by Sonia</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect"
          href="https://fonts.gstatic.com"
          crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap"
          rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Main CSS -->
    <link rel="stylesheet"
          href="assets/css/style.css">

    <!-- Header Styling -->
    <style>
        .welcome-user {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #5b21b6;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
        }

        .welcome-user i {
            font-size: 20px;
        }

        .header-actions .login-btn {
            text-decoration: none;
        }
    </style>

</head>

<body>

<!-- ==================================================
     HEADER
================================================== -->

<header class="main-header">

    <!-- Logo -->
    <div class="logo-area">

        <img src="assets/images/images.jpeg"
             alt="Handicraft by Sonia">

        <div class="logo-text">
            <h2>Handicraft</h2>
            <span>by Sonia</span>
        </div>

    </div>


    <!-- Navigation -->
    <nav class="navbar">

        <a href="index.php">Home</a>

        <a href="shop.php">Shop</a>

        <a href="categories.php">Categories</a>

        <a href="custom-order.php">Custom Order</a>

        <a href="about.php">About Us</a>

        <a href="faq.php">FAQ</a>

    </nav>


    <!-- Header Actions -->
    <div class="header-actions">

        <!-- Search -->
        <button type="button" aria-label="Search" onclick="window.location.href='shop.php'">
            <i class="bi bi-search"></i>
        </button>


        <!-- Wishlist -->
        <a href="wishlist.php"
           class="icon-link notification"
           aria-label="Wishlist">

            <i class="bi bi-heart"></i>

            <span><?= (int) $wishlistCount ?></span>

        </a>


        <!-- Shopping Cart -->
        <a href="cart.php"
           class="icon-link notification"
           aria-label="Shopping Cart">

            <i class="bi bi-cart3"></i>

            <span><?= (int) $cartCount ?></span>

        </a>


        <?php if ($isLoggedIn): ?>

            <!-- Greeting links to My Account -->
            <a href="my-account.php"
               class="welcome-user"
               title="My Account">

                <i class="bi bi-person-circle"></i>

                <span>
                    Hi, <?= htmlspecialchars(
                        $userName,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </span>

            </a>


            <!-- Logout -->
            <a href="auth/logout.php"
               class="login-btn">

                <i class="bi bi-box-arrow-right"></i>

                Logout

            </a>

        <?php else: ?>

            <!-- Login -->
            <a href="auth/login.php"
               class="login-btn">

                <i class="bi bi-person"></i>

                Login

            </a>


            <!-- Register -->
            <a href="auth/register.php"
               class="login-btn">

                Register

            </a>

        <?php endif; ?>

    </div>

</header>


<!-- ==================================================
     CART HEADER
================================================== -->

<section class="cart-header">

    <span>YOUR SHOPPING</span>

    <h1>Shopping Cart</h1>

    <p>
        Review your selected handmade products before checkout.
    </p>

</section>


<!-- ==================================================
     CART SECTION
================================================== -->

<section class="cart-section">

    <div class="cart-container">

        <!-- CART ITEMS -->
        <div class="cart-items">

            <div class="cart-title">

                <h2>Your Cart</h2>

                <span><?= (int) $cartCount ?> Items</span>

            </div>


            <?php if (empty($_SESSION['cart'])): ?>

                <!-- EMPTY CART -->
                <div class="empty-cart">

                    <i class="bi bi-cart3"></i>

                    <h3>Your cart is empty</h3>

                    <p>
                        Looks like you haven't added anything
                        to your cart yet.
                    </p>

                    <a href="shop.php" class="continue-shopping">

                        Continue Shopping

                        <i class="bi bi-arrow-right"></i>

                    </a>

                </div>

            <?php else: ?>

                <!-- CART FORM -->
                <form method="POST" action="cart.php">

                    <?php foreach ($_SESSION['cart'] as $item): ?>

                        <div class="cart-item">

                            <!-- PRODUCT IMAGE -->
                            <div class="cart-item-image">

                                <img
                                    src="assets/images/<?= htmlspecialchars(
                                        $item['image'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    alt="<?= htmlspecialchars(
                                        $item['name'] ?? 'Product',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >

                            </div>


                            <!-- PRODUCT INFO -->
                            <div class="cart-item-info">

                                <h3>
                                    <a href="product-details.php?id=<?= (int) $item['id'] ?>">
                                        <?= htmlspecialchars(
                                            $item['name'] ?? 'Product',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </a>
                                </h3>

                                <p>Handmade Product</p>

                                <div class="cart-item-price">
                                    ৳<?= number_format(
                                        (float) ($item['price'] ?? 0),
                                        0
                                    ) ?>
                                </div>

                            </div>


                            <!-- QUANTITY -->
                            <div class="cart-quantity">

                                <input
                                    type="number"
                                    name="quantity[<?= (int) $item['id'] ?>]"
                                    value="<?= (int) ($item['quantity'] ?? 1) ?>"
                                    min="1"
                                    aria-label="Product quantity"
                                >

                            </div>


                            <!-- REMOVE ITEM -->
                            <a
                                href="cart.php?remove=<?= (int) $item['id'] ?>"
                                class="remove-cart-item"
                                title="Remove"
                                aria-label="Remove product"
                                onclick="return confirm('Are you sure you want to remove this product?');"
                            >

                                <i class="bi bi-trash3"></i>

                            </a>

                        </div>

                    <?php endforeach; ?>


                    <!-- UPDATE CART -->
                    <button
                        type="submit"
                        name="update_cart"
                        value="1"
                        class="continue-shopping">

                        <i class="bi bi-arrow-repeat"></i>

                        Update Cart

                    </button>

                </form>

            <?php endif; ?>

        </div>


        <!-- ORDER SUMMARY -->
        <div class="cart-summary">

            <h2>Order Summary</h2>


            <!-- SUBTOTAL -->
            <div class="summary-row">

                <span>Subtotal</span>

                <strong>
                    ৳<?= number_format($subtotal, 0) ?>
                </strong>

            </div>


            <!-- DELIVERY -->
            <div class="summary-row">

                <span>Delivery</span>

                <strong>
                    ৳<?= number_format($delivery, 0) ?>
                </strong>

            </div>


            <div class="summary-divider"></div>


            <!-- TOTAL -->
            <div class="summary-total">

                <span>Total</span>

                <strong>
                    ৳<?= number_format($total, 0) ?>
                </strong>

            </div>


            <!-- CHECKOUT -->
            <?php if ($cartCount > 0): ?>

                <button
                    type="button"
                    class="checkout-btn"
                    onclick="window.location.href='checkout.php'">

                    Proceed to Checkout

                    <i class="bi bi-arrow-right"></i>

                </button>

            <?php else: ?>

                <button
                    type="button"
                    class="checkout-btn"
                    disabled>

                    Proceed to Checkout

                    <i class="bi bi-arrow-right"></i>

                </button>

            <?php endif; ?>

        </div>

    </div>

</section>


<!-- ==================================================
     FOOTER
================================================== -->

<footer>

    <p>
        © 2026 Handicraft by Sonia |
        All Rights Reserved By Team Triple Pixel
    </p>

</footer>

<?php include_once __DIR__ . '/chat.php'; ?>

</body>
</html>