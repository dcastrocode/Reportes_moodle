<?php
namespace local_reportes\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

final class get_catalogue extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        global $DB, $USER;
        self::validate_context(\context_system::instance());
        require_capability('local/reportes:manage', \context_system::instance());
        $cohorts = array_values($DB->get_records('cohort', null, 'name ASC', 'id,idnumber,name'));
        $latestrecords = $DB->get_records('local_reports_export', ['userid' => $USER->id], 'id DESC', '*', 0, 1);
        $latest = $latestrecords ? reset($latestrecords) : null;
        return [
            'cohorts' => array_map(static fn($cohort) => [
                'id' => (int) $cohort->id,
                'name' => format_string($cohort->name),
                'idnumber' => (string) $cohort->idnumber,
            ], $cohorts),
            'export' => \local_reportes\local\export_service::format_status($latest ?: null),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cohorts' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Cohort id'),
                'name' => new external_value(PARAM_TEXT, 'Cohort name'),
                'idnumber' => new external_value(PARAM_RAW, 'Cohort code'),
            ])),
            'export' => \local_reportes\local\export_service::status_structure(),
        ]);
    }
}
