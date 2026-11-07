<?php
require_once __DIR__ . '/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        <?= h($pageTitle ?? APP_NAME) ?>
        - <?= h(APP_NAME) ?>
    </title>

    <link rel="stylesheet"
          href="<?= h(url('assets/css/style.css')) ?>">

    <link rel="stylesheet"
          href="<?= h(url('assets/css/responsive.css')) ?>">
</head>

<body>
<?php require __DIR__ . '/navbar.php'; ?>

<main class="container">
    <?php show_notice(); ?>