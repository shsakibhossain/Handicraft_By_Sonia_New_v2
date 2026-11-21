<?php
require_once "config/auth.php";
require_once "../config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($val) {
    return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
}

// 1. Capture & Validate Filters
$fromDate = trim($_GET['from_date'] ?? '');
$toDate   = trim($_GET['to_date'] ?? '');
$status   = trim($_GET['status'] ?? '');
$payment  = trim($_GET['payment_method'] ?? '');

$allowedStatuses = ['Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
$allowedPayments = ['cash_on_delivery', 'bkash', 'nagad', 'rocket'];

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
    $fromDate = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
    $toDate = '';
}
if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}
if (!in_array($payment, $allowedPayments, true)) {
    $payment = '';
}

// Build Dynamic WHERE Clause
$where = [];
$params = [];

if ($fromDate !== '') {
    $where[] = "DATE(o.created_at) >= :from_date";
    $params[':from_date'] = $fromDate;
}
if ($toDate !== '') {
    $where[] = "DATE(o.created_at) <= :to_date";
    $params[':to_date'] = $toDate;
}
if ($status !== '') {
    $where[] = "o.order_status = :status";
    $params[':status'] = $status;
}
if ($payment !== '') {
    $where[] = "o.payment_method = :payment";
    $params[':payment'] = $payment;
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

// 2. Executive KPI Summary Query
$kpiSql = "
    SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(CASE WHEN LOWER(o.order_status) != 'cancelled' THEN o.total_amount ELSE 0 END), 0) AS active_revenue,
        COALESCE(SUM(CASE WHEN LOWER(o.order_status) != 'cancelled' THEN o.subtotal ELSE 0 END), 0) AS net_subtotal,
        COALESCE(SUM(CASE WHEN LOWER(o.order_status) != 'cancelled' THEN o.delivery_charge ELSE 0 END), 0) AS total_delivery,
        COALESCE(SUM(CASE WHEN LOWER(o.order_status) = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_count,
        COALESCE(SUM(CASE WHEN LOWER(o.order_status) = 'cancelled' THEN o.total_amount ELSE 0 END), 0) AS cancelled_amount
    FROM orders o
    $whereSql
";
$kpiStmt = $pdo->prepare($kpiSql);
$kpiStmt->execute($params);
$kpi = $kpiStmt->fetch(PDO::FETCH_ASSOC);

// 3. Payment Method Breakdown (Non-cancelled or filtered)
$paySql = "
    SELECT
        o.payment_method,
        COUNT(*) AS order_count,
        COALESCE(SUM(o.total_amount), 0) AS total_sum
    FROM orders o
    $whereSql
    GROUP BY o.payment_method
    ORDER BY total_sum DESC
";
$payStmt = $pdo->prepare($paySql);
$payStmt->execute($params);
$paymentBreakdown = $payStmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Order Status Breakdown
$statSql = "
    SELECT
        o.order_status,
        COUNT(*) AS order_count,
        COALESCE(SUM(o.total_amount), 0) AS total_sum
    FROM orders o
    $whereSql
    GROUP BY o.order_status
    ORDER BY order_count DESC
";
$statStmt = $pdo->prepare($statSql);
$statStmt->execute($params);
$statusBreakdown = $statStmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Top-Selling Products Summary
$prodWhere = $where;
$prodWhere[] = "LOWER(o.order_status) != 'cancelled'";
$prodWhereSql = "WHERE " . implode(" AND ", $prodWhere);

$topProdSql = "
    SELECT
        oi.product_id,
        COALESCE(p.name, CONCAT('Product #', oi.product_id)) AS product_name,
        COALESCE(p.price, oi.price) AS unit_price,
        COALESCE(p.stock, 0) AS current_stock,
        SUM(oi.quantity) AS units_sold,
        SUM(oi.price * oi.quantity) AS product_revenue
    FROM order_items oi
    INNER JOIN orders o ON oi.order_id = o.id
    LEFT JOIN products p ON oi.product_id = p.id
    $prodWhereSql
    GROUP BY oi.product_id, p.name, p.price, oi.price, p.stock
    ORDER BY units_sold DESC, product_revenue DESC
    LIMIT 10
";
$topProdStmt = $pdo->prepare($topProdSql);
$topProdStmt->execute($params);
$topProducts = $topProdStmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Detailed Itemized Order Ledger
$ledgerSql = "
    SELECT o.*, u.phone AS user_phone
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    $whereSql
    ORDER BY o.created_at DESC
";
$ledgerStmt = $pdo->prepare($ledgerSql);
$ledgerStmt->execute($params);
$orders = $ledgerStmt->fetchAll(PDO::FETCH_ASSOC);

// Helper formatting
function formatPaymentLabel($method) {
    return match (strtolower((string)$method)) {
        'cash_on_delivery' => 'Cash on Delivery',
        'bkash'            => 'bKash',
        'nagad'            => 'Nagad',
        'rocket'           => 'Rocket',
        default            => ucfirst((string)$method)
    };
}

$reportRef   = 'RPT-' . date('Ymd') . '-' . strtoupper(substr(md5($fromDate . $toDate . $status . $payment), 0, 4));
$adminName   = $_SESSION['admin_name'] ?? 'System Administrator';
$rangeLabel  = ($fromDate || $toDate)
    ? (($fromDate ?: 'Beginning') . ' to ' . ($toDate ?: 'Present'))
    : 'All Recorded Dates';
$grandTotalSumAll = array_sum(array_column($paymentBreakdown, 'total_sum'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crystal Report — Sales &amp; Revenue | Handicraft by Sonia</title>
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0;
        background: #fbf8ff;
        color: #1f1630;
        font-family: 'Times New Roman', Times, serif;
    }

    .report-wrapper {
        padding: 26px 30px 45px;
        max-width: 1280px;
    }

    /* Filter Toolbar (Screen Only) */
    .filter-card {
        background: #ffffff;
        border: 1px solid #e5d6f7;
        border-radius: 12px;
        padding: 18px 22px;
        margin-bottom: 22px;
        box-shadow: 0 4px 16px rgba(76, 29, 149, 0.06);
        font-family: Arial, sans-serif;
    }
    .filter-title {
        font-size: 14px;
        font-weight: 700;
        color: #4c1d95;
        margin: 0 0 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .filter-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 12px;
        align-items: end;
    }
    .filter-group label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #5b4b70;
        margin-bottom: 5px;
    }
    .filter-group input,
    .filter-group select {
        width: 100%;
        padding: 9px 11px;
        border: 1px solid #d5c4eb;
        border-radius: 7px;
        font-size: 13px;
        background: #fdfbff;
    }
    .filter-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .btn {
        padding: 10px 15px;
        border: none;
        border-radius: 7px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }
    .btn-primary { background: #5b21b6; color: #fff; }
    .btn-primary:hover { background: #4c1d95; }
    .btn-print { background: #111827; color: #fff; }
    .btn-print:hover { background: #000000; }
    .btn-reset { background: #f1e9fb; color: #5b21b6; }

    /* Crystal Report Sheet Container */
    .crystal-sheet {
        background: #ffffff;
        border: 1.5px solid #2d1b4e;
        padding: 32px 36px;
        box-shadow: 0 8px 28px rgba(45, 27, 78, 0.08);
    }

    /* Report Header */
    .cr-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 2.5px double #2d1b4e;
        padding-bottom: 16px;
        margin-bottom: 20px;
        gap: 20px;
    }
    .cr-brand h1 {
        margin: 0 0 4px;
        font-size: 28px;
        color: #2d1b4e;
        letter-spacing: 0.4px;
        text-transform: uppercase;
    }
    .cr-brand .subtitle {
        font-size: 16px;
        font-weight: 700;
        color: #4c1d95;
        margin: 0 0 4px;
    }
    .cr-brand .address {
        font-size: 13px;
        color: #444;
        margin: 0;
    }
    .cr-meta {
        text-align: right;
        font-size: 13px;
        line-height: 1.65;
        color: #222;
    }
    .cr-meta strong { color: #000; }

    /* KPI Strip */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 10px;
        margin-bottom: 22px;
    }
    .kpi-box {
        border: 1px solid #3b2663;
        background: #faf7ff;
        padding: 12px 14px;
        text-align: center;
    }
    .kpi-box .kpi-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #4b3a63;
        font-weight: 700;
        margin-bottom: 6px;
    }
    .kpi-box .kpi-val {
        font-size: 19px;
        font-weight: 700;
        color: #1f1137;
        font-variant-numeric: tabular-nums;
    }
    .kpi-box .kpi-sub {
        font-size: 11px;
        color: #666;
        margin-top: 3px;
    }

    /* Section Headings */
    .cr-section-title {
        font-size: 15px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #2d1b4e;
        background: #f3ebff;
        border-left: 4px solid #4c1d95;
        padding: 7px 12px;
        margin: 22px 0 10px;
    }

    /* Two-column Analytical Grid */
    .two-col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 10px;
    }

    /* Crystal Report Formal Tables */
    table.cr-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        font-variant-numeric: tabular-nums;
    }
    table.cr-table th,
    table.cr-table td {
        border: 1px solid #8c7aa8;
        padding: 7px 10px;
        text-align: left;
    }
    table.cr-table th {
        background: #ede3fc;
        color: #1f1137;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 11.5px;
    }
    table.cr-table td.num,
    table.cr-table th.num {
        text-align: right;
    }
    table.cr-table td.center,
    table.cr-table th.center {
        text-align: center;
    }
    table.cr-table tbody tr:nth-child(even) {
        background: #fcfaff;
    }
    table.cr-table tfoot td {
        background: #ede3fc;
        font-weight: 700;
        border-top: 2px solid #2d1b4e;
        border-bottom: 2px solid #2d1b4e;
        color: #111;
    }

    .status-pill {
        display: inline-block;
        padding: 2px 7px;
        border: 1px solid #666;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 700;
    }

    /* Signatures & Footer */
    .cr-footer {
        margin-top: 34px;
        padding-top: 14px;
        border-top: 1px solid #8c7aa8;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 20px;
        font-size: 12px;
        color: #333;
    }
    .sig-group {
        display: flex;
        gap: 48px;
    }
    .sig-box {
        text-align: center;
        min-width: 170px;
    }
    .sig-line {
        border-top: 1px solid #111;
        margin-top: 36px;
        padding-top: 5px;
        font-weight: 700;
        font-size: 12.5px;
    }

    @media (max-width: 900px) {
        .kpi-grid { grid-template-columns: repeat(2, 1fr); }
        .two-col { grid-template-columns: 1fr; }
        .cr-header { flex-direction: column; }
        .cr-meta { text-align: left; }
    }

    /* STRICT MONOCHROME PRINT STYLES (CRYSTAL REPORT EXPORT) */
    @page {
        size: A4 landscape;
        margin: 12mm;
    }
    @media print {
        body {
            background: #ffffff !important;
            padding-left: 0 !important;
            color: #000000 !important;
        }
        .hbs-sidebar,
        .hbs-topbar,
        .hbs-backdrop,
        .filter-card,
        .no-print {
            display: none !important;
        }
        .report-wrapper {
            padding: 0 !important;
            max-width: 100% !important;
            width: 100% !important;
        }
        .crystal-sheet {
            border: 1.5px solid #000000 !important;
            box-shadow: none !important;
            padding: 16px 20px !important;
        }
        .cr-header {
            border-bottom-color: #000000 !important;
        }
        .cr-brand h1,
        .cr-brand .subtitle,
        .cr-section-title,
        .kpi-box .kpi-val {
            color: #000000 !important;
        }
        .kpi-box {
            background: #ffffff !important;
            border: 1px solid #000000 !important;
        }
        .cr-section-title {
            background: #f2f2f2 !important;
            border-left: 4px solid #000000 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        table.cr-table th,
        table.cr-table tfoot td {
            background: #ededed !important;
            color: #000000 !important;
            border-color: #000000 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        table.cr-table td {
            border-color: #444444 !important;
        }
        tr, .kpi-box, .cr-footer {
            break-inside: avoid;
        }
    }
</style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="report-wrapper">

    <!-- Filter Controls (Hidden on Print) -->
    <div class="filter-card no-print">
        <div class="filter-title">Crystal Report Parameters &amp; Filters</div>
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label for="from_date">From Date</label>
                <input type="date" id="from_date" name="from_date" value="<?= e($fromDate) ?>">
            </div>

            <div class="filter-group">
                <label for="to_date">To Date</label>
                <input type="date" id="to_date" name="to_date" value="<?= e($toDate) ?>">
            </div>

            <div class="filter-group">
                <label for="status">Order Status</label>
                <select id="status" name="status">
                    <option value="">All Statuses</option>
                    <?php foreach ($allowedStatuses as $st): ?>
                        <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="payment_method">Payment Method</label>
                <select id="payment_method" name="payment_method">
                    <option value="">All Methods</option>
                    <?php foreach ($allowedPayments as $pm): ?>
                        <option value="<?= e($pm) ?>" <?= $payment === $pm ? 'selected' : '' ?>><?= e(formatPaymentLabel($pm)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Generate Report</button>
                <a href="reports.php" class="btn btn-reset">Reset</a>
                <button type="button" class="btn btn-print" onclick="window.print()">⎙ Print / Export PDF</button>
            </div>
        </form>
    </div>

    <!-- Printable Crystal Report Document -->
    <div class="crystal-sheet">

        <!-- Formal Header -->
        <div class="cr-header">
            <div class="cr-brand">
                <h1>Handicraft by Sonia</h1>
                <div class="subtitle">Sales, Revenue &amp; Inventory Analytical Report (Crystal Report)</div>
                <p class="address">South Khulshi, Chattogram, Bangladesh &bull; E-Commerce Management System</p>
            </div>
            <div class="cr-meta">
                <div><strong>Report ID:</strong> <?= e($reportRef) ?></div>
                <div><strong>Date Range:</strong> <?= e($rangeLabel) ?></div>
                <div><strong>Status Filter:</strong> <?= e($status ?: 'All Statuses') ?> | <strong>Payment:</strong> <?= e($payment ? formatPaymentLabel($payment) : 'All') ?></div>
                <div><strong>Generated On:</strong> <?= e(date('d M Y, h:i A')) ?></div>
                <div><strong>Prepared By:</strong> <?= e($adminName) ?></div>
            </div>
        </div>

        <!-- Executive KPI Strip -->
        <div class="kpi-grid">
            <div class="kpi-box">
                <div class="kpi-label">Total Orders</div>
                <div class="kpi-val"><?= (int)$kpi['total_orders'] ?></div>
                <div class="kpi-sub">Recorded in period</div>
            </div>
            <div class="kpi-box">
                <div class="kpi-label">Active Gross Revenue</div>
                <div class="kpi-val">৳ <?= number_format((float)$kpi['active_revenue'], 2) ?></div>
                <div class="kpi-sub">Excludes cancelled orders</div>
            </div>
            <div class="kpi-box">
                <div class="kpi-label">Net Product Sales</div>
                <div class="kpi-val">৳ <?= number_format((float)$kpi['net_subtotal'], 2) ?></div>
                <div class="kpi-sub">Subtotal before shipping</div>
            </div>
            <div class="kpi-box">
                <div class="kpi-label">Delivery Charges</div>
                <div class="kpi-val">৳ <?= number_format((float)$kpi['total_delivery'], 2) ?></div>
                <div class="kpi-sub">Shipping fees collected</div>
            </div>
            <div class="kpi-box">
                <div class="kpi-label">Cancelled Orders</div>
                <div class="kpi-val"><?= (int)$kpi['cancelled_count'] ?></div>
                <div class="kpi-sub">Value: ৳ <?= number_format((float)$kpi['cancelled_amount'], 2) ?></div>
            </div>
        </div>

        <!-- Section A: Payment & Status Breakdown -->
        <div class="two-col">
            <div>
                <div class="cr-section-title">Section A1: Revenue by Payment Method</div>
                <table class="cr-table">
                    <thead>
                        <tr>
                            <th>Payment Method</th>
                            <th class="center">Orders</th>
                            <th class="num">Total Amount (৳)</th>
                            <th class="num">Share (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($paymentBreakdown): ?>
                            <?php foreach ($paymentBreakdown as $row): ?>
                                <?php
                                    $amt = (float)$row['total_sum'];
                                    $pct = $grandTotalSumAll > 0 ? ($amt / $grandTotalSumAll) * 100 : 0;
                                ?>
                                <tr>
                                    <td><?= e(formatPaymentLabel($row['payment_method'])) ?></td>
                                    <td class="center"><?= (int)$row['order_count'] ?></td>
                                    <td class="num"><?= number_format($amt, 2) ?></td>
                                    <td class="num"><?= number_format($pct, 1) ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="center">No data available.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div>
                <div class="cr-section-title">Section A2: Orders by Fulfillment Status</div>
                <table class="cr-table">
                    <thead>
                        <tr>
                            <th>Order Status</th>
                            <th class="center">Order Count</th>
                            <th class="num">Total Value (৳)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($statusBreakdown): ?>
                            <?php foreach ($statusBreakdown as $row): ?>
                                <tr>
                                    <td><span class="status-pill"><?= e($row['order_status']) ?></span></td>
                                    <td class="center"><?= (int)$row['order_count'] ?></td>
                                    <td class="num"><?= number_format((float)$row['total_sum'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="center">No data available.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section B: Top-Selling Products Summary -->
        <div class="cr-section-title">Section B: Top-Selling Products &amp; Current Stock Summary</div>
        <table class="cr-table">
            <thead>
                <tr>
                    <th class="center">Product ID</th>
                    <th>Product Name</th>
                    <th class="num">Unit Price (৳)</th>
                    <th class="center">Units Sold</th>
                    <th class="center">Remaining Stock</th>
                    <th class="num">Product Revenue (৳)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($topProducts): ?>
                    <?php
                        $sumUnits = 0;
                        $sumProdRev = 0;
                    ?>
                    <?php foreach ($topProducts as $tp): ?>
                        <?php
                            $sumUnits += (int)$tp['units_sold'];
                            $sumProdRev += (float)$tp['product_revenue'];
                        ?>
                        <tr>
                            <td class="center">#<?= (int)$tp['product_id'] ?></td>
                            <td><?= e($tp['product_name']) ?></td>
                            <td class="num"><?= number_format((float)$tp['unit_price'], 2) ?></td>
                            <td class="center"><?= (int)$tp['units_sold'] ?></td>
                            <td class="center"><?= (int)$tp['current_stock'] ?></td>
                            <td class="num"><?= number_format((float)$tp['product_revenue'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="center">No active product sales found for the selected criteria.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($topProducts)): ?>
            <tfoot>
                <tr>
                    <td colspan="3" class="num">Top Products Subtotal:</td>
                    <td class="center"><?= $sumUnits ?></td>
                    <td class="center">—</td>
                    <td class="num">৳ <?= number_format($sumProdRev, 2) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>

        <!-- Section C: Detailed Itemized Order Ledger -->
        <div class="cr-section-title">Section C: Detailed Itemized Order Ledger</div>
        <table class="cr-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Order Date</th>
                    <th>Customer Name &amp; Phone</th>
                    <th>City</th>
                    <th>Payment &amp; TrxID</th>
                    <th class="center">Status</th>
                    <th class="num">Subtotal (৳)</th>
                    <th class="num">Delivery (৳)</th>
                    <th class="num">Grand Total (৳)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    $ledgerSub = 0;
                    $ledgerDel = 0;
                    $ledgerTot = 0;
                ?>
                <?php if ($orders): ?>
                    <?php foreach ($orders as $ord): ?>
                        <?php
                            $ledgerSub += (float)$ord['subtotal'];
                            $ledgerDel += (float)$ord['delivery_charge'];
                            $ledgerTot += (float)$ord['total_amount'];
                        ?>
                        <tr>
                            <td><strong>#ORD-<?= str_pad((string)$ord['id'], 3, '0', STR_PAD_LEFT) ?></strong></td>
                            <td><?= e(date('d M Y', strtotime($ord['created_at']))) ?></td>
                            <td>
                                <?= e($ord['customer_name']) ?><br>
                                <small><?= e($ord['customer_phone'] ?? $ord['phone'] ?? $ord['user_phone'] ?? '') ?></small>
                            </td>
                            <td><?= e($ord['city']) ?></td>
                            <td>
                                <?= e(formatPaymentLabel($ord['payment_method'])) ?>
                                <?php if (!empty($ord['transaction_id'])): ?>
                                    <br><small>Trx: <?= e($ord['transaction_id']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="center"><span class="status-pill"><?= e($ord['order_status']) ?></span></td>
                            <td class="num"><?= number_format((float)$ord['subtotal'], 2) ?></td>
                            <td class="num"><?= number_format((float)$ord['delivery_charge'], 2) ?></td>
                            <td class="num"><strong><?= number_format((float)$ord['total_amount'], 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="center">No orders matched the selected filter parameters.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" class="num">REPORT GRAND TOTAL (<?= count($orders) ?> Orders):</td>
                    <td class="num">৳ <?= number_format($ledgerSub, 2) ?></td>
                    <td class="num">৳ <?= number_format($ledgerDel, 2) ?></td>
                    <td class="num">৳ <?= number_format($ledgerTot, 2) ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- Crystal Report Footer & Signature Blocks -->
        <div class="cr-footer">
            <div>
                <strong>Note:</strong> Computer-generated Crystal Report — Handicraft by Sonia Admin Management System.<br>
                All monetary figures are stated in Bangladeshi Taka (BDT / ৳).
            </div>
            <div class="sig-group">
                <div class="sig-box">
                    <div class="sig-line">Prepared By (System Admin)</div>
                </div>
                <div class="sig-box">
                    <div class="sig-line">Approved By (Business Owner)</div>
                </div>
            </div>
        </div>

    </div>
</div>

</body>
</html>