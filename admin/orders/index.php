
<?php
require_once "../config/auth.php";
require_once "../../config/database.php";

function order_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$allowedStatuses = [
    'Pending', 'Confirmed', 'Processing',
    'Shipped', 'Delivered', 'Cancelled'
];

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$perPage = 10;
$offset = ($page - 1) * $perPage;

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        CAST(o.id AS CHAR) LIKE :search
        OR o.customer_name LIKE :search
        OR o.phone LIKE :search
    )";
    $params[':search'] = '%' . $search . '%';
}

if ($status !== '') {
    $where[] = "o.order_status = :status";
    $params[':status'] = $status;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM orders o
        $whereSQL
    ");

    $countStmt->execute($params);
    $totalOrders = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($totalOrders / $perPage));

    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    $sql = "
        SELECT
            o.*,
            (
                SELECT COALESCE(SUM(oi.quantity), 0)
                FROM order_items oi
                WHERE oi.order_id = o.id
            ) AS total_items
        FROM orders o
        $whereSQL
        ORDER BY o.id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(order_status = 'Pending') AS pending,
            SUM(order_status = 'Processing') AS processing,
            SUM(order_status = 'Delivered') AS delivered
        FROM orders
    ")->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Unable to load orders. Please check your database.");
}

$successMessages = [
    'updated' => 'Order status updated successfully.'
];

$errorMessages = [
    'invalid' => 'Invalid order information.',
    'security' => 'Security verification failed.',
    'notfound' => 'Order not found.',
    'failed' => 'Unable to update order status.'
];

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

function order_status_class($status) {
    switch (strtolower($status)) {
        case 'delivered': return 'delivered';
        case 'cancelled': return 'cancelled';
        case 'shipped': return 'shipped';
        case 'processing': return 'processing';
        case 'confirmed': return 'confirmed';
        default: return 'pending';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Orders Management | Handicraft by Sonia</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
* { margin:0; padding:0; box-sizing:border-box; }

body {
    font-family:Arial,sans-serif;
    background:#f7f5fc;
    color:#30243a;
}

.main {
    max-width:1450px;
    margin:auto;
    padding:30px;
}

.header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:15px;
    margin-bottom:25px;
}

.header h1 {
    color:#4c1d95;
    font-size:27px;
    margin-bottom:7px;
}

.header p { color:#81758e; font-size:14px; }

.back {
    color:white;
    background:#7c3aed;
    text-decoration:none;
    padding:11px 17px;
    border-radius:8px;
    font-size:14px;
}

.stats {
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:18px;
    margin-bottom:25px;
}

.stat {
    background:white;
    padding:21px;
    border-radius:12px;
    box-shadow:0 3px 15px rgba(70,35,120,.05);
}

.stat p { color:#81758e; font-size:13px; margin-bottom:10px; }
.stat h2 { font-size:27px; color:#4c1d95; }

.card {
    background:white;
    border-radius:13px;
    padding:23px;
    box-shadow:0 3px 15px rgba(70,35,120,.05);
}

.toolbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:15px;
    margin-bottom:20px;
}

.toolbar h2 { font-size:18px; color:#4c1d95; }

.filters {
    display:flex;
    flex-wrap:wrap;
    gap:9px;
}

.filters input, .filters select {
    padding:10px 12px;
    border:1px solid #e2d9ef;
    border-radius:8px;
    outline:none;
    min-width:150px;
}

.filters button {
    border:0;
    background:#7c3aed;
    color:white;
    padding:10px 16px;
    border-radius:8px;
    cursor:pointer;
}

.reset {
    text-decoration:none;
    color:#6b21a8;
    padding:10px;
    font-size:13px;
}

.alert {
    padding:13px;
    border-radius:8px;
    margin-bottom:18px;
    font-size:14px;
}

.success { background:#eaf8ef; color:#187443; }
.error { background:#fff0f0; color:#b42318; }

.table-wrap { overflow-x:auto; }

table {
    width:100%;
    min-width:900px;
    border-collapse:collapse;
}

th {
    background:#f5f0ff;
    color:#5b21b6;
    text-align:left;
    padding:14px;
    font-size:13px;
}

td {
    padding:15px 14px;
    border-bottom:1px solid #f0ebf5;
    font-size:13px;
}

.status {
    display:inline-block;
    padding:6px 10px;
    border-radius:20px;
    font-size:11px;
    font-weight:bold;
}

.pending { background:#fff4d8; color:#946200; }
.confirmed { background:#e8eaff; color:#4338a8; }
.processing { background:#eee5ff; color:#6b21a8; }
.shipped { background:#e0f2fe; color:#0369a1; }
.delivered { background:#dcfce7; color:#15803d; }
.cancelled { background:#fee2e2; color:#b91c1c; }

.view-btn {
    background:#7c3aed;
    color:white;
    text-decoration:none;
    padding:8px 12px;
    border-radius:7px;
    font-size:12px;
    white-space:nowrap;
}

.empty {
    text-align:center;
    padding:40px;
    color:#81758e;
}

.pagination {
    display:flex;
    gap:7px;
    justify-content:center;
    margin-top:22px;
    flex-wrap:wrap;
}

.pagination a, .pagination span {
    padding:8px 12px;
    border:1px solid #e2d9ef;
    border-radius:7px;
    text-decoration:none;
    color:#6b21a8;
    font-size:13px;
}

.pagination .active {
    background:#7c3aed;
    color:white;
    border-color:#7c3aed;
}

@media(max-width:800px) {
    .stats { grid-template-columns:repeat(2,1fr); }
}

@media(max-width:550px) {
    .main { padding:17px; }
    .stats { grid-template-columns:1fr 1fr; gap:10px; }
    .stat { padding:15px; }
    .stat h2 { font-size:22px; }
    .card { padding:15px; }
    .filters { width:100%; }
    .filters input, .filters select { width:100%; }
}
</style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main">

    <div class="header">
        <div>
            <h1>Orders Management</h1>
            <p>View and manage all customer orders.</p>
        </div>

        <a href="../dashboard.php" class="back">
            <i class="bi bi-arrow-left"></i> Dashboard
        </a>
    </div>

    <div class="stats">
        <div class="stat">
            <p>Total Orders</p>
            <h2><?= (int)($stats['total'] ?? 0) ?></h2>
        </div>

        <div class="stat">
            <p>Pending Orders</p>
            <h2><?= (int)($stats['pending'] ?? 0) ?></h2>
        </div>

        <div class="stat">
            <p>Processing Orders</p>
            <h2><?= (int)($stats['processing'] ?? 0) ?></h2>
        </div>

        <div class="stat">
            <p>Delivered Orders</p>
            <h2><?= (int)($stats['delivered'] ?? 0) ?></h2>
        </div>
    </div>

    <?php if (isset($successMessages[$success])): ?>
        <div class="alert success">
            <i class="bi bi-check-circle"></i>
            <?= order_escape($successMessages[$success]) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($errorMessages[$error])): ?>
        <div class="alert error">
            <i class="bi bi-exclamation-circle"></i>
            <?= order_escape($errorMessages[$error]) ?>
        </div>
    <?php endif; ?>

    <div class="card">

        <div class="toolbar">
            <h2>All Orders</h2>

            <form method="GET" class="filters">

                <input
                    type="text"
                    name="search"
                    placeholder="Order ID, name or phone"
                    value="<?= order_escape($search) ?>"
                >

                <select name="status">
                    <option value="">All Statuses</option>

                    <?php foreach ($allowedStatuses as $s): ?>
                        <option value="<?= order_escape($s) ?>"
                            <?= $status === $s ? 'selected' : '' ?>>
                            <?= order_escape($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">
                    <i class="bi bi-search"></i> Search
                </button>

                <a href="index.php" class="reset">Reset</a>

            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Items</th>
                        <th>Total Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($orders): ?>

                    <?php foreach ($orders as $order): ?>

                        <tr>
                            <td>#<?= (int)$order['id'] ?></td>

                            <td><?= order_escape($order['customer_name']) ?></td>

                            <td><?= order_escape($order['phone']) ?></td>

                            <td><?= (int)$order['total_items'] ?></td>

                            <td>
                                ৳<?= number_format((float)$order['total_amount'], 2) ?>
                            </td>

                            <td><?= order_escape($order['payment_method']) ?></td>

                            <td>
                                <span class="status <?= order_status_class($order['order_status']) ?>">
                                    <?= order_escape($order['order_status']) ?>
                                </span>
                            </td>

                            <td>
                                <?= date('d M Y', strtotime($order['created_at'])) ?>
                            </td>

                            <td>
                                <a class="view-btn"
                                   href="view.php?id=<?= (int)$order['id'] ?>">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="9" class="empty">
                            <i class="bi bi-inbox" style="font-size:30px;"></i>
                            <p>No orders found.</p>
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

                    <a href="?<?= order_escape($query) ?>"
                       class="<?= $page === $i ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>

                <?php endfor; ?>

            </div>
        <?php endif; ?>

    </div>

</div>

</body>
</html>
