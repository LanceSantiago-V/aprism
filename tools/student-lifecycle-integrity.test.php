<?php

declare(strict_types=1);

// Local, rollback-only relational lifecycle check. It does not exercise the
// HTTP import screens or make fixture data permanent.
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../includes/helper/teacher_reports_helper.php';

function lifecycleCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

function lifecycleInsert(PDO $pdo, string $sql, array $params): int
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return (int) $pdo->lastInsertId();
}

function lifecycleScalar(PDO $pdo, string $sql, array $params): mixed
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
}

$base = $pdo->query("SELECT oc.teacher_id, oc.subject_id, sec.program_id FROM operational_classes oc INNER JOIN sections sec ON sec.section_id = oc.section_id INNER JOIN programs p ON p.program_id = sec.program_id AND p.academic_level = 'College' INNER JOIN school_years sy ON sy.school_year = oc.school_year AND sy.status = 'Active' WHERE sec.program_id IS NOT NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$years = $pdo->query("SELECT school_year_id, school_year, start_date FROM school_years ORDER BY start_date LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
$periods = $pdo->query("SELECT period_name, academic_period_id FROM academic_periods WHERE academic_level = 'College' AND semester = 'First Semester' AND is_archived = 0 AND school_year_id = (SELECT school_year_id FROM school_years WHERE status = 'Active' LIMIT 1)")->fetchAll(PDO::FETCH_KEY_PAIR);
if (!$base || count($years) < 2 || !isset($periods['Prelim'], $periods['Midterm'], $periods['Final'])) {
    fwrite(STDERR, "SKIP: College class, two School Years, and Prelim/Midterm/Final periods are required.\n");
    exit(2);
}

$token = strtoupper(bin2hex(random_bytes(4)));
$pdo->beginTransaction();
try {
    $sections = [];
    foreach (['Y1A' => '1', 'Y1B' => '1', 'Y2' => '2', 'Y3' => '3'] as $label => $level) {
        $sections[$label] = lifecycleInsert($pdo, "INSERT INTO sections (program_id, section_name, year_level, status) VALUES (?, ?, ?, 'Active')", [(int) $base['program_id'], "QA-LIFECYCLE-{$token}-{$label}", $level]);
    }
    $classes = [];
    foreach ([
        'first' => [$sections['Y1A'], $years[0]['school_year'], 'First Semester'],
        'second' => [$sections['Y1B'], $years[0]['school_year'], 'Second Semester'],
        'repeat' => [$sections['Y2'], $years[1]['school_year'], 'First Semester'],
    ] as $key => [$sectionId, $schoolYear, $semester]) {
        $classes[$key] = lifecycleInsert($pdo, "INSERT INTO operational_classes (teacher_id, subject_id, section_id, school_year, semester, academic_level, status) VALUES (?, ?, ?, ?, ?, 'College', 'Active')", [(int) $base['teacher_id'], (int) $base['subject_id'], $sectionId, $schoolYear, $semester]);
    }
    $student = lifecycleInsert($pdo, "INSERT INTO students (student_number, first_name, last_name, status) VALUES (?, 'APRISM', 'Lifecycle', 'Active')", ["QA-LIFECYCLE-{$token}"]);
    $irregular = lifecycleInsert($pdo, "INSERT INTO students (student_number, first_name, last_name, status) VALUES (?, 'APRISM', 'Irregular', 'Active')", ["QA-LIFECYCLE-{$token}-I"]);
    $unresolved = lifecycleInsert($pdo, "INSERT INTO students (student_number, first_name, last_name, status) VALUES (NULL, 'APRISM', 'Unresolved', 'Active')", []);
    $placements = [];
    foreach ([
        'first' => [$student, $years[0]['school_year_id'], 'First Semester', $sections['Y1A'], '1', $years[0]['start_date']],
        'second' => [$student, $years[0]['school_year_id'], 'Second Semester', $sections['Y1B'], '1', $years[0]['start_date']],
        'next' => [$student, $years[1]['school_year_id'], 'First Semester', $sections['Y2'], '2', $years[1]['start_date']],
        'irregular' => [$irregular, $years[1]['school_year_id'], 'First Semester', $sections['Y3'], '3', $years[1]['start_date']],
    ] as $key => [$studentId, $yearId, $semester, $sectionId, $yearLevel, $start]) {
        $placements[$key] = lifecycleInsert($pdo, "INSERT INTO student_academic_enrollments (student_id, school_year_id, semester, academic_level, program_id, section_id, year_level, status, effective_start) VALUES (?, ?, ?, 'College', ?, ?, ?, 'Active', ?)", [$studentId, $yearId, $semester, (int) $base['program_id'], $sectionId, $yearLevel, $start]);
    }
    $attempts = [];
    foreach ([
        'first' => [$student, $placements['first'], $classes['first']],
        'second' => [$student, $placements['second'], $classes['second']],
        'repeat' => [$student, $placements['next'], $classes['repeat']],
        'irregular' => [$irregular, $placements['irregular'], $classes['repeat']],
        'unresolved' => [$unresolved, null, $classes['first']],
    ] as $key => [$studentId, $placementId, $classId]) {
        $attempts[$key] = lifecycleInsert($pdo, "INSERT INTO student_class_enrollments (student_id, enrollment_id, operational_class_id, status) VALUES (?, ?, ?, 'Active')", [$studentId, $placementId, $classId]);
    }

    lifecycleCheck((int) lifecycleScalar($pdo, 'SELECT COUNT(*) FROM students WHERE student_id = ? AND student_number = ?', [$student, "QA-LIFECYCLE-{$token}"]) === 1, 'Student Number resolves to one permanent Student');
    lifecycleCheck(count(array_unique([$placements['first'], $placements['second'], $placements['next']])) === 3, 'Semester and next-year placement are distinct SAE records');
    lifecycleCheck(count(array_unique([$attempts['first'], $attempts['second'], $attempts['repeat']])) === 3, 'Repeated subject and later terms have distinct class attempts');
    lifecycleCheck((int) lifecycleScalar($pdo, 'SELECT COUNT(*) FROM student_class_enrollments WHERE student_id = ? AND enrollment_id = ? AND operational_class_id = ?', [$irregular, $placements['irregular'], $classes['repeat']]) === 1, 'Year 3 placement can participate in a Year 2 class');
    lifecycleCheck((int) lifecycleScalar($pdo, 'SELECT COUNT(*) FROM student_class_enrollments WHERE student_class_enrollment_id = ? AND operational_class_id = ?', [$attempts['first'], $classes['first']]) === 1, 'Section change leaves original class attempt attached');

    $components = [];
    foreach (['Prelim', 'Midterm', 'Final'] as $periodName) {
        $category = lifecycleInsert($pdo, 'INSERT INTO assessment_categories (operational_class_id, academic_period_id, category_name) VALUES (?, ?, ?)', [$classes['first'], $periods[$periodName], "QA-{$token}"]);
        $components[$periodName] = lifecycleInsert($pdo, "INSERT INTO assessment_components (assessment_category_id, component_code, component_name, max_score) VALUES (?, 'QA1', 'QA lifecycle score', 100)", [$category]);
        lifecycleInsert($pdo, 'INSERT INTO student_assessment_scores (student_class_enrollment_id, assessment_component_id, raw_score) VALUES (?, ?, ?)', [$attempts['first'], $components[$periodName], 70]);
    }
    lifecycleCheck((int) lifecycleScalar($pdo, 'SELECT COUNT(*) FROM student_assessment_scores WHERE student_class_enrollment_id = ?', [$attempts['first']]) === 3, 'Prelim, Midterm, and Final scores share one SCE without overwriting periods');
    lifecycleCheck((int) lifecycleScalar($pdo, 'SELECT COUNT(*) FROM student_assessment_scores WHERE student_class_enrollment_id = ? AND assessment_component_id = ?', [$attempts['first'], $components['Final']]) === 1, 'Final score remains attached to original term');

    $schedule = lifecycleInsert($pdo, "INSERT INTO class_schedules (operational_class_id, day, start_time, end_time, room) VALUES (?, 'Monday', '08:00:00', '09:00:00', ?)", [$classes['first'], "QA-{$token}"]);
    $session = lifecycleInsert($pdo, "INSERT INTO attendance_sessions (operational_class_id, class_schedule_id, meeting_date, scheduled_day, scheduled_start_time, scheduled_end_time, scheduled_room) VALUES (?, ?, ?, 'Monday', '08:00:00', '09:00:00', ?)", [$classes['first'], $schedule, $years[0]['start_date'], "QA-{$token}"]);
    lifecycleInsert($pdo, "INSERT INTO student_attendance_records (attendance_session_id, student_class_enrollment_id, status) VALUES (?, ?, 'Absent')", [$session, $attempts['first']]);
    lifecycleCheck((int) lifecycleScalar($pdo, 'SELECT COUNT(*) FROM student_attendance_records WHERE student_class_enrollment_id = ?', [$attempts['first']]) === 1, 'Old attendance stays with the original class attempt');

    $number = "QA-LIFECYCLE-{$token}-R";
    $stmt = $pdo->prepare('UPDATE students SET student_number = ? WHERE student_id = ? AND student_number IS NULL');
    $stmt->execute([$number, $unresolved]);
    lifecycleCheck($stmt->rowCount() === 1 && (int) lifecycleScalar($pdo, 'SELECT student_id FROM students WHERE student_number = ?', [$number]) === $unresolved && (int) lifecycleScalar($pdo, 'SELECT student_id FROM student_class_enrollments WHERE student_class_enrollment_id = ?', [$attempts['unresolved']]) === $unresolved, 'Later Student Number reconciliation preserves Student and SCE IDs');

    lifecycleCheck((int) lifecycleScalar($pdo, 'SELECT COUNT(*) FROM student_class_enrollments WHERE student_id = ?', [$student]) === 3, 'Later placement and class attempts leave earlier SCE rows intact');
    $pdo->prepare("UPDATE student_class_enrollments SET status = 'Completed' WHERE student_class_enrollment_id = ?")->execute([$attempts['first']]);
    $pdo->prepare("UPDATE students SET status = 'Inactive' WHERE student_id = ?")->execute([$student]);
    $reportStudents = aprismTeacherReportStudents($pdo, (int) $base['teacher_id'], $classes['first']);
    $reportRows = aprismTeacherAttendanceReportRows($pdo, (int) $base['teacher_id'], $classes['first'], $student);
    lifecycleCheck(
        count(array_filter($reportStudents, static fn(array $row): bool => (int) $row['student_id'] === $student)) === 1
            && count($reportRows) === 1
            && (int) $reportRows[0]['absent_count'] === 1,
        'Teacher report retains completed attempt and inactive Student history'
    );
    $pdo->rollBack();
    lifecycleCheck((int) lifecycleScalar($pdo, 'SELECT COUNT(*) FROM students WHERE student_number = ?', ["QA-LIFECYCLE-{$token}"]) === 0, 'Rollback leaves no synthetic Student');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'FAIL: ' . $exception->getMessage() . "\n");
    exit(1);
}
