<?php

$fullName = trim(
    ($_SESSION['first_name'] ?? '') .
    ' ' .
    ($_SESSION['last_name'] ?? '')
);

$initials =
    strtoupper(substr($_SESSION['first_name'] ?? '', 0, 1)) .
    strtoupper(substr($_SESSION['last_name'] ?? '', 0, 1));

$roleName = $_SESSION['role_name'] ?? 'User';

?>

<header class="top-navbar">

    <div class="navbar-left">

        <button
            class="mobile-menu-toggle"
            id="menuToggle"
            type="button">

            <i data-lucide="menu" class="w-5 h-5"></i>

        </button>

        <button
            class="back-btn"
            id="sidebarToggle"
            type="button">

            <i
                data-lucide="chevron-left"
                id="sidebarToggleIcon"
                class="sidebar-toggle-icon">
            </i>

        </button>

    </div>

    <div class="navbar-right">

        <div class="active-term-badge">

            <i data-lucide="calendar" class="w-4 h-4"></i>

            <span>

                Academic Term:
                --

            </span>

        </div>

        <div class="notification-bell">

            <i data-lucide="bell" class="w-5 h-5"></i>

            <span class="notification-dot"></span>

        </div>

        <div class="user-profile">

            <div class="profile-text">

                <h4 class="profile-name">

                    <?= htmlspecialchars($fullName) ?>

                </h4>

                <p class="profile-role">

                    <?= htmlspecialchars($roleName) ?>

                </p>

            </div>

            <div class="profile-avatar">

                <?= htmlspecialchars($initials) ?>

            </div>

        </div>

    </div>

</header>