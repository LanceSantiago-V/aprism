<?php

declare(strict_types=1);

require_once __DIR__ . '/monitoring_helper.php';
require_once __DIR__ . '/grade_analytics_helper.php';
require_once __DIR__ . '/teacher_referral_helper.php';
require_once __DIR__ . '/current_teaching_context_helper.php';

function aprismTeacherDashboardSummary(PDO $pdo, int $teacherId, ?string $schoolYear): array
{
    $summary = [
        'active_classes' => 0,
        'current_students' => 0,
        'classes_today' => 0,
        'open_referrals' => 0,
        'pending_grade_imports' => 0,
        'attendance_records_today' => 0,
    ];
    if ($teacherId <= 0 || $schoolYear === null || trim($schoolYear) === '') {
        return $summary;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT oc.operational_class_id) AS active_classes,
                COUNT(DISTINCT sce.student_id) AS current_students
         FROM operational_classes oc
         LEFT JOIN student_class_enrollments sce
           ON sce.operational_class_id = oc.operational_class_id
          AND sce.status = 'Active'
         LEFT JOIN students st
           ON st.student_id = sce.student_id
          AND st.status = 'Active'
         WHERE oc.teacher_id = ?
           AND oc.school_year = ?
           AND oc.status = 'Active'
           AND " . aprismCurrentClassCondition('oc')
    );
    $stmt->execute([$teacherId, $schoolYear]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $summary['active_classes'] = (int) ($row['active_classes'] ?? 0);
    $summary['current_students'] = (int) ($row['current_students'] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM teacher_referrals tr
         INNER JOIN operational_classes oc
           ON oc.operational_class_id = tr.operational_class_id
          AND oc.teacher_id = tr.teacher_id
          AND oc.school_year = ?
          AND oc.status = 'Active'
         WHERE tr.teacher_id = ?
           AND tr.status = 'Submitted'"
    );
    $stmt->execute([$schoolYear, $teacherId]);
    $summary['open_referrals'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM grade_import_batches
         WHERE teacher_id = ?
           AND status IN ('Review', 'Validated')"
    );
    $stmt->execute([$teacherId]);
    $summary['pending_grade_imports'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT sar.student_attendance_record_id)
         FROM student_attendance_records sar
         INNER JOIN attendance_sessions ats
           ON ats.attendance_session_id = sar.attendance_session_id
         INNER JOIN operational_classes oc
           ON oc.operational_class_id = ats.operational_class_id
          AND oc.teacher_id = ?
          AND oc.school_year = ?
          AND oc.status = 'Active'
          AND " . aprismCurrentClassCondition('oc') . "
         WHERE ats.meeting_date = CURDATE()"
    );
    $stmt->execute([$teacherId, $schoolYear]);
    $summary['attendance_records_today'] = (int) $stmt->fetchColumn();

    return $summary;
}

function aprismTeacherDashboardSchedule(PDO $pdo, int $teacherId, ?string $schoolYear): array
{
    if ($teacherId <= 0 || $schoolYear === null || trim($schoolYear) === '') {
        return [];
    }

    $today = date('l');
    $dayOrder = [
        'Sunday' => 0,
        'Monday' => 1,
        'Tuesday' => 2,
        'Wednesday' => 3,
        'Thursday' => 4,
        'Friday' => 5,
        'Saturday' => 6,
    ];
    $todayIndex = $dayOrder[$today] ?? 0;
    $stmt = $pdo->prepare(
        "SELECT oc.operational_class_id, s.subject_name, sec.section_name,
                cs.day, cs.start_time, cs.end_time, cs.room,
                ats.status AS attendance_status
         FROM operational_classes oc
         INNER JOIN subjects s ON s.subject_id = oc.subject_id
         INNER JOIN sections sec ON sec.section_id = oc.section_id
         INNER JOIN class_schedules cs
           ON cs.operational_class_id = oc.operational_class_id
          AND cs.status = 'Active'
         LEFT JOIN attendance_sessions ats
           ON ats.operational_class_id = oc.operational_class_id
          AND ats.class_schedule_id = cs.class_schedule_id
          AND ats.meeting_date = CURDATE()
         WHERE oc.teacher_id = ?
           AND oc.school_year = ?
           AND oc.status = 'Active'
           AND " . aprismCurrentClassCondition('oc')
    );
    $stmt->execute([$teacherId, $schoolYear]);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dayIndex = $dayOrder[(string) $row['day']] ?? 7;
        $offset = ($dayIndex - $todayIndex + 7) % 7;
        $row['schedule_state'] = $offset === 0 ? 'Today' : 'Upcoming';
        $row['day_offset'] = $offset;
        $row['scheduled_date'] = date('Y-m-d', strtotime('+' . $offset . ' days'));
        $row['sort_time'] = $offset . '|' . (string) $row['start_time'];
        $rows[] = $row;
    }
    usort($rows, static function (array $left, array $right): int {
        return strcmp((string) $left['sort_time'], (string) $right['sort_time']);
    });
    return array_slice($rows, 0, 12);
}

/**
 * Returns Teacher referrals attached to the authenticated Teacher's current
 * active classes. Class IDs are supplied by the page after its normal
 * School Year and teaching-context filtering.
 */
function aprismTeacherDashboardReferralRecords(
    PDO $pdo,
    int $teacherId,
    ?string $schoolYear,
    array $operationalClassIds
): array {
    $classIds = array_values(array_unique(array_filter(array_map('intval', $operationalClassIds), static fn(int $id): bool => $id > 0)));
    if ($teacherId <= 0 || $schoolYear === null || trim($schoolYear) === '' || $classIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($classIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT tr.teacher_referral_id, tr.operational_class_id, tr.student_id,
                tr.reason_code, tr.reason, tr.status, tr.created_at,
                st.student_number,
                CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) AS student_name,
                s.subject_name, sec.section_name
         FROM teacher_referrals tr
         INNER JOIN operational_classes oc
           ON oc.operational_class_id = tr.operational_class_id
          AND oc.teacher_id = tr.teacher_id
          AND oc.status = 'Active'
          AND oc.school_year = ?
          AND " . aprismCurrentClassCondition('oc') . "
         INNER JOIN students st ON st.student_id = tr.student_id
         LEFT JOIN subjects s ON s.subject_id = oc.subject_id
         LEFT JOIN sections sec ON sec.section_id = oc.section_id
         WHERE tr.teacher_id = ?
           AND tr.operational_class_id IN ($placeholders)
         ORDER BY tr.created_at DESC, tr.teacher_referral_id DESC"
    );
    $stmt->execute(array_merge([$schoolYear, $teacherId], $classIds));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismTeacherDashboardReferralMetrics(array $referrals): array
{
    $statuses = ['Submitted' => 0, 'Returned' => 0, 'Accepted' => 0, 'Closed' => 0];
    $reasonCounts = [];
    foreach ($referrals as $referral) {
        $status = (string) ($referral['status'] ?? '');
        if (array_key_exists($status, $statuses)) {
            $statuses[$status]++;
        }

        $reasonCode = trim((string) ($referral['reason_code'] ?? ''));
        $reasonKey = aprismReferralReasonIsValid($reasonCode) ? $reasonCode : 'legacy_unclassified';
        $reasonLabel = $reasonKey === 'legacy_unclassified'
            ? 'Legacy / Unclassified'
            : aprismReferralReasonLabel($reasonKey);
        if (!isset($reasonCounts[$reasonKey])) {
            $reasonCounts[$reasonKey] = ['key' => $reasonKey, 'label' => $reasonLabel, 'count' => 0];
        }
        $reasonCounts[$reasonKey]['count']++;
    }

    usort($reasonCounts, static fn(array $left, array $right): int =>
        ((int) $right['count'] <=> (int) $left['count']) ?: strcmp((string) $left['label'], (string) $right['label'])
    );

    return [
        'total' => count($referrals),
        'statuses' => $statuses,
        'reasons' => array_values($reasonCounts),
    ];
}

function aprismTeacherDashboardAttention(
    PDO $pdo,
    int $teacherId,
    ?string $schoolYear,
    array $operationalClassIds = []
): array
{
    if ($teacherId <= 0 || $schoolYear === null || trim($schoolYear) === '') {
        return [];
    }

    $attention = [];
    foreach (aprismTeacherMonitoringAttendance($pdo, $teacherId, $schoolYear) as $row) {
        $absent = (int) ($row['absent_count'] ?? 0);
        $late = (int) ($row['late_count'] ?? 0);
        if ($absent === 0 && $late === 0) {
            continue;
        }
        $studentId = (int) ($row['student_id'] ?? 0);
        $attention[$studentId] = [
            'student_id' => $studentId,
            'student_name' => (string) ($row['student_name'] ?? ''),
            'student_number' => (string) ($row['student_number'] ?? ''),
            'class_context' => (string) ($row['teaching_context'] ?? 'Teacher-owned classes'),
            'reasons' => [],
            'links' => [],
            'sort_score' => ($absent * 3) + $late,
        ];
        if ($absent > 0) {
            $attention[$studentId]['reasons'][] = $absent . ' absent';
        }
        if ($late > 0) {
            $attention[$studentId]['reasons'][] = $late . ' late';
        }
        $attention[$studentId]['links'][] = [
            'label' => 'Attendance',
            'url' => APP_URL . '/dashboard/teacher_advisory_student.php?scope=teacher&student_id=' . $studentId,
        ];
    }

    foreach (aprismTeacherDashboardReferralRecords($pdo, $teacherId, $schoolYear, $operationalClassIds) as $referral) {
        if ((string) ($referral['status'] ?? '') !== 'Submitted') {
            continue;
        }
        $studentId = (int) ($referral['student_id'] ?? 0);
        if ($studentId <= 0) continue;
        if (!isset($attention[$studentId])) {
            $attention[$studentId] = [
                'student_id' => $studentId,
                'student_name' => (string) ($referral['student_name'] ?? ''),
                'student_number' => (string) ($referral['student_number'] ?? ''),
                'class_context' => (string) (($referral['subject_name'] ?? '') . ' · ' . ($referral['section_name'] ?? '')),
                'reasons' => [],
                'links' => [],
                'sort_score' => 0,
            ];
        }
        $attention[$studentId]['reasons'][] = 'Open referral';
        $attention[$studentId]['links'][] = [
            'label' => 'Referral',
            'url' => APP_URL . '/dashboard/teacher_referral_detail.php?referral_id=' . (int) $referral['teacher_referral_id'],
        ];
        $attention[$studentId]['sort_score'] += 2;
    }

    foreach (aprismGradeTeacherClasses($pdo, $teacherId) as $class) {
        if ((string) ($class['school_year'] ?? '') !== $schoolYear || (string) ($class['status'] ?? '') !== 'Active') {
            continue;
        }
        if (!aprismClassIsInCurrentTeachingContext($pdo, (int) ($class['operational_class_id'] ?? 0))) {
            continue;
        }
        $context = aprismGradeClassContext($pdo, $teacherId, (int) $class['operational_class_id']);
        if ($context === null) continue;
        foreach (aprismGradePeriodsForClass($pdo, $context) as $period) {
            $analytics = aprismGradeAnalyticsBuild(
                $pdo,
                $teacherId,
                (int) $class['operational_class_id'],
                (int) $period['academic_period_id']
            );
            if ($analytics === null || empty($analytics['summary']) || (int) ($analytics['summary']['expected_entries'] ?? 0) === 0) {
                continue;
            }
            foreach ($analytics['rows'] as $student) {
                if (($student['completion_state'] ?? 'Complete') === 'Complete') continue;
                $studentId = (int) ($student['student_id'] ?? 0);
                if ($studentId <= 0) continue;
                if (!isset($attention[$studentId])) {
                    $attention[$studentId] = [
                        'student_id' => $studentId,
                        'student_name' => (string) ($student['student_name'] ?? ''),
                        'student_number' => (string) ($student['student_number'] ?? ''),
                        'class_context' => (string) ($class['subject_name'] . ' · ' . $class['section_name']),
                        'reasons' => [],
                        'links' => [],
                        'sort_score' => 0,
                    ];
                }
                $attention[$studentId]['reasons'][] = 'Missing ' . (int) ($student['missing_entries'] ?? 0) . ' grade entries (' . (string) $period['period_name'] . ')';
                $attention[$studentId]['links'][] = [
                    'label' => 'Grades',
                    'url' => APP_URL . '/dashboard/teacher_grade_student.php?' . http_build_query([
                        'class_id' => (int) $class['operational_class_id'],
                        'period_id' => (int) $period['academic_period_id'],
                        'student_id' => $studentId,
                    ]) . '#student-grade-detail',
                ];
                $attention[$studentId]['sort_score'] += 1;
            }
        }
    }

    foreach ($attention as &$row) {
        $row['reasons'] = array_values(array_unique($row['reasons']));
        $row['links'] = array_values(array_unique($row['links'], SORT_REGULAR));
    }
    unset($row);
    usort($attention, static function (array $left, array $right): int {
        return ((int) $right['sort_score']) <=> ((int) $left['sort_score'])
            ?: strcmp((string) $left['student_name'], (string) $right['student_name']);
    });
    return array_slice($attention, 0, 8);
}
