<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? APP_NAME) ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/css/style.css">
</head>
<body>
    <?php if (isset($current_user)): ?>
    <header class="top-bar">
        <div class="logo">📧 <?= APP_NAME ?></div>
        <div class="user-info">
            <span class="email"><?= htmlspecialchars($current_user) ?></span>
            <a href="<?= APP_URL ?>/index.php?page=logout" class="btn btn-small">Wyloguj</a>
        </div>
    </header>
    <?php endif; ?>

    <?php if (!empty($flash['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($flash['success']) ?></div>
    <?php endif; ?>
    <?php if (!empty($flash['error'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($flash['error']) ?></div>
    <?php endif; ?>

    <main class="container">
        <?php
        $viewFile = __DIR__ . '/' . ($content_view ?? '') . '.php';
        if (file_exists($viewFile)) require $viewFile;
        ?>
    </main>
</body>
</html>
