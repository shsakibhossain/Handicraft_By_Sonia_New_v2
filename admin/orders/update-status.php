
<?php
require_once "../config/auth.php";
require_once "../../config/database.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$newStatus = trim($_POST['order_status'] ?? '');
$token = $_POST['csrf_token'] ?? '';

$allowedStatuses = [
    'Pending',
    'Confirmed',
    'Processing',
    'Shipped',
    'Delivered',
    'Cancelled'
];

if (!$id || $id < 1) {
    header("Location: index.php?error=invalid");
    exit;
}

if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $token)
) {
    header("Location: view.php?id=" . $id . "&error=security");
    exit;
}

if (!in_array($newStatus, $allowedStatuses, true)) {
    header("Location: view.php?id=" . $id . "&error=invalid");
    exit;
}

try {
    $check = $pdo->prepare("
        SELECT id FROM orders
        WHERE id = ?
        LIMIT 1
    ");
    $check->execute([$id]);

    if (!$check->fetch()) {
        header("Location: index.php?error=notfound");
        exit;
    }

    $update = $pdo->prepare("
        UPDATE orders
        SET order_status = ?
        WHERE id = ?
    ");

    $update->execute([$newStatus, $id]);

    header("Location: view.php?id=" . $id . "&success=updated");
    exit;

} catch (PDOException $e) {
    header("Location: view.php?id=" . $id . "&error=failed");
    exit;
}
