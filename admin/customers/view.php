
<?php
require_once "../config/auth.php";
require_once "../../config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header("Location: index.php");
    exit;
}

// Get customer information
$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        username,
        email,
        phone
    FROM users
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([':id' => $id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header("Location: index.php");
    exit;
}

// Get customer's order statistics
$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(total_amount), 0) AS total_spent
    FROM orders
    WHERE user_id = :user_id
");

$statsStmt->execute([':user_id' => $id]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Get customer's order history
$ordersStmt = $pdo->prepare("
    SELECT
        id,
        total_amount,
        order_status,
        payment_method,
        created_at
    FROM orders
    WHERE user_id = :user_id
    ORDER BY created_at DESC
");

$ordersStmt->execute([':user_id' => $id]);
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

function statusClass($status) {
    $status = strtolower(trim((string)$status));

    if (in_array($status, ['delivered', 'completed'], true)) {
        return 'success';
    }

    if (in_array($status, ['pending', 'processing'], true)) {
        return 'warning';
    }

    if (in_array($status, ['cancelled', 'canceled', 'rejected'], true)) {
        return 'danger';
    }

    return 'neutral';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Customer Details | Admin Panel</title>

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
    margin-bottom: 22px;
}

.heading h1 {
    margin: 0;
    font-size: 27px;
}

.card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 22px;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
}

.card h2 {
    font-size: 18px;
    margin: 0 0 20px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 22px;
}

.info label {
    display: block;
    color: #888;
    font-size: 12px;
    margin-bottom: 7px;
}

.info strong {
    font-size: 14px;
    overflow-wrap: anywhere;
}

.stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
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
    color: #777;
    font-size: 13px;
    margin-bottom: 8px;
}

.stat-card strong {
    font-size: 25px;
    color: #5b21b6;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 750px;
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
}

.badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

.success {
    background: #e4f7eb;
    color: #18743c;
}

.warning {
    background: #fff0d9;
    color: #a45b00;
}

.danger {
    background: #fde8e8;
    color: #a32121;
}

.neutral {
    background: #eee8f8;
    color: #5b21b6;
}

.btn {
    display: inline-block;
    padding: 10px 15px;
    border: none;
    border-radius: 7px;
    background: #5b21b6;
    color: white;
    text-decoration: none;
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

.empty {
    text-align: center;
    padding: 30px;
    color: #888;
}

@media (max-width: 850px) {
    .info-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .main {
        padding: 16px;
    }

    .card {
        padding: 17px;
    }

    .info-grid,
    .stats {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main">

    <div class="heading">

        <div>
            <h1>Customer Details</h1>
            <p>Customer ID: #<?= (int)$customer['id'] ?></p>
        </div>

        <a href="index.php" class="btn btn-light">
            ← Back to Customers
        </a>

    </div>

    <div class="card">

        <h2>Profile & Personal Information</h2>

        <div class="info-grid">

            <div class="info">
                <label>Customer ID</label>
                <strong>#<?= (int)$customer['id'] ?></strong>
            </div>

            <div class="info">
                <label>Full Name</label>
                <strong><?= e($customer['name']) ?></strong>
            </div>

            <div class="info">
                <label>Username</label>
                <strong><?= e($customer['username']) ?></strong>
            </div>

            <div class="info">
                <label>Email Address</label>
                <strong><?= e($customer['email']) ?></strong>
            </div>

            <div class="info">
                <label>Phone Number</label>
                <strong><?= e($customer['phone'] ?: 'Not provided') ?></strong>
            </div>

        </div>

    </div>

    <div class="stats">

        <div class="stat-card">
            <span>Total Orders</span>
            <strong><?= (int)$stats['total_orders'] ?></strong>
        </div>

        <div class="stat-card">
            <span>Total Spent</span>
            <strong>৳<?= number_format((float)$stats['total_spent'], 2) ?></strong>
        </div>

    </div>

    <div class="card">

        <h2>Customer Order History</h2>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Order Date</th>
                        <th>Payment Method</th>
                        <th>Total Amount</th>
                        <th>Order Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($orders): ?>

                    <?php foreach ($orders as $order): ?>

                    <tr>

                        <td>
                            <strong>#<?= (int)$order['id'] ?></strong>
                        </td>

                        <td>
                            <?= e(date('d M Y, h:i A', strtotime($order['created_at']))) ?>
                        </td>

                        <td>
                            <?= e($order['payment_method'] ?: 'Not specified') ?>
                        </td>

                        <td>
                            ৳<?= number_format((float)$order['total_amount'], 2) ?>
                        </td>

                        <td>
                            <span class="badge <?= e(statusClass($order['order_status'])) ?>">
                                <?= e($order['order_status']) ?>
                            </span>
                        </td>

                        <td>
                            <a
                                href="../orders/view.php?id=<?= (int)$order['id'] ?>"
                                class="btn"
                            >
                                View Order
                            </a>
                        </td>

                    </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="empty">
                            This customer has not placed any orders yet.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>
