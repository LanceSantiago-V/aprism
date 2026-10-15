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
$formState = is_array($_SESSION['teacher_referral_form'] ?? null) ? $_SESSION['teacher_referral_form'] : [];
$formErrors = is_array($_SESSION['teacher_referral_errors'] ?? null) ? $_SESSION['teacher_referral_errors'] : [];
unset($_SESSION['teacher_referral_form'], $_SESSION['teacher_referral_errors']);
$requestedClassId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT)
  ?: (int) ($formState['operational_class_id'] ?? 0);
$selectedClass = null;
foreach ($referralClasses as $class) {
  if ((int) $class['operational_class_id'] === (int) $requestedClassId) {
    $selectedClass = $class;
    break;
  }
}
$selectedClassId = (int) ($selectedClass['operational_class_id'] ?? 0);
$roster = $selectedClassId ? aprismTeacherReferralRoster($pdo, $teacherId, $selectedClassId) : [];
$requestedSceId = (int) ($formState['student_class_enrollment_id']
  ?? (filter_input(INPUT_GET, 'student_class_enrollment_id', FILTER_VALIDATE_INT) ?: 0));
$requestedStudentId = $formState === []
  ? (filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT) ?: 0)
  : 0;
$selectedSceId = 0;
foreach ($roster as $student) {
  if (
    (int) $student['student_class_enrollment_id'] === $requestedSceId
    || ($requestedSceId === 0 && (int) $student['student_id'] === (int) $requestedStudentId)
  ) {
    $selectedSceId = (int) $student['student_class_enrollment_id'];
    break;
  }
}
$reasonOptions = aprismReferralReasonOptions();
$selectedReasonCode = (string) ($formState['reason_code'] ?? '');
if (!array_key_exists($selectedReasonCode, $reasonOptions)) {
  $selectedReasonCode = '';
}
$supportingNote = (string) ($formState['note'] ?? '');
$referralSourceContext = 'referrals';
$referralSourcePage = 1;
$referralSheetData = aprismTeacherReferralSheetData($pdo, $teacherId, $currentSchoolYear, $referralClasses);
$selectedEvidence = null;
$selectedEvidenceToken = '';
if ($selectedClassId > 0 && $selectedSceId > 0) {
  $selectedEvidenceRow = aprismTeacherReferralAttendanceEvidence($pdo, $teacherId, $selectedClassId, $selectedSceId);
  if ($selectedEvidenceRow !== null) {
    $selectedEvidence = aprismTeacherReferralEvidenceSnapshot($selectedEvidenceRow, $referralSourceContext);
    $selectedEvidenceToken = aprismTeacherReferralIssueEvidenceToken($selectedEvidence);
  }
}
$referralSheetInitiallyOpen = (filter_input(INPUT_GET, 'new', FILTER_VALIDATE_INT) === 1) || $formErrors !== [];
$referrals = aprismTeacherReferrals($pdo, $teacherId);
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">

<head><?php require __DIR__ . '/../includes/components/head.php'; ?><link rel="stylesheet" href="<?= APP_URL ?>/assets/css/components/teacher-referral-action-sheet.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/components/teacher-referral-action-sheet.css') ?>"></head>

<body>
  <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
  <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
    <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
    <div class="content-wrapper referral-workspace">
      <header class="referral-page-header referral-create-page-header">
        <h1 class="teacher-page-title" tabindex="-1" data-referral-sheet-return-focus>Referrals</h1>
        <button type="button" class="btn btn-primary referral-page-action" data-referral-sheet-open data-source-context="referrals">
          <i data-lucide="plus" aria-hidden="true"></i> New Referral
        </button>
      </header>
      <section class="teacher-panel referral-history-panel" aria-labelledby="referral-history-title">
        <div class="teacher-panel-header referral-history-toolbar">
          <h2 id="referral-history-title" class="teacher-panel-title">Referral History</h2>
          <span class="referral-history-count"><?= count($referrals) ?> referrals</span>
        </div>
        <noscript><p>JavaScript is disabled. Choose your class to open the secure referral form:</p><?php foreach ($referralClasses as $class): ?><a class="referral-row-action" href="<?= APP_URL ?>/dashboard/teacher_referrals.php?new=1&amp;class_id=<?= (int) $class['operational_class_id'] ?>#new-referral"><?= htmlspecialchars(trim((string) $class['subject_name'] . ' · ' . (string) $class['section_name']), ENT_QUOTES, 'UTF-8') ?></a> <?php endforeach; ?></noscript>
        <?php if (!$referrals): ?>
          <div class="teacher-empty-state"><i data-lucide="clipboard-plus"></i>
            <h3 class="teacher-empty-state-title">No referrals yet</h3>
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
                  <th>Referral reason</th>
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
                    <td data-label="Referral reason"><strong><?= htmlspecialchars(aprismReferralReasonLabel($referral['reason_code'] ?? null), ENT_QUOTES, 'UTF-8') ?></strong><?php if (trim((string) ($referral['reason'] ?? '')) !== ''): ?><small><?= htmlspecialchars(mb_strimwidth((string) $referral['reason'], 0, 110, '…'), ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?><?php $snapshotSummary = aprismTeacherReferralEvidenceSnapshotSummary($referral['evidence_snapshot_json'] ?? null); if ($snapshotSummary !== null): ?><small><?= htmlspecialchars($snapshotSummary, ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></td>
                    <td data-label="Submitted"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($referral['created_at']))) ?></td>
                    <td data-label="Status"><span
                        class="status-pill status-<?= htmlspecialchars(strtolower($referral['status'] ?? 'submitted')) ?>"><?= htmlspecialchars($referral['status'] ?? 'Submitted') ?></span>
                    </td>
                    <td data-label="Action"><a class="referral-row-action" href="<?= APP_URL ?>/dashboard/teacher_referral_detail.php?referral_id=<?= (int) $referral['teacher_referral_id'] ?>#referral-detail-title"><i data-lucide="arrow-up-right" aria-hidden="true"></i> View details</a></td>
                  </tr><?php endforeach; ?>
              </tbody>
            </table>
          </div><?php endif; ?>
      </section>
      <?php
      $referralSheetId = 'teacher-referral-sheet';
      $referralSourceContext = 'referrals';
      require __DIR__ . '/../includes/components/teacher_referral_action_sheet.php';
      ?>
    </div>
  </main>
  <div class="toast-container-custom" id="toastContainer" aria-live="polite" aria-atomic="true"></div>
  <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?><?php require __DIR__ . '/../includes/components/footer.php'; ?>
  <script>window.APRISM_REFERRAL_FLASH = <?= json_encode(['success' => $flash['success'], 'error' => $flash['error']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
  <script src="<?= APP_URL ?>/assets/js/components/teacher-referral-action-sheet.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/components/teacher-referral-action-sheet.js') ?>"></script>
</body>

</html>
