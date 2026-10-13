<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/pdo.php';
require_once __DIR__ . '/../../auth/role_helper.php';
$allowedRoles = [ROLE_TEACHER];
require_once __DIR__ . '/../../auth/session_guard.php';
require_once __DIR__ . '/../../auth/csrf_helper.php';
require_once __DIR__ . '/../../includes/helper/grade_ledger_helper.php';
require_once __DIR__ . '/../../includes/helper/teacher_referral_helper.php';
require_once __DIR__ . '/../../includes/helper/teacher_reports_helper.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('The report export request could not be verified.');
}
$reportType = (string) ($_POST['report_type'] ?? '');
$classId = filter_input(INPUT_POST, 'operational_class_id', FILTER_VALIDATE_INT);
$teacherId = (int) ($_SESSION['user_id'] ?? 0);
if (!in_array($reportType, ['attendance', 'grades', 'referrals'], true) || $classId === false || $classId === null || $classId < 1) {
    http_response_code(422);
    exit('The selected report could not be exported.');
}
$selectedClass = null;
foreach (aprismTeacherReportClasses($pdo, $teacherId) as $class) {
    if ((int) $class['operational_class_id'] === (int) $classId) {
        $selectedClass = $class;
        break;
    }
}
if ($selectedClass === null) {
    http_response_code(403);
    exit('The selected class is not available in your Teacher scope.');
}
$periodId = filter_input(INPUT_POST, 'period_id', FILTER_VALIDATE_INT);
$periodId = $periodId !== false && $periodId !== null && $periodId > 0 ? (int) $periodId : null;
$selectedPeriodLabel = null;
$studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
$studentId = $studentId !== false && $studentId !== null && $studentId > 0 ? (int) $studentId : null;
$status = (string) ($_POST['status'] ?? 'all');
$status = in_array($status, ['all', 'Submitted', 'Returned', 'Accepted', 'Closed'], true) ? $status : 'all';
$dateFrom = (string) ($_POST['date_from'] ?? '');
$dateTo = (string) ($_POST['date_to'] ?? '');
$isValidDate = static function (string $value): bool {
    if ($value === '') return true;
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
};
if (!$isValidDate($dateFrom) || !$isValidDate($dateTo)) {
    $dateFrom = '';
    $dateTo = '';
}
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}
if ($studentId !== null) {
    $studentFound = false;
    foreach (aprismTeacherReportStudents($pdo, $teacherId, (int) $classId) as $student) {
        if ((int) $student['student_id'] === $studentId) {
            $studentFound = true;
            break;
        }
    }
    if (!$studentFound) {
        http_response_code(403);
        exit('The selected Student is not available in your Teacher scope.');
    }
}
if ($periodId !== null) {
    $periodFound = false;
    $classContext = aprismGradeClassContext($pdo, $teacherId, (int) $classId);
    if ($classContext !== null) {
        foreach (aprismGradePeriodsForClass($pdo, $classContext) as $period) {
            if ((int) $period['academic_period_id'] === $periodId) {
                $periodFound = true;
                $selectedPeriodLabel = (string) $period['period_name'];
                break;
            }
        }
    }
    if (!$periodFound) {
        http_response_code(422);
        exit('The selected Academic Period is not available for this class.');
    }
}

function exportText($value): string
{
    $value = (string) ($value ?? '');
    // Spreadsheets interpret these prefixes as formulas. Preserve the visible
    // value while forcing text treatment in both CSV and XLSX exports.
    return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1 ? "'" . $value : $value;
}

function exportRow(array $values): array
{
    return array_map(static fn ($value): string => exportText($value), $values);
}
function exportExcelDateValue(string $value): float|string
{
    try {
        return ExcelDate::PHPToExcel(new DateTimeImmutable($value));
    } catch (Throwable) {
        return exportText($value);
    }
}
function styleExportHeader($sheet, int $row, int $lastColumn): void
{
    $range = 'A' . $row . ':' . PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColumn) . $row;
    $sheet->getStyle($range)->getFont()->setBold(true)->setColor(new PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('17324D');
    $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    $sheet->getStyle($range)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('F2C94C');
    $sheet->getRowDimension($row)->setRowHeight(32);
}
function csvExport(array $rows, string $reportType, int $classId): never
{
    $filename = 'aprism_' . $reportType . '_report_' . $classId . '_' . date('Ymd_His') . '.csv';
    $output = fopen('php://temp/maxmemory:2097152', 'w+b');
    if ($output === false) throw new RuntimeException('The report output could not be prepared.');
    if (fwrite($output, "\xEF\xBB\xBF") !== 3) throw new RuntimeException('The report output could not be written.');
    foreach ($rows as $row) {
        if (fputcsv($output, exportRow($row)) === false) throw new RuntimeException('The report output could not be written.');
    }
    rewind($output);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    fpassthru($output);
    fclose($output);
    exit;
}
function finishExport(Spreadsheet $book, string $reportType, int $classId, ?int $mainHeaderRow, int $contextLastRow = 5): never
{
    $sheet = $book->getActiveSheet();
    $lastColumn = PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
    $lastColumnLetter = PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColumn);
    $lastRow = $sheet->getHighestRow();
    $book->getDefaultStyle()->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
    $sheet->getStyle('A1:' . $lastColumnLetter . $lastRow)->getFont()->setName('Aptos')->setSize(10);
    $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_LETTER)->setFitToWidth(1)->setFitToHeight(0);
    $sheet->getPageSetup()->setFitToPage(true);
    $sheet->getPageMargins()->setTop(0.45)->setBottom(0.45)->setLeft(0.35)->setRight(0.35);
    $sheet->setShowGridlines(false);
    $sheet->getColumnDimension('A')->setWidth($reportType === 'referrals' ? 26 : 18);
    $sheet->getColumnDimension('B')->setWidth(28);
    for ($index = 3; $index <= $lastColumn; $index++) {
        $column = PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
        $sheet->getColumnDimension($column)->setWidth($reportType === 'referrals' && $index === 4 ? 22 : 15);
    }
    if ($reportType === 'referrals') {
        $sheet->getColumnDimension('C')->setWidth(24);
        $sheet->getColumnDimension('D')->setWidth(52);
        $sheet->getColumnDimension('E')->setWidth(22);
        $sheet->getColumnDimension('F')->setWidth(18);
    }
    if ($reportType === 'attendance') {
        for ($index = 3; $index <= 6; $index++) {
            $sheet->getColumnDimension(PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index))->setWidth(13);
        }
    }
    $sheet->getStyle('A1:' . $lastColumnLetter . $lastRow)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR);
    foreach ([1, 2] as $titleRow) {
        $sheet->mergeCells('A' . $titleRow . ':' . $lastColumnLetter . $titleRow);
    }
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('17324D');
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(17)->getColor()->setRGB('17324D');
    $sheet->getRowDimension(1)->setRowHeight(20);
    $sheet->getRowDimension(2)->setRowHeight(30);
    $sheet->getRowDimension(5)->setRowHeight(32);
    for ($row = 3; $row <= $contextLastRow; $row++) {
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->getColor()->setRGB('4B6075');
        $sheet->mergeCells('B' . $row . ':' . $lastColumnLetter . $row);
    }
    if ($mainHeaderRow !== null && $reportType !== 'attendance') {
        $sheet->freezePane('A' . ($mainHeaderRow + 1));
    }
    $filename = 'aprism_' . $reportType . '_report_' . $classId . '_' . date('Ymd_His') . '.xlsx';
    $outputPath = tempnam(sys_get_temp_dir(), 'aprism-teacher-report-');
    if ($outputPath === false) throw new RuntimeException('The workbook output could not be prepared.');
    try {
        (new Xlsx($book))->save($outputPath);
    } catch (Throwable $exception) {
        @unlink($outputPath);
        throw $exception;
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($outputPath);
    @unlink($outputPath);
    $book->disconnectWorksheets();
    exit;
}

$studentLabel = 'All students';
if ($studentId !== null) {
    foreach (aprismTeacherReportStudents($pdo, $teacherId, (int) $classId) as $student) {
        if ((int) $student['student_id'] === $studentId) {
            $studentLabel = (string) $student['student_number'] . ' — ' . (string) $student['student_name'];
            break;
        }
    }
}
$filterRows = [['Student', exportText($studentLabel)]];
if ($reportType === 'attendance') {
    $filterRows[] = ['Date range', exportText($dateFrom !== '' || $dateTo !== '' ? (($dateFrom !== '' ? $dateFrom : 'Start') . ' to ' . ($dateTo !== '' ? $dateTo : 'End')) : 'All dates')];
} elseif ($reportType === 'grades') {
    $filterRows[] = ['Academic Period', exportText($selectedPeriodLabel ?? 'All academic periods')];
} else {
    $filterRows[] = ['Status', exportText($status === 'all' ? 'All statuses' : $status)];
}
if ((string) ($_POST['format'] ?? 'xlsx') === 'csv') {
    $csvRows = [
        ['APRISM'],
        [$reportType === 'attendance' ? 'Attendance Report' : ($reportType === 'grades' ? 'Grade Records Report' : 'Referral Activity Report')],
        ['Subject', exportText($selectedClass['subject_name'])],
        ['Section', exportText($selectedClass['section_name'])],
        ['School Year', exportText($selectedClass['school_year'])],
        ['Semester', exportText($selectedClass['semester'])],
    ];
    $csvRows = array_merge($csvRows, $filterRows, [[]]);
    if ($reportType === 'attendance') {
        $summary = aprismTeacherAttendanceReport($pdo, $teacherId, (int) $classId, $studentId, $dateFrom !== '' ? $dateFrom : null, $dateTo !== '' ? $dateTo : null)[0] ?? [];
        $csvRows[] = ['Sessions', 'Present', 'Late', 'Absent'];
        $csvRows[] = [(int) ($summary['session_count'] ?? 0), (int) ($summary['present_count'] ?? 0), (int) ($summary['late_count'] ?? 0), (int) ($summary['absent_count'] ?? 0)];
        $csvRows[] = [];
        $csvRows[] = ['Student Number', 'Student', 'Present', 'Late', 'Absent', 'Recorded'];
        foreach (aprismTeacherAttendanceReportRows($pdo, $teacherId, (int) $classId, $studentId, $dateFrom !== '' ? $dateFrom : null, $dateTo !== '' ? $dateTo : null) as $record) {
            $csvRows[] = [$record['student_number'], $record['student_name'], (int) $record['present_count'], (int) $record['late_count'], (int) $record['absent_count'], (int) $record['recorded_count']];
        }
    } elseif ($reportType === 'grades') {
        $csvRows[] = ['Academic Period', 'Student Number', 'Student', 'Component Code', 'Component', 'Maximum Score', 'Raw Score'];
        foreach (aprismTeacherConfirmedGradeReport($pdo, $teacherId, (int) $classId, $periodId, $studentId) as $record) {
            $csvRows[] = [$record['period_name'], $record['student_number'], $record['student_name'], $record['component_code'], $record['component_name'], $record['max_score'], $record['raw_score']];
        }
    } else {
        $csvRows[] = ['Student', 'Student Number', 'Section', 'Concern', 'Submitted', 'Status'];
        $records = aprismTeacherReferrals($pdo, $teacherId, (int) $classId);
        foreach ($records as $record) {
            if ($studentId !== null && (int) ($record['student_id'] ?? 0) !== $studentId) continue;
            if ($status !== 'all' && (string) ($record['status'] ?? '') !== $status) continue;
            $csvRows[] = [$record['student_name'], $record['student_number'], $record['section_name'], $record['reason'], date('Y-m-d H:i', strtotime($record['created_at'])), $record['status']];
        }
    }
    csvExport($csvRows, $reportType, (int) $classId);
}

$book = new Spreadsheet();
$sheet = $book->getActiveSheet();
$sheet->setTitle(ucfirst($reportType) . ' Report');
$reportTitle = $reportType === 'attendance' ? 'Attendance Report' : ($reportType === 'grades' ? 'Grade Records Report' : 'Referral Activity Report');
$filterSummary = implode(' · ', array_map(static fn (array $filter): string => $filter[0] . ': ' . $filter[1], $filterRows));
$contextRows = [
    ['APRISM'],
    [$reportTitle],
    ['Class', exportText($selectedClass['subject_name'] . ' — ' . $selectedClass['section_name'])],
    ['Academic context', exportText($selectedClass['school_year'] . ' · ' . $selectedClass['semester'])],
    ['Filters', exportText($filterSummary)],
];
$row = 1;
foreach ($contextRows as $contextRow) {
    $sheet->fromArray(exportRow($contextRow), null, 'A' . $row++);
}
$row++;
$mainHeaderRow = null;
if ($reportType === 'attendance') {
    $summary = aprismTeacherAttendanceReport($pdo, $teacherId, (int) $classId, $studentId, $dateFrom !== '' ? $dateFrom : null, $dateTo !== '' ? $dateTo : null)[0] ?? [];
    $sheet->fromArray([['Summary']], null, 'A' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('17324D');
    $row++;
    $sheet->fromArray([['Sessions', 'Present', 'Late', 'Absent']], null, 'A' . $row);
    styleExportHeader($sheet, $row++, 4);
    $summaryRow = $row++;
    foreach (['A' => 'session_count', 'B' => 'present_count', 'C' => 'late_count', 'D' => 'absent_count'] as $column => $key) {
        $sheet->setCellValueExplicit($column . $summaryRow, (int) ($summary[$key] ?? 0), DataType::TYPE_NUMERIC);
    }
    $sheet->getStyle('A' . $summaryRow . ':D' . $summaryRow)->getNumberFormat()->setFormatCode('#,##0;[Red]-#,##0;0');
    $row++;
    $headers = ['Student Number', 'Student', 'Present', 'Late', 'Absent', 'Recorded'];
    $sheet->fromArray([$headers], null, 'A' . $row);
    $mainHeaderRow = $row;
    styleExportHeader($sheet, $row++, count($headers));
    $records = aprismTeacherAttendanceReportRows($pdo, $teacherId, (int) $classId, $studentId, $dateFrom !== '' ? $dateFrom : null, $dateTo !== '' ? $dateTo : null);
    if ($records === []) {
        $sheet->setCellValue('A' . $row, 'No records available for this report.');
    } else {
        foreach ($records as $record) {
            $sheet->fromArray([exportRow([$record['student_number'], $record['student_name'], (int) $record['present_count'], (int) $record['late_count'], (int) $record['absent_count'], (int) $record['recorded_count']])], null, 'A' . $row);
            $sheet->setCellValueExplicit('A' . $row, exportText($record['student_number']), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B' . $row, exportText($record['student_name']), DataType::TYPE_STRING);
            foreach (['C' => 'present_count', 'D' => 'late_count', 'E' => 'absent_count', 'F' => 'recorded_count'] as $column => $key) {
                $sheet->setCellValueExplicit($column . $row, (int) $record[$key], DataType::TYPE_NUMERIC);
            }
            $row++;
        }
    }
    $sheet->getStyle('C' . ($mainHeaderRow + 1) . ':F' . max($mainHeaderRow + 1, $row - 1))->getNumberFormat()->setFormatCode('#,##0');
} elseif ($reportType === 'grades') {
    $gradeRows = aprismTeacherConfirmedGradeReport($pdo, $teacherId, (int) $classId, $periodId, $studentId);
    $periods = [];
    foreach ($gradeRows as $record) {
        $period = (string) $record['period_name'];
        $code = (string) $record['component_code'];
        $student = (string) $record['student_number'];
        if (!isset($periods[$period])) {
            $periods[$period] = ['components' => [], 'students' => []];
        }
        $periods[$period]['components'][$code] = $record['max_score'];
        if (!isset($periods[$period]['students'][$student])) {
            $periods[$period]['students'][$student] = ['number' => $record['student_number'], 'name' => $record['student_name'], 'scores' => []];
        }
        $periods[$period]['students'][$student]['scores'][$code] = $record['raw_score'];
    }
    if ($periods === []) {
        $sheet->setCellValue('A' . $row, 'No confirmed raw Grade Import records are available for this class.');
    } else {
        foreach ($periods as $periodName => $period) {
            $sheet->fromArray([exportRow([$periodName])], null, 'A' . $row);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('17324D');
            $row++;
            $headers = ['Student Number', 'Student'];
            foreach (array_keys($period['components']) as $code) {
                $headers[] = $code . "\nMax: " . (string) $period['components'][$code];
            }
            $sheet->fromArray([$headers], null, 'A' . $row);
            $mainHeaderRow ??= $row;
            $componentColumn = 3;
            foreach (array_keys($period['components']) as $code) {
                $headerValue = exportText($code . "\nMax: " . (string) $period['components'][$code]);
                $sheet->setCellValueExplicit(PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($componentColumn) . $row, $headerValue, DataType::TYPE_STRING);
                $componentColumn++;
            }
            styleExportHeader($sheet, $row++, count($headers));
            foreach ($period['students'] as $student) {
                $values = [$student['number'], $student['name']];
                foreach (array_keys($period['components']) as $code) {
                    $values[] = array_key_exists($code, $student['scores']) ? $student['scores'][$code] : '—';
                }
                $sheet->fromArray([exportRow($values)], null, 'A' . $row);
                $sheet->setCellValueExplicit('A' . $row, exportText($student['number']), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('B' . $row, exportText($student['name']), DataType::TYPE_STRING);
                $componentColumn = 3;
                foreach (array_keys($period['components']) as $code) {
                    if (array_key_exists($code, $student['scores']) && is_numeric($student['scores'][$code])) {
                        $sheet->setCellValueExplicit(PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($componentColumn) . $row, (string) $student['scores'][$code], DataType::TYPE_NUMERIC);
                    }
                    $componentColumn++;
                }
                $row++;
            }
            if (count($headers) > 2 && count($period['students']) > 0) {
                $sheet->getStyle('C' . ($row - count($period['students'])) . ':' . PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.########');
            }
            $row++;
        }
    }
} else {
    $sheet->fromArray([['Teacher-submitted referrals']], null, 'A' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('17324D');
    $row++;
    $headers = ['Student', 'Student Number', 'Section', 'Concern', 'Submitted', 'Status'];
    $sheet->fromArray([$headers], null, 'A' . $row);
    $mainHeaderRow = $row;
    styleExportHeader($sheet, $row++, count($headers));
    $records = aprismTeacherReferrals($pdo, $teacherId, (int) $classId);
    if ($studentId !== null) {
        $records = array_values(array_filter($records, static fn(array $record): bool => (int) ($record['student_id'] ?? 0) === $studentId));
    }
    if ($status !== 'all') {
        $records = array_values(array_filter($records, static fn(array $record): bool => (string) ($record['status'] ?? '') === $status));
    }
    if ($records === []) {
        $sheet->setCellValue('A' . $row, 'No records available for this report.');
    } else {
        foreach ($records as $record) {
            $sheet->fromArray([exportRow([$record['student_name'], $record['student_number'], $record['section_name'], $record['reason']])], null, 'A' . $row);
            $sheet->setCellValueExplicit('A' . $row, exportText($record['student_name']), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B' . $row, exportText($record['student_number']), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C' . $row, exportText($record['section_name']), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D' . $row, exportText($record['reason']), DataType::TYPE_STRING);
            $submittedAt = trim((string) ($record['created_at'] ?? ''));
            $sheet->setCellValue('E' . $row, $submittedAt !== '' ? exportExcelDateValue($submittedAt) : '');
            $sheet->setCellValue('F' . $row, exportText($record['status'] ?? ''));
            $row++;
        }
        if ($row > $mainHeaderRow + 1) {
            $sheet->getStyle('E' . ($mainHeaderRow + 1) . ':E' . ($row - 1))->getNumberFormat()->setFormatCode('mmm d, yyyy h:mm AM/PM');
        }
    }
}
if ($reportType === 'attendance' && $mainHeaderRow !== null && $row > $mainHeaderRow + 1) {
    $sheet->setAutoFilter('A' . $mainHeaderRow . ':F' . ($row - 1));
} elseif ($reportType === 'referrals' && $mainHeaderRow !== null && $row > $mainHeaderRow + 1) {
    $sheet->setAutoFilter('A' . $mainHeaderRow . ':F' . ($row - 1));
}
finishExport($book, $reportType, (int) $classId, $mainHeaderRow);
