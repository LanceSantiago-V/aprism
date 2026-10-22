<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helper/guidance_helper.php';

date_default_timezone_set('Asia/Manila');

$validCases = [
    '2026-10-22T14:30' => '2026-10-22 14:30:00',
    '2024-02-29T09:05' => '2024-02-29 09:05:00',
];

foreach ($validCases as $input => $expected) {
    $parsed = aprismGuidanceParseFollowupDateTime($input);
    if ($parsed->format('Y-m-d H:i:s') !== $expected || $parsed->getTimezone()->getName() !== 'Asia/Manila') {
        throw new RuntimeException('Valid follow-up date/time was not preserved: ' . $input);
    }
}

$invalidCases = [
    '2026-02-30T10:00',
    '2026-13-01T10:00',
    '2026-00-01T10:00',
    '2026-10-22T24:00',
    '2026-10-22T10:60',
    '2026-10-22 10:00',
    '2026-10-22T10:00:00',
    '',
    ['2026-10-22T10:00'],
];

foreach ($invalidCases as $input) {
    try {
        aprismGuidanceParseFollowupDateTime($input);
    } catch (InvalidArgumentException) {
        continue;
    }

    throw new RuntimeException('Invalid follow-up date/time was accepted.');
}

echo "Follow-up date validation: " . count($validCases) . " valid cases and " . count($invalidCases) . " invalid cases passed.\n";
