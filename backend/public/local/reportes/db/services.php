<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_reportes_get_catalogue' => [
        'classname' => 'local_reportes\external\get_catalogue',
        'description' => 'Returns cohorts and the latest export for the current user.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_reportes_start_export' => [
        'classname' => 'local_reportes\external\start_export',
        'description' => 'Queues a full cohort workbook export.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_reportes_get_export_status' => [
        'classname' => 'local_reportes\external\get_export_status',
        'description' => 'Returns the status of a cohort workbook export.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
