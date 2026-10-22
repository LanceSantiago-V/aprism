<?php
declare(strict_types=1);

// Read-only local regression check for the loaded APRISM_QA_DEMO_V1 profile.
require_once __DIR__ . '/../config/pdo.php';
require_once __DIR__ . '/../includes/helper/risk_history_helper.php';
require_once __DIR__ . '/../_qa_fixtures/APRISM_QA_DEMO_V1/loader/fixture_validation.php';
qaDemoGuardLocalRuntime();

function riskHistoryAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$manifest = qaDemoManifest();
$context = qaDemoContext($pdo, $manifest, 'REFERENCE');
$class = qaDemoOne($pdo,
    'SELECT oc.operational_class_id FROM operational_classes oc
     JOIN users u ON u.user_id=oc.teacher_id
     JOIN subjects sub ON sub.subject_id=oc.subject_id
     JOIN sections sec ON sec.section_id=oc.section_id
     WHERE u.email=? AND sub.subject_name=? AND sec.section_name=?
       AND oc.school_year=? AND oc.semester=?',
    [$context['tuple']['teacher_email'], $context['tuple']['subject_name'],
        $context['tuple']['section_name'], $context['tuple']['school_year'],
        $context['tuple']['semester']], 'QA class');
$classId = (int) $class['operational_class_id'];
$prelim = $context['periodsByName']['Prelim'];
$midterm = $context['periodsByName']['Midterm'];
$prelimEvidence = aprismRiskHistoryClassEvidence($pdo, $classId, $prelim['end_date']);
$midtermEvidence = aprismRiskHistoryClassEvidence($pdo, $classId, $midterm['end_date']);
riskHistoryAssert(count($prelimEvidence) === 14 && count($midtermEvidence) === 14, 'Fixture cohort changed.');
$prelimByStudent = array_column($prelimEvidence, null, 'student_id');
$midtermByStudent = array_column($midtermEvidence, null, 'student_id');
$numberStmt = $pdo->prepare('SELECT student_id FROM students WHERE student_number=?');
$numberStmt->execute(['2000992001']);
$averyId = (int) $numberStmt->fetchColumn();
riskHistoryAssert($averyId > 0, 'Fixture Student missing.');
riskHistoryAssert((int) $prelimByStudent[$averyId]['recorded_count']
    < (int) $midtermByStudent[$averyId]['recorded_count'], 'Future meetings entered the earlier cutoff.');
$later = $pdo->prepare(
    'SELECT COUNT(*) FROM student_attendance_records sar
     JOIN attendance_sessions ats ON ats.attendance_session_id=sar.attendance_session_id
     WHERE sar.student_class_enrollment_id=? AND ats.meeting_date>?'
);
$later->execute([(int) $prelimByStudent[$averyId]['student_class_enrollment_id'], $prelim['end_date']]);
riskHistoryAssert((int) $later->fetchColumn() > 0, 'Cutoff test has no later evidence.');

$before = $pdo->prepare('SELECT risk_period_class_capture_id,captured_at FROM risk_period_class_captures WHERE academic_period_id=? AND operational_class_id=?');
$before->execute([(int) $prelim['academic_period_id'], $classId]);
$capture = $before->fetch(PDO::FETCH_ASSOC);
riskHistoryAssert($capture !== false, 'Prelim capture missing.');
$again = aprismRiskHistoryCaptureClass($pdo, (int) $prelim['academic_period_id'], $classId, 'FixtureReconstruction');
riskHistoryAssert($again === (int) $capture['risk_period_class_capture_id'], 'Capture was duplicated.');
$before->execute([(int) $prelim['academic_period_id'], $classId]);
riskHistoryAssert($before->fetch(PDO::FETCH_ASSOC) === $capture, 'Historical capture changed.');

$multiClass = aprismRiskHistoryStudentTierCounts([
    ['student_id' => 10, 'tier' => 1],
    ['student_id' => 10, 'tier' => 3],
    ['student_id' => 11, 'tier' => 2],
    ['student_id' => 12, 'tier' => null],
]);
riskHistoryAssert($multiClass['student_count'] === 3
    && $multiClass['tiers'] === [3 => 1, 2 => 1, 1 => 0],
    'Highest-tier Student aggregation failed.');

$trend = aprismRiskHistoryTrend($pdo, 'College');
riskHistoryAssert(count($trend) === 4, 'College period setup changed.');
riskHistoryAssert($trend[0]['tiers'] === [3 => 0, 2 => 1, 1 => 11]
    && $trend[1]['tiers'] === [3 => 1, 2 => 1, 1 => 12],
    'Period-tier results changed.');
riskHistoryAssert(!$trend[2]['available'] && !$trend[3]['available']
    && !$trend[2]['completed'] && !$trend[3]['completed'],
    'Future period was presented as captured.');
riskHistoryAssert($trend[0]['captured_attempts'] === 14
    && $trend[0]['eligible_attempts'] > 14 && $trend[0]['partial'],
    'Partial institutional coverage was hidden.');

$current = aprismMonitoringStudentRiskSummaries(
    aprismMonitoringCurrentClassRiskEvidence($pdo, 'disciplinary')
);
$currentCounts = [3 => 0, 2 => 0, 1 => 0];
foreach ($current as $student) {
    if (isset($currentCounts[$student['tier'] ?? 0])) {
        $currentCounts[$student['tier']]++;
    }
}
riskHistoryAssert($currentCounts === [3 => 1, 2 => 1, 1 => 12], 'Current evaluator changed.');
echo "Risk history fixture checks passed: cutoff, idempotence, Student de-duplication, period points, future gaps, current tiers.\n";
