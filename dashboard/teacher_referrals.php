<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';
$allowedRoles = [ROLE_TEACHER];
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../auth/csrf_helper.php';
require_once __DIR__ . '/../includes/helper/flash_message.php';
require_once __DIR__ . '/../includes/helper/teacher_referral_helper.php';
$pageTitle = 'Referrals';
$activePage = 'referrals';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/teacher-referrals.css';
$teacherId = (int) ($_SESSION['user_id'] ?? 0);
$currentSchoolYear = $pdo->query("SELECT school_year FROM school_years WHERE status = 'Active' ORDER BY school_year_id DESC LIMIT 1")->fetchColumn() ?: null;
$referralClasses = aprismTeacherReferralClasses($pdo, $teacherId, $currentSchoolYear);
$requestedClassId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT) ?: 0;
$selectedClass = null;
foreach ($referralClasses as $class) {
  if ((int) $class['operational_class_id'] === (int) $requestedClassId) {
    $selectedClass = $class;
    break;
  }
}
$selectedClassId = (int) ($selectedClass['operational_class_id'] ?? 0);
$roster = $selectedClassId ? aprismTeacherReferralRoster($pdo, $teacherId, $selectedClassId) : [];
$referrals = aprismTeacherReferrals($pdo, $teacherId);
$csrfToken = generateCsrfToken();
$referralClassSearch = trim(
  (string) ($selectedClass['subject_name'] ?? '') . ' ' .
  (string) ($selectedClass['section_name'] ?? '')
);
$referralBackUrl = $selectedClassId > 0
  ? APP_URL . '/dashboard/teacher_my_class.php?' . http_build_query(['search' => $referralClassSearch])
  : APP_URL . '/dashboard/teacher_referrals.php#referral-history-title';
$referralBackLabel = $selectedClassId > 0 ? 'Back to My Classes' : 'Back to Referrals';
?>
<!DOCTYPE html>
<html lang="en">

<head><?php require __DIR__ . '/../includes/components/head.php'; ?></head>

<body>
  <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
  <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
    <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
    <div class="content-wrapper referral-workspace">
      <header class="referral-page-header referral-create-page-header">
        <div class="referral-page-heading">
          <a class="teacher-class-workspace-back referral-page-back" href="<?= htmlspecialchars($referralBackUrl, ENT_QUOTES, 'UTF-8') ?>"
            aria-label="<?= htmlspecialchars($referralBackLabel, ENT_QUOTES, 'UTF-8') ?>"
            title="<?= htmlspecialchars($referralBackLabel, ENT_QUOTES, 'UTF-8') ?>">
            <i data-lucide="arrow-left" aria-hidden="true"></i>
          </a>
          <div class="referral-page-title-block">
          <h1 class="teacher-page-title">Referrals</h1>
          </div>
        </div>
      </header>
      <details class="teacher-panel referral-create-panel" <?= $selectedClassId > 0 || !$referrals ? 'open' : '' ?>>
        <summary class="referral-create-toggle">
          <span><i data-lucide="clipboard-plus" aria-hidden="true"></i><strong>Create Referral</strong></span>
          <span class="referral-create-toggle-hint">Select a class and Student, then record the observed concern</span>
        </summary>
        <form method="post" action="<?= APP_URL ?>/actions/teacher/create_referral.php" class="referral-form"
          data-referral-form><input type="hidden" name="csrf_token"
            value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
          <div class="referral-field-grid"><label class="referral-field" for="referral-class"><span>Class <b class="referral-required" aria-hidden="true">*</b></span><select id="referral-class" name="operational_class_id" required
                data-referral-class><?php if ($referralClasses): ?>
                  <option value="">Select a class</option><?php else: ?>
                  <option value="">No active classes available</option>
                <?php endif; ?><?php foreach ($referralClasses as $class): ?>
                  <option value="<?= (int) $class['operational_class_id'] ?>"
                    data-subject="<?= htmlspecialchars((string) $class['subject_name'], ENT_QUOTES, 'UTF-8') ?>"
                    data-section="<?= htmlspecialchars((string) $class['section_name'], ENT_QUOTES, 'UTF-8') ?>"
                    data-school-year="<?= htmlspecialchars((string) $class['school_year'], ENT_QUOTES, 'UTF-8') ?>"
                    data-semester="<?= htmlspecialchars((string) $class['semester'], ENT_QUOTES, 'UTF-8') ?>" <?= (int) $class['operational_class_id'] === $selectedClassId ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) $class['subject_name'] . ' · ' . (string) $class['section_name'], ENT_QUOTES, 'UTF-8') ?>
                  </option><?php endforeach; ?>
              </select></label>
            <label class="referral-field" for="referral-student"><span>Student <b class="referral-required" aria-hidden="true">*</b></span><select
                id="referral-student" name="student_class_enrollment_id" required data-referral-student <?= $roster ? '' : 'disabled' ?>>
                <option value="">
                  <?= !$selectedClassId ? 'Select a class first' : (!$roster ? 'No enrolled Students in this class.' : 'Select a Student') ?>
                </option><?php foreach ($roster as $student): ?>
                  <option value="<?= (int) $student['student_class_enrollment_id'] ?>"
                    data-class-id="<?= $selectedClassId ?>"
                    data-student-name="<?= htmlspecialchars((string) $student['student_name'], ENT_QUOTES, 'UTF-8') ?>"
                    data-student-number="<?= htmlspecialchars((string) $student['student_number'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($student['student_name'] . ' · ' . $student['student_number']) ?></option>
                <?php endforeach; ?>
              </select><span class="referral-student-context" data-referral-student-context aria-live="polite"
                hidden></span></label>
          </div>
          <label class="referral-field referral-concern-field" for="referral-reason"><span>Observed
              Concern</span><textarea id="referral-reason" name="reason" rows="6" maxlength="5000" required
              placeholder="Describe the observed concern and relevant context."></textarea></label>
          <div class="referral-form-footer">
            <button class="btn btn-primary"
              type="submit" <?= $roster ? '' : 'disabled' ?>><i data-lucide="send" aria-hidden="true"></i> Submit
              Referral</button>
          </div>
        </form>
      </details>
      <section class="teacher-panel referral-history-panel" aria-labelledby="referral-history-title">
        <div class="teacher-panel-header">
          <div>
            <p class="teacher-eyebrow">Record history</p>
            <h2 id="referral-history-title" class="teacher-panel-title">Submitted Referrals</h2>
          </div><span class="referral-history-count"><?= count($referrals) ?> submitted</span>
        </div><?php if (!$referrals): ?>
          <div class="teacher-empty-state"><i data-lucide="clipboard-plus"></i>
            <h3 class="teacher-empty-state-title">No referrals submitted</h3>
            <p class="teacher-empty-state-text">When you record a Student concern, it will appear here with its class
              context and current status.</p>
          </div><?php else: ?>
          <div class="teacher-table-wrapper referral-table-scroll">
            <table class="teacher-table referral-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Student</th>
                  <th>Class / context</th>
                  <th>Observed concern</th>
                  <th>Submitted</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody><?php foreach ($referrals as $index => $referral): ?>
                  <tr>
                    <td data-label="Row"><?= $index + 1 ?></td>
                    <td data-label="Student">
                      <strong><?= htmlspecialchars($referral['student_name']) ?></strong><small><?= htmlspecialchars($referral['student_number']) ?></small>
                    </td>
                    <td data-label="Class">
                      <?= htmlspecialchars(trim(($referral['subject_name'] ?? 'Class unavailable') . ' · ' . ($referral['section_name'] ?? ''))) ?><small><?= htmlspecialchars(trim(($referral['school_year_snapshot'] ?? '') . ' · ' . ($referral['semester_snapshot'] ?? ''))) ?></small>
                    </td>
                    <td data-label="Concern"><?= htmlspecialchars(mb_strimwidth($referral['reason'], 0, 110, '…')) ?></td>
                    <td data-label="Submitted"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($referral['created_at']))) ?></td>
                    <td data-label="Status"><span
                        class="status-pill status-<?= htmlspecialchars(strtolower($referral['status'] ?? 'submitted')) ?>"><?= htmlspecialchars($referral['status'] ?? 'Submitted') ?></span>
                    </td>
                    <td data-label="Action"><a class="referral-row-action" href="<?= APP_URL ?>/dashboard/teacher_referral_detail.php?referral_id=<?= (int) $referral['teacher_referral_id'] ?>"><i data-lucide="arrow-up-right" aria-hidden="true"></i> View details</a></td>
                  </tr><?php endforeach; ?>
              </tbody>
            </table>
          </div><?php endif; ?>
      </section>
    </div>
  </main>
  <div class="toast-container-custom" id="toastContainer" aria-live="polite" aria-atomic="true"></div>
  <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?><?php require __DIR__ . '/../includes/components/footer.php'; ?>
  <script>window.APRISM_REFERRAL_FLASH = <?= json_encode(['success' => $flash['success'], 'error' => $flash['error']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
  <script src="<?= APP_URL ?>/assets/js/pages/teacher-referrals.js"></script>
</body>

</html>
