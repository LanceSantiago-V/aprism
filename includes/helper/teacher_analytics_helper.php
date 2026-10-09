<?php

declare(strict_types=1);

require_once __DIR__ . '/grade_analytics_helper.php';
require_once __DIR__ . '/monitoring_helper.php';

function aprismTeacherAnalyticsDate(mixed $value): ?string
{
    if (!is_scalar($value) || trim((string) $value) === '') {
        return null;
    }

    $value = trim((string) $value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value
        ? $date->format('Y-m-d')
        : null;
}

function aprismTeacherAnalyticsEmptyAttendance(): array
{
    return [
        'summary' => [
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'recorded' => 0,
            'attendance_rate' => null,
            'effective_absences' => 0,
            'session_count' => 0,
        ],
        'sessions' => [],
        'students' => [],
    ];
}

function aprismTeacherAnalyticsAttendance(
    PDO $pdo,
    int $teacherId,
    int $classId,
    ?string $startDate = null,
    ?string $endDate = null
): array {
    $class = aprismGradeClassContext($pdo, $teacherId, $classId);
    if ($class === null) {
        return aprismTeacherAnalyticsEmptyAttendance();
    }

    $dateSql = '';
    $dateParams = [];
    if ($startDate !== null) {
        $dateSql .= ' AND ats.meeting_date >= ?';
        $dateParams[] = $startDate;
    }
    if ($endDate !== null) {
        $dateSql .= ' AND ats.meeting_date <= ?';
        $dateParams[] = $endDate;
    }

    $sessionStmt = $pdo->prepare(
        "SELECT ats.attendance_session_id, ats.meeting_date, ats.scheduled_day,
                ats.scheduled_start_time, ats.scheduled_end_time, ats.scheduled_room,
                ats.status,
                SUM(CASE WHEN sar.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
                SUM(CASE WHEN sar.status = 'Late' THEN 1 ELSE 0 END) AS late_count,
                SUM(CASE WHEN sar.status = 'Absent' THEN 1 ELSE 0 END) AS absent_count,
                COUNT(sar.student_attendance_record_id) AS recorded_count
         FROM attendance_sessions ats
         INNER JOIN operational_classes oc
             ON oc.operational_class_id = ats.operational_class_id
            AND oc.teacher_id = ?
         LEFT JOIN student_attendance_records sar
             ON sar.attendance_session_id = ats.attendance_session_id
         WHERE ats.operational_class_id = ? {$dateSql}
         GROUP BY ats.attendance_session_id, ats.meeting_date, ats.scheduled_day,
                  ats.scheduled_start_time, ats.scheduled_end_time, ats.scheduled_room,
                  ats.status
         ORDER BY ats.meeting_date ASC, ats.scheduled_start_time ASC,
                  ats.attendance_session_id ASC"
    );
    $sessionStmt->execute(array_merge([$teacherId, $classId], $dateParams));

    $sessions = [];
    $summary = aprismTeacherAnalyticsEmptyAttendance()['summary'];
    foreach ($sessionStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $present = (int) $row['present_count'];
        $late = (int) $row['late_count'];
        $absent = (int) $row['absent_count'];
        $recorded = $present + $late + $absent;
        $row['present_count'] = $present;
        $row['late_count'] = $late;
        $row['absent_count'] = $absent;
        $row['recorded_count'] = $recorded;
        $row['attendance_rate'] = $recorded > 0 ? ($present / $recorded) * 100 : null;
        $sessions[] = $row;
        $summary['present'] += $present;
        $summary['late'] += $late;
        $summary['absent'] += $absent;
        $summary['recorded'] += $recorded;
    }
    $summary['session_count'] = count($sessions);
    $summary['attendance_rate'] = $summary['recorded'] > 0
        ? ($summary['present'] / $summary['recorded']) * 100
        : null;
    $summary = array_merge($summary, aprismMonitoringAttendanceMetrics([
        'present_count' => $summary['present'],
        'late_count' => $summary['late'],
        'absent_count' => $summary['absent'],
    ]));

    $studentStmt = $pdo->prepare(
        "SELECT sce.student_class_enrollment_id, st.student_id, st.student_number,
                CONCAT_WS(' ', st.first_name, st.middle_name, st.last_name, st.suffix) AS student_name,
                COUNT(sar.student_attendance_record_id) AS recorded_count,
                SUM(CASE WHEN sar.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
                SUM(CASE WHEN sar.status = 'Late' THEN 1 ELSE 0 END) AS late_count,
                SUM(CASE WHEN sar.status = 'Absent' THEN 1 ELSE 0 END) AS absent_count,
                COUNT(DISTINCT ats.attendance_session_id) AS session_count
         FROM student_class_enrollments sce
         INNER JOIN students st
             ON st.student_id = sce.student_id
            AND st.status = 'Active'
         LEFT JOIN attendance_sessions ats
             ON ats.operational_class_id = sce.operational_class_id {$dateSql}
         LEFT JOIN student_attendance_records sar
             ON sar.attendance_session_id = ats.attendance_session_id
            AND sar.student_class_enrollment_id = sce.student_class_enrollment_id
         WHERE sce.operational_class_id = ?
           AND sce.status = 'Active'
         GROUP BY sce.student_class_enrollment_id, st.student_id, st.student_number,
                  st.first_name, st.middle_name, st.last_name, st.suffix
         ORDER BY st.last_name, st.first_name, st.student_number"
    );
    $studentStmt->execute(array_merge($dateParams, [$classId]));

    $students = [];
    foreach ($studentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $present = (int) $row['present_count'];
        $late = (int) $row['late_count'];
        $absent = (int) $row['absent_count'];
        $recorded = $present + $late + $absent;
        $metrics = aprismMonitoringAttendanceMetrics([
            'present_count' => $present,
            'late_count' => $late,
            'absent_count' => $absent,
        ]);
        $students[(int) $row['student_id']] = array_merge($row, $metrics, [
            'present_count' => $present,
            'late_count' => $late,
            'absent_count' => $absent,
            'recorded_count' => $recorded,
            'session_count' => (int) $row['session_count'],
            'attendance_rate' => $recorded > 0 ? ($present / $recorded) * 100 : null,
        ]);
    }

    return [
        'summary' => $summary,
        'sessions' => $sessions,
        'students' => array_values($students),
    ];
}

function aprismTeacherAnalyticsAttendanceStudentHistory(
    PDO $pdo,
    int $teacherId,
    int $classId,
    int $studentId,
    ?string $startDate = null,
    ?string $endDate = null
): array {
    $class = aprismGradeClassContext($pdo, $teacherId, $classId);
    if ($class === null || $studentId <= 0) {
        return [];
    }

    $dateSql = '';
    $params = [$teacherId, $classId, $studentId];
    if ($startDate !== null) {
        $dateSql .= ' AND ats.meeting_date >= ?';
        $params[] = $startDate;
    }
    if ($endDate !== null) {
        $dateSql .= ' AND ats.meeting_date <= ?';
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare(
        "SELECT ats.attendance_session_id, ats.meeting_date, ats.scheduled_day,
                ats.scheduled_start_time, ats.scheduled_end_time, ats.scheduled_room,
                sar.status, sar.checked_in_at, sar.capture_source
         FROM attendance_sessions ats
         INNER JOIN operational_classes oc
             ON oc.operational_class_id = ats.operational_class_id
            AND oc.teacher_id = ?
         INNER JOIN student_class_enrollments sce
             ON sce.operational_class_id = ats.operational_class_id
            AND sce.student_id = ?
            AND sce.status = 'Active'
         LEFT JOIN student_attendance_records sar
             ON sar.attendance_session_id = ats.attendance_session_id
            AND sar.student_class_enrollment_id = sce.student_class_enrollment_id
         WHERE ats.operational_class_id = ? {$dateSql}
         ORDER BY ats.meeting_date DESC, ats.scheduled_start_time DESC,
                  ats.attendance_session_id DESC"
    );
    $stmt->execute([$teacherId, $studentId, $classId, ...array_slice($params, 3)]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
