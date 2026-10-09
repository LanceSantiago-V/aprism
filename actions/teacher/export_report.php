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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
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
    return preg_match('/^[=+\-@]/', $value) === 1 ? "'" . $value : $value;
}

function exportRow(array $values): array
{
    return array_map(static fn ($value): string => exportText($value), $values);
}
function exportContextRows(array $class, string $title): array
{
    return [['APRISM'], [$title], ['Subject', exportText($class['subject_name'])], ['Section', exportText($class['section_name'])], ['School Year', exportText($class['school_year'])], ['Semester', exportText($class['semester'])]];
}
function styleExportHeader($sheet, int $row, int $lastColumn): void
{
    $range = 'A' . $row . ':' . PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColumn) . $row;
    $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('005596');
    $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}
function csvExport(array $rows, string $reportType, int $classId): never
{
    $filename = 'aprism_' . $reportType . '_report_' . $classId . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    $output = fopen('php://output', 'wb');
    fwrite($output, "\xEF\xBB\xBF");
    foreach ($rows as $row) {
        fputcsv($output, exportRow($row));
    }
    fclose($output);
    exit;
}
function finishExport(Spreadsheet $book, string $reportType, int $classId): never
{
    $sheet = $book->getActiveSheet();
    $lastColumn = PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
    for ($index = 1; $index <= $lastColumn; $index++) {
        $column = PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
    $sheet->freezePane('A8');
    $filename = 'aprism_' . $reportType . '_report_' . $classId . '_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0, no-store');
    (new Xlsx($book))->save('php://output');
    exit;
}

if ((string) ($_POST['format'] ?? 'xlsx') === 'csv') {
    $csvRows = [
        ['APRISM'],
        [$reportType === 'attendance' ? 'Attendance Report' : ($reportType === 'grades' ? 'Grade Records Report' : 'Referral Activity Report')],
        ['Subject', exportText($selectedClass['subject_name'])],
        ['Section', exportText($selectedClass['section_name'])],
        ['School Year', exportText($selectedClass['school_year'])],
        ['Semester', exportText($selectedClass['semester'])],
        [],
    ];
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
$row = 1;
foreach (exportContextRows($selectedClass, $reportType === 'attendance' ? 'Attendance Report' : ($reportType === 'grades' ? 'Grade Records Report' : 'Referral Activity Report')) as $contextRow) {
    $sheet->fromArray(exportRow($contextRow), null, 'A' . $row++);
}
$row++;
if ($reportType === 'attendance') {
    $summary = aprismTeacherAttendanceReport($pdo, $teacherId, (int) $classId, $studentId, $dateFrom !== '' ? $dateFrom : null, $dateTo !== '' ? $dateTo : null)[0] ?? [];
    $sheet->fromArray([['Summary']], null, 'A' . $row++);
    $sheet->fromArray([['Sessions', 'Present', 'Late', 'Absent']], null, 'A' . $row);
    styleExportHeader($sheet, $row++, 4);
    $sheet->fromArray([[(int) ($summary['session_count'] ?? 0), (int) ($summary['present_count'] ?? 0), (int) ($summary['late_count'] ?? 0), (int) ($summary['absent_count'] ?? 0)]], null, 'A' . $row++);
    $row++;
    $headers = ['Student Number', 'Student', 'Present', 'Late', 'Absent', 'Recorded'];
    $sheet->fromArray([$headers], null, 'A' . $row);
    styleExportHeader($sheet, $row++, count($headers));
    $records = aprismTeacherAttendanceReportRows($pdo, $teacherId, (int) $classId, $studentId, $dateFrom !== '' ? $dateFrom : null, $dateTo !== '' ? $dateTo : null);
    if ($records === []) {
        $sheet->setCellValue('A' . $row, 'No records available for this report.');
    } else {
        foreach ($records as $record) {
            $sheet->fromArray([exportRow([$record['student_number'], $record['student_name'], (int) $record['present_count'], (int) $record['late_count'], (int) $record['absent_count'], (int) $record['recorded_count']])], null, 'A' . $row++);
        }
    }
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
            $sheet->fromArray([exportRow([$periodName])], null, 'A' . $row++);
            $headers = ['Student Number', 'Student'];
            foreach (array_keys($period['components']) as $code) {
                $headers[] = $code;
            }
            $sheet->fromArray([$headers], null, 'A' . $row);
            styleExportHeader($sheet, $row++, count($headers));
            foreach ($period['students'] as $student) {
                $values = [$student['number'], $student['name']];
                foreach (array_keys($period['components']) as $code) {
                    $values[] = array_key_exists($code, $student['scores']) ? $student['scores'][$code] : '—';
                }
                $sheet->fromArray([exportRow($values)], null, 'A' . $row++);
            }
            $row++;
        }
    }
} else {
    $sheet->fromArray([['Teacher-submitted referrals']], null, 'A' . $row++);
    $headers = ['Student', 'Student Number', 'Concern', 'Submitted', 'Status'];
    $sheet->fromArray([$headers], null, 'A' . $row);
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
            $sheet->fromArray([exportRow([$record['student_name'], $record['student_number'], $record['reason'], date('M j, Y g:i A', strtotime($record['created_at'])), $record['status']])], null, 'A' . $row++);
        }
    }
}
finishExport($book, $reportType, (int) $classId);
