<?php

require_once __DIR__ . '/../auth/role_helper.php';

$allowedRoles = [
    ROLE_TECHNICAL_ADMINISTRATOR
];

require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helper/flash_message.php';
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

$recordsPerPage = 6;

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

    $whereConditions[] = "
        a.created_at >= :date_from
    ";

    $queryParams[':date_from'] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {

    $whereConditions[] = "
        a.created_at <= :date_to
    ";

    $queryParams[':date_to'] = $dateTo . ' 23:59:59';
}

$whereSql = '';

if (!empty($whereConditions)) {
    $whereSql = ' WHERE ' . implode(' AND ', $whereConditions);
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

// Count matching records.
$totalRecords = 0;
$totalPages = 1;

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
}

// Load audit records.
$auditLogs = [];
$auditLoadError = false;

try {

    $sql = "
        SELECT
            a.audit_log_id,
            a.created_at,
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

function buildAuditPageUrl(
    int $page,
    string $search,
    string $actionFilter,
    string $dateFrom,
    string $dateTo
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

    return '?' . http_build_query($params);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Audit Logs - APRISM</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700;800&display=swap"
        rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/base.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/technical-admin.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/pages/technical-admin-audit-logs.css">

</head>

<body>

    <?php require __DIR__ . '/../includes/components/technical_admin_sidebar.php'; ?>

    <main class="main-content">

        <header class="top-navbar">

            <div class="navbar-left">

                <button class="mobile-menu-toggle" id="menuToggle" type="button" aria-label="Open sidebar">

                    <i data-lucide="menu"></i>

                </button>

                <button class="back-btn" id="sidebarToggle" type="button" aria-label="Toggle sidebar">

                    <i data-lucide="chevron-left" id="sidebarToggleIcon"></i>

                </button>

                <div class="search-wrapper">

                    <i data-lucide="search" class="search-icon"></i>

                    <input type="text" class="navbar-search" placeholder="Search...">

                </div>

            </div>

            <div class="navbar-right">

                <div class="active-term-badge">

                    <i data-lucide="calendar"></i>

                    <span>Academic Term: --</span>

                </div>

                <div class="notification-bell">

                    <i data-lucide="bell"></i>

                    <span class="notification-dot"></span>

                </div>

                <div class="user-profile">

                    <div class="profile-text">

                        <h4 class="profile-name">
                            <?= htmlspecialchars(trim($fullName)) ?>
                        </h4>

                        <p class="profile-role">
                            Technical Administrator
                        </p>

                    </div>

                    <div class="profile-avatar">
                        <?= htmlspecialchars($initials) ?>
                    </div>

                </div>

            </div>

        </header>

        <section class="page-header">

            <div>

                <h1 class="page-title">
                    System Audit Logs
                </h1>

                <p class="page-description">
                    Review recorded administrative and system activity throughout APRISM.
                </p>

            </div>

            <div class="record-count">

                <?= number_format($totalRecords) ?>

                <?= $totalRecords === 1
                    ? 'record'
                    : 'records'
                    ?>

            </div>

        </section>

        <section class="audit-card">

            <form method="GET" class="filter-form">

                <div class="filter-group search-group">

                    <label class="filter-label" for="auditSearch">
                        Search
                    </label>

                    <div class="filter-control-wrapper">

                        <i data-lucide="search" class="filter-search-icon"></i>

                        <input type="text" id="auditSearch" name="search" class="filter-control search"
                            placeholder="User, employee no., action, IP..." value="<?= htmlspecialchars($search) ?>">

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

                                <?= htmlspecialchars($auditAction) ?>

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

            <div class="audit-table-container">

                <table class="table audit-table align-middle">

                    <thead>

                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Action</th>
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

                                <tr>

                                    <td class="font-mono-custom">

                                        <?= date(
                                            'M d, Y h:i A',
                                            strtotime($log['created_at'])
                                        ) ?>

                                    </td>

                                    <td>

                                        <div class="audit-user">

                                            <?= htmlspecialchars(
                                                $log['full_name']
                                            ) ?>

                                        </div>

                                        <div class="audit-employee">

                                            <?= htmlspecialchars(
                                                $log['employee_number']
                                            ) ?>

                                        </div>

                                    </td>

                                    <td>

                                        <span class="badge-role">

                                            <?= htmlspecialchars(
                                                $log['role_name']
                                            ) ?>

                                        </span>

                                    </td>

                                    <td>

                                        <span class="action-badge">

                                            <?= htmlspecialchars(
                                                $log['action']
                                            ) ?>

                                        </span>

                                    </td>

                                    <td class="font-mono-custom">

                                        <?= htmlspecialchars(
                                            $log['ip_address'] ?? '—'
                                        ) ?>

                                    </td>

                                    <td class="text-center">

                                        <button type="button" class="details-view-btn" title="View Details"
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
                                                         date(
                                                             'M d, Y h:i A',
                                                             strtotime($log['created_at'])
                                                         ),
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

            <?php if (!$auditLoadError && $totalRecords > 0): ?>

                <?php

                $firstRecord = $offset + 1;

                $lastRecord = min(
                    $offset + $recordsPerPage,
                    $totalRecords
                );

                ?>

                <div class="pagination-container">

                    <div class="pagination-info">

                        Showing
                        <?= number_format($firstRecord) ?>
                        to
                        <?= number_format($lastRecord) ?>
                        of
                        <?= number_format($totalRecords) ?>
                        records

                    </div>

                    <?php if ($totalPages > 1): ?>

                        <div class="pagination-controls">

                            <a href="<?= $currentPage > 1
                                ? htmlspecialchars(
                                    buildAuditPageUrl(
                                        $currentPage - 1,
                                        $search,
                                        $actionFilter,
                                        $dateFrom,
                                        $dateTo
                                    )
                                )
                                : '#'
                                ?>" class="page-link-custom <?= $currentPage <= 1
                                ? 'disabled'
                                : ''
                                ?>" aria-label="Previous page">

                                <i data-lucide="chevron-left"></i>

                            </a>

                            <?php

                            $startPage = max(
                                1,
                                $currentPage - 2
                            );

                            $endPage = min(
                                $totalPages,
                                $currentPage + 2
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
                                        $dateTo
                                    )
                                ) ?>" class="page-link-custom <?= $page === $currentPage
                                     ? 'active'
                                     : ''
                                     ?>">

                                    <?= $page ?>

                                </a>

                            <?php endfor; ?>

                            <a href="<?= $currentPage < $totalPages
                                ? htmlspecialchars(
                                    buildAuditPageUrl(
                                        $currentPage + 1,
                                        $search,
                                        $actionFilter,
                                        $dateFrom,
                                        $dateTo
                                    )
                                )
                                : '#'
                                ?>" class="page-link-custom <?= $currentPage >= $totalPages
                                ? 'disabled'
                                : ''
                                ?>" aria-label="Next page">

                                <i data-lucide="chevron-right"></i>

                            </a>

                        </div>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </section>

    </main>

    <div class="modal fade" id="auditDetailsModal" tabindex="-1" aria-labelledby="auditDetailsModalLabel"
        aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header">

                    <h2 class="modal-title" id="auditDetailsModalLabel">

                        Audit Log Details

                    </h2>

                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    </button>

                </div>

                <div class="modal-body">

                    <div class="detail-grid">

                        <div class="detail-item">
                            <div class="detail-label">Audit ID</div>
                            <div class="detail-value" id="detailAuditId">—</div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">Timestamp</div>
                            <div class="detail-value" id="detailDate">—</div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">User</div>
                            <div class="detail-value" id="detailUser">—</div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">Employee Number</div>
                            <div class="detail-value" id="detailEmployee">—</div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">Username</div>
                            <div class="detail-value" id="detailUsername">—</div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">Role</div>
                            <div class="detail-value" id="detailRole">—</div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">Action</div>
                            <div class="detail-value" id="detailAction">—</div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">IP Address</div>
                            <div class="detail-value" id="detailIp">—</div>
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

    <script src="https://unpkg.com/lucide@latest"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
        </script>

    <script>

        const sidebar =
            document.getElementById('sidebar');

        const mainContent =
            document.querySelector('.main-content');

        const sidebarToggle =
            document.getElementById('sidebarToggle');

        const menuToggle =
            document.getElementById('menuToggle');

        menuToggle?.addEventListener('click', () => {
            sidebar?.classList.toggle('open');
        });

        sidebarToggle?.addEventListener('click', () => {

            sidebar?.classList.toggle('collapsed');
            mainContent?.classList.toggle('expanded');
            sidebarToggle.classList.toggle('rotated');

        });

        document.addEventListener('click', event => {

            if (
                window.innerWidth <= 1200 &&
                sidebar &&
                menuToggle &&
                sidebar.classList.contains('open') &&
                !sidebar.contains(event.target) &&
                !menuToggle.contains(event.target)
            ) {
                sidebar.classList.remove('open');
            }

        });

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

        lucide.createIcons();

    </script>

</body>

</html>