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

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        co.id,
        co.user_id,
        co.description,
        co.required_date,
        co.file_path,
        co.status,
        co.created_at,
        u.name AS customer_name,
        u.username AS customer_username,
        u.email AS customer_email,
        u.phone AS customer_phone
    FROM custom_orders co
    LEFT JOIN users u ON co.user_id = u.id
    WHERE co.id = :id
    LIMIT 1
");

$stmt->execute([':id' => $id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: index.php");
    exit;
}

$success = $_SESSION['custom_order_success'] ?? '';
$error = $_SESSION['custom_order_error'] ?? '';

unset($_SESSION['custom_order_success'], $_SESSION['custom_order_error']);

$allowedStatuses = [
    'Pending', 'Reviewing', 'Approved',
    'In Progress', 'Completed', 'Rejected', 'Cancelled'
];

$filePath = trim((string)($order['file_path'] ?? ''));
$validFilePath = (
    $filePath !== '' &&
    !str_contains($filePath, '..') &&
    !preg_match('/^(?:[a-z]+:|\/|\\\\)/i', $filePath)
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Custom Order Details | Admin Panel</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: 'DM Sans', Arial, sans-serif;
    background: #fbf8ff;
    color: #30243a;
}

.main { padding: 25px 28px 35px; }

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
    font-size: 24px;
    color: #482078;
}

.card {
    background: #fff;
    border-radius: 13px;
    border: 1px solid #eee4f7;
    padding: 25px;
    max-width: 950px;
    box-shadow: 0 5px 20px rgba(67, 28, 111, .07);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 22px;
    margin-bottom: 25px;
}

.info label,
.section-label {
    display: block;
    color: #888;
    font-size: 12px;
    margin-bottom: 8px;
}

.info strong {
    font-size: 14px;
    overflow-wrap: anywhere;
}

.section {
    border-top: 1px solid #eee;
    padding-top: 20px;
    margin-top: 20px;
}

.description {
    background: #faf9fc;
    border: 1px solid #eee;
    padding: 18px;
    border-radius: 8px;
    line-height: 1.8;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    min-height: 100px;
}

.file-link {
    color: #5b21b6;
    font-weight: bold;
    text-decoration: none;
    display: inline-block;
    margin-bottom: 12px;
}

.file-link:hover { text-decoration: underline; }

.ref-preview-img {
    display: block;
    max-width: 320px;
    max-height: 320px;
    border-radius: 10px;
    border: 1px solid #e2d8f3;
    object-fit: cover;
    box-shadow: 0 4px 14px rgba(0,0,0,.06);
}

.status-form {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.status-form select {
    padding: 11px;
    border: 1px solid #ddd;
    border-radius: 7px;
}

.btn {
    display: inline-block;
    padding: 11px 16px;
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

.alert {
    padding: 12px 15px;
    border-radius: 7px;
    margin-bottom: 16px;
    font-size: 14px;
}

.success { background: #e4f7eb; color: #18743c; }
.error { background: #fde8e8; color: #a32121; }

@media (max-width: 700px) {
    .main { padding: 18px; }
    .info-grid { grid-template-columns: 1fr; gap: 16px; }
    .card { padding: 17px; }
}
</style>
</head>

<body>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main">

    <div class="heading">
        <h1>Custom Order Details #<?= (int)$order['id'] ?></h1>
        <a href="index.php" class="btn btn-light">← Back to Custom Orders</a>
    </div>

    <div class="card">

        <?php if ($success): ?>
            <div class="alert success"><?= e($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="info-grid">

            <div class="info">
                <label>Customer Name</label>
                <strong><?= e($order['customer_name'] ?? 'Unknown Customer') ?></strong>
            </div>

            <div class="info">
                <label>Customer ID</label>
                <strong>#<?= (int)$order['user_id'] ?></strong>
            </div>

            <div class="info">
                <label>Username</label>
                <strong><?= e($order['customer_username'] ?? 'Not available') ?></strong>
            </div>

            <div class="info">
                <label>Email Address</label>
                <strong><?= e($order['customer_email'] ?? 'Not available') ?></strong>
            </div>

            <div class="info">
                <label>Phone Number</label>
                <strong><?= e($order['customer_phone'] ?? 'Not available') ?></strong>
            </div>

            <div class="info">
                <label>Required Date</label>
                <strong>
                    <?= $order['required_date']
                        ? e(date('d M Y', strtotime($order['required_date'])))
                        : 'Not specified' ?>
                </strong>
            </div>

            <div class="info">
                <label>Submitted Date</label>
                <strong><?= e(date('d M Y, h:i A', strtotime($order['created_at']))) ?></strong>
            </div>

            <div class="info">
                <label>Current Status</label>
                <strong><?= e($order['status']) ?></strong>
            </div>

        </div>

        <div class="section">
            <label class="section-label">Customer Customization Requirements</label>
            <div class="description"><?= e($order['description']) ?></div>
        </div>

        <div class="section">
            <label class="section-label">Reference Image / File</label>

            <?php if ($validFilePath): ?>
                <a
                    class="file-link"
                    href="../../<?= e($filePath) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    View Full Size Uploaded Image ↗
                </a>
                <img
                    src="../../<?= e($filePath) ?>"
                    alt="Customer Reference Image"
                    class="ref-preview-img"
                    onerror="this.style.display='none'"
                >
            <?php else: ?>
                <span>No reference file uploaded.</span>
            <?php endif; ?>
        </div>

        <div class="section">

            <label class="section-label">Update Custom Order Status</label>

            <form action="update-status.php" method="POST" class="status-form">

                <input type="hidden" name="csrf_token"
                       value="<?= e($_SESSION['admin_csrf_token']) ?>">

                <input type="hidden" name="order_id"
                       value="<?= (int)$order['id'] ?>">

                <select name="status" required>
                    <?php foreach ($allowedStatuses as $item): ?>
                        <option value="<?= e($item) ?>"
                            <?= $order['status'] === $item ? 'selected' : '' ?>>
                            <?= e($item) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn">
                    Update Status
                </button>

            </form>

        </div>

    </div>

</div>

</body>
</html>