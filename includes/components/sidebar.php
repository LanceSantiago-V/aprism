<?php

$activePage = $activePage ?? '';

require_once __DIR__ . '/../navigation/navigation.php';

$sidebarItems = getSidebarItems(
    (int) ($_SESSION['role_id'] ?? 0),
    $_SESSION['responsibilities'] ?? []
);

if (
    (int) ($_SESSION['role_id'] ?? 0) === ROLE_TEACHER
    && aprismTeacherHasCurrentResponsibility($pdo, (int) ($_SESSION['user_id'] ?? 0))
    && ($activeSchoolYear = aprismActiveSchoolYear($pdo)) !== null
    && aprismAdviserAssignments(
        $pdo,
        (int) ($_SESSION['user_id'] ?? 0),
        (int) $activeSchoolYear['school_year_id']
    ) !== []
) {
    foreach ($sidebarItems as &$sidebarItem) {
        if (($sidebarItem['id'] ?? '') === 'adviser' && !empty($sidebarItem['items'])) {
            foreach ($sidebarItem['items'] as &$adviserItem) {
                $adviserItem['enabled'] = true;
            }
            unset($adviserItem);
        }
    }
    unset($sidebarItem);
}

?>

<!-- Shared Sidebar -->
<aside class="sidebar <?= !empty($_SESSION['sidebar_collapsed']) ? 'collapsed' : '' ?>" id="sidebar">

    <!-- Fixed Sidebar Brand -->
    <div class="sidebar-brand">

        <img src="<?= APP_URL ?>/assets/images/aprism-logo.png" alt="APRISM Logo" style="width: 44px; height: auto;">

        <div class="brand-text">

            <h2 class="brand-title">
                APRISM
            </h2>

            <p class="brand-subtitle">
                STI Dasmariñas
            </p>

        </div>

    </div>


    <!-- Scrollable Navigation Region -->
    <div class="sidebar-navigation">

        <nav>

            <ul class="sidebar-menu">

                <?php foreach ($sidebarItems as $item): ?>

                    <?php
                    /*
                     * Responsibility/navigation section.
                     *
                     * Sections are non-clickable labels that contain
                     * additional navigation items such as Adviser
                     * or Program Head responsibilities.
                     */
                    if (($item['type'] ?? 'item') === 'section'):
                        ?>

                        <li class="sidebar-section">

                            <div class="sidebar-section-title">

                                <span>
                                    <?= htmlspecialchars($item['label']) ?>
                                </span>

                            </div>


                            <?php if (!empty($item['items']) && is_array($item['items'])): ?>

                                <ul class="sidebar-section-menu">

                                    <?php foreach ($item['items'] as $sectionItem): ?>

                                        <?php
                                        $enabled = $sectionItem['enabled'] ?? true;

                                        $sidebarLinkClasses = 'sidebar-link';

                                        if ($activePage === ($sectionItem['id'] ?? '')) {
                                            $sidebarLinkClasses .= ' active';
                                        }

                                        if (!$enabled) {
                                            $sidebarLinkClasses .= ' disabled';
                                        }
                                        ?>

                                        <li>

                                            <?php if ($enabled): ?>

                                                <a href="<?= htmlspecialchars($sectionItem['url']) ?>" class="<?= $sidebarLinkClasses ?>"<?= $activePage === ($sectionItem['id'] ?? '') ? ' aria-current="page"' : '' ?>>

                                                    <i data-lucide="<?= htmlspecialchars($sectionItem['icon']) ?>" class="w-5 h-5"></i>

                                                    <span>
                                                        <?= htmlspecialchars($sectionItem['label']) ?>
                                                    </span>

                                                </a>

                                            <?php else: ?>

                                                <a href="#" class="<?= $sidebarLinkClasses ?>" tabindex="-1" aria-disabled="true" title="<?= htmlspecialchars($sectionItem['title'] ?? 'Coming Soon') ?>">

                                                    <i data-lucide="<?= htmlspecialchars($sectionItem['icon']) ?>" class="w-5 h-5"></i>

                                                    <span>
                                                        <?= htmlspecialchars($sectionItem['label']) ?>
                                                    </span>

                                                </a>

                                            <?php endif; ?>

                                        </li>

                                    <?php endforeach; ?>

                                </ul>

                            <?php endif; ?>

                        </li>


                    <?php else: ?>

                        <?php
                        /*
                         * Normal navigation item.
                         *
                         * Existing Teacher, Academic Head, and
                         * Technical Administrator navigation continues
                         * through this branch unchanged.
                         */
                        $enabled = $item['enabled'] ?? true;

                        $sidebarLinkClasses = 'sidebar-link';

                        if ($activePage === ($item['id'] ?? '')) {
                            $sidebarLinkClasses .= ' active';
                        }

                        if (!$enabled) {
                            $sidebarLinkClasses .= ' disabled';
                        }
                        ?>

                        <li>

                            <?php if ($enabled): ?>

                                <a href="<?= htmlspecialchars($item['url']) ?>" class="<?= $sidebarLinkClasses ?>"<?= $activePage === ($item['id'] ?? '') ? ' aria-current="page"' : '' ?>>

                                    <i data-lucide="<?= htmlspecialchars($item['icon']) ?>" class="w-5 h-5"></i>

                                    <span>
                                        <?= htmlspecialchars($item['label']) ?>
                                    </span>

                                </a>

                            <?php else: ?>

                                <a href="#" class="<?= $sidebarLinkClasses ?>" tabindex="-1" aria-disabled="true" title="Coming Soon">

                                    <i data-lucide="<?= htmlspecialchars($item['icon']) ?>" class="w-5 h-5"></i>

                                    <span>
                                        <?= htmlspecialchars($item['label']) ?>
                                    </span>

                                </a>

                            <?php endif; ?>

                        </li>

                    <?php endif; ?>

                <?php endforeach; ?>

            </ul>

        </nav>

    </div>


    <!-- Fixed Sidebar Footer -->
    <div class="sidebar-footer">

        <a href="<?= APP_URL ?>/auth/logout.php" class="logout-link" data-bs-toggle="modal" data-bs-target="#logoutModal">

            <i data-lucide="log-out" class="w-5 h-5 text-danger"></i>

            <span>
                Logout
            </span>

        </a>

    </div>

</aside>
