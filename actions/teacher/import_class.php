<?php

declare(strict_types=1);

/**
 * APRISM — Teacher Schedule Import / Confirmation
 *
 * FINAL TEACHER SCHEDULE CONTRACT
 *
 * Teacher-facing information:
 *   - Subject
 *   - Section
 *   - Room (optional)
 *   - Day
 *   - Start Time
 *   - End Time
 *
 * Academic Setup supplies:
 *   - Active School Year
 *   - Current Semester
 *
 * NEVER required from Teacher:
 *   - subject_id
 *   - program_id
 *   - section_id
 *   - operational_class_id
 *   - subject_code
 *   - program_code
 *   - units
 *   - academic_level
 *   - year_level
 *
 * IMPORTANT:
 *   Programs, Subjects, and Sections are persistent institutional
 *   reference records.
 *
 *   Existing records are always reused by their stable database IDs.
 *   A genuinely new Subject, Program, or Section may be established
 *   inside this transaction when enough meaningful information exists.
 *
 *   Teacher-facing identifiers/codes are never required and no fake
 *   institutional codes are generated.
 *
 *   If required institutional information is missing or ambiguous, the
 *   workflow returns to Review for the minimum human-readable values.
 *
 * Persistence:
 *
 *   Resolved Subject
 *        +
 *   Resolved Section
 *        +
 *   Teacher
 *        +
 *   Academic Setup
 *        ↓
 *   Find / Reuse Operational Class
 *        ↓
 *   Validate Schedule
 *        ↓
 *   Create Class Schedule
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../auth/role_helper.php';
require_once __DIR__ . '/../../includes/import/import_engine.php';
require_once __DIR__ . '/../../includes/import/import_resolver.php';
require_once __DIR__ . '/../../includes/helper/audit_helper.php';


/*
|--------------------------------------------------------------------------
| Teacher Authentication
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role_id'])
) {
    $_SESSION['error_message'] =
        'Your session has expired. Please log in again.';

    header(
        'Location: ' .
        APP_URL .
        '/auth/login.php'
    );

    exit;
}

if (
    (int) $_SESSION['role_id'] !==
    (int) ROLE_TEACHER
) {
    http_response_code(403);
    exit('403 Forbidden');
}


/*
|--------------------------------------------------------------------------
| Request Method
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    $_SESSION['error_message'] =
        'Invalid import request.';

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/teacher_my_class.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Validation-Only Mode
|--------------------------------------------------------------------------
|
| Used by the existing Review → Validation flow.
|
| Validation uses the same resolution/conflict rules as Confirmation,
| but rolls the transaction back so nothing is persisted.
|
*/

$validationOnly =
    isset($_POST['validation_only']) &&
    in_array(
        strtolower(
            trim(
                (string) $_POST['validation_only']
            )
        ),
        ['1', 'true', 'yes'],
        true
    );


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function importClean(mixed $value): string
{
    return trim(
        (string) ($value ?? '')
    );
}


function importIsAjaxRequest(): bool
{
    return strtolower(
        (string) (
            $_SERVER['HTTP_X_REQUESTED_WITH']
            ?? ''
        )
    ) === 'xmlhttprequest';
}


function importJsonResponse(
    bool $success,
    string $message,
    int $status = 200,
    array $extra = []
): never {
    http_response_code($status);

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    header(
        'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
    );

    echo json_encode(
        array_merge(
            [
                'success' =>
                    $success,

                'message' =>
                    $message,
            ],
            $extra
        ),
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


function importRedirect(
    string $type,
    string $message
): never {

    /*
     * AJAX requests receive JSON and are handled by the existing
     * Teacher-side toast system. Do not also queue a session flash for
     * AJAX requests, otherwise the next page load can show the same
     * message twice.
     *
     * Non-AJAX requests continue to use the existing APRISM session flash.
     */
    if (
        importIsAjaxRequest()
    ) {
        importJsonResponse(
            $type === 'success',
            $message,
            $type === 'success'
            ? 200
            : 422
        );
    }

    $_SESSION[
        $type . '_message'
    ] = $message;

    header(
        'Location: ' .
        APP_URL .
        '/dashboard/teacher_my_class.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| AJAX Validation Error
|--------------------------------------------------------------------------
|
| The Teacher UI already understands server-side validation errors.
| Keep the response compatible with the existing Review UI.
|
*/

function importValidationError(
    string $message,
    string $field = 'general',
    string $code = 'VALIDATION_ERROR'
): never {

    $errors = [
        [
            'field' =>
                $field,

            'code' =>
                $code,

            'message' =>
                $message,
        ],
    ];

    if (
        importIsAjaxRequest()
    ) {
        importJsonResponse(
            false,
            $message,
            422,
            [
                'validation' => [
                    'is_valid' =>
                        false,

                    'errors' =>
                        $errors,

                    'warnings' =>
                        [],
                ],
            ]
        );
    }

    importRedirect(
        'error',
        $message
    );
}


/**
 * Return a conditional Review request without persisting anything.
 */
function importReviewRequired(string $message, array $reviewFields, array $data = []): never
{
    global $pdo;
    if ($pdo instanceof PDO && $pdo->inTransaction())
        $pdo->rollBack();

    if (importIsAjaxRequest()) {
        importJsonResponse(false, $message, 422, [
            'requires_review' => true,
            'review_fields' => $reviewFields,
            'data' => $data,
        ]);
    }
    importRedirect('error', $message);
}


/*
|--------------------------------------------------------------------------
| Normalize Incoming Field Names
|--------------------------------------------------------------------------
|
| The existing Teacher UI may submit either:
|
|   snake_case
|
| or:
|
|   camelCase
|
*/

function importField(
    array $row,
    string $snake,
    string $camel = ''
): string {

    if (
        array_key_exists(
            $snake,
            $row
        )
    ) {
        return importClean(
            $row[$snake]
        );
    }

    if (
        $camel !== '' &&
        array_key_exists(
            $camel,
            $row
        )
    ) {
        return importClean(
            $row[$camel]
        );
    }

    return '';
}


/*
|--------------------------------------------------------------------------
| Normalize Semester Labels
|--------------------------------------------------------------------------
*/

function importNormalizeSemester(
    string $semester
): string {

    $value =
        strtolower(
            trim($semester)
        );

    return match ($value) {

        'first semester',
        '1st semester',
        '1st sem',
        'first sem',
        'semester 1',
        'sem 1'
        =>
        'First Semester',

        'second semester',
        '2nd semester',
        '2nd sem',
        'second sem',
        'semester 2',
        'sem 2'
        =>
        'Second Semester',

        default
        =>
        trim($semester),
    };
}


/*
|--------------------------------------------------------------------------
| Read Import Payload
|--------------------------------------------------------------------------
*/

$rows = [];

$importDataRaw =
    $_POST['import_data']
    ?? null;

if (
    is_string($importDataRaw) &&
    trim($importDataRaw) !== ''
) {

    $decoded =
        json_decode(
            $importDataRaw,
            true
        );

    if (
        json_last_error() !==
        JSON_ERROR_NONE
    ) {
        importRedirect(
            'error',
            'The imported class information could not be read.'
        );
    }

    if (
        isset($decoded['rows']) &&
        is_array($decoded['rows'])
    ) {

        $rows =
            $decoded['rows'];

    } elseif (
        is_array($decoded)
    ) {

        $rows[] =
            $decoded;
    }
}


/*
|--------------------------------------------------------------------------
| Direct POST Compatibility
|--------------------------------------------------------------------------
|
| Keep compatibility with the existing manual Teacher Schedule form.
|
| Only the actual Teacher-facing fields are used.
|
*/

if (
    empty($rows)
) {

    $rows[] = [

        'source' =>
            importClean(
                $_POST['source']
                ?? 'manual'
            ),

        'subject_name' =>
            importClean(
                $_POST['subject_name']
                ??
                $_POST['subject']
                ??
                ''
            ),

        'section_name' =>
            importClean(
                $_POST['section_name']
                ??
                $_POST['section']
                ??
                ''
            ),

        'school_year' =>
            importClean(
                $_POST['school_year']
                ?? ''
            ),

        'semester' =>
            importClean(
                $_POST['semester']
                ?? ''
            ),

        'day' =>
            importClean(
                $_POST['day']
                ?? ''
            ),

        'start_time' =>
            importClean(
                $_POST['start_time']
                ?? ''
            ),

        'end_time' =>
            importClean(
                $_POST['end_time']
                ?? ''
            ),

        'room' =>
            importClean(
                $_POST['room']
                ?? ''
            ),
    ];
}


/*
|--------------------------------------------------------------------------
| Current Confirmation Scope
|--------------------------------------------------------------------------
|
| Teacher My Classes currently confirms one class at a time.
|
*/

if (
    count($rows) !== 1
) {
    importRedirect(
        'error',
        'Please confirm one class at a time.'
    );
}

$row =
    $rows[0];

if (
    !is_array($row)
) {
    importRedirect(
        'error',
        'The imported class information is invalid.'
    );
}


/*
|--------------------------------------------------------------------------
| Convert UI Fields Into Common Import Structure
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| We intentionally do NOT collect:
|
|   subject_code
|   program_code
|   program_name
|   units
|   academic_level
|   year_level
|
| Those are not Teacher Schedule inputs.
|
*/

$input = [

    'source' =>
        importField(
            $row,
            'source'
        ) ?: 'manual',

    'subject_name' =>
        importField(
            $row,
            'subject_name',
            'subject'
        ),

    'section_name' =>
        importField(
            $row,
            'section_name',
            'section'
        ),

    'school_year' =>
        importField(
            $row,
            'school_year',
            'schoolYear'
        ),

    'semester' =>
        importField(
            $row,
            'semester'
        ),

    'day' =>
        importField(
            $row,
            'day'
        ),

    'start_time' =>
        importField(
            $row,
            'start_time',
            'startTime'
        ),

    'end_time' =>
        importField(
            $row,
            'end_time',
            'endTime'
        ),

    'room' =>
        importField(
            $row,
            'room'
        ),

    'subject_code' =>
        importField($row, 'subject_code', 'subjectCode'),

    'program_code' =>
        importField($row, 'program_code', 'programCode'),

    'program_name' =>
        importField($row, 'program_name', 'programName'),

    'academic_level' =>
        importField($row, 'academic_level', 'academicLevel'),

    'year_level' =>
        importField($row, 'year_level', 'yearLevel'),

];


/*
|--------------------------------------------------------------------------
| Transaction
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Resolve Active School Year
    |--------------------------------------------------------------------------
    |
    | Academic Setup is the source of truth.
    |
    | The Teacher cannot create or activate a School Year here.
    |
    */

    $sourceSchoolYear =
        $input['school_year'];

    $schoolYearSql = "
        SELECT
            school_year_id,
            school_year,
            start_date,
            end_date,
            status
        FROM school_years
        WHERE status = 'Active'
    ";

    $schoolYearParams = [];

    if (
        $sourceSchoolYear !== ''
    ) {

        $schoolYearSql .= "
            AND school_year = ?
        ";

        $schoolYearParams[] =
            $sourceSchoolYear;
    }

    $schoolYearSql .= "
        ORDER BY
            school_year_id DESC
        LIMIT 1
        FOR UPDATE
    ";

    $schoolYearStmt =
        $pdo->prepare(
            $schoolYearSql
        );

    $schoolYearStmt->execute(
        $schoolYearParams
    );

    $schoolYear =
        $schoolYearStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (
        !$schoolYear
    ) {

        importValidationError(
            $sourceSchoolYear !== ''
            ? 'The selected School Year does not match the currently Active School Year.'
            : 'No Active School Year is currently available.',
            'school_year',
            'ACADEMIC_CONTEXT_ERROR'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Determine Current Academic Period / Semester
    |--------------------------------------------------------------------------
    |
    | We keep the existing Academic Setup behavior.
    |
    */

    $today =
        date('Y-m-d');

    $periodStmt =
        $pdo->prepare(
            "
            SELECT
                academic_period_id,
                academic_level,
                semester,
                period_name,
                start_date,
                end_date
            FROM academic_periods
            WHERE school_year_id = ?
              AND is_archived = 0
              AND start_date <= ?
              AND end_date >= ?
            ORDER BY
                start_date ASC,
                academic_period_id ASC
            FOR UPDATE
            "
        );

    $periodStmt->execute([
        (int) 
        $schoolYear['school_year_id'],

        $today,
        $today,
    ]);

    $currentPeriods =
        $periodStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    if (
        empty($currentPeriods)
    ) {

        importValidationError(
            'No active Academic Period currently covers today for the Active School Year.',
            'semester',
            'ACADEMIC_PERIOD_ERROR'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Determine Semester
    |--------------------------------------------------------------------------
    */

    $sourceSemester =
        importNormalizeSemester(
            $input['semester']
        );

    if (
        $sourceSemester !== ''
    ) {

        $semesterMatches = [];

        foreach (
            $currentPeriods
            as $period
        ) {

            $periodSemester =
                importNormalizeSemester(
                    importClean(
                        $period['semester']
                    )
                );

            if (
                $periodSemester ===
                $sourceSemester
            ) {

                $semesterMatches[] =
                    $period;
            }
        }

        if (
            empty($semesterMatches)
        ) {

            importValidationError(
                'The selected Semester does not match the current Academic Setup.',
                'semester',
                'ACADEMIC_CONTEXT_ERROR'
            );
        }

        $currentPeriods =
            $semesterMatches;
    }


    /*
    |--------------------------------------------------------------------------
    | Require One Current Semester Context
    |--------------------------------------------------------------------------
    */

    $semesterValues = [];

    foreach (
        $currentPeriods
        as $period
    ) {

        $periodSemester =
            importNormalizeSemester(
                importClean(
                    $period['semester']
                )
            );

        if (
            $periodSemester !== ''
        ) {

            $semesterValues[
                $periodSemester
            ] = true;
        }
    }

    if (
        count($semesterValues) !== 1
    ) {

        importValidationError(
            'APRISM could not determine one current Semester from Academic Setup.',
            'semester',
            'ACADEMIC_CONTEXT_ERROR'
        );
    }

    $semester =
        array_key_first(
            $semesterValues
        );


    /*
    |--------------------------------------------------------------------------
    | Academic Setup Context Is Authoritative
    |--------------------------------------------------------------------------
    */

    $input['school_year'] =
        $schoolYear['school_year'];

    $input['semester'] =
        $semester;


    /*
    |--------------------------------------------------------------------------
    | Basic Teacher Schedule Validation
    |--------------------------------------------------------------------------
    */

    $subjectName =
        importClean(
            $input['subject_name']
        );

    $sectionName =
        importClean(
            $input['section_name']
        );

    $day =
        importClean(
            $input['day']
        );

    $startTime =
        importClean(
            $input['start_time']
        );

    $endTime =
        importClean(
            $input['end_time']
        );

    $room =
        importClean(
            $input['room']
        );


    if (
        $subjectName === ''
    ) {

        importValidationError(
            'Subject is required.',
            'subject',
            'SUBJECT_REQUIRED'
        );
    }


    if (
        $sectionName === ''
    ) {

        importValidationError(
            'Section is required.',
            'section',
            'SECTION_REQUIRED'
        );
    }


    if (
        $day === ''
    ) {

        importValidationError(
            'Day is required.',
            'day',
            'DAY_REQUIRED'
        );
    }


    if (
        $startTime === '' ||
        $endTime === ''
    ) {

        importValidationError(
            'Start Time and End Time are required.',
            'time',
            'TIME_REQUIRED'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Normalize Through Existing Import Engine
    |--------------------------------------------------------------------------
    |
    | We retain the existing normalization pipeline.
    |
    | Import sources can still eventually provide additional values,
    | but Teacher Schedule itself only requires the six teaching fields.
    |
    */

    $normalized =
        ImportEngine::normalizeRecord(
            $input,
            $input['source']
        );


    /*
    |--------------------------------------------------------------------------
    | Import Engine Validation
    |--------------------------------------------------------------------------
    */

    $engineValidation =
        $normalized['validation']
        ??
        [
            'is_valid' =>
                true,

            'errors' =>
                [],

            'warnings' =>
                [],
        ];


    /*
    |--------------------------------------------------------------------------
    | Do Not Let Obsolete Reference Requirements Block Teacher Schedule
    |--------------------------------------------------------------------------
    |
    | The old importer used validation to demand:
    |
    |   Subject Code
    |   Units
    |   Program Code
    |   Program Name
    |   Academic Level
    |   Year Level
    |
    | Those are NOT Teacher Schedule requirements anymore.
    |
    | We therefore only honor structural errors that concern the
    | actual Teacher Schedule fields.
    |--------------------------------------------------------------------------
    */

    if (
        empty(
        $engineValidation['is_valid']
    )
    ) {

        $allowedFields = [
            'subject',
            'subject_name',
            'section',
            'section_name',
            'day',
            'start_time',
            'end_time',
            'room',
            'school_year',
            'semester',
        ];

        $relevantErrors = [];

        foreach (
            ($engineValidation['errors'] ?? [])
            as $engineError
        ) {

            $field =
                importClean(
                    $engineError['field']
                    ??
                    $engineError['name']
                    ??
                    ''
                );

            /*
             * Ignore obsolete master-data requirements.
             *
             * The resolver below is the authority for whether the
             * existing Subject/Section can actually be resolved.
             */

            if (
                in_array(
                    $field,
                    [
                        'subject_code',
                        'program_code',
                        'program_name',
                        'units',
                        'academic_level',
                        'year_level',
                        'subjectCode',
                        'programCode',
                        'programName',
                        'academicLevel',
                        'yearLevel',
                    ],
                    true
                )
            ) {
                continue;
            }

            if (
                in_array(
                    $field,
                    $allowedFields,
                    true
                )
            ) {

                $relevantErrors[] =
                    $engineError;
            }
        }

        if (
            !empty($relevantErrors)
        ) {

            $firstError =
                $relevantErrors[0];

            importValidationError(
                $firstError['message']
                ??
                'The class information contains an invalid value.',
                $firstError['field']
                ??
                'general',
                $firstError['code']
                ??
                'VALIDATION_ERROR'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Use Normalized Values
    |--------------------------------------------------------------------------
    */

    $subjectName =
        importClean(
            $normalized['subject_name']
            ??
            $subjectName
        );

    $sectionName =
        importClean(
            $normalized['section_name']
            ??
            $sectionName
        );

    $day =
        importClean(
            $normalized['day']
            ??
            $day
        );

    $startTime =
        importClean(
            $normalized['start_time']
            ??
            $startTime
        );

    $endTime =
        importClean(
            $normalized['end_time']
            ??
            $endTime
        );

    $room =
        importClean(
            $normalized['room']
            ??
            $room
        );

    $programCode =
        importClean($normalized['program_code'] ?? $input['program_code']);

    $programName =
        importClean($normalized['program_name'] ?? $input['program_name']);

    $academicLevel =
        importClean($normalized['academic_level'] ?? $input['academic_level']);

    $yearLevel =
        importClean($normalized['year_level'] ?? $input['year_level']);


    /*
    |--------------------------------------------------------------------------
    | Normalize Day
    |--------------------------------------------------------------------------
    */

    $dayMap = [

        'monday' =>
            'Monday',

        'tuesday' =>
            'Tuesday',

        'wednesday' =>
            'Wednesday',

        'thursday' =>
            'Thursday',

        'friday' =>
            'Friday',

        'saturday' =>
            'Saturday',
    ];

    $normalizedDay =
        $dayMap[
            strtolower(
                $day
            )
        ] ?? null;

    if (
        $normalizedDay === null
    ) {

        importValidationError(
            'The selected Day is invalid.',
            'day',
            'INVALID_DAY'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Times
    |--------------------------------------------------------------------------
    */

    $startDateTime =
        DateTime::createFromFormat(
            'H:i',
            $startTime
        );

    $endDateTime =
        DateTime::createFromFormat(
            'H:i',
            $endTime
        );


    if (
        $startDateTime === false ||
        $endDateTime === false
    ) {

        importValidationError(
            'The Start Time or End Time format is invalid.',
            'time',
            'INVALID_TIME'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Detect DateTime Warnings
    |--------------------------------------------------------------------------
    */

    $startErrors =
        DateTime::getLastErrors();

    $endErrors =
        DateTime::getLastErrors();

    if (
        is_array($startErrors) &&
        (
            $startErrors['warning_count'] > 0 ||
            $startErrors['error_count'] > 0
        )
    ) {

        importValidationError(
            'The Start Time is invalid.',
            'start_time',
            'INVALID_START_TIME'
        );
    }

    if (
        is_array($endErrors) &&
        (
            $endErrors['warning_count'] > 0 ||
            $endErrors['error_count'] > 0
        )
    ) {

        importValidationError(
            'The End Time is invalid.',
            'end_time',
            'INVALID_END_TIME'
        );
    }


    $normalizedStartTime =
        $startDateTime->format(
            'H:i:s'
        );

    $normalizedEndTime =
        $endDateTime->format(
            'H:i:s'
        );


    if (
        $normalizedEndTime <=
        $normalizedStartTime
    ) {

        importValidationError(
            'End Time must be later than Start Time.',
            'end_time',
            'INVALID_TIME_RANGE'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Resolve Existing Institutional References
    |--------------------------------------------------------------------------
    |
    | THIS IS THE CRITICAL CHANGE.
    |
    | Subject may be established automatically when it is genuinely new.
    | Program and Section remain existing institutional references for the
    | six-field Teacher workflow.
    |
    | Existing institutional records only.
    |
    */

    $resolver =
        new ImportResolver(
            $pdo
        );


    /*
    |--------------------------------------------------------------------------
    | Subject Resolve-or-Establish
    |--------------------------------------------------------------------------
    |
    | Subject is the one persistent reference that Teacher Schedule may
    | establish automatically from the Teacher's human-readable Subject
    | name. No code, units, or database identifier is required.
    |
    | Existing Subject  -> reuse existing subject_id
    | Missing Subject   -> create exactly one persistent Subject
    | Ambiguous Subject -> block
    |
    | The operation is inside the existing transaction, so validation-only
    | requests roll it back and failed schedule validation rolls it back.
    |--------------------------------------------------------------------------
    */

    $subjectResolution =
        $resolver->resolveSubject(
            null,
            $subjectName
        );


    if (
        $subjectResolution['status'] ===
        'ambiguous'
    ) {

        importValidationError(
            'The Subject "' .
            $subjectName .
            '" matches more than one institutional Subject record.',
            'subject',
            'SUBJECT_AMBIGUOUS'
        );
    }


    if (
        $subjectResolution['status'] ===
        'invalid'
    ) {

        importValidationError(
            $subjectResolution['message']
            ??
            'The Subject information is invalid.',
            'subject',
            'SUBJECT_INVALID'
        );
    }


    if (
        $subjectResolution['status'] ===
        'resolved'
    ) {

        $subject =
            $subjectResolution['record'];

    } elseif (
        $subjectResolution['status'] ===
        'missing'
    ) {

        try {

            $subject =
                $resolver->createSubject(
                    $subjectName,
                    null,
                    null
                );

        } catch (RuntimeException $e) {

            importValidationError(
                $e->getMessage(),
                'subject',
                'SUBJECT_ESTABLISHMENT_FAILED'
            );
        }

    } else {

        importValidationError(
            $subjectResolution['message']
            ??
            'The Subject could not be resolved or established.',
            'subject',
            'SUBJECT_RESOLUTION_FAILED'
        );
    }


    $subjectId =
        (int) 
        $subject['subject_id'];


    /*
    |--------------------------------------------------------------------------
    | Section / Program Resolve-or-Establish
    |--------------------------------------------------------------------------
    |
    | Existing Section -> reuse section_id and its existing program_id.
    | New Section -> create using Section Name only.
    |
    | Program and Year Level are intentionally optional for a new Section.
    | Multiple matching Sections remain ambiguous and must never be guessed.
    |--------------------------------------------------------------------------
    */

    $sectionResolution = $resolver->resolveSection($sectionName);

    if ($sectionResolution['status'] === 'resolved') {

        $section = $sectionResolution['record'];

    } elseif ($sectionResolution['status'] === 'ambiguous') {

        importValidationError(
            $sectionResolution['message'] ?? 'The Section name matches more than one institutional Section record.',
            'section',
            'SECTION_AMBIGUOUS'
        );

    } elseif ($sectionResolution['status'] === 'invalid') {

        importValidationError(
            $sectionResolution['message'] ?? 'The Section information is invalid.',
            'section',
            'SECTION_INVALID'
        );

    } elseif ($sectionResolution['status'] === 'missing') {

        try {
            $section = $resolver->createSection($sectionName, null, null);
        } catch (RuntimeException $e) {
            importValidationError($e->getMessage(), 'section', 'SECTION_ESTABLISHMENT_FAILED');
        }

    } else {

        importValidationError(
            $sectionResolution['message'] ?? 'The Section could not be resolved or established.',
            'section',
            'SECTION_RESOLUTION_FAILED'
        );
    }

    $sectionId = (int) $section['section_id'];
    $programId = $section['program_id'] !== null
        ? (int) $section['program_id']
        : null;

    /*
    |--------------------------------------------------------------------------
    | Final Reference Safety Check
    |--------------------------------------------------------------------------
    */

    if (
        $subjectId <= 0
    ) {

        importValidationError(
            'APRISM could not resolve the institutional Subject.',
            'subject',
            'SUBJECT_RESOLUTION_FAILED'
        );
    }


    if (
        $sectionId <= 0
    ) {

        importValidationError(
            'APRISM could not resolve the institutional Section.',
            'section',
            'SECTION_RESOLUTION_FAILED'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Resolve / Reuse Operational Class
    |--------------------------------------------------------------------------
    |
    | An Operational Class represents the teaching assignment/context:
    |
    |   Teacher + Subject + Section + School Year + Semester
    |
    | Therefore an existing Operational Class is NOT automatically a
    | duplicate. It may legitimately receive another Class Schedule.
    |
    | Example:
    |
    |   Existing OC:
    |       Database Systems Laboratory
    |       BSIT-3A
    |       Teacher A
    |
    |   New valid schedule:
    |       Wednesday 1:00 PM - 3:00 PM
    |
    |   Result:
    |       REUSE the existing operational_class_id
    |       CREATE a new class_schedule
    |
    | Only an exact duplicate Class Schedule is blocked.
    |
    */

    $operationalClassStmt =
        $pdo->prepare(
            "
            SELECT
                operational_class_id
            FROM operational_classes
            WHERE teacher_id = ?
              AND subject_id = ?
              AND section_id = ?
              AND school_year = ?
              AND semester = ?
              AND status = 'Active'
            LIMIT 1
            FOR UPDATE
            "
        );

    $operationalClassStmt->execute([

        (int) 
        $_SESSION['user_id'],

        $subjectId,

        $sectionId,

        $schoolYear['school_year'],

        $semester,
    ]);

    $existingOperationalClass =
        $operationalClassStmt->fetch(
            PDO::FETCH_ASSOC
        );

    $operationalClassId =
        $existingOperationalClass
        ? (int) $existingOperationalClass['operational_class_id']
        : 0;


    /*
    |--------------------------------------------------------------------------
    | Exact Duplicate Schedule
    |--------------------------------------------------------------------------
    |
    | Same Operational Class + same Day + same Start + same End + same Room
    | is an exact duplicate and must be blocked.
    |
    | The room comparison uses MySQL's NULL-safe equality operator (<=>)
    | so two schedules with no room are also treated as exact duplicates.
    |
    */

    if (
        $operationalClassId > 0
    ) {

        $duplicateScheduleStmt =
            $pdo->prepare(
                "
                SELECT
                    class_schedule_id
                FROM class_schedules
                WHERE operational_class_id = ?
                  AND day = ?
                  AND start_time = ?
                  AND end_time = ?
                  AND (room <=> ?)
                  AND status = 'Active'
                LIMIT 1
                FOR UPDATE
                "
            );

        $duplicateScheduleStmt->execute([

            $operationalClassId,

            $normalizedDay,

            $normalizedStartTime,

            $normalizedEndTime,

            $room !== ''
            ? $room
            : null,
        ]);

        if (
            $duplicateScheduleStmt->fetch(
                PDO::FETCH_ASSOC
            )
        ) {

            importValidationError(
                'This exact class schedule already exists in your My Classes for the current School Year and Semester.',
                'schedule',
                'DUPLICATE_CLASS_SCHEDULE'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Teacher Schedule Conflict
    |--------------------------------------------------------------------------
    |
    | Same Teacher + overlapping time = BLOCK.
    |
    */

    $teacherConflictStmt =
        $pdo->prepare(
            "
            SELECT
                oc.operational_class_id
            FROM operational_classes AS oc

            INNER JOIN class_schedules AS cs
                ON cs.operational_class_id =
                   oc.operational_class_id

            WHERE oc.teacher_id = ?

              AND oc.school_year = ?

              AND oc.semester = ?

              AND oc.status = 'Active'

              AND cs.day = ?

              AND cs.status = 'Active'

              AND cs.start_time < ?

              AND cs.end_time > ?

            LIMIT 1

            FOR UPDATE
            "
        );


    $teacherConflictStmt->execute([

        (int) 
        $_SESSION['user_id'],

        $schoolYear['school_year'],

        $semester,

        $normalizedDay,

        $normalizedEndTime,

        $normalizedStartTime,
    ]);


    if (
        $teacherConflictStmt->fetch(
            PDO::FETCH_ASSOC
        )
    ) {

        importValidationError(
            'This schedule conflicts with another active class assigned to you.',
            'schedule',
            'TEACHER_SCHEDULE_CONFLICT'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Section Schedule Conflict
    |--------------------------------------------------------------------------
    |
    | Same Section + overlapping time = BLOCK.
    |
    */

    $sectionConflictStmt =
        $pdo->prepare(
            "
            SELECT
                oc.operational_class_id

            FROM operational_classes AS oc

            INNER JOIN class_schedules AS cs
                ON cs.operational_class_id =
                   oc.operational_class_id

            WHERE oc.section_id = ?

              AND oc.school_year = ?

              AND oc.semester = ?

              AND oc.status = 'Active'

              AND cs.day = ?

              AND cs.status = 'Active'

              AND cs.start_time < ?

              AND cs.end_time > ?

            LIMIT 1

            FOR UPDATE
            "
        );


    $sectionConflictStmt->execute([

        $sectionId,

        $schoolYear['school_year'],

        $semester,

        $normalizedDay,

        $normalizedEndTime,

        $normalizedStartTime,
    ]);


    if (
        $sectionConflictStmt->fetch(
            PDO::FETCH_ASSOC
        )
    ) {

        importValidationError(
            'This schedule conflicts with another active class assigned to the selected Section.',
            'schedule',
            'SECTION_SCHEDULE_CONFLICT'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Room Schedule Conflict
    |--------------------------------------------------------------------------
    |
    | Room is optional.
    |
    | If supplied:
    |
    | Same Room + overlapping time = BLOCK.
    |
    */

    if (
        $room !== ''
    ) {

        $roomConflictStmt =
            $pdo->prepare(
                "
                SELECT
                    cs.class_schedule_id

                FROM class_schedules AS cs

                INNER JOIN operational_classes AS oc
                    ON oc.operational_class_id =
                       cs.operational_class_id

                WHERE oc.school_year = ?

                  AND oc.semester = ?

                  AND oc.status = 'Active'

                  AND cs.day = ?

                  AND cs.status = 'Active'

                  AND TRIM(cs.room) =
                      TRIM(?)

                  AND cs.start_time < ?

                  AND cs.end_time > ?

                LIMIT 1

                FOR UPDATE
                "
            );


        $roomConflictStmt->execute([

            $schoolYear['school_year'],

            $semester,

            $normalizedDay,

            $room,

            $normalizedEndTime,

            $normalizedStartTime,
        ]);


        if (
            $roomConflictStmt->fetch(
                PDO::FETCH_ASSOC
            )
        ) {

            importValidationError(
                'The selected room is already occupied during this time.',
                'room',
                'ROOM_SCHEDULE_CONFLICT'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Validation-Only
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | No INSERT happens before this point.
    |
    | Therefore validation can safely roll back without creating
    | anything.
    |
    */

    if (
        $validationOnly
    ) {

        $pdo->rollBack();

        importJsonResponse(
            true,
            'Class information passed validation.',
            200,
            [
                'validation' => [

                    'is_valid' =>
                        true,

                    'errors' =>
                        [],

                    'warnings' =>
                        [],
                ],

                'data' => [

                    'school_year' =>
                        $schoolYear['school_year'],

                    'semester' =>
                        $semester,

                    'subject_name' =>
                        $subjectName,

                    'section_name' =>
                        $sectionName,

                    'room' =>
                        $room,

                    'day' =>
                        $normalizedDay,

                    'start_time' =>
                        $normalizedStartTime,

                    'end_time' =>
                        $normalizedEndTime,

                    /*
                     * Internal IDs are returned only to the backend
                     * response for downstream processing if required.
                     *
                     * They are NOT Teacher inputs and should not be
                     * displayed by the UI.
                     */
                    'subject_id' =>
                        $subjectId,

                    'program_id' =>
                        $programId,

                    'section_id' =>
                        $sectionId,

                    'program_name' =>
                        $section['program_name'] ?? $programName,

                    'academic_level' =>
                        $section['academic_level'] ?? $academicLevel,

                    'year_level' =>
                        $section['year_level'] ?? $yearLevel,
                ],
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Create Operational Class When Needed
    |--------------------------------------------------------------------------
    |
    | If the teaching assignment already exists, keep its existing
    | operational_class_id and continue.
    |
    | If it does not exist, create exactly one Operational Class for
    | this Teacher + Subject + Section + School Year + Semester.
    |
    */

    if (
        $operationalClassId <= 0
    ) {

        $insertOperationalClass =
            $pdo->prepare(
                "
                INSERT INTO operational_classes (
                    teacher_id,
                    subject_id,
                    section_id,
                    school_year,
                    semester,
                    status
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'Active'
                )
                "
            );

        try {

            $insertOperationalClass->execute([

                (int) 
                $_SESSION['user_id'],

                $subjectId,

                $sectionId,

                $schoolYear['school_year'],

                $semester,
            ]);

        } catch (
            PDOException $e
        ) {

            /*
             * A concurrent request may have created the same
             * Operational Class after our lookup.
             *
             * Roll back this transaction and let the normal error
             * handling report the failure rather than creating an
             * inconsistent second class.
             */

            if (
                $e->getCode() === '23000'
            ) {

                throw new RuntimeException(
                    'This teaching assignment was created by another request. Please reopen the class import and try the schedule again.'
                );
            }

            throw $e;
        }

        $operationalClassId =
            (int) 
            $pdo->lastInsertId();

        if (
            $operationalClassId <= 0
        ) {

            throw new RuntimeException(
                'APRISM could not create the Operational Class.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Create Class Schedule
    |--------------------------------------------------------------------------
    */

    $insertSchedule =
        $pdo->prepare(
            "
            INSERT INTO class_schedules (
                operational_class_id,
                day,
                start_time,
                end_time,
                room,
                status
            )
            VALUES (
                ?,
                ?,
                ?,
                ?,
                ?,
                'Active'
            )
            "
        );


    try {

        $insertSchedule->execute([

            $operationalClassId,

            $normalizedDay,

            $normalizedStartTime,

            $normalizedEndTime,

            $room !== ''
            ? $room
            : null,
        ]);

    } catch (
        PDOException $e
    ) {

        /*
         * Any failure here must roll back the Operational Class too.
         */

        throw $e;
    }


    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    */

    logAudit(
        $pdo,
        'Import Class',
        'Added operational class for Subject "' .
        $subjectName .
        '" and Section "' .
        $sectionName .
        '".'
    );


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    |
    | Uses the existing global APRISM flash/toast system.
    |
    */

    importRedirect(
        'success',
        'Class added to My Classes successfully.'
    );


} catch (
    PDOException $e
) {

    if (
        $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }


    error_log(
        '[APRISM Import Class PDO] ' .
        $e->getMessage()
    );


    importRedirect(
        'error',
        'The class could not be imported because a database operation failed. No changes were saved.'
    );


} catch (
    RuntimeException $e
) {

    if (
        $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }


    /*
     * RuntimeException messages produced by validation/conflict
     * paths should reach the existing APRISM toast system.
     */

    importRedirect(
        'error',
        $e->getMessage()
    );


} catch (
    Throwable $e
) {

    if (
        $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }


    error_log(
        '[APRISM Import Class] ' .
        $e->getMessage()
    );


    importRedirect(
        'error',
        'The class could not be imported. No changes were saved.'
    );
}