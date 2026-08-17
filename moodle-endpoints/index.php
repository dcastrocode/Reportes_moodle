<?php
// Incluimos el archivo de configuración de Moodle.
require_once('../../../config.php');
defined('MOODLE_INTERNAL') || die();

// Aseguramos que el usuario esté logueado y tenga permisos de administrador.
require_login();
require_capability('moodle/site:config', context_system::instance());

// Establecemos el contexto de la página.
$PAGE->set_context(context_system::instance());

// Establecemos el título de la página.
$PAGE->set_title('Reportes de Cohorte');
$PAGE->set_heading('Reportes de Cohorte');
$PAGE->set_url(new moodle_url('/aplicacion_externa/reportes/index.php'));

// Imprimimos el encabezado de Moodle.
echo $OUTPUT->header();

// ───── Config de página para la SPA (XSS‑safe) ─────
$sesskey   = sesskey();
$reports_api = (new moodle_url('/aplicacion_externa/reportes/reports.php'))->out(false);
$wwwroot   = s($CFG->wwwroot); // escapar para usar en atributo src
?>

<script>
  window.MOODLE_SESSKEY = <?php echo json_encode($sesskey); ?>;
  window.REPORTS_API     = <?php echo json_encode($reports_api); ?>;
  window.WWWROOT         = <?php echo json_encode($wwwroot); ?>;
</script>

<div id="root"></div>

<script src="<?php echo $wwwroot; ?>/aplicacion_externa/reportes/dist/app_3.js"></script>

<?php
// Imprimimos el pie de página de Moodle.
echo $OUTPUT->footer();
?>
