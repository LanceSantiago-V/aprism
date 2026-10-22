<?php
declare(strict_types=1);

require_once __DIR__ . '/monitoring_helper.php';

/**
 * Attendance applicable through a period cutoff for each class attempt.
 * meeting_date is the academic evidence date; record creation time is not.
 * This deliberately calls the same approved tier evaluator as current risk.
 *
 * @return array<int,array<string,mixed>>
 */
function aprismRiskHistoryClassEvidence(PDO $pdo, int $classId, string $cutoffDate): array
{
    $stmt = $pdo->prepare(
        "SELECT sce.student_class_enrollment_id, sce.student_id,
            COUNT(sar.student_attendance_record_id) AS recorded_count,
            SUM(CASE WHEN sar.status = 'Absent' THEN 1 ELSE 0 END) AS absent_count,
            SUM(CASE WHEN sar.status = 'Late' THEN 1 ELSE 0 END) AS late_count,
            GROUP_CONCAT(sar.status ORDER BY ats.meeting_date, ats.scheduled_start_time,
                ats.attendance_session_id SEPARATOR ',') AS status_sequence
         FROM student_class_enrollments sce
         INNER JOIN students st ON st.student_id = sce.student_id
         LEFT JOIN attendance_sessions ats
           ON ats.operational_class_id = sce.operational_class_id
          AND ats.meeting_date <= ?
         LEFT JOIN student_attendance_records sar
           ON sar.attendance_session_id = ats.attendance_session_id
          AND sar.student_class_enrollment_id = sce.student_class_enrollment_id
         WHERE sce.operational_class_id = ?
           AND sce.enrolled_at < DATE_ADD(?, INTERVAL 1 DAY)
           AND (sce.ended_at IS NULL OR sce.ended_at >= DATE_ADD(?, INTERVAL 1 DAY))
         GROUP BY sce.student_class_enrollment_id, sce.student_id
         ORDER BY sce.student_class_enrollment_id"
    );
    $stmt->execute([$cutoffDate, $classId, $cutoffDate, $cutoffDate]);
    return array_map(
        static fn(array $row): array => array_merge($row, aprismMonitoringEvaluateClassRisk($row)),
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );
}

/** Capture a single completed period/class once, including its no-tier cohort. */
function aprismRiskHistoryCaptureClass(
    PDO $pdo,
    int $periodId,
    int $classId,
    string $origin = 'AutomaticPeriodClose'
): int {
    if (!in_array($origin, ['AutomaticPeriodClose', 'FixtureReconstruction'], true)) {
        throw new InvalidArgumentException('Unsupported risk snapshot origin.');
    }
    if ($origin === 'FixtureReconstruction'
        && (PHP_SAPI !== 'cli' || !defined('APP_ENV') || APP_ENV !== 'local')) {
        throw new RuntimeException('Fixture reconstruction is local CLI only.');
    }
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    $existing = $pdo->prepare('SELECT risk_period_class_capture_id FROM risk_period_class_captures WHERE academic_period_id=? AND operational_class_id=?');
    try {
        // Serialize period capture with Academic Period edits before reading its cutoff/context.
        $periodStmt = $pdo->prepare(
            'SELECT end_date, is_archived, school_year_id, academic_level, semester
             FROM academic_periods
             WHERE academic_period_id = ?
             FOR UPDATE'
        );
        $periodStmt->execute([$periodId]);
        $period = $periodStmt->fetch(PDO::FETCH_ASSOC);
        $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
        if (!$period || (int) $period['is_archived'] !== 0 || $period['end_date'] >= $today) {
            throw new RuntimeException('Risk snapshot needs a matching completed Academic Period.');
        }

        $classMatch = $pdo->prepare(
            "SELECT 1
             FROM school_years sy
             INNER JOIN operational_classes oc ON oc.school_year = sy.school_year
                AND oc.semester = ?
             INNER JOIN sections sec ON sec.section_id = oc.section_id
             INNER JOIN programs p ON p.program_id = sec.program_id
                AND p.academic_level = ?
             WHERE sy.school_year_id = ? AND oc.operational_class_id = ?
               AND (oc.academic_level IS NULL OR oc.academic_level = ?)
             LIMIT 1"
        );
        $classMatch->execute([
            $period['semester'],
            $period['academic_level'],
            (int) $period['school_year_id'],
            $classId,
            $period['academic_level'],
        ]);
        if ($classMatch->fetchColumn() === false) {
            throw new RuntimeException('Risk snapshot needs a matching completed Academic Period.');
        }

        $existing->execute([$periodId, $classId]);
        $captureId = $existing->fetchColumn();
        if ($captureId !== false) {
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return (int) $captureId;
        }

        $rows = aprismRiskHistoryClassEvidence($pdo, $classId, $period['end_date']);
        $insert = $pdo->prepare(
            'INSERT INTO risk_period_class_captures
             (academic_period_id,operational_class_id,cutoff_date,origin,cohort_count)
             VALUES (?,?,?,?,?)'
        );
        $insert->execute([$periodId, $classId, $period['end_date'], $origin, count($rows)]);
        $captureId = (int) $pdo->lastInsertId();
        $insertRow = $pdo->prepare(
            'INSERT INTO risk_period_student_snapshots
             (risk_period_class_capture_id,student_class_enrollment_id,student_id,assessable,
              tier,recorded_count,absent_count,late_count,effective_absent_count,consecutive_absence_count)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        foreach ($rows as $row) {
            $insertRow->execute([
                $captureId, $row['student_class_enrollment_id'], $row['student_id'],
                (int) $row['assessable'], $row['tier'], $row['recorded_count'],
                $row['absent_count'], $row['late_count'], $row['effective_absent_count'],
                $row['consecutive_absence_count'],
            ]);
        }
        if ($ownsTransaction) {
            $pdo->commit();
        }
        return $captureId;
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // A concurrent request may have won the unique period/class insert.
        if ($ownsTransaction && $error instanceof PDOException && $error->getCode() === '23000') {
            $existing->execute([$periodId, $classId]);
            $captureId = $existing->fetchColumn();
            if ($captureId !== false) {
                return (int) $captureId;
            }
        }
        throw $error;
    }
}

/**
 * First authenticated DO Dashboard request after a period ends captures it.
 * Periods ending before this migration was applied are never auto-backfilled.
 * Existing period/class captures are immutable, so later requests are no-ops.
 */
function aprismRiskHistoryCaptureNewlyClosed(PDO $pdo): void
{
    $migration = '20261018_create_risk_period_snapshots.sql';
    $stmt = $pdo->prepare('SELECT DATE(applied_at) FROM schema_migrations WHERE migration_name=?');
    $stmt->execute([$migration]);
    $startDate = $stmt->fetchColumn();
    if ($startDate === false) {
        return;
    }
    $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
    $periods = $pdo->prepare(
        "SELECT ap.academic_period_id, ap.end_date
         FROM academic_periods ap
         WHERE ap.is_archived = 0 AND ap.end_date >= ? AND ap.end_date < ?
         ORDER BY ap.end_date, ap.academic_period_id"
    );
    $periods->execute([$startDate, $today]);
    $classes = $pdo->prepare(
        "SELECT oc.operational_class_id
         FROM academic_periods ap
         INNER JOIN school_years sy ON sy.school_year_id = ap.school_year_id
         INNER JOIN operational_classes oc ON oc.school_year = sy.school_year
            AND oc.semester = ap.semester
            AND oc.created_at < DATE_ADD(ap.end_date, INTERVAL 1 DAY)
         INNER JOIN sections sec ON sec.section_id = oc.section_id
         INNER JOIN programs p ON p.program_id = sec.program_id
            AND p.academic_level = ap.academic_level
         LEFT JOIN risk_period_class_captures rpc
            ON rpc.academic_period_id = ap.academic_period_id
           AND rpc.operational_class_id = oc.operational_class_id
         WHERE ap.academic_period_id = ?
           AND (oc.academic_level IS NULL OR oc.academic_level = ap.academic_level)
           AND rpc.risk_period_class_capture_id IS NULL"
    );
    foreach ($periods->fetchAll(PDO::FETCH_ASSOC) as $period) {
        $classes->execute([(int) $period['academic_period_id']]);
        foreach ($classes->fetchAll(PDO::FETCH_COLUMN) as $classId) {
            aprismRiskHistoryCaptureClass($pdo, (int) $period['academic_period_id'], (int) $classId);
        }
    }
}

/** Highest class tier per Student, matching the live dashboard aggregation. */
function aprismRiskHistoryStudentTierCounts(array $snapshotRows): array
{
    $highest = [];
    foreach ($snapshotRows as $row) {
        if ($row['student_id'] === null) {
            continue;
        }
        $studentId = (int) $row['student_id'];
        $highest[$studentId] = max($highest[$studentId] ?? 0, (int) ($row['tier'] ?? 0));
    }
    $counts = [3 => 0, 2 => 0, 1 => 0];
    foreach ($highest as $tier) {
        if (isset($counts[$tier])) {
            $counts[$tier]++;
        }
    }
    return ['student_count' => count($highest), 'tiers' => $counts];
}

/**
 * Period totals use each Student's highest captured class tier. Coverage is
 * reported in class attempts; a partial institutional cohort is never hidden.
 *
 * @return array<int,array<string,mixed>>
 */
function aprismRiskHistoryTrend(PDO $pdo, string $academicLevel): array
{
    if (!in_array($academicLevel, ['College', 'Senior High School'], true)) {
        throw new InvalidArgumentException('Invalid trend scope.');
    }
    $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
    $periodStmt = $pdo->prepare(
        "SELECT ap.academic_period_id, ap.period_name, ap.semester, ap.end_date
         FROM academic_periods ap
         INNER JOIN school_years sy ON sy.school_year_id = ap.school_year_id
         WHERE sy.status = 'Active' AND ap.academic_level = ?
           AND ap.is_archived = 0
         ORDER BY ap.start_date, ap.academic_period_id"
    );
    $periodStmt->execute([$academicLevel]);
    $snapshotStmt = $pdo->prepare(
        'SELECT rpc.risk_period_class_capture_id, rpc.cohort_count, rpc.origin,
                rps.student_id, rps.student_class_enrollment_id, rps.tier
         FROM risk_period_class_captures rpc
         LEFT JOIN risk_period_student_snapshots rps
           ON rps.risk_period_class_capture_id = rpc.risk_period_class_capture_id
         WHERE rpc.academic_period_id = ?'
    );
    $eligibleStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM student_class_enrollments sce
         INNER JOIN operational_classes oc ON oc.operational_class_id = sce.operational_class_id
         INNER JOIN sections sec ON sec.section_id = oc.section_id
         INNER JOIN programs p ON p.program_id = sec.program_id
         INNER JOIN school_years sy ON sy.school_year = oc.school_year
         INNER JOIN academic_periods ap ON ap.school_year_id = sy.school_year_id
            AND ap.semester = oc.semester AND ap.academic_level = p.academic_level
         WHERE ap.academic_period_id = ?
           AND (oc.academic_level IS NULL OR oc.academic_level = ap.academic_level)
           AND sce.enrolled_at < DATE_ADD(ap.end_date, INTERVAL 1 DAY)
           AND (sce.ended_at IS NULL OR sce.ended_at >= DATE_ADD(ap.end_date, INTERVAL 1 DAY))"
    );
    $result = [];
    foreach ($periodStmt->fetchAll(PDO::FETCH_ASSOC) as $period) {
        $periodId = (int) $period['academic_period_id'];
        $eligibleStmt->execute([$periodId]);
        $eligible = (int) $eligibleStmt->fetchColumn();
        $snapshotStmt->execute([$periodId]);
        $snapshotRows = [];
        $captureSizes = [];
        $reconstructed = false;
        $capturedAttempts = 0;
        foreach ($snapshotStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $captureId = (int) $row['risk_period_class_capture_id'];
            $captureSizes[$captureId] = (int) $row['cohort_count'];
            $reconstructed = $reconstructed || $row['origin'] === 'FixtureReconstruction';
            if ($row['student_class_enrollment_id'] === null) {
                continue;
            }
            $capturedAttempts++;
            $snapshotRows[] = $row;
        }
        if (array_sum($captureSizes) !== $capturedAttempts) {
            throw new RuntimeException('Incomplete historical risk capture.');
        }
        $summary = aprismRiskHistoryStudentTierCounts($snapshotRows);
        $result[] = [
            'period_id' => $periodId,
            'label' => $period['period_name'],
            'semester' => $period['semester'],
            'cutoff' => $period['end_date'],
            'completed' => $period['end_date'] < $today,
            'available' => $capturedAttempts > 0,
            'captured_attempts' => $capturedAttempts,
            'eligible_attempts' => $eligible,
            'student_count' => $summary['student_count'],
            'partial' => $capturedAttempts !== $eligible,
            'reconstructed' => $reconstructed,
            'tiers' => $summary['tiers'],
        ];
    }
    return $result;
}
