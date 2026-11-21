<?php
/* ==========================================
   HANDICRAFT BY SONIA - ABOUT & CONTACT
   PHP + MySQL + PDO
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

/* ==========================================
   LOAD CONTENT FROM ADMIN CONTENT MANAGEMENT
========================================== */

$defaultSiteContent = [
    'hero_eyebrow' => 'Handmade with love',
    'hero_heading' => 'Beautiful Creations, Made to Make You Special.',
    'hero_text' => 'Welcome to Handicraft by Sonia — a place for thoughtful handmade creations. We believe that every handmade piece carries its own beauty, care and story. Our goal is to make special moments even more memorable with creative designs.',
    'story_date' => 'A Journey That Began in 2017',
    'story_text' => "Handicraft by Sonia began in 2017 with a simple love for handmade creations. My mother loved making crystal pearl bags, while I enjoyed paper crafting. Together, we started creating beautiful hijab pins with floral designs, ribbon bows and delicate pearl details.\n\nWhen we shared our creations on Facebook, the love and appreciation from everyone inspired us to take our little hobby further. Soon, we received our very first order, and that moment became the beginning of something special.\n\nWhat started with hijab pins gradually grew into a collection of jewellery — from everyday pearl accessories to party wear, Holud and bridal jewellery.\n\nToday, our mother-daughter journey continues with one simple dream: to make your every special moment more beautiful with jewellery handcrafted with love.",
    'who_we_are' => 'A Mother-Daughter Journey\nHandicraft by Sonia is a handmade jewellery and accessories brand born from a mother-daughter passion for creativity. Since 2017, we have been turning our love for handmade art into beautiful creations for every occasion.',
    'what_we_do' => 'Jewellery for Every Occasion\nFrom delicate everyday jewellery to elegant party wear and stunning bridal collections, we create beautiful handmade accessories. We also offer customized designs to match your personal style and preferences.',
    'mission' => "Making Every Moment Special\nOur mission is to bring happiness through unique handmade creations, thoughtful designs and quality craftsmanship, making every customer's special moments even more memorable.",
    'vision' => 'Growing Through Creativity\nOur vision is to grow as a trusted handmade jewellery brand, inspiring creativity and bringing beautiful, personalized designs to customers for every stage and celebration of life.',
    'why_heading' => 'Why Choose Us?',
    'why_description' => 'We want every customer to enjoy a thoughtful, simple and memorable handmade shopping experience.',
    'why_care' => 'We value the effort and attention that go into handmade creations.',
    'why_design' => 'Explore a variety of designs and request custom ideas that match your taste.',
    'why_gifts' => 'Find handmade items that can make celebrations and special days more meaningful.',
    'why_service' => 'We welcome your questions, feedback and ideas. Your experience matters to us.',
    'contact_email' => 'handicraft.by.sonia@gmail.com',
    'contact_phone' => '01537312870',
    'facebook_url' => 'https://www.facebook.com/Handicraft.by.sonia',
    'instagram_url' => 'https://www.instagram.com/handicraft_by_soniaa',
    'contact_address' => '',
    'google_maps_embed_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3689.914871099135!2d91.81231170000002!3d22.356842999999994!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x30acd8ed2cc0611d%3A0x94e9ee636ae94ed6!2sSouth%20Kulshi%20Road%20%26%20S%20Khulshi%20Rd%2C%20Chittagong!5e0!3m2!1sen!2sbd!4v1793560436278!5m2!1sen!2sbd',
    'about_video_1' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
    'about_video_2' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerEscapes.mp4',
    'about_video_3' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerFun.mp4'
];

$siteContent = $defaultSiteContent;

try {
    $settingsQuery = $pdo->query("SELECT setting_key, setting_value FROM site_settings");

    foreach ($settingsQuery->fetchAll(PDO::FETCH_ASSOC) as $settingRow) {
        if (array_key_exists($settingRow['setting_key'], $siteContent)) {
            $siteContent[$settingRow['setting_key']] =
                (string)($settingRow['setting_value'] ?? '');
        }
    }
} catch (Throwable $e) {
    error_log('About page settings error: ' . $e->getMessage());
}

$googleMapsEmbedUrl = trim($siteContent['google_maps_embed_url']);

/* Safe link helper */
function about_safe_url($url): string
{
    $url = trim((string)$url);

    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);

    if (
        !$parts ||
        !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
    ) {
        return '';
    }

    return $url;
}

/* Escape Function */
if (!function_exists('about_escape')) {
    function about_escape($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/* Contact Form Variables */
$contactSuccess = '';
$contactError = '';

$formData = [
    'name' => '',
    'email' => '',
    'subject' => '',
    'message' => ''
];

/* CSRF Token */
if (empty($_SESSION['contact_csrf_token'])) {
    $_SESSION['contact_csrf_token'] = bin2hex(random_bytes(32));
}

/* Contact Form Submission */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_contact_message'])) {

    foreach ($formData as $field => $value) {
        $formData[$field] = trim((string)($_POST[$field] ?? ''));
    }

    $submittedToken = (string)($_POST['contact_csrf_token'] ?? '');

    if (!hash_equals($_SESSION['contact_csrf_token'], $submittedToken)) {

        $contactError = 'Your session has expired. Please refresh and try again.';
    } elseif (
        $formData['name'] === '' ||
        $formData['email'] === '' ||
        $formData['subject'] === '' ||
        $formData['message'] === ''
    ) {

        $contactError = 'Please complete all fields.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {

        $contactError = 'Please enter a valid email address.';
    } elseif (
        strlen($formData['name']) > 100 ||
        strlen($formData['email']) > 150 ||
        strlen($formData['subject']) > 180 ||
        strlen($formData['message']) > 5000
    ) {

        $contactError = 'One or more fields are too long.';
    } else {

        try {

            $insert = $pdo->prepare("
                INSERT INTO contact_messages
                (user_id, name, email, subject, message)
                VALUES
                (:user_id, :name, :email, :subject, :message)
            ");

            $insert->execute([
                ':user_id' => $isLoggedIn ? (int)$_SESSION['user_id'] : null,
                ':name' => $formData['name'],
                ':email' => $formData['email'],
                ':subject' => $formData['subject'],
                ':message' => $formData['message']
            ]);

            $contactSuccess = 'Thank you! Your message has been sent successfully.';

            $formData = [
                'name' => '',
                'email' => '',
                'subject' => '',
                'message' => ''
            ];

            $_SESSION['contact_csrf_token'] = bin2hex(random_bytes(32));
        } catch (Throwable $e) {

            error_log('Contact form error: ' . $e->getMessage());

            $contactError = 'Sorry, we could not send your message. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>About & Contact | Handicraft by Sonia</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .about-contact-page {
            width: min(1180px, 92%);
            margin: 0 auto;
            color: #32145f;
        }

        .ac-hero {
            display: grid;
            grid-template-columns: 1.05fr .95fr;
            align-items: center;
            gap: 42px;
            padding: 68px 0 58px;
        }

        .ac-eyebrow {
            display: inline-block;
            margin-bottom: 13px;
            color: #7028d8;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .ac-hero h1,
        .ac-section-heading h2 {
            font-family: 'Playfair Display', Georgia, serif;
            color: #32145f;
        }

        .ac-hero h1 {
            margin: 0 0 17px;
            font-size: clamp(34px, 5vw, 54px);
            line-height: 1.16;
        }

        .ac-hero h1 span {
            color: #8b5cf6;
        }

        .ac-hero-copy,
        .ac-section-heading p {
            color: #756783;
            font-size: 14px;
            line-height: 1.9;
        }

        .ac-hero-image {
            min-height: 330px;
            height: 360px;
            overflow: hidden;
            border-radius: 28px;
            background: #f3eaff;
            box-shadow: 0 18px 45px rgba(84, 36, 155, .12);
        }

        .ac-hero-image img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .ac-section {
            padding: 58px 0;
        }

        .ac-soft-section {
            width: 100%;
            background: #faf7ff;
        }

        .ac-soft-section .about-contact-page {
            padding-top: 58px;
            padding-bottom: 58px;
        }

        .ac-section-heading {
            max-width: 680px;
            margin: 0 auto 34px;
            text-align: center;
        }

        .ac-section-heading h2 {
            margin: 0 0 12px;
            font-size: clamp(28px, 4vw, 38px);
        }

        .ac-story-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }

        .ac-story-card,
        .ac-value-card,
        .ac-why-card,
        .ac-contact-card,
        .ac-form-card,
        .ac-map-card {
            border: 1px solid #eee5fa;
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 8px 28px rgba(50, 20, 95, .045);
        }

        .ac-story-card {
            padding: 28px;
        }

        .ac-story-card h3,
        .ac-value-card h3,
        .ac-why-card h3,
        .ac-contact-card h3,
        .ac-form-card h3,
        .ac-map-card h3 {
            margin: 0 0 10px;
            color: #54249b;
            font-size: 17px;
        }

        .ac-story-card p,
        .ac-value-card p,
        .ac-why-card p,
        .ac-contact-card p,
        .ac-map-card p {
            margin: 0;
            color: #756783;
            font-size: 13px;
            line-height: 1.85;
        }

        .ac-value-grid,
        .ac-why-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-top: 22px;
        }

        .ac-value-card,
        .ac-why-card {
            padding: 24px;
        }

        .ac-icon {
            display: grid;
            place-items: center;
            width: 48px;
            height: 48px;
            margin-bottom: 15px;
            border-radius: 15px;
            background: #f1e8ff;
            color: #7028d8;
            font-size: 22px;
        }

        .ac-contact-layout {
            display: grid;
            grid-template-columns: .85fr 1.15fr;
            gap: 24px;
            align-items: start;
        }

        .ac-contact-info {
            display: grid;
            gap: 14px;
        }

        .ac-contact-card {
            display: flex;
            gap: 15px;
            align-items: flex-start;
            padding: 19px;
        }

        .ac-contact-card .ac-icon {
            flex: 0 0 46px;
            width: 46px;
            height: 46px;
            margin: 0;
        }

        .ac-contact-card h3 {
            margin-bottom: 5px;
            font-size: 14px;
        }

        .ac-contact-card a {
            color: #7028d8;
            overflow-wrap: anywhere;
        }

        .ac-form-card {
            padding: 28px;
        }

        .ac-form-card h3 {
            margin-bottom: 20px;
        }

        .ac-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .ac-form-field {
            display: flex;
            flex-direction: column;
            gap: 7px;
            margin-bottom: 15px;
        }

        .ac-form-field.full {
            grid-column: 1 / -1;
        }

        .ac-form-field label {
            color: #514064;
            font-size: 12px;
            font-weight: 600;
        }

        .ac-form-field input,
        .ac-form-field textarea {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid #e7dcef;
            border-radius: 10px;
            outline: none;
            background: #fdfbff;
            color: #32145f;
            font: inherit;
            font-size: 13px;
        }

        .ac-form-field input:focus,
        .ac-form-field textarea:focus {
            border-color: #9b72e8;
            box-shadow: 0 0 0 3px rgba(112, 40, 216, .09);
        }

        .ac-form-field textarea {
            min-height: 130px;
            resize: vertical;
        }

        .ac-submit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 13px 22px;
            border: 0;
            border-radius: 10px;
            background: linear-gradient(110deg, #7028d8, #9b72e8);
            color: #fff;
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: .2s ease;
        }

        .ac-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 18px rgba(112, 40, 216, .2);
        }

        .ac-alert {
            margin-bottom: 17px;
            padding: 13px 15px;
            border-radius: 10px;
            font-size: 13px;
            line-height: 1.6;
        }

        .ac-alert.success {
            background: #e9f9ef;
            color: #166534;
            border: 1px solid #bbebcc;
        }

        .ac-alert.error {
            background: #fff0f0;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .ac-map-card {
            margin-top: 25px;
            padding: 24px;
        }

        .ac-map-frame {
            display: grid;
            place-items: center;
            min-height: 300px;
            margin-top: 16px;
            overflow: hidden;
            border: 1px dashed #cbb6ed;
            border-radius: 15px;
            background: linear-gradient(135deg, #faf7ff, #f0e8ff);
            text-align: center;
        }

        .ac-map-frame iframe {
            display: block;
            width: 100%;
            height: 330px;
            border: 0;
        }

        .ac-map-placeholder {
            max-width: 420px;
            padding: 25px;
        }

        .ac-map-placeholder i {
            display: block;
            margin-bottom: 12px;
            color: #8b5cf6;
            font-size: 42px;
        }

        .ac-map-placeholder strong {
            display: block;
            margin-bottom: 7px;
            color: #54249b;
        }

        .ac-map-placeholder p {
            color: #756783;
            font-size: 12px;
            line-height: 1.8;
        }

        /* 3 Portrait Videos Grid */
        .ac-portrait-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 22px;
        }

        .ac-portrait-card {
            margin-top: 0;
            text-align: center;
            padding: 18px;
        }

        .ac-portrait-card h3 {
            font-size: 15px;
            margin-bottom: 12px;
        }

        .ac-portrait-video {
            width: 100%;
            max-width: 280px;
            aspect-ratio: 9 / 16;
            max-height: 460px;
            margin: 0 auto;
            border-radius: 16px;
            background: #1e0b36;
            object-fit: contain;
            display: block;
            box-shadow: 0 10px 25px rgba(50, 20, 95, 0.14);
        }

        @media (max-width: 900px) {
            .ac-portrait-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 850px) {
            .ac-hero {
                gap: 25px;
                padding: 48px 0;
            }

            .ac-contact-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 650px) {

            .ac-hero,
            .ac-story-grid,
            .ac-portrait-grid {
                grid-template-columns: 1fr;
            }

            .ac-hero {
                padding: 38px 0;
            }

            .ac-hero-image {
                height: 250px;
                min-height: 250px;
            }

            .ac-section {
                padding: 42px 0;
            }

            .ac-soft-section .about-contact-page {
                padding-top: 42px;
                padding-bottom: 42px;
            }

            .ac-value-grid,
            .ac-why-grid,
            .ac-form-grid {
                grid-template-columns: 1fr;
            }

            .ac-form-field.full {
                grid-column: auto;
            }

            .ac-story-card,
            .ac-value-card,
            .ac-why-card,
            .ac-form-card,
            .ac-map-card {
                padding: 20px;
            }
        }

        .ac-story-layout {
            display: grid;
            grid-template-columns: .9fr 1.1fr;
            gap: 32px;
            align-items: center;
            margin-bottom: 28px;
        }

        .ac-story-slider {
            position: relative;
            height: 390px;
            min-height: 300px;
            overflow: hidden;
            border-radius: 22px;
            background: #f3eaff;
            box-shadow: 0 14px 35px rgba(84, 36, 155, .12);
        }

        .ac-story-slider .ac-story-slide {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0;
            transition: opacity 1s ease-in-out;
        }

        .ac-story-slider .ac-story-slide.active {
            opacity: 1;
        }

        .ac-story-dots {
            position: absolute;
            bottom: 16px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 9px;
            z-index: 2;
        }

        .ac-story-dot {
            width: 10px;
            height: 10px;
            padding: 0;
            border: 0;
            border-radius: 20px;
            background: rgba(255, 255, 255, .75);
            cursor: pointer;
            transition: .3s;
        }

        .ac-story-dot.active {
            width: 25px;
            background: #7c3aed;
        }

        .ac-story-content .ac-section-heading {
            max-width: none;
            margin: 0 0 18px;
            text-align: left;
        }

        .ac-story-content .ac-section-heading h2 {
            margin-bottom: 5px;
        }

        .ac-story-content .ac-story-description {
            color: #756783;
            font-size: 13px;
            line-height: 1.9;
            text-align: left;
        }

        @media (max-width: 650px) {
            .ac-story-layout {
                grid-template-columns: 1fr;
                gap: 22px;
            }

            .ac-story-slider {
                height: 280px;
                min-height: 250px;
            }
        }
    </style>
</head>

<body>

    <!-- WEBSITE HEADER -->
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
            <a href="about.php" class="active">About Us</a>
            <a href="faq.php">FAQ</a>
        </nav>

        <div class="header-actions">

            <a href="shop.php" class="icon-link" aria-label="Search" title="Search">
                <i class="bi bi-search"></i>
            </a>

            <a href="wishlist.php" class="icon-link notification" aria-label="Wishlist" title="Wishlist">
                <i class="bi bi-heart"></i>
                <span><?php echo (int)$wishlistCount; ?></span>
            </a>

            <a href="cart.php" class="icon-link notification" aria-label="Cart" title="Cart">
                <i class="bi bi-cart3"></i>
                <span><?php echo (int)$cartCount; ?></span>
            </a>

            <?php if ($isLoggedIn): ?>
                <a href="my-account.php" class="welcome-user" title="My Account">
                    <i class="bi bi-person-circle"></i>
                    <span>Hi, <?php echo about_escape($userName); ?></span>
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


    <main>

        <!-- ABOUT HERO -->
        <section class="about-contact-page ac-hero">

            <div>
                <span class="ac-eyebrow">
                    <?php echo about_escape($siteContent['hero_eyebrow']); ?>
                </span>

                <h1>
                    <?php echo nl2br(about_escape($siteContent['hero_heading'])); ?>
                </h1>

                <p class="ac-hero-copy">
                    <?php echo nl2br(about_escape($siteContent['hero_text'])); ?>
                </p>
            </div>

            <div class="ac-hero-image">
                <img src="assets/images/about.jpg" alt="Handmade jewellery collection">
            </div>

        </section>


        <!-- OUR STORY -->
        <section class="ac-soft-section">

            <div class="about-contact-page">

                <div class="ac-story-layout">

                    <div class="ac-story-slider" id="ourStorySlider">

                        <img
                            src="assets/images/01.png"
                            alt="Handmade jewellery by Handicraft by Sonia"
                            class="ac-story-slide active">

                        <img
                            src="assets/images/02.png"
                            alt="Handmade craft collection by Handicraft by Sonia"
                            class="ac-story-slide">

                        <div class="ac-story-dots">
                            <button type="button" class="ac-story-dot active"
                                aria-label="Show first story image"></button>

                            <button type="button" class="ac-story-dot"
                                aria-label="Show second story image"></button>
                        </div>

                    </div>

                    <div class="ac-story-content">

                        <div class="ac-section-heading">
                            <span class="ac-eyebrow">Get to know us</span>
                            <h2>Our Story</h2>

                            <h5>
                                <?php echo about_escape($siteContent['story_date']); ?>
                            </h5>
                        </div>

                        <p class="ac-story-description">
                            <?php echo nl2br(about_escape($siteContent['story_text'])); ?>
                        </p>

                    </div>

                </div>


                <div class="ac-story-grid">

                    <article class="ac-story-card">
                        <h3>Who We Are</h3>

                        <p>
                            <?php echo nl2br(about_escape($siteContent['who_we_are'])); ?>
                        </p>
                    </article>


                    <article class="ac-story-card">
                        <h3>What We Do</h3>

                        <p>
                            <?php echo nl2br(about_escape($siteContent['what_we_do'])); ?>
                        </p>
                    </article>

                </div>


                <!-- MISSION AND VISION -->
                <div class="ac-value-grid">

                    <article class="ac-value-card">

                        <div class="ac-icon">
                            <i class="bi bi-heart"></i>
                        </div>

                        <h3>Our Mission</h3>

                        <p>
                            <?php echo nl2br(about_escape($siteContent['mission'])); ?>
                        </p>

                    </article>


                    <article class="ac-value-card">

                        <div class="ac-icon">
                            <i class="bi bi-stars"></i>
                        </div>

                        <h3>Our Vision</h3>

                        <p>
                            <?php echo nl2br(about_escape($siteContent['vision'])); ?>
                        </p>

                    </article>

                </div>

            </div>

        </section>


        <!-- WHY CHOOSE US -->
        <section class="about-contact-page ac-section">

            <div class="ac-section-heading">

                <span class="ac-eyebrow">The little things matter</span>

                <h2>
                    <?php echo about_escape($siteContent['why_heading']); ?>
                </h2>

                <p>
                    <?php echo nl2br(about_escape($siteContent['why_description'])); ?>
                </p>

            </div>


            <div class="ac-why-grid">

                <article class="ac-why-card">

                    <div class="ac-icon">
                        <i class="bi bi-hand-thumbs-up"></i>
                    </div>

                    <h3>Made with Care</h3>

                    <p>
                        <?php echo nl2br(about_escape($siteContent['why_care'])); ?>
                    </p>

                </article>


                <article class="ac-why-card">

                    <div class="ac-icon">
                        <i class="bi bi-palette"></i>
                    </div>

                    <h3>Creative Designs</h3>

                    <p>
                        <?php echo nl2br(about_escape($siteContent['why_design'])); ?>
                    </p>

                </article>


                <article class="ac-why-card">

                    <div class="ac-icon">
                        <i class="bi bi-gift"></i>
                    </div>

                    <h3>Thoughtful Gifts</h3>

                    <p>
                        <?php echo nl2br(about_escape($siteContent['why_gifts'])); ?>
                    </p>

                </article>


                <article class="ac-why-card">

                    <div class="ac-icon">
                        <i class="bi bi-chat-heart"></i>
                    </div>

                    <h3>Customer Care</h3>

                    <p>
                        <?php echo nl2br(about_escape($siteContent['why_service'])); ?>
                    </p>

                </article>

            </div>

        </section>


        <!-- 3 PORTRAIT VIDEOS SHOWCASE SECTION (Dynamic from Admin CMS) -->
        <?php
            $portraitVideos = [
                [
                    'title'  => 'Crafting Showcase 01',
                    'url'    => trim((string)($siteContent['about_video_1'] ?? '')),
                    'poster' => 'assets/images/01.png'
                ],
                [
                    'title'  => 'Crafting Showcase 02',
                    'url'    => trim((string)($siteContent['about_video_2'] ?? '')),
                    'poster' => 'assets/images/02.png'
                ],
                [
                    'title'  => 'Crafting Showcase 03',
                    'url'    => trim((string)($siteContent['about_video_3'] ?? '')),
                    'poster' => 'assets/images/about.jpg'
                ]
            ];
        ?>
        <section class="about-contact-page ac-section" style="padding-top: 10px;">
            <div class="ac-section-heading">
                <span class="ac-eyebrow">Behind The Scenes</span>
                <h2>Watch How Handmade Jewellery Is Crafted</h2>
                <p>Experience the delicate artistry behind our floral and pearl handmade accessories.</p>
            </div>

            <div class="ac-portrait-grid">
                <?php foreach ($portraitVideos as $vid): ?>
                    <?php if ($vid['url'] !== ''): ?>
                        <div class="ac-map-card ac-portrait-card">
                            <h3>
                                <i class="bi bi-play-circle-fill"></i> <?php echo about_escape($vid['title']); ?>
                            </h3>
                            <video
                                class="ac-portrait-video"
                                controls
                                preload="metadata"
                                poster="<?php echo about_escape($vid['poster']); ?>">
                                <source src="<?php echo about_escape($vid['url']); ?>" type="video/mp4">
                                Your browser does not support HTML5 video.
                            </video>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>


        <!-- CONTACT SECTION -->
        <section class="ac-soft-section" id="contact">

            <div class="about-contact-page ac-section">

                <div class="ac-section-heading">

                    <span class="ac-eyebrow">We are here to help</span>

                    <h2>Get In Touch</h2>

                    <p>
                        Have a question, feedback or a custom idea?
                        Send us a message. We would love to hear from you.
                    </p>

                </div>


                <div class="ac-contact-layout">

                    <!-- CONTACT INFORMATION -->
                    <div class="ac-contact-info">

                        <div class="ac-contact-card">

                            <div class="ac-icon">
                                <i class="bi bi-envelope"></i>
                            </div>

                            <div>
                                <h3>Email Address</h3>

                                <p>
                                    <a href="mailto:<?php echo about_escape($siteContent['contact_email']); ?>">
                                        <?php echo about_escape($siteContent['contact_email']); ?>
                                    </a>
                                </p>
                            </div>

                        </div>


                        <div class="ac-contact-card">

                            <div class="ac-icon">
                                <i class="bi bi-telephone"></i>
                            </div>

                            <div>
                                <h3>Phone Number</h3>

                                <p>
                                    <a href="tel:<?php echo about_escape($siteContent['contact_phone']); ?>">
                                        <?php echo about_escape($siteContent['contact_phone']); ?>
                                    </a>
                                </p>
                            </div>

                        </div>


                        <div class="ac-contact-card">

                            <div class="ac-icon">
                                <i class="bi bi-facebook"></i>
                            </div>

                            <div>
                                <h3>Facebook Page</h3>

                                <?php
                                $facebookUrl = about_safe_url($siteContent['facebook_url']);
                                ?>

                                <p>
                                    <?php if ($facebookUrl !== ''): ?>
                                        <a href="<?php echo about_escape($facebookUrl); ?>"
                                            target="_blank"
                                            rel="noopener noreferrer">
                                            <?php echo about_escape($facebookUrl); ?>
                                        </a>
                                    <?php endif; ?>
                                </p>
                            </div>

                        </div>


                        <div class="ac-contact-card">

                            <div class="ac-icon">
                                <i class="bi bi-instagram"></i>
                            </div>

                            <div>
                                <h3>Instagram</h3>

                                <?php
                                $instagramUrl = about_safe_url($siteContent['instagram_url']);
                                ?>

                                <p>
                                    <?php if ($instagramUrl !== ''): ?>
                                        <a href="<?php echo about_escape($instagramUrl); ?>"
                                            target="_blank"
                                            rel="noopener noreferrer">
                                            <?php echo about_escape($instagramUrl); ?>
                                        </a>
                                    <?php endif; ?>
                                </p>
                            </div>

                        </div>


                        <?php if (trim($siteContent['contact_address']) !== ''): ?>

                            <div class="ac-contact-card">

                                <div class="ac-icon">
                                    <i class="bi bi-geo-alt"></i>
                                </div>

                                <div>
                                    <h3>Business Address</h3>

                                    <p>
                                        <?php echo nl2br(about_escape($siteContent['contact_address'])); ?>
                                    </p>
                                </div>

                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- CONTACT FORM -->
                    <div class="ac-form-card">

                        <h3>Send Us a Message</h3>


                        <?php if ($contactSuccess !== ''): ?>

                            <div class="ac-alert success" role="status">
                                <?php echo about_escape($contactSuccess); ?>
                            </div>

                        <?php endif; ?>


                        <?php if ($contactError !== ''): ?>

                            <div class="ac-alert error" role="alert">
                                <?php echo about_escape($contactError); ?>
                            </div>

                        <?php endif; ?>


                        <form method="POST" action="about.php#contact">

                            <input
                                type="hidden"
                                name="contact_csrf_token"
                                value="<?php echo about_escape($_SESSION['contact_csrf_token']); ?>">


                            <div class="ac-form-grid">

                                <div class="ac-form-field">

                                    <label for="contact-name">Your Name</label>

                                    <input
                                        type="text"
                                        id="contact-name"
                                        name="name"
                                        maxlength="100"
                                        value="<?php echo about_escape($formData['name']); ?>"
                                        placeholder="Enter your name"
                                        required>

                                </div>


                                <div class="ac-form-field">

                                    <label for="contact-email">Email Address</label>

                                    <input
                                        type="email"
                                        id="contact-email"
                                        name="email"
                                        maxlength="150"
                                        value="<?php echo about_escape($formData['email']); ?>"
                                        placeholder="Enter your email"
                                        required>

                                </div>


                                <div class="ac-form-field full">

                                    <label for="contact-subject">Subject</label>

                                    <input
                                        type="text"
                                        id="contact-subject"
                                        name="subject"
                                        maxlength="180"
                                        value="<?php echo about_escape($formData['subject']); ?>"
                                        placeholder="What is your message about?"
                                        required>

                                </div>


                                <div class="ac-form-field full">

                                    <label for="contact-message">Your Message</label>

                                    <textarea
                                        id="contact-message"
                                        name="message"
                                        maxlength="5000"
                                        placeholder="Write your message here..."
                                        required><?php echo about_escape($formData['message']); ?></textarea>

                                </div>

                            </div>


                            <button
                                type="submit"
                                name="send_contact_message"
                                class="ac-submit-btn">

                                <i class="bi bi-send"></i>
                                Send Message

                            </button>

                        </form>

                    </div>

                </div>


                <!-- GOOGLE MAPS -->
                <div class="ac-map-card">

                    <h3>
                        <i class="bi bi-geo-alt-fill"></i>
                        Find Our Location
                    </h3>

                    <p>
                        Visit us or use the map to find our business location.
                    </p>


                    <div class="ac-map-frame">

                        <?php if ($googleMapsEmbedUrl !== ''): ?>

                            <iframe
                                src="<?php echo about_escape($googleMapsEmbedUrl); ?>"
                                width="100%"
                                height="330"
                                style="border:0; border-radius:15px;"
                                allowfullscreen
                                loading="lazy"
                                referrerpolicy="strict-origin-when-cross-origin"
                                title="Handicraft by Sonia Location">
                            </iframe>

                        <?php else: ?>

                            <div class="ac-map-placeholder">
                                <i class="bi bi-geo-alt"></i>
                                <strong>Our Location Map</strong>
                                <p>Google Maps location will appear here.</p>
                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </section>

    </main>


    <!-- WEBSITE FOOTER -->
    <footer>

        <p>
            © 2026 Handicraft by Sonia |
            All Rights Reserved By Team Triple Pixel
        </p>

    </footer>


    <!-- Main JavaScript -->
    <script src="assets/js/script.js"></script>


    <!-- OUR STORY IMAGE SLIDER -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const slider = document.getElementById('ourStorySlider');

            if (!slider) return;

            const slides = slider.querySelectorAll('.ac-story-slide');
            const dots = slider.querySelectorAll('.ac-story-dot');

            if (slides.length < 2) return;

            let currentSlide = 0;
            let sliderInterval;

            function showSlide(index) {

                slides.forEach(function(slide) {
                    slide.classList.remove('active');
                });

                dots.forEach(function(dot) {
                    dot.classList.remove('active');
                });

                currentSlide = index;

                slides[currentSlide].classList.add('active');

                if (dots[currentSlide]) {
                    dots[currentSlide].classList.add('active');
                }
            }

            function startSlider() {

                sliderInterval = setInterval(function() {

                    showSlide((currentSlide + 1) % slides.length);

                }, 3500);
            }

            dots.forEach(function(dot, index) {

                dot.addEventListener('click', function() {

                    clearInterval(sliderInterval);

                    showSlide(index);

                    startSlider();

                });

            });

            startSlider();

        });
    </script>

    <?php include_once __DIR__ . '/chat.php'; ?>

</body>

</html>