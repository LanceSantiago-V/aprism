<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/class_list_import_engine.php';

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
     * @param array<int, array<string, mixed>> $identityReconciliations
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
        array $identityOverrides = [],
        array $identityReconciliations = []
    ): array {
        $sourceInspection = (new ClassListImportEngine())->inspect(
            (string) $source['path'],
            (string) ($source['original_name'] ?? 'class-list'),
            (string) $source['extension'],
            $worksheetName,
            $headerRow,
            $firstDataRow
        );
        $sourceContext = is_array($sourceInspection['source_context'] ?? null)
            ? $sourceInspection['source_context']
            : [
                'supported' => false,
                'values' => [],
                'evidence' => [],
                'conflicts' => [],
                'detected' => false,
                'review_required' => false,
            ];

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

        $unresolvedClassParticipants = $this->loadUnresolvedClassParticipants(
            $pdo,
            (int) $classContext['operational_class_id']
        );
        $allClassParticipants = $this->loadClassParticipants(
            $pdo,
            (int) $classContext['operational_class_id']
        );

        $existingStudentsByName = $this->loadStudentsByName($pdo);

        $studentIds = array_values(array_map(
            static fn(array $student): int => (int) $student['student_id'],
            $existingStudents
        ));
        foreach ($unresolvedClassParticipants as $participants) {
            foreach ($participants as $participant) {
                $studentIds[] = (int) $participant['student_id'];
            }
        }
        $studentIds = array_values(array_unique(array_filter($studentIds, static fn(int $id): bool => $id > 0)));

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
            'identity_suggestions' => 0,
            'identity_conflicts' => 0,
            'identity_ambiguous' => 0,
            'academic_context_resolved' => 0,
            'academic_context_unresolved' => 0,
            'academic_context_ambiguous' => 0,
            'academic_context_conflicts' => 0,
            'already_enrolled' => 0,
            'source_duplicates' => 0,
            'missing_class_participants' => 0,
        ];

        foreach ($sourceRows['rows'] as $row) {
            $resolved = $this->resolveRow(
                $pdo,
                $row,
                $sourceNumberCounts,
                $existingStudents,
                $academicEnrollments,
                $classEnrollments,
                $unresolvedClassParticipants,
                $classContext,
                $identityOverrides[(int) $row['source_row_number']] ?? [],
                $sourceContext,
                $identityReconciliations[(int) $row['source_row_number']] ?? null,
                $existingStudentsByName
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

            if (($resolved['identity_suggestion']['confident'] ?? false) === true) {
                $summary['identity_suggestions']++;
            }

            if ($resolved['identity_status'] === 'Identity conflict') {
                $summary['identity_conflicts']++;
            }

            if ($resolved['identity_status'] === 'Identity ambiguous') {
                $summary['identity_ambiguous']++;
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

            if ($resolved['academic_status'] === 'Academic context conflict') {
                $summary['academic_context_conflicts']++;
            }

            if ($resolved['class_status'] === 'Already enrolled in this class') {
                $summary['already_enrolled']++;
            }

            if ($resolved['source_duplicate'] === true) {
                $summary['source_duplicates']++;
            }
        }

        $sourceParticipantIds = [];
        $sourceNames = [];
        foreach ($resolvedRows as $resolved) {
            if (($resolved['candidate_student_id'] ?? null) !== null) {
                $sourceParticipantIds[(int) $resolved['candidate_student_id']] = true;
            }
            $nameKey = $this->normalizeIdentityName($resolved);
            if ($nameKey !== '') $sourceNames[$nameKey] = true;
        }
        $missingParticipants = [];
        foreach ($allClassParticipants as $participant) {
            $nameKey = $this->normalizeIdentityName($participant);
            if (!isset($sourceParticipantIds[(int) $participant['student_id']]) && !isset($sourceNames[$nameKey])) {
                $missingParticipants[] = $participant;
            }
        }
        $summary['missing_class_participants'] = count($missingParticipants);

        return [
            'source' => [
                'original_name' => (string) $source['original_name'],
                'worksheet_name' => $worksheetName,
                'header_row_number' => $headerRow,
                'first_data_row_number' => $firstDataRow,
            ],
            'source_context' => array_merge(
                $sourceContext,
                ['applies_to_rows' => count($sourceRows['rows'])]
            ),
            'class_context' => [
                'operational_class_id' => (int) $classContext['operational_class_id'],
                'school_year' => (string) $classContext['school_year'],
                'semester' => (string) $classContext['semester'],
                'section_name' => (string) ($classContext['section_name'] ?? ''),
                'program_name' => (string) ($classContext['program_name'] ?? ''),
                'program_code' => (string) ($classContext['program_code'] ?? ''),
                'academic_level' => (string) ($classContext['academic_level'] ?? ''),
                'year_level' => (string) ($classContext['section_year_level'] ?? ''),
            ],
            'summary' => $summary,
            'rows' => $resolvedRows,
            'missing_class_participants' => $missingParticipants,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, int> $sourceNumberCounts
     * @param array<string, array<string, mixed>> $existingStudents
     * @param array<int, array<int, array<string, mixed>>> $academicEnrollments
     * @param array<int, array<string, mixed>> $classEnrollments
     * @param array<string, array<int, array<string, mixed>>> $unresolvedClassParticipants
     * @param array<string, mixed> $classContext
     * @param array<string, string> $identityOverride
     * @param array<string, mixed> $sourceContext
     * @return array<string, mixed>
     */
    private function resolveRow(
        PDO $pdo,
        array $row,
        array $sourceNumberCounts,
        array $existingStudents,
        array $academicEnrollments,
        array $classEnrollments,
        array $unresolvedClassParticipants,
        array $classContext,
        array $identityOverride = [],
        array $sourceContext = [],
        ?array $identityReconciliation = null,
        array $existingStudentsByName = []
    ): array {
        $errors = [];
        $warnings = [];

        $sourceRowValues = [
            'program' => trim((string) ($row['program'] ?? '')),
            'section' => trim((string) ($row['section'] ?? '')),
            'year_level' => trim((string) ($row['year_level'] ?? '')),
        ];
        $sourceContextValues = is_array($sourceContext['values'] ?? null)
            ? $sourceContext['values']
            : [];
        $sourceContextErrors = [];

        foreach (($sourceContext['conflicts'] ?? []) as $conflict) {
            if (!is_array($conflict)) {
                continue;
            }
            $field = trim((string) ($conflict['field'] ?? 'academic placement'));
            $sourceContextErrors[] = sprintf(
                'The source contains conflicting explicit %s metadata.',
                str_replace('_', ' ', $field)
            );
        }

        foreach (['program', 'section', 'year_level'] as $field) {
            $rowValue = $sourceRowValues[$field];
            $contextValue = trim((string) ($sourceContextValues[$field] ?? ''));
            if ($rowValue !== '' && $contextValue !== '' && !$this->sameText($rowValue, $contextValue)) {
                $sourceContextErrors[] = sprintf(
                    'The row %s conflicts with the explicit source-level %s.',
                    $field === 'year_level' ? 'Year Level' : ucfirst($field),
                    $field === 'year_level' ? 'Year Level' : $field
                );
            }
        }

        $sourceSchoolYear = trim((string) ($sourceContextValues['school_year'] ?? ''));
        if ($sourceSchoolYear !== '' && !$this->sameText($sourceSchoolYear, (string) ($classContext['school_year'] ?? ''))) {
            $sourceContextErrors[] = 'The explicit source School Year conflicts with the selected Operational Class context.';
        }

        $sourceSemester = trim((string) ($sourceContextValues['semester'] ?? ''));
        if ($sourceSemester !== '' && !$this->sameText($sourceSemester, (string) ($classContext['semester'] ?? ''))) {
            $sourceContextErrors[] = 'The explicit source Semester conflicts with the selected Operational Class context.';
        }

        $row['source_program_raw'] = $sourceRowValues['program'];
        $row['source_year_level_raw'] = $sourceRowValues['year_level'];
        $row['program'] = $sourceRowValues['program'] !== ''
            ? $sourceRowValues['program']
            : trim((string) ($sourceContextValues['program'] ?? ''));
        $row['section'] = $sourceRowValues['section'] !== ''
            ? $sourceRowValues['section']
            : trim((string) ($sourceContextValues['section'] ?? ''));
        $row['year_level'] = $sourceRowValues['year_level'] !== ''
            ? $sourceRowValues['year_level']
            : trim((string) ($sourceContextValues['year_level'] ?? ''));
        $row['source_academic_level'] = trim((string) ($sourceContextValues['academic_level'] ?? ''));
        $row['source_program_code'] = trim((string) ($sourceContextValues['program_code'] ?? ''));
        $row['source_program_name'] = trim((string) ($sourceContextValues['program_name'] ?? ''));
        $row['source_school_year'] = trim((string) ($sourceContextValues['school_year'] ?? ''));
        $row['source_semester'] = trim((string) ($sourceContextValues['semester'] ?? ''));

        $placementNormalization = $this->normalizePlacementEvidence(
            $pdo,
            (string) ($row['program'] ?? ''),
            (string) ($row['year_level'] ?? '')
        );

        if (($placementNormalization['program']['status'] ?? '') === 'resolved') {
            $row['program'] = (string) ($placementNormalization['program']['code'] ?? $row['program']);
        }

        if (($placementNormalization['year_level']['status'] ?? '') === 'resolved') {
            $row['year_level'] = (string) ($placementNormalization['year_level']['value'] ?? $row['year_level']);
        }

        $studentNumber = trim((string) ($row['student_number'] ?? ''));
        $combinedName = trim((string) ($row['student_name_raw'] ?? ''));
        $firstName = trim((string) ($row['first_name'] ?? ''));
        $lastName = trim((string) ($row['last_name'] ?? ''));
        $middleName = trim((string) ($row['middle_name'] ?? ''));
        $suffix = trim((string) ($row['suffix'] ?? ''));

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
        $sameClassCandidate = null;
        $matchingEnrollmentCount = 0;
        $identityCompletionRequired = false;
        $identityCompletionComplete = false;
        $identitySuggestion = [
            'confident' => false,
            'format' => null,
        ];
        $proposedIdentity = [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'suffix' => $suffix,
        ];

        if (
            $proposedIdentity['first_name'] === ''
            && $proposedIdentity['last_name'] === ''
            && $combinedName !== ''
        ) {
            $suggestion = $this->suggestCombinedName($combinedName);

            if ($suggestion !== null) {
                $proposedIdentity = $suggestion;
                $identitySuggestion = [
                    'confident' => true,
                    'format' => 'Family name, given name(s)',
                ];
            }
        }

        foreach (array_keys($proposedIdentity) as $field) {
            if (array_key_exists($field, $identityOverride)) {
                $proposedIdentity[$field] = trim(
                    (string) $identityOverride[$field]
                );
            }
        }

        if ($structuralStatus === 'Structurally Valid') {
            if ($studentNumber !== '') {
                $existingStudent = $existingStudents[$studentNumber] ?? null;
                $identityKey = $this->normalizeIdentityName($proposedIdentity);
                $sameClassCandidates = $identityKey === '' ? [] : ($unresolvedClassParticipants[$identityKey] ?? []);

                if ($existingStudent === null && count($sameClassCandidates) === 1) {
                    $sameClassCandidate = $sameClassCandidates[0];
                    $selectedCandidateId = (int) ($identityReconciliation['student_id'] ?? 0);
                    if (($identityReconciliation['action'] ?? '') === 'reconcile_same_class'
                        && $selectedCandidateId === (int) $sameClassCandidate['student_id']) {
                        $existingStudent = $sameClassCandidate;
                        $identityStatus = 'Existing unresolved participant selected';
                    } else {
                        $identityStatus = 'Identity reconciliation required';
                        $warnings[] = 'A same-class unresolved participant matches this name. Explicit reconciliation is required before assigning the incoming Student Number.';
                    }
                } elseif ($existingStudent === null && count($sameClassCandidates) > 1) {
                    $identityStatus = 'Identity ambiguous';
                    $warnings[] = 'More than one unresolved class participant matches this normalized name.';
                } elseif ($existingStudent !== null && count($sameClassCandidates) === 1
                    && (int) $sameClassCandidates[0]['student_id'] !== (int) $existingStudent['student_id']) {
                    $identityStatus = 'Identity conflict';
                    $warnings[] = 'The incoming Student Number belongs to a different canonical Student than the unresolved same-class participant. Controlled reconciliation is required.';
                }
            } else {
                $identityKey = $this->normalizeIdentityName($proposedIdentity);
                $sameClassCandidates = $identityKey === ''
                    ? []
                    : ($unresolvedClassParticipants[$identityKey] ?? []);

                if (count($sameClassCandidates) === 1) {
                    $existingStudent = $sameClassCandidates[0];
                    $identityStatus = 'Existing Student matched';
                    // A missing Student Number is expected for this source. It is
                    // shown as neutral pending identity evidence, not a warning.
                } elseif (count($sameClassCandidates) > 1) {
                    $identityStatus = 'Identity ambiguous';
                    $warnings[] = 'More than one unresolved class participant has the same normalized name. Review is required; no Student was selected.';
                }
            }

            if ($existingStudent === null && $identityStatus !== 'Identity reconciliation required') {
                /*
                 * A conventional "Family, Given names" source value is a
                 * deterministic, reviewable suggestion.  The original value
                 * remains student_name_raw evidence and a Teacher can still
                 * replace this suggestion in Review & Fix.
                 */
                $identityCompletionComplete =
                    $proposedIdentity['first_name'] !== ''
                    && $proposedIdentity['last_name'] !== '';

                $identityCompletionRequired =
                    !$sourceDuplicate && !$identityCompletionComplete;

                if ($identityCompletionComplete && !$sourceDuplicate) {
                    $identityStatus = 'New Student candidate';
                    // Student Number is intentionally absent from this source.
                    // The later authoritative Grade Workbook reconciliation path
                    // remains responsible for completing that identity field.
                } else {
                    $identityStatus = 'Identity incomplete';
                    $warnings[] =
                        $sourceDuplicate
                        ? 'Duplicate Student Number rows cannot be prepared as a new Student candidate.'
                        : 'Complete First Name and Last Name. The source name remains evidence until a reviewed structured identity is available.';
                }

                $academicStatus = 'Academic context unresolved';
                $classStatus = 'Not enrolled';
            } elseif ($existingStudent !== null) {
                if ($identityStatus !== 'Existing unresolved participant selected') {
                    $identityStatus = 'Existing Student matched';
                }

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

                    if (
                        $this->hasPartialPlacementEvidence($row)
                        && $this->hasCurrentContextConflict(
                            $academicEnrollments[$studentId] ?? [],
                            $row,
                            $classContext
                        )
                    ) {
                        $academicStatus = 'Academic context conflict';
                        $warnings[] =
                            'Explicit placement information conflicts with the existing current Academic Enrollment. No placement will be overwritten.';
                    }

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

        $sameNameDifferentNumber = false;
        $identityKey = $this->normalizeIdentityName($proposedIdentity);
        if ($studentNumber !== '' && $identityKey !== '' && isset($existingStudentsByName[$identityKey])) {
            foreach ($existingStudentsByName[$identityKey] as $namedStudent) {
                if ((string) ($namedStudent['student_number'] ?? '') !== ''
                    && (string) $namedStudent['student_number'] !== $studentNumber) {
                    $sameNameDifferentNumber = true;
                    if ($identityStatus === 'New Student candidate') {
                        $identityStatus = 'Identity conflict';
                        $warnings[] = 'This name already belongs to a different canonical Student Number. Review is required; no name-only merge was performed.';
                    }
                }
            }
        }

        if ($sourceDuplicate) {
            $identityStatus = 'Duplicate Student Number in source';
            $warnings[] = 'Duplicate Student Number in source. No Student identity can be assigned until the source is corrected.';
        }

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
            'source_context' => $sourceContextValues,
            'source_context_status' => $sourceContextErrors !== []
                ? 'CONFLICT'
                : (($sourceContext['detected'] ?? false) === true ? 'DETECTED' : 'NOT DETECTED'),
            'source_context_errors' => array_values(array_unique($sourceContextErrors)),
            'mapped_source_context' => $sourceRowValues,
            'structural_status' => $structuralStatus,
            'identity_status' => $identityStatus,
            'academic_status' => $academicStatus,
            'class_status' => $classStatus,
            'source_duplicate' => $sourceDuplicate,
            'candidate_student_id' => ($existingStudent ?? $sameClassCandidate) !== null
                ? (int) ($existingStudent ?? $sameClassCandidate)['student_id']
                : null,
            'identity_resolution' => $identityStatus === 'Existing unresolved participant selected'
                ? 'Same-class unresolved participant selected for explicit Student Number reconciliation'
                : ($studentNumber === '' && $existingStudent !== null
                    ? 'Unresolved same-class participant matched by reviewed name evidence'
                    : null),
            'same_class_candidate_student_id' => $sameClassCandidate !== null ? (int) $sameClassCandidate['student_id'] : null,
            'same_name_different_number' => $sameNameDifferentNumber,
            'identity_reconciliation_required' => $identityStatus === 'Identity reconciliation required',
            'candidate_student_status' => $existingStudent !== null
                ? (string) $existingStudent['status']
                : null,
            'candidate_academic_enrollment_id' => $candidateEnrollment !== null
                ? (int) $candidateEnrollment['student_academic_enrollment_id']
                : null,
            'academic_evidence' => array_merge(
                $academicEvidence,
                ['normalization' => $placementNormalization]
            ),
            'identity_completion_required' => $identityCompletionRequired,
            'identity_completion_complete' => $identityCompletionComplete,
            'student_number_pending' => $studentNumber === '',
            'proposed_identity' => $proposedIdentity,
            'identity_suggestion' => $identitySuggestion,
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
            'academic_level' => trim((string) ($row['source_academic_level'] ?? '')),
            'program' => trim((string) ($row['source_program_raw'] ?? $row['program'] ?? '')),
            'section' => trim((string) ($row['section'] ?? '')),
            'year_level' => trim((string) ($row['source_year_level_raw'] ?? $row['year_level'] ?? '')),
            'school_year' => trim((string) ($row['source_school_year'] ?? '')),
            'semester' => trim((string) ($row['source_semester'] ?? '')),
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

        if (($row['source_context_errors'] ?? []) !== []) {
            $evidence['reason'] = 'Explicit source-level placement metadata conflicts with this row or the selected class context.';
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
            $hasExplicitPlacement = $this->hasPartialPlacementEvidence($row);
            $evidence['reason'] = $hasExplicitPlacement
                ? 'Exactly one existing Academic Enrollment in the selected School Year and Semester matches the explicit mapped placement evidence. It can be reused without creating a duplicate.'
                : 'Exactly one existing current Academic Enrollment matches the selected School Year and Semester. It can be reused without asking the Teacher to re-enter placement.';
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
     * Normalize only source representations that can be matched to one active
     * institutional Program without guessing. Missing or ambiguous mappings
     * remain evidence and never block the SCE-first import path.
     *
     * @return array<string, mixed>
     */
    private function normalizePlacementEvidence(
        PDO $pdo,
        string $programValue,
        string $yearLevel
    ): array {
        $program = [
            'status' => $programValue === '' ? 'not_supplied' : 'unresolved',
            'source' => $programValue,
            'program_id' => null,
            'code' => null,
            'name' => null,
            'reason' => null,
        ];

        if ($programValue !== '') {
            try {
                $stmt = $pdo->query("\n                    SELECT program_id, program_code, program_name\n                    FROM programs\n                    WHERE status = 'Active'\n                    ORDER BY program_id\n                ");
                $matches = [];
                $sourceSignature = $this->programSignature($programValue);

                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
                    if ($sourceSignature === '' || $sourceSignature !== $this->programSignature(
                        (string) ($candidate['program_name'] ?? '')
                    )) {
                        continue;
                    }

                    $matches[] = $candidate;
                }

                if (count($matches) === 1) {
                    $program = [
                        'status' => 'resolved',
                        'source' => $programValue,
                        'program_id' => (int) $matches[0]['program_id'],
                        'code' => (string) ($matches[0]['program_code'] ?? ''),
                        'name' => (string) ($matches[0]['program_name'] ?? ''),
                        'reason' => 'Matched one active institutional Program by deterministic STI source normalization.',
                    ];
                } elseif (count($matches) > 1) {
                    $program['status'] = 'ambiguous';
                    $program['reason'] = 'More than one active institutional Program matches the normalized source representation.';
                } else {
                    $program['reason'] = 'No single active institutional Program matches the normalized source representation.';
                }
            } catch (Throwable $exception) {
                // Placement enrichment must never turn a valid class roster
                // into a failed import when the catalog is temporarily
                // unavailable. The raw source value remains available.
                $program['reason'] = 'Institutional Program normalization was unavailable; source evidence was retained.';
            }
        }

        $year = [
            'status' => $yearLevel === '' ? 'not_supplied' : 'unresolved',
            'source' => $yearLevel,
            'value' => null,
            'reason' => null,
        ];

        if ($yearLevel !== '' && in_array($yearLevel, ['1', '2', '3', '4', '11', '12'], true)) {
            $year['status'] = 'resolved';
            $year['value'] = $yearLevel;
            $year['reason'] = 'Source value already matches APRISM’s supported Year Level vocabulary.';
        } elseif ($yearLevel !== '') {
            $year['reason'] = 'The source Level format was retained because its Year Level semantics are not established by APRISM reference data.';
        }

        return [
            'program' => $program,
            'year_level' => $year,
        ];
    }

    private function programSignature(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['&', '/', '-', '(', ')', ',', '.'], ' ', $value);
        $value = preg_replace('/\bang\b/u', 'and', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        $tokens = array_values(array_filter(
            explode(' ', trim($value)),
            static function (string $token): bool {
                if ($token === '') {
                    return false;
                }

                return !in_array($token, [
                    'bachelor',
                    'bs',
                    'science',
                    'in',
                    'of',
                    'the',
                    'and',
                    'free',
                    'plan',
                ], true) && !preg_match('/^bs[a-z0-9]+$/', $token);
            }
        ));

        sort($tokens, SORT_STRING);
        return implode(' ', array_values(array_unique($tokens)));
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
        $sourceAcademicLevel = trim((string) ($row['source_academic_level'] ?? ''));

        $matches = [];

        foreach ($enrollments as $enrollment) {
            if (
                (string) ($enrollment['status'] ?? '') !== 'Active'
                || (string) ($enrollment['effective_start'] ?? '') > date('Y-m-d')
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
                $sourceAcademicLevel !== ''
                && !$this->sameText(
                    $sourceAcademicLevel,
                    (string) ($enrollment['academic_level'] ?? '')
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

    /** @param array<string, mixed> $row */
    private function hasPartialPlacementEvidence(array $row): bool
    {
        $values = [
            trim((string) ($row['program'] ?? '')),
            trim((string) ($row['section'] ?? '')),
            trim((string) ($row['year_level'] ?? '')),
            trim((string) ($row['source_academic_level'] ?? '')),
        ];

        return count(array_filter($values, static fn(string $value): bool => $value !== '')) > 0;
    }

    /**
     * Detect a partial explicit placement mismatch against one current
     * context. Participation may still be imported, but established academic
     * placement must not be silently replaced.
     *
     * @param array<int, array<string, mixed>> $enrollments
     * @param array<string, mixed> $row
     * @param array<string, mixed> $classContext
     */
    private function hasCurrentContextConflict(
        array $enrollments,
        array $row,
        array $classContext
    ): bool {
        $current = $this->currentContextEnrollments($enrollments, $classContext);

        if (count($current) !== 1) {
            return false;
        }

        $enrollment = $current[0];
        $comparisons = [
            [
                'value' => $row['source_academic_level'] ?? '',
                'existing' => $enrollment['academic_level'] ?? '',
            ],
            [
                'value' => $row['program'] ?? '',
                'existing' => $enrollment['program_code'] ?? '',
                'alternative' => $enrollment['program_name'] ?? '',
            ],
            [
                'value' => $row['section'] ?? '',
                'existing' => $enrollment['section_name'] ?? '',
            ],
            [
                'value' => $row['year_level'] ?? '',
                'existing' => $enrollment['year_level'] ?? '',
            ],
        ];

        foreach ($comparisons as $comparison) {
            $value = trim((string) ($comparison['value'] ?? ''));
            if ($value === '') {
                continue;
            }

            $matchesExisting = $this->sameText(
                $value,
                (string) ($comparison['existing'] ?? '')
            );
            if (isset($comparison['alternative'])) {
                $matchesExisting = $matchesExisting
                    || $this->sameText($value, (string) $comparison['alternative']);
            }

            if (!$matchesExisting) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, array<string, mixed>> $enrollments */
    private function currentContextEnrollments(
        array $enrollments,
        array $classContext
    ): array {
        $matches = [];

        foreach ($enrollments as $enrollment) {
            if (
                (string) ($enrollment['status'] ?? '') !== 'Active'
                || (string) ($enrollment['effective_start'] ?? '') > date('Y-m-d')
                || ($enrollment['effective_end'] ?? null) !== null
                || (string) ($enrollment['semester'] ?? '') !== (string) ($classContext['semester'] ?? '')
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

    /** @return array<string, array<int, array<string, mixed>>> */
    private function loadStudentsByName(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT student_id, student_number, first_name, middle_name, last_name, suffix, status FROM students WHERE status = 'Active'");
        $students = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $student) {
            $key = $this->normalizeIdentityName($student);
            if ($key !== '') {
                $students[$key][] = $student;
            }
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
                sae.effective_start,
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

    /**
     * Load only active class participants whose permanent institutional number
     * is still unresolved. These rows are scoped to the selected Operational
     * Class; a name match here is never a global Student merge.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function loadClassParticipants(PDO $pdo, int $operationalClassId): array
    {
        $stmt = $pdo->prepare("SELECT s.student_id, s.student_number, s.first_name, s.middle_name, s.last_name, s.suffix, s.status, sce.student_class_enrollment_id FROM student_class_enrollments AS sce INNER JOIN students AS s ON s.student_id = sce.student_id WHERE sce.operational_class_id = ? AND sce.status = 'Active' ORDER BY s.last_name, s.first_name, s.student_id");
        $stmt->execute([$operationalClassId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function loadUnresolvedClassParticipants(
        PDO $pdo,
        int $operationalClassId
    ): array {
        $stmt = $pdo->prepare("\n            SELECT\n                s.student_id,\n                s.student_number,\n                s.first_name,\n                s.middle_name,\n                s.last_name,\n                s.suffix,\n                s.status,\n                sce.student_class_enrollment_id,\n                sce.status AS class_enrollment_status\n            FROM student_class_enrollments AS sce\n            INNER JOIN students AS s\n                ON s.student_id = sce.student_id\n            WHERE sce.operational_class_id = ?\n              AND sce.status = 'Active'\n              AND s.student_number IS NULL\n            ORDER BY s.last_name, s.first_name, s.student_id\n        ");
        $stmt->execute([$operationalClassId]);

        $participants = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $participant) {
            if ((string) ($participant['status'] ?? '') !== 'Active') {
                continue;
            }

            $identityKey = $this->normalizeIdentityName($participant);
            if ($identityKey === '') {
                continue;
            }

            $participants[$identityKey][] = $participant;
        }

        return $participants;
    }

    /**
     * Normalize only canonical identity fields. This is intentionally exact
     * after punctuation/whitespace normalization; no fuzzy matching occurs.
     *
     * @param array<string, mixed> $identity
     */
    private function normalizeIdentityName(array $identity): string
    {
        $parts = [
            (string) ($identity['first_name'] ?? ''),
            (string) ($identity['middle_name'] ?? ''),
            (string) ($identity['last_name'] ?? ''),
            (string) ($identity['suffix'] ?? ''),
        ];
        $value = mb_strtolower(trim(implode(' ', array_filter(
            array_map(static fn(string $part): string => trim($part), $parts),
            static fn(string $part): bool => $part !== ''
        ))));
        $normalized = preg_replace('/[^\\p{L}\\p{N}]+/u', ' ', $value);

        return trim((string) ($normalized ?? $value));
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

    /**
     * Return a suggestion only for the unambiguous roster convention
     * "Family name, given name(s)".  It deliberately does not attempt to
     * interpret space-only names, multiple commas, initials, or other local
     * naming conventions.
     *
     * @return array{first_name: string, middle_name: string, last_name: string, suffix: string}|null
     */
    private function suggestCombinedName(string $value): ?array
    {
        $parts = explode(',', trim($value));

        if (count($parts) !== 2) {
            return null;
        }

        $lastName = trim($parts[0]);
        $givenNames = trim($parts[1]);

        if ($lastName === '' || $givenNames === '') {
            return null;
        }

        /* Do not "recognize" a suffix by guessing; keep it in the given name. */
        if (
            preg_match('/^[[:alpha:]][[:alpha:] .\'’-]*$/u', $lastName) !== 1
            || preg_match('/^[[:alpha:]][[:alpha:] .\'’-]*$/u', $givenNames) !== 1
        ) {
            return null;
        }

        return [
            'first_name' => $givenNames,
            'middle_name' => '',
            'last_name' => $lastName,
            'suffix' => '',
        ];
    }

    private function sameText(string $left, string $right): bool
    {
        return mb_strtolower(trim($left)) === mb_strtolower(trim($right));
    }
}
