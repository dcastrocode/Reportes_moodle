<?php
namespace local_reportes\task;

defined('MOODLE_INTERNAL') || die();

final class generate_export extends \core\task\adhoc_task {
    public function get_name(): string {
        return get_string('exporttask', 'local_reportes');
    }

    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();
        $exportid = (int) ($data->exportid ?? 0);
        $export = $DB->get_record('local_reports_export', ['id' => $exportid]);
        if (!$export || in_array($export->status, ['completed', 'completed_with_warnings', 'expired'], true)) {
            return;
        }

        $factory = \core\lock\lock_config::get_lock_factory('local_reportes_export');
        $lock = $factory->get_lock('all-cohorts', 0);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }

        $spreadsheet = null;
        $temporarypath = null;
        try {
            $generator = new \local_reportes\local\report_generator();
            $cohorts = $generator->get_cohorts();
            $now = time();
            $export->status = 'running';
            $export->progress = 0;
            $export->totalcohorts = count($cohorts);
            $export->processedcohorts = 0;
            $export->timestarted = $now;
            $export->timemodified = $now;
            $DB->update_record('local_reports_export', $export);

            $result = $generator->create_complete_workbook(
                $cohorts,
                static function(int $processed, int $total, string $current) use ($DB, $exportid): void {
                    $DB->set_field('local_reports_export', 'processedcohorts', $processed, ['id' => $exportid]);
                    $DB->set_field('local_reports_export', 'progress', $total ? (int) floor($processed * 95 / $total) : 95, ['id' => $exportid]);
                    $DB->set_field('local_reports_export', 'currentcohort', $current, ['id' => $exportid]);
                    $DB->set_field('local_reports_export', 'timemodified', time(), ['id' => $exportid]);
                }
            );
            $spreadsheet = $result['spreadsheet'];

            $filename = 'reporte_cohortes_' . userdate(time(), '%Y-%m-%d_%H%M%S', 99, false) . '.xlsx';
            $temporarypath = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save($temporarypath);

            $context = \context_system::instance();
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'local_reportes', 'exports', $exportid);
            $fs->create_file_from_pathname([
                'contextid' => $context->id,
                'component' => 'local_reportes',
                'filearea' => 'exports',
                'itemid' => $exportid,
                'filepath' => '/',
                'filename' => $filename,
                'userid' => $export->userid,
            ], $temporarypath);

            $warnings = $result['warnings'];
            $completed = time();
            $export->status = $warnings ? 'completed_with_warnings' : 'completed';
            $export->progress = 100;
            $export->processedcohorts = count($cohorts);
            $export->currentcohort = null;
            $export->filename = $filename;
            $export->warningcount = count($warnings);
            $export->errormessage = $warnings ? implode("\n", $warnings) : null;
            $export->timecompleted = $completed;
            $export->timemodified = $completed;
            $export->expiresat = $completed + (2 * DAYSECS);
            $DB->update_record('local_reports_export', $export);
        } catch (\Throwable $exception) {
            $DB->set_field('local_reports_export', 'status', 'failed', ['id' => $exportid]);
            $DB->set_field('local_reports_export', 'errormessage', $exception->getMessage(), ['id' => $exportid]);
            $DB->set_field('local_reports_export', 'timecompleted', time(), ['id' => $exportid]);
            $DB->set_field('local_reports_export', 'timemodified', time(), ['id' => $exportid]);
            mtrace('local_reportes export ' . $exportid . ' failed: ' . $exception->getMessage());
        } finally {
            if ($spreadsheet) {
                $spreadsheet->disconnectWorksheets();
            }
            if ($temporarypath && file_exists($temporarypath)) {
                @unlink($temporarypath);
            }
            $lock->release();
        }
    }
}
