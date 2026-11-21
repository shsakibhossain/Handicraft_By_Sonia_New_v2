<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../config/database.php';

if (!function_exists('admin_edit_escape')) {
    function admin_edit_escape($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$productId || $productId < 1) {
    http_response_code(400);
    exit('Invalid product ID.');
}

$productStmt = $pdo->prepare(
    'SELECT id, category_id, name, description, price, image, stock FROM products WHERE id = :id LIMIT 1'
);
$productStmt->execute(['id' => $productId]);
$product = $productStmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    http_response_code(404);
    exit('Product not found.');
}

$categoryStmt = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC');
$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
$validCategoryIds = array_map('strval', array_column($categories, 'id'));

$errors = [];
$name = (string)$product['name'];
$categoryId = (string)$product['category_id'];
$description = (string)($product['description'] ?? '');
$price = (string)$product['price'];
$stock = (string)($product['stock'] ?? '0');
$currentImage = (string)($product['image'] ?? '');

if (empty($_SESSION['admin_edit_product_csrf'])) {
    $_SESSION['admin_edit_product_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $categoryId = trim($_POST['category_id'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $stock = trim($_POST['stock'] ?? '0');
    $csrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['admin_edit_product_csrf'], $csrf)) {
        $errors[] = 'Your form session expired. Please refresh the page and try again.';
    }
    if ($name === '') {
        $errors[] = 'Please enter the product name.';
    } elseif (mb_strlen($name) > 150) {
        $errors[] = 'Product name cannot exceed 150 characters.';
    }
    if ($categoryId === '' || !ctype_digit($categoryId) || !in_array($categoryId, $validCategoryIds, true)) {
        $errors[] = 'Please select a valid category.';
    }
    if ($price === '' || !is_numeric($price) || (float)$price < 0 || (float)$price > 99999999.99) {
        $errors[] = 'Please enter a valid price (0 to 99,999,999.99).';
    }
    if ($stock === '' || filter_var($stock, FILTER_VALIDATE_INT) === false || (int)$stock < 0) {
        $errors[] = 'Stock must be a whole number (0 or more).';
    }

    $hasUploadedImage = isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE;
    $newImageName = null;
    if ($hasUploadedImage && $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'The image could not be uploaded. Please choose the image again.';
    } elseif ($hasUploadedImage) {
        $image = $_FILES['image'];
        if ($image['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Image size must be 5 MB or less.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($image['tmp_name']);
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];
            if (!isset($allowedTypes[$mimeType])) {
                $errors[] = 'Only JPG, JPEG, PNG and WEBP images are allowed.';
            } else {
                $newImageName = 'product_' . bin2hex(random_bytes(12)) . '.' . $allowedTypes[$mimeType];
            }
        }
    }

    if (!$errors) {
        $uploadDirectory = __DIR__ . '/../../assets/images/';
        $uploadedPath = null;
        if ($hasUploadedImage) {
            if (!is_dir($uploadDirectory) || !is_writable($uploadDirectory)) {
                $errors[] = 'The assets/images folder is missing or is not writable.';
            } else {
                $uploadedPath = $uploadDirectory . $newImageName;
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadedPath)) {
                    $errors[] = 'Could not save the uploaded image. Please try again.';
                }
            }
        }

        if (!$errors) {
            $imageToSave = $hasUploadedImage ? $newImageName : ($currentImage !== '' ? $currentImage : null);
            try {
                $update = $pdo->prepare(
                    'UPDATE products
                     SET category_id = :category_id,
                         name = :name,
                         description = :description,
                         price = :price,
                         image = :image,
                         stock = :stock
                     WHERE id = :id'
                );
                $update->execute([
                    'category_id' => (int)$categoryId,
                    'name' => $name,
                    'description' => $description !== '' ? $description : null,
                    'price' => number_format((float)$price, 2, '.', ''),
                    'image' => $imageToSave,
                    'stock' => (int)$stock,
                    'id' => $productId
                ]);

                // Delete the previous local image only after the database update succeeds.
                if ($hasUploadedImage && $currentImage !== '') {
                    $oldImageName = basename($currentImage);
                    $oldImagePath = $uploadDirectory . $oldImageName;
                    if ($oldImageName !== $newImageName && is_file($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                unset($_SESSION['admin_edit_product_csrf']);
                $_SESSION['admin_product_success'] = 'Product updated successfully.';
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                if ($uploadedPath && is_file($uploadedPath)) {
                    unlink($uploadedPath);
                }
                $errors[] = 'The product could not be updated. Please try again.';
            }
        }
    }
}

$imagePreviewPath = $currentImage !== ''
    ? '../../assets/images/' . rawurlencode(basename($currentImage))
    : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product | Handicraft by Sonia Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --purple: #5b21b6;
            --purple-dark: #421080;
            --purple-light: #f3eaff;
            --page: #f8f5fc;
            --text: #261b3b;
            --muted: #81768f;
            --border: #eee7f7
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            background: var(--page);
            font-family: 'Poppins', sans-serif;
            color: var(--text)
        }

        .layout {
            min-height: 100vh;
            display: flex
        }

        .sidebar {
            width: 250px;
            flex: 0 0 250px;
            background: linear-gradient(180deg, #552095, #351064);
            color: #fff;
            padding: 25px 15px;
            display: flex;
            flex-direction: column
        }

        .brand {
            text-align: center;
            padding: 2px 8px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, .2)
        }

        .brand img {
            width: 94px;
            height: 94px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            background: #fff
        }

        .brand h2 {
            font-size: 17px;
            margin: 10px 0 2px;
            color: #fff
        }

        .brand span {
            font-size: 12px;
            color: #e8d9ff;
            letter-spacing: 1px
        }

        .nav {
            margin-top: 22px;
            display: grid;
            gap: 6px
        }

        .nav a {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 12px 13px;
            border-radius: 9px;
            color: #f7efff;
            text-decoration: none;
            font-size: 13px
        }

        .nav a i {
            font-size: 17px;
            width: 21px;
            text-align: center
        }

        .nav a:hover,
        .nav a.active {
            background: linear-gradient(90deg, #a855e8, #8b3bd1);
            color: #fff
        }

        .nav .logout {
            margin-top: 12px;
            border-top: 1px solid rgba(255, 255, 255, .18);
            border-radius: 0;
            padding-top: 19px
        }

        .sidebar-note {
            margin-top: auto;
            text-align: center;
            color: #e8d9ff;
            font-family: Georgia, serif;
            font-style: italic;
            font-size: 17px;
            line-height: 1.5;
            padding: 28px 8px 8px
        }

        .main {
            min-width: 0;
            flex: 1
        }

        .topbar {
            height: 82px;
            background: #fff;
            box-shadow: 0 2px 15px rgba(67, 30, 111, .06);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            gap: 15px
        }

        .top-left {
            display: flex;
            align-items: center;
            gap: 18px
        }

        .menu-toggle {
            display: none;
            border: 0;
            background: none;
            color: var(--purple);
            font-size: 25px;
            cursor: pointer
        }

        .topbar h1 {
            font-size: 22px;
            color: var(--purple);
            margin: 0;
            font-weight: 600
        }

        .admin-chip {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 13px;
            font-weight: 600
        }

        .admin-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--purple-light);
            display: grid;
            place-items: center;
            color: var(--purple);
            font-size: 18px
        }

        .content {
            padding: 28px 32px 38px;
            max-width: 1250px;
            margin: auto
        }

        .page-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 22px
        }

        .eyebrow {
            color: #9b75c9;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase
        }

        .page-heading h2 {
            margin: 3px 0 4px;
            font-size: 25px;
            color: var(--purple-dark)
        }

        .page-heading p {
            margin: 0;
            color: var(--muted);
            font-size: 13px
        }

         .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 0;
            border-radius: 9px;
            padding: 11px 16px;
            background: var(--purple);
            color: #ffffff;
            text-decoration: none;
            text-decoration-color: white;
            font: 600 13px 'Poppins', sans-serif;
            cursor: pointer;
            box-shadow: 0 5px 12px rgba(113, 62, 194, 0.15)
        }

        .btn:hover {
            background: #a681fb
        }

        .btn-outline {
            background: #caa5f7;
            color: var(--white);
            border: 1px solid #ffffff;
            box-shadow: none
        }

        .form-panel {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: 0 4px 18px rgba(62, 26, 103, .045);
            overflow: hidden
        }

        .panel-head {
            padding: 19px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 11px;
            color: var(--purple-dark);
            font-weight: 700
        }

        .panel-head i {
            font-size: 19px;
            color: #8b3bd1
        }

        .form-body {
            padding: 25px
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px
        }

        .form-group.full {
            grid-column: 1/-1
        }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #49345f
        }

        .required {
            color: #dc4566
        }

        .field {
            width: 100%;
            min-height: 45px;
            border: 1px solid #e6ddef;
            border-radius: 9px;
            padding: 11px 13px;
            background: #fff;
            color: var(--text);
            font: 13px 'Poppins', sans-serif;
            outline: none
        }

        .field:focus {
            border-color: #a879dc;
            box-shadow: 0 0 0 3px #f3eaff
        }

        .field::placeholder {
            color: #b2a7bd
        }

        textarea.field {
            min-height: 120px;
            resize: vertical
        }

        .help {
            font-size: 10px;
            color: var(--muted);
            line-height: 1.5
        }

        .upload-box {
            border: 1.5px dashed #cdb8e5;
            background: #fcfaff;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap
        }

        .preview {
            width: 105px;
            height: 105px;
            border-radius: 10px;
            background: #f1e8fb;
            display: grid;
            place-items: center;
            overflow: hidden;
            color: #a78ac9;
            font-size: 30px;
            flex: 0 0 105px
        }

        .preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block
        }

        .upload-actions {
            flex: 1;
            min-width: 180px
        }

        .upload-actions input {
            max-width: 100%;
            font: 11px 'Poppins', sans-serif
        }

        .upload-actions p {
            font-size: 10px;
            color: var(--muted);
            margin: 7px 0 0
        }

        .alert {
            padding: 13px 16px;
            border-radius: 9px;
            margin: 0 0 18px;
            font-size: 12px
        }

        .alert-error {
            background: #fff0f2;
            color: #a32948;
            border: 1px solid #f7d4dc
        }

        .alert-error ul {
            margin: 5px 0 0;
            padding-left: 19px
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 23px;
            margin-top: 23px;
            border-top: 1px solid var(--border)
        }

        @media(max-width:900px) {
            .sidebar {
                width: 220px;
                flex-basis: 220px
            }

            .content {
                padding: 23px 20px
            }

            .topbar {
                padding: 0 20px
            }
        }

        @media(max-width:700px) {
            .sidebar {
                position: fixed;
                z-index: 20;
                left: -260px;
                top: 0;
                bottom: 0;
                width: 250px;
                transition: left .25s;
                box-shadow: 8px 0 25px rgba(0, 0, 0, .12)
            }

            .sidebar.open {
                left: 0
            }

            .menu-toggle {
                display: block
            }

            .topbar {
                height: 70px;
                padding: 0 15px
            }

            .topbar h1 {
                font-size: 17px
            }

            .content {
                padding: 20px 14px
            }

            .page-heading {
                align-items: flex-start;
                flex-direction: column
            }

            .form-body {
                padding: 18px
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 16px
            }

            .form-group.full {
                grid-column: auto
            }

            .admin-name {
                display: none
            }
        }
    </style>
</head>

<body>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="layout">
        <aside class="sidebar" id="sidebar">
            <div class="brand">
                <img src="../../assets/images/images.jpeg" alt="Handicraft by Sonia Logo" onerror="this.style.display='none'">
                <h2>Handicraft by Sonia</h2><span>— Admin Panel —</span>
            </div>
            <nav class="nav">
                <a href="../dashboard.php"><i class="bi bi-house-door-fill"></i>Dashboard</a>
                <a class="active" href="index.php"><i class="bi bi-box-seam-fill"></i>Products</a>
                <a href="../dashboard.php"><i class="bi bi-bag-check-fill"></i>Orders</a>
                <a href="../dashboard.php"><i class="bi bi-people-fill"></i>Customers</a>
                <a href="../dashboard.php"><i class="bi bi-chat-dots-fill"></i>Messages</a>
                <a href="../dashboard.php"><i class="bi bi-palette-fill"></i>Custom Orders</a>
                <a href="../dashboard.php"><i class="bi bi-grid-fill"></i>Categories</a>
                <a href="../dashboard.php"><i class="bi bi-file-earmark-text-fill"></i>Content Management</a>
                <a class="logout" href="../logout.php"><i class="bi bi-box-arrow-left"></i>Logout</a>
            </nav>
            <div class="sidebar-note">Crafting Happiness<br>Together ♡</div>
        </aside>
        <main class="main">
            <header class="topbar">
                <div class="top-left"><button class="menu-toggle" type="button" id="menuToggle" aria-label="Toggle menu"><i class="bi bi-list"></i></button>
                    <h1>Edit Product</h1>
                </div>
                <div class="admin-chip"><span class="admin-avatar"><i class="bi bi-person-fill"></i></span><span class="admin-name"><?= admin_edit_escape($_SESSION['admin_name'] ?? 'Admin') ?></span></div>
            </header>
            <section class="content">
                <div class="page-heading">
                    <div>
                        <div class="eyebrow">Manage your store</div>
                        <h2>Update Product</h2>
                        <p>Edit product details, category, price, stock or image.</p>
                    </div>
                    <a class="btn btn-outline" href="index.php"><i class="bi bi-arrow-left"></i> Back to Products</a>
                </div>
                <?php if ($errors): ?>
                    <div class="alert alert-error"><strong>Please fix the following:</strong>
                        <ul><?php foreach ($errors as $error): ?><li><?= admin_edit_escape($error) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>
                <div class="form-panel">
                    <div class="panel-head"><i class="bi bi-pencil-square"></i> Product Information — #<?= (int)$productId ?></div>
                    <form method="POST" enctype="multipart/form-data" class="form-body">
                        <input type="hidden" name="csrf_token" value="<?= admin_edit_escape($_SESSION['admin_edit_product_csrf']) ?>">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">Product Name <span class="required">*</span></label>
                                <input class="field" type="text" id="name" name="name" maxlength="150" value="<?= admin_edit_escape($name) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="category_id">Category <span class="required">*</span></label>
                                <select class="field" id="category_id" name="category_id" required>
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= (int)$category['id'] ?>" <?= (string)$categoryId === (string)$category['id'] ? 'selected' : '' ?>><?= admin_edit_escape($category['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="price">Price (৳) <span class="required">*</span></label>
                                <input class="field" type="number" id="price" name="price" min="0" max="99999999.99" step="0.01" value="<?= admin_edit_escape($price) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="stock">Available Stock <span class="required">*</span></label>
                                <input class="field" type="number" id="stock" name="stock" min="0" step="1" value="<?= admin_edit_escape($stock) ?>" required>
                            </div>
                            <div class="form-group full">
                                <label for="description">Product Description</label>
                                <textarea class="field" id="description" name="description" placeholder="Write product details, materials, size, colour, or customization information..."><?= admin_edit_escape($description) ?></textarea>
                            </div>
                            <div class="form-group full">
                                <label for="image">Product Image</label>
                                <div class="upload-box">
                                    <div class="preview"><img id="imagePreview" src="<?= admin_edit_escape($imagePreviewPath) ?>" alt="Current product image" onerror="this.style.display='none';document.getElementById('previewIcon').style.display='block'"><i class="bi bi-image" id="previewIcon" style="display:<?= $imagePreviewPath === '' ? 'block' : 'none' ?>"></i></div>
                                    <div class="upload-actions">
                                        <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                        <p>Current image will remain unchanged unless you choose a new one. Allowed: JPG, JPEG, PNG, WEBP. Maximum size: 5 MB.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-actions">
                            <a class="btn btn-outline" href="index.php">Cancel</a>
                            <button class="btn" type="submit"><i class="bi bi-check2-circle"></i> Update Product</button>
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </div>
    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        if (menuToggle && sidebar) menuToggle.addEventListener('click', () => sidebar.classList.toggle('open'));

        const imageInput = document.getElementById('image');
        const imagePreview = document.getElementById('imagePreview');
        const previewIcon = document.getElementById('previewIcon');
        if (imageInput) {
            imageInput.addEventListener('change', function() {
                const file = this.files && this.files[0];
                if (!file) return;
                if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                    alert('Please select a JPG, PNG or WEBP image.');
                    this.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = event => {
                    imagePreview.src = event.target.result;
                    imagePreview.style.display = 'block';
                    previewIcon.style.display = 'none';
                };
                reader.readAsDataURL(file);
            });
        }
    </script>
</body>

</html>