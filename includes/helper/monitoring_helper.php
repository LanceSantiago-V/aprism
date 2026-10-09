<?php

declare(strict_types=1);

/**
 * Shared monitoring calculations used by teaching-scope and Adviser views.
 *
 * These functions expose persisted signals only. They do not assign a
 * predictive risk tier or invent grade/referral thresholds.
 */

// No Technical Admin setting currently exists for late equivalence or FDA.
// These remain the approved APRISM baseline until a canonical setting and
// authoritative total-class-hours source are introduced.
const APRISM_MONITORING_LATE_EQUIVALENCE = 3;

function aprismMonitoringAttendanceMetrics(array $row): array
{
    $absentCount = max(0, (int) ($row['absent_count'] ?? 0));
    $lateCount = max(0, (int) ($row['late_count'] ?? 0));
    $totalClassMinutes = max(0.0, (float) ($row['total_class_minutes'] ?? 0));
    $absentClassMinutes = max(0.0, (float) ($row['absent_class_minutes'] ?? 0));
    $lateClassMinutes = max(0.0, (float) ($row['late_class_minutes'] ?? 0));
    $effectiveAbsentCount = $absentCount + intdiv($lateCount, APRISM_MONITORING_LATE_EQUIVALENCE);
    $effectiveAbsentMinutes = $absentClassMinutes + ($lateClassMinutes / APRISM_MONITORING_LATE_EQUIVALENCE);

    $concernReasons = [];
    if ($lateCount > 0) {
        $concernReasons[] = 'Tardiness concern';
    }
    if ($absentCount > 0) {
        $concernReasons[] = 'Attendance concern';
    }
    if (intdiv($lateCount, APRISM_MONITORING_LATE_EQUIVALENCE) > 0) {
        $concernReasons[] = 'Effective absence accumulation';
    }

    return [
        'effective_absent_count' => $effectiveAbsentCount,
        'total_class_minutes' => $totalClassMinutes,
        'absent_class_minutes' => $absentClassMinutes,
        'late_class_minutes' => $lateClassMinutes,
        'effective_absent_minutes' => $effectiveAbsentMinutes,
        'fda_percent' => null,
        'fda_threshold_reached' => false,
        'fda_status' => 'Unavailable',
        'fda_unavailable_reason' => 'Authoritative total class hours are not available.',
        'concern_reasons' => $concernReasons,
    ];
}

function aprismTeacherMonitoringStudents(
    PDO $pdo,
    int $teacherId,
    ?string $schoolYear
): array {
    if ($teacherId <= 0 || $schoolYear === null || trim($schoolYear) === '') {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT
            st.student_id,
            st.student_number,
            CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) AS student_name,
            GROUP_CONCAT(
                DISTINCT CONCAT(s.subject_name, ' · ', sec.section_name)
                ORDER BY s.subject_name, sec.section_name
                SEPARATOR ', '
            ) AS teaching_context,
            COUNT(DISTINCT oc.operational_class_id) AS class_count
        FROM operational_classes oc
        INNER JOIN subjects s ON s.subject_id = oc.subject_id
        INNER JOIN sections sec ON sec.section_id = oc.section_id
        INNER JOIN student_class_enrollments sce
            ON sce.operational_class_id = oc.operational_class_id
           AND sce.status = 'Active'
        INNER JOIN students st
            ON st.student_id = sce.student_id
           AND st.status = 'Active'
        WHERE oc.teacher_id = ?
          AND oc.school_year = ?
          AND oc.status = 'Active'
        GROUP BY
            st.student_id,
            st.student_number,
            st.first_name,
            st.middle_name,
            st.last_name,
            st.suffix
        ORDER BY st.last_name, st.first_name, st.student_number"
    );
    $stmt->execute([$teacherId, $schoolYear]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Returns the normalized, de-duplicated Student scope for a Teacher page.
 * The scope is always derived from Teacher-owned Operational Classes and
 * active Student Class Enrollments; Adviser placement is not consulted.
 *
 * @return array{0: string, 1: array<int, mixed>}
 */
function aprismTeacherStudentScopeSql(
    int $teacherId,
    ?string $schoolYear,
    string $search = ''
): array {
    $where = [
        'oc.teacher_id = ?',
        'oc.school_year = ?',
        "oc.status = 'Active'",
        "sce.status = 'Active'",
        "st.status = 'Active'",
    ];
    $params = [$teacherId, $schoolYear];

    if ($search !== '') {
        $where[] = "(
            st.student_number LIKE ?
            OR CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) LIKE ?
            OR s.subject_name LIKE ?
            OR sec.section_name LIKE ?
        )";
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }

    return [
        "FROM operational_classes oc
         INNER JOIN subjects s ON s.subject_id = oc.subject_id
         INNER JOIN sections sec ON sec.section_id = oc.section_id
         INNER JOIN student_class_enrollments sce
             ON sce.operational_class_id = oc.operational_class_id
         INNER JOIN students st ON st.student_id = sce.student_id
         WHERE " . implode(' AND ', $where),
        $params,
    ];
}

function aprismTeacherStudentsCount(
    PDO $pdo,
    int $teacherId,
    ?string $schoolYear,
    string $search = ''
): int {
    if ($teacherId <= 0 || $schoolYear === null || trim($schoolYear) === '') {
        return 0;
    }

    [$scopeSql, $params] = aprismTeacherStudentScopeSql(
        $teacherId,
        $schoolYear,
        trim($search)
    );
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT sce.student_id) {$scopeSql}");
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function aprismTeacherStudentsPage(
    PDO $pdo,
    int $teacherId,
    ?string $schoolYear,
    int $limit,
    int $offset,
    string $search = ''
): array {
    if ($teacherId <= 0 || $schoolYear === null || trim($schoolYear) === '') {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    [$scopeSql, $params] = aprismTeacherStudentScopeSql(
        $teacherId,
        $schoolYear,
        trim($search)
    );
    $stmt = $pdo->prepare(
        "SELECT
            st.student_id,
            st.student_number,
            CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) AS student_name,
            GROUP_CONCAT(
                DISTINCT CONCAT(s.subject_name, ' · ', sec.section_name)
                ORDER BY s.subject_name, sec.section_name
                SEPARATOR ', '
            ) AS teaching_context,
            GROUP_CONCAT(
                DISTINCT sec.section_name
                ORDER BY sec.section_name
                SEPARATOR ', '
            ) AS sections,
            COUNT(DISTINCT oc.operational_class_id) AS class_count
        {$scopeSql}
        GROUP BY
            st.student_id,
            st.student_number,
            st.first_name,
            st.middle_name,
            st.last_name,
            st.suffix
        ORDER BY st.last_name, st.first_name, st.student_number
        LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismTeacherMonitoringAttendance(
    PDO $pdo,
    int $teacherId,
    ?string $schoolYear
): array {
    if ($teacherId <= 0 || $schoolYear === null || trim($schoolYear) === '') {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT
            st.student_id,
            SUM(CASE WHEN sar.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN sar.status = 'Late' THEN 1 ELSE 0 END) AS late_count,
            SUM(CASE WHEN sar.status = 'Absent' THEN 1 ELSE 0 END) AS absent_count,
            COUNT(sar.student_attendance_record_id) AS recorded_count,
            COALESCE(SUM(GREATEST(0, TIMESTAMPDIFF(MINUTE, ats.scheduled_start_time, ats.scheduled_end_time))), 0) AS total_class_minutes,
            COALESCE(SUM(CASE WHEN sar.status = 'Absent' THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, ats.scheduled_start_time, ats.scheduled_end_time)) ELSE 0 END), 0) AS absent_class_minutes,
            COALESCE(SUM(CASE WHEN sar.status = 'Late' THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, ats.scheduled_start_time, ats.scheduled_end_time)) ELSE 0 END), 0) AS late_class_minutes
        FROM operational_classes oc
        INNER JOIN student_class_enrollments sce
            ON sce.operational_class_id = oc.operational_class_id
           AND sce.status = 'Active'
        INNER JOIN students st
            ON st.student_id = sce.student_id
           AND st.status = 'Active'
        INNER JOIN attendance_sessions ats
            ON ats.operational_class_id = oc.operational_class_id
        LEFT JOIN student_attendance_records sar
            ON sar.attendance_session_id = ats.attendance_session_id
           AND sar.student_class_enrollment_id = sce.student_class_enrollment_id
        WHERE oc.teacher_id = ?
          AND oc.school_year = ?
          AND oc.status = 'Active'
        GROUP BY st.student_id"
    );
    $stmt->execute([$teacherId, $schoolYear]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row = array_merge($row, aprismMonitoringAttendanceMetrics($row));
        $rows[(int) $row['student_id']] = $row;
    }

    return $rows;
}

function aprismTeacherMonitoringReferralCounts(
    PDO $pdo,
    int $teacherId,
    ?string $schoolYear
): array {
    if ($teacherId <= 0 || $schoolYear === null || trim($schoolYear) === '') {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT tr.student_id, COUNT(*) AS referral_count
        FROM teacher_referrals tr
        INNER JOIN operational_classes oc
            ON oc.operational_class_id = tr.operational_class_id
           AND oc.teacher_id = tr.teacher_id
           AND oc.school_year = ?
           AND oc.status = 'Active'
        WHERE tr.teacher_id = ?
        GROUP BY tr.student_id"
    );
    $stmt->execute([$schoolYear, $teacherId]);

    $counts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[(int) $row['student_id']] = (int) $row['referral_count'];
    }

    return $counts;
}

function aprismTeacherMonitoringGradeCounts(
    PDO $pdo,
    int $teacherId,
    ?string $schoolYear
): array {
    if ($teacherId <= 0 || $schoolYear === null || trim($schoolYear) === '') {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT sce.student_id, COUNT(DISTINCT sas.student_assessment_score_id) AS score_count
        FROM operational_classes oc
        INNER JOIN grade_import_batches gib
            ON gib.operational_class_id = oc.operational_class_id
           AND gib.teacher_id = oc.teacher_id
           AND gib.status = 'Confirmed'
        INNER JOIN assessment_categories ac
            ON ac.operational_class_id = oc.operational_class_id
           AND ac.status = 'Active'
        INNER JOIN assessment_components cmp
            ON cmp.assessment_category_id = ac.assessment_category_id
           AND cmp.status = 'Active'
        INNER JOIN student_assessment_scores sas
            ON sas.assessment_component_id = cmp.assessment_component_id
           AND sas.originating_grade_import_batch_id = gib.grade_import_batch_id
        INNER JOIN student_class_enrollments sce
            ON sce.student_class_enrollment_id = sas.student_class_enrollment_id
           AND sce.operational_class_id = oc.operational_class_id
           AND sce.status = 'Active'
        WHERE oc.teacher_id = ?
          AND oc.school_year = ?
          AND oc.status = 'Active'
        GROUP BY sce.student_id"
    );
    $stmt->execute([$teacherId, $schoolYear]);

    $counts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[(int) $row['student_id']] = (int) $row['score_count'];
    }

    return $counts;
}

function aprismAdviserMonitoringGradeCounts(
    PDO $pdo,
    int $teacherId,
    ?int $schoolYearId
): array {
    if ($teacherId <= 0 || $schoolYearId === null || $schoolYearId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT sce.student_id, COUNT(DISTINCT sas.student_assessment_score_id) AS score_count
        FROM adviser_assignments aa
        INNER JOIN school_years sy
            ON sy.school_year_id = aa.school_year_id
           AND sy.status = 'Active'
        INNER JOIN sections sec
            ON sec.section_id = aa.section_id
           AND sec.status = 'Active'
        INNER JOIN programs p
            ON p.program_id = sec.program_id
           AND p.status = 'Active'
           AND p.academic_level = 'Senior High School'
        INNER JOIN teacher_responsibility_assignments tra
            ON tra.teacher_id = aa.teacher_id
           AND tra.responsibility_name = 'Adviser'
           AND tra.school_year_id = aa.school_year_id
           AND tra.status = 'Active'
        INNER JOIN student_academic_enrollments sae
            ON sae.section_id = aa.section_id
           AND sae.school_year_id = aa.school_year_id
           AND sae.status = 'Active'
           AND sae.effective_end IS NULL
        INNER JOIN student_class_enrollments sce
            ON sce.student_id = sae.student_id
           AND sce.status = 'Active'
        INNER JOIN operational_classes oc
            ON oc.operational_class_id = sce.operational_class_id
           AND oc.school_year = sy.school_year
           AND oc.status = 'Active'
        INNER JOIN grade_import_batches gib
            ON gib.operational_class_id = oc.operational_class_id
           AND gib.status = 'Confirmed'
        INNER JOIN assessment_categories ac
            ON ac.operational_class_id = oc.operational_class_id
           AND ac.status = 'Active'
        INNER JOIN assessment_components cmp
            ON cmp.assessment_category_id = ac.assessment_category_id
           AND cmp.status = 'Active'
        INNER JOIN student_assessment_scores sas
            ON sas.assessment_component_id = cmp.assessment_component_id
           AND sas.originating_grade_import_batch_id = gib.grade_import_batch_id
           AND sas.student_class_enrollment_id = sce.student_class_enrollment_id
        WHERE aa.teacher_id = ?
          AND aa.school_year_id = ?
          AND aa.status = 'Active'
          AND aa.teaching_context IN ('First Semester', 'Second Semester')
        GROUP BY sce.student_id"
    );
    $stmt->execute([$teacherId, $schoolYearId]);

    $counts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[(int) $row['student_id']] = (int) $row['score_count'];
    }

    return $counts;
}
