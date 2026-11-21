<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once "config/database.php";

$isLoggedIn = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name']
    ?? $_SESSION['username']
    ?? $_SESSION['name']
    ?? 'Customer';

/* ==========================================
   INITIALIZE CART
========================================== */
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

/* ==========================================
   ADD TO CART
========================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = (int) $_POST['product_id'];
    $quantity   = (int) $_POST['quantity'];

    if ($quantity < 1) {
        $quantity = 1;
    }

    $cartSql = "SELECT * FROM products WHERE id = :id LIMIT 1";
    $cartStmt = $pdo->prepare($cartSql);
    $cartStmt->execute(['id' => $product_id]);
    $cartProduct = $cartStmt->fetch(PDO::FETCH_ASSOC);

    if ($cartProduct && $cartProduct['stock'] > 0) {
        if ($quantity > $cartProduct['stock']) {
            $quantity = (int) $cartProduct['stock'];
        }

        if (isset($_SESSION['cart'][$product_id])) {
            $newQuantity = $_SESSION['cart'][$product_id]['quantity'] + $quantity;
            if ($newQuantity > $cartProduct['stock']) {
                $newQuantity = (int) $cartProduct['stock'];
            }
            $_SESSION['cart'][$product_id]['quantity'] = $newQuantity;
        } else {
            $_SESSION['cart'][$product_id] = [
                'id'       => (int) $cartProduct['id'],
                'name'     => $cartProduct['name'],
                'price'    => (float) $cartProduct['price'],
                'image'    => $cartProduct['image'],
                'quantity' => $quantity
            ];
        }

        header("Location: cart.php");
        exit;
    }
}

/* ==========================================
   CHECK PRODUCT ID
========================================== */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: shop.php");
    exit;
}

$product_id = (int) $_GET['id'];

/* ==========================================
   FETCH PRODUCT
========================================== */
$sql = "SELECT * FROM products WHERE id = :id LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: shop.php");
    exit;
}

/* ==========================================
   FETCH RATINGS, REACTIONS & COMMENTS
========================================== */
$statStmt = $pdo->prepare("SELECT COALESCE(ROUND(AVG(rating), 1), 0) AS avg_rating, COUNT(*) AS total_ratings FROM ratings WHERE product_id = :pid");
$statStmt->execute([':pid' => $product_id]);
$ratingStats = $statStmt->fetch(PDO::FETCH_ASSOC);
$avgRating = (float)($ratingStats['avg_rating'] ?? 0);
$totalRatings = (int)($ratingStats['total_ratings'] ?? 0);

$userRating = 0;
$userReaction = null;
if ($isLoggedIn) {
    $urStmt = $pdo->prepare("SELECT rating FROM ratings WHERE product_id = :pid AND user_id = :uid");
    $urStmt->execute([':pid' => $product_id, ':uid' => $user_id]);
    $userRating = (int)$urStmt->fetchColumn();

    $ureactStmt = $pdo->prepare("SELECT reaction FROM reactions WHERE product_id = :pid AND user_id = :uid");
    $ureactStmt->execute([':pid' => $product_id, ':uid' => $user_id]);
    $userReaction = $ureactStmt->fetchColumn() ?: null;
}

$reactStmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN reaction = 'like' THEN 1 ELSE 0 END) AS likes,
        SUM(CASE WHEN reaction = 'dislike' THEN 1 ELSE 0 END) AS dislikes
    FROM reactions WHERE product_id = :pid
");
$reactStmt->execute([':pid' => $product_id]);
$reactionCounts = $reactStmt->fetch(PDO::FETCH_ASSOC);
$likesCount = (int)($reactionCounts['likes'] ?? 0);
$dislikesCount = (int)($reactionCounts['dislikes'] ?? 0);

$commStmt = $pdo->prepare("
    SELECT c.id, c.comment, c.created_at, u.name AS user_name
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.product_id = :pid
    ORDER BY c.id DESC
");
$commStmt->execute([':pid' => $product_id]);
$commentsList = $commStmt->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================
   CART & WISHLIST COUNT
========================================== */
$cartCount = 0;
foreach ($_SESSION['cart'] as $cartItem) {
    $cartCount += (int) $cartItem['quantity'];
}

$wishlistCount = 0;
if ($isLoggedIn) {
    try {
        $wStmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = :uid");
        $wStmt->execute([':uid' => $user_id]);
        $wishlistCount = (int)$wStmt->fetchColumn();
    } catch (Exception $e) {
        $wishlistCount = isset($_SESSION['wishlist']) ? count($_SESSION['wishlist']) : 0;
    }
} else {
    $wishlistCount = isset($_SESSION['wishlist']) ? count($_SESSION['wishlist']) : 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Handicraft by Sonia</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .reaction-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .react-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: 30px;
            border: 1px solid #d8c6f2;
            background: #ffffff;
            color: #43216d;
            font-size: 13px;
            font-weight: 600;
            transition: 0.25s;
        }
        .react-btn:hover, .react-btn.active {
            background: #6d28d9;
            color: #ffffff;
            border-color: #6d28d9;
        }
        .engagement-section {
            background: #faf7ff;
            padding: 10px 5% 70px;
        }
        .engagement-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 30px;
            align-items: start;
        }
        .star-picker {
            display: inline-flex;
            gap: 8px;
            font-size: 26px;
            color: #d8c6f2;
            cursor: pointer;
            margin: 10px 0 15px;
        }
        .star-picker i.active, .star-picker i:hover {
            color: #7c3aed;
        }
        .comment-item {
            padding: 16px 0;
            border-bottom: 1px solid #eee5f8;
        }
        .comment-item:last-child {
            border-bottom: none;
        }
        .comment-item strong {
            color: #3b176d;
            font-size: 14px;
        }
        .comment-item small {
            color: #888;
            font-size: 11px;
            margin-left: 8px;
        }
        .comment-item p {
            color: #555;
            font-size: 13px;
            margin-top: 6px;
            line-height: 1.6;
        }
        @media (max-width: 900px) {
            .engagement-grid {
                grid-template-columns: 1fr;
            }
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
        <a href="shop.php" class="active">Shop</a>
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
            <span><?php echo $wishlistCount; ?></span>
        </a>

        <a href="cart.php" class="icon-link notification" aria-label="Shopping Cart">
            <i class="bi bi-cart3"></i>
            <span><?php echo $cartCount; ?></span>
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

<section class="product-details-section">
    <div class="product-details-container">

        <div class="product-details-image">
            <img src="assets/images/<?php echo htmlspecialchars($product['image']); ?>"
                 alt="<?php echo htmlspecialchars($product['name']); ?>">
        </div>

        <div class="product-details-info">
            <span class="product-label">HANDMADE COLLECTION</span>

            <h1><?php echo htmlspecialchars($product['name']); ?></h1>

            <div class="product-details-rating">
                <span id="avgStarsDisplay">
                    <?php
                    $roundedAvg = (int)round($avgRating);
                    for ($i = 1; $i <= 5; $i++) {
                        echo $i <= $roundedAvg ? '★' : '☆';
                    }
                    ?>
                </span>
                <small id="avgRatingText">
                    <?= number_format($avgRating, 1) ?> / 5.0 (<?= $totalRatings ?> Ratings)
                </small>
            </div>

            <div class="product-details-price">
                ৳<?php echo number_format($product['price'], 0); ?>
            </div>

            <p class="product-details-description">
                <?php echo htmlspecialchars($product['description']); ?>
            </p>

            <!-- Like & Dislike Buttons -->
            <div class="reaction-bar">
                <button type="button" class="react-btn <?= $userReaction === 'like' ? 'active' : '' ?>" id="likeBtn" onclick="submitReaction('like')">
                    <i class="bi bi-hand-thumbs-up-fill"></i> Like (<span id="likeCount"><?= $likesCount ?></span>)
                </button>
                <button type="button" class="react-btn <?= $userReaction === 'dislike' ? 'active' : '' ?>" id="dislikeBtn" onclick="submitReaction('dislike')">
                    <i class="bi bi-hand-thumbs-down-fill"></i> Dislike (<span id="dislikeCount"><?= $dislikesCount ?></span>)
                </button>
            </div>

            <div class="product-stock">
                <?php if ($product['stock'] > 0): ?>
                    <i class="bi bi-check-circle"></i>
                    <span>In Stock (<?php echo $product['stock']; ?> available)</span>
                <?php else: ?>
                    <i class="bi bi-x-circle"></i>
                    <span>Out of Stock</span>
                <?php endif; ?>
            </div>

            <?php if ($product['stock'] > 0): ?>
                <form method="POST" action="" id="addToCartForm">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">

                    <div class="quantity-box">
                        <label>Quantity</label>
                        <div class="quantity-controls">
                            <button type="button" id="minusBtn">−</button>
                            <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>">
                            <button type="button" id="plusBtn">+</button>
                        </div>
                    </div>

                    <div class="product-actions">
                        <button type="submit" name="add_to_cart" class="add-cart-btn">
                            <i class="bi bi-cart-plus"></i> Add to Cart
                        </button>

                        <a href="wishlist.php?action=toggle&id=<?php echo (int)$product['id']; ?>"
                           class="details-wishlist-btn"
                           style="display: inline-flex; align-items: center; justify-content: center;"
                           title="Add to Wishlist">
                            <i class="bi bi-heart"></i>
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <button class="out-of-stock-btn" disabled>Out of Stock</button>
            <?php endif; ?>

            <a href="shop.php" class="back-shop">
                <i class="bi bi-arrow-left"></i> Back to Shop
            </a>
        </div>

    </div>
</section>

<!-- Customer Rating & Comments Section matching style.css -->
<section class="engagement-section">
    <div class="engagement-grid">

        <!-- Rate Product Card -->
        <div class="checkout-summary" style="position: static;">
            <h2>Rate This Product</h2>
            <p style="font-size: 13px; color: #777; margin-bottom: 10px;">Share your experience with this handmade item</p>

            <div class="star-picker" id="starPicker">
                <?php for ($s = 1; $s <= 5; $s++): ?>
                    <i class="bi bi-star-fill <?= $s <= $userRating ? 'active' : '' ?>" data-value="<?= $s ?>" onclick="submitRating(<?= $s ?>)"></i>
                <?php endfor; ?>
            </div>
            <p id="ratingFeedback" style="font-size: 12px; color: #6d28d9; font-weight: 600;"></p>
        </div>

        <!-- Product Comments Card -->
        <div class="checkout-form-box">
            <div class="checkout-box-title">
                <i class="bi bi-chat-square-text"></i>
                <div>
                    <h2>Customer Comments (<span id="commentTotal"><?= count($commentsList) ?></span>)</h2>
                    <p>Ask a question or leave your feedback about this product</p>
                </div>
            </div>

            <form id="commentForm" class="checkout-form" style="margin-bottom: 25px;">
                <div class="form-group">
                    <label>Write a Comment</label>
                    <textarea id="commentText" rows="3" placeholder="Write your comment here..." required></textarea>
                </div>
                <button type="submit" class="place-order-btn" style="max-width: 220px; height: 44px;">
                    <i class="bi bi-send"></i> Post Comment
                </button>
                <p id="commentFeedback" style="font-size: 12px; color: #d33030;"></p>
            </form>

            <div id="commentsContainer">
                <?php if (empty($commentsList)): ?>
                    <p id="noCommentsMsg" style="font-size: 13px; color: #888;">No comments yet. Be the first to comment!</p>
                <?php else: ?>
                    <?php foreach ($commentsList as $c): ?>
                        <div class="comment-item">
                            <strong><?= htmlspecialchars($c['user_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <small><?= date('M d, Y h:i A', strtotime($c['created_at'])) ?></small>
                            <p><?= htmlspecialchars($c['comment'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</section>

<footer>
    <p>© 2026 Handicraft by Sonia | All Rights Reserved By Team Triple Pixel</p>
</footer>

<script>
const productId = <?= (int)$product['id'] ?>;
const quantityInput = document.getElementById("quantity");
const minusBtn = document.getElementById("minusBtn");
const plusBtn = document.getElementById("plusBtn");

if (minusBtn && quantityInput) {
    minusBtn.addEventListener("click", function () {
        let value = parseInt(quantityInput.value) || 1;
        if (value > 1) quantityInput.value = value - 1;
    });
}

if (plusBtn && quantityInput) {
    plusBtn.addEventListener("click", function () {
        let value = parseInt(quantityInput.value) || 1;
        let max = parseInt(quantityInput.max);
        if (value < max) quantityInput.value = value + 1;
    });
}

// Like / Dislike AJAX
// Like / Dislike AJAX Function
async function submitReaction(type) {
    const likeBtn = document.getElementById('likeBtn');
    const dislikeBtn = document.getElementById('dislikeBtn');
    const likeCountEl = document.getElementById('likeCount');
    const dislikeCountEl = document.getElementById('dislikeCount');

    try {
        const fd = new FormData();
        fd.append('product_id', productId);
        fd.append('reaction', type);

        const res = await fetch('api/reaction.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        });
        const data = await res.json();

        if (data.success) {
            likeCountEl.textContent = data.likes;
            dislikeCountEl.textContent = data.dislikes;
            likeBtn.classList.toggle('active', data.user_reaction === 'like');
            dislikeBtn.classList.toggle('active', data.user_reaction === 'dislike');
        } else {
            alert(data.message || 'Could not update reaction.');
        }
    } catch (e) {
        console.error('Reaction error:', e);
    }
}

// Star Rating AJAX
// Star Rating AJAX Function
async function submitRating(stars) {
    const feedbackEl = document.getElementById('ratingFeedback');
    const avgStarsEl = document.getElementById('avgStarsDisplay');
    const avgTextEl = document.getElementById('avgRatingText');

    try {
        const fd = new FormData();
        fd.append('product_id', productId);
        fd.append('rating', stars);

        const res = await fetch('api/rating.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        });
        const data = await res.json();

        if (data.success) {
            document.querySelectorAll('#starPicker i').forEach(el => {
                const val = parseInt(el.getAttribute('data-value')) || 0;
                el.classList.toggle('active', val <= stars);
            });

            if (feedbackEl) {
                feedbackEl.textContent = data.message || 'Thank you for rating!';
            }

            const avgVal = parseFloat(data.avg_rating) || stars;
            const rounded = Math.round(avgVal);
            let starStr = '';
            for (let i = 1; i <= 5; i++) {
                starStr += (i <= rounded ? '★' : '☆');
            }
            if (avgStarsEl) avgStarsEl.textContent = starStr;
            if (avgTextEl) avgTextEl.textContent = `${avgVal.toFixed(1)} / 5.0 (${data.total_ratings} Ratings)`;
        } else {
            if (feedbackEl) feedbackEl.textContent = data.message || 'Could not save rating.';
        }
    } catch (e) {
        console.error('Rating error:', e);
    }
}

// Comment AJAX
document.getElementById('commentForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const commentInput = document.getElementById('commentText');
    const feedback = document.getElementById('commentFeedback');
    const text = commentInput.value.trim();
    if (!text) return;

    try {
        const fd = new FormData();
        fd.append('product_id', productId);
        fd.append('comment', text);

        const res = await fetch('api/comment.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.status === 'unauthorized') {
            window.location.href = 'auth/login.php';
            return;
        }
        if (data.success && data.data) {
            commentInput.value = '';
            feedback.textContent = '';
            const noMsg = document.getElementById('noCommentsMsg');
            if (noMsg) noMsg.remove();

            const container = document.getElementById('commentsContainer');
            const div = document.createElement('div');
            div.className = 'comment-item';
            div.innerHTML = `<strong>${data.data.user_name}</strong><small>${data.data.created_at}</small><p>${data.data.comment}</p>`;
            container.prepend(div);

            const totalEl = document.getElementById('commentTotal');
            totalEl.textContent = parseInt(totalEl.textContent) + 1;
        } else {
            feedback.textContent = data.message || 'Could not post comment.';
        }
    } catch (err) {
        feedback.textContent = 'Error posting comment.';
    }
});
</script>

<?php include_once __DIR__ . '/chat.php'; ?>
</body>
</html>