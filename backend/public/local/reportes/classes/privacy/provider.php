<?php
namespace local_reportes\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;

final class provider implements \core_privacy\local\metadata\provider {
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_reports_export', [
            'userid' => 'privacy:metadata:exports:userid',
            'filename' => 'privacy:metadata:exports:filename',
            'timecreated' => 'privacy:metadata:exports:timecreated',
        ], 'privacy:metadata:exports');
        return $collection;
    }
}
