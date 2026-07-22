<?php

if (!defined('RESPONSIBILITY_ADVISER')) {
    define('RESPONSIBILITY_ADVISER', 'Adviser');
}

if (!defined('RESPONSIBILITY_PROGRAM_HEAD')) {
    define('RESPONSIBILITY_PROGRAM_HEAD', 'Program Head');
}

if (!function_exists('getUserResponsibilities')) {

    /**
     * Retrieves all institutional responsibilities
     * assigned to a user.
     *
     * Responsibilities expand a user's data scope.
     * They do NOT grant additional administrative capabilities.
     *
     * @param PDO $pdo
     * @param int $userId
     * @return array
     */
    function getUserResponsibilities(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare("
            SELECT permission_name
            FROM user_permissions
            WHERE user_id = ?
            ORDER BY permission_name
        ");

        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

if (!function_exists('hasResponsibility')) {

    /**
     * Determines whether the supplied responsibility
     * is assigned to the user.
     *
     * @param array $responsibilities
     * @param string $responsibility
     * @return bool
     */
    function hasResponsibility(array $responsibilities, string $responsibility): bool
    {
        return in_array($responsibility, $responsibilities, true);
    }
}

if (!function_exists('getSessionResponsibilities')) {

    /**
     * Retrieves the authenticated user's
     * responsibilities from the session.
     *
     * @return array
     */
    function getSessionResponsibilities(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return [];
        }

        return $_SESSION['responsibilities'] ?? [];
    }
}

if (!function_exists('userHasResponsibility')) {

    /**
     * Determines whether the authenticated
     * user has the supplied responsibility.
     *
     * @param string $responsibility
     * @return bool
     */
    function userHasResponsibility(string $responsibility): bool
    {
        return hasResponsibility(
            getSessionResponsibilities(),
            $responsibility
        );
    }
}