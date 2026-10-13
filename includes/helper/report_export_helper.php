<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function aprismExportSafeText(mixed $value): string
{
    $value = (string) ($value ?? '');
    return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1 ? "'" . $value : $value;
}

function aprismExportFilename(string $name): string
{
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?? 'aprism-report';
    return trim($name, '.-_') ?: 'aprism-report';
}

/** @param list<list<mixed>> $rows */
function aprismSendCsv(string $filename, array $rows): never
{
    $stream = fopen('php://temp/maxmemory:2097152', 'w+b');
    if ($stream === false) {
        throw new RuntimeException('The report output could not be prepared.');
    }
    if (fwrite($stream, "\xEF\xBB\xBF") !== 3) {
        fclose($stream);
        throw new RuntimeException('The report output could not be written.');
    }
    foreach ($rows as $row) {
        $values = array_map(static fn(mixed $value): string => aprismExportSafeText($value), $row);
        if (fputcsv($stream, $values) === false) {
            fclose($stream);
            throw new RuntimeException('The report output could not be written.');
        }
    }
    rewind($stream);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . aprismExportFilename($filename) . '.csv"');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    fpassthru($stream);
    fclose($stream);
    exit;
}

/**
 * @param list<list<mixed>> $rows Rows before the column header
 * @param list<string> $headers
 * @param array<string,int> $widths Optional explicit widths keyed by column letter
 */
function aprismSendXlsx(string $filename, string $title, array $context, array $headers, array $rows, array $widths = [], bool $landscape = true): never
{
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle(substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $title) ?: 'Report', 0, 31));
    $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1, count($headers)));
    $sheet->fromArray([['APRISM']], null, 'A1');
    $sheet->fromArray([[$title]], null, 'A2');
    $sheet->mergeCells('A1:' . $lastColumn . '1');
    $sheet->mergeCells('A2:' . $lastColumn . '2');
    $row = 3;
    $columnCount = max(1, count($headers));
    $pairsPerRow = $columnCount >= 6 ? 2 : 1;
    $entries = array_map(static fn($label, $value): array => [(string) $label, (string) $value], array_keys($context), array_values($context));
    foreach (array_chunk($entries, $pairsPerRow) as $groups) {
        foreach ($groups as $groupIndex => [$label, $value]) {
            $labelIndex = $pairsPerRow === 2 && $groupIndex === 1 ? (int) floor($columnCount / 2) + 1 : 1;
            $valueEndIndex = $pairsPerRow === 2 && $groupIndex === 0 ? (int) floor($columnCount / 2) : $columnCount;
            if ($labelIndex >= $columnCount) { $labelIndex = 1; $valueEndIndex = $columnCount; }
            $labelColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($labelIndex);
            $valueColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(min($labelIndex + 1, $columnCount));
            $valueEndColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max($labelIndex + 1, min($valueEndIndex, $columnCount)));
            $sheet->setCellValueExplicit($labelColumn . $row, aprismExportSafeText($label), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit($valueColumn . $row, aprismExportSafeText($value), DataType::TYPE_STRING);
            if ($valueEndColumn !== $valueColumn) $sheet->mergeCells($valueColumn . $row . ':' . $valueEndColumn . $row);
            $sheet->getStyle($labelColumn . $row)->getFont()->setBold(true)->getColor()->setRGB('4B6075');
            $sheet->getStyle($labelColumn . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EAF0F6');
            $sheet->getStyle($labelColumn . $row . ':' . $valueEndColumn . $row)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('DCE3EA');
        }
        $row++;
    }
    $headerRow = $row + 1;
    $sheet->fromArray([$headers], null, 'A' . $headerRow);
    $dateColumns = [];
    foreach ($rows as $dataRow) {
        $targetRow = $sheet->getHighestRow() + 1;
        foreach (array_values($dataRow) as $index => $value) {
            $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1) . $targetRow;
            if ($value === null || $value === '') {
                $sheet->setCellValue($coordinate, null);
            } elseif (is_string($value) && in_array((string) ($headers[$index] ?? ''), ['Submitted', 'Occurred At', 'Last Login', 'Scheduled'], true) && trim($value) !== '') {
                try {
                    $sheet->setCellValueExplicit($coordinate, ExcelDate::PHPToExcel(new DateTimeImmutable($value)), DataType::TYPE_NUMERIC);
                    $dateColumns[$index + 1] = true;
                } catch (Throwable) {
                    $sheet->setCellValueExplicit($coordinate, aprismExportSafeText($value), DataType::TYPE_STRING);
                }
            } elseif (is_int($value) || is_float($value)) {
                $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_NUMERIC);
                $sheet->getStyle($coordinate)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            } else {
                $sheet->setCellValueExplicit($coordinate, aprismExportSafeText($value), DataType::TYPE_STRING);
            }
        }
    }
    $lastRow = max($headerRow, $sheet->getHighestRow());
    $sheet->getStyle('A1:' . $lastColumn . $lastRow)->getFont()->setName('Aptos')->setSize(10);
    $sheet->getStyle('A1:' . $lastColumn . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('17324D');
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('17324D');
    $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('17324D');
    $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('F2C94C');
    $sheet->getStyle('A2:' . $lastColumn . '2')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('F2C94C');
    $sheet->getRowDimension($headerRow)->setRowHeight(30);
    if ($lastRow > $headerRow) {
        $sheet->setAutoFilter('A' . $headerRow . ':' . $lastColumn . $lastRow);
        $sheet->freezePane('A' . ($headerRow + 1));
        for ($r = $headerRow + 1; $r <= $lastRow; $r++) {
            if (($r - $headerRow) % 2 === 0) {
                $sheet->getStyle('A' . $r . ':' . $lastColumn . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F7F9FC');
            }
            $sheet->getStyle('A' . $r . ':' . $lastColumn . $r)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('DCE3EA');
        }
    }
    foreach (array_keys($dateColumns) as $index) {
        $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
        $sheet->getStyle($column . ($headerRow + 1) . ':' . $column . $lastRow)->getNumberFormat()->setFormatCode('mmm d, yyyy h:mm AM/PM');
    }
    foreach (range(1, max(1, count($headers))) as $index) {
        $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
        $requestedWidth = (float) ($widths[$column] ?? 14);
        $headerText = (string) ($headers[$index - 1] ?? '');
        $contentWidth = (function_exists('mb_strwidth') ? mb_strwidth($headerText, 'UTF-8') : strlen($headerText)) + 2;
        for ($sampleRow = $headerRow + 1, $sampled = 0; $sampleRow <= $lastRow && $sampled < 80; $sampleRow++, $sampled++) {
            $sampleValue = $sheet->getCell($column . $sampleRow)->getValue();
            if (is_scalar($sampleValue)) {
                $sampleText = (string) $sampleValue;
                $contentWidth = max($contentWidth, (function_exists('mb_strwidth') ? mb_strwidth($sampleText, 'UTF-8') : strlen($sampleText)) + 2);
            }
        }
        $sheet->getColumnDimension($column)->setWidth(max(11, min(40, max($requestedWidth, min($contentWidth, 32)))));
    }
    for ($r = $headerRow + 1; $r <= $lastRow; $r++) $sheet->getRowDimension($r)->setRowHeight(-1);
    $sheet->getStyle('A1:' . $lastColumn . '2')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(false);
    $sheet->getRowDimension(1)->setRowHeight(19);
    $sheet->getRowDimension(2)->setRowHeight(27);
    $sheet->setShowGridlines(false);
    $sheet->getPageSetup()->setOrientation($landscape ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT)->setPaperSize(PageSetup::PAPERSIZE_LETTER)->setFitToWidth(1)->setFitToHeight(0)->setFitToPage(true);
    $sheet->getPageMargins()->setTop(0.45)->setBottom(0.45)->setLeft(0.35)->setRight(0.35);
    $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
    $sheet->getPageSetup()->setPrintArea('A1:' . $lastColumn . $lastRow);
    $sheet->getHeaderFooter()->setOddFooter('&LAPRISM&CPage &P of &N');
    $outputPath = tempnam(sys_get_temp_dir(), 'aprism-report-');
    if ($outputPath === false) throw new RuntimeException('The workbook output could not be prepared.');
    try {
        (new Xlsx($book))->save($outputPath);
    } catch (Throwable $exception) {
        @unlink($outputPath);
        throw $exception;
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . aprismExportFilename($filename) . '.xlsx"');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($outputPath);
    @unlink($outputPath);
    $book->disconnectWorksheets();
    exit;
}
