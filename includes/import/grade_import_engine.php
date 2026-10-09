<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/** Term-workbook extraction and period-scoped matching. Persistence stays in confirm_grade_import.php. */
final class GradeImportEngine
{
    private const SHS_CODES = ['A1', 'A2', 'A3', 'A4', 'A5', 'HW1', 'HW2', 'HW3', 'HW4', 'HW5', 'Q1', 'Q2', 'Q3', 'P1', 'P2', 'P3', 'P4', 'EX'];
    private const COLLEGE_CODES = ['PTL1', 'PTL2', 'PTL3', 'PTL4', 'Q1', 'Q2', 'Q3', 'Q4', 'EX'];

    public function inspect(string $path, string $originalName): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) throw new InvalidArgumentException('Use an .xlsx, .xls, or .csv file.');
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($path);
        $candidate = null;
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $matrix = $sheet->toArray(null, false, false, false);
            $profile = $this->detectProfile($matrix);
            if ($profile !== null) { $candidate = [$sheet->getTitle(), $matrix, $profile]; break; }
        }
        if ($candidate === null) throw new InvalidArgumentException('The workbook is not a supported SHS or College grading template.');
        [$sheetName, $matrix, $profile] = $candidate;
        $identityRow = $this->findIdentityRow($matrix);
        if ($identityRow === null) throw new InvalidArgumentException('Student Number and Student Name headers could not be located.');
        $periods = [];
        foreach ($this->findPeriodBlocks($matrix, $profile['type'], $identityRow) as $block) {
            $components = $this->findComponents($matrix, $profile['type'], $identityRow, $block['start'], $block['end']);
            $periods[] = ['period_key' => $block['key'], 'source_label' => $block['label'], 'components' => $components, 'categories' => $this->groupCategories($components), 'rows' => $this->extractRows($matrix, $identityRow, $components)];
        }
        if ($periods === []) throw new InvalidArgumentException('No supported grading-period blocks were detected.');
        return ['template_type' => $profile['type'], 'worksheet' => $sheetName, 'student_header_row' => $identityRow + 1, 'period_blocks' => $profile['period_blocks'], 'periods' => $periods, 'formula_policy' => 'Formula, TTL, EQV, weighted, transmuted, and final-grade cells are review-only and are not imported as raw scores.', 'source_filename' => $originalName];
    }

    private function detectProfile(array $matrix): ?array
    {
        $text = strtoupper(implode(' ', array_map(static fn (array $row): string => implode(' ', array_map(static fn ($value): string => (string) $value, $row)), array_slice($matrix, 0, 12))));
        if (str_contains($text, '1ST/3RD PERIODICAL') && str_contains($text, '2ND/4TH PERIODICAL')) return ['type' => 'SHS', 'period_blocks' => ['1ST/3RD PERIODICAL', '2ND/4TH PERIODICAL']];
        if (str_contains($text, 'PRELIMS') && str_contains($text, 'MIDTERM') && str_contains($text, 'PRE-FINAL')) return ['type' => 'College/Tertiary', 'period_blocks' => ['PRELIMS', 'MIDTERM', 'PRE-FINALS', 'FINALS']];
        return null;
    }

    private function findIdentityRow(array $matrix): ?int
    {
        foreach ($matrix as $index => $row) { $normalized = array_map(static fn ($value): string => strtolower(trim((string) $value)), $row); $hasNumber = in_array('student no.', $normalized, true) || in_array('stud no.', $normalized, true) || in_array('student number', $normalized, true); if ($hasNumber && in_array('student name', $normalized, true)) return $index; }
        return null;
    }

    private function findPeriodBlocks(array $matrix, string $profile, int $identityRow): array
    {
        $labels = $profile === 'SHS' ? [['key' => 'SHS_BLOCK_1', 'label' => '1ST/3RD PERIODICAL'], ['key' => 'SHS_BLOCK_2', 'label' => '2ND/4TH PERIODICAL']] : [['key' => 'Prelim', 'label' => 'PRELIMS'], ['key' => 'Midterm', 'label' => 'MIDTERM'], ['key' => 'Pre-Final', 'label' => 'PRE-FINALS'], ['key' => 'Final', 'label' => 'FINALS']];
        $found = [];
        for ($row = 0; $row < $identityRow; $row++) foreach ($matrix[$row] as $column => $value) foreach ($labels as $label) if (strtoupper(trim((string) $value)) === $label['label']) $found[$label['key']] = ['key' => $label['key'], 'label' => $label['label'], 'start' => (int) $column];
        if ($found === []) return [];
        $ordered = array_values(array_filter(array_map(static fn (array $label): ?array => $found[$label['key']] ?? null, $labels)));
        $lastColumn = max(0, count($matrix[$identityRow]) - 1);
        foreach ($ordered as $index => &$block) $block['end'] = ($ordered[$index + 1]['start'] ?? ($lastColumn + 1)) - 1;
        unset($block);
        return $ordered;
    }

    private function findComponents(array $matrix, string $profile, int $identityRow, int $start, int $end): array
    {
        $codes = $profile === 'SHS' ? self::SHS_CODES : self::COLLEGE_CODES; $found = [];
        for ($row = max(0, $identityRow - 5); $row < $identityRow; $row++) for ($column = max(0, $start); $column <= $end; $column++) { $code = strtoupper(trim((string) ($matrix[$row][$column] ?? ''))); if (!in_array($code, $codes, true) || isset($found[$code])) continue; $possible = $matrix[$row + 1][$column] ?? null; $found[$code] = ['code' => $code, 'column' => $column, 'max_score' => is_numeric($possible) ? (float) $possible : null, 'category_name' => $this->categoryFor($profile, $code)]; }
        return array_values($found);
    }

    private function extractRows(array $matrix, int $identityRow, array $components): array
    {
        $rows = [];
        for ($r = $identityRow + 1, $count = count($matrix); $r < $count; $r++) { $number = trim((string) ($matrix[$r][1] ?? '')); $name = trim((string) ($matrix[$r][2] ?? '')); if ($number === '' && $name === '') continue; $scores = []; $issues = []; if ($number === '' || $name === '') $issues[] = 'Student Number and name are both required.'; foreach ($components as $component) { $cell = $matrix[$r][$component['column']] ?? null; if ($cell === null || $cell === '') continue; if (is_string($cell) && str_starts_with(trim($cell), '=')) { $issues[] = 'Formula output was ignored for ' . $component['code'] . '.'; continue; } if (!is_numeric($cell)) { $issues[] = 'Score for ' . $component['code'] . ' is not numeric.'; continue; } $score = (float) $cell; if ($score < 0 || ($component['max_score'] !== null && $score > $component['max_score'])) { $issues[] = 'Score for ' . $component['code'] . ' is outside the supported range.'; continue; } $scores[] = ['code' => $component['code'], 'category_name' => $component['category_name'], 'score' => $score, 'max_score' => $component['max_score']]; } $rows[] = ['row' => $r + 1, 'student_number' => $number, 'student_name' => $name, 'issues' => array_values(array_unique($issues)), 'scores' => $scores]; }
        $seen = []; foreach ($rows as $index => $row) if ($row['student_number'] !== '') $seen[$row['student_number']][] = $index; foreach ($seen as $indexes) if (count($indexes) > 1) foreach ($indexes as $index) $rows[$index]['issues'][] = 'Student Number is duplicated in the workbook.';
        return $rows;
    }

    private function groupCategories(array $components): array { $categories = []; foreach ($components as $component) { $name = $component['category_name']; if (!isset($categories[$name])) $categories[$name] = ['category_name' => $name, 'components' => []]; $categories[$name]['components'][] = $component; } return array_values($categories); }
    private function categoryFor(string $profile, string $code): string { if ($profile === 'SHS') { if (str_starts_with($code, 'A')) return 'Activities'; if (str_starts_with($code, 'HW')) return 'Homework'; if (str_starts_with($code, 'Q')) return 'Quizzes'; if (str_starts_with($code, 'P')) return 'Performance Tasks'; return 'Exam'; } if (str_starts_with($code, 'PTL')) return 'PT/Lab Exercises'; if (str_starts_with($code, 'Q')) return 'Quizzes'; return 'Exam'; }


    public function match(PDO $pdo, array $data, int $operationalClassId, array $periodContexts): array { $contextByKey = []; foreach ($periodContexts as $context) $contextByKey[(string) $context['period_key']] = $context; foreach ($data['periods'] as &$period) { $context = $contextByKey[(string) $period['period_key']] ?? null; $period['academic_period_id'] = $context ? (int) $context['academic_period_id'] : null; $period['period_name'] = $context['period_name'] ?? null; $this->matchPeriod($pdo, $period, $operationalClassId); } unset($period); $data['matching_complete'] = true; return $data; }

    private function matchPeriod(PDO $pdo, array &$period, int $classId): void
    {
        $numbers = array_values(array_unique(array_filter(array_map(
            static fn(array $row): string => trim((string) ($row['student_number'] ?? '')),
            $period['rows']
        ))));
        $students = [];

        if ($numbers !== []) {
            $placeholders = implode(',', array_fill(0, count($numbers), '?'));
            $stmt = $pdo->prepare("\n                SELECT\n                    s.student_id,\n                    s.student_number,\n                    s.first_name,\n                    s.middle_name,\n                    s.last_name,\n                    s.suffix,\n                    s.status AS student_status,\n                    sce.student_class_enrollment_id\n                FROM students AS s\n                LEFT JOIN student_class_enrollments AS sce\n                    ON sce.student_id = s.student_id\n                   AND sce.operational_class_id = ?\n                   AND sce.status = 'Active'\n                WHERE s.student_number IN ($placeholders)\n            ");
            $stmt->execute(array_merge([$classId], $numbers));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $student) {
                $students[(string) $student['student_number']] = $student;
            }
        }

        $unresolvedParticipants = $this->loadUnresolvedParticipants($pdo, $classId);
        $componentMap = []; $codes = array_values(array_unique(array_map(static fn (array $component): string => (string) $component['code'], $period['components'])));
        if ($codes !== [] && $period['academic_period_id'] !== null) { $placeholders = implode(',', array_fill(0, count($codes), '?')); $stmt = $pdo->prepare("SELECT ac.category_name, cmp.component_code, cmp.assessment_component_id, cmp.max_score FROM assessment_categories ac INNER JOIN assessment_components cmp ON cmp.assessment_category_id=ac.assessment_category_id WHERE ac.operational_class_id=? AND ac.academic_period_id=? AND cmp.component_code IN ($placeholders)"); $stmt->execute(array_merge([$classId, $period['academic_period_id']], $codes)); foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $component) $componentMap[$component['category_name'] . '|' . $component['component_code']] = $component; }
        foreach ($period['rows'] as &$row) {
            $number = trim((string) ($row['student_number'] ?? ''));
            $duplicate = $number !== '' && count(array_filter(
                $period['rows'],
                static fn(array $candidate): bool => trim((string) ($candidate['student_number'] ?? '')) === $number
            )) > 1;

            if ($number === '') {
                $row['match_status'] = 'MISSING STUDENT NUMBER';
            } elseif ($duplicate) {
                $row['match_status'] = 'DUPLICATE STUDENT NUMBER IN WORKBOOK';
            } elseif (!isset($students[$number])) {
                $sameName = $this->findUnresolvedParticipantMatches(
                    $unresolvedParticipants,
                    (string) ($row['student_name'] ?? '')
                );

                if (count($sameName) === 1) {
                    $candidate = $sameName[0];
                    $row['match_status'] = 'IDENTITY RECONCILIATION REQUIRED';
                    $row['student_class_enrollment_id'] = (int) $candidate['student_class_enrollment_id'];
                    $row['identity_reconciliation'] = [
                        'mode' => 'attach_authoritative_number',
                        'candidate_student_id' => (int) $candidate['student_id'],
                        'candidate_student_class_enrollment_id' => (int) $candidate['student_class_enrollment_id'],
                        'candidate_name' => $this->displayName($candidate),
                        'incoming_student_number' => $number,
                        'incoming_student_name' => (string) ($row['student_name'] ?? ''),
                    ];
                    $row['matched_student_name'] = $this->displayName($candidate);
                } elseif (count($sameName) > 1) {
                    $row['match_status'] = 'IDENTITY RECONCILIATION AMBIGUOUS';
                } else {
                    $row['match_status'] = 'STUDENT NOT FOUND';
                }
            } elseif ($students[$number]['student_status'] !== 'Active') {
                $row['match_status'] = 'STUDENT NOT ACTIVE';
            } elseif ($students[$number]['student_class_enrollment_id'] === null) {
                $sameName = $this->findUnresolvedParticipantMatches(
                    $unresolvedParticipants,
                    (string) ($row['student_name'] ?? '')
                );
                $row['match_status'] = count($sameName) === 1
                    ? 'CANONICAL STUDENT EXISTS - RECONCILIATION REQUIRED'
                    : 'NOT ENROLLED IN SELECTED CLASS';
                if (count($sameName) === 1) {
                    $row['identity_reconciliation'] = [
                        'mode' => 'canonical_student_exists',
                        'candidate_student_id' => (int) $sameName[0]['student_id'],
                        'candidate_student_class_enrollment_id' => (int) $sameName[0]['student_class_enrollment_id'],
                        'candidate_name' => $this->displayName($sameName[0]),
                        'incoming_student_number' => $number,
                        'incoming_student_name' => (string) ($row['student_name'] ?? ''),
                    ];
                }
            } else {
                $incomingName = $this->normalizeWorkbookName((string) ($row['student_name'] ?? ''));
                $canonicalName = $this->normalizeCanonicalName($students[$number]);
                $row['student_class_enrollment_id'] = (int) $students[$number]['student_class_enrollment_id'];
                $row['matched_student_name'] = $this->displayName($students[$number]);
                if ($incomingName !== '' && $incomingName !== $canonicalName) {
                    $row['match_status'] = 'STUDENT NAME DISCREPANCY';
                    $row['name_discrepancy'] = [
                        'canonical_name' => $row['matched_student_name'],
                        'incoming_name' => (string) ($row['student_name'] ?? ''),
                    ];
                } else {
                    $row['match_status'] = 'MATCHED';
                }
            }

            foreach ($row['scores'] as &$score) {
                $existing = $componentMap[$score['category_name'] . '|' . $score['code']] ?? null;
                $score['existing_raw_score'] = null;
                $score['existing_score_state'] = $existing === null ? 'NEW COMPONENT' : 'STRUCTURE FOUND';
            }
            unset($score);
        }
        unset($row);
        $existingScores = []; if ($period['academic_period_id'] !== null) { $scoreStmt = $pdo->prepare("SELECT sas.student_class_enrollment_id, cmp.component_code, ac.category_name, sas.raw_score FROM student_assessment_scores sas INNER JOIN assessment_components cmp ON cmp.assessment_component_id=sas.assessment_component_id INNER JOIN assessment_categories ac ON ac.assessment_category_id=cmp.assessment_category_id WHERE ac.operational_class_id=? AND ac.academic_period_id=?"); $scoreStmt->execute([$classId, $period['academic_period_id']]); foreach ($scoreStmt->fetchAll(PDO::FETCH_ASSOC) as $score) $existingScores[$score['student_class_enrollment_id'] . '|' . $score['category_name'] . '|' . $score['component_code']] = (float) $score['raw_score']; }
        foreach ($period['rows'] as &$row) foreach ($row['scores'] as &$score) { $key = ((int) ($row['student_class_enrollment_id'] ?? 0)) . '|' . $score['category_name'] . '|' . $score['code']; if (array_key_exists($key, $existingScores)) { $score['existing_raw_score'] = $existingScores[$key]; $score['existing_score_state'] = ((float) $score['score'] === $existingScores[$key]) ? 'EXISTING SCORE UNCHANGED' : 'EXISTING SCORE CONFLICT'; } } unset($row, $score);
    }

    /** @return array<int, array<string, mixed>> */
    private function loadUnresolvedParticipants(PDO $pdo, int $classId): array
    {
        $stmt = $pdo->prepare("\n            SELECT s.student_id, s.student_number, s.first_name, s.middle_name, s.last_name, s.suffix, sce.student_class_enrollment_id\n            FROM students AS s\n            INNER JOIN student_class_enrollments AS sce ON sce.student_id = s.student_id\n            WHERE sce.operational_class_id = ?\n              AND sce.status = 'Active'\n              AND s.status = 'Active'\n              AND s.student_number IS NULL\n            ORDER BY s.last_name, s.first_name, s.student_id\n        ");
        $stmt->execute([$classId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<int, array<string, mixed>> $participants @return array<int, array<string, mixed>> */
    private function findUnresolvedParticipantMatches(array $participants, string $workbookName): array
    {
        $key = $this->normalizeWorkbookName($workbookName);
        if ($key === '') {
            return [];
        }

        return array_values(array_filter(
            $participants,
            fn(array $participant): bool => $this->normalizeCanonicalName($participant) === $key
        ));
    }

    /** @param array<string, mixed> $student */
    private function displayName(array $student): string
    {
        return trim(implode(' ', array_filter([
            $student['first_name'] ?? '',
            $student['middle_name'] ?? '',
            $student['last_name'] ?? '',
            $student['suffix'] ?? '',
        ], static fn($value): bool => trim((string) $value) !== '')));
    }

    private function normalizeWorkbookName(string $value): string
    {
        $parts = explode(',', trim($value));
        if (count($parts) !== 2) {
            return '';
        }

        return $this->normalizeCanonicalName([
            'first_name' => trim($parts[1]),
            'middle_name' => '',
            'last_name' => trim($parts[0]),
            'suffix' => '',
        ]);
    }

    /** @param array<string, mixed> $identity */
    private function normalizeCanonicalName(array $identity): string
    {
        $value = mb_strtolower(trim(implode(' ', array_filter([
            (string) ($identity['first_name'] ?? ''),
            (string) ($identity['middle_name'] ?? ''),
            (string) ($identity['last_name'] ?? ''),
            (string) ($identity['suffix'] ?? ''),
        ], static fn(string $part): bool => trim($part) !== ''))));
        $normalized = preg_replace('/[^\\p{L}\\p{N}]+/u', ' ', $value);

        return trim((string) ($normalized ?? $value));
    }
}
