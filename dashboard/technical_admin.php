<?php

require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helper/flash_message.php';

$activePage = 'dashboard';

try {

  $dashboardStats = [

    'totalPersonnel' => 0,
    'activeAccounts' => 0,
    'disabledAccounts' => 0,
    'mustChangePassword' => 0

  ];

  $dashboardStats['totalPersonnel'] =
    (int) $pdo->query("
            SELECT COUNT(*)
            FROM users
        ")->fetchColumn();

  $dashboardStats['activeAccounts'] =
    (int) $pdo->query("
            SELECT COUNT(*)
            FROM users
            WHERE account_status = 'Active'
        ")->fetchColumn();

  $dashboardStats['disabledAccounts'] =
    (int) $pdo->query("
            SELECT COUNT(*)
            FROM users
            WHERE account_status = 'Disabled'
        ")->fetchColumn();

  $dashboardStats['mustChangePassword'] =
    (int) $pdo->query("
            SELECT COUNT(*)
            FROM users
            WHERE must_change_password = TRUE
        ")->fetchColumn();

} catch (PDOException $e) {

  error_log(
    '[APRISM Dashboard] Failed to load dashboard statistics: ' .
    $e->getMessage()
  );

  $dashboardStats = [

    'totalPersonnel' => 0,
    'activeAccounts' => 0,
    'disabledAccounts' => 0,
    'mustChangePassword' => 0

  ];

}

$fullName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

$backupFiles = glob(BACKUP_DIRECTORY . DIRECTORY_SEPARATOR . '*.sql');

usort(
  $backupFiles,
  fn($a, $b) => filemtime($b) <=> filemtime($a)
);

$initials =
  strtoupper(substr($_SESSION['first_name'], 0, 1)) .
  strtoupper(substr($_SESSION['last_name'], 0, 1));

try {

  $auditLogs = [];

  $sql = "

        SELECT

            a.created_at,

            CONCAT(u.first_name,' ',u.last_name) AS full_name,

            r.role_name,

            a.action,

            a.description,

            a.ip_address

        FROM audit_logs a

        INNER JOIN users u
            ON a.user_id = u.user_id

        INNER JOIN roles r
            ON u.role_id = r.role_id

        ORDER BY a.created_at DESC

        LIMIT 4

    ";

  $stmt = $pdo->query($sql);

  $auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

  error_log(
    '[APRISM Dashboard] Failed to load audit logs: ' .
    $e->getMessage()
  );

  $auditLogs = [];

}


$pageTitle = 'Dashboard';

$activePage = 'dashboard';

$roleStylesheet = 'assets/css/technical-admin.css';

$pageStylesheet = 'assets/css/pages/technical-admin-dashboard.css';


/*
|--------------------------------------------------------------------------
| Active School Year
|--------------------------------------------------------------------------
*/

$currentSchoolYear = null;

try {

  $stmt = $pdo->query("
    SELECT school_year
    FROM school_years
    WHERE status = 'Active'
    LIMIT 1
  ");

  $currentSchoolYear = $stmt->fetchColumn() ?: null;

} catch (PDOException $e) {

  error_log(
    '[APRISM Active School Year] ' .
    $e->getMessage()
  );

}

?>

<!DOCTYPE html>
<html lang="en">

<?php
require __DIR__ . '/../includes/components/head.php';
?>

<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/pages/technical-admin-dashboard.css">

<body>

  <!-- Technical Administrator Sidebar -->
  <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>


  <!-- Main Content Wrapper -->
  <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">

    <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>

    <!-- Dashboard Title Banner -->
    <section class="page-header">

      <div class="page-header-left">

        <h1 class="page-title">

          Technical Administrator Dashboard

        </h1>

      <div class="page-header-right">

        <button class="refresh-btn" onclick="location.reload();" title="Refresh Dashboard">

          <i data-lucide="refresh-cw" class="animate-hover-spin">
          </i>

        </button>

      </div>

    </section>

    <!-- Quick Stats Grid -->
    <section class="row g-4 mb-4">

      <div class="col-12 col-md-6 col-xl-3">

        <div class="stat-card dashboard-nav-card" data-url="<?= APP_URL ?>/dashboard/technical_admin_users.php">

          <div class="stat-icon-box health">

            <i data-lucide="shield-check" class="w-6 h-6"></i>

          </div>

          <p class="stat-label">

            Total Personnel

          </p>

          <h3 class="stat-value">

            <?= number_format($dashboardStats['totalPersonnel']) ?>

          </h3>

        </div>

      </div>

      <div class="col-12 col-md-6 col-xl-3">

        <div class="stat-card dashboard-nav-card" data-url="<?= APP_URL ?>/dashboard/technical_admin_users.php">

          <div class="stat-icon-box sessions">

            <i data-lucide="users" class="w-6 h-6"></i>

          </div>

          <p class="stat-label">

            Active Accounts

          </p>

          <h3 class="stat-value">

            <?= number_format($dashboardStats['activeAccounts']) ?>

          </h3>

        </div>

      </div>

      <div class="col-12 col-md-6 col-xl-3">

        <div class="stat-card dashboard-nav-card" data-url="<?= APP_URL ?>/dashboard/technical_admin_users.php">

          <div class="stat-icon-box db">

            <i data-lucide="user-x" class="w-6 h-6"></i>

          </div>

          <p class="stat-label">

            Disabled Accounts

          </p>

          <h3 class="stat-value">

            <?= number_format($dashboardStats['disabledAccounts']) ?>

          </h3>

        </div>

      </div>

      <div class="col-12 col-md-6 col-xl-3">

        <div class="stat-card dashboard-nav-card" data-url="<?= APP_URL ?>/dashboard/technical_admin_users.php">

          <div class="stat-icon-box disk">

            <i data-lucide="key-round" class="w-6 h-6"></i>

          </div>

          <p class="stat-label">

            Must Change Password

          </p>

          <h3 class="stat-value">

            <?= number_format($dashboardStats['mustChangePassword']) ?>

          </h3>

        </div>

      </div>

    </section>

    <!-- Secondary Columns Grid (Audit Logs + Backups) -->
    <section class="row g-4">

      <!-- System Audit Logs Section -->
      <div class="col-12 col-xl-8">

        <a href="<?= APP_URL ?>/dashboard/technical_admin_audit_logs.php" class="dashboard-card-link">

          <div class="section-card dashboard-preview-card">

            <div class="section-card-header">

              <div class="section-title-row">

                <h2 class="section-title">

                  System Audit Log

                </h2>

              </div>

            </div>

            <div class="audit-table-container">

              <table class="table audit-table align-middle">

                <thead>

                  <tr>

                    <th class="text-center">
                      Timestamp
                    </th>

                    <th class="text-center">
                      User
                    </th>

                    <th class="text-center">
                      Role
                    </th>

                    <th class="text-center">
                      Action
                    </th>

                    <th class="text-center">
                      IP Address
                    </th>

                    <th class="text-center">
                      Details
                    </th>

                  </tr>

                </thead>

                <tbody>

                  <?php if (empty($auditLogs)): ?>

                    <tr>

                      <td colspan="6">

                        <div class="empty-state">

                          <i data-lucide="file-search"></i>

                          <p class="mb-1 fw-bold">

                            No audit records yet.

                          </p>

                          <small>

                            Administrative activity will automatically appear here.

                          </small>

                        </div>

                      </td>

                    </tr>

                  <?php else: ?>

                    <?php foreach ($auditLogs as $log): ?>

                      <tr>

                        <td class="font-mono-custom">

                          <?= date(
                            'M d, Y h:i A',
                            strtotime($log['created_at'])
                          ) ?>

                        </td>

                        <td>

                          <?= htmlspecialchars($log['full_name']) ?>

                        </td>

                        <td>

                          <span class="badge-role admin">

                            <?= htmlspecialchars($log['role_name']) ?>

                          </span>

                        </td>

                        <td>

                          <?= htmlspecialchars($log['action']) ?>

                        </td>

                        <td class="text-center font-mono-custom">

                          <?= htmlspecialchars($log['ip_address']) ?>

                        </td>

                        <td class="text-center">

                          <button class="details-view-btn" title="<?= htmlspecialchars($log['description']) ?>">

                            <i data-lucide="eye"></i>

                          </button>

                        </td>

                      </tr>

                    <?php endforeach; ?>

                  <?php endif; ?>

                </tbody>

              </table>

            </div>

          </div>

        </a>

      </div>

      <!-- Backups & Recovery Section -->
      <div class="col-12 col-xl-4">

        <a href="<?= APP_URL ?>/dashboard/technical_admin_backups.php" class="dashboard-card-link">

          <div class="section-card dashboard-preview-card">

            <div class="section-card-header">

              <div class="section-title-row">

                <h2 class="section-title">

                  Database Backup History

                </h2>

              </div>

              <form action="<?= APP_URL ?>/actions/system/create_backup.php" method="POST">

                <button type="submit" class="create-backup-btn">

                  <i data-lucide="database"></i>

                  <span>Create Backup</span>

                </button>

              </form>

            </div>

            <div class="backup-stack">

              <?php if (empty($backupFiles)): ?>

                <div class="empty-state">

                  <i data-lucide="database-backup"></i>

                  <p class="mb-1 fw-bold">

                    No Backups Yet

                  </p>

                  <small>

                    Generate your first database snapshot to begin keeping manual recovery points.

                  </small>

                </div>

              <?php else: ?>

                <?php foreach (array_slice($backupFiles, 0, 2) as $backup): ?>

                  <?php

                  $fileName = basename($backup);

                  $fileSize = number_format(
                    filesize($backup) / 1024,
                    2
                  );

                  $fileDate = date(
                    'F d, Y h:i A',
                    filemtime($backup)
                  );

                  ?>

                  <div class="backup-item-card">

                    <div class="backup-header-row">

                      <div>

                        <p class="backup-filename">

                          <?= htmlspecialchars($fileName) ?>

                        </p>

                        <p class="backup-meta">

                          <?= $fileDate ?>

                          •

                          <?= $fileSize ?> KB

                        </p>

                      </div>

                      <span class="badge-status successful">

                        VALID

                      </span>

                    </div>

                    <div class="backup-actions-row">

                      <a href="<?= APP_URL ?>/actions/system/download_backup.php?file=<?= urlencode($fileName) ?>"
                        class="backup-btn download">

                        <i data-lucide="download"></i>

                      </a>

                    </div>

                  </div>

                <?php endforeach; ?>

              <?php endif; ?>

            </div>

          </div>

        </a>

      </div>

    </section>

  </main>

  <?php require_once __DIR__ . '/../includes/components/logout_modal.php'; ?>

  <?php if ($flash['success']): ?>

    <div class="toast-custom" id="aprismToast">

      <div class="toast-icon success">

        <i data-lucide="circle-check-big"></i>

      </div>

      <div>

        <div class="fw-bold">

          Backup Complete

        </div>

        <div class="toast-text">

          <?= htmlspecialchars($flash['success']) ?>

        </div>

      </div>

    </div>

  <?php endif; ?>

  <?php if ($flash['error']): ?>

    <div class="toast-custom" id="aprismToast">

      <div class="toast-icon warning">

        <i data-lucide="triangle-alert"></i>

      </div>

      <div>

        <div class="fw-bold">

          Backup Failed

        </div>

        <div class="toast-text">

          <?= htmlspecialchars($flash['error']) ?>

        </div>

      </div>

    </div>

  <?php endif; ?>


  <!-- Custom Frontend Interaction Logic -->
  <script>

    const toast =
      document.getElementById('aprismToast');

    if (toast) {

      setTimeout(() => {

        toast.style.opacity = '0';

        toast.style.transform =
          'translateX(30px)';

        setTimeout(() => {

          toast.remove();

        }, 250);

      }, 3000);

    }

  </script>

  <?php
  require_once __DIR__ .
    '/../includes/components/footer.php';
  ?>

</body>

</html>