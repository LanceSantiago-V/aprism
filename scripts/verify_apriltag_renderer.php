<?php

declare(strict_types=1);

/**
 * APRISM Phase A AprilTag Renderer Verification
 *
 * Verifies:
 * - official fixture matrix equality;
 * - IDs 0, 1, 2, 12345, and 48713;
 * - invalid IDs -1 and 48714;
 * - exact cell-by-cell comparison.
 *
 * No database, assignment, Composer, or production access is used.
 */

require_once __DIR__ . '/../includes/helper/apriltag_renderer.php';

$fixtureDirectory = __DIR__
    . '/fixtures/apriltag/tagStandard52h13';

$cases = [
    0 => 'tag52_13_00000.png',
    1 => 'tag52_13_00001.png',
    2 => 'tag52_13_00002.png',
    12345 => 'tag52_13_12345.png',
    48713 => 'tag52_13_48713.png',
];

$failed = false;

foreach ($cases as $tagId => $fixtureName) {
    $fixturePath = $fixtureDirectory . DIRECTORY_SEPARATOR . $fixtureName;

    if (!is_file($fixturePath)) {
        echo "FAIL ID {$tagId}: fixture is missing\n";
        $failed = true;
        continue;
    }

    $image = imagecreatefrompng($fixturePath);

    if ($image === false) {
        echo "FAIL ID {$tagId}: fixture could not be loaded\n";
        $failed = true;
        continue;
    }

    $width = imagesx($image);
    $height = imagesy($image);

    if ($width !== 10 || $height !== 10) {
        echo "FAIL ID {$tagId}: fixture is {$width}x{$height}, expected 10x10\n";
        imagedestroy($image);
        $failed = true;
        continue;
    }

    $expected = aprism_apriltag_matrix($tagId);
    $mismatches = [];

    for ($y = 0; $y < 10; $y++) {
        for ($x = 0; $x < 10; $x++) {
            $rgb = imagecolorat($image, $x, $y);
            $actual = (($rgb & 0xFF) >= 128) ? 1 : 0;

            if ($actual !== $expected[$y][$x]) {
                $mismatches[] = [
                    'x' => $x,
                    'y' => $y,
                    'expected' => $expected[$y][$x],
                    'actual' => $actual,
                ];
            }
        }
    }

    imagedestroy($image);

    if ($mismatches === []) {
        echo "PASS ID {$tagId}: all 100 cells match\n";
        continue;
    }

    echo "FAIL ID {$tagId}: "
        . count($mismatches)
        . " mismatching cell(s)\n";

    foreach ($mismatches as $mismatch) {
        echo "  ({$mismatch['x']},{$mismatch['y']}): "
            . "expected={$mismatch['expected']} "
            . "actual={$mismatch['actual']}\n";
    }

    $failed = true;
}

foreach ([-1, 48714] as $invalidId) {
    try {
        aprism_apriltag_matrix($invalidId);
        echo "FAIL invalid ID {$invalidId}: was not rejected\n";
        $failed = true;
    } catch (InvalidArgumentException) {
        echo "PASS invalid ID {$invalidId}: rejected\n";
    }
}

if ($failed) {
    echo "RESULT: FAIL\n";
    exit(1);
}

echo "RESULT: PASS\n";
exit(0);