<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/role_helper.php';
$allowedRoles = [ROLE_DISCIPLINARY_OFFICER];
require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../includes/helper/guidance_helper.php';

$pageTitle = 'Dashboard';
$activePage = 'guidance_dashboard';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/guidance.css';
$userId = (int) $_SESSION['user_id'];

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
    'open_cases' => $assignedCount($pdo, $userId, 'Open'),
    'active_cases' => $assignedCount($pdo, $userId, 'Active'),
    'closed_cases' => $assignedCount($pdo, $userId, 'Closed'),
    'due' => $scheduledCount($pdo, $userId, 'scheduled_at <= DATE_ADD(NOW(), INTERVAL 24 HOUR)'),
    'overdue' => $scheduledCount($pdo, $userId, 'scheduled_at < NOW()'),
];

$attention = $pdo->query("SELECT 'referral' item_type, tr.teacher_referral_id item_id,
        CONCAT_WS(' ', s.first_name, s.last_name) student_name, tr.reason detail,
        tr.created_at event_at, tr.status state, NULL case_id
        FROM teacher_referrals tr
        INNER JOIN students s ON s.student_id = tr.student_id
        WHERE tr.status IN ('Submitted', 'Returned')
        ORDER BY tr.created_at DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

$overdue = $pdo->prepare("SELECT 'followup' item_type, gf.guidance_followup_id item_id,
        CONCAT_WS(' ', s.first_name, s.last_name) student_name,
        CONCAT('Follow-up due ', DATE_FORMAT(gf.scheduled_at, '%b %e, %Y %l:%i %p')) detail,
        gf.scheduled_at event_at, gf.followup_status state, gc.guidance_case_id case_id
        FROM guidance_followups gf
        INNER JOIN guidance_cases gc ON gc.guidance_case_id = gf.guidance_case_id
        INNER JOIN teacher_referrals tr ON tr.teacher_referral_id = gc.teacher_referral_id
        INNER JOIN students s ON s.student_id = tr.student_id
        WHERE gf.assigned_user_id = ? AND gf.followup_status = 'Scheduled' AND gf.scheduled_at < NOW()
        ORDER BY gf.scheduled_at ASC LIMIT 6");
$overdue->execute([$userId]);
$attention = array_merge($attention, $overdue->fetchAll(PDO::FETCH_ASSOC));

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

$activityStmt = $pdo->prepare("SELECT e.event_type, e.event_summary, e.created_at,
        gc.guidance_case_id, gc.case_number
        FROM guidance_case_events e
        INNER JOIN guidance_cases gc ON gc.guidance_case_id = e.guidance_case_id
        INNER JOIN guidance_case_assignments gca ON gca.guidance_case_id = gc.guidance_case_id
            AND gca.assigned_user_id = ? AND gca.ended_at IS NULL
        ORDER BY e.created_at DESC, e.guidance_case_event_id DESC LIMIT 5");
$activityStmt->execute([$userId]);
$activity = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

$caseStatusStmt = $pdo->prepare("SELECT gc.case_status label, COUNT(DISTINCT gc.guidance_case_id) total
    FROM guidance_cases gc
    INNER JOIN guidance_case_assignments gca
        ON gca.guidance_case_id = gc.guidance_case_id
       AND gca.assigned_user_id = ?
       AND gca.ended_at IS NULL
    GROUP BY gc.case_status
    ORDER BY FIELD(gc.case_status, 'Open', 'Active', 'Closed')");
$caseStatusStmt->execute([$userId]);
$caseStatusRows = $caseStatusStmt->fetchAll(PDO::FETCH_ASSOC);

$referralStatusStmt = $pdo->prepare("SELECT tr.status label, COUNT(DISTINCT tr.teacher_referral_id) total
    FROM teacher_referrals tr
    INNER JOIN guidance_cases gc ON gc.teacher_referral_id = tr.teacher_referral_id
    INNER JOIN guidance_case_assignments gca ON gca.guidance_case_id = gc.guidance_case_id
        AND gca.assigned_user_id = ? AND gca.ended_at IS NULL
    GROUP BY tr.status
    ORDER BY FIELD(tr.status, 'Submitted', 'Returned', 'Accepted', 'Closed')");
$referralStatusStmt->execute([$userId]);
$referralStatusRows = $referralStatusStmt->fetchAll(PDO::FETCH_ASSOC);
$followupStatusStmt = $pdo->prepare("SELECT followup_status label, COUNT(*) total FROM guidance_followups WHERE assigned_user_id=? GROUP BY followup_status ORDER BY FIELD(followup_status,'Scheduled','Completed','Cancelled')");
$followupStatusStmt->execute([$userId]);
$followupStatusRows = $followupStatusStmt->fetchAll(PDO::FETCH_ASSOC);
$referralTrendStmt = $pdo->prepare("SELECT DATE_FORMAT(tr.created_at,'%b %Y') label, DATE_FORMAT(tr.created_at,'%Y-%m') sort_key, COUNT(DISTINCT tr.teacher_referral_id) total FROM teacher_referrals tr INNER JOIN guidance_cases gc ON gc.teacher_referral_id=tr.teacher_referral_id INNER JOIN guidance_case_assignments gca ON gca.guidance_case_id=gc.guidance_case_id AND gca.assigned_user_id=? AND gca.ended_at IS NULL GROUP BY sort_key,label ORDER BY sort_key");
$referralTrendStmt->execute([$userId]);
$referralTrendRows = $referralTrendStmt->fetchAll(PDO::FETCH_ASSOC);
$chartJson = static fn(array $values): string => htmlspecialchars(
    json_encode(array_values($values), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    ENT_QUOTES,
    'UTF-8'
);
$normalizeChartRows = static function (array $rows, array $labels): array {
    $counts = [];
    foreach ($rows as $row) $counts[(string) $row['label']] = (int) $row['total'];
    return array_map(static fn(string $label): array => ['label' => $label, 'total' => $counts[$label] ?? 0], $labels);
};
$referralChartRows = $normalizeChartRows($referralStatusRows, ['Submitted', 'Returned', 'Accepted', 'Closed']);
$caseChartRows = $normalizeChartRows($caseStatusRows, ['Open', 'Active', 'Closed']);
$followupChartRows = $normalizeChartRows($followupStatusRows, ['Scheduled', 'Completed', 'Cancelled']);
$dashboardMetrics = [
    ['label' => 'New referrals', 'value' => $stats['new_referrals'], 'description' => 'New referrals received', 'icon' => 'file-text', 'class' => 'is-alert', 'href' => APP_URL . '/dashboard/guidance_referrals.php?status=Submitted'],
    ['label' => 'Open cases', 'value' => $stats['open_cases'], 'description' => 'Currently open', 'icon' => 'folder-open', 'class' => '', 'href' => APP_URL . '/dashboard/guidance_cases.php?status=Open'],
    ['label' => 'Active cases', 'value' => $stats['active_cases'], 'description' => 'Under intervention', 'icon' => 'layers', 'class' => 'is-purple', 'href' => APP_URL . '/dashboard/guidance_cases.php?status=Active'],
    ['label' => 'Closed cases', 'value' => $stats['closed_cases'], 'description' => 'Cases resolved', 'icon' => 'circle-check', 'class' => 'is-teal', 'href' => APP_URL . '/dashboard/guidance_cases.php?status=Closed'],
    ['label' => 'Follow-ups due', 'value' => $stats['due'], 'description' => 'Due for follow-up', 'icon' => 'clock-3', 'class' => 'is-warm', 'href' => APP_URL . '/dashboard/guidance_followups.php'],
    ['label' => 'Overdue follow-ups', 'value' => $stats['overdue'], 'description' => 'Past due', 'icon' => 'triangle-alert', 'class' => 'is-danger', 'href' => APP_URL . '/dashboard/guidance_followups.php'],
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
            <p class="page-eyebrow">Disciplinary Officer</p>
            <h1>Dashboard</h1>
        </div>
        <a class="guidance-button guidance-button-primary" href="<?= APP_URL ?>/dashboard/guidance_referrals.php">
            <i data-lucide="inbox" aria-hidden="true"></i>
            <span>Review referral queue</span>
        </a>
    </header>
    <section class="guidance-summary-grid" aria-label="Operational summary">
        <?php foreach ($dashboardMetrics as $metric): ?>
        <a class="guidance-summary-card <?= disciplinaryDashboardEsc($metric['class']) ?>" href="<?= disciplinaryDashboardEsc($metric['href']) ?>">
            <span class="guidance-summary-icon"><i data-lucide="<?= disciplinaryDashboardEsc($metric['icon']) ?>" aria-hidden="true"></i></span>
            <span class="guidance-summary-copy">
                <span class="guidance-summary-label"><?= disciplinaryDashboardEsc($metric['label']) ?></span>
                <strong><?= (int) $metric['value'] ?></strong>
                <span class="guidance-summary-description"><?= disciplinaryDashboardEsc($metric['description']) ?></span>
            </span>
        </a>
        <?php endforeach; ?>
    </section>
    <details class="guidance-secondary-insights">
        <summary><i data-lucide="chart-no-axes-combined" aria-hidden="true"></i><span>View workload summaries</span></summary>
        <section class="guidance-chart-grid" aria-label="Referral, case and follow-up summaries">
        <?php
        $chartDefinitions = [
            ['title' => 'Referral status', 'kicker' => 'Assigned referrals', 'type' => 'doughnut', 'rows' => $referralChartRows, 'empty' => 'No assigned referral data.'],
            ['title' => 'Case status', 'kicker' => 'Assigned cases', 'type' => 'doughnut', 'rows' => $caseChartRows, 'empty' => 'No assigned case data.'],
            ['title' => 'Follow-up workload', 'kicker' => 'Assigned follow-ups', 'type' => 'doughnut', 'rows' => $followupChartRows, 'empty' => 'No follow-up data.'],
            ['title' => 'Referral trend', 'kicker' => 'Submitted over time', 'type' => 'line', 'rows' => $referralTrendRows, 'empty' => 'No referral trend data.'],
        ];
        $chartLegendTones = [
            'submitted' => 'is-submitted', 'returned' => 'is-returned', 'accepted' => 'is-accepted',
            'closed' => 'is-closed', 'open' => 'is-open', 'active' => 'is-active',
            'scheduled' => 'is-scheduled', 'completed' => 'is-completed', 'cancelled' => 'is-cancelled',
            'referrals' => 'is-referrals',
        ];
        foreach ($chartDefinitions as $chart):
            $chartTotal = array_sum(array_map(static fn(array $row): int => (int) $row['total'], $chart['rows']));
            $chartHasData = $chartTotal > 0;
            $chartLegendRows = $chart['type'] === 'line' ? [['label' => 'Referrals', 'total' => null]] : $chart['rows'];
        ?>
        <article class="guidance-panel guidance-chart-panel">
            <div class="guidance-panel-heading">
                <div>
                    <p class="guidance-section-kicker"><?= disciplinaryDashboardEsc($chart['kicker']) ?></p>
                    <h2><?= disciplinaryDashboardEsc($chart['title']) ?></h2>
                </div>
                <span class="guidance-chart-icon" aria-hidden="true"><i data-lucide="chart-no-axes-combined"></i></span>
            </div>
            <div class="guidance-chart-visualization">
                <?php if (!$chartHasData): ?>
                    <div class="guidance-chart-empty" role="status">
                        <i data-lucide="chart-no-axes-column-increasing" aria-hidden="true"></i>
                        <span><?= disciplinaryDashboardEsc($chart['empty']) ?></span>
                    </div>
                <?php else: ?>
                    <div class="guidance-chart-wrap">
                        <canvas data-guidance-dashboard-chart data-guidance-chart-type="<?= disciplinaryDashboardEsc($chart['type']) ?>" data-guidance-chart-labels="<?= $chartJson(array_column($chart['rows'], 'label')) ?>" data-guidance-chart-values="<?= $chartJson(array_column($chart['rows'], 'total')) ?>" role="img" aria-label="<?= disciplinaryDashboardEsc($chart['title']) ?> chart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
            <ul class="guidance-chart-legend<?= $chart['type'] === 'line' ? ' is-trend' : '' ?>" aria-label="<?= disciplinaryDashboardEsc($chart['title']) ?> legend">
                <?php if ($chartHasData): foreach ($chartLegendRows as $legendRow):
                    $legendLabel = (string) $legendRow['label'];
                    $legendTone = $chartLegendTones[strtolower($legendLabel)] ?? 'is-default';
                ?>
                    <li class="guidance-chart-legend-item">
                        <span class="guidance-chart-legend-swatch <?= disciplinaryDashboardEsc($legendTone) ?>" aria-hidden="true"></span>
                        <span class="guidance-chart-legend-label"><?= disciplinaryDashboardEsc($legendLabel) ?></span>
                        <?php if ($legendRow['total'] !== null): ?><strong class="guidance-chart-legend-value"><?= (int) $legendRow['total'] ?></strong><?php endif; ?>
                    </li>
                <?php endforeach; endif; ?>
            </ul>
        </article>
        <?php endforeach; ?>
        </section>
    </details>
    <div class="guidance-dashboard-grid">
        <section class="guidance-panel guidance-attention-panel">
            <div class="guidance-panel-heading">
                <div><p class="guidance-section-kicker">Priority queue</p><h2>Needs attention</h2></div>
                <span class="guidance-count-badge" aria-label="<?= count($attention) ?> items"><?= count($attention) ?></span>
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
                <div class="guidance-attention-list">
                    <?php foreach ($attention as $item): ?>
                    <article class="guidance-attention-item">
                        <div class="guidance-item-icon <?= $item['item_type'] === 'followup' ? 'is-followup' : 'is-referral' ?>"><i data-lucide="<?= $item['item_type'] === 'followup' ? 'clock-3' : 'triangle-alert' ?>" aria-hidden="true"></i></div>
                        <div class="guidance-item-copy">
                            <strong><?= disciplinaryDashboardEsc($item['student_name']) ?></strong>
                            <span><?= disciplinaryDashboardEsc($item['detail']) ?></span>
                            <small><?= disciplinaryDashboardEsc($item['state']) ?> · <?= disciplinaryDashboardEsc(date('M j, Y · g:i A', strtotime((string) $item['event_at']))) ?></small>
                        </div>
                        <a class="guidance-button guidance-button-secondary" href="<?= APP_URL ?>/dashboard/<?= $item['item_type'] === 'followup' ? 'guidance_case.php?case_id=' . (int) $item['case_id'] : 'guidance_referrals.php?status=' . urlencode((string) $item['state']) ?>">Review</a>
                    </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <section class="guidance-panel guidance-upcoming-panel">
            <div class="guidance-panel-heading">
                <div><p class="guidance-section-kicker">Scheduled work</p><h2>Upcoming follow-ups</h2></div>
                <a class="guidance-text-link" href="<?= APP_URL ?>/dashboard/guidance_followups.php">View all</a>
            </div>
            <?php if ($upcoming === []): ?>
                <div class="guidance-empty-state guidance-upcoming-empty">
                    <span class="guidance-empty-icon" aria-hidden="true"><i data-lucide="calendar-check-2"></i></span>
                    <div class="guidance-empty-copy">
                        <strong>No follow-ups scheduled.</strong>
                        <span>Scheduled follow-ups will appear here.</span>
                    </div>
                </div>
            <?php else: ?>
                <div class="guidance-agenda-list">
                    <?php foreach ($upcoming as $item): ?>
                    <a class="guidance-agenda-item" href="<?= APP_URL ?>/dashboard/guidance_case.php?case_id=<?= (int) $item['case_id'] ?>">
                        <time datetime="<?= disciplinaryDashboardEsc(date(DATE_ATOM, strtotime((string) $item['scheduled_at']))) ?>">
                            <span><?= disciplinaryDashboardEsc(date('M j', strtotime((string) $item['scheduled_at']))) ?></span>
                            <b><?= disciplinaryDashboardEsc(date('g:i A', strtotime((string) $item['scheduled_at']))) ?></b>
                        </time>
                        <span><strong><?= disciplinaryDashboardEsc($item['student_name']) ?></strong><small>Case #<?= (int) $item['case_id'] ?></small></span>
                        <i data-lucide="chevron-right" aria-hidden="true"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <section class="guidance-panel guidance-recent-activity-panel">
            <div class="guidance-panel-heading">
                <div><p class="guidance-section-kicker">Assigned work</p><h2>Recent case activity</h2></div>
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
                    <a class="guidance-activity-item" href="<?= APP_URL ?>/dashboard/guidance_case.php?case_id=<?= (int) $item['guidance_case_id'] ?>">
                        <span class="guidance-activity-dot <?= $activityTone ?>" aria-hidden="true"></span>
                        <span class="guidance-activity-icon <?= $activityTone ?>" aria-hidden="true"><i data-lucide="<?= disciplinaryDashboardEsc($activityIcon) ?>"></i></span>
                        <span class="guidance-activity-copy">
                            <strong><?= disciplinaryDashboardEsc($item['event_type']) ?></strong>
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
