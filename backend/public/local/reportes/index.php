<?php
require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/reportes:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/reportes/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_reportes'));
$PAGE->set_heading(get_string('pluginname', 'local_reportes'));

$bootstrap = [
    'ajaxUrl' => (new moodle_url('/lib/ajax/service.php'))->out(false),
    'sesskey' => sesskey(),
    'individualExportUrl' => (new moodle_url('/local/reportes/reports.php'))->out(false),
    'userId' => $USER->id,
];

echo $OUTPUT->header();
echo html_writer::script('window.REPORTS_BOOTSTRAP = ' . json_encode($bootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';');
echo html_writer::empty_tag('link', ['rel' => 'stylesheet', 'href' => (new moodle_url('/local/reportes/dist/assets/reportes.css'))->out(false)]);
echo html_writer::div('', '', ['id' => 'root']);
echo html_writer::tag('script', '', ['type' => 'module', 'src' => (new moodle_url('/local/reportes/dist/assets/reportes.js'))->out(false)]);
echo $OUTPUT->footer();
