
<?php
require_once "../config/auth.php";
require_once "../../config/database.php";

// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

// Validate Product ID
$productId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$productId || $productId < 1) {
    header("Location: index.php?error=invalid");
    exit;
}

// Validate CSRF Token
$token = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $token)
) {
    header("Location: index.php?error=security");
    exit;
}

try {

    // Find Product
    $stmt = $pdo->prepare("
        SELECT id, image
        FROM products
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        header("Location: index.php?error=notfound");
        exit;
    }

    // Delete Product from Database
    $delete = $pdo->prepare("
        DELETE FROM products
        WHERE id = ?
    ");

    $delete->execute([$productId]);

    // Delete Image only if it belongs to this product
    if (!empty($product['image'])) {

        $imageName = basename($product['image']);

        $imagePath = realpath(
            "../../assets/images/" . $imageName
        );

        $allowedDirectory = realpath("../../assets/images");

        if (
            $imagePath !== false &&
            $allowedDirectory !== false &&
            strpos(
                $imagePath,
                $allowedDirectory . DIRECTORY_SEPARATOR
            ) === 0 &&
            is_file($imagePath)
        ) {
            unlink($imagePath);
        }
    }

    header("Location: index.php?success=deleted");
    exit;

} catch (PDOException $e) {

    header("Location: index.php?error=deletefailed");
    exit;
}
