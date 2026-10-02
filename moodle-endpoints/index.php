<?php
require_once('../../../config.php');

require_login();
require_capability('local/reportes:manage', context_system::instance());
redirect(new moodle_url('/local/reportes/index.php'));
