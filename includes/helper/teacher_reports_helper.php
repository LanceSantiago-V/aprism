<?php

declare(strict_types=1);

function aprismTeacherReportClasses(PDO $pdo, int $teacherId): array
{
    return aprismGradeTeacherClasses($pdo, $teacherId);
}

function aprismTeacherReportStudents(PDO $pdo, int $teacherId, int $classId): array
{
    $stmt = $pdo->prepare(
        "SELECT DISTINCT st.student_id, st.student_number,
            CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) AS student_name
        FROM student_class_enrollments sce
        INNER JOIN operational_classes oc
            ON oc.operational_class_id = sce.operational_class_id
           AND oc.teacher_id = ?
        INNER JOIN students st
            ON st.student_id = sce.student_id
           AND st.status = 'Active'
        WHERE sce.operational_class_id = ?
          AND sce.status = 'Active'
        ORDER BY st.last_name, st.first_name, st.student_number"
    );
    $stmt->execute([$teacherId, $classId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismTeacherAttendanceReport(
    PDO $pdo,
    int $teacherId,
    ?int $classId = null,
    ?int $studentId = null,
    ?string $dateFrom = null,
    ?string $dateTo = null
): array
{
    $sql = "SELECT oc.operational_class_id, s.subject_name, sec.section_name,
        COUNT(DISTINCT ats.attendance_session_id) AS session_count,
        COALESCE(SUM(sar.status = 'Present'), 0) AS present_count,
        COALESCE(SUM(sar.status = 'Late'), 0) AS late_count,
        COALESCE(SUM(sar.status = 'Absent'), 0) AS absent_count
        FROM attendance_sessions ats
        INNER JOIN operational_classes oc
            ON oc.operational_class_id = ats.operational_class_id
           AND oc.teacher_id = ?
        INNER JOIN subjects s ON s.subject_id = oc.subject_id
        INNER JOIN sections sec ON sec.section_id = oc.section_id
        LEFT JOIN student_attendance_records sar
            ON sar.attendance_session_id = ats.attendance_session_id
        WHERE 1=1";
    $params = [$teacherId];
    if ($classId !== null) { $sql .= " AND oc.operational_class_id = ?"; $params[] = $classId; }
    if ($studentId !== null) {
        $sql .= " AND sar.student_class_enrollment_id IN (
            SELECT sce_filter.student_class_enrollment_id
            FROM student_class_enrollments sce_filter
            WHERE sce_filter.student_id = ?
              AND sce_filter.operational_class_id = oc.operational_class_id
        )";
        $params[] = $studentId;
    }
    if ($dateFrom !== null) { $sql .= " AND ats.meeting_date >= ?"; $params[] = $dateFrom; }
    if ($dateTo !== null) { $sql .= " AND ats.meeting_date <= ?"; $params[] = $dateTo; }
    $sql .= " GROUP BY oc.operational_class_id, s.subject_name, sec.section_name ORDER BY s.subject_name, sec.section_name";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismTeacherAttendanceReportRows(
    PDO $pdo,
    int $teacherId,
    int $classId,
    ?int $studentId = null,
    ?string $dateFrom = null,
    ?string $dateTo = null
): array
{
    $sql = "SELECT st.student_id, st.student_number,
        CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) AS student_name,
        COALESCE(SUM(sar.status = 'Present'), 0) AS present_count,
        COALESCE(SUM(sar.status = 'Late'), 0) AS late_count,
        COALESCE(SUM(sar.status = 'Absent'), 0) AS absent_count,
        COUNT(sar.student_attendance_record_id) AS recorded_count
        FROM operational_classes oc
        INNER JOIN student_class_enrollments sce
            ON sce.operational_class_id = oc.operational_class_id
           AND sce.status = 'Active'
        INNER JOIN students st
            ON st.student_id = sce.student_id
           AND st.status = 'Active'
        LEFT JOIN attendance_sessions ats
            ON ats.operational_class_id = oc.operational_class_id
        LEFT JOIN student_attendance_records sar
            ON sar.attendance_session_id = ats.attendance_session_id
           AND sar.student_class_enrollment_id = sce.student_class_enrollment_id
        WHERE oc.operational_class_id = ?
          AND oc.teacher_id = ?";
    $params = [$classId, $teacherId];
    if ($studentId !== null) { $sql .= " AND st.student_id = ?"; $params[] = $studentId; }
    if ($dateFrom !== null) { $sql .= " AND (ats.meeting_date IS NULL OR ats.meeting_date >= ?)"; $params[] = $dateFrom; }
    if ($dateTo !== null) { $sql .= " AND (ats.meeting_date IS NULL OR ats.meeting_date <= ?)"; $params[] = $dateTo; }
    $sql .= " GROUP BY st.student_id, st.student_number, st.first_name, st.middle_name, st.last_name, st.suffix
        ORDER BY st.last_name, st.first_name, st.student_number";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismTeacherGradeReport(PDO $pdo, int $teacherId, ?int $classId = null): array
{
    $sql = "SELECT oc.operational_class_id, ap.period_name, ap.academic_level, ap.semester, COUNT(DISTINCT sas.student_assessment_score_id) AS score_count, COUNT(DISTINCT sce.student_class_enrollment_id) AS participant_count FROM operational_classes oc INNER JOIN assessment_categories ac ON ac.operational_class_id = oc.operational_class_id INNER JOIN academic_periods ap ON ap.academic_period_id = ac.academic_period_id INNER JOIN assessment_components cmp ON cmp.assessment_category_id = ac.assessment_category_id INNER JOIN student_assessment_scores sas ON sas.assessment_component_id = cmp.assessment_component_id INNER JOIN student_class_enrollments sce ON sce.student_class_enrollment_id = sas.student_class_enrollment_id WHERE oc.teacher_id = ?";
    $params = [$teacherId];
    if ($classId !== null) { $sql .= " AND oc.operational_class_id = ?"; $params[] = $classId; }
    $sql .= " GROUP BY oc.operational_class_id, ap.academic_period_id, ap.period_name, ap.academic_level, ap.semester, ap.start_date ORDER BY ap.start_date, ap.academic_period_id";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismTeacherConfirmedGradeReport(
    PDO $pdo,
    int $teacherId,
    int $classId,
    ?int $periodId = null,
    ?int $studentId = null
): array
{
    $sql = "SELECT ap.academic_period_id, ap.period_name, ap.academic_level, ap.semester,
        cmp.component_code, cmp.component_name, cmp.max_score,
        st.student_id, st.student_number,
        CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) AS student_name,
        sas.raw_score
        FROM grade_import_batches gib
        INNER JOIN operational_classes oc
            ON oc.operational_class_id = gib.operational_class_id
           AND oc.teacher_id = gib.teacher_id
        INNER JOIN school_years sy ON sy.school_year = oc.school_year
        INNER JOIN academic_periods ap
            ON ap.school_year_id = sy.school_year_id
           AND ap.semester = oc.semester
        INNER JOIN assessment_categories ac
            ON ac.operational_class_id = gib.operational_class_id
           AND ac.academic_period_id = ap.academic_period_id
           AND ac.status = 'Active'
        INNER JOIN assessment_components cmp
            ON cmp.assessment_category_id = ac.assessment_category_id
           AND cmp.status = 'Active'
        INNER JOIN student_assessment_scores sas
            ON sas.assessment_component_id = cmp.assessment_component_id
           AND sas.originating_grade_import_batch_id = gib.grade_import_batch_id
        INNER JOIN student_class_enrollments sce
            ON sce.student_class_enrollment_id = sas.student_class_enrollment_id
           AND sce.operational_class_id = gib.operational_class_id
        INNER JOIN students st ON st.student_id = sce.student_id
        WHERE gib.teacher_id = ?
          AND gib.operational_class_id = ?
          AND gib.status = 'Confirmed'
          AND gib.school_year_snapshot = oc.school_year
          AND gib.semester_snapshot = oc.semester";
    $params = [$teacherId, $classId];
    if ($periodId !== null) { $sql .= " AND ap.academic_period_id = ?"; $params[] = $periodId; }
    if ($studentId !== null) { $sql .= " AND st.student_id = ?"; $params[] = $studentId; }
    $sql .= " ORDER BY ap.start_date, ap.academic_period_id, st.last_name, st.first_name, st.student_number, cmp.display_order, cmp.assessment_component_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
