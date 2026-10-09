<?php

declare(strict_types=1);

function aprismTeacherReferralClasses(PDO $pdo, int $teacherId, ?string $schoolYear = null): array
{
    if ($schoolYear === null || trim($schoolYear) === '') return [];
    $stmt = $pdo->prepare("SELECT oc.operational_class_id, oc.school_year, oc.semester, s.subject_name, s.subject_code, sec.section_name, COUNT(DISTINCT st.student_id) AS active_student_count
        FROM operational_classes oc
        INNER JOIN subjects s ON s.subject_id = oc.subject_id
        INNER JOIN sections sec ON sec.section_id = oc.section_id
        LEFT JOIN student_class_enrollments sce ON sce.operational_class_id = oc.operational_class_id AND sce.status = 'Active'
        LEFT JOIN students st ON st.student_id = sce.student_id AND st.status = 'Active'
        WHERE oc.teacher_id = ? AND oc.school_year = ? AND oc.status = 'Active'
        GROUP BY oc.operational_class_id, oc.school_year, oc.semester, s.subject_name, s.subject_code, sec.section_name
        ORDER BY s.subject_name, sec.section_name, oc.operational_class_id");
    $stmt->execute([$teacherId, $schoolYear]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismTeacherReferralRoster(PDO $pdo, int $teacherId, int $classId): array
{
    $stmt = $pdo->prepare("SELECT sce.student_class_enrollment_id, st.student_id, st.student_number, CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) AS student_name FROM student_class_enrollments sce INNER JOIN students st ON st.student_id = sce.student_id INNER JOIN operational_classes oc ON oc.operational_class_id = sce.operational_class_id WHERE oc.operational_class_id = ? AND oc.teacher_id = ? AND oc.status = 'Active' AND sce.status = 'Active' AND st.status = 'Active' ORDER BY st.last_name, st.first_name, st.student_number");
    $stmt->execute([$classId, $teacherId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismTeacherReferrals(PDO $pdo, int $teacherId, ?int $classId = null): array
{
    $sql = "SELECT tr.teacher_referral_id, tr.reason, tr.status, tr.school_year_snapshot, tr.semester_snapshot, tr.created_at, st.student_id, st.student_number, CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) AS student_name, s.subject_name, sec.section_name FROM teacher_referrals tr INNER JOIN students st ON st.student_id = tr.student_id LEFT JOIN operational_classes oc ON oc.operational_class_id = tr.operational_class_id LEFT JOIN subjects s ON s.subject_id = oc.subject_id LEFT JOIN sections sec ON sec.section_id = oc.section_id WHERE tr.teacher_id = ?";
    $params = [$teacherId];
    if ($classId !== null) { $sql .= " AND tr.operational_class_id = ?"; $params[] = $classId; }
    $sql .= " ORDER BY tr.created_at DESC, tr.teacher_referral_id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismTeacherReferralDetail(PDO $pdo, int $viewerId, int $referralId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT tr.teacher_referral_id, tr.teacher_id, tr.student_id, tr.operational_class_id,
            tr.student_class_enrollment_id, tr.school_year_snapshot, tr.semester_snapshot,
            tr.reason, tr.status, tr.created_at, tr.updated_at,
            COALESCE(NULLIF(TRIM(CONCAT_WS(' ', referral_teacher.first_name, referral_teacher.last_name)), ''), referral_teacher.email, 'Teacher') AS referral_teacher_name,
            gc.guidance_case_id,
            st.student_number,
            CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) AS student_name,
            s.subject_name, s.subject_code, sec.section_name
        FROM teacher_referrals tr
        LEFT JOIN users referral_teacher ON referral_teacher.user_id = tr.teacher_id
        LEFT JOIN guidance_cases gc ON gc.teacher_referral_id = tr.teacher_referral_id
        INNER JOIN students st ON st.student_id = tr.student_id
        LEFT JOIN operational_classes oc ON oc.operational_class_id = tr.operational_class_id
        LEFT JOIN subjects s ON s.subject_id = oc.subject_id
        LEFT JOIN sections sec ON sec.section_id = oc.section_id
        WHERE tr.teacher_referral_id = ?
          AND (
            tr.teacher_id = ?
            OR EXISTS (
                SELECT 1
                FROM adviser_assignments aa
                INNER JOIN sections advisory_sec ON advisory_sec.section_id = aa.section_id
                    AND advisory_sec.status = 'Active'
                INNER JOIN programs p ON p.program_id = advisory_sec.program_id
                    AND p.status = 'Active'
                    AND p.academic_level = 'Senior High School'
                INNER JOIN school_years sy ON sy.school_year_id = aa.school_year_id
                    AND sy.status = 'Active'
                    AND sy.school_year = tr.school_year_snapshot
                INNER JOIN teacher_responsibility_assignments tra
                    ON tra.teacher_id = aa.teacher_id
                   AND tra.responsibility_name = 'Adviser'
                   AND tra.school_year_id = aa.school_year_id
                   AND tra.status = 'Active'
                INNER JOIN student_academic_enrollments sae
                    ON sae.student_id = tr.student_id
                   AND sae.section_id = aa.section_id
                   AND sae.school_year_id = aa.school_year_id
                   AND sae.semester = aa.teaching_context
                   AND sae.status = 'Active'
                   AND sae.effective_end IS NULL
                WHERE aa.teacher_id = ?
                  AND aa.status = 'Active'
                  AND aa.teaching_context IN ('First Semester', 'Second Semester')
                  AND tr.semester_snapshot = aa.teaching_context
            )
          )
        LIMIT 1"
    );
    $stmt->execute([$referralId, $viewerId, $viewerId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function aprismTeacherReferralFollowupRows(PDO $pdo, int $viewerId, int $referralId): array
{
    $referral = aprismTeacherReferralDetail($pdo, $viewerId, $referralId);
    if ($referral === null) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT rf.referral_followup_id, rf.note, rf.status_after, rf.created_at,
            rf.author_user_id,
            COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.email) AS author_name
        FROM teacher_referral_followups rf
        INNER JOIN users u ON u.user_id = rf.author_user_id
        WHERE rf.teacher_referral_id = ?
        ORDER BY rf.created_at ASC, rf.referral_followup_id ASC"
    );
    $stmt->execute([$referralId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismTeacherReferralEvidence(PDO $pdo, int $viewerId, array $referral): array
{
    $studentId = (int) ($referral['student_id'] ?? 0);
    $classId = (int) ($referral['operational_class_id'] ?? 0);
    $sceId = (int) ($referral['student_class_enrollment_id'] ?? 0);
    $evidence = [
        'attendance' => ['present' => 0, 'late' => 0, 'absent' => 0],
        'previous_referrals' => 0,
    ];

    if ($studentId > 0 && $classId > 0 && $sceId > 0) {
        $stmt = $pdo->prepare(
            "SELECT sar.status, COUNT(*) AS status_count
            FROM student_attendance_records sar
            INNER JOIN attendance_sessions ats ON ats.attendance_session_id = sar.attendance_session_id
            WHERE ats.operational_class_id = ?
              AND sar.student_class_enrollment_id = ?
              AND sar.status IN ('Present', 'Late', 'Absent')
            GROUP BY sar.status"
        );
        $stmt->execute([$classId, $sceId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = strtolower((string) $row['status']);
            if (array_key_exists($key, $evidence['attendance'])) {
                $evidence['attendance'][$key] = (int) $row['status_count'];
            }
        }
    }

    if ($studentId > 0) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
            FROM teacher_referrals tr
            WHERE tr.student_id = ? AND tr.teacher_id = ?"
        );
        $stmt->execute([$studentId, $viewerId]);
        $evidence['previous_referrals'] = max(0, (int) $stmt->fetchColumn() - 1);
    }

    return $evidence;
}

function aprismTeacherReferralContext(PDO $pdo, int $teacherId, int $classId, int $sceId): ?array
{
    $stmt = $pdo->prepare("SELECT oc.operational_class_id, oc.school_year, oc.semester, sce.student_class_enrollment_id, sce.student_id FROM operational_classes oc INNER JOIN student_class_enrollments sce ON sce.operational_class_id = oc.operational_class_id AND sce.status = 'Active' INNER JOIN students st ON st.student_id = sce.student_id AND st.status = 'Active' WHERE oc.operational_class_id = ? AND oc.teacher_id = ? AND oc.status = 'Active' AND sce.student_class_enrollment_id = ? LIMIT 1");
    $stmt->execute([$classId, $teacherId, $sceId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
