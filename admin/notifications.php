<?php
/*
 * Handicraft by Sonia - Admin Notifications Center
 * Matches admin/dashboard.php design & sidebar structure
 */
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/../config/database.php';

function hbs_notif_e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$firstName = preg_split('/\s+/', trim($adminName))[0] ?? 'Admin';

$pendingOrders = [];
$pendingCustomOrders = [];
$unreadMessages = [];
$lowStockProducts = [];
$recentRatings = [];

try {
    // 1. Pending Regular Orders
    $stmt = $pdo->query("
        SELECT id, customer_name, phone, total_amount, order_status, created_at
        FROM orders
        WHERE LOWER(order_status) = 'pending'
        ORDER BY id DESC
        LIMIT 15
    ");
    $pendingOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Pending Custom Orders
    $stmt = $pdo->query("
        SELECT co.id, co.required_date, co.status, co.created_at, u.name AS customer_name
        FROM custom_orders co
        LEFT JOIN users u ON co.user_id = u.id
        WHERE LOWER(co.status) = 'pending'
        ORDER BY co.id DESC
        LIMIT 15
    ");
    $pendingCustomOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Unread Contact Messages
    $stmt = $pdo->query("
        SELECT id, name, email, subject, message, created_at
        FROM contact_messages
        WHERE LOWER(status) = 'unread'
        ORDER BY id DESC
        LIMIT 15
    ");
    $unreadMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Low Stock Products (stock <= 5)
    $stmt = $pdo->query("
        SELECT id, name, price, stock
        FROM products
        WHERE stock <= 5
        ORDER BY stock ASC, id DESC
        LIMIT 15
    ");
    $lowStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Recent Product Ratings
    $stmt = $pdo->query("
        SELECT r.id, r.rating, r.created_at, p.name AS product_name, u.name AS customer_name
        FROM ratings r
        LEFT JOIN products p ON r.product_id = p.id
        LEFT JOIN users u ON r.user_id = u.id
        ORDER BY r.id DESC
        LIMIT 8
    ");
    $recentRatings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Notifications query error: ' . $e->getMessage());
}

$totalAlerts = count($pendingOrders) + count($pendingCustomOrders) + count($unreadMessages) + count($lowStockProducts);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | Admin Panel - Handicraft by Sonia</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --purple: #54209b;
            --purple2: #702bd0;
            --ink: #24143f;
            --muted: #82768f;
            --line: #eee5f8;
            --shadow: 0 5px 20px rgba(67, 28, 111, .07);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #fbf8ff;
            color: var(--ink);
            font-family: 'DM Sans', Arial, sans-serif;
            font-size: 14px;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: 252px;
            background: linear-gradient(180deg, #4b1688 0%, #5b209e 55%, #4b187f 100%);
            color: #fff;
            padding: 20px 13px 18px;
            display: flex;
            flex-direction: column;
            z-index: 20;
            overflow-y: auto;
        }

        .brand {
            text-align: center;
            padding: 2px 8px 19px;
            border-bottom: 1px solid rgba(255, 255, 255, .25);
        }

        .brand-logo {
            width: 106px;
            height: 106px;
            border-radius: 50%;
            object-fit: cover;
            background: #fff;
            border: 4px solid rgba(255, 255, 255, .85);
            display: block;
            margin: 0 auto 9px;
        }

        .brand-name {
            font-weight: 800;
            font-size: 17px;
        }

        .brand-sub {
            font-size: 11px;
            letter-spacing: 2px;
            margin-top: 7px;
            color: #e8d8ff;
        }

        .brand-rule {
            height: 1px;
            width: 68px;
            background: rgba(255, 255, 255, .65);
            margin: 17px auto 0;
        }

        .nav {
            display: flex;
            flex-direction: column;
            gap: 5px;
            padding-top: 17px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 11px 12px;
            border-radius: 9px;
            color: #f7edff;
            font-weight: 600;
            transition: .2s;
        }

        .nav-link:hover,
        .nav-link.active {
            background: linear-gradient(90deg, #a653e9, #9346d5);
            color: #fff;
            box-shadow: 0 5px 14px rgba(30, 0, 60, .16);
        }

        .nav-icon {
            width: 20px;
            text-align: center;
            font-size: 17px;
        }

        .nav-chevron {
            margin-left: auto;
            font-size: 12px;
        }

        .sidebar-bottom {
            margin-top: auto;
            text-align: center;
            padding: 24px 8px 0;
            color: #f4e8ff;
        }

        .yarn {
            font-size: 38px;
            line-height: 1;
        }

        .tagline {
            font-family: 'Playfair Display', serif;
            font-style: italic;
            font-size: 16px;
            line-height: 1.45;
            margin-top: 8px;
        }

        .main {
            margin-left: 252px;
            min-height: 100vh;
        }

        .topbar {
            height: 82px;
            background: rgba(248, 240, 255, .92);
            border-bottom: 1px solid #f0e6fa;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 29px;
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(10px);
        }

        .top-left {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .menu-toggle {
            border: 0;
            background: none;
            color: var(--purple2);
            font-size: 29px;
            cursor: pointer;
            padding: 0 4px;
        }

        .top-divider {
            height: 34px;
            width: 1px;
            background: #b993df;
        }

        .welcome {
            font-family: 'Times New Roman', Times, serif;
            font-style: normal;
            font-weight: 700;
            color: #6b25bd;
            font-size: 25px;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .notification {
            position: relative;
            font-size: 22px;
            color: #762acb;
        }

        .notification-count {
            position: absolute;
            top: -8px;
            right: -9px;
            background: #ef477c;
            color: white;
            font-size: 10px;
            font-weight: 800;
            border-radius: 20px;
            min-width: 17px;
            height: 17px;
            display: grid;
            place-items: center;
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            color: #4b2870;
        }

        .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #e8d7fa;
            display: grid;
            place-items: center;
            color: #6424ad;
            font-size: 19px;
            border: 2px solid #fff;
            box-shadow: 0 2px 8px #d9c4ed;
        }

        .content {
            padding: 25px 28px 35px;
        }

        .page-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 22px;
        }

        .page-title h1 {
            font-size: 23px;
            margin: 0;
            color: #482078;
        }

        .page-title p {
            margin: 5px 0 0;
            color: var(--muted);
        }

        .summary-badges {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .sum-box {
            background: #fff;
            border: 1px solid #eee3f7;
            border-radius: 13px;
            padding: 18px;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .sum-box strong {
            display: block;
            font-size: 26px;
            color: #54209b;
            margin-bottom: 4px;
        }

        .sum-box span {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
        }

        .notif-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .panel {
            background: #fff;
            border: 1px solid #eee4f7;
            border-radius: 13px;
            box-shadow: var(--shadow);
            padding: 18px 20px;
        }

        .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #f0eaf5;
            padding-bottom: 12px;
            margin-bottom: 12px;
        }

        .panel-title {
            font-size: 16px;
            font-weight: 800;
            color: #51218b;
            margin: 0;
        }

        .view-link {
            color: #762acb;
            font-weight: 700;
            font-size: 12px;
        }

        .notif-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f5effa;
        }

        .notif-item:last-child {
            border-bottom: 0;
        }

        .notif-item strong {
            display: block;
            font-size: 13px;
            color: #2d1948;
            margin-bottom: 3px;
        }

        .notif-item small {
            color: var(--muted);
            font-size: 11px;
        }

        .action-btn {
            padding: 7px 13px;
            border-radius: 7px;
            background: #f2e8ff;
            color: #5b21b6;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .action-btn:hover {
            background: #5b21b6;
            color: #fff;
        }

        .empty-state {
            padding: 24px 10px;
            text-align: center;
            color: #9588a4;
            font-size: 12px;
        }

        .sidebar-backdrop {
            display: none;
        }

        @media (max-width: 980px) {
            .summary-badges {
                grid-template-columns: repeat(2, 1fr);
            }

            .notif-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 760px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform .25s ease;
                width: 260px;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .sidebar-backdrop.show {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(25, 10, 40, .4);
                z-index: 15;
            }

            .main {
                margin-left: 0;
            }

            .topbar {
                height: 68px;
                padding: 0 15px;
            }

            .welcome {
                font-size: 16px;
            }

            .content {
                padding: 18px 14px;
            }
        }
    </style>
</head>

<body>
    <div class="admin-shell">
        <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar(false)"></div>
        <aside class="sidebar" id="adminSidebar">
            <div class="brand">
                <img class="brand-logo" src="../assets/images/images.jpeg" alt="Handicraft by Sonia logo" onerror="this.style.display='none'">
                <div class="brand-name">Handicraft by Sonia</div>
                <div class="brand-sub">— &nbsp; ADMIN PANEL &nbsp; —</div>
                <div class="brand-rule"></div>
            </div>

            <nav class="nav" aria-label="Admin navigation">
                <a class="nav-link" href="dashboard.php">
                    <span class="nav-icon">⌂</span>
                    Dashboard
                </a>
                <a class="nav-link" href="products/index.php">
                    <span class="nav-icon">▣</span>
                    Products
                    <span class="nav-chevron">⌄</span>
                </a>
                <a class="nav-link" href="orders/index.php">
                    <span class="nav-icon">▤</span>
                    Orders
                    <span class="nav-chevron">⌄</span>
                </a>
                <a class="nav-link" href="categories/index.php">
                    <span class="nav-icon">▦</span>
                    Categories
                </a>
                <a class="nav-link" href="customers/index.php">
                    <span class="nav-icon">♟</span>
                    Customers
                </a>
                <a class="nav-link" href="messages/index.php">
                    <span class="nav-icon">▰</span>
                    Messages
                </a>
                <a class="nav-link" href="custom-orders/index.php">
                    <span class="nav-icon">✿</span>
                    Custom Orders
                </a>
                <a class="nav-link" href="content/index.php">
                    <span class="nav-icon">▧</span>
                    Content Management
                </a>
                <a class="nav-link" href="reviews/index.php">
                    <span class="nav-icon">★</span>
                    Reviews &amp; Ratings
                </a>
                <a class="nav-link active" href="notifications.php">
                    <span class="nav-icon">♧</span>
                    Notifications
                </a>
                <a class="nav-link" href="logout.php">
                    <span class="nav-icon">⇥</span>
                    Logout
                </a>
            </nav>

            <div class="sidebar-bottom">
                <div class="yarn">◉</div>
                <div class="tagline">Crafting Happiness<br>Together ♡</div>
            </div>
        </aside>

        <main class="main">
            <header class="topbar">
                <div class="top-left">
                    <button class="menu-toggle" type="button" aria-label="Toggle menu" onclick="toggleSidebar()">☰</button>
                    <span class="top-divider"></span>
                    <div class="welcome" style="font-family: 'Times New Roman', Times, serif !important; font-style: normal !important; font-weight: 700 !important;">Notifications Center</div>
                </div>
                <div class="top-actions">
                    <a class="notification" href="notifications.php" aria-label="Notifications">
                        ♧<span class="notification-count"><?= (int)$totalAlerts ?></span>
                    </a>
                    <div class="profile">
                        <span class="avatar"><?= hbs_notif_e(mb_strtoupper(mb_substr($firstName, 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
                        <span class="profile-name"><?= hbs_notif_e($firstName) ?></span>
                    </div>
                </div>
            </header>

            <section class="content">
                <div class="page-title">
                    <div>
                        <h1>Store Alerts &amp; Notifications</h1>
                        <p>Monitor pending orders, custom design requests, unread customer messages, and low stock alerts.</p>
                    </div>
                    <a href="dashboard.php" class="action-btn">← Back to Dashboard</a>
                </div>

                <div class="summary-badges">
                    <div class="sum-box">
                        <strong><?= count($pendingOrders) ?></strong>
                        <span>Pending Orders</span>
                    </div>
                    <div class="sum-box">
                        <strong><?= count($pendingCustomOrders) ?></strong>
                        <span>Pending Custom Orders</span>
                    </div>
                    <div class="sum-box">
                        <strong><?= count($unreadMessages) ?></strong>
                        <span>Unread Messages</span>
                    </div>
                    <div class="sum-box">
                        <strong><?= count($lowStockProducts) ?></strong>
                        <span>Low Stock Products</span>
                    </div>
                </div>

                <div class="notif-grid">

                    <!-- 1. Pending Regular Orders -->
                    <section class="panel">
                        <div class="panel-head">
                            <h2 class="panel-title">▤ Pending Customer Orders</h2>
                            <a href="orders/index.php" class="view-link">Manage All →</a>
                        </div>
                        <?php if ($pendingOrders): ?>
                            <?php foreach ($pendingOrders as $ord): ?>
                                <div class="notif-item">
                                    <div>
                                        <strong>Order #<?= (int)$ord['id'] ?> — <?= hbs_notif_e($ord['customer_name']) ?></strong>
                                        <small>Amount: ৳<?= number_format((float)$ord['total_amount'], 2) ?> • <?= date('d M Y, h:i A', strtotime($ord['created_at'])) ?></small>
                                    </div>
                                    <a href="orders/view.php?id=<?= (int)$ord['id'] ?>" class="action-btn">Review</a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">No pending orders right now.</div>
                        <?php endif; ?>
                    </section>

                    <!-- 2. Pending Custom Orders -->
                    <section class="panel">
                        <div class="panel-head">
                            <h2 class="panel-title">✿ Pending Custom Order Requests</h2>
                            <a href="custom-orders/index.php" class="view-link">Manage All →</a>
                        </div>
                        <?php if ($pendingCustomOrders): ?>
                            <?php foreach ($pendingCustomOrders as $co): ?>
                                <div class="notif-item">
                                    <div>
                                        <strong>Request #<?= (int)$co['id'] ?> — <?= hbs_notif_e($co['customer_name'] ?? 'Customer') ?></strong>
                                        <small>Required by: <?= hbs_notif_e(date('d M Y', strtotime($co['required_date']))) ?></small>
                                    </div>
                                    <a href="custom-orders/view.php?id=<?= (int)$co['id'] ?>" class="action-btn">View Request</a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">No pending custom order requests.</div>
                        <?php endif; ?>
                    </section>

                    <!-- 3. Unread Contact Messages -->
                    <section class="panel">
                        <div class="panel-head">
                            <h2 class="panel-title">▰ Unread Customer Messages</h2>
                            <a href="messages/index.php" class="view-link">View Inbox →</a>
                        </div>
                        <?php if ($unreadMessages): ?>
                            <?php foreach ($unreadMessages as $msg): ?>
                                <div class="notif-item">
                                    <div>
                                        <strong><?= hbs_notif_e($msg['name']) ?>: <?= hbs_notif_e($msg['subject']) ?></strong>
                                        <small><?= hbs_notif_e($msg['email']) ?> • <?= date('d M Y', strtotime($msg['created_at'])) ?></small>
                                    </div>
                                    <a href="messages/view.php?id=<?= (int)$msg['id'] ?>" class="action-btn">Read</a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">All customer messages have been read!</div>
                        <?php endif; ?>
                    </section>

                    <!-- 4. Low Stock Alerts -->
                    <section class="panel">
                        <div class="panel-head">
                            <h2 class="panel-title">⚠ Low Stock Alerts (≤ 5 Left)</h2>
                            <a href="products/index.php" class="view-link">Manage Stock →</a>
                        </div>
                        <?php if ($lowStockProducts): ?>
                            <?php foreach ($lowStockProducts as $prod): ?>
                                <div class="notif-item">
                                    <div>
                                        <strong><?= hbs_notif_e($prod['name']) ?></strong>
                                        <small style="color: #e11d48; font-weight: 700;">Only <?= (int)$prod['stock'] ?> left in stock</small>
                                    </div>
                                    <a href="products/edit.php?id=<?= (int)$prod['id'] ?>" class="action-btn">Update Stock</a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">All products are well stocked!</div>
                        <?php endif; ?>
                    </section>

                </div>
            </section>
        </main>
    </div>

    <script>
        function toggleSidebar(forceState) {
            const sidebar = document.getElementById('adminSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            const shouldOpen = typeof forceState === 'boolean' ? forceState : !sidebar.classList.contains('open');
            sidebar.classList.toggle('open', shouldOpen);
            backdrop.classList.toggle('show', shouldOpen);
        }
    </script>
</body>

</html>