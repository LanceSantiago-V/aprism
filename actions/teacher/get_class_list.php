<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/role_helper.php';

$allowedRoles = [
    ROLE_TEACHER,
];

require_once __DIR__ . '/../../auth/session_guard.php';

function classListReadResponse(
    bool $success,
    string $message,
    int $status = 200,
    array $data = []
): never {
    http_response_code($status);

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ],
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    classListReadResponse(
        false,
        'Invalid Class List request.',
        405
    );
}

$operationalClassId = filter_var(
    $_GET['operational_class_id'] ?? null,
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1,
        ],
    ]
);

if ($operationalClassId === false) {
    classListReadResponse(
        false,
        'A valid Operational Class is required.',
        422
    );
}

$operationalClassId = (int) $operationalClassId;

try {
    $classStmt = $pdo->prepare("
        SELECT
            oc.operational_class_id,
            oc.school_year,
            oc.semester,
            oc.status,
            active_sy.school_year AS active_school_year,
            sub.subject_code,
            sub.subject_name,
            sec.section_name
        FROM operational_classes AS oc
        INNER JOIN school_years AS active_sy
            ON active_sy.school_year = oc.school_year
           AND active_sy.status = 'Active'
        INNER JOIN subjects AS sub
            ON sub.subject_id = oc.subject_id
        INNER JOIN sections AS sec
            ON sec.section_id = oc.section_id
        WHERE oc.operational_class_id = ?
          AND oc.teacher_id = ?
          AND oc.status = 'Active'
        LIMIT 1
    ");

    $classStmt->execute([
        $operationalClassId,
        (int) $_SESSION['user_id'],
    ]);

    $classContext = $classStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($classContext === null) {
        classListReadResponse(
            false,
            'The selected operational class is unavailable or you do not have permission to manage it.',
            403
        );
    }

    $scheduleStmt = $pdo->prepare("
        SELECT
            class_schedule_id,
            day,
            start_time,
            end_time,
            room
        FROM class_schedules
        WHERE operational_class_id = ?
          AND status = 'Active'
        ORDER BY
            FIELD(
                day,
                'Monday',
                'Tuesday',
                'Wednesday',
                'Thursday',
                'Friday',
                'Saturday'
            ),
            start_time,
            class_schedule_id
    ");

    $scheduleStmt->execute([
        $operationalClassId,
    ]);

    $classContext['schedules'] = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

    $listStmt = $pdo->prepare("
        SELECT
            sce.student_class_enrollment_id,
            sce.status AS class_status,
            sce.enrolled_at,
            sce.ended_at,
            st.student_id,
            st.student_number,
            st.first_name,
            st.middle_name,
            st.last_name,
            st.suffix,
            st.status AS student_status,
            sae.student_academic_enrollment_id,
            sae.academic_level,
            sae.semester AS academic_semester,
            sae.year_level,
            sae.status AS academic_enrollment_status,
            sy.school_year AS academic_school_year,
            p.program_code,
            p.program_name,
            academic_section.section_name AS academic_section
        FROM student_class_enrollments AS sce
        INNER JOIN students AS st
            ON st.student_id = sce.student_id
        INNER JOIN student_academic_enrollments AS sae
            ON sae.student_academic_enrollment_id = sce.enrollment_id
           AND sae.student_id = sce.student_id
        INNER JOIN school_years AS sy
            ON sy.school_year_id = sae.school_year_id
        LEFT JOIN programs AS p
            ON p.program_id = sae.program_id
        LEFT JOIN sections AS academic_section
            ON academic_section.section_id = sae.section_id
        WHERE sce.operational_class_id = ?
        ORDER BY
            st.last_name,
            st.first_name,
            st.middle_name,
            st.student_number
    ");

    $listStmt->execute([
        $operationalClassId,
    ]);

    $students = [];

    foreach ($listStmt->fetchAll(PDO::FETCH_ASSOC) as $student) {
        $displayName = trim(
            (string) $student['last_name'] .
            ', ' .
            (string) $student['first_name'] .
            ' ' .
            (string) ($student['middle_name'] ?? '') .
            ' ' .
            (string) ($student['suffix'] ?? '')
        );

        $displayName = preg_replace('/\s+/', ' ', $displayName) ?? $displayName;

        $student['display_name'] = $displayName;
        $students[] = $student;
    }

    classListReadResponse(
        true,
        'Class List loaded successfully.',
        200,
        [
            'class' => $classContext,
            'students' => $students,
            'count' => count($students),
        ]
    );
} catch (PDOException $e) {
    error_log(
        '[APRISM Get Class List] ' .
        $e->getMessage()
    );

    classListReadResponse(
        false,
        'The Class List could not be loaded. Please try again.',
        500
    );
}