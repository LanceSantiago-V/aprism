<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helper/grade_ledger_helper.php';

function gradeContextAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
        exit(1);
    }
    echo "PASS: {$message}" . PHP_EOL;
}

$periodCases = [
    ['College', 'First Semester', ['Prelim', 'Midterm', 'Pre-Final', 'Final']],
    ['College', 'Second Semester', ['Prelim', 'Midterm', 'Pre-Final', 'Final']],
    ['Senior High School', 'First Semester', ['Quarter 1', 'Quarter 2']],
    ['Senior High School', 'Second Semester', ['Quarter 3', 'Quarter 4']],
    ['Senior High School', 'Unknown', []],
];
foreach ($periodCases as [$level, $semester, $expected]) {
    gradeContextAssert(
        aprismGradeApplicablePeriodNames($level, $semester) === $expected,
        "{$level} / {$semester} resolves only its applicable term periods"
    );
}

$read = static fn(string $path): string => (string) file_get_contents(__DIR__ . '/../' . $path);
$preview = $read('actions/teacher/preview_grade_import.php');
$confirm = $read('actions/teacher/confirm_grade_import.php');
$resolution = $read('actions/teacher/resolve_operational_class_academic_level.php');
$gradePage = $read('dashboard/teacher_grade_import.php');
$ledger = $read('includes/helper/grade_ledger_helper.php');
$analytics = $read('includes/helper/grade_analytics_helper.php');
$migration = $read('database/migrations/20261014_add_operational_class_academic_level.sql');

gradeContextAssert(
    str_contains($gradePage, 'oc.academic_level') && str_contains($gradePage, 'p.academic_level AS suggested_academic_level'),
    'Grade Import reads the saved class level and labels Program level only as a suggestion'
);
gradeContextAssert(
    str_contains($preview, '$templateAcademicLevel !== $classContext[\'academic_level\']')
        && str_contains($preview, "'academic_level' => \$classContext['academic_level']"),
    'Preview blocks a mismatched workbook and snapshots the saved class level'
);
gradeContextAssert(
    str_contains($confirm, 'oc.academic_level')
        && str_contains($confirm, 'academic_level=? AND is_archived=0')
        && str_contains($confirm, '$lockedClass[\'academic_level\']'),
    'Confirmation revalidates class level and Academic Period scope under lock'
);
gradeContextAssert(
    str_contains($confirm, '$e instanceof PDOException')
        && str_contains($confirm, 'The Grade Import could not be confirmed. No changes were saved.'),
    'Confirmation hides database exception details after rollback'
);
gradeContextAssert(
    str_contains($resolution, 'validateCsrfToken')
        && str_contains($resolution, 'oc.teacher_id=?')
        && str_contains($resolution, 'academic_level IS NULL')
        && str_contains($resolution, 'assessment_categories'),
    'Legacy resolution is Teacher-owned, CSRF-protected, NULL-only, and checked against existing grade context'
);
gradeContextAssert(
    str_contains($ledger, 'academic_level=?')
        && str_contains($ledger, 'period_name IN ($placeholders)')
        && str_contains($analytics, 'ap.academic_level = ?')
        && str_contains($analytics, 'ap.period_name IN ($periodPlaceholders)'),
    'Grade Ledger and Analytics scope periods to class level and teaching term'
);
gradeContextAssert(
    str_contains($migration, "ENUM('College', 'Senior High School') NULL")
        && str_contains($migration, 'no historical value is inferred or backfilled'),
    'Migration adds a controlled nullable value without backfilling legacy classes'
);
