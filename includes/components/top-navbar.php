<?php

if (!isset($currentSchoolYear) && isset($pdo) && $pdo instanceof PDO) {
    require_once __DIR__ . '/../helper/responsibility_helper.php';
    $activeSchoolYear = aprismActiveSchoolYear($pdo);
    $currentSchoolYear = $activeSchoolYear['school_year'] ?? null;
}

require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../helper/notification_helper.php';

$fullName = trim(
    ($_SESSION['first_name'] ?? '') .
    ' ' .
    ($_SESSION['last_name'] ?? '')
);

$initials =
    strtoupper(substr($_SESSION['first_name'] ?? '', 0, 1)) .
    strtoupper(substr($_SESSION['last_name'] ?? '', 0, 1));

$roleName = trim((string) ($_SESSION['role_name'] ?? ''));
if ($roleName === '' && isset($pdo) && $pdo instanceof PDO && isset($_SESSION['role_id'])) {
    $roleStmt = $pdo->prepare('SELECT role_name FROM roles WHERE role_id = ? LIMIT 1');
    $roleStmt->execute([(int) $_SESSION['role_id']]);
    $roleName = trim((string) ($roleStmt->fetchColumn() ?: ''));
}
$roleName = $roleName !== '' ? $roleName : 'Authenticated User';
if ($roleName === 'Teacher' && isset($pdo) && $pdo instanceof PDO) {
    aprismQueueTeacherClassReminders($pdo, (int) ($_SESSION['user_id'] ?? 0), $currentSchoolYear ?? null);
}
$notificationCount = isset($pdo) && $pdo instanceof PDO ? aprismUnreadNotificationCount($pdo, (int) ($_SESSION['user_id'] ?? 0)) : 0;
$recentNotifications = isset($pdo) && $pdo instanceof PDO ? aprismRecentNotifications($pdo, (int) ($_SESSION['user_id'] ?? 0)) : [];
$navbarCsrfToken = generateCsrfToken();

?>

<header class="top-navbar">

    <div class="navbar-left">

        <button class="mobile-menu-toggle" id="menuToggle" type="button" aria-label="Toggle navigation menu" aria-controls="sidebar" aria-expanded="false">

            <i data-lucide="menu" class="w-5 h-5"></i>

        </button>

        <button class="back-btn" id="sidebarToggle" type="button" aria-label="Toggle sidebar" aria-controls="sidebar" aria-expanded="<?=empty($_SESSION['sidebar_collapsed']) ? 'true' : 'false'?>">

            <i data-lucide="chevron-left" id="sidebarToggleIcon" class="sidebar-toggle-icon">
            </i>

        </button>

    </div>


    <div class="navbar-right">

        <div class="active-term-badge" aria-label="Academic Year">

            <i data-lucide="calendar" class="w-4 h-4"></i>

            <span>

                <span class="active-term-label">Academic Year:</span>
                <span class="active-term-value"><?= htmlspecialchars($currentSchoolYear ?? '--') ?></span>

            </span>

        </div>


        <details class="notification-bell" data-notification-menu>
            <summary aria-label="Notifications">
                <i data-lucide="bell" class="w-5 h-5"></i>
                <?php if ($notificationCount > 0): ?><span class="notification-dot" aria-label="<?= $notificationCount ?> unread notifications"><?= $notificationCount > 9 ? '9+' : $notificationCount ?></span><?php endif; ?>
            </summary>
            <div class="notification-menu">
                <div class="notification-menu-heading"><strong>Notifications</strong><button type="button" data-notification-mark-all aria-label="Mark all notifications as read">Mark all read</button></div>
                <div class="notification-menu-list">
                    <?php if ($recentNotifications === []): ?><p class="notification-empty">No current notifications.</p><?php endif; ?>
                    <?php foreach ($recentNotifications as $notification): ?>
                        <?php $notificationTitle = trim((string) $notification['title']); $notificationAriaLabel = ($notification['read_at'] === null ? 'Unread notification: ' : 'Notification: ') . $notificationTitle; ?>
                        <a class="notification-item <?= $notification['read_at'] === null ? 'is-unread' : '' ?>" data-notification-id="<?= (int) $notification['notification_id'] ?>" aria-label="<?= htmlspecialchars($notificationAriaLabel, ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($notification['context_url'] ?: APP_URL . '/dashboard/notifications.php', ENT_QUOTES, 'UTF-8') ?>">
                            <strong><?= htmlspecialchars($notification['title']) ?></strong><span><?= htmlspecialchars($notification['message']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <a class="notification-center-link" href="<?= APP_URL ?>/dashboard/notifications.php">Open Notification Center</a>
            </div>
        </details>


        <a class="user-profile" href="<?= APP_URL ?>/dashboard/profile.php" aria-label="Open My Profile — <?= htmlspecialchars($fullName !== '' ? $fullName : 'Account', ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8') ?>">

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

        </a>

    </div>

</header>
<script>
window.APRISM_NOTIFICATION_CSRF = <?= json_encode($navbarCsrfToken) ?>;
document.addEventListener('DOMContentLoaded', function () {
  const endpoint = <?= json_encode(APP_URL . '/actions/system/notifications.php') ?>;
  function postNotification(data) { return fetch(endpoint, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'}, body:new URLSearchParams(Object.assign({csrf_token:window.APRISM_NOTIFICATION_CSRF}, data))}); }
  document.querySelectorAll('[data-notification-id]').forEach(function (item) { item.addEventListener('click', function (event) { event.preventDefault(); var destination = item.href; postNotification({operation:'mark_read', notification_id:item.dataset.notificationId}).finally(function () { window.location.href = destination; }); }); });
  const markAll = document.querySelector('[data-notification-mark-all]');
  if (markAll) markAll.addEventListener('click', function () { postNotification({operation:'mark_all_read'}).then(function () { window.location.reload(); }); });
});
</script>
