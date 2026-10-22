<?php
/** @var int $status @var string $message @var Throwable $exception */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Error · <?= e($exception::class) ?></title>
    <link rel="stylesheet" href="/error.css">
</head>
<body class="debug">
    <main>
        <p class="code"><?= e($exception::class) ?></p>
        <h1><?= e($exception->getMessage()) ?></h1>
        <p><code><?= e($exception->getFile()) ?>:<?= e($exception->getLine()) ?></code></p>
        <pre><?= e($exception->getTraceAsString()) ?></pre>
        <p class="hint">Debug page: only shown while APP_DEBUG=true.</p>
    </main>
</body>
</html>
