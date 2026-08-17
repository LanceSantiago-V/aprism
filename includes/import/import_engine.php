<?php

declare(strict_types=1);

/**
 * APRISM Import Engine
 *
 * Provides one normalized pipeline for:
 *
 * - Excel
 * - CSV
 * - Manual Entry
 *
 * Extraction/parsing produces normalized class records.
 * This class does NOT create or modify database records.
 *
 * Database resolution and persistence are handled separately.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

final class ImportEngine
{
    /**
     * Canonical fields accepted by the Import Engine.
     */
    private const CANONICAL_FIELDS = [
        'subject_code',
        'subject_name',
        'units',
        'section_name',
        'program_code',
        'program_name',
        'year_level',
        'school_year',
        'semester',
        'day',
        'start_time',
        'end_time',
        'room',
    ];


    /**
     * Header aliases used when normalizing Excel/CSV files.
     *
     * These are explicit aliases only.
     * The engine does not attempt fuzzy guessing.
     */
    private const HEADER_ALIASES = [

        'subject_code' => [
            'subject code',
            'subject_code',
            'subjectcode',
            'code',
            'course code',
            'course_code',
        ],

        'subject_name' => [
            'subject',
            'subject name',
            'subject_name',
            'subjectname',
            'course',
            'course name',
            'course_name',
        ],

        'units' => [
            'units',
            'unit',
            'credit',
            'credits',
        ],

        'section_name' => [
            'section',
            'section name',
            'section_name',
            'sectionname',
            'class section',
        ],

        'program_code' => [
            'program code',
            'program_code',
            'programcode',
            'program',
            'course code',
            'course_code',
        ],

        'program_name' => [
            'program name',
            'program_name',
            'programname',
            'program',
            'course',
            'course name',
            'course_name',
        ],

        'year_level' => [
            'year level',
            'year_level',
            'yearlevel',
            'year',
            'level',
        ],

        'school_year' => [
            'school year',
            'school_year',
            'schoolyear',
            'academic year',
            'academic_year',
        ],

        'semester' => [
            'semester',
            'term',
            'academic term',
            'academic_term',
        ],

        'day' => [
            'day',
            'class day',
            'class_day',
            'schedule day',
            'schedule_day',
        ],

        'start_time' => [
            'start time',
            'start_time',
            'starttime',
            'time start',
            'time_start',
            'from',
        ],

        'end_time' => [
            'end time',
            'end_time',
            'endtime',
            'time end',
            'time_end',
            'to',
        ],

        'room' => [
            'room',
            'room number',
            'room_number',
            'classroom',
            'class room',
            'location',
        ],

    ];


    /**
     * Normalize a manually supplied class record.
     *
     * This method intentionally follows the same normalized
     * structure used by Excel/CSV imports.
     *
     * @param array $data
     * @return array
     */
    public static function normalizeManual(array $data): array
    {
        return self::normalizeRecord(
            $data,
            'manual',
            null
        );
    }


    /**
     * Parse an uploaded Excel/CSV file.
     *
     * Supported:
     *
     * - .xlsx
     * - .xls
     * - .csv
     *
     * PDF and image/OCR are intentionally not implemented here.
     *
     * @param string $filePath
     * @return array
     */
    public static function parseFile(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new RuntimeException(
                'The import file could not be found.'
            );
        }

        if (!is_readable($filePath)) {
            throw new RuntimeException(
                'The import file cannot be read.'
            );
        }

        $extension =
            strtolower(
                pathinfo(
                    $filePath,
                    PATHINFO_EXTENSION
                )
            );

        if (
            !in_array(
                $extension,
                ['xlsx', 'xls', 'csv'],
                true
            )
        ) {
            throw new RuntimeException(
                'This import format is not supported yet. Please use an Excel or CSV file.'
            );
        }

        try {

            $reader =
                IOFactory::createReaderForFile(
                    $filePath
                );

            $reader->setReadDataOnly(true);

            $spreadsheet =
                $reader->load($filePath);

        } catch (Throwable $e) {

            error_log(
                '[APRISM Import Engine] File parsing failed: ' .
                $e->getMessage()
            );

            throw new RuntimeException(
                'APRISM could not read the uploaded file. Please verify that the file is a valid Excel or CSV document.'
            );
        }

        $worksheet =
            $spreadsheet->getActiveSheet();

        $highestRow =
            $worksheet->getHighestRow();

        $highestColumn =
            $worksheet->getHighestColumn();

        $highestColumnIndex =
            Coordinate::columnIndexFromString(
                $highestColumn
            );

        if ($highestRow < 1) {

            return [
                'source' => $extension,
                'headers' => [],
                'rows' => [],
                'errors' => [
                    'The imported file does not contain any data.'
                ],
            ];

        }

        /*
         * --------------------------------------------------------------
         * Header Row
         * --------------------------------------------------------------
         */

        $rawHeaders = [];

        for (
            $column = 1;
            $column <= $highestColumnIndex;
            $column++
        ) {

            $value =
                $worksheet
                    ->getCellByColumnAndRow(
                        $column,
                        1
                    )
                    ->getValue();

            $rawHeaders[$column] =
                self::normalizeHeader(
                    (string) $value
                );
        }


        /*
         * --------------------------------------------------------------
         * Map Headers
         * --------------------------------------------------------------
         */

        $headerMap = [];

        foreach ($rawHeaders as $column => $header) {

            if ($header === '') {
                continue;
            }

            $canonical =
                self::resolveHeaderAlias(
                    $header
                );

            if ($canonical !== null) {

                /*
                 * Do not silently overwrite a duplicate canonical
                 * field. The first recognized header remains the
                 * authoritative column for this import.
                 */
                if (!isset($headerMap[$canonical])) {

                    $headerMap[$canonical] =
                        $column;
                }

            }

        }


        /*
         * --------------------------------------------------------------
         * Validate Header Availability
         * --------------------------------------------------------------
         *
         * We do not require every field to exist in the source.
         *
         * Missing information must reach the Review step so the
         * Teacher can correct it.
         */

        if (empty($headerMap)) {

            return [
                'source' => $extension,
                'headers' => $rawHeaders,
                'rows' => [],
                'errors' => [
                    'No recognized APRISM class-data columns were found in the imported file.'
                ],
            ];

        }


        /*
         * --------------------------------------------------------------
         * Read Data Rows
         * --------------------------------------------------------------
         */

        $rows = [];

        for (
            $rowNumber = 2;
            $rowNumber <= $highestRow;
            $rowNumber++
        ) {

            $record = [];

            $hasAnyValue = false;

            foreach (
                $headerMap
                as $canonical => $column
            ) {

                $cell =
                    $worksheet
                        ->getCellByColumnAndRow(
                            $column,
                            $rowNumber
                        );

                $value =
                    $cell->getValue();

                if (
                    $value !== null &&
                    trim((string) $value) !== ''
                ) {
                    $hasAnyValue = true;
                }

                $record[$canonical] =
                    $value;
            }


            /*
             * Completely blank rows are ignored.
             */
            if (!$hasAnyValue) {
                continue;
            }


            $normalized =
                self::normalizeRecord(
                    $record,
                    $extension,
                    $rowNumber
                );

            $rows[] =
                $normalized;
        }


        return [
            'source' => $extension,
            'headers' => $headerMap,
            'rows' => $rows,
            'errors' => [],
        ];
    }


    /**
     * Normalize one extracted/imported record.
     *
     * @param array       $record
     * @param string      $source
     * @param int|null    $rowNumber
     * @return array
     */
    public static function normalizeRecord(
        array $record,
        string $source = 'manual',
        ?int $rowNumber = null
    ): array {

        $normalized = [

            'source' =>
                $source,

            'source_row' =>
                $rowNumber,

            'subject_code' =>
                self::normalizeText(
                    $record['subject_code'] ?? null
                ),

            'subject_name' =>
                self::normalizeText(
                    $record['subject_name'] ?? null
                ),

            'units' =>
                self::normalizeDecimal(
                    $record['units'] ?? null
                ),

            'section_name' =>
                self::normalizeText(
                    $record['section_name'] ?? null
                ),

            'program_code' =>
                self::normalizeText(
                    $record['program_code'] ?? null
                ),

            'program_name' =>
                self::normalizeText(
                    $record['program_name'] ?? null
                ),

            'year_level' =>
                self::normalizeYearLevel(
                    $record['year_level'] ?? null
                ),

            'school_year' =>
                self::normalizeSchoolYear(
                    $record['school_year'] ?? null
                ),

            'semester' =>
                self::normalizeText(
                    $record['semester'] ?? null
                ),

            'day' =>
                self::normalizeDay(
                    $record['day'] ?? null
                ),

            'start_time' =>
                self::normalizeTime(
                    $record['start_time'] ?? null
                ),

            'end_time' =>
                self::normalizeTime(
                    $record['end_time'] ?? null
                ),

            'room' =>
                self::normalizeText(
                    $record['room'] ?? null
                ),

        ];


        /*
         * Every normalized record gets validation information.
         *
         * Nothing is silently rejected here simply because information
         * is missing. Missing information belongs in the Review step.
         */
        $normalized['validation'] =
            self::validateRecord(
                $normalized
            );


        return $normalized;
    }


    /**
     * Validate a normalized record without changing it.
     *
     * @param array $record
     * @return array
     */
    public static function validateRecord(
        array $record
    ): array {

        $errors = [];

        $warnings = [];


        /*
         * --------------------------------------------------------------
         * Subject
         * --------------------------------------------------------------
         *
         * Either code or name is acceptable at extraction time.
         *
         * Final database resolution will determine whether the
         * institutional record can actually be identified.
         */

        if (
            ($record['subject_code'] ?? '') === '' &&
            ($record['subject_name'] ?? '') === ''
        ) {

            $errors[] = [
                'field' => 'subject',
                'code' => 'missing_subject',
                'message' =>
                    'Subject information is required.'
            ];

        }


        /*
         * --------------------------------------------------------------
         * Section
         * --------------------------------------------------------------
         */

        if (
            ($record['section_name'] ?? '') === ''
        ) {

            $errors[] = [
                'field' => 'section_name',
                'code' => 'missing_section',
                'message' =>
                    'Section information is required.'
            ];

        }


        /*
         * --------------------------------------------------------------
         * School Year
         * --------------------------------------------------------------
         */

        if (
            ($record['school_year'] ?? '') === ''
        ) {

            $errors[] = [
                'field' => 'school_year',
                'code' => 'missing_school_year',
                'message' =>
                    'School Year information is required.'
            ];

        }


        /*
         * --------------------------------------------------------------
         * Schedule
         * --------------------------------------------------------------
         *
         * A class schedule requires day, start time, and end time.
         */

        if (
            ($record['day'] ?? '') === ''
        ) {

            $errors[] = [
                'field' => 'day',
                'code' => 'missing_day',
                'message' =>
                    'Class day is required.'
            ];

        }

        if (
            ($record['start_time'] ?? '') === ''
        ) {

            $errors[] = [
                'field' => 'start_time',
                'code' => 'missing_start_time',
                'message' =>
                    'Class start time is required.'
            ];

        }

        if (
            ($record['end_time'] ?? '') === ''
        ) {

            $errors[] = [
                'field' => 'end_time',
                'code' => 'missing_end_time',
                'message' =>
                    'Class end time is required.'
            ];

        }


        /*
         * --------------------------------------------------------------
         * Time Relationship
         * --------------------------------------------------------------
         */

        if (
            ($record['start_time'] ?? '') !== '' &&
            ($record['end_time'] ?? '') !== ''
        ) {

            if (
                $record['end_time'] <=
                $record['start_time']
            ) {

                $errors[] = [
                    'field' => 'end_time',
                    'code' => 'invalid_time_range',
                    'message' =>
                        'End time must be later than start time.'
                ];

            }

        }


        /*
         * --------------------------------------------------------------
         * Program / Year Level
         * --------------------------------------------------------------
         *
         * These may be necessary for resolving or creating a missing
         * Section. They are not automatically guessed.
         */

        if (
            ($record['program_code'] ?? '') === '' &&
            ($record['program_name'] ?? '') === ''
        ) {

            $warnings[] = [
                'field' => 'program',
                'code' => 'missing_program_context',
                'message' =>
                    'Program information was not provided. Section resolution may require additional information.'
            ];

        }


        if (
            ($record['year_level'] ?? '') === ''
        ) {

            $warnings[] = [
                'field' => 'year_level',
                'code' => 'missing_year_level',
                'message' =>
                    'Year level was not provided. Section resolution may require additional information.'
            ];

        }


        return [

            'is_valid' =>
                empty($errors),

            'errors' =>
                $errors,

            'warnings' =>
                $warnings,

        ];
    }


    /**
     * Normalize an Excel/CSV header.
     *
     * @param string $header
     * @return string
     */
    private static function normalizeHeader(
        string $header
    ): string {

        $header =
            trim(
                strtolower(
                    $header
                )
            );

        $header =
            preg_replace(
                '/[\r\n\t]+/',
                ' ',
                $header
            );

        $header =
            preg_replace(
                '/\s+/',
                ' ',
                $header
            );

        return trim(
            (string) $header
        );
    }


    /**
     * Resolve a normalized header to a canonical field.
     *
     * @param string $header
     * @return string|null
     */
    private static function resolveHeaderAlias(
        string $header
    ): ?string {

        foreach (
            self::HEADER_ALIASES
            as $canonical => $aliases
        ) {

            foreach ($aliases as $alias) {

                if ($header === $alias) {
                    return $canonical;
                }

            }

        }

        return null;
    }


    /**
     * Normalize text.
     *
     * @param mixed $value
     * @return string
     */
    private static function normalizeText(
        mixed $value
    ): string {

        if ($value === null) {
            return '';
        }

        return trim(
            preg_replace(
                '/\s+/',
                ' ',
                (string) $value
            )
        );
    }


    /**
     * Normalize decimal values.
     *
     * @param mixed $value
     * @return string
     */
    private static function normalizeDecimal(
        mixed $value
    ): string {

        if (
            $value === null ||
            trim((string) $value) === ''
        ) {
            return '';
        }

        $value =
            str_replace(
                ',',
                '.',
                trim((string) $value)
            );

        if (!is_numeric($value)) {
            return '';
        }

        return number_format(
            (float) $value,
            1,
            '.',
            ''
        );
    }


    /**
     * Normalize College year level.
     *
     * @param mixed $value
     * @return string
     */
    private static function normalizeYearLevel(
        mixed $value
    ): string {

        $value =
            self::normalizeText(
                $value
            );

        if ($value === '') {
            return '';
        }

        if (
            preg_match(
                '/^[1-4]$/',
                $value
            )
        ) {
            return $value;
        }

        if (
            preg_match(
                '/^([1-4])(st|nd|rd|th)?$/i',
                $value,
                $matches
            )
        ) {
            return $matches[1];
        }

        return $value;
    }


    /**
     * Normalize School Year.
     *
     * Expected format:
     *
     * 2026-2027
     *
     * @param mixed $value
     * @return string
     */
    private static function normalizeSchoolYear(
        mixed $value
    ): string {

        $value =
            self::normalizeText(
                $value
            );

        if ($value === '') {
            return '';
        }

        if (
            preg_match(
                '/^(\d{4})\s*[-\/]\s*(\d{4})$/',
                $value,
                $matches
            )
        ) {

            return
                $matches[1] .
                '-' .
                $matches[2];

        }

        return $value;
    }


    /**
     * Normalize day names.
     *
     * @param mixed $value
     * @return string
     */
    private static function normalizeDay(
        mixed $value
    ): string {

        $value =
            strtolower(
                self::normalizeText(
                    $value
                )
            );

        if ($value === '') {
            return '';
        }

        $days = [

            'mon' => 'Monday',
            'monday' => 'Monday',

            'tue' => 'Tuesday',
            'tues' => 'Tuesday',
            'tuesday' => 'Tuesday',

            'wed' => 'Wednesday',
            'wednesday' => 'Wednesday',

            'thu' => 'Thursday',
            'thur' => 'Thursday',
            'thurs' => 'Thursday',
            'thursday' => 'Thursday',

            'fri' => 'Friday',
            'friday' => 'Friday',

            'sat' => 'Saturday',
            'saturday' => 'Saturday',

            'sun' => 'Sunday',
            'sunday' => 'Sunday',

        ];

        return
            $days[$value]
            ?? ucfirst($value);
    }


    /**
     * Normalize a time value.
     *
     * Supports:
     *
     * - HH:MM
     * - HH:MM:SS
     * - AM/PM strings
     * - Excel time fractions
     * - Excel datetime values
     *
     * @param mixed $value
     * @return string
     */
    private static function normalizeTime(
        mixed $value
    ): string {

        if (
            $value === null ||
            trim((string) $value) === ''
        ) {
            return '';
        }


        /*
         * Excel time fraction.
         */
        if (
            is_numeric($value) &&
            (float) $value >= 0 &&
            (float) $value < 1
        ) {

            try {

                $date =
                    ExcelDate::excelToDateTimeObject(
                        (float) $value
                    );

                return $date->format('H:i');

            } catch (Throwable) {
                // Continue with normal parsing.
            }

        }


        $value =
            trim(
                (string) $value
            );


        /*
         * Normalize common separators.
         */
        $value =
            str_replace(
                '.',
                ':',
                $value
            );


        /*
         * 12-hour / 24-hour time.
         */
        $formats = [

            'H:i',
            'H:i:s',

            'G:i',
            'G:i:s',

            'h:i A',
            'h:i:s A',

            'g:i A',
            'g:i:s A',

            'h A',
            'g A',

        ];


        foreach ($formats as $format) {

            $date =
                DateTime::createFromFormat(
                    $format,
                    $value
                );

            if ($date !== false) {

                return $date->format('H:i');

            }

        }


        /*
         * Numeric compact values such as 830 or 1330.
         *
         * This is normalization only; values must still pass final
         * validation before confirmation.
         */
        if (
            preg_match(
                '/^(\d{3,4})$/',
                $value,
                $matches
            )
        ) {

            $digits =
                $matches[1];

            if (strlen($digits) === 3) {

                $hour =
                    substr(
                        $digits,
                        0,
                        1
                    );

                $minute =
                    substr(
                        $digits,
                        1,
                        2
                    );

            } else {

                $hour =
                    substr(
                        $digits,
                        0,
                        2
                    );

                $minute =
                    substr(
                        $digits,
                        2,
                        2
                    );

            }

            if (
                (int) $hour <= 23 &&
                (int) $minute <= 59
            ) {

                return sprintf(
                    '%02d:%02d',
                    (int) $hour,
                    (int) $minute
                );

            }

        }


        /*
         * If the value cannot be normalized safely, return blank.
         *
         * The Review/Validation layer will expose the missing value
         * instead of silently guessing.
         */
        return '';
    }
}