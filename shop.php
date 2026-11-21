<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/config/database.php";

/* ================= LOGIN STATUS ================= */

$isLoggedIn = !empty($_SESSION['user_id']);

$userName = $_SESSION['user_name']
    ?? $_SESSION['username']
    ?? $_SESSION['name']
    ?? 'User';

$userName = trim((string) $userName);

if ($userName === '') {
    $userName = 'User';
}

/* ================= CART & WISHLIST COUNTS ================= */

$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int)($item['quantity'] ?? 1);
    }
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

/* ================= CATEGORY FILTER & FETCH PRODUCTS ================= */

$categoryId = isset($_GET['category']) && is_numeric($_GET['category']) ? (int)$_GET['category'] : 0;
$selectedCategoryName = 'All Products';

if ($categoryId > 0) {
    $catStmt = $pdo->prepare("SELECT name FROM categories WHERE id = :id LIMIT 1");
    $catStmt->execute([':id' => $categoryId]);
    $catRow = $catStmt->fetch(PDO::FETCH_ASSOC);
    if ($catRow) {
        $selectedCategoryName = $catRow['name'];
    }

    $sql = "SELECT * FROM products WHERE category_id = :category_id ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':category_id' => $categoryId]);
} else {
    $sql = "SELECT * FROM products ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
}

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Shop - Handicraft by Sonia</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Header Account Styling -->
    <style>
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-account-menu {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-greeting {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #5b21b6;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
        }

        .user-greeting i {
            font-size: 20px;
        }

        .user-greeting:hover {
            color: #7c3aed;
        }

        .logout-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 9px 14px;
            border-radius: 8px;
            background: #5b21b6;
            color: #fff;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
            transition: background 0.2s ease;
        }

        .logout-btn:hover {
            background: #4c1d95;
            color: #fff;
        }

        .header-actions .icon-link {
            text-decoration: none;
        }

        @media (max-width: 768px) {
            .header-actions {
                gap: 10px;
                flex-wrap: wrap;
            }

            .user-account-menu {
                gap: 8px;
                flex-wrap: wrap;
            }

            .user-greeting {
                font-size: 12px;
            }

            .logout-btn {
                padding: 8px 10px;
                font-size: 12px;
            }
        }
    </style>

</head>

<body>

<!-- ================= HEADER ================= -->

<header class="main-header">

    <!-- Logo -->
    <div class="logo-area">

        <a href="index.php" class="logo-image-link">
            <img
                src="assets/images/images.jpeg"
                alt="Handicraft by Sonia">
        </a>

        <div class="logo-text">
            <h2>Handicraft</h2>
            <span>by Sonia</span>
        </div>

    </div>

    <!-- Navigation -->
    <nav class="navbar">

        <a href="index.php">Home</a>
        <a href="shop.php" class="active">Shop</a>
        <a href="categories.php">Categories</a>
        <a href="custom-order.php">Custom Order</a>
        <a href="about.php">About Us</a>
        <a href="faq.php">FAQ</a>

    </nav>

    <!-- Header Actions -->
    <div class="header-actions">

        <!-- Search -->
        <a
            href="shop.php"
            class="icon-link"
            aria-label="Search products"
            title="Search products">

            <i class="bi bi-search"></i>

        </a>

        <!-- Wishlist -->
        <a
            href="wishlist.php"
            class="icon-link notification"
            aria-label="Wishlist"
            title="Wishlist">

            <i class="bi bi-heart"></i>
            <span><?php echo $wishlistCount; ?></span>

        </a>

        <!-- Cart -->
        <a
            href="cart.php"
            class="icon-link notification"
            aria-label="Shopping cart"
            title="Shopping cart">

            <i class="bi bi-cart3"></i>
            <span><?php echo $cartCount; ?></span>

        </a>

        <?php if ($isLoggedIn): ?>

            <!-- Logged-in User -->
            <div class="user-account-menu">

                <a
                    href="my-account.php"
                    class="user-greeting"
                    title="My Account">

                    <i class="bi bi-person-circle"></i>

                    <span>
                        Hi,
                        <?php
                        echo htmlspecialchars(
                            $userName,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </span>

                </a>

                <a href="auth/logout.php" class="logout-btn">
                    <i class="bi bi-box-arrow-right"></i>
                    Logout
                </a>

            </div>

        <?php else: ?>

            <!-- Guest User -->
            <a href="auth/login.php" class="login-btn">
                <i class="bi bi-person"></i>
                Login
            </a>

        <?php endif; ?>

    </div>

</header>


<!-- ================= SHOP HEADER ================= -->

<section class="shop-header">

    <div class="shop-header-content">

        <span>Our Collection</span>

        <h1>Shop Our Products</h1>

        <p>
            Discover beautiful handmade products crafted with love.
        </p>

    </div>

</section>


<!-- ================= SHOP PRODUCTS ================= -->

<section class="shop-section">

    <div class="shop-container">

        <div class="shop-top">

            <div>
                <h2><?php echo htmlspecialchars($selectedCategoryName, ENT_QUOTES, 'UTF-8'); ?></h2>
                <?php if ($categoryId > 0): ?>
                    <a href="shop.php" class="view-product" style="display: inline-block; margin-top: 6px;">
                        <i class="bi bi-arrow-left"></i> Show All Products
                    </a>
                <?php endif; ?>
            </div>

            <span>
                <?php echo count($products); ?> Products
            </span>

        </div>

        <div class="shop-product-grid">

            <?php if (!empty($products)): ?>

                <?php foreach ($products as $product): ?>

                    <div class="shop-product-card">

                        <!-- Product Image -->
                        <div class="shop-product-image">

                            <a href="product-details.php?id=<?php echo (int) ($product['id'] ?? 0); ?>">
                                <img
                                    src="assets/images/<?php
                                        echo rawurlencode(
                                            (string) ($product['image'] ?? '')
                                        );
                                    ?>"
                                    alt="<?php
                                        echo htmlspecialchars(
                                            (string) ($product['name'] ?? 'Product'),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>">
                            </a>

                            <a
                                href="wishlist.php?action=toggle&id=<?php echo (int) ($product['id'] ?? 0); ?>"
                                class="wishlist-btn"
                                aria-label="Add to wishlist"
                                title="Add to Wishlist">

                                <i class="bi bi-heart"></i>

                            </a>

                        </div>

                        <!-- Product Information -->
                        <div class="shop-product-info">

                            <h3>
                                <a href="product-details.php?id=<?php echo (int) ($product['id'] ?? 0); ?>">
                                    <?php
                                    echo htmlspecialchars(
                                        (string) ($product['name'] ?? ''),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </a>
                            </h3>

                            <p class="product-description">
                                <?php
                                echo htmlspecialchars(
                                    (string) ($product['description'] ?? ''),
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                ?>
                            </p>

                            <strong>
                                ৳<?php
                                    echo number_format(
                                        (float) ($product['price'] ?? 0),
                                        0
                                    );
                                ?>
                            </strong>

                            <div class="shop-product-bottom">

                                <span class="stock">

                                    <?php if ((int) ($product['stock'] ?? 0) > 0): ?>

                                        <i class="bi bi-check-circle"></i>
                                        In Stock

                                    <?php else: ?>

                                        <i class="bi bi-x-circle"></i>
                                        Out of Stock

                                    <?php endif; ?>

                                </span>

                                <a
                                    href="product-details.php?id=<?php
                                        echo (int) ($product['id'] ?? 0);
                                    ?>"
                                    class="view-product">

                                    View Product
                                    <i class="bi bi-arrow-right"></i>

                                </a>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="no-products">

                    <i class="bi bi-box-seam"></i>

                    <h3>No Products Found</h3>

                    <p>
                        There are currently no products available in this category.
                    </p>

                    <?php if ($categoryId > 0): ?>
                        <a href="shop.php">View All Products</a>
                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </div>

    </div>

</section>


<!-- ================= FOOTER ================= -->

<footer>

    <p>
        © 2026 Handicraft by Sonia |
        All Rights Reserved By Team Triple Pixel
    </p>

</footer>

<?php include_once __DIR__ . '/chat.php'; ?>

</body>
</html>