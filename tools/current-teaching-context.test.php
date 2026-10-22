<?php

declare(strict_types=1);

// Rollback-only integration test against the local APRISM schema.
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../includes/helper/current_teaching_context_helper.php';
require_once __DIR__ . '/../includes/helper/section_context_helper.php';
require_once __DIR__ . '/../includes/helper/teacher_dashboard_helper.php';

function termCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

$year = $pdo->query("SELECT school_year_id, school_year FROM school_years WHERE status = 'Active' LIMIT 1")
    ->fetch(PDO::FETCH_ASSOC) ?: null;
$base = $pdo->query("SELECT oc.teacher_id, oc.subject_id, p.program_id FROM operational_classes oc
    INNER JOIN sections sec ON sec.section_id = oc.section_id
    INNER JOIN programs p ON p.program_id = sec.program_id AND p.academic_level = 'College'
    WHERE oc.status = 'Active' LIMIT 1") ->fetch(PDO::FETCH_ASSOC) ?: null;
if ($year === null || $base === null) {
    fwrite(STDERR, "SKIP: Active School Year and a College Program/class reference are required.\n");
    exit(2);
}

$name = 'QA-TERM-' . strtoupper(bin2hex(random_bytes(4)));
$pdo->beginTransaction();
try {
    $sectionStmt = $pdo->prepare("INSERT INTO sections (program_id, section_name, year_level, status) VALUES (?, ?, '1', 'Active')");
    $sectionStmt->execute([(int) $base['program_id'], $name]);
    $sectionId = (int) $pdo->lastInsertId();
    $classes = [];
    foreach (['First Semester', 'Second Semester'] as $term) {
        $classStmt = $pdo->prepare("INSERT INTO operational_classes
            (teacher_id, subject_id, section_id, school_year, semester, academic_level, status)
            VALUES (?, ?, ?, ?, ?, 'College', 'Active')");
        $classStmt->execute([(int) $base['teacher_id'], (int) $base['subject_id'], $sectionId, $year['school_year'], $term]);
        $classes[$term] = (int) $pdo->lastInsertId();
    }

    $key = aprismTeachingContextSettingKey((int) $year['school_year_id'], 'College');
    setSystemSetting($pdo, $key, 'First Semester', (int) $base['teacher_id']);
    termCheck(aprismClassIsInCurrentTeachingContext($pdo, $classes['First Semester']), 'First Semester class is current when First Semester is selected');
    termCheck(!aprismClassIsInCurrentTeachingContext($pdo, $classes['Second Semester']), 'Second Semester class is not current before the switch');
    $periodStmt = $pdo->prepare("SELECT academic_period_id FROM academic_periods
        WHERE school_year_id = ? AND academic_level = 'College' AND semester = 'First Semester'
          AND is_archived = 0 ORDER BY start_date LIMIT 2");
    $periodStmt->execute([(int) $year['school_year_id']]);
    $periods = $periodStmt->fetchAll(PDO::FETCH_COLUMN);
    if (count($periods) >= 2) {
        termCheck(!str_contains(aprismCurrentClassCondition('oc'), 'academic_periods')
            && aprismClassIsInCurrentTeachingContext($pdo, $classes['First Semester']),
            'Current class selection is independent of grading-period dates within the semester');
    }
    aprismTeacherDashboardSummary($pdo, (int) $base['teacher_id'], (string) $year['school_year']);
    aprismTeacherDashboardSchedule($pdo, (int) $base['teacher_id'], (string) $year['school_year']);
    aprismTeacherReferralClasses($pdo, (int) $base['teacher_id'], (string) $year['school_year']);
    aprismTeacherMonitoringStudents($pdo, (int) $base['teacher_id'], (string) $year['school_year']);
    aprismTeacherMonitoringAttendance($pdo, (int) $base['teacher_id'], (string) $year['school_year']);
    aprismMonitoringCurrentClassRiskEvidence($pdo, 'disciplinary');
    termCheck(true, 'Teacher dashboard, referral, monitoring, and DO risk queries accept the current-term predicate');

    $historyTables = ['operational_classes', 'student_academic_enrollments', 'student_class_enrollments',
        'attendance_sessions', 'teacher_referrals', 'student_assessment_scores'];
    $historyCounts = [];
    foreach ($historyTables as $table) {
        $historyCounts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }
    setSystemSetting($pdo, $key, 'Second Semester', (int) $base['teacher_id']);
    termCheck(!aprismClassIsInCurrentTeachingContext($pdo, $classes['First Semester']), 'Old Active class stops being current after the switch');
    termCheck(aprismClassIsInCurrentTeachingContext($pdo, $classes['Second Semester']), 'Second Semester class becomes current');
    $stmt = $pdo->prepare('SELECT status FROM operational_classes WHERE operational_class_id = ?');
    $stmt->execute([$classes['First Semester']]);
    termCheck($stmt->fetchColumn() === 'Active', 'Teaching-context switch does not rewrite class status or delete history');
    foreach ($historyCounts as $table => $count) {
        termCheck((int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() === $count,
            "Teaching-context switch leaves {$table} records unchanged");
    }

    termCheck(aprismParseSectionName('BSIT1.1A')['semester_candidate'] === 'First Semester', 'College first-term Section recognized');
    termCheck(aprismParseSectionName('BSIT1.2A')['semester_candidate'] === 'Second Semester', 'College second-term Section recognized');
    termCheck(aprismParseSectionName('ICTMAWD111')['semester_candidate'] === 'First Semester', 'SHS first context recognized');
    termCheck(aprismParseSectionName('ICTMAWD112')['semester_candidate'] === 'Second Semester', 'SHS second context recognized');
    termCheck(aprismTeachingContextSettingKey((int) $year['school_year_id'], 'Senior High School') !== $key,
        'SHS selection is independent from College');
} finally {
    $pdo->rollBack();
}
