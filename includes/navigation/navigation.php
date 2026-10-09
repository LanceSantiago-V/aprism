<?php

declare(strict_types=1);


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . '/../../auth/role_helper.php';
require_once __DIR__ . '/../helper/responsibility_helper.php';
require_once __DIR__ . '/../helper/adviser_helper.php';



/**
 * Builds the sidebar navigation for the authenticated user.
 *
 * The primary role determines the user's base navigation.
 * Additional responsibilities may append responsibility-specific
 * navigation groups without replacing the base role navigation.
 *
 * Responsibilities are intentionally handled separately from
 * authentication roles.
 *
 * @param int   $roleId
 * @param array $responsibilities
 * @return array
 */
function getSidebarItems(
    int $roleId,
    array $responsibilities = []
): array {

    $sidebarDefinitions = require __DIR__ . '/sidebar_items.php';

    /*
     * The primary role always determines the base navigation.
     *
     * If an unknown role is supplied, return an empty navigation
     * rather than attempting to construct responsibility navigation.
     */
    $sidebarItems = $sidebarDefinitions[$roleId] ?? [];

    /*
     * Responsibility-based navigation currently applies only
     * to Teacher accounts.
     *
     * Academic Head and Technical Administrator navigation
     * remain completely unchanged.
     */
    if ($roleId !== ROLE_TEACHER) {
        return $sidebarItems;
    }

    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO) {
        $currentAdviser = aprismTeacherHasCurrentResponsibility(
            $pdo,
            (int) ($_SESSION['user_id'] ?? 0),
            RESPONSIBILITY_ADVISER
        );
        $activeSchoolYear = aprismActiveSchoolYear($pdo);
        $hasCurrentAdviserScope = $activeSchoolYear !== null
            && aprismAdviserAssignments(
                $pdo,
                (int) ($_SESSION['user_id'] ?? 0),
                (int) $activeSchoolYear['school_year_id']
            ) !== [];
        $responsibilities = array_values(array_filter(
            $responsibilities,
            static fn (string $item): bool => $item !== RESPONSIBILITY_ADVISER
        ));
        if ($currentAdviser && $hasCurrentAdviserScope) {
            $responsibilities[] = RESPONSIBILITY_ADVISER;
        }
    }

    return applyResponsibilityNavigation(
        $sidebarItems,
        $responsibilities,
        $sidebarDefinitions
    );
}



/**
 * Appends responsibility-specific navigation to the base
 * Teacher navigation.
 *
 * Adviser is an additional Teacher responsibility, not an authentication
 * role. Program Head remains a legacy/deferred capability and is intentionally
 * not surfaced in the active navigation.
 *
 * Therefore:
 *
 * Teacher
 *      ↓
 * Base Teacher navigation
 *      +
 * Adviser navigation, when assigned
 *
 * @param array $sidebarItems
 * @param array $responsibilities
 * @param array $sidebarDefinitions
 * @return array
 */
function applyResponsibilityNavigation(
    array $sidebarItems,
    array $responsibilities,
    array $sidebarDefinitions
): array {

    $responsibilityNavigation =
        $sidebarDefinitions['responsibility_navigation']
        ?? [];


    /*
     * --------------------------------------------------------------
     * Adviser Responsibility
     * --------------------------------------------------------------
     *
     * Append the Adviser section only when the Teacher has
     * the Adviser responsibility.
     *
     * The existing Teacher navigation is never replaced.
     */
    if (
        hasResponsibility(
            $responsibilities,
            RESPONSIBILITY_ADVISER
        )
        &&
        isset($responsibilityNavigation['Adviser'])
        &&
        is_array($responsibilityNavigation['Adviser'])
    ) {

        $sidebarItems[] =
            $responsibilityNavigation['Adviser'];
    }

    return $sidebarItems;
}
