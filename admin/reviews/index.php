<?php
require_once "../config/auth.php";
require_once "../../config/database.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$search = trim($_GET['search'] ?? '');
$rating = trim($_GET['rating'] ?? '');

if ($rating !== '' && !in_array($rating, ['1', '2', '3', '4', '5'], true)) {
    $rating = '';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        CAST(r.id AS CHAR) LIKE :id_search
        OR u.name LIKE :name_search
        OR u.email LIKE :email_search
        OR p.name LIKE :product_search
    )";

    $term = "%{$search}%";
    $params[':id_search'] = $term;
    $params[':name_search'] = $term;
    $params[':email_search'] = $term;
    $params[':product_search'] = $term;
}

if ($rating !== '') {
    $where[] = "r.rating = :rating";
    $params[':rating'] = (int)$rating;
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

$countSql = "
    SELECT COUNT(*)
    FROM ratings r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN products p ON r.product_id = p.id
    $whereSql
";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);

$totalRatings = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRatings / $limit));

$sql = "
    SELECT
        r.id,
        r.product_id,
        r.user_id,
        r.rating,
        r.created_at,
        u.name AS customer_name,
        u.email AS customer_email,
        p.name AS product_name
    FROM ratings r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN products p ON r.product_id = p.id
    $whereSql
    ORDER BY r.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $type = ($key === ':rating') ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($key, $value, $type);
}

$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statsStmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(rating = 5) AS five_star,
        SUM(rating = 4) AS four_star,
        SUM(rating <= 3) AS low_rating,
        AVG(rating) AS average_rating
    FROM ratings
");

$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

function stars($number)
{
    $number = max(0, min(5, (int)$number));
    return str_repeat('★', $number) . str_repeat('☆', 5 - $number);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews & Ratings | Admin Panel</title>

    <style>
        * {
            box-sizing: border-box;
        }

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

        .heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }

        .heading h1 {
            margin: 0 0 7px;
            font-size: 27px;
        }

        .heading p {
            margin: 0;
            color: #777;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            border: none;
            border-radius: 7px;
            background: #5b21b6;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 13px;
        }

        .btn:hover {
            background: #4c1d95;
        }

        .btn-light {
            background: #eee8f8;
            color: #5b21b6;
        }

        .btn-light:hover {
            background: #e3d8f5;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 22px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .04);
        }

        .stat-card span {
            display: block;
            font-size: 13px;
            color: #777;
            margin-bottom: 8px;
        }

        .stat-card strong {
            font-size: 25px;
            color: #5b21b6;
        }

        .panel {
            background: white;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .04);
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

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 800px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }

        th {
            background: #faf9fc;
            color: #777;
            font-weight: 600;
        }

        .stars {
            color: #f59e0b;
            font-size: 17px;
            letter-spacing: 1px;
            white-space: nowrap;
        }

        .rating-number {
            color: #555;
            font-size: 12px;
            margin-left: 5px;
        }

        .empty {
            text-align: center;
            padding: 35px;
            color: #888;
        }

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
            color: white;
        }

        @media (max-width: 800px) {
            .main {
                padding: 20px;
            }

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 500px) {
            .main {
                padding: 14px;
            }

            .panel {
                padding: 14px;
            }

            .stats {
                gap: 10px;
            }

            .stat-card {
                padding: 14px;
            }
        }
    </style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main">

        <div class="heading">
            <div>
                <h1>Reviews & Ratings</h1>
                <p>View and manage customer product ratings.</p>
            </div>

            <a href="../dashboard.php" class="btn btn-light">← Dashboard</a>
        </div>

        <div class="stats">

            <div class="stat-card">
                <span>Total Ratings</span>
                <strong><?= (int)($stats['total'] ?? 0) ?></strong>
            </div>

            <div class="stat-card">
                <span>Average Rating</span>
                <strong>
                    <?= $stats['average_rating'] !== null
                        ? number_format((float)$stats['average_rating'], 1) . '/5'
                        : '0.0/5' ?>
                </strong>
            </div>

            <div class="stat-card">
                <span>5 Star Ratings</span>
                <strong><?= (int)($stats['five_star'] ?? 0) ?></strong>
            </div>

            <div class="stat-card">
                <span>Ratings 3 Stars or Below</span>
                <strong><?= (int)($stats['low_rating'] ?? 0) ?></strong>
            </div>

        </div>

        <div class="panel">

            <?php if (!empty($_SESSION['review_success'])): ?>
                <div style="background:#e4f7eb;color:#18743c;padding:12px;border-radius:7px;margin-bottom:15px;">
                    <?= e($_SESSION['review_success']) ?>
                </div>
                <?php unset($_SESSION['review_success']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['review_error'])): ?>
                <div style="background:#fde8e8;color:#a32121;padding:12px;border-radius:7px;margin-bottom:15px;">
                    <?= e($_SESSION['review_error']) ?>
                </div>
                <?php unset($_SESSION['review_error']); ?>
            <?php endif; ?>

            <form method="GET" class="filters">

                <input
                    type="text"
                    name="search"
                    placeholder="Search customer, email, product or rating ID..."
                    value="<?= e($search) ?>">

                <select name="rating">
                    <option value="">All Ratings</option>

                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?= $i ?>"
                            <?= $rating === (string)$i ? 'selected' : '' ?>>
                            <?= $i ?> Star
                        </option>
                    <?php endfor; ?>

                </select>

                <button type="submit" class="btn">Search</button>
                <a href="index.php" class="btn btn-light">Reset</a>

            </form>

            <div class="table-wrap">

                <table>
                    <thead>
                        <tr>
                            <th>Rating ID</th>
                            <th>Customer</th>
                            <th>Product</th>
                            <th>Rating</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if ($ratings): ?>

                            <?php foreach ($ratings as $item): ?>

                                <tr>
                                    <td>#<?= (int)$item['id'] ?></td>

                                    <td>
                                        <strong><?= e($item['customer_name'] ?? 'Unknown Customer') ?></strong><br>
                                        <small><?= e($item['customer_email'] ?? '') ?></small>
                                    </td>

                                    <td><?= e($item['product_name'] ?? 'Product not found') ?></td>

                                    <td>
                                        <span class="stars"><?= stars($item['rating']) ?></span>
                                        <span class="rating-number"><?= (int)$item['rating'] ?>/5</span>
                                    </td>

                                    <td><?= e(date('d M Y', strtotime($item['created_at']))) ?></td>

                                    <td>
                                        <a href="view.php?id=<?= (int)$item['id'] ?>" class="btn">
                                            View
                                        </a>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="6" class="empty">
                                    No ratings found.
                                </td>
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
                            'rating' => $rating,
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