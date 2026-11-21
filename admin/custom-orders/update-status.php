
<?php
require_once "../config/auth.php";
require_once "../../config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$status = trim($_POST['status'] ?? '');
$csrfToken = $_POST['csrf_token'] ?? '';

$allowedStatuses = [
    'Pending',
    'Reviewing',
    'Approved',
    'In Progress',
    'Completed',
    'Rejected',
    'Cancelled'
];

if (
    empty($_SESSION['admin_csrf_token']) ||
    !hash_equals($_SESSION['admin_csrf_token'], $csrfToken)
) {
    $_SESSION['custom_order_error'] = "Security verification failed. Please try again.";
    header("Location: index.php");
    exit;
}

if (
    !$orderId ||
    $orderId < 1 ||
    !in_array($status, $allowedStatuses, true)
) {
    $_SESSION['custom_order_error'] = "Invalid order or status.";
    header("Location: index.php");
    exit;
}

try {

    $check = $pdo->prepare("
        SELECT id
        FROM custom_orders
        WHERE id = :id
        LIMIT 1
    ");

    $check->execute([':id' => $orderId]);

    if (!$check->fetchColumn()) {
        $_SESSION['custom_order_error'] = "Custom order not found.";
        header("Location: index.php");
        exit;
    }

    $update = $pdo->prepare("
        UPDATE custom_orders
        SET status = :status
        WHERE id = :id
    ");

    $update->execute([
        ':status' => $status,
        ':id' => $orderId
    ]);

    $_SESSION['custom_order_success'] = "Custom order status updated successfully.";

    header("Location: view.php?id=" . $orderId);
    exit;

} catch (PDOException $e) {

    error_log("Custom order status update error: " . $e->getMessage());

    $_SESSION['custom_order_error'] = "Unable to update custom order status.";

    header("Location: view.php?id=" . $orderId);
    exit;
}
