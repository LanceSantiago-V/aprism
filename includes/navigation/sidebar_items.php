<?php

declare(strict_types=1);

if (!defined('ROLE_TECHNICAL_ADMINISTRATOR')) {
    require_once __DIR__ . '/../../auth/role_helper.php';
}

return [

    ROLE_TECHNICAL_ADMINISTRATOR => [

        [
            'id' => 'dashboard',
            'label' => 'Dashboard',
            'icon' => 'layout-dashboard',
            'url' => APP_URL . '/dashboard/technical_admin.php',
        ],

        [
            'id' => 'users',
            'label' => 'Users',
            'icon' => 'users',
            'url' => APP_URL . '/dashboard/technical_admin_users.php',
        ],

        [
            'id' => 'audit_logs',
            'label' => 'Audit Logs',
            'icon' => 'scroll-text',
            'url' => APP_URL . '/dashboard/technical_admin_audit_logs.php',
        ],

        [
            'id' => 'backups',
            'label' => 'Database Backups',
            'icon' => 'database-backup',
            'url' => APP_URL . '/dashboard/technical_admin_backups.php',
        ],

        [
            'id' => 'settings',
            'label' => 'Settings',
            'icon' => 'settings',
            'url' => APP_URL . '/dashboard/technical_admin_settings.php',
        ],

    ],

    ROLE_ACADEMIC_HEAD => [

        [
            'id' => 'dashboard',
            'label' => 'Dashboard',
            'icon' => 'layout-dashboard',
            'url' => APP_URL . '/dashboard/academic_head.php',
            'enabled' => true,
        ],

        [
            'id' => 'academic_setup',
            'label' => 'Academic Setup',
            'icon' => 'clipboard-list',
            'url' => APP_URL . '/dashboard/academic_head_academic_setup.php',
            'enabled' => true,
        ],

        [
            'id' => 'programs',
            'label' => 'Programs',
            'icon' => 'graduation-cap',
            'url' => APP_URL . '/dashboard/academic_head_programs.php',
            'enabled' => true,
        ],

        [
            'id' => 'sections',
            'label' => 'Sections',
            'icon' => 'layers-3',
            'url' => APP_URL . '/dashboard/academic_head_sections.php',
            'enabled' => true,
        ],

        [
            'id' => 'subjects',
            'label' => 'Subjects',
            'icon' => 'book-open',
            'url' => APP_URL . '/dashboard/academic_head_subjects.php',
            'enabled' => true,
        ],

        [
            'id' => 'schedules',
            'label' => 'Schedules',
            'icon' => 'calendar-days',
            'url' => APP_URL . '/dashboard/academic_head_schedules.php',
            'enabled' => true,
        ],

        [
            'id' => 'students',
            'label' => 'Students',
            'icon' => 'users',
            'url' => APP_URL . '/dashboard/academic_head_students.php',
            'enabled' => true,
        ],

        [
            'id' => 'reports',
            'label' => 'Reports',
            'icon' => 'file-text',
            'url' => APP_URL . '/dashboard/academic_head_reports.php',
            'enabled' => true,
        ],

        [
            'id' => 'master_reference',
            'label' => 'Master Reference',
            'icon' => 'library',
            'url' => '#',
            'enabled' => false,
        ],

    ],

];
