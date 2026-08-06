<?php

$activePage = $activePage ?? '';

require_once __DIR__ . '/../navigation/navigation.php';

$sidebarItems = getSidebarItems(
    (int) ($_SESSION['role_id'] ?? 0),
    $_SESSION['responsibilities'] ?? []
);

?>

<!-- Shared Sidebar -->
<aside class="sidebar <?= !empty($_SESSION['sidebar_collapsed']) ? 'collapsed' : '' ?>" id="sidebar">
    <div>

        <div class="sidebar-brand">
            <img src="<?= APP_URL ?>/assets/images/aprism-logo.png" alt="APRISM Logo"
                style="width: 44px; height: auto;">

            <div class="brand-text">
                <h2 class="brand-title">APRISM</h2>
                <p class="brand-subtitle">STI Dasmariñas</p>
            </div>
        </div>

        <nav>
            <ul class="sidebar-menu">
                

                <?php foreach ($sidebarItems as $item): ?>

                    <?php
                    $enabled = $item['enabled'] ?? true;

                    $classes = 'sidebar-link';

                    if ($activePage === $item['id']) {
                        $classes .= ' active';
                    }

                    if (!$enabled) {
                        $classes .= ' disabled';
                    }
                    ?>

                    <li>

                        <?php if ($enabled): ?>

                            <a href="<?= htmlspecialchars($item['url']) ?>" class="<?= $classes ?>">

                                <i data-lucide="<?= htmlspecialchars($item['icon']) ?>" class="w-5 h-5">
                                </i>

                                <span>
                                    <?= htmlspecialchars($item['label']) ?>
                                </span>

                            </a>

                        <?php else: ?>

                            <a href="#" class="<?= $classes ?>" tabindex="-1" aria-disabled="true" title="Coming Soon">

                                <i data-lucide="<?= htmlspecialchars($item['icon']) ?>" class="w-5 h-5">
                                </i>

                                <span>
                                    <?= htmlspecialchars($item['label']) ?>
                                </span>

                            </a>

                        <?php endif; ?>

                    </li>

                <?php endforeach; ?>

            </ul>
        </nav>

    </div>

    <div class="sidebar-footer">

        <a href="#" class="logout-link" data-bs-toggle="modal" data-bs-target="#logoutModal">

            <i data-lucide="log-out" class="w-5 h-5 text-danger">
            </i>

            <span>
                Logout
            </span>

        </a>

    </div>

</aside>