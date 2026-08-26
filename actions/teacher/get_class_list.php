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
    classListReadResponse(false, 'Invalid Class List request.', 405);
}

$operationalClassId = filter_var(
    $_GET['operational_class_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($operationalClassId === false) {
    classListReadResponse(false, 'A valid Operational Class is required.', 422);
}

$operationalClassId = (int) $operationalClassId;

try {
    $classStmt = $pdo->prepare("\n        SELECT\n            oc.operational_class_id,\n            oc.school_year,\n            oc.semester,\n            oc.status,\n            active_sy.school_year AS active_school_year,\n            sub.subject_code,\n            sub.subject_name,\n            sec.section_name\n        FROM operational_classes AS oc\n        INNER JOIN school_years AS active_sy\n            ON active_sy.school_year = oc.school_year\n           AND active_sy.status = 'Active'\n        INNER JOIN subjects AS sub\n            ON sub.subject_id = oc.subject_id\n        INNER JOIN sections AS sec\n            ON sec.section_id = oc.section_id\n        WHERE oc.operational_class_id = ?\n          AND oc.teacher_id = ?\n          AND oc.status = 'Active'\n        LIMIT 1\n    ");

    $classStmt->execute([$operationalClassId, (int) $_SESSION['user_id']]);
    $classContext = $classStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($classContext === null) {
        classListReadResponse(
            false,
            'The selected operational class is unavailable or you do not have permission to manage it.',
            403
        );
    }

    $scheduleStmt = $pdo->prepare("\n        SELECT class_schedule_id, day, start_time, end_time, room\n        FROM class_schedules\n        WHERE operational_class_id = ?\n          AND status = 'Active'\n        ORDER BY\n            FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),\n            start_time,\n            class_schedule_id\n    ");
    $scheduleStmt->execute([$operationalClassId]);
    $classContext['schedules'] = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

    $listStmt = $pdo->prepare("\n        SELECT\n            sce.student_class_enrollment_id,\n            sce.status AS class_status,\n            sce.enrolled_at,\n            sce.ended_at,\n            st.student_id,\n            st.student_number,\n            st.first_name,\n            st.middle_name,\n            st.last_name,\n            st.suffix,\n            st.status AS student_status,\n            sae.student_academic_enrollment_id,\n            sae.academic_level,\n            sae.semester AS academic_semester,\n            sae.year_level,\n            sae.status AS academic_enrollment_status,\n            sy.school_year AS academic_school_year,\n            p.program_code,\n            p.program_name,\n            academic_section.section_name AS academic_section\n        FROM student_class_enrollments AS sce\n        INNER JOIN students AS st\n            ON st.student_id = sce.student_id\n        LEFT JOIN student_academic_enrollments AS sae\n            ON sae.student_academic_enrollment_id = sce.enrollment_id\n           AND sae.student_id = sce.student_id\n        LEFT JOIN school_years AS sy\n            ON sy.school_year_id = sae.school_year_id\n        LEFT JOIN programs AS p\n            ON p.program_id = sae.program_id\n        LEFT JOIN sections AS academic_section\n            ON academic_section.section_id = sae.section_id\n        WHERE sce.operational_class_id = ?\n        ORDER BY st.last_name, st.first_name, st.middle_name, st.student_number\n    ");
    $listStmt->execute([$operationalClassId]);

    $students = [];

    foreach ($listStmt->fetchAll(PDO::FETCH_ASSOC) as $student) {
        $displayName = trim(
            (string) $student['last_name'] . ', ' .
            (string) $student['first_name'] . ' ' .
            (string) ($student['middle_name'] ?? '') . ' ' .
            (string) ($student['suffix'] ?? '')
        );

        $student['display_name'] = preg_replace('/\\s+/', ' ', $displayName) ?? $displayName;
        $student['academic_context_status'] = $student['student_academic_enrollment_id'] === null
            ? 'Academic placement not yet recorded'
            : 'Academic placement recorded';
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
    error_log('[APRISM Get Class List] ' . $e->getMessage());
    classListReadResponse(false, 'The Class List could not be loaded. Please try again.', 500);
}
