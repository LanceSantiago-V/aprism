<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__, 2);
$fixtureRoot = $repoRoot . '/scripts/fixtures/apriltag/tagStandard52h13';
$modulePath = $repoRoot . '/assets/vendor/apriltag/aprism_apriltag_detector.js';
$temporaryDirectory = sys_get_temp_dir() . '/aprism-apriltag-wasm-' . bin2hex(random_bytes(6));

if (!extension_loaded('gd')) {
    throw new RuntimeException('PHP GD is required to convert the PNG fixtures.');
}

if (!is_dir($temporaryDirectory) && !mkdir($temporaryDirectory, 0700, true)) {
    throw new RuntimeException('Unable to create the temporary fixture directory.');
}

try {
    foreach ([0, 1, 2, 12345, 48713] as $tagId) {
        $filename = sprintf('tag52_13_%05d.png', $tagId);
        $image = imagecreatefrompng($fixtureRoot . '/' . $filename);
        if ($image === false) {
            throw new RuntimeException('Unable to load fixture ' . $filename);
        }

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        $width = 800;
        $height = 800;
        $scaledImage = imagecreatetruecolor($width, $height);
        imagecopyresized(
            $scaledImage,
            $image,
            0,
            0,
            0,
            0,
            $width,
            $height,
            $sourceWidth,
            $sourceHeight
        );
        imagedestroy($image);
        $pixels = '';
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($scaledImage, $x, $y);
                $red = ($color >> 16) & 0xff;
                $green = ($color >> 8) & 0xff;
                $blue = $color & 0xff;
                $pixels .= chr((int) round(($red + $green + $blue) / 3));
            }
        }
        imagedestroy($scaledImage);

        $rawFilename = $temporaryDirectory . '/' . sprintf('tag52_13_%05d.raw', $tagId);
        file_put_contents($rawFilename, $width . "\n" . $height . "\n" . $pixels, LOCK_EX);
    }

    $blankWidth = 400;
    $blankHeight = 400;
    file_put_contents(
        $temporaryDirectory . '/blank.raw',
        $blankWidth . "\n" . $blankHeight . "\n" . str_repeat("\xff", $blankWidth * $blankHeight),
        LOCK_EX
    );

    $command = 'node ' . escapeshellarg($repoRoot . '/tools/apriltag-wasm/verify-fixtures.mjs')
        . ' ' . escapeshellarg($temporaryDirectory)
        . ' ' . escapeshellarg($modulePath);
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException('The WASM fixture verification failed.');
    }
} finally {
    foreach (glob($temporaryDirectory . '/*') ?: [] as $path) {
        unlink($path);
    }
    rmdir($temporaryDirectory);
}
