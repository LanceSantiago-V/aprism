<?php

if (!isset($pageTitle)) {
    $pageTitle = 'APRISM';
}

$roleStylesheet = $roleStylesheet ?? null;
$pageStylesheet = $pageStylesheet ?? null;

?>

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle) ?> - APRISM</title>

    <!-- Fonts -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/fonts/fonts.css">

    <!-- Bootstrap -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/vendor/bootstrap/css/bootstrap.min.css">

    <!-- Shared -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/base.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/motion.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/layout.css">

    <!-- Role Stylesheet -->
    <?php if (!empty($roleStylesheet)): ?>
        <link rel="stylesheet" href="<?= APP_URL . '/' . ltrim($roleStylesheet, '/') ?>">
    <?php endif; ?>

    <!-- Page Stylesheet -->
    <?php if (!empty($pageStylesheet)): ?>
        <link rel="stylesheet" href="<?= APP_URL . '/' . ltrim($pageStylesheet, '/') ?>">
    <?php endif; ?>

</head>