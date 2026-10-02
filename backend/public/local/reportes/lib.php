<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Serves generated exports from Moodle file storage.
 */
function local_reportes_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []): void {
    global $DB, $USER;

    require_login();
    if ($context->contextlevel !== CONTEXT_SYSTEM || $filearea !== 'exports') {
        send_file_not_found();
    }

    $exportid = (int) array_shift($args);
    $filename = array_pop($args);
    $export = $DB->get_record('local_reports_export', ['id' => $exportid], '*', MUST_EXIST);
    if ((int) $export->userid !== (int) $USER->id && !has_capability('local/reportes:manage', $context)) {
        throw new required_capability_exception($context, 'local/reportes:manage', 'nopermissions', '');
    }

    $file = get_file_storage()->get_file(
        $context->id,
        'local_reportes',
        'exports',
        $exportid,
        '/',
        $filename
    );
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }
    send_stored_file($file, 0, 0, true, ['filter' => false]);
}
