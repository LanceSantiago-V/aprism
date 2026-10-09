<?php

$gradeWorkspaceActive = $gradeWorkspaceActive ?? '';
$gradeWorkspaceTitle = $gradeWorkspaceTitle ?? 'Grades';
$gradeWorkspaceClassId = (int) ($gradeWorkspaceClassId ?? 0);
$gradeWorkspacePeriodId = (int) ($gradeWorkspacePeriodId ?? 0);
$gradeWorkspaceLedgerQuery = array_filter([
    'class_id' => $gradeWorkspaceClassId ?: null,
    'period_id' => $gradeWorkspacePeriodId ?: null,
]);
$gradeWorkspaceAnalyticsQuery = $gradeWorkspaceLedgerQuery;
$gradeWorkspaceImportQuery = array_filter([
    'operational_class_id' => $gradeWorkspaceClassId ?: null,
]);
?>

<header class="grade-workspace-header">
    <p class="grade-workspace-eyebrow">Teacher workspace · Grades</p>
    <h1><?= htmlspecialchars($gradeWorkspaceTitle, ENT_QUOTES, 'UTF-8') ?></h1>
</header>

<nav class="grade-workspace-switcher" aria-label="Grade workspace">
    <a class="<?= $gradeWorkspaceActive === 'import' ? 'active' : '' ?>" href="<?= APP_URL ?>/dashboard/teacher_grade_import.php<?= $gradeWorkspaceImportQuery !== [] ? '?' . http_build_query($gradeWorkspaceImportQuery) : '' ?>">
        <i data-lucide="file-up" aria-hidden="true"></i><span>Grade Import</span>
    </a>
    <a class="<?= $gradeWorkspaceActive === 'ledger' ? 'active' : '' ?>" href="<?= APP_URL ?>/dashboard/teacher_grade_ledger.php<?= $gradeWorkspaceLedgerQuery !== [] ? '?' . http_build_query($gradeWorkspaceLedgerQuery) : '' ?>">
        <i data-lucide="table-2" aria-hidden="true"></i><span>Grade Ledger</span>
    </a>
    <a class="<?= $gradeWorkspaceActive === 'analytics' ? 'active' : '' ?>" href="<?= APP_URL ?>/dashboard/teacher_grade_analytics.php<?= $gradeWorkspaceAnalyticsQuery !== [] ? '?' . http_build_query($gradeWorkspaceAnalyticsQuery) : '' ?>">
        <i data-lucide="chart-no-axes-column" aria-hidden="true"></i><span>Grade Analytics</span>
    </a>
</nav>
