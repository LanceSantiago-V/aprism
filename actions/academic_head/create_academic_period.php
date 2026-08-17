<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../includes/helper/audit_helper.php';


/*
|--------------------------------------------------------------------------
| RESPONSE MODE
|--------------------------------------------------------------------------
|
| AJAX / fetch requests receive JSON.
|
| Normal browser POST requests use the existing redirect/flash fallback.
|
|--------------------------------------------------------------------------
*/

$requestedWith =
    strtolower(
        trim(
            $_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''
        )
    );

$acceptHeader =
    strtolower(
        $_SERVER['HTTP_ACCEPT'] ?? ''
    );

$isAjaxRequest =
    $requestedWith === 'xmlhttprequest'
    ||
    str_contains(
        $acceptHeader,
        'application/json'
    );


/*
|--------------------------------------------------------------------------
| RESPONSE HELPER
|--------------------------------------------------------------------------
*/

$respond = function (bool $success, string $message, int $statusCode = 200, array $extra = []) use ($isAjaxRequest) {

    /*
     * AJAX / fetch response
     */

    if ($isAjaxRequest) {

        http_response_code(
            $statusCode
        );

        header(
            'Content-Type: application/json; charset=utf-8'
        );

        echo json_encode(
            array_merge(
                [
                    'success' => $success,
                    'message' => $message
                ],
                $extra
            ),
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }


    /*
     * Normal POST fallback
     */

    if ($success) {

        $_SESSION['success_message'] =
            $message;

    } else {

        $_SESSION['error_message'] =
            $message;
    }


    header(
        'Location: ' .
        APP_URL .
        '/dashboard/academic_head_academic_setup.php'
    );

    exit;
};


/*
|--------------------------------------------------------------------------
| REQUEST METHOD
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    if ($isAjaxRequest) {

        $respond(
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
| INPUT
|--------------------------------------------------------------------------
*/

$schoolYearId =
    (int) (
        $_POST['school_year_id'] ?? 0
    );


$academicLevel =
    trim(
        $_POST['academic_level'] ?? ''
    );


$semester =
    trim(
        $_POST['semester'] ?? ''
    );


$periodName =
    trim(
        $_POST['period_name'] ?? ''
    );


$startDate =
    trim(
        $_POST['start_date'] ?? ''
    );


$endDate =
    trim(
        $_POST['end_date'] ?? ''
    );


/*
|--------------------------------------------------------------------------
| REQUIRED FIELD VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $schoolYearId <= 0
    ||
    $academicLevel === ''
    ||
    $periodName === ''
    ||
    $startDate === ''
    ||
    $endDate === ''
) {

    $respond(
        false,
        'School Year, academic level, academic period, start date, and end date are required.',
        422
    );
}


/*
|--------------------------------------------------------------------------
| ACADEMIC LEVEL VALIDATION
|--------------------------------------------------------------------------
*/

$allowedLevels = [
    'College',
    'Senior High School'
];


if (
    !in_array(
        $academicLevel,
        $allowedLevels,
        true
    )
) {

    $respond(
        false,
        'Invalid academic level.',
        422
    );
}


/*
|--------------------------------------------------------------------------
| PERIOD NAME VALIDATION
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

    $respond(
        false,
        'Invalid academic period for the selected academic level.',
        422
    );
}


/*
|--------------------------------------------------------------------------
| SEMESTER VALIDATION / NORMALIZATION
|--------------------------------------------------------------------------
|
| College:
|
|   First Semester
|   Second Semester
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

$allowedSemesters = [
    'First Semester',
    'Second Semester'
];


if (
    $academicLevel === 'College'
) {

    if (
        !in_array(
            $semester,
            $allowedSemesters,
            true
        )
    ) {

        $respond(
            false,
            'A valid semester is required for College academic periods.',
            422
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
| DATE FORMAT VALIDATION
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


/*
 * DateTime::getLastErrors() may return false when there are
 * no errors, depending on the PHP version.
 */

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
    !$startDateObject
    ||
    !$endDateObject
    ||
    $startHasErrors
    ||
    $endHasErrors
    ||
    $startDateObject->format('Y-m-d') !== $startDate
    ||
    $endDateObject->format('Y-m-d') !== $endDate
) {

    $respond(
        false,
        'Please provide valid start and end dates.',
        422
    );
}


/*
|--------------------------------------------------------------------------
| START / END DATE ORDER
|--------------------------------------------------------------------------
*/

if (
    $startDate > $endDate
) {

    $respond(
        false,
        'The start date cannot be later than the end date.',
        422
    );
}


/*
|--------------------------------------------------------------------------
| DATABASE OPERATIONS
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | 1. VERIFY SCHOOL YEAR
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            school_year_id,
            school_year,
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
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$schoolYear) {

        $respond(
            false,
            'The selected School Year does not exist.',
            422
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ARCHIVED SCHOOL YEAR PROTECTION
    |--------------------------------------------------------------------------
    */

    if (
        $schoolYear['status'] === 'Archived'
    ) {

        $respond(
            false,
            'Academic periods cannot be created for an Archived School Year.',
            422
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 2. SCHOOL YEAR DATE BOUNDARY
    |--------------------------------------------------------------------------
    */

    $schoolYearStartDate =
        $schoolYear['start_date'];


    $schoolYearEndDate =
        $schoolYear['end_date'];


    if (
        $startDate <
        $schoolYearStartDate
    ) {

        $respond(
            false,
            'The academic period cannot start before the selected School Year begins.',
            422
        );
    }


    if (
        $endDate >
        $schoolYearEndDate
    ) {

        $respond(
            false,
            'The academic period cannot end after the selected School Year ends.',
            422
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 3. EXACT DUPLICATE VALIDATION
    |--------------------------------------------------------------------------
    |
    | College:
    |
    | School Year
    | + Academic Level
    | + Semester
    | + Period Name
    |
    | SHS:
    |
    | School Year
    | + Academic Level
    | + Semester
    | + Period Name
    |
    */

    $stmt = $pdo->prepare("
        SELECT
            academic_period_id
        FROM academic_periods
        WHERE school_year_id = ?
          AND academic_level = ?
          AND semester = ?
          AND period_name = ?
        LIMIT 1
    ");


    $stmt->execute([
        $schoolYearId,
        $academicLevel,
        $semester,
        $periodName
    ]);


    if ($stmt->fetch()) {

        $respond(
            false,
            'That academic period already exists for the selected School Year, academic level, and semester.',
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 4. DETERMINE PERIOD SEQUENCE
    |--------------------------------------------------------------------------
    |
    | College:
    |
    | First Semester:
    |   Prelim
    |   Midterm
    |   Pre-Final
    |   Final
    |
    | Second Semester:
    |   Prelim
    |   Midterm
    |   Pre-Final
    |   Final
    |
    | SHS:
    |
    | First Semester:
    |   Quarter 1
    |   Quarter 2
    |
    | Second Semester:
    |   Quarter 3
    |   Quarter 4
    |
    */

    $periodOrder =
        $allowedPeriods[$academicLevel];


    $currentPeriodIndex =
        array_search(
            $periodName,
            $periodOrder,
            true
        );


    if (
        $currentPeriodIndex === false
    ) {

        $respond(
            false,
            'Invalid academic period sequence.',
            422
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 5. DETERMINE THE REQUIRED PRECEDING PERIOD
    |--------------------------------------------------------------------------
    */

    $previousPeriodName = null;
    $previousSemester = null;


    if (
        $academicLevel === 'College'
    ) {

        if (
            $semester === 'First Semester'
        ) {

            /*
             * First Semester Prelim is the first
             * period of the College academic cycle.
             */

            if (
                $currentPeriodIndex > 0
            ) {

                $previousPeriodName =
                    $periodOrder[
                        $currentPeriodIndex - 1
                    ];

                $previousSemester =
                    'First Semester';
            }

        } else {

            /*
             * Second Semester starts only after
             * First Semester Final.
             */

            if (
                $currentPeriodIndex === 0
            ) {

                $previousPeriodName =
                    'Final';

                $previousSemester =
                    'First Semester';

            } else {

                $previousPeriodName =
                    $periodOrder[
                        $currentPeriodIndex - 1
                    ];

                $previousSemester =
                    'Second Semester';
            }
        }

    } else {

        /*
         * SHS semester boundaries:
         *
         * Quarter 1 -> First Semester
         * Quarter 2 -> First Semester
         * Quarter 3 -> Second Semester
         * Quarter 4 -> Second Semester
         *
         * Therefore Quarter 3 must follow Quarter 2,
         * even though it belongs to a new semester.
         */

        if (
            $currentPeriodIndex > 0
        ) {

            $previousPeriodName =
                $periodOrder[
                    $currentPeriodIndex - 1
                ];


            if (
                $currentPeriodIndex <= 1
            ) {

                $previousSemester =
                    'First Semester';

            } else {

                $previousSemester =
                    'Second Semester';
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | 6. VERIFY PRECEDING PERIOD
    |--------------------------------------------------------------------------
    */

    $previousPeriod = null;


    if (
        $previousPeriodName !== null
    ) {

        $stmt = $pdo->prepare("
            SELECT
                academic_period_id,
                period_name,
                semester,
                start_date,
                end_date,
                is_archived
            FROM academic_periods
            WHERE school_year_id = ?
              AND academic_level = ?
              AND semester = ?
              AND period_name = ?
            LIMIT 1
        ");


        $stmt->execute([
            $schoolYearId,
            $academicLevel,
            $previousSemester,
            $previousPeriodName
        ]);


        $previousPeriod =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$previousPeriod) {

            $requiredLabel =
                $previousPeriodName .
                ' (' .
                $previousSemester .
                ')';


            $respond(
                false,
                $requiredLabel .
                ' must be configured before ' .
                $periodName .
                '.',
                422
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | 7. PREVIOUS PERIOD DATE SEQUENCE
    |--------------------------------------------------------------------------
    */

    if (
        $previousPeriod
    ) {

        $previousEndDate =
            $previousPeriod['end_date'];


        if (
            $startDate <=
            $previousEndDate
        ) {

            $previousLabel =
                $previousPeriod['period_name'] .
                ' (' .
                $previousPeriod['semester'] .
                ')';


            $respond(
                false,
                'The ' .
                $periodName .
                ' cannot start on or before the end of the preceding ' .
                $previousLabel .
                ' period (' .
                date(
                    'M d, Y',
                    strtotime($previousEndDate)
                ) .
                '). It must start after the preceding period ends.',
                422
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | 8. GLOBAL OVERLAPPING PERIOD VALIDATION
    |--------------------------------------------------------------------------
    |
    | Semester is intentionally NOT included here.
    |
    | This prevents academic periods from overlapping even when
    | they belong to different semesters.
    |
    */

    $stmt = $pdo->prepare("
        SELECT
            academic_period_id,
            period_name,
            semester,
            start_date,
            end_date,
            is_archived
        FROM academic_periods
        WHERE school_year_id = ?
          AND academic_level = ?
          AND start_date <= ?
          AND end_date >= ?
        LIMIT 1
    ");


    $stmt->execute([
        $schoolYearId,
        $academicLevel,
        $endDate,
        $startDate
    ]);


    $overlappingPeriod =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        $overlappingPeriod
    ) {

        $existingPeriodLabel =
            $overlappingPeriod['period_name'];


        if (
            !empty(
            $overlappingPeriod['semester']
        )
        ) {

            $existingPeriodLabel .=
                ' (' .
                $overlappingPeriod['semester'] .
                ')';
        }


        $respond(
            false,
            'The selected date range overlaps the existing ' .
            $existingPeriodLabel .
            ' period for this School Year and academic level.',
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 9. CREATE ACADEMIC PERIOD
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO academic_periods (
            school_year_id,
            academic_level,
            semester,
            period_name,
            start_date,
            end_date
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ");


    $stmt->execute([
        $schoolYearId,
        $academicLevel,
        $semester,
        $periodName,
        $startDate,
        $endDate
    ]);


    $academicPeriodId =
        (int) $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | 10. AUDIT LOG
    |--------------------------------------------------------------------------
    */

    $auditDescription =
        'Created academic period "' .
        $periodName .
        '" for ' .
        $academicLevel .
        ' - ' .
        $semester;


    $auditDescription .=
        ' in School Year ' .
        $schoolYear['school_year'] .
        '.';


    logAudit(
        $pdo,
        'Create Academic Period',
        $auditDescription
    );


    /*
    |--------------------------------------------------------------------------
    | 11. SUCCESS
    |--------------------------------------------------------------------------
    */

    $respond(
        true,
        'Academic period created successfully.',
        200,
        [
            'academic_period_id' =>
                $academicPeriodId
        ]
    );


} catch (PDOException $e) {

    /*
    |--------------------------------------------------------------------------
    | DATABASE CONSTRAINT PROTECTION
    |--------------------------------------------------------------------------
    */

    if (
        isset(
        $e->errorInfo[1]
    )
        &&
        (int) $e->errorInfo[1] === 1062
    ) {

        $respond(
            false,
            'That academic period already exists for the selected School Year, academic level, and semester.',
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UNEXPECTED DATABASE ERROR
    |--------------------------------------------------------------------------
    */

    error_log(
        '[APRISM Create Academic Period] ' .
        $e->getMessage()
    );


    $respond(
        false,
        'Unable to create the academic period at this time.',
        500
    );
}


/*
|--------------------------------------------------------------------------
| FALLBACK
|--------------------------------------------------------------------------
*/

exit;