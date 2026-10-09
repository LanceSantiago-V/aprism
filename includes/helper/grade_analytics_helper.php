<?php

declare(strict_types=1);

require_once __DIR__ . '/grade_ledger_helper.php';

/**
 * Read-only aggregation for Teacher Grade Analytics.
 *
 * APRISM currently persists raw component scores and their possible scores,
 * but no canonical computed/transmuted period grade.  These helpers therefore
 * report coverage and clearly labelled recorded raw-score percentages only.
 */
function aprismGradeAnalyticsPeriods(PDO $pdo, int $classId): array
{
    $stmt = $pdo->prepare(
        "SELECT DISTINCT ap.academic_period_id, ap.academic_level, ap.semester,
                ap.period_name, ap.start_date, ap.end_date
         FROM assessment_categories ac
         INNER JOIN academic_periods ap ON ap.academic_period_id = ac.academic_period_id
         WHERE ac.operational_class_id = ?
           AND ac.status = 'Active'
           AND ap.is_archived = 0
         ORDER BY FIELD(ap.period_name, 'Prelim', 'Midterm', 'Pre-Final', 'Final',
                         'Quarter 1', 'Quarter 2', 'Quarter 3', 'Quarter 4'),
                  ap.start_date, ap.academic_period_id"
    );
    $stmt->execute([$classId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismGradeAnalyticsStudentRows(PDO $pdo, int $classId, int $periodId): array
{
    $componentStmt = $pdo->prepare(
        "SELECT cmp.assessment_component_id, cmp.max_score
         FROM assessment_categories ac
         INNER JOIN assessment_components cmp
             ON cmp.assessment_category_id = ac.assessment_category_id
            AND cmp.status = 'Active'
         WHERE ac.operational_class_id = ?
           AND ac.academic_period_id = ?
           AND ac.status = 'Active'
         ORDER BY ac.display_order, ac.assessment_category_id,
                  cmp.display_order, cmp.assessment_component_id"
    );
    $componentStmt->execute([$classId, $periodId]);
    $components = $componentStmt->fetchAll(PDO::FETCH_ASSOC);
    $componentCount = count($components);

    $rosterStmt = $pdo->prepare(
        "SELECT sce.student_class_enrollment_id, sce.student_id, s.student_number,
                CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name, s.suffix) AS student_name
         FROM student_class_enrollments sce
         INNER JOIN students s ON s.student_id = sce.student_id
         WHERE sce.operational_class_id = ?
           AND sce.status = 'Active'
           AND s.status = 'Active'
         ORDER BY s.last_name, s.first_name, s.student_number"
    );
    $rosterStmt->execute([$classId]);
    $rows = $rosterStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows === [] || $components === []) {
        foreach ($rows as &$row) {
            $row['component_count'] = $componentCount;
            $row['score_count'] = 0;
            $row['raw_total'] = null;
            $row['recorded_possible_total'] = null;
            $row['recorded_raw_percent'] = null;
        }
        unset($row);
        return ['components' => $components, 'rows' => $rows];
    }

    $componentIds = array_map(static fn(array $component): int => (int) $component['assessment_component_id'], $components);
    $placeholders = implode(',', array_fill(0, count($componentIds), '?'));
    $scoreStmt = $pdo->prepare(
        "SELECT student_class_enrollment_id, assessment_component_id, raw_score
         FROM student_assessment_scores
         WHERE assessment_component_id IN ($placeholders)"
    );
    $scoreStmt->execute($componentIds);
    $scores = [];
    foreach ($scoreStmt->fetchAll(PDO::FETCH_ASSOC) as $score) {
        $scores[(int) $score['student_class_enrollment_id']][(int) $score['assessment_component_id']] = (float) $score['raw_score'];
    }

    $maxScores = [];
    foreach ($components as $component) {
        $maxScores[(int) $component['assessment_component_id']] = (float) $component['max_score'];
    }

    foreach ($rows as &$row) {
        $sceId = (int) $row['student_class_enrollment_id'];
        $studentScores = $scores[$sceId] ?? [];
        $scoreCount = count($studentScores);
        $rawTotal = $scoreCount ? array_sum($studentScores) : null;
        $possibleTotal = 0.0;
        foreach (array_keys($studentScores) as $componentId) {
            $possibleTotal += $maxScores[$componentId] ?? 0.0;
        }
        $row['component_count'] = $componentCount;
        $row['score_count'] = $scoreCount;
        $row['raw_total'] = $rawTotal;
        $row['recorded_possible_total'] = $scoreCount ? $possibleTotal : null;
        $row['recorded_raw_percent'] = $scoreCount && $possibleTotal > 0
            ? ($rawTotal / $possibleTotal) * 100
            : null;
    }
    unset($row);

    return ['components' => $components, 'rows' => $rows];
}

function aprismGradeAnalyticsCategorySummary(PDO $pdo, int $classId, int $periodId, int $rosterCount): array
{
    $stmt = $pdo->prepare(
        "SELECT ac.assessment_category_id, ac.category_name,
                COUNT(DISTINCT cmp.assessment_component_id) AS component_count,
                COUNT(CASE WHEN sce.student_class_enrollment_id IS NOT NULL
                           THEN sas.student_assessment_score_id END) AS populated_entries,
                COUNT(DISTINCT CASE WHEN sce.student_class_enrollment_id IS NOT NULL
                                    THEN sas.student_class_enrollment_id END) AS students_scored,
                AVG(CASE WHEN cmp.max_score > 0 AND sce.student_class_enrollment_id IS NOT NULL
                         THEN (sas.raw_score / cmp.max_score) * 100 END) AS average_component_percent
         FROM assessment_categories ac
         INNER JOIN assessment_components cmp
             ON cmp.assessment_category_id = ac.assessment_category_id
            AND cmp.status = 'Active'
         LEFT JOIN student_assessment_scores sas
             ON sas.assessment_component_id = cmp.assessment_component_id
         LEFT JOIN student_class_enrollments sce
             ON sce.student_class_enrollment_id = sas.student_class_enrollment_id
            AND sce.operational_class_id = ?
            AND sce.status = 'Active'
         WHERE ac.operational_class_id = ?
           AND ac.academic_period_id = ?
           AND ac.status = 'Active'
         GROUP BY ac.assessment_category_id, ac.category_name, ac.display_order
         ORDER BY ac.display_order, ac.assessment_category_id"
    );
    $stmt->execute([$classId, $classId, $periodId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['expected_entries'] = $rosterCount * (int) $row['component_count'];
        $row['missing_entries'] = max(0, (int) $row['expected_entries'] - (int) $row['populated_entries']);
    }
    unset($row);
    return $rows;
}

function aprismGradeAnalyticsTermCoverage(PDO $pdo, int $classId, int $rosterCount): array
{
    $stmt = $pdo->prepare(
        "SELECT ap.academic_period_id, ap.period_name, ap.academic_level,
                COUNT(DISTINCT cmp.assessment_component_id) AS component_count,
                COUNT(CASE WHEN sce.student_class_enrollment_id IS NOT NULL
                           THEN sas.student_assessment_score_id END) AS populated_entries
         FROM assessment_categories ac
         INNER JOIN academic_periods ap ON ap.academic_period_id = ac.academic_period_id
         INNER JOIN assessment_components cmp
             ON cmp.assessment_category_id = ac.assessment_category_id
            AND cmp.status = 'Active'
         LEFT JOIN student_assessment_scores sas
             ON sas.assessment_component_id = cmp.assessment_component_id
         LEFT JOIN student_class_enrollments sce
             ON sce.student_class_enrollment_id = sas.student_class_enrollment_id
            AND sce.operational_class_id = ?
            AND sce.status = 'Active'
         WHERE ac.operational_class_id = ?
           AND ac.status = 'Active'
           AND ap.is_archived = 0
         GROUP BY ap.academic_period_id, ap.period_name, ap.academic_level, ap.start_date
         ORDER BY FIELD(ap.period_name, 'Prelim', 'Midterm', 'Pre-Final', 'Final',
                        'Quarter 1', 'Quarter 2', 'Quarter 3', 'Quarter 4'),
                  ap.start_date, ap.academic_period_id"
    );
    $stmt->execute([$classId, $classId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['expected_entries'] = $rosterCount * (int) $row['component_count'];
        $row['coverage_percent'] = $row['expected_entries'] > 0
            ? min(100, ((int) $row['populated_entries'] / (int) $row['expected_entries']) * 100)
            : null;
    }
    unset($row);
    return $rows;
}

function aprismGradeAnalyticsBatch(PDO $pdo, int $teacherId, int $classId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT grade_import_batch_id, original_filename, template_type, status, confirmed_at
         FROM grade_import_batches
         WHERE teacher_id = ? AND operational_class_id = ? AND status = 'Confirmed'
         ORDER BY confirmed_at DESC, grade_import_batch_id DESC
         LIMIT 1"
    );
    $stmt->execute([$teacherId, $classId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function aprismGradeAnalyticsStudentDetail(PDO $pdo, int $teacherId, int $classId, int $periodId, int $studentId): ?array
{
    $studentStmt = $pdo->prepare(
        "SELECT sce.student_class_enrollment_id, s.student_id, s.student_number,
                CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name, s.suffix) AS student_name
         FROM operational_classes oc
         INNER JOIN student_class_enrollments sce
             ON sce.operational_class_id = oc.operational_class_id
            AND sce.status = 'Active'
         INNER JOIN students s ON s.student_id = sce.student_id AND s.status = 'Active'
         WHERE oc.operational_class_id = ? AND oc.teacher_id = ? AND s.student_id = ?
         LIMIT 1"
    );
    $studentStmt->execute([$classId, $teacherId, $studentId]);
    $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
    if ($student === false) {
        return null;
    }

    $componentStmt = $pdo->prepare(
        "SELECT ac.category_name, cmp.component_code, cmp.component_name,
                cmp.max_score, sas.raw_score,
                CASE WHEN sas.student_assessment_score_id IS NULL THEN 0 ELSE 1 END AS has_score
         FROM assessment_categories ac
         INNER JOIN assessment_components cmp
             ON cmp.assessment_category_id = ac.assessment_category_id
            AND cmp.status = 'Active'
         LEFT JOIN student_assessment_scores sas
             ON sas.assessment_component_id = cmp.assessment_component_id
            AND sas.student_class_enrollment_id = ?
         WHERE ac.operational_class_id = ?
           AND ac.academic_period_id = ?
           AND ac.status = 'Active'
         ORDER BY ac.display_order, ac.assessment_category_id,
                  cmp.display_order, cmp.assessment_component_id"
    );
    $componentStmt->execute([(int) $student['student_class_enrollment_id'], $classId, $periodId]);
    $components = $componentStmt->fetchAll(PDO::FETCH_ASSOC);

    $comparisonStmt = $pdo->prepare(
        "SELECT ap.academic_period_id, ap.period_name,
                COUNT(DISTINCT cmp.assessment_component_id) AS component_count,
                COUNT(sas.student_assessment_score_id) AS populated_entries,
                SUM(sas.raw_score) AS recorded_raw_total,
                SUM(CASE WHEN sas.student_assessment_score_id IS NOT NULL THEN cmp.max_score ELSE 0 END) AS recorded_possible_total
         FROM assessment_categories ac
         INNER JOIN academic_periods ap ON ap.academic_period_id = ac.academic_period_id
         INNER JOIN assessment_components cmp
             ON cmp.assessment_category_id = ac.assessment_category_id
            AND cmp.status = 'Active'
         LEFT JOIN student_assessment_scores sas
             ON sas.assessment_component_id = cmp.assessment_component_id
            AND sas.student_class_enrollment_id = ?
         WHERE ac.operational_class_id = ? AND ac.status = 'Active'
         GROUP BY ap.academic_period_id, ap.period_name, ap.start_date
         ORDER BY FIELD(ap.period_name, 'Prelim', 'Midterm', 'Pre-Final', 'Final',
                        'Quarter 1', 'Quarter 2', 'Quarter 3', 'Quarter 4'),
                  ap.start_date, ap.academic_period_id"
    );
    $comparisonStmt->execute([(int) $student['student_class_enrollment_id'], $classId]);
    $periods = $comparisonStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($periods as &$period) {
        $period['recorded_raw_percent'] = (float) ($period['recorded_possible_total'] ?? 0) > 0
            ? ((float) $period['recorded_raw_total'] / (float) $period['recorded_possible_total']) * 100
            : null;
    }
    unset($period);

    return ['student' => $student, 'components' => $components, 'period_comparison' => $periods];
}

function aprismGradeAnalyticsBuild(PDO $pdo, int $teacherId, int $classId, int $periodId): ?array
{
    $class = aprismGradeClassContext($pdo, $teacherId, $classId);
    if ($class === null) {
        return null;
    }
    $workspaceContext = aprismGradeWorkspaceContext($pdo, $teacherId, $classId, $periodId);
    $periods = $workspaceContext['periods'];
    $selectedPeriod = $workspaceContext['period'];
    if ($selectedPeriod === null) {
        return ['class' => $class, 'periods' => $periods, 'period' => null, 'rows' => [], 'components' => [], 'summary' => null, 'categories' => [], 'term_coverage' => [], 'batch' => aprismGradeAnalyticsBatch($pdo, $teacherId, $classId)];
    }

    $studentData = aprismGradeAnalyticsStudentRows($pdo, $classId, $periodId);
    $rows = $studentData['rows'];
    $componentCount = count($studentData['components']);
    $summary = [
        'enrolled_students' => count($rows),
        'components' => $componentCount,
        'expected_entries' => count($rows) * $componentCount,
        'populated_entries' => 0,
        'complete_students' => 0,
        'incomplete_students' => 0,
        'no_data_students' => 0,
    ];
    foreach ($rows as &$row) {
        $scoreCount = (int) $row['score_count'];
        $row['missing_entries'] = max(0, $componentCount - $scoreCount);
        $row['completion_state'] = $componentCount === 0 || $scoreCount === 0
            ? 'No data'
            : ($scoreCount === $componentCount ? 'Complete' : 'Incomplete');
        $summary['populated_entries'] += $scoreCount;
        if ($row['completion_state'] === 'Complete') {
            $summary['complete_students']++;
        } elseif ($row['completion_state'] === 'Incomplete') {
            $summary['incomplete_students']++;
        } else {
            $summary['no_data_students']++;
        }
    }
    unset($row);
    $summary['missing_entries'] = max(0, $summary['expected_entries'] - $summary['populated_entries']);
    $summary['coverage_percent'] = $summary['expected_entries'] > 0
        ? min(100, ($summary['populated_entries'] / $summary['expected_entries']) * 100)
        : null;

    return [
        'class' => $class,
        'periods' => $periods,
        'period' => $selectedPeriod,
        'rows' => $rows,
        'components' => $studentData['components'],
        'summary' => $summary,
        'categories' => aprismGradeAnalyticsCategorySummary($pdo, $classId, $periodId, count($rows)),
        'term_coverage' => aprismGradeAnalyticsTermCoverage($pdo, $classId, count($rows)),
        'batch' => aprismGradeAnalyticsBatch($pdo, $teacherId, $classId),
    ];
}
