<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';
require_once __DIR__ . '/../includes/helper/responsibility_helper.php';
require_once __DIR__ . '/../includes/helper/adviser_helper.php';
require_once __DIR__ . '/../includes/helper/academic_head_pagination.php';
$allowedRoles = [ROLE_TEACHER];
require_once __DIR__ . '/../auth/session_guard.php';
$teacherId = (int) ($_SESSION['user_id'] ?? 0);
$active = $pdo->query("SELECT school_year_id,school_year FROM school_years WHERE status='Active' ORDER BY school_year_id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$currentSchoolYear = $active['school_year'] ?? null;
$yearId = $active ? (int) $active['school_year_id'] : null;
$assignments = aprismAdviserAssignments($pdo, $teacherId, $yearId);
if (
    !userHasResponsibility(RESPONSIBILITY_ADVISER)
    || $active === null
    || $assignments === []
) {
    http_response_code(403);
    exit('Adviser responsibility is not assigned for the current School Year.');
}
$rows = aprismAdviserReferralFollowups($pdo, $teacherId, $yearId);
$advisorySectionNames = [];
$advisoryGradeLevels = [];
foreach ($assignments as $assignment) {
    $sectionName = trim((string) ($assignment['section_name'] ?? ''));
    if ($sectionName !== '') {
        $advisorySectionNames[$sectionName] = $sectionName;
    }

    $yearLevel = trim((string) ($assignment['year_level'] ?? ''));
    if ($yearLevel !== '') {
        $gradeLabel = in_array($yearLevel, ['11', '12'], true)
            ? 'Grade ' . $yearLevel
            : (ctype_digit($yearLevel) ? 'Year ' . $yearLevel : $yearLevel);
        $advisoryGradeLevels[$gradeLabel] = $gradeLabel;
    }
}
$advisoryContextParts = array_values(array_filter([
    implode(', ', $advisorySectionNames),
    'Senior High School', // Adviser assignments are scoped to active SHS programs.
    implode(', ', $advisoryGradeLevels),
]));

$totalReferrals = count($rows);
$pageSize = aprismAcademicHeadPageSize('adviser');
$totalPages = $totalReferrals > 0 ? (int) ceil($totalReferrals / $pageSize) : 0;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$currentPage = $totalPages > 0 ? min($currentPage, $totalPages) : 1;
$offset = ($currentPage - 1) * $pageSize;
$visibleRows = array_slice($rows, $offset, $pageSize);
$firstReferral = $totalReferrals > 0 ? $offset + 1 : 0;
$lastReferral = $totalReferrals > 0
    ? min($offset + count($visibleRows), $totalReferrals)
    : 0;
$pageNumbers = $totalPages > 0
    ? ($totalPages <= 7
        ? range(1, $totalPages)
        : array_values(array_unique(array_filter(
            [1, $currentPage - 1, $currentPage, $currentPage + 1, $totalPages],
            static fn (int $page): bool => $page >= 1 && $page <= $totalPages
        ))))
    : [];
sort($pageNumbers, SORT_NUMERIC);
$pageUrl = static function (int $page) use ($pageSize): string {
    return '?' . http_build_query([
        'page' => max(1, $page),
        'per_page' => $pageSize,
    ]);
};
$pageTitle = 'Referral Follow-ups';
$activePage = 'referral_followups';
$roleStylesheet = 'assets/css/teacher.css';
$pageStylesheet = 'assets/css/pages/adviser.css'; ?><!doctype html>
<html lang="en">

<head><?php require __DIR__ . '/../includes/components/head.php'; ?></head>

<body><?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content adviser-followups-shell <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
        <div class="content-wrapper adviser-page adviser-followups-page">
            <div class="page-header adviser-page-header">
                <div>
                    <h1 class="teacher-page-title">Referral Follow-ups</h1>
                    <div class="adviser-students-context adviser-followups-context" aria-label="Current advisory class and academic context">
                        <?php foreach ($advisoryContextParts as $index => $contextPart): ?>
                            <?php if ($index > 0): ?><span class="adviser-students-context-separator" aria-hidden="true">·</span><?php endif; ?>
                            <span><?= htmlspecialchars($contextPart, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <section class="teacher-panel adviser-workspace-panel adviser-followups-panel" aria-labelledby="submitted-referrals-title">
                <div class="adviser-workspace-header adviser-followups-header">
                    <h2 class="teacher-panel-title" id="submitted-referrals-title">Submitted referrals</h2>
                    <span class="registry-result-count">
                        <?= number_format($totalReferrals) ?> <?= $totalReferrals === 1 ? 'Referral' : 'Referrals' ?>
                    </span>
                </div>

                <?php if ($totalReferrals === 0): ?>
                    <div class="teacher-empty-state adviser-followups-empty-state" role="status">
                        <span class="adviser-followups-empty-icon" aria-hidden="true"><i data-lucide="clipboard-check"></i></span>
                        <h3 class="teacher-empty-state-title">No submitted referrals yet</h3>
                        <p class="teacher-empty-state-text">Submitted referrals for Students in your assigned advisory scope will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="adviser-table-shell adviser-followups-table-shell">
                        <table class="teacher-table adviser-followup-table adviser-followups-table">
                            <caption class="visually-hidden">Referral records in the current advisory scope</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Student Number</th>
                                    <th scope="col">Student</th>
                                    <th scope="col">Referral Concern</th>
                                    <th scope="col">Referring Context</th>
                                    <th scope="col">Submitted</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($visibleRows as $row): ?>
                                    <?php
                                    $rawCreatedAt = (string) ($row['created_at'] ?? '');
                                    $createdTimestamp = strtotime($rawCreatedAt);
                                    $submittedLabel = $createdTimestamp === false
                                        ? $rawCreatedAt
                                        : date('M j, Y', $createdTimestamp);
                                    $status = (string) ($row['status'] ?? '');
                                    $statusClass = match ($status) {
                                        'Submitted' => 'is-submitted',
                                        'Returned' => 'is-returned',
                                        'Accepted' => 'is-accepted',
                                        'Closed' => 'is-closed',
                                        default => 'is-other',
                                    };
                                    $referringContext = trim(
                                        (string) ($row['subject_name'] ?? 'Class unavailable')
                                        . ' — '
                                        . (string) ($row['section_name'] ?? '')
                                    );
                                    $detailUrl = APP_URL . '/dashboard/teacher_referral_detail.php?referral_id=' . (int) $row['teacher_referral_id'];
                                    ?>
                                    <tr data-adviser-followup-row>
                                        <td data-label="Student Number"><?= htmlspecialchars((string) $row['student_number'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td data-label="Student"><?= htmlspecialchars((string) $row['student_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td data-label="Referral Concern"><p class="adviser-followups-reason"><?= nl2br(htmlspecialchars((string) $row['reason'], ENT_QUOTES, 'UTF-8')) ?></p></td>
                                        <td data-label="Referring Context"><?= htmlspecialchars($referringContext, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td data-label="Submitted"><time datetime="<?= htmlspecialchars($rawCreatedAt, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($submittedLabel, ENT_QUOTES, 'UTF-8') ?></time></td>
                                        <td data-label="Status"><span class="adviser-followups-status <?= $statusClass ?>"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td data-label="Action"><a class="aprism-action-btn aprism-action-btn-secondary adviser-followups-view" href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>">View referral</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="teacher-pagination adviser-followups-pagination">
                        <div class="teacher-pagination-info">
                            Showing <?= number_format($firstReferral) ?>–<?= number_format($lastReferral) ?> of <?= number_format($totalReferrals) ?> <?= $totalReferrals === 1 ? 'referral' : 'referrals' ?>
                        </div>
                        <?php if ($totalPages > 1): ?>
                            <nav class="teacher-pagination-controls" aria-label="Referral follow-ups pagination">
                                <?php if ($currentPage > 1): ?>
                                    <a class="teacher-page-link" href="<?= htmlspecialchars($pageUrl($currentPage - 1), ENT_QUOTES, 'UTF-8') ?>" rel="prev">Previous</a>
                                <?php else: ?>
                                    <span class="teacher-page-link disabled" aria-disabled="true">Previous</span>
                                <?php endif; ?>
                                <?php $lastDisplayedPage = 0; ?>
                                <?php foreach ($pageNumbers as $page): ?>
                                    <?php if ($lastDisplayedPage > 0 && $page - $lastDisplayedPage > 1): ?><span class="teacher-page-ellipsis" aria-hidden="true">…</span><?php endif; ?>
                                    <a class="teacher-page-link <?= $page === $currentPage ? 'active' : '' ?>" href="<?= htmlspecialchars($pageUrl($page), ENT_QUOTES, 'UTF-8') ?>" <?= $page === $currentPage ? 'aria-current="page"' : '' ?> aria-label="Page <?= $page ?>"><?= $page ?></a>
                                    <?php $lastDisplayedPage = $page; ?>
                                <?php endforeach; ?>
                                <?php if ($currentPage < $totalPages): ?>
                                    <a class="teacher-page-link" href="<?= htmlspecialchars($pageUrl($currentPage + 1), ENT_QUOTES, 'UTF-8') ?>" rel="next">Next</a>
                                <?php else: ?>
                                    <span class="teacher-page-link disabled" aria-disabled="true">Next</span>
                                <?php endif; ?>
                            </nav>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main><?php require __DIR__ . '/../includes/components/footer.php'; ?>
    <?php if ($totalReferrals > 0): ?>
        <script src="<?= APP_URL ?>/assets/js/academic-head-density.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/academic-head-density.js') ?>" data-density-scope="adviser"></script>
    <?php endif; ?>
</body>

</html>
