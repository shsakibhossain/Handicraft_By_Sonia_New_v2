<?php
/**
 * Shared Admin Sidebar & Topbar Layout (With Proper Content Padding & Toggle)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$isSubfolder = (bool)preg_match('#/admin/[^/]+/[^/]+\.php$#', $scriptPath);
$adminBase = $isSubfolder ? '../' : '';
$rootBase  = $isSubfolder ? '../../' : '../';

$activeMenu = 'dashboard';
if (str_contains($scriptPath, '/products/')) {
    $activeMenu = 'products';
} elseif (str_contains($scriptPath, '/orders/')) {
    $activeMenu = 'orders';
} elseif (str_contains($scriptPath, '/categories/')) {
    $activeMenu = 'categories';
} elseif (str_contains($scriptPath, '/customers/')) {
    $activeMenu = 'customers';
} elseif (str_contains($scriptPath, '/messages/')) {
    $activeMenu = 'messages';
} elseif (str_contains($scriptPath, '/custom-orders/')) {
    $activeMenu = 'custom-orders';
} elseif (str_contains($scriptPath, '/content/')) {
    $activeMenu = 'content';
} elseif (str_contains($scriptPath, '/reviews/')) {
    $activeMenu = 'reviews';
} elseif (str_contains($scriptPath, 'notifications.php')) {
    $activeMenu = 'notifications';
} elseif (str_contains($scriptPath, 'reports.php')) {
    $activeMenu = 'reports';
}

$hbsAdminName = $_SESSION['admin_name'] ?? 'Admin';
$hbsFirstName = preg_split('/\s+/', trim($hbsAdminName))[0] ?? 'Admin';
$hbsInitial   = mb_strtoupper(mb_substr($hbsFirstName, 0, 1, 'UTF-8'), 'UTF-8');

$hbsAlertCount = 0;
if (isset($pdo)) {
    try {
        $pOrd = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE LOWER(order_status) = 'pending'")->fetchColumn();
        $uMsg = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE LOWER(status) = 'unread'")->fetchColumn();
        $pCus = (int)$pdo->query("SELECT COUNT(*) FROM custom_orders WHERE LOWER(status) = 'pending'")->fetchColumn();
        $hbsAlertCount = $pOrd + $uMsg + $pCus;
    } catch (Throwable $e) {
        $hbsAlertCount = 0;
    }
}
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

<style>
    body {
        display: block !important;
        margin: 0 !important;
        padding: 0 0 0 252px !important;
        background: #fbf8ff;
        transition: padding-left 0.3s ease;
    }

    body.hbs-collapsed {
        padding-left: 0 !important;
    }

    /* Hide any old sidebar if a subpage already had one */
    .sidebar:not(#hbsAdminSidebar),
    aside:not(#hbsAdminSidebar),
    .admin-sidebar:not(#hbsAdminSidebar) {
        display: none !important;
    }

    /* Give every page's main container clean breathing room away from the sidebar */
    body > .main,
    body > .main-content,
    body > .content,
    body > .container,
    body > .wrapper,
    body > .page-wrapper,
    body > .admin-container,
    body > .admin-shell,
    body > .layout,
    body > div:not(.hbs-backdrop):not(#hbsAdminSidebar) {
        margin-left: 0 !important;
        margin-right: 0 !important;
        padding: 26px 30px 35px !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }

    .hbs-sidebar {
        position: fixed;
        inset: 0 auto 0 0;
        width: 252px;
        background: linear-gradient(180deg, #4b1688 0%, #5b209e 55%, #4b187f 100%);
        color: #fff;
        padding: 20px 13px 18px;
        display: flex;
        flex-direction: column;
        z-index: 1000;
        overflow-y: auto;
        font-family: 'DM Sans', Arial, sans-serif;
        transition: transform 0.3s ease;
    }

    body.hbs-collapsed .hbs-sidebar {
        transform: translateX(-100%);
    }

    .hbs-sidebar a {
        text-decoration: none;
    }
    .hbs-brand {
        text-align: center;
        padding: 2px 8px 19px;
        border-bottom: 1px solid rgba(255, 255, 255, .25);
    }
    .hbs-brand-logo {
        width: 106px;
        height: 106px;
        border-radius: 50%;
        object-fit: cover;
        background: #fff;
        border: 4px solid rgba(255, 255, 255, .85);
        display: block;
        margin: 0 auto 9px;
    }
    .hbs-brand-name {
        font-weight: 800;
        font-size: 17px;
        letter-spacing: .1px;
        color: #fff;
    }
    .hbs-brand-sub {
        font-size: 11px;
        letter-spacing: 2px;
        margin-top: 7px;
        color: #e8d8ff;
    }
    .hbs-brand-rule {
        height: 1px;
        width: 68px;
        background: rgba(255, 255, 255, .65);
        margin: 17px auto 0;
    }
    .hbs-nav {
        display: flex;
        flex-direction: column;
        gap: 5px;
        padding-top: 17px;
    }
    .hbs-nav-link {
        display: flex;
        align-items: center;
        gap: 13px;
        padding: 11px 12px;
        border-radius: 9px;
        color: #f7edff !important;
        font-weight: 600;
        font-size: 14px;
        transition: .2s;
    }
    .hbs-nav-link:hover,
    .hbs-nav-link.active {
        background: linear-gradient(90deg, #a653e9, #9346d5);
        color: #fff !important;
        box-shadow: 0 5px 14px rgba(30, 0, 60, .16);
    }
    .hbs-nav-icon {
        width: 20px;
        text-align: center;
        font-size: 17px;
    }
    .hbs-nav-chevron {
        margin-left: auto;
        font-size: 12px;
    }
    .hbs-sidebar-bottom {
        margin-top: auto;
        text-align: center;
        padding: 24px 8px 0;
        color: #f4e8ff;
    }
    .hbs-yarn {
        font-size: 38px;
        line-height: 1;
    }
    .hbs-tagline {
        font-family: 'Playfair Display', serif;
        font-style: italic;
        font-size: 16px;
        line-height: 1.45;
        margin-top: 8px;
    }
    .hbs-topbar {
        height: 82px;
        background: rgba(248, 240, 255, .92);
        border-bottom: 1px solid #f0e6fa;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 30px;
        position: sticky;
        top: 0;
        z-index: 900;
        backdrop-filter: blur(10px);
        width: 100%;
        font-family: 'DM Sans', Arial, sans-serif;
    }
    .hbs-top-left {
        display: flex;
        align-items: center;
        gap: 22px;
    }
    .hbs-menu-toggle {
        border: 0;
        background: none;
        color: #702bd0;
        font-size: 29px;
        cursor: pointer;
        padding: 0 4px;
    }
    .hbs-top-divider {
        height: 34px;
        width: 1px;
        background: #b993df;
    }
    .hbs-welcome {
        font-family: 'Times New Roman', Times, serif !important;
        font-style: normal !important;
        font-weight: 700;
        color: #6b25bd;
        font-size: 25px;
    }
    .hbs-top-actions {
        display: flex;
        align-items: center;
        gap: 22px;
    }
    .hbs-notification {
        position: relative;
        font-size: 22px;
        color: #762acb;
        text-decoration: none;
    }
    .hbs-notification-count {
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
    .hbs-profile {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        color: #4b2870;
    }
    .hbs-avatar {
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
    .hbs-backdrop {
        display: none;
    }
    @media (max-width: 760px) {
        body {
            padding-left: 0 !important;
        }
        body > .main,
        body > .main-content,
        body > .content,
        body > .container,
        body > .wrapper,
        body > .page-wrapper,
        body > .admin-container,
        body > .admin-shell,
        body > .layout,
        body > div:not(.hbs-backdrop):not(#hbsAdminSidebar) {
            padding: 18px 15px 25px !important;
        }
        .hbs-sidebar {
            transform: translateX(-100%);
            width: 260px;
        }
        .hbs-sidebar.open {
            transform: translateX(0);
        }
        .hbs-backdrop.show {
            display: block;
            position: fixed;
            inset: 0;
            background: rgba(25, 10, 40, .4);
            z-index: 999;
        }
        .hbs-topbar {
            height: 68px;
            padding: 0 15px;
        }
        .hbs-welcome {
            font-size: 18px;
        }
        .hbs-profile-name {
            display: none;
        }
    }
</style>

<div class="hbs-backdrop" id="hbsSidebarBackdrop" onclick="hbsToggleSidebar(false)"></div>

<aside class="hbs-sidebar" id="hbsAdminSidebar">
    <div class="hbs-brand">
        <img class="hbs-brand-logo" src="<?= htmlspecialchars($rootBase, ENT_QUOTES, 'UTF-8') ?>assets/images/images.jpeg" alt="Handicraft by Sonia logo" onerror="this.style.display='none'">
        <div class="hbs-brand-name">Handicraft by Sonia</div>
        <div class="hbs-brand-sub">— &nbsp; ADMIN PANEL &nbsp; —</div>
        <div class="hbs-brand-rule"></div>
    </div>

    <nav class="hbs-nav" aria-label="Admin navigation">
        <a class="hbs-nav-link <?= $activeMenu === 'dashboard' ? 'active' : '' ?>" href="<?= $adminBase ?>dashboard.php">
            <span class="hbs-nav-icon">⌂</span>
            Dashboard
        </a>
        <a class="hbs-nav-link <?= $activeMenu === 'products' ? 'active' : '' ?>" href="<?= $adminBase ?>products/index.php">
            <span class="hbs-nav-icon">▣</span>
            Products
            <span class="hbs-nav-chevron">⌄</span>
        </a>
        <a class="hbs-nav-link <?= $activeMenu === 'orders' ? 'active' : '' ?>" href="<?= $adminBase ?>orders/index.php">
            <span class="hbs-nav-icon">▤</span>
            Orders
            <span class="hbs-nav-chevron">⌄</span>
        </a>
        <a class="hbs-nav-link <?= $activeMenu === 'categories' ? 'active' : '' ?>" href="<?= $adminBase ?>categories/index.php">
            <span class="hbs-nav-icon">▦</span>
            Categories
        </a>
        <a class="hbs-nav-link <?= $activeMenu === 'customers' ? 'active' : '' ?>" href="<?= $adminBase ?>customers/index.php">
            <span class="hbs-nav-icon">♟</span>
            Customers
        </a>
        <a class="hbs-nav-link <?= $activeMenu === 'messages' ? 'active' : '' ?>" href="<?= $adminBase ?>messages/index.php">
            <span class="hbs-nav-icon">▰</span>
            Messages
        </a>
        <a class="hbs-nav-link <?= $activeMenu === 'custom-orders' ? 'active' : '' ?>" href="<?= $adminBase ?>custom-orders/index.php">
            <span class="hbs-nav-icon">✿</span>
            Custom Orders
        </a>
        <a class="hbs-nav-link <?= $activeMenu === 'content' ? 'active' : '' ?>" href="<?= $adminBase ?>content/index.php">
            <span class="hbs-nav-icon">▧</span>
            Content Management
        </a>
        <a class="hbs-nav-link <?= $activeMenu === 'reviews' ? 'active' : '' ?>" href="<?= $adminBase ?>reviews/index.php">
            <span class="hbs-nav-icon">★</span>
            Reviews &amp; Ratings
        </a>
        <a class="hbs-nav-link <?= $activeMenu === 'reports' ? 'active' : '' ?>" href="<?= $adminBase ?>reports.php">
            <span class="hbs-nav-icon">⎙</span>
            Sales Report
        </a>
        <a class="hbs-nav-link <?= $activeMenu === 'notifications' ? 'active' : '' ?>" href="<?= $adminBase ?>notifications.php">
            <span class="hbs-nav-icon">♧</span>
            Notifications
        </a>
        <a class="hbs-nav-link" href="<?= $adminBase ?>logout.php">
            <span class="hbs-nav-icon">⇥</span>
            Logout
        </a>
    </nav>

    <div class="hbs-sidebar-bottom">
        <div class="hbs-yarn">◉</div>
        <div class="hbs-tagline">Crafting Happiness<br>Together ♡</div>
    </div>
</aside>

<header class="hbs-topbar">
    <div class="hbs-top-left">
        <button class="hbs-menu-toggle" type="button" aria-label="Toggle menu" onclick="hbsToggleSidebar()">☰</button>
        <span class="hbs-top-divider"></span>
        <div class="hbs-welcome" style="font-family: 'Times New Roman', Times, serif !important; font-style: normal !important; font-weight: 700 !important;">Welcome, <?= htmlspecialchars($hbsAdminName, ENT_QUOTES, 'UTF-8') ?> ♥</div>
    </div>
    <div class="hbs-top-actions">
        <a class="hbs-notification" href="<?= $adminBase ?>notifications.php" aria-label="Notifications">
            ♧<span class="hbs-notification-count"><?= (int)$hbsAlertCount ?></span>
        </a>
        <div class="hbs-profile">
            <span class="hbs-avatar"><?= htmlspecialchars($hbsInitial, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="hbs-profile-name"><?= htmlspecialchars($hbsFirstName, ENT_QUOTES, 'UTF-8') ?>⌄</span>
        </div>
    </div>
</header>

<script>
function hbsToggleSidebar(forceState) {
    const sidebar = document.getElementById('hbsAdminSidebar');
    const backdrop = document.getElementById('hbsSidebarBackdrop');
    if (!sidebar || !backdrop) return;

    if (window.innerWidth > 760) {
        document.body.classList.toggle('hbs-collapsed');
    } else {
        const shouldOpen = typeof forceState === 'boolean' ? forceState : !sidebar.classList.contains('open');
        sidebar.classList.toggle('open', shouldOpen);
        backdrop.classList.toggle('show', shouldOpen);
    }
}

// Automatically fix any internal 200px+ left margin while keeping nice inner padding
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('body *').forEach(function (el) {
        if (el.id === 'hbsAdminSidebar' || el.closest('#hbsAdminSidebar') || el.classList.contains('hbs-topbar')) return;
        const cs = window.getComputedStyle(el);
        const ml = parseFloat(cs.marginLeft);
        if (ml >= 180) {
            el.style.setProperty('margin-left', '0px', 'important');
        }
        if (cs.display === 'grid' && cs.gridTemplateColumns) {
            const cols = cs.gridTemplateColumns.split(' ');
            if (cols.length === 2 && parseFloat(cols[0]) >= 180 && parseFloat(cols[0]) <= 320) {
                el.style.setProperty('grid-template-columns', '1fr', 'important');
            }
        }
    });
});
</script>