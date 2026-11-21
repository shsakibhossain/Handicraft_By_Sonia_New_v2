
<?php
require_once "../config/auth.php";
require_once "../../config/database.php";

function order_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header("Location: index.php?error=invalid");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: index.php?error=notfound");
    exit;
}

$itemStmt = $pdo->prepare("
    SELECT *
    FROM order_items
    WHERE order_id = ?
    ORDER BY id ASC
");
$itemStmt->execute([$id]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

$allowedStatuses = [
    'Pending', 'Confirmed', 'Processing',
    'Shipped', 'Delivered', 'Cancelled'
];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$statusClass = strtolower($order['order_status']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Order Details | Handicraft by Sonia</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
* { margin:0; padding:0; box-sizing:border-box; }

body {
    font-family:Arial,sans-serif;
    background:#f7f5fc;
    color:#30243a;
}

.container {
    max-width:1150px;
    margin:30px auto;
    padding:0 20px;
}

.header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    flex-wrap:wrap;
    margin-bottom:25px;
}

.header h1 { color:#4c1d95; font-size:26px; }

.btn {
    display:inline-block;
    text-decoration:none;
    border:0;
    border-radius:8px;
    padding:11px 16px;
    cursor:pointer;
    font-size:13px;
    font-weight:bold;
}

.purple { background:#7c3aed; color:white; }
.light { background:#eee8f7; color:#5b21b6; }

.grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
}

.card {
    background:white;
    border-radius:13px;
    padding:23px;
    box-shadow:0 3px 15px rgba(70,35,120,.05);
    margin-bottom:20px;
}

.card h2 {
    color:#4c1d95;
    font-size:18px;
    margin-bottom:20px;
}

.info {
    display:grid;
    grid-template-columns:140px 1fr;
    gap:13px;
    font-size:14px;
    margin-bottom:13px;
    overflow-wrap:anywhere;
}

.info strong { color:#81758e; }

table { width:100%; border-collapse:collapse; min-width:500px; }

th {
    text-align:left;
    padding:12px;
    background:#f5f0ff;
    color:#5b21b6;
    font-size:12px;
}

td {
    padding:13px 12px;
    border-bottom:1px solid #f0ebf5;
    font-size:13px;
}

.table-wrap { overflow-x:auto; }

.total {
    max-width:360px;
    margin-left:auto;
    margin-top:18px;
}

.total-row {
    display:flex;
    justify-content:space-between;
    padding:9px 0;
    font-size:14px;
}

.grand {
    border-top:1px solid #e8e0f0;
    margin-top:8px;
    padding-top:15px;
    color:#5b21b6;
    font-weight:bold;
    font-size:18px;
}

.status-form select {
    width:100%;
    padding:12px;
    border:1px solid #e2d9ef;
    border-radius:8px;
    margin-bottom:15px;
    background:white;
}

.status-form button { width:100%; }

.notice {
    background:#f5f0ff;
    color:#5b21b6;
    padding:13px;
    border-radius:8px;
    margin-bottom:15px;
    font-size:13px;
}

@media(max-width:750px) {
    .grid { grid-template-columns:1fr; }
    .card { padding:17px; }
    .info { grid-template-columns:115px 1fr; }
}
</style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="container">

    <div class="header">
        <div>
            <h1>Order #<?= (int)$order['id'] ?></h1>
            <p style="color:#81758e;margin-top:7px;">
                Order placed on <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?>
            </p>
        </div>

        <div style="display:flex;gap:9px;flex-wrap:wrap;">
            <a href="index.php" class="btn light">
                <i class="bi bi-arrow-left"></i> Back
            </a>

            <a href="invoice.php?id=<?= (int)$order['id'] ?>"
               target="_blank" class="btn purple">
                <i class="bi bi-printer"></i> Print Invoice
            </a>
        </div>
    </div>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'updated'): ?>
        <div class="notice">
            <i class="bi bi-check-circle"></i>
            Order status updated successfully.
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['error'])): ?>
        <div class="notice" style="background:#fff0f0;color:#b42318;">
            Unable to update order status. Please check and try again.
        </div>
    <?php endif; ?>

    <div class="grid">

        <div class="card">
            <h2><i class="bi bi-person"></i> Customer Information</h2>

            <div class="info">
                <strong>Customer Name</strong>
                <span><?= order_escape($order['customer_name']) ?></span>
            </div>

            <div class="info">
                <strong>Phone</strong>
                <span><?= order_escape($order['phone']) ?></span>
            </div>

            <div class="info">
                <strong>Customer ID</strong>
                <span><?= $order['user_id'] !== null ? (int)$order['user_id'] : 'Guest Order' ?></span>
            </div>

            <div class="info">
                <strong>Address</strong>
                <span><?= nl2br(order_escape($order['address'])) ?></span>
            </div>

            <div class="info">
                <strong>Area</strong>
                <span><?= order_escape($order['area']) ?></span>
            </div>

            <div class="info">
                <strong>City</strong>
                <span><?= order_escape($order['city']) ?></span>
            </div>
        </div>

        <div class="card">
            <h2><i class="bi bi-credit-card"></i> Payment Information</h2>

            <div class="info">
                <strong>Payment Method</strong>
                <span><?= order_escape($order['payment_method']) ?></span>
            </div>

            <div class="info">
                <strong>Payment Number</strong>
                <span><?= !empty($order['payment_number']) ? order_escape($order['payment_number']) : 'Not provided' ?></span>
            </div>

            <div class="info">
                <strong>Transaction ID</strong>
                <span><?= !empty($order['transaction_id']) ? order_escape($order['transaction_id']) : 'Not provided' ?></span>
            </div>

            <div class="info">
                <strong>Current Status</strong>
                <span><strong><?= order_escape($order['order_status']) ?></strong></span>
            </div>

            <hr style="border:0;border-top:1px solid #eee;margin:20px 0;">

            <h2><i class="bi bi-arrow-repeat"></i> Update Order Status</h2>

            <form action="update-status.php" method="POST" class="status-form">

                <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">

                <input type="hidden" name="csrf_token"
                       value="<?= order_escape($_SESSION['csrf_token']) ?>">

                <select name="order_status" required>
                    <?php foreach ($allowedStatuses as $s): ?>
                        <option value="<?= order_escape($s) ?>"
                            <?= $order['order_status'] === $s ? 'selected' : '' ?>>
                            <?= order_escape($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn purple">
                    <i class="bi bi-check-circle"></i> Update Status
                </button>

            </form>
        </div>

    </div>

    <div class="card">

        <h2><i class="bi bi-bag"></i> Ordered Products</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= order_escape($item['product_name']) ?></td>
                            <td>৳<?= number_format((float)$item['price'], 2) ?></td>
                            <td><?= (int)$item['quantity'] ?></td>
                            <td>৳<?= number_format((float)$item['subtotal'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$items): ?>
                        <tr>
                            <td colspan="4">No order items found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="total">
            <div class="total-row">
                <span>Subtotal</span>
                <span>৳<?= number_format((float)$order['subtotal'], 2) ?></span>
            </div>

            <div class="total-row">
                <span>Delivery Charge</span>
                <span>৳<?= number_format((float)$order['delivery_charge'], 2) ?></span>
            </div>

            <div class="total-row grand">
                <span>Total Amount</span>
                <span>৳<?= number_format((float)$order['total_amount'], 2) ?></span>
            </div>
        </div>

    </div>

</div>

</body>
</html>
