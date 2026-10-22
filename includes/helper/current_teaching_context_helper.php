<?php

declare(strict_types=1);

require_once __DIR__ . '/system_settings_helper.php';

/** The institution selects one teaching context per academic level and School Year. */
function aprismTeachingContextSettingKey(int $schoolYearId, string $academicLevel): string
{
    if ($schoolYearId <= 0 || !in_array($academicLevel, ['College', 'Senior High School'], true)) {
        throw new InvalidArgumentException('Invalid academic context.');
    }

    return 'current_teaching_context_' . $schoolYearId . '_'
        . ($academicLevel === 'College' ? 'college' : 'shs');
}

function aprismCurrentTeachingContext(PDO $pdo, int $schoolYearId, string $academicLevel): ?string
{
    $value = getSystemSetting($pdo, aprismTeachingContextSettingKey($schoolYearId, $academicLevel));
    return in_array($value, ['First Semester', 'Second Semester'], true) ? $value : null;
}

/**
 * SQL condition for an Operational Class in the selected institutional term.
 * The class's stored academic level is authoritative; a legacy NULL level can
 * use its stored Section -> Program relationship, never a parsed Section name.
 * Callers must separately enforce teacher/role scope and class status.
 */
function aprismCurrentClassCondition(string $alias = 'oc'): string
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
        throw new InvalidArgumentException('Invalid class alias.');
    }

    return "EXISTS (
        SELECT 1 FROM school_years current_sy
        INNER JOIN system_settings current_term
          ON current_term.setting_key = CONCAT('current_teaching_context_', current_sy.school_year_id, '_',
             CASE COALESCE($alias.academic_level, (
                 SELECT current_program.academic_level FROM sections current_section
                 INNER JOIN programs current_program ON current_program.program_id = current_section.program_id
                 WHERE current_section.section_id = $alias.section_id
             )) WHEN 'College' THEN 'college' WHEN 'Senior High School' THEN 'shs' ELSE 'unresolved' END)
         AND current_term.setting_value = $alias.semester
        WHERE current_sy.status = 'Active'
          AND current_sy.school_year = $alias.school_year
    )";
}

function aprismClassIsInCurrentTeachingContext(PDO $pdo, int $classId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM operational_classes oc WHERE oc.operational_class_id = ? AND '
        . aprismCurrentClassCondition('oc') . ' LIMIT 1');
    $stmt->execute([$classId]);
    return (bool) $stmt->fetchColumn();
}
