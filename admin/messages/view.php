
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
    SELECT id, user_id, name, email, subject, message, status, created_at
    FROM contact_messages
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$msg = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$msg) {
    header("Location: index.php");
    exit;
}

$success = $_SESSION['message_success'] ?? '';
$error = $_SESSION['message_error'] ?? '';

unset($_SESSION['message_success'], $_SESSION['message_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Message Details | Admin Panel</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f5f3fa;
    color: #30243a;
}

.main {
    margin-left: 250px;
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
    background: #fff;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
    max-width: 900px;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 22px;
}

.info label {
    display: block;
    font-size: 12px;
    color: #888;
    margin-bottom: 7px;
}

.info strong {
    display: block;
    font-size: 14px;
    overflow-wrap: anywhere;
}

.subject {
    border-top: 1px solid #eee;
    padding-top: 20px;
    margin-top: 10px;
}

.subject h3 { margin: 0 0 10px; }

.message-body {
    background: #faf9fc;
    border: 1px solid #eee;
    padding: 18px;
    border-radius: 8px;
    line-height: 1.8;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    min-height: 120px;
}

.status-form {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
    margin-top: 22px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

select {
    padding: 11px;
    border: 1px solid #ddd;
    border-radius: 7px;
}

.btn {
    display: inline-block;
    padding: 11px 16px;
    border: 0;
    border-radius: 7px;
    background: #5b21b6;
    color: white;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
}

.btn:hover { background: #c7b8df; }

.btn-light {
    background: #eee8f8;
    color: #5b21b6;
}

.alert {
    padding: 12px 15px;
    border-radius: 7px;
    margin-bottom: 16px;
    font-size: 14px;
}

.success { background: #e4f7eb; color: #18743c; }
.error { background: #fde8e8; color: #a32121; }

@media (max-width: 900px) {
    .main { margin-left: 0; padding: 20px; }
}

@media (max-width: 550px) {
    .info-grid { grid-template-columns: 1fr; gap: 15px; }
    .card { padding: 17px; }
}
</style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main">

    <div class="heading">
        <h1>Message Details</h1>
        <a href="index.php" class="btn btn-light">← Back to Messages</a>
    </div>

    <div class="card">

        <?php if ($success): ?>
            <div class="alert success"><?= e($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="info-grid">
            <div class="info">
                <label>Message ID</label>
                <strong>#<?= (int)$msg['id'] ?></strong>
            </div>

            <div class="info">
                <label>Customer Name</label>
                <strong><?= e($msg['name']) ?></strong>
            </div>

            <div class="info">
                <label>Email Address</label>
                <strong><?= e($msg['email']) ?></strong>
            </div>

            <div class="info">
                <label>Submitted At</label>
                <strong><?= e(date('d M Y, h:i A', strtotime($msg['created_at']))) ?></strong>
            </div>

            <div class="info">
                <label>Customer Account ID</label>
                <strong><?= $msg['user_id'] ? '#' . (int)$msg['user_id'] : 'Guest Customer' ?></strong>
            </div>

            <div class="info">
                <label>Current Status</label>
                <strong><?= e(ucfirst($msg['status'])) ?></strong>
            </div>
        </div>

        <div class="subject">
            <label>Subject</label>
            <h3><?= e($msg['subject']) ?></h3>
        </div>

        <div>
            <label>Customer Message</label>
            <div class="message-body"><?= e($msg['message']) ?></div>
        </div>

        <form action="update-status.php" method="POST" class="status-form">
            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION['admin_csrf_token']) ?>">
            <input type="hidden" name="message_id" value="<?= (int)$msg['id'] ?>">

            <label for="status"><strong>Update Status:</strong></label>

            <select name="status" id="status" required>
                <option value="unread" <?= $msg['status'] === 'unread' ? 'selected' : '' ?>>Unread</option>
                <option value="read" <?= $msg['status'] === 'read' ? 'selected' : '' ?>>Read</option>
                <option value="replied" <?= $msg['status'] === 'replied' ? 'selected' : '' ?>>Replied</option>
            </select>

            <button type="submit" class="btn">Update Status</button>
        </form>

    </div>
</div>

</body>
</html>
