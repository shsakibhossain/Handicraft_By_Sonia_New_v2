<?php
/* ==========================================
   HANDICRAFT BY SONIA - CUSTOMER FAQ PAGE
========================================== */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';

$isLoggedIn = !empty($_SESSION['user_id']);
$userName = $_SESSION['user_name']
    ?? $_SESSION['username']
    ?? $_SESSION['name']
    ?? 'Customer';

$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $cItem) {
        $cartCount += (int)($cItem['quantity'] ?? 1);
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

/* Escape Function */
function faq_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* Load Active FAQs */
$faqs = [];
$faqError = false;

try {
    $stmt = $pdo->prepare("
        SELECT id, question, answer
        FROM faqs
        WHERE status = 'active'
        ORDER BY sort_order ASC, id DESC
    ");

    $stmt->execute();
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log('FAQ loading error: ' . $e->getMessage());
    $faqError = true;
}

$faqCategories = [
    'General' => [
        'What is Handicraft by Sonia?',
        'Are your products handmade?'
    ],
    'Orders & Delivery' => [
        'How can I place an order?',
        'How can I track my order?'
    ],
    'Customization' => [
        'Can I customize a product?',
        'How can I request a custom order?'
    ]
];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Frequently Asked Questions | Handicraft by Sonia</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="assets/css/style.css">

    <style>

        .faq-page {
            width: min(1000px, 92%);
            margin: 0 auto;
            padding: 55px 0 75px;
            font-family: 'Poppins', sans-serif;
        }

        .faq-hero {
            padding: 55px 25px;
            margin-bottom: 35px;
            border-radius: 22px;
            background: linear-gradient(120deg, #f3eaff, #fbf7ff);
            text-align: center;
        }

        .faq-hero-icon {
            display: inline-grid;
            place-items: center;
            width: 62px;
            height: 62px;
            margin-bottom: 15px;
            border-radius: 50%;
            background: #fff;
            color: #7028d8;
            font-size: 27px;
            box-shadow: 0 5px 18px rgba(112,40,216,.10);
        }

        .faq-hero h1 {
            margin: 0 0 12px;
            color: #32145f;
            font-family: 'Playfair Display', Georgia, serif;
            font-size: clamp(30px, 5vw, 43px);
        }

        .faq-hero p {
            max-width: 600px;
            margin: 0 auto;
            color: #756783;
            font-size: 13px;
            line-height: 1.8;
        }

        .faq-search {
            position: relative;
            max-width: 570px;
            margin: 25px auto 0;
        }

        .faq-search i {
            position: absolute;
            top: 50%;
            left: 17px;
            transform: translateY(-50%);
            color: #8b5cf6;
        }

        .faq-search input {
            width: 100%;
            padding: 14px 17px 14px 45px;
            border: 1px solid #e5d8f5;
            border-radius: 12px;
            outline: none;
            background: #fff;
            color: #32145f;
            font-family: inherit;
            font-size: 13px;
        }

        .faq-search input:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139,92,246,.10);
        }

        .faq-section-title {
            margin: 35px 0 18px;
            color: #54249b;
            font-size: 17px;
            font-weight: 600;
        }

        .faq-list {
            display: grid;
            gap: 13px;
        }

        .faq-item {
            overflow: hidden;
            border: 1px solid #eee5fa;
            border-radius: 13px;
            background: #fff;
            box-shadow: 0 5px 18px rgba(50,20,95,.035);
        }

        .faq-question {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            width: 100%;
            padding: 19px 21px;
            border: 0;
            background: transparent;
            color: #32145f;
            text-align: left;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .faq-question i {
            flex-shrink: 0;
            color: #8b5cf6;
            font-size: 17px;
            transition: transform .25s ease;
        }

        .faq-item.active .faq-question {
            color: #7028d8;
        }

        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }

        .faq-answer {
            display: none;
            padding: 0 21px 19px;
            color: #756783;
            font-size: 13px;
            line-height: 1.9;
            white-space: normal;
        }

        .faq-item.active .faq-answer {
            display: block;
        }

        .faq-empty {
            padding: 45px 20px;
            border: 1px dashed #d9c6f3;
            border-radius: 15px;
            background: #faf7ff;
            text-align: center;
        }

        .faq-empty i {
            display: block;
            margin-bottom: 12px;
            color: #8b5cf6;
            font-size: 38px;
        }

        .faq-empty h3 {
            color: #54249b;
            font-size: 17px;
        }

        .faq-empty p {
            color: #756783;
            font-size: 13px;
        }

        .faq-contact {
            margin-top: 35px;
            padding: 30px 20px;
            border-radius: 17px;
            background: #f6f0ff;
            text-align: center;
        }

        .faq-contact h2 {
            margin: 0 0 10px;
            color: #32145f;
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 25px;
        }

        .faq-contact p {
            margin: 0 0 18px;
            color: #756783;
            font-size: 13px;
        }

        .faq-contact a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 9px;
            background: #7028d8;
            color: #fff;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: .2s;
        }

        .faq-contact a:hover {
            background: #54249b;
            transform: translateY(-2px);
        }

        .faq-error {
            padding: 20px;
            border-radius: 12px;
            background: #fff0f0;
            color: #991b1b;
            font-size: 13px;
            text-align: center;
        }

        .faq-no-result {
            display: none;
            padding: 20px;
            color: #756783;
            text-align: center;
            font-size: 13px;
        }

        @media(max-width: 600px) {

            .faq-page {
                padding: 35px 0 50px;
            }

            .faq-hero {
                padding: 38px 18px;
            }

            .faq-question {
                padding: 16px;
                font-size: 12px;
            }

            .faq-answer {
                padding: 0 16px 16px;
                font-size: 12px;
            }

        }

    </style>

</head>

<body>

    <!-- HEADER -->

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

            <a href="faq.php" class="active">FAQ</a>

        </nav>


        <div class="header-actions">

            <a href="shop.php" class="icon-link" title="Search">
                <i class="bi bi-search"></i>
            </a>

            <a href="wishlist.php" class="icon-link notification" title="Wishlist">
                <i class="bi bi-heart"></i>
                <span><?php echo (int)$wishlistCount; ?></span>
            </a>

            <a href="cart.php" class="icon-link notification" title="Cart">
                <i class="bi bi-cart3"></i>
                <span><?php echo (int)$cartCount; ?></span>
            </a>

            <?php if ($isLoggedIn): ?>
                <a href="my-account.php" class="welcome-user" title="My Account">
                    <i class="bi bi-person-circle"></i>
                    <span>Hi, <?php echo faq_escape($userName); ?></span>
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


    <!-- FAQ CONTENT -->

    <main class="faq-page">

        <section class="faq-hero">

            <div class="faq-hero-icon">
                <i class="bi bi-question-circle"></i>
            </div>

            <h1>Frequently Asked Questions</h1>

            <p>
                Have questions about our products, orders or services?
                Find answers to the most common questions below.
            </p>


            <div class="faq-search">

                <i class="bi bi-search"></i>

                <input
                    type="search"
                    id="faqSearch"
                    placeholder="Search your question..."
                    autocomplete="off">

            </div>

        </section>


        <?php if ($faqError): ?>

            <div class="faq-error">
                Sorry! We could not load FAQs right now.
                Please try again later.
            </div>

        <?php elseif (empty($faqs)): ?>

            <div class="faq-empty">

                <i class="bi bi-chat-square-text"></i>

                <h3>No FAQs Available Yet</h3>

                <p>
                    We are preparing helpful answers for our customers.
                    Please check back later.
                </p>

            </div>

        <?php else: ?>

            <h2 class="faq-section-title">
                <i class="bi bi-chat-left-text"></i>
                All Questions
            </h2>


            <div class="faq-list" id="faqList">

                <?php foreach ($faqs as $index => $faq): ?>

                    <article class="faq-item">

                        <button
                            type="button"
                            class="faq-question"
                            aria-expanded="false">

                            <span>
                                <?php echo faq_escape($faq['question']); ?>
                            </span>

                            <i class="bi bi-chevron-down"></i>

                        </button>


                        <div class="faq-answer">

                            <?php echo nl2br(faq_escape($faq['answer'])); ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>


            <div class="faq-no-result" id="faqNoResult">
                <i class="bi bi-search"></i>
                No matching questions found.
            </div>

        <?php endif; ?>


        <!-- CONTACT SUPPORT -->

        <section class="faq-contact">

            <h2>Still Have Questions?</h2>

            <p>
                Can't find the answer you are looking for?
                Feel free to contact us.
            </p>

            <a href="about.php#contact">

                <i class="bi bi-envelope"></i>

                Contact Us

            </a>

        </section>

    </main>


    <!-- FOOTER -->

    <footer>

        <p>
            © 2026 Handicraft by Sonia |
            All Rights Reserved By Team Triple Pixel
        </p>

    </footer>


    <!-- JAVASCRIPT -->

    <script src="assets/js/script.js"></script>

    <script>

        document.addEventListener('DOMContentLoaded', function () {

            const faqItems = document.querySelectorAll('.faq-item');

            /* Accordion */

            faqItems.forEach(function (item) {

                const question = item.querySelector('.faq-question');

                question.addEventListener('click', function () {

                    const isOpen = item.classList.contains('active');

                    /* Close other FAQs */

                    faqItems.forEach(function (otherItem) {

                        otherItem.classList.remove('active');

                        otherItem.querySelector('.faq-question')
                            .setAttribute('aria-expanded', 'false');

                    });

                    /* Toggle clicked FAQ */

                    if (!isOpen) {

                        item.classList.add('active');

                        question.setAttribute('aria-expanded', 'true');

                    }

                });

            });


            /* FAQ Search */

            const searchInput = document.getElementById('faqSearch');

            const noResult = document.getElementById('faqNoResult');

            if (searchInput) {

                searchInput.addEventListener('input', function () {

                    const searchText = this.value.toLowerCase().trim();

                    let visibleCount = 0;

                    faqItems.forEach(function (item) {

                        const question = item.querySelector('.faq-question')
                            .innerText.toLowerCase();

                        const answer = item.querySelector('.faq-answer')
                            .innerText.toLowerCase();

                        const matched =
                            question.includes(searchText) ||
                            answer.includes(searchText);

                        item.style.display = matched ? '' : 'none';

                        if (matched) {

                            visibleCount++;

                        } else {

                            item.classList.remove('active');

                            item.querySelector('.faq-question')
                                .setAttribute('aria-expanded', 'false');

                        }

                    });

                    if (noResult) {

                        noResult.style.display =
                            visibleCount === 0 ? 'block' : 'none';

                    }

                });

            }

        });

    </script>

    <?php include_once __DIR__ . '/chat.php'; ?>

</body>

</html>