<?php

require_once __DIR__ . '/role_helper.php';

if (!function_exists('getCurrentRoleId')) {

    /**
     * Retrieves the authenticated user's role ID
     * from the current session.
     *
     * @return int|null
     */
    function getCurrentRoleId(): ?int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        return isset($_SESSION['role_id'])
            ? (int) $_SESSION['role_id']
            : null;
    }
}

if (!function_exists('userHasRole')) {

    /**
     * Determines whether the authenticated user
     * has the supplied role.
     *
     * @param int $roleId
     * @return bool
     */
    function userHasRole(int $roleId): bool
    {
        return getCurrentRoleId() === $roleId;
    }
}

if (!function_exists('isTechnicalAdministrator')) {

    function isTechnicalAdministrator(): bool
    {
        return userHasRole(ROLE_TECHNICAL_ADMINISTRATOR);
    }
}

if (!function_exists('isAcademicHead')) {

    function isAcademicHead(): bool
    {
        return userHasRole(ROLE_ACADEMIC_HEAD);
    }
}

if (!function_exists('isTeacher')) {

    function isTeacher(): bool
    {
        return userHasRole(ROLE_TEACHER);
    }
}

if (!function_exists('isDisciplinaryOfficer')) {

    function isDisciplinaryOfficer(): bool
    {
        return userHasRole(ROLE_DISCIPLINARY_OFFICER);
    }
}