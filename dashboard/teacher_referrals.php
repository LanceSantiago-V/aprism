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
$referralStatusCounts = ['Submitted' => 0, 'Returned' => 0, 'Accepted' => 0, 'Closed' => 0];
$referralSections = [];
foreach ($referrals as $referral) {
  $status = (string) ($referral['status'] ?? 'Submitted');
  if (array_key_exists($status, $referralStatusCounts)) {
    $referralStatusCounts[$status]++;
  }
  $section = trim((string) ($referral['section_name'] ?? ''));
  if ($section !== '') {
    $referralSections[$section] = $section;
  }
}
natcasesort($referralSections);
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
      <header class="referral-page-header referral-create-page-header referral-root-header">
        <div class="referral-page-title-block">
          <div class="referral-title-line">
            <h1 class="teacher-page-title" tabindex="-1" data-referral-sheet-return-focus>Referrals</h1>
          </div>
        </div>
        <div class="referral-root-actions">
          <button type="button" class="btn btn-primary referral-page-action" data-referral-sheet-open data-source-context="referrals">
            <i data-lucide="plus" aria-hidden="true"></i> New Referral
          </button>
        </div>
      </header>

      <section class="referral-filterbar" aria-label="Filter referral history">
        <div class="referral-status-filters" role="group" aria-label="Referral status">
          <button type="button" class="is-active" data-referral-status-filter="all" aria-pressed="true">All <span><?= count($referrals) ?></span></button>
          <button type="button" data-referral-status-filter="pending" aria-pressed="false" title="Submitted and returned referrals">Pending <span><?= $referralStatusCounts['Submitted'] + $referralStatusCounts['Returned'] ?></span></button>
          <button type="button" data-referral-status-filter="Accepted" aria-pressed="false">Accepted <span><?= $referralStatusCounts['Accepted'] ?></span></button>
          <button type="button" data-referral-status-filter="Closed" aria-pressed="false">Closed <span><?= $referralStatusCounts['Closed'] ?></span></button>
        </div>
        <label class="referral-search-control">
          <i data-lucide="search" aria-hidden="true"></i>
          <span class="visually-hidden">Search referrals</span>
          <input type="search" placeholder="Search student, ID, or keyword…" data-referral-search>
        </label>
        <label class="referral-section-control">
          <span class="visually-hidden">Filter by section</span>
          <select data-referral-section>
            <option value="">All Sections</option>
            <?php foreach ($referralSections as $section): ?><option value="<?= htmlspecialchars($section, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($section, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
          </select>
          <i data-lucide="chevron-down" aria-hidden="true"></i>
        </label>
        <div class="referral-page-actions referral-toolbar-actions" aria-label="Referral history actions">
          <button type="button" class="btn btn-outline-secondary referral-export-action" data-referral-export aria-label="Export referral history to Excel" data-aprism-tooltip="Export referral history to Excel">
            <i data-lucide="file-spreadsheet" aria-hidden="true"></i>
          </button>
          <button type="button" class="btn btn-outline-secondary referral-print-action" data-referral-print aria-label="Print or save referral history as PDF" data-aprism-tooltip="Print or save referral history as PDF">
            <i data-lucide="printer" aria-hidden="true"></i>
          </button>
        </div>
        <button type="button" class="referral-filter-reset" data-referral-clear aria-label="Clear referral filters" title="Clear filters"><i data-lucide="list-filter" aria-hidden="true"></i></button>
      </section>

      <section class="teacher-panel referral-history-panel" aria-labelledby="referral-history-title">
        <div class="teacher-panel-header referral-history-toolbar">
          <div>
            <h2 id="referral-history-title" class="teacher-panel-title">Referral History &amp; Case Tracking</h2>
          </div>
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
                  <th>Student name &amp; ID</th>
                  <th>Class context / subject</th>
                  <th>Referral reason &amp; notes</th>
                  <th>Submitted date</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody><?php foreach ($referrals as $referral): ?>
                  <?php
                  $referralStatus = (string) ($referral['status'] ?? 'Submitted');
                  $filterStatus = in_array($referralStatus, ['Submitted', 'Returned'], true) ? 'pending' : $referralStatus;
                  $nameParts = preg_split('/\s+/', trim((string) ($referral['student_name'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                  $initials = mb_strtoupper(mb_substr((string) ($nameParts[0] ?? 'S'), 0, 1) . (count($nameParts) > 1 ? mb_substr((string) end($nameParts), 0, 1) : ''));
                  $academicContext = implode(' · ', array_values(array_filter([
                    trim((string) ($referral['school_year_snapshot'] ?? '')),
                    trim((string) ($referral['semester_snapshot'] ?? '')),
                  ], static fn(string $value): bool => $value !== '')));
                  $hasStructuredReason = trim((string) ($referral['reason_code'] ?? '')) !== '';
                  $reasonLabel = $hasStructuredReason ? aprismReferralReasonLabel($referral['reason_code']) : 'Legacy entry';
                  ?>
                  <tr data-referral-row data-status="<?= htmlspecialchars($filterStatus, ENT_QUOTES, 'UTF-8') ?>" data-section="<?= htmlspecialchars((string) ($referral['section_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-student="<?= htmlspecialchars((string) ($referral['student_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-student-number="<?= htmlspecialchars((string) ($referral['student_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-subject="<?= htmlspecialchars((string) ($referral['subject_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-reason="<?= htmlspecialchars($reasonLabel, ENT_QUOTES, 'UTF-8') ?>" data-note="<?= htmlspecialchars((string) ($referral['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-submitted="<?= htmlspecialchars(date('M j, Y g:i A', strtotime($referral['created_at'])), ENT_QUOTES, 'UTF-8') ?>" data-real-status="<?= htmlspecialchars($referralStatus, ENT_QUOTES, 'UTF-8') ?>">
                    <td data-label="Student">
                      <span class="referral-student-cell"><span class="referral-row-avatar" aria-hidden="true"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span><span class="referral-row-student-copy"><strong><?= htmlspecialchars($referral['student_name']) ?></strong><small><?= htmlspecialchars($referral['student_number']) ?></small></span></span>
                    </td>
                    <td data-label="Class">
                      <strong class="referral-subject-name"><?= htmlspecialchars((string) ($referral['subject_name'] ?? 'Class unavailable')) ?></strong><small class="referral-class-context"><span class="referral-class-chip"><?= htmlspecialchars((string) ($referral['section_name'] ?? 'Section unavailable')) ?></span><?php if ($academicContext !== ''): ?><span><?= htmlspecialchars($academicContext, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></small>
                    </td>
                    <td data-label="Referral reason"><span class="referral-reason-badge<?= $hasStructuredReason ? '' : ' is-legacy' ?>"<?= $hasStructuredReason ? '' : ' tabindex="0" aria-label="Legacy entry. Created before structured referral reasons were introduced." title="Created before structured referral reasons were introduced."' ?>><?= htmlspecialchars($reasonLabel, ENT_QUOTES, 'UTF-8') ?></span><?php if (trim((string) ($referral['reason'] ?? '')) !== ''): ?><small class="referral-reason-note"><?= htmlspecialchars(mb_strimwidth((string) $referral['reason'], 0, 110, '…'), ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?><?php $snapshotSummary = aprismTeacherReferralEvidenceSnapshotSummary($referral['evidence_snapshot_json'] ?? null); if ($snapshotSummary !== null): ?><small class="referral-evidence-note"><?= htmlspecialchars($snapshotSummary, ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></td>
                    <td data-label="Submitted"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($referral['created_at']))) ?></td>
                    <td data-label="Status"><span
                        class="status-pill status-<?= htmlspecialchars(strtolower($referral['status'] ?? 'submitted')) ?>"><?= htmlspecialchars($referral['status'] ?? 'Submitted') ?></span>
                    </td>
                    <td data-label="Action"><a class="referral-row-action" href="<?= APP_URL ?>/dashboard/teacher_referral_detail.php?referral_id=<?= (int) $referral['teacher_referral_id'] ?>#referral-detail-title"><i data-lucide="arrow-up-right" aria-hidden="true"></i> View details</a></td>
                  </tr><?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <p class="referral-no-matches" data-referral-no-matches role="status" hidden>No referrals match these filters.</p>
          <div class="referral-history-footer" data-referral-pagination>
            <span data-referral-range>Showing <?= count($referrals) ? '1 to ' . min(8, count($referrals)) . ' of ' . count($referrals) : '0' ?> referral records</span>
            <nav class="referral-pagination" aria-label="Referral history pages" data-referral-page-controls></nav>
          </div>
          <?php endif; ?>
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
  <script>window.APRISM_REFERRAL_FLASH = <?= json_encode(['success' => $flash['success'], 'error' => $flash['error'], 'warning' => $flash['warning']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
  <script src="<?= APP_URL ?>/assets/js/components/teacher-referral-action-sheet.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/components/teacher-referral-action-sheet.js') ?>"></script>
  <script src="<?= APP_URL ?>/assets/js/pages/teacher-referral-history.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/pages/teacher-referral-history.js') ?>"></script>
</body>

</html>
