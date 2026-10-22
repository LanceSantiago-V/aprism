<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/role_helper.php';
$allowedRoles = [ROLE_DISCIPLINARY_OFFICER];
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../includes/helper/monitoring_helper.php';

$pageTitle = 'Risk Monitoring';
$activePage = 'guidance_risk_monitoring';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/guidance.css';
$additionalStylesheets = ['assets/css/pages/technical-admin-audit-logs.css'];
$userId = (int) ($_SESSION['user_id'] ?? 0);

function guidanceRiskEsc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$tierFilter = is_scalar($_GET['tier'] ?? null) ? (string) $_GET['tier'] : 'All';
if (!in_array($tierFilter, ['All', '3', '2', '1'], true)) {
    $tierFilter = 'All';
}
$searchRaw = is_scalar($_GET['search'] ?? null) ? trim((string) $_GET['search']) : '';
$search = function_exists('mb_substr') ? mb_substr($searchRaw, 0, 100) : substr($searchRaw, 0, 100);
$requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$requestedPage = is_int($requestedPage) && $requestedPage > 0 ? $requestedPage : 1;

$classEvidence = aprismMonitoringCurrentClassRiskEvidence($pdo, 'disciplinary');
$riskByStudent = aprismMonitoringStudentRiskSummaries($classEvidence);
$tierCounts = [3 => 0, 2 => 0, 1 => 0];
$riskRows = [];
$currentClassIds = [];
foreach ($riskByStudent as $studentId => $risk) {
    $tier = (int) ($risk['tier'] ?? 0);
    if (!isset($tierCounts[$tier])) {
        continue;
    }
    $tierCounts[$tier]++;
    $leadingClass = $risk['contributing_classes'][0] ?? [];
    foreach ($risk['class_evidence'] as $evidence) {
        $classId = (int) ($evidence['operational_class_id'] ?? 0);
        if ($classId > 0) {
            $currentClassIds[$classId] = $classId;
        }
    }
    $riskRows[$studentId] = [
        'student_id' => (int) $studentId,
        'student_name' => (string) ($risk['student_name'] ?? ''),
        'student_number' => (string) ($risk['student_number'] ?? ''),
        'program_code' => (string) ($leadingClass['program_code'] ?? ''),
        'section_name' => (string) ($leadingClass['section_name'] ?? ''),
        'subject_name' => (string) ($leadingClass['subject_name'] ?? ''),
        'tier' => $tier,
        'main_reason' => (string) ($risk['main_reason'] ?? 'Attendance concern'),
        'referral_status' => null,
        'case_status' => null,
    ];
}

// A referral is optional context for a risk record, never a prerequisite.
// Mirror the DO's existing visibility rule: pending referrals or assigned cases.
if ($riskRows !== [] && $currentClassIds !== []) {
    $studentPlaceholders = implode(',', array_fill(0, count($riskRows), '?'));
    $classPlaceholders = implode(',', array_fill(0, count($currentClassIds), '?'));
    $workflowStmt = $pdo->prepare(
        "SELECT tr.student_id, tr.status AS referral_status, gc.case_status
         FROM teacher_referrals tr
         LEFT JOIN guidance_cases gc ON gc.teacher_referral_id = tr.teacher_referral_id
         WHERE tr.student_id IN ($studentPlaceholders)
           AND tr.operational_class_id IN ($classPlaceholders)
           AND (
               (gc.guidance_case_id IS NULL AND tr.status IN ('Submitted', 'Returned'))
               OR EXISTS (
                   SELECT 1 FROM guidance_case_assignments gca
                   WHERE gca.guidance_case_id = gc.guidance_case_id
                     AND gca.assigned_user_id = ?
                     AND gca.ended_at IS NULL
               )
           )
         ORDER BY (gc.case_status = 'Active') DESC,
                  (gc.case_status = 'Open') DESC,
                  tr.updated_at DESC, tr.teacher_referral_id DESC"
    );
    $workflowStmt->execute(array_merge(array_keys($riskRows), array_values($currentClassIds), [$userId]));
    $seenWorkflowStudents = [];
    foreach ($workflowStmt->fetchAll(PDO::FETCH_ASSOC) as $workflow) {
        $studentId = (int) $workflow['student_id'];
        if (isset($seenWorkflowStudents[$studentId])) {
            continue;
        }
        $seenWorkflowStudents[$studentId] = true;
        $riskRows[$studentId]['referral_status'] = $workflow['referral_status'];
        $riskRows[$studentId]['case_status'] = $workflow['case_status'];
    }
}

$visibleRisk = array_values(array_filter($riskRows, static function (array $row) use ($tierFilter, $search): bool {
    if ($tierFilter !== 'All' && $row['tier'] !== (int) $tierFilter) {
        return false;
    }
    if ($search === '') {
        return true;
    }
    return stripos($row['student_name'] . ' ' . $row['student_number'], $search) !== false;
}));
usort($visibleRisk, static fn(array $a, array $b): int =>
    ($b['tier'] <=> $a['tier'])
        ?: strcasecmp($a['student_name'], $b['student_name'])
        ?: ($a['student_id'] <=> $b['student_id'])
);

// Seven rows keep the current risk worklist bounded while preserving readable row density.
$pageSize = 7;
$totalRows = count($visibleRisk);
$totalPages = max(1, (int) ceil($totalRows / $pageSize));
$currentPage = min($requestedPage, $totalPages);
$firstRow = $totalRows === 0 ? 0 : (($currentPage - 1) * $pageSize) + 1;
$lastRow = min($currentPage * $pageSize, $totalRows);
$pageRows = array_slice($visibleRisk, ($currentPage - 1) * $pageSize, $pageSize);
$filterQuery = array_filter([
    'tier' => $tierFilter !== 'All' ? $tierFilter : null,
    'search' => $search !== '' ? $search : null,
], static fn(?string $value): bool => $value !== null);
$pageUrl = static fn(int $page): string => APP_URL . '/dashboard/guidance_risk_monitoring.php?'
    . http_build_query(array_merge($filterQuery, ['page' => $page]));
$profileReturnQuery = http_build_query(array_merge($filterQuery, ['page' => $currentPage]));
?>
<!doctype html>
<html lang="en">
<?php require __DIR__ . '/../includes/components/head.php'; ?>
<body>
<?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
<main class="main-content"><div class="main-wrapper">
<?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
<section class="guidance-page guidance-risk-monitoring-page">
    <header class="page-header">
        <div><h1 class="page-title">Risk Monitoring</h1></div>
        <div class="record-count"><?= number_format(array_sum($tierCounts)) ?> students</div>
    </header>

    <section class="audit-card guidance-risk-monitoring-records" aria-label="Students at Risk">
      <div class="filter-form guidance-risk-monitoring-toolbar">
        <nav class="guidance-risk-monitoring-tabs" aria-label="Risk tier">
            <?php foreach (['All' => ['All Tiers', array_sum($tierCounts)], '3' => ['Tier 3', $tierCounts[3]], '2' => ['Tier 2', $tierCounts[2]], '1' => ['Tier 1', $tierCounts[1]]] as $tierValue => [$label, $count]):
                $tierUrl = APP_URL . '/dashboard/guidance_risk_monitoring.php'
                    . ($search !== '' || $tierValue !== 'All'
                        ? '?' . http_build_query(array_filter(['tier' => $tierValue !== 'All' ? $tierValue : null, 'search' => $search !== '' ? $search : null]))
                        : '');
            ?>
                <a href="<?= guidanceRiskEsc($tierUrl) ?>" <?= $tierFilter === (string) $tierValue ? 'aria-current="page"' : '' ?>><?= guidanceRiskEsc($label) ?> <span><?= (int) $count ?></span></a>
            <?php endforeach; ?>
        </nav>
        <form method="get" role="search" class="guidance-risk-monitoring-search">
            <?php if ($tierFilter !== 'All'): ?><input type="hidden" name="tier" value="<?= guidanceRiskEsc($tierFilter) ?>"><?php endif; ?>
            <label class="visually-hidden" for="risk-student-search">Search by Student name or Student Number</label>
            <input id="risk-student-search" class="filter-control guidance-risk-search-input" type="search" name="search" value="<?= guidanceRiskEsc($search) ?>" placeholder="Search name or Student Number">
            <button class="filter-btn apply" type="submit">Search</button>
            <?php if ($search !== ''): ?><a class="filter-btn clear" href="<?= APP_URL ?>/dashboard/guidance_risk_monitoring.php<?= $tierFilter !== 'All' ? '?tier=' . guidanceRiskEsc($tierFilter) : '' ?>">Clear</a><?php endif; ?>
        </form>
      </div>

        <div class="audit-table-container guidance-risk-monitoring-table-wrap">
            <table class="table audit-table guidance-risk-monitoring-table align-middle">
                <caption class="visually-hidden">Students with a current attendance risk tier</caption>
                <thead><tr><th scope="col">Student</th><th scope="col">Program / Section</th><th scope="col">Class</th><th scope="col">Current Risk</th><th scope="col">Referral / Case</th><th scope="col">Action</th></tr></thead>
                <tbody>
                <?php foreach ($pageRows as $row):
                    $profileUrl = APP_URL . '/dashboard/guidance_student_profile.php?' . http_build_query([
                        'student_id' => $row['student_id'],
                        'source' => 'risk',
                        'return_query' => $profileReturnQuery,
                    ]);
                ?>
                    <tr>
                        <td data-label="Student"><div class="audit-user-value"><strong class="audit-user"><?= guidanceRiskEsc($row['student_name']) ?></strong><small class="audit-employee"><?= guidanceRiskEsc($row['student_number']) ?></small></div></td>
                        <td data-label="Program / Section"><?= guidanceRiskEsc(implode(' · ', array_filter([$row['program_code'], $row['section_name']])) ?: 'Not recorded') ?></td>
                        <td data-label="Class"><?= guidanceRiskEsc($row['subject_name'] ?: 'Class context unavailable') ?></td>
                        <td data-label="Current Risk"><div class="guidance-risk-cell"><span class="guidance-risk-tier tier-<?= $row['tier'] ?>">Tier <?= $row['tier'] ?></span><small><?= guidanceRiskEsc($row['main_reason']) ?></small></div></td>
                        <td data-label="Referral / Case"><div class="guidance-risk-cell">
                            <?php if ($row['referral_status'] !== null): ?>
                                <span><?= guidanceRiskEsc($row['referral_status']) ?> referral</span>
                                <?php if ($row['case_status'] !== null): ?><small><?= guidanceRiskEsc($row['case_status']) ?> case</small><?php endif; ?>
                            <?php else: ?><span class="guidance-table-muted">No referral or case</span><?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Action" class="text-center"><a class="details-view-btn" href="<?= guidanceRiskEsc($profileUrl) ?>" aria-label="View risk detail for <?= guidanceRiskEsc($row['student_name']) ?>" title="View risk details" data-aprism-tooltip="View risk details"><i data-lucide="eye" aria-hidden="true"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($pageRows === []): ?><tr class="guidance-risk-empty-row"><td colspan="6"><?= $search !== '' || $tierFilter !== 'All' ? 'No Students match these filters.' : 'No Students currently have an attendance risk tier.' ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="pagination-container guidance-risk-monitoring-pagination" aria-live="polite" <?= $totalRows === 0 ? 'hidden' : '' ?>>
            <div class="pagination-info">Showing <?= number_format($firstRow) ?> to <?= number_format($lastRow) ?> of <?= number_format($totalRows) ?> students</div>
            <?php if ($totalPages > 1): ?>
                <?php
                $startPage = max(1, min($currentPage - 1, $totalPages - 2));
                $endPage = min($totalPages, $startPage + 2);
                ?>
                <nav class="pagination-controls" aria-label="Risk Monitoring pages">
                    <?php if ($currentPage <= 1): ?><span class="page-link-custom disabled" aria-label="First page" aria-disabled="true" title="First page"><i data-lucide="chevrons-left" aria-hidden="true"></i></span><?php else: ?><a href="<?= guidanceRiskEsc($pageUrl(1)) ?>" class="page-link-custom" aria-label="First page" title="First page" data-aprism-tooltip="First page"><i data-lucide="chevrons-left" aria-hidden="true"></i></a><?php endif; ?>
                    <?php if ($currentPage <= 1): ?><span class="page-link-custom disabled" aria-label="Previous page" aria-disabled="true" title="Previous page"><i data-lucide="chevron-left" aria-hidden="true"></i></span><?php else: ?><a href="<?= guidanceRiskEsc($pageUrl($currentPage - 1)) ?>" class="page-link-custom" aria-label="Previous page" title="Previous page" data-aprism-tooltip="Previous page"><i data-lucide="chevron-left" aria-hidden="true"></i></a><?php endif; ?>
                    <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
                        <a href="<?= guidanceRiskEsc($pageUrl($page)) ?>" class="page-link-custom <?= $page === $currentPage ? 'active' : '' ?>" aria-label="Page <?= $page ?>" <?= $page === $currentPage ? 'aria-current="page"' : '' ?>><?= $page ?></a>
                    <?php endfor; ?>
                    <?php if ($currentPage >= $totalPages): ?><span class="page-link-custom disabled" aria-label="Next page" aria-disabled="true" title="Next page"><i data-lucide="chevron-right" aria-hidden="true"></i></span><?php else: ?><a href="<?= guidanceRiskEsc($pageUrl($currentPage + 1)) ?>" class="page-link-custom" aria-label="Next page" title="Next page" data-aprism-tooltip="Next page"><i data-lucide="chevron-right" aria-hidden="true"></i></a><?php endif; ?>
                    <?php if ($currentPage >= $totalPages): ?><span class="page-link-custom disabled" aria-label="Last page" aria-disabled="true" title="Last page"><i data-lucide="chevrons-right" aria-hidden="true"></i></span><?php else: ?><a href="<?= guidanceRiskEsc($pageUrl($totalPages)) ?>" class="page-link-custom" aria-label="Last page" title="Last page" data-aprism-tooltip="Last page"><i data-lucide="chevrons-right" aria-hidden="true"></i></a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
    </section>
</section>
</div></main>
<?php require __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>





