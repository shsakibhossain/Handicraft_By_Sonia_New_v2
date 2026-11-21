
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

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$allowedStatuses = ['unread', 'read', 'replied'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(name LIKE :search OR email LIKE :search OR subject LIKE :search OR message LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if ($status !== '') {
    $where[] = "status = :status";
    $params[':status'] = $status;
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM contact_messages $whereSql");
$countStmt->execute($params);
$totalMessages = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalMessages / $limit));

$sql = "SELECT id, name, email, subject, message, status, created_at
        FROM contact_messages
        $whereSql
        ORDER BY
            CASE status
                WHEN 'unread' THEN 1
                WHEN 'read' THEN 2
                WHEN 'replied' THEN 3
                ELSE 4
            END,
            created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statsStmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'unread') AS unread,
        SUM(status = 'read') AS read_count,
        SUM(status = 'replied') AS replied
    FROM contact_messages
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

function statusLabel($status) {
    $labels = [
        'unread' => 'Unread',
        'read' => 'Read',
        'replied' => 'Replied'
    ];
    return $labels[$status] ?? ucfirst($status);
}

function statusClass($status) {
    $classes = [
        'unread' => 'unread',
        'read' => 'read',
        'replied' => 'replied'
    ];
    return $classes[$status] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contact Messages | Admin Panel</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f5f3fa;
    color: #30243a;
}

.main {
    margin-left: 0 !important;
    width: 100%;
    max-width: none;
    padding: 30px;
}

.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 24px;
}

.page-heading h1 {
    margin: 0 0 6px;
    font-size: 27px;
}

.page-heading p {
    margin: 0;
    color: #777;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 22px;
}

.stat-card {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
}

.stat-card span {
    display: block;
    color: #777;
    font-size: 13px;
    margin-bottom: 8px;
}

.stat-card strong {
    font-size: 25px;
    color: #5b21b6;
}

.panel {
    background: #fff;
    border-radius: 12px;
    padding: 22px;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
}

.filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.filters input,
.filters select {
    padding: 11px 13px;
    border: 1px solid #ddd;
    border-radius: 7px;
    font-size: 14px;
}

.filters input {
    flex: 1;
    min-width: 190px;
}

.btn {
    display: inline-block;
    padding: 10px 15px;
    border: 0;
    border-radius: 7px;
    background: #5b21b6;
    color: white;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
}

.btn:hover { background: #4c1d95; }

.btn-light {
    background: #eee8f8;
    color: #5b21b6;
}

.btn-light:hover {
    background: #e3d8f5;
}

.table-wrap { overflow-x: auto; }

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 750px;
}

th, td {
    padding: 14px 12px;
    border-bottom: 1px solid #eee;
    text-align: left;
    font-size: 13px;
}

th {
    color: #777;
    background: #faf9fc;
    font-weight: 600;
}

td.subject {
    max-width: 210px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

.badge.unread { background: #fff0d9; color: #a45b00; }
.badge.read { background: #e8efff; color: #3159a5; }
.badge.replied { background: #e4f7eb; color: #18743c; }

.pagination {
    display: flex;
    gap: 7px;
    justify-content: center;
    margin-top: 22px;
    flex-wrap: wrap;
}

.pagination a,
.pagination span {
    padding: 8px 12px;
    border-radius: 6px;
    text-decoration: none;
    background: #f0edf6;
    color: #5b21b6;
    font-size: 13px;
}

.pagination .active {
    background: #5b21b6;
    color: #fff;
}

.empty {
    text-align: center;
    padding: 35px;
    color: #888;
}

@media (max-width: 900px) {
    .main {
        margin-left: 0 !important;
        width: 100%;
        padding: 20px;
    }
}

@media (max-width: 500px) {
    .stats { grid-template-columns: 1fr 1fr; gap: 10px; }
    .stat-card { padding: 14px; }
    .panel { padding: 14px; }
}
</style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<?php
// Dashboard sidebar include থাকলে এখানে সেটি include করতে পারো।
// আপাতত পেজটি নিজস্ব layout-এ চলবে।
?>

<div class="main">

    <div class="page-heading">
        <div>
            <h1>Contact Messages</h1>
            <p>View and manage messages sent by customers.</p>
        </div>
        <a href="../dashboard.php" class="btn btn-light">← Dashboard</a>
    </div>

    <div class="stats">
        <div class="stat-card">
            <span>Total Messages</span>
            <strong><?= (int)($stats['total'] ?? 0) ?></strong>
        </div>
        <div class="stat-card">
            <span>Unread</span>
            <strong><?= (int)($stats['unread'] ?? 0) ?></strong>
        </div>
        <div class="stat-card">
            <span>Read</span>
            <strong><?= (int)($stats['read_count'] ?? 0) ?></strong>
        </div>
        <div class="stat-card">
            <span>Replied</span>
            <strong><?= (int)($stats['replied'] ?? 0) ?></strong>
        </div>
    </div>

    <div class="panel">

        <form method="GET" class="filters">
            <input
                type="text"
                name="search"
                placeholder="Search name, email, subject or message..."
                value="<?= e($search) ?>"
            >

            <select name="status">
                <option value="">All Status</option>
                <option value="unread" <?= $status === 'unread' ? 'selected' : '' ?>>Unread</option>
                <option value="read" <?= $status === 'read' ? 'selected' : '' ?>>Read</option>
                <option value="replied" <?= $status === 'replied' ? 'selected' : '' ?>>Replied</option>
            </select>

            <button type="submit" class="btn">Search</button>
            <a href="index.php" class="btn btn-light">Reset</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Subject</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($messages): ?>
                    <?php foreach ($messages as $msg): ?>
                    <tr>
                        <td>#<?= (int)$msg['id'] ?></td>
                        <td>
                            <strong><?= e($msg['name']) ?></strong><br>
                            <small><?= e($msg['email']) ?></small>
                        </td>
                        <td class="subject" title="<?= e($msg['subject']) ?>">
                            <?= e($msg['subject']) ?>
                        </td>
                        <td><?= e(date('d M Y, h:i A', strtotime($msg['created_at']))) ?></td>
                        <td>
                            <span class="badge <?= e(statusClass($msg['status'])) ?>">
                                <?= e(statusLabel($msg['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <a class="btn" href="view.php?id=<?= (int)$msg['id'] ?>">
                                View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="empty">No messages found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php
                $query = http_build_query([
                    'search' => $search,
                    'status' => $status,
                    'page' => $i
                ]);
                ?>
                <?php if ($i === $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="?<?= e($query) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>
