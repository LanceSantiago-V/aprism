<?php

declare(strict_types=1);

require_once __DIR__ . '/monitoring_helper.php';

/**
 * Broader SHS teaching contexts used by Adviser assignments.
 *
 * Academic Periods are finer-grained grading contexts and are deliberately
 * not used as Adviser assignment scope.
 */
function aprismAdviserTeachingContexts(): array
{
    return [
        'First Semester',
        'Second Semester',
    ];
}

function aprismAdviserTeachingContextIsValid(?string $context): bool
{
    return $context !== null
        && in_array($context, aprismAdviserTeachingContexts(), true);
}

function aprismAdviserAssignments(PDO $pdo, int $teacherId, ?int $schoolYearId = null): array
{
    $sql = "SELECT aa.adviser_assignment_id, aa.teacher_id, aa.section_id, aa.school_year_id, aa.teaching_context, aa.status, sy.school_year, sec.section_name, sec.year_level, p.program_name
        FROM adviser_assignments aa
        INNER JOIN sections sec ON sec.section_id = aa.section_id
        INNER JOIN school_years sy ON sy.school_year_id = aa.school_year_id
        INNER JOIN programs p ON p.program_id = sec.program_id
            AND p.status = 'Active'
            AND p.academic_level = 'Senior High School'
        INNER JOIN teacher_responsibility_assignments tra
            ON tra.teacher_id = aa.teacher_id
           AND tra.responsibility_name = 'Adviser'
           AND tra.school_year_id = aa.school_year_id
           AND tra.status = 'Active'
        WHERE aa.teacher_id = ?
          AND aa.status = 'Active'
          AND sy.status = 'Active'
          AND sec.status = 'Active'
          AND aa.teaching_context IN ('First Semester', 'Second Semester')";
    $params = [$teacherId];
    if ($schoolYearId !== null) { $sql .= " AND aa.school_year_id = ?"; $params[] = $schoolYearId; }
    $sql .= " ORDER BY sy.start_date DESC, sec.section_name";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismAdviserHasAssignment(PDO $pdo, int $teacherId, int $sectionId, int $schoolYearId, ?string $teachingContext = null): bool
{
    $sql = "SELECT 1
        FROM adviser_assignments aa
        INNER JOIN sections sec
            ON sec.section_id = aa.section_id
           AND sec.status = 'Active'
        INNER JOIN programs p
            ON p.program_id = sec.program_id
           AND p.status = 'Active'
           AND p.academic_level = 'Senior High School'
        INNER JOIN school_years sy
            ON sy.school_year_id = aa.school_year_id
           AND sy.status = 'Active'
        INNER JOIN teacher_responsibility_assignments tra
            ON tra.teacher_id = aa.teacher_id
           AND tra.responsibility_name = 'Adviser'
           AND tra.school_year_id = aa.school_year_id
           AND tra.status = 'Active'
        WHERE aa.teacher_id = ?
          AND aa.section_id = ?
          AND aa.school_year_id = ?
          AND aa.status = 'Active'
          AND aa.teaching_context IN ('First Semester', 'Second Semester')";
    if ($teachingContext !== null) {
        $sql .= ' AND aa.teaching_context = ?';
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $params = [$teacherId, $sectionId, $schoolYearId];
    if ($teachingContext !== null) { $params[] = $teachingContext; }
    $stmt->execute($params);
    return (bool) $stmt->fetchColumn();
}

function aprismAdviserStudents(PDO $pdo, int $teacherId, ?int $schoolYearId = null): array
{
    $sql = "SELECT DISTINCT st.student_id, st.student_number,
        CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) student_name,
        aa.section_id, sec.section_name, sy.school_year, sae.academic_level, sae.year_level, aa.school_year_id
        FROM adviser_assignments aa
        INNER JOIN sections sec ON sec.section_id = aa.section_id
        INNER JOIN school_years sy ON sy.school_year_id = aa.school_year_id
        INNER JOIN programs p ON p.program_id = sec.program_id
            AND p.status = 'Active'
            AND p.academic_level = 'Senior High School'
        INNER JOIN teacher_responsibility_assignments tra
            ON tra.teacher_id = aa.teacher_id
           AND tra.responsibility_name = 'Adviser'
           AND tra.school_year_id = aa.school_year_id
           AND tra.status = 'Active'
        INNER JOIN student_academic_enrollments sae ON sae.section_id = aa.section_id AND sae.school_year_id = aa.school_year_id AND sae.semester = aa.teaching_context AND sae.status = 'Active' AND sae.effective_end IS NULL
        INNER JOIN students st ON st.student_id = sae.student_id AND st.status = 'Active'
        WHERE aa.teacher_id = ?
          AND aa.status = 'Active'
          AND sy.status = 'Active'
          AND sec.status = 'Active'
          AND aa.teaching_context IN ('First Semester', 'Second Semester')";
    $params = [$teacherId];
    if ($schoolYearId !== null) { $sql .= " AND aa.school_year_id = ?"; $params[] = $schoolYearId; }
    $sql .= " ORDER BY st.last_name, st.first_name, st.student_number";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismAdviserStudentsCount(
    PDO $pdo,
    int $teacherId,
    ?int $schoolYearId = null,
    string $search = ''
): int {
    if ($teacherId <= 0) {
        return 0;
    }

    $sql = "SELECT COUNT(DISTINCT st.student_id)
        FROM adviser_assignments aa
        INNER JOIN sections sec ON sec.section_id = aa.section_id
        INNER JOIN school_years sy ON sy.school_year_id = aa.school_year_id
        INNER JOIN programs p ON p.program_id = sec.program_id
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
           AND sae.semester = aa.teaching_context
           AND sae.status = 'Active'
           AND sae.effective_end IS NULL
        INNER JOIN students st
            ON st.student_id = sae.student_id
           AND st.status = 'Active'
        WHERE aa.teacher_id = ?
          AND aa.status = 'Active'
          AND sy.status = 'Active'
          AND sec.status = 'Active'
          AND aa.teaching_context IN ('First Semester', 'Second Semester')";
    $params = [$teacherId];

    if ($schoolYearId !== null) {
        $sql .= " AND aa.school_year_id = ?";
        $params[] = $schoolYearId;
    }

    $search = trim($search);
    if ($search !== '') {
        $sql .= " AND (
            st.student_number LIKE ?
            OR CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) LIKE ?
            OR sec.section_name LIKE ?
        )";
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function aprismAdviserStudentsPage(
    PDO $pdo,
    int $teacherId,
    ?int $schoolYearId,
    int $limit,
    int $offset,
    string $search = ''
): array {
    if ($teacherId <= 0) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $sql = "SELECT DISTINCT st.student_id, st.student_number,
        CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) student_name,
        aa.section_id, sec.section_name, sy.school_year, sae.academic_level, sae.year_level, aa.school_year_id
        FROM adviser_assignments aa
        INNER JOIN sections sec ON sec.section_id = aa.section_id
        INNER JOIN school_years sy ON sy.school_year_id = aa.school_year_id
        INNER JOIN programs p ON p.program_id = sec.program_id
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
           AND sae.semester = aa.teaching_context
           AND sae.status = 'Active'
           AND sae.effective_end IS NULL
        INNER JOIN students st
            ON st.student_id = sae.student_id
           AND st.status = 'Active'
        WHERE aa.teacher_id = ?
          AND aa.status = 'Active'
          AND sy.status = 'Active'
          AND sec.status = 'Active'
          AND aa.teaching_context IN ('First Semester', 'Second Semester')";
    $params = [$teacherId];

    if ($schoolYearId !== null) {
        $sql .= " AND aa.school_year_id = ?";
        $params[] = $schoolYearId;
    }

    $search = trim($search);
    if ($search !== '') {
        $sql .= " AND (
            st.student_number LIKE ?
            OR CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) LIKE ?
            OR sec.section_name LIKE ?
        )";
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }

    $sql .= " ORDER BY st.last_name, st.first_name, st.student_number
        LIMIT {$limit} OFFSET {$offset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismAdviserStudent(PDO $pdo, int $teacherId, int $studentId, ?int $schoolYearId = null): ?array
{
    if ($teacherId <= 0 || $studentId <= 0) {
        return null;
    }

    $sql = "SELECT DISTINCT st.student_id, st.student_number,
        CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) AS student_name,
        aa.section_id, sec.section_name, sy.school_year,
        sae.academic_level, sae.year_level, aa.school_year_id
        FROM adviser_assignments aa
        INNER JOIN sections sec ON sec.section_id = aa.section_id
        INNER JOIN school_years sy ON sy.school_year_id = aa.school_year_id
        INNER JOIN programs p ON p.program_id = sec.program_id
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
           AND sae.semester = aa.teaching_context
           AND sae.status = 'Active'
           AND sae.effective_end IS NULL
        INNER JOIN students st
            ON st.student_id = sae.student_id
           AND st.status = 'Active'
        WHERE aa.teacher_id = ?
          AND aa.status = 'Active'
          AND sy.status = 'Active'
          AND sec.status = 'Active'
          AND aa.teaching_context IN ('First Semester', 'Second Semester')
          AND st.student_id = ?";
    $params = [$teacherId, $studentId];

    if ($schoolYearId !== null) {
        $sql .= " AND aa.school_year_id = ?";
        $params[] = $schoolYearId;
    }

    $sql .= " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student === false) {
        http_response_code(404);
        exit('Student is outside your current advisory scope.');
    }

    return $student;
}

function aprismAdviserReferralFollowups(PDO $pdo, int $teacherId, ?int $schoolYearId = null): array
{
    $sql = "SELECT DISTINCT tr.teacher_referral_id, tr.reason, tr.status, tr.created_at,
        st.student_id, st.student_number, CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) student_name,
        s.subject_name, advisory_sec.section_name, tr.school_year_snapshot, tr.semester_snapshot
        FROM teacher_referrals tr
        INNER JOIN students st ON st.student_id = tr.student_id
        INNER JOIN adviser_assignments aa ON aa.teacher_id = ? AND aa.status = 'Active'
        INNER JOIN sections advisory_sec ON advisory_sec.section_id = aa.section_id AND advisory_sec.status = 'Active'
        INNER JOIN programs p ON p.program_id = advisory_sec.program_id
            AND p.status = 'Active'
            AND p.academic_level = 'Senior High School'
        INNER JOIN school_years sy ON sy.school_year_id = aa.school_year_id AND sy.status = 'Active'
        INNER JOIN teacher_responsibility_assignments tra
            ON tra.teacher_id = aa.teacher_id
           AND tra.responsibility_name = 'Adviser'
           AND tra.school_year_id = aa.school_year_id
           AND tra.status = 'Active'
        INNER JOIN student_academic_enrollments sae ON sae.student_id = tr.student_id AND sae.section_id = aa.section_id AND sae.school_year_id = aa.school_year_id AND sae.semester = aa.teaching_context AND sae.status = 'Active' AND sae.effective_end IS NULL
        LEFT JOIN operational_classes oc ON oc.operational_class_id = tr.operational_class_id AND oc.semester = aa.teaching_context
        LEFT JOIN subjects s ON s.subject_id = oc.subject_id
        LEFT JOIN sections class_sec ON class_sec.section_id = oc.section_id
        WHERE 1 = 1";
    $params = [$teacherId];
    if ($schoolYearId !== null) { $sql .= " AND aa.school_year_id = ?"; $params[] = $schoolYearId; }
    $sql .= " AND aa.teaching_context IN ('First Semester', 'Second Semester')
        AND tr.semester_snapshot = aa.teaching_context
        ORDER BY tr.created_at DESC, tr.teacher_referral_id DESC";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismAdviserAttendanceConcerns(PDO $pdo, int $teacherId, ?int $schoolYearId = null): array
{
    $sql = "SELECT st.student_id,
        COUNT(CASE WHEN sar.status = 'Absent' THEN 1 END) absent_count,
        COUNT(CASE WHEN sar.status = 'Late' THEN 1 END) late_count
        FROM adviser_assignments aa
        INNER JOIN sections sec ON sec.section_id = aa.section_id AND sec.status = 'Active'
        INNER JOIN programs p ON p.program_id = sec.program_id
            AND p.status = 'Active'
            AND p.academic_level = 'Senior High School'
        INNER JOIN school_years sy ON sy.school_year_id = aa.school_year_id AND sy.status = 'Active'
        INNER JOIN teacher_responsibility_assignments tra
            ON tra.teacher_id = aa.teacher_id
           AND tra.responsibility_name = 'Adviser'
           AND tra.school_year_id = aa.school_year_id
           AND tra.status = 'Active'
        INNER JOIN student_academic_enrollments sae ON sae.section_id = aa.section_id AND sae.school_year_id = aa.school_year_id AND sae.semester = aa.teaching_context AND sae.status = 'Active' AND sae.effective_end IS NULL
        INNER JOIN students st ON st.student_id = sae.student_id AND st.status = 'Active'
        INNER JOIN student_class_enrollments sce ON sce.student_id = st.student_id AND sce.status IN ('Active', 'Completed')
        INNER JOIN operational_classes oc ON oc.operational_class_id = sce.operational_class_id
            AND oc.school_year = sy.school_year
            AND oc.semester = aa.teaching_context
        INNER JOIN attendance_sessions ats ON ats.operational_class_id = oc.operational_class_id
        INNER JOIN student_attendance_records sar ON sar.attendance_session_id = ats.attendance_session_id
            AND sar.student_class_enrollment_id = sce.student_class_enrollment_id
        WHERE aa.teacher_id = ?
          AND aa.status = 'Active'
          AND aa.teaching_context IN ('First Semester', 'Second Semester')";
    $params = [$teacherId];
    if ($schoolYearId !== null) { $sql .= " AND aa.school_year_id = ?"; $params[] = $schoolYearId; }
    $sql .= " GROUP BY st.student_id HAVING absent_count > 0 OR late_count > 0 ORDER BY absent_count DESC, late_count DESC";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row = array_merge($row, aprismMonitoringAttendanceMetrics($row));
    }
    unset($row);
    return $rows;
}

/**
 * Returns attendance-record counts for Students in the current Adviser scope.
 * The assignment, SAE, SCE, School Year, and teaching-context joins mirror
 * the existing Adviser attendance-concern query without requiring a concern.
 *
 * @return array<int, int> Student ID to recorded attendance-row count
 */
function aprismAdviserAttendanceEvidenceCounts(PDO $pdo, int $teacherId, ?int $schoolYearId = null): array
{
    if ($teacherId <= 0) {
        return [];
    }

    $sql = "SELECT st.student_id,
        COUNT(DISTINCT sar.student_attendance_record_id) attendance_record_count
        FROM adviser_assignments aa
        INNER JOIN sections sec ON sec.section_id = aa.section_id AND sec.status = 'Active'
        INNER JOIN programs p ON p.program_id = sec.program_id
            AND p.status = 'Active'
            AND p.academic_level = 'Senior High School'
        INNER JOIN school_years sy ON sy.school_year_id = aa.school_year_id AND sy.status = 'Active'
        INNER JOIN teacher_responsibility_assignments tra
            ON tra.teacher_id = aa.teacher_id
           AND tra.responsibility_name = 'Adviser'
           AND tra.school_year_id = aa.school_year_id
           AND tra.status = 'Active'
        INNER JOIN student_academic_enrollments sae
            ON sae.section_id = aa.section_id
           AND sae.school_year_id = aa.school_year_id
           AND sae.semester = aa.teaching_context
           AND sae.status = 'Active'
           AND sae.effective_end IS NULL
        INNER JOIN students st ON st.student_id = sae.student_id AND st.status = 'Active'
        LEFT JOIN student_class_enrollments sce
            ON sce.student_id = st.student_id
           AND sce.status IN ('Active', 'Completed')
        LEFT JOIN operational_classes oc
            ON oc.operational_class_id = sce.operational_class_id
           AND oc.school_year = sy.school_year
           AND oc.semester = aa.teaching_context
        LEFT JOIN attendance_sessions ats ON ats.operational_class_id = oc.operational_class_id
        LEFT JOIN student_attendance_records sar
            ON sar.attendance_session_id = ats.attendance_session_id
           AND sar.student_class_enrollment_id = sce.student_class_enrollment_id
        WHERE aa.teacher_id = ?
          AND aa.status = 'Active'
          AND aa.teaching_context IN ('First Semester', 'Second Semester')";
    $params = [$teacherId];
    if ($schoolYearId !== null) {
        $sql .= " AND aa.school_year_id = ?";
        $params[] = $schoolYearId;
    }
    $sql .= ' GROUP BY st.student_id';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $counts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[(int) $row['student_id']] = (int) $row['attendance_record_count'];
    }

    return $counts;
}
