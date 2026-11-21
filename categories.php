<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/config/database.php";

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name']
    ?? $_SESSION['username']
    ?? $_SESSION['name']
    ?? 'Customer';

// Cart & Wishlist count for header
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

// Fetch categories with product count from database
$stmt = $pdo->query("
    SELECT c.id, c.name, c.description, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id, c.name, c.description
    ORDER BY c.id ASC
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Exact category images used in your index.php
$categoryImages = [
    1  => 'Holud Floral Jewellery.jpeg',
    2  => 'Golap Gajra.jpeg',
    3  => 'Bridal Kalira2.jpeg',
    4  => 'Bangles1.jpeg',
    5  => 'Kundan Jwellery1.jpeg',
    6  => 'Antique Jewellery1.jpeg',
    7  => 'Jhapta1.jpeg',
    8  => 'Bracelet 1.jpg',
    9  => 'Hijab Pin101.jpeg',
    10 => 'Finger Ring.jpeg'
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - Handicraft by Sonia</title>

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
        <a href="categories.php" class="active">Categories</a>
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
        <span>HANDMADE COLLECTIONS</span>
        <h1>Our Product Categories</h1>
        <p>Explore our handcrafted floral jewellery, bridal kalira, hair gajra, bangles, and accessories</p>
    </div>
</section>

<section class="category-section" style="background: #faf7ff; padding-top: 10px; padding-bottom: 70px;">
    <div class="shop-container">
        <div class="shop-product-grid">
            <?php foreach ($categories as $cat): 
                $catId = (int)$cat['id'];
                $imgFile = $categoryImages[$catId] ?? 'Holud Floral Jewellery.jpeg';
            ?>
                <div class="shop-product-card">
                    <a href="shop.php?category=<?= $catId ?>">
                        <div class="shop-product-image" style="height: 240px;">
                            <img src="assets/images/<?= htmlspecialchars($imgFile, ENT_QUOTES, 'UTF-8') ?>"
                                 alt="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </a>
                    <div class="shop-product-info">
                        <span class="product-category-name"><?= (int)$cat['product_count'] ?> Products Available</span>
                        <h3>
                            <a href="shop.php?category=<?= $catId ?>">
                                <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </h3>
                        <p class="product-description">
                            <?= htmlspecialchars($cat['description'] ?: 'Beautiful handmade collection crafted with care.', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <div class="shop-product-bottom">
                            <span class="stock"><i class="bi bi-stars"></i> Handmade</span>
                            <a href="shop.php?category=<?= $catId ?>" class="view-product">
                                View Collection <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<footer>
    <p>© 2026 Handicraft by Sonia | All Rights Reserved By Team Triple Pixel</p>
</footer>

<?php include_once __DIR__ . '/chat.php'; ?>
</body>
</html>