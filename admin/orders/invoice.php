
<?php
require_once "../config/auth.php";
require_once "../../config/database.php";

function invoice_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit("Invalid order ID.");
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    exit("Order not found.");
}

$itemStmt = $pdo->prepare("
    SELECT *
    FROM order_items
    WHERE order_id = ?
    ORDER BY id ASC
");
$itemStmt->execute([$id]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Invoice #<?= (int)$order['id'] ?> | Handicraft by Sonia</title>

<style>
* { box-sizing:border-box; }

body {
    font-family:Arial,sans-serif;
    background:#f5f5f5;
    color:#30243a;
    padding:25px;
}

.invoice {
    max-width:850px;
    margin:auto;
    background:white;
    padding:45px;
    border-radius:10px;
}

.top {
    display:flex;
    justify-content:space-between;
    gap:20px;
    border-bottom:2px solid #7c3aed;
    padding-bottom:20px;
    margin-bottom:25px;
}

h1 { color:#5b21b6; margin-bottom:8px; }

h2 {
    color:#5b21b6;
    font-size:17px;
    margin:25px 0 15px;
}

p { font-size:13px; line-height:1.7; }

table {
    width:100%;
    border-collapse:collapse;
    margin-top:15px;
}

th {
    background:#f2eaff;
    color:#5b21b6;
    text-align:left;
}

th, td {
    padding:13px 10px;
    border-bottom:1px solid #eee;
    font-size:13px;
}

.totals {
    width:320px;
    max-width:100%;
    margin:20px 0 0 auto;
}

.total-row {
    display:flex;
    justify-content:space-between;
    padding:9px 0;
    font-size:14px;
}

.grand {
    font-weight:bold;
    color:#5b21b6;
    border-top:1px solid #ddd;
    font-size:17px;
    padding-top:13px;
}

.footer {
    text-align:center;
    margin-top:45px;
    border-top:1px solid #eee;
    padding-top:20px;
    color:#81758e;
    font-size:12px;
}

.print-btn {
    display:block;
    margin:20px auto;
    background:#7c3aed;
    color:white;
    border:0;
    padding:12px 25px;
    border-radius:8px;
    cursor:pointer;
}

@media(max-width:600px) {
    body { padding:10px; }
    .invoice { padding:20px; }
    .top { flex-direction:column; }
}

@media print {
    body { background:white; padding:0; }
    .invoice { box-shadow:none; padding:20px; }
    .print-btn { display:none; }
}
</style>
</head>

<body>

<div class="invoice">

    <div class="top">
        <div>
            <h1>Handicraft by Sonia</h1>
            <p>Handmade With Love</p>
            <p>Thank you for shopping with us.</p>
        </div>

        <div>
            <h2>INVOICE</h2>
            <p><strong>Order ID:</strong> #<?= (int)$order['id'] ?></p>
            <p><strong>Date:</strong> <?= date('d M Y', strtotime($order['created_at'])) ?></p>
            <p><strong>Status:</strong> <?= invoice_escape($order['order_status']) ?></p>
        </div>
    </div>

    <h2>Customer Information</h2>

    <p><strong>Name:</strong> <?= invoice_escape($order['customer_name']) ?></p>
    <p><strong>Phone:</strong> <?= invoice_escape($order['phone']) ?></p>
    <p><strong>Address:</strong> <?= nl2br(invoice_escape($order['address'])) ?></p>
    <p><strong>Area:</strong> <?= invoice_escape($order['area']) ?>,
       <?= invoice_escape($order['city']) ?></p>

    <h2>Order Items</h2>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Subtotal</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= invoice_escape($item['product_name']) ?></td>
                    <td>৳<?= number_format((float)$item['price'], 2) ?></td>
                    <td><?= (int)$item['quantity'] ?></td>
                    <td>৳<?= number_format((float)$item['subtotal'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        <div class="total-row">
            <span>Subtotal</span>
            <span>৳<?= number_format((float)$order['subtotal'], 2) ?></span>
        </div>

        <div class="total-row">
            <span>Delivery Charge</span>
            <span>৳<?= number_format((float)$order['delivery_charge'], 2) ?></span>
        </div>

        <div class="total-row grand">
            <span>Total</span>
            <span>৳<?= number_format((float)$order['total_amount'], 2) ?></span>
        </div>
    </div>

    <h2>Payment Information</h2>
    <p><strong>Method:</strong> <?= invoice_escape($order['payment_method']) ?></p>

    <div class="footer">
        Thank you for choosing Handicraft by Sonia!<br>
        Handmade With Love
    </div>

</div>

<button class="print-btn" onclick="window.print()">
    Print Invoice
</button>

</body>
</html>
