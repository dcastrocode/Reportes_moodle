<?php
namespace local_reportes\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;

final class start_export extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        global $DB, $USER;
        self::validate_context(\context_system::instance());
        require_capability('local/reportes:manage', \context_system::instance());

        $factory = \core\lock\lock_config::get_lock_factory('local_reportes_export_request');
        $lock = $factory->get_lock('all-cohorts', 5);
        if (!$lock) {
            throw new \moodle_exception('exportalreadyrunning', 'local_reportes');
        }
        try {
            $activerecords = $DB->get_records_select(
                'local_reports_export',
                "status IN ('pending', 'running')",
                null,
                'id DESC',
                '*',
                0,
                1
            );
            $active = $activerecords ? reset($activerecords) : null;
            if ($active) {
                if ((int) $active->userid === (int) $USER->id) {
                    return \local_reportes\local\export_service::format_status($active);
                }
                throw new \moodle_exception('exportalreadyrunning', 'local_reportes');
            }

            $now = time();
            $exportid = $DB->insert_record('local_reports_export', (object) [
                'userid' => $USER->id,
                'status' => 'pending',
                'progress' => 0,
                'totalcohorts' => 0,
                'processedcohorts' => 0,
                'warningcount' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $task = new \local_reportes\task\generate_export();
            $task->set_custom_data(['exportid' => $exportid]);
            $task->set_userid($USER->id);
            \core\task\manager::queue_adhoc_task($task, true);
            return \local_reportes\local\export_service::format_status(
                $DB->get_record('local_reports_export', ['id' => $exportid], '*', MUST_EXIST)
            );
        } finally {
            $lock->release();
        }
    }

    public static function execute_returns(): \core_external\external_single_structure {
        return \local_reportes\local\export_service::status_structure();
    }
}
