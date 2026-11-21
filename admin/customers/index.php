
<?php
require_once "../config/auth.php";
require_once "../../config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$search = trim($_GET['search'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$where = "";
$params = [];

if ($search !== '') {
    $where = "
        WHERE (
            CAST(u.id AS CHAR) LIKE :id
            OR u.name LIKE :name
            OR u.username LIKE :username
            OR u.email LIKE :email
            OR u.phone LIKE :phone
        )
    ";

    $term = "%{$search}%";

    $params = [
        ':id' => $term,
        ':name' => $term,
        ':username' => $term,
        ':email' => $term,
        ':phone' => $term
    ];
}

// Count customers
$countSql = "
    SELECT COUNT(*)
    FROM users u
    $where
";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);

$totalCustomers = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalCustomers / $limit));

// Customer list with order statistics
$sql = "
    SELECT
        u.id,
        u.name,
        u.username,
        u.email,
        u.phone,
        COALESCE(o.total_orders, 0) AS total_orders,
        COALESCE(o.total_spent, 0) AS total_spent
    FROM users u

    LEFT JOIN (
        SELECT
            user_id,
            COUNT(*) AS total_orders,
            SUM(total_amount) AS total_spent
        FROM orders
        WHERE user_id IS NOT NULL
        GROUP BY user_id
    ) o ON o.user_id = u.id

    $where

    ORDER BY u.id DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}

$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Overall statistics
$statsStmt = $pdo->query("
    SELECT
        COUNT(*) AS total_customers
    FROM users
");

$totalAllCustomers = (int)$statsStmt->fetchColumn();

$orderStatsStmt = $pdo->query("
    SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(total_amount), 0) AS total_sales
    FROM orders
    WHERE user_id IS NOT NULL
");

$orderStats = $orderStatsStmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Customers Management | Admin Panel</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f5f3fa;
    color: #30243a;
}

.main {
    margin-left: 0 !important;
    width: 100%;
    max-width: none;
    padding: 30px;
}

.heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 24px;
}

.heading h1 {
    margin: 0 0 7px;
    font-size: 27px;
}

.heading p {
    margin: 0;
    color: #777;
}

.btn {
    display: inline-block;
    padding: 10px 15px;
    border: none;
    border-radius: 7px;
    background: #5b21b6;
    color: white;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
}

.btn:hover {
    background: #4c1d95;
}

.btn-light {
    background: #eee8f8;
    color: #5b21b6;
}

.btn-light:hover {
    background: #e3d8f5;
}

.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 22px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
}

.stat-card span {
    display: block;
    font-size: 13px;
    color: #777;
    margin-bottom: 9px;
}

.stat-card strong {
    font-size: 25px;
    color: #5b21b6;
}

.panel {
    background: white;
    border-radius: 12px;
    padding: 22px;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
}

.filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.filters input {
    flex: 1;
    min-width: 200px;
    padding: 11px 13px;
    border: 1px solid #ddd;
    border-radius: 7px;
    font-size: 14px;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
}

th, td {
    padding: 14px 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
    font-size: 13px;
}

th {
    background: #faf9fc;
    color: #777;
    font-weight: 600;
}

.customer-name {
    font-weight: bold;
    color: #30243a;
}

.customer-email {
    display: block;
    color: #888;
    font-size: 11px;
    margin-top: 4px;
}

.empty {
    text-align: center;
    padding: 35px;
    color: #888;
}

.pagination {
    display: flex;
    gap: 7px;
    justify-content: center;
    margin-top: 22px;
    flex-wrap: wrap;
}

.pagination a,
.pagination span {
    padding: 8px 12px;
    border-radius: 6px;
    text-decoration: none;
    background: #f0edf6;
    color: #5b21b6;
    font-size: 13px;
}

.pagination .active {
    background: #5b21b6;
    color: white;
}

@media (max-width: 800px) {
    .main {
        padding: 20px;
    }

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 500px) {
    .main {
        padding: 14px;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .panel {
        padding: 14px;
    }
}
</style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main">

    <div class="heading">
        <div>
            <h1>Customers Management</h1>
            <p>View and manage registered customers.</p>
        </div>

        <a href="../dashboard.php" class="btn btn-light">
            ← Dashboard
        </a>
    </div>

    <div class="stats">

        <div class="stat-card">
            <span>Total Registered Customers</span>
            <strong><?= $totalAllCustomers ?></strong>
        </div>

        <div class="stat-card">
            <span>Total Orders (Registered Customers)</span>
            <strong><?= (int)$orderStats['total_orders'] ?></strong>
        </div>

        <div class="stat-card">
            <span>Total Sales (Registered Customers)</span>
            <strong>৳<?= number_format((float)$orderStats['total_sales'], 2) ?></strong>
        </div>

    </div>

    <div class="panel">

        <form method="GET" class="filters">

            <input
                type="text"
                name="search"
                placeholder="Search ID, name, username, email or phone..."
                value="<?= e($search) ?>"
            >

            <button type="submit" class="btn">
                Search
            </button>

            <a href="index.php" class="btn btn-light">
                Reset
            </a>

        </form>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>Customer ID</th>
                        <th>Customer</th>
                        <th>Username</th>
                        <th>Phone</th>
                        <th>Total Orders</th>
                        <th>Total Spent</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($customers): ?>

                    <?php foreach ($customers as $customer): ?>

                    <tr>

                        <td>#<?= (int)$customer['id'] ?></td>

                        <td>
                            <span class="customer-name">
                                <?= e($customer['name']) ?>
                            </span>

                            <span class="customer-email">
                                <?= e($customer['email']) ?>
                            </span>
                        </td>

                        <td><?= e($customer['username']) ?></td>

                        <td><?= e($customer['phone'] ?: 'Not provided') ?></td>

                        <td><?= (int)$customer['total_orders'] ?></td>

                        <td>
                            ৳<?= number_format((float)$customer['total_spent'], 2) ?>
                        </td>

                        <td>
                            <a
                                href="view.php?id=<?= (int)$customer['id'] ?>"
                                class="btn"
                            >
                                View Details
                            </a>
                        </td>

                    </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7" class="empty">
                            No customers found.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <?php if ($totalPages > 1): ?>

        <div class="pagination">

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                <?php
                $query = http_build_query([
                    'search' => $search,
                    'page' => $i
                ]);
                ?>

                <?php if ($i === $page): ?>

                    <span class="active"><?= $i ?></span>

                <?php else: ?>

                    <a href="?<?= e($query) ?>"><?= $i ?></a>

                <?php endif; ?>

            <?php endfor; ?>

        </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
