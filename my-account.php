<?php
/* ==========================================
   HANDICRAFT BY SONIA - MY ACCOUNT
   PHP + MySQL + PDO
========================================== */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

$user_id = (int) $_SESSION['user_id'];

$user = [
    'name' => '',
    'username' => '',
    'email' => '',
    'phone' => ''
];

$orders = [];
$wishlist_items = [];
$user_ratings = [];
$user_comments = [];
$saved_addresses = [];
$page_error = '';

/* HTML escaping */
if (!function_exists('account_escape')) {
    function account_escape($value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

/* Order status CSS class */
if (!function_exists('account_status_class')) {
    function account_status_class($status): string
    {
        $status = strtolower(trim((string) $status));

        $allowed = [
            'pending',
            'confirmed',
            'processing',
            'shipped',
            'delivered',
            'completed',
            'cancelled'
        ];

        if (!in_array($status, $allowed, true)) {
            return 'pending';
        }

        if ($status === 'confirmed') {
            return 'processing';
        }

        return $status === 'completed' ? 'delivered' : $status;
    }
}

/* ==========================================
   FETCH PROFILE, ORDERS, WISHLIST & REVIEWS
========================================== */

try {
    $stmt = $pdo->prepare(
        "SELECT id, name, username, email, phone
         FROM users
         WHERE id = :id
         LIMIT 1"
    );

    $stmt->execute(['id' => $user_id]);

    $db_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$db_user) {
        $_SESSION = [];
        session_destroy();

        header('Location: auth/login.php');
        exit;
    }

    $user['name'] = $db_user['name'] ?? '';
    $user['username'] = $db_user['username'] ?? '';
    $user['email'] = $db_user['email'] ?? '';
    $user['phone'] = $db_user['phone'] ?? '';

    $_SESSION['user_name'] = $user['name'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_email'] = $user['email'];

    // Orders
    $orderStmt = $pdo->prepare(
        "SELECT id, customer_name, phone, address, city, area,
                total_amount, order_status, created_at
         FROM orders
         WHERE user_id = :user_id
         ORDER BY id DESC"
    );
    $orderStmt->execute(['user_id' => $user_id]);
    $orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

    // Distinct Addresses from previous orders
    $seen_addr = [];
    foreach ($orders as $ord) {
        $addr_key = strtolower(trim(($ord['address'] ?? '') . '|' . ($ord['city'] ?? '') . '|' . ($ord['area'] ?? '')));
        if ($addr_key !== '||' && !isset($seen_addr[$addr_key])) {
            $seen_addr[$addr_key] = true;
            $saved_addresses[] = [
                'name' => $ord['customer_name'] ?? $user['name'],
                'phone' => $ord['phone'] ?? $user['phone'],
                'address' => $ord['address'] ?? '',
                'area' => $ord['area'] ?? '',
                'city' => $ord['city'] ?? ''
            ];
        }
    }

    // Wishlist items
    try {
        $wStmt = $pdo->prepare("
            SELECT p.id, p.name, p.price, p.image, p.stock
            FROM wishlist w
            JOIN products p ON w.product_id = p.id
            WHERE w.user_id = :uid
            ORDER BY w.id DESC
        ");
        $wStmt->execute([':uid' => $user_id]);
        $wishlist_items = $wStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $wishlist_items = [];
    }

    // User Ratings
    $rStmt = $pdo->prepare("
        SELECT r.rating, r.created_at, p.id AS product_id, p.name AS product_name
        FROM ratings r
        JOIN products p ON r.product_id = p.id
        WHERE r.user_id = :uid
        ORDER BY r.id DESC
    ");
    $rStmt->execute([':uid' => $user_id]);
    $user_ratings = $rStmt->fetchAll(PDO::FETCH_ASSOC);

    // User Comments
    $cStmt = $pdo->prepare("
        SELECT c.comment, c.created_at, p.id AS product_id, p.name AS product_name
        FROM comments c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = :uid
        ORDER BY c.id DESC
    ");
    $cStmt->execute([':uid' => $user_id]);
    $user_comments = $cStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('My Account error: ' . $e->getMessage());
    $page_error = 'Unable to load your account. Please try again.';
}

/* ==========================================
   ORDER STATISTICS & HEADER COUNTS
========================================== */

$total_orders = count($orders);
$pending_orders = 0;
$completed_orders = 0;

foreach ($orders as $order) {
    $status = strtolower(
        trim((string) ($order['order_status'] ?? 'pending'))
    );

    if ($status === 'pending') {
        $pending_orders++;
    }

    if (in_array($status, ['delivered', 'completed'], true)) {
        $completed_orders++;
    }
}

$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $cItem) {
        $cartCount += (int)($cItem['quantity'] ?? 1);
    }
}
$wishlistCount = count($wishlist_items);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Account | Handicraft by Sonia</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        :root {
            --account-purple: #6d28d9;
            --account-purple-dark: #4c1d95;
            --account-purple-light: #f1e6fb;
            --account-pink: #db2777;
            --account-text: #30243a;
            --account-muted: #81758b;
            --account-border: #eee6f6;
            --account-bg: #fcfafe;
            --account-gradient: linear-gradient(
                110deg, #8b5cf6 0%, #a78bfa 55%, #c084fc 100%
            );
            --account-soft-gradient: linear-gradient(
                110deg, #ede2f7 0%, #e5d5f3 52%, #dcc6ef 100%
            );
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--account-bg);
            color: var(--account-text);
            font-family: 'Poppins', sans-serif;
        }

        .account-page {
            width: min(1240px, 94%);
            margin: 35px auto 70px;
        }

        .account-intro {
            margin-bottom: 25px;
        }

        .account-intro .eyebrow {
            color: var(--account-purple);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 2px;
        }

        .account-intro h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(28px, 4vw, 38px);
            margin: 8px 0;
        }

        .account-intro p {
            color: var(--account-muted);
            margin: 0;
            font-size: 14px;
        }

        .account-layout {
            display: grid;
            grid-template-columns: 270px minmax(0, 1fr);
            align-items: start;
            gap: 25px;
        }

        /* Sidebar */

        .account-sidebar {
            padding: 20px 14px;
            background: #fff;
            border: 1px solid var(--account-border);
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(48, 36, 58, .035);
        }

        .sidebar-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 5px 8px 20px;
            margin-bottom: 15px;
            border-bottom: 1px solid var(--account-border);
            min-width: 0;
        }

        .profile-avatar {
            display: grid;
            place-items: center;
            width: 48px;
            height: 48px;
            flex-shrink: 0;
            border-radius: 15px;
            background: var(--account-soft-gradient);
            color: var(--account-purple);
            font-size: 23px;
        }

        .sidebar-profile strong {
            display: block;
            font-size: 13px;
            overflow-wrap: anywhere;
        }

        .sidebar-profile small {
            display: block;
            color: var(--account-muted);
            font-size: 10px;
            margin-top: 4px;
            overflow-wrap: anywhere;
        }

        .sidebar-label {
            padding: 5px 12px 12px;
            color: #a196ad;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.4px;
        }

        .account-menu {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .account-menu button,
        .account-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            min-width: 0;
            padding: 12px 13px;
            border: 0;
            border-radius: 11px;
            background: transparent;
            color: #66596f;
            text-decoration: none;
            text-align: left;
            font-family: inherit;
            font-size: 12px;
            cursor: pointer;
            transition: .2s ease;
        }

        .account-menu button i,
        .account-menu a i {
            width: 20px;
            flex-shrink: 0;
            font-size: 17px;
            text-align: center;
        }

        .account-menu button:hover,
        .account-menu a:hover {
            color: var(--account-purple);
            background: #faf5ff;
        }

        .account-menu button.active {
            background: var(--account-gradient);
            color: #fff;
            font-weight: 600;
            box-shadow: 0 5px 14px rgba(139, 92, 246, .20);
        }

        .account-menu .logout-link {
            margin-top: 8px;
            border-top: 1px solid var(--account-border);
            padding-top: 17px;
            color: #dc2626;
        }

        /* Main content */

        .account-main {
            min-width: 0;
        }

        .welcome-banner {
            position: relative;
            isolation: isolate;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 28px;
            min-height: 170px;
            border: 1px solid rgba(109, 40, 217, .10);
            border-radius: 22px;
            color: var(--account-text);
            background: var(--account-soft-gradient);
            box-shadow: 0 12px 28px rgba(91, 33, 182, .10);
        }

        .welcome-banner::after {
            content: "";
            position: absolute;
            z-index: -1;
            width: 180px;
            height: 180px;
            right: 70px;
            top: -100px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .38);
        }

        .welcome-banner h2 {
            margin: 0 0 9px;
            font-family: 'Playfair Display', serif;
            font-size: clamp(23px, 3vw, 31px);
            overflow-wrap: anywhere;
        }

        .welcome-banner p {
            margin: 0;
            color: #5b4770;
            font-size: 12px;
            line-height: 1.8;
        }

        .welcome-icon {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            width: 70px;
            height: 70px;
            border: 1px solid rgba(109, 40, 217, .16);
            border-radius: 22px;
            background: rgba(255, 255, 255, .55);
            color: var(--account-purple);
            font-size: 32px;
        }

        .account-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .stat-card {
            padding: 19px;
            border: 1px solid var(--account-border);
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 6px 20px rgba(48, 36, 58, .025);
        }

        .stat-icon {
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            border-radius: 13px;
            background: var(--account-purple-light);
            color: var(--account-purple);
            font-size: 20px;
        }

        .stat-card h3 {
            margin: 13px 0 3px;
            font-size: 27px;
        }

        .stat-card p {
            margin: 0;
            color: var(--account-muted);
            font-size: 11px;
        }

        .content-panel {
            display: none;
            padding: 26px;
            border: 1px solid var(--account-border);
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 8px 25px rgba(48, 36, 58, .025);
        }

        .content-panel.active {
            display: block;
            animation: panelFade .22s ease;
        }

        @keyframes panelFade {
            from {
                opacity: .5;
                transform: translateY(4px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .panel-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 19px;
            margin-bottom: 22px;
            border-bottom: 1px solid var(--account-border);
        }

        .panel-heading > i {
            display: grid;
            place-items: center;
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            border-radius: 13px;
            background: var(--account-purple-light);
            color: var(--account-purple);
            font-size: 21px;
        }

        .panel-heading h2 {
            margin: 0;
            font-size: 18px;
        }

        .panel-heading p {
            margin: 5px 0 0;
            color: var(--account-muted);
            font-size: 11px;
            line-height: 1.6;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .profile-field {
            min-width: 0;
            padding: 17px;
            border: 1px solid #f0e8f9;
            border-radius: 13px;
            background: #fdfbff;
        }

        .profile-field span {
            display: block;
            margin-bottom: 8px;
            color: var(--account-muted);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .7px;
            text-transform: uppercase;
        }

        .profile-field strong {
            display: block;
            overflow-wrap: anywhere;
            font-size: 13px;
            font-weight: 600;
        }

        .account-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            padding: 11px 16px;
            border: 0;
            border-radius: 10px;
            background: var(--account-gradient);
            color: #fff;
            text-decoration: none;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: .2s ease;
        }

        .account-button:hover {
            filter: brightness(.97);
            color: #fff;
        }

        .account-button.light {
            color: var(--account-purple);
            background: var(--account-purple-light);
        }

        .account-button.light:hover {
            background: #e9d5ff;
            color: var(--account-purple-dark);
        }

        .profile-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--account-border);
            border-radius: 12px;
        }

        .account-table {
            width: 100%;
            min-width: 570px;
            border-collapse: collapse;
        }

        .account-table th,
        .account-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #f0eaf6;
            text-align: left;
            font-size: 11px;
        }

        .account-table th {
            background: #f5edfc;
            color: var(--account-purple);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .account-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .order-number {
            color: var(--account-purple);
            font-weight: 700;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 7px;
            white-space: nowrap;
            font-size: 10px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.processing {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-badge.shipped {
            background: #ede9fe;
            color: #5b21b6;
        }

        .status-badge.delivered {
            background: #dcfce7;
            color: #166534;
        }

        .status-badge.cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .empty-state {
            padding: 25px 10px;
            text-align: center;
        }

        .empty-state > i {
            display: grid;
            place-items: center;
            width: 65px;
            height: 65px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: #f0e7ff;
            color: var(--account-purple);
            font-size: 28px;
        }

        .empty-state h3 {
            margin: 0 0 8px;
            font-size: 17px;
        }

        .empty-state p {
            max-width: 400px;
            margin: 0 auto 18px;
            color: var(--account-muted);
            font-size: 12px;
            line-height: 1.8;
        }

        .info-note {
            padding: 14px;
            border: 1px solid #e9d5ff;
            border-radius: 11px;
            background: #f7f0fd;
            color: #5b21b6;
            font-size: 12px;
            line-height: 1.8;
        }

        .quick-links {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 20px;
        }

        .quick-link {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            padding: 15px;
            border: 1px solid var(--account-border);
            border-radius: 13px;
            color: var(--account-text);
            text-decoration: none;
            transition: .2s ease;
        }

        .quick-link:hover {
            border-color: #c4b5fd;
            background: #fcfaff;
            color: var(--account-purple);
        }

        .quick-link > i {
            display: grid;
            place-items: center;
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            border-radius: 12px;
            background: var(--account-purple-light);
            color: var(--account-purple);
            font-size: 19px;
        }

        .quick-link strong {
            display: block;
            font-size: 12px;
        }

        .quick-link small {
            display: block;
            margin-top: 4px;
            color: var(--account-muted);
            font-size: 10px;
            line-height: 1.5;
        }

        .account-error {
            margin-bottom: 18px;
            padding: 15px;
            border-radius: 12px;
            background: #fee2e2;
            color: #991b1b;
            font-size: 13px;
        }


        @media (max-width: 900px) {
            .account-layout {
                grid-template-columns: 220px minmax(0, 1fr);
                gap: 16px;
            }

            .account-sidebar {
                padding: 15px 9px;
            }

            .account-menu button,
            .account-menu a {
                gap: 8px;
                padding: 11px 9px;
                font-size: 11px;
            }

            .content-panel {
                padding: 20px;
            }

            .account-stats {
                gap: 10px;
            }

            .stat-card {
                padding: 14px;
            }
        }

        @media (max-width: 650px) {
            .account-page {
                width: 94%;
                margin-top: 25px;
            }

            .account-layout {
                grid-template-columns: 1fr;
            }

            .account-sidebar {
                padding: 14px;
            }

            .account-menu {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 6px;
            }

            .account-menu button,
            .account-menu a {
                min-height: 46px;
                padding: 9px;
                border: 1px solid #f0eaf6;
                font-size: 10px;
                overflow-wrap: anywhere;
            }

            .account-menu button i,
            .account-menu a i {
                width: 17px;
                font-size: 15px;
            }

            .account-menu .logout-link {
                margin-top: 0;
                padding-top: 9px;
                border-top: 1px solid #f0eaf6;
            }

            .welcome-banner {
                padding: 23px 20px;
                min-height: 145px;
                border-radius: 17px;
            }

            .welcome-icon {
                width: 52px;
                height: 52px;
                border-radius: 15px;
                font-size: 23px;
            }

            .account-stats {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .profile-grid,
            .quick-links {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 380px) {
            .account-menu,
            .account-stats {
                grid-template-columns: 1fr;
            }

            .stat-card {
                display: grid;
                grid-template-columns: 40px 1fr;
                align-items: center;
                column-gap: 10px;
            }

            .stat-icon {
                grid-row: span 2;
            }

            .stat-card h3 {
                margin: 0;
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
        <a href="about.php">About Us</a>
        <a href="faq.php">FAQ</a>
    </nav>

    <div class="header-actions">
        <a href="shop.php" class="icon-link" aria-label="Search products">
            <i class="bi bi-search"></i>
        </a>

        <a href="wishlist.php" class="icon-link notification" aria-label="Wishlist">
            <i class="bi bi-heart"></i>
            <span><?php echo (int) $wishlistCount; ?></span>
        </a>

        <a href="cart.php" class="icon-link notification" aria-label="Shopping cart">
            <i class="bi bi-cart3"></i>
            <span><?php echo (int) $cartCount; ?></span>
        </a>

        <a href="my-account.php" class="welcome-user" title="My Account">
            <i class="bi bi-person-circle"></i>
            <span>Hi, <?php echo account_escape($user['name'] ?: $user['username']); ?></span>
        </a>

        <a href="auth/logout.php" class="login-btn">
            <i class="bi bi-box-arrow-right"></i>
            Logout
        </a>
    </div>
</header>

<main class="account-page">

    <div class="account-intro">
        <span class="eyebrow">YOUR PERSONAL SPACE</span>
        <h1>My Account</h1>
        <p>Manage your profile, orders and shopping preferences.</p>
    </div>

    <?php if ($page_error !== ''): ?>
        <div class="account-error" role="alert">
            <?php echo account_escape($page_error); ?>
        </div>
    <?php endif; ?>

    <div class="account-layout">

        <!-- ACCOUNT SIDEBAR -->
        <aside class="account-sidebar">

            <div class="sidebar-profile">
                <div class="profile-avatar">
                    <i class="bi bi-person-heart"></i>
                </div>

                <div>
                    <strong>
                        <?php
                        echo account_escape(
                            $user['name'] ?: $user['username']
                        );
                        ?>
                    </strong>

                    <small>
                        <?php echo account_escape($user['email']); ?>
                    </small>
                </div>
            </div>

            <div class="sidebar-label">ACCOUNT MENU</div>

            <nav class="account-menu" aria-label="Account menu">

                <button type="button" class="active"
                        data-panel="profile">
                    <i class="bi bi-person-vcard"></i>
                    <span>Profile & Personal Info</span>
                </button>

                <button type="button" data-panel="security">
                    <i class="bi bi-shield-lock"></i>
                    <span>Password & Security</span>
                </button>

                <button type="button" data-panel="orders">
                    <i class="bi bi-receipt"></i>
                    <span>Order History</span>
                </button>

                <button type="button" data-panel="tracking">
                    <i class="bi bi-truck"></i>
                    <span>Order Tracking</span>
                </button>

                <button type="button" data-panel="returns">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    <span>Returns & Refunds</span>
                </button>

                <a href="cart.php">
                    <i class="bi bi-cart3"></i>
                    <span>Shopping Cart</span>
                </a>

                <button type="button" data-panel="wishlist">
                    <i class="bi bi-heart"></i>
                    <span>Wishlist / Favorites</span>
                </button>

                <button type="button" data-panel="addresses">
                    <i class="bi bi-geo-alt"></i>
                    <span>Address Book</span>
                </button>

                <button type="button" data-panel="reviews">
                    <i class="bi bi-star"></i>
                    <span>My Reviews</span>
                </button>

                <button type="button" data-panel="notifications">
                    <i class="bi bi-bell"></i>
                    <span>Notification Preferences</span>
                </button>

                <a href="auth/logout.php" class="logout-link">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>

            </nav>
        </aside>

        <!-- MAIN ACCOUNT CONTENT -->
        <div class="account-main">

            <div class="welcome-banner">
                <div>
                    <h2>
                        Welcome,
                        <?php
                        echo account_escape(
                            $user['name'] ?: $user['username']
                        );
                        ?>!
                    </h2>

                    <p>
                        Thank you for being part of Handicraft by Sonia.
                        <br>
                        Your handmade shopping journey starts here.
                    </p>
                </div>

                <div class="welcome-icon">
                    <i class="bi bi-person-heart"></i>
                </div>
            </div>

            <!-- ORDER STATISTICS -->
            <div class="account-stats">

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-bag-check"></i>
                    </div>
                    <h3><?php echo $total_orders; ?></h3>
                    <p>Total Orders</p>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <h3><?php echo $pending_orders; ?></h3>
                    <p>Pending Orders</p>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <h3><?php echo $completed_orders; ?></h3>
                    <p>Delivered Orders</p>
                </div>

            </div>

            <!-- 1. PROFILE & PERSONAL INFORMATION -->
            <section class="content-panel active" id="panel-profile">

                <div class="panel-heading">
                    <i class="bi bi-person-vcard"></i>
                    <div>
                        <h2>Profile & Personal Information</h2>
                        <p>View your registered account information.</p>
                    </div>
                </div>

                <div class="profile-grid">

                    <div class="profile-field">
                        <span>Full Name</span>
                        <strong>
                            <?php echo account_escape($user['name']); ?>
                        </strong>
                    </div>

                    <div class="profile-field">
                        <span>Username</span>
                        <strong>
                            <?php echo account_escape($user['username']); ?>
                        </strong>
                    </div>

                    <div class="profile-field">
                        <span>Email Address</span>
                        <strong>
                            <?php echo account_escape($user['email']); ?>
                        </strong>
                    </div>

                    <div class="profile-field">
                        <span>Phone Number</span>
                        <strong>
                            <?php
                            echo account_escape(
                                $user['phone'] ?: 'Not provided'
                            );
                            ?>
                        </strong>
                    </div>

                </div>

                <div class="info-note" style="margin-top:18px">
                    Your information is displayed from your registered
                    account.
                </div>

            </section>

            <!-- 2. PASSWORD & SECURITY -->
            <section class="content-panel" id="panel-security">

                <div class="panel-heading">
                    <i class="bi bi-shield-lock"></i>
                    <div>
                        <h2>Password & Security</h2>
                        <p>Keep your account secure.</p>
                    </div>
                </div>

                <div class="info-note">
                    Your account password is encrypted with secure PHP hashing. If you ever need to reset your password or verify your email OTP, use the authentication portal.
                </div>

                <div class="profile-actions">
                    <a href="auth/verify-otp.php" class="account-button light">
                        <i class="bi bi-shield-check"></i>
                        Email OTP Verification
                    </a>
                </div>

            </section>

            <!-- 3. ORDER HISTORY -->
            <section class="content-panel" id="panel-orders">

                <div class="panel-heading">
                    <i class="bi bi-receipt"></i>
                    <div>
                        <h2>Order History</h2>
                        <p>View orders placed through your account.</p>
                    </div>
                </div>

                <?php if (empty($orders)): ?>

                    <div class="empty-state">
                        <i class="bi bi-bag-x"></i>
                        <h3>No Orders Yet</h3>
                        <p>
                            Your order history will appear here
                            after you place an order.
                        </p>
                        <a href="shop.php" class="account-button">
                            <i class="bi bi-shop"></i>
                            Explore Products
                        </a>
                    </div>

                <?php else: ?>

                    <div class="table-wrap">
                        <table class="account-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td class="order-number">
                                            #<?php
                                            echo (int) $order['id'];
                                            ?>
                                        </td>

                                        <td>
                                            <?php
                                            echo !empty($order['created_at'])
                                                ? account_escape(
                                                    date(
                                                        'd M Y',
                                                        strtotime(
                                                            $order['created_at']
                                                        )
                                                    )
                                                )
                                                : '—';
                                            ?>
                                        </td>

                                        <td>
                                            ৳<?php
                                            echo number_format(
                                                (float) (
                                                    $order['total_amount'] ?? 0
                                                ),
                                                2
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            <span class="status-badge <?php
                                                echo account_status_class(
                                                    $order['order_status']
                                                        ?? 'pending'
                                                );
                                            ?>">
                                                <?php
                                                echo account_escape(
                                                    $order['order_status']
                                                        ?? 'Pending'
                                                );
                                                ?>
                                            </span>
                                        </td>

                                        <td>
                                            <a href="my-orders.php?order_id=<?php echo (int) $order['id']; ?>" class="view-product">
                                                View / Print <i class="bi bi-arrow-right"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>

            </section>

            <!-- 4. ORDER TRACKING -->
            <section class="content-panel" id="panel-tracking">

                <div class="panel-heading">
                    <i class="bi bi-truck"></i>
                    <div>
                        <h2>Order Tracking</h2>
                        <p>Check the latest saved status of your orders.</p>
                    </div>
                </div>

                <?php if (empty($orders)): ?>

                    <div class="empty-state">
                        <i class="bi bi-truck"></i>
                        <h3>No Orders to Track</h3>
                        <p>
                            Your order tracking information will
                            appear here after you place an order.
                        </p>
                    </div>

                <?php else: ?>

                    <div class="table-wrap">
                        <table class="account-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Order Date</th>
                                    <th>Current Status</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td class="order-number">
                                            #<?php
                                            echo (int) $order['id'];
                                            ?>
                                        </td>

                                        <td>
                                            <?php
                                            echo !empty($order['created_at'])
                                                ? account_escape(
                                                    date(
                                                        'd M Y',
                                                        strtotime(
                                                            $order['created_at']
                                                        )
                                                    )
                                                )
                                                : '—';
                                            ?>
                                        </td>

                                        <td>
                                            <span class="status-badge <?php
                                                echo account_status_class(
                                                    $order['order_status']
                                                        ?? 'pending'
                                                );
                                            ?>">
                                                <?php
                                                echo account_escape(
                                                    $order['order_status']
                                                        ?? 'Pending'
                                                );
                                                ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>

            </section>

            <!-- 5. RETURNS & REFUNDS -->
            <section class="content-panel" id="panel-returns">

                <div class="panel-heading">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    <div>
                        <h2>Returns & Refunds</h2>
                        <p>Manage requests related to returned products.</p>
                    </div>
                </div>

                <div class="info-note">
                    If you receive a damaged or incorrect handmade item, please contact us through our About & Contact page with your Order ID and photos within 24 hours of delivery.
                </div>

            </section>

            <!-- 6. WISHLIST / FAVORITES -->
            <section class="content-panel" id="panel-wishlist">

                <div class="panel-heading">
                    <i class="bi bi-heart"></i>
                    <div>
                        <h2>Wishlist / Favorites (<?php echo count($wishlist_items); ?>)</h2>
                        <p>Keep your favorite handmade products together.</p>
                    </div>
                </div>

                <?php if (empty($wishlist_items)): ?>
                    <div class="empty-state">
                        <i class="bi bi-heart"></i>
                        <h3>Your Wishlist is Empty</h3>
                        <p>Browse our shop and save your favorite handmade products to view them here.</p>
                        <a href="shop.php" class="account-button">
                            <i class="bi bi-shop"></i>
                            Browse Shop
                        </a>
                    </div>
                <?php else: ?>
                    <div class="quick-links" style="margin-top: 0;">
                        <?php foreach ($wishlist_items as $wItem): ?>
                            <a href="product-details.php?id=<?php echo (int)$wItem['id']; ?>" class="quick-link">
                                <i class="bi bi-heart-fill"></i>
                                <div>
                                    <strong><?php echo account_escape($wItem['name']); ?></strong>
                                    <small>৳<?php echo number_format((float)$wItem['price'], 0); ?> • Click to view</small>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="profile-actions">
                        <a href="wishlist.php" class="account-button">
                            <i class="bi bi-heart"></i>
                            Open Full Wishlist Page
                        </a>
                    </div>
                <?php endif; ?>

            </section>

            <!-- 7. ADDRESS BOOK -->
            <section class="content-panel" id="panel-addresses">

                <div class="panel-heading">
                    <i class="bi bi-geo-alt"></i>
                    <div>
                        <h2>Address Book</h2>
                        <p>Delivery addresses from your orders.</p>
                    </div>
                </div>

                <?php if (empty($saved_addresses)): ?>
                    <div class="info-note">
                        No delivery addresses saved yet. Your delivery address will automatically appear here once you place an order.
                    </div>
                <?php else: ?>
                    <div class="profile-grid">
                        <?php foreach ($saved_addresses as $idx => $addr): ?>
                            <div class="profile-field">
                                <span>Delivery Address #<?php echo $idx + 1; ?></span>
                                <strong><?php echo account_escape($addr['name']); ?> (<?php echo account_escape($addr['phone']); ?>)</strong>
                                <p style="margin: 6px 0 0; font-size: 12px; color: #555;">
                                    <?php echo account_escape($addr['address']); ?><br>
                                    <?php echo account_escape($addr['area']); ?>, <?php echo account_escape($addr['city']); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </section>

            <!-- 8. MY REVIEWS -->
            <section class="content-panel" id="panel-reviews">

                <div class="panel-heading">
                    <i class="bi bi-star"></i>
                    <div>
                        <h2>My Reviews & Comments</h2>
                        <p>Product ratings and comments submitted from your account.</p>
                    </div>
                </div>

                <?php if (empty($user_ratings) && empty($user_comments)): ?>
                    <div class="empty-state">
                        <i class="bi bi-star"></i>
                        <h3>No Reviews or Comments Yet</h3>
                        <p>Visit any product details page to rate products or post a comment.</p>
                    </div>
                <?php else: ?>
                    <?php if (!empty($user_ratings)): ?>
                        <h3 style="font-size: 14px; margin-bottom: 10px; color: var(--account-purple);">My Star Ratings</h3>
                        <div class="table-wrap" style="margin-bottom: 20px;">
                            <table class="account-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Rating</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($user_ratings as $ur): ?>
                                        <tr>
                                            <td>
                                                <a href="product-details.php?id=<?php echo (int)$ur['product_id']; ?>" class="order-number">
                                                    <?php echo account_escape($ur['product_name']); ?>
                                                </a>
                                            </td>
                                            <td style="color: #7c3aed; font-size: 13px;">
                                                <?php
                                                for ($i = 1; $i <= 5; $i++) {
                                                    echo $i <= (int)$ur['rating'] ? '★' : '☆';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($ur['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($user_comments)): ?>
                        <h3 style="font-size: 14px; margin-bottom: 10px; color: var(--account-purple);">My Product Comments</h3>
                        <div class="table-wrap">
                            <table class="account-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Comment</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($user_comments as $uc): ?>
                                        <tr>
                                            <td>
                                                <a href="product-details.php?id=<?php echo (int)$uc['product_id']; ?>" class="order-number">
                                                    <?php echo account_escape($uc['product_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo account_escape($uc['comment']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($uc['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            </section>

            <!-- 9. NOTIFICATION PREFERENCES -->
            <section class="content-panel" id="panel-notifications">

                <div class="panel-heading">
                    <i class="bi bi-bell"></i>
                    <div>
                        <h2>Notification Preferences</h2>
                        <p>Manage your account notification preferences.</p>
                    </div>
                </div>

                <div class="info-note">
                    Order status updates and OTP verification emails are sent to your registered email address (<strong><?php echo account_escape($user['email']); ?></strong>).
                </div>

            </section>

        </div>
    </div>
</main>

<footer>
    <p>
        © 2026 Handicraft by Sonia |
        All Rights Reserved By Team Triple Pixel
    </p>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const menuButtons = document.querySelectorAll(
        '.account-menu button[data-panel]'
    );

    const panels = document.querySelectorAll('.content-panel');

    function showPanel(panelName) {
        const targetPanel = document.getElementById(
            'panel-' + panelName
        );

        if (!targetPanel) {
            return;
        }

        panels.forEach(function (panel) {
            panel.classList.remove('active');
        });

        menuButtons.forEach(function (button) {
            const isActive = button.dataset.panel === panelName;

            button.classList.toggle('active', isActive);
            button.setAttribute(
                'aria-pressed',
                isActive ? 'true' : 'false'
            );
        });

        targetPanel.classList.add('active');
    }

    menuButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            showPanel(button.dataset.panel);
        });
    });

    document.querySelectorAll('[data-open-panel]').forEach(
        function (button) {
            button.addEventListener('click', function () {
                showPanel(button.dataset.openPanel);
            });
        }
    );

});
</script>

<?php include_once __DIR__ . '/chat.php'; ?>

</body>
</html>