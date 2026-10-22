<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/role_helper.php';
$allowedRoles = [ROLE_DISCIPLINARY_OFFICER];
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../includes/helper/guidance_helper.php';
require_once __DIR__ . '/../includes/helper/monitoring_helper.php';
require_once __DIR__ . '/../includes/helper/risk_history_helper.php';

$pageTitle = 'Dashboard';
$activePage = 'guidance_dashboard';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/guidance.css';
$userId = (int) $_SESSION['user_id'];

// A completed period is captured once on the first authenticated DO visit.
// Periods predating this capability are excluded by the capture service.
aprismRiskHistoryCaptureNewlyClosed($pdo);
$riskTrendByLevel = [
    'College' => aprismRiskHistoryTrend($pdo, 'College'),
    'Senior High School' => aprismRiskHistoryTrend($pdo, 'Senior High School'),
];

$riskClassEvidence = aprismMonitoringCurrentClassRiskEvidence($pdo, 'disciplinary');
$riskStudents = aprismMonitoringStudentRiskSummaries($riskClassEvidence);
$riskTierCounts = [3 => 0, 2 => 0, 1 => 0];
$insufficientEvidenceCount = 0;
foreach ($riskStudents as $riskStudent) {
    if (empty($riskStudent['assessable'])) {
        $insufficientEvidenceCount++;
        continue;
    }
    $tier = (int) ($riskStudent['tier'] ?? 0);
    if (isset($riskTierCounts[$tier])) {
        $riskTierCounts[$tier]++;
    }
}
$studentsAtRisk = array_sum($riskTierCounts);

function disciplinaryDashboardEsc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$assignedCount = static function (PDO $pdo, int $userId, string $status): int {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT gc.guidance_case_id)
        FROM guidance_cases gc
        INNER JOIN guidance_case_assignments gca
            ON gca.guidance_case_id = gc.guidance_case_id
           AND gca.assigned_user_id = ?
           AND gca.ended_at IS NULL
        WHERE gc.case_status = ?");
    $stmt->execute([$userId, $status]);
    return (int) $stmt->fetchColumn();
};

$scheduledCount = static function (PDO $pdo, int $userId, string $condition = ''): int {
    $sql = "SELECT COUNT(*) FROM guidance_followups
        WHERE assigned_user_id = ? AND followup_status = 'Scheduled'";
    if ($condition !== '') {
        $sql .= ' AND ' . $condition;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
};

$stats = [
    'new_referrals' => (int) $pdo->query("SELECT COUNT(*) FROM teacher_referrals WHERE status = 'Submitted'")->fetchColumn(),
    'active_cases' => $assignedCount($pdo, $userId, 'Active'),
    'due' => $scheduledCount($pdo, $userId, 'scheduled_at <= DATE_ADD(NOW(), INTERVAL 24 HOUR)'),
];

$attention = $pdo->query("SELECT 'referral' item_type, tr.teacher_referral_id item_id,
        tr.student_id, s.student_number, CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name, s.suffix) student_name,
        tr.reason_code, tr.reason, subj.subject_name, sec.section_name, p.program_code,
        tr.created_at event_at, tr.status state, NULL case_id
        FROM teacher_referrals tr
        INNER JOIN students s ON s.student_id = tr.student_id
        LEFT JOIN operational_classes oc ON oc.operational_class_id = tr.operational_class_id
        LEFT JOIN subjects subj ON subj.subject_id = oc.subject_id
        LEFT JOIN sections sec ON sec.section_id = oc.section_id
        LEFT JOIN programs p ON p.program_id = sec.program_id
        WHERE tr.status IN ('Submitted', 'Returned')
        ORDER BY tr.created_at DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

$overdue = $pdo->prepare("SELECT 'followup' item_type, gf.guidance_followup_id item_id,
        s.student_id, s.student_number, CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name, s.suffix) student_name,
        subj.subject_name, sec.section_name, p.program_code,
        CONCAT('Follow-up due ', DATE_FORMAT(gf.scheduled_at, '%b %e, %Y %l:%i %p')) detail,
        gf.scheduled_at event_at, gf.followup_status state, gc.guidance_case_id case_id
        FROM guidance_followups gf
        INNER JOIN guidance_cases gc ON gc.guidance_case_id = gf.guidance_case_id
        INNER JOIN teacher_referrals tr ON tr.teacher_referral_id = gc.teacher_referral_id
        INNER JOIN students s ON s.student_id = tr.student_id
        LEFT JOIN operational_classes oc ON oc.operational_class_id = tr.operational_class_id
        LEFT JOIN subjects subj ON subj.subject_id = oc.subject_id
        LEFT JOIN sections sec ON sec.section_id = oc.section_id
        LEFT JOIN programs p ON p.program_id = sec.program_id
        WHERE gf.assigned_user_id = ? AND gf.followup_status = 'Scheduled' AND gf.scheduled_at < NOW()
        ORDER BY gf.scheduled_at ASC LIMIT 6");
$overdue->execute([$userId]);
$overdueRows = $overdue->fetchAll(PDO::FETCH_ASSOC);
$baseAttention = array_merge($attention, $overdueRows);
$riskAttention = [];
foreach ($riskStudents as $riskStudent) {
    $studentId = (int) ($riskStudent['student_id'] ?? 0);
    $tier = (int) ($riskStudent['tier'] ?? 0);
    if ($studentId <= 0 || $tier <= 0) {
        continue;
    }
    $classEvidence = $riskStudent['contributing_classes'][0] ?? [];
    $classContext = trim(implode(' · ', array_filter([
        (string) ($classEvidence['subject_name'] ?? ''),
        (string) ($classEvidence['section_name'] ?? ''),
    ])));
    $riskDetail = (string) ($riskStudent['tier_label'] ?? 'Current risk') . ' · '
        . (string) ($riskStudent['main_reason'] ?? 'Attendance concern');
    if ($classContext !== '') {
        $riskDetail .= ' · ' . $classContext;
    }
    $riskAttention[] = [
        'item_type' => 'risk',
        'item_id' => $studentId,
        'student_id' => $studentId,
        'student_name' => (string) ($riskStudent['student_name'] ?? 'Student'),
        'student_number' => (string) ($riskStudent['student_number'] ?? ''),
        'detail' => $riskDetail,
        'reason_detail' => (string) ($riskStudent['main_reason'] ?? 'Attendance concern'),
        'program_code' => (string) ($classEvidence['program_code'] ?? ''),
        'section_name' => (string) ($classEvidence['section_name'] ?? ''),
        'event_at' => $riskStudent['latest_activity_at'],
        'state' => (string) ($riskStudent['tier_label'] ?? 'Current risk'),
        'case_id' => null,
        'tier' => $tier,
    ];
}
usort($riskAttention, static fn(array $left, array $right): int =>
    ((int) $right['tier'] <=> (int) $left['tier'])
        ?: strcmp((string) ($right['event_at'] ?? ''), (string) ($left['event_at'] ?? ''))
        ?: strcmp((string) $left['student_name'], (string) $right['student_name'])
);
$riskAttention = array_slice($riskAttention, 0, 2);
$riskPriorityIds = array_values(array_unique(array_filter(array_map(
    static fn(array $item): int => (int) ($item['student_id'] ?? 0),
    $riskAttention
))));
$attentionWorkflowStatus = [];
if ($riskPriorityIds !== []) {
    $placeholders = implode(',', array_fill(0, count($riskPriorityIds), '?'));
    $workflowStmt = $pdo->prepare("SELECT tr.student_id, tr.status referral_status, gc.case_status,
            COALESCE(gc.opened_at, tr.updated_at) status_at
        FROM teacher_referrals tr
        LEFT JOIN guidance_cases gc ON gc.teacher_referral_id = tr.teacher_referral_id
        WHERE tr.student_id IN ($placeholders)
        ORDER BY (gc.case_status = 'Active') DESC, (gc.case_status = 'Open') DESC,
            tr.updated_at DESC, tr.teacher_referral_id DESC");
    $workflowStmt->execute($riskPriorityIds);
    foreach ($workflowStmt->fetchAll(PDO::FETCH_ASSOC) as $workflowRow) {
        $studentId = (int) $workflowRow['student_id'];
        if (isset($attentionWorkflowStatus[$studentId])) {
            continue;
        }
        $caseStatus = trim((string) ($workflowRow['case_status'] ?? ''));
        $referralStatus = trim((string) ($workflowRow['referral_status'] ?? ''));
        $attentionWorkflowStatus[$studentId] = $caseStatus !== ''
            ? ['label' => 'Case ' . $caseStatus, 'at' => (string) $workflowRow['status_at']]
            : ['label' => $referralStatus, 'at' => (string) $workflowRow['status_at']];
    }
}
$attention = array_merge($riskAttention, $baseAttention);
$attentionPreview = [];
$attentionPreviewLimits = ['risk' => 2, 'referral' => 1, 'followup' => 1];
$attentionPreviewCounts = array_fill_keys(array_keys($attentionPreviewLimits), 0);
$attentionPreviewStudentIds = [];
foreach ($attention as $attentionItem) {
    $attentionType = (string) ($attentionItem['item_type'] ?? '');
    $attentionStudentId = (int) ($attentionItem['student_id'] ?? 0);
    if (!isset($attentionPreviewLimits[$attentionType])
        || $attentionPreviewCounts[$attentionType] >= $attentionPreviewLimits[$attentionType]
        || ($attentionStudentId > 0 && isset($attentionPreviewStudentIds[$attentionStudentId]))) {
        continue;
    }
    $attentionPreview[] = $attentionItem;
    $attentionPreviewCounts[$attentionType]++;
    if ($attentionStudentId > 0) {
        $attentionPreviewStudentIds[$attentionStudentId] = true;
    }
}
foreach ($attentionPreview as &$attentionItem) {
    $studentId = (int) ($attentionItem['student_id'] ?? 0);
    $riskSummary = $riskStudents[$studentId] ?? [];
    $classContext = $riskSummary['contributing_classes'][0] ?? $riskSummary['class_evidence'][0] ?? [];
    $attentionItem['student_number'] = (string) ($attentionItem['student_number'] ?? $riskSummary['student_number'] ?? '');
    $attentionItem['program_code'] = (string) ($attentionItem['program_code'] ?? $classContext['program_code'] ?? '');
    $attentionItem['section_name'] = (string) ($attentionItem['section_name'] ?? $classContext['section_name'] ?? '');
    $attentionItem['tier'] = (int) ($attentionItem['tier'] ?? $riskSummary['tier'] ?? 0);
    if (($attentionItem['item_type'] ?? '') === 'risk') {
        $attentionItem['reason_detail'] = (string) ($attentionItem['reason_detail'] ?? 'Attendance concern');
        $workflow = $attentionWorkflowStatus[$studentId] ?? null;
        $attentionItem['workflow_status'] = (string) ($workflow['label'] ?? 'No case / referral');
        if (!empty($workflow['at'])) {
            $attentionItem['event_at'] = $workflow['at'];
        }
    }
}
unset($attentionItem);

$upcomingStmt = $pdo->prepare("SELECT gf.guidance_followup_id,
        CONCAT_WS(' ', s.first_name, s.last_name) student_name,
        gc.guidance_case_id case_id, gf.scheduled_at, gf.followup_status
        FROM guidance_followups gf
        INNER JOIN guidance_cases gc ON gc.guidance_case_id = gf.guidance_case_id
        INNER JOIN teacher_referrals tr ON tr.teacher_referral_id = gc.teacher_referral_id
        INNER JOIN students s ON s.student_id = tr.student_id
        WHERE gf.assigned_user_id = ? AND gf.followup_status = 'Scheduled' AND gf.scheduled_at >= NOW()
        ORDER BY gf.scheduled_at ASC LIMIT 5");
$upcomingStmt->execute([$userId]);
$upcoming = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);
$dashboardMonth = new DateTimeImmutable('first day of this month');
$dashboardMonthDays = (int) $dashboardMonth->format('t');
$dashboardMonthLeadingDays = (int) $dashboardMonth->format('w');
$upcomingByDate = [];
foreach ($upcoming as $upcomingItem) {
    $scheduledDate = date('Y-m-d', strtotime((string) $upcomingItem['scheduled_at']));
    $upcomingByDate[$scheduledDate][] = $upcomingItem;
}

$activityStmt = $pdo->prepare("SELECT e.event_type, e.event_summary, e.created_at,
        gc.guidance_case_id, gc.case_number,
        CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name, s.suffix) AS student_name
        FROM guidance_case_events e
        INNER JOIN guidance_cases gc ON gc.guidance_case_id = e.guidance_case_id
        INNER JOIN teacher_referrals tr ON tr.teacher_referral_id = gc.teacher_referral_id
        INNER JOIN students s ON s.student_id = tr.student_id
        INNER JOIN guidance_case_assignments gca ON gca.guidance_case_id = gc.guidance_case_id
            AND gca.assigned_user_id = ? AND gca.ended_at IS NULL
        ORDER BY e.created_at DESC, e.guidance_case_event_id DESC LIMIT 4");
$activityStmt->execute([$userId]);
$activity = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

$chartJson = static fn(array $values): string => htmlspecialchars(
    json_encode($values, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR),
    ENT_QUOTES,
    'UTF-8'
);
$dashboardMetrics = [
    ['label' => 'Students at risk', 'value' => $studentsAtRisk, 'description' => 'Current active classes', 'icon' => 'users-round', 'class' => '', 'href' => APP_URL . '/dashboard/guidance_risk_monitoring.php'],
    ['label' => 'Tier 3 high risk', 'value' => $riskTierCounts[3], 'description' => 'Highest current class tier', 'icon' => 'triangle-alert', 'class' => 'is-danger', 'href' => APP_URL . '/dashboard/guidance_risk_monitoring.php?tier=3'],
    ['label' => 'New referrals', 'value' => $stats['new_referrals'], 'description' => 'Submitted for review', 'icon' => 'file-text', 'class' => 'is-alert', 'href' => APP_URL . '/dashboard/guidance_referrals.php?status=Submitted'],
    ['label' => 'Active cases', 'value' => $stats['active_cases'], 'description' => 'Currently in intervention', 'icon' => 'layers', 'class' => 'is-purple', 'href' => APP_URL . '/dashboard/guidance_cases.php?status=Active'],
    ['label' => 'Follow-ups due', 'value' => $stats['due'], 'description' => 'Overdue or due within 24 hours', 'icon' => 'clock-3', 'class' => 'is-warm', 'href' => APP_URL . '/dashboard/guidance_followups.php'],
];
$activityIconMap = [
    'follow-up completed' => 'calendar-check-2',
    'case status changed' => 'user-round',
    'follow-up scheduled' => 'calendar-plus-2',
    'confidential note added' => 'file-text',
    'case opened' => 'folder-open',
];
$activityToneMap = [
    'follow-up completed' => 'is-complete',
    'case status changed' => 'is-status-changed',
    'follow-up scheduled' => 'is-scheduled',
    'confidential note added' => 'is-note',
    'case opened' => 'is-case-opened',
];
?>
<!doctype html>
<html lang="en">
<?php require __DIR__ . '/../includes/components/head.php'; ?>
<body>
<?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
<main class="main-content"><div class="main-wrapper">
<?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
<section class="guidance-page guidance-dashboard-page">
    <header class="guidance-page-header">
        <div class="guidance-dashboard-title">
            <h1>Dashboard</h1>
        </div>
        <a class="guidance-button guidance-button-primary" href="<?= APP_URL ?>/dashboard/guidance_referrals.php">
            <i data-lucide="inbox" aria-hidden="true"></i>
            <span>Review referral queue</span>
        </a>
    </header>
    <section class="guidance-dashboard-metrics" aria-label="Operational summary">
        <?php foreach ($dashboardMetrics as $metric): ?>
        <a class="guidance-summary-card <?= disciplinaryDashboardEsc($metric['class']) ?>" href="<?= disciplinaryDashboardEsc($metric['href']) ?>">
            <span class="guidance-summary-icon"><i data-lucide="<?= disciplinaryDashboardEsc($metric['icon']) ?>" aria-hidden="true"></i></span>
            <span class="guidance-summary-copy">
                <span class="guidance-summary-label"><?= disciplinaryDashboardEsc($metric['label']) ?></span>
                <strong><?= (int) $metric['value'] ?></strong>
            </span>
        </a>
        <?php endforeach; ?>
    </section>
    <section class="guidance-risk-grid" aria-label="Current attendance risk analytics">
        <article class="guidance-panel guidance-risk-panel guidance-risk-trend-panel">
            <div class="guidance-risk-panel-heading">
                <h2>Risk Trends Across Terms</h2>
                <div class="guidance-trend-scopes" role="group" aria-label="Risk trend academic level">
                    <button type="button" data-guidance-trend-scope="All" aria-pressed="true">All</button>
                    <button type="button" data-guidance-trend-scope="College" aria-pressed="false">Tertiary</button>
                    <button type="button" data-guidance-trend-scope="Senior High School" aria-pressed="false">SHS</button>
                </div>
            </div>
            <div class="guidance-risk-trend-plot" data-guidance-risk-trend
                 data-guidance-trend-periods="<?= $chartJson($riskTrendByLevel) ?>">
                <canvas data-guidance-risk-trend-chart role="img" aria-label="Historical risk tiers by completed Academic Period"></canvas>
                <div class="guidance-trend-empty" data-guidance-trend-empty role="status" hidden>
                    <strong>No historical risk snapshot is available for this scope yet.</strong>
                    <span>Current risk tiers are shown in the breakdown.</span>
                </div>
            </div>
            <p class="guidance-trend-coverage" data-guidance-trend-coverage aria-live="polite"></p>
            <div class="guidance-trend-legend" aria-label="Risk tier series">
                <span><i class="is-tier-3" aria-hidden="true"></i>Tier 3</span>
                <span><i class="is-tier-2" aria-hidden="true"></i>Tier 2</span>
                <span><i class="is-tier-1" aria-hidden="true"></i>Tier 1</span>
            </div>
        </article>
        <article class="guidance-panel guidance-risk-panel guidance-risk-breakdown-panel">
            <div class="guidance-risk-panel-heading">
                <h2>Current Risk Tier Breakdown</h2>
                <a class="guidance-risk-total" href="<?= APP_URL ?>/dashboard/guidance_risk_monitoring.php" aria-label="View all <?= $studentsAtRisk ?> at-risk students in Risk Monitoring"><strong><?= $studentsAtRisk ?></strong><small>Total</small></a>
            </div>
            <?php if ($studentsAtRisk > 0): ?>
                <div class="guidance-risk-donut-wrap">
                    <canvas data-guidance-risk-breakdown
                        data-guidance-chart-labels="<?= $chartJson(['Tier 3 High Risk', 'Tier 2 Elevated', 'Tier 1 Moderate']) ?>"
                        data-guidance-chart-values="<?= $chartJson([$riskTierCounts[3], $riskTierCounts[2], $riskTierCounts[1]]) ?>"
                        data-guidance-chart-total="<?= $studentsAtRisk ?>"
                        role="img" aria-label="Current risk tier breakdown for <?= $studentsAtRisk ?> students"></canvas>
                </div>
            <?php else: ?>
                <div class="guidance-risk-no-data" role="status">No students currently have a risk tier.</div>
            <?php endif; ?>
            <?php if ($insufficientEvidenceCount > 0): ?>
                <p class="guidance-risk-insufficient-note"><?= $insufficientEvidenceCount ?> <?= $insufficientEvidenceCount === 1 ? 'student has' : 'students have' ?> insufficient attendance evidence.</p>
            <?php endif; ?>
            <ul class="guidance-risk-breakdown-list" aria-label="Current students by risk tier">
                <?php foreach ([3 => ['Tier 3 High Risk', 'is-tier-3'], 2 => ['Tier 2 Elevated', 'is-tier-2'], 1 => ['Tier 1 Moderate', 'is-tier-1']] as $tier => [$label, $class]):
                    $share = $studentsAtRisk > 0 ? (int) round(($riskTierCounts[$tier] / $studentsAtRisk) * 100) : null;
                ?>
                    <li class="<?= $class ?>">
                        <a class="guidance-risk-breakdown-link" href="<?= APP_URL ?>/dashboard/guidance_risk_monitoring.php?tier=<?= $tier ?>" aria-label="View Tier <?= $tier ?> students in Risk Monitoring">
                            <span class="guidance-risk-breakdown-dot" aria-hidden="true"></span>
                            <span><?= disciplinaryDashboardEsc($label) ?></span>
                            <strong><?= $riskTierCounts[$tier] ?></strong>
                            <?php if ($share !== null): ?><small><?= $share ?>%</small><?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </article>
    </section>
    <div class="guidance-dashboard-grid">
        <section class="guidance-panel guidance-attention-panel">
            <div class="guidance-panel-heading">
                <h2>Needs Immediate Attention</h2>
                <nav class="guidance-attention-filters" aria-label="Risk and referral shortcuts">
                    <a href="<?= APP_URL ?>/dashboard/guidance_risk_monitoring.php">All Tiers <span><?= $studentsAtRisk ?></span></a>
                    <a href="<?= APP_URL ?>/dashboard/guidance_risk_monitoring.php?tier=3">Tier 3 <span><?= $riskTierCounts[3] ?></span></a>
                    <a href="<?= APP_URL ?>/dashboard/guidance_risk_monitoring.php?tier=2">Tier 2 <span><?= $riskTierCounts[2] ?></span></a>
                    <a href="<?= APP_URL ?>/dashboard/guidance_risk_monitoring.php?tier=1">Tier 1 <span><?= $riskTierCounts[1] ?></span></a>
                    <a href="<?= APP_URL ?>/dashboard/guidance_referrals.php?status=Submitted">Pending Referral <span><?= $stats['new_referrals'] ?></span></a>
                </nav>
            </div>
            <?php if ($attention === []): ?>
                <div class="guidance-empty-state guidance-attention-empty">
                    <span class="guidance-empty-icon" aria-hidden="true"><i data-lucide="circle-check"></i></span>
                    <div class="guidance-empty-copy">
                        <strong>No immediate action items</strong>
                        <span>New referrals and overdue follow-ups will appear here.</span>
                    </div>
                </div>
            <?php else: ?>
                <div class="guidance-attention-table-wrap" role="region" aria-label="Priority referral and risk preview" tabindex="0">
                    <table class="guidance-attention-table">
                        <thead><tr>
                            <th scope="col">Student</th>
                            <th scope="col">Program &amp; Section</th>
                            <th scope="col">Risk Tier</th>
                            <th scope="col">Trigger / Primary Reason</th>
                            <th scope="col">Case / Referral Status</th>
                            <th scope="col"><span class="visually-hidden">Action</span></th>
                        </tr></thead>
                        <tbody>
                    <?php foreach ($attentionPreview as $item): ?>
                    <?php
                    $attentionDetail = $item['item_type'] === 'referral'
                        ? aprismReferralReasonDisplay($item['reason_code'] ?? null, $item['reason'] ?? null)
                        : (string) ($item['reason_detail'] ?? $item['detail'] ?? '');
                    $nameParts = preg_split('/\s+/', trim((string) $item['student_name']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    $studentInitials = strtoupper(substr($nameParts[0] ?? 'S', 0, 1) . substr($nameParts[count($nameParts) - 1] ?? '', 0, 1));
                    $programSection = trim(implode(' · ', array_filter([
                        (string) ($item['program_code'] ?? ''),
                        (string) ($item['section_name'] ?? ''),
                    ])));
                    $tier = (int) ($item['tier'] ?? 0);
                    $tierClass = $tier > 0 ? 'tier-' . $tier : 'is-unassessable';
                    $tierLabel = $tier > 0 ? 'Tier ' . $tier : 'Not assessed';
                    $statusLabel = $item['item_type'] === 'risk'
                        ? (string) ($item['workflow_status'] ?? 'No case / referral')
                        : (string) $item['state'];
                    ?>
                    <tr>
                        <td data-label="Student">
                            <span class="guidance-attention-student">
                                <span class="guidance-attention-avatar" aria-hidden="true"><?= disciplinaryDashboardEsc($studentInitials) ?></span>
                                <span><strong><?= disciplinaryDashboardEsc($item['student_name']) ?></strong><small><?= disciplinaryDashboardEsc($item['student_number'] ?? '') ?></small></span>
                            </span>
                        </td>
                        <td data-label="Program & Section"><span class="guidance-attention-context"><?= disciplinaryDashboardEsc($programSection !== '' ? $programSection : '—') ?></span></td>
                        <td data-label="Risk Tier"><span class="guidance-risk-tier <?= $tierClass ?>"><?= disciplinaryDashboardEsc($tierLabel) ?></span></td>
                        <td data-label="Trigger / Primary Reason"><span class="guidance-attention-reason-copy"><?= disciplinaryDashboardEsc($attentionDetail) ?></span></td>
                        <td data-label="Case / Referral Status"><span class="guidance-status <?= $item['item_type'] === 'risk' ? 'active' : strtolower((string) $item['state']) ?>"><?= disciplinaryDashboardEsc($statusLabel) ?></span><small class="guidance-attention-date"><?= disciplinaryDashboardEsc(date('M j · g:i A', strtotime((string) $item['event_at']))) ?></small></td>
                        <td data-label="Action" class="guidance-attention-action">
                        <?php if ($item['item_type'] === 'followup'): ?>
                            <a class="guidance-button guidance-button-secondary" aria-label="Review follow-up for <?= disciplinaryDashboardEsc($item['student_name']) ?>" href="<?= APP_URL ?>/dashboard/guidance_case.php?case_id=<?= (int) $item['case_id'] ?>#case-followups">Review</a>
                        <?php elseif ($item['item_type'] === 'risk'): ?>
                            <a class="guidance-button guidance-button-secondary" aria-label="View profile for <?= disciplinaryDashboardEsc($item['student_name']) ?>" href="<?= APP_URL ?>/dashboard/guidance_student_profile.php?student_id=<?= (int) $item['student_id'] ?>&amp;source=risk">View</a>
                        <?php else: ?>
                            <a class="guidance-button guidance-button-secondary" aria-label="Review referral for <?= disciplinaryDashboardEsc($item['student_name']) ?>" href="<?= APP_URL ?>/dashboard/guidance_referrals.php?status=<?= urlencode((string) $item['state']) ?>">Review</a>
                        <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <section class="guidance-panel guidance-upcoming-panel">
            <div class="guidance-panel-heading">
                <div><p class="guidance-section-kicker">Scheduled work</p><h2>Follow-ups &amp; Scheduled Work</h2></div>
                <a class="guidance-text-link" href="<?= APP_URL ?>/dashboard/guidance_followups.php">View all</a>
            </div>
            <div class="guidance-followup-dashboard-split">
                <section class="guidance-mini-calendar" aria-label="Follow-up dates for <?= disciplinaryDashboardEsc($dashboardMonth->format('F Y')) ?>">
                    <h3><?= disciplinaryDashboardEsc($dashboardMonth->format('F Y')) ?></h3>
                    <div class="guidance-mini-calendar-grid" role="list">
                        <?php foreach (['S', 'M', 'T', 'W', 'T', 'F', 'S'] as $weekday): ?>
                            <span class="guidance-mini-calendar-weekday" aria-hidden="true"><?= $weekday ?></span>
                        <?php endforeach; ?>
                        <?php for ($blankDay = 0; $blankDay < $dashboardMonthLeadingDays; $blankDay++): ?>
                            <span class="guidance-mini-calendar-day is-empty" aria-hidden="true"></span>
                        <?php endfor; ?>
                        <?php for ($monthDay = 1; $monthDay <= $dashboardMonthDays; $monthDay++):
                            $calendarDate = $dashboardMonth->setDate(
                                (int) $dashboardMonth->format('Y'),
                                (int) $dashboardMonth->format('n'),
                                $monthDay
                            )->format('Y-m-d');
                            $calendarItems = $upcomingByDate[$calendarDate] ?? [];
                            if ($calendarItems !== []):
                                $calendarItem = $calendarItems[0];
                        ?>
                            <a class="guidance-mini-calendar-day is-event" role="listitem"
                                href="<?= APP_URL ?>/dashboard/guidance_case.php?case_id=<?= (int) $calendarItem['case_id'] ?>#case-detail-header"
                                aria-label="<?= disciplinaryDashboardEsc($dashboardMonth->setDate((int) $dashboardMonth->format('Y'), (int) $dashboardMonth->format('n'), $monthDay)->format('F j')) ?>, <?= count($calendarItems) ?> scheduled follow-up<?= count($calendarItems) === 1 ? '' : 's' ?>">
                                <?= $monthDay ?><i aria-hidden="true"></i>
                            </a>
                        <?php else: ?>
                            <span class="guidance-mini-calendar-day" role="listitem"><?= $monthDay ?></span>
                        <?php endif; endfor; ?>
                    </div>
                    <p class="guidance-mini-calendar-key"><i aria-hidden="true"></i> Scheduled follow-up</p>
                </section>
                <div class="guidance-agenda-list">
                    <?php if ($upcoming === []): ?>
                        <div class="guidance-agenda-empty">No upcoming follow-ups are scheduled.</div>
                    <?php else: ?>
                        <?php foreach ($upcoming as $item): ?>
                        <a class="guidance-agenda-item" href="<?= APP_URL ?>/dashboard/guidance_case.php?case_id=<?= (int) $item['case_id'] ?>#case-detail-header">
                            <time datetime="<?= disciplinaryDashboardEsc(date(DATE_ATOM, strtotime((string) $item['scheduled_at']))) ?>">
                                <span><?= disciplinaryDashboardEsc(date('M j', strtotime((string) $item['scheduled_at']))) ?></span>
                                <b><?= disciplinaryDashboardEsc(date('g:i A', strtotime((string) $item['scheduled_at']))) ?></b>
                            </time>
                            <span><strong><?= disciplinaryDashboardEsc($item['student_name']) ?></strong><small>Case #<?= (int) $item['case_id'] ?></small></span>
                            <i data-lucide="chevron-right" aria-hidden="true"></i>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <section class="guidance-panel guidance-recent-activity-panel">
            <div class="guidance-panel-heading">
                <div><p class="guidance-section-kicker">Assigned work</p><h2>Recent Case &amp; Intervention Activity</h2></div>
                <a class="guidance-text-link" href="<?= APP_URL ?>/dashboard/guidance_cases.php">View cases</a>
            </div>
            <?php if ($activity === []): ?>
                <div class="guidance-empty-state guidance-activity-empty">
                    <span class="guidance-empty-icon" aria-hidden="true"><i data-lucide="history"></i></span>
                    <div class="guidance-empty-copy"><strong>No case activity yet.</strong></div>
                </div>
            <?php else: ?>
                <div class="guidance-activity-list">
                    <?php foreach ($activity as $item): ?>
                    <?php
                    $eventTypeKey = strtolower(trim((string) $item['event_type']));
                    $activityIcon = $activityIconMap[$eventTypeKey] ?? 'history';
                    $activityTone = $activityToneMap[$eventTypeKey] ?? '';
                    ?>
                    <a class="guidance-activity-item" href="<?= APP_URL ?>/dashboard/guidance_case.php?case_id=<?= (int) $item['guidance_case_id'] ?>#case-detail-header">
                        <span class="guidance-activity-dot <?= $activityTone ?>" aria-hidden="true"></span>
                        <span class="guidance-activity-icon <?= $activityTone ?>" aria-hidden="true"><i data-lucide="<?= disciplinaryDashboardEsc($activityIcon) ?>"></i></span>
                        <span class="guidance-activity-copy">
                            <strong><?= disciplinaryDashboardEsc($item['event_type']) ?></strong>
                            <span class="guidance-activity-student-name"><?= disciplinaryDashboardEsc($item['student_name']) ?></span>
                            <span class="guidance-activity-meta">
                                <small><?= disciplinaryDashboardEsc($item['event_summary']) ?></small>
                                <time datetime="<?= disciplinaryDashboardEsc(date(DATE_ATOM, strtotime((string) $item['created_at']))) ?>"><?= disciplinaryDashboardEsc(date('M j, Y · g:i A', strtotime((string) $item['created_at']))) ?></time>
                            </span>
                        </span>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section></div></main>
<?php require __DIR__ . '/../includes/components/footer.php'; ?>
<script src="<?= APP_URL ?>/assets/js/pages/guidance-dashboard-charts.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/pages/guidance-dashboard-charts.js') ?>"></script>
</body></html>
