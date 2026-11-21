
<?php
require_once "../config/auth.php";
require_once "../../config/database.php";

function category_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$name = '';
$description = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = "Security verification failed. Please try again.";
    } else {

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            $error = "Category name is required.";
        } elseif (mb_strlen($name) > 100) {
            $error = "Category name cannot exceed 100 characters.";
        } else {

            try {
                $check = $pdo->prepare("
                    SELECT id FROM categories
                    WHERE LOWER(name) = LOWER(?)
                    LIMIT 1
                ");
                $check->execute([$name]);

                if ($check->fetch()) {
                    $error = "This category already exists.";
                } else {

                    $stmt = $pdo->prepare("
                        INSERT INTO categories (name, description)
                        VALUES (?, ?)
                    ");

                    $stmt->execute([
                        $name,
                        $description !== '' ? $description : null
                    ]);

                    header("Location: index.php?success=added");
                    exit;
                }

            } catch (PDOException $e) {
                $error = "Unable to save category. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Category | Handicraft by Sonia</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            background: #f7f5fc;
            color: #30243a;
        }

        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .heading { margin-bottom: 25px; }

        .heading h1 {
            color: #4c1d95;
            font-size: 27px;
            margin-bottom: 8px;
        }

        .heading p { color: #81758e; font-size: 14px; }

        .card {
            background: white;
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 3px 15px rgba(70,35,120,.06);
        }

        .form-group { margin-bottom: 22px; }

        label {
            display: block;
            margin-bottom: 9px;
            font-weight: bold;
            font-size: 14px;
            color: #4c1d95;
        }

        input, textarea {
            width: 100%;
            padding: 13px;
            border: 1px solid #e2d9ef;
            border-radius: 8px;
            outline: none;
            font-family: inherit;
            font-size: 14px;
        }

        input:focus, textarea:focus { border-color: #7c3aed; }

        textarea { min-height: 130px; resize: vertical; }

        .error {
            background: #fff0f0;
            color: #b42318;
            padding: 13px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .save {
            background: #7c3aed;
            color: white;
        }

        .save:hover { background: #5b21b6; }

        .back {
            background: #eee8f7;
            color: #5b21b6;
        }

        @media(max-width: 600px) {
            .container { margin: 22px auto; }
            .card { padding: 20px; }
        }
    </style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="container">

    <div class="heading">
        <h1>Add New Category</h1>
        <p>Create a new product category for your store.</p>
    </div>

    <div class="card">

        <?php if ($error !== ''): ?>
            <div class="error">
                <?= category_escape($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <input type="hidden" name="csrf_token"
                   value="<?= category_escape($_SESSION['csrf_token']) ?>">

            <div class="form-group">
                <label for="name">Category Name *</label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    maxlength="100"
                    placeholder="Enter category name"
                    value="<?= category_escape($name) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="description">Description</label>

                <textarea
                    id="description"
                    name="description"
                    placeholder="Write category description..."
                ><?= category_escape($description) ?></textarea>
            </div>

            <div class="buttons">
                <button type="submit" class="btn save">
                    <i class="bi bi-plus-circle"></i>
                    Save Category
                </button>

                <a href="index.php" class="btn back">
                    <i class="bi bi-arrow-left"></i>
                    Back to Categories
                </a>
            </div>

        </form>
    </div>

</div>

</body>
</html>
