<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helper/academic_period_protection_helper.php';

function academicPeriodProtectionAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE academic_periods (academic_period_id INTEGER PRIMARY KEY)');
$pdo->exec('CREATE TABLE assessment_categories (assessment_category_id INTEGER PRIMARY KEY, academic_period_id INTEGER NOT NULL)');
$pdo->exec('CREATE TABLE assessment_components (assessment_component_id INTEGER PRIMARY KEY, assessment_category_id INTEGER NOT NULL)');
$pdo->exec('CREATE TABLE student_assessment_scores (student_assessment_score_id INTEGER PRIMARY KEY, assessment_component_id INTEGER NOT NULL)');
$pdo->exec('CREATE TABLE risk_period_class_captures (risk_period_class_capture_id INTEGER PRIMARY KEY, academic_period_id INTEGER NOT NULL)');
$pdo->exec('INSERT INTO academic_periods (academic_period_id) VALUES (1), (2), (3)');
$pdo->exec('INSERT INTO assessment_categories VALUES (20, 2)');
$pdo->exec('INSERT INTO assessment_components VALUES (30, 20)');
$pdo->exec('INSERT INTO student_assessment_scores VALUES (40, 30)');
$pdo->exec('INSERT INTO risk_period_class_captures VALUES (50, 3)');

$base = [
    'academic_period_id' => 1,
    'school_year_id' => 10,
    'academic_level' => 'College',
    'semester' => 'First Semester',
    'period_name' => 'Prelim',
    'start_date' => '2026-08-01',
    'end_date' => '2026-09-01',
];
$dateChange = $base;
$dateChange['end_date'] = '2026-09-02';
academicPeriodProtectionAssert(
    !aprismAcademicPeriodHasProtectedChanges($pdo, $base, $dateChange),
    'An unreferenced period remains editable.'
);

$scorePeriod = $base;
$scorePeriod['academic_period_id'] = 2;
$snapshotPeriod = $base;
$snapshotPeriod['academic_period_id'] = 3;
$fieldChanges = [
    'school_year_id' => 11,
    'academic_level' => 'Senior High School',
    'semester' => 'Second Semester',
    'period_name' => 'Midterm',
    'start_date' => '2026-08-02',
    'end_date' => '2026-09-02',
];
foreach ($fieldChanges as $field => $value) {
    $proposed = $base;
    $proposed[$field] = $value;
    academicPeriodProtectionAssert(
        aprismAcademicPeriodHasProtectedChanges($pdo, $scorePeriod, $proposed),
        "A period with stored raw scores blocks a {$field} change."
    );
    academicPeriodProtectionAssert(
        aprismAcademicPeriodHasProtectedChanges($pdo, $snapshotPeriod, $proposed),
        "A period with historical risk capture blocks a {$field} change."
    );
}
academicPeriodProtectionAssert(
    !aprismAcademicPeriodHasProtectedChanges($pdo, $scorePeriod, $scorePeriod),
    'Submitting unchanged metadata does not count as a protected change.'
);

$action = file_get_contents(__DIR__ . '/../actions/academic_head/update_academic_period.php');
$capture = file_get_contents(__DIR__ . '/../includes/helper/risk_history_helper.php');
academicPeriodProtectionAssert(
    is_string($action)
        && str_contains($action, 'ROLE_ACADEMIC_HEAD')
        && str_contains($action, 'validateCsrfToken')
        && str_contains($action, 'FOR UPDATE')
        && str_contains($action, 'aprismAcademicPeriodHasProtectedChanges')
        && str_contains($action, 'Archived academic periods cannot be edited.'),
    'The update route retains authorization, CSRF, locking, and archived-period protection.'
);
academicPeriodProtectionAssert(
    is_string($capture)
        && preg_match('/FROM academic_periods\s+WHERE academic_period_id = \?\s+FOR UPDATE/', $capture) === 1,
    'Historical risk capture locks the period while reading its cutoff/context.'
);

echo "Academic Period protection checks passed: unreferenced edit, stored score guard, historical snapshot guard, no-op edit, auth/CSRF/archive contract, capture locking.\n";
