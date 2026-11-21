
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
        r.id,
        r.product_id,
        r.user_id,
        r.rating,
        r.created_at,
        u.name AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone,
        p.name AS product_name,
        p.price AS product_price,
        p.image AS product_image
    FROM ratings r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN products p ON r.product_id = p.id
    WHERE r.id = :id
    LIMIT 1
");

$stmt->execute([':id' => $id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header("Location: index.php");
    exit;
}

$success = $_SESSION['review_success'] ?? '';
$error = $_SESSION['review_error'] ?? '';

unset($_SESSION['review_success'], $_SESSION['review_error']);

$rating = max(0, min(5, (int)$item['rating']));
$stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rating Details | Admin Panel</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f5f3fa;
    color: #30243a;
}

.main {
    margin-left: 0 !important;
    width: 100%;
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

.heading h1 { margin: 0; font-size: 27px; }

.card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    max-width: 900px;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
}

.grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
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

.stars {
    color: #f59e0b;
    font-size: 30px;
    letter-spacing: 2px;
}

.section {
    border-top: 1px solid #eee;
    padding-top: 20px;
    margin-top: 22px;
}

.btn {
    display: inline-block;
    padding: 11px 16px;
    border: none;
    border-radius: 7px;
    background: #5b21b6;
    color: white;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
}

.btn:hover { background: #4c1d95; }

.btn-light {
    background: #eee8f8;
    color: #5b21b6;
}

.btn-danger { background: #dc2626; }
.btn-danger:hover { background: #b91c1c; }

.alert {
    padding: 12px 15px;
    border-radius: 7px;
    margin-bottom: 16px;
    font-size: 14px;
}

.success { background: #e4f7eb; color: #18743c; }
.error { background: #fde8e8; color: #a32121; }

.delete-form { margin-top: 22px; }

@media (max-width: 700px) {
    .main { padding: 18px; }
    .card { padding: 17px; }
    .grid { grid-template-columns: 1fr; gap: 16px; }
}
</style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main">

    <div class="heading">
        <h1>Rating Details #<?= (int)$item['id'] ?></h1>
        <a href="index.php" class="btn btn-light">← Back to Ratings</a>
    </div>

    <div class="card">

        <?php if ($success): ?>
            <div class="alert success"><?= e($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="grid">

            <div class="info">
                <label>Rating ID</label>
                <strong>#<?= (int)$item['id'] ?></strong>
            </div>

            <div class="info">
                <label>Rating Date</label>
                <strong><?= e(date('d M Y, h:i A', strtotime($item['created_at']))) ?></strong>
            </div>

            <div class="info">
                <label>Customer Name</label>
                <strong><?= e($item['customer_name'] ?? 'Unknown Customer') ?></strong>
            </div>

            <div class="info">
                <label>Customer ID</label>
                <strong>#<?= (int)$item['user_id'] ?></strong>
            </div>

            <div class="info">
                <label>Email Address</label>
                <strong><?= e($item['customer_email'] ?? 'Not available') ?></strong>
            </div>

            <div class="info">
                <label>Phone Number</label>
                <strong><?= e($item['customer_phone'] ?? 'Not available') ?></strong>
            </div>

            <div class="info">
                <label>Product Name</label>
                <strong><?= e($item['product_name'] ?? 'Product not found') ?></strong>
            </div>

            <div class="info">
                <label>Product ID</label>
                <strong>#<?= (int)$item['product_id'] ?></strong>
            </div>

            <div class="info">
                <label>Product Price</label>
                <strong>
                    <?= $item['product_price'] !== null
                        ? '৳' . number_format((float)$item['product_price'], 2)
                        : 'Not available' ?>
                </strong>
            </div>

        </div>

        <div class="section">
            <label>Customer Rating</label>
            <div class="stars"><?= e($stars) ?></div>
            <strong><?= $rating ?>/5 Stars</strong>
        </div>

        <div class="section">
            <p>
                <strong>Note:</strong>
                Your current ratings table stores star ratings only.
                Written review comments are not available in the database.
            </p>
        </div>

        <form
            action="delete.php"
            method="POST"
            class="delete-form"
            onsubmit="return confirm('Are you sure you want to delete this rating?');"
        >
            <input type="hidden" name="rating_id" value="<?= (int)$item['id'] ?>">
            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION['admin_csrf_token'] ?? '') ?>">

            <button type="submit" class="btn btn-danger">
                Delete Rating
            </button>
        </form>

    </div>

</div>

</body>
</html>
