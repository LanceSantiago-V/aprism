<?php

declare(strict_types=1);

/**
 * Recognize STI Section naming during setup only. Stored Section and class
 * relationships remain authoritative after the Teacher confirms the import.
 * Unrecognized historical names are deliberately left alone.
 *
 * @return array<string, mixed>
 */
function aprismParseSectionName(string $sectionName): array
{
    $sectionName = trim($sectionName);
    $compact = strtoupper((string) preg_replace('/\s+/u', '', $sectionName));
    $result = [
        'recognized' => false,
        'validation_state' => 'unrecognized',
        'confidence' => 'none',
        'program_code_candidate' => null,
        'program_id' => null,
        'academic_level' => null,
        'year_level_candidate' => null,
        'semester_candidate' => null,
        'section_suffix' => null,
        'period_family' => [],
        'section_record' => null,
        'message' => '',
    ];

    if (preg_match('/^([A-Z]+)([1-4])\.([12])([A-Z])$/D', $compact, $parts) === 1) {
        $result['recognized'] = true;
        $result['confidence'] = 'confirmed_college_convention';
        $result['program_code_candidate'] = $parts[1];
        $result['academic_level'] = 'College';
        $result['year_level_candidate'] = $parts[2];
        $result['semester_candidate'] = $parts[3] === '1' ? 'First Semester' : 'Second Semester';
        $result['section_suffix'] = $parts[4];
        $result['period_family'] = ['Prelim', 'Midterm', 'Pre-Final', 'Final'];
    } elseif (preg_match('/^([A-Z]+)(11|12)([12])$/D', $compact, $parts) === 1) {
        $result['recognized'] = true;
        $result['confidence'] = 'confirmed_shs_convention';
        $result['program_code_candidate'] = $parts[1];
        $result['academic_level'] = 'Senior High School';
        $result['year_level_candidate'] = $parts[2];
        $result['semester_candidate'] = $parts[3] === '1' ? 'First Semester' : 'Second Semester';
        $result['period_family'] = $parts[3] === '1' ? ['Quarter 1', 'Quarter 2'] : ['Quarter 3', 'Quarter 4'];
    } elseif (preg_match('/^[A-Z]+[1-9]\.[1-9][A-Z]$/D', $compact) === 1 || preg_match('/^[A-Z]+(?:11|12)[1-9]$/D', $compact) === 1) {
        $result['validation_state'] = 'invalid_convention';
        $result['message'] = 'This Section resembles an institutional Section code but its year or teaching context is invalid. Review the Section name before continuing.';
        return $result;
    } else {
        return $result;
    }

    return $result;
}

/**
 * Validate a parsed setup candidate against active Program and stored Section
 * relationships. This is never used to recalculate historical record context.
 *
 * @return array<string, mixed>
 */
function aprismSectionSetupContext(PDO $pdo, string $sectionName, ?string $currentSemester = null): array
{
    $sectionName = trim($sectionName);
    $result = aprismParseSectionName($sectionName);
    if (!$result['recognized']) {
        return $result;
    }

    $code = $result['program_code_candidate'];
    $year = $result['year_level_candidate'];
    $semester = $result['semester_candidate'];
    $summary = $code . ', ' . ($result['academic_level'] === 'College' ? 'Year ' : 'Grade ') . $year . ', ' . $semester;
    if ($result['section_suffix'] !== null) {
        $summary .= ', Section ' . $result['section_suffix'];
    }

    $programStmt = $pdo->prepare("SELECT program_id, academic_level FROM programs WHERE status = 'Active' AND UPPER(TRIM(program_code)) = ? LIMIT 2");
    $programStmt->execute([$code]);
    $programs = $programStmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($programs) !== 1) {
        $result['validation_state'] = count($programs) > 1 ? 'ambiguous_program' : 'unresolved_program';
        $result['message'] = count($programs) > 1
            ? 'More than one active Program matches ' . $code . '. Ask Academic Head to resolve the ambiguity.'
            : 'The Section suggests ' . $summary . ', but ' . $code . ' is not an active institutional Program or Strand. Ask Academic Head to review it.';
        return $result;
    }

    $program = $programs[0];
    if ((string) $program['academic_level'] !== $result['academic_level']) {
        $result['validation_state'] = 'conflict';
        $result['message'] = 'The Section suggests ' . $summary . ', but ' . $code . ' is registered for a different Academic Level. Review the Section before continuing.';
        return $result;
    }
    $result['program_id'] = (int) $program['program_id'];

    if ($currentSemester !== null && trim($currentSemester) !== '' && strcasecmp(trim($currentSemester), $semester) !== 0) {
        $result['validation_state'] = 'conflict';
        $result['message'] = 'The Section suggests ' . $summary . ', but the current teaching context is ' . $currentSemester . '. Use a Section for the current context.';
        return $result;
    }

    $compactName = strtoupper((string) preg_replace('/\s+/u', '', $sectionName));
    $sectionStmt = $pdo->prepare("SELECT section_id, section_name, program_id, year_level FROM sections WHERE status = 'Active' AND UPPER(REPLACE(TRIM(section_name), ' ', '')) = ? LIMIT 2");
    $sectionStmt->execute([$compactName]);
    $sections = $sectionStmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($sections) > 1) {
        $result['validation_state'] = 'ambiguous_section';
        $result['message'] = 'More than one active Section has this name. Ask Academic Head to identify the correct Section.';
        return $result;
    }
    if (count($sections) === 1) {
        $existing = $sections[0];
        $result['section_record'] = $existing;
        if ($existing['program_id'] === null || $existing['year_level'] === null) {
            $result['validation_state'] = 'incomplete_section';
            $result['message'] = 'The Section suggests ' . $summary . ', but its stored Program or Year Level is still unconfirmed. Ask Academic Head to review it before using this Section.';
            return $result;
        }
        if ((int) $existing['program_id'] !== $result['program_id'] || (string) $existing['year_level'] !== $year) {
            $result['validation_state'] = 'conflict';
            $result['message'] = 'The Section suggests ' . $summary . ', but its stored Program or Year Level differs. Review the Section before continuing.';
            return $result;
        }
    }

    $result['validation_state'] = 'validated';
    $result['message'] = $summary . '. APRISM will use the confirmed Section and current Academic Setup context.';
    if ($result['section_record'] !== null && (string) $result['section_record']['section_name'] !== $sectionName) {
        $result['message'] .= ' Existing Section: ' . $result['section_record']['section_name'] . '.';
    }
    return $result;
}
