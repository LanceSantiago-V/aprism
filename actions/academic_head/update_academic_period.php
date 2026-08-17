<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../includes/helper/audit_helper.php';



/*
|--------------------------------------------------------------------------
| AJAX Response Handling
|--------------------------------------------------------------------------
|
| The Edit Academic Term modal submits through fetch().
| Validation and database errors must return JSON so the modal can remain
| open and preserve the user's entered values.
|
| Normal non-AJAX POST requests keep the existing redirect/session behavior
| as a fallback.
|
|--------------------------------------------------------------------------
*/

$isAjaxRequest =
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';


function academicPeriodJsonResponse(
    bool $success,
    string $message,
    int $statusCode = 200
): void {

    http_response_code($statusCode);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);

    exit;
}


function academicPeriodRedirectWithMessage(
    string $type,
    string $message
): void {

    $_SESSION[$type . '_message'] = $message;

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/academic_head_academic_setup.php'
    );

    exit;
}


function academicPeriodRespondError(
    string $message,
    int $statusCode = 422
): void {

    global $isAjaxRequest;

    if ($isAjaxRequest) {

        academicPeriodJsonResponse(
            false,
            $message,
            $statusCode
        );

    }

    academicPeriodRedirectWithMessage(
        'error',
        $message
    );
}


function academicPeriodRespondSuccess(
    string $message
): void {

    global $isAjaxRequest;

    if ($isAjaxRequest) {

        academicPeriodJsonResponse(
            true,
            $message,
            200
        );

    }

    academicPeriodRedirectWithMessage(
        'success',
        $message
    );
}




/*
|--------------------------------------------------------------------------
| Request Method
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    if ($isAjaxRequest) {

        academicPeriodJsonResponse(
            false,
            'Invalid request method.',
            405
        );

    }

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

$academicPeriodId =
    (int) ($_POST['academic_period_id'] ?? 0);

$schoolYearId =
    (int) ($_POST['school_year_id'] ?? 0);

$academicLevel =
    trim($_POST['academic_level'] ?? '');

$semester =
    trim($_POST['semester'] ?? '');

$periodName =
    trim($_POST['period_name'] ?? '');

$startDate =
    trim($_POST['start_date'] ?? '');

$endDate =
    trim($_POST['end_date'] ?? '');



/*
|--------------------------------------------------------------------------
| Required Fields
|--------------------------------------------------------------------------
*/

if (
    $academicPeriodId <= 0 ||
    $schoolYearId <= 0 ||
    $academicLevel === '' ||
    $periodName === '' ||
    $startDate === '' ||
    $endDate === ''
) {

    academicPeriodRespondError(
        'Academic period, School Year, academic level, period name, start date, and end date are required.'
    );
}



/*
|--------------------------------------------------------------------------
| Academic Level Validation
|--------------------------------------------------------------------------
*/

$allowedLevels = [
    'College',
    'Senior High School'
];


if (!in_array($academicLevel, $allowedLevels, true)) {

    academicPeriodRespondError(
        'Invalid academic level.'
    );
}



/*
|--------------------------------------------------------------------------
| Academic Period Validation
|--------------------------------------------------------------------------
*/

$allowedPeriods = [

    'College' => [
        'Prelim',
        'Midterm',
        'Pre-Final',
        'Final'
    ],

    'Senior High School' => [
        'Quarter 1',
        'Quarter 2',
        'Quarter 3',
        'Quarter 4'
    ]

];


if (
    !in_array(
        $periodName,
        $allowedPeriods[$academicLevel],
        true
    )
) {

    academicPeriodRespondError(
        'Invalid academic period for the selected academic level.'
    );
}



/*
|--------------------------------------------------------------------------
| Semester Validation / Normalization
|--------------------------------------------------------------------------
|
| College:
|   Semester is required.
|
| Senior High School:
|
|   Quarter 1 -> First Semester
|   Quarter 2 -> First Semester
|   Quarter 3 -> Second Semester
|   Quarter 4 -> Second Semester
|
| The SHS semester is derived from the quarter rather than trusted
| from the submitted browser value.
|
|--------------------------------------------------------------------------
*/

if ($academicLevel === 'College') {

    $allowedSemesters = [
        'First Semester',
        'Second Semester'
    ];

    if (!in_array($semester, $allowedSemesters, true)) {

        academicPeriodRespondError(
            'Please select a valid semester for College.'
        );
    }

} else {

    /*
     * SHS semester is determined by the quarter.
     */

    if (
        in_array(
            $periodName,
            [
                'Quarter 1',
                'Quarter 2'
            ],
            true
        )
    ) {

        $semester =
            'First Semester';

    } else {

        $semester =
            'Second Semester';
    }
}



/*
|--------------------------------------------------------------------------
| Date Validation
|--------------------------------------------------------------------------
*/

$startDateObject =
    DateTime::createFromFormat(
        'Y-m-d',
        $startDate
    );

$endDateObject =
    DateTime::createFromFormat(
        'Y-m-d',
        $endDate
    );


$startDateErrors =
    DateTime::getLastErrors();


$endDateErrors =
    DateTime::getLastErrors();


$startHasErrors =
    is_array($startDateErrors)
    &&
    (
        $startDateErrors['warning_count'] > 0
        ||
        $startDateErrors['error_count'] > 0
    );


$endHasErrors =
    is_array($endDateErrors)
    &&
    (
        $endDateErrors['warning_count'] > 0
        ||
        $endDateErrors['error_count'] > 0
    );


if (
    !$startDateObject ||
    !$endDateObject ||
    $startHasErrors ||
    $endHasErrors ||
    $startDateObject->format('Y-m-d') !== $startDate ||
    $endDateObject->format('Y-m-d') !== $endDate
) {

    academicPeriodRespondError(
        'Please provide valid start and end dates.'
    );
}



/*
|--------------------------------------------------------------------------
| Date Order
|--------------------------------------------------------------------------
*/

if ($startDate > $endDate) {

    academicPeriodRespondError(
        'The start date cannot be later than the end date.'
    );
}



/*
|--------------------------------------------------------------------------
| Database Operation
|--------------------------------------------------------------------------
*/

try {


    /*
    |--------------------------------------------------------------------------
    | Verify Existing Academic Period
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            academic_period_id,
            school_year_id,
            academic_level,
            semester,
            period_name,
            start_date,
            end_date,
            is_archived
        FROM academic_periods
        WHERE academic_period_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $academicPeriodId
    ]);

    $existingPeriod =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$existingPeriod) {

        academicPeriodRespondError(
            'The selected academic period does not exist.'
        );
    }



    /*
    |--------------------------------------------------------------------------
    | Archived Periods Cannot Be Edited
    |--------------------------------------------------------------------------
    */

    if ((int) $existingPeriod['is_archived'] === 1) {

        academicPeriodRespondError(
            'Archived academic periods cannot be edited.'
        );
    }



    /*
    |--------------------------------------------------------------------------
    | Verify School Year
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            school_year_id,
            start_date,
            end_date,
            status
        FROM school_years
        WHERE school_year_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $schoolYearId
    ]);

    $schoolYear =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$schoolYear) {

        academicPeriodRespondError(
            'The selected School Year does not exist.'
        );
    }



    /*
    |--------------------------------------------------------------------------
    | ARCHIVED SCHOOL YEAR PROTECTION
    |--------------------------------------------------------------------------
    */

    if ($schoolYear['status'] === 'Archived') {

        academicPeriodRespondError(
            'Academic periods cannot be edited for an Archived School Year.'
        );
    }



    /*
    |--------------------------------------------------------------------------
    | Academic Period Must Stay Inside School Year
    |--------------------------------------------------------------------------
    */

    $schoolYearStartDate =
        $schoolYear['start_date'];

    $schoolYearEndDate =
        $schoolYear['end_date'];


    if ($startDate < $schoolYearStartDate) {

        academicPeriodRespondError(
            'The academic period cannot start before the selected School Year begins.'
        );
    }


    if ($endDate > $schoolYearEndDate) {

        academicPeriodRespondError(
            'The academic period cannot end after the selected School Year ends.'
        );
    }



    /*
    |--------------------------------------------------------------------------
    | Prevent Overlapping Academic Periods
    |--------------------------------------------------------------------------
    |
    | Same:
    |   School Year
    |   Academic Level
    |   Semester
    |
    | And overlapping date range.
    |
    | The current record is excluded from the check.
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            academic_period_id
        FROM academic_periods
        WHERE school_year_id = ?
          AND academic_level = ?

          AND semester = ?

          AND start_date <= ?
          AND end_date >= ?

          AND academic_period_id <> ?

        LIMIT 1
    ");


    $stmt->execute([
        $schoolYearId,
        $academicLevel,
        $semester,
        $endDate,
        $startDate,
        $academicPeriodId
    ]);


    if ($stmt->fetch()) {

        academicPeriodRespondError(
            'The selected date range overlaps an existing academic period for this School Year, academic level, and semester.'
        );
    }



    /*
    |--------------------------------------------------------------------------
    | Update Academic Period
    |--------------------------------------------------------------------------
    |
    | is_archived is intentionally NOT changed here.
    |
    | There is also no status column to update.
    |
    | Status remains derived from:
    |
    |   Today < Start Date
    |       = Upcoming
    |
    |   Start Date <= Today <= End Date
    |       = Active
    |
    |   Today > End Date
    |       = Completed
    |
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE academic_periods
        SET
            school_year_id = ?,
            academic_level = ?,
            semester = ?,
            period_name = ?,
            start_date = ?,
            end_date = ?
        WHERE academic_period_id = ?
        LIMIT 1
    ");


    $stmt->execute([
        $schoolYearId,
        $academicLevel,
        $semester,
        $periodName,
        $startDate,
        $endDate,
        $academicPeriodId
    ]);



    /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    */

    logAudit(
        $pdo,
        'Update Academic Period',
        'Updated academic period "' .
        $periodName .
        '" for ' .
        $academicLevel .
        ' - ' .
        $semester .
        '.'
    );



    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    academicPeriodRespondSuccess(
        'Academic period updated successfully.'
    );



} catch (PDOException $e) {


    /*
    |--------------------------------------------------------------------------
    | Duplicate Constraint
    |--------------------------------------------------------------------------
    */

    if (
        isset($e->errorInfo[1]) &&
        (int) $e->errorInfo[1] === 1062
    ) {

        academicPeriodRespondError(
            'That academic period already exists for the selected School Year, academic level, period, and semester.',
            409
        );

    } else {

        error_log(
            '[APRISM Update Academic Period] ' .
            $e->getMessage()
        );

        academicPeriodRespondError(
            'Unable to update the academic period at this time.',
            500
        );
    }

}