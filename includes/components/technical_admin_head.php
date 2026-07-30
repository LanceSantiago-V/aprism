<?php

if (!isset($pageTitle)) {
    $pageTitle = 'APRISM';
}

if (!isset($pageCss)) {
    $pageCss = '';
}

?>

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle) ?> - APRISM</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700;800&display=swap"
        rel="stylesheet">

    <!-- Bootstrap -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/vendor/bootstrap/css/bootstrap.min.css">

    <!-- Shared CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/base.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/technical-admin.css">

    <?php if ($pageCss): ?>

        <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/pages/<?= htmlspecialchars($pageCss) ?>">

    <?php endif; ?>

    <script>

        if (localStorage.getItem('technicalAdminSidebarCollapsed') === 'true') {

            document.documentElement.classList.add('sidebar-collapsed');

        }

    </script>

</head>