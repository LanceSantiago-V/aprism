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

$backupsPerPage = 7;

$currentPage = max(
    1,
    (int) ($_GET['page'] ?? 1)
);

$totalPages = 1;
$totalBackups = 0;


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

    $totalBackups = count($backupFiles);

    $totalPages = max(
        1,
        (int) ceil($totalBackups / $backupsPerPage)
    );

    $currentPage = min(
        $currentPage,
        $totalPages
    );

    $backupFiles = array_slice(
        $backupFiles,
        ($currentPage - 1) * $backupsPerPage,
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


        <section class="backup-card">

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


            <div class="backup-table-container">

                <table class="table backup-table align-middle">

                    <thead>

                        <tr>

                            <th class="text-center">
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

                                <tr>

                                    <td>

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

                                    <td class="backup-date">

                                        <?= date(
                                            'M d, Y h:i A',
                                            $backup['created_at']
                                        ) ?>

                                    </td>

                                    <td class="backup-size">

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


            <?php if ($totalPages > 1): ?>

                <div class="backup-pagination">

                    <a class="page-btn <?= $currentPage === 1 ? 'disabled' : '' ?>"
                        href="<?= $currentPage > 1 ? '?page=' . ($currentPage - 1) : '#' ?>"
                        aria-label="Previous page">

                        <i data-lucide="chevron-left"></i>

                    </a>

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

                    <?php for ($page = $startPage; $page <= $endPage; $page++): ?>

                        <a class="page-number <?= $page === $currentPage ? 'active' : '' ?>" href="?page=<?= $page ?>">

                            <?= $page ?>

                        </a>

                    <?php endfor; ?>

                    <a class="page-btn <?= $currentPage === $totalPages ? 'disabled' : '' ?>"
                        href="<?= $currentPage < $totalPages ? '?page=' . ($currentPage + 1) : '#' ?>"
                        aria-label="Next page">

                        <i data-lucide="chevron-right"></i>

                    </a>

                </div>

            <?php endif; ?>

        </section>

    </main>


    <!-- Create backup modal -->

    <div class="modal fade" id="createBackupModal" tabindex="-1" aria-labelledby="createBackupModalLabel"
        aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content">

                <div class="backup-modal-icon">

                    <i data-lucide="database-zap"></i>

                </div>

                <h2 class="backup-modal-title" id="createBackupModalLabel">

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


    <?php
    require_once __DIR__ . '/../includes/components/footer.php';
    ?>

</body>

</html>
