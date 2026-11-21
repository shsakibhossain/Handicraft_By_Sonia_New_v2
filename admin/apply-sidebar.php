<?php
/**
 * One-Click Auto Installer for Admin Sidebar across all Admin Module Pages
 * Run once in browser: http://localhost/Handicraft-by-Sonia/admin/apply-sidebar.php
 */
$folders = [
    'products',
    'orders',
    'categories',
    'customers',
    'messages',
    'custom-orders',
    'content',
    'reviews'
];

$skipFiles = ['delete.php', 'update-status.php', 'invoice.php'];
$includeLine = "<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>";

$updatedFiles = [];
$alreadyUpdated = [];

foreach ($folders as $folder) {
    $dirPath = __DIR__ . '/' . $folder;
    if (!is_dir($dirPath)) {
        continue;
    }

    foreach (glob($dirPath . '/*.php') as $filePath) {
        $fileName = basename($filePath);
        if (in_array(strtolower($fileName), $skipFiles, true)) {
            continue;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            continue;
        }

        // Skip if already contains sidebar.php
        if (str_contains($content, 'includes/sidebar.php')) {
            $alreadyUpdated[] = $folder . '/' . $fileName;
            continue;
        }

        // Only inject into files that have a <body> tag
        if (preg_match('/<body[^>]*>/i', $content)) {
            $newContent = preg_replace(
                '/(<body[^>]*>)/i',
                "$1\n" . $includeLine,
                $content,
                1
            );

            if ($newContent !== null && $newContent !== $content) {
                file_put_contents($filePath, $newContent);
                $updatedFiles[] = $folder . '/' . $fileName;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Sidebar Auto-Installer</title>
    <style>
        body { font-family: Arial, sans-serif; background: #fbf8ff; color: #24143f; padding: 40px; }
        .box { max-width: 680px; margin: 0 auto; background: #fff; border: 1px solid #e5d8f5; border-radius: 14px; padding: 28px; box-shadow: 0 6px 20px rgba(84,32,155,.08); }
        h2 { color: #5b21b6; margin-top: 0; }
        ul { line-height: 1.8; }
        .btn { display: inline-block; margin-top: 15px; padding: 11px 20px; background: #5b21b6; color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="box">
        <h2>✓ Admin Sidebar Applied Successfully!</h2>
        <p><strong>Newly Updated Pages (<?= count($updatedFiles) ?>):</strong></p>
        <ul>
            <?php if ($updatedFiles): ?>
                <?php foreach ($updatedFiles as $f): ?>
                    <li style="color: #15803d; font-weight: bold;">Updated: admin/<?= htmlspecialchars($f) ?></li>
                <?php endforeach; ?>
            <?php else: ?>
                <li>No new files needed updating.</li>
            <?php endif; ?>
        </ul>

        <?php if ($alreadyUpdated): ?>
            <p><strong>Already Had Sidebar (<?= count($alreadyUpdated) ?>):</strong></p>
            <ul>
                <?php foreach ($alreadyUpdated as $f): ?>
                    <li style="color: #5b21b6;">Active: admin/<?= htmlspecialchars($f) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <a class="btn" href="dashboard.php">Go to Admin Dashboard →</a>
    </div>
</body>
</html>