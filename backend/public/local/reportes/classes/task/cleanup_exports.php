<?php
namespace local_reportes\task;

defined('MOODLE_INTERNAL') || die();

final class cleanup_exports extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('cleanup', 'local_reportes');
    }

    public function execute(): void {
        global $DB;
        $now = time();
        $context = \context_system::instance();
        $fs = get_file_storage();
        $expired = $DB->get_records_select('local_reports_export', 'expiresat > 0 AND expiresat <= :now', ['now' => $now]);
        foreach ($expired as $export) {
            $fs->delete_area_files($context->id, 'local_reportes', 'exports', $export->id);
            $export->status = 'expired';
            $export->filename = null;
            $export->timemodified = $now;
            $DB->update_record('local_reports_export', $export);
        }
        $DB->delete_records_select('local_reports_export', 'timecreated < :cutoff AND status = :status', [
            'cutoff' => $now - (30 * DAYSECS),
            'status' => 'expired',
        ]);
    }
}
