
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

$messageId = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);
$status = $_POST['status'] ?? '';
$csrfToken = $_POST['csrf_token'] ?? '';

$allowedStatuses = ['unread', 'read', 'replied'];

if (
    empty($_SESSION['admin_csrf_token']) ||
    !hash_equals($_SESSION['admin_csrf_token'], $csrfToken)
) {
    $_SESSION['message_error'] = "Security verification failed. Please try again.";
    header("Location: index.php");
    exit;
}

if (!$messageId || $messageId < 1 || !in_array($status, $allowedStatuses, true)) {
    $_SESSION['message_error'] = "Invalid message or status.";
    header("Location: index.php");
    exit;
}

try {
    $check = $pdo->prepare("SELECT id FROM contact_messages WHERE id = :id LIMIT 1");
    $check->execute([':id' => $messageId]);

    if (!$check->fetchColumn()) {
        $_SESSION['message_error'] = "Message not found.";
        header("Location: index.php");
        exit;
    }

    $update = $pdo->prepare("
        UPDATE contact_messages
        SET status = :status
        WHERE id = :id
    ");

    $update->execute([
        ':status' => $status,
        ':id' => $messageId
    ]);

    $_SESSION['message_success'] = "Message status updated successfully.";

    header("Location: view.php?id=" . $messageId);
    exit;

} catch (PDOException $e) {
    error_log("Message status update error: " . $e->getMessage());

    $_SESSION['message_error'] = "Unable to update message status.";
    header("Location: view.php?id=" . $messageId);
    exit;
}
