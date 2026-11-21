<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/config/database.php";

// Login status
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name']
    ?? $_SESSION['username']
    ?? $_SESSION['name']
    ?? 'Customer';

// Cart Count
$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int)($item['quantity'] ?? 1);
    }
}

// Wishlist Count
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

// Fetch featured products
$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.name,
        p.price,
        p.image,
        COALESCE(AVG(r.rating), 0) AS average_rating,
        COUNT(r.id) AS review_count
    FROM products p
    LEFT JOIN ratings r ON p.id = r.product_id
    GROUP BY
        p.id,
        p.name,
        p.price,
        p.image
    ORDER BY p.id ASC
    LIMIT 6
");

$stmt->execute();
$featuredProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Handicraft by Sonia</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .welcome-user {
            color: #5b21b6;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
        }

        .header-actions .login-btn {
            text-decoration: none;
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

        <a href="index.php" class="active">Home</a>
        <a href="shop.php">Shop</a>
        <a href="categories.php">Categories</a>
        <a href="custom-order.php">Custom Order</a>
        <a href="about.php">About Us</a>
        <a href="faq.php">FAQ</a>

    </nav>

<div class="header-actions">

    <!-- Search -->
    <button type="button" aria-label="Search" onclick="window.location.href='shop.php'">
        <i class="bi bi-search"></i>
    </button>

    <!-- Wishlist -->
    <a href="wishlist.php" class="icon-link notification" aria-label="Wishlist">
        <i class="bi bi-heart"></i>
        <span><?= $wishlistCount ?></span>
    </a>

    <!-- Shopping Cart -->
    <a href="cart.php" class="icon-link notification" aria-label="Shopping Cart">
        <i class="bi bi-cart3"></i>
        <span><?= $cartCount ?></span>
    </a>

    <?php if ($isLoggedIn): ?>

        <!-- Click the greeting to open My Account -->
        <a href="my-account.php" class="welcome-user" title="My Account">
            <i class="bi bi-person-circle"></i>
            <span>Hi, <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></span>
        </a>

        <!-- Logout -->
        <a href="auth/logout.php" class="login-btn">
            <i class="bi bi-box-arrow-right"></i>
            Logout
        </a>

    <?php else: ?>

        <!-- Login and Register -->
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


<!-- HERO SLIDER -->

<section class="hero-slider">

    <div class="slider-wrapper">

        <div class="slide active">
            <img src="assets/images/Cover Page1.png" alt="Handicraft by Sonia Banner">
            <a href="shop.php" class="banner-btn">
                Shop Now <i class="bi bi-arrow-right"></i>
            </a>
        </div>

        <div class="slide">
            <img src="assets/images/Cover Page2.png" alt="Floral Hand Gajra">
            <a href="categories.php" class="banner-btn">
                View Collection <i class="bi bi-arrow-right"></i>
            </a>
        </div>

        <div class="slide">
            <img src="assets/images/Cover Page3.png" alt="Bridal Kalira">
            <a href="categories.php" class="banner-btn">
                Explore Collection <i class="bi bi-arrow-right"></i>
            </a>
        </div>

        <div class="slide">
            <img src="assets/images/Cover Page4.png" alt="Handmade Collection">
            <a href="categories.php" class="banner-btn">
                Shop Now <i class="bi bi-arrow-right"></i>
            </a>
        </div>

        <div class="slide">
            <img src="assets/images/Cover Page5.png" alt="Handmade Collection">
            <a href="categories.php" class="banner-btn">
                View Collection <i class="bi bi-arrow-right"></i>
            </a>
        </div>

    </div>

    <button class="slider-btn prev" id="prevBtn" type="button" aria-label="Previous slide">
        <i class="bi bi-chevron-left"></i>
    </button>

    <button class="slider-btn next" id="nextBtn" type="button" aria-label="Next slide">
        <i class="bi bi-chevron-right"></i>
    </button>

    <div class="slider-dots" id="sliderDots">
        <button class="dot active" data-slide="0" aria-label="Slide 1"></button>
        <button class="dot" data-slide="1" aria-label="Slide 2"></button>
        <button class="dot" data-slide="2" aria-label="Slide 3"></button>
        <button class="dot" data-slide="3" aria-label="Slide 4"></button>
        <button class="dot" data-slide="4" aria-label="Slide 5"></button>
    </div>

</section>


<!-- CATEGORIES -->

<section class="category-section">

    <div class="category-header">

        <div>
            <span class="small-title">EXPLORE</span>
            <h2>Our Categories</h2>
            <p>Discover beautiful handmade collections for every occasion</p>
        </div>

        <a href="categories.php" class="view-all">
            View All Categories <i class="bi bi-arrow-right"></i>
        </a>

    </div>

    <div class="category-grid">

        <a href="shop.php?category=1" class="category-card">
            <div class="category-image">
                <img src="assets/images/Holud Floral Jewellery.jpeg" alt="Handmade Floral Jewellery">
            </div>
            <div class="category-info">
                <h3>Handmade Floral Jewellery</h3>
                <span>View Collection <i class="bi bi-arrow-right"></i></span>
            </div>
        </a>

        <a href="shop.php?category=2" class="category-card">
            <div class="category-image">
                <img src="assets/images/Golap Gajra.jpeg" alt="Hair Gajra / Khopa">
            </div>
            <div class="category-info">
                <h3>Hair Gajra / Khopa</h3>
                <span>View Collection <i class="bi bi-arrow-right"></i></span>
            </div>
        </a>

        <a href="shop.php?category=3" class="category-card">
            <div class="category-image">
                <img src="assets/images/Bridal Kalira2.jpeg" alt="Bridal Kalira">
            </div>
            <div class="category-info">
                <h3>Bridal Kalira</h3>
                <span>View Collection <i class="bi bi-arrow-right"></i></span>
            </div>
        </a>

        <a href="shop.php?category=4" class="category-card">
            <div class="category-image">
                <img src="assets/images/Bangles1.jpeg" alt="Churi">
            </div>
            <div class="category-info">
                <h3>Churi</h3>
                <span>View Collection <i class="bi bi-arrow-right"></i></span>
            </div>
        </a>

        <a href="shop.php?category=5" class="category-card">
            <div class="category-image">
                <img src="assets/images/Kundan Jwellery1.jpeg" alt="Kundan Jewellery">
            </div>
            <div class="category-info">
                <h3>Kundan Jewellery</h3>
                <span>View Collection <i class="bi bi-arrow-right"></i></span>
            </div>
        </a>

        <a href="shop.php?category=6" class="category-card">
            <div class="category-image">
                <img src="assets/images/Antique Jewellery1.jpeg" alt="Antique Jewellery">
            </div>
            <div class="category-info">
                <h3>Antique Jewellery</h3>
                <span>View Collection <i class="bi bi-arrow-right"></i></span>
            </div>
        </a>

        <a href="shop.php?category=7" class="category-card">
            <div class="category-image">
                <img src="assets/images/Jhapta1.jpeg" alt="Jhapta">
            </div>
            <div class="category-info">
                <h3>Jhapta</h3>
                <span>View Collection <i class="bi bi-arrow-right"></i></span>
            </div>
        </a>

        <a href="shop.php?category=8" class="category-card">
            <div class="category-image">
                <img src="assets/images/Bracelet 1.jpg" alt="Bracelets">
            </div>
            <div class="category-info">
                <h3>Bracelets</h3>
                <span>View Collection <i class="bi bi-arrow-right"></i></span>
            </div>
        </a>

        <a href="shop.php?category=9" class="category-card">
            <div class="category-image">
                <img src="assets/images/Hijab Pin101.jpeg" alt="Hijab Pin">
            </div>
            <div class="category-info">
                <h3>Hijab Pin</h3>
                <span>View Collection <i class="bi bi-arrow-right"></i></span>
            </div>
        </a>

        <a href="shop.php?category=10" class="category-card">
            <div class="category-image">
                <img src="assets/images/Finger Ring.jpeg" alt="Finger Ring">
            </div>
            <div class="category-info">
                <h3>Finger Ring</h3>
                <span>View Collection <i class="bi bi-arrow-right"></i></span>
            </div>
        </a>

    </div>

</section>


<!-- FEATURED PRODUCTS -->

<section class="featured-products">

    <div class="featured-header">

        <div>
            <span class="featured-small-title">OUR COLLECTION</span>
            <h2>Flagship Products</h2>

            <div class="heading-line">
                <span></span>
                <i class="bi bi-star-fill"></i>
                <span></span>
            </div>
        </div>

        <a href="shop.php" class="view-all-products">
            View All Products <i class="bi bi-arrow-right"></i>
        </a>

    </div>

    <div class="product-grid">

        <?php if (!empty($featuredProducts)): ?>

            <?php foreach ($featuredProducts as $product): ?>

                <div class="product-card">

                    <div class="product-image">

                        <?php
                        $imageName = !empty($product['image'])
                            ? $product['image']
                            : 'default-product.jpg';
                        ?>

                        <a href="product-details.php?id=<?= (int)$product['id'] ?>">
                            <img
                                src="assets/images/<?= htmlspecialchars($imageName, ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"
                            >
                        </a>

                        <a href="wishlist.php?action=toggle&id=<?= (int)$product['id'] ?>" class="wishlist-btn" aria-label="Add to wishlist">
                            <i class="bi bi-heart"></i>
                        </a>

                    </div>

                    <div class="product-info">

                        <h3>
                            <a href="product-details.php?id=<?= (int)$product['id'] ?>">
                                <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </h3>

                        <strong>
                            ৳<?= number_format((float)$product['price'], 0) ?>
                        </strong>

                        <div class="product-rating">

                            <?php
                            $averageRating = (int) round((float)$product['average_rating']);
                            $reviewCount = (int)$product['review_count'];
                            ?>

                            <span>
                                <?php
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $averageRating ? '★' : '☆';
                                }
                                ?>
                            </span>

                            <small>(<?= $reviewCount ?>)</small>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <p>No featured products available.</p>

        <?php endif; ?>

    </div>

</section>


<!-- FEATURES -->

<section class="features">

    <div class="feature">
        <i class="bi bi-truck"></i>
        <div>
            <h3>Fast Delivery</h3>
            <p>Across Bangladesh</p>
        </div>
    </div>

    <div class="feature">
        <i class="bi bi-shield-check"></i>
        <div>
            <h3>Secure Payment</h3>
            <p>100% Secure</p>
        </div>
    </div>

    <div class="feature">
        <i class="bi bi-patch-check"></i>
        <div>
            <h3>Premium Quality</h3>
            <p>Best Handmade</p>
        </div>
    </div>

    <div class="feature">
        <i class="bi bi-gift"></i>
        <div>
            <h3>Custom Order</h3>
            <p>Made for You</p>
        </div>
    </div>

    <div class="feature">
        <i class="bi bi-headset"></i>
        <div>
            <h3>24/7 Support</h3>
            <p>We're here to help</p>
        </div>
    </div>

</section>


<footer>
    <p>
        © 2026 Handicraft by Sonia | All Rights Reserved By Team Triple Pixel
    </p>
</footer>

<script src="assets/js/script.js"></script>
<!-- Safe GSAP Animation (No invisible cards conflict) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    if (typeof gsap !== "undefined") {
        // সব কার্ড যেন ১০০% দৃশ্যমান থাকে তা নিশ্চিত করা
        gsap.set(".category-card, .product-card, .feature", { opacity: 1, visibility: "visible" });

        // শুধুমাত্র হালকা স্লাইড অ্যানিমেশন (Opacity 0 ছাড়া)
        gsap.fromTo(".hero-slider", 
            { y: 15 }, 
            { y: 0, duration: 0.7, ease: "power2.out" }
        );

        gsap.fromTo(".category-card", 
            { y: 20 }, 
            { y: 0, duration: 0.5, stagger: 0.05, ease: "power2.out", clearProps: "transform" }
        );

        gsap.fromTo(".product-card", 
            { y: 20 }, 
            { y: 0, duration: 0.5, stagger: 0.07, ease: "power2.out", clearProps: "transform" }
        );
    }
});
</script>

<?php include_once __DIR__ . '/chat.php'; ?>

</body>
</html>