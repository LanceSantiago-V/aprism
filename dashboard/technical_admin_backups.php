<?php

require_once __DIR__ . '/../auth/role_helper.php';

$allowedRoles = [
    ROLE_TECHNICAL_ADMINISTRATOR
];

require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../auth/csrf_helper.php';
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../includes/helper/flash_message.php';

$activePage = 'backups';

$fullName =
    ($_SESSION['first_name'] ?? '') . ' ' .
    ($_SESSION['last_name'] ?? '');

$initials =
    strtoupper(substr($_SESSION['first_name'] ?? '', 0, 1)) .
    strtoupper(substr($_SESSION['last_name'] ?? '', 0, 1));

$backupFiles = [];
$backupLoadError = false;


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$requestedBackupsPerPage = filter_input(INPUT_GET, 'per_page', FILTER_VALIDATE_INT);
$backupsPerPage = $requestedBackupsPerPage && $requestedBackupsPerPage > 0
    ? min($requestedBackupsPerPage, 100)
    : 6;
$createdBackupFile = isset($_SESSION['created_backup_focus_file'])
    ? (string) $_SESSION['created_backup_focus_file']
    : '';
unset($_SESSION['created_backup_focus_file']);

$focusBackupFile = isset($_GET['focus_file'])
    ? (string) $_GET['focus_file']
    : $createdBackupFile;
$focusBackupFile = $focusBackupFile !== '' && basename($focusBackupFile) === $focusBackupFile
    ? $focusBackupFile
    : '';
$anchorBackupFile = isset($_GET['anchor_file']) ? (string) $_GET['anchor_file'] : '';
$anchorBackupFile = $anchorBackupFile !== '' && basename($anchorBackupFile) === $anchorBackupFile
    ? $anchorBackupFile
    : '';
$positionBackupFile = $focusBackupFile !== '' ? $focusBackupFile : $anchorBackupFile;

$currentPage = max(
    1,
    (int) ($_GET['page'] ?? 1)
);

$totalPages = 1;
$totalBackups = 0;
$backupOffset = 0;


// Load backup files
try {

    if (!is_dir(BACKUP_DIRECTORY)) {

        if (!mkdir(BACKUP_DIRECTORY, 0755, true)) {

            throw new RuntimeException(
                'Backup directory could not be created.'
            );
        }
    }

    $files = scandir(BACKUP_DIRECTORY);

    if ($files === false) {

        throw new RuntimeException(
            'Backup directory could not be read.'
        );
    }

    foreach ($files as $file) {

        if ($file === '.' || $file === '..') {
            continue;
        }

        $filePath =
            BACKUP_DIRECTORY .
            DIRECTORY_SEPARATOR .
            $file;

        if (
            !is_file($filePath) ||
            strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'sql'
        ) {
            continue;
        }

        $backupFiles[] = [
            'name' => $file,
            'size' => filesize($filePath),
            'created_at' => filemtime($filePath)
        ];
    }

    usort(
        $backupFiles,
        function ($a, $b) {
            return $b['created_at'] <=> $a['created_at'];
        }
    );

    if ($positionBackupFile !== '') {
        foreach ($backupFiles as $index => $backup) {
            if (hash_equals($backup['name'], $positionBackupFile)) {
                $currentPage = intdiv($index, $backupsPerPage) + 1;
                break;
            }
        }
    }

    $totalBackups = count($backupFiles);

    $totalPages = max(
        1,
        (int) ceil($totalBackups / $backupsPerPage)
    );

    $currentPage = min(
        $currentPage,
        $totalPages
    );

    $backupOffset = ($currentPage - 1) * $backupsPerPage;

    $backupFiles = array_slice(
        $backupFiles,
        $backupOffset,
        $backupsPerPage
    );

} catch (Throwable $e) {

    error_log(
        '[APRISM Backups] Failed to load backups: ' .
        $e->getMessage()
    );

    $backupFiles = [];
    $backupLoadError = true;
    $totalBackups = 0;
    $totalPages = 1;
    $backupOffset = 0;

}


function formatFileSize(int|float $bytes): string
{
    if ($bytes >= 1073741824) {

        return number_format(
            $bytes / 1073741824,
            2
        ) . ' GB';
    }

    if ($bytes >= 1048576) {

        return number_format(
            $bytes / 1048576,
            2
        ) . ' MB';
    }

    if ($bytes >= 1024) {

        return number_format(
            $bytes / 1024,
            2
        ) . ' KB';
    }

    return number_format($bytes) . ' B';
}


$pageTitle = 'Database Backups';

$activePage = 'backups';

$roleStylesheet = 'assets/css/technical-admin.css';

$pageStylesheet = 'assets/css/pages/technical-admin-backups.css';


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

    <?php
    require __DIR__ .
        '/../includes/components/sidebar.php';
    ?>

    <main class="main-content <?= !empty($_SESSION['sidebar_collapsed']) ? 'expanded' : '' ?>">

        <?php require __DIR__ . '/../includes/components/top-navbar.php'; ?>

        <section class="page-header">

            <div>

                <h1 class="page-title">
                    Database Backups
                </h1>

            </div>

            <button type="button" class="create-backup-btn" data-bs-toggle="modal" data-bs-target="#createBackupModal">

                <i data-lucide="database-backup"></i>

                Create Backup

            </button>

        </section>


        <section class="backup-card" data-adaptive-capacity-surface>

            <div class="backup-card-header">

                <h2 class="backup-card-title">
                    Backup History
                </h2>

                <div class="backup-count" <?= $backupLoadError ? 'role="status"' : '' ?>>

                    <?php if ($backupLoadError): ?>

                        Count unavailable

                    <?php else: ?>

                    <?= number_format(
                        $totalBackups
                    ) ?>

                    <?= $totalBackups === 1
                        ? 'backup'
                        : 'backups'
                        ?>

                    <?php endif; ?>

                </div>

            </div>


            <script>
                document.documentElement.classList.add('adaptive-record-capacity-js');
            </script>

            <div
                    class="backup-table-container"
                    data-adaptive-record-capacity
                    data-capacity-pending="true"
                    data-page-size="<?= $backupsPerPage ?>"
                    data-total-records="<?= $totalBackups ?>"
                    data-page-param="per_page"
                    data-anchor-param="anchor_file"
                    data-focus-param="focus_file"
                    data-pagination-selector=".backup-pagination"
                    data-bottom-gap="12"
                    aria-busy="true"
            >

                <table class="table backup-table align-middle">

                    <thead>

                        <tr>

                            <th class="text-start">
                                Backup File
                            </th>

                            <th class="text-center">
                                Created
                            </th>

                            <th class="text-center">
                                Size
                            </th>

                            <th class="text-center">
                                Download
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if ($backupLoadError): ?>

                            <tr>

                                <td colspan="4">

                                    <div class="empty-state">

                                        <i data-lucide="triangle-alert"></i>

                                        <div class="empty-state-title">
                                            Unable to load backups.
                                        </div>

                                        <p class="empty-state-text">
                                            Please refresh the page or try again later.
                                        </p>

                                    </div>

                                </td>

                            </tr>

                        <?php elseif (empty($backupFiles)): ?>

                            <tr>

                                <td colspan="4">

                                    <div class="empty-state">

                                        <i data-lucide="database"></i>

                                        <div class="empty-state-title">
                                            No database backups yet.
                                        </div>

                                        <p class="empty-state-text">
                                            Create a backup to keep a copy of the current APRISM database.
                                        </p>

                                    </div>

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($backupFiles as $backup): ?>

                                <tr id="backup-record-<?= htmlspecialchars(hash('sha256', $backup['name']), ENT_QUOTES, 'UTF-8') ?>" data-adaptive-record data-record-anchor="<?= htmlspecialchars($backup['name'], ENT_QUOTES, 'UTF-8') ?>"<?= $backup['name'] === $focusBackupFile ? ' class="destination-focus-target" tabindex="-1"' : '' ?>>

                                    <td class="backup-file-cell">

                                        <div class="backup-file">

                                            <div class="backup-file-icon">

                                                <i data-lucide="database"></i>

                                            </div>

                                            <div class="backup-file-name">

                                                <?= htmlspecialchars(
                                                    $backup['name']
                                                ) ?>

                                            </div>

                                        </div>

                                    </td>

                                    <td class="backup-date text-center">

                                        <?= date(
                                            'M d, Y h:i A',
                                            $backup['created_at']
                                        ) ?>

                                    </td>

                                    <td class="backup-size text-center">

                                        <?= htmlspecialchars(
                                            formatFileSize(
                                                $backup['size']
                                            )
                                        ) ?>

                                    </td>

                                    <td class="text-center">

                                        <a class="download-btn" href="<?= APP_URL ?>/actions/system/download_backup.php?file=<?= urlencode(
                                              $backup['name']
                                          ) ?>" title="Download Backup" aria-label="Download backup">

                                            <i data-lucide="download"></i>

                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

            </div>


            <?php if (!$backupLoadError): ?>

                <?php
                $firstBackup = $totalBackups > 0
                    ? $backupOffset + 1
                    : 0;
                $lastBackup = min(
                    $backupOffset + count($backupFiles),
                    $totalBackups
                );
                ?>

                <div class="backup-pagination" data-capacity-pending="true">

                    <div class="backup-pagination-info">
                        Showing <?= number_format($firstBackup) ?> to
                        <?= number_format($lastBackup) ?> of
                        <?= number_format($totalBackups) ?> backups
                    </div>

                    <?php if ($totalPages > 1): ?>

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

                        <nav class="backup-pagination-controls" aria-label="Backup pages">

                            <?php if ($currentPage > 1): ?>
                                <a class="page-btn" href="?page=1&amp;per_page=<?= $backupsPerPage ?>" aria-label="First page" data-aprism-tooltip="First page">
                                    <i data-lucide="chevrons-left" aria-hidden="true"></i>
                                </a>
                                <a class="page-btn" href="?page=<?= $currentPage - 1 ?>&amp;per_page=<?= $backupsPerPage ?>" aria-label="Previous page" data-aprism-tooltip="Previous page">
                                    <i data-lucide="chevron-left" aria-hidden="true"></i>
                                </a>
                            <?php else: ?>
                                <span class="page-btn disabled" aria-disabled="true" aria-label="First page" data-aprism-tooltip="First page">
                                    <i data-lucide="chevrons-left" aria-hidden="true"></i>
                                </span>
                                <span class="page-btn disabled" aria-disabled="true" aria-label="Previous page" data-aprism-tooltip="Previous page">
                                    <i data-lucide="chevron-left" aria-hidden="true"></i>
                                </span>
                            <?php endif; ?>

                            <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
                                <a
                                    class="page-number <?= $page === $currentPage ? 'active' : '' ?>"
                                    href="?page=<?= $page ?>&amp;per_page=<?= $backupsPerPage ?>"
                                    <?= $page === $currentPage ? 'aria-current="page"' : '' ?>
                                >
                                    <?= $page ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($currentPage < $totalPages): ?>
                                <a class="page-btn" href="?page=<?= $currentPage + 1 ?>&amp;per_page=<?= $backupsPerPage ?>" aria-label="Next page" data-aprism-tooltip="Next page">
                                    <i data-lucide="chevron-right" aria-hidden="true"></i>
                                </a>
                            <?php else: ?>
                                <span class="page-btn disabled" aria-disabled="true" aria-label="Next page" data-aprism-tooltip="Next page">
                                    <i data-lucide="chevron-right" aria-hidden="true"></i>
                                </span>
                            <?php endif; ?>

                            <?php if ($currentPage < $totalPages): ?>
                                <a class="page-btn" href="?page=<?= $totalPages ?>&amp;per_page=<?= $backupsPerPage ?>" aria-label="Last page" data-aprism-tooltip="Last page">
                                    <i data-lucide="chevrons-right" aria-hidden="true"></i>
                                </a>
                            <?php else: ?>
                                <span class="page-btn disabled" aria-disabled="true" aria-label="Last page" data-aprism-tooltip="Last page">
                                    <i data-lucide="chevrons-right" aria-hidden="true"></i>
                                </span>
                            <?php endif; ?>

                        </nav>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </section>

    </main>


    <!-- Create backup modal -->

    <div class="modal fade" id="createBackupModal" tabindex="-1" aria-labelledby="createBackupModalLabel"
        aria-hidden="true" data-aprism-dialog-focus>

        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content">

                <div class="backup-modal-icon">

                    <i data-lucide="database-zap"></i>

                </div>

                <h2 class="backup-modal-title" id="createBackupModalLabel" tabindex="-1" data-aprism-dialog-initial-focus>

                    Create Database Backup?

                </h2>

                <p class="backup-modal-text">

                    A new SQL backup of the current APRISM database
                    will be created and stored on the server.

                </p>

                <div class="modal-actions">

                    <button type="button" class="modal-cancel-btn" data-bs-dismiss="modal">

                        Cancel

                    </button>

                    <form method="POST" action="<?= APP_URL ?>/actions/system/create_backup.php"
                        class="d-flex flex-fill" id="createBackupForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="per_page" value="<?= (int) $backupsPerPage ?>">

                        <button type="submit" class="modal-confirm-btn w-100" id="confirmBackupButton">

                            Create Backup

                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>


    <?php
    require_once __DIR__ . '/../includes/components/logout_modal.php';
    ?>

    <script src="<?= APP_URL ?>/assets/js/components/adaptive-record-capacity.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/components/adaptive-record-capacity.js') ?>" defer></script>



    <div
        class="toast-container-custom"
        id="toastContainer"
        aria-live="polite"
        aria-atomic="true"
    ></div>


    <script>

        const backupFlash = <?= json_encode(
            [
                'success' => $flash['success'] ?? null,
                'error' => $flash['error'] ?? null
            ],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?>;

        function showBackupToast(title, text, type = 'success') {

            const toastContainer =
                document.getElementById('toastContainer');

            if (!toastContainer || !text) {
                return;
            }

            const toast = document.createElement('div');
            toast.className = 'toast-custom';

            const icon = type === 'warning'
                ? 'alert-circle'
                : 'check';

            toast.innerHTML = `
                <div class="toast-icon ${type}">
                    <i data-lucide="${icon}"></i>
                </div>
                <div class="toast-content">
                    <h5 class="toast-title"></h5>
                    <p class="toast-text"></p>
                </div>
            `;

            toast.querySelector('.toast-title').textContent = title;
            toast.querySelector('.toast-text').textContent = text;
            toastContainer.appendChild(toast);

            if (window.lucide) {
                lucide.createIcons();
            }

            setTimeout(() => toast.classList.add('show'), 10);

            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 4500);
        }

        document.addEventListener('DOMContentLoaded', () => {

            if (backupFlash.success) {
                showBackupToast(
                    'Success',
                    backupFlash.success,
                    'success'
                );
            }

            if (backupFlash.error) {
                showBackupToast(
                    'Backup Failed',
                    backupFlash.error,
                    'warning'
                );
            }

        });

        // Prevent duplicate backup requests

        const createBackupForm =
            document.getElementById('createBackupForm');

        const confirmBackupButton =
            document.getElementById('confirmBackupButton');

        createBackupForm?.addEventListener(
            'submit',
            () => {

                if (confirmBackupButton) {

                    confirmBackupButton.disabled = true;

                    confirmBackupButton.textContent =
                        'Creating...';

                }

            }
        );

    </script>


    <script src="<?= APP_URL ?>/assets/js/pages/technical-admin-destination-focus.js?v=<?= (int) filemtime(__DIR__ . '/../assets/js/pages/technical-admin-destination-focus.js') ?>" defer></script>

    <?php
    require_once __DIR__ . '/../includes/components/footer.php';
    ?>

</body>

</html>
