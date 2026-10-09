<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../includes/helper/responsibility_helper.php';
require_once __DIR__ . '/../includes/helper/adviser_helper.php';

$userId = (int) $_SESSION['user_id'];
$profileStmt = $pdo->prepare('SELECT u.first_name, u.last_name, u.email, u.account_status, u.created_at, r.role_name FROM users u INNER JOIN roles r ON r.role_id=u.role_id WHERE u.user_id=? LIMIT 1');
$profileStmt->execute([$userId]);
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
if ($profile === false) { http_response_code(404); exit('Profile is unavailable.'); }
$responsibilities = getUserResponsibilities($pdo, $userId);
$adviserAssignments = isTeacher() ? aprismAdviserAssignments($pdo, $userId) : [];
$createdAtTimestamp = strtotime((string) ($profile['created_at'] ?? ''));
$accountCreatedLabel = $createdAtTimestamp === false ? null : date('F j, Y', $createdAtTimestamp);
$hasProfileContext = $responsibilities !== [] || $adviserAssignments !== [];
$pageTitle = 'My Profile'; $activePage = ''; $roleStylesheet = 'assets/css/teacher.css'; $pageStylesheet = 'assets/css/pages/profile-notifications.css';
?>
<!DOCTYPE html><html lang="en"><?php require __DIR__ . '/../includes/components/head.php'; ?><body>
<?php require __DIR__ . '/../includes/components/sidebar.php'; ?><main class="main-content"><div class="main-wrapper">
<?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
<section class="profile-page profile-account-page" aria-labelledby="profile-title">
  <header class="profile-page-header">
    <div>
      <p class="page-eyebrow">Account</p>
      <h1 id="profile-title">My Profile</h1>
    </div>
  </header>

  <div class="profile-overview">
    <section class="profile-hero" aria-labelledby="profile-identity-title">
      <div class="profile-identity-avatar" aria-hidden="true">
        <?= htmlspecialchars(strtoupper(substr((string) $profile['first_name'], 0, 1) . substr((string) $profile['last_name'], 0, 1)), ENT_QUOTES, 'UTF-8') ?>
      </div>
      <div class="profile-identity-copy">
        <p class="profile-section-kicker">APRISM account</p>
        <h2 id="profile-identity-title"><?= htmlspecialchars(trim($profile['first_name'] . ' ' . $profile['last_name']), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="profile-email"><?= htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8') ?></p>
        <div class="profile-identity-tags">
          <span class="profile-role-badge"><?= htmlspecialchars($profile['role_name'], ENT_QUOTES, 'UTF-8') ?></span>
          <span class="profile-status-badge <?= strtolower((string) $profile['account_status']) === 'active' ? 'is-active' : 'is-inactive' ?>">
            <?= htmlspecialchars($profile['account_status'], ENT_QUOTES, 'UTF-8') ?>
          </span>
        </div>
      </div>
    </section>

    <div class="profile-content-grid <?= $hasProfileContext ? '' : 'profile-content-grid-single' ?>">
      <section class="profile-section" aria-labelledby="profile-information-title">
        <div class="profile-section-heading">
          <span class="profile-section-icon" aria-hidden="true"><i data-lucide="user-round"></i></span>
          <div class="profile-section-heading-copy">
            <p class="profile-section-kicker">Account</p>
            <h2 id="profile-information-title">Account Information</h2>
          </div>
        </div>
        <dl class="profile-meta-list">
          <div><dt>Full Name</dt><dd><?= htmlspecialchars(trim($profile['first_name'] . ' ' . $profile['last_name']), ENT_QUOTES, 'UTF-8') ?></dd></div>
          <div><dt>Email Address</dt><dd><?= htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8') ?></dd></div>
          <div><dt>Role</dt><dd><?= htmlspecialchars($profile['role_name'], ENT_QUOTES, 'UTF-8') ?></dd></div>
          <div><dt>Account Status</dt><dd><span class="profile-status-badge <?= strtolower((string) $profile['account_status']) === 'active' ? 'is-active' : 'is-inactive' ?>"><?= htmlspecialchars($profile['account_status'], ENT_QUOTES, 'UTF-8') ?></span></dd></div>
          <?php if ($accountCreatedLabel !== null): ?>
            <div><dt>Account Created</dt><dd><?= htmlspecialchars($accountCreatedLabel, ENT_QUOTES, 'UTF-8') ?></dd></div>
          <?php endif; ?>
        </dl>
      </section>

      <?php if ($hasProfileContext): ?>
        <section class="profile-section" aria-labelledby="profile-responsibilities-title">
          <div class="profile-section-heading">
            <span class="profile-section-icon" aria-hidden="true"><i data-lucide="users-round"></i></span>
            <div class="profile-section-heading-copy">
              <p class="profile-section-kicker">Role / work context</p>
              <h2 id="profile-responsibilities-title">Responsibilities and Context</h2>
            </div>
          </div>
          <?php if ($responsibilities !== []): ?>
            <div class="profile-context-group">
              <p class="profile-subsection-label">Current responsibilities</p>
              <ul class="profile-responsibility-list">
                <?php foreach ($responsibilities as $responsibility): ?>
                  <li><?= htmlspecialchars($responsibility, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <?php if ($adviserAssignments !== []): ?>
            <div class="profile-assignment-list">
              <p class="profile-subsection-label">Current Adviser assignments</p>
              <ul class="profile-context">
                <?php foreach ($adviserAssignments as $assignment): ?>
                  <li>
                    <strong><?= htmlspecialchars($assignment['section_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span><?= htmlspecialchars($assignment['school_year'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($assignment['teaching_context'], ENT_QUOTES, 'UTF-8') ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </div>

    <section class="profile-security-panel" aria-labelledby="profile-security-title">
      <span class="profile-section-icon" aria-hidden="true"><i data-lucide="lock-keyhole"></i></span>
      <div class="profile-security-copy">
        <p class="profile-section-kicker">Security</p>
        <h2 id="profile-security-title">Protect your account</h2>
        <p class="profile-security-note">Your role and institutional identity are managed by authorized personnel.</p>
      </div>
      <a class="profile-header-action profile-security-action" href="<?= APP_URL ?>/auth/change_password.php">Change Password</a>
    </section>
  </div>
</section></div></main><?php require __DIR__ . '/../includes/components/footer.php'; ?></body></html>
