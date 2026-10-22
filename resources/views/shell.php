<?php
/** @var string $title @var string $component @var string $propsJson @var string $viteTags @var string $csrfToken */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title><?= e($title) ?></title>
    <?= $viteTags ?>
</head>
<body>
    <div id="app" data-component="<?= e($component) ?>"></div>
    <script type="application/json" id="mphp-props"><?= $propsJson ?></script>
    <noscript>This page needs JavaScript.</noscript>
</body>
</html>
