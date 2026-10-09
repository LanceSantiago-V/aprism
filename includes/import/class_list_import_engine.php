<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

require_once __DIR__ . '/../../vendor/autoload.php';

final class ClassListImportEngine
{
    private const MAX_HEADER_SCAN_ROWS = 25;
    private const MAX_SAMPLE_ROWS = 30;
    private const MAX_COLUMNS = 80;

    /** @return array<string, mixed> */
    public function inspect(string $filePath, string $originalFileName, string $extension, ?string $sheetName = null, ?int $headerRow = null, ?int $firstDataRow = null): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException('The uploaded temporary source file is unavailable or unreadable.');
        }

        $reader = $this->createReader($extension);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);

        try {
            $selection = $this->selectWorksheet($spreadsheet, $sheetName);
            $worksheet = $selection['worksheet'];

            if (!$worksheet instanceof Worksheet) {
                throw new RuntimeException('The workbook does not contain a readable worksheet.');
            }

            $headerAnalysis = $this->detectHeader($worksheet);
            $suggestedHeaderRow = $headerAnalysis['row'];
            $headerRow ??= $suggestedHeaderRow;

            if ($headerRow === null) {
                throw new RuntimeException('No source header row could be detected in the first 25 rows.');
            }

            $headers = $this->extractHeaders($worksheet, $headerRow);

            if ($headers === []) {
                throw new RuntimeException('The selected header row has no readable text labels.');
            }

            $firstDataRow ??= $headerRow + 1;
            $firstDataRow = max($headerRow + 1, $firstDataRow);
            $automaticMapping = $this->automaticMapping($headers);
            $sourceContext = $this->extractSourceContext(
                $worksheet,
                $headerRow,
                $firstDataRow,
                $extension
            );
            $mappingConfident = $automaticMapping['student_name_raw'] !== null
                || ($automaticMapping['first_name'] !== null && $automaticMapping['last_name'] !== null);
            $needsCorrection = [];

            if ($sheetName === null && $selection['confident'] !== true)
                $needsCorrection[] = 'worksheet';
            if ($headerRow !== $suggestedHeaderRow || $headerAnalysis['confident'] !== true)
                $needsCorrection[] = 'header_row';
            if ($firstDataRow !== $headerRow + 1)
                $needsCorrection[] = 'first_data_row';
            if (!$mappingConfident)
                $needsCorrection[] = 'mapping';

            return [
                'source' => ['original_name' => $originalFileName, 'extension' => $extension],
                'sheets' => $this->sheetMetadata($spreadsheet),
                'selected_sheet' => ['name' => $worksheet->getTitle()],
                'header_row_number' => $headerRow,
                'suggested_header_row_number' => $suggestedHeaderRow,
                'first_data_row_number' => $firstDataRow,
                'max_row_number' => min($worksheet->getHighestDataRow(), 200),
                'headers' => $headers,
                'sample_rows' => $this->extractSampleRows($worksheet, $firstDataRow, $headers),
                'structure_preview' => $this->extractStructurePreview($worksheet, $headerRow, $firstDataRow),
                'source_context' => $sourceContext,
                'sample_limit' => self::MAX_SAMPLE_ROWS,
                'analysis' => [
                    'automatic_mapping' => $automaticMapping,
                    'worksheet_confident' => $selection['confident'],
                    'header_confident' => $headerAnalysis['confident'],
                    'mapping_confident' => $mappingConfident,
                    'needs_correction' => $needsCorrection,
                ],
            ];
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /** @return array<string, mixed> */
    public function extract(string $filePath, string $originalFileName, string $extension): array
    {
        return $this->inspect($filePath, $originalFileName, $extension);
    }

    private function createReader(string $extension): \PhpOffice\PhpSpreadsheet\Reader\IReader
    {
        return match (strtolower($extension)) {
            'xlsx' => IOFactory::createReader(IOFactory::READER_XLSX),
            'xls' => IOFactory::createReader(IOFactory::READER_XLS),
            'csv' => IOFactory::createReader(IOFactory::READER_CSV),
            default => throw new RuntimeException('No spreadsheet reader is available for this file type.'),
        };
    }

    /** @return array<int, array{name: string, index: int}> */
    private function sheetMetadata(Spreadsheet $spreadsheet): array
    {
        $sheets = [];
        foreach ($spreadsheet->getWorksheetIterator() as $index => $worksheet)
            $sheets[] = ['name' => $worksheet->getTitle(), 'index' => $index];
        return $sheets;
    }

    /** @return array{worksheet: ?Worksheet, confident: bool} */
    private function selectWorksheet(Spreadsheet $spreadsheet, ?string $sheetName): array
    {
        if ($sheetName !== null && $sheetName !== '') {
            foreach ($spreadsheet->getWorksheetIterator() as $worksheet)
                if ($worksheet->getTitle() === $sheetName)
                    return ['worksheet' => $worksheet, 'confident' => true];
        }
        $best = null;
        $bestScore = -1;
        $ties = 0;
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            if ($worksheet->getHighestDataRow() < 1)
                continue;
            $analysis = $this->detectHeader($worksheet);
            if ($analysis['score'] > $bestScore) {
                $best = $worksheet;
                $bestScore = $analysis['score'];
                $ties = 1;
            } elseif ($analysis['score'] === $bestScore)
                $ties++;
        }
        return ['worksheet' => $best, 'confident' => $best !== null && $bestScore >= 60 && $ties === 1];
    }

    /** @return array{row: ?int, score: int, confident: bool} */
    private function detectHeader(Worksheet $worksheet): array
    {
        $highestRow = min($worksheet->getHighestDataRow(), self::MAX_HEADER_SCAN_ROWS);
        $highestColumn = min(Coordinate::columnIndexFromString($worksheet->getHighestDataColumn()), self::MAX_COLUMNS);
        $bestRow = null;
        $bestScore = -1;
        for ($row = 1; $row <= $highestRow; $row++) {
            $labels = 0;
            $recognized = 0;
            $nonEmpty = 0;
            $identityFields = [];
            for ($column = 1; $column <= $highestColumn; $column++) {
                $value = $this->cellText($worksheet, $column, $row);
                if ($value === '')
                    continue;
                $nonEmpty++;
                if (!$this->isUsefulHeaderLabel($value))
                    continue;
                $labels++;
                $field = $this->canonicalField($value);
                if ($field !== null) {
                    $recognized++;
                    if (in_array($field, ['student_number', 'student_name_raw', 'first_name', 'last_name'], true))
                        $identityFields[$field] = true;
                }
            }
            if ($labels < 2)
                continue;
            $continuation = 0;
            for ($next = $row + 1; $next <= min($worksheet->getHighestDataRow(), $row + 10); $next++) {
                $values = 0;
                for ($column = 1; $column <= $highestColumn; $column++)
                    if ($this->cellText($worksheet, $column, $next) !== '')
                        $values++;
                if ($values >= 2)
                    $continuation++;
            }
            // A roster identity pair is stronger evidence than a generic
            // assessment/template row with many text labels.
            $identityBonus = 0;
            if (isset($identityFields['student_number'], $identityFields['student_name_raw']))
                $identityBonus = 500;
            elseif (isset($identityFields['student_number']) && (isset($identityFields['first_name']) || isset($identityFields['last_name'])))
                $identityBonus = 460;
            elseif (isset($identityFields['first_name'], $identityFields['last_name']))
                $identityBonus = 420;
            elseif (isset($identityFields['student_number']) || isset($identityFields['student_name_raw']))
                $identityBonus = 120;
            $score = ($labels * 8) + ($recognized * 35) + $continuation + $identityBonus - max(0, $nonEmpty - $labels);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRow = $row;
            }
        }
        return ['row' => $bestRow, 'score' => max(0, $bestScore), 'confident' => $bestRow !== null && $bestScore >= 60];
    }

    /** @return array<int, array{column_index: int, column_letter: string, label: string, raw_label: string}> */
    private function extractHeaders(Worksheet $worksheet, int $headerRow): array
    {
        $headers = [];
        $highestColumn = min(Coordinate::columnIndexFromString($worksheet->getHighestDataColumn()), self::MAX_COLUMNS);
        for ($column = 1; $column <= $highestColumn; $column++) {
            $label = $this->cellText($worksheet, $column, $headerRow);
            // A grade/date value is evidence in the sheet, never a mappable student-field label.
            if (!$this->isUsefulHeaderLabel($label))
                continue;
            $letter = Coordinate::stringFromColumnIndex($column);
            $headers[] = ['column_index' => $column, 'column_letter' => $letter, 'label' => $label . ' (' . $letter . ')', 'raw_label' => $label];
        }
        return $headers;
    }

    /** @param array<int, array{column_index: int, raw_label: string}> $headers
     * @return array<string, ?int>
     */
    private function automaticMapping(array $headers): array
    {
        $mapping = array_fill_keys(['student_number', 'student_name_raw', 'first_name', 'middle_name', 'last_name', 'suffix', 'program', 'section', 'year_level'], null);
        foreach ($headers as $header) {
            $field = $this->canonicalField($header['raw_label']);
            if ($field !== null && $mapping[$field] === null)
                $mapping[$field] = $header['column_index'];
        }
        return $mapping;
    }

    private function canonicalField(string $label): ?string
    {
        $label = strtolower(trim($label));
        $label = trim((string) preg_replace('/[^a-z0-9]+/', ' ', $label));
        return match ($label) {
            'student no', 'student number', 'student id', 'id number' => 'student_number',
            // In this Class List source, an exact `ID` header is the
            // authoritative institutional Student Number column.  The engine
            // is Class-List-specific, so this does not broaden generic import
            // mapping rules elsewhere.
            'id' => 'student_number',
            'student name', 'full name', 'name' => 'student_name_raw',
            'first name', 'given name' => 'first_name', 'middle name' => 'middle_name',
            'last name', 'family name', 'surname' => 'last_name', 'suffix' => 'suffix',
            'program', 'course', 'program and plan' => 'program', 'section' => 'section', 'year level', 'year', 'level' => 'year_level',
            default => null,
        };
    }

    private function isUsefulHeaderLabel(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 120 || str_starts_with($value, '='))
            return false;
        if (is_numeric(str_replace(',', '', $value)))
            return false;
        return preg_match('/[[:alpha:]]/u', $value) === 1;
    }

    /**
     * Detect only explicitly labelled placement values outside the roster
     * rows. Worksheet names, filenames, subjects, and class ownership are
     * deliberately not treated as academic placement evidence.
     *
     * @return array<string, mixed>
     */
    private function extractSourceContext(
        Worksheet $worksheet,
        int $headerRow,
        int $firstDataRow,
        string $extension
    ): array {
        $empty = [
            'supported' => strtolower($extension) !== 'csv',
            'values' => [],
            'evidence' => [],
            'conflicts' => [],
            'detected' => false,
            'review_required' => false,
        ];

        if (strtolower($extension) === 'csv') {
            return $empty;
        }

        $labels = [
            'academic_level' => [
                'academic level',
            ],
            'program_code' => [
                'program code',
                'course code',
                'strand code',
            ],
            'program_name' => [
                'program name',
                'strand name',
            ],
            'program' => [
                'program',
                'course',
                'strand',
            ],
            'section' => [
                'section',
                'section name',
            ],
            'year_level' => [
                'year level',
                'grade level',
            ],
            'school_year' => [
                'school year',
                'academic year',
                'sy',
            ],
            'semester' => [
                'semester',
                'teaching context',
            ],
        ];

        $highestColumn = min(
            Coordinate::columnIndexFromString($worksheet->getHighestDataColumn()),
            self::MAX_COLUMNS
        );
        $scanTo = min($worksheet->getHighestDataRow(), $firstDataRow - 1, self::MAX_HEADER_SCAN_ROWS);
        $candidates = [];

        $addCandidate = function (string $field, string $value, int $row, int $column, string $label) use (&$candidates): void {
            $value = $this->normalizeSourceContextValue($field, $value);
            if ($value === '') {
                return;
            }

            $candidates[$field][] = [
                'value' => $value,
                'row' => $row,
                'column' => $column,
                'label' => $label,
            ];
        };

        for ($row = 1; $row <= $scanTo; $row++) {
            if ($row === $headerRow) {
                continue;
            }

            for ($column = 1; $column <= $highestColumn; $column++) {
                $text = $this->cellText($worksheet, $column, $row);
                if ($text === '') {
                    continue;
                }

                $handled = false;
                foreach ($labels as $field => $fieldLabels) {
                    foreach ($fieldLabels as $label) {
                        $labelPattern = preg_quote($label, '/');
                        if (preg_match('/^\s*' . $labelPattern . '\s*:\s*(.+?)\s*$/i', $text, $match) === 1) {
                            $addCandidate($field, $match[1], $row, $column, $label);
                            $handled = true;
                            break;
                        }

                        if ($this->normalizeSourceContextLabel($text) !== $this->normalizeSourceContextLabel($label)) {
                            continue;
                        }

                        $adjacent = $column < $highestColumn
                            ? $this->cellText($worksheet, $column + 1, $row)
                            : '';
                        if ($adjacent !== '') {
                            $addCandidate($field, $adjacent, $row, $column + 1, $label);
                        } elseif ($row + 1 < $firstDataRow) {
                            $below = $this->cellText($worksheet, $column, $row + 1);
                            if ($below !== '') {
                                $addCandidate($field, $below, $row + 1, $column, $label);
                            }
                        }
                        $handled = true;
                        break;
                    }
                    if ($handled) {
                        break;
                    }
                }
                if ($handled) {
                    continue;
                }
            }
        }

        $values = [];
        $evidence = [];
        $conflicts = [];

        foreach ($candidates as $field => $fieldCandidates) {
            $distinct = [];
            foreach ($fieldCandidates as $candidate) {
                $known = false;
                foreach ($distinct as $existing) {
                    if (strcasecmp($existing, $candidate['value']) === 0) {
                        $known = true;
                        break;
                    }
                }
                if (!$known) {
                    $distinct[] = $candidate['value'];
                }
            }

            $evidence[$field] = $fieldCandidates;
            if (count($distinct) === 1) {
                $values[$field] = $distinct[0];
            } elseif (count($distinct) > 1) {
                $conflicts[] = [
                    'field' => $field,
                    'values' => $distinct,
                ];
            }
        }

        // A labelled Program Code or Program Name is explicit program
        // evidence. It can provide the compact program value when a generic
        // Program label is not present; it is never derived from a Section.
        if (!isset($values['program'])) {
            $values['program'] = $values['program_code'] ?? ($values['program_name'] ?? null);
            if ($values['program'] === null) {
                unset($values['program']);
            }
        }

        return [
            'supported' => true,
            'values' => $values,
            'evidence' => $evidence,
            'conflicts' => $conflicts,
            'detected' => $values !== [] || $conflicts !== [],
            'review_required' => $conflicts !== [],
        ];
    }

    private function normalizeSourceContextLabel(string $value): string
    {
        $value = strtolower($value);
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    private function normalizeSourceContextValue(string $field, string $value): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', $value));
        if ($field === 'academic_level') {
            return match ($this->normalizeSourceContextLabel($value)) {
                'college' => 'College',
                'shs', 'senior high', 'senior high school' => 'Senior High School',
                default => $value,
            };
        }
        if ($field === 'year_level') {
            return preg_match('/^(1|2|3|4|11|12)(?:\.0+)?$/', $value, $match) === 1
                ? $match[1]
                : $value;
        }
        if ($field === 'semester') {
            return match ($this->normalizeSourceContextLabel($value)) {
                'first semester', '1st semester', 'first sem', '1st sem' => 'First Semester',
                'second semester', '2nd semester', 'second sem', '2nd sem' => 'Second Semester',
                default => $value,
            };
        }
        return $value;
    }

    /** @param array<int, array{column_index: int}> $headers
     * @return array<int, array{row_number: int, values: array<int, string>}>
     */
    private function extractSampleRows(Worksheet $worksheet, int $firstDataRow, array $headers): array
    {
        $rows = [];
        for ($row = $firstDataRow; $row <= $worksheet->getHighestDataRow() && count($rows) < self::MAX_SAMPLE_ROWS; $row++) {
            $values = [];
            $hasValue = false;
            foreach ($headers as $header) {
                $column = $header['column_index'];
                $value = $this->cellText($worksheet, $column, $row);
                $values[$column] = $value;
                $hasValue = $hasValue || $value !== '';
            }
            if ($hasValue)
                $rows[] = ['row_number' => $row, 'values' => $values];
        }
        return $rows;
    }

    /** @return array<string, mixed> */
    private function extractStructurePreview(Worksheet $worksheet, int $headerRow, int $firstDataRow): array
    {
        $from = max(1, min($headerRow, $firstDataRow) - 4);
        $to = min($worksheet->getHighestDataRow(), max($headerRow, $firstDataRow) + 8);
        $columns = min(Coordinate::columnIndexFromString($worksheet->getHighestDataColumn()), 16);
        $rows = [];
        for ($row = $from; $row <= $to; $row++) {
            $values = [];
            for ($column = 1; $column <= $columns; $column++)
                $values[] = $this->cellText($worksheet, $column, $row);
            $rows[] = ['row_number' => $row, 'values' => $values];
        }
        return ['from_row' => $from, 'to_row' => $to, 'column_count' => $columns, 'rows' => $rows];
    }

    private function cellText(Worksheet $worksheet, int $column, int $row): string
    {
        $value = $worksheet->getCell(Coordinate::stringFromColumnIndex($column) . $row)->getValue();
        if ($value === null)
            return '';
        if (is_bool($value))
            return $value ? 'TRUE' : 'FALSE';
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
