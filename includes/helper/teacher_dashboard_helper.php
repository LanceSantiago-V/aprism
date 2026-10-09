<?php

declare(strict_types=1);

require_once __DIR__ . '/monitoring_helper.php';
require_once __DIR__ . '/grade_analytics_helper.php';
require_once __DIR__ . '/teacher_referral_helper.php';

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
           AND oc.status = 'Active'"
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
           AND oc.status = 'Active'"
    );
    $stmt->execute([$teacherId, $schoolYear]);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dayIndex = $dayOrder[(string) $row['day']] ?? 7;
        $offset = ($dayIndex - $todayIndex + 7) % 7;
        $row['schedule_state'] = $offset === 0 ? 'Today' : 'Upcoming';
        $row['day_offset'] = $offset;
        $row['sort_time'] = $offset . '|' . (string) $row['start_time'];
        $rows[] = $row;
    }
    usort($rows, static function (array $left, array $right): int {
        return strcmp((string) $left['sort_time'], (string) $right['sort_time']);
    });
    return array_slice($rows, 0, 12);
}

function aprismTeacherDashboardAttention(PDO $pdo, int $teacherId, ?string $schoolYear): array
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

    foreach (aprismTeacherReferrals($pdo, $teacherId) as $referral) {
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
                    ]),
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
