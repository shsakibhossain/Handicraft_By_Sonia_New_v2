
<?php
require_once "../config/auth.php";
require_once "../../config/database.php";

// Only POST requests are allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

// Validate ID
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header("Location: index.php?error=invalid");
    exit;
}

// Validate CSRF token
$token = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $token)
) {
    header("Location: index.php?error=security");
    exit;
}

try {

    // Check whether category exists
    $stmt = $pdo->prepare("
        SELECT id FROM categories
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);

    if (!$stmt->fetch()) {
        header("Location: index.php?error=notfound");
        exit;
    }

    // Check products linked to this category
    $check = $pdo->prepare("
        SELECT COUNT(*) FROM products
        WHERE category_id = ?
    ");
    $check->execute([$id]);

    $productCount = (int)$check->fetchColumn();

    if ($productCount > 0) {
        header("Location: index.php?error=hasproducts");
        exit;
    }

    // Delete category
    $delete = $pdo->prepare("
        DELETE FROM categories
        WHERE id = ?
    ");
    $delete->execute([$id]);

    header("Location: index.php?success=deleted");
    exit;

} catch (PDOException $e) {
    header("Location: index.php?error=deletefailed");
    exit;
}
