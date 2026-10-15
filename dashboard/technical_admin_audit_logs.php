<?php

require_once __DIR__ . '/../auth/role_helper.php';

$allowedRoles = [
    ROLE_TECHNICAL_ADMINISTRATOR
];

require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../includes/helper/flash_message.php';

// MySQL TIMESTAMP values are returned in the connection session timezone.
// Match that wall-clock representation to APRISM's configured institutional
// timezone without relying on MySQL's unavailable named-timezone tables.
$institutionalTimezone = new DateTimeZone(APRISM_TIMEZONE);
$institutionalOffset = $institutionalTimezone->getOffset(new DateTimeImmutable('now', $institutionalTimezone));
$offsetSign = $institutionalOffset < 0 ? '-' : '+';
$offsetMagnitude = abs($institutionalOffset);
$auditSessionOffset = sprintf(
    '%s%02d:%02d',
    $offsetSign,
    intdiv($offsetMagnitude, 3600),
    intdiv($offsetMagnitude % 3600, 60)
);
$pdo->exec('SET time_zone = ' . $pdo->quote($auditSessionOffset));

$activePage = 'audit_logs';

$fullName =
    ($_SESSION['first_name'] ?? '') . ' ' .
    ($_SESSION['last_name'] ?? '');

$initials =
    strtoupper(substr($_SESSION['first_name'] ?? '', 0, 1)) .
    strtoupper(substr($_SESSION['last_name'] ?? '', 0, 1));

$search = trim($_GET['search'] ?? '');
$actionFilter = trim($_GET['action'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$parseAuditDate = static function (string $value) use ($institutionalTimezone): ?DateTimeImmutable {
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $institutionalTimezone);
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
        return null;
    }

    return $date;
};
$dateFromValue = $parseAuditDate($dateFrom);
$dateToValue = $parseAuditDate($dateTo);
$dateFrom = $dateFromValue?->format('Y-m-d') ?? '';
$dateTo = $dateToValue?->format('Y-m-d') ?? '';

$requestedRecordsPerPage = filter_input(INPUT_GET, 'per_page', FILTER_VALIDATE_INT);
$recordsPerPage = $requestedRecordsPerPage && $requestedRecordsPerPage > 0
    ? min($requestedRecordsPerPage, 100)
    : 6;
$focusAuditId = filter_input(INPUT_GET, 'focus_id', FILTER_VALIDATE_INT) ?: 0;
$anchorAuditId = filter_input(INPUT_GET, 'anchor_id', FILTER_VALIDATE_INT) ?: 0;
$focusAuditOffset = null;

$currentPage = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT
);

if (!$currentPage || $currentPage < 1) {
    $currentPage = 1;
}

$offset = ($currentPage - 1) * $recordsPerPage;

$whereConditions = [];
$queryParams = [];

if ($search !== '') {

    $whereConditions[] = "
        (
            CONCAT(u.first_name, ' ', u.last_name) LIKE :search_name
            OR u.employee_number LIKE :search_employee
            OR u.username LIKE :search_username
            OR r.role_name LIKE :search_role
            OR (
                a.user_id IS NULL
                AND 'System' LIKE :search_system
            )
            OR a.action LIKE :search_action
            OR a.description LIKE :search_description
            OR a.ip_address LIKE :search_ip
        )
    ";

    $searchValue = '%' . $search . '%';

    $queryParams[':search_name'] = $searchValue;
    $queryParams[':search_employee'] = $searchValue;
    $queryParams[':search_username'] = $searchValue;
    $queryParams[':search_role'] = $searchValue;
    $queryParams[':search_system'] = $searchValue;
    $queryParams[':search_action'] = $searchValue;
    $queryParams[':search_description'] = $searchValue;
    $queryParams[':search_ip'] = $searchValue;
}

if ($actionFilter !== '') {

    $whereConditions[] = "
        a.action = :action_filter
    ";

    $queryParams[':action_filter'] = $actionFilter;
}

if ($dateFrom !== '') {

    $whereConditions[] = 'a.created_at >= :date_from';

    $queryParams[':date_from'] = $dateFromValue->format('Y-m-d 00:00:00');
}

if ($dateTo !== '') {

    $whereConditions[] = 'a.created_at < :date_to';

    $queryParams[':date_to'] = $dateToValue->modify('+1 day')->format('Y-m-d 00:00:00');
}

$whereSql = '';

if (!empty($whereConditions)) {
    $whereSql = ' WHERE ' . implode(' AND ', $whereConditions);
}

// Resolve a dashboard deep link or resize anchor against active filters and ordering.
$positionAuditId = $focusAuditId > 0 ? $focusAuditId : $anchorAuditId;
if ($positionAuditId > 0) {
    try {
        $focusRecordSql = "
            SELECT a.created_at
            FROM audit_logs a
            LEFT JOIN users u ON a.user_id = u.user_id
            LEFT JOIN roles r ON u.role_id = r.role_id
            {$whereSql}
            " . ($whereSql === '' ? ' WHERE ' : ' AND ') . "a.audit_log_id = :focus_id
            LIMIT 1
        ";
        $focusRecordStmt = $pdo->prepare($focusRecordSql);
        foreach ($queryParams as $key => $value) {
            $focusRecordStmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $focusRecordStmt->bindValue(':focus_id', $positionAuditId, PDO::PARAM_INT);
        $focusRecordStmt->execute();
        $focusCreatedAt = $focusRecordStmt->fetchColumn();

        if ($focusCreatedAt !== false) {
            $rankConditions = $whereConditions;
            $rankConditions[] = '(
                a.created_at > :focus_created_at_before
                OR (a.created_at = :focus_created_at_equal AND a.audit_log_id > :focus_id)
            )';
            $rankSql = '
                SELECT COUNT(*)
                FROM audit_logs a
                LEFT JOIN users u ON a.user_id = u.user_id
                LEFT JOIN roles r ON u.role_id = r.role_id
                WHERE ' . implode(' AND ', $rankConditions);
            $rankStmt = $pdo->prepare($rankSql);
            foreach ($queryParams as $key => $value) {
                $rankStmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $rankStmt->bindValue(':focus_created_at_before', $focusCreatedAt, PDO::PARAM_STR);
            $rankStmt->bindValue(':focus_created_at_equal', $focusCreatedAt, PDO::PARAM_STR);
            $rankStmt->bindValue(':focus_id', $positionAuditId, PDO::PARAM_INT);
            $rankStmt->execute();
            $focusAuditOffset = (int) $rankStmt->fetchColumn();
            $currentPage = intdiv($focusAuditOffset, $recordsPerPage) + 1;
            $offset = ($currentPage - 1) * $recordsPerPage;
        } else {
            $focusAuditId = 0;
            $anchorAuditId = 0;
        }
    } catch (Throwable $e) {
        error_log('[APRISM Audit Logs] Failed to resolve focused audit record: ' . $e->getMessage());
        $focusAuditId = 0;
        $anchorAuditId = 0;
    }
}


// Load available audit actions.
$auditActions = [];

try {

    $actionStmt = $pdo->query("
        SELECT DISTINCT action
        FROM audit_logs
        ORDER BY action ASC
    ");

    $auditActions = $actionStmt->fetchAll(PDO::FETCH_COLUMN);

} catch (Throwable $e) {

    error_log(
        '[APRISM Audit Logs] Failed to load actions: ' .
        $e->getMessage()
    );
}

$auditActionLabels = [
    'Guidance accept_referral' => 'Disciplinary Officer: Accept Referral',
    'Guidance add_note' => 'Disciplinary Officer: Add Case Note',
    'Guidance complete_followup' => 'Disciplinary Officer: Complete Follow-up',
    'Guidance schedule_followup' => 'Disciplinary Officer: Schedule Follow-up',
    'Guidance update_case' => 'Disciplinary Officer: Update Case'
];


// Count matching records.
$totalRecords = 0;
$totalPages = 1;
$auditCountError = false;

try {

    $countSql = "
        SELECT COUNT(*)
        FROM audit_logs a

        LEFT JOIN users u
            ON a.user_id = u.user_id

        LEFT JOIN roles r
            ON u.role_id = r.role_id

        {$whereSql}
    ";

    $countStmt = $pdo->prepare($countSql);

    foreach ($queryParams as $key => $value) {

        $countStmt->bindValue(
            $key,
            $value,
            PDO::PARAM_STR
        );
    }

    $countStmt->execute();

    $totalRecords = (int) $countStmt->fetchColumn();

    $totalPages = max(
        1,
        (int) ceil($totalRecords / $recordsPerPage)
    );

    if ($currentPage > $totalPages) {

        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $recordsPerPage;
    }

} catch (Throwable $e) {

    error_log(
        '[APRISM Audit Logs] Failed to count audit logs: ' .
        $e->getMessage()
    );

    $auditCountError = true;
    $currentPage = 1;
    $offset = 0;
}


// Load audit records.
$auditLogs = [];
$auditLoadError = false;

try {

    $sql = "
        SELECT
            a.audit_log_id,
            a.created_at,
            UNIX_TIMESTAMP(a.created_at) AS created_at_epoch,
            a.action,
            a.description,
            a.ip_address,
            u.user_id,

            COALESCE(
                u.employee_number,
                '—'
            ) AS employee_number,

            COALESCE(
                u.username,
                '—'
            ) AS username,

            CASE
                WHEN a.user_id IS NULL THEN 'System'
                ELSE CONCAT(
                    u.first_name,
                    ' ',
                    u.last_name
                )
            END AS full_name,

            COALESCE(
                r.role_name,
                'System'
            ) AS role_name

        FROM audit_logs a

        LEFT JOIN users u
            ON a.user_id = u.user_id

        LEFT JOIN roles r
            ON u.role_id = r.role_id

        {$whereSql}

        ORDER BY
            a.created_at DESC,
            a.audit_log_id DESC

        LIMIT :limit
        OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($queryParams as $key => $value) {

        $stmt->bindValue(
            $key,
            $value,
            PDO::PARAM_STR
        );
    }

    $stmt->bindValue(
        ':limit',
        $recordsPerPage,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':offset',
        $offset,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    error_log(
        '[APRISM Audit Logs] Failed to load audit logs: ' .
        $e->getMessage()
    );

    $auditLoadError = true;
}

$isAuditAjaxRequest = ($_GET['ajax'] ?? '') === '1';
if ($isAuditAjaxRequest) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $responseRecords = [];
    foreach ($auditLogs as $log) {
        $timestamp = (new DateTimeImmutable((string) $log['created_at'], $institutionalTimezone))
            ->setTimezone($institutionalTimezone)
            ->format('M d, Y h:i A');
        $responseRecords[] = [
            'audit_log_id' => (int) $log['audit_log_id'],
            'timestamp' => $timestamp,
            'full_name' => (string) $log['full_name'],
            'employee_number' => (string) $log['employee_number'],
            'username' => (string) $log['username'],
            'role_name' => (string) $log['role_name'],
            'action' => (string) $log['action'],
            'ip_address' => (string) ($log['ip_address'] ?? '—'),
            'description' => (string) ($log['description'] ?? ''),
        ];
    }

    $suggestions = [];
    $suggestionKeys = [];
    $lowercase = static fn (string $value): string => function_exists('mb_strtolower')
        ? mb_strtolower($value, 'UTF-8')
        : strtolower($value);
    $queryForSuggestions = $lowercase($search);
    foreach ($responseRecords as $record) {
        foreach ([
            ['type' => 'User', 'value' => $record['full_name']],
            ['type' => 'Employee no.', 'value' => $record['employee_number']],
            ['type' => 'Action', 'value' => $record['action']],
            ['type' => 'IP address', 'value' => $record['ip_address']],
        ] as $candidate) {
            $candidateValue = trim((string) $candidate['value']);
            $candidateLower = $lowercase($candidateValue);
            $key = $candidate['type'] . ':' . $candidateLower;
            if (
                $candidateValue === '' || $candidateValue === '—' ||
                !str_contains($candidateLower, $queryForSuggestions) ||
                isset($suggestionKeys[$key])
            ) {
                continue;
            }
            $suggestionKeys[$key] = true;
            $suggestions[] = ['type' => $candidate['type'], 'value' => $candidateValue];
            if (count($suggestions) >= 5) {
                break 2;
            }
        }
    }

    $firstRecord = $totalRecords > 0 ? $offset + 1 : 0;
    $lastRecord = min($offset + $recordsPerPage, $totalRecords);
    echo json_encode([
        'success' => !$auditCountError && !$auditLoadError,
        'records' => $responseRecords,
        'suggestions' => $suggestions,
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'records_per_page' => $recordsPerPage,
        'first_record' => $firstRecord,
        'last_record' => $lastRecord,
        'search' => $search,
        'has_filters' => $search !== '' || $actionFilter !== '' || $dateFrom !== '' || $dateTo !== '',
    ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    exit;
}


function buildAuditPageUrl(
    int $page,
    string $search,
    string $actionFilter,
    string $dateFrom,
    string $dateTo,
    int $recordsPerPage
): string {

    $params = [];

    if ($search !== '') {
        $params['search'] = $search;
    }

    if ($actionFilter !== '') {
        $params['action'] = $actionFilter;
    }

    if ($dateFrom !== '') {
        $params['date_from'] = $dateFrom;
    }

    if ($dateTo !== '') {
        $params['date_to'] = $dateTo;
    }

    $params['page'] = $page;
    $params['per_page'] = $recordsPerPage;

    return '?' . http_build_query($params);
}


$pageTitle = 'Audit Logs';

$activePage = 'audit_logs';

$roleStylesheet = 'assets/css/technical-admin.css';

$pageStylesheet = 'assets/css/pages/technical-admin-audit-logs.css';


/*
|--------------------------------------------------------------------------
| Active School Year
|--------------------------------------------------------------------------
*/

$currentSchoolYear = null;

try {

    $stmt = $pdo->query("
        SELECT school_year
        FROM school_years
        WHERE status = 'Active'
        LIMIT 1
    ");

    $currentSchoolYear = $stmt->fetchColumn() ?: null;

} catch (PDOException $e) {

    error_log(
        '[APRISM Active School Year] ' .
        $e->getMessage()
    );

}

?>

<!DOCTYPE html>
<html lang="en">

<?php
require_once __DIR__ . '/../includes/components/head.php';
?>


<body>

    <?php require __DIR__ . '/../includes/components/sidebar.php'; ?>

    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">

        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>

        <section class="page-header">

            <div>

                <h1 class="page-title">
                    System Audit Logs
                </h1>

            </div>

            <div class="record-count" id="auditRecordCount" <?= $auditCountError ? 'role="status"' : '' ?>>

                <?php if ($auditCountError): ?>

                    Count unavailable

                <?php else: ?>

                    <?= number_format($totalRecords) ?>

                    <?= $totalRecords === 1
                        ? 'record'
                        : 'records'
                        ?>

                <?php endif; ?>

            </div>

        </section>

        <section class="audit-card" data-adaptive-capacity-surface>

            <form method="GET" class="filter-form" id="auditFiltersForm">

                <input type="hidden" name="per_page" value="<?= $recordsPerPage ?>">

                <div class="filter-group search-group">

                    <label class="filter-label" for="auditSearch">
                        Search
                    </label>

                    <div class="filter-control-wrapper audit-search-wrapper">

                        <i data-lucide="search" class="filter-search-icon"></i>

                        <input type="search" id="auditSearch" name="search" class="filter-control search"
                            placeholder="User, employee no., action, IP..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                            role="combobox" aria-label="Search audit logs" aria-autocomplete="list"
                            aria-expanded="false" aria-controls="auditSearchSuggestions" aria-haspopup="listbox">
                        <button type="button" id="clearAuditSearch" class="audit-search-clear" aria-label="Clear search"
                            title="Clear search" data-aprism-tooltip="Clear search" <?= $search === '' ? 'hidden' : '' ?>>
                            <i data-lucide="x" aria-hidden="true"></i>
                        </button>
                        <div id="auditSearchSuggestions" class="audit-search-suggestions" role="listbox" aria-label="Audit log suggestions" hidden></div>

                    </div>

                </div>

                <div class="filter-group">

                    <label class="filter-label" for="actionFilter">
                        Action
                    </label>

                    <select id="actionFilter" name="action" class="filter-control">

                        <option value="">
                            All Actions
                        </option>

                        <?php foreach ($auditActions as $auditAction): ?>

                            <option value="<?= htmlspecialchars($auditAction) ?>" <?= $actionFilter === $auditAction
                                  ? 'selected'
                                  : ''
                                  ?>>

                                <?= htmlspecialchars($auditActionLabels[$auditAction] ?? $auditAction) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="filter-group">

                    <label class="filter-label" for="dateFrom">
                        From
                    </label>

                    <input type="date" id="dateFrom" name="date_from" class="filter-control"
                        value="<?= htmlspecialchars($dateFrom) ?>">

                </div>

                <div class="filter-group">

                    <label class="filter-label" for="dateTo">
                        To
                    </label>

                    <input type="date" id="dateTo" name="date_to" class="filter-control"
                        value="<?= htmlspecialchars($dateTo) ?>">

                </div>

                <div class="filter-actions">

                    <button type="submit" class="filter-btn apply">

                        <i data-lucide="search"></i>

                        Apply

                    </button>

                    <a href="<?= APP_URL ?>/dashboard/technical_admin_audit_logs.php" class="filter-btn clear">

                        <i data-lucide="rotate-ccw"></i>

                        Clear

                    </a>

                </div>

            </form>

            <script>
                document.documentElement.classList.add('adaptive-record-capacity-js');
            </script>

            <div
                class="audit-table-container"
                data-audit-table
                data-adaptive-record-capacity
                data-capacity-pending="true"
                data-page-size="<?= $recordsPerPage ?>"
                data-total-records="<?= $totalRecords ?>"
                data-current-page="<?= $currentPage ?>"
                data-adaptive-recheck="viewport"
                data-focus-id="<?= $focusAuditId ?>"
                data-page-param="per_page"
                data-anchor-param="anchor_id"
                data-focus-param="focus_id"
                data-pagination-selector=".pagination-container"
                aria-busy="true"
            >


                <table class="table audit-table align-middle">

                    <thead>

                        <tr>

                            <th>Timestamp</th>
                            <th>User</th>
                            <th class="text-center">Role</th>
                            <th class="text-center">Action</th>
                            <th>IP Address</th>
                            <th class="text-center">Details</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if ($auditLoadError): ?>

                            <tr>

                                <td colspan="6">

                                    <div class="empty-state">

                                        <i data-lucide="triangle-alert"></i>

                                        <div class="empty-state-title">
                                            Unable to load audit records.
                                        </div>

                                        <p class="empty-state-text">
                                            Please refresh the page or try again later.
                                        </p>

                                    </div>

                                </td>

                            </tr>

                        <?php elseif (empty($auditLogs)): ?>

                            <tr>

                                <td colspan="6">

                                    <div class="empty-state">

                                        <i data-lucide="file-search"></i>

                                        <div class="empty-state-title">

                                            <?php if (
                                                $search !== '' ||
                                                $actionFilter !== '' ||
                                                $dateFrom !== '' ||
                                                $dateTo !== ''
                                            ): ?>

                                                No matching audit records.

                                            <?php else: ?>

                                                No audit records yet.

                                            <?php endif; ?>

                                        </div>

                                        <p class="empty-state-text">

                                            <?php if (
                                                $search !== '' ||
                                                $actionFilter !== '' ||
                                                $dateFrom !== '' ||
                                                $dateTo !== ''
                                            ): ?>

                                                Try adjusting or clearing the current filters.

                                            <?php else: ?>

                                                Administrative and system activity will automatically appear here.

                                            <?php endif; ?>

                                        </p>

                                    </div>

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($auditLogs as $log): ?>

                                <tr id="audit-record-<?= (int) $log['audit_log_id'] ?>" data-adaptive-record data-record-anchor="<?= (int) $log['audit_log_id'] ?>"<?= (int) $log['audit_log_id'] === $focusAuditId ? ' class="destination-focus-target" tabindex="-1"' : '' ?>>

                                    <td class="font-mono-custom audit-timestamp">

                                        <?= htmlspecialchars((new DateTimeImmutable((string) $log['created_at'], $institutionalTimezone))->setTimezone($institutionalTimezone)->format('M d, Y h:i A'), ENT_QUOTES, 'UTF-8') ?>

                                    </td>

                                    <td>

                                        <div class="audit-user-value">
                                            <div class="audit-user">
                                                <?= htmlspecialchars($log['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <div class="audit-employee">
                                                <?= htmlspecialchars($log['employee_number'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        </div>

                                    </td>

                                    <td class="text-center">

                                        <span class="badge-role">

                                            <?= htmlspecialchars(
                                                $log['role_name']
                                            ) ?>

                                        </span>

                                    </td>

                                    <td class="text-center">

                                        <span class="action-badge">

                                            <?= htmlspecialchars(
                                                $log['action']
                                            ) ?>

                                        </span>

                                    </td>

                                    <td class="font-mono-custom audit-ip-address">

                                        <?= htmlspecialchars(
                                            $log['ip_address'] ?? '—'
                                        ) ?>

                                    </td>

                                    <td class="text-center">

                                        <button type="button" class="details-view-btn" title="View details" aria-label="View details for <?= htmlspecialchars($log['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-aprism-tooltip="View details"
                                            data-bs-toggle="modal" data-bs-target="#auditDetailsModal"
                                            data-audit-id="<?= (int) $log['audit_log_id'] ?>" data-user="<?= htmlspecialchars(
                                                   $log['full_name'],
                                                   ENT_QUOTES,
                                                   'UTF-8'
                                               ) ?>" data-employee="<?= htmlspecialchars(
                                                    $log['employee_number'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>" data-username="<?= htmlspecialchars(
                                                     $log['username'],
                                                     ENT_QUOTES,
                                                     'UTF-8'
                                                 ) ?>" data-role="<?= htmlspecialchars(
                                                      $log['role_name'],
                                                      ENT_QUOTES,
                                                      'UTF-8'
                                                  ) ?>" data-action="<?= htmlspecialchars(
                                                       $log['action'],
                                                       ENT_QUOTES,
                                                       'UTF-8'
                                                   ) ?>" data-ip="<?= htmlspecialchars(
                                                        $log['ip_address'] ?? '—',
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>" data-date="<?= htmlspecialchars(
                                                         (new DateTimeImmutable((string) $log['created_at'], $institutionalTimezone))->setTimezone($institutionalTimezone)->format('M d, Y h:i A'),
                                                         ENT_QUOTES,
                                                         'UTF-8'
                                                     ) ?>" data-description="<?= htmlspecialchars(
                                                          $log['description'] ?? '',
                                                          ENT_QUOTES,
                                                          'UTF-8'
                                                      ) ?>">

                                            <i data-lucide="eye"></i>

                                        </button>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <?php if (!$auditLoadError && !$auditCountError): ?>

                <?php
                $firstRecord = $totalRecords > 0 ? $offset + 1 : 0;
                $lastRecord = min($offset + $recordsPerPage, $totalRecords);
                ?>

                <div class="pagination-container" data-capacity-pending="true" <?= $totalRecords === 0 ? 'hidden' : '' ?>>

                    <div class="pagination-info">

                        Showing <span id="auditPaginationStart"><?= number_format($firstRecord) ?></span>
                        to <span id="auditPaginationEnd"><?= number_format($lastRecord) ?></span>
                        of <span id="auditPaginationTotal"><?= number_format($totalRecords) ?></span> records

                    </div>

                    <?php if ($totalPages > 1): ?>

                        <nav class="pagination-controls" id="auditPaginationControls" aria-label="Audit log pages">

                            <?php if ($currentPage <= 1): ?><span class="page-link-custom disabled" aria-label="First page" aria-disabled="true" title="First page"><i data-lucide="chevrons-left" aria-hidden="true"></i></span><?php else: ?><a href="<?= htmlspecialchars(buildAuditPageUrl(1, $search, $actionFilter, $dateFrom, $dateTo, $recordsPerPage)) ?>" class="page-link-custom" aria-label="First page" title="First page" data-aprism-tooltip="First page"><i data-lucide="chevrons-left" aria-hidden="true"></i></a><?php endif; ?>

                            <?php if ($currentPage <= 1): ?><span class="page-link-custom disabled" aria-label="Previous page" aria-disabled="true" title="Previous page"><i data-lucide="chevron-left" aria-hidden="true"></i></span><?php else: ?><a href="<?= htmlspecialchars(buildAuditPageUrl($currentPage - 1, $search, $actionFilter, $dateFrom, $dateTo, $recordsPerPage)) ?>" class="page-link-custom" aria-label="Previous page" title="Previous page" data-aprism-tooltip="Previous page"><i data-lucide="chevron-left" aria-hidden="true"></i></a><?php endif; ?>

                            <?php

                            $startPage = max(
                                1,
                                min(
                                    $currentPage - 1,
                                    $totalPages - 2
                                )
                            );

                            $endPage = min(
                                $totalPages,
                                $startPage + 2
                            );

                            ?>

                            <?php for (
                                $page = $startPage;
                                $page <= $endPage;
                                $page++
                            ): ?>

                                <a href="<?= htmlspecialchars(
                                    buildAuditPageUrl(
                                        $page,
                                        $search,
                                        $actionFilter,
                                        $dateFrom,
                                        $dateTo,
                                        $recordsPerPage
                                    )
                                ) ?>" class="page-link-custom <?= $page === $currentPage
                                     ? 'active'
                                     : ''
                                     ?>" aria-label="Page <?= $page ?>" <?= $page === $currentPage ? 'aria-current="page"' : '' ?>>

                                    <?= $page ?>

                                </a>

                            <?php endfor; ?>

                            <?php if ($currentPage >= $totalPages): ?><span class="page-link-custom disabled" aria-label="Next page" aria-disabled="true" title="Next page"><i data-lucide="chevron-right" aria-hidden="true"></i></span><?php else: ?><a href="<?= htmlspecialchars(buildAuditPageUrl($currentPage + 1, $search, $actionFilter, $dateFrom, $dateTo, $recordsPerPage)) ?>" class="page-link-custom" aria-label="Next page" title="Next page" data-aprism-tooltip="Next page"><i data-lucide="chevron-right" aria-hidden="true"></i></a><?php endif; ?>

                            <?php if ($currentPage >= $totalPages): ?><span class="page-link-custom disabled" aria-label="Last page" aria-disabled="true" title="Last page"><i data-lucide="chevrons-right" aria-hidden="true"></i></span><?php else: ?><a href="<?= htmlspecialchars(buildAuditPageUrl($totalPages, $search, $actionFilter, $dateFrom, $dateTo, $recordsPerPage)) ?>" class="page-link-custom" aria-label="Last page" title="Last page" data-aprism-tooltip="Last page"><i data-lucide="chevrons-right" aria-hidden="true"></i></a><?php endif; ?>

                        </nav>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </section>

    </main>


    <div class="modal fade" id="auditDetailsModal" tabindex="-1" aria-labelledby="auditDetailsModalLabel"
        aria-hidden="true" data-aprism-dialog-focus>

        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header">

                    <h2 class="modal-title" id="auditDetailsModalLabel" tabindex="-1" data-aprism-dialog-initial-focus>

                        Audit Log Details

                    </h2>

                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    </button>

                </div>

                <div class="modal-body">

                    <div class="detail-grid">

                        <div class="detail-item">

                            <div class="detail-label">
                                Audit ID
                            </div>

                            <div class="detail-value" id="detailAuditId">
                                —
                            </div>

                        </div>

                        <div class="detail-item">

                            <div class="detail-label">
                                Timestamp
                            </div>

                            <div class="detail-value" id="detailDate">
                                —
                            </div>

                        </div>

                        <div class="detail-item">

                            <div class="detail-label">
                                User
                            </div>

                            <div class="detail-value" id="detailUser">
                                —
                            </div>

                        </div>

                        <div class="detail-item">

                            <div class="detail-label">
                                Employee Number
                            </div>

                            <div class="detail-value" id="detailEmployee">
                                —
                            </div>

                        </div>

                        <div class="detail-item">

                            <div class="detail-label">
                                Username
                            </div>

                            <div class="detail-value" id="detailUsername">
                                —
                            </div>

                        </div>

                        <div class="detail-item">

                            <div class="detail-label">
                                Role
                            </div>

                            <div class="detail-value" id="detailRole">
                                —
                            </div>

                        </div>

                        <div class="detail-item">

                            <div class="detail-label">
                                Action
                            </div>

                            <div class="detail-value" id="detailAction">
                                —
                            </div>

                        </div>

                        <div class="detail-item">

                            <div class="detail-label">
                                IP Address
                            </div>

                            <div class="detail-value" id="detailIp">
                                —
                            </div>

                        </div>

                        <div class="detail-item full">

                            <div class="detail-label">
                                Description
                            </div>

                            <div class="detail-description" id="detailDescription">
                                —
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <?php require_once __DIR__ . '/../includes/components/logout_modal.php'; ?>

    <script src="<?= APP_URL ?>/assets/js/components/adaptive-record-capacity.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/components/adaptive-record-capacity.js') ?>" defer></script>
    <script src="<?= APP_URL ?>/assets/js/pages/technical-admin-destination-focus.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/pages/technical-admin-destination-focus.js') ?>" defer></script>
    <script src="<?= APP_URL ?>/assets/js/pages/technical-admin-audit-logs.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/pages/technical-admin-audit-logs.js') ?>" defer></script>


    <script>

        const auditDetailsModal =
            document.getElementById('auditDetailsModal');

        auditDetailsModal?.addEventListener(
            'show.bs.modal',
            event => {

                const button = event.relatedTarget;

                if (!button) {
                    return;
                }

                const setText = (id, value) => {

                    const element =
                        document.getElementById(id);

                    if (element) {
                        element.textContent = value || '—';
                    }

                };

                setText(
                    'detailAuditId',
                    button.dataset.auditId
                );

                setText(
                    'detailDate',
                    button.dataset.date
                );

                setText(
                    'detailUser',
                    button.dataset.user
                );

                setText(
                    'detailEmployee',
                    button.dataset.employee
                );

                setText(
                    'detailUsername',
                    button.dataset.username
                );

                setText(
                    'detailRole',
                    button.dataset.role
                );

                setText(
                    'detailAction',
                    button.dataset.action
                );

                setText(
                    'detailIp',
                    button.dataset.ip
                );

                setText(
                    'detailDescription',
                    button.dataset.description
                );

            }
        );

    </script>


    <?php
    require_once __DIR__ . '/../includes/components/footer.php';
    ?>

</body>

</html>
