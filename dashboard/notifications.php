<?php
declare(strict_types=1); require_once __DIR__.'/../auth/session_guard.php'; require_once __DIR__.'/../includes/helper/notification_helper.php';
$pageTitle='Notifications';$activePage='';$roleStylesheet='assets/css/teacher.css';$pageStylesheet='assets/css/pages/profile-notifications.css';$page=max(1,(int)($_GET['page']??1));$limit=20;$offset=($page-1)*$limit;$userId=(int)$_SESSION['user_id'];
$count=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE target_user_id=? AND archived_at IS NULL');$count->execute([$userId]);$total=(int)$count->fetchColumn();$stmt=$pdo->prepare("SELECT * FROM notifications WHERE target_user_id=? AND archived_at IS NULL ORDER BY created_at DESC, notification_id DESC LIMIT {$limit} OFFSET {$offset}");$stmt->execute([$userId]);$notifications=$stmt->fetchAll(PDO::FETCH_ASSOC);$pages=max(1,(int)ceil($total/$limit));function aprismNotificationEsc(?string $value):string{return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
?>
<?php
$rangeStart = $notifications !== [] ? $offset + 1 : 0;
$rangeEnd = $notifications !== [] ? min($offset + count($notifications), $total) : 0;
?>
<!doctype html>
<html lang="en">
<?php require __DIR__ . '/../includes/components/head.php'; ?>
<body>
<?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
<main class="main-content">
  <div class="main-wrapper">
    <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
    <section class="profile-page guidance-notification-center guidance-worklist-page">
      <header class="guidance-page-header">
        <div>
          <p class="page-eyebrow">Account</p>
          <h1>Notifications</h1>
          <p class="guidance-notification-meta"><?=$total?> notification<?=$total === 1 ? '' : 's'?></p>
        </div>
        <div class="notification-center-actions">
          <button class="btn btn-secondary" type="button" data-notification-page-mark-all>Mark all read</button>
        </div>
      </header>

      <section class="profile-card notification-center-list" aria-label="Notification history">
        <?php if ($total === 0): ?>
          <div class="guidance-empty-state is-compact"><span>No notifications yet.</span></div>
        <?php endif; ?>

        <?php foreach ($notifications as $notification): ?>
          <?php
          $notificationType = (string) ($notification['notification_type'] ?? '');
          $notificationIcon = 'bell';
          $notificationTone = 'blue';
          if ($notificationType === 'guidance_case_accepted') {
              $notificationIcon = 'users';
              $notificationTone = 'green';
          } elseif (strpos($notificationType, 'teacher_referral_') === 0) {
              $notificationIcon = 'users';
          } elseif (strpos($notificationType, 'class_') === 0 || strpos($notificationType, 'guidance_case_') === 0) {
              $notificationIcon = 'calendar';
          } elseif ($notificationType === 'adviser_assignment') {
              $notificationIcon = 'users';
          }
          $createdAt = (string) $notification['created_at'];
          $createdAtTimestamp = strtotime($createdAt);
          $displayDate = $createdAtTimestamp !== false ? date('M j, Y', $createdAtTimestamp) : $createdAt;
          $displayTime = $createdAtTimestamp !== false ? date('g:i A', $createdAtTimestamp) : '';
          ?>
          <a class="notification-record <?=$notification['read_at'] === null ? 'is-unread' : ''?>"
             data-notification-id="<?=(int) $notification['notification_id']?>"
             href="<?=aprismNotificationEsc($notification['context_url'] ?: '#')?>">
            <span class="notification-record-indicator" aria-hidden="true"></span>
            <span class="notification-record-icon notification-record-icon--<?=$notificationTone?>" aria-hidden="true">
              <i data-lucide="<?=$notificationIcon?>"></i>
            </span>
            <span class="notification-record-copy">
              <strong><?=aprismNotificationEsc($notification['title'])?></strong>
              <span><?=aprismNotificationEsc($notification['message'])?></span>
            </span>
            <span class="notification-record-meta">
              <time datetime="<?=aprismNotificationEsc($createdAt)?>">
                <span><?=aprismNotificationEsc($displayDate)?></span>
                <?php if ($displayTime !== ''): ?><span><?=aprismNotificationEsc($displayTime)?></span><?php endif; ?>
              </time>
              <span class="notification-record-action">Open <i data-lucide="arrow-up-right" aria-hidden="true"></i></span>
            </span>
          </a>
        <?php endforeach; ?>

        <footer class="notification-results-footer">
          <p class="notification-results-count">
            Showing <?=$rangeStart?> to <?=$rangeEnd?> of <?=$total?> notification<?=$total === 1 ? '' : 's'?>
          </p>
          <?php if ($pages > 1): ?>
            <nav class="notification-pagination" aria-label="Notification pages">
              <?php for ($i = 1; $i <= $pages; $i++): ?>
                <a class="<?=$i === $page ? 'active' : ''?>" href="?page=<?=$i?>"><?=$i?></a>
              <?php endfor; ?>
            </nav>
          <?php endif; ?>
        </footer>
      </section>
    </section>
  </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const pageMarkAll = document.querySelector('[data-notification-page-mark-all]');
  if (!pageMarkAll) return;
  pageMarkAll.addEventListener('click', function () {
    fetch(<?=json_encode(APP_URL . '/actions/system/notifications.php')?>, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json'},
      body: new URLSearchParams({
        csrf_token: window.APRISM_NOTIFICATION_CSRF,
        operation: 'mark_all_read'
      })
    }).then(function () {
      window.location.reload();
    });
  });
});
</script>
<?php require __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>
