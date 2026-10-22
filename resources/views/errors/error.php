<?php
/** @var int $status @var string $message */
$titles = [403 => 'Forbidden', 404 => 'Page not found', 405 => 'Method not allowed', 419 => 'Page expired', 429 => 'Too many requests', 500 => 'Server error'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($status) ?> · <?= e($titles[$status] ?? 'Error') ?></title>
    <link rel="stylesheet" href="/error.css">
</head>
<body>
    <main>
        <p class="code"><?= e($status) ?></p>
        <h1><?= e($titles[$status] ?? 'Error') ?></h1>
        <p><?= e($message) ?></p>
        <a href="/">Back to home</a>
    </main>
</body>
</html>
