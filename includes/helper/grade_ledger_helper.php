<?php

declare(strict_types=1);

function aprismGradeTeacherClasses(PDO $pdo, int $teacherId): array
{
    $stmt = $pdo->prepare("SELECT oc.operational_class_id, oc.school_year, oc.semester, oc.status, s.subject_name, sec.section_name FROM operational_classes oc INNER JOIN subjects s ON s.subject_id=oc.subject_id INNER JOIN sections sec ON sec.section_id=oc.section_id WHERE oc.teacher_id=? ORDER BY oc.status='Active' DESC, oc.school_year DESC, s.subject_name, sec.section_name");
    $stmt->execute([$teacherId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismGradeClassContext(PDO $pdo, int $teacherId, int $classId): ?array
{
    $stmt = $pdo->prepare("SELECT oc.operational_class_id, oc.school_year, oc.semester, oc.status, sy.school_year_id, s.subject_name, sec.section_name FROM operational_classes oc INNER JOIN school_years sy ON sy.school_year=oc.school_year INNER JOIN subjects s ON s.subject_id=oc.subject_id INNER JOIN sections sec ON sec.section_id=oc.section_id WHERE oc.operational_class_id=? AND oc.teacher_id=? LIMIT 1");
    $stmt->execute([$classId, $teacherId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function aprismGradePeriodsForClass(PDO $pdo, array $class): array
{
    $stmt = $pdo->prepare("SELECT academic_period_id, academic_level, semester, period_name, start_date, end_date FROM academic_periods WHERE school_year_id=? AND semester=? AND is_archived=0 ORDER BY FIELD(period_name, 'Prelim', 'Midterm', 'Pre-Final', 'Final', 'Quarter 1', 'Quarter 2', 'Quarter 3', 'Quarter 4'), start_date, academic_period_id");
    $stmt->execute([(int) $class['school_year_id'], (string) $class['semester']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aprismGradeWorkspaceContext(PDO $pdo, int $teacherId, int $classId = 0, int $periodId = 0): array
{
    $class = $classId > 0 ? aprismGradeClassContext($pdo, $teacherId, $classId) : null;
    $periods = $class !== null ? aprismGradePeriodsForClass($pdo, $class) : [];
    $period = null;
    foreach ($periods as $candidate) {
        if ((int) $candidate['academic_period_id'] === $periodId) {
            $period = $candidate;
            break;
        }
    }

    return [
        'class' => $class,
        'periods' => $periods,
        'period' => $period,
        'class_id' => $class !== null ? (int) $class['operational_class_id'] : 0,
        'period_id' => $period !== null ? (int) $period['academic_period_id'] : 0,
    ];
}

function aprismGradeLedger(PDO $pdo, int $teacherId, int $classId, int $periodId): ?array
{
    $class = aprismGradeClassContext($pdo, $teacherId, $classId);
    if ($class === null) return null;
    $periodStmt = $pdo->prepare("SELECT academic_period_id, academic_level, semester, period_name FROM academic_periods WHERE academic_period_id=? AND school_year_id=? AND semester=? AND is_archived=0 LIMIT 1");
    $periodStmt->execute([$periodId, (int) $class['school_year_id'], (string) $class['semester']]);
    $period = $periodStmt->fetch(PDO::FETCH_ASSOC);
    if ($period === false) return ['class' => $class, 'period' => null, 'categories' => [], 'students' => []];
    $categoriesStmt = $pdo->prepare("SELECT ac.assessment_category_id, ac.category_name, ac.display_order, cmp.assessment_component_id, cmp.component_code, cmp.component_name, cmp.max_score, cmp.display_order AS component_order FROM assessment_categories ac INNER JOIN assessment_components cmp ON cmp.assessment_category_id=ac.assessment_category_id AND cmp.status='Active' WHERE ac.operational_class_id=? AND ac.academic_period_id=? AND ac.status='Active' ORDER BY ac.display_order, ac.assessment_category_id, cmp.display_order, cmp.assessment_component_id");
    $categoriesStmt->execute([$classId, $periodId]);
    $categories = []; $components = [];
    foreach ($categoriesStmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $categoryId = (int) $row['assessment_category_id']; if (!isset($categories[$categoryId])) $categories[$categoryId] = ['name' => $row['category_name'], 'components' => []]; $component = ['id' => (int) $row['assessment_component_id'], 'code' => $row['component_code'], 'name' => $row['component_name'], 'max_score' => $row['max_score']]; $categories[$categoryId]['components'][] = $component; $components[$component['id']] = $component; }
    $studentStmt = $pdo->prepare("SELECT sce.student_class_enrollment_id, s.student_number, CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name, s.suffix) AS student_name FROM student_class_enrollments sce INNER JOIN students s ON s.student_id=sce.student_id WHERE sce.operational_class_id=? AND sce.status='Active' ORDER BY s.last_name, s.first_name, s.student_number"); $studentStmt->execute([$classId]); $students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);
    $scores = []; if ($components !== []) { $ids = implode(',', array_fill(0, count($components), '?')); $scoreStmt = $pdo->prepare("SELECT student_class_enrollment_id, assessment_component_id, raw_score FROM student_assessment_scores WHERE assessment_component_id IN ($ids)"); $scoreStmt->execute(array_keys($components)); foreach ($scoreStmt->fetchAll(PDO::FETCH_ASSOC) as $score) $scores[$score['student_class_enrollment_id'] . '|' . $score['assessment_component_id']] = $score['raw_score']; }
    return ['class' => $class, 'period' => $period, 'categories' => array_values($categories), 'components' => array_values($components), 'students' => $students, 'scores' => $scores];
}

function aprismGradeImportHistory(PDO $pdo, int $teacherId): array
{
    $stmt = $pdo->prepare("SELECT gib.grade_import_batch_id, gib.school_year_snapshot, gib.semester_snapshot, gib.original_filename, gib.template_type, gib.status, gib.reviewed_at, gib.confirmed_at, s.subject_name, sec.section_name FROM grade_import_batches gib INNER JOIN operational_classes oc ON oc.operational_class_id=gib.operational_class_id INNER JOIN subjects s ON s.subject_id=oc.subject_id INNER JOIN sections sec ON sec.section_id=oc.section_id WHERE gib.teacher_id=? ORDER BY gib.created_at DESC"); $stmt->execute([$teacherId]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
