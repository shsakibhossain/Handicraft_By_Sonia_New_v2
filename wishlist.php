<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/config/database.php";

$isLoggedIn = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name']
    ?? $_SESSION['username']
    ?? $_SESSION['name']
    ?? 'Customer';

if (!isset($_SESSION['wishlist']) || !is_array($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

// Ensure wishlist table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `wishlist` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `product_id` int(11) NOT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `user_product_unique` (`user_id`, `product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (Exception $e) {}

// Handle Add / Remove / Toggle
$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if ($product_id > 0 && in_array($action, ['add', 'remove', 'toggle'], true)) {
    $state = 'added';
    if ($isLoggedIn) {
        $chk = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = :uid AND product_id = :pid");
        $chk->execute([':uid' => $user_id, ':pid' => $product_id]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);

        if ($action === 'remove' || ($action === 'toggle' && $row)) {
            if ($row) {
                $del = $pdo->prepare("DELETE FROM wishlist WHERE id = :id");
                $del->execute([':id' => $row['id']]);
            }
            $state = 'removed';
        } elseif (!$row) {
            $ins = $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (:uid, :pid)");
            $ins->execute([':uid' => $user_id, ':pid' => $product_id]);
            $state = 'added';
        }
    } else {
        $idx = array_search($product_id, $_SESSION['wishlist']);
        if ($action === 'remove' || ($action === 'toggle' && $idx !== false)) {
            if ($idx !== false) {
                unset($_SESSION['wishlist'][$idx]);
                $_SESSION['wishlist'] = array_values($_SESSION['wishlist']);
            }
            $state = 'removed';
        } elseif ($idx === false) {
            $_SESSION['wishlist'][] = $product_id;
            $state = 'added';
        }
    }

    if (isset($_GET['ajax']) || isset($_POST['ajax'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'status' => $state, 'product_id' => $product_id]);
        exit;
    }

    header("Location: wishlist.php");
    exit;
}

// Fetch wishlist products
$wishlistProducts = [];
if ($isLoggedIn) {
    if (!empty($_SESSION['wishlist'])) {
        $mStmt = $pdo->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (:uid, :pid)");
        foreach ($_SESSION['wishlist'] as $sid) {
            $mStmt->execute([':uid' => $user_id, ':pid' => (int)$sid]);
        }
        $_SESSION['wishlist'] = [];
    }
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE w.user_id = :uid
        ORDER BY w.id DESC
    ");
    $stmt->execute([':uid' => $user_id]);
    $wishlistProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif (!empty($_SESSION['wishlist'])) {
    $ids = array_map('intval', $_SESSION['wishlist']);
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id IN ($in)
    ");
    $stmt->execute($ids);
    $wishlistProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$wishlistCount = count($wishlistProducts);
$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int)($item['quantity'] ?? 1);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - Handicraft by Sonia</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
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
            <i class="bi bi-heart-fill"></i>
            <span><?= $wishlistCount ?></span>
        </a>

        <a href="cart.php" class="icon-link notification" aria-label="Shopping Cart">
            <i class="bi bi-cart3"></i>
            <span><?= $cartCount ?></span>
        </a>

        <?php if ($isLoggedIn): ?>
            <a href="my-account.php" class="welcome-user" title="My Account">
                <i class="bi bi-person-circle"></i>
                <span>Hi, <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></span>
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
            <a href="auth/register.php" class="login-btn">
                Register
            </a>
        <?php endif; ?>
    </div>
</header>

<section class="shop-header">
    <div class="shop-header-content">
        <span>FAVOURITE COLLECTION</span>
        <h1>My Wishlist</h1>
        <p>Your saved handmade jewellery and accessories</p>
    </div>
</section>

<section class="shop-section">
    <div class="shop-container">
        <?php if (empty($wishlistProducts)): ?>
            <div class="no-products">
                <i class="bi bi-heart"></i>
                <h3>Your Wishlist is Empty</h3>
                <p>Explore our handmade collections and click the heart icon on any product to save it here.</p>
                <a href="shop.php" class="continue-shopping">
                    <i class="bi bi-bag-heart"></i> Explore Shop
                </a>
            </div>
        <?php else: ?>
            <div class="shop-top">
                <h2>Saved Items</h2>
                <span><?= $wishlistCount ?> Product(s) in Wishlist</span>
            </div>

            <div class="shop-product-grid">
                <?php foreach ($wishlistProducts as $product): ?>
                    <div class="shop-product-card">
                        <div class="shop-product-image">
                            <img src="assets/images/<?= htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8') ?>"
                                 alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>">

                            <a href="wishlist.php?action=remove&id=<?= (int)$product['id'] ?>"
                               class="wishlist-btn"
                               title="Remove from Wishlist"
                               style="background: #6d28d9; color: #fff;">
                                <i class="bi bi-trash3"></i>
                            </a>
                        </div>

                        <div class="shop-product-info">
                            <span class="product-category-name">
                                <?= htmlspecialchars($product['category_name'] ?? 'Handmade', ENT_QUOTES, 'UTF-8') ?>
                            </span>

                            <h3>
                                <a href="product-details.php?id=<?= (int)$product['id'] ?>">
                                    <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </h3>

                            <p class="product-description">
                                <?= htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8') ?>
                            </p>

                            <strong>৳<?= number_format((float)$product['price'], 0) ?></strong>

                            <div class="shop-product-bottom">
                                <?php if ((int)$product['stock'] > 0): ?>
                                    <span class="stock"><i class="bi bi-check-circle-fill"></i> In Stock</span>
                                <?php else: ?>
                                    <span class="stock" style="color: #d33030;"><i class="bi bi-x-circle-fill"></i> Out of Stock</span>
                                <?php endif; ?>

                                <a href="product-details.php?id=<?= (int)$product['id'] ?>" class="view-product">
                                    View Details <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<footer>
    <p>© 2026 Handicraft by Sonia | All Rights Reserved By Team Triple Pixel</p>
</footer>

<?php include_once __DIR__ . '/chat.php'; ?>
</body>
</html>