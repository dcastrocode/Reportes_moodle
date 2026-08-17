<?php
// Incluimos el archivo de configuración de Moodle.
require_once('../../../config.php');

// Incluimos la librería para exportar a Excel.
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/csvlib.class.php');
//require_once($CFG->libdir . '/phpspreadsheet/vendor/autoload.php');

// Ahora podemos usar las clases de PhpSpreadsheet.
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Aseguramos que el usuario esté logueado y tenga permisos de administrador.
require_login();
require_capability('moodle/site:config', context_system::instance());

// Verificamos el tipo de solicitud.
$action = optional_param('action', '', PARAM_TEXT);

// Acciones
if ($action === 'cohorts') {
    // Retorna las cohortes disponibles en formato JSON.
    $cohorts = $DB->get_records('cohort', null, '', 'id, idnumber, name');
    $result = [];
    foreach ($cohorts as $cohort) {
        $result[] = [
            'name' => $cohort->name,
            'idnumber' => $cohort->idnumber
        ];
    }
    echo json_encode($result);
    exit;
}

if ($action === 'export') {
    // Obtenemos el idnumber de la cohorte seleccionada.
    $autoload = $CFG->dirroot . '/vendor/autoload.php';
    $idnumber = required_param('idnumber', PARAM_TEXT);
    error_log('idnumber: ' . $idnumber);
    $cohort = $DB->get_record('cohort', ['idnumber' => $idnumber], '*', MUST_EXIST);

    // Obtenemos los cursos asociados a la cohorte.
    $cohortid = $cohort->id;
    $courses = $DB->get_records_sql(
        "SELECT c.id AS courseid, c.fullname, c.shortname
         FROM {course} c
         JOIN {enrol} e ON e.courseid = c.id
         JOIN {user_enrolments} ue ON ue.enrolid = e.id
         WHERE e.enrol = 'cohort' AND e.customint1 = :cohortid",
        ['cohortid' => $cohortid]
    );


    // Creamos el archivo Excel.
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'Cohorte');
    $sheet->setCellValue('B1', 'Curso');
    $sheet->setCellValue('C1', 'Shortname');
    $sheet->setCellValue('D1', 'ID del curso');
    $sheet->setCellValue('E1', 'Tipo');
    $sheet->setCellValue('F1', 'Nombre');
    $sheet->setCellValue('G1', 'Visible');
    $sheet->setCellValue('H1', 'Sección');

    $row = 2;

   foreach ($courses as $course) {
    $course_modules = $DB->get_records('course_modules', ['course' => $course->courseid]);
    foreach ($course_modules as $module) {
        // Obtener el nombre del módulo usando el ID
        $modinfo = $DB->get_record('modules', ['id' => $module->module]);
        $modulename = $modinfo->name; // Ejemplo: "assign", "forum", etc.

        // Consultar el módulo en la tabla correcta
        $module_instance = $DB->get_record($modulename, ['id' => $module->instance]);

        // Verificamos si el módulo está visible.
        $visible = $module->visible ? 'Sí' : 'No';
        $section = $DB->get_field('course_sections', 'name', ['id' => $module->section]);

        // Escribimos en el archivo Excel.
        $sheet->setCellValue('A' . $row, $cohort->name);
        $sheet->setCellValue('B' . $row, $course->fullname);
        $sheet->setCellValue('C' . $row, $course->shortname);
        $sheet->setCellValue('D' . $row, $course->courseid);
        $sheet->setCellValue('E' . $row, get_string('modulename', 'mod_' . $modulename));
        $sheet->setCellValue('F' . $row, $module_instance->name);
        $sheet->setCellValue('G' . $row, $visible);
        $sheet->setCellValue('H' . $row, $section);

        $row++;
        }
    }

    // Configuramos el encabezado para la descarga del archivo.
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="reporte_cohorte_' . $cohort->idnumber . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
