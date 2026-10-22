<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helper/section_context_helper.php';

function sectionContextAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

$college = [
    ['BSIT1.1A', 'BSIT', '1', 'First Semester', 'A'],
    ['BSIT1.1C', 'BSIT', '1', 'First Semester', 'C'],
    ['BSIT1.2C', 'BSIT', '1', 'Second Semester', 'C'],
    ['BSIT2.1A', 'BSIT', '2', 'First Semester', 'A'],
    ['BSIT2.1B', 'BSIT', '2', 'First Semester', 'B'],
    ['BSIT2.1C', 'BSIT', '2', 'First Semester', 'C'],
    ['BMMA3.1A', 'BMMA', '3', 'First Semester', 'A'],
    ['BMMA3.1B', 'BMMA', '3', 'First Semester', 'B'],
];
foreach ($college as [$name, $code, $year, $semester, $suffix]) {
    $parsed = aprismParseSectionName($name);
    sectionContextAssert(
        $parsed['recognized'] === true
            && $parsed['program_code_candidate'] === $code
            && $parsed['year_level_candidate'] === $year
            && $parsed['semester_candidate'] === $semester
            && $parsed['section_suffix'] === $suffix
            && $parsed['period_family'] === ['Prelim', 'Midterm', 'Pre-Final', 'Final'],
        $name . ' has the confirmed College setup meaning'
    );
}

foreach ([
    ['ICTMAWD 111', '11', 'First Semester', ['Quarter 1', 'Quarter 2']],
    ['ICTMAWD112', '11', 'Second Semester', ['Quarter 3', 'Quarter 4']],
    ['ICTMAWD121', '12', 'First Semester', ['Quarter 1', 'Quarter 2']],
    ['ICTMAWD 122', '12', 'Second Semester', ['Quarter 3', 'Quarter 4']],
] as [$name, $grade, $semester, $periods]) {
    $parsed = aprismParseSectionName($name);
    sectionContextAssert(
        $parsed['recognized'] === true
            && $parsed['program_code_candidate'] === 'ICTMAWD'
            && $parsed['year_level_candidate'] === $grade
            && $parsed['semester_candidate'] === $semester
            && $parsed['period_family'] === $periods,
        $name . ' has the confirmed SHS setup meaning'
    );
}

foreach (['BSIT5.1A', 'BSIT1.3A', 'ICTMAWD113'] as $name) {
    sectionContextAssert(
        aprismParseSectionName($name)['validation_state'] === 'invalid_convention',
        $name . ' requires review instead of a guessed context'
    );
}
sectionContextAssert(
    aprismParseSectionName('BSIT-3A')['validation_state'] === 'unrecognized',
    'an older Section label is not reinterpreted'
);

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE programs (program_id INTEGER PRIMARY KEY, program_code TEXT, academic_level TEXT, status TEXT);
    CREATE TABLE sections (section_id INTEGER PRIMARY KEY, section_name TEXT, program_id INTEGER, year_level TEXT, status TEXT);
    INSERT INTO programs VALUES (1, 'BSIT', 'College', 'Active'), (2, 'ICTMAWD', 'Senior High School', 'Active');
    INSERT INTO sections VALUES (1, 'BSIT2.1C', 1, '3', 'Active'),
        (2, 'BSIT1.1B', NULL, NULL, 'Active'),
        (3, 'ICTMAWD 111', 2, '11', 'Active');");

sectionContextAssert(
    aprismSectionSetupContext($pdo, 'BSIT1.1A', 'First Semester')['validation_state'] === 'validated',
    'a new Section can use its active Program and confirmed context'
);
sectionContextAssert(
    aprismSectionSetupContext($pdo, 'BMMA3.1A', 'First Semester')['validation_state'] === 'unresolved_program',
    'a missing Program is not created or guessed'
);
sectionContextAssert(
    aprismSectionSetupContext($pdo, 'BSIT1.2C', 'First Semester')['validation_state'] === 'conflict',
    'a Section from the other teaching term is rejected'
);
sectionContextAssert(
    aprismSectionSetupContext($pdo, 'BSIT2.1C', 'First Semester')['validation_state'] === 'conflict',
    'an existing conflicting Year Level is not overwritten'
);
sectionContextAssert(
    aprismSectionSetupContext($pdo, 'BSIT1.1B', 'First Semester')['validation_state'] === 'incomplete_section',
    'existing incomplete Section relationships require review'
);
sectionContextAssert(
    aprismSectionSetupContext($pdo, 'ICTMAWD 111', 'First Semester')['validation_state'] === 'validated',
    'a stored SHS Section relationship can be reused'
);
$formattedShs = aprismSectionSetupContext($pdo, 'ICTMAWD111', 'First Semester');
sectionContextAssert(
    $formattedShs['validation_state'] === 'validated'
        && $formattedShs['section_record']['section_name'] === 'ICTMAWD 111',
    'a spacing variant reuses the stored SHS Section instead of creating a duplicate'
);
$pdo->exec("INSERT INTO sections VALUES (4, 'ICTMAWD111', 2, '11', 'Active')");
sectionContextAssert(
    aprismSectionSetupContext($pdo, 'ICTMAWD111', 'First Semester')['validation_state'] === 'ambiguous_section',
    'duplicate formatting variants require review rather than a guessed Section'
);
