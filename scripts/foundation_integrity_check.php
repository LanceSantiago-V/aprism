<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/pdo.php';

$checks = [
    'active_roles' => [
        "SELECT GROUP_CONCAT(role_name ORDER BY role_id) FROM roles",
        'Technical Administrator,Academic Head,Teacher,Disciplinary Officer',
    ],
    'duplicate_student_numbers' => [
        "SELECT COUNT(*) FROM (SELECT student_number FROM students WHERE student_number IS NOT NULL GROUP BY student_number HAVING COUNT(*) > 1) duplicates",
        '0',
    ],
    'duplicate_class_participation' => [
        "SELECT COUNT(*) FROM (SELECT operational_class_id, student_id FROM student_class_enrollments GROUP BY operational_class_id, student_id HAVING COUNT(*) > 1) duplicates",
        '0',
    ],
    'orphan_class_participation' => [
        "SELECT COUNT(*) FROM student_class_enrollments sce LEFT JOIN students s ON s.student_id = sce.student_id WHERE s.student_id IS NULL",
        '0',
    ],
    'orphan_guidance_cases' => [
        "SELECT COUNT(*) FROM guidance_cases gc LEFT JOIN teacher_referrals tr ON tr.teacher_referral_id = gc.teacher_referral_id WHERE tr.teacher_referral_id IS NULL",
        '0',
    ],
    'orphan_guidance_followups' => [
        "SELECT COUNT(*) FROM guidance_followups gf LEFT JOIN guidance_cases gc ON gc.guidance_case_id = gf.guidance_case_id WHERE gc.guidance_case_id IS NULL",
        '0',
    ],
    'duplicate_current_apriltag_ownership' => [
        "SELECT COUNT(*) FROM (SELECT current_student_id FROM april_tags WHERE current_student_id IS NOT NULL GROUP BY current_student_id HAVING COUNT(*) > 1) duplicates",
        '0',
    ],
    'orphan_apriltag_assignments' => [
        "SELECT COUNT(*) FROM april_tag_assignments ata LEFT JOIN april_tags at ON at.april_tag_id = ata.april_tag_id LEFT JOIN students st ON st.student_id = ata.student_id WHERE at.april_tag_id IS NULL OR st.student_id IS NULL",
        '0',
    ],
    'duplicate_attendance_records' => [
        "SELECT COUNT(*) FROM (SELECT attendance_session_id, student_class_enrollment_id FROM student_attendance_records GROUP BY attendance_session_id, student_class_enrollment_id HAVING COUNT(*) > 1) duplicates",
        '0',
    ],
    'orphan_attendance_records' => [
        "SELECT COUNT(*) FROM student_attendance_records sar LEFT JOIN attendance_sessions ats ON ats.attendance_session_id = sar.attendance_session_id LEFT JOIN student_class_enrollments sce ON sce.student_class_enrollment_id = sar.student_class_enrollment_id WHERE ats.attendance_session_id IS NULL OR sce.student_class_enrollment_id IS NULL",
        '0',
    ],
    'orphan_grade_scores' => [
        "SELECT COUNT(*) FROM student_assessment_scores sas LEFT JOIN student_class_enrollments sce ON sce.student_class_enrollment_id = sas.student_class_enrollment_id LEFT JOIN assessment_components ac ON ac.assessment_component_id = sas.assessment_component_id WHERE sce.student_class_enrollment_id IS NULL OR ac.assessment_component_id IS NULL",
        '0',
    ],
];

$failed = false;
foreach ($checks as $name => [$sql, $expected]) {
    $actual = (string) $pdo->query($sql)->fetchColumn();
    $passed = hash_equals($expected, $actual);
    echo sprintf("%s: %s%s", $name, $passed ? 'PASS' : 'FAIL', PHP_EOL);
    if (!$passed) {
        fwrite(STDERR, sprintf("  expected=%s actual=%s%s", $expected, $actual, PHP_EOL));
        $failed = true;
    }
}

exit($failed ? 1 : 0);
