<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../config/database.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!function_exists('admin_product_escape')) {
    function admin_product_escape($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$search = trim($_GET['search'] ?? '');
$categoryFilter = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 8;
$offset = ($page - 1) * $perPage;

// Load categories for the filter dropdown.
$categoryStmt = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC');
$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

$successMessage = $_SESSION['admin_product_success'] ?? '';
unset($_SESSION['admin_product_success']);

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(p.name LIKE :search_name OR p.description LIKE :search_description)';
    $params['search_name'] = '%' . $search . '%';
    $params['search_description'] = '%' . $search . '%';
}

if ($categoryFilter !== false && $categoryFilter !== null && $categoryFilter > 0) {
    $where[] = 'p.category_id = :category_id';
    $params['category_id'] = $categoryFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM products p $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalProducts / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$listSql = "
    SELECT
        p.id,
        p.category_id,
        p.name,
        p.description,
        p.price,
        p.image,
        COALESCE(p.stock, 0) AS stock,
        p.created_at,
        c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    $whereSql
    ORDER BY p.id DESC
    LIMIT :limit OFFSET :offset
";
$listStmt = $pdo->prepare($listSql);
foreach ($params as $key => $value) {
    $listStmt->bindValue(':' . $key, $value);
}
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$products = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$fromRow = $totalProducts === 0 ? 0 : $offset + 1;
$toRow = min($offset + $perPage, $totalProducts);
$queryBase = [];
if ($search !== '') $queryBase['search'] = $search;
if ($categoryFilter !== false && $categoryFilter !== null && $categoryFilter > 0) $queryBase['category'] = $categoryFilter;

function product_page_url(int $pageNumber, array $queryBase): string
{
    $queryBase['page'] = $pageNumber;
    return '?' . http_build_query($queryBase);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products | Handicraft by Sonia Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --purple: #5b21b6;
            --purple-dark: #421080;
            --purple-light: #f3eaff;
            --page: #f8f5fc;
            --text: #261b3b;
            --muted: #81768f;
            --border: #eee7f7;
            --white: #fff
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            background: var(--page);
            font-family: 'Poppins', sans-serif;
            color: var(--text)
        }

        .layout {
            min-height: 100vh;
            display: flex
        }

        .sidebar {
            width: 250px;
            flex: 0 0 250px;
            background: linear-gradient(180deg, #552095, #351064);
            color: #fff;
            padding: 25px 15px;
            display: flex;
            flex-direction: column
        }

        .brand {
            text-align: center;
            padding: 2px 8px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, .2)
        }

        .brand img {
            width: 94px;
            height: 94px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            background: #fff
        }

        .brand h2 {
            font-size: 17px;
            margin: 10px 0 2px;
            color: #fff
        }

        .brand span {
            font-size: 12px;
            color: #e8d9ff;
            letter-spacing: 1px
        }

        .nav {
            margin-top: 22px;
            display: grid;
            gap: 6px
        }

        .nav a {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 12px 13px;
            border-radius: 9px;
            color: #f7efff;
            text-decoration: none;
            font-size: 13px;
            transition: .2s
        }

        .nav a i {
            font-size: 17px;
            width: 21px;
            text-align: center
        }

        .nav a:hover,
        .nav a.active {
            background: linear-gradient(90deg, #a855e8, #8b3bd1);
            color: #fff
        }

        .nav .logout {
            margin-top: 12px;
            border-top: 1px solid rgba(255, 255, 255, .18);
            border-radius: 0;
            padding-top: 19px
        }

        .sidebar-note {
            margin-top: auto;
            text-align: center;
            color: #e8d9ff;
            font-family: Georgia, serif;
            font-style: italic;
            font-size: 17px;
            line-height: 1.5;
            padding: 28px 8px 8px
        }

        .main {
            min-width: 0;
            flex: 1
        }

        .topbar {
            height: 82px;
            background: #fff;
            box-shadow: 0 2px 15px rgba(67, 30, 111, .06);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            gap: 15px
        }

        .top-left {
            display: flex;
            align-items: center;
            gap: 18px
        }

        .menu-toggle {
            display: none;
            border: 0;
            background: none;
            color: var(--purple);
            font-size: 25px;
            cursor: pointer
        }

        .topbar h1 {
            font-size: 22px;
            color: var(--purple);
            margin: 0;
            font-weight: 600
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 18px
        }

        .top-actions a {
            color: var(--purple);
            font-size: 21px;
            text-decoration: none
        }

        .admin-chip {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 13px;
            font-weight: 600
        }

        .admin-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--purple-light);
            display: grid;
            place-items: center;
            color: var(--purple);
            font-size: 18px
        }

        .content {
            padding: 28px 32px 38px;
            max-width: 1600px;
            margin: auto
        }

        .page-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 22px
        }

        .eyebrow {
            color: #9b75c9;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase
        }

        .page-heading h2 {
            margin: 3px 0 4px;
            font-size: 25px;
            color: var(--purple-dark)
        }

        .page-heading p {
            margin: 0;
            color: var(--muted);
            font-size: 13px
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 0;
            border-radius: 9px;
            padding: 11px 16px;
            background: var(--purple);
            color: #fff;
            text-decoration: none;
            font: 600 13px 'Poppins', sans-serif;
            cursor: pointer;
            box-shadow: 0 5px 12px rgba(91, 33, 182, .15)
        }

        .btn:hover {
            background: #48158f
        }

        .btn-outline {
            background: #fff;
            color: var(--purple);
            border: 1px solid #dfcff2;
            box-shadow: none
        }

        .summary-row {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 20px
        }

        .summary-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 13px;
            padding: 17px 19px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 4px 15px rgba(62, 26, 103, .035)
        }

        .summary-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: #f0e2ff;
            color: #7c2bd1;
            font-size: 20px
        }

        .summary-card strong {
            display: block;
            font-size: 20px;
            color: #2d174c
        }

        .summary-card small {
            color: var(--muted);
            font-size: 11px
        }

        .panel {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(62, 26, 103, .04);
            overflow: hidden
        }

        .filters {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap
        }

        .filters-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--purple-dark)
        }

        .filter-form {
            display: flex;
            align-items: center;
            gap: 9px;
            flex-wrap: wrap
        }

        .field {
            height: 40px;
            border: 1px solid #e6ddef;
            border-radius: 8px;
            padding: 0 12px;
            background: #fff;
            color: var(--text);
            font: 12px 'Poppins', sans-serif;
            outline: none
        }

        .field:focus {
            border-color: #a879dc;
            box-shadow: 0 0 0 3px #f3eaff
        }

        .search-field {
            width: 230px
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px
        }

        thead {
            background: #f4ecfc
        }

        th {
            text-align: left;
            color: #5c2b91;
            font-size: 11px;
            font-weight: 700;
            padding: 14px 15px;
            white-space: nowrap
        }

        td {
            padding: 13px 15px;
            border-top: 1px solid #f1ebf7;
            font-size: 12px;
            vertical-align: middle
        }

        tbody tr:hover {
            background: #fcfaff
        }

        .product-cell {
            display: flex;
            align-items: center;
            gap: 11px;
            min-width: 220px
        }

        .product-thumb {
            width: 53px;
            height: 53px;
            border-radius: 9px;
            object-fit: cover;
            background: #f5effb;
            border: 1px solid #eee5f7
        }

        .no-image {
            width: 53px;
            height: 53px;
            border-radius: 9px;
            background: #f5effb;
            color: #a78ac9;
            display: grid;
            place-items: center;
            font-size: 20px
        }

        .product-name {
            font-weight: 600;
            color: #34204e
        }

        .product-id {
            font-size: 10px;
            color: #a092b1;
            margin-top: 3px
        }

        .category-pill {
            display: inline-block;
            background: #f2e9fc;
            color: #6b35a0;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 10px;
            white-space: nowrap
        }

        .price {
            font-weight: 700;
            white-space: nowrap
        }

        .stock {
            font-weight: 600;
            white-space: nowrap
        }

        .stock.good {
            color: #15945b
        }

        .stock.low {
            color: #d97706
        }

        .stock.out {
            color: #dc3545
        }

        .date {
            white-space: nowrap;
            color: #756a82
        }

        .actions {
            display: flex;
            gap: 7px
        }

        .icon-btn {
            width: 31px;
            height: 31px;
            display: grid;
            place-items: center;
            border-radius: 7px;
            text-decoration: none;
            border: 1px solid #e9ddf5;
            color: #7137aa;
            background: #fff
        }

        .icon-btn.delete {
            color: #d34b65;
            border-color: #f4dce2
        }

        .empty {
            padding: 45px 20px;
            text-align: center;
            color: var(--muted)
        }

        .empty i {
            display: block;
            font-size: 34px;
            color: #c7addf;
            margin-bottom: 8px
        }

        .empty strong {
            display: block;
            color: #50306f;
            margin-bottom: 4px
        }

        .panel-footer {
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: 11px
        }

        .pagination {
            display: flex;
            gap: 6px
        }

        .pagination a,
        .pagination span {
            min-width: 30px;
            height: 30px;
            padding: 0 8px;
            border: 1px solid #e8def2;
            border-radius: 7px;
            display: grid;
            place-items: center;
            text-decoration: none;
            color: #6b35a0;
            font-size: 11px
        }

        .pagination .current {
            background: var(--purple);
            color: #fff;
            border-color: var(--purple)
        }

        .alert-success {
            padding: 13px 16px;
            border-radius: 9px;
            margin: 0 0 18px;
            background: #eafaf1;
            color: #187447;
            border: 1px solid #c9efd9;
            font-size: 12px
        }

        @media(max-width:1050px) {
            .sidebar {
                width: 220px;
                flex-basis: 220px
            }

            .content {
                padding: 23px 20px
            }

            .topbar {
                padding: 0 20px
            }

            .summary-row {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }
        }

        @media(max-width:760px) {
            .sidebar {
                position: fixed;
                z-index: 20;
                left: -260px;
                top: 0;
                bottom: 0;
                width: 250px;
                transition: left .25s;
                box-shadow: 8px 0 25px rgba(0, 0, 0, .12)
            }

            .sidebar.open {
                left: 0
            }

            .menu-toggle {
                display: block
            }

            .topbar {
                height: 70px;
                padding: 0 15px
            }

            .topbar h1 {
                font-size: 17px
            }

            .top-actions {
                gap: 10px
            }

            .admin-chip .admin-name {
                display: none
            }

            .content {
                padding: 20px 13px
            }

            .page-heading {
                align-items: flex-start;
                flex-direction: column
            }

            .summary-row {
                grid-template-columns: 1fr 1fr;
                gap: 10px
            }

            .summary-card {
                padding: 13px;
                gap: 9px
            }

            .summary-icon {
                width: 38px;
                height: 38px;
                font-size: 17px
            }

            .summary-card strong {
                font-size: 17px
            }

            .filters {
                align-items: flex-start
            }

            .filter-form {
                width: 100%
            }

            .search-field {
                flex: 1;
                min-width: 150px
            }

            .filter-form select {
                flex: 1;
                min-width: 130px
            }

            .panel-footer {
                align-items: flex-start;
                flex-direction: column
            }
        }

        @media(max-width:420px) {
            .summary-row {
                grid-template-columns: 1fr
            }

            .topbar h1 {
                font-size: 15px
            }
        }
    </style>
</head>

<body>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="layout">
        <aside class="sidebar" id="adminSidebar">
            <div class="brand">
                <img src="../../assets/images/images.jpeg" alt="Handicraft by Sonia logo">
                <h2>Handicraft by Sonia</h2>
                <span>— ADMIN PANEL —</span>
            </div>
            <nav class="nav">
                <a href="../dashboard.php"><i class="bi bi-house-door-fill"></i>Dashboard</a>
                <a class="active" href="index.php"><i class="bi bi-box-seam-fill"></i>Products</a>
                <a href="../orders/index.php"><i class="bi bi-bag-check-fill"></i>Orders</a>
                <a href="../customers/index.php"><i class="bi bi-people-fill"></i>Customers</a>
                <a href="../messages/index.php"><i class="bi bi-chat-dots-fill"></i>Messages</a>
                <a href="../custom-orders/index.php"><i class="bi bi-palette-fill"></i>Custom Orders</a>
                <a href="../categories/index.php"><i class="bi bi-grid-fill"></i>Categories</a>
                <a href="../dashboard.php"><i class="bi bi-file-earmark-text-fill"></i>Content Management</a>
                <a href="../dashboard.php"><i class="bi bi-star-fill"></i>Reviews &amp; Ratings</a>
                <a class="logout" href="../logout.php"><i class="bi bi-box-arrow-left"></i>Logout</a>
            </nav>
            <div class="sidebar-note">Crafting Happiness<br>Together ♡</div>
        </aside>

        <main class="main">
            <header class="topbar">
                <div class="top-left">
                    <button class="menu-toggle" type="button" id="menuToggle" aria-label="Toggle menu"><i class="bi bi-list"></i></button>
                    <h1>Product Management</h1>
                </div>
                <div class="top-actions">
                    <a href="../dashboard.php" aria-label="Dashboard"><i class="bi bi-bell"></i></a>
                    <div class="admin-chip"><span class="admin-avatar"><i class="bi bi-person-fill"></i></span><span class="admin-name"><?= admin_product_escape($_SESSION['admin_name'] ?? 'Admin') ?></span></div>
                </div>
            </header>

            <section class="content">
                <div class="page-heading">
                    <div>
                        <div class="eyebrow">Manage your store</div>
                        <h2>All Products</h2>
                        <p>View and manage the products displayed on your customer website.</p>
                    </div>
                    <a class="btn" href="add.php"><i class="bi bi-plus-lg"></i> Add New Product</a>
                </div>

                <?php if ($successMessage !== ''): ?>
                    <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= admin_product_escape($successMessage) ?></div>
                <?php endif; ?>

                <div class="summary-row">
                    <div class="summary-card"><span class="summary-icon"><i class="bi bi-box-seam"></i></span>
                        <div><strong><?= number_format($totalProducts) ?></strong><small>Products Found</small></div>
                    </div>
                    <div class="summary-card"><span class="summary-icon"><i class="bi bi-tags"></i></span>
                        <div><strong><?= number_format(count($categories)) ?></strong><small>Available Categories</small></div>
                    </div>
                    <div class="summary-card"><span class="summary-icon"><i class="bi bi-exclamation-triangle"></i></span>
                        <div><strong><?php
                                        $stockStmt = $pdo->query('SELECT COUNT(*) FROM products WHERE COALESCE(stock, 0) <= 5');
                                        echo number_format((int)$stockStmt->fetchColumn());
                                        ?></strong><small>Low / Out of Stock (≤ 5)</small></div>
                    </div>
                </div>

                <div class="panel">
                    <div class="filters">
                        <div class="filters-title"><i class="bi bi-list-ul"></i> Product List</div>
                        <form class="filter-form" method="GET" action="index.php">
                            <input class="field search-field" type="search" name="search" placeholder="Search product name..." value="<?= admin_product_escape($search) ?>">
                            <select class="field" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int)$category['id'] ?>" <?= ($categoryFilter !== false && (int)$categoryFilter === (int)$category['id']) ? 'selected' : '' ?>><?= admin_product_escape($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn" type="submit"><i class="bi bi-search"></i> Search</button>
                            <?php if ($search !== '' || ($categoryFilter !== false && $categoryFilter !== null && $categoryFilter > 0)): ?>
                                <a class="btn btn-outline" href="index.php">Reset</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>PRODUCT</th>
                                    <th>CATEGORY</th>
                                    <th>PRICE</th>
                                    <th>STOCK</th>
                                    <th>ADDED DATE</th>
                                    <th>ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$products): ?>
                                    <tr>
                                        <td colspan="6">
                                            <div class="empty"><i class="bi bi-box2"></i><strong>No products found</strong><span>Try another search or add a new product.</span></div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($products as $product):
                                        $imageName = basename((string)($product['image'] ?? ''));
                                        $imagePath = '../../assets/images/' . rawurlencode($imageName);
                                        $stock = (int)$product['stock'];
                                        $stockClass = $stock <= 0 ? 'out' : ($stock <= 5 ? 'low' : 'good');
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="product-cell">
                                                    <?php if ($imageName !== ''): ?>
                                                        <img class="product-thumb" src="<?= admin_product_escape($imagePath) ?>" alt="<?= admin_product_escape($product['name']) ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='grid';">
                                                        <span class="no-image" style="display:none"><i class="bi bi-image"></i></span>
                                                    <?php else: ?>
                                                        <span class="no-image"><i class="bi bi-image"></i></span>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="product-name"><?= admin_product_escape($product['name']) ?></div>
                                                        <div class="product-id">Product #<?= (int)$product['id'] ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="category-pill"><?= admin_product_escape($product['category_name'] ?? 'Uncategorized') ?></span></td>
                                            <td class="price">৳ <?= number_format((float)$product['price'], 2) ?></td>
                                            <td><span class="stock <?= $stockClass ?>"><?= $stock ?> <?= $stock === 1 ? 'item' : 'items' ?></span></td>
                                            <td class="date"><?= !empty($product['created_at']) ? date('d M Y', strtotime($product['created_at'])) : '—' ?></td>
                                            <td>

                                                <div class="actions">

                                                    <a class="icon-btn"
                                                        href="edit.php?id=<?= (int)$product['id'] ?>"
                                                        title="Edit product"
                                                        aria-label="Edit product">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </a>

                                                    <form action="delete.php"
                                                        method="POST"
                                                        style="display:inline;"
                                                        onsubmit="return confirm('Are you sure you want to delete this product?');">

                                                        <input type="hidden"
                                                            name="id"
                                                            value="<?= (int)$product['id'] ?>">

                                                        <input type="hidden"
                                                            name="csrf_token"
                                                            value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                                                        <button type="submit"
                                                            class="icon-btn delete"
                                                            title="Delete product"
                                                            aria-label="Delete product">

                                                            <i class="bi bi-trash3"></i>

                                                        </button>

                                                    </form>

                                                </div>

                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="panel-footer">
                        <span>Showing <?= number_format($fromRow) ?>–<?= number_format($toRow) ?> of <?= number_format($totalProducts) ?> products</span>
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?><a href="<?= admin_product_escape(product_page_url($page - 1, $queryBase)) ?>" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a><?php endif; ?>
                                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                                    <?php if ($p === $page): ?><span class="current"><?= $p ?></span><?php else: ?><a href="<?= admin_product_escape(product_page_url($p, $queryBase)) ?>"><?= $p ?></a><?php endif; ?>
                                <?php endfor; ?>
                                <?php if ($page < $totalPages): ?><a href="<?= admin_product_escape(product_page_url($page + 1, $queryBase)) ?>" aria-label="Next page"><i class="bi bi-chevron-right"></i></a><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <script>
        const menuToggle = document.getElementById('menuToggle');
        const adminSidebar = document.getElementById('adminSidebar');
        if (menuToggle && adminSidebar) {
            menuToggle.addEventListener('click', () => adminSidebar.classList.toggle('open'));
        }
    </script>
</body>

</html>