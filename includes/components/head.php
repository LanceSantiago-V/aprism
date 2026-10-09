<?php

require_once __DIR__ . '/../../auth/csrf_helper.php';

if (!isset($pageTitle)) {
    $pageTitle = 'APRISM';
}

$roleStylesheet = $roleStylesheet ?? null;
$pageStylesheet = $pageStylesheet ?? null;
$additionalStylesheets = $additionalStylesheets ?? [];
$stylesheetHref = static function (string $stylesheet): string {
    $relativePath = ltrim($stylesheet, '/');
    $absolutePath = __DIR__ . '/../../' . $relativePath;
    $version = is_file($absolutePath) ? (int) filemtime($absolutePath) : 0;

    return APP_URL . '/' . $relativePath . '?v=' . $version;
};

?>

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="aprism-csrf-token" content="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

    <title><?= htmlspecialchars($pageTitle) ?> - APRISM</title>

    <!-- Fonts -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/fonts/fonts.css">

    <!-- Bootstrap -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/vendor/bootstrap/css/bootstrap.min.css">

    <!-- Shared -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/base.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/motion.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/layout.css?v=<?= (int) filemtime(__DIR__ . '/../../assets/css/layout.css') ?>">

    <!-- Role Stylesheet -->
    <?php if (!empty($roleStylesheet)): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($stylesheetHref((string) $roleStylesheet), ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>

    <!-- Page Stylesheet -->
    <?php if (!empty($pageStylesheet)): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($stylesheetHref((string) $pageStylesheet), ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php foreach ($additionalStylesheets as $stylesheet): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($stylesheetHref((string) $stylesheet), ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app-system.css?v=<?= (int) filemtime(__DIR__ . '/../../assets/css/app-system.css') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($stylesheetHref('assets/css/aprism-consolidation.css'), ENT_QUOTES, 'UTF-8') ?>">

</head>
