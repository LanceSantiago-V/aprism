<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../auth/role_helper.php';

$allowedRoles = [
    ROLE_ACADEMIC_HEAD
];

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/session_guard.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';


/*
|--------------------------------------------------------------------------
| Request Validation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/academic_head_academic_setup.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Input
|--------------------------------------------------------------------------
*/

$schoolYear = trim($_POST['school_year'] ?? '');
$startDate = trim($_POST['start_date'] ?? '');
$endDate = trim($_POST['end_date'] ?? '');
$status = trim($_POST['status'] ?? 'Inactive');


/*
|--------------------------------------------------------------------------
| School Year Validation
|--------------------------------------------------------------------------
*/

if ($schoolYear === '') {

    $_SESSION['error_message'] =
        'School Year is required.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/academic_head_academic_setup.php'
    );

    exit;
}


/*
 * Expected format:
 *
 * YYYY-YYYY
 *
 * Example:
 * 2026-2027
 */

if (!preg_match('/^\d{4}-\d{4}$/', $schoolYear)) {

    $_SESSION['error_message'] =
        'School Year must use the format YYYY-YYYY.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/academic_head_academic_setup.php'
    );

    exit;
}


$years = explode('-', $schoolYear);

$startYear = (int) $years[0];
$endYear = (int) $years[1];


if ($endYear !== ($startYear + 1)) {

    $_SESSION['error_message'] =
        'School Year must contain consecutive academic years.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/academic_head_academic_setup.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Date Validation
|--------------------------------------------------------------------------
*/

if ($startDate === '' || $endDate === '') {

    $_SESSION['error_message'] =
        'Start Date and End Date are required.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/academic_head_academic_setup.php'
    );

    exit;
}


$startDateObject = DateTime::createFromFormat(
    'Y-m-d',
    $startDate
);

$endDateObject = DateTime::createFromFormat(
    'Y-m-d',
    $endDate
);


if (
    !$startDateObject ||
    $startDateObject->format('Y-m-d') !== $startDate ||
    !$endDateObject ||
    $endDateObject->format('Y-m-d') !== $endDate
) {

    $_SESSION['error_message'] =
        'Invalid academic calendar dates.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/academic_head_academic_setup.php'
    );

    exit;
}


if ($startDate > $endDate) {

    $_SESSION['error_message'] =
        'Start Date cannot be later than End Date.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/academic_head_academic_setup.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Status Validation
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'Active',
    'Inactive'
];

if (!in_array($status, $allowedStatuses, true)) {

    $_SESSION['error_message'] =
        'Invalid School Year status.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/academic_head_academic_setup.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Database Operation
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
     * Check for an existing School Year.
     */

    $checkStmt = $pdo->prepare("
        SELECT school_year_id
        FROM school_years
        WHERE school_year = ?
        LIMIT 1
    ");

    $checkStmt->execute([
        $schoolYear
    ]);


    if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {

        $pdo->rollBack();

        $_SESSION['error_message'] =
            'This School Year already exists.';

        header(
            'Location: ' .
            APP_URL .
            '/dashboard/academic_head_academic_setup.php'
        );

        exit;
    }


    /*
     * Only one School Year may be Active.
     *
     * If the new School Year is created as Active,
     * the existing Active School Year becomes Inactive.
     *
     * Existing records are preserved.
     */

    if ($status === 'Active') {

        $deactivateStmt = $pdo->prepare("
            UPDATE school_years
            SET status = 'Inactive'
            WHERE status = 'Active'
        ");

        $deactivateStmt->execute();
    }


    /*
     * Insert the new School Year.
     */

    $insertStmt = $pdo->prepare("
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
            ?
        )
    ");

    $insertStmt->execute([
        $schoolYear,
        $startDate,
        $endDate,
        $status
    ]);


    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    */

    logAudit(
        $pdo,
        'Create School Year',
        'Created School Year ' .
        $schoolYear .
        ' (' .
        $startDate .
        ' to ' .
        $endDate .
        ') with status ' .
        $status .
        '.'
    );


    $_SESSION['success_message'] =
        'School Year ' .
        $schoolYear .
        ' was created successfully.';


} catch (PDOException $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();
    }


    error_log(
        '[APRISM Create School Year] ' .
        $e->getMessage()
    );


    $_SESSION['error_message'] =
        'Unable to create the School Year.';
}


/*
|--------------------------------------------------------------------------
| Return to Academic Setup
|--------------------------------------------------------------------------
*/

header(
    'Location: ' .
    APP_URL .
    '/dashboard/academic_head_academic_setup.php'
);

exit;