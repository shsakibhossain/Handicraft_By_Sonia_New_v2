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

$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$product_id = isset($input['product_id']) ? (int)$input['product_id'] : (isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0);

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Invalid product ID.']);
    exit;
}

// সেশন থেকে ইউজার আইডি বের করা (লগইন না থাকলে ডেমোর জন্য প্রথম ভ্যালিড ইউজার নেওয়া)
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
    $rating = isset($input['rating']) ? (int)$input['rating'] : 0;
    if ($rating < 1 || $rating > 5) {
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => 'Rating must be between 1 and 5 stars.'
        ]);
        exit;
    }

    try {
        if ($user_id) {
            $checkStmt = $db->prepare("SELECT id FROM ratings WHERE product_id = :pid AND user_id = :uid LIMIT 1");
            $checkStmt->execute([':pid' => $product_id, ':uid' => $user_id]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $updStmt = $db->prepare("UPDATE ratings SET rating = :rating WHERE id = :id");
                $updStmt->execute([':rating' => $rating, ':id' => $existing['id']]);
            } else {
                $insStmt = $db->prepare("INSERT INTO ratings (product_id, user_id, rating) VALUES (:pid, :uid, :rating)");
                $insStmt->execute([
                    ':pid' => $product_id,
                    ':uid' => $user_id,
                    ':rating' => $rating
                ]);
            }
        }

        // নতুন গড় রেটিং ও মোট সংখ্যা বের করা
        $statStmt = $db->prepare("
            SELECT COALESCE(ROUND(AVG(rating), 1), 0) AS avg_rating, COUNT(*) AS total_ratings
            FROM ratings
            WHERE product_id = :pid
        ");
        $statStmt->execute([':pid' => $product_id]);
        $stats = $statStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'status' => 'success',
            'message' => 'Thank you! You rated this product ' . $rating . ' star(s).',
            'user_rating' => $rating,
            'avg_rating' => (float)($stats['avg_rating'] ?? $rating),
            'total_ratings' => (int)($stats['total_ratings'] ?? 1)
        ]);
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    exit;
}

// GET রিকোয়েস্টের জন্য
try {
    $statStmt = $db->prepare("
        SELECT COALESCE(ROUND(AVG(rating), 1), 0) AS avg_rating, COUNT(*) AS total_ratings
        FROM ratings
        WHERE product_id = :pid
    ");
    $statStmt->execute([':pid' => $product_id]);
    $stats = $statStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'status' => 'success',
        'avg_rating' => (float)($stats['avg_rating'] ?? 0),
        'total_ratings' => (int)($stats['total_ratings'] ?? 0)
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'status' => 'error']);
}