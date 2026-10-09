<?php
/**
 * APRISM AprilTag Renderer Foundation
 *
 * Renders the official tagStandard52h13 family data into a deterministic PNG.
 *
 * Encoding and family data provenance:
 * - Upstream project: AprilRobotics/apriltag
 * - Pinned revision: b7c0ebe9aa20f82ec7a828579004f9e706bfecd9
 * - Family source: tagStandard52h13.c
 * - Family source SHA-256:
 *   d93f9ead23897031c6846f0c0465cbfed1ee95b6d44aa973896ffc869d9769f7
 *
 * The family/codeword data is mechanically generated in:
 * includes/data/apriltag/tagStandard52h13.php
 *
 * AprilRobotics copyright notice:
 * Copyright (C) 2013-2016, The Regents of the University of Michigan.
 *
 * Licensed under the BSD 2-Clause License.
 *
 * This helper performs no database writes and makes no assignment changes.
 */

declare(strict_types=1);

const APRISM_APRILTAG_FAMILY = 'tagStandard52h13';
const APRISM_APRILTAG_MIN_ID = 0;
const APRISM_APRILTAG_MAX_ID = 48713;

/**
 * Load the mechanically generated official family data.
 *
 * @return array<string, mixed>
 *
 * @throws RuntimeException If the generated family data cannot be loaded.
 */
function aprism_apriltag_family_data(): array
{
    static $familyData = null;

    if ($familyData !== null) {
        return $familyData;
    }

    $dataPath = __DIR__ . '/../data/apriltag/tagStandard52h13.php';

    if (!is_file($dataPath)) {
        throw new RuntimeException(
            'The generated tagStandard52h13 family data file is missing.'
        );
    }

    $data = require $dataPath;

    if (!is_array($data)) {
        throw new RuntimeException(
            'The generated tagStandard52h13 family data is invalid.'
        );
    }

    $familyData = $data;

    return $familyData;
}

/**
 * Validate an AprilTag numeric ID.
 *
 * @param mixed $tagId
 *
 * @throws InvalidArgumentException If the ID is not an integer in range.
 */
function aprism_validate_apriltag_id(mixed $tagId): int
{
    if (is_bool($tagId) || !is_int($tagId)) {
        throw new InvalidArgumentException(
            'AprilTag ID must be an integer.'
        );
    }

    if (
        $tagId < APRISM_APRILTAG_MIN_ID
        || $tagId > APRISM_APRILTAG_MAX_ID
    ) {
        throw new InvalidArgumentException(
            'AprilTag ID must be between 0 and 48713.'
        );
    }

    return $tagId;
}

/**
 * Build the official normalized 10x10 AprilTag module matrix.
 *
 * Matrix values:
 * - 0 = black
 * - 1 = white
 *
 * This follows the pinned AprilRobotics apriltag_to_image() behavior:
 * - black image initialization;
 * - official four-loop white-border construction;
 * - MSB-first codeword interpretation;
 * - official bit_x/bit_y coordinates and offsets.
 *
 * @param int $tagId
 *
 * @return array<int, array<int, int>>
 */
function aprism_apriltag_matrix(int $tagId): array
{
    $tagId = aprism_validate_apriltag_id($tagId);
    $data = aprism_apriltag_family_data();

    $family = $data['family'] ?? null;
    $codewords = $data['codewords'] ?? null;
    $bitX = $data['bit_x'] ?? null;
    $bitY = $data['bit_y'] ?? null;

    if (
        !is_array($family)
        || !is_array($codewords)
        || !is_array($bitX)
        || !is_array($bitY)
    ) {
        throw new RuntimeException(
            'The generated tagStandard52h13 family data has an invalid structure.'
        );
    }

    $familyName = $family['name'] ?? null;
    $height = $family['h'] ?? null;
    $codeCount = $family['ncodes'] ?? null;
    $bitCount = $family['nbits'] ?? null;
    $widthAtBorder = $family['width_at_border'] ?? null;
    $totalWidth = $family['total_width'] ?? null;
    $reversedBorder = $family['reversed_border'] ?? null;

    if (
        $familyName !== APRISM_APRILTAG_FAMILY
        || $height !== 13
        || $codeCount !== 48714
        || $bitCount !== 52
        || $widthAtBorder !== 6
        || $totalWidth !== 10
        || $reversedBorder !== true
        || count($codewords) !== 48714
        || count($bitX) !== 52
        || count($bitY) !== 52
    ) {
        throw new RuntimeException(
            'The generated tagStandard52h13 family metadata is invalid.'
        );
    }

    $codeword = $codewords[$tagId] ?? null;

    if (!is_int($codeword) && !is_string($codeword)) {
        throw new RuntimeException(
            'The requested AprilTag codeword is missing.'
        );
    }

    $codeword = (int) $codeword;

    // Official apriltag_to_image() border calculation.
    $whiteBorderWidth = $widthAtBorder
        + ($reversedBorder ? 0 : 2);

    $whiteBorderStart = intdiv(
        $totalWidth - $whiteBorderWidth,
        2
    );

    // Official image buffer starts completely black.
    $matrix = array_fill(
        0,
        $totalWidth,
        array_fill(0, $totalWidth, 0)
    );

    /*
     * Exact translation of the pinned official four assignments:
     *
     * top:
     *   row start, column start + i
     *
     * right:
     *   row start + i, column total - 1 - start
     *
     * bottom:
     *   row total - 1 - start, column start + i + 1
     *
     * left:
     *   row start + i + 1, column start
     */
    for ($i = 0; $i < $whiteBorderWidth - 1; $i++) {
        // Top edge.
        $matrix[$whiteBorderStart]
        [$whiteBorderStart + $i] = 1;

        // Right edge.
        $matrix[$whiteBorderStart + $i]
        [$totalWidth - 1 - $whiteBorderStart] = 1;

        // Bottom edge.
        $matrix[$totalWidth - 1 - $whiteBorderStart]
        [$whiteBorderStart + $i + 1] = 1;

        // Left edge.
        $matrix[$whiteBorderStart + $i + 1]
        [$whiteBorderStart] = 1;
    }

    // Official data-coordinate offset.
    $borderStart = intdiv(
        $totalWidth - $widthAtBorder,
        2
    );

    for ($bit = 0; $bit < $bitCount; $bit++) {
        /*
         * Exact official MSB-first mapping:
         *
         * code & (APRILTAG_U64_ONE << (fam->nbits - i - 1))
         */
        $bitIsWhite = (
            ($codeword >> ($bitCount - $bit - 1)) & 1
        ) === 1;

        if (!$bitIsWhite) {
            continue;
        }

        $x = (int) $bitX[$bit] + $borderStart;
        $y = (int) $bitY[$bit] + $borderStart;

        if (
            $x < 0
            || $x >= $totalWidth
            || $y < 0
            || $y >= $totalWidth
        ) {
            throw new RuntimeException(
                'The generated AprilTag bit coordinate is outside the family layout.'
            );
        }

        $matrix[$y][$x] = 1;
    }

    return $matrix;
}

/**
 * Render the official 10x10 matrix as a PNG binary string using PHP GD.
 *
 * The encoded matrix is not changed. Each module is rasterized as an exact
 * square of pixels. No quiet zone or print margin is added here.
 *
 * @param int $tagId
 * @param int $moduleSize Number of pixels per module.
 *
 * @return string PNG binary data.
 */
function aprism_apriltag_png(
    int $tagId,
    int $moduleSize = 20
): string {
    $tagId = aprism_validate_apriltag_id($tagId);

    if ($moduleSize < 1) {
        throw new InvalidArgumentException(
            'AprilTag module size must be at least 1 pixel.'
        );
    }

    if (!function_exists('imagecreatetruecolor')) {
        throw new RuntimeException(
            'PHP GD is required to render AprilTag PNG output.'
        );
    }

    $matrix = aprism_apriltag_matrix($tagId);
    $matrixWidth = count($matrix);
    $canvasSize = $matrixWidth * $moduleSize;

    $image = imagecreatetruecolor($canvasSize, $canvasSize);

    if ($image === false) {
        throw new RuntimeException(
            'Unable to create the AprilTag GD image.'
        );
    }

    imagealphablending($image, false);
    imagesavealpha($image, false);

    $black = imagecolorallocate($image, 0, 0, 0);
    $white = imagecolorallocate($image, 255, 255, 255);

    imagefill($image, 0, 0, $black);

    foreach ($matrix as $row => $modules) {
        foreach ($modules as $column => $value) {
            if ($value !== 1) {
                continue;
            }

            $left = $column * $moduleSize;
            $top = $row * $moduleSize;
            $right = $left + $moduleSize - 1;
            $bottom = $top + $moduleSize - 1;

            imagefilledrectangle(
                $image,
                $left,
                $top,
                $right,
                $bottom,
                $white
            );
        }
    }

    ob_start();

    $pngCreated = imagepng($image);
    $pngData = ob_get_clean();

    imagedestroy($image);

    if (!$pngCreated || !is_string($pngData)) {
        throw new RuntimeException(
            'Unable to encode the AprilTag image as PNG.'
        );
    }

    return $pngData;
}