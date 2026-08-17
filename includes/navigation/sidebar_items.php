<?php

declare(strict_types=1);

if (!defined('ROLE_TECHNICAL_ADMINISTRATOR')) {
    require_once __DIR__ . '/../../auth/role_helper.php';
}

return [

    /*
    |--------------------------------------------------------------------------
    | Technical Administrator
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | Academic Head
    |--------------------------------------------------------------------------
    */

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
            'id' => 'sections',
            'label' => 'Sections',
            'icon' => 'layers-3',
            'url' => APP_URL . '/dashboard/academic_head_sections.php',
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

    ],


    /*
    |--------------------------------------------------------------------------
    | Teacher
    |--------------------------------------------------------------------------
    |
    | This is the base Teacher navigation.
    |
    | Responsibility navigation is intentionally NOT mixed into this
    | array. The base Teacher experience must remain unchanged.
    |
    */

    ROLE_TEACHER => [

        [
            'id' => 'dashboard',
            'label' => 'Dashboard',
            'icon' => 'layout-dashboard',
            'url' => APP_URL . '/dashboard/teacher.php',
            'enabled' => true,
        ],

        [
            'id' => 'my_classes',
            'label' => 'My Classes',
            'icon' => 'book-open',
            'url' => APP_URL . '/dashboard/teacher_my_class.php',
            'enabled' => true,
        ],

        [
            'id' => 'attendance',
            'label' => 'Attendance',
            'icon' => 'users',
            'url' => APP_URL . '/dashboard/teacher_attendance.php',
            'enabled' => true,
        ],

        [
            'id' => 'grade_import',
            'label' => 'Grade Import',
            'icon' => 'file-up',
            'url' => APP_URL . '/dashboard/teacher_grade_import.php',
            'enabled' => true,
        ],

        [
            'id' => 'referrals',
            'label' => 'Referrals',
            'icon' => 'triangle-alert',
            'url' => APP_URL . '/dashboard/teacher_referrals.php',
            'enabled' => true,
        ],

        [
            'id' => 'reports',
            'label' => 'Reports',
            'icon' => 'file-text',
            'url' => APP_URL . '/dashboard/teacher_reports.php',
            'enabled' => true,
        ],

    ],


    /*
    |--------------------------------------------------------------------------
    | Responsibility Navigation
    |--------------------------------------------------------------------------
    |
    | These are definitions only.
    |
    | navigation.php will later determine which groups are appended
    | according to the authenticated Teacher's responsibilities.
    |
    | These pages are intentionally disabled for now because the
    | Adviser and Program Head pages have not been implemented yet.
    |
    */

    'responsibility_navigation' => [

        'Adviser' => [

            'type' => 'section',

            'id' => 'adviser',

            'label' => 'Adviser',

            'items' => [

                [
                    'id' => 'advisory_monitoring',
                    'label' => 'Advisory Monitoring',
                    'icon' => 'layout-dashboard',
                    'url' => APP_URL . '/dashboard/teacher_advisory_monitoring.php',
                    'enabled' => true,
                ],

                [
                    'id' => 'advisory_students',
                    'label' => 'Advisory Students',
                    'icon' => 'users',
                    'url' => APP_URL . '/dashboard/teacher_advisory_students.php',
                    'enabled' => false,
                ],

                [
                    'id' => 'risk_alerts',
                    'label' => 'Risk Alerts',
                    'icon' => 'triangle-alert',
                    'url' => APP_URL . '/dashboard/teacher_risk_alerts.php',
                    'enabled' => false,
                ],

                [
                    'id' => 'referral_followups',
                    'label' => 'Referral Follow-ups',
                    'icon' => 'clipboard-check',
                    'url' => APP_URL . '/dashboard/teacher_referral_followups.php',
                    'enabled' => false,
                ],

                [
                    'id' => 'advisory_reports',
                    'label' => 'Advisory Reports',
                    'icon' => 'file-text',
                    'url' => APP_URL . '/dashboard/teacher_advisory_reports.php',
                    'enabled' => false,
                ],

            ],

        ],


        'Program Head' => [

            'type' => 'section',

            'id' => 'program_head',

            'label' => 'Program Head',

            'items' => [

                [
                    'id' => 'program_overview',
                    'label' => 'Program Overview',
                    'icon' => 'layout-dashboard',
                    'url' => APP_URL . '/dashboard/teacher_program_overview.php',
                    'enabled' => true,
                ],

                [
                    'id' => 'program_analytics',
                    'label' => 'Program Analytics',
                    'icon' => 'chart-column',
                    'url' => APP_URL . '/dashboard/teacher_program_analytics.php',
                    'enabled' => false,
                ],

                [
                    'id' => 'faculty_overview',
                    'label' => 'Faculty Overview',
                    'icon' => 'users',
                    'url' => APP_URL . '/dashboard/teacher_faculty_overview.php',
                    'enabled' => false,
                ],

                [
                    'id' => 'program_reports',
                    'label' => 'Program Reports',
                    'icon' => 'file-text',
                    'url' => APP_URL . '/dashboard/teacher_program_reports.php',
                    'enabled' => false,
                ],

            ],

        ],

    ],

];