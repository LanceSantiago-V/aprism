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

    /**
     * Extract source metadata and a bounded raw preview only.
     * This class intentionally has no database dependency or persistence path.
     *
     * @return array<string, mixed>
     */
    public function inspect(string $filePath, string $originalFileName, string $extension, ?string $sheetName = null, ?int $headerRow = null, ?int $firstDataRow = null): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException('The uploaded temporary source file is unavailable or unreadable.');
        }

        $reader = $this->createReader($extension);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($filePath);

        try {
            $sheets = $this->sheetMetadata($spreadsheet);
            $worksheet = $this->worksheetByName($spreadsheet, $sheetName) ?? $this->firstReadableWorksheet($spreadsheet);

            if ($worksheet === null) {
                throw new RuntimeException('The workbook does not contain a readable worksheet.');
            }

            $suggestedHeaderRow = $this->detectHeaderRow($worksheet);
            $headerRow ??= $suggestedHeaderRow;

            if ($headerRow === null) {
                throw new RuntimeException('No source header row could be detected in the first 25 rows.');
            }

            $headers = $this->extractHeaders($worksheet, $headerRow);

            if ($headers === []) {
                throw new RuntimeException('The detected source header row has no usable columns.');
            }

            $firstDataRow ??= $headerRow + 1;
            $firstDataRow = max($headerRow + 1, $firstDataRow);
            return [
                'source' => [
                    'original_name' => $originalFileName,
                    'extension' => $extension,
                ],
                'sheets' => $sheets,
                'selected_sheet' => [
                    'name' => $worksheet->getTitle(),
                ],
                'header_row_number' => $headerRow,
                'suggested_header_row_number' => $suggestedHeaderRow,
                'first_data_row_number' => $firstDataRow,
                'max_row_number' => min($worksheet->getHighestDataRow(), 200),
                'headers' => $headers,
                'sample_rows' => $this->extractSampleRows($worksheet, $firstDataRow, $headers),
                'structure_preview' => $this->extractStructurePreview($worksheet, $headerRow, $firstDataRow),
                'sample_limit' => self::MAX_SAMPLE_ROWS,
            ];
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

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

        foreach ($spreadsheet->getWorksheetIterator() as $index => $worksheet) {
            $sheets[] = [
                'name' => $worksheet->getTitle(),
                'index' => $index,
            ];
        }

        return $sheets;
    }

    private function firstReadableWorksheet(Spreadsheet $spreadsheet): ?Worksheet
    {
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            if ($worksheet->getHighestDataRow() >= 1) {
                return $worksheet;
            }
        }

        return null;
    }

    private function worksheetByName(Spreadsheet $spreadsheet, ?string $sheetName): ?Worksheet
    {
        if ($sheetName === null || $sheetName === '')
            return null;
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            if ($worksheet->getTitle() === $sheetName)
                return $worksheet;
        }
        return null;
    }

    private function detectHeaderRow(Worksheet $worksheet): ?int
    {
        $highestRow = min($worksheet->getHighestDataRow(), self::MAX_HEADER_SCAN_ROWS);
        $highestColumn = min(
            Coordinate::columnIndexFromString($worksheet->getHighestDataColumn()),
            self::MAX_COLUMNS
        );

        $bestRow = null;
        $bestScore = -1;
        for ($row = 1; $row <= $highestRow; $row++) {
            $nonEmpty = 0;

            for ($column = 1; $column <= $highestColumn; $column++) {
                if ($this->cellText($worksheet, $column, $row) !== '') {
                    $nonEmpty++;
                }
            }

            if ($nonEmpty < 2)
                continue;
            $continuation = 0;
            for ($next = $row + 1; $next <= min($highestRow + 10, $worksheet->getHighestDataRow()); $next++) {
                $rowValues = 0;
                for ($column = 1; $column <= $highestColumn; $column++)
                    if ($this->cellText($worksheet, $column, $next) !== '')
                        $rowValues++;
                if ($rowValues >= 2)
                    $continuation++;
            }
            $score = ($nonEmpty * 10) + $continuation;
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRow = $row;
            }
        }
        return $bestRow;
    }

    /** @return array<int, array{column_index: int, column_letter: string, label: string, raw_label: string}> */
    private function extractHeaders(Worksheet $worksheet, int $headerRow): array
    {
        $headers = [];
        $highestColumn = min(
            Coordinate::columnIndexFromString($worksheet->getHighestDataColumn()),
            self::MAX_COLUMNS
        );

        for ($column = 1; $column <= $highestColumn; $column++) {
            $rawLabel = $this->cellText($worksheet, $column, $headerRow);

            if ($rawLabel === '') {
                continue;
            }

            $columnLetter = Coordinate::stringFromColumnIndex($column);
            $headers[] = [
                'column_index' => $column,
                'column_letter' => $columnLetter,
                'label' => $rawLabel . ' (' . $columnLetter . ')',
                'raw_label' => $rawLabel,
            ];
        }

        return $headers;
    }

    /** @param array<int, array{column_index: int}> $headers
     * @return array<int, array{row_number: int, values: array<int, string>}>
     */
    private function extractSampleRows(Worksheet $worksheet, int $firstDataRow, array $headers): array
    {
        $rows = [];
        $lastRow = $worksheet->getHighestDataRow();

        for ($row = $firstDataRow; $row <= $lastRow && count($rows) < self::MAX_SAMPLE_ROWS; $row++) {
            $values = [];
            $hasValue = false;

            foreach ($headers as $header) {
                $column = $header['column_index'];
                $value = $this->cellText($worksheet, $column, $row);
                $values[$column] = $value;
                $hasValue = $hasValue || $value !== '';
            }

            if ($hasValue) {
                $rows[] = [
                    'row_number' => $row,
                    'values' => $values,
                ];
            }
        }

        return $rows;
    }

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
        $coordinate = Coordinate::stringFromColumnIndex($column) . $row;
        $value = $worksheet->getCell($coordinate)->getValue();

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }
}