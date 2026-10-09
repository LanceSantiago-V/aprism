<?php

declare(strict_types=1);

require_once __DIR__ . '/institutional_registry_helper.php';

/** @return array<string, string> */
function aprismAcademicHeadReportTypes(): array
{
    return [
        'placement' => 'Student Placement Summary',
        'sections' => 'Section Population Summary',
        'attendance' => 'Attendance Summary',
        'referrals' => 'Referral Summary',
    ];
}

/** @return array<string, mixed> */
function aprismAcademicHeadReportOptions(PDO $pdo): array
{
    return [
        'school_years' => $pdo->query(
            "SELECT school_year_id, school_year, status
             FROM school_years
             ORDER BY start_date DESC, school_year_id DESC"
        )->fetchAll(PDO::FETCH_ASSOC),
        'levels' => $pdo->query(
            "SELECT DISTINCT academic_level
             FROM (
                 SELECT academic_level FROM programs
                 UNION
                 SELECT academic_level FROM student_academic_enrollments
             ) levels
             WHERE academic_level IS NOT NULL AND academic_level <> ''
             ORDER BY academic_level"
        )->fetchAll(PDO::FETCH_COLUMN),
        'programs' => $pdo->query(
            "SELECT program_id, program_code, program_name, academic_level
             FROM programs
             ORDER BY academic_level, program_code, program_name"
        )->fetchAll(PDO::FETCH_ASSOC),
        'sections' => $pdo->query(
            "SELECT sec.section_id, sec.section_name, sec.program_id,
                    sec.year_level, p.program_code, p.program_name
             FROM sections sec
             LEFT JOIN programs p ON p.program_id = sec.program_id
             ORDER BY sec.section_name, sec.section_id"
        )->fetchAll(PDO::FETCH_ASSOC),
        'teachers' => $pdo->query(
            "SELECT u.user_id, u.employee_number,
                    CONCAT_WS(' ', u.first_name, u.last_name) AS teacher_name
             FROM users u
             INNER JOIN roles r ON r.role_id = u.role_id
                AND r.role_name = 'Teacher'
             WHERE u.account_status = 'Active'
             ORDER BY u.last_name, u.first_name, u.employee_number"
        )->fetchAll(PDO::FETCH_ASSOC),
        'contexts' => $pdo->query(
            "SELECT DISTINCT semester
             FROM (
                 SELECT semester FROM student_academic_enrollments
                 UNION
                 SELECT semester FROM operational_classes
                 UNION
                 SELECT semester_snapshot AS semester FROM teacher_referrals
             ) contexts
             WHERE semester IS NOT NULL AND semester <> ''
             ORDER BY semester"
        )->fetchAll(PDO::FETCH_COLUMN),
        'attendance_statuses' => $pdo->query(
            "SELECT DISTINCT status FROM attendance_sessions
             WHERE status IS NOT NULL AND status <> ''
             ORDER BY status"
        )->fetchAll(PDO::FETCH_COLUMN),
        'referral_statuses' => $pdo->query(
            "SELECT DISTINCT status FROM teacher_referrals
             WHERE status IS NOT NULL AND status <> ''
             ORDER BY status"
        )->fetchAll(PDO::FETCH_COLUMN),
    ];
}

/** @return array<string, mixed> */
function aprismAcademicHeadDashboardData(PDO $pdo, array $filters): array
{
    $schoolYearId = (int) ($filters['school_year_id'] ?? 0);
    $semester = trim((string) ($filters['semester'] ?? 'All'));
    $placementFilters = [
        'school_year_id' => $schoolYearId,
        'semester' => $semester,
        'status' => 'All',
    ];
    $selection = aprismRegistryPlacementSelection($placementFilters, 'sae', 'st');
    $currentSelection = aprismRegistryPlacementSelection($placementFilters, 'sae_current', 'st');
    $placementWhere = $selection['sql'];
    $placementParams = $selection['params'];

    $participationSelection = aprismRegistryCurrentParticipationSelection($placementFilters, 'st');
    $currentStudentsStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM students st
         WHERE st.status = 'Active'
           AND (
               EXISTS (
                   SELECT 1
                   FROM student_academic_enrollments sae_current
                   WHERE {$currentSelection['sql']}
               )
               OR {$participationSelection['sql']}
           )"
    );
    $currentStudentsStmt->execute(array_merge($currentSelection['params'], $participationSelection['params']));
    $activeStudents = (int) $currentStudentsStmt->fetchColumn();

    $placedStmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT st.student_id)
         FROM students st
         INNER JOIN student_academic_enrollments sae ON {$placementWhere}
         WHERE st.status = 'Active'
           AND NOT EXISTS (
               SELECT 1
               FROM student_academic_enrollments sae_duplicate
               WHERE sae_duplicate.student_id = st.student_id
                 AND sae_duplicate.school_year_id = sae.school_year_id
                 AND sae_duplicate.semester <=> sae.semester
                 AND sae_duplicate.student_academic_enrollment_id <> sae.student_academic_enrollment_id
                 AND sae_duplicate.status = 'Active'
                 AND sae_duplicate.effective_start <= CURRENT_DATE
                 AND (sae_duplicate.effective_end IS NULL OR sae_duplicate.effective_end >= CURRENT_DATE)
           )"
    );
    $placedStmt->execute($placementParams);
    $placedStudents = (int) $placedStmt->fetchColumn();

    $scopeParams = [];
    $scopeWhere = [];
    if ($schoolYearId > 0) {
        $scopeWhere[] = 'sy.school_year_id = ?';
        $scopeParams[] = $schoolYearId;
    }
    if ($semester !== '' && $semester !== 'All') {
        $scopeWhere[] = 'oc.semester = ?';
        $scopeParams[] = $semester;
    }
    $scopeSql = $scopeWhere === [] ? '1 = 1' : implode(' AND ', $scopeWhere);

    $classesStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM operational_classes oc
         LEFT JOIN school_years sy ON sy.school_year = oc.school_year
         WHERE oc.status = 'Active' AND {$scopeSql}"
    );
    $classesStmt->execute($scopeParams);

    $schedulesStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM class_schedules cs
         INNER JOIN operational_classes oc ON oc.operational_class_id = cs.operational_class_id
         LEFT JOIN school_years sy ON sy.school_year = oc.school_year
         WHERE cs.status = 'Active' AND oc.status = 'Active' AND {$scopeSql}"
    );
    $schedulesStmt->execute($scopeParams);

    $adviserWhere = [
        "aa.status = 'Active'",
        "sec.status = 'Active'",
        "p.status = 'Active'",
        "p.academic_level = 'Senior High School'",
        "tra.status = 'Active'",
        "tra.responsibility_name = 'Adviser'",
        "sy.status = 'Active'",
    ];
    $adviserParams = [];
    if ($schoolYearId > 0) {
        $adviserWhere[] = 'aa.school_year_id = ?';
        $adviserParams[] = $schoolYearId;
    }
    if ($semester !== '' && $semester !== 'All') {
        $adviserWhere[] = 'aa.teaching_context = ?';
        $adviserParams[] = $semester;
    }
    $adviserStmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT aa.adviser_assignment_id)
         FROM adviser_assignments aa
         INNER JOIN school_years sy ON sy.school_year_id = aa.school_year_id
         INNER JOIN sections sec ON sec.section_id = aa.section_id
         INNER JOIN programs p ON p.program_id = sec.program_id
         INNER JOIN teacher_responsibility_assignments tra
            ON tra.teacher_id = aa.teacher_id
           AND tra.school_year_id = aa.school_year_id
         WHERE " . implode(' AND ', $adviserWhere)
    );
    $adviserStmt->execute($adviserParams);

    $chartScope = $selection['sql'];
    $chartQuery = static function (PDO $pdo, string $select, string $group, array $params, string $scope) use ($placementParams): array {
        $stmt = $pdo->prepare(
            "SELECT {$select}, COUNT(DISTINCT st.student_id) AS student_count
             FROM students st
             INNER JOIN student_academic_enrollments sae ON {$scope}
             {$group}"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };

    $levelRows = $chartQuery(
        $pdo,
        'sae.academic_level AS label',
        'WHERE st.status = \'Active\' GROUP BY sae.academic_level ORDER BY sae.academic_level',
        $placementParams,
        $chartScope
    );
    $programRows = $chartQuery(
        $pdo,
        "COALESCE(NULLIF(p.program_code, ''), p.program_name) AS label",
        'INNER JOIN programs p ON p.program_id = sae.program_id WHERE st.status = \'Active\' GROUP BY p.program_id, p.program_code, p.program_name ORDER BY student_count DESC, label',
        $placementParams,
        $chartScope
    );
    $sectionRows = $chartQuery(
        $pdo,
        'COALESCE(sec.section_name, \'Unresolved\') AS label',
        'LEFT JOIN sections sec ON sec.section_id = sae.section_id WHERE st.status = \'Active\' GROUP BY sec.section_id, sec.section_name ORDER BY student_count DESC, label',
        $placementParams,
        $chartScope
    );

    $sectionFilters = [
        'search' => '',
        'academic_level' => 'All',
        'program_id' => 0,
        'year_level' => 'All',
        'semester' => $semester,
        'status' => 'Active',
        'school_year_id' => $schoolYearId,
    ];

    return [
        'metrics' => [
            'active_students' => $activeStudents,
            'placed_students' => $placedStudents,
            'unresolved_students' => max(0, $activeStudents - $placedStudents),
            'active_sections' => (int) $pdo->query("SELECT COUNT(*) FROM sections WHERE status = 'Active'")->fetchColumn(),
            'active_teachers' => (int) $pdo->query("SELECT COUNT(*) FROM users u INNER JOIN roles r ON r.role_id = u.role_id AND r.role_name = 'Teacher' WHERE u.account_status = 'Active'")->fetchColumn(),
            'active_classes' => (int) $classesStmt->fetchColumn(),
            'active_schedules' => (int) $schedulesStmt->fetchColumn(),
            'active_adviser_assignments' => (int) $adviserStmt->fetchColumn(),
        ],
        'charts' => [
            'levels' => $levelRows,
            'programs' => array_slice($programRows, 0, 8),
            'sections' => array_slice($sectionRows, 0, 8),
        ],
        'sections' => aprismRegistrySectionsPage($pdo, $sectionFilters, 8, 0),
    ];
}

/** @return array{where: string, params: array<int, mixed>} */
function aprismAcademicHeadAttendanceWhere(array $filters): array
{
    $where = ['1 = 1'];
    $params = [];
    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(s.subject_name LIKE ? OR s.subject_code LIKE ? OR sec.section_name LIKE ? OR CONCAT_WS(\' \' , u.first_name, u.last_name) LIKE ? OR cs.room LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }
    if ((int) ($filters['school_year_id'] ?? 0) > 0) {
        $where[] = 'sy.school_year_id = ?';
        $params[] = (int) $filters['school_year_id'];
    }
    if (($filters['semester'] ?? 'All') !== 'All') {
        $where[] = 'oc.semester = ?';
        $params[] = $filters['semester'];
    }
    if ((int) ($filters['teacher_id'] ?? 0) > 0) {
        $where[] = 'oc.teacher_id = ?';
        $params[] = (int) $filters['teacher_id'];
    }
    if ((int) ($filters['section_id'] ?? 0) > 0) {
        $where[] = 'oc.section_id = ?';
        $params[] = (int) $filters['section_id'];
    }
    if ((int) ($filters['operational_class_id'] ?? 0) > 0) {
        $where[] = 'oc.operational_class_id = ?';
        $params[] = (int) $filters['operational_class_id'];
    }
    if (($filters['status'] ?? 'All') !== 'All') {
        $where[] = 'ats.status = ?';
        $params[] = $filters['status'];
    }
    return ['where' => implode(' AND ', $where), 'params' => $params];
}

function aprismAcademicHeadAttendanceCount(PDO $pdo, array $filters): int
{
    $parts = aprismAcademicHeadAttendanceWhere($filters);
    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT oc.operational_class_id)
         FROM operational_classes oc
         INNER JOIN subjects s ON s.subject_id = oc.subject_id
         INNER JOIN sections sec ON sec.section_id = oc.section_id
         INNER JOIN users u ON u.user_id = oc.teacher_id
         INNER JOIN roles r ON r.role_id = u.role_id AND r.role_name = 'Teacher'
         LEFT JOIN school_years sy ON sy.school_year = oc.school_year
         LEFT JOIN class_schedules cs ON cs.operational_class_id = oc.operational_class_id
         LEFT JOIN attendance_sessions ats ON ats.operational_class_id = oc.operational_class_id
         WHERE {$parts['where']}"
    );
    $stmt->execute($parts['params']);
    return (int) $stmt->fetchColumn();
}

function aprismAcademicHeadAttendanceRows(PDO $pdo, array $filters, int $limit, int $offset): array
{
    $parts = aprismAcademicHeadAttendanceWhere($filters);
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $stmt = $pdo->prepare(
        "SELECT oc.operational_class_id, s.subject_code, s.subject_name,
                sec.section_name,
                CONCAT_WS(' ', u.first_name, u.last_name) AS teacher_name,
                u.employee_number, oc.school_year, oc.semester,
                COUNT(DISTINCT ats.attendance_session_id) AS session_count,
                COUNT(DISTINCT CASE WHEN sar.status = 'Present' THEN sar.student_attendance_record_id END) AS present_count,
                COUNT(DISTINCT CASE WHEN sar.status = 'Late' THEN sar.student_attendance_record_id END) AS late_count,
                COUNT(DISTINCT CASE WHEN sar.status = 'Absent' THEN sar.student_attendance_record_id END) AS absent_count
         FROM operational_classes oc
         INNER JOIN subjects s ON s.subject_id = oc.subject_id
         INNER JOIN sections sec ON sec.section_id = oc.section_id
         INNER JOIN users u ON u.user_id = oc.teacher_id
         INNER JOIN roles r ON r.role_id = u.role_id AND r.role_name = 'Teacher'
         LEFT JOIN school_years sy ON sy.school_year = oc.school_year
         LEFT JOIN class_schedules cs ON cs.operational_class_id = oc.operational_class_id
         LEFT JOIN attendance_sessions ats ON ats.operational_class_id = oc.operational_class_id
         LEFT JOIN student_attendance_records sar ON sar.attendance_session_id = ats.attendance_session_id
         WHERE {$parts['where']}
         GROUP BY oc.operational_class_id, s.subject_code, s.subject_name,
                  sec.section_name, u.first_name, u.last_name, u.employee_number,
                  oc.school_year, oc.semester
         ORDER BY oc.school_year DESC, s.subject_name, sec.section_name, teacher_name, oc.operational_class_id
         LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($parts['params']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Return classes with at least one saved Student attendance record in scope.
 *
 * @return array<int, array<string, mixed>>
 */
function aprismAcademicHeadAttendanceClassOptions(PDO $pdo, array $filters): array
{
    $optionFilters = $filters;
    unset($optionFilters['operational_class_id']);
    $parts = aprismAcademicHeadAttendanceWhere($optionFilters);
    $stmt = $pdo->prepare(
        "SELECT oc.operational_class_id, s.subject_code, s.subject_name,
                sec.section_name,
                CONCAT_WS(' ', u.first_name, u.last_name) AS teacher_name,
                COUNT(DISTINCT ats.attendance_session_id) AS recorded_session_count
         FROM operational_classes oc
         INNER JOIN subjects s ON s.subject_id = oc.subject_id
         INNER JOIN sections sec ON sec.section_id = oc.section_id
         INNER JOIN users u ON u.user_id = oc.teacher_id
         INNER JOIN roles r ON r.role_id = u.role_id AND r.role_name = 'Teacher'
         LEFT JOIN school_years sy ON sy.school_year = oc.school_year
         INNER JOIN attendance_sessions ats ON ats.operational_class_id = oc.operational_class_id
         LEFT JOIN class_schedules cs ON cs.class_schedule_id = ats.class_schedule_id
         INNER JOIN student_attendance_records sar ON sar.attendance_session_id = ats.attendance_session_id
         WHERE {$parts['where']}
         GROUP BY oc.operational_class_id, s.subject_code, s.subject_name,
                  sec.section_name, u.first_name, u.last_name
         ORDER BY s.subject_name, sec.section_name, teacher_name
         LIMIT 100"
    );
    $stmt->execute($parts['params']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Return the latest saved attendance sessions, with factual Student status counts.
 * Sessions without saved Student attendance rows are deliberately excluded.
 *
 * @return array<int, array<string, mixed>>
 */
function aprismAcademicHeadAttendanceSessionRows(PDO $pdo, array $filters, int $limit = 8): array
{
    $parts = aprismAcademicHeadAttendanceWhere($filters);
    $limit = max(1, min(12, $limit));
    $stmt = $pdo->prepare(
        "SELECT ats.attendance_session_id, ats.meeting_date,
                oc.operational_class_id, s.subject_code, s.subject_name,
                sec.section_name,
                CONCAT_WS(' ', u.first_name, u.last_name) AS teacher_name,
                COUNT(DISTINCT sar.student_attendance_record_id) AS record_count,
                COUNT(DISTINCT CASE WHEN sar.status = 'Present' THEN sar.student_attendance_record_id END) AS present_count,
                COUNT(DISTINCT CASE WHEN sar.status = 'Late' THEN sar.student_attendance_record_id END) AS late_count,
                COUNT(DISTINCT CASE WHEN sar.status = 'Absent' THEN sar.student_attendance_record_id END) AS absent_count
         FROM attendance_sessions ats
         INNER JOIN operational_classes oc ON oc.operational_class_id = ats.operational_class_id
         INNER JOIN subjects s ON s.subject_id = oc.subject_id
         INNER JOIN sections sec ON sec.section_id = oc.section_id
         INNER JOIN users u ON u.user_id = oc.teacher_id
         INNER JOIN roles r ON r.role_id = u.role_id AND r.role_name = 'Teacher'
         LEFT JOIN school_years sy ON sy.school_year = oc.school_year
         LEFT JOIN class_schedules cs ON cs.class_schedule_id = ats.class_schedule_id
         INNER JOIN student_attendance_records sar ON sar.attendance_session_id = ats.attendance_session_id
         WHERE {$parts['where']}
         GROUP BY ats.attendance_session_id, ats.meeting_date,
                  oc.operational_class_id, s.subject_code, s.subject_name,
                  sec.section_name, u.first_name, u.last_name
         HAVING COUNT(DISTINCT sar.student_attendance_record_id) > 0
         ORDER BY ats.meeting_date DESC, ats.attendance_session_id DESC
         LIMIT {$limit}"
    );
    $stmt->execute($parts['params']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_reverse($rows);
}

/** @return array{where: string, params: array<int, mixed>} */
function aprismAcademicHeadReferralWhere(array $filters): array
{
    $where = ['1 = 1'];
    $params = [];
    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = '(st.student_number LIKE ? OR CONCAT_WS(\' \', st.first_name, st.middle_name, st.last_name, st.suffix) LIKE ? OR tr.reason LIKE ? OR CONCAT_WS(\' \', u.first_name, u.last_name) LIKE ? OR sec.section_name LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }
    if ((int) ($filters['school_year_id'] ?? 0) > 0) {
        $where[] = 'sy.school_year_id = ?';
        $params[] = (int) $filters['school_year_id'];
    }
    if (($filters['semester'] ?? 'All') !== 'All') {
        $where[] = 'tr.semester_snapshot = ?';
        $params[] = $filters['semester'];
    }
    if ((int) ($filters['teacher_id'] ?? 0) > 0) {
        $where[] = 'tr.teacher_id = ?';
        $params[] = (int) $filters['teacher_id'];
    }
    if ((int) ($filters['section_id'] ?? 0) > 0) {
        $where[] = 'oc.section_id = ?';
        $params[] = (int) $filters['section_id'];
    }
    if (($filters['status'] ?? 'All') !== 'All') {
        $where[] = 'tr.status = ?';
        $params[] = $filters['status'];
    }
    return ['where' => implode(' AND ', $where), 'params' => $params];
}

function aprismAcademicHeadReferralCount(PDO $pdo, array $filters): int
{
    $parts = aprismAcademicHeadReferralWhere($filters);
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM teacher_referrals tr
         INNER JOIN students st ON st.student_id = tr.student_id
         LEFT JOIN users u ON u.user_id = tr.teacher_id
         LEFT JOIN operational_classes oc ON oc.operational_class_id = tr.operational_class_id
         LEFT JOIN sections sec ON sec.section_id = oc.section_id
         LEFT JOIN school_years sy ON sy.school_year = tr.school_year_snapshot
         WHERE {$parts['where']}"
    );
    $stmt->execute($parts['params']);
    return (int) $stmt->fetchColumn();
}

function aprismAcademicHeadReferralRows(PDO $pdo, array $filters, int $limit, int $offset): array
{
    $parts = aprismAcademicHeadReferralWhere($filters);
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $stmt = $pdo->prepare(
        "SELECT tr.teacher_referral_id, st.student_number,
                CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) AS student_name,
                CONCAT_WS(' ', u.first_name, u.last_name) AS teacher_name,
                u.employee_number, s.subject_name, sec.section_name,
                tr.school_year_snapshot, tr.semester_snapshot,
                tr.reason, tr.status, tr.created_at
         FROM teacher_referrals tr
         INNER JOIN students st ON st.student_id = tr.student_id
         LEFT JOIN users u ON u.user_id = tr.teacher_id
         LEFT JOIN operational_classes oc ON oc.operational_class_id = tr.operational_class_id
         LEFT JOIN subjects s ON s.subject_id = oc.subject_id
         LEFT JOIN sections sec ON sec.section_id = oc.section_id
         LEFT JOIN school_years sy ON sy.school_year = tr.school_year_snapshot
         WHERE {$parts['where']}
         ORDER BY tr.created_at DESC, tr.teacher_referral_id DESC
         LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($parts['params']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismAcademicHeadReportCount(PDO $pdo, string $type, array $filters): int
{
    return match ($type) {
        'placement' => aprismRegistryStudentsCount($pdo, $filters),
        'sections' => aprismRegistrySectionsCount($pdo, $filters),
        'attendance' => aprismAcademicHeadAttendanceCount($pdo, $filters),
        'referrals' => aprismAcademicHeadReferralCount($pdo, $filters),
        default => 0,
    };
}

function aprismAcademicHeadReportRows(PDO $pdo, string $type, array $filters, int $limit, int $offset): array
{
    return match ($type) {
        'placement' => aprismRegistryStudentsPage($pdo, $filters, $limit, $offset),
        'sections' => aprismRegistrySectionsPage($pdo, $filters, $limit, $offset),
        'attendance' => aprismAcademicHeadAttendanceRows($pdo, $filters, $limit, $offset),
        'referrals' => aprismAcademicHeadReferralRows($pdo, $filters, $limit, $offset),
        default => [],
    };
}

/** @return array<string, string> */
function aprismAcademicHeadReportColumns(string $type): array
{
    return match ($type) {
        'placement' => [
            'student_number' => 'Student Number',
            'student_name' => 'Student',
            'academic_level' => 'Academic Level',
            'program_name' => 'Program / Strand',
            'year_level' => 'Year / Grade',
            'section_name' => 'Section',
            'school_year' => 'School Year',
            'semester' => 'Context',
            'placement_status' => 'Placement Status',
        ],
        'sections' => [
            'section_name' => 'Section',
            'program_name' => 'Program / Strand',
            'academic_level' => 'Academic Level',
            'year_level' => 'Year / Grade',
            'active_student_count' => 'Active Students',
            'status' => 'Status',
        ],
        'attendance' => [
            'subject_name' => 'Subject',
            'section_name' => 'Section',
            'teacher_name' => 'Teacher',
            'school_year' => 'School Year',
            'semester' => 'Context',
            'session_count' => 'Sessions',
            'present_count' => 'Present',
            'late_count' => 'Late',
            'absent_count' => 'Absent',
        ],
        'referrals' => [
            'student_number' => 'Student Number',
            'student_name' => 'Student',
            'teacher_name' => 'Referring Teacher',
            'section_name' => 'Section',
            'reason' => 'Concern',
            'created_at' => 'Submitted',
            'status' => 'Status',
        ],
        default => [],
    };
}

function aprismAcademicHeadReportCell(array $row, string $key): string
{
    if ($key === 'program_name') {
        $code = trim((string) ($row['program_code'] ?? ''));
        $name = trim((string) ($row['program_name'] ?? ''));
        return trim($code . ($code !== '' && $name !== '' ? ' · ' : '') . $name);
    }
    if ($key === 'student_name') {
        return (string) ($row['student_name'] ?? '');
    }
    if ($key === 'teacher_name') {
        return (string) ($row['teacher_name'] ?? '');
    }
    if ($key === 'section_name' && isset($row['section_name'])) {
        return (string) $row['section_name'];
    }
    return (string) ($row[$key] ?? '');
}
