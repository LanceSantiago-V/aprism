<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/role_helper.php';

$allowedRoles = [
    ROLE_ACADEMIC_HEAD
];

require_once __DIR__ . '/../../auth/session_guard.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';


/*
|--------------------------------------------------------------------------
| Redirect Helper
|--------------------------------------------------------------------------
*/

function redirectToAcademicSetup(): never
{
    header(
        'Location: ' .
        APP_URL .
        '/dashboard/academic_head_academic_setup.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Flash Helpers
|--------------------------------------------------------------------------
*/

function setSuccessMessage(string $message): void
{
    $_SESSION['success_message'] = $message;
}


function setErrorMessage(string $message): void
{
    $_SESSION['error_message'] = $message;
}


/*
|--------------------------------------------------------------------------
| Request Validation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    setErrorMessage(
        'Invalid request method.'
    );

    redirectToAcademicSetup();
}


$action = $_POST['action'] ?? '';

$allowedActions = [
    'create',
    'update',
    'activate',
    'archive'
];

if (!in_array($action, $allowedActions, true)) {

    setErrorMessage(
        'Invalid School Year operation.'
    );

    redirectToAcademicSetup();
}


/*
|--------------------------------------------------------------------------
| Common Validation Helpers
|--------------------------------------------------------------------------
*/

function getSchoolYearId(): ?int
{
    $schoolYearId = filter_input(
        INPUT_POST,
        'school_year_id',
        FILTER_VALIDATE_INT
    );

    if (
        $schoolYearId === false ||
        $schoolYearId === null ||
        $schoolYearId <= 0
    ) {
        return null;
    }

    return (int) $schoolYearId;
}


function validateSchoolYearFormat(string $schoolYear): bool
{
    /*
     * Supports:
     *
     * 2025-2026
     * 2025–2026
     *
     * The database field remains VARCHAR(9).
     */

    return preg_match(
        '/^\d{4}[-–]\d{4}$/u',
        $schoolYear
    ) === 1;
}


function validateDateRange(
    string $startDate,
    string $endDate
): bool {

    $start = DateTime::createFromFormat(
        'Y-m-d',
        $startDate
    );

    $end = DateTime::createFromFormat(
        'Y-m-d',
        $endDate
    );

    if (!$start || !$end) {
        return false;
    }

    if (
        $start->format('Y-m-d') !== $startDate ||
        $end->format('Y-m-d') !== $endDate
    ) {
        return false;
    }

    return $start <= $end;
}


/*
|--------------------------------------------------------------------------
| Check Academic Period Boundaries
|--------------------------------------------------------------------------
|
| Existing Academic Period records must remain untouched.
|
| A School Year date edit is rejected if any existing Academic
| Period would fall outside the proposed School Year range.
|
*/

function hasAcademicPeriodsOutsideSchoolYearRange(
    PDO $pdo,
    int $schoolYearId,
    string $startDate,
    string $endDate
): bool {

    $stmt = $pdo->prepare("
        SELECT
            academic_period_id
        FROM academic_periods
        WHERE school_year_id = ?
          AND (
                start_date < ?
                OR
                end_date > ?
          )
        LIMIT 1
    ");

    $stmt->execute([
        $schoolYearId,
        $startDate,
        $endDate
    ]);

    return (bool) $stmt->fetch(
        PDO::FETCH_ASSOC
    );
}


/*
|--------------------------------------------------------------------------
| CREATE
|--------------------------------------------------------------------------
|
| New School Years always begin as Inactive.
|
| Activation is a separate controlled operation.
|
*/

if ($action === 'create') {

    $schoolYear = trim(
        (string) ($_POST['school_year'] ?? '')
    );

    $startDate = trim(
        (string) ($_POST['start_date'] ?? '')
    );

    $endDate = trim(
        (string) ($_POST['end_date'] ?? '')
    );


    if ($schoolYear === '') {

        setErrorMessage(
            'School Year is required.'
        );

        redirectToAcademicSetup();
    }


    if (!validateSchoolYearFormat($schoolYear)) {

        setErrorMessage(
            'School Year must use the format YYYY-YYYY.'
        );

        redirectToAcademicSetup();
    }


    if (!validateDateRange($startDate, $endDate)) {

        setErrorMessage(
            'Start Date must be earlier than or equal to End Date.'
        );

        redirectToAcademicSetup();
    }


    try {

        $stmt = $pdo->prepare("
            INSERT INTO school_years
            (
                school_year,
                start_date,
                end_date,
                status
            )
            VALUES
            (
                ?,
                ?,
                ?,
                'Inactive'
            )
        ");

        $stmt->execute([
            $schoolYear,
            $startDate,
            $endDate
        ]);


        $schoolYearId = (int) $pdo->lastInsertId();


        logAudit(
            $pdo,
            'Create School Year',
            'Created School Year ' .
            $schoolYear .
            ' (ID: ' .
            $schoolYearId .
            ').'
        );


        setSuccessMessage(
            'School Year created successfully.'
        );


    } catch (PDOException $e) {

        if (
            isset($e->errorInfo[1]) &&
            (int) $e->errorInfo[1] === 1062
        ) {

            setErrorMessage(
                'That School Year already exists.'
            );

        } else {

            error_log(
                '[APRISM School Year Create] ' .
                $e->getMessage()
            );

            setErrorMessage(
                'Unable to create School Year.'
            );
        }
    }


    redirectToAcademicSetup();
}


/*
|--------------------------------------------------------------------------
| UPDATE
|--------------------------------------------------------------------------
|
| IMPORTANT LIFECYCLE RULE
|--------------------------------------------------------------------------
|
| Editing a School Year does NOT change its administrative status.
|
| The submitted "status" value is intentionally ignored.
|
| Active / Inactive / Archived changes must happen through their
| dedicated lifecycle operations.
|
| This prevents a normal date edit from accidentally changing:
|
|     Active → Inactive
|
| or:
|
|     Active → Archived
|
|--------------------------------------------------------------------------
*/

if ($action === 'update') {

    $schoolYearId = getSchoolYearId();

    $schoolYear = trim(
        (string) ($_POST['school_year'] ?? '')
    );

    $startDate = trim(
        (string) ($_POST['start_date'] ?? '')
    );

    $endDate = trim(
        (string) ($_POST['end_date'] ?? '')
    );


    if ($schoolYearId === null) {

        setErrorMessage(
            'Invalid School Year selected.'
        );

        redirectToAcademicSetup();
    }


    if ($schoolYear === '') {

        setErrorMessage(
            'School Year is required.'
        );

        redirectToAcademicSetup();
    }


    if (!validateSchoolYearFormat($schoolYear)) {

        setErrorMessage(
            'School Year must use the format YYYY-YYYY.'
        );

        redirectToAcademicSetup();
    }


    if (!validateDateRange($startDate, $endDate)) {

        setErrorMessage(
            'Start Date must be earlier than or equal to End Date.'
        );

        redirectToAcademicSetup();
    }


    try {

        $pdo->beginTransaction();


        /*
         * Lock the selected School Year while validating and updating it.
         */

        $findStmt = $pdo->prepare("
            SELECT
                school_year,
                start_date,
                end_date,
                status
            FROM school_years
            WHERE school_year_id = ?
            FOR UPDATE
        ");

        $findStmt->execute([
            $schoolYearId
        ]);

        $existing = $findStmt->fetch(
            PDO::FETCH_ASSOC
        );


        if (!$existing) {

            $pdo->rollBack();

            setErrorMessage(
                'School Year not found.'
            );

            redirectToAcademicSetup();
        }


        /*
         * Archived School Years are historical records.
         *
         * They must not be edited through the normal School Year
         * workflow.
         */

        if ($existing['status'] === 'Archived') {

            $pdo->rollBack();

            setErrorMessage(
                'Archived School Years cannot be edited.'
            );

            redirectToAcademicSetup();
        }


        /*
         * Protect existing Academic Periods.
         *
         * We do NOT modify, move, archive, or delete them.
         *
         * If the proposed School Year range would make any existing
         * Academic Period invalid, reject the School Year edit.
         */

        $periodOutsideRange =
            hasAcademicPeriodsOutsideSchoolYearRange(
                $pdo,
                $schoolYearId,
                $startDate,
                $endDate
            );


        if ($periodOutsideRange) {

            $pdo->rollBack();

            setErrorMessage(
                'The School Year dates cannot be changed because one or more existing Academic Periods would fall outside the new School Year date range.'
            );

            redirectToAcademicSetup();
        }


        /*
         * IMPORTANT:
         *
         * Do NOT modify the School Year's status here.
         *
         * If it was Active, it remains Active.
         * If it was Inactive, it remains Inactive.
         *
         * The Academic Head must use the dedicated lifecycle actions
         * for activation/archival.
         */

        $updateStmt = $pdo->prepare("
            UPDATE school_years
            SET
                school_year = ?,
                start_date = ?,
                end_date = ?
            WHERE school_year_id = ?
        ");

        $updateStmt->execute([
            $schoolYear,
            $startDate,
            $endDate,
            $schoolYearId
        ]);


        $pdo->commit();


        logAudit(
            $pdo,
            'Update School Year',
            'Updated School Year ' .
            $schoolYear .
            ' (ID: ' .
            $schoolYearId .
            ').'
        );


        setSuccessMessage(
            'School Year updated successfully.'
        );


    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }


        if (
            isset($e->errorInfo[1]) &&
            (int) $e->errorInfo[1] === 1062
        ) {

            setErrorMessage(
                'That School Year already exists.'
            );

        } else {

            error_log(
                '[APRISM School Year Update] ' .
                $e->getMessage()
            );

            setErrorMessage(
                'Unable to update School Year.'
            );
        }
    }


    redirectToAcademicSetup();
}


/*
|--------------------------------------------------------------------------
| ACTIVATE
|--------------------------------------------------------------------------
|
| Rules:
|
| 1. Archived School Years cannot be activated.
| 2. Future School Years cannot be activated before Start Date.
| 3. If another School Year is currently Active, its End Date
|    must already have been reached before the new School Year
|    can be activated.
| 4. Only one School Year may be Active.
| 5. Activating a new School Year archives the previous Active
|    School Year.
| 6. Existing Academic Periods remain untouched.
|
*/

if ($action === 'activate') {

    $schoolYearId = getSchoolYearId();


    if ($schoolYearId === null) {

        setErrorMessage(
            'Invalid School Year selected.'
        );

        redirectToAcademicSetup();
    }


    try {

        $pdo->beginTransaction();


        /*
         * Lock the selected School Year.
         */

        $findStmt = $pdo->prepare("
            SELECT
                school_year,
                start_date,
                end_date,
                status
            FROM school_years
            WHERE school_year_id = ?
            FOR UPDATE
        ");

        $findStmt->execute([
            $schoolYearId
        ]);

        $schoolYear = $findStmt->fetch(
            PDO::FETCH_ASSOC
        );


        if (!$schoolYear) {

            $pdo->rollBack();

            setErrorMessage(
                'School Year not found.'
            );

            redirectToAcademicSetup();
        }


        /*
         * Archived School Years are permanently historical under
         * the normal lifecycle.
         */

        if ($schoolYear['status'] === 'Archived') {

            $pdo->rollBack();

            setErrorMessage(
                'Archived School Years cannot be activated.'
            );

            redirectToAcademicSetup();
        }


        /*
         * A School Year cannot become the current institutional
         * context before its own Start Date.
         */

        $today = date('Y-m-d');


        if ($schoolYear['start_date'] > $today) {

            $pdo->rollBack();

            setErrorMessage(
                'This School Year cannot be activated before its Start Date.'
            );

            redirectToAcademicSetup();
        }


        /*
         * If this School Year is already Active, there is nothing
         * else to transition.
         */

        if ($schoolYear['status'] === 'Active') {

            $pdo->rollBack();

            setSuccessMessage(
                'School Year is already active.'
            );

            redirectToAcademicSetup();
        }


        /*
         * Find the current Active School Year.
         *
         * It is intentionally locked before the transition so the
         * old context cannot be modified concurrently.
         */

        $activeStmt = $pdo->query("
            SELECT
                school_year_id,
                school_year,
                start_date,
                end_date
            FROM school_years
            WHERE status = 'Active'
            LIMIT 1
            FOR UPDATE
        ");

        $previousActive =
            $activeStmt->fetch(
                PDO::FETCH_ASSOC
            );


        /*
         * IMPORTANT ACTIVATION RULE
         *
         * If another School Year is currently Active, the new
         * School Year cannot be activated until the current
         * School Year's End Date has been reached.
         *
         * This does NOT automatically change the previous School
         * Year's status when its End Date is reached.
         *
         * The previous School Year remains Active until the
         * replacement is explicitly activated.
         */

        if (
            $previousActive &&
            (int) $previousActive['school_year_id'] !== $schoolYearId
        ) {

            if ($previousActive['end_date'] > $today) {

                $pdo->rollBack();

                setErrorMessage(
                    'The current Active School Year has not reached its End Date yet. The next School Year cannot be activated until the current School Year has ended.'
                );

                redirectToAcademicSetup();
            }
        }


        /*
         * Archive the previous Active School Year.
         *
         * This is the official institutional transition:
         *
         *     Previous Active → Archived
         *     New Inactive → Active
         *
         * Existing Academic Periods remain untouched.
         */

        if (
            $previousActive &&
            (int) $previousActive['school_year_id'] !== $schoolYearId
        ) {

            $archivePreviousStmt = $pdo->prepare("
                UPDATE school_years
                SET status = 'Archived'
                WHERE school_year_id = ?
                  AND status = 'Active'
            ");

            $archivePreviousStmt->execute([
                (int) $previousActive['school_year_id']
            ]);
        }


        /*
         * Activate the selected School Year.
         */

        $activateStmt = $pdo->prepare("
            UPDATE school_years
            SET status = 'Active'
            WHERE school_year_id = ?
              AND status = 'Inactive'
        ");

        $activateStmt->execute([
            $schoolYearId
        ]);


        if ($activateStmt->rowCount() !== 1) {

            $pdo->rollBack();

            setErrorMessage(
                'Unable to activate the selected School Year.'
            );

            redirectToAcademicSetup();
        }


        $pdo->commit();


        /*
         * Audit the new activation.
         */

        $auditDescription =
            'Activated School Year ' .
            $schoolYear['school_year'] .
            ' (ID: ' .
            $schoolYearId .
            ').';


        if ($previousActive) {

            $auditDescription .=
                ' Previous Active School Year ' .
                $previousActive['school_year'] .
                ' (ID: ' .
                (int) $previousActive['school_year_id'] .
                ') was archived.';

        }


        logAudit(
            $pdo,
            'Activate School Year',
            $auditDescription
        );


        setSuccessMessage(
            'School Year activated successfully.'
        );


    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }


        error_log(
            '[APRISM School Year Activate] ' .
            $e->getMessage()
        );


        setErrorMessage(
            'Unable to activate School Year.'
        );
    }


    redirectToAcademicSetup();
}


/*
|--------------------------------------------------------------------------
| ARCHIVE
|--------------------------------------------------------------------------
|
| Manual archive is allowed only for an Inactive School Year.
|
| The normal transition from the current Active School Year to the
| next School Year happens through ACTIVATE:
|
|     Old Active → Archived
|     New Inactive → Active
|
| This prevents an Active School Year from being archived without
| establishing a replacement academic context.
|
*/

if ($action === 'archive') {

    $schoolYearId = getSchoolYearId();


    if ($schoolYearId === null) {

        setErrorMessage(
            'Invalid School Year selected.'
        );

        redirectToAcademicSetup();
    }


    try {

        $pdo->beginTransaction();


        $findStmt = $pdo->prepare("
            SELECT
                school_year,
                status
            FROM school_years
            WHERE school_year_id = ?
            FOR UPDATE
        ");

        $findStmt->execute([
            $schoolYearId
        ]);

        $schoolYear = $findStmt->fetch(
            PDO::FETCH_ASSOC
        );


        if (!$schoolYear) {

            $pdo->rollBack();

            setErrorMessage(
                'School Year not found.'
            );

            redirectToAcademicSetup();
        }


        if ($schoolYear['status'] === 'Archived') {

            $pdo->rollBack();

            setErrorMessage(
                'School Year is already archived.'
            );

            redirectToAcademicSetup();
        }


        /*
         * An Active School Year cannot be manually archived.
         *
         * It becomes Archived as part of activating its replacement.
         */

        if ($schoolYear['status'] === 'Active') {

            $pdo->rollBack();

            setErrorMessage(
                'An Active School Year cannot be manually archived. Activate its replacement School Year to complete the transition.'
            );

            redirectToAcademicSetup();
        }


        /*
         * Only Inactive School Years may be manually archived.
         */

        $archiveStmt = $pdo->prepare("
            UPDATE school_years
            SET status = 'Archived'
            WHERE school_year_id = ?
              AND status = 'Inactive'
        ");

        $archiveStmt->execute([
            $schoolYearId
        ]);


        if ($archiveStmt->rowCount() !== 1) {

            $pdo->rollBack();

            setErrorMessage(
                'Unable to archive School Year.'
            );

            redirectToAcademicSetup();
        }


        $pdo->commit();


        logAudit(
            $pdo,
            'Archive School Year',
            'Archived School Year ' .
            $schoolYear['school_year'] .
            ' (ID: ' .
            $schoolYearId .
            ').'
        );


        setSuccessMessage(
            'School Year archived successfully.'
        );


    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }


        error_log(
            '[APRISM School Year Archive] ' .
            $e->getMessage()
        );


        setErrorMessage(
            'Unable to archive School Year.'
        );
    }


    redirectToAcademicSetup();
}