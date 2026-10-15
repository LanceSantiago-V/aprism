<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/role_helper.php';
$allowedRoles = [ROLE_DISCIPLINARY_OFFICER];
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../auth/csrf_helper.php';
require_once __DIR__ . '/../includes/helper/monitoring_helper.php';
require_once __DIR__ . '/../includes/helper/referral_reason_helper.php';

$pageTitle = 'Risk & Referral Queue';
$activePage = 'guidance_referrals';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/guidance.css';
$csrf = generateCsrfToken();
$userId = (int) ($_SESSION['user_id'] ?? 0);

function guidanceReferralEsc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function guidanceReferralDateTime(mixed $value): string
{
    $timestamp = strtotime((string) $value);
    return $timestamp === false ? 'No recorded activity' : date('M j, Y g:i A', $timestamp);
}

$tierFilter = trim((string) ($_GET['tier'] ?? 'All'));
if (!in_array($tierFilter, ['All', '3', '2', '1', 'Insufficient'], true)) {
    $tierFilter = 'All';
}
$referralStatus = trim((string) ($_GET['status'] ?? 'All'));
if (!in_array($referralStatus, ['All', 'None', 'Submitted', 'Returned', 'Accepted', 'Closed'], true)) {
    $referralStatus = 'All';
}
$caseStatus = trim((string) ($_GET['case_status'] ?? 'All'));
if (!in_array($caseStatus, ['All', 'None', 'Open', 'Active', 'Closed'], true)) {
    $caseStatus = 'All';
}
$search = trim((string) ($_GET['search'] ?? ''));
$search = function_exists('mb_substr') ? mb_substr($search, 0, 100) : substr($search, 0, 100);

$classEvidence = aprismMonitoringCurrentClassRiskEvidence($pdo, 'disciplinary');
$riskByStudent = aprismMonitoringStudentRiskSummaries($classEvidence);

$referralStmt = $pdo->prepare(
    "SELECT
        tr.teacher_referral_id,
        tr.student_id,
        tr.reason_code,
        tr.reason,
        tr.evidence_snapshot_json,
        tr.status AS referral_status,
        tr.created_at,
        tr.updated_at,
        CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) AS student_name,
        st.student_number,
        CONCAT_WS(' ', teacher.first_name, teacher.last_name) AS teacher_name,
        subj.subject_name,
        sec.section_name,
        p.program_code,
        p.program_name,
        gc.guidance_case_id,
        gc.case_number,
        gc.case_status,
        COALESCE(case_activity.latest_event_at, gc.opened_at, tr.updated_at, tr.created_at) AS latest_activity_at,
        clarification.note AS clarification_note,
        clarification.created_at AS clarification_at
    FROM teacher_referrals tr
    INNER JOIN students st ON st.student_id = tr.student_id
    INNER JOIN users teacher ON teacher.user_id = tr.teacher_id
    LEFT JOIN operational_classes oc ON oc.operational_class_id = tr.operational_class_id
    LEFT JOIN subjects subj ON subj.subject_id = oc.subject_id
    LEFT JOIN sections sec ON sec.section_id = oc.section_id
    LEFT JOIN programs p ON p.program_id = sec.program_id
    LEFT JOIN guidance_cases gc ON gc.teacher_referral_id = tr.teacher_referral_id
    LEFT JOIN (
        SELECT guidance_case_id, MAX(created_at) AS latest_event_at
        FROM guidance_case_events
        GROUP BY guidance_case_id
    ) case_activity ON case_activity.guidance_case_id = gc.guidance_case_id
    LEFT JOIN teacher_referral_followups clarification
        ON clarification.referral_followup_id = (
            SELECT latest.referral_followup_id
            FROM teacher_referral_followups latest
            WHERE latest.teacher_referral_id = tr.teacher_referral_id
            ORDER BY latest.created_at DESC, latest.referral_followup_id DESC
            LIMIT 1
        )
    WHERE (
        (gc.guidance_case_id IS NULL AND tr.status IN ('Submitted', 'Returned'))
        OR EXISTS (
            SELECT 1
            FROM guidance_case_assignments gca
            WHERE gca.guidance_case_id = gc.guidance_case_id
              AND gca.assigned_user_id = ?
              AND gca.ended_at IS NULL
        )
    )"
);
$referralStmt->execute([$userId]);
$referralRows = $referralStmt->fetchAll(PDO::FETCH_ASSOC);

$queueRows = [];
$studentsWithVisibleReferral = [];
foreach ($referralRows as $row) {
    $studentId = (int) $row['student_id'];
    $studentsWithVisibleReferral[$studentId] = true;
    $risk = $riskByStudent[$studentId] ?? [
        'assessable' => false,
        'tier' => null,
        'tier_label' => 'Insufficient evidence',
        'main_reason' => 'Insufficient current attendance evidence',
        'contributing_classes' => [],
    ];
    $queueRows[] = array_merge($row, ['queue_kind' => 'referral', 'risk' => $risk]);
}

foreach ($riskByStudent as $studentId => $risk) {
    if ((int) ($risk['tier'] ?? 0) <= 0 || isset($studentsWithVisibleReferral[$studentId])) {
        continue;
    }
    $leadingClass = $risk['contributing_classes'][0] ?? [];
    $queueRows[] = [
        'queue_kind' => 'risk',
        'teacher_referral_id' => null,
        'student_id' => $studentId,
        'student_name' => (string) ($risk['student_name'] ?? ''),
        'student_number' => (string) ($risk['student_number'] ?? ''),
        'teacher_name' => null,
        'subject_name' => $leadingClass['subject_name'] ?? null,
        'section_name' => $leadingClass['section_name'] ?? null,
        'program_code' => $leadingClass['program_code'] ?? null,
        'program_name' => $leadingClass['program_name'] ?? null,
        'reason' => null,
        'referral_status' => null,
        'guidance_case_id' => null,
        'case_number' => null,
        'case_status' => null,
        'latest_activity_at' => $risk['latest_activity_at'] ?? null,
        'clarification_note' => null,
        'clarification_at' => null,
        'risk' => $risk,
    ];
}

$queueRows = array_values(array_filter($queueRows, static function (array $row) use ($tierFilter, $referralStatus, $caseStatus, $search): bool {
    $risk = $row['risk'];
    $tier = (int) ($risk['tier'] ?? 0);
    if ($tierFilter === 'Insufficient' && !empty($risk['assessable'])) {
        return false;
    }
    if (in_array($tierFilter, ['1', '2', '3'], true) && $tier !== (int) $tierFilter) {
        return false;
    }
    $rowReferralStatus = (string) ($row['referral_status'] ?? '');
    if ($referralStatus === 'None' && $rowReferralStatus !== '') {
        return false;
    }
    if (!in_array($referralStatus, ['All', 'None'], true) && $rowReferralStatus !== $referralStatus) {
        return false;
    }
    $rowCaseStatus = (string) ($row['case_status'] ?? '');
    if ($caseStatus === 'None' && $rowCaseStatus !== '') {
        return false;
    }
    if (!in_array($caseStatus, ['All', 'None'], true) && $rowCaseStatus !== $caseStatus) {
        return false;
    }
    if ($search !== '') {
        $haystack = implode(' ', [
            $row['student_number'] ?? '', $row['student_name'] ?? '',
            $row['program_code'] ?? '', $row['program_name'] ?? '',
            $row['section_name'] ?? '', $row['subject_name'] ?? '',
        ]);
        if (stripos($haystack, $search) === false) {
            return false;
        }
    }
    return true;
}));

usort($queueRows, static function (array $left, array $right): int {
    $tierOrder = (int) ($right['risk']['tier'] ?? 0) <=> (int) ($left['risk']['tier'] ?? 0);
    if ($tierOrder !== 0) {
        return $tierOrder;
    }
    $statusPriority = ['Submitted' => 0, 'Returned' => 1, 'Accepted' => 2, 'Closed' => 3, '' => 4];
    $statusOrder = ($statusPriority[(string) ($left['referral_status'] ?? '')] ?? 5)
        <=> ($statusPriority[(string) ($right['referral_status'] ?? '')] ?? 5);
    return $statusOrder !== 0
        ? $statusOrder
        : strcmp((string) ($right['latest_activity_at'] ?? ''), (string) ($left['latest_activity_at'] ?? ''));
});

$requestedReferralId = filter_var($_GET['referral_id'] ?? null, FILTER_VALIDATE_INT);
if (is_int($requestedReferralId) && $requestedReferralId > 0) {
    foreach ($queueRows as $index => $row) {
        if ((int) ($row['teacher_referral_id'] ?? 0) === $requestedReferralId) {
            $pageSize = 20;
            $currentPage = intdiv($index, $pageSize) + 1;
            break;
        }
    }
}

$totalRows = count($queueRows);
$pageSize = 20;
$totalPages = $totalRows > 0 ? (int) ceil($totalRows / $pageSize) : 0;
$currentPage = isset($currentPage) ? $currentPage : max(1, (int) ($_GET['page'] ?? 1));
$currentPage = $totalPages > 0 ? min($currentPage, $totalPages) : 1;
$visibleRows = array_slice($queueRows, ($currentPage - 1) * $pageSize, $pageSize);
$filterQuery = array_filter([
    'tier' => $tierFilter !== 'All' ? $tierFilter : null,
    'status' => $referralStatus !== 'All' ? $referralStatus : null,
    'case_status' => $caseStatus !== 'All' ? $caseStatus : null,
    'search' => $search !== '' ? $search : null,
], static fn(mixed $value): bool => $value !== null && $value !== '');
?>
<!doctype html>
<html lang="en">
<?php require __DIR__ . '/../includes/components/head.php'; ?>
<body>
<?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
<main class="main-content">
<div class="main-wrapper">
<?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
<section class="guidance-page guidance-worklist-page guidance-referral-queue-page">
    <header class="guidance-page-header">
        <div>
            <p class="page-eyebrow">Disciplinary Officer</p>
            <h1>Risk &amp; Referral Queue</h1>
            <p class="guidance-context-meta">Current attendance signals and referral work remain separate, with the highest class-level Tier shown for orientation.</p>
        </div>
    </header>

    <section class="guidance-panel guidance-toolbar guidance-intervention-toolbar">
        <form method="get" class="guidance-intervention-filter-form" role="search">
            <label class="guidance-label guidance-intervention-search" for="queue-search"><span>Student search</span><input id="queue-search" type="search" name="search" value="<?= guidanceReferralEsc($search) ?>" placeholder="Name, student number, program or class"></label>
            <label class="guidance-label" for="tier-filter"><span>Tier</span><select id="tier-filter" name="tier">
                <?php foreach (['All' => 'All tiers', '3' => 'Tier 3', '2' => 'Tier 2', '1' => 'Tier 1', 'Insufficient' => 'Insufficient evidence'] as $value => $label): ?>
                    <option value="<?= guidanceReferralEsc($value) ?>" <?= $tierFilter === $value ? 'selected' : '' ?>><?= guidanceReferralEsc($label) ?></option>
                <?php endforeach; ?>
            </select></label>
            <label class="guidance-label" for="referral-status"><span>Referral</span><select id="referral-status" name="status">
                <?php foreach (['All' => 'All referrals', 'None' => 'No referral', 'Submitted' => 'Submitted', 'Returned' => 'Returned', 'Accepted' => 'Accepted', 'Closed' => 'Closed'] as $value => $label): ?>
                    <option value="<?= guidanceReferralEsc($value) ?>" <?= $referralStatus === $value ? 'selected' : '' ?>><?= guidanceReferralEsc($label) ?></option>
                <?php endforeach; ?>
            </select></label>
            <label class="guidance-label" for="case-status"><span>Case</span><select id="case-status" name="case_status">
                <?php foreach (['All' => 'All cases', 'None' => 'No case', 'Open' => 'Open', 'Active' => 'Active', 'Closed' => 'Closed'] as $value => $label): ?>
                    <option value="<?= guidanceReferralEsc($value) ?>" <?= $caseStatus === $value ? 'selected' : '' ?>><?= guidanceReferralEsc($label) ?></option>
                <?php endforeach; ?>
            </select></label>
            <button class="btn btn-primary" type="submit">Apply</button>
            <?php if ($filterQuery !== []): ?><a class="btn btn-secondary" href="<?= APP_URL ?>/dashboard/guidance_referrals.php">Clear</a><?php endif; ?>
        </form>
    </section>

    <section class="guidance-panel guidance-worklist-panel">
        <div class="guidance-panel-heading">
            <div><p class="guidance-section-kicker">Prioritized worklist</p><h2>Students and referrals needing review</h2></div>
            <span class="guidance-toolbar-meta"><?= number_format($totalRows) ?> matching</span>
        </div>
        <div class="guidance-table-wrap">
            <table class="guidance-table guidance-referral-table guidance-intervention-table">
                <thead><tr><th>Student</th><th>Current risk</th><th>Referral</th><th>Case</th><th>Latest activity</th><th>Next action</th></tr></thead>
                <tbody>
                <?php foreach ($visibleRows as $row): ?>
                    <?php
                    $risk = $row['risk'];
                    $tier = (int) ($risk['tier'] ?? 0);
                    $referralId = (int) ($row['teacher_referral_id'] ?? 0);
                    $caseId = (int) ($row['guidance_case_id'] ?? 0);
                    $profileUrl = APP_URL . '/dashboard/guidance_student_profile.php?'
                        . http_build_query([
                            'student_id' => (int) $row['student_id'],
                            'return_query' => http_build_query(array_merge($filterQuery, ['page' => $currentPage])),
                        ]);
                    ?>
                    <tr id="<?= $referralId > 0 ? 'referral-' . $referralId : 'risk-student-' . (int) $row['student_id'] ?>">
                        <td data-label="Student"><strong><?= guidanceReferralEsc($row['student_name']) ?></strong><small><?= guidanceReferralEsc($row['student_number']) ?></small><small><?= guidanceReferralEsc(implode(' · ', array_filter([$row['program_code'] ?? '', $row['section_name'] ?? '']))) ?></small></td>
                        <td data-label="Current risk"><span class="guidance-risk-tier <?= $tier > 0 ? 'tier-' . $tier : 'is-unassessable' ?>"><?= guidanceReferralEsc($risk['tier_label'] ?? 'Insufficient evidence') ?></span><small class="guidance-risk-reason"><?= guidanceReferralEsc($risk['main_reason'] ?? 'Insufficient current attendance evidence') ?></small></td>
                        <td data-label="Referral">
                            <?php if (!empty($row['referral_status'])): ?>
                                <span class="guidance-status <?= strtolower((string) $row['referral_status']) ?>"><?= guidanceReferralEsc($row['referral_status']) ?></span><small><?= guidanceReferralEsc($row['teacher_name']) ?></small><small class="guidance-reason-cell"><?= guidanceReferralEsc(aprismReferralReasonDisplay($row['reason_code'] ?? null, $row['reason'] ?? null)) ?></small><?php $snapshotSummary = aprismTeacherReferralEvidenceSnapshotSummary($row['evidence_snapshot_json'] ?? null); if ($snapshotSummary !== null): ?><small class="guidance-evidence-snapshot"><?= guidanceReferralEsc($snapshotSummary) ?></small><?php endif; ?>
                                <?php if ($row['referral_status'] === 'Submitted' && $row['clarification_note'] !== null): ?><small class="guidance-referral-clarification">Clarification response: <?= guidanceReferralEsc($row['clarification_note']) ?></small><?php endif; ?>
                            <?php else: ?><span class="guidance-table-muted">No referral</span><?php endif; ?>
                        </td>
                        <td data-label="Case"><?php if (!empty($row['case_status'])): ?><span class="guidance-status <?= strtolower((string) $row['case_status']) ?>"><?= guidanceReferralEsc($row['case_status']) ?></span><small><?= guidanceReferralEsc($row['case_number']) ?></small><?php else: ?><span class="guidance-table-muted">No case</span><?php endif; ?></td>
                        <td data-label="Latest activity"><time><?= guidanceReferralEsc(guidanceReferralDateTime($row['latest_activity_at'])) ?></time></td>
                        <td data-label="Next action" class="guidance-actions guidance-intervention-actions">
                            <a class="btn btn-secondary" href="<?= guidanceReferralEsc($profileUrl) ?>">View student</a>
                            <?php if ($caseId > 0): ?>
                                <a class="btn btn-primary" href="<?= APP_URL ?>/dashboard/guidance_case.php?case_id=<?= $caseId ?>&amp;return_to=referrals#case-detail-header">Open case</a>
                            <?php elseif ($referralId > 0 && in_array($row['referral_status'], ['Submitted', 'Returned'], true)): ?>
                                <form method="post" action="<?= APP_URL ?>/actions/guidance/manage_case.php"><input type="hidden" name="csrf_token" value="<?= guidanceReferralEsc($csrf) ?>"><input type="hidden" name="operation" value="accept_referral"><input type="hidden" name="teacher_referral_id" value="<?= $referralId ?>"><button class="btn btn-primary" type="submit">Accept</button></form>
                                <?php if ($row['referral_status'] === 'Submitted'): ?>
                                    <form method="post" action="<?= APP_URL ?>/actions/guidance/manage_case.php" class="guidance-return-form"><input type="hidden" name="csrf_token" value="<?= guidanceReferralEsc($csrf) ?>"><input type="hidden" name="operation" value="return_referral"><input type="hidden" name="teacher_referral_id" value="<?= $referralId ?>"><label class="visually-hidden" for="return-reason-<?= $referralId ?>">Clarification reason</label><input id="return-reason-<?= $referralId ?>" name="reason" required placeholder="Clarification reason"><button class="btn btn-secondary" type="submit">Return</button></form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($visibleRows === []): ?><tr><td colspan="6" class="guidance-empty"><span>No students or referrals match these filters.</span></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
            <nav class="guidance-intervention-pagination" aria-label="Risk and referral queue pages">
                <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                    <?php $pageUrl = APP_URL . '/dashboard/guidance_referrals.php?' . http_build_query(array_merge($filterQuery, ['page' => $page])); ?>
                    <a class="btn <?= $page === $currentPage ? 'btn-primary' : 'btn-secondary' ?>" href="<?= guidanceReferralEsc($pageUrl) ?>" <?= $page === $currentPage ? 'aria-current="page"' : '' ?>><?= $page ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </section>
</section>
</div>
</main>
<?php require __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>
