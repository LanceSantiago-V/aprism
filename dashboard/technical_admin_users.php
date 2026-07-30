<?php

require_once __DIR__ . '/../auth/role_helper.php';


$allowedRoles = [
  ROLE_TECHNICAL_ADMINISTRATOR
];

require_once __DIR__ . '/../includes/helper/flash_message.php';
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../config/database.php';

$activePage = 'users';

try {

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
        ORDER BY
            u.last_name ASC,
            u.first_name ASC
    ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute();

  $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $responsibilityStmt = $pdo->query("
    SELECT
        user_id,
        permission_name
    FROM user_permissions
    ORDER BY
        user_id,
        permission_name
");

  $responsibilityMap = [];

  while ($row = $responsibilityStmt->fetch(PDO::FETCH_ASSOC)) {

    $responsibilityMap[$row['user_id']][] =
      $row['permission_name'];

  }

} catch (PDOException $e) {

  $users = [];

}

$fullName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

$initials =
  strtoupper(substr($_SESSION['first_name'], 0, 1)) .
  strtoupper(substr($_SESSION['last_name'], 0, 1));

$successMessage = $flash['success'] ?? null;

$temporaryPassword = $_SESSION['temporary_password'] ?? null;

$temporaryPasswordUser =
  $_SESSION['temporary_password_user'] ?? null;

unset($_SESSION['temporary_password']);
unset($_SESSION['temporary_password_user']);


$pageTitle = 'Manage Users';
$pageCss = 'technical-admin-users.css';

?>

<!DOCTYPE html>
<html lang="en">

<?php
require_once __DIR__
    . '/../includes/components/technical_admin_head.php';
?>

<body>

  <!-- Technical Administrator Sidebar -->
  <?php require __DIR__ . '/../includes/components/technical_admin_sidebar.php'; ?>

  <!-- Main Content Wrapper -->
  <main class="main-content">

    <!-- Top Navigation Bar -->
    <header class="top-navbar">
      <div class="navbar-left">
        <button class="mobile-menu-toggle" id="menuToggle">
          <i data-lucide="menu"></i>
        </button>
        <button class="back-btn" id="sidebarToggle">
          <i data-lucide="chevron-left" id="sidebarToggleIcon"></i>
        </button>
        <div class="search-wrapper">
          <i data-lucide="search" class="search-icon"></i>
          <input type="text" class="navbar-search" placeholder="Search Users" id="globalSearch" />
        </div>
      </div>

      <div class="navbar-right">
        <div class="active-term-badge">
          <i data-lucide="calendar"></i>
          <span>Academic Term: --</span>
        </div>
        <div class="notification-bell">
          <i data-lucide="bell"></i>
          <span class="notification-dot"></span>
        </div>
        <div class="user-profile">
          <div class="profile-text">
            <h4 class="profile-name"><?= htmlspecialchars($fullName) ?></h4>
            <p class="profile-role">Technical Administrator</p>
          </div>
          <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
        </div>
      </div>
    </header>

    <!-- Page Header Title & Core Actions -->
    <section class="page-header">
      <div>
        <h1 class="page-title">Manage Users</h1>
        <div class="page-description-row">
          <span class=" status-pulse"></span>
          <p class="page-description">Active User Database</p>
        </div>
      </div>
      <div class="header-actions-group">
        <!-- Primary Action: Create User -->
        <button class="header-action-btn btn-primary-action" id="btnAddUser" title="Create User">
          <i data-lucide="user-plus"></i>
        </button>
        <!-- Secondary Action: Import Users 
        <button class="header-action-btn btn-import-action" id="btnImportUsers" title="Import Users">
          <i data-lucide="upload"></i>
        </button> -->
        <!-- Secondary Action: Export Users -->
        <button class="header-action-btn btn-export-action" id="btnExportUsers" title="Export Users">
          <i data-lucide="file-spreadsheet"></i>
        </button>
      </div>
    </section>

    <!-- Main Content Panel -->
    <section class="section-card">

      <!-- Table Search & Filter Controls -->
      <div class="filter-grid">
        <div class="filter-search-wrapper">
          <i data-lucide="search" class="filter-search-icon"></i>
          <input type="text" class="filter-search-input" id="searchFilter" placeholder="Search by name or email..." />
        </div>

        <select class="filter-select" id="roleFilter">
          <option value="All">All Main Roles</option>
          <option value="Technical Administrator">
            Technical Administrator
          </option>

          <option value="Academic Head">
            Academic Head
          </option>

          <option value="Teacher">
            Teacher
          </option>

          <option value="Disciplinary Officer">
            Disciplinary Officer
          </option>
        </select>

        <select class="filter-select" id="responsibilityFilter">
          <option value="All">All Responsibilities</option>
          <option value="Adviser">Adviser</option>
          <option value="Program Head">Program Head</option>
        </select>

        <select class="filter-select" id="statusFilter">
          <option value="All">All Statuses</option>
          <option value="Active">Active Only</option>
          <option value="Disabled">Disabled Only</option>
        </select>
      </div>

      <!-- Users Directory Table Grid -->
      <div class="user-table-container">
        <div class="table-responsive">
          <table class="table user-table align-middle">
            <thead>
              <tr>
                <th class="col-name" style="width: 25%;">Name</th>
                <th style="width: 20%;">Institutional Email</th>
                <th style="width: 15%;">Role</th>
                <th style="width: 20%;">Responsibilities</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 10%;">Last Login</th>
                <th style="width: 10%;">Actions</th>
              </tr>
            </thead>
            <tbody id="usersTableBody">

              <?php if (empty($users)): ?>

                <tr>
                  <td colspan="7">
                    <div class="empty-state">
                      <i data-lucide="users"></i>
                      <p class="mb-1 fw-bold">No users available.</p>
                      <small>
                        No records found. Please use the
                        "Add User" button to create the first account.
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
                          data-last-login="<?= htmlspecialchars($user['last_login_at'] ?? '') ?>" title="View User">

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
                          data-last-login="<?= htmlspecialchars($user['last_login_at'] ?? '') ?>" title="Edit User">

                          <i data-lucide="edit"></i>

                        </button>

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
                          title="Manage Responsibilities">

                          <i data-lucide="shield"></i>

                        </button>

                        <button type="button" class="action-row-btn btn-reset" data-user-id="<?= (int) $user['user_id'] ?>"
                          data-employee-number="<?= htmlspecialchars($user['employee_number']) ?>"
                          data-username="<?= htmlspecialchars($user['username']) ?>"
                          data-first-name="<?= htmlspecialchars($user['first_name']) ?>"
                          data-last-name="<?= htmlspecialchars($user['last_name']) ?>"
                          data-email="<?= htmlspecialchars($user['email']) ?>" data-role-id="<?= (int) $user['role_id'] ?>"
                          data-role="<?= htmlspecialchars($user['role_name']) ?>"
                          data-status="<?= htmlspecialchars($user['account_status']) ?>"
                          data-last-login="<?= htmlspecialchars($user['last_login_at'] ?? '') ?>" title="Reset Password">

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
                          title="<?= $isActive ? 'Disable Account' : 'Enable Account' ?>">
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

    </section>

  </main>

  <!--MODALS-->

  <?php require_once __DIR__ . '/../includes/components/logout_modal.php'; ?>

  <!-- Create User Modal -->
  <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content modal-content-custom">
        <div class="modal-header-custom">
          <div class="d-flex align-items-center">
            <div class="modal-header-icon-box primary">
              <i data-lucide="user-plus"></i>
            </div>
            <div class="modal-header-title-wrapper">
              <h3 class="modal-title-custom">Create User</h3>
              <p class="modal-subtitle-custom">Primary directory entry registration</p>
            </div>
          </div>
          <button class="modal-close-icon-btn" data-bs-dismiss="modal">
            <i data-lucide="x"></i>
          </button>
        </div>
        <form id="addUserForm" action="<?= APP_URL ?>/actions/users/create_user.php" method="POST">
          <div class="modal-body-custom">

            <div class="row g-3">

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom">Username</label>
                <input type="text" class="form-control-custom" id="addUsername" name="username"
                  placeholder="e.g. msantos" required />
              </div>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom">First Name</label>
                <input type="text" class="form-control-custom" id="addFirstName" name="first_name"
                  placeholder="e.g. Maria" required />
              </div>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom">Last Name</label>
                <input type="text" class="form-control-custom" id="addLastName" name="last_name"
                  placeholder="e.g. Santos" required />
              </div>

              <div class="col-12 form-group-custom">
                <label class="form-label-custom">Institutional Email</label>
                <input type="email" class="form-control-custom" id="addEmail" name="email"
                  placeholder="e.g. maria.santos@dasmarinas.sti.edu.ph" required />
              </div>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom">Role</label>
                <select class="form-control-custom" id="addRole" name="role_id" required>
                  <option value="1">Technical Administrator</option>
                  <option value="2">Academic Head</option>
                  <option value="3">Teacher</option>
                  <option value="4">Disciplinary Officer</option>
                </select>
              </div>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom">Status</label>
                <select class="form-control-custom" id="addStatus" name="account_status" required>
                  <option value="Active">Active</option>
                  <option value="Disabled">Disabled</option>
                </select>
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
          <div class="modal-footer-custom">
            <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="modal-btn-action">Create User</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit User Modal -->
  <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content modal-content-custom">
        <div class="modal-header-custom">
          <div class="d-flex align-items-center">
            <div class="modal-header-icon-box primary">
              <i data-lucide="edit"></i>
            </div>
            <div class="modal-header-title-wrapper">
              <h3 class="modal-title-custom">Save Changes</h3>
              <p class="modal-subtitle-custom">Modify core administrative profile</p>
            </div>
          </div>
          <button class="modal-close-icon-btn" data-bs-dismiss="modal">
            <i data-lucide="x"></i>
          </button>
        </div>
        <form id="editUserForm" action="<?= APP_URL ?>/actions/users/update_user.php" method="POST">

          <input type="hidden" name="user_id" id="editUserId">
          <div class="modal-body-custom">

            <div class="row g-3">
              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom">Employee Number</label>
                <input type="text" class="form-control-custom font-mono" id="editEmployeeNumber" disabled />
              </div>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom">Username</label>
                <input type="text" class="form-control-custom" id="editUsername" name="username" placeholder="Username"
                  required />
              </div>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom">First Name</label>
                <input type="text" class="form-control-custom" id="editFirstName" name="first_name"
                  placeholder="First Name" required />
              </div>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom">Last Name</label>
                <input type="text" class="form-control-custom" id="editLastName" name="last_name"
                  placeholder="Last Name" required />
              </div>

              <div class="col-12 form-group-custom">
                <label class="form-label-custom">Institutional Email</label>
                <input type="email" class="form-control-custom" id="editEmail" name="email"
                  placeholder="Institutional Email" required />
              </div>

              <select class="form-control-custom" id="editRole" name="role_id" required>

                <option value="1">Technical Administrator</option>
                <option value="2">Academic Head</option>
                <option value="3">Teacher</option>
                <option value="4">Disciplinary Officer</option>
              </select>

              <div class="col-md-6 form-group-custom">
                <label class="form-label-custom">Status</label>
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
  <div class="modal fade" id="responsibilitiesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-content-custom">
        <div class="modal-header-custom">
          <div class="d-flex align-items-center">
            <div class="modal-header-icon-box warning">
              <i data-lucide="shield"></i>
            </div>
            <div class="modal-header-title-wrapper">
              <h3 class="modal-title-custom">Manage Responsibilities</h3>
              <p class="modal-subtitle-custom">Assign institutional responsibilities</p>
            </div>
          </div>
          <button class="modal-close-icon-btn" data-bs-dismiss="modal">
            <i data-lucide="x"></i>
          </button>
        </div>
        <form id="responsibilitiesForm" action="<?= APP_URL ?>/actions/users/update_responsibilities.php" method="POST">
          <input type="hidden" id="respUserId" name="user_id">
          <div class="modal-body-custom">
            <p class="text-xs text-muted mb-3" style="font-size: 0.75rem; font-weight: 500; line-height: 1.5;">
              Assign institutional responsibilities to this personnel. These responsibilities determine whether the user
              serves as an Adviser or Program Head within the academic structure. A user may hold none, one, or both
              responsibilities depending on institutional assignments.
            </p>

            <div class="responsibilities-checklist" id="editResponsibilitiesContainer">
              <!-- Populated dynamically -->
            </div>

            <!-- Role transformation alert box -->
            <div class="alert-card-warning mt-3"
              style="background-color: #f3e8ff; border-color: #e9d5ff; color: #6b21a8;">
              <i data-lucide="sparkles" style="color: #a855f7;"></i>
              <p class="alert-card-warning-text" style="color: #6b21a8;">
                <strong>Responsibility Assignment Note:</strong>
                Assigning the <strong>Adviser</strong> responsibility designates this teacher as the adviser for one or
                more assigned sections. Advisers can access advisory student monitoring and records only for the
                sections assigned to them. The <strong>Program Head</strong> responsibility identifies personnel
                responsible for overseeing their assigned academic program.
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

  <!-- View User Modal -->
  <div class="modal fade" id="viewUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-content-custom">
        <div class="view-profile-header d-flex align-items-center">
          <div class="view-profile-avatar" id="viewUserAvatar">--</div>
          <div class="view-profile-details">
            <h3 class="view-profile-name" id="viewUserName">--</h3>
            <div class="view-profile-sub">
              <i data-lucide="shield-check"></i>
              <span id="viewUserRole">--</span>
            </div>
          </div>
          <button class="modal-close-icon-btn" data-bs-dismiss="modal"
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
  <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-content-custom">

        <div class="modal-header-custom">

          <div class="d-flex align-items-center">

            <div class="modal-header-icon-box warning">
              <i data-lucide="key"></i>
            </div>

            <div class="modal-header-title-wrapper">
              <h3 class="modal-title-custom">
                Reset Password
              </h3>

              <p class="modal-subtitle-custom">
                Generate a new temporary password
              </p>
            </div>

          </div>

          <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal">

            <i data-lucide="x"></i>

          </button>

        </div>

        <form id="resetPasswordForm" action="<?= APP_URL ?>/actions/users/reset_password.php" method="POST">

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
  <div class="modal fade" id="accountStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-content-custom">

        <div class="modal-header-custom">

          <div class="d-flex align-items-center">

            <div class="modal-header-icon-box warning">
              <i data-lucide="shield"></i>
            </div>

            <div class="modal-header-title-wrapper">

              <h3 class="modal-title-custom" id="accountStatusModalTitle">

                Update Account Status

              </h3>

              <p class="modal-subtitle-custom">

                Confirm this administrative action

              </p>

            </div>

          </div>

          <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal">

            <i data-lucide="x"></i>

          </button>

        </div>

        <form id="accountStatusForm" action="<?= APP_URL ?>/actions/users/toggle_account_status.php" method="POST">

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
  <div class="modal fade" id="tempPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-content-custom" style="padding: 2.25rem 2rem;">
        <div class="modal-body p-0 text-center">
          <div class="logout-icon"
            style="width: 44px; height: 44px; border-radius: 50%; background-color: rgba(255, 199, 44, 0.08); color: #f59e0b; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem;">
            <i data-lucide="key" style="width: 18px; height: 18px;"></i>
          </div>
          <h3 class="modal-title-custom mb-2 text-center" style="font-size: 1.3rem; font-weight: 700;">Security Code
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
        desc: "Assigned as the adviser of one or more sections."
      },
      {
        name: "Program Head",
        desc: "Assigned as the program head for one or more academic programs."
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

      document
        .getElementById('editUserModal')
        .addEventListener('shown.bs.modal', () => {

          document
            .getElementById('editUsername')
            .focus();

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
      const btnExportUsers = document.getElementById('btnExportUsers');

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

      document
        .getElementById('tempPasswordModal')
        .addEventListener('shown.bs.modal', () => {

          document
            .getElementById('acknowledgeTempPasswordBtn')
            ?.focus();

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
        if (role === 'Guidance') return 'Guidance/DO';
        return role;
      }

      function filterUsers() {

        const keyword =
          searchFilter.value.trim().toLowerCase();

        const selectedRole =
          roleFilter.value;

        const selectedResponsibility =
          responsibilityFilter.value;

        const selectedStatus =
          statusFilter.value;

        const rows =
          document.querySelectorAll(
            "#usersTableBody tr"
          );

        rows.forEach(function (row) {

          if (row.cells.length < 7) {
            return;
          }

          const name =
            row.cells[0].textContent.toLowerCase();

          const email =
            row.cells[1].textContent.toLowerCase();

          const role =
            row.cells[2].textContent.trim();

          const responsibilities =
            row.cells[3].textContent.trim();

          const status =
            row.cells[4].textContent.trim();

          const matchesSearch =
            keyword === "" ||
            name.includes(keyword) ||
            email.includes(keyword);

          const matchesRole =
            selectedRole === "All" ||
            role === selectedRole;

          const matchesResponsibility =
            selectedResponsibility === "All" ||
            responsibilities.includes(selectedResponsibility);

          const matchesStatus =
            selectedStatus === "All" ||
            status === selectedStatus;

          row.style.display =
            (
              matchesSearch &&
              matchesRole &&
              matchesResponsibility &&
              matchesStatus
            )
              ? ""
              : "none";

        });

      }

      searchFilter.addEventListener(
        "input",
        filterUsers
      );

      roleFilter.addEventListener(
        "change",
        filterUsers
      );

      responsibilityFilter.addEventListener(
        "change",
        filterUsers
      );

      statusFilter.addEventListener(
        "change",
        filterUsers
      );

      addUserForm.addEventListener('submit', function (event) {

        const emailField =
          document.getElementById('addEmail');

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

      });

      // Trigger Create User modal
      btnAddUser.addEventListener('click', () => {
        addUserForm.reset();
        document.querySelectorAll('#addResponsibilitiesContainer .checklist-checkbox').forEach(cb => cb.checked = false);
        addUserModal.show();
      });

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

      // Export action triggers warning notification in sandbox mode
      btnExportUsers.addEventListener('click', () => {
        showToast('Database Session Required', 'Data Export: An active database session is required to compile and download personnel registries.', 'warning');
      });


      // Actions delegation inside directory grid
      usersTableBody.addEventListener('click', (e) => {
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
    '/../includes/components/technical_admin_footer.php';
  ?>

</body>

</html>