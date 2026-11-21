
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

$ratingId = filter_input(INPUT_POST, 'rating_id', FILTER_VALIDATE_INT);
$csrfToken = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['admin_csrf_token']) ||
    !hash_equals($_SESSION['admin_csrf_token'], $csrfToken)
) {
    $_SESSION['review_error'] = "Security verification failed.";
    header("Location: index.php");
    exit;
}

if (!$ratingId || $ratingId < 1) {
    $_SESSION['review_error'] = "Invalid rating ID.";
    header("Location: index.php");
    exit;
}

try {
    $stmt = $pdo->prepare("
        DELETE FROM ratings
        WHERE id = :id
    ");

    $stmt->execute([':id' => $ratingId]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['review_success'] = "Rating deleted successfully.";
    } else {
        $_SESSION['review_error'] = "Rating not found.";
    }

    header("Location: index.php");
    exit;

} catch (PDOException $e) {
    error_log("Rating delete error: " . $e->getMessage());

    $_SESSION['review_error'] = "Unable to delete rating. It may be linked to another record.";
    header("Location: view.php?id=" . $ratingId);
    exit;
}
