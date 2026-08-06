<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../auth/role_helper.php';
require_once __DIR__ . '/../helper/responsibility_helper.php';

function getSidebarItems(
    int $roleId,
    array $responsibilities = []
): array {

    $sidebarDefinitions = require __DIR__ . '/sidebar_items.php';

    $sidebarItems = $sidebarDefinitions[$roleId] ?? [];

    return applyResponsibilityNavigation(
        $sidebarItems,
        $responsibilities
    );

}

function applyResponsibilityNavigation(
    array $sidebarItems,
    array $responsibilities
): array {

    return $sidebarItems;

}