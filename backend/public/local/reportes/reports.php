<?php
require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();
$context = context_system::instance();
require_capability('local/reportes:manage', $context);

$cohortid = required_param('cohortid', PARAM_INT);
$cohort = $DB->get_record('cohort', ['id' => $cohortid], '*', MUST_EXIST);
$generator = new \local_reportes\local\report_generator();
$spreadsheet = $generator->create_single_cohort_workbook($cohort);
$safeid = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $cohort->idnumber ?: (string) $cohort->id);
$filename = 'reporte_cohorte_' . $safeid . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
$spreadsheet->disconnectWorksheets();
exit;
