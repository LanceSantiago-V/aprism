<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../auth/role_helper.php';
require_once __DIR__ . '/../includes/helper/flash_message.php';
require_once __DIR__ . '/../includes/helper/academic_head_schedule_helper.php';
require_once __DIR__ . '/../includes/helper/academic_head_pagination.php';

$allowedRoles = [ROLE_ACADEMIC_HEAD];
require_once __DIR__ . '/../auth/session_guard.php';

$pageTitle = 'Schedules';
$activePage = 'schedules';
$roleStylesheet = 'assets/css/academic-head.css';
$pageStylesheet = 'assets/css/pages/academic-head-schedules.css';

function academicScheduleEsc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'section_id' => filter_input(INPUT_GET, 'section_id', FILTER_VALIDATE_INT) ?: 0,
    'teacher_id' => filter_input(INPUT_GET, 'teacher_id', FILTER_VALIDATE_INT) ?: 0,
    'school_year_id' => filter_input(INPUT_GET, 'school_year_id', FILTER_VALIDATE_INT) ?: 0,
    'semester' => trim((string) ($_GET['semester'] ?? 'All')),
    'status' => trim((string) ($_GET['status'] ?? 'All')),
];
$filters['search'] = function_exists('mb_substr') ? mb_substr($filters['search'], 0, 100) : substr($filters['search'], 0, 100);

$sections = [];
$teachers = [];
$schoolYears = [];
$semesters = [];
$schedules = [];
$totalSchedules = 0;
$loadError = null;
$statuses = ['Active', 'Archived'];
$pageSize = 6;
$currentPage = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);

if (!in_array($filters['status'], array_merge(['All'], $statuses), true)) {
    $filters['status'] = 'All';
}

try {
    $sections = $pdo->query("SELECT section_id, section_name FROM sections ORDER BY section_name, section_id")->fetchAll(PDO::FETCH_ASSOC);
    $teachers = $pdo->query("SELECT u.user_id, u.employee_number, CONCAT_WS(' ', u.first_name, u.last_name) AS teacher_name\n        FROM users u INNER JOIN roles r ON r.role_id = u.role_id AND r.role_name = 'Teacher'\n        ORDER BY u.last_name, u.first_name, u.employee_number")->fetchAll(PDO::FETCH_ASSOC);
    $schoolYears = $pdo->query("SELECT school_year_id, school_year, status FROM school_years ORDER BY start_date DESC, school_year_id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $semesters = $pdo->query("SELECT DISTINCT semester FROM operational_classes WHERE semester IS NOT NULL AND semester <> '' ORDER BY semester")->fetchAll(PDO::FETCH_COLUMN);
    $totalSchedules = aprismAcademicSchedulesCount($pdo, $filters);
    $totalPages = max(1, (int) ceil($totalSchedules / $pageSize));
    $currentPage = min($currentPage, $totalPages);
    $schedules = aprismAcademicSchedulesPage($pdo, $filters, $pageSize, ($currentPage - 1) * $pageSize);
} catch (Throwable $exception) {
    error_log('[APRISM Academic Head Schedules] ' . $exception::class . ': ' . $exception->getMessage());
    $loadError = 'Unable to load institutional schedule records.';
    $totalPages = 1;
}

$firstSchedule = $totalSchedules > 0 ? (($currentPage - 1) * $pageSize) + 1 : 0;
$lastSchedule = $totalSchedules > 0 ? min(($currentPage - 1) * $pageSize + count($schedules), $totalSchedules) : 0;
$pageUrl = static function (int $page) use ($filters, $pageSize): string {
    $query = array_filter(array_merge($filters, ['per_page' => $pageSize]), static fn (mixed $value): bool => $value !== '' && $value !== 'All' && $value !== 0 && $value !== null);
    $query['page'] = max(1, $page);
    return '?' . http_build_query($query);
};

?>
<!doctype html>
<html lang="en">
<head><?php require __DIR__ . '/../includes/components/head.php'; ?></head>
<body>
    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>
    <main class="main-content schedules-page-shell <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">
        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>
        <div class="content-wrapper registry-page schedules-workspace">
            <section class="page-header">
                <div class="page-header-left"><h1 class="page-title">Schedules</h1></div>
            </section>

            <?php if ($flash['success'] || $flash['error'] || $loadError): ?>
                <div class="registry-feedback <?= ($flash['error'] || $loadError) ? 'is-error' : 'is-success' ?>" role="<?= ($flash['error'] || $loadError) ? 'alert' : 'status' ?>"><?= academicScheduleEsc($loadError ?: ($flash['error'] ?: $flash['success'])) ?></div>
            <?php endif; ?>

            <section class="module-card registry-records-card schedule-records-card">
                <form method="get" class="registry-filter-toolbar schedule-filter-toolbar" role="search">
                    <input type="hidden" name="per_page" value="<?= $pageSize ?>">
                    <label class="registry-filter-search"><span>Search</span><input type="search" name="search" value="<?= academicScheduleEsc($filters['search']) ?>" placeholder="Subject, section, teacher, or room"></label>
                    <label><span>Section</span><select name="section_id"><option value="0">All Sections</option><?php foreach ($sections as $section): ?><option value="<?= (int) $section['section_id'] ?>" <?= (int) $filters['section_id'] === (int) $section['section_id'] ? 'selected' : '' ?>><?= academicScheduleEsc($section['section_name']) ?></option><?php endforeach; ?></select></label>
                    <label><span>Teacher</span><select name="teacher_id"><option value="0">All Teachers</option><?php foreach ($teachers as $teacher): ?><option value="<?= (int) $teacher['user_id'] ?>" <?= (int) $filters['teacher_id'] === (int) $teacher['user_id'] ? 'selected' : '' ?>><?= academicScheduleEsc($teacher['teacher_name'] . ' · ' . $teacher['employee_number']) ?></option><?php endforeach; ?></select></label>
                    <label><span>School Year</span><select name="school_year_id"><option value="0">All School Years</option><?php foreach ($schoolYears as $schoolYear): ?><option value="<?= (int) $schoolYear['school_year_id'] ?>" <?= (int) $filters['school_year_id'] === (int) $schoolYear['school_year_id'] ? 'selected' : '' ?>><?= academicScheduleEsc($schoolYear['school_year']) ?></option><?php endforeach; ?></select></label>
                    <label><span>Context</span><select name="semester"><option value="All">All Contexts</option><?php foreach ($semesters as $semester): ?><option value="<?= academicScheduleEsc($semester) ?>" <?= $filters['semester'] === $semester ? 'selected' : '' ?>><?= academicScheduleEsc($semester) ?></option><?php endforeach; ?></select></label>
                    <label><span>Status</span><select name="status"><option value="All">All Statuses</option><?php foreach ($statuses as $status): ?><option value="<?= $status ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></label>
                    <div class="schedule-filter-actions">
                        <button type="submit" class="aprism-action-btn aprism-action-btn-primary is-standard">Filter</button>
                        <a class="aprism-action-btn aprism-action-btn-secondary is-standard" href="?per_page=<?= $pageSize ?>">Reset Filters</a>
                    </div>
                </form>

                <div class="registry-table-heading"><h2>Schedule records</h2><span><?= $loadError !== null ? 'Records unavailable' : number_format($totalSchedules) . ' schedule' . ($totalSchedules === 1 ? '' : 's') ?></span></div>
                <div class="table-wrapper registry-table-wrapper schedule-table-wrapper"><table class="module-table registry-table academic-schedule-table"><thead><tr><th class="table-row-number" scope="col">#</th><th>Subject</th><th>Section</th><th>Teacher</th><th>Schedule</th><th>Room</th><th>Academic Context</th><th>Status</th></tr></thead><tbody>
                    <?php if ($loadError !== null): ?>
                        <tr><td colspan="8"><div class="registry-empty-state" role="alert"><i data-lucide="circle-alert" aria-hidden="true"></i><h3>Schedule records unavailable</h3><p>Records could not be loaded. Refresh the page to try again.</p></div></td></tr>
                    <?php elseif ($schedules === []): ?>
                        <tr><td colspan="8"><div class="registry-empty-state"><i data-lucide="calendar-days" aria-hidden="true"></i><h3>No schedule records</h3><p><?= $filters['search'] !== '' || $filters['status'] !== 'All' ? 'No records match the current search and filters.' : 'Teacher-owned class schedules will appear here for oversight.' ?></p></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($schedules as $scheduleIndex => $schedule): ?>
                            <tr>
                                <td class="table-row-number" data-label="#"><?= $firstSchedule + $scheduleIndex ?></td>
                                <td class="registry-primary-cell" data-label="Subject"><strong><?= academicScheduleEsc($schedule['subject_name']) ?></strong><?php if ($schedule['subject_code']): ?><small><?= academicScheduleEsc($schedule['subject_code']) ?></small><?php endif; ?></td>
                                <td data-label="Section"><?= academicScheduleEsc($schedule['section_name']) ?></td>
                                <td data-label="Teacher"><strong><?= academicScheduleEsc($schedule['teacher_name']) ?></strong><small><?= academicScheduleEsc($schedule['employee_number']) ?></small></td>
                                <td data-label="Schedule"><strong><?= academicScheduleEsc($schedule['day']) ?></strong><small><?= academicScheduleEsc(substr((string) $schedule['start_time'], 0, 5)) ?> – <?= academicScheduleEsc(substr((string) $schedule['end_time'], 0, 5)) ?></small></td>
                                <td class="<?= trim((string) ($schedule['room'] ?? '')) === '' ? 'registry-value-empty' : '' ?>" data-label="Room"><?= academicScheduleEsc($schedule['room'] ?: '—') ?></td>
                                <td data-label="Academic Context"><strong><?= academicScheduleEsc($schedule['school_year']) ?></strong><?php if (trim((string) ($schedule['semester'] ?? '')) !== ''): ?><small><?= academicScheduleEsc($schedule['semester']) ?></small><?php endif; ?></td>
                                <td data-label="Status"><span class="registry-status-badge registry-status-<?= strtolower((string) $schedule['status']) ?>"><?= academicScheduleEsc($schedule['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody></table></div>
                <div class="schedule-mobile-list" aria-label="Schedule records">
                    <?php if ($loadError !== null): ?>
                        <div class="schedule-mobile-empty" role="alert"><i data-lucide="circle-alert" aria-hidden="true"></i><h3>Schedule records unavailable</h3><p>Records could not be loaded. Refresh the page to try again.</p></div>
                    <?php elseif ($schedules === []): ?>
                        <div class="schedule-mobile-empty"><i data-lucide="calendar-days" aria-hidden="true"></i><h3>No schedule records</h3><p><?= $filters['search'] !== '' || $filters['status'] !== 'All' ? 'No records match the current search and filters.' : 'Teacher-owned class schedules will appear here for oversight.' ?></p></div>
                    <?php else: ?>
                        <?php foreach ($schedules as $schedule): ?>
                            <article class="schedule-mobile-card">
                                <div class="schedule-mobile-card-heading">
                                    <div class="schedule-mobile-card-title"><strong><?= academicScheduleEsc($schedule['subject_name']) ?></strong><?php if ($schedule['subject_code']): ?><small><?= academicScheduleEsc($schedule['subject_code']) ?></small><?php endif; ?></div>
                                    <span class="registry-status-badge registry-status-<?= strtolower((string) $schedule['status']) ?>"><?= academicScheduleEsc($schedule['status']) ?></span>
                                </div>
                                <div class="schedule-mobile-card-fields">
                                    <div class="schedule-mobile-card-field"><span>Section</span><strong><?= academicScheduleEsc($schedule['section_name']) ?></strong></div>
                                    <div class="schedule-mobile-card-field"><span>Room</span><strong><?= academicScheduleEsc($schedule['room'] ?: '—') ?></strong></div>
                                    <div class="schedule-mobile-card-field is-wide"><span>Teacher</span><strong><?= academicScheduleEsc($schedule['teacher_name']) ?></strong><small><?= academicScheduleEsc($schedule['employee_number']) ?></small></div>
                                    <div class="schedule-mobile-card-field is-wide"><span>Schedule</span><strong><?= academicScheduleEsc($schedule['day']) ?> · <?= academicScheduleEsc(substr((string) $schedule['start_time'], 0, 5)) ?> – <?= academicScheduleEsc(substr((string) $schedule['end_time'], 0, 5)) ?></strong></div>
                                    <div class="schedule-mobile-card-field is-wide"><span>Academic Context</span><strong><?= academicScheduleEsc($schedule['school_year']) ?><?php if (trim((string) ($schedule['semester'] ?? '')) !== ''): ?> · <?= academicScheduleEsc($schedule['semester']) ?><?php endif; ?></strong></div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php if ($totalSchedules > 0 && $totalPages > 1): ?>
                    <div class="registry-pagination"><span>Showing <?= number_format($firstSchedule) ?> to <?= number_format($lastSchedule) ?> of <?= number_format($totalSchedules) ?> schedules</span>
                        <?php $startPage = max(1, min($currentPage - 1, $totalPages - 2)); $endPage = min($totalPages, $startPage + 2); ?>
                        <nav aria-label="Institutional Schedule pagination"><a class="registry-page-link <?= $currentPage === 1 ? 'disabled' : '' ?>" href="<?= academicScheduleEsc($pageUrl($currentPage - 1)) ?>" aria-label="Previous page"><i data-lucide="chevron-left"></i></a><?php for ($page = $startPage; $page <= $endPage; $page++): ?><a class="registry-page-link <?= $page === $currentPage ? 'active' : '' ?>" href="<?= academicScheduleEsc($pageUrl($page)) ?>"><?= $page ?></a><?php endfor; ?><a class="registry-page-link <?= $currentPage === $totalPages ? 'disabled' : '' ?>" href="<?= academicScheduleEsc($pageUrl($currentPage + 1)) ?>" aria-label="Next page"><i data-lucide="chevron-right"></i></a></nav>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
    <?php require __DIR__ . '/../includes/components/logout_modal.php'; ?>
    <?php require __DIR__ . '/../includes/components/footer.php'; ?>
    <script src="<?= APP_URL ?>/assets/js/academic-head-density.js?v=<?= filemtime(__DIR__ . '/../assets/js/academic-head-density.js') ?>" data-density-scope="fixed-six" defer></script>
</body>
</html>
