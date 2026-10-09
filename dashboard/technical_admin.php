<?php

require_once __DIR__ . '/../auth/role_helper.php';

$allowedRoles = [ROLE_TECHNICAL_ADMINISTRATOR];

require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../auth/csrf_helper.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../includes/helper/flash_message.php';

$activePage = 'dashboard';

$dashboardStatsLoadError = false;

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

  $dashboardStatsLoadError = true;

}

$fullName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

$dashboardBackupLoadError = false;
$backupFiles = glob(BACKUP_DIRECTORY . DIRECTORY_SEPARATOR . '*.sql');

if ($backupFiles === false) {

  error_log('[APRISM Dashboard] Failed to list backup files.');
  $dashboardBackupLoadError = true;
  $backupFiles = [];

} else {

  $backupFiles = array_values(array_filter($backupFiles, 'is_file'));

  usort(
    $backupFiles,
    fn($a, $b) => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0)
  );

}

$initials =
  strtoupper(substr($_SESSION['first_name'], 0, 1)) .
  strtoupper(substr($_SESSION['last_name'], 0, 1));

try {

  $auditLogs = [];
  $dashboardAuditLoadError = false;

  $sql = "

        SELECT

            a.created_at,

            a.user_id,

            CASE
              WHEN a.user_id IS NULL THEN 'System'
              ELSE CONCAT(u.first_name, ' ', u.last_name)
            END AS full_name,

            COALESCE(r.role_name, 'System') AS role_name,

            a.action,

            a.description,

            a.ip_address

        FROM audit_logs a

        LEFT JOIN users u
            ON a.user_id = u.user_id

        LEFT JOIN roles r
            ON u.role_id = r.role_id

        ORDER BY a.created_at DESC

        LIMIT 8

    ";

  $stmt = $pdo->query($sql);

  $auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

  error_log(
    '[APRISM Dashboard] Failed to load audit logs: ' .
    $e->getMessage()
  );

  $auditLogs = [];
  $dashboardAuditLoadError = true;

}


$pageTitle = 'Dashboard';

$activePage = 'dashboard';

$roleStylesheet = 'assets/css/technical-admin.css';

$pageStylesheet = 'assets/css/pages/technical-admin-dashboard.css';
$additionalStylesheets = ['assets/css/pages/dashboard-shared.css'];


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


<body>

  <!-- Technical Administrator Sidebar -->
  <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>


  <!-- Main Content Wrapper -->
  <main class="main-content aprism-dashboard <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">

    <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>

    <!-- Dashboard Title Banner -->
    <section class="page-header">

      <div class="page-header-left">

        <h1 class="page-title">

          Technical Administrator Dashboard

        </h1>

      </div>

      <div class="page-header-right">

        <button class="refresh-btn" onclick="location.reload();" title="Refresh Dashboard" aria-label="Refresh dashboard">

          <i data-lucide="refresh-cw" class="animate-hover-spin">
          </i>

        </button>

      </div>

    </section>

    <?php if ($dashboardStatsLoadError): ?>
      <div class="alert alert-warning dashboard-load-alert" role="alert">
        Account statistics could not be loaded. Refresh the page to try again.
      </div>
    <?php endif; ?>

    <!-- Quick Stats Grid -->
    <section class="row g-4 mb-4 technical-admin-kpi-grid">

      <div class="col-12 col-md-6 col-xl-3">

        <a href="<?= APP_URL ?>/dashboard/technical_admin_users.php" class="stat-card dashboard-nav-card">

          <div class="stat-icon-box health">

            <i data-lucide="shield-check" class="w-6 h-6"></i>

          </div>

          <p class="stat-label">

            Total Personnel

          </p>

          <h3 class="stat-value">

            <?= $dashboardStatsLoadError ? '—' : number_format($dashboardStats['totalPersonnel']) ?>

          </h3>

        </a>

      </div>

      <div class="col-12 col-md-6 col-xl-3">

        <a href="<?= APP_URL ?>/dashboard/technical_admin_users.php" class="stat-card dashboard-nav-card">

          <div class="stat-icon-box sessions">

            <i data-lucide="users" class="w-6 h-6"></i>

          </div>

          <p class="stat-label">

            Active Accounts

          </p>

          <h3 class="stat-value">

            <?= $dashboardStatsLoadError ? '—' : number_format($dashboardStats['activeAccounts']) ?>

          </h3>

        </a>

      </div>

      <div class="col-12 col-md-6 col-xl-3">

        <a href="<?= APP_URL ?>/dashboard/technical_admin_users.php" class="stat-card dashboard-nav-card">

          <div class="stat-icon-box db">

            <i data-lucide="user-x" class="w-6 h-6"></i>

          </div>

          <p class="stat-label">

            Disabled Accounts

          </p>

          <h3 class="stat-value">

            <?= $dashboardStatsLoadError ? '—' : number_format($dashboardStats['disabledAccounts']) ?>

          </h3>

        </a>

      </div>

      <div class="col-12 col-md-6 col-xl-3">

        <a href="<?= APP_URL ?>/dashboard/technical_admin_users.php" class="stat-card dashboard-nav-card">

          <div class="stat-icon-box disk">

            <i data-lucide="key-round" class="w-6 h-6"></i>

          </div>

          <p class="stat-label">

            Must Change Password

          </p>

          <h3 class="stat-value">

            <?= $dashboardStatsLoadError ? '—' : number_format($dashboardStats['mustChangePassword']) ?>

          </h3>

        </a>

      </div>

    </section>

    <!-- Secondary Columns Grid (Audit Logs + Backups) -->
    <section class="row g-4 dashboard-secondary-grid">

      <!-- System Audit Logs Section -->
      <div class="col-12 col-xl-8 dashboard-audit-column">

          <div class="section-card dashboard-preview-card">

            <div class="section-card-header">

              <div class="section-title-row">

                <h2 class="section-title">
                  <a href="<?= APP_URL ?>/dashboard/technical_admin_audit_logs.php" class="dashboard-section-link">System Audit Log</a>
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

                  <?php if ($dashboardAuditLoadError): ?>

                    <tr>
                      <td colspan="6">
                        <div class="empty-state" role="alert">
                          <i data-lucide="triangle-alert"></i>
                          <p class="mb-1 fw-bold">Unable to load audit records.</p>
                          <small>Refresh the page to try again.</small>
                        </div>
                      </td>
                    </tr>

                  <?php elseif (empty($auditLogs)): ?>

                    <tr>

                      <td colspan="6">

                        <div class="empty-state" role="status">

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

                          <?= htmlspecialchars($log['full_name'] ?? 'System') ?>

                        </td>

                        <td>

                          <span class="badge-role admin">

                            <?= htmlspecialchars($log['role_name'] ?? 'System') ?>

                          </span>

                        </td>

                        <td>

                          <?= htmlspecialchars($log['action']) ?>

                        </td>

                        <td class="text-center font-mono-custom">

                          <?= htmlspecialchars($log['ip_address'] ?? '—') ?>

                        </td>

                        <td class="text-center">

                          <button
                            type="button"
                            class="details-view-btn"
                            title="View audit event details"
                            aria-label="View audit event details for <?= htmlspecialchars($log['full_name'] ?? 'System', ENT_QUOTES, 'UTF-8') ?>"
                            data-bs-toggle="modal"
                            data-bs-target="#dashboardAuditDetailsModal"
                            data-audit-user="<?= htmlspecialchars($log['full_name'] ?? 'System', ENT_QUOTES, 'UTF-8') ?>"
                            data-audit-role="<?= htmlspecialchars($log['role_name'] ?? 'System', ENT_QUOTES, 'UTF-8') ?>"
                            data-audit-action="<?= htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8') ?>"
                            data-audit-ip="<?= htmlspecialchars($log['ip_address'] ?? '—', ENT_QUOTES, 'UTF-8') ?>"
                            data-audit-date="<?= htmlspecialchars(date('M d, Y h:i A', strtotime($log['created_at'])), ENT_QUOTES, 'UTF-8') ?>"
                            data-audit-description="<?= htmlspecialchars($log['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

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

      </div>

      <!-- Backups & Recovery Section -->
      <div class="col-12 col-xl-4 dashboard-backup-column">

          <div class="section-card dashboard-preview-card">

            <div class="section-card-header">

              <div class="section-title-row">

                <h2 class="section-title">
                  <a href="<?= APP_URL ?>/dashboard/technical_admin_backups.php" class="dashboard-section-link">Database Backup History</a>
                </h2>

              </div>

              <form action="<?= APP_URL ?>/actions/system/create_backup.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                <button type="submit" class="create-backup-btn">

                  <i data-lucide="database"></i>

                  <span>Create Backup</span>

                </button>

              </form>

            </div>

            <div class="backup-stack">

              <?php if ($dashboardBackupLoadError): ?>

                <div class="empty-state" role="alert">
                  <i data-lucide="triangle-alert"></i>
                  <p class="mb-1 fw-bold">Unable to load backup history.</p>
                  <small>Refresh the page to try again.</small>
                </div>

              <?php elseif (empty($backupFiles)): ?>

                <div class="empty-state" role="status">

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

                    </div>

                    <div class="backup-actions-row">

                      <a href="<?= APP_URL ?>/actions/system/download_backup.php?file=<?= urlencode($fileName) ?>"
                        class="backup-btn download"
                        title="Download backup"
                        aria-label="Download backup <?= htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') ?>">

                        <i data-lucide="download"></i>

                      </a>

                    </div>

                  </div>

                <?php endforeach; ?>

              <?php endif; ?>

            </div>

          </div>

      </div>

    </section>

  </main>

  <div class="modal fade" id="dashboardAuditDetailsModal" tabindex="-1" aria-labelledby="dashboardAuditDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title fs-5" id="dashboardAuditDetailsModalLabel">Audit Event Details</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <dl class="dashboard-audit-detail-grid">
            <div><dt>User</dt><dd data-audit-detail="user"></dd></div>
            <div><dt>Role</dt><dd data-audit-detail="role"></dd></div>
            <div><dt>Action</dt><dd data-audit-detail="action"></dd></div>
            <div><dt>Timestamp</dt><dd data-audit-detail="date"></dd></div>
            <div><dt>IP Address</dt><dd data-audit-detail="ip"></dd></div>
            <div class="full"><dt>Description</dt><dd data-audit-detail="description"></dd></div>
          </dl>
        </div>
      </div>
    </div>
  </div>

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

    const dashboardAuditDetailsModal = document.getElementById('dashboardAuditDetailsModal');

    dashboardAuditDetailsModal?.addEventListener('show.bs.modal', (event) => {
      const trigger = event.relatedTarget;

      if (!trigger) {
        return;
      }

      const values = {
        user: trigger.dataset.auditUser,
        role: trigger.dataset.auditRole,
        action: trigger.dataset.auditAction,
        date: trigger.dataset.auditDate,
        ip: trigger.dataset.auditIp,
        description: trigger.dataset.auditDescription || '—'
      };

      Object.entries(values).forEach(([key, value]) => {
        const field = dashboardAuditDetailsModal.querySelector(`[data-audit-detail="${key}"]`);

        if (field) {
          field.textContent = value || '—';
        }
      });
    });

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
