<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/role_helper.php';

$allowedRoles = [
    ROLE_TECHNICAL_ADMINISTRATOR
];

require_once __DIR__ . '/../auth/session_guard.php';
require_once __DIR__ . '/../includes/helper/flash_message.php';
require_once __DIR__ . '/../includes/helper/system_settings_helper.php';
require_once __DIR__ . '/../auth/csrf_helper.php';

// Load attendance policy settings

$attendanceGracePeriodSetting = getSystemSetting(
    $pdo,
    'attendance_grace_period_minutes'
);

$attendanceAbsentThresholdSetting = getSystemSetting(
    $pdo,
    'attendance_absent_threshold_minutes'
);

$attendanceGracePeriodMinutes =
    $attendanceGracePeriodSetting !== null
    ? (int) $attendanceGracePeriodSetting
    : null;

$attendanceAbsentThresholdMinutes =
    $attendanceAbsentThresholdSetting !== null
    ? (int) $attendanceAbsentThresholdSetting
    : null;

$sessionTimeoutSetting = getSystemSetting(
    $pdo,
    'security_session_timeout_minutes'
);

$sessionTimeoutMinutes = $sessionTimeoutSetting !== null
    ? (int) $sessionTimeoutSetting
    : 60;

$temporaryLockDurationSetting = getSystemSetting(
    $pdo,
    'security_temporary_lock_duration_minutes'
);

$temporaryLockDurationMinutes =
    $temporaryLockDurationSetting !== null
    ? (int) $temporaryLockDurationSetting
    : 15;

$maxFailedLoginAttemptsSetting = getSystemSetting(
    $pdo,
    'security_max_failed_login_attempts'
);

$maxFailedLoginAttempts =
    $maxFailedLoginAttemptsSetting !== null
    ? (int) $maxFailedLoginAttemptsSetting
    : null;

$backupRetentionSetting = getSystemSetting(
    $pdo,
    'backup_retention_days'
);

$backupRetentionDays = $backupRetentionSetting !== null
    ? (int) $backupRetentionSetting
    : null;

// Load automatic backup scheduling settings

$backupScheduleEnabledSetting = getSystemSetting(
    $pdo,
    'backup_schedule_enabled'
);

$backupScheduleFrequencySetting = getSystemSetting(
    $pdo,
    'backup_schedule_frequency'
);

$backupScheduleTimeSetting = getSystemSetting(
    $pdo,
    'backup_schedule_time'
);

$backupScheduleDaySetting = getSystemSetting(
    $pdo,
    'backup_schedule_day'
);

// Prepare automatic backup scheduling values

$backupScheduleEnabled =
    $backupScheduleEnabledSetting === '1';

$backupScheduleFrequency =
    $backupScheduleFrequencySetting ?? 'daily';

$backupScheduleTime =
    $backupScheduleTimeSetting ?? '02:00';

$backupScheduleDay =
    $backupScheduleDaySetting ?? 'monday';

$activePage = 'settings';

$successMessage = $flash['success'] ?? null;
$errorMessage = $flash['error'] ?? null;
$warningMessage = $flash['warning'] ?? null;

/*
 * Read-only System Information values.
 * These are retrieved from the current APRISM runtime environment.
 */

$phpVersion = PHP_VERSION;
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unavailable';
$serverTimezone = date_default_timezone_get();

$databaseVersion = 'Unavailable';
$databaseStatus = 'Unavailable';

try {

    if (isset($pdo) && $pdo instanceof PDO) {

        $databaseVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        $databaseStatus = 'Connected';

    } elseif (isset($conn) && $conn instanceof mysqli) {

        $databaseVersion = $conn->server_info;
        $databaseStatus = 'Connected';

    }

} catch (Throwable $exception) {

    $databaseVersion = 'Unavailable';
    $databaseStatus = 'Unavailable';

}

$pageTitle = 'System Settings';
$pageCss = 'technical-admin-settings.css';

?>

<!DOCTYPE html>
<html lang="en">

<?php
require_once __DIR__ . '/../includes/components/technical_admin_head.php';
?>

<body>

    <div class="app-layout">

        <?php
        require_once __DIR__ . '/../includes/components/technical_admin_sidebar.php';
        ?>

        <main class="main-content">

            <!-- Settings Workspace -->
            <div class="settings-workspace">

                <div class="settings-workspace-header">

                    <div class="settings-workspace-heading">

                        <i data-lucide="settings"></i>

                        <div>

                            <h1 class="page-title">
                                System Settings Portal
                            </h1>

                            <p class="page-description">
                                Configure system-wide settings and administrative policies.
                            </p>

                        </div>

                    </div>

                </div>


                <!-- Settings Layout -->
                <div class="settings-layout">

                    <!-- Settings Navigation -->
                    <aside class="settings-navigation">

                        <div class="settings-tabs" id="settingsTabs" role="tablist">

                            <button class="settings-tab active" id="system-tab" data-bs-toggle="pill"
                                data-bs-target="#system" type="button" role="tab" aria-controls="system"
                                aria-selected="true">

                                <i data-lucide="settings-2"></i>

                                <span>
                                    System Configuration
                                </span>

                            </button>


                            <button class="settings-tab" id="security-tab" data-bs-toggle="pill"
                                data-bs-target="#security" type="button" role="tab" aria-controls="security"
                                aria-selected="false">

                                <i data-lucide="shield-check"></i>

                                <span>
                                    Security & Access
                                </span>

                            </button>


                            <button class="settings-tab" id="backup-tab" data-bs-toggle="pill" data-bs-target="#backup"
                                type="button" role="tab" aria-controls="backup" aria-selected="false">

                                <i data-lucide="database-backup"></i>

                                <span>
                                    Backup & Recovery
                                </span>

                            </button>


                            <button class="settings-tab" id="information-tab" data-bs-toggle="pill"
                                data-bs-target="#information" type="button" role="tab" aria-controls="information"
                                aria-selected="false">

                                <i data-lucide="info"></i>

                                <span>
                                    System Information
                                </span>

                            </button>

                        </div>

                    </aside>


                    <!-- Settings Content -->
                    <div class="tab-content settings-tab-content" id="settingsTabsContent">


                        <!-- System Configuration -->
                        <div class="tab-pane fade show active" id="system" role="tabpanel" aria-labelledby="system-tab">

                            <div class="settings-panel">

                                <!-- Panel Header -->
                                <div class="settings-panel-header">

                                    <div>

                                        <h2 class="settings-panel-title">
                                            System Configuration
                                        </h2>

                                        <p class="settings-panel-description">
                                            Maintain APRISM operational parameters that implement
                                            institutionally approved system policies.
                                        </p>

                                    </div>

                                    <div class="settings-panel-icon">
                                        <i data-lucide="settings-2"></i>
                                    </div>

                                </div>


                                <form action="<?= APP_URL ?>/actions/system/update_system_configuration.php"
                                    method="POST" class="settings-form" id="systemConfigurationForm">

                                    <input type="hidden" name="csrf_token"
                                        value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">


                                    <!-- Attendance Policy -->
                                    <section class="settings-group">

                                        <h3 class="settings-group-title">
                                            Attendance Policy Parameters
                                        </h3>

                                        <div class="row g-3">

                                            <div class="col-md-6">

                                                <label class="settings-label" for="gracePeriod">
                                                    Grace Period
                                                </label>

                                                <div class="settings-input-group">

                                                    <input type="number" min="0"
                                                        class="settings-control settings-control-with-addon"
                                                        id="gracePeriod" name="attendance_grace_period_minutes"
                                                        value="<?= $attendanceGracePeriodMinutes !== null ? htmlspecialchars((string) $attendanceGracePeriodMinutes, ENT_QUOTES, 'UTF-8') : '' ?>"
                                                        placeholder="Not configured">

                                                    <span class="settings-addon">
                                                        MINS
                                                    </span>

                                                </div>

                                            </div>


                                            <div class="col-md-6">

                                                <label class="settings-label" for="absentThreshold">
                                                    Absent Threshold
                                                </label>

                                                <div class="settings-input-group">

                                                    <input type="number" min="0"
                                                        class="settings-control settings-control-with-addon"
                                                        id="absentThreshold" name="attendance_absent_threshold_minutes"
                                                        value="<?= $attendanceAbsentThresholdMinutes !== null ? htmlspecialchars((string) $attendanceAbsentThresholdMinutes, ENT_QUOTES, 'UTF-8') : '' ?>"
                                                        placeholder="Not configured">

                                                    <span class="settings-addon">
                                                        MINS
                                                    </span>

                                                </div>

                                            </div>

                                        </div>

                                    </section>



                                    <!-- Actions -->
                                    <div class="settings-actions">

                                        <button type="submit" class="settings-btn settings-btn-primary"
                                            id="saveSystemConfigurationBtn">
                                            <i data-lucide="save"></i>
                                            <span>Save System Configuration</span>
                                        </button>

                                    </div>

                                    <!-- Policy Notice -->
                                    <div class="settings-notice">

                                        <div class="settings-notice-icon">
                                            <i data-lucide="info"></i>
                                        </div>

                                        <div>

                                            <span class="settings-notice-title">
                                                Attendance Policy Configuration
                                            </span>

                                            <p>
                                                Leave a parameter blank if its institutional value has not
                                                yet been confirmed. Attendance enforcement is not performed
                                                from this settings page.
                                            </p>

                                        </div>

                                    </div>

                                </form>

                            </div>

                        </div>


                        <!-- Security & Access -->
                        <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">

                            <div class="settings-panel">

                                <!-- Panel Header -->
                                <div class="settings-panel-header">

                                    <div>

                                        <h2 class="settings-panel-title">
                                            Security & Access
                                        </h2>

                                        <p class="settings-panel-description">
                                            Configure authentication thresholds, session timeouts,
                                            and system access policies.
                                        </p>

                                    </div>

                                    <div class="settings-panel-icon">
                                        <i data-lucide="shield-check"></i>
                                    </div>

                                </div>


                                <form action="<?= APP_URL ?>/actions/system/update_security_settings.php" method="POST"
                                    class="settings-form" id="securitySettingsForm">

                                    <!-- Authentication -->
                                    <section class="settings-group">

                                        <h3 class="settings-group-title">
                                            Authentication & Session Policies
                                        </h3>

                                        <div class="row g-3">

                                            <div class="col-md-4">

                                                <label class="settings-label">
                                                    Maximum Failed Login Attempts
                                                </label>

                                                <select class="settings-control settings-select"
                                                    name="maximum_failed_login_attempts">

                                                    <option value="" <?= $maxFailedLoginAttempts === null ? 'selected' : '' ?>>
                                                        Not enforced
                                                    </option>

                                                    <option value="3" <?= $maxFailedLoginAttempts === 3 ? 'selected' : '' ?>>
                                                        3 attempts
                                                    </option>

                                                    <option value="5" <?= $maxFailedLoginAttempts === 5 ? 'selected' : '' ?>>
                                                        5 attempts
                                                    </option>

                                                    <option value="10" <?= $maxFailedLoginAttempts === 10 ? 'selected' : '' ?>>
                                                        10 attempts
                                                    </option>

                                                </select>

                                            </div>

                                            <div class="col-md-4">

                                                <label class="settings-label">
                                                    Temporary Lock Duration
                                                </label>

                                                <div class="settings-input-group">

                                                    <input type="number" min="1" max="1440"
                                                        class="settings-control settings-control-with-addon"
                                                        name="temporary_lock_duration"
                                                        value="<?= htmlspecialchars((string) $temporaryLockDurationMinutes, ENT_QUOTES, 'UTF-8') ?>">

                                                    <span class="settings-addon">
                                                        MINS
                                                    </span>

                                                </div>

                                            </div>


                                            <div class="col-md-4">

                                                <label class="settings-label">
                                                    Standard Session Timeout
                                                </label>

                                                <div class="settings-input-group">

                                                    <input type="number" min="5" max="1440"
                                                        class="settings-control settings-control-with-addon"
                                                        name="session_timeout"
                                                        value="<?= htmlspecialchars((string) $sessionTimeoutMinutes, ENT_QUOTES, 'UTF-8') ?>">

                                                    <span class="settings-addon">
                                                        MINS
                                                    </span>

                                                </div>

                                            </div>

                                        </div>

                                    </section>

                                    <!-- Actions -->
                                    <div class="settings-actions">

                                        <button type="submit" class="settings-btn settings-btn-primary"
                                            id="saveSecuritySettingsBtn">
                                            <i data-lucide="save"></i>
                                            <span>Save Security Settings</span>
                                        </button>

                                    </div>

                                    <!-- Role Access Overview -->
                                    <section class="settings-group">

                                        <h3 class="settings-group-title">
                                            Role Access Overview
                                        </h3>

                                        <div class="settings-access-list">

                                            <!-- Technical Administrator -->
                                            <div class="settings-access-card">

                                                <div class="settings-access-header">

                                                    <div>

                                                        <span class="settings-access-title">
                                                            Technical Administrator
                                                        </span>

                                                        <span class="settings-access-description">
                                                            Technical administration, system maintenance,
                                                            security configuration, and account management.
                                                        </span>

                                                    </div>

                                                    <span class="settings-status-badge settings-status-blue">
                                                        Technical Administration
                                                    </span>

                                                </div>

                                                <div class="settings-permission-list">

                                                    <span class="settings-permission">
                                                        <i data-lucide="check"></i>
                                                        User Management
                                                    </span>

                                                    <span class="settings-permission">
                                                        <i data-lucide="check"></i>
                                                        Audit Logs
                                                    </span>

                                                    <span class="settings-permission">
                                                        <i data-lucide="check"></i>
                                                        Database Backups
                                                    </span>

                                                    <span class="settings-permission">
                                                        <i data-lucide="check"></i>
                                                        System Settings
                                                    </span>

                                                </div>

                                            </div>


                                            <!-- Academic Head -->
                                            <div class="settings-access-card">

                                                <div class="settings-access-header">

                                                    <div>

                                                        <span class="settings-access-title">
                                                            Academic Head
                                                        </span>

                                                        <span class="settings-access-description">
                                                            Academic administration, academic structure,
                                                            assignments, and institutionally authorized academic
                                                            operations.
                                                        </span>

                                                    </div>

                                                    <span class="settings-status-badge">
                                                        Academic Administration
                                                    </span>

                                                </div>

                                                <div class="settings-permission-list">

                                                    <span class="settings-permission">
                                                        <i data-lucide="check"></i>
                                                        Academic Structure
                                                    </span>

                                                    <span class="settings-permission">
                                                        <i data-lucide="check"></i>
                                                        Faculty Assignments
                                                    </span>

                                                    <span class="settings-permission">
                                                        <i data-lucide="check"></i>
                                                        Academic Administration
                                                    </span>

                                                </div>

                                            </div>


                                            <!-- Faculty -->
                                            <div class="settings-access-card">

                                                <div class="settings-access-header">

                                                    <div>

                                                        <span class="settings-access-title">
                                                            Faculty / Teacher
                                                        </span>

                                                        <span class="settings-access-description">
                                                            Classroom operations based on assigned classes,
                                                            sections, and teaching responsibilities.
                                                        </span>

                                                    </div>

                                                    <span class="settings-status-badge">
                                                        Classroom Operations
                                                    </span>

                                                </div>

                                                <div class="settings-permission-list">

                                                    <span class="settings-permission">
                                                        <i data-lucide="check"></i>
                                                        Attendance
                                                    </span>

                                                    <span class="settings-permission">
                                                        <i data-lucide="check"></i>
                                                        Assessment Records
                                                    </span>

                                                    <span class="settings-permission">
                                                        <i data-lucide="check"></i>
                                                        Assigned Classes
                                                    </span>

                                                </div>

                                            </div>


                                            <!-- Guidance Office -->
                                            <div class="settings-access-card">

                                                <div class="settings-access-header">

                                                    <div>

                                                        <span class="settings-access-title">
                                                            Guidance Office
                                                        </span>

                                                        <span class="settings-access-description">
                                                            Student intervention support, referral management,
                                                            consultation records, and follow-up tracking within
                                                            authorized student cases.
                                                        </span>

                                                    </div>

                                                    <span class="settings-status-badge">
                                                        Intervention Support
                                                    </span>

                                                </div>

                                                <div class="settings-permission-list">

                                                    <span class="settings-permission">
                                                        <i data-lucide="check"></i>
                                                        Referrals
                                                    </span>

                                                    <span class="settings-permission">
                                                        <i data-lucide="check"></i>
                                                        Consultation Records
                                                    </span>

                                                    <span class="settings-permission">
                                                        <i data-lucide="check"></i>
                                                        Follow-Up Tracking
                                                    </span>

                                                </div>

                                            </div>


                                            <!-- Additional Responsibilities -->
                                            <div class="settings-access-card">

                                                <div class="settings-access-header">

                                                    <div>

                                                        <span class="settings-access-title">
                                                            Additional Academic Responsibilities
                                                        </span>

                                                        <span class="settings-access-description">
                                                            Adviser and Program Head responsibilities extend academic
                                                            access only within their assigned scope.
                                                        </span>

                                                    </div>

                                                    <span class="settings-status-badge">
                                                        Assignment-Based
                                                    </span>

                                                </div>

                                                <div class="settings-permission-list">

                                                    <span class="settings-permission">
                                                        <i data-lucide="check"></i>
                                                        Adviser
                                                    </span>

                                                    <span class="settings-permission">
                                                        <i data-lucide="check"></i>
                                                        Program Head
                                                    </span>

                                                </div>

                                            </div>

                                        </div>

                                    </section>

                                </form>

                            </div>

                        </div>


                        <!-- Backup & Recovery -->
                        <div class="tab-pane fade" id="backup" role="tabpanel" aria-labelledby="backup-tab">

                            <div class="settings-panel">

                                <!-- Panel Header -->
                                <div class="settings-panel-header">

                                    <div>

                                        <h2 class="settings-panel-title">
                                            Backup & Recovery
                                        </h2>

                                        <p class="settings-panel-description">
                                            Configure supported backup policies and review the operational
                                            status of APRISM database protection.
                                        </p>

                                    </div>

                                    <div class="settings-panel-icon">
                                        <i data-lucide="database-backup"></i>
                                    </div>

                                </div>


                                <!-- Backup Retention -->
                                <form action="<?= APP_URL ?>/actions/system/update_backup_retention.php" method="POST"
                                    class="backup-settings-section" id="backupRetentionForm">

                                    <div class="backup-settings-section-header">

                                        <div>

                                            <h3 class="settings-group-title">
                                                Backup Policy
                                            </h3>

                                            <p class="backup-settings-section-description">
                                                Control how long generated database backups are retained.
                                            </p>

                                        </div>

                                    </div>

                                    <div class="backup-settings-content">

                                        <div class="backup-retention-field">

                                            <label class="settings-label" for="backupRetention">
                                                Backup Retention Limit
                                            </label>

                                            <div class="settings-input-group">

                                                <input type="number" min="1" max="365"
                                                    class="settings-control settings-control-with-addon"
                                                    id="backupRetention" name="backup_retention_days"
                                                    value="<?= $backupRetentionDays !== null ? htmlspecialchars((string) $backupRetentionDays, ENT_QUOTES, 'UTF-8') : '' ?>"
                                                    placeholder="No retention limit">

                                                <span class="settings-addon">
                                                    DAYS
                                                </span>

                                            </div>

                                        </div>

                                        <button type="submit" class="settings-btn settings-btn-primary"
                                            id="saveBackupRetentionBtn">
                                            <i data-lucide="save"></i>
                                            <span>Save Retention Settings</span>
                                        </button>

                                    </div>

                                </form>


                                <!-- Automatic Backup Scheduling -->
                                <form action="<?= APP_URL ?>/actions/system/update_backup_schedule.php" method="POST"
                                    class="backup-settings-section" id="backupScheduleForm">

                                    <div class="backup-settings-section-header">

                                        <div>

                                            <h3 class="settings-group-title">
                                                Automatic Backup Scheduling
                                            </h3>

                                            <p class="backup-settings-section-description">
                                                Configure when APRISM automatically creates database backups.
                                            </p>

                                        </div>

                                    </div>

                                    <div class="backup-schedule-grid">

                                        <div>

                                            <label class="settings-label" for="backupScheduleEnabled">
                                                Automatic Backups
                                            </label>

                                            <select class="settings-control settings-select" id="backupScheduleEnabled"
                                                name="backup_schedule_enabled">

                                                <option value="0" <?= !$backupScheduleEnabled ? 'selected' : '' ?>>
                                                    Disabled
                                                </option>

                                                <option value="1" <?= $backupScheduleEnabled ? 'selected' : '' ?>>
                                                    Enabled
                                                </option>

                                            </select>

                                        </div>


                                        <div>

                                            <label class="settings-label" for="backupScheduleFrequency">
                                                Frequency
                                            </label>

                                            <select class="settings-control settings-select"
                                                id="backupScheduleFrequency" name="backup_schedule_frequency"
                                                <?= !$backupScheduleEnabled ? 'disabled' : '' ?>>

                                                <option value="daily" <?= $backupScheduleFrequency === 'daily' ? 'selected' : '' ?>>
                                                    Daily
                                                </option>

                                                <option value="weekly" <?= $backupScheduleFrequency === 'weekly' ? 'selected' : '' ?>>
                                                    Weekly
                                                </option>

                                            </select>

                                        </div>


                                        <div>

                                            <label class="settings-label" for="backupScheduleTime">
                                                Backup Time
                                            </label>

                                            <input type="time" class="settings-control" id="backupScheduleTime"
                                                name="backup_schedule_time"
                                                value="<?= htmlspecialchars($backupScheduleTime, ENT_QUOTES, 'UTF-8') ?>"
                                                <?= !$backupScheduleEnabled ? 'disabled' : '' ?>>

                                        </div>


                                        <div id="backupScheduleDayContainer" <?= (!$backupScheduleEnabled || $backupScheduleFrequency !== 'weekly') ? 'hidden' : '' ?>>

                                            <label class="settings-label" for="backupScheduleDay">
                                                Backup Day
                                            </label>

                                            <select class="settings-control settings-select" id="backupScheduleDay"
                                                name="backup_schedule_day" <?= (!$backupScheduleEnabled || $backupScheduleFrequency !== 'weekly') ? 'disabled' : '' ?>>

                                                <option value="monday" <?= $backupScheduleDay === 'monday' ? 'selected' : '' ?>>
                                                    Monday
                                                </option>

                                                <option value="tuesday" <?= $backupScheduleDay === 'tuesday' ? 'selected' : '' ?>>
                                                    Tuesday
                                                </option>

                                                <option value="wednesday" <?= $backupScheduleDay === 'wednesday' ? 'selected' : '' ?>>
                                                    Wednesday
                                                </option>

                                                <option value="thursday" <?= $backupScheduleDay === 'thursday' ? 'selected' : '' ?>>
                                                    Thursday
                                                </option>

                                                <option value="friday" <?= $backupScheduleDay === 'friday' ? 'selected' : '' ?>>
                                                    Friday
                                                </option>

                                                <option value="saturday" <?= $backupScheduleDay === 'saturday' ? 'selected' : '' ?>>
                                                    Saturday
                                                </option>

                                                <option value="sunday" <?= $backupScheduleDay === 'sunday' ? 'selected' : '' ?>>
                                                    Sunday
                                                </option>

                                            </select>

                                        </div>

                                    </div>

                                    <div class="backup-settings-footer">

                                        <button type="submit" class="settings-btn settings-btn-primary"
                                            id="saveBackupScheduleBtn">
                                            <i data-lucide="save"></i>
                                            <span>Save Schedule Settings</span>
                                        </button>

                                    </div>

                                </form>


                                <!-- Database Backup Management -->
                                <div class="backup-management-card">

                                    <div class="backup-management-info">

                                        <div class="settings-notice-icon">
                                            <i data-lucide="database"></i>
                                        </div>

                                        <div>

                                            <span class="settings-notice-title">
                                                Database Backup Management
                                            </span>

                                            <p>
                                                Backup creation, backup history, and downloads are handled
                                                through the dedicated Database Backups module.
                                            </p>

                                        </div>

                                    </div>

                                    <a href="<?= APP_URL ?>/dashboard/technical_admin_backups.php"
                                        class="settings-btn settings-btn-secondary">
                                        <i data-lucide="external-link"></i>
                                        <span>Manage Database Backups</span>
                                    </a>

                                </div>

                            </div>

                        </div>

                        <!-- System Information -->
                        <div class="tab-pane fade" id="information" role="tabpanel" aria-labelledby="information-tab">

                            <div class="settings-panel">

                                <!-- Panel Header -->
                                <div class="settings-panel-header">

                                    <div>

                                        <h2 class="settings-panel-title">
                                            System Information
                                        </h2>

                                        <p class="settings-panel-description">
                                            View read-only information about the current APRISM
                                            application and runtime environment.
                                        </p>

                                    </div>

                                    <div class="settings-panel-icon">
                                        <i data-lucide="info"></i>
                                    </div>

                                </div>


                                <!-- Information Grid -->
                                <div class="settings-info-grid">

                                    <div class="settings-info-card">

                                        <div class="settings-info-icon">
                                            <i data-lucide="activity"></i>
                                        </div>

                                        <div>

                                            <span class="settings-info-label">
                                                Application
                                            </span>

                                            <span class="settings-info-value">
                                                APRISM
                                            </span>

                                        </div>

                                    </div>


                                    <div class="settings-info-card">

                                        <div class="settings-info-icon">
                                            <i data-lucide="code-2"></i>
                                        </div>

                                        <div>

                                            <span class="settings-info-label">
                                                PHP Version
                                            </span>

                                            <span class="settings-info-value">
                                                <?= htmlspecialchars($phpVersion) ?>
                                            </span>

                                        </div>

                                    </div>


                                    <div class="settings-info-card">

                                        <div class="settings-info-icon">
                                            <i data-lucide="database"></i>
                                        </div>

                                        <div>

                                            <span class="settings-info-label">
                                                Database Version
                                            </span>

                                            <span class="settings-info-value">
                                                <?= htmlspecialchars($databaseVersion) ?>
                                            </span>

                                        </div>

                                    </div>


                                    <div class="settings-info-card">

                                        <div class="settings-info-icon">
                                            <i data-lucide="circle-check"></i>
                                        </div>

                                        <div>

                                            <span class="settings-info-label">
                                                Database Status
                                            </span>

                                            <span class="settings-info-value">
                                                <?= htmlspecialchars($databaseStatus) ?>
                                            </span>

                                        </div>

                                    </div>


                                    <div class="settings-info-card">

                                        <div class="settings-info-icon">
                                            <i data-lucide="server"></i>
                                        </div>

                                        <div>

                                            <span class="settings-info-label">
                                                Server Environment
                                            </span>

                                            <span class="settings-info-value">
                                                <?= htmlspecialchars($serverSoftware) ?>
                                            </span>

                                        </div>

                                    </div>


                                    <div class="settings-info-card">

                                        <div class="settings-info-icon">
                                            <i data-lucide="clock"></i>
                                        </div>

                                        <div>

                                            <span class="settings-info-label">
                                                Configured Timezone
                                            </span>

                                            <span class="settings-info-value">
                                                <?= htmlspecialchars($serverTimezone) ?>
                                            </span>

                                        </div>

                                    </div>


                                    <div class="settings-info-card">

                                        <div class="settings-info-icon">
                                            <i data-lucide="shield-check"></i>
                                        </div>

                                        <div>

                                            <span class="settings-info-label">
                                                Access Control
                                            </span>

                                            <span class="settings-info-value">
                                                Role-Based Access Control
                                            </span>

                                        </div>

                                    </div>

                                </div>


                                <!-- System Notice -->
                                <div class="settings-notice">

                                    <div class="settings-notice-icon">
                                        <i data-lucide="shield-check"></i>
                                    </div>

                                    <div>

                                        <span class="settings-notice-title">
                                            Read-Only System Information
                                        </span>

                                        <p>
                                            Runtime information is retrieved from the current APRISM
                                            environment and cannot be modified from this page.
                                        </p>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>
                    <!-- End Settings Content -->

                </div>
                <!-- End Settings Layout -->

            </div>
            <!-- End Settings Workspace -->

        </main>

    </div>


    <div class="toast-container-custom" id="toastContainer"
        data-success-message="<?= htmlspecialchars($successMessage ?? '', ENT_QUOTES, 'UTF-8') ?>"
        data-error-message="<?= htmlspecialchars($errorMessage ?? '', ENT_QUOTES, 'UTF-8') ?>"
        data-warning-message="<?= htmlspecialchars($warningMessage ?? '', ENT_QUOTES, 'UTF-8') ?>">
    </div>


    <?php
    require_once __DIR__ . '/../includes/components/logout_modal.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <?php
    require_once __DIR__ . '/../includes/components/technical_admin_footer.php';
    ?>

</body>

</html>