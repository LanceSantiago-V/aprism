<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/role_helper.php';
$allowedRoles = [ROLE_DISCIPLINARY_OFFICER];
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../includes/helper/flash_message.php';
require_once __DIR__ . '/../auth/csrf_helper.php';
require_once __DIR__ . '/../includes/helper/monitoring_helper.php';
require_once __DIR__ . '/../includes/helper/referral_reason_helper.php';

$pageTitle = 'Referral Queue';
$activePage = 'guidance_referrals';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/guidance.css';
$additionalStylesheets = ['assets/css/pages/technical-admin-audit-logs.css'];
$csrf = generateCsrfToken();
$userId = (int) ($_SESSION['user_id'] ?? 0);

function guidanceReferralEsc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function guidanceReferralDateTime(mixed $value): string
{
    $timestamp = strtotime((string) $value);
    return $timestamp === false ? 'No recorded activity' : date('M j · g:i a', $timestamp);
}

$tierFilter = trim((string) ($_GET['tier'] ?? 'All'));
if (!in_array($tierFilter, ['All', '3', '2', '1', 'Insufficient'], true)) {
    $tierFilter = 'All';
}
$referralStatus = trim((string) ($_GET['status'] ?? 'All'));
if (!in_array($referralStatus, ['All', 'Submitted', 'Returned', 'Accepted', 'Closed'], true)) {
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
foreach ($referralRows as $row) {
    $studentId = (int) $row['student_id'];
    $risk = $riskByStudent[$studentId] ?? [
        'assessable' => false,
        'tier' => null,
        'tier_label' => 'Insufficient evidence',
        'main_reason' => 'Insufficient current attendance evidence',
        'contributing_classes' => [],
    ];
    $queueRows[] = array_merge($row, ['risk' => $risk]);
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
    if ($referralStatus !== 'All' && $rowReferralStatus !== $referralStatus) {
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

// Eight rows keep Referral Queue paging aligned with its compact review rows.
$pageSize = 8;
$requestedReferralId = filter_var($_GET['referral_id'] ?? null, FILTER_VALIDATE_INT);
if (is_int($requestedReferralId) && $requestedReferralId > 0) {
    foreach ($queueRows as $index => $row) {
        if ((int) ($row['teacher_referral_id'] ?? 0) === $requestedReferralId) {
            $currentPage = intdiv($index, $pageSize) + 1;
            break;
        }
    }
}

$totalRows = count($queueRows);
$totalPages = max(1, (int) ceil($totalRows / $pageSize));
$currentPage = isset($currentPage) ? $currentPage : max(1, (int) ($_GET['page'] ?? 1));
$currentPage = min(max(1, $currentPage), $totalPages);
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
<section class="guidance-page guidance-referral-queue-page">
    <header class="page-header">
        <h1 class="page-title">Referral Queue</h1>
        <span class="record-count"><?= number_format($totalRows) ?> matching</span>
    </header>

    <section class="audit-card guidance-referral-records" aria-label="Referral queue records">
        <form method="get" class="filter-form guidance-referral-toolbar" role="search">
            <div class="filter-group search-group">
                <label class="filter-label" for="queue-search">Search</label>
                <input id="queue-search" class="filter-control search" type="search" name="search" value="<?= guidanceReferralEsc($search) ?>" placeholder="Name, Student Number, program or class">
            </div>
            <div class="filter-group">
                <label class="filter-label" for="tier-filter">Tier</label>
                <select id="tier-filter" name="tier" class="filter-control">
                <?php foreach (['All' => 'All tiers', '3' => 'Tier 3', '2' => 'Tier 2', '1' => 'Tier 1', 'Insufficient' => 'Insufficient evidence'] as $value => $label): ?>
                    <option value="<?= guidanceReferralEsc($value) ?>" <?= $tierFilter === (string) $value ? 'selected' : '' ?>><?= guidanceReferralEsc($label) ?></option>
                <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label" for="referral-status">Referral</label>
                <select id="referral-status" name="status" class="filter-control">
                <?php foreach (['All' => 'All referrals', 'Submitted' => 'Submitted', 'Returned' => 'Returned', 'Accepted' => 'Accepted', 'Closed' => 'Closed'] as $value => $label): ?>
                    <option value="<?= guidanceReferralEsc($value) ?>" <?= $referralStatus === $value ? 'selected' : '' ?>><?= guidanceReferralEsc($label) ?></option>
                <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label" for="case-status">Case</label>
                <select id="case-status" name="case_status" class="filter-control">
                <?php foreach (['All' => 'All cases', 'None' => 'No case', 'Open' => 'Open', 'Active' => 'Active', 'Closed' => 'Closed'] as $value => $label): ?>
                    <option value="<?= guidanceReferralEsc($value) ?>" <?= $caseStatus === $value ? 'selected' : '' ?>><?= guidanceReferralEsc($label) ?></option>
                <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <button class="filter-btn apply" type="submit">Apply</button>
                <?php if ($filterQuery !== []): ?><a class="filter-btn clear" href="<?= APP_URL ?>/dashboard/guidance_referrals.php">Clear</a><?php endif; ?>
            </div>
        </form>

        <div class="audit-table-container guidance-referral-table-wrap">
            <table class="table audit-table guidance-referral-table align-middle">
                <thead><tr><th scope="col">Student</th><th scope="col">Current Risk</th><th scope="col">Referral Reason</th><th scope="col">Workflow Status</th><th scope="col">Latest Activity</th><th scope="col">Action</th></tr></thead>
                <tbody>
                <?php foreach ($visibleRows as $row): ?>
                    <?php
                    $risk = $row['risk'];
                    $tier = (int) ($risk['tier'] ?? 0);
                    $referralId = (int) ($row['teacher_referral_id'] ?? 0);
                    $caseId = (int) ($row['guidance_case_id'] ?? 0);
                    $reasonLabel = aprismReferralReasonLabel($row['reason_code'] ?? null);
                    $reasonNote = trim((string) ($row['reason'] ?? ''));
                    $snapshotSummary = aprismTeacherReferralEvidenceSnapshotSummary($row['evidence_snapshot_json'] ?? null);
                    $currentRiskEvidence = (string) ($risk['main_reason'] ?? 'Insufficient current attendance evidence');
                    $profileUrl = APP_URL . '/dashboard/guidance_student_profile.php?'
                        . http_build_query([
                            'student_id' => (int) $row['student_id'],
                            'return_query' => http_build_query(array_merge($filterQuery, ['page' => $currentPage])),
                        ]);
                    ?>
                    <tr id="referral-<?= $referralId ?>">
                        <td data-label="Student"><div class="audit-user-value guidance-referral-mobile-value"><p class="audit-user"><?= guidanceReferralEsc($row['student_name']) ?></p><small class="audit-employee"><?= guidanceReferralEsc($row['student_number']) ?></small><small class="guidance-referral-placement"><?= guidanceReferralEsc(implode(' · ', array_filter([$row['program_code'] ?? '', $row['section_name'] ?? '']))) ?></small></div></td>
                        <td data-label="Current Risk"><div class="guidance-referral-mobile-value"><span class="guidance-risk-tier <?= $tier > 0 ? 'tier-' . $tier : 'is-unassessable' ?>"><?= guidanceReferralEsc($risk['tier_label'] ?? 'Insufficient evidence') ?></span><small class="guidance-referral-risk-evidence"><?= guidanceReferralEsc($tier > 0 ? $currentRiskEvidence : 'Insufficient attendance evidence') ?></small></div></td>
                        <td data-label="Referral Reason"><div class="guidance-referral-mobile-value">
                            <span class="guidance-referral-reason-label"><?= guidanceReferralEsc($reasonLabel) ?></span>
                        </div></td>
                        <td data-label="Workflow Status"><div class="guidance-referral-mobile-value guidance-referral-workflow">
                            <span class="guidance-status <?= strtolower((string) $row['referral_status']) ?>"><?= guidanceReferralEsc($row['referral_status']) ?></span>
                            <?php if ($caseId > 0): ?><small>Case <?= guidanceReferralEsc($row['case_status']) ?></small>
                            <?php elseif ($row['referral_status'] === 'Submitted'): ?><small>Awaiting review</small>
                            <?php elseif ($row['referral_status'] === 'Accepted'): ?><small>No case opened</small><?php endif; ?>
                        </div></td>
                        <td data-label="Latest Activity"><div class="guidance-referral-mobile-value"><time title="<?= guidanceReferralEsc(date('M j, Y · g:i A', strtotime((string) $row['latest_activity_at']))) ?>"><?= guidanceReferralEsc(guidanceReferralDateTime($row['latest_activity_at'])) ?></time></div></td>
                        <td data-label="Action" class="guidance-referral-actions"><div class="guidance-referral-mobile-actions">
                            <?php if ($caseId > 0): ?>
                                <a class="filter-btn clear guidance-referral-case-link" href="<?= APP_URL ?>/dashboard/guidance_case.php?case_id=<?= $caseId ?>&amp;return_to=referrals#case-detail-header">View Case</a>
                            <?php elseif (in_array($row['referral_status'], ['Submitted', 'Returned'], true)): ?>
                                <button class="filter-btn clear guidance-referral-review-trigger" type="button" popovertarget="referral-review-<?= $referralId ?>">Review</button>
                                <div class="guidance-referral-review-panel" id="referral-review-<?= $referralId ?>" popover="auto" role="region" aria-labelledby="referral-review-title-<?= $referralId ?>">
                                        <header><h2 id="referral-review-title-<?= $referralId ?>">Review referral</h2><button class="guidance-referral-review-close" type="button" popovertarget="referral-review-<?= $referralId ?>" popovertargetaction="hide" aria-label="Close referral review">×</button></header>
                                        <dl>
                                            <div><dt>Referred by</dt><dd><?= guidanceReferralEsc($row['teacher_name']) ?></dd></div>
                                            <div><dt>Reason</dt><dd><?= guidanceReferralEsc($reasonLabel) ?></dd></div>
                                            <?php if ($reasonNote !== ''): ?><div><dt>Teacher note</dt><dd><?= nl2br(guidanceReferralEsc($reasonNote)) ?></dd></div><?php endif; ?>
                                            <div><dt>Class</dt><dd><?= guidanceReferralEsc(trim(implode(' · ', array_filter([$row['subject_name'] ?? '', $row['section_name'] ?? '']))) ?: 'Class context unavailable') ?></dd></div>
                                            <div><dt>Submitted</dt><dd><?= guidanceReferralEsc(guidanceReferralDateTime($row['created_at'])) ?></dd></div>
                                            <?php if ($snapshotSummary !== null): ?><div><dt>At referral submission</dt><dd><?= guidanceReferralEsc($snapshotSummary) ?></dd></div><?php endif; ?>
                                            <?php if (!empty($row['clarification_note'])): ?><div><dt>Clarification requested</dt><dd><?= nl2br(guidanceReferralEsc($row['clarification_note'])) ?></dd></div><?php endif; ?>
                                        </dl>
                                        <a class="guidance-referral-student-link" href="<?= guidanceReferralEsc($profileUrl) ?>">View Student</a>
                                        <div class="guidance-referral-review-actions">
                                            <form method="post" action="<?= APP_URL ?>/actions/guidance/manage_case.php" class="guidance-referral-action-form"><input type="hidden" name="csrf_token" value="<?= guidanceReferralEsc($csrf) ?>"><input type="hidden" name="operation" value="accept_referral"><input type="hidden" name="teacher_referral_id" value="<?= $referralId ?>"><button class="filter-btn apply" type="submit">Accept and open case</button></form>
                                            <?php if ($row['referral_status'] === 'Submitted'): ?>
                                                <details class="guidance-referral-return"><summary class="filter-btn clear">Return</summary><form method="post" action="<?= APP_URL ?>/actions/guidance/manage_case.php" class="guidance-referral-return-form"><input type="hidden" name="csrf_token" value="<?= guidanceReferralEsc($csrf) ?>"><input type="hidden" name="operation" value="return_referral"><input type="hidden" name="teacher_referral_id" value="<?= $referralId ?>"><label class="filter-label" for="return-reason-<?= $referralId ?>">Reason for returning</label><textarea id="return-reason-<?= $referralId ?>" name="reason" required></textarea><button class="filter-btn apply" type="submit">Send back</button></form></details>
                                            <?php endif; ?>
                                        </div>
                                </div>
                            <?php else: ?>
                                <a class="filter-btn clear guidance-referral-student-link" href="<?= guidanceReferralEsc($profileUrl) ?>">View Student</a>
                            <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($visibleRows === []): ?><tr><td colspan="6" class="guidance-empty"><span>No referrals match these filters.</span></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="pagination-container guidance-referral-pagination" aria-live="polite" <?= $totalRows === 0 ? 'hidden' : '' ?>>
            <div class="pagination-info">Showing <?= number_format($totalRows === 0 ? 0 : (($currentPage - 1) * $pageSize) + 1) ?> to <?= number_format(min($currentPage * $pageSize, $totalRows)) ?> of <?= number_format($totalRows) ?> matching</div>
            <?php if ($totalPages > 1): ?>
                <nav class="pagination-controls" aria-label="Referral queue pages">
                    <?php if ($currentPage <= 1): ?><span class="page-link-custom disabled" aria-label="First page" aria-disabled="true" title="First page"><i data-lucide="chevrons-left" aria-hidden="true"></i></span><?php else: ?><a href="<?= guidanceReferralEsc(APP_URL . '/dashboard/guidance_referrals.php?' . http_build_query(array_merge($filterQuery, ['page' => 1]))) ?>" class="page-link-custom" aria-label="First page" title="First page" data-aprism-tooltip="First page"><i data-lucide="chevrons-left" aria-hidden="true"></i></a><?php endif; ?>
                    <?php if ($currentPage <= 1): ?><span class="page-link-custom disabled" aria-label="Previous page" aria-disabled="true" title="Previous page"><i data-lucide="chevron-left" aria-hidden="true"></i></span><?php else: ?><a href="<?= guidanceReferralEsc(APP_URL . '/dashboard/guidance_referrals.php?' . http_build_query(array_merge($filterQuery, ['page' => $currentPage - 1]))) ?>" class="page-link-custom" aria-label="Previous page" title="Previous page" data-aprism-tooltip="Previous page"><i data-lucide="chevron-left" aria-hidden="true"></i></a><?php endif; ?>
                    <?php $startPage = max(1, min($currentPage - 1, $totalPages - 2)); $endPage = min($totalPages, $startPage + 2); ?>
                    <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
                        <a href="<?= guidanceReferralEsc(APP_URL . '/dashboard/guidance_referrals.php?' . http_build_query(array_merge($filterQuery, ['page' => $page]))) ?>" class="page-link-custom <?= $page === $currentPage ? 'active' : '' ?>" aria-label="Page <?= $page ?>" <?= $page === $currentPage ? 'aria-current="page"' : '' ?>><?= $page ?></a>
                    <?php endfor; ?>
                    <?php if ($currentPage >= $totalPages): ?><span class="page-link-custom disabled" aria-label="Next page" aria-disabled="true" title="Next page"><i data-lucide="chevron-right" aria-hidden="true"></i></span><?php else: ?><a href="<?= guidanceReferralEsc(APP_URL . '/dashboard/guidance_referrals.php?' . http_build_query(array_merge($filterQuery, ['page' => $currentPage + 1]))) ?>" class="page-link-custom" aria-label="Next page" title="Next page" data-aprism-tooltip="Next page"><i data-lucide="chevron-right" aria-hidden="true"></i></a><?php endif; ?>
                    <?php if ($currentPage >= $totalPages): ?><span class="page-link-custom disabled" aria-label="Last page" aria-disabled="true" title="Last page"><i data-lucide="chevrons-right" aria-hidden="true"></i></span><?php else: ?><a href="<?= guidanceReferralEsc(APP_URL . '/dashboard/guidance_referrals.php?' . http_build_query(array_merge($filterQuery, ['page' => $totalPages]))) ?>" class="page-link-custom" aria-label="Last page" title="Last page" data-aprism-tooltip="Last page"><i data-lucide="chevrons-right" aria-hidden="true"></i></a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
    </section>
</section>
</div>
</main>
<?php require __DIR__ . '/../includes/components/flash_toast.php'; ?>
<?php require __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>
