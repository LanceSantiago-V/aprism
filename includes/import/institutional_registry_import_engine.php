<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Academic Head Institutional Registry importer.
 *
 * This importer deliberately keeps extraction, review, resolution, and
 * confirmation separate. It never infers placement from names and never
 * persists anything during extraction or validation.
 */
final class InstitutionalRegistryImportEngine
{
    private const MAX_ROWS = 5000;
    private const MAX_COLUMNS = 40;

    private const FIELDS = [
        'student_number',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'academic_level',
        'program_code',
        'program_name',
        'section_name',
        'year_level',
        'school_year',
        'semester',
    ];

    private const ALIASES = [
        'student_number' => ['student number', 'student no', 'student id', 'id number', 'student_number'],
        'first_name' => ['first name', 'given name', 'first_name'],
        'middle_name' => ['middle name', 'middle_name', 'mi'],
        'last_name' => ['last name', 'surname', 'family name', 'last_name'],
        'suffix' => ['suffix', 'name suffix'],
        'academic_level' => ['academic level', 'level', 'academic_level'],
        'program_code' => ['program code', 'program_code', 'strand code', 'course code'],
        'program_name' => ['program name', 'program_name', 'program', 'strand', 'strand name'],
        'section_name' => ['section', 'section name', 'section_name'],
        'year_level' => ['year level', 'grade level', 'year_level', 'grade', 'year'],
        'school_year' => ['school year', 'academic year', 'school_year', 'academic_year'],
        'semester' => ['semester', 'teaching context', 'teaching_context', 'term'],
    ];

    public static function canonicalFields(): array
    {
        return self::FIELDS;
    }

    /** @return array<string, mixed> */
    public function parseFile(string $path, string $originalName, string $extension): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('The uploaded placement source is unavailable or unreadable.');
        }

        $extension = strtolower($extension);
        if (!in_array($extension, ['csv', 'xlsx', 'xls'], true)) {
            throw new RuntimeException('Use a CSV, XLSX, or XLS placement data file.');
        }

        $reader = IOFactory::createReader($extension === 'csv'
            ? IOFactory::READER_CSV
            : ($extension === 'xlsx' ? IOFactory::READER_XLSX : IOFactory::READER_XLS));
        $reader->setReadDataOnly(true);
        $workbook = $reader->load($path);

        try {
            $sheet = $workbook->getActiveSheet();
            $highestRow = min((int) $sheet->getHighestDataRow(), self::MAX_ROWS + 1);
            $highestColumn = min(
                Coordinate::columnIndexFromString($sheet->getHighestDataColumn()),
                self::MAX_COLUMNS
            );

            $headerRow = null;
            for ($row = 1; $row <= min($highestRow, 10); $row++) {
                $hasValue = false;
                for ($column = 1; $column <= $highestColumn; $column++) {
                    if ($this->cellText($sheet, $column, $row) !== '') {
                        $hasValue = true;
                        break;
                    }
                }
                if ($hasValue) {
                    $headerRow = $row;
                    break;
                }
            }

            if ($headerRow === null) {
                throw new RuntimeException('The placement data file does not contain a header row.');
            }

            $headers = [];
            for ($column = 1; $column <= $highestColumn; $column++) {
                $label = $this->cellText($sheet, $column, $headerRow);
                if ($label !== '') {
                    $headers[] = [
                        'column' => $column,
                        'label' => $label,
                        'canonical' => $this->canonicalHeader($label),
                    ];
                }
            }

            if ($headers === []) {
                throw new RuntimeException('The placement data file does not contain usable column labels.');
            }

            $mapping = array_fill_keys(self::FIELDS, null);
            foreach ($headers as $header) {
                $canonical = $header['canonical'];
                if ($canonical !== null && $mapping[$canonical] === null) {
                    $mapping[$canonical] = $header['column'];
                }
            }

            $rows = [];
            for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                $values = [];
                $hasValue = false;
                for ($column = 1; $column <= $highestColumn; $column++) {
                    $text = $this->cellText($sheet, $column, $row);
                    if ($text !== '') {
                        $hasValue = true;
                    }
                    $values[$column] = $text;
                }
                if ($hasValue) {
                    $rows[] = ['source_row' => $row, 'values' => $values];
                }
            }

            if ($rows === []) {
                throw new RuntimeException('The placement data file does not contain any data rows.');
            }

            return [
                'source' => [
                    'original_name' => basename($originalName),
                    'extension' => $extension,
                    'sheet' => $sheet->getTitle(),
                    'header_row' => $headerRow,
                ],
                'headers' => $headers,
                'mapping' => $mapping,
                'rows' => $rows,
            ];
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    /** @param array<string, mixed> $source */
    /** @param array<string, mixed> $mapping */
    public function applyMapping(array $source, array $mapping): array
    {
        $normalizedRows = [];
        foreach (($source['rows'] ?? []) as $sourceRow) {
            $values = is_array($sourceRow['values'] ?? null) ? $sourceRow['values'] : [];
            $record = ['source_row' => (int) ($sourceRow['source_row'] ?? 0)];
            foreach (self::FIELDS as $field) {
                $column = filter_var($mapping[$field] ?? null, FILTER_VALIDATE_INT);
                $record[$field] = $column !== false && $column > 0
                    ? $this->normalizeField($field, $values[$column] ?? null)
                    : null;
            }
            $normalizedRows[] = $record;
        }
        return $normalizedRows;
    }

    /** @param array<int, array<string, mixed>> $rows */
    public function resolve(PDO $pdo, array $rows): array
    {
        $resolved = [];
        $errors = [];
        $seenContexts = [];

        foreach ($rows as $index => $row) {
            $rowNumber = (int) ($row['source_row'] ?? ($index + 1));
            $rowErrors = $this->validateRow($row);
            $result = ['source_row' => $rowNumber, 'data' => $row, 'errors' => $rowErrors];

            if ($rowErrors === []) {
                $schoolYear = $this->findSchoolYear($pdo, (string) $row['school_year']);
                if ($schoolYear === null) {
                    $rowErrors[] = 'School Year does not match an existing APRISM School Year.';
                } else {
                    $result['school_year'] = $schoolYear;
                }

                $program = $this->findProgram($pdo, $row);
                if ($program['error'] !== null) {
                    $rowErrors[] = $program['error'];
                } else {
                    $result['program'] = $program['record'];
                }

                if ($rowErrors === [] && is_array($program['record'] ?? null)) {
                    $section = $this->findSection($pdo, $row, $program['record']);
                    if ($section['error'] !== null) {
                        $rowErrors[] = $section['error'];
                    } else {
                        $result['section'] = $section['record'];
                    }
                }

                $student = $this->findStudent($pdo, $row);
                if ($student['error'] !== null) {
                    $rowErrors[] = $student['error'];
                } else {
                    $result['student'] = $student['record'];
                }

                if ($rowErrors === [] && is_array($result['student'] ?? null) && is_array($result['school_year'] ?? null)) {
                    $contextKey = implode('|', [
                        (string) $result['student']['student_id'],
                        (string) $result['school_year']['school_year_id'],
                        strtolower((string) ($row['semester'] ?? '')),
                    ]);
                    $placementKey = implode('|', [
                        (string) ($result['program']['program_id'] ?? 'new'),
                        (string) ($result['section']['section_id'] ?? 'new'),
                        (string) $row['year_level'],
                        (string) $row['academic_level'],
                    ]);
                    if (isset($seenContexts[$contextKey]) && $seenContexts[$contextKey] !== $placementKey) {
                        $rowErrors[] = 'This Student has conflicting placement values in the same School Year and teaching context.';
                    }
                    $seenContexts[$contextKey] = $placementKey;

                    $sae = $this->findAcademicEnrollment($pdo, $result, $row);
                    if ($sae['error'] !== null) {
                        $rowErrors[] = $sae['error'];
                    } else {
                        $result['academic_enrollment'] = $sae['record'];
                    }
                }
            }

            $result['errors'] = array_values(array_unique($rowErrors));
            if ($result['errors'] !== []) {
                $errors[] = 'Source row ' . $rowNumber . ': ' . implode(' ', $result['errors']);
            }
            $resolved[] = $result;
        }

        return ['rows' => $resolved, 'errors' => $errors, 'valid' => $errors === [] && $resolved !== []];
    }

    /** @param array<int, array<string, mixed>> $resolvedRows */
    public function persist(PDO $pdo, array $resolvedRows, int $importedBy, string $originalName): array
    {
        if ($resolvedRows === []) {
            throw new RuntimeException('There are no validated placement rows to confirm.');
        }

        $pdo->beginTransaction();
        try {
            $createdPrograms = 0;
            $createdSections = 0;
            $createdStudents = 0;
            $createdEnrollments = 0;
            $reusedEnrollments = 0;
            $linkedClassEnrollments = 0;

            foreach ($resolvedRows as $resolved) {
                if (($resolved['errors'] ?? []) !== []) {
                    throw new RuntimeException('The placement-data review contains unresolved validation errors.');
                }

                $row = $resolved['data'];
                $schoolYear = $this->lockSchoolYear($pdo, (int) $resolved['school_year']['school_year_id']);
                $program = $this->lockOrCreateProgram($pdo, $row, $createdPrograms);
                $section = $this->lockOrCreateSection($pdo, $row, $program, $createdSections);
                $student = $this->lockOrCreateStudent($pdo, $row, $createdStudents);
                $enrollment = $this->lockOrCreateAcademicEnrollment(
                    $pdo,
                    $row,
                    $schoolYear,
                    $program,
                    $section,
                    $student,
                    $createdEnrollments,
                    $reusedEnrollments
                );
                $this->linkCompatibleClassEnrollments(
                    $pdo,
                    (int) $student['student_id'],
                    (int) $enrollment['student_academic_enrollment_id'],
                    (string) $schoolYear['school_year'],
                    (string) $row['semester'],
                    $linkedClassEnrollments
                );
            }

            $pdo->commit();

            require_once __DIR__ . '/../helper/audit_helper.php';
            logAudit(
                $pdo,
                'Student Placement Data Imported',
                sprintf(
                    'Confirmed student placement import %s: %d rows, %d Students, %d academic placements, %d Programs, %d Sections created, %d Class Enrollments linked.',
                    basename($originalName),
                    count($resolvedRows),
                    $createdStudents,
                    $createdEnrollments,
                    $createdPrograms,
                    $createdSections,
                    $linkedClassEnrollments
                )
            );
            $this->recordImportHistory(
                $pdo,
                $importedBy,
                $originalName,
                count($resolvedRows),
                $createdStudents,
                $reusedEnrollments
            );

            return [
                'rows' => count($resolvedRows),
                'created_programs' => $createdPrograms,
                'created_sections' => $createdSections,
                'created_students' => $createdStudents,
                'created_enrollments' => $createdEnrollments,
                'reused_enrollments' => $reusedEnrollments,
                'class_enrollments_linked' => $linkedClassEnrollments,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $row */
    private function validateRow(array $row): array
    {
        $errors = [];
        foreach (['student_number', 'first_name', 'last_name', 'academic_level', 'program_name', 'section_name', 'year_level', 'school_year'] as $field) {
            if (($row[$field] ?? null) === null || trim((string) $row[$field]) === '') {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }

        $level = (string) ($row['academic_level'] ?? '');
        $year = (string) ($row['year_level'] ?? '');
        if ($level === 'College' && !in_array($year, ['1', '2', '3', '4'], true)) {
            $errors[] = 'College Year Level must be 1, 2, 3, or 4.';
        }
        if ($level === 'Senior High School' && !in_array($year, ['11', '12'], true)) {
            $errors[] = 'Senior High School Grade Level must be 11 or 12.';
        }
        if ($level !== '' && !in_array($level, ['College', 'Senior High School'], true)) {
            $errors[] = 'Academic Level must be College or Senior High School.';
        }
        if (($row['semester'] ?? null) !== null && $row['semester'] !== '' && !in_array($row['semester'], ['First Semester', 'Second Semester'], true)) {
            $errors[] = 'Teaching context must be First Semester or Second Semester when supplied.';
        }
        return $errors;
    }

    private function cellText($sheet, int $column, int $row): string
    {
        $coordinate = Coordinate::stringFromColumnIndex($column) . $row;
        $value = $sheet->getCell($coordinate)->getValue();
        return trim((string) $value);
    }

    private function canonicalHeader(string $label): ?string
    {
        $normalized = strtolower(trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower($label))));
        foreach (self::ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                if ($normalized === strtolower(trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower($alias))))) {
                    return $field;
                }
            }
        }
        return null;
    }

    private function normalizeField(string $field, mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }
        if ($field === 'academic_level') {
            $key = strtolower(preg_replace('/[^a-z]+/', ' ', $text) ?? $text);
            return match (trim($key)) {
                'college' => 'College',
                'shs', 'senior high', 'senior high school' => 'Senior High School',
                default => $text,
            };
        }
        if ($field === 'year_level') {
            return preg_match('/^(1|2|3|4|11|12)(?:\.0+)?$/', $text, $match) === 1
                ? $match[1]
                : $text;
        }
        if ($field === 'semester') {
            $key = strtolower(preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text);
            return match (trim($key)) {
                'first semester', '1st semester', 'first sem', '1st sem' => 'First Semester',
                'second semester', '2nd semester', 'second sem', '2nd sem' => 'Second Semester',
                default => $text,
            };
        }
        return preg_replace('/\s+/', ' ', $text) ?? $text;
    }

    /** @return array<string, mixed>|null */
    private function findSchoolYear(PDO $pdo, string $schoolYear): ?array
    {
        $stmt = $pdo->prepare('SELECT school_year_id, school_year, start_date, status FROM school_years WHERE school_year = ? LIMIT 1');
        $stmt->execute([$schoolYear]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @param array<string, mixed> $row */
    private function findProgram(PDO $pdo, array $row): array
    {
        $code = $row['program_code'] ?? null;
        $name = (string) ($row['program_name'] ?? '');
        $level = (string) ($row['academic_level'] ?? '');
        $stmt = $pdo->prepare('SELECT program_id, program_code, program_name, academic_level, status FROM programs WHERE (program_code = ? AND ? IS NOT NULL) OR LOWER(TRIM(program_name)) = LOWER(TRIM(?)) ORDER BY program_id LIMIT 3');
        $stmt->execute([$code, $code, $name]);
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($matches as $match) {
            $sameCode = $code !== null && strcasecmp((string) $match['program_code'], (string) $code) === 0;
            $sameName = strcasecmp((string) $match['program_name'], $name) === 0;
            if ($code !== null && $sameName && !$sameCode) {
                return ['record' => null, 'error' => 'The supplied Program Code conflicts with the existing Program Name.'];
            }
            if ($sameCode && !$sameName) {
                return ['record' => null, 'error' => 'The supplied Program Name conflicts with the existing Program Code.'];
            }
            if (($sameCode || ($code === null && $sameName)) && ($match['academic_level'] !== $level || $match['status'] !== 'Active')) {
                return ['record' => null, 'error' => 'Program Code/Name matches an existing Program with conflicting level or inactive status.'];
            }
            if ($sameCode || ($code === null && $sameName)) {
                return ['record' => $match, 'error' => null];
            }
        }
        return ['record' => [
            'program_id' => null,
            'program_code' => $code,
            'program_name' => $name,
            'academic_level' => $level,
            'status' => 'Active',
        ], 'error' => null];
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $program */
    private function findSection(PDO $pdo, array $row, array $program): array
    {
        $stmt = $pdo->prepare('SELECT section_id, program_id, section_name, year_level, status FROM sections WHERE program_id = ? AND LOWER(TRIM(section_name)) = LOWER(TRIM(?)) LIMIT 2');
        $stmt->execute([$program['program_id'], $row['section_name']]);
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($matches) > 1) {
            return ['record' => null, 'error' => 'The Section matches multiple institutional records.'];
        }
        if ($matches !== []) {
            $section = $matches[0];
            if ($section['status'] !== 'Active' || (string) $section['year_level'] !== (string) $row['year_level']) {
                return ['record' => null, 'error' => 'The existing Section has a conflicting Year Level or inactive status.'];
            }
            return ['record' => $section, 'error' => null];
        }
        return ['record' => [
            'section_id' => null,
            'program_id' => $program['program_id'],
            'section_name' => $row['section_name'],
            'year_level' => $row['year_level'],
            'status' => 'Active',
        ], 'error' => null];
    }

    /** @param array<string, mixed> $row */
    private function findStudent(PDO $pdo, array $row): array
    {
        $stmt = $pdo->prepare('SELECT student_id, student_number, first_name, middle_name, last_name, suffix, status FROM students WHERE student_number = ? LIMIT 1');
        $stmt->execute([$row['student_number']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$student) {
            return ['record' => [
                'student_id' => null,
                'student_number' => $row['student_number'],
                'first_name' => $row['first_name'],
                'middle_name' => $row['middle_name'],
                'last_name' => $row['last_name'],
                'suffix' => $row['suffix'],
                'status' => 'Active',
            ], 'error' => null];
        }
        foreach (['first_name', 'middle_name', 'last_name', 'suffix'] as $field) {
            if (!$this->sameText($student[$field] ?? null, $row[$field] ?? null)) {
                return ['record' => null, 'error' => 'Student Number matches an existing Student with conflicting identity details.'];
            }
        }
        return ['record' => $student, 'error' => null];
    }

    /** @param array<string, mixed> $resolved @param array<string, mixed> $row */
    private function findAcademicEnrollment(PDO $pdo, array $resolved, array $row): array
    {
        $stmt = $pdo->prepare("SELECT student_academic_enrollment_id, student_id, school_year_id, semester, academic_level, program_id, section_id, year_level, status
            FROM student_academic_enrollments
            WHERE student_id = ? AND school_year_id = ?
              AND status = 'Active' AND effective_end IS NULL
            ORDER BY student_academic_enrollment_id");
        $stmt->execute([$resolved['student']['student_id'], $resolved['school_year']['school_year_id']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $existing) {
            $sameContext = $this->sameText($existing['semester'], $row['semester']);
            if (!$sameContext) {
                continue;
            }
            $samePlacement = $existing['academic_level'] === $row['academic_level']
                && (int) $existing['program_id'] === (int) $resolved['program']['program_id']
                && (int) $existing['section_id'] === (int) $resolved['section']['section_id']
                && (string) $existing['year_level'] === (string) $row['year_level'];
            if ($samePlacement) {
                return ['record' => $existing, 'error' => null];
            }
            return ['record' => null, 'error' => 'The Student already has a conflicting placement in this School Year and teaching context.'];
        }
        return ['record' => [
            'student_academic_enrollment_id' => null,
            'status' => 'Active',
        ], 'error' => null];
    }

    private function sameText(mixed $left, mixed $right): bool
    {
        $left = trim((string) ($left ?? ''));
        $right = trim((string) ($right ?? ''));
        return strcasecmp($left, $right) === 0;
    }

    private function recordImportHistory(PDO $pdo, int $importedBy, string $originalName, int $totalRows, int $insertedRows, int $skippedRows): void
    {
        try {
            $exists = $pdo->query("SHOW TABLES LIKE 'import_history'")->fetchColumn();
            if ($exists === false) {
                return;
            }
            $stmt = $pdo->prepare(
                "INSERT INTO import_history
                    (imported_by, import_type, original_filename, total_rows,
                     inserted_rows, updated_rows, skipped_rows, failed_rows, status)
                 VALUES (?, 'Students', ?, ?, ?, 0, ?, 0, 'Success')"
            );
            $stmt->execute([$importedBy, basename($originalName), $totalRows, $insertedRows, $skippedRows]);
        } catch (Throwable $exception) {
            error_log('[APRISM Institutional Registry Import History] ' . $exception->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function lockSchoolYear(PDO $pdo, int $id): array
    {
        $stmt = $pdo->prepare('SELECT school_year_id, school_year, start_date FROM school_years WHERE school_year_id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('The selected School Year is no longer available.');
        }
        return $row;
    }

    /** @param array<string, mixed> $row */
    private function lockOrCreateProgram(PDO $pdo, array $row, int &$created): array
    {
        $stmt = $pdo->prepare('SELECT program_id, program_code, program_name, academic_level, status FROM programs WHERE (program_code = ? AND ? IS NOT NULL) OR LOWER(TRIM(program_name)) = LOWER(TRIM(?)) ORDER BY program_id LIMIT 3 FOR UPDATE');
        $stmt->execute([$row['program_code'], $row['program_code'], $row['program_name']]);
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($matches as $match) {
            $sameCode = $row['program_code'] !== null && strcasecmp((string) $match['program_code'], (string) $row['program_code']) === 0;
            $sameName = strcasecmp((string) $match['program_name'], (string) $row['program_name']) === 0;
            if ($row['program_code'] !== null && $sameName && !$sameCode) {
                throw new RuntimeException('A Program Code conflict was found while confirming placement data.');
            }
            if ($sameCode && !$sameName) {
                throw new RuntimeException('A Program Name conflict was found while confirming placement data.');
            }
            if ($sameCode || ($row['program_code'] === null && $sameName)) {
                if ($match['academic_level'] !== $row['academic_level'] || $match['status'] !== 'Active') {
                    throw new RuntimeException('A Program conflict was found while confirming placement data.');
                }
                return $match;
            }
        }
        $insert = $pdo->prepare('INSERT INTO programs (program_code, program_name, academic_level, status) VALUES (?, ?, ?, \'Active\')');
        $insert->execute([$row['program_code'], $row['program_name'], $row['academic_level']]);
        $created++;
        return ['program_id' => (int) $pdo->lastInsertId(), 'program_code' => $row['program_code'], 'program_name' => $row['program_name'], 'academic_level' => $row['academic_level'], 'status' => 'Active'];
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $program */
    private function lockOrCreateSection(PDO $pdo, array $row, array $program, int &$created): array
    {
        $stmt = $pdo->prepare('SELECT section_id, program_id, section_name, year_level, status FROM sections WHERE program_id = ? AND LOWER(TRIM(section_name)) = LOWER(TRIM(?)) LIMIT 2 FOR UPDATE');
        $stmt->execute([$program['program_id'], $row['section_name']]);
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($matches) > 1) {
            throw new RuntimeException('A Section conflict was found while confirming placement data.');
        }
        if ($matches !== []) {
            if ($matches[0]['status'] !== 'Active' || (string) $matches[0]['year_level'] !== (string) $row['year_level']) {
                throw new RuntimeException('The Section has changed since review and no longer matches the imported placement.');
            }
            return $matches[0];
        }
        $insert = $pdo->prepare('INSERT INTO sections (program_id, section_name, year_level, status) VALUES (?, ?, ?, \'Active\')');
        $insert->execute([$program['program_id'], $row['section_name'], $row['year_level']]);
        $created++;
        return ['section_id' => (int) $pdo->lastInsertId(), 'program_id' => $program['program_id'], 'section_name' => $row['section_name'], 'year_level' => $row['year_level'], 'status' => 'Active'];
    }

    /** @param array<string, mixed> $row */
    private function lockOrCreateStudent(PDO $pdo, array $row, int &$created): array
    {
        $stmt = $pdo->prepare('SELECT student_id, student_number, first_name, middle_name, last_name, suffix, status FROM students WHERE student_number = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$row['student_number']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($student) {
            foreach (['first_name', 'middle_name', 'last_name', 'suffix'] as $field) {
                if (!$this->sameText($student[$field] ?? null, $row[$field] ?? null)) {
                    throw new RuntimeException('Student identity changed since review; no identity was overwritten.');
                }
            }
            return $student;
        }
        $insert = $pdo->prepare('INSERT INTO students (student_number, first_name, middle_name, last_name, suffix, status) VALUES (?, ?, ?, ?, ?, \'Active\')');
        $insert->execute([$row['student_number'], $row['first_name'], $row['middle_name'], $row['last_name'], $row['suffix']]);
        $created++;
        return ['student_id' => (int) $pdo->lastInsertId(), 'student_number' => $row['student_number'], 'first_name' => $row['first_name'], 'middle_name' => $row['middle_name'], 'last_name' => $row['last_name'], 'suffix' => $row['suffix'], 'status' => 'Active'];
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $schoolYear @param array<string, mixed> $program @param array<string, mixed> $section @param array<string, mixed> $student */
    private function lockOrCreateAcademicEnrollment(PDO $pdo, array $row, array $schoolYear, array $program, array $section, array $student, int &$created, int &$reused): array
    {
        $stmt = $pdo->prepare("SELECT student_academic_enrollment_id, student_id, school_year_id, semester, academic_level, program_id, section_id, year_level, status
            FROM student_academic_enrollments
            WHERE student_id = ? AND school_year_id = ?
              AND status = 'Active' AND effective_end IS NULL
            ORDER BY student_academic_enrollment_id
            FOR UPDATE");
        $stmt->execute([$student['student_id'], $schoolYear['school_year_id']]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $existing) {
            if (!$this->sameText($existing['semester'], $row['semester'])) {
                continue;
            }
            $same = $existing['academic_level'] === $row['academic_level']
                && (int) $existing['program_id'] === (int) $program['program_id']
                && (int) $existing['section_id'] === (int) $section['section_id']
                && (string) $existing['year_level'] === (string) $row['year_level'];
            if (!$same) {
                throw new RuntimeException('A conflicting Student Academic Enrollment already exists for this context.');
            }
            $reused++;
            return $existing;
        }
        $insert = $pdo->prepare('INSERT INTO student_academic_enrollments (student_id, school_year_id, semester, academic_level, program_id, section_id, year_level, status, effective_start, effective_end) VALUES (?, ?, ?, ?, ?, ?, ?, \'Active\', ?, NULL)');
        $insert->execute([$student['student_id'], $schoolYear['school_year_id'], $row['semester'], $row['academic_level'], $program['program_id'], $section['section_id'], $row['year_level'], $schoolYear['start_date']]);
        $created++;
        return ['student_academic_enrollment_id' => (int) $pdo->lastInsertId(), 'status' => 'Active'];
    }

    /**
     * Attach only compatible existing class participation rows to the
     * authoritative SAE just created or reused by this Registry import.
     * A different non-null link is a conflict and aborts the transaction;
     * it is never overwritten.
     */
    private function linkCompatibleClassEnrollments(
        PDO $pdo,
        int $studentId,
        int $academicEnrollmentId,
        string $schoolYear,
        string $semester,
        int &$linked
    ): void {
        $stmt = $pdo->prepare("SELECT sce.student_class_enrollment_id, sce.enrollment_id
            FROM student_class_enrollments AS sce
            INNER JOIN operational_classes AS oc
                ON oc.operational_class_id = sce.operational_class_id
            WHERE sce.student_id = ?
              AND sce.status <> 'Archived'
              AND oc.school_year = ?
              AND oc.semester = ?
            FOR UPDATE");
        $stmt->execute([$studentId, $schoolYear, $semester]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $classEnrollment) {
            $existingEnrollmentId = $classEnrollment['enrollment_id'];
            if ($existingEnrollmentId !== null && (int) $existingEnrollmentId !== $academicEnrollmentId) {
                throw new RuntimeException(
                    'A Class Enrollment is linked to a different Academic Enrollment for the same Student and academic context.'
                );
            }

            if ($existingEnrollmentId === null) {
                $update = $pdo->prepare("UPDATE student_class_enrollments
                    SET enrollment_id = ?
                    WHERE student_class_enrollment_id = ?
                      AND student_id = ?
                      AND enrollment_id IS NULL");
                $update->execute([
                    $academicEnrollmentId,
                    (int) $classEnrollment['student_class_enrollment_id'],
                    $studentId,
                ]);
                $linked += $update->rowCount();
            }
        }
    }
}
