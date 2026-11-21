<?php
/*
 * Handicraft by Sonia - Admin Dashboard
 * Requires the existing admin/config/auth.php and ../config/database.php
 */
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/../config/database.php';

function hbs_admin_e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function hbs_admin_money($amount): string
{
    return '৳ ' . number_format((float)$amount, 2);
}

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$firstName = preg_split('/\s+/', trim($adminName))[0] ?? 'Admin';
$stats = [
    'products' => 0,
    'orders' => 0,
    'customers' => 0,
    'revenue' => 0,
];
$recentOrders = [];
$lowStockProducts = [];
$recentMessages = [];
$monthlySales = [];
$pendingOrders = 0;
$unreadMessages = 0;
$dashboardErrors = [];

try {
    $stats['products'] = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $stats['orders'] = (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $stats['customers'] = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stats['revenue'] = (float)$pdo->query('SELECT COALESCE(SUM(total_amount), 0) FROM orders')->fetchColumn();

    $stmt = $pdo->query("SELECT id, customer_name, total_amount, order_status, created_at
                         FROM orders ORDER BY id DESC LIMIT 5");
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT id, name, image, stock
                         FROM products
                         WHERE stock <= 5
                         ORDER BY stock ASC, id DESC
                         LIMIT 5");
    $lowStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT id, name, subject, message, status, created_at
                         FROM contact_messages
                         ORDER BY id DESC LIMIT 4");
    $recentMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pendingOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE LOWER(order_status) = 'pending'")->fetchColumn();
    $unreadMessages = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE LOWER(status) = 'unread'")->fetchColumn();

    // Build six calendar-month buckets, including months with zero sales.
    $salesByMonth = [];
    $salesStmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key,
                                     SUM(total_amount) AS month_total
                              FROM orders
                              WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
                              GROUP BY DATE_FORMAT(created_at, '%Y-%m')");
    foreach ($salesStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $salesByMonth[$row['month_key']] = (float)$row['month_total'];
    }
    for ($i = 5; $i >= 0; $i--) {
        $date = new DateTime('first day of this month');
        $date->modify('-' . $i . ' months');
        $key = $date->format('Y-m');
        $monthlySales[] = [
            'label' => $date->format('M'),
            'value' => $salesByMonth[$key] ?? 0,
        ];
    }
} catch (Throwable $e) {
    error_log('Admin dashboard query error: ' . $e->getMessage());
    $dashboardErrors[] = 'Some dashboard information could not be loaded. Please check the database table structure.';
}

// SVG chart coordinates are generated from database results only.
$chartWidth = 620;
$chartHeight = 205;
$chartLeft = 42;
$chartRight = 12;
$chartTop = 18;
$chartBottom = 34;
$plotWidth = $chartWidth - $chartLeft - $chartRight;
$plotHeight = $chartHeight - $chartTop - $chartBottom;
$maxSales = max(array_column($monthlySales, 'value') ?: [0]);
$chartCeiling = $maxSales > 0 ? $maxSales * 1.18 : 100;
$chartPoints = [];
$countMonths = max(count($monthlySales), 1);
foreach ($monthlySales as $index => $month) {
    $x = $chartLeft + ($countMonths === 1 ? 0 : ($index * $plotWidth / ($countMonths - 1)));
    $y = $chartTop + $plotHeight - (($month['value'] / $chartCeiling) * $plotHeight);
    $chartPoints[] = ['x' => $x, 'y' => $y, 'label' => $month['label'], 'value' => $month['value']];
}
$linePath = '';
foreach ($chartPoints as $index => $point) {
    $linePath .= ($index === 0 ? 'M ' : ' L ') . round($point['x'], 2) . ' ' . round($point['y'], 2);
}
$areaPath = $linePath;
if ($chartPoints) {
    $firstPoint = $chartPoints[0];
    $lastPoint = $chartPoints[count($chartPoints) - 1];
    $areaPath .= ' L ' . round($lastPoint['x'], 2) . ' ' . ($chartTop + $plotHeight);
    $areaPath .= ' L ' . round($firstPoint['x'], 2) . ' ' . ($chartTop + $plotHeight) . ' Z';
}

function hbs_admin_status_class($status): string
{
    $status = strtolower(trim((string)$status));
    $map = [
        'pending' => 'pending',
        'processing' => 'processing',
        'shipped' => 'processing',
        'delivered' => 'delivered',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
        'canceled' => 'cancelled',
    ];
    return $map[$status] ?? 'default';
}

function hbs_admin_product_image($image): string
{
    $image = trim((string)$image);
    if ($image === '') {
        return '';
    }
    if (preg_match('~^(https?:)?//~i', $image) || str_contains($image, '..')) {
        return '';
    }
    if (str_starts_with($image, 'assets/') || str_starts_with($image, 'uploads/')) {
        return '../' . ltrim($image, '/');
    }
    return '../assets/images/' . ltrim($image, '/');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Handicraft by Sonia</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --purple: #54209b;
            --purple2: #702bd0;
            --purple3: #8b45df;
            --lavender: #f7f1ff;
            --ink: #24143f;
            --muted: #82768f;
            --line: #eee5f8;
            --white: #fff;
            --shadow: 0 5px 20px rgba(67, 28, 111, .07)
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            background: #fbf8ff;
            color: var(--ink);
            font-family: 'DM Sans', Arial, sans-serif;
            font-size: 14px
        }

        a {
            text-decoration: none;
            color: inherit
        }

        .admin-shell {
            min-height: 100vh
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
            overflow-y: auto
        }

        .brand {
            text-align: center;
            padding: 2px 8px 19px;
            border-bottom: 1px solid rgba(255, 255, 255, .25)
        }

        .brand-logo {
            width: 106px;
            height: 106px;
            border-radius: 50%;
            object-fit: cover;
            background: #fff;
            border: 4px solid rgba(255, 255, 255, .85);
            display: block;
            margin: 0 auto 9px
        }

        .brand-name {
            font-weight: 800;
            font-size: 17px;
            letter-spacing: .1px
        }

        .brand-sub {
            font-size: 11px;
            letter-spacing: 2px;
            margin-top: 7px;
            color: #e8d8ff
        }

        .brand-rule {
            height: 1px;
            width: 68px;
            background: rgba(255, 255, 255, .65);
            margin: 17px auto 0
        }

        .nav {
            display: flex;
            flex-direction: column;
            gap: 5px;
            padding-top: 17px
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 11px 12px;
            border-radius: 9px;
            color: #f7edff;
            font-weight: 600;
            transition: .2s
        }

        .nav-link:hover,
        .nav-link.active {
            background: linear-gradient(90deg, #a653e9, #9346d5);
            color: #fff;
            box-shadow: 0 5px 14px rgba(30, 0, 60, .16)
        }

        .nav-icon {
            width: 20px;
            text-align: center;
            font-size: 17px
        }

        .nav-chevron {
            margin-left: auto;
            font-size: 12px
        }

        .sidebar-bottom {
            margin-top: auto;
            text-align: center;
            padding: 24px 8px 0;
            color: #f4e8ff
        }

        .yarn {
            font-size: 38px;
            line-height: 1
        }

        .tagline {
            font-family: 'Playfair Display', serif;
            font-style: italic;
            font-size: 16px;
            line-height: 1.45;
            margin-top: 8px
        }

        .main {
            margin-left: 252px;
            min-height: 100vh
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
            backdrop-filter: blur(10px)
        }

        .top-left {
            display: flex;
            align-items: center;
            gap: 22px
        }

        .menu-toggle {
            border: 0;
            background: none;
            color: var(--purple2);
            font-size: 29px;
            cursor: pointer;
            padding: 0 4px
        }

        .top-divider {
            height: 34px;
            width: 1px;
            background: #b993df
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
            gap: 22px
        }

        .notification {
            position: relative;
            font-size: 22px;
            color: #762acb
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
            place-items: center
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            color: #4b2870
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
            box-shadow: 0 2px 8px #d9c4ed
        }

        .content {
            padding: 23px 28px 30px
        }

        .page-title {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin: 0 0 18px
        }

        .page-title h1 {
            font-size: 22px;
            margin: 0;
            color: #482078
        }

        .page-title p {
            margin: 5px 0 0;
            color: var(--muted)
        }

        .today {
            color: #8b7b9d;
            font-size: 12px
        }

        .alert {
            background: #fff5dc;
            color: #865c08;
            border: 1px solid #ffe4a6;
            padding: 11px 14px;
            border-radius: 10px;
            margin-bottom: 15px
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 17px;
            margin-bottom: 19px
        }

        .stat-card {
            min-height: 130px;
            border: 1px solid #eee3f7;
            border-radius: 13px;
            background: #fff;
            box-shadow: var(--shadow);
            padding: 18px 14px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            gap: 8px;
            position: relative;
            overflow: hidden
        }

        .stat-card:after {
            content: '';
            position: absolute;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            right: -32px;
            bottom: -48px;
            background: currentColor;
            opacity: .045
        }

        .stat-card.purple {
            border-left: 4px solid #8426d2;
            color: #8426d2;
            background: linear-gradient(110deg, #fff, #fbf5ff)
        }

        .stat-card.pink {
            border-left: 4px solid #ed4387;
            color: #df3d83;
            background: linear-gradient(110deg, #fff, #fff5fa)
        }

        .stat-card.orange {
            border-left: 4px solid #f28b24;
            color: #ed831d;
            background: linear-gradient(110deg, #fff, #fff9f1)
        }

        .stat-card.green {
            border-left: 4px solid #16b978;
            color: #0ca66a;
            background: linear-gradient(110deg, #fff, #f2fff8)
        }

        .stat-icon {
            display: none;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            place-items: center;
            font-size: 24px;
            background: rgba(255, 255, 255, 0.9);
            color: currentColor;
            box-shadow: 0 3px 10px rgba(0, 0, 0, .06)
        }

        .stat-info {
            position: relative;
            z-index: 1;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .stat-number {
            font-size: 26px;
            font-weight: 800;
            color: #1f1740;
            line-height: 1.15;
            white-space: nowrap
        }

        .stat-label {
            color: #3c2859;
            font-size: 14px;
            font-weight: 600;
            margin-top: 5px
        }

        .stat-note {
            font-size: 11px;
            color: #16965d;
            margin-top: 6px
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.04fr) minmax(0, .96fr);
            gap: 17px;
            margin-bottom: 18px
        }

        .panel {
            background: #fff;
            border: 1px solid #eee4f7;
            border-radius: 13px;
            box-shadow: var(--shadow);
            padding: 17px 18px;
            min-width: 0
        }

        .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px
        }

        .panel-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #51218b;
            font-size: 17px;
            font-weight: 800;
            margin: 0
        }

        .panel-symbol {
            color: #8428d2;
            font-size: 19px
        }

        .view-link {
            color: #762acb;
            font-weight: 700;
            font-size: 12px;
            white-space: nowrap
        }

        .chart-wrap {
            width: 100%;
            overflow: hidden
        }

        .sales-chart {
            width: 100%;
            height: auto;
            display: block
        }

        .chart-grid {
            stroke: #eee6f6;
            stroke-width: 1
        }

        .chart-label {
            fill: #594b70;
            font-size: 11px;
            font-family: 'DM Sans', sans-serif
        }

        .chart-line {
            fill: none;
            stroke: #8125d0;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round
        }

        .chart-area {
            fill: url(#salesFill)
        }

        .chart-dot {
            fill: #8125d0;
            stroke: white;
            stroke-width: 2
        }

        .chart-empty {
            padding: 45px 10px;
            text-align: center;
            color: var(--muted)
        }

        .table-wrap {
            overflow-x: auto
        }

        table {
            border-collapse: collapse;
            width: 100%;
            min-width: 450px
        }

        thead {
            background: #f3e8fc
        }

        th {
            font-size: 11px;
            color: #4e247e;
            text-align: left;
            padding: 11px 9px;
            font-weight: 800;
            white-space: nowrap
        }

        td {
            padding: 11px 9px;
            border-bottom: 1px solid #f0eaf5;
            font-size: 12px;
            white-space: nowrap;
            color: #35254c
        }

        tbody tr:last-child td {
            border-bottom: 0
        }

        .order-id {
            font-weight: 800;
            color: #5b2695
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800
        }

        .status.pending {
            background: #fff0bf;
            color: #a76a00
        }

        .status.processing {
            background: #dcefff;
            color: #0870bf
        }

        .status.delivered {
            background: #d9f8e9;
            color: #07834d
        }

        .status.completed {
            background: #eedcff;
            color: #7b28bb
        }

        .status.cancelled {
            background: #ffe1e5;
            color: #c22c49
        }

        .status.default {
            background: #eee;
            color: #555
        }

        .bottom-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.04fr) minmax(0, .96fr);
            gap: 17px
        }

        .stock-list,
        .message-list {
            display: flex;
            flex-direction: column
        }

        .stock-row {
            display: grid;
            grid-template-columns: 57px minmax(90px, 1fr) auto 105px;
            align-items: center;
            gap: 11px;
            padding: 8px 0;
            border-bottom: 1px solid #f1ebf6
        }

        .stock-row:last-child,
        .message-row:last-child {
            border-bottom: 0
        }

        .product-thumb {
            width: 57px;
            height: 48px;
            border-radius: 8px;
            object-fit: cover;
            background: #f5edfb
        }

        .product-fallback {
            display: grid;
            place-items: center;
            font-size: 22px;
            color: #9b69cc
        }

        .product-name {
            font-size: 12px;
            font-weight: 700;
            color: #33214c;
            overflow: hidden;
            text-overflow: ellipsis
        }

        .stock-count {
            font-size: 11px;
            font-weight: 800;
            color: #e84a69;
            white-space: nowrap
        }

        .stock-count.medium {
            color: #df861e
        }

        .stock-bar {
            height: 7px;
            border-radius: 10px;
            background: #eee7f5;
            overflow: hidden
        }

        .stock-fill {
            height: 100%;
            border-radius: 10px;
            background: linear-gradient(90deg, #ff9c42, #ed477a)
        }

        .empty-state {
            padding: 22px 8px;
            text-align: center;
            color: #9588a4;
            font-size: 12px
        }

        .message-row {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            padding: 10px 0;
            border-bottom: 1px solid #f1ebf6
        }

        .message-avatar {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: white;
            background: linear-gradient(135deg, #ef5ca8, #a66af0);
            font-weight: 800
        }

        .message-body {
            min-width: 0;
            flex: 1
        }

        .message-name {
            font-weight: 800;
            font-size: 12px;
            color: #35204e
        }

        .message-text {
            font-size: 11px;
            color: #746783;
            margin-top: 3px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 100%
        }

        .message-time {
            font-size: 10px;
            color: #9889a7;
            white-space: nowrap
        }

        .footer-note {
            text-align: center;
            color: #a194af;
            font-size: 11px;
            padding: 22px 0 4px
        }

        .sidebar-backdrop {
            display: none
        }

        .mobile-close {
            display: none
        }

        @media(max-width:1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }

            .dashboard-grid,
            .bottom-grid {
                grid-template-columns: 1fr
            }

            .welcome {
                font-size: 21px
            }
        }

        @media(max-width:760px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform .25s ease;
                width: 260px
            }

            .sidebar.open {
                transform: translateX(0)
            }

            .sidebar-backdrop.show {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(25, 10, 40, .4);
                z-index: 15
            }

            .main {
                margin-left: 0
            }

            .topbar {
                height: 68px;
                padding: 0 15px
            }

            .top-left {
                gap: 12px
            }

            .top-divider {
                height: 28px
            }

            .welcome {
                font-size: 16px
            }

            .top-actions {
                gap: 13px
            }

            .profile-name {
                display: none
            }

            .avatar {
                width: 36px;
                height: 36px
            }

            .content {
                padding: 18px 14px
            }

            .stats-grid {
                gap: 10px
            }

            .stat-card {
                padding: 13px 11px;
                min-height: 105px;
                gap: 6px
            }

            .stat-number {
                font-size: 20px !important
            }

            .stat-label {
                font-size: 11px
            }

            .stat-note {
                font-size: 9px
            }

            .panel {
                padding: 14px 12px
            }

            .panel-title {
                font-size: 15px
            }

            .stock-row {
                grid-template-columns: 45px minmax(75px, 1fr) auto;
                gap: 8px
            }

            .product-thumb {
                width: 45px;
                height: 42px
            }

            .stock-bar {
                display: none
            }

            .page-title {
                align-items: flex-start;
                flex-direction: column;
                gap: 5px
            }

            .today {
                font-size: 11px
            }
        }

        @media(max-width:390px) {
            .stats-grid {
                grid-template-columns: 1fr
            }

            .stat-card {
                min-height: 90px
            }

            .welcome {
                font-size: 14px
            }

            .notification {
                font-size: 19px
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
                <a class="nav-link active" href="dashboard.php">
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
                <a class="nav-link" href="reports.php">
                    <span class="nav-icon">⎙</span>
                    Sales Report
                </a>
                <a class="nav-link" href="notifications.php">
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
                    <div class="welcome" style="font-family: 'Times New Roman', Times, serif !important; font-style: normal !important; font-weight: 700 !important;">Welcome, <?= hbs_admin_e($adminName) ?> ♥</div>
                </div>
                <div class="top-actions">
                    <a class="notification" href="notifications.php" aria-label="Notifications">♧<span class="notification-count"><?= (int)($pendingOrders + $unreadMessages) ?></span></a>
                    <div class="profile"><span class="avatar"><?= hbs_admin_e(mb_strtoupper(mb_substr($firstName, 0, 1, 'UTF-8'), 'UTF-8')) ?></span><span class="profile-name"><?= hbs_admin_e($firstName) ?>⌄</span></div>
                </div>
            </header>

            <section class="content">
                <div class="page-title">
                    <div>
                        <h1>Dashboard Overview</h1>
                        <p>Here is what's happening with your store today.</p>
                    </div>
                    <div class="today"><?= date('l, d F Y') ?></div>
                </div>
                <?php foreach ($dashboardErrors as $error): ?><div class="alert"><?= hbs_admin_e($error) ?></div><?php endforeach; ?>

                <div class="stats-grid">
                    <article class="stat-card purple">
                        <div class="stat-icon">▣</div>
                        <div class="stat-info">
                            <div class="stat-number"><?= number_format($stats['products']) ?></div>
                            <div class="stat-label">Total Products</div>
                            <div class="stat-note">In your store</div>
                        </div>
                    </article>
                    <article class="stat-card pink">
                        <div class="stat-icon">▤</div>
                        <div class="stat-info">
                            <div class="stat-number"><?= number_format($stats['orders']) ?></div>
                            <div class="stat-label">Total Orders</div>
                            <div class="stat-note"><?= number_format($pendingOrders) ?> pending orders</div>
                        </div>
                    </article>
                    <article class="stat-card orange">
                        <div class="stat-icon">♟</div>
                        <div class="stat-info">
                            <div class="stat-number"><?= number_format($stats['customers']) ?></div>
                            <div class="stat-label">Total Customers</div>
                            <div class="stat-note">Registered accounts</div>
                        </div>
                    </article>
                    <article class="stat-card green">
                        <div class="stat-icon">৳</div>
                        <div class="stat-info">
                            <div class="stat-number" style="font-size:21px"><?= hbs_admin_money($stats['revenue']) ?></div>
                            <div class="stat-label">Total Order Revenue</div>
                            <div class="stat-note">Based on recorded orders</div>
                        </div>
                    </article>
                </div>

                <div class="dashboard-grid">
                    <section class="panel">
                        <div class="panel-head">
                            <h2 class="panel-title"><span class="panel-symbol">▥</span>Sales Overview</h2><span class="view-link">Last 6 Months</span>
                        </div>
                        <?php if ($monthlySales && array_sum(array_column($monthlySales, 'value')) > 0): ?>
                            <div class="chart-wrap">
                                <svg class="sales-chart" viewBox="0 0 <?= $chartWidth ?> <?= $chartHeight ?>" role="img" aria-label="Monthly order revenue chart">
                                    <defs>
                                        <linearGradient id="salesFill" x1="0" x2="0" y1="0" y2="1">
                                            <stop offset="0%" stop-color="#9c55e5" stop-opacity=".30" />
                                            <stop offset="100%" stop-color="#9c55e5" stop-opacity=".025" />
                                        </linearGradient>
                                    </defs>
                                    <?php for ($g = 0; $g <= 4; $g++): $gy = $chartTop + ($plotHeight * $g / 4); ?>
                                        <line class="chart-grid" x1="<?= $chartLeft ?>" y1="<?= $gy ?>" x2="<?= $chartWidth - $chartRight ?>" y2="<?= $gy ?>" />
                                        <text class="chart-label" x="3" y="<?= $gy + 4 ?>"><?= hbs_admin_e(number_format($chartCeiling * (4 - $g) / 4, 0)) ?></text>
                                    <?php endfor; ?>
                                    <path class="chart-area" d="<?= hbs_admin_e($areaPath) ?>" />
                                    <path class="chart-line" d="<?= hbs_admin_e($linePath) ?>" />
                                    <?php foreach ($chartPoints as $point): ?>
                                        <circle class="chart-dot" cx="<?= round($point['x'], 2) ?>" cy="<?= round($point['y'], 2) ?>" r="4.5">
                                            <title><?= hbs_admin_e($point['label']) ?>: <?= hbs_admin_money($point['value']) ?></title>
                                        </circle>
                                        <text class="chart-label" text-anchor="middle" x="<?= round($point['x'], 2) ?>" y="<?= $chartHeight - 8 ?>"><?= hbs_admin_e($point['label']) ?></text>
                                    <?php endforeach; ?>
                                </svg>
                            </div>
                        <?php else: ?><div class="chart-empty">Sales chart will appear here when order data is available.</div><?php endif; ?>
                    </section>

                    <section class="panel">
                        <div class="panel-head">
                            <h2 class="panel-title"><span class="panel-symbol">▣</span>Recent Orders</h2><a class="view-link" href="orders/index.php">View All →</a>
                        </div>
                        <?php if ($recentOrders): ?>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentOrders as $order): ?><tr>
                                                <td class="order-id">#ORD-<?= str_pad((string)$order['id'], 3, '0', STR_PAD_LEFT) ?></td>
                                                <td><?= hbs_admin_e($order['customer_name']) ?></td>
                                                <td><?= hbs_admin_money($order['total_amount']) ?></td>
                                                <td><span class="status <?= hbs_admin_status_class($order['order_status']) ?>"><?= hbs_admin_e($order['order_status']) ?></span></td>
                                                <td><?= !empty($order['created_at']) ? date('d M Y', strtotime($order['created_at'])) : '—' ?></td>
                                            </tr><?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?><div class="empty-state">No orders found yet.</div><?php endif; ?>
                    </section>
                </div>

                <div class="bottom-grid">
                    <section class="panel">
                        <div class="panel-head">
                            <h2 class="panel-title"><span class="panel-symbol" style="color:#ee5683">⚠</span>Low Stock Products</h2><a class="view-link" href="products/index.php">View All →</a>
                        </div>
                        <?php if ($lowStockProducts): ?><div class="stock-list">
                                <?php foreach ($lowStockProducts as $product): $imageSrc = hbs_admin_product_image($product['image'] ?? '');
                                    $stock = max(0, (int)$product['stock']);
                                    $fill = min(100, max(8, $stock * 16)); ?>
                                    <div class="stock-row">
                                        <?php if ($imageSrc): ?><img class="product-thumb" src="<?= hbs_admin_e($imageSrc) ?>" alt="<?= hbs_admin_e($product['name']) ?>" onerror="this.style.display='none'">
                                        <?php else: ?><div class="product-thumb product-fallback">✿</div><?php endif; ?>
                                        <div class="product-name"><?= hbs_admin_e($product['name']) ?></div>
                                        <div class="stock-count <?= $stock > 2 ? 'medium' : '' ?>">Stock: <?= $stock ?></div>
                                        <div class="stock-bar">
                                            <div class="stock-fill" style="width:<?= $fill ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div><?php else: ?><div class="empty-state">No low-stock products. Great work!</div><?php endif; ?>
                    </section>

                    <section class="panel">
                        <div class="panel-head">
                            <h2 class="panel-title"><span class="panel-symbol">▣</span>Recent Messages</h2><a class="view-link" href="messages/index.php">View All →</a>
                        </div>
                        <?php if ($recentMessages): ?><div class="message-list">
                                <?php foreach ($recentMessages as $message): $initial = mb_strtoupper(mb_substr((string)$message['name'], 0, 1, 'UTF-8'), 'UTF-8'); ?>
                                    <div class="message-row">
                                        <div class="message-avatar"><?= hbs_admin_e($initial) ?></div>
                                        <div class="message-body">
                                            <div class="message-name"><?= hbs_admin_e($message['name']) ?></div>
                                            <div class="message-text"><?= hbs_admin_e($message['subject'] ?: $message['message']) ?></div>
                                        </div>
                                        <div class="message-time"><?= !empty($message['created_at']) ? date('d M', strtotime($message['created_at'])) : '' ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div><?php else: ?><div class="empty-state">No customer messages yet.</div><?php endif; ?>
                    </section>
                </div>
                <div class="footer-note">© <?= date('Y') ?> Handicraft by Sonia | All Rights Reserved By Team Triple Pixel ♡</div>
            </section>
        </main>
    </div>
    <style>
        .sidebar,
        .main {
            transition: all 0.3s ease;
        }

        body.sidebar-collapsed .sidebar {
            transform: translateX(-100%);
        }

        body.sidebar-collapsed .main {
            margin-left: 0 !important;
        }
    </style>
    <script>
        function toggleSidebar(forceState) {
            const sidebar = document.getElementById('adminSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');

            if (window.innerWidth > 760) {
                document.body.classList.toggle('sidebar-collapsed');
            } else {
                const shouldOpen = typeof forceState === 'boolean' ? forceState : !sidebar.classList.contains('open');
                sidebar.classList.toggle('open', shouldOpen);
                backdrop.classList.toggle('show', shouldOpen);
            }
        }
    </script>
</body>

</html>