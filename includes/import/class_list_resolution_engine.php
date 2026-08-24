<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

require_once __DIR__ . '/../../vendor/autoload.php';

final class ClassListResolutionEngine
{
    private const MAX_COLUMNS = 80;
    private const MAX_PREVIEW_ROWS = 500;

    /**
     * SELECT-only resolution preview. This class never writes to the database.
     *
     * @param array<string, mixed> $source
     * @param array<string, mixed> $classContext
     * @param array<string, int|null> $mapping
     * @param array<int, array<string, string>> $identityOverrides
     * @return array<string, mixed>
     */
    public function preview(
        PDO $pdo,
        array $source,
        array $classContext,
        string $worksheetName,
        int $headerRow,
        int $firstDataRow,
        array $mapping,
        array $identityOverrides = []
    ): array {
        $sourceRows = $this->readMappedRows(
            (string) $source['path'],
            (string) $source['extension'],
            $worksheetName,
            $headerRow,
            $firstDataRow,
            $mapping
        );

        $studentNumbers = [];

        foreach ($sourceRows['rows'] as $row) {
            $studentNumber = trim((string) ($row['student_number'] ?? ''));

            if ($studentNumber !== '') {
                $studentNumbers[$studentNumber] = true;
            }
        }

        $existingStudents = $this->loadStudents(
            $pdo,
            array_keys($studentNumbers)
        );

        $studentIds = array_map(
            static fn(array $student): int => (int) $student['student_id'],
            $existingStudents
        );

        $academicEnrollments = $this->loadAcademicEnrollments(
            $pdo,
            $studentIds,
            (int) $classContext['school_year_id']
        );

        $classEnrollments = $this->loadClassEnrollments(
            $pdo,
            $studentIds,
            (int) $classContext['operational_class_id']
        );

        $sourceNumberCounts = [];

        foreach ($sourceRows['rows'] as $row) {
            $studentNumber = trim((string) ($row['student_number'] ?? ''));

            if ($studentNumber !== '') {
                $sourceNumberCounts[$studentNumber] =
                    ($sourceNumberCounts[$studentNumber] ?? 0) + 1;
            }
        }

        $resolvedRows = [];
        $summary = [
            'source_row_count' => $sourceRows['source_row_count'],
            'previewed_row_count' => count($sourceRows['rows']),
            'is_truncated' => $sourceRows['is_truncated'],
            'structurally_valid' => 0,
            'structurally_invalid' => 0,
            'existing_student_matches' => 0,
            'new_student_candidates' => 0,
            'identity_completion_required' => 0,
            'identity_completion_complete' => 0,
            'identity_conflicts' => 0,
            'academic_context_resolved' => 0,
            'academic_context_unresolved' => 0,
            'academic_context_ambiguous' => 0,
            'already_enrolled' => 0,
            'source_duplicates' => 0,
        ];

        foreach ($sourceRows['rows'] as $row) {
            $resolved = $this->resolveRow(
                $row,
                $sourceNumberCounts,
                $existingStudents,
                $academicEnrollments,
                $classEnrollments,
                $classContext,
                $identityOverrides[(int) $row['source_row_number']] ?? []
            );

            $resolvedRows[] = $resolved;

            if ($resolved['structural_status'] === 'Structurally Valid') {
                $summary['structurally_valid']++;
            } else {
                $summary['structurally_invalid']++;
            }

            if ($resolved['identity_status'] === 'Existing Student matched') {
                $summary['existing_student_matches']++;
            }

            if ($resolved['identity_status'] === 'New Student candidate') {
                $summary['new_student_candidates']++;
            }

            if ($resolved['identity_completion_required'] === true) {
                $summary['identity_completion_required']++;
            }

            if ($resolved['identity_completion_complete'] === true) {
                $summary['identity_completion_complete']++;
            }

            if ($resolved['identity_status'] === 'Identity conflict') {
                $summary['identity_conflicts']++;
            }

            if ($resolved['academic_status'] === 'Academic context candidate') {
                $summary['academic_context_resolved']++;
            }

            if ($resolved['academic_status'] === 'Academic context unresolved') {
                $summary['academic_context_unresolved']++;
            }

            if ($resolved['academic_status'] === 'Academic context ambiguous') {
                $summary['academic_context_ambiguous']++;
            }

            if ($resolved['class_status'] === 'Already enrolled in this class') {
                $summary['already_enrolled']++;
            }

            if ($resolved['source_duplicate'] === true) {
                $summary['source_duplicates']++;
            }
        }

        return [
            'source' => [
                'original_name' => (string) $source['original_name'],
                'worksheet_name' => $worksheetName,
                'header_row_number' => $headerRow,
                'first_data_row_number' => $firstDataRow,
            ],
            'class_context' => [
                'operational_class_id' => (int) $classContext['operational_class_id'],
                'school_year' => (string) $classContext['school_year'],
                'semester' => (string) $classContext['semester'],
                'section_name' => (string) $classContext['section_name'],
                'program_name' => (string) ($classContext['program_name'] ?? ''),
                'program_code' => (string) ($classContext['program_code'] ?? ''),
                'academic_level' => (string) ($classContext['academic_level'] ?? ''),
                'year_level' => (string) ($classContext['section_year_level'] ?? ''),
            ],
            'summary' => $summary,
            'rows' => $resolvedRows,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, int> $sourceNumberCounts
     * @param array<string, array<string, mixed>> $existingStudents
     * @param array<int, array<int, array<string, mixed>>> $academicEnrollments
     * @param array<int, array<string, mixed>> $classEnrollments
     * @param array<string, mixed> $classContext
     * @param array<string, string> $identityOverride
     * @return array<string, mixed>
     */
    private function resolveRow(
        array $row,
        array $sourceNumberCounts,
        array $existingStudents,
        array $academicEnrollments,
        array $classEnrollments,
        array $classContext,
        array $identityOverride = []
    ): array {
        $errors = [];
        $warnings = [];

        $studentNumber = trim((string) ($row['student_number'] ?? ''));
        $combinedName = trim((string) ($row['student_name_raw'] ?? ''));
        $firstName = trim((string) ($row['first_name'] ?? ''));
        $lastName = trim((string) ($row['last_name'] ?? ''));
        $middleName = trim((string) ($row['middle_name'] ?? ''));
        $suffix = trim((string) ($row['suffix'] ?? ''));

        if ($studentNumber === '') {
            $errors[] = 'Student Number is required.';
        }

        if ($combinedName === '' && ($firstName === '' || $lastName === '')) {
            $errors[] =
                'Map a combined Student Name, or both First Name and Last Name.';
        }

        $structuralStatus = $errors === []
            ? 'Structurally Valid'
            : 'Structurally Invalid';

        $sourceDuplicate = $studentNumber !== ''
            && ($sourceNumberCounts[$studentNumber] ?? 0) > 1;

        if ($sourceDuplicate) {
            $warnings[] =
                'This Student Number appears more than once in the uploaded source.';
        }

        $identityStatus = 'Blocked by structural validation';
        $academicStatus = 'Not evaluated';
        $classStatus = 'Not evaluated';
        $existingStudent = null;
        $candidateEnrollment = null;
        $matchingEnrollmentCount = 0;
        $identityCompletionRequired = false;
        $identityCompletionComplete = false;
        $proposedIdentity = [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'suffix' => $suffix,
        ];

        if ($structuralStatus === 'Structurally Valid') {
            $existingStudent = $existingStudents[$studentNumber] ?? null;

            if ($existingStudent === null) {
                $identityCompletionRequired = !$sourceDuplicate;

                if ($identityCompletionRequired) {
                    foreach (array_keys($proposedIdentity) as $field) {
                        if (array_key_exists($field, $identityOverride)) {
                            $proposedIdentity[$field] = trim(
                                (string) $identityOverride[$field]
                            );
                        }
                    }
                }

                $identityCompletionComplete =
                    $proposedIdentity['first_name'] !== ''
                    && $proposedIdentity['last_name'] !== '';

                if ($identityCompletionComplete && !$sourceDuplicate) {
                    $identityStatus = 'New Student candidate';
                    $warnings[] =
                        'No existing Student Number matched. Proposed identity details are review-only and have not been saved.';
                } else {
                    $identityStatus = 'Identity incomplete';
                    $warnings[] =
                        $sourceDuplicate
                        ? 'Duplicate Student Number rows cannot be prepared as a new Student candidate.'
                        : 'Complete First Name and Last Name explicitly. A combined source name is preserved as evidence and is not split automatically.';
                }

                $academicStatus = 'Academic context unresolved';
                $classStatus = 'Not enrolled';
            } else {
                $identityStatus = 'Existing Student matched';

                if (
                    $firstName !== '' && !$this->sameText(
                        $firstName,
                        (string) $existingStudent['first_name']
                    )
                ) {
                    $identityStatus = 'Identity conflict';
                    $warnings[] =
                        'Mapped First Name differs from the existing Student identity.';
                }

                if (
                    $lastName !== '' && !$this->sameText(
                        $lastName,
                        (string) $existingStudent['last_name']
                    )
                ) {
                    $identityStatus = 'Identity conflict';
                    $warnings[] =
                        'Mapped Last Name differs from the existing Student identity.';
                }

                if ($combinedName !== '') {
                    $warnings[] =
                        'Combined Student Name is preserved as source evidence and is not automatically split.';
                }

                if ((string) $existingStudent['status'] !== 'Active') {
                    $warnings[] =
                        'The matched Student is not currently Active.';
                }

                $studentId = (int) $existingStudent['student_id'];

                $matchingEnrollments = $this->matchingEnrollments(
                    $academicEnrollments[$studentId] ?? [],
                    $row,
                    $classContext
                );
                $matchingEnrollmentCount = count($matchingEnrollments);

                if ($matchingEnrollmentCount === 1) {
                    $candidateEnrollment = $matchingEnrollments[0];
                    $academicStatus = 'Academic context candidate';
                } elseif ($matchingEnrollmentCount > 1) {
                    $academicStatus = 'Academic context ambiguous';
                    $warnings[] =
                        'More than one historical Academic Enrollment matches this source context.';
                } else {
                    $academicStatus = 'Academic context unresolved';

                    if (($academicEnrollments[$studentId] ?? []) !== []) {
                        $warnings[] =
                            'Existing Academic Enrollment records were found, but none safely match the selected class/source context.';
                    } else {
                        $warnings[] =
                            'No Academic Enrollment was found for this Student in the selected School Year.';
                    }
                }

                if (isset($classEnrollments[$studentId])) {
                    $classStatus = 'Already enrolled in this class';
                } else {
                    $classStatus = 'Not enrolled';
                }
            }
        }

        $academicEvidence = $this->academicEvidence(
            $row,
            $classContext,
            $existingStudent,
            $academicEnrollments[(int) ($existingStudent['student_id'] ?? 0)] ?? [],
            $candidateEnrollment,
            $matchingEnrollmentCount,
            $structuralStatus,
            $identityStatus,
            $sourceDuplicate
        );

        return [
            'source_row_number' => (int) $row['source_row_number'],
            'student_number' => $studentNumber,
            'student_name_raw' => $combinedName,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'suffix' => $suffix,
            'program' => trim((string) ($row['program'] ?? '')),
            'section' => trim((string) ($row['section'] ?? '')),
            'year_level' => trim((string) ($row['year_level'] ?? '')),
            'structural_status' => $structuralStatus,
            'identity_status' => $identityStatus,
            'academic_status' => $academicStatus,
            'class_status' => $classStatus,
            'source_duplicate' => $sourceDuplicate,
            'candidate_student_id' => $existingStudent !== null
                ? (int) $existingStudent['student_id']
                : null,
            'candidate_student_status' => $existingStudent !== null
                ? (string) $existingStudent['status']
                : null,
            'candidate_academic_enrollment_id' => $candidateEnrollment !== null
                ? (int) $candidateEnrollment['student_academic_enrollment_id']
                : null,
            'academic_evidence' => $academicEvidence,
            'identity_completion_required' => $identityCompletionRequired,
            'identity_completion_complete' => $identityCompletionComplete,
            'proposed_identity' => $proposedIdentity,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Build display evidence only. This deliberately does not recommend, select,
     * create, or update a Student Academic Enrollment.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $classContext
     * @param array<string, mixed>|null $existingStudent
     * @param array<int, array<string, mixed>> $availableEnrollments
     * @param array<string, mixed>|null $candidateEnrollment
     * @return array<string, mixed>
     */
    private function academicEvidence(
        array $row,
        array $classContext,
        ?array $existingStudent,
        array $availableEnrollments,
        ?array $candidateEnrollment,
        int $matchingEnrollmentCount,
        string $structuralStatus,
        string $identityStatus,
        bool $sourceDuplicate
    ): array {
        $sourceContext = [
            'program' => trim((string) ($row['program'] ?? '')),
            'section' => trim((string) ($row['section'] ?? '')),
            'year_level' => trim((string) ($row['year_level'] ?? '')),
        ];

        $evidence = [
            'source_context' => $sourceContext,
            'available_enrollment_count' => count($availableEnrollments),
            'matching_enrollment_count' => $matchingEnrollmentCount,
            'candidate' => $candidateEnrollment === null
                ? null
                : [
                    'student_academic_enrollment_id' => (int) $candidateEnrollment['student_academic_enrollment_id'],
                    'semester' => (string) ($candidateEnrollment['semester'] ?? ''),
                    'academic_level' => (string) ($candidateEnrollment['academic_level'] ?? ''),
                    'program_code' => (string) ($candidateEnrollment['program_code'] ?? ''),
                    'program_name' => (string) ($candidateEnrollment['program_name'] ?? ''),
                    'section_name' => (string) ($candidateEnrollment['section_name'] ?? ''),
                    'year_level' => (string) ($candidateEnrollment['year_level'] ?? ''),
                    'status' => (string) ($candidateEnrollment['status'] ?? ''),
                ],
            'reason' => '',
        ];

        if ($structuralStatus !== 'Structurally Valid') {
            $evidence['reason'] = 'Academic context was not evaluated because this source row is structurally incomplete.';
            return $evidence;
        }

        if ($sourceDuplicate) {
            $evidence['reason'] = 'This duplicate Student Number must be resolved in the source before any future academic-context action. Context is shown as evidence only.';
            return $evidence;
        }

        if ($existingStudent === null) {
            $evidence['reason'] = $identityStatus === 'New Student candidate'
                ? 'No existing Student or historical Academic Enrollment is available for this Student Number. A later reviewed workflow must establish academic context explicitly; it is not inferred from the Operational Class.'
                : 'Academic context remains pending until the new Student identity is complete and a later reviewed workflow establishes academic context explicitly.';
            return $evidence;
        }

        if ($identityStatus === 'Identity conflict') {
            $evidence['reason'] = 'Existing academic records are shown only as evidence. Resolve the Student identity conflict before any future academic-context action.';
            return $evidence;
        }

        if ($candidateEnrollment !== null) {
            $evidence['reason'] = 'Exactly one existing Academic Enrollment in the selected School Year and Semester matches the mapped source context. This is evidence only; no enrollment has been selected or changed.';
            return $evidence;
        }

        if ($matchingEnrollmentCount > 1) {
            $evidence['reason'] = 'More than one existing Academic Enrollment matches the selected School Year, Semester, and mapped source context. No enrollment is selected.';
            return $evidence;
        }

        $evidence['reason'] = $availableEnrollments === []
            ? 'No existing Academic Enrollment was found for this Student in the selected School Year. The Operational Class context is not adopted automatically.'
            : 'Existing Academic Enrollment records were found, but none safely match the selected School Year, Semester, and mapped source context. The Operational Class context is not adopted automatically.';

        return $evidence;
    }

    /**
     * @param array<int, array<string, mixed>> $enrollments
     * @param array<string, mixed> $row
     * @param array<string, mixed> $classContext
     * @return array<int, array<string, mixed>>
     */
    private function matchingEnrollments(
        array $enrollments,
        array $row,
        array $classContext
    ): array {
        $sourceProgram = trim((string) ($row['program'] ?? ''));
        $sourceSection = trim((string) ($row['section'] ?? ''));
        $sourceYearLevel = trim((string) ($row['year_level'] ?? ''));

        /*
         * A teaching assignment is not academic-placement evidence. Without
         * mapped source context, do not select an SAE by the class section.
         */
        if (
            $sourceProgram === ''
            && $sourceSection === ''
            && $sourceYearLevel === ''
        ) {
            return [];
        }

        $matches = [];

        foreach ($enrollments as $enrollment) {
            if (
                (string) ($enrollment['status'] ?? '') !== 'Active'
                || ($enrollment['effective_end'] ?? null) !== null
            ) {
                continue;
            }

            if (
                (string) ($enrollment['semester'] ?? '') !==
                (string) $classContext['semester']
            ) {
                continue;
            }

            if (
                $sourceProgram !== ''
                && !$this->sameText(
                    $sourceProgram,
                    (string) ($enrollment['program_code'] ?? '')
                )
                && !$this->sameText(
                    $sourceProgram,
                    (string) ($enrollment['program_name'] ?? '')
                )
            ) {
                continue;
            }

            if (
                $sourceSection !== ''
                && !$this->sameText(
                    $sourceSection,
                    (string) ($enrollment['section_name'] ?? '')
                )
            ) {
                continue;
            }

            if (
                $sourceYearLevel !== ''
                && !$this->sameText(
                    $sourceYearLevel,
                    (string) ($enrollment['year_level'] ?? '')
                )
            ) {
                continue;
            }

            $matches[] = $enrollment;
        }

        return $matches;
    }

    /**
     * @param array<string, int|null> $mapping
     * @return array{rows: array<int, array<string, mixed>>, source_row_count: int, is_truncated: bool}
     */
    private function readMappedRows(
        string $filePath,
        string $extension,
        string $worksheetName,
        int $headerRow,
        int $firstDataRow,
        array $mapping
    ): array {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException('The temporary source is unavailable.');
        }

        $reader = match (strtolower($extension)) {
            'xlsx' => IOFactory::createReader(IOFactory::READER_XLSX),
            'xls' => IOFactory::createReader(IOFactory::READER_XLS),
            'csv' => IOFactory::createReader(IOFactory::READER_CSV),
            default => throw new RuntimeException('Unsupported Class List source type.'),
        };

        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);

        try {
            $worksheet = $spreadsheet->getSheetByName($worksheetName);

            if (!$worksheet instanceof Worksheet) {
                throw new RuntimeException('The selected worksheet is unavailable.');
            }

            if ($headerRow < 1 || $firstDataRow <= $headerRow) {
                throw new RuntimeException('The confirmed source structure is invalid.');
            }

            $highestColumn = min(
                Coordinate::columnIndexFromString(
                    $worksheet->getHighestDataColumn()
                ),
                self::MAX_COLUMNS
            );

            foreach ($mapping as $column) {
                if ($column !== null && ($column < 1 || $column > $highestColumn)) {
                    throw new RuntimeException('A selected source column is unavailable.');
                }
            }

            $rows = [];
            $sourceRowCount = 0;
            $isTruncated = false;
            $lastRow = $worksheet->getHighestDataRow();

            for ($rowNumber = $firstDataRow; $rowNumber <= $lastRow; $rowNumber++) {
                $row = [
                    'source_row_number' => $rowNumber,
                ];

                $hasMappedValue = false;

                foreach ($mapping as $field => $column) {
                    $value = $column === null
                        ? ''
                        : $this->cellText($worksheet, $column, $rowNumber);

                    $row[$field] = $value;
                    $hasMappedValue = $hasMappedValue || $value !== '';
                }

                if (!$hasMappedValue) {
                    continue;
                }

                $sourceRowCount++;

                if (count($rows) >= self::MAX_PREVIEW_ROWS) {
                    $isTruncated = true;
                    continue;
                }

                $rows[] = $row;
            }

            return [
                'rows' => $rows,
                'source_row_count' => $sourceRowCount,
                'is_truncated' => $isTruncated,
            ];
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * @param array<int, string> $studentNumbers
     * @return array<string, array<string, mixed>>
     */
    private function loadStudents(PDO $pdo, array $studentNumbers): array
    {
        if ($studentNumbers === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($studentNumbers), '?'));

        $stmt = $pdo->prepare("
            SELECT
                student_id,
                student_number,
                first_name,
                middle_name,
                last_name,
                suffix,
                status
            FROM students
            WHERE student_number IN ($placeholders)
        ");

        $stmt->execute($studentNumbers);

        $students = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $student) {
            $students[(string) $student['student_number']] = $student;
        }

        return $students;
    }

    /**
     * @param array<int, int> $studentIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function loadAcademicEnrollments(
        PDO $pdo,
        array $studentIds,
        int $schoolYearId
    ): array {
        if ($studentIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));

        $stmt = $pdo->prepare("
            SELECT
                sae.student_academic_enrollment_id,
                sae.student_id,
                sae.semester,
                sae.academic_level,
                sae.program_id,
                sae.section_id,
                sae.year_level,
                sae.status,
                sae.effective_end,
                p.program_code,
                p.program_name,
                sec.section_name
            FROM student_academic_enrollments AS sae
            LEFT JOIN programs AS p
                ON p.program_id = sae.program_id
            LEFT JOIN sections AS sec
                ON sec.section_id = sae.section_id
            WHERE sae.school_year_id = ?
              AND sae.student_id IN ($placeholders)
            ORDER BY
                sae.student_id,
                sae.student_academic_enrollment_id
        ");

        $stmt->execute(array_merge([$schoolYearId], $studentIds));

        $enrollments = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $enrollment) {
            $studentId = (int) $enrollment['student_id'];
            $enrollments[$studentId][] = $enrollment;
        }

        return $enrollments;
    }

    /**
     * @param array<int, int> $studentIds
     * @return array<int, array<string, mixed>>
     */
    private function loadClassEnrollments(
        PDO $pdo,
        array $studentIds,
        int $operationalClassId
    ): array {
        if ($studentIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));

        $stmt = $pdo->prepare("
            SELECT
                student_class_enrollment_id,
                student_id,
                enrollment_id,
                status
            FROM student_class_enrollments
            WHERE operational_class_id = ?
              AND student_id IN ($placeholders)
        ");

        $stmt->execute(array_merge([$operationalClassId], $studentIds));

        $enrollments = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $enrollment) {
            $enrollments[(int) $enrollment['student_id']] = $enrollment;
        }

        return $enrollments;
    }

    private function cellText(
        Worksheet $worksheet,
        int $column,
        int $row
    ): string {
        $coordinate = Coordinate::stringFromColumnIndex($column) . $row;
        $value = $worksheet->getCell($coordinate)->getValue();

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if (is_scalar($value)) {
            return trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
        }

        return '';
    }

    private function sameText(string $left, string $right): bool
    {
        return mb_strtolower(trim($left)) === mb_strtolower(trim($right));
    }
}