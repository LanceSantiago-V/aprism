<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/role_helper.php';
$allowedRoles = [ROLE_DISCIPLINARY_OFFICER];
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../includes/helper/monitoring_helper.php';
require_once __DIR__ . '/../includes/helper/referral_reason_helper.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
$studentId = $studentId !== false && $studentId !== null && $studentId > 0 ? (int) $studentId : 0;
$fromRiskMonitoring = ($_GET['source'] ?? '') === 'risk';
$returnQueryRaw = trim((string) ($_GET['return_query'] ?? ''));
$returnValues = [];
if ($returnQueryRaw !== '' && strlen($returnQueryRaw) <= 500) {
    parse_str($returnQueryRaw, $parsedReturnValues);
    if (is_array($parsedReturnValues)) {
        $returnValues = $parsedReturnValues;
    }
}
$returnParams = [];
$returnTier = is_scalar($returnValues['tier'] ?? null) ? (string) $returnValues['tier'] : '';
if (in_array($returnTier, ['3', '2', '1', 'Insufficient'], true)) { $returnParams['tier'] = $returnTier; }
$returnReferralStatus = is_scalar($returnValues['status'] ?? null) ? (string) $returnValues['status'] : '';
if (in_array($returnReferralStatus, ['None', 'Submitted', 'Returned', 'Accepted', 'Closed'], true)) { $returnParams['status'] = $returnReferralStatus; }
$returnCaseStatus = is_scalar($returnValues['case_status'] ?? null) ? (string) $returnValues['case_status'] : '';
if (in_array($returnCaseStatus, ['None', 'Open', 'Active', 'Closed'], true)) { $returnParams['case_status'] = $returnCaseStatus; }
$returnSearchValue = is_scalar($returnValues['search'] ?? null) ? trim((string) $returnValues['search']) : '';
$returnSearch = function_exists('mb_substr') ? mb_substr($returnSearchValue, 0, 100) : substr($returnSearchValue, 0, 100);
if ($returnSearch !== '') { $returnParams['search'] = $returnSearch; }
$returnPage = is_scalar($returnValues['page'] ?? null) ? filter_var($returnValues['page'], FILTER_VALIDATE_INT) : false;
if (is_int($returnPage) && $returnPage > 1) { $returnParams['page'] = min($returnPage, 100000); }
$backToQueueUrl = APP_URL . ($fromRiskMonitoring ? '/dashboard/guidance_risk_monitoring.php' : '/dashboard/guidance_referrals.php')
    . ($returnParams !== [] ? '?' . http_build_query($returnParams) : '');
$backLabel = $fromRiskMonitoring ? 'Back to Risk Monitoring' : 'Back to queue';

function guidanceProfileEsc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function guidanceProfileDateTime(mixed $value): string
{
    $timestamp = strtotime((string) $value);
    return $timestamp === false ? 'Not recorded' : date('M j, Y g:i A', $timestamp);
}

$classEvidence = $studentId > 0
    ? aprismMonitoringCurrentClassRiskEvidence($pdo, 'disciplinary', 0, $studentId)
    : [];
$riskSummaries = aprismMonitoringStudentRiskSummaries($classEvidence);
$risk = $riskSummaries[$studentId] ?? null;

$referralStmt = $pdo->prepare(
    "SELECT
        tr.teacher_referral_id,
        tr.reason_code,
        tr.reason,
        tr.evidence_snapshot_json,
        tr.status AS referral_status,
        tr.school_year_snapshot,
        tr.semester_snapshot,
        tr.created_at,
        tr.updated_at,
        CONCAT_WS(' ', teacher.first_name, teacher.last_name) AS teacher_name,
        subj.subject_name,
        sec.section_name,
        gc.guidance_case_id,
        gc.case_number,
        gc.case_status,
        gc.non_confidential_disposition,
        gc.opened_at,
        gc.closed_at
    FROM teacher_referrals tr
    INNER JOIN users teacher ON teacher.user_id = tr.teacher_id
    LEFT JOIN operational_classes oc ON oc.operational_class_id = tr.operational_class_id
    LEFT JOIN subjects subj ON subj.subject_id = oc.subject_id
    LEFT JOIN sections sec ON sec.section_id = oc.section_id
    LEFT JOIN guidance_cases gc ON gc.teacher_referral_id = tr.teacher_referral_id
    WHERE tr.student_id = ?
      AND (
        (gc.guidance_case_id IS NULL AND tr.status IN ('Submitted', 'Returned'))
        OR EXISTS (
            SELECT 1
            FROM guidance_case_assignments gca
            WHERE gca.guidance_case_id = gc.guidance_case_id
              AND gca.assigned_user_id = ?
              AND gca.ended_at IS NULL
        )
      )
    ORDER BY tr.created_at DESC, tr.teacher_referral_id DESC"
);
$referralStmt->execute([$studentId, $userId]);
$referrals = $referralStmt->fetchAll(PDO::FETCH_ASSOC);

$isCurrentRiskTarget = $risk !== null && (int) ($risk['tier'] ?? 0) > 0;
if ($studentId <= 0 || (!$isCurrentRiskTarget && $referrals === [])) {
    http_response_code(404);
    $pageTitle = 'Student profile unavailable';
    $activePage = $fromRiskMonitoring ? 'guidance_risk_monitoring' : 'guidance_referrals';
    $roleStylesheet = 'assets/css/teacher.css';
    $pageStylesheet = 'assets/css/pages/guidance.css';
    ?>
    <!doctype html><html lang="en"><?php require __DIR__ . '/../includes/components/head.php'; ?><body>
    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?><main class="main-content"><div class="main-wrapper">
    <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
    <section class="guidance-page guidance-case-unavailable-page"><header class="guidance-page-header"><div><p class="page-eyebrow">Student intervention</p><h1>Student profile unavailable</h1></div></header>
    <section class="guidance-panel guidance-unavailable-panel"><p>This student is not in your current risk or intervention scope.</p><a class="btn btn-secondary" href="<?= guidanceProfileEsc($backToQueueUrl) ?>"><?= guidanceProfileEsc($backLabel) ?></a></section></section>
    </div></main><?php require __DIR__ . '/../includes/components/footer.php'; ?></body></html>
    <?php exit;
}

$studentStmt = $pdo->prepare(
    "SELECT st.student_id, st.student_number,
            CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) AS student_name,
            st.status
     FROM students st
     WHERE st.student_id = ?
     LIMIT 1"
);
$studentStmt->execute([$studentId]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    http_response_code(404);
    exit('Student profile unavailable.');
}

$caseIds = [];
foreach ($referrals as $referral) {
    $caseId = (int) ($referral['guidance_case_id'] ?? 0);
    if ($caseId > 0) {
        $caseIds[] = $caseId;
    }
}
$caseIds = array_values(array_unique($caseIds));

$events = [];
$followups = [];
if ($caseIds !== []) {
    $placeholders = implode(',', array_fill(0, count($caseIds), '?'));
    $eventStmt = $pdo->prepare(
        "SELECT e.guidance_case_id, e.event_type, e.event_summary, e.created_at,
                CONCAT_WS(' ', u.first_name, u.last_name) AS actor_name
         FROM guidance_case_events e
         INNER JOIN users u ON u.user_id = e.actor_user_id
         WHERE e.guidance_case_id IN ({$placeholders})
         ORDER BY e.created_at DESC, e.guidance_case_event_id DESC"
    );
    $eventStmt->execute($caseIds);
    $events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);

    $followupStmt = $pdo->prepare(
        "SELECT f.guidance_followup_id, f.guidance_case_id, f.scheduled_at,
                f.followup_status, f.completion_note, f.completed_at, f.cancelled_at,
                CONCAT_WS(' ', u.first_name, u.last_name) AS assigned_name
         FROM guidance_followups f
         INNER JOIN users u ON u.user_id = f.assigned_user_id
         WHERE f.guidance_case_id IN ({$placeholders})
         ORDER BY f.scheduled_at DESC, f.guidance_followup_id DESC"
    );
    $followupStmt->execute($caseIds);
    $followups = $followupStmt->fetchAll(PDO::FETCH_ASSOC);
}

$assessmentRows = [];
$classEnrollmentIds = array_values(array_unique(array_map(
    static fn(array $row): int => (int) ($row['student_class_enrollment_id'] ?? 0),
    $classEvidence
)));
$classEnrollmentIds = array_values(array_filter($classEnrollmentIds, static fn(int $id): bool => $id > 0));
if ($classEnrollmentIds !== []) {
    $placeholders = implode(',', array_fill(0, count($classEnrollmentIds), '?'));
    $assessmentStmt = $pdo->prepare(
        "SELECT subj.subject_name, sec.section_name, ap.period_name,
                ac.category_name, cmp.component_code, cmp.component_name,
                cmp.max_score, sas.raw_score, sas.updated_at
         FROM student_assessment_scores sas
         INNER JOIN student_class_enrollments sce
            ON sce.student_class_enrollment_id = sas.student_class_enrollment_id
         INNER JOIN operational_classes oc ON oc.operational_class_id = sce.operational_class_id
         INNER JOIN subjects subj ON subj.subject_id = oc.subject_id
         INNER JOIN sections sec ON sec.section_id = oc.section_id
         INNER JOIN assessment_components cmp ON cmp.assessment_component_id = sas.assessment_component_id
         INNER JOIN assessment_categories ac ON ac.assessment_category_id = cmp.assessment_category_id
         INNER JOIN academic_periods ap ON ap.academic_period_id = ac.academic_period_id
         LEFT JOIN grade_import_batches gib
            ON gib.grade_import_batch_id = sas.originating_grade_import_batch_id
         WHERE sas.student_class_enrollment_id IN ({$placeholders})
           AND cmp.status = 'Active'
           AND ac.status = 'Active'
           AND (gib.grade_import_batch_id IS NULL OR gib.status = 'Confirmed')
         ORDER BY sas.updated_at DESC, subj.subject_name, ap.start_date DESC, ac.display_order, cmp.display_order
         LIMIT 20"
    );
    $assessmentStmt->execute($classEnrollmentIds);
    $assessmentRows = $assessmentStmt->fetchAll(PDO::FETCH_ASSOC);
}

$timeline = [];
foreach ($referrals as $referral) {
    $timeline[] = [
        'at' => $referral['created_at'],
        'title' => 'Referral ' . $referral['referral_status'],
        'detail' => aprismReferralReasonDisplay($referral['reason_code'] ?? null, $referral['reason'] ?? null),
    ];
}
foreach ($events as $event) {
    $timeline[] = [
        'at' => $event['created_at'],
        'title' => $event['event_type'],
        'detail' => $event['event_summary'] . ' · ' . $event['actor_name'],
    ];
}
foreach ($followups as $followup) {
    $detail = $followup['followup_status'] . ' follow-up with ' . $followup['assigned_name'];
    if (trim((string) ($followup['completion_note'] ?? '')) !== '') {
        $detail .= ': ' . trim((string) $followup['completion_note']);
    }
    $timeline[] = ['at' => $followup['scheduled_at'], 'title' => 'Follow-up', 'detail' => $detail];
}
usort($timeline, static fn(array $left, array $right): int => strcmp((string) $right['at'], (string) $left['at']));

$latestReferral = $referrals[0] ?? null;
$latestCase = null;
foreach ($referrals as $referral) {
    if (!empty($referral['guidance_case_id'])) {
        $latestCase = $referral;
        break;
    }
}
$identityContexts = [];
foreach ($classEvidence as $evidence) {
    $context = implode(' · ', array_filter([
        (string) ($evidence['program_code'] ?? ''),
        (string) ($evidence['section_name'] ?? ''),
    ]));
    if ($context !== '') {
        $identityContexts[$context] = $context;
    }
}
if ($identityContexts === []) {
    foreach ($referrals as $referral) {
        $context = trim((string) ($referral['section_name'] ?? ''));
        if ($context !== '') {
            $identityContexts[$context] = $context;
        }
    }
}
$pageTitle = (string) $student['student_name'];
$activePage = $fromRiskMonitoring ? 'guidance_risk_monitoring' : 'guidance_referrals';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/guidance.css';
?>
<!doctype html>
<html lang="en">
<?php require __DIR__ . '/../includes/components/head.php'; ?>
<body>
<?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
<main class="main-content"><div class="main-wrapper">
<?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
<section class="guidance-page guidance-student-profile-page">
    <header class="guidance-page-header guidance-profile-header">
        <div><p class="page-eyebrow">Student Intervention Profile</p><h1><?= guidanceProfileEsc($student['student_name']) ?></h1><p class="guidance-context-meta"><?= guidanceProfileEsc($student['student_number']) ?><?php if ($identityContexts !== []): ?> · <?= guidanceProfileEsc(implode(', ', array_values($identityContexts))) ?><?php endif; ?></p></div>
        <a class="btn btn-secondary" href="<?= guidanceProfileEsc($backToQueueUrl) ?>"><?= guidanceProfileEsc($backLabel) ?></a>
    </header>

    <section class="guidance-panel guidance-profile-summary" aria-labelledby="profile-summary-title">
        <div><p class="guidance-section-kicker">Current orientation</p><h2 id="profile-summary-title">Why this student is here</h2></div>
        <dl class="guidance-profile-summary-grid">
            <div><dt>Current Tier</dt><dd><span class="guidance-risk-tier <?= (int) ($risk['tier'] ?? 0) > 0 ? 'tier-' . (int) $risk['tier'] : 'is-unassessable' ?>"><?= guidanceProfileEsc($risk['tier_label'] ?? 'Insufficient evidence') ?></span></dd></div>
            <div><dt>Attendance context</dt><dd><?= guidanceProfileEsc($risk['main_reason'] ?? 'Insufficient current attendance evidence') ?></dd></div>
            <div><dt>Referral</dt><dd><?= guidanceProfileEsc($latestReferral['referral_status'] ?? 'No referral') ?></dd></div>
            <div><dt>Case</dt><dd><?= guidanceProfileEsc($latestCase['case_status'] ?? 'No case') ?></dd></div>
        </dl>
    </section>

    <div class="guidance-profile-grid">
        <section class="guidance-panel guidance-profile-main" aria-labelledby="risk-evidence-title">
            <div class="guidance-panel-heading"><div><p class="guidance-section-kicker">Current class context</p><h2 id="risk-evidence-title">Attendance evidence</h2></div></div>
            <?php if ($classEvidence === []): ?>
                <div class="guidance-empty"><span>No current active class participation is available for attendance assessment.</span></div>
            <?php else: ?>
                <div class="guidance-profile-evidence-list">
                    <?php foreach ($classEvidence as $evidence): ?>
                        <article class="guidance-profile-evidence-item">
                            <div class="guidance-profile-evidence-heading"><div><strong><?= guidanceProfileEsc($evidence['subject_name']) ?></strong><small><?= guidanceProfileEsc($evidence['section_name'] . ' · ' . $evidence['semester']) ?></small></div><span class="guidance-risk-tier <?= (int) ($evidence['tier'] ?? 0) > 0 ? 'tier-' . (int) $evidence['tier'] : 'is-unassessable' ?>"><?= guidanceProfileEsc($evidence['tier_label']) ?></span></div>
                            <?php if (!$evidence['assessable']): ?><p>Insufficient evidence: no attendance record has been captured for this active class.</p><?php else: ?>
                                <dl class="guidance-evidence-metrics"><div><dt>Absences</dt><dd><?= number_format((int) $evidence['absent_count']) ?></dd></div><div><dt>Lates</dt><dd><?= number_format((int) $evidence['late_count']) ?></dd></div><div><dt>Effective absences</dt><dd><?= number_format((int) $evidence['effective_absent_count']) ?></dd></div><div><dt>Recorded sessions</dt><dd><?= number_format((int) $evidence['recorded_count']) ?></dd></div></dl>
                                <?php if ($evidence['has_consecutive_absences']): ?><p class="guidance-evidence-alert"><?= number_format((int) $evidence['consecutive_absence_count']) ?> consecutive recorded absences detected.</p><?php endif; ?>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <aside class="guidance-profile-side">
            <section class="guidance-panel" aria-labelledby="intervention-title">
                <div class="guidance-panel-heading"><div><p class="guidance-section-kicker">Human-led workflow</p><h2 id="intervention-title">Intervention</h2></div></div>
                <?php if ($referrals === []): ?><div class="guidance-empty"><span>No referral or assigned case exists. Risk signals do not create one automatically.</span></div><?php else: ?>
                    <div class="guidance-profile-intervention-list"><?php foreach ($referrals as $referral): ?><article><div><strong><?= guidanceProfileEsc($referral['referral_status']) ?> referral</strong><small><?= guidanceProfileEsc($referral['subject_name'] ?? 'Class context unavailable') ?> · <?= guidanceProfileEsc(guidanceProfileDateTime($referral['created_at'])) ?></small></div><p><?= guidanceProfileEsc(aprismReferralReasonDisplay($referral['reason_code'] ?? null, $referral['reason'] ?? null)) ?></p><?php $snapshotSummary = aprismTeacherReferralEvidenceSnapshotSummary($referral['evidence_snapshot_json'] ?? null); if ($snapshotSummary !== null): ?><small class="guidance-evidence-snapshot"><?= guidanceProfileEsc($snapshotSummary) ?></small><?php endif; ?><?php if (!empty($referral['guidance_case_id'])): ?><a class="btn btn-secondary" href="<?= APP_URL ?>/dashboard/guidance_case.php?case_id=<?= (int) $referral['guidance_case_id'] ?>&amp;return_to=student_profile#case-detail-header">Open <?= guidanceProfileEsc($referral['case_number']) ?></a><?php endif; ?></article><?php endforeach; ?></div>
                <?php endif; ?>
            </section>
        </aside>
    </div>

    <section class="guidance-panel" aria-labelledby="academic-evidence-title">
        <div class="guidance-panel-heading"><div><p class="guidance-section-kicker">Latest confirmed raw scores</p><h2 id="academic-evidence-title">Academic evidence</h2></div><span class="guidance-toolbar-meta">Up to 20 recent records · Does not change the Tier</span></div>
        <?php if ($assessmentRows === []): ?><div class="guidance-empty"><span>No confirmed raw-score evidence is available for the student's current active classes.</span></div><?php else: ?>
            <div class="guidance-table-wrap"><table class="guidance-table guidance-profile-assessment-table"><thead><tr><th>Class</th><th>Period</th><th>Assessment</th><th>Raw score</th><th>Updated</th></tr></thead><tbody><?php foreach ($assessmentRows as $assessment): ?><tr><td data-label="Class"><strong><?= guidanceProfileEsc($assessment['subject_name']) ?></strong><small><?= guidanceProfileEsc($assessment['section_name']) ?></small></td><td data-label="Period"><?= guidanceProfileEsc($assessment['period_name']) ?></td><td data-label="Assessment"><strong><?= guidanceProfileEsc($assessment['component_name']) ?></strong><small><?= guidanceProfileEsc($assessment['category_name']) ?></small></td><td data-label="Raw score"><?= guidanceProfileEsc($assessment['raw_score']) ?> / <?= guidanceProfileEsc($assessment['max_score']) ?></td><td data-label="Updated"><?= guidanceProfileEsc(guidanceProfileDateTime($assessment['updated_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    </section>

    <section class="guidance-panel" aria-labelledby="history-title">
        <div class="guidance-panel-heading"><div><p class="guidance-section-kicker">Referral, case and follow-up record</p><h2 id="history-title">History</h2></div></div>
        <?php if ($timeline === []): ?><div class="guidance-empty"><span>No intervention history is recorded.</span></div><?php else: ?><ol class="guidance-timeline guidance-profile-timeline"><?php foreach ($timeline as $item): ?><li><span class="guidance-timeline-marker" aria-hidden="true"></span><div><strong><?= guidanceProfileEsc($item['title']) ?></strong><time><?= guidanceProfileEsc(guidanceProfileDateTime($item['at'])) ?></time><p><?= guidanceProfileEsc($item['detail']) ?></p></div></li><?php endforeach; ?></ol><?php endif; ?>
    </section>
</section>
</div></main>
<?php require __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>
