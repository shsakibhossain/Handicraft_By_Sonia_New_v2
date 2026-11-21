<?php
require_once "../config/auth.php";
require_once "../../config/database.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Escape output safely
function category_escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Search
$search = trim($_GET['search'] ?? '');

try {
    $sql = "
        SELECT
            c.id,
            c.name,
            c.description,
            COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id
    ";

    if ($search !== '') {
        $sql .= "
            WHERE c.name LIKE :search
            OR c.description LIKE :search
        ";
    }

    $sql .= "
        GROUP BY c.id, c.name, c.description
        ORDER BY c.id DESC
    ";

    $stmt = $pdo->prepare($sql);

    if ($search !== '') {
        $stmt->bindValue(':search', '%' . $search . '%');
    }

    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Total category count
    $totalCategories = count($categories);
} catch (PDOException $e) {
    $categories = [];
    $totalCategories = 0;
    $errorMessage = "Unable to load categories. Please check your database.";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Category Management | Handicraft by Sonia</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f7f5fc;
            color: #30243a;
        }

        .main-content {
            padding: 35px;
            max-width: 1400px;
            margin: auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 28px;
        }

        .page-header h1 {
            font-size: 27px;
            color: #4c1d95;
            margin-bottom: 7px;
        }

        .page-header p {
            color: #81758e;
            font-size: 14px;
        }

        .add-btn {
            background: #7a54bc;
            color: white;
            text-decoration: none;
            padding: 12px 19px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
        }

        .add-btn:hover {
            background: #bd9cf2;
            color: white;
        }

        .summary-card {
            background: white;
            padding: 22px;
            border-radius: 13px;
            box-shadow: 0 3px 15px rgba(70, 35, 120, 0.06);
            width: 240px;
            margin-bottom: 25px;
        }

        .summary-card .icon {
            width: 43px;
            height: 43px;
            background: #f0e8ff;
            color: #7c3aed;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 21px;
            margin-bottom: 13px;
        }

        .summary-card h2 {
            font-size: 27px;
            margin-bottom: 5px;
        }

        .summary-card p {
            color: #81758e;
            font-size: 13px;
        }

        .content-card {
            background: white;
            border-radius: 13px;
            padding: 24px;
            box-shadow: 0 3px 15px rgba(70, 35, 120, 0.06);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .table-header h2 {
            font-size: 18px;
            color: #4c1d95;
        }

        .search-form {
            display: flex;
            gap: 8px;
        }

        .search-form input {
            width: 230px;
            padding: 10px 13px;
            border: 1px solid #e2d9ef;
            border-radius: 8px;
            outline: none;
        }

        .search-form input:focus {
            border-color: #7c3aed;
        }

        .search-form button {
            border: none;
            background: #7c3aed;
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th {
            background: #f5f0ff;
            color: #5b21b6;
            font-size: 13px;
            text-align: left;
            padding: 15px;
        }

        td {
            padding: 16px 15px;
            border-bottom: 1px solid #f0ebf5;
            font-size: 14px;
            vertical-align: middle;
        }

        tr:hover td {
            background: #fcfaff;
        }

        .category-name {
            font-weight: bold;
            color: #3b2850;
        }

        .count-badge {
            background: #f0e8ff;
            color: #6b21a8;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .empty-state {
            text-align: center;
            padding: 45px 15px;
            color: #81758e;
        }

        .empty-state i {
            font-size: 35px;
            display: block;
            margin-bottom: 12px;
        }

        .error-message {
            background: #fff0f0;
            color: #b42318;
            padding: 13px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .back-link {
            display: inline-block;
            margin-top: 22px;
            color: #6b21a8;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        }

        .back-link:hover {
            color: #4c1d95;
        }

        @media (max-width: 600px) {
            .main-content {
                padding: 18px;
            }

            .page-header h1 {
                font-size: 23px;
            }

            .summary-card {
                width: 100%;
            }

            .content-card {
                padding: 16px;
            }

            .search-form {
                width: 100%;
            }

            .search-form input {
                width: 100%;
                min-width: 0;
            }
        }
    </style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">

        <div class="page-header">
            <div>
                <h1>Category Management</h1>
                <p>Manage all product categories of your store.</p>
            </div>

            <a href="add.php" class="add-btn">
                <i class="bi bi-plus-circle"></i>
                Add New Category
            </a>
        </div>

        <?php if (!empty($errorMessage)): ?>
            <div class="error-message">
                <?= category_escape($errorMessage) ?>
            </div>
        <?php endif; ?>


        <?php
        $success = $_GET['success'] ?? '';
        $error = $_GET['error'] ?? '';

        $successMessages = [
            'added' => 'Category added successfully.',
            'updated' => 'Category updated successfully.',
            'deleted' => 'Category deleted successfully.'
        ];

        $errorMessages = [
            'invalid' => 'Invalid category ID.',
            'notfound' => 'Category not found.',
            'security' => 'Security verification failed.',
            'hasproducts' => 'This category has products. Move those products to another category before deleting it.',
            'deletefailed' => 'Unable to delete category. Please try again.'
        ];
        ?>

        <?php if (isset($successMessages[$success])): ?>
            <div style="background:#eaf8ef;color:#187443;padding:14px;border-radius:8px;margin-bottom:20px;">
                <i class="bi bi-check-circle"></i>
                <?= category_escape($successMessages[$success]) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($errorMessages[$error])): ?>
            <div style="background:#fff0f0;color:#b42318;padding:14px;border-radius:8px;margin-bottom:20px;">
                <i class="bi bi-exclamation-circle"></i>
                <?= category_escape($errorMessages[$error]) ?>
            </div>
        <?php endif; ?>

        <div class="summary-card">
            <div class="icon">
                <i class="bi bi-grid"></i>
            </div>

            <h2><?= (int)$totalCategories ?></h2>
            <p>Total Categories</p>
        </div>

        <div class="content-card">

            <div class="table-header">
                <h2>All Categories</h2>

                <form method="GET" class="search-form">
                    <input
                        type="text"
                        name="search"
                        placeholder="Search category..."
                        value="<?= category_escape($search) ?>">

                    <button type="submit" title="Search">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
            </div>

            <div class="table-wrapper">

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Category Name</th>
                            <th>Description</th>
                            <th>Total Products</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (!empty($categories)): ?>

                            <?php foreach ($categories as $category): ?>

                                <tr>
                                    <td>#<?= (int)$category['id'] ?></td>

                                    <td class="category-name">
                                        <?= category_escape($category['name']) ?>
                                    </td>

                                    <td>
                                        <?= !empty($category['description'])
                                            ? category_escape($category['description'])
                                            : 'No description' ?>
                                    </td>

                                    <td>
                                        <span class="count-badge">
                                            <?= (int)$category['product_count'] ?> Products
                                        </span>
                                    </td>
                                    <td>
                                        <a
                                            href="edit.php?id=<?= (int)$category['id'] ?>"
                                            title="Edit Category"
                                            style="color:#7c3aed; font-size:19px; margin-right:12px;">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>

                                        <form
                                            action="delete.php"
                                            method="POST"
                                            style="display:inline;"
                                            onsubmit="return confirm('Are you sure you want to delete this category?');">
                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$category['id'] ?>">

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= category_escape($_SESSION['csrf_token'] ?? '') ?>">

                                            <button
                                                type="submit"
                                                title="Delete Category"
                                                style="border:none; background:none; color:#dc2626; font-size:19px; cursor:pointer;">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="bi bi-folder-x"></i>

                                        <?php if ($search !== ''): ?>
                                            No matching categories found.
                                        <?php else: ?>
                                            No categories available.
                                        <?php endif; ?>
                                    </div>
                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>
                </table>

            </div>
        </div>

        <a href="../dashboard.php" class="back-link">
            <i class="bi bi-arrow-left"></i>
            Back to Dashboard
        </a>

    </div>

</body>

</html>