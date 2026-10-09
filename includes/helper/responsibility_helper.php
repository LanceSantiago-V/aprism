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
        global $pdo;
        if ($responsibility === RESPONSIBILITY_ADVISER && isset($pdo) && $pdo instanceof PDO) {
            return aprismTeacherHasCurrentResponsibility($pdo, (int) ($_SESSION['user_id'] ?? 0), $responsibility);
        }
        return hasResponsibility(
            getSessionResponsibilities(),
            $responsibility
        );
    }
}

if (!function_exists('aprismActiveSchoolYear')) {
    function aprismActiveSchoolYear(PDO $pdo): ?array
    {
        $stmt = $pdo->query("SELECT school_year_id, school_year FROM school_years WHERE status = 'Active' ORDER BY school_year_id DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('aprismTeacherHasCurrentResponsibility')) {
    function aprismTeacherHasCurrentResponsibility(PDO $pdo, int $teacherId, string $responsibility = RESPONSIBILITY_ADVISER, ?int $schoolYearId = null): bool
    {
        if ($schoolYearId === null) {
            $active = aprismActiveSchoolYear($pdo);
            $schoolYearId = $active ? (int) $active['school_year_id'] : 0;
        }
        if ($teacherId <= 0 || $schoolYearId <= 0) return false;
        $stmt = $pdo->prepare("SELECT 1 FROM teacher_responsibility_assignments WHERE teacher_id = ? AND responsibility_name = ? AND school_year_id = ? AND status = 'Active' LIMIT 1");
        $stmt->execute([$teacherId, $responsibility, $schoolYearId]);
        return (bool) $stmt->fetchColumn();
    }
}

if (!function_exists('aprismTeacherHasHistoricalResponsibility')) {
    function aprismTeacherHasHistoricalResponsibility(PDO $pdo, int $teacherId, string $responsibility = RESPONSIBILITY_ADVISER, ?int $schoolYearId = null): bool
    {
        $sql = "SELECT 1 FROM teacher_responsibility_assignments WHERE teacher_id = ? AND responsibility_name = ? AND status IN ('Ended', 'Archived')";
        $params = [$teacherId, $responsibility];
        if ($schoolYearId !== null) { $sql .= " AND school_year_id = ?"; $params[] = $schoolYearId; }
        $stmt = $pdo->prepare($sql . " LIMIT 1");
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }
}
