<?php

$activePage = $activePage ?? '';

?>

<!-- Technical Administrator Sidebar -->
<aside class="sidebar" id="sidebar">
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

                <li>
                    <a href="<?= APP_URL ?>/dashboard/technical_admin.php"
                        class="sidebar-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                        <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li>
                    <a href="<?= APP_URL ?>/dashboard/technical_admin_users.php"
                        class="sidebar-link <?= $activePage === 'users' ? 'active' : '' ?>">
                        <i data-lucide="users" class="w-5 h-5"></i>
                        <span>Users</span>
                    </a>
                </li>

                <li>
                    <a href="<?= APP_URL ?>/dashboard/technical_admin_audit_logs.php"
                        class="sidebar-link <?= $activePage === 'audit_logs' ? 'active' : '' ?>">
                        <i data-lucide="scroll-text" class="w-5 h-5"></i>
                        <span>Audit Logs</span>
                    </a>
                </li>

                <li>
                    <a href="<?= APP_URL ?>/dashboard/technical_admin_backups.php"
                        class="sidebar-link <?= $activePage === 'backups' ? 'active' : '' ?>">
                        <i data-lucide="database-backup" class="w-5 h-5"></i>
                        <span>Database Backups</span>
                    </a>
                </li>

                <li>
                    <a href="<?= APP_URL ?>/dashboard/technical_admin_settings.php"
                        class="sidebar-link <?= $activePage === 'settings' ? 'active' : '' ?>">
                        <i data-lucide="settings" class="w-5 h-5"></i>
                        <span>Settings</span>
                    </a>
                </li>

            </ul>
        </nav>

    </div>

    <div class="sidebar-footer">
        <a href="#" class="logout-link" data-bs-toggle="modal" data-bs-target="#logoutModal">
            <i data-lucide="log-out" class="w-5 h-5 text-danger"></i>
            <span>Logout</span>
        </a>
    </div>

</aside>