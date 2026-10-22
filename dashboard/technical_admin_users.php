<?php

require_once __DIR__ . '/../auth/role_helper.php';


$allowedRoles = [
  ROLE_TECHNICAL_ADMINISTRATOR
];

require_once __DIR__ . '/../includes/helper/flash_message.php';
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../auth/csrf_helper.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../includes/helper/responsibility_helper.php';
require_once __DIR__ . '/../includes/helper/report_export_helper.php';

$activePage = 'users';
$csrfToken = generateCsrfToken();

$userSearch = trim($_GET['search'] ?? '');
$userRoleFilter = trim($_GET['role'] ?? 'All');
$userResponsibilityFilter = trim($_GET['responsibility'] ?? 'All');
$userStatusFilter = trim($_GET['status'] ?? 'All');
$userExportFormat = in_array((string) ($_GET['export'] ?? ''), ['csv', 'xlsx'], true) ? (string) $_GET['export'] : '';
$usersPerPage = 7;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$totalUsers = 0;
$totalPages = 1;
$offset = 0;
$activeSchoolYear = null;
$userLoadError = false;
$clientUsers = [];

try {

  $activeSchoolYear = aprismActiveSchoolYear($pdo);

  $whereConditions = [];
  $queryParams = [];

  if ($userSearch !== '') {
    $whereConditions[] = "
      (
        CONCAT(u.first_name, ' ', u.last_name) LIKE :user_search_name
        OR u.email LIKE :user_search_email
      )
    ";

    $searchValue = '%' . $userSearch . '%';
    $queryParams[':user_search_name'] = $searchValue;
    $queryParams[':user_search_email'] = $searchValue;
  }

  if ($userRoleFilter !== '' && $userRoleFilter !== 'All') {
    $whereConditions[] = 'r.role_name = :user_role_filter';
    $queryParams[':user_role_filter'] = $userRoleFilter;
  }

  if ($userStatusFilter !== '' && $userStatusFilter !== 'All') {
    $whereConditions[] = 'u.account_status = :user_status_filter';
    $queryParams[':user_status_filter'] = $userStatusFilter;
  }

  if ($userResponsibilityFilter !== '' && $userResponsibilityFilter !== 'All') {
    $whereConditions[] = "
      EXISTS (
        SELECT 1
        FROM teacher_responsibility_assignments tra_filter
        WHERE tra_filter.teacher_id = u.user_id
          AND tra_filter.school_year_id = :user_responsibility_school_year
          AND tra_filter.responsibility_name = :user_responsibility_filter
          AND tra_filter.status = 'Active'
      )
    ";
    $queryParams[':user_responsibility_school_year'] = $activeSchoolYear
      ? (int) $activeSchoolYear['school_year_id']
      : 0;
    $queryParams[':user_responsibility_filter'] = $userResponsibilityFilter;
  }

  $whereSql = $whereConditions
    ? ' WHERE ' . implode(' AND ', $whereConditions)
    : '';

  $countSql = "
    SELECT COUNT(*)
    FROM users u
    INNER JOIN roles r
      ON u.role_id = r.role_id
    {$whereSql}
  ";

  $countStmt = $pdo->prepare($countSql);

  foreach ($queryParams as $key => $value) {
    $countStmt->bindValue(
      $key,
      $value,
      is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
    );
  }

  $countStmt->execute();
  $totalUsers = (int) $countStmt->fetchColumn();
  $totalPages = max(1, (int) ceil($totalUsers / $usersPerPage));
  $currentPage = min($currentPage, $totalPages);
  $offset = ($currentPage - 1) * $usersPerPage;

  $sql = "
    SELECT
      u.user_id,
      u.role_id,
      u.employee_number,
      u.username,
      u.email,
      u.first_name,
      u.last_name,
      u.account_status,
      u.last_login_at,
      r.role_name
    FROM users u
    INNER JOIN roles r
      ON u.role_id = r.role_id
    {$whereSql}
    ORDER BY
      u.last_name ASC,
      u.first_name ASC,
      u.user_id ASC
    " . ($userExportFormat !== '' ? '' : 'LIMIT :user_limit OFFSET :user_offset') . "
    ";

  $stmt = $pdo->prepare($sql);

  foreach ($queryParams as $key => $value) {
    $stmt->bindValue(
      $key,
      $value,
      is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
    );
  }

  if ($userExportFormat === '') {
    $stmt->bindValue(':user_limit', $usersPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':user_offset', $offset, PDO::PARAM_INT);
  }
  $stmt->execute();

  $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $printUsers = $users;
  if ($userExportFormat === '') {
    $printSql = str_replace('LIMIT :user_limit OFFSET :user_offset', '', $sql);
    $printStmt = $pdo->prepare($printSql);
    foreach ($queryParams as $key => $value) {
      $printStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $printStmt->execute();
    $printUsers = $printStmt->fetchAll(PDO::FETCH_ASSOC);
  }
  $responsibilityStmt = $pdo->prepare("SELECT teacher_id AS user_id, responsibility_name AS permission_name FROM teacher_responsibility_assignments WHERE school_year_id = ? AND status = 'Active' ORDER BY teacher_id, responsibility_name");
  $responsibilityStmt->execute([$activeSchoolYear ? (int) $activeSchoolYear['school_year_id'] : 0]);
  $responsibilityMap = [];

  while ($row = $responsibilityStmt->fetch(PDO::FETCH_ASSOC)) {

    $responsibilityMap[$row['user_id']][] =
      $row['permission_name'];

  }

  if ($userExportFormat === '') {
    $directoryStmt = $pdo->query("SELECT u.user_id, u.role_id, u.employee_number, u.username, u.email, u.first_name, u.last_name, u.account_status, u.last_login_at, r.role_name FROM users u INNER JOIN roles r ON u.role_id = r.role_id ORDER BY u.last_name ASC, u.first_name ASC, u.user_id ASC");
    $clientUsers = $directoryStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($clientUsers as &$directoryUser) {
      $directoryUser['responsibilities'] = $responsibilityMap[$directoryUser['user_id']] ?? [];
    }
    unset($directoryUser);
  }

} catch (PDOException $e) {

  error_log('[APRISM Users] Failed to load user directory: ' . $e->getMessage());
  $users = [];
  $printUsers = [];
  $userLoadError = true;

}

if ($userLoadError && in_array($userExportFormat, ['csv', 'xlsx'], true)) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=UTF-8');
  header('Cache-Control: no-store');
  exit('Unable to prepare the Users export. Refresh the page to try again.');
}

if (!$userLoadError && in_array($userExportFormat, ['csv', 'xlsx'], true)) {
  $exportRows = [];
  foreach ($users as $user) {
    $exportRows[] = [
      (string) ($user['first_name'] . ' ' . $user['last_name']),
      (string) ($user['employee_number'] ?? ''),
      (string) ($user['email'] ?? ''),
      (string) ($user['role_name'] ?? ''),
      implode(', ', $responsibilityMap[$user['user_id']] ?? []),
      (string) ($user['account_status'] ?? ''),
      $user['last_login_at'] === null ? '' : (strtotime((string) $user['last_login_at']) !== false ? date('Y-m-d H:i', strtotime((string) $user['last_login_at'])) : (string) $user['last_login_at']),
    ];
  }
  $context = ['School Year' => (string) ($activeSchoolYear['school_year'] ?? 'Unavailable'), 'Filtered Users' => (string) $totalUsers, 'Filters' => implode(' · ', array_filter([
    $userSearch !== '' ? 'Search: ' . $userSearch : '',
    $userRoleFilter !== 'All' ? 'Role: ' . $userRoleFilter : '',
    $userResponsibilityFilter !== 'All' ? 'Responsibility: ' . $userResponsibilityFilter : '',
    $userStatusFilter !== 'All' ? 'Status: ' . $userStatusFilter : '',
  ])) ?: 'All Users'];
  $headers = ['Name', 'Employee Number', 'Institutional Email', 'Main Role', 'Responsibilities', 'Account Status', 'Last Login'];
  $filename = 'aprism-users-' . date('Ymd_His');
  if ($userExportFormat === 'csv') aprismSendCsv($filename, [['APRISM'], ['Technical Administrator Users'], ['School Year', $context['School Year']], ['Filtered Users', $context['Filtered Users']], ['Filters', $context['Filters']], [], $headers, ...$exportRows]);
  aprismSendXlsx($filename, 'Technical Administrator Users', $context, $headers, $exportRows, ['A' => 28, 'B' => 20, 'C' => 34, 'D' => 28, 'E' => 34, 'F' => 18, 'G' => 22], true);
}

$fullName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

$initials =
  strtoupper(substr($_SESSION['first_name'], 0, 1)) .
  strtoupper(substr($_SESSION['last_name'], 0, 1));

$successMessage = $flash['success'] ?? null;
$createUserRetry = $_SESSION['create_user_retry'] ?? null;
$createUserFieldErrors = $_SESSION['create_user_field_errors'] ?? [];
$createUserGeneralError = $_SESSION['create_user_general_error'] ?? '';
unset($_SESSION['create_user_retry'], $_SESSION['create_user_field_errors'], $_SESSION['create_user_general_error']);

$temporaryPassword = $_SESSION['temporary_password'] ?? null;

$temporaryPasswordUser =
  $_SESSION['temporary_password_user'] ?? null;

unset($_SESSION['temporary_password']);
unset($_SESSION['temporary_password_user']);

function buildUsersPageUrl(
  int $page,
  string $search,
  string $role,
  string $responsibility,
  string $status
): string {
  $params = [];

  if ($search !== '') {
    $params['search'] = $search;
  }

  if ($role !== '' && $role !== 'All') {
    $params['role'] = $role;
  }

  if ($responsibility !== '' && $responsibility !== 'All') {
    $params['responsibility'] = $responsibility;
  }

  if ($status !== '' && $status !== 'All') {
    $params['status'] = $status;
  }

  $params['page'] = $page;

  return '?' . http_build_query($params);
}


$pageTitle = 'Users';

$activePage = 'users';

$roleStylesheet = 'assets/css/technical-admin.css';

$pageStylesheet = 'assets/css/pages/technical-admin-users.css?v=' . filemtime(__DIR__ . '/../assets/css/pages/technical-admin-users.css');


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
require_once __DIR__
  . '/../includes/components/head.php';
?>


<body class="aprism-report-print-page technical-admin-users-page">

  <!-- Technical Administrator Sidebar -->
  <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>

  <!-- Main Content Wrapper -->
  <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">

    <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>

    <!-- Page Header Title & Core Actions -->
    <section class="page-header">
      <div>
        <h1 class="page-title">Manage Users</h1>
      </div>
      <div class="header-actions-group users-header-actions">
        <!-- Secondary Action: Export Users -->
        <div class="aprism-export-actions no-print" aria-label="Filtered users export actions">
          <a class="aprism-export-action aprism-export-csv" href="?<?= htmlspecialchars(http_build_query(['search' => $userSearch, 'role' => $userRoleFilter, 'responsibility' => $userResponsibilityFilter, 'status' => $userStatusFilter, 'export' => 'csv']), ENT_QUOTES, 'UTF-8') ?>"><i data-lucide="file-down" aria-hidden="true"></i><span>CSV</span></a>
          <a class="aprism-export-action aprism-export-xlsx" href="?<?= htmlspecialchars(http_build_query(['search' => $userSearch, 'role' => $userRoleFilter, 'responsibility' => $userResponsibilityFilter, 'status' => $userStatusFilter, 'export' => 'xlsx']), ENT_QUOTES, 'UTF-8') ?>"><i data-lucide="file-spreadsheet" aria-hidden="true"></i><span>Excel</span></a>
          <button class="aprism-export-action aprism-export-print" type="button" data-report-print <?= $userLoadError ? 'disabled' : '' ?>><i data-lucide="printer" aria-hidden="true"></i><span>Print / PDF</span></button>
        </div>
        <button class="header-action-btn btn-primary-action users-add-user-action" id="btnAddUser" type="button" title="Add User" aria-label="Add User" data-aprism-tooltip="Add User">
          <i data-lucide="user-plus" aria-hidden="true"></i>
        </button>
      </div>
    </section>
    <div class="aprism-report-print-only users-print-heading"><strong>APRISM</strong><h1>Technical Administrator Users</h1><p>School Year: <?= htmlspecialchars((string) ($currentSchoolYear ?? 'Unavailable'), ENT_QUOTES, 'UTF-8') ?> · <span id="usersPrintCount"><?= number_format($totalUsers) ?></span> filtered users</p><p id="usersPrintFilterSummary"><?= htmlspecialchars(implode(' · ', array_filter([$userSearch !== '' ? 'Search: ' . $userSearch : '', $userRoleFilter !== 'All' ? 'Role: ' . $userRoleFilter : '', $userResponsibilityFilter !== 'All' ? 'Responsibility: ' . $userResponsibilityFilter : '', $userStatusFilter !== 'All' ? 'Status: ' . $userStatusFilter : 'All Users'])), ENT_QUOTES, 'UTF-8') ?></p></div>

    <!-- Main Content Panel -->
    <section class="section-card">

      <!-- Table Search & Filter Controls -->
      <form method="GET" class="users-toolbar" id="userFiltersForm">
        <div class="toolbar-search">
          <i data-lucide="search" class="toolbar-search-icon"></i>
          <label class="visually-hidden" for="searchFilter">Search users by name or email</label>
          <input type="text" class="toolbar-search-input" id="searchFilter" name="search"
            placeholder="Search by name or email..." value="<?= htmlspecialchars($userSearch) ?>"
            role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="userSearchSuggestions" autocomplete="off" />
          <button type="button" class="users-search-clear" id="clearUserSearch" aria-label="Clear search" title="Clear search" hidden>
            <i data-lucide="x" aria-hidden="true"></i>
          </button>
          <div class="users-search-suggestions" id="userSearchSuggestions" role="listbox" aria-label="Matching users" hidden></div>
        </div>

        <label class="visually-hidden" for="roleFilter">Filter users by role</label>
        <select class="toolbar-select" id="roleFilter" name="role">
          <option value="All" <?= $userRoleFilter === 'All' ? 'selected' : '' ?>>All Main Roles</option>
          <option value="Technical Administrator" <?= $userRoleFilter === 'Technical Administrator' ? 'selected' : '' ?>>
            Technical Administrator
          </option>

          <option value="Academic Head" <?= $userRoleFilter === 'Academic Head' ? 'selected' : '' ?>>
            Academic Head
          </option>

          <option value="Teacher" <?= $userRoleFilter === 'Teacher' ? 'selected' : '' ?>>
            Teacher
          </option>

          <option value="Disciplinary Officer" <?= $userRoleFilter === 'Disciplinary Officer' ? 'selected' : '' ?>>
            Disciplinary Officer
          </option>
        </select>

        <label class="visually-hidden" for="responsibilityFilter">Filter users by responsibility</label>
        <select class="toolbar-select" id="responsibilityFilter" name="responsibility">
          <option value="All" <?= $userResponsibilityFilter === 'All' ? 'selected' : '' ?>>All Responsibilities</option>
          <option value="Adviser" <?= $userResponsibilityFilter === 'Adviser' ? 'selected' : '' ?>>Adviser</option>
        </select>

        <label class="visually-hidden" for="statusFilter">Filter users by account status</label>
        <select class="toolbar-select" id="statusFilter" name="status">
          <option value="All" <?= $userStatusFilter === 'All' ? 'selected' : '' ?>>All Statuses</option>
          <option value="Active" <?= $userStatusFilter === 'Active' ? 'selected' : '' ?>>Active Only</option>
          <option value="Disabled" <?= $userStatusFilter === 'Disabled' ? 'selected' : '' ?>>Disabled Only</option>
        </select>
      </form>

      <!-- Users Directory Table Grid -->
      <div class="aprism-users-print-table-wrap">
        <table class="aprism-users-print-table">
          <thead><tr><th>Name</th><th>Employee Number</th><th>Institutional Email</th><th>Main Role</th><th>Responsibilities</th><th>Account Status</th><th>Last Login</th></tr></thead>
          <tbody id="usersPrintTableBody">
            <?php foreach ($printUsers as $printUser): ?>
              <tr>
                <td><?= htmlspecialchars($printUser['first_name'] . ' ' . $printUser['last_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($printUser['employee_number'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($printUser['email'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($printUser['role_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(implode(', ', $responsibilityMap[$printUser['user_id']] ?? []) ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($printUser['account_status'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($printUser['last_login_at'] === null ? 'Never' : (string) $printUser['last_login_at'], ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if ($userLoadError): ?><tr><td colspan="7">Unable to load users for printing.</td></tr><?php elseif ($printUsers === []): ?><tr><td colspan="7">No users match the current filters.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="user-table-container users-screen-table">
        <div class="table-responsive">
          <table class="table user-table align-middle">
            <thead>
              <tr>
                <th class="text-center col-name" style="width: 25%;">Name</th>
                <th class="text-center" style="width: 20%;">Institutional Email</th>
                <th class="text-center" style="width: 15%;">Role</th>
                <th class="text-center" style="width: 20%;">Responsibilities</th>
                <th class="text-center" style="width: 10%;">Status</th>
                <th class="text-center" style="width: 10%;">Last Login</th>
                <th class="text-center" style="width: 10%;">Actions</th>
              </tr>
            </thead>
            <tbody id="usersTableBody">

              <?php if ($userLoadError): ?>

                <tr>
                  <td colspan="7">
                    <div class="empty-state" role="alert">
                      <i data-lucide="triangle-alert"></i>
                      <p class="mb-1 fw-bold">Unable to load users.</p>
                      <small>Please refresh the page or try again later.</small>
                    </div>
                  </td>
                </tr>

              <?php elseif (empty($users)): ?>

                <tr>
                  <td colspan="7">
                    <div class="empty-state" role="status">
                      <i data-lucide="<?= ($userSearch !== '' || $userRoleFilter !== 'All' || $userResponsibilityFilter !== 'All' || $userStatusFilter !== 'All') ? 'file-search' : 'users' ?>"></i>
                      <p class="mb-1 fw-bold">
                        <?= ($userSearch !== '' || $userRoleFilter !== 'All' || $userResponsibilityFilter !== 'All' || $userStatusFilter !== 'All') ? 'No matching users.' : 'No users available.' ?>
                      </p>
                      <small>
                        <?php if ($userSearch !== '' || $userRoleFilter !== 'All' || $userResponsibilityFilter !== 'All' || $userStatusFilter !== 'All'): ?>
                          Try adjusting or clearing the current filters.
                        <?php else: ?>
                          Use the "Add User" button to create the first account.
                        <?php endif; ?>
                      </small>
                    </div>
                  </td>
                </tr>

              <?php else: ?>

                <?php foreach ($users as $user): ?>

                  <tr>

                    <td>
                      <strong>
                        <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                      </strong>
                      <br>
                      <small class="text-muted">
                        <?= htmlspecialchars($user['employee_number']) ?>
                      </small>
                    </td>

                    <td>
                      <?= htmlspecialchars($user['email']) ?>
                    </td>

                    <td>
                      <?= htmlspecialchars($user['role_name']) ?>
                    </td>

                    <td>
                      <?php
                      $responsibilities =
                        $responsibilityMap[$user['user_id']] ?? [];
                      ?>

                      <?php if (empty($responsibilities)): ?>

                        --

                      <?php else: ?>

                        <?php foreach ($responsibilities as $responsibility): ?>

                          <span class="badge-responsibility">
                            <?= htmlspecialchars($responsibility) ?>
                          </span>

                        <?php endforeach; ?>

                      <?php endif; ?>

                    </td>

                    <td>
                      <?= htmlspecialchars($user['account_status']) ?>
                    </td>

                    <td>

                      <?php if ($user['last_login_at'] === null): ?>

                        Never

                      <?php else: ?>

                        <?= htmlspecialchars($user['last_login_at']) ?>

                      <?php endif; ?>

                    </td>

                    <td>

                      <div class="action-btn-container">

                        <button type="button" class="action-row-btn btn-view" data-user-id="<?= (int) $user['user_id'] ?>"
                          data-responsibilities="<?= htmlspecialchars(
                            json_encode($responsibilityMap[$user['user_id']] ?? []),
                            ENT_QUOTES,
                            'UTF-8'
                          ) ?>" data-employee-number="<?= htmlspecialchars($user['employee_number']) ?>"
                          data-username="<?= htmlspecialchars($user['username']) ?>"
                          data-first-name="<?= htmlspecialchars($user['first_name']) ?>"
                          data-last-name="<?= htmlspecialchars($user['last_name']) ?>"
                          data-email="<?= htmlspecialchars($user['email']) ?>" data-role-id="<?= (int) $user['role_id'] ?>"
                          data-role="<?= htmlspecialchars($user['role_name']) ?>"
                          data-status="<?= htmlspecialchars($user['account_status']) ?>"
                          data-last-login="<?= htmlspecialchars($user['last_login_at'] ?? '') ?>" title="View User"
                          aria-label="View user <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES, 'UTF-8') ?>">

                          <i data-lucide="eye"></i>

                        </button>

                        <button type="button" class="action-row-btn btn-edit" data-user-id="<?= (int) $user['user_id'] ?>"
                          data-employee-number="<?= htmlspecialchars($user['employee_number']) ?>"
                          data-username="<?= htmlspecialchars($user['username']) ?>"
                          data-first-name="<?= htmlspecialchars($user['first_name']) ?>"
                          data-last-name="<?= htmlspecialchars($user['last_name']) ?>"
                          data-email="<?= htmlspecialchars($user['email']) ?>" data-role-id="<?= (int) $user['role_id'] ?>"
                          data-role="<?= htmlspecialchars($user['role_name']) ?>"
                          data-status="<?= htmlspecialchars($user['account_status']) ?>"
                          data-last-login="<?= htmlspecialchars($user['last_login_at'] ?? '') ?>" title="Edit User"
                          aria-label="Edit user <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES, 'UTF-8') ?>">

                          <i data-lucide="edit"></i>

                        </button>

                        <?php if ($user['role_name'] === 'Teacher'): ?>
                        <button type="button" class="action-row-btn btn-responsibilities"
                          data-user-id="<?= (int) $user['user_id'] ?>" data-responsibilities="<?= htmlspecialchars(
                               json_encode($responsibilityMap[$user['user_id']] ?? []),
                               ENT_QUOTES,
                               'UTF-8'
                             ) ?>" data-employee-number="<?= htmlspecialchars($user['employee_number']) ?>"
                          data-username="<?= htmlspecialchars($user['username']) ?>"
                          data-first-name="<?= htmlspecialchars($user['first_name']) ?>"
                          data-last-name="<?= htmlspecialchars($user['last_name']) ?>"
                          data-email="<?= htmlspecialchars($user['email']) ?>" data-role-id="<?= (int) $user['role_id'] ?>"
                          data-role="<?= htmlspecialchars($user['role_name']) ?>"
                          data-status="<?= htmlspecialchars($user['account_status']) ?>"
                          data-last-login="<?= htmlspecialchars($user['last_login_at'] ?? '') ?>"
                          title="Manage Responsibilities"
                          aria-label="Manage responsibilities for <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES, 'UTF-8') ?>">

                          <i data-lucide="shield"></i>

                        </button>
                        <?php endif; ?>

                        <button type="button" class="action-row-btn btn-reset" data-user-id="<?= (int) $user['user_id'] ?>"
                          data-employee-number="<?= htmlspecialchars($user['employee_number']) ?>"
                          data-username="<?= htmlspecialchars($user['username']) ?>"
                          data-first-name="<?= htmlspecialchars($user['first_name']) ?>"
                          data-last-name="<?= htmlspecialchars($user['last_name']) ?>"
                          data-email="<?= htmlspecialchars($user['email']) ?>" data-role-id="<?= (int) $user['role_id'] ?>"
                          data-role="<?= htmlspecialchars($user['role_name']) ?>"
                          data-status="<?= htmlspecialchars($user['account_status']) ?>"
                          data-last-login="<?= htmlspecialchars($user['last_login_at'] ?? '') ?>" title="Reset Password"
                          aria-label="Reset password for <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES, 'UTF-8') ?>">

                          <i data-lucide="key"></i>

                        </button>

                        <?php
                        $isActive = $user['account_status'] === 'Active';
                        ?>

                        <button type="button"
                          class="action-row-btn <?= $isActive ? 'btn-toggle-disable' : 'btn-toggle-enable' ?>"
                          data-user-id="<?= (int) $user['user_id'] ?>"
                          data-employee-number="<?= htmlspecialchars($user['employee_number']) ?>"
                          data-username="<?= htmlspecialchars($user['username']) ?>"
                          data-first-name="<?= htmlspecialchars($user['first_name']) ?>"
                          data-last-name="<?= htmlspecialchars($user['last_name']) ?>"
                          data-email="<?= htmlspecialchars($user['email']) ?>" data-role-id="<?= (int) $user['role_id'] ?>"
                          data-role="<?= htmlspecialchars($user['role_name']) ?>"
                          data-status="<?= htmlspecialchars($user['account_status']) ?>"
                          data-last-login="<?= htmlspecialchars($user['last_login_at'] ?? '') ?>"
                          title="<?= $isActive ? 'Disable Account' : 'Enable Account' ?>"
                          aria-label="<?= $isActive ? 'Disable' : 'Enable' ?> account for <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES, 'UTF-8') ?>">
                          <i data-lucide="<?= $isActive ? 'ban' : 'check-circle' ?>"></i>
                        </button>

                      </div>

                    </td>

                  </tr>

                <?php endforeach; ?>

              <?php endif; ?>

            </tbody>
          </table>
        </div>
      </div>

      <?php if (!$userLoadError && $totalUsers > 0): ?>

        <?php

        $firstUser = $offset + 1;
        $lastUser = min($offset + $usersPerPage, $totalUsers);

        ?>

        <div class="user-pagination-container" id="usersPagination" aria-live="polite">

          <div class="user-pagination-info" id="usersPaginationInfo">

            Showing
            <?= number_format($firstUser) ?>
            to
            <?= number_format($lastUser) ?>
            of
            <?= number_format($totalUsers) ?>
            users

          </div>

          <div class="user-pagination-controls" id="usersPaginationControls" <?= $totalPages <= 1 ? 'hidden' : '' ?>>
          <?php if ($totalPages > 1): ?>

            <?php

            $startPage = max(
              1,
              min(
                $currentPage - 1,
                $totalPages - 2
              )
            );

            $endPage = min(
              $totalPages,
              $startPage + 2
            );

            ?>

              <a href="<?= $currentPage > 1 ? htmlspecialchars(
                buildUsersPageUrl(
                  $currentPage - 1,
                  $userSearch,
                  $userRoleFilter,
                  $userResponsibilityFilter,
                  $userStatusFilter
                )
              ) : '#' ?>" class="user-page-link <?= $currentPage <= 1 ? 'disabled' : '' ?>"
                aria-label="Previous page">

                <i data-lucide="chevron-left"></i>

              </a>

              <?php for ($page = $startPage; $page <= $endPage; $page++): ?>

                <a href="<?= htmlspecialchars(
                  buildUsersPageUrl(
                    $page,
                    $userSearch,
                    $userRoleFilter,
                    $userResponsibilityFilter,
                    $userStatusFilter
                  )
                ) ?>" class="user-page-link <?= $page === $currentPage ? 'active' : '' ?>">

                  <?= $page ?>

                </a>

              <?php endfor; ?>

              <a href="<?= $currentPage < $totalPages ? htmlspecialchars(
                buildUsersPageUrl(
                  $currentPage + 1,
                  $userSearch,
                  $userRoleFilter,
                  $userResponsibilityFilter,
                  $userStatusFilter
                )
              ) : '#' ?>" class="user-page-link <?= $currentPage >= $totalPages ? 'disabled' : '' ?>"
                aria-label="Next page">

                <i data-lucide="chevron-right"></i>

              </a>

          <?php endif; ?>
          </div>

        </div>

      <?php endif; ?>

      <?php if (!$userLoadError): ?>
        <script type="application/json" id="usersDirectoryData"><?= json_encode($clientUsers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
      <?php endif; ?>

    </section>

  </main>

  <!--MODALS-->

  <?php require_once __DIR__ . '/../includes/components/logout_modal.php'; ?>

  <!-- Create User Modal -->
  <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true" aria-labelledby="addUserModalTitle" data-aprism-dialog-focus>
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content modal-content-custom">
        <div class="modal-header-custom">
          <div class="d-flex align-items-center">
            <div class="modal-header-icon-box primary">
              <i data-lucide="user-plus"></i>
            </div>
            <div class="modal-header-title-wrapper">
              <h3 class="modal-title-custom" id="addUserModalTitle" tabindex="-1" data-aprism-dialog-initial-focus>Create User</h3>
              <p class="modal-subtitle-custom">Primary directory entry registration</p>
            </div>
          </div>
          <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal" aria-label="Close create user dialog">
            <i data-lucide="x"></i>
          </button>
        </div>
        <form id="addUserForm" action="<?= APP_URL ?>/actions/users/create_user.php" method="POST" novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
          <div class="modal-body-custom" id="addUserEditStep">

            <p class="users-create-general-issue" id="addUserGeneralIssue" role="alert" <?= $createUserGeneralError === '' ? 'hidden' : '' ?>><?= htmlspecialchars((string) $createUserGeneralError, ENT_QUOTES, 'UTF-8') ?></p>

            <div class="row g-3">

              <div class="col-lg-6 form-group-custom">
                <label class="form-label-custom" for="addUsername">Username</label>
                <input type="text" class="form-control-custom" id="addUsername" name="username"
                  placeholder="e.g. msantos" maxlength="50" required aria-required="true" aria-describedby="addUsernameIssue" value="<?= htmlspecialchars((string) ($createUserRetry['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
                <small class="users-field-issue" id="addUsernameIssue" data-field-issue="username" <?= empty($createUserFieldErrors['username']) ? 'hidden' : '' ?>><?= htmlspecialchars((string) ($createUserFieldErrors['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
              </div>

              <div class="col-lg-6 form-group-custom">
                <label class="form-label-custom" for="addFirstName">First Name</label>
                <input type="text" class="form-control-custom" id="addFirstName" name="first_name"
                  placeholder="e.g. Maria" maxlength="100" required aria-required="true" aria-describedby="addFirstNameIssue" value="<?= htmlspecialchars((string) ($createUserRetry['first_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
                <small class="users-field-issue" id="addFirstNameIssue" data-field-issue="first_name" <?= empty($createUserFieldErrors['first_name']) ? 'hidden' : '' ?>><?= htmlspecialchars((string) ($createUserFieldErrors['first_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
              </div>

              <div class="col-lg-6 form-group-custom">
                <label class="form-label-custom" for="addLastName">Last Name</label>
                <input type="text" class="form-control-custom" id="addLastName" name="last_name"
                  placeholder="e.g. Santos" maxlength="100" required aria-required="true" aria-describedby="addLastNameIssue" value="<?= htmlspecialchars((string) ($createUserRetry['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
                <small class="users-field-issue" id="addLastNameIssue" data-field-issue="last_name" <?= empty($createUserFieldErrors['last_name']) ? 'hidden' : '' ?>><?= htmlspecialchars((string) ($createUserFieldErrors['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
              </div>

              <div class="col-12 form-group-custom">
                <label class="form-label-custom" for="addEmail">Institutional Email</label>
                <input type="email" class="form-control-custom" id="addEmail" name="email"
                  placeholder="e.g. maria.santos@dasmarinas.sti.edu.ph" maxlength="255" required aria-required="true" aria-describedby="addEmailIssue" value="<?= htmlspecialchars((string) ($createUserRetry['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
                <small class="users-field-issue" id="addEmailIssue" data-field-issue="email" <?= empty($createUserFieldErrors['email']) ? 'hidden' : '' ?>><?= htmlspecialchars((string) ($createUserFieldErrors['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
              </div>

              <div class="col-lg-6 form-group-custom">
                <label class="form-label-custom" for="addRole">Role</label>
                <select class="form-control-custom" id="addRole" name="role_id" required aria-required="true" aria-describedby="addRoleIssue">
                  <option value="1" <?= ($createUserRetry['role_id'] ?? '1') === '1' ? 'selected' : '' ?>>Technical Administrator</option>
                  <option value="2" <?= ($createUserRetry['role_id'] ?? '') === '2' ? 'selected' : '' ?>>Academic Head</option>
                  <option value="3" <?= ($createUserRetry['role_id'] ?? '') === '3' ? 'selected' : '' ?>>Teacher</option>
                  <option value="4" <?= ($createUserRetry['role_id'] ?? '') === '4' ? 'selected' : '' ?>>Disciplinary Officer</option>
                </select>
                <small class="users-field-issue" id="addRoleIssue" data-field-issue="role_id" <?= empty($createUserFieldErrors['role_id']) ? 'hidden' : '' ?>><?= htmlspecialchars((string) ($createUserFieldErrors['role_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
              </div>

              <div class="col-lg-6 form-group-custom">
                <label class="form-label-custom" for="addStatus">Status</label>
                <select class="form-control-custom" id="addStatus" name="account_status" required aria-required="true" aria-describedby="addStatusIssue">
                  <option value="Active" <?= ($createUserRetry['account_status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active</option>
                  <option value="Disabled" <?= ($createUserRetry['account_status'] ?? '') === 'Disabled' ? 'selected' : '' ?>>Disabled</option>
                </select>
                <small class="users-field-issue" id="addStatusIssue" data-field-issue="account_status" <?= empty($createUserFieldErrors['account_status']) ? 'hidden' : '' ?>><?= htmlspecialchars((string) ($createUserFieldErrors['account_status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
              </div>
            </div>

            <!-- Responsibilities Checklist inside the create dialog -->
            <div class="form-group-custom mt-4">
              <label class="form-label-custom">Initial Responsibilities</label>
              <div class="responsibilities-checklist" id="addResponsibilitiesContainer">
                <!-- Populated dynamically -->
              </div>
            </div>

          </div>
          <section class="users-create-confirmation" id="addUserConfirmation" aria-labelledby="addUserConfirmationTitle" hidden>
            <h4 id="addUserConfirmationTitle" tabindex="-1">Confirm new user</h4>
            <p class="users-confirm-name" id="confirmUserName"></p>
            <p class="users-confirm-account" id="confirmUserAccount"></p>
            <dl class="users-confirm-details">
              <div><dt>Role</dt><dd id="confirmUserRole"></dd></div>
              <div><dt>Status</dt><dd id="confirmUserStatus"></dd></div>
              <div><dt>Responsibilities</dt><dd id="confirmUserResponsibilities"></dd></div>
            </dl>
          </section>
          <div class="modal-footer-custom" id="addUserEditFooter">
            <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="modal-btn-action" id="reviewCreateUserButton">Create User</button>
        </div>
        <div class="modal-footer-custom users-confirm-footer" id="addUserConfirmFooter" hidden>
          <button type="button" class="modal-btn-dismiss" id="backToUserForm">Back</button>
          <button type="submit" class="modal-btn-action" id="confirmCreateUserButton">Create User</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit User Modal -->
  <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true" aria-labelledby="editUserModalTitle" data-aprism-dialog-focus>
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content modal-content-custom">
        <div class="modal-header-custom">
          <div class="d-flex align-items-center">
            <div class="modal-header-icon-box primary">
              <i data-lucide="edit"></i>
            </div>
            <div class="modal-header-title-wrapper">
              <h3 class="modal-title-custom" id="editUserModalTitle" tabindex="-1" data-aprism-dialog-initial-focus>Edit User</h3>
              <p class="modal-subtitle-custom">Modify core administrative profile</p>
            </div>
          </div>
          <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal" aria-label="Close edit user dialog">
            <i data-lucide="x"></i>
          </button>
        </div>
        <form id="editUserForm" action="<?= APP_URL ?>/actions/users/update_user.php" method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

          <input type="hidden" name="user_id" id="editUserId">
          <div class="modal-body-custom">

            <div class="row g-3">
              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom" for="editEmployeeNumber">Employee Number</label>
                <input type="text" class="form-control-custom font-mono" id="editEmployeeNumber" disabled />
              </div>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom" for="editUsername">Username</label>
                <input type="text" class="form-control-custom" id="editUsername" name="username" placeholder="Username"
                  required />
              </div>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom" for="editFirstName">First Name</label>
                <input type="text" class="form-control-custom" id="editFirstName" name="first_name"
                  placeholder="First Name" required />
              </div>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom" for="editLastName">Last Name</label>
                <input type="text" class="form-control-custom" id="editLastName" name="last_name"
                  placeholder="Last Name" required />
              </div>

              <div class="col-12 form-group-custom">
                <label class="form-label-custom" for="editEmail">Institutional Email</label>
                <input type="email" class="form-control-custom" id="editEmail" name="email"
                  placeholder="Institutional Email" required />
              </div>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom" for="editRole">Role</label>
                <select class="form-control-custom" id="editRole" name="role_id" required>
                  <option value="1">Technical Administrator</option>
                  <option value="2">Academic Head</option>
                  <option value="3">Teacher</option>
                  <option value="4">Disciplinary Officer</option>
                </select>
              </div>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom" for="editStatus">Status</label>
                <select class="form-control-custom" id="editStatus" name="account_status" required>
                  <option value="Active">Active</option>
                  <option value="Disabled">Disabled</option>
                </select>
              </div>
            </div>

            <!-- Deletion warning alert box -->
            <div class="alert-card-warning">
              <i data-lucide="alert-circle"></i>
              <p class="alert-card-warning-text">
                <strong>Platform Data Policy Note:</strong> Deleting institutional personnel records permanently is
                restricted to maintain Student Advisory histories, Intervention Referrals, and system auditing logs. Set
                the status to <strong>Disabled</strong> to prevent future access instead.
              </p>
            </div>

          </div>
          <div class="modal-footer-custom">
            <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="modal-btn-action">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Manage Responsibilities Modal -->
  <div class="modal fade" id="responsibilitiesModal" tabindex="-1" aria-hidden="true" aria-labelledby="responsibilitiesModalTitle" data-aprism-dialog-focus>
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-content-custom">
        <div class="modal-header-custom">
          <div class="d-flex align-items-center">
            <div class="modal-header-icon-box warning">
              <i data-lucide="shield"></i>
            </div>
            <div class="modal-header-title-wrapper">
              <h3 class="modal-title-custom" id="responsibilitiesModalTitle" tabindex="-1" data-aprism-dialog-initial-focus>Manage Responsibilities</h3>
              <p class="modal-subtitle-custom">Current context<?= !empty($activeSchoolYear['school_year']) ? ': ' . htmlspecialchars($activeSchoolYear['school_year']) : '' ?></p>
            </div>
          </div>
          <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal" aria-label="Close responsibilities dialog">
            <i data-lucide="x"></i>
          </button>
        </div>
        <form id="responsibilitiesForm" action="<?= APP_URL ?>/actions/users/update_responsibilities.php" method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" id="respUserId" name="user_id">
          <div class="modal-body-custom responsibility-modal-body">
            <div class="responsibilities-checklist" id="editResponsibilitiesContainer">
              <!-- Populated dynamically -->
            </div>

          </div>
          <div class="modal-footer-custom">
            <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="modal-btn-action">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- View User Modal -->
  <div class="modal fade" id="viewUserModal" tabindex="-1" aria-hidden="true" aria-labelledby="viewUserName" data-aprism-dialog-focus>
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-content-custom">
        <div class="view-profile-header d-flex align-items-center">
          <div class="view-profile-avatar" id="viewUserAvatar">--</div>
          <div class="view-profile-details">
            <h2 class="view-profile-name" id="viewUserName" tabindex="-1" data-aprism-dialog-initial-focus>--</h2>
            <div class="view-profile-sub">
              <i data-lucide="shield-check"></i>
              <span id="viewUserRole">--</span>
            </div>
          </div>
          <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal" aria-label="Close user details dialog"
            style="position: absolute; top: 1.5rem; right: 1.5rem; background: rgba(255,255,255,0.15); color: white;">
            <i data-lucide="x"></i>
          </button>
        </div>
        <div class="modal-body-custom">

          <div class="view-details-grid mb-4">
            <div class="view-detail-card">
              <div class="view-detail-label">Employee Number</div>
              <p class="view-detail-value mono" id="viewUserEmpNum">--</p>
            </div>
            <div class="view-detail-card">
              <div class="view-detail-label">Username</div>
              <p class="view-detail-value" id="viewUserUsername">--</p>
            </div>
            <div class="view-detail-card">
              <div class="view-detail-label">Status</div>
              <div class="status-dot-badge active" id="viewUserStatusBadge">
                <span class="status-dot active"></span>
                <span class="status-text">--</span>
              </div>
            </div>
            <div class="view-detail-card">
              <div class="view-detail-label">Last Login</div>
              <p class="view-detail-value font-mono-custom" id="viewUserLastLogin">--</p>
            </div>
          </div>

          <div class="form-group-custom mb-4">
            <label class="form-label-custom">Institutional Email</label>
            <p class="fw-bold text-dark m-0" style="font-size: 0.85rem;" id="viewUserEmail">--</p>
          </div>

          <div class="form-group-custom">
            <label class="form-label-custom">Assigned Responsibilities</label>
            <div id="viewUserResponsibilities" class="pt-1">
              <!-- Populated dynamically -->
            </div>
          </div>

        </div>
        <div class="modal-footer-custom">
          <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal" style="width: 100%;">Close Profile
            Info</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Reset Password Confirmation Modal -->
  <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true" aria-labelledby="resetPasswordModalTitle" data-aprism-dialog-focus>
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-content-custom">

        <div class="modal-header-custom">

          <div class="d-flex align-items-center">

            <div class="modal-header-icon-box warning">
              <i data-lucide="key"></i>
            </div>

            <div class="modal-header-title-wrapper">
              <h3 class="modal-title-custom" id="resetPasswordModalTitle" tabindex="-1" data-aprism-dialog-initial-focus>
                Reset Password
              </h3>

              <p class="modal-subtitle-custom">
                Generate a new temporary password
              </p>
            </div>

          </div>

          <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal" aria-label="Close reset password dialog">

            <i data-lucide="x"></i>

          </button>

        </div>

        <form id="resetPasswordForm" action="<?= APP_URL ?>/actions/users/reset_password.php" method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

          <input type="hidden" name="user_id" id="resetUserId">

          <div class="modal-body-custom">

            <p style="
              font-size: 0.85rem;
              line-height: 1.6;
              color: #64748b;
            ">

              Are you sure you want to reset the password for
              <strong id="resetUserName">this user</strong>?

            </p>

            <div class="alert-card-warning">

              <i data-lucide="alert-circle"></i>

              <p class="alert-card-warning-text">

                A new temporary password will be generated.
                The user's current password will stop working
                immediately, and they will be required to change
                the temporary password during their next login.

              </p>

            </div>

          </div>

          <div class="modal-footer-custom">

            <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">

              Cancel

            </button>

            <button type="submit" class="modal-btn-action">

              Reset Password

            </button>

          </div>

        </form>

      </div>
    </div>
  </div>

  <!-- Account Status Confirmation Modal -->
  <div class="modal fade" id="accountStatusModal" tabindex="-1" aria-hidden="true" aria-labelledby="accountStatusModalTitle" data-aprism-dialog-focus>
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-content-custom">

        <div class="modal-header-custom">

          <div class="d-flex align-items-center">

            <div class="modal-header-icon-box warning">
              <i data-lucide="shield"></i>
            </div>

            <div class="modal-header-title-wrapper">

              <h3 class="modal-title-custom" id="accountStatusModalTitle" tabindex="-1" data-aprism-dialog-initial-focus>

                Update Account Status

              </h3>

              <p class="modal-subtitle-custom">

                Confirm this administrative action

              </p>

            </div>

          </div>

          <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal" aria-label="Close account status dialog">

            <i data-lucide="x"></i>

          </button>

        </div>

        <form id="accountStatusForm" action="<?= APP_URL ?>/actions/users/toggle_account_status.php" method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

          <input type="hidden" name="user_id" id="accountStatusUserId">

          <div class="modal-body-custom">

            <p id="accountStatusMessage" style="
              font-size:0.85rem;
              line-height:1.6;
              color:#64748b;
            ">

              --

            </p>

            <div class="alert-card-warning">

              <i data-lucide="alert-circle"></i>

              <p class="alert-card-warning-text">

                This action changes the user's ability to sign in.
                Existing academic records and audit logs remain
                preserved.

              </p>

            </div>

          </div>

          <div class="modal-footer-custom">

            <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">

              Cancel

            </button>

            <button type="submit" class="modal-btn-action" id="accountStatusSubmitButton">

              Confirm

            </button>

          </div>

        </form>

      </div>
    </div>
  </div>

  <!-- Temporary Password Security Token Modal -->
  <div class="modal fade" id="tempPasswordModal" tabindex="-1" aria-hidden="true" aria-labelledby="tempPasswordModalTitle" data-aprism-dialog-focus>
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-content-custom" style="padding: 2.25rem 2rem;">
        <div class="modal-body p-0 text-center">
          <div class="logout-icon"
            style="width: 44px; height: 44px; border-radius: 50%; background-color: rgba(255, 199, 44, 0.08); color: #f59e0b; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem;">
            <i data-lucide="key" style="width: 18px; height: 18px;"></i>
          </div>
          <h3 class="modal-title-custom mb-2 text-center" id="tempPasswordModalTitle" tabindex="-1" data-aprism-dialog-initial-focus style="font-size: 1.3rem; font-weight: 700;">Security Code
            Generated</h3>
          <p class="logout-message"
            style="font-size: 0.95rem; color: #64748b; line-height: 1.6; font-weight: 500; margin-bottom: 1rem;">A
            temporary secure authorization code has been generated for:</p>

          <div class="p-3 rounded-4 mb-3" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
            <span id="tempPassUserName" class="d-block text-dark fw-bold" style="font-size: 0.85rem;">--</span>
          </div>

          <span class="form-label-custom" style="margin-bottom: 0.25rem;">Temporary Password</span>
          <div class="temp-password-box">

            <div class="d-flex align-items-center justify-content-between">

              <div class="temp-password-value" id="generatedTempPassword">
                --
              </div>

              <button type="button" id="copyTempPasswordBtn"
                class="btn btn-sm btn-light d-flex align-items-center gap-2" style="
                border-radius:12px;
                font-weight:700;
                padding:0.45rem 0.75rem;
            ">
                <i data-lucide="copy" id="copyPasswordIcon" style="width:16px;height:16px;"></i>

                <span id="copyPasswordText">
                  Copy
                </span>

              </button>

            </div>

          </div>

          <p class="text-muted mb-4" style="font-size: 0.65rem; font-weight: 500; line-height: 1.5;">
            This temporary password is shown only once for security reasons.
            Copy it before closing this dialog. If it is lost, simply perform
            another password reset to generate a new temporary password.
          </p>

          <button id="acknowledgeTempPasswordBtn" class="modal-btn-action w-100"
            data-bs-dismiss="modal">Acknowledged</button>
        </div>
      </div>
    </div>
  </div>

  <!--TOAST NOTIFICATION CONTAINER-->
  <div class="toast-container-custom" id="toastContainer"></div>


  <!-- Frontend logic -->
  <script>

    // Responsibility options definition
    const RESPONSIBILITY_OPTIONS = [
      {
        name: "Adviser",
        desc: "Teacher capability managed by Academic Head assignment."
      }
    ];

    document.addEventListener('DOMContentLoaded', () => {
      // Create responsibilities list UI
      populateResponsibilitiesChecklists();

      // Setup Modals
      const addUserModal = new bootstrap.Modal(document.getElementById('addUserModal'));
      const editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
      const originalEditValues = {
        username: '',
        email: '',
        firstName: '',
        lastName: '',
        roleId: '',
        accountStatus: ''
      };
      const responsibilitiesModal = new bootstrap.Modal(document.getElementById('responsibilitiesModal'));
      const viewUserModal = new bootstrap.Modal(document.getElementById('viewUserModal'));
      // const importUsersModal = new bootstrap.Modal(document.getElementById('importUsersModal'));
      const resetPasswordModal = new bootstrap.Modal(
        document.getElementById('resetPasswordModal')
      );
      const tempPasswordModal = new bootstrap.Modal(document.getElementById('tempPasswordModal'));

      const accountStatusModal =
        new bootstrap.Modal(
          document.getElementById('accountStatusModal')
        );

      // DOM Elements
      const usersTableBody = document.getElementById('usersTableBody');
      const searchFilter = document.getElementById('searchFilter');
      const roleFilter = document.getElementById('roleFilter');
      const responsibilityFilter = document.getElementById('responsibilityFilter');
      const statusFilter = document.getElementById('statusFilter');
      const globalSearch = document.getElementById('globalSearch');

      const addUserForm = document.getElementById('addUserForm');
      const editUserForm = document.getElementById('editUserForm');
      const responsibilitiesForm = document.getElementById('responsibilitiesForm');
      function updateEditSubmitButtonState() {

        const submitButton =
          editUserForm.querySelector(
            'button[type="submit"]'
          );

        const hasChanges =
          document.getElementById('editUsername').value !== originalEditValues.username ||
          document.getElementById('editEmail').value !== originalEditValues.email ||
          document.getElementById('editFirstName').value !== originalEditValues.firstName ||
          document.getElementById('editLastName').value !== originalEditValues.lastName ||
          document.getElementById('editRole').value !== originalEditValues.roleId ||
          document.getElementById('editStatus').value !== originalEditValues.accountStatus;

        submitButton.disabled = !hasChanges;

        submitButton.textContent = 'Save Changes';

        return hasChanges;

      }

      [
        'editUsername',
        'editEmail',
        'editFirstName',
        'editLastName',
        'editRole',
        'editStatus'
      ].forEach(id => {

        document
          .getElementById(id)
          .addEventListener('input', updateEditSubmitButtonState);

        document
          .getElementById(id)
          .addEventListener('change', updateEditSubmitButtonState);

      });

      if (editUserForm) {

        editUserForm.addEventListener('submit', (event) => {

          const submitButton =
            editUserForm.querySelector(
              'button[type="submit"]'
            );

          const hasChanges = updateEditSubmitButtonState();

          if (!hasChanges) {

            event.preventDefault();

            updateEditSubmitButtonState();

            showToast(
              'No Changes',
              'There are no changes to save.',
              'info'
            );

            return;

          }

          const emailField =
            document.getElementById('editEmail');

          emailField.value =
            emailField.value.trim().toLowerCase();

          const email =
            emailField.value;

          const institutionalEmailPattern =
            /^[^\s@]+@dasmarinas\.sti\.edu\.ph$/;

          if (!institutionalEmailPattern.test(email)) {

            event.preventDefault();

            showToast(
              'Invalid Email',
              'Please enter a valid STI College Dasmariñas institutional email address.',
              'warning'
            );

            emailField.focus();

            return;

          }

          submitButton.disabled = true;

          submitButton.textContent =
            'Saving...';

        });

      }

      const btnAddUser = document.getElementById('btnAddUser');
      //    const btnImportUsers = document.getElementById('btnImportUsers');

      const toastContainer = document.getElementById('toastContainer');
      //    const dropzone = document.getElementById('dropzone');
      //    const importFileInput = document.getElementById('importFileInput');

      // Toast feedback helper
      function showToast(title, text, type = 'success') {
        const toast = document.createElement('div');
        toast.className = 'toast-custom';

        let icon = 'check';
        if (type === 'warning') icon = 'alert-circle';
        if (type === 'info') icon = 'info';

        toast.innerHTML = `
          <div class="toast-icon ${type}">
            <i data-lucide="${icon}"></i>
          </div>
          <div class="toast-content">
            <h5 class="toast-title">${title}</h5>
            <p class="toast-text">${text}</p>
          </div>
        `;

        toastContainer.appendChild(toast);
        lucide.createIcons();

        setTimeout(() => toast.classList.add('show'), 10);

        setTimeout(() => {
          toast.classList.remove('show');
          setTimeout(() => toast.remove(), 300);
        }, 4500);
      }

      <?php if ($successMessage): ?>

        showToast(
          'Success',
          <?= json_encode($successMessage) ?>,
          'success'
        );

      <?php endif; ?>

      <?php if (!empty($flash['error'])): ?>
        showToast('Action not completed', <?= json_encode((string) $flash['error'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>, 'warning');
      <?php endif; ?>

      <?php if ($temporaryPassword): ?>

        document.getElementById('generatedTempPassword').textContent =
          <?= json_encode($temporaryPassword) ?>;

        document.getElementById('tempPassUserName').textContent =
          <?= json_encode($temporaryPasswordUser) ?>;

        tempPasswordModal.show();

      <?php endif; ?>

      document
        .getElementById('tempPasswordModal')
        .addEventListener('show.bs.modal', () => {

          document.getElementById('copyPasswordText').textContent =
            'Copy';

          const icon =
            document.getElementById('copyPasswordIcon');

          icon.setAttribute('data-lucide', 'copy');

          document.getElementById('copyTempPasswordBtn').disabled = false;

          lucide.createIcons();

        });

      const copyTempPasswordBtn =
        document.getElementById('copyTempPasswordBtn');

      if (copyTempPasswordBtn) {

        copyTempPasswordBtn.addEventListener('click', async () => {

          const password =
            document.getElementById('generatedTempPassword').textContent.trim();

          try {

            await navigator.clipboard.writeText(password);

            document.getElementById('copyPasswordText').textContent =
              'Copied!';

            const icon =
              document.getElementById('copyPasswordIcon');

            icon.setAttribute('data-lucide', 'check');

            lucide.createIcons();

            copyTempPasswordBtn.disabled = true;

            setTimeout(() => {

              document.getElementById('copyPasswordText').textContent =
                'Copy';

              icon.setAttribute('data-lucide', 'copy');

              lucide.createIcons();

              copyTempPasswordBtn.disabled = false;

            }, 2000);

          } catch (error) {

            console.error('Unable to copy password:', error);

          }

        });

      }

      const resetPasswordForm =
        document.getElementById('resetPasswordForm');

      if (resetPasswordForm) {

        resetPasswordForm.addEventListener('submit', () => {

          const submitButton =
            resetPasswordForm.querySelector(
              'button[type="submit"]'
            );

          submitButton.disabled = true;

          submitButton.textContent =
            'Resetting...';

        });

      }

      // Populate checklists inside dialog modals
      function populateResponsibilitiesChecklists() {
        const renderChecklist = (options, prefix) => {
          return options.map((opt) => `
            <label class="checklist-item" for="${prefix}-${opt.name.toLowerCase().replace(/\s+/g, '-')}">
              <div class="checklist-item-left">
                <span class="checklist-item-title">${opt.name}</span>
                <span class="checklist-item-desc">${opt.desc}</span>
              </div>
              <input type="checkbox" class="checklist-checkbox" name="responsibilities[]" value="${opt.name}" id="${prefix}-${opt.name.toLowerCase().replace(/\s+/g, '-')}" />
            </label>
          `).join('');
        };

      document.getElementById('addResponsibilitiesContainer').innerHTML = renderChecklist(RESPONSIBILITY_OPTIONS, 'add');
      document.getElementById('editResponsibilitiesContainer').innerHTML = renderChecklist(RESPONSIBILITY_OPTIONS, 'edit');

      }

      // Format clean UI labels
      function getRoleLabel(role) {
        if (role === 'Admin') return 'Technical Admin';
        if (role === 'Teacher') return 'Teacher';
        if (role === 'AcademicHead') return 'Academic Admin/Head';
        if (role === 'Disciplinary') return 'Disciplinary Officer';
        return role;
      }

      const userFiltersForm = document.getElementById('userFiltersForm');
      const clearUserSearch = document.getElementById('clearUserSearch');
      const suggestionsList = document.getElementById('userSearchSuggestions');
      const directoryDataNode = document.getElementById('usersDirectoryData');
      const usersPagination = document.getElementById('usersPagination');
      const usersPaginationInfo = document.getElementById('usersPaginationInfo');
      const usersPaginationControls = document.getElementById('usersPaginationControls');
      const pageSize = 7;
      let directoryUsers = [];
      let activeSuggestionIndex = -1;
      let selectedSuggestionId = null;
      let suggestionsSuppressed = true;
      let visibleSuggestions = [];
      let currentDirectoryPage = <?= (int) $currentPage ?>;

      try {
        directoryUsers = directoryDataNode ? JSON.parse(directoryDataNode.textContent) : [];
      } catch (error) {
        directoryUsers = [];
      }

      const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
      }[character]));

      function rowActionButton(user, className, icon, title, ariaLabel) {
        const responsibilities = escapeHtml(JSON.stringify(user.responsibilities || []));
        const lastLogin = escapeHtml(user.last_login_at || '');
        return `<button type="button" class="action-row-btn ${className}" data-user-id="${escapeHtml(user.user_id)}" data-responsibilities="${responsibilities}" data-employee-number="${escapeHtml(user.employee_number)}" data-username="${escapeHtml(user.username)}" data-first-name="${escapeHtml(user.first_name)}" data-last-name="${escapeHtml(user.last_name)}" data-email="${escapeHtml(user.email)}" data-role-id="${escapeHtml(user.role_id)}" data-role="${escapeHtml(user.role_name)}" data-status="${escapeHtml(user.account_status)}" data-last-login="${lastLogin}" title="${title}" aria-label="${ariaLabel}"><i data-lucide="${icon}" aria-hidden="true"></i></button>`;
      }

      function renderUserRow(user) {
        const fullName = `${user.first_name} ${user.last_name}`.trim();
        const responsibilities = user.responsibilities || [];
        const active = user.account_status === 'Active';
        const actions = [
          rowActionButton(user, 'btn-view', 'eye', 'View User', `View user ${fullName}`),
          rowActionButton(user, 'btn-edit', 'edit', 'Edit User', `Edit user ${fullName}`)
        ];
        if (user.role_name === 'Teacher') actions.push(rowActionButton(user, 'btn-responsibilities', 'shield', 'Manage Responsibilities', `Manage responsibilities for ${fullName}`));
        actions.push(rowActionButton(user, 'btn-reset', 'key', 'Reset Password', `Reset password for ${fullName}`));
        actions.push(rowActionButton(user, active ? 'btn-toggle-disable' : 'btn-toggle-enable', active ? 'ban' : 'check-circle', active ? 'Disable Account' : 'Enable Account', `${active ? 'Disable' : 'Enable'} account for ${fullName}`));

        return `<tr>
          <td><strong>${escapeHtml(fullName)}</strong><br><small class="text-muted">${escapeHtml(user.employee_number)}</small></td>
          <td>${escapeHtml(user.email)}</td><td>${escapeHtml(user.role_name)}</td>
          <td>${responsibilities.length ? responsibilities.map((item) => `<span class="badge-responsibility">${escapeHtml(item)}</span>`).join(' ') : '--'}</td>
          <td>${escapeHtml(user.account_status)}</td><td>${user.last_login_at ? escapeHtml(user.last_login_at) : 'Never'}</td>
          <td><div class="action-btn-container">${actions.join('')}</div></td>
        </tr>`;
      }

      function setPageUrl() {
        const url = new URL(window.location.href);
        const filters = { search: searchFilter.value.trim(), role: roleFilter.value, responsibility: responsibilityFilter.value, status: statusFilter.value };
        Object.entries(filters).forEach(([key, value]) => {
          if (value && value !== 'All') url.searchParams.set(key, value);
          else url.searchParams.delete(key);
        });
        if (currentDirectoryPage > 1) url.searchParams.set('page', String(currentDirectoryPage));
        else url.searchParams.delete('page');
        url.searchParams.delete('export');
        window.history.replaceState({}, '', url);
        document.querySelectorAll('.aprism-export-csv, .aprism-export-xlsx').forEach((link) => {
          const exportUrl = new URL(window.location.href);
          exportUrl.searchParams.set('export', link.classList.contains('aprism-export-csv') ? 'csv' : 'xlsx');
          link.href = exportUrl.toString();
        });
      }

      function closeSuggestions() {
        suggestionsSuppressed = true;
        suggestionsList.hidden = true;
        searchFilter.setAttribute('aria-expanded', 'false');
        searchFilter.removeAttribute('aria-activedescendant');
        activeSuggestionIndex = -1;
      }

      function renderSuggestions(matches) {
        const query = searchFilter.value.trim().toLocaleLowerCase();
        if (!query || suggestionsSuppressed) {
          closeSuggestions();
          return;
        }
        visibleSuggestions = matches.slice().sort((a, b) => {
          const aName = `${a.first_name} ${a.last_name}`.toLocaleLowerCase();
          const bName = `${b.first_name} ${b.last_name}`.toLocaleLowerCase();
          const aStarts = aName.startsWith(query) || String(a.email).toLocaleLowerCase().startsWith(query);
          const bStarts = bName.startsWith(query) || String(b.email).toLocaleLowerCase().startsWith(query);
          return Number(bStarts) - Number(aStarts);
        }).slice(0, 5);
        if (!visibleSuggestions.length) {
          closeSuggestions();
          return;
        }
        suggestionsList.innerHTML = visibleSuggestions.map((user, index) => {
          const name = `${user.first_name} ${user.last_name}`.trim();
          return `<div class="users-search-suggestion" id="userSuggestion${index}" role="option" aria-selected="${index === activeSuggestionIndex}" data-suggestion-index="${index}"><span class="users-suggestion-name">${escapeHtml(name)}</span><span class="users-suggestion-email">${escapeHtml(user.email)}</span></div>`;
        }).join('');
        suggestionsList.hidden = false;
        searchFilter.setAttribute('aria-expanded', 'true');
        if (activeSuggestionIndex >= visibleSuggestions.length) activeSuggestionIndex = visibleSuggestions.length - 1;
        updateActiveSuggestion();
      }

      function updateActiveSuggestion() {
        suggestionsList.querySelectorAll('[role="option"]').forEach((option, index) => {
          option.setAttribute('aria-selected', index === activeSuggestionIndex ? 'true' : 'false');
          if (index === activeSuggestionIndex) searchFilter.setAttribute('aria-activedescendant', option.id);
        });
        if (activeSuggestionIndex < 0) searchFilter.removeAttribute('aria-activedescendant');
      }

      function filterDirectory() {
        if (!directoryDataNode) return;
        const query = searchFilter.value.trim().toLocaleLowerCase();
        const role = roleFilter.value;
        const responsibility = responsibilityFilter.value;
        const status = statusFilter.value;
        const matches = directoryUsers.filter((user) => {
          if (selectedSuggestionId !== null && String(user.user_id) !== String(selectedSuggestionId)) return false;
          const name = `${user.first_name} ${user.last_name}`.toLocaleLowerCase();
          const email = String(user.email || '').toLocaleLowerCase();
          const queryMatches = !query || name.includes(query) || email.includes(query);
          const roleMatches = role === 'All' || user.role_name === role;
          const responsibilityMatches = responsibility === 'All' || (user.responsibilities || []).includes(responsibility);
          const statusMatches = status === 'All' || user.account_status === status;
          return queryMatches && roleMatches && responsibilityMatches && statusMatches;
        });
        const totalPages = Math.max(1, Math.ceil(matches.length / pageSize));
        currentDirectoryPage = Math.min(Math.max(currentDirectoryPage, 1), totalPages);
        const start = (currentDirectoryPage - 1) * pageSize;
        const pageUsers = matches.slice(start, start + pageSize);
        const printBody = document.getElementById('usersPrintTableBody');
        if (printBody) {
          printBody.innerHTML = matches.length
            ? matches.map((user) => `<tr><td>${escapeHtml(`${user.first_name} ${user.last_name}`.trim())}</td><td>${escapeHtml(user.employee_number || '—')}</td><td>${escapeHtml(user.email || '—')}</td><td>${escapeHtml(user.role_name || '—')}</td><td>${escapeHtml((user.responsibilities || []).join(', ') || '—')}</td><td>${escapeHtml(user.account_status || '—')}</td><td>${escapeHtml(user.last_login_at || 'Never')}</td></tr>`).join('')
            : '<tr><td colspan="7">No users match the current filters.</td></tr>';
        }
        const printCount = document.getElementById('usersPrintCount');
        if (printCount) printCount.textContent = String(matches.length);
        const filterSummary = document.getElementById('usersPrintFilterSummary');
        if (filterSummary) filterSummary.textContent = [
          query ? `Search: ${searchFilter.value.trim()}` : '',
          role !== 'All' ? `Role: ${role}` : '',
          responsibility !== 'All' ? `Responsibility: ${responsibility}` : '',
          status !== 'All' ? `Status: ${status}` : ''
        ].filter(Boolean).join(' · ') || 'All Users';
        usersTableBody.innerHTML = pageUsers.length
          ? pageUsers.map(renderUserRow).join('')
          : `<tr><td colspan="7"><div class="empty-state" role="status"><i data-lucide="file-search" aria-hidden="true"></i><p class="mb-1 fw-bold">${directoryUsers.length ? 'No matching users.' : 'No users available.'}</p><small>${directoryUsers.length ? 'Try adjusting or clearing the current filters.' : 'Use the Add User button to create the first account.'}</small></div></td></tr>`;
        lucide.createIcons();
        if (usersPaginationInfo) usersPaginationInfo.textContent = matches.length
          ? `Showing ${start + 1} to ${Math.min(start + pageUsers.length, matches.length)} of ${matches.length} users`
          : 'No users to show';
        if (usersPaginationControls) {
          usersPaginationControls.hidden = totalPages <= 1 || matches.length === 0;
          if (totalPages > 1 && matches.length) {
            const pages = [];
            pages.push(`<button type="button" class="user-page-link ${currentDirectoryPage <= 1 ? 'disabled' : ''}" data-page="${currentDirectoryPage - 1}" aria-label="Previous page" ${currentDirectoryPage <= 1 ? 'disabled' : ''}><i data-lucide="chevron-left" aria-hidden="true"></i></button>`);
            const firstPage = Math.max(1, Math.min(currentDirectoryPage - 1, totalPages - 2));
            for (let page = firstPage; page <= Math.min(totalPages, firstPage + 2); page += 1) pages.push(`<button type="button" class="user-page-link ${page === currentDirectoryPage ? 'active' : ''}" data-page="${page}" aria-current="${page === currentDirectoryPage ? 'page' : 'false'}">${page}</button>`);
            pages.push(`<button type="button" class="user-page-link ${currentDirectoryPage >= totalPages ? 'disabled' : ''}" data-page="${currentDirectoryPage + 1}" aria-label="Next page" ${currentDirectoryPage >= totalPages ? 'disabled' : ''}><i data-lucide="chevron-right" aria-hidden="true"></i></button>`);
            usersPaginationControls.innerHTML = pages.join('');
            lucide.createIcons();
          }
        }
        clearUserSearch.hidden = searchFilter.value.length === 0;
        renderSuggestions(matches);
        setPageUrl();
      }

      [roleFilter, responsibilityFilter, statusFilter].forEach((filter) => filter.addEventListener('change', () => {
        if (!directoryDataNode) {
          userFiltersForm.submit();
          return;
        }
        currentDirectoryPage = 1;
        filterDirectory();
      }));
      searchFilter.addEventListener('input', () => {
        currentDirectoryPage = 1;
        selectedSuggestionId = null;
        suggestionsSuppressed = false;
        activeSuggestionIndex = -1;
        filterDirectory();
      });
      userFiltersForm.addEventListener('submit', (event) => {
        if (directoryDataNode) event.preventDefault();
      });
      clearUserSearch.addEventListener('click', () => {
        searchFilter.value = '';
        selectedSuggestionId = null;
        closeSuggestions();
        currentDirectoryPage = 1;
        filterDirectory();
        searchFilter.focus();
      });
      searchFilter.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          closeSuggestions();
          return;
        }
        if (suggestionsList.hidden) return;
        if (event.key === 'ArrowDown') {
          event.preventDefault();
          activeSuggestionIndex = (activeSuggestionIndex + 1) % visibleSuggestions.length;
          updateActiveSuggestion();
          suggestionsList.querySelector(`#userSuggestion${activeSuggestionIndex}`)?.scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'ArrowUp') {
          event.preventDefault();
          activeSuggestionIndex = activeSuggestionIndex <= 0 ? visibleSuggestions.length - 1 : activeSuggestionIndex - 1;
          updateActiveSuggestion();
          suggestionsList.querySelector(`#userSuggestion${activeSuggestionIndex}`)?.scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && activeSuggestionIndex >= 0) {
          event.preventDefault();
          selectSuggestion(visibleSuggestions[activeSuggestionIndex]);
        }
      });
      function selectSuggestion(user) {
        selectedSuggestionId = user.user_id;
        searchFilter.value = `${user.first_name} ${user.last_name}`.trim();
        closeSuggestions();
        currentDirectoryPage = 1;
        filterDirectory();
      }
      suggestionsList.addEventListener('click', (event) => {
        const option = event.target.closest('[data-suggestion-index]');
        if (option) selectSuggestion(visibleSuggestions[Number(option.dataset.suggestionIndex)]);
      });
      document.addEventListener('click', (event) => {
        if (!event.target.closest('.toolbar-search')) closeSuggestions();
      });
      usersPaginationControls?.addEventListener('click', (event) => {
        const pageButton = event.target.closest('[data-page]');
        if (!pageButton || pageButton.disabled) return;
        currentDirectoryPage = Number(pageButton.dataset.page);
        filterDirectory();
      });
      filterDirectory();

      const addUserEditStep = document.getElementById('addUserEditStep');
      const addUserConfirmation = document.getElementById('addUserConfirmation');
      const addUserEditFooter = document.getElementById('addUserEditFooter');
      const addUserConfirmFooter = document.getElementById('addUserConfirmFooter');
      const addUserRole = document.getElementById('addRole');
      const addUserStatus = document.getElementById('addStatus');
      let createUserConfirmed = false;
      const createFields = [
        { id: 'addUsername', key: 'username', validate: (value) => !value ? 'Enter a username.' : value.length > 50 ? 'Use 50 characters or fewer.' : '' },
        { id: 'addFirstName', key: 'first_name', validate: (value) => !value ? 'Enter a first name.' : value.length > 100 ? 'Use 100 characters or fewer.' : !/\p{L}/u.test(value) ? 'Enter a name that includes at least one letter.' : '' },
        { id: 'addLastName', key: 'last_name', validate: (value) => !value ? 'Enter a last name.' : value.length > 100 ? 'Use 100 characters or fewer.' : !/\p{L}/u.test(value) ? 'Enter a name that includes at least one letter.' : '' },
        { id: 'addEmail', key: 'email', validate: (value, field) => !value ? 'Enter an institutional email.' : !field.validity.valid ? 'Enter a valid email address.' : !value.toLowerCase().endsWith('@dasmarinas.sti.edu.ph') ? 'Use your STI College Dasmariñas institutional email.' : value.length > 255 ? 'Use 255 characters or fewer.' : '' },
        { id: 'addRole', key: 'role_id', validate: (value) => ['1', '2', '3', '4'].includes(value) ? '' : 'Choose a valid APRISM role.' },
        { id: 'addStatus', key: 'account_status', validate: (value) => ['Active', 'Disabled'].includes(value) ? '' : 'Choose a valid account status.' }
      ];
      function validateCreateField(definition) {
        const field = document.getElementById(definition.id);
        const issue = document.querySelector(`[data-field-issue="${definition.key}"]`);
        const message = definition.validate(field.value.trim(), field);
        issue.textContent = message;
        issue.hidden = !message;
        field.setAttribute('aria-invalid', message ? 'true' : 'false');
        field.classList.toggle('is-invalid', Boolean(message));
        return !message;
      }
      createFields.forEach((definition) => {
        const field = document.getElementById(definition.id);
        field.addEventListener('input', () => {
          const issue = document.querySelector(`[data-field-issue="${definition.key}"]`);
          if (!issue.hidden || field.dataset.validationTouched === 'true') validateCreateField(definition);
        });
        field.addEventListener('blur', () => {
          field.dataset.validationTouched = 'true';
          validateCreateField(definition);
        });
      });
      createFields.filter((definition) => definition.id === 'addRole' || definition.id === 'addStatus').forEach((definition) => {
        document.getElementById(definition.id).addEventListener('change', () => {
          if (document.getElementById(definition.id).dataset.validationTouched === 'true') validateCreateField(definition);
        });
      });
      document.querySelectorAll('#addUserEditStep [data-field-issue]:not([hidden])').forEach((issue) => {
        const input = issue.parentElement.querySelector('input, select');
        if (input) {
          input.setAttribute('aria-invalid', 'true');
          input.classList.add('is-invalid');
        }
      });
      function updateResponsibilityAvailability() {
        const canAssign = addUserRole.value === '3';
        document.querySelectorAll('#addResponsibilitiesContainer .checklist-checkbox').forEach((checkbox) => {
          checkbox.disabled = !canAssign;
          if (!canAssign) checkbox.checked = false;
        });
      }
      addUserRole.addEventListener('change', updateResponsibilityAvailability);
      function populateConfirmation() {
        const firstName = document.getElementById('addFirstName').value.trim();
        const lastName = document.getElementById('addLastName').value.trim();
        const username = document.getElementById('addUsername').value.trim();
        const email = document.getElementById('addEmail').value.trim().toLowerCase();
        document.getElementById('confirmUserName').textContent = `${firstName} ${lastName}`;
        document.getElementById('confirmUserAccount').textContent = `${username} · ${email}`;
        document.getElementById('confirmUserRole').textContent = addUserRole.selectedOptions[0]?.textContent.trim() || '';
        document.getElementById('confirmUserStatus').textContent = addUserStatus.value;
        const selectedResponsibilities = Array.from(document.querySelectorAll('#addResponsibilitiesContainer .checklist-checkbox:checked')).map((checkbox) => checkbox.value);
        document.getElementById('confirmUserResponsibilities').textContent = selectedResponsibilities.join(', ') || 'None';
      }
      function showCreateReview() {
        populateConfirmation();
        addUserEditStep.hidden = true;
        addUserEditFooter.hidden = true;
        addUserConfirmation.hidden = false;
        addUserConfirmFooter.hidden = false;
        document.getElementById('addUserConfirmationTitle').focus();
      }
      function returnToCreateForm() {
        addUserEditStep.hidden = false;
        addUserEditFooter.hidden = false;
        addUserConfirmation.hidden = true;
        addUserConfirmFooter.hidden = true;
        createUserConfirmed = false;
        document.getElementById('reviewCreateUserButton').focus();
      }
      addUserForm.addEventListener('submit', function (event) {
        if (createUserConfirmed) {
          const emailField = document.getElementById('addEmail');
          emailField.value = emailField.value.trim().toLowerCase();
          const finalButton = document.getElementById('confirmCreateUserButton');
          finalButton.disabled = true;
          finalButton.textContent = 'Creating…';
          return;
        }
        event.preventDefault();
        let valid = true;
        createFields.forEach((definition) => { if (!validateCreateField(definition)) valid = false; });
        document.getElementById('addUsername').dataset.validationTouched = 'true';
        document.getElementById('addFirstName').dataset.validationTouched = 'true';
        document.getElementById('addLastName').dataset.validationTouched = 'true';
        document.getElementById('addEmail').dataset.validationTouched = 'true';
        document.getElementById('addRole').dataset.validationTouched = 'true';
        document.getElementById('addStatus').dataset.validationTouched = 'true';
        if (!valid) {
          addUserEditStep.querySelector('[aria-invalid="true"]')?.focus();
          return;
        }
        document.getElementById('addUserGeneralIssue').hidden = true;
        createUserConfirmed = true;
        showCreateReview();
      });
      document.getElementById('backToUserForm').addEventListener('click', returnToCreateForm);
      btnAddUser.addEventListener('click', () => {
        addUserForm.reset();
        createUserConfirmed = false;
        createFields.forEach((definition) => {
          const field = document.getElementById(definition.id);
          const issue = document.querySelector(`[data-field-issue="${definition.key}"]`);
          field.dataset.validationTouched = 'false';
          field.removeAttribute('aria-invalid');
          field.classList.remove('is-invalid');
          issue.textContent = '';
          issue.hidden = true;
        });
        document.getElementById('addUserGeneralIssue').hidden = true;
        document.querySelectorAll('#addResponsibilitiesContainer .checklist-checkbox').forEach((checkbox) => { checkbox.checked = false; });
        addUserEditStep.hidden = false;
        addUserEditFooter.hidden = false;
        addUserConfirmation.hidden = true;
        addUserConfirmFooter.hidden = true;
        updateResponsibilityAvailability();
        addUserModal.show();
      });

      document.querySelectorAll('#addResponsibilitiesContainer .checklist-checkbox').forEach((checkbox) => {
        if ((<?= json_encode(array_values($createUserRetry['responsibilities'] ?? []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>).includes(checkbox.value)) checkbox.checked = true;
      });
      updateResponsibilityAvailability();
      <?php if (is_array($createUserRetry)): ?>
        addUserModal.show();
      <?php endif; ?>

      // Trigger Import Roster dialog
      /*   btnImportUsers.addEventListener('click', () => {
           importUsersModal.show();
         });
   
         // Drag and drop import directory interactions
         if (dropzone) {
           dropzone.addEventListener('click', () => {
             importFileInput.click();
           });
   
           importFileInput.addEventListener('change', () => {
             if (importFileInput.files.length > 0) {
               showToast('Database Session Required', 'Spreadsheet enrollment parses values into active server databases. Establish sessions first.', 'warning');
               importUsersModal.hide();
             }
           });
   
           dropzone.addEventListener('dragover', (e) => {
             e.preventDefault();
             dropzone.classList.add('dragover');
           });
   
           dropzone.addEventListener('dragleave', () => {
             dropzone.classList.remove('dragover');
           });
   
           dropzone.addEventListener('drop', (e) => {
             e.preventDefault();
             dropzone.classList.remove('dragover');
             if (e.dataTransfer.files.length > 0) {
               showToast('Database Session Required', 'Spreadsheet enrollment parses values into active server databases. Establish sessions first.', 'warning');
               importUsersModal.hide();
             }
           });
         }
           */

      // Actions delegation inside directory grid
      document.addEventListener('click', (e) => {
        const btn = e.target.closest('.action-row-btn');
        if (!btn) return;

        // 1. View User details
        if (btn.classList.contains('btn-view')) {

          const firstName = btn.dataset.firstName;
          const lastName = btn.dataset.lastName;

          const initials =
            (firstName.charAt(0) + lastName.charAt(0)).toUpperCase();

          document.getElementById("viewUserAvatar").innerText = initials;

          document.getElementById("viewUserName").innerText =
            firstName + " " + lastName;

          document.getElementById("viewUserRole").innerText =
            btn.dataset.role;

          document.getElementById("viewUserEmpNum").innerText =
            btn.dataset.employeeNumber;

          document.getElementById("viewUserUsername").innerText =
            btn.dataset.username;

          document.getElementById("viewUserEmail").innerText =
            btn.dataset.email;

          document.getElementById("viewUserLastLogin").innerText =
            btn.dataset.lastLogin || "--";

          const badgeContainer =
            document.getElementById("viewUserStatusBadge");

          const status =
            btn.dataset.status;

          const statusClass =
            status === "Active" ? "active" : "disabled";

          badgeContainer.className =
            `status-dot-badge ${statusClass}`;

          badgeContainer.querySelector(".status-dot").className =
            `status-dot ${statusClass}`;

          badgeContainer.querySelector(".status-text").innerText =
            status;

          let responsibilities = [];

          try {

            responsibilities = JSON.parse(
              btn.dataset.responsibilities || "[]"
            );

          } catch (error) {

            responsibilities = [];

          }

          const responsibilitiesContainer =
            document.getElementById("viewUserResponsibilities");

          if (responsibilities.length === 0) {

            responsibilitiesContainer.innerHTML =
              '<span class="text-muted italic" style="font-size:0.7rem;">--</span>';

          } else {

            responsibilitiesContainer.innerHTML =
              responsibilities
                .map(function (responsibility) {

                  return `
                    <span class="badge-responsibility">
                        ${responsibility}
                    </span>
                `;

                })
                .join("");

          }

          viewUserModal.show();
        }

        // 2. Edit User details
        else if (btn.classList.contains('btn-edit')) {
          document.getElementById('editUserId').value =
            btn.dataset.userId;

          document.getElementById('editEmployeeNumber').value =
            btn.dataset.employeeNumber;

          document.getElementById('editUsername').value =
            btn.dataset.username;

          document.getElementById('editFirstName').value =
            btn.dataset.firstName;

          document.getElementById('editLastName').value =
            btn.dataset.lastName;

          document.getElementById('editEmail').value =
            btn.dataset.email;

          // Populate the Role dropdown using the database role_id.
          document.getElementById("editRole").value =
            btn.dataset.roleId;

          document.getElementById('editStatus').value =
            btn.dataset.status;

          originalEditValues.username =
            btn.dataset.username;

          originalEditValues.email =
            btn.dataset.email;

          originalEditValues.firstName =
            btn.dataset.firstName;

          originalEditValues.lastName =
            btn.dataset.lastName;

          originalEditValues.roleId =
            btn.dataset.roleId;

          originalEditValues.accountStatus =
            btn.dataset.status;

          updateEditSubmitButtonState();

          editUserModal.show();

        }

        // 3. Manage responsibilities
        else if (btn.classList.contains('btn-responsibilities')) {

          document.getElementById("respUserId").value =
            btn.dataset.userId;

          document.querySelectorAll(
            "#editResponsibilitiesContainer .checklist-checkbox"
          ).forEach(cb => {
            cb.checked = false;
          });

          let responsibilities = [];

          try {

            responsibilities = JSON.parse(
              btn.dataset.responsibilities || "[]"
            );

          } catch (error) {

            responsibilities = [];

          }

          responsibilities.forEach(function (responsibility) {

            const checkbox = document.querySelector(
              '#editResponsibilitiesContainer input[value="' +
              responsibility +
              '"]'
            );

            if (checkbox) {

              checkbox.checked = true;

            }

          });

          responsibilitiesModal.show();

        }

        // 4. Reset security keys / Password
        else if (btn.classList.contains('btn-reset')) {

          const fullName =
            `${btn.dataset.firstName} ${btn.dataset.lastName}`;

          document.getElementById('resetUserId').value =
            btn.dataset.userId;

          document.getElementById('resetUserName').textContent =
            fullName;

          resetPasswordModal.show();

        }

        // 5. Account Activation / Deactivation
        else if (
          btn.classList.contains("btn-toggle-disable") ||
          btn.classList.contains("btn-toggle-enable")
        ) {

          const isDisable =
            btn.classList.contains("btn-toggle-disable");

          const fullName =
            `${btn.dataset.firstName} ${btn.dataset.lastName}`;

          document.getElementById(
            "accountStatusUserId"
          ).value = btn.dataset.userId;

          document.getElementById(
            "accountStatusModalTitle"
          ).textContent =
            isDisable
              ? "Disable Account"
              : "Enable Account";

          document.getElementById(
            "accountStatusMessage"
          ).innerHTML =
            isDisable
              ? `Are you sure you want to <strong>disable</strong> <strong>${fullName}</strong>?`
              : `Are you sure you want to <strong>enable</strong> <strong>${fullName}</strong>?`;

          const submitButton =
            document.getElementById(
              "accountStatusSubmitButton"
            );

          submitButton.textContent =
            isDisable
              ? "Disable Account"
              : "Enable Account";

          submitButton.classList.remove(
            "btn-danger-action"
          );

          if (isDisable) {

            submitButton.classList.add(
              "btn-danger-action"
            );

          }

          accountStatusModal.show();

        }

      });

    });
  </script>

  <?php
  require_once __DIR__ .
    '/../includes/components/footer.php';
  ?>
</body>

</html>
