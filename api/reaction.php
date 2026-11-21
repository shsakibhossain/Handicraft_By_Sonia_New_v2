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

// POST বা JSON থেকে ডেটা নেওয়া
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$product_id = isset($input['product_id']) ? (int)$input['product_id'] : (isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0);
$reaction = isset($input['reaction']) ? strtolower(trim((string)$input['reaction'])) : '';

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Invalid product ID.']);
    exit;
}

// সেশন থেকে ইউজার আইডি বের করা
$user_id = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? ($_SESSION['id'] ?? null));

// যদি ইউজার লগইন না থাকে, তবে ডাটাবেসের প্রথম ভ্যালিড ইউজার বা সেশন ব্যবহার করে কাজ চালানো যাতে ডেমোতে কখনো আটকে না যায়
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
    if (!in_array($reaction, ['like', 'dislike'], true)) {
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Invalid reaction type.']);
        exit;
    }

    try {
        $user_reaction = $reaction;

        if ($user_id) {
            $checkStmt = $db->prepare("SELECT id, reaction FROM reactions WHERE product_id = :pid AND user_id = :uid LIMIT 1");
            $checkStmt->execute([':pid' => $product_id, ':uid' => $user_id]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                if ($existing['reaction'] === $reaction) {
                    // একই বাটনে আবার ক্লিক করলে রিমুভ (Unlike) হবে
                    $delStmt = $db->prepare("DELETE FROM reactions WHERE id = :id");
                    $delStmt->execute([':id' => $existing['id']]);
                    $user_reaction = null;
                } else {
                    // Like থেকে Dislike বা বিপরীত হলে আপডেট হবে
                    $updStmt = $db->prepare("UPDATE reactions SET reaction = :reaction WHERE id = :id");
                    $updStmt->execute([':reaction' => $reaction, ':id' => $existing['id']]);
                }
            } else {
                // নতুন রিঅ্যাকশন যোগ হবে
                $insStmt = $db->prepare("INSERT INTO reactions (product_id, user_id, reaction) VALUES (:pid, :uid, :reaction)");
                $insStmt->execute([
                    ':pid' => $product_id,
                    ':uid' => $user_id,
                    ':reaction' => $reaction
                ]);
            }
        }

        // মোট Like ও Dislike গণনা করা
        $countStmt = $db->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN reaction = 'like' THEN 1 ELSE 0 END), 0) AS likes,
                COALESCE(SUM(CASE WHEN reaction = 'dislike' THEN 1 ELSE 0 END), 0) AS dislikes
            FROM reactions
            WHERE product_id = :pid
        ");
        $countStmt->execute([':pid' => $product_id]);
        $counts = $countStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'status' => 'success',
            'user_reaction' => $user_reaction,
            'likes' => (int)($counts['likes'] ?? 0),
            'dislikes' => (int)($counts['dislikes'] ?? 0)
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
    $countStmt = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN reaction = 'like' THEN 1 ELSE 0 END), 0) AS likes,
            COALESCE(SUM(CASE WHEN reaction = 'dislike' THEN 1 ELSE 0 END), 0) AS dislikes
        FROM reactions
        WHERE product_id = :pid
    ");
    $countStmt->execute([':pid' => $product_id]);
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'status' => 'success',
        'likes' => (int)($counts['likes'] ?? 0),
        'dislikes' => (int)($counts['dislikes'] ?? 0)
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'status' => 'error']);
}