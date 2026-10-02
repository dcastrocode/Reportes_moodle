<?php
namespace local_reportes\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;

final class get_export_status extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(['exportid' => new external_value(PARAM_INT, 'Export id')]);
    }

    public static function execute(int $exportid): array {
        global $DB, $USER;
        ['exportid' => $exportid] = self::validate_parameters(self::execute_parameters(), ['exportid' => $exportid]);
        self::validate_context(\context_system::instance());
        require_capability('local/reportes:manage', \context_system::instance());
        $export = $DB->get_record('local_reports_export', ['id' => $exportid], '*', MUST_EXIST);
        if ((int) $export->userid !== (int) $USER->id) {
            throw new \moodle_exception('nopermissions', 'error');
        }
        return \local_reportes\local\export_service::format_status($export);
    }

    public static function execute_returns(): \core_external\external_single_structure {
        return \local_reportes\local\export_service::status_structure();
    }
}
