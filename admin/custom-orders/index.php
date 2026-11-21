<?php
require_once "../config/auth.php";
require_once "../../config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$allowedStatuses = [
    'Pending', 'Reviewing', 'Approved',
    'In Progress', 'Completed', 'Rejected', 'Cancelled'
];

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        CAST(co.id AS CHAR) LIKE :search
        OR u.name LIKE :search
        OR u.email LIKE :search
        OR co.description LIKE :search
    )";
    $params[':search'] = "%{$search}%";
}

if ($status !== '') {
    $where[] = "co.status = :status";
    $params[':status'] = $status;
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

$countSql = "
    SELECT COUNT(*)
    FROM custom_orders co
    LEFT JOIN users u ON co.user_id = u.id
    $whereSql
";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);

$totalOrders = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalOrders / $limit));

$sql = "
    SELECT
        co.id,
        co.user_id,
        co.description,
        co.required_date,
        co.file_path,
        co.status,
        co.created_at,
        u.name AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone
    FROM custom_orders co
    LEFT JOIN users u ON co.user_id = u.id
    $whereSql
    ORDER BY co.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statsStmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'Pending') AS pending,
        SUM(status = 'Approved') AS approved,
        SUM(status = 'Completed') AS completed
    FROM custom_orders
");

$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

function statusClass($status) {
    $classes = [
        'Pending' => 'pending',
        'Reviewing' => 'reviewing',
        'Approved' => 'approved',
        'In Progress' => 'progress',
        'Completed' => 'completed',
        'Rejected' => 'rejected',
        'Cancelled' => 'cancelled'
    ];

    return $classes[$status] ?? 'other';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Custom Orders | Admin Panel</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: 'DM Sans', Arial, sans-serif;
    background: #fbf8ff;
    color: #30243a;
}

.main {
    padding: 25px 28px 35px;
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
    font-size: 24px;
    color: #482078;
}

.heading p {
    margin: 0;
    color: #82768f;
}

.btn {
    display: inline-block;
    padding: 10px 15px;
    border: none;
    border-radius: 7px;
    background: #5b21b6;
    color: #fff;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
}

.btn:hover { background: #4c1d95; }

.btn-light {
    background: #eee8f8;
    color: #5b21b6;
}

.btn-light:hover { background: #e3d8f5; }

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 22px;
}

.stat-card {
    background: #fff;
    padding: 20px;
    border-radius: 13px;
    border: 1px solid #eee3f7;
    box-shadow: 0 5px 20px rgba(67, 28, 111, .07);
    text-align: center;
}

.stat-card span {
    display: block;
    font-size: 13px;
    color: #777;
    margin-bottom: 9px;
    font-weight: 600;
}

.stat-card strong {
    font-size: 26px;
    color: #5b21b6;
}

.panel {
    background: #fff;
    border-radius: 13px;
    border: 1px solid #eee4f7;
    padding: 22px;
    box-shadow: 0 5px 20px rgba(67, 28, 111, .07);
}

.filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.filters input,
.filters select {
    padding: 11px 13px;
    border: 1px solid #ddd;
    border-radius: 7px;
    font-size: 14px;
}

.filters input {
    flex: 1;
    min-width: 190px;
}

.table-wrap { overflow-x: auto; }

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
    background: #f3e8fc;
    color: #4e247e;
    font-weight: 700;
}

.badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
    white-space: nowrap;
}

.pending { background: #fff0d9; color: #a45b00; }
.reviewing { background: #e8efff; color: #3159a5; }
.approved { background: #e4f7eb; color: #18743c; }
.progress { background: #f0e8ff; color: #6b35ad; }
.completed { background: #d9f5e7; color: #126b3a; }
.rejected, .cancelled { background: #fde8e8; color: #a32121; }
.other { background: #eee; color: #555; }

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
    color: #fff;
}

@media (max-width: 750px) {
    .main { padding: 18px; }
    .stats { grid-template-columns: repeat(2, 1fr); }
    .panel { padding: 14px; }
}
</style>
</head>

<body>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main">

    <div class="heading">
        <div>
            <h1>Custom Orders</h1>
            <p>Manage customer customization requests.</p>
        </div>
    </div>

    <div class="stats">

        <div class="stat-card">
            <span>Total Custom Orders</span>
            <strong><?= (int)($stats['total'] ?? 0) ?></strong>
        </div>

        <div class="stat-card">
            <span>Pending</span>
            <strong><?= (int)($stats['pending'] ?? 0) ?></strong>
        </div>

        <div class="stat-card">
            <span>Approved</span>
            <strong><?= (int)($stats['approved'] ?? 0) ?></strong>
        </div>

        <div class="stat-card">
            <span>Completed</span>
            <strong><?= (int)($stats['completed'] ?? 0) ?></strong>
        </div>

    </div>

    <div class="panel">

        <form method="GET" class="filters">

            <input
                type="text"
                name="search"
                placeholder="Search ID, customer, email or description..."
                value="<?= e($search) ?>"
            >

            <select name="status">
                <option value="">All Status</option>

                <?php foreach ($allowedStatuses as $item): ?>
                    <option value="<?= e($item) ?>"
                        <?= $status === $item ? 'selected' : '' ?>>
                        <?= e($item) ?>
                    </option>
                <?php endforeach; ?>

            </select>

            <button type="submit" class="btn">Search</button>

            <a href="index.php" class="btn btn-light">Reset</a>

        </form>

        <div class="table-wrap">

            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Required Date</th>
                        <th>Submitted Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($orders): ?>

                    <?php foreach ($orders as $order): ?>

                    <tr>
                        <td><strong>#<?= (int)$order['id'] ?></strong></td>

                        <td>
                            <strong><?= e($order['customer_name'] ?? 'Unknown Customer') ?></strong><br>
                            <small><?= e($order['customer_email'] ?? '') ?></small>
                        </td>

                        <td>
                            <?= $order['required_date']
                                ? e(date('d M Y', strtotime($order['required_date'])))
                                : 'Not specified' ?>
                        </td>

                        <td>
                            <?= e(date('d M Y', strtotime($order['created_at']))) ?>
                        </td>

                        <td>
                            <span class="badge <?= e(statusClass($order['status'])) ?>">
                                <?= e($order['status']) ?>
                            </span>
                        </td>

                        <td>
                            <a href="view.php?id=<?= (int)$order['id'] ?>" class="btn">
                                View Details
                            </a>
                        </td>
                    </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="empty">
                            No custom orders found.
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
                    'status' => $status,
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