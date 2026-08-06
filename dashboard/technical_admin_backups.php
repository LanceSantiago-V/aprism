<?php

require_once __DIR__ . '/../auth/role_helper.php';

$allowedRoles = [
    ROLE_TECHNICAL_ADMINISTRATOR
];

require_once __DIR__ . '/../auth/session_guard.php';
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

/* Pagination */

$backupsPerPage = 7;

$currentPage = max(
    1,
    (int) ($_GET['page'] ?? 1)
);

$totalPages = 1;

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

?>

<!DOCTYPE html>
<html lang="en">

<?php
require_once __DIR__ . '/../includes/components/head.php';
?>

<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/pages/technical-admin-backups.css">

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

                <p class="page-description">
                    Create and download database backups for APRISM.
                </p>

            </div>

            <button type="button" class="create-backup-btn" data-bs-toggle="modal" data-bs-target="#createBackupModal">
                <i data-lucide="database-backup"></i>
                Create Backup
            </button>

        </section>

        <?php if (!empty($_SESSION['success_message'])): ?>

            <div class="page-alert success">

                <i data-lucide="circle-check"></i>

                <span>
                    <?= htmlspecialchars(
                        $_SESSION['success_message']
                    ) ?>
                </span>

            </div>

            <?php unset($_SESSION['success_message']); ?>

        <?php endif; ?>

        <?php if (!empty($_SESSION['error_message'])): ?>

            <div class="page-alert error">

                <i data-lucide="circle-alert"></i>

                <span>
                    <?= htmlspecialchars(
                        $_SESSION['error_message']
                    ) ?>
                </span>

            </div>

            <?php unset($_SESSION['error_message']); ?>

        <?php endif; ?>

        <section class="backup-card">

            <div class="backup-card-header">

                <h2 class="backup-card-title">
                    Backup History
                </h2>

                <div class="backup-count">

                    <?= number_format(
                        $totalBackups
                    ) ?>

                    <?= $totalBackups === 1
                        ? 'backup'
                        : 'backups'
                        ?>

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
                        href="?page=<?= max(1, $currentPage - 1) ?>">

                        Previous

                    </a>

                    <?php for ($page = 1; $page <= $totalPages; $page++): ?>

                        <a class="page-number <?= $page === $currentPage ? 'active' : '' ?>" href="?page=<?= $page ?>">

                            <?= $page ?>

                        </a>

                    <?php endfor; ?>

                    <a class="page-btn <?= $currentPage === $totalPages ? 'disabled' : '' ?>"
                        href="?page=<?= min($totalPages, $currentPage + 1) ?>">

                        Next

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

    <script>

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