<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$db = isset($pdo) ? $pdo : (isset($conn) ? $conn : null);
if (!$db) {
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? ($_SESSION['id'] ?? null));
if (!$user_id) {
    try {
        $uStmt = $db->query("SELECT id FROM users ORDER BY id ASC LIMIT 1");
        $firstUser = $uStmt->fetchColumn();
        if ($firstUser) {
            $user_id = (int)$firstUser;
        }
    } catch (Throwable $e) {
        $user_id = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
    if (empty($input)) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }

    $product_id = isset($input['product_id']) ? (int)$input['product_id'] : 0;
    $comment = isset($input['comment']) ? trim((string)$input['comment']) : '';

    if ($product_id <= 0 || $comment === '') {
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => 'Please write a comment before posting.'
        ]);
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO comments (product_id, user_id, comment) VALUES (:pid, :uid, :comment)");
        $stmt->execute([
            ':pid' => $product_id,
            ':uid' => $user_id,
            ':comment' => $comment
        ]);

        $comment_id = (int)$db->lastInsertId();

        $userName = $_SESSION['user_name'] ?? ($_SESSION['username'] ?? '');
        if ($userName === '' && $user_id) {
            $userStmt = $db->prepare("SELECT name FROM users WHERE id = :id LIMIT 1");
            $userStmt->execute([':id' => $user_id]);
            $userName = (string)$userStmt->fetchColumn();
        }
        if ($userName === '') {
            $userName = 'Customer';
        }

        echo json_encode([
            'success' => true,
            'status' => 'success',
            'message' => 'Comment posted!',
            'data' => [
                'id' => $comment_id,
                'product_id' => $product_id,
                'user_name' => htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'),
                'comment' => htmlspecialchars($comment, ENT_QUOTES, 'UTF-8'),
                'created_at' => date('M d, Y h:i A')
            ]
        ]);
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => 'Could not save comment.'
        ]);
    }
    exit;
}