<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/role_helper.php';
$allowedRoles = [ROLE_TECHNICAL_ADMINISTRATOR];
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../auth/csrf_helper.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../includes/helper/flash_message.php';
require_once __DIR__ . '/../includes/helper/responsibility_helper.php';

$activePage = 'class_handover';
$pageTitle = 'Class Handover';
$roleStylesheet = 'assets/css/technical-admin.css';
$pageStylesheet = 'assets/css/pages/technical-admin-class-handover.css';
$csrfToken = generateCsrfToken();

$handoverErrors = $_SESSION['class_handover_errors'] ?? [];
$handoverForm = $_SESSION['class_handover_form'] ?? [];
unset($_SESSION['class_handover_errors'], $_SESSION['class_handover_form']);
if (!isset($_SESSION['class_handover_token'])) {
    $_SESSION['class_handover_token'] = bin2hex(random_bytes(32));
}

$activeSchoolYear = null;
$handoverClasses = [];
$handoverTeachers = [];
$handoverHistory = [];
$handoverHistorySchemaReady = false;
$attendanceConductorSchemaReady = false;
$handoverSchemaReady = false;
$handoverLoadError = false;
$historyLoadError = false;

try {
    $activeSchoolYear = aprismActiveSchoolYear($pdo);
    $handoverHistorySchemaReady = $pdo->query("SHOW TABLES LIKE 'operational_class_handovers'")->fetchColumn() !== false;
    $attendanceConductorSchemaReady = (bool) $pdo->query("SHOW COLUMNS FROM attendance_sessions LIKE 'conducted_by_user_id'")->fetch(PDO::FETCH_ASSOC);
    $handoverSchemaReady = $handoverHistorySchemaReady && $attendanceConductorSchemaReady;

    if ($activeSchoolYear !== null) {
        $classStmt = $pdo->prepare("SELECT oc.operational_class_id, oc.teacher_id,
                oc.school_year, oc.semester, oc.academic_level, s.subject_name,
                sec.section_name, CONCAT_WS(' ', u.first_name, u.last_name) AS teacher_name,
                u.employee_number AS teacher_employee_number
            FROM operational_classes oc
            INNER JOIN subjects s ON s.subject_id = oc.subject_id
            INNER JOIN sections sec ON sec.section_id = oc.section_id
            INNER JOIN users u ON u.user_id = oc.teacher_id
            WHERE oc.school_year = ? AND oc.status = 'Active'
            ORDER BY s.subject_name, sec.section_name, oc.semester, oc.operational_class_id");
        $classStmt->execute([(string) $activeSchoolYear['school_year']]);
        $handoverClasses = $classStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $handoverTeachers = $pdo->query("SELECT u.user_id, u.employee_number, CONCAT_WS(' ', u.first_name, u.last_name) AS teacher_name
        FROM users u
        INNER JOIN roles r ON r.role_id = u.role_id AND r.role_name = 'Teacher'
        WHERE u.account_status = 'Active'
        ORDER BY u.last_name, u.first_name, u.user_id")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log('[APRISM Class Handover Options] ' . $exception->getMessage());
    $handoverLoadError = true;
}

if ($handoverHistorySchemaReady) {
    try {
        $historyStmt = $pdo->query("SELECT h.operational_class_handover_id, h.effective_at, h.reason,
                oc.school_year, oc.semester, s.subject_name, sec.section_name,
                CONCAT_WS(' ', former.first_name, former.last_name) AS former_teacher,
                former.employee_number AS former_employee_number,
                CONCAT_WS(' ', replacement.first_name, replacement.last_name) AS replacement_teacher,
                replacement.employee_number AS replacement_employee_number,
                CONCAT_WS(' ', authorizer.first_name, authorizer.last_name) AS authorized_by
            FROM operational_class_handovers h
            INNER JOIN operational_classes oc ON oc.operational_class_id = h.operational_class_id
            INNER JOIN subjects s ON s.subject_id = oc.subject_id
            INNER JOIN sections sec ON sec.section_id = oc.section_id
            INNER JOIN users former ON former.user_id = h.from_teacher_id
            INNER JOIN users replacement ON replacement.user_id = h.to_teacher_id
            INNER JOIN users authorizer ON authorizer.user_id = h.authorized_by_user_id
            ORDER BY h.effective_at DESC, h.operational_class_handover_id DESC
            LIMIT 30");
        $handoverHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        error_log('[APRISM Class Handover History] ' . $exception->getMessage());
        $historyLoadError = true;
    }
}

$selectedHandoverClassId = (int) ($handoverForm['operational_class_id'] ?? 0);
$selectedHandoverClass = null;
foreach ($handoverClasses as $classOption) {
    if ((int) $classOption['operational_class_id'] === $selectedHandoverClassId) {
        $selectedHandoverClass = $classOption;
        break;
    }
}
$selectedReplacementId = (int) ($handoverForm['replacement_teacher_id'] ?? 0);
$handoverReasonCategories = [
    'personnel' => 'Personnel change',
    'assignment' => 'Teaching assignment',
    'other' => 'Other',
];
$handoverReasonOptions = [
    'personnel' => [
        'resignation' => 'Resignation',
        'leave_of_absence' => 'Leave of Absence',
    ],
    'assignment' => [
        'change_in_teaching_assignment' => 'Change in Teaching Assignment',
        'schedule_reassignment' => 'Schedule Reassignment',
    ],
];
$selectedReasonCategory = (string) ($handoverForm['reason_category'] ?? '');
$selectedReasonCode = (string) ($handoverForm['reason_code'] ?? '');
$selectedReasonDetails = (string) ($handoverForm['reason_details'] ?? '');
$showReasonDetails = !empty($handoverForm['show_reason_details']) || $selectedReasonCategory === 'other' || !empty($handoverErrors['reason_details']);
$displayEffectiveAt = static function (string $value): string {
    try {
        return (new DateTimeImmutable($value, new DateTimeZone(APRISM_TIMEZONE)))->format('M d, Y h:i A');
    } catch (Throwable) {
        return $value;
    }
};

$fullName = trim((string) ($_SESSION['first_name'] ?? '') . ' ' . (string) ($_SESSION['last_name'] ?? ''));
$initials = strtoupper(substr((string) ($_SESSION['first_name'] ?? ''), 0, 1) . substr((string) ($_SESSION['last_name'] ?? ''), 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<?php require __DIR__ . '/../includes/components/head.php'; ?>
<body class="technical-admin-handover-page">
    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>

    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>

        <section class="page-header">
            <div>
                <h1 class="page-title">Class Handover</h1>
                <p class="handover-page-context">Transfer an existing Operational Class to an active Teacher.</p>
            </div>
        </section>

        <section class="section-card handover-panel" aria-labelledby="handover-form-title">
            <header class="handover-panel-header">
                <div>
                    <h2 id="handover-form-title">Assign a replacement Teacher</h2>
                    <p>Select the class and review the transfer before confirming.</p>
                </div>
            </header>

            <?php if ($handoverLoadError): ?>
                <div class="handover-state-card handover-state-error" role="alert">
                    <i data-lucide="circle-alert" aria-hidden="true"></i>
                    <div><h3>Handover options unavailable</h3><p>Class and Teacher options could not be loaded. Refresh this page before continuing.</p></div>
                </div>
            <?php elseif (!$handoverSchemaReady): ?>
                <div class="handover-state-card" role="status">
                    <i data-lucide="database" aria-hidden="true"></i>
                    <div><h3>Transfers are temporarily unavailable</h3><p>The required handover history and Attendance records are not ready yet. No class responsibility or academic records have been changed.</p></div>
                </div>
            <?php elseif ($handoverClasses === []): ?>
                <div class="handover-state-card" role="status">
                    <i data-lucide="book-open-check" aria-hidden="true"></i>
                    <div><h3><?= $activeSchoolYear === null ? 'No active School Year' : 'No classes available' ?></h3><p><?= $activeSchoolYear === null ? 'An active School Year is not configured, so no class can be transferred.' : 'There are no active Operational Classes in the current School Year to transfer.' ?></p></div>
                </div>
            <?php else: ?>
                <?php if (!empty($handoverErrors['form'])): ?>
                    <div class="handover-state handover-state-error" role="alert"><?= htmlspecialchars((string) $handoverErrors['form'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <form method="post" action="<?= APP_URL ?>/actions/users/handover_operational_class.php" class="handover-form" id="classHandoverForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="handover_token" value="<?= htmlspecialchars((string) $_SESSION['class_handover_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="expected_teacher_id" id="handoverExpectedTeacher" value="<?= (int) ($selectedHandoverClass['teacher_id'] ?? 0) ?>">

                    <div class="handover-fields">
                        <div class="handover-field handover-field-class">
                            <label for="handoverClass">Operational Class</label>
                            <select id="handoverClass" name="operational_class_id" required aria-describedby="handoverClassError">
                                <option value="">Select an active class</option>
                                <?php foreach ($handoverClasses as $classOption):
                                    $classContext = implode(' · ', array_filter([
                                        (string) $classOption['subject_name'],
                                        (string) $classOption['section_name'],
                                        (string) $classOption['academic_level'],
                                        (string) $classOption['semester'],
                                        (string) $classOption['school_year'],
                                    ], static fn(string $part): bool => trim($part) !== ''));
                                ?>
                                    <option value="<?= (int) $classOption['operational_class_id'] ?>"
                                        data-owner-id="<?= (int) $classOption['teacher_id'] ?>"
                                        data-owner-name="<?= htmlspecialchars((string) $classOption['teacher_name'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-owner-employee-number="<?= htmlspecialchars((string) $classOption['teacher_employee_number'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-class-context="<?= htmlspecialchars($classContext, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= (int) $classOption['operational_class_id'] === $selectedHandoverClassId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($classContext . ' · ' . $classOption['teacher_name'] . ' (Employee ID ' . $classOption['teacher_employee_number'] . ')', ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($handoverErrors['operational_class_id'])): ?><small id="handoverClassError" class="handover-field-error"><?= htmlspecialchars((string) $handoverErrors['operational_class_id'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                            <p class="handover-current-teacher" id="handoverCurrentTeacher" hidden><strong>Current Teacher</strong> <span id="handoverCurrentTeacherName"></span></p>
                        </div>

                        <div class="handover-field">
                            <label for="handoverTeacher">Replacement Teacher</label>
                            <select id="handoverTeacher" name="replacement_teacher_id" required aria-describedby="handoverTeacherError">
                                <option value="">Select an active Teacher</option>
                                <?php foreach ($handoverTeachers as $teacher): ?>
                                    <option value="<?= (int) $teacher['user_id'] ?>" <?= (int) $teacher['user_id'] === $selectedReplacementId ? 'selected' : '' ?>><?= htmlspecialchars((string) $teacher['teacher_name'] . ' · Employee ID ' . $teacher['employee_number'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($handoverErrors['replacement_teacher_id'])): ?><small id="handoverTeacherError" class="handover-field-error"><?= htmlspecialchars((string) $handoverErrors['replacement_teacher_id'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                            <div class="handover-no-replacement" id="handoverNoReplacement" role="status" hidden>
                                <i data-lucide="user-round-x" aria-hidden="true"></i>
                                <span>No other active Teacher is available for this class.</span>
                            </div>
                        </div>

                        <fieldset class="handover-reason-fieldset" aria-describedby="handoverReasonCategoryError">
                            <legend>Reason for handover <b aria-hidden="true">*</b></legend>
                            <div class="handover-reason-options" id="handoverReasonOptions" role="group" aria-label="Reason category" aria-describedby="handoverReasonCategoryError">
                                <?php foreach ($handoverReasonCategories as $categoryCode => $categoryLabel): ?>
                                    <button type="button" class="handover-reason-option<?= $selectedReasonCategory === $categoryCode ? ' is-selected' : '' ?>" data-handover-category="<?= htmlspecialchars($categoryCode, ENT_QUOTES, 'UTF-8') ?>" aria-pressed="<?= $selectedReasonCategory === $categoryCode ? 'true' : 'false' ?>"><?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8') ?></button>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="reason_category" id="handoverReasonCategory" value="<?= htmlspecialchars($selectedReasonCategory, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="show_reason_details" id="handoverShowReasonDetails" value="<?= $showReasonDetails ? '1' : '0' ?>">
                            <?php if (!empty($handoverErrors['reason_category'])): ?><small id="handoverReasonCategoryError" class="handover-field-error" role="alert"><?= htmlspecialchars((string) $handoverErrors['reason_category'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>

                            <label class="handover-reason-specific" id="handoverSpecificReasonWrap" for="handoverSpecificReason"<?= in_array($selectedReasonCategory, ['personnel', 'assignment'], true) ? '' : ' hidden' ?>>
                                <span>Specific reason <b aria-hidden="true">*</b></span>
                                <select id="handoverSpecificReason" name="reason_code"<?= in_array($selectedReasonCategory, ['personnel', 'assignment'], true) ? ' required' : ' disabled' ?> aria-describedby="handoverReasonCodeError">
                                    <option value="">Select a specific reason</option>
                                    <?php foreach ($handoverReasonOptions as $reasonCategory => $reasonOptions): foreach ($reasonOptions as $reasonCode => $reasonLabel): ?>
                                        <option value="<?= htmlspecialchars($reasonCode, ENT_QUOTES, 'UTF-8') ?>" data-category="<?= htmlspecialchars($reasonCategory, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedReasonCode === $reasonCode ? 'selected' : '' ?>><?= htmlspecialchars($reasonLabel, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; endforeach; ?>
                                </select>
                                <?php if (!empty($handoverErrors['reason_code'])): ?><small id="handoverReasonCodeError" class="handover-field-error" role="alert"><?= htmlspecialchars((string) $handoverErrors['reason_code'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                            </label>

                            <div class="handover-reason-details" id="handoverReasonDetailsWrap"<?= $selectedReasonCategory === 'other' || ($showReasonDetails && $selectedReasonCategory !== '') ? '' : ' hidden' ?>>
                                <label for="handoverReasonDetails"><span>Brief operational explanation</span> <b id="handoverReasonDetailsRequired" aria-hidden="true"<?= $selectedReasonCategory === 'other' ? '' : ' hidden' ?>>*</b> <span class="handover-reason-optional" id="handoverReasonDetailsOptional"<?= $selectedReasonCategory === 'other' ? ' hidden' : '' ?>>(Optional)</span></label>
                                <textarea id="handoverReasonDetails" name="reason_details" rows="2" maxlength="900"<?= $selectedReasonCategory === 'other' ? ' required' : '' ?> aria-describedby="handoverReasonDetailsHelp handoverReasonDetailsError" placeholder="Keep this operational; do not include private personnel details."><?= htmlspecialchars($selectedReasonDetails, ENT_QUOTES, 'UTF-8') ?></textarea>
                                <small id="handoverReasonDetailsHelp">Do not include private or sensitive employment information.</small>
                                <?php if (!empty($handoverErrors['reason_details'])): ?><small id="handoverReasonDetailsError" class="handover-field-error" role="alert"><?= htmlspecialchars((string) $handoverErrors['reason_details'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                            </div>

                            <button type="button" class="handover-details-toggle" id="handoverDetailsToggle" aria-controls="handoverReasonDetailsWrap" aria-expanded="<?= $showReasonDetails ? 'true' : 'false' ?>"<?= $selectedReasonCategory === 'other' || $selectedReasonCategory === '' ? ' hidden' : '' ?>>Add brief details <span>(Optional)</span></button>
                        </fieldset>
                    </div>

                    <p class="handover-review-prompt" id="handoverReviewPrompt">Select a class, replacement Teacher, and reason to review the transfer.</p>
                    <section class="handover-review" id="handoverReview" aria-labelledby="handoverReviewTitle" aria-live="polite" hidden>
                        <div class="handover-review-heading">
                            <i data-lucide="clipboard-check" aria-hidden="true"></i>
                            <h3 id="handoverReviewTitle">Review transfer</h3>
                        </div>
                        <dl class="handover-review-details">
                            <div><dt>Class and context</dt><dd id="handoverReviewClass"><?= $selectedHandoverClass ? htmlspecialchars(implode(' · ', array_filter([(string) $selectedHandoverClass['subject_name'], (string) $selectedHandoverClass['section_name'], (string) $selectedHandoverClass['academic_level'], (string) $selectedHandoverClass['semester'], (string) $selectedHandoverClass['school_year']], static fn(string $part): bool => trim($part) !== '')), ENT_QUOTES, 'UTF-8') : 'Select an Operational Class' ?></dd></div>
                            <div><dt>Current Teacher</dt><dd id="handoverReviewOriginal">—</dd></div>
                            <div><dt>Replacement Teacher</dt><dd id="handoverReviewReplacement">—</dd></div>
                            <div><dt>Reason</dt><dd id="handoverReviewReason">—</dd></div>
                            <div><dt>Effective</dt><dd>Immediately after confirmation</dd></div>
                        </dl>
                        <p class="handover-preservation-note">Student participation, schedules, Attendance, scores, and referrals stay with this class. Original authors and timestamps remain unchanged.</p>
                        <p class="handover-conflict-note">APRISM checks schedule conflicts and unfinished Attendance or Grade Import work when you confirm.</p>
                        <p class="handover-offline-note"><strong>Offline Attendance:</strong> Sync saved scans from the former Teacher's device before confirming. Unsynced scans remain on that device and cannot sync after transfer. APRISM cannot detect a queue stored on another device.</p>
                    </section>

                        <div id="handoverConfirmation" hidden>
                            <label class="handover-confirm">
                                <input type="checkbox" name="confirmation_intent" value="confirm_handover" required>
                                <span>I reviewed this transfer and confirmed the former Teacher's saved scans are synced or none are pending.</span>
                            </label>
                            <?php if (!empty($handoverErrors['confirmation_intent'])): ?><small class="handover-field-error"><?= htmlspecialchars((string) $handoverErrors['confirmation_intent'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                            <div class="handover-actions">
                                <button type="submit" class="modal-btn-action" id="handoverSubmit" disabled>Confirm Class Handover</button>
                            </div>
                        </div>
                </form>
            <?php endif; ?>
        </section>

        <section class="section-card handover-history" aria-labelledby="handover-history-title">
            <header class="handover-panel-header handover-history-header">
                <div>
                    <h2 id="handover-history-title">Handover History</h2>
                    <p>Most recent completed class transfers.</p>
                </div>
            </header>

            <?php if (!$handoverHistorySchemaReady): ?>
                <div class="handover-state-card" role="status">
                    <i data-lucide="history" aria-hidden="true"></i>
                    <div><h3>History is not available yet</h3><p>Completed transfers will appear here when handover history can be recorded.</p></div>
                </div>
            <?php elseif ($historyLoadError): ?>
                <div class="handover-state-card handover-state-error" role="alert">
                    <i data-lucide="circle-alert" aria-hidden="true"></i>
                    <div><h3>History unavailable</h3><p>Completed handovers could not be loaded. Refresh this page to try again.</p></div>
                </div>
            <?php elseif ($handoverHistory === []): ?>
                <div class="handover-empty-state" role="status">
                    <i data-lucide="history" aria-hidden="true"></i>
                    <h3>No handovers yet</h3>
                    <p>Completed class transfers will appear here.</p>
                </div>
            <?php else: ?>
                <div class="handover-history-table-wrap">
                    <table class="handover-history-table">
                        <thead><tr><th scope="col">Class</th><th scope="col">From Teacher</th><th scope="col">To Teacher</th><th scope="col">Effective</th><th scope="col">Reason</th><th scope="col">Authorized By</th></tr></thead>
                        <tbody>
                            <?php foreach ($handoverHistory as $record): ?>
                                <tr>
                                    <td data-label="Class"><strong><?= htmlspecialchars((string) $record['subject_name'], ENT_QUOTES, 'UTF-8') ?></strong><span><?= htmlspecialchars(implode(' · ', array_filter([(string) $record['section_name'], (string) $record['semester'], (string) $record['school_year']])), ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td data-label="From Teacher" class="handover-history-person"><strong><?= htmlspecialchars((string) $record['former_teacher'], ENT_QUOTES, 'UTF-8') ?></strong><span>Employee ID <?= htmlspecialchars((string) $record['former_employee_number'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td data-label="To Teacher" class="handover-history-person"><strong><?= htmlspecialchars((string) $record['replacement_teacher'], ENT_QUOTES, 'UTF-8') ?></strong><span>Employee ID <?= htmlspecialchars((string) $record['replacement_employee_number'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td data-label="Effective"><?= htmlspecialchars($displayEffectiveAt((string) $record['effective_at']), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Reason" class="handover-history-reason"><?= htmlspecialchars((string) $record['reason'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Authorized By"><?= htmlspecialchars((string) $record['authorized_by'], ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script src="<?= APP_URL ?>/assets/js/pages/technical-admin-class-handover.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/pages/technical-admin-class-handover.js') ?>" defer></script>
    <?php require_once __DIR__ . '/../includes/components/footer.php'; ?>
    <?php require __DIR__ . '/../includes/components/flash_toast.php'; ?>
</body>
</html>
