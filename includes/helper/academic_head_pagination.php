<?php

declare(strict_types=1);

/**
 * Return a bounded server-side page size for Academic Head data workspaces.
 * Client hints are accepted only from the explicit allow-list for each page.
 */
function aprismAcademicHeadPageSize(string $scope): int
{
    $allowed = match ($scope) {
        'adviser' => [4, 5, 6],
        'reports' => [4, 5, 6, 7],
        default => [5, 6, 7, 8],
    };

    $requested = filter_input(INPUT_GET, 'per_page', FILTER_VALIDATE_INT);

    return in_array($requested, $allowed, true) ? $requested : $allowed[0];
}
