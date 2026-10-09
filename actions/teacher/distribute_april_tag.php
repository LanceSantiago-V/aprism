<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/pdo.php';
require_once __DIR__ . '/../../auth/role_helper.php';
require_once __DIR__ . '/../../includes/helper/apriltag_renderer.php';

$allowedRoles = [ROLE_TEACHER];

require_once __DIR__ . '/../../auth/session_guard.php';

function aprilTagDistributionFail(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

function aprilTagDistributionEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    aprilTagDistributionFail(
        405,
        'This AprilTag distribution endpoint accepts GET requests only.'
    );
}

$operationalClassId = filter_var(
    $_GET['operational_class_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$enrollmentId = filter_var(
    $_GET['student_class_enrollment_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$mode = trim((string) ($_GET['mode'] ?? 'view'));
$bulkModes = ['bulk_download', 'bulk_print'];

if (
    $operationalClassId === false
    || ($enrollmentId === false && !in_array($mode, $bulkModes, true))
) {
    aprilTagDistributionFail(
        422,
        'A valid Operational Class and Student Class Enrollment are required.'
    );
}

if (!in_array($mode, ['view', 'print', 'download', ...$bulkModes], true)) {
    aprilTagDistributionFail(422, 'Invalid AprilTag distribution mode.');
}

$operationalClassId = (int) $operationalClassId;
$enrollmentId = $enrollmentId === false ? null : (int) $enrollmentId;
$teacherId = (int) ($_SESSION['user_id'] ?? 0);

try {
    $statement = $pdo->prepare(
        "SELECT
            oc.operational_class_id,
            oc.semester,
            sy.school_year,
            sub.subject_name,
            sec.section_name,
            sce.student_class_enrollment_id,
            st.student_number,
            st.first_name,
            st.middle_name,
            st.last_name,
            st.suffix,
            at.tag_code
        FROM operational_classes AS oc
        LEFT JOIN school_years AS sy
            ON sy.school_year = oc.school_year
        INNER JOIN subjects AS sub
            ON sub.subject_id = oc.subject_id
        INNER JOIN sections AS sec
            ON sec.section_id = oc.section_id
        INNER JOIN student_class_enrollments AS sce
            ON sce.operational_class_id = oc.operational_class_id
           AND sce.status = 'Active'
        INNER JOIN students AS st
            ON st.student_id = sce.student_id
           AND st.status = 'Active'
        INNER JOIN april_tags AS at
            ON at.current_student_id = st.student_id
           AND at.tag_family = 'tagStandard52h13'
           AND at.status = 'Assigned'
        WHERE oc.operational_class_id = ?
          AND oc.teacher_id = ?
          AND oc.status = 'Active'
        " . ($enrollmentId === null
            ? ''
            : ' AND sce.student_class_enrollment_id = ?') . "
        ORDER BY st.last_name, st.first_name, st.student_number"
        . ($enrollmentId === null ? '' : ' LIMIT 1')
    );

    $parameters = [$operationalClassId, $teacherId];

    if ($enrollmentId !== null) {
        $parameters[] = $enrollmentId;
    }

    $statement->execute($parameters);

    $records = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('[APRISM AprilTag Distribution] ' . $exception->getMessage());
    aprilTagDistributionFail(
        500,
        'The assigned AprilTag could not be loaded.'
    );
}

if ($records === []) {
    aprilTagDistributionFail(
        404,
        'The assigned AprilTag is unavailable for this Operational Class.'
    );
}

$record = $records[0];
$tagCode = (int) $record['tag_code'];
$png = null;

try {
    if ($mode === 'bulk_download') {
        if (!class_exists('ZipArchive')) {
            aprilTagDistributionFail(
                500,
                'Bulk AprilTag download requires the PHP ZipArchive extension.'
            );
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'aprism-apriltags-');

        if ($zipPath === false) {
            aprilTagDistributionFail(
                500,
                'The bulk AprilTag download could not be prepared.'
            );
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            aprilTagDistributionFail(
                500,
                'The bulk AprilTag ZIP could not be created.'
            );
        }

        foreach ($records as $bulkRecord) {
            $bulkTagCode = (int) $bulkRecord['tag_code'];
            $bulkPng = aprism_apriltag_png($bulkTagCode, 40);
            $studentNumber = preg_replace(
                '/[^A-Za-z0-9_-]+/',
                '-',
                (string) $bulkRecord['student_number']
            ) ?: 'student';

            $zip->addFromString(
                'student-' . trim($studentNumber, '-')
                . '-apriltag-' . $bulkTagCode . '.png',
                $bulkPng
            );
        }

        $zip->close();

        header('Content-Type: application/zip');
        header(
            'Content-Disposition: attachment; filename="aprism-apriltags-class-'
            . $operationalClassId
            . '.zip"'
        );
        header('Cache-Control: no-store, max-age=0');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    }

    if ($mode === 'bulk_print') {
        foreach ($records as &$bulkRecord) {
            $bulkRecord['png'] = aprism_apriltag_png(
                (int) $bulkRecord['tag_code'],
                40
            );
        }

        unset($bulkRecord);
    } elseif ($mode === 'download' || $mode === 'print') {
        $png = aprism_apriltag_png($tagCode, 40);

        if ($mode === 'download') {
            header('Content-Type: image/png');
            header(
                'Content-Disposition: attachment; filename="aprism-apriltag-'
                . $tagCode
                . '.png"'
            );
            header('Cache-Control: no-store, max-age=0');
            header('Content-Length: ' . strlen($png));
            echo $png;
            exit;
        }
    }

    $matrix = aprism_apriltag_matrix($tagCode);
} catch (Throwable $exception) {
    error_log('[APRISM AprilTag Distribution] ' . $exception->getMessage());
    aprilTagDistributionFail(
        500,
        'The assigned AprilTag could not be rendered.'
    );
}

$displayName = trim(
    (string) $record['last_name'] . ', '
    . (string) $record['first_name'] . ' '
    . (string) ($record['middle_name'] ?? '') . ' '
    . (string) ($record['suffix'] ?? '')
);

$displayName = preg_replace('/\s+/', ' ', $displayName) ?: $displayName;
$studentNumber = (string) $record['student_number'];
$title = in_array($mode, ['print', 'bulk_print'], true)
    ? 'Print AprilTag'
    : 'View AprilTag';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
?><!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= aprilTagDistributionEscape($title) ?> · APRISM</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Arial, sans-serif;
            color: #0f2f57;
            background: #f4f8fc;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 2rem;
        }

        .card {
            width: min(100%, 620px);
            padding: 2rem;
            border: 1px solid #d9e5ef;
            border-radius: 20px;
            background: #ffffff;
            box-shadow: 0 18px 50px rgba(15, 47, 87, .12);
            text-align: center;
        }

        .identity {
            margin-bottom: 1.5rem;
        }

        .identity h1 {
            margin: 0;
            font-size: 1.25rem;
        }

        .identity p {
            margin: .35rem 0 0;
            color: #5d7388;
        }

        .identity strong {
            color: #0f2f57;
        }

        .marker {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            grid-template-rows: repeat(10, 1fr);
            width: min(100%, 400px);
            aspect-ratio: 1;
            margin: 0 auto 1.5rem;
            background: #ffffff;
            image-rendering: pixelated;
        }

        .marker-image {
            display: block;
            height: auto;
            object-fit: contain;
        }

        .bulk-print-list {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .tag-sheet {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .module {
            display: block;
        }

        .module-black {
            background: #000000;
        }

        .module-white {
            background: #ffffff;
        }

        .context {
            color: #6a7f92;
            font-size: .82rem;
            line-height: 1.6;
        }

        .actions {
            display: flex;
            justify-content: center;
            gap: .6rem;
            flex-wrap: wrap;
        }

        .actions a {
            color: #0b5ea8;
            font-weight: 700;
            text-decoration: none;
        }

        @media print {
            @page {
                margin: 12mm;
            }

            body {
                background: #fff;
                padding: 0;
            }

            .card {
                width: 100%;
                border: 0;
                box-shadow: none;
                padding: 0;
            }

            .actions {
                display: none;
            }

            .marker {
                width: 80mm;
            }

            .marker-image {
                width: 80mm;
                height: 80mm;
            }

            .bulk-print-list {
                gap: 0;
            }

            .tag-sheet {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                min-height: 245mm;
            }

            .tag-sheet:not(:last-child) {
                page-break-after: always;
                break-after: page;
            }
        }
    </style>
</head>

<body>
    <main class="card">
        <?php if ($mode === 'bulk_print'): ?>
            <div class="bulk-print-list">
                <?php foreach ($records as $bulkRecord): ?>
                    <article class="tag-sheet">
                        <div class="identity">
                            <h1><?= aprilTagDistributionEscape(
                                trim(
                                    (string) $bulkRecord['last_name'] . ', '
                                    . (string) $bulkRecord['first_name'] . ' '
                                    . (string) ($bulkRecord['middle_name'] ?? '') . ' '
                                    . (string) ($bulkRecord['suffix'] ?? '')
                                )
                            ) ?></h1>
                            <p>
                                Student No.
                                <?= aprilTagDistributionEscape(
                                    (string) $bulkRecord['student_number']
                                ) ?>
                            </p>
                            <p>
                                <strong>AprilTag #<?= (int) $bulkRecord['tag_code'] ?></strong>
                                · tagStandard52h13
                            </p>
                        </div>

                        <img class="marker marker-image" src="data:image/png;base64,<?= base64_encode(
                            $bulkRecord['png']
                        ) ?>"
                            alt="10 by 10 tagStandard52h13 marker for AprilTag #<?= (int) $bulkRecord['tag_code'] ?>">

                        <p class="context">
                            <?= aprilTagDistributionEscape(
                                (string) $bulkRecord['subject_name']
                            ) ?>
                            ·
                            <?= aprilTagDistributionEscape(
                                (string) $bulkRecord['section_name']
                            ) ?>
                            ·
                            <?= aprilTagDistributionEscape(
                                (string) $bulkRecord['school_year']
                            ) ?>
                            ·
                            <?= aprilTagDistributionEscape(
                                (string) $bulkRecord['semester']
                            ) ?>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="identity">
                <h1><?= aprilTagDistributionEscape($displayName) ?></h1>
                <p>
                    Student No.
                    <?= aprilTagDistributionEscape($studentNumber) ?>
                </p>
                <p>
                    <strong>AprilTag #<?= $tagCode ?></strong>
                    · tagStandard52h13
                </p>
            </div>

            <?php if ($mode === 'print' && $png !== null): ?>
                <img class="marker marker-image" src="data:image/png;base64,<?= base64_encode($png) ?>"
                    alt="10 by 10 tagStandard52h13 marker for AprilTag #<?= $tagCode ?>">
            <?php else: ?>
                <div class="marker" role="img" aria-label="10 by 10 tagStandard52h13 marker for AprilTag #<?= $tagCode ?>">
                    <?php foreach ($matrix as $row): ?>
                        <?php foreach ($row as $cell): ?>
                            <span class="module <?= $cell === 1
                                ? 'module-white'
                                : 'module-black' ?>" aria-hidden="true"></span>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <p class="context">
                <?= aprilTagDistributionEscape(
                    (string) $record['subject_name']
                ) ?>
                ·
                <?= aprilTagDistributionEscape(
                    (string) $record['section_name']
                ) ?>
                ·
                <?= aprilTagDistributionEscape(
                    (string) $record['school_year']
                ) ?>
                ·
                <?= aprilTagDistributionEscape(
                    (string) $record['semester']
                ) ?>
            </p>

            <div class="actions">
                <a
                    href="?operational_class_id=<?= $operationalClassId ?>&student_class_enrollment_id=<?= $enrollmentId ?>&mode=download">
                    Download PNG
                </a>

                <a
                    href="?operational_class_id=<?= $operationalClassId ?>&student_class_enrollment_id=<?= $enrollmentId ?>&mode=print">
                    Print
                </a>
            </div>
        <?php endif; ?>
    </main>

    <?php if (in_array($mode, ['print', 'bulk_print'], true)): ?>
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    <?php endif; ?>
</body>

</html>