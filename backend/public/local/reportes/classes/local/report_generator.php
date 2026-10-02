<?php
namespace local_reportes\local;

defined('MOODLE_INTERNAL') || die();

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use stdClass;

final class report_generator {
    private const HEADERS = ['Curso', 'Shortname', 'ID del curso', 'Tipo', 'Nombre', 'Visible', 'Sección'];
    private const HEADERCOLOUR = '075F85';
    private const HEADERFILL = 'D9EDF7';

    /** @return stdClass[] */
    public function get_cohorts(): array {
        global $DB;
        return array_values($DB->get_records('cohort', null, 'name ASC', 'id,idnumber,name,visible'));
    }

    /** @return stdClass[] */
    private function get_courses(int $cohortid): array {
        global $DB;
        $sql = "SELECT DISTINCT c.id, c.fullname, c.shortname
                  FROM {course} c
                  JOIN {enrol} e ON e.courseid = c.id
                 WHERE e.enrol = :enrol
                   AND e.customint1 = :cohortid
              ORDER BY c.fullname ASC";
        return array_values($DB->get_records_sql($sql, ['enrol' => 'cohort', 'cohortid' => $cohortid]));
    }

    public function create_single_cohort_workbook(stdClass $cohort): Spreadsheet {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sheet_name($cohort, []));
        $this->write_cohort_sheet($sheet, $cohort);
        return $spreadsheet;
    }

    /**
     * Creates the complete workbook and reports progress after every cohort.
     *
     * @param callable(int,int,string):void $progresscallback
     * @return array{spreadsheet: Spreadsheet, summaries: array, warnings: array}
     */
    public function create_complete_workbook(array $cohorts, callable $progresscallback): array {
        $spreadsheet = new Spreadsheet();
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Resumen');
        $this->prepare_summary($summary);
        $usednames = ['Resumen'];
        $summaries = [];
        $warnings = [];
        $total = count($cohorts);

        foreach ($cohorts as $index => $cohort) {
            $progresscallback($index, $total, $cohort->name);
            $sheetname = $this->sheet_name($cohort, $usednames);
            $usednames[] = $sheetname;
            $sheet = new Worksheet($spreadsheet, $sheetname);
            $spreadsheet->addSheet($sheet);

            try {
                $result = $this->write_cohort_sheet($sheet, $cohort);
                $result['status'] = 'Completado';
                $result['observation'] = '';
            } catch (\Throwable $exception) {
                $result = ['courses' => 0, 'modules' => 0, 'status' => 'Con advertencia', 'observation' => $exception->getMessage()];
                $warnings[] = $cohort->name . ': ' . $exception->getMessage();
                $sheet->setCellValue('A1', 'No fue posible procesar esta cohorte.');
                $sheet->setCellValue('A2', $exception->getMessage());
                $sheet->getStyle('A1')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF842029'));
            }

            $result['cohort'] = $cohort;
            $result['sheetname'] = $sheetname;
            $summaries[] = $result;
            $this->append_summary($summary, count($summaries) + 1, $result);
            $progresscallback($index + 1, $total, $cohort->name);
        }

        $summary->setAutoFilter('A1:F' . (count($summaries) + 1));
        $summary->freezePane('A2');
        $spreadsheet->setActiveSheetIndex(0);
        return ['spreadsheet' => $spreadsheet, 'summaries' => $summaries, 'warnings' => $warnings];
    }

    /** @return array{courses:int,modules:int} */
    private function write_cohort_sheet(Worksheet $sheet, stdClass $cohort): array {
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $this->style_header($sheet, 'A1:G1');
        $row = 2;
        $modulecount = 0;
        $courses = $this->get_courses((int) $cohort->id);

        foreach ($courses as $course) {
            $modinfo = get_fast_modinfo((int) $course->id);
            foreach ($modinfo->get_cms() as $cm) {
                if (!$cm->uservisible && $cm->deletioninprogress) {
                    continue;
                }
                $section = $modinfo->get_section_info($cm->sectionnum);
                $sectionname = $section && trim((string) $section->name) !== ''
                    ? format_string($section->name, true, ['context' => \context_course::instance($course->id)])
                    : get_string('section') . ' ' . $cm->sectionnum;
                $values = [
                    format_string($course->fullname),
                    $course->shortname,
                    (string) $course->id,
                    get_string('modulename', 'mod_' . $cm->modname),
                    $cm->get_formatted_name(),
                    $cm->visible ? 'Sí' : 'No',
                    $sectionname,
                ];
                foreach ($values as $column => $value) {
                    $coordinate = Coordinate::stringFromColumnIndex($column + 1) . $row;
                    $sheet->setCellValueExplicit($coordinate, (string) $value, DataType::TYPE_STRING);
                }
                $row++;
                $modulecount++;
            }
        }

        $lastrow = max(1, $row - 1);
        $sheet->setAutoFilter("A1:G{$lastrow}");
        $sheet->freezePane('A2');
        $this->set_cohort_widths($sheet);
        return ['courses' => count($courses), 'modules' => $modulecount];
    }

    private function prepare_summary(Worksheet $sheet): void {
        $sheet->fromArray(['Cohorte', 'Código', 'Cursos', 'Elementos', 'Estado', 'Observaciones'], null, 'A1');
        $this->style_header($sheet, 'A1:F1');
        foreach (['A' => 34, 'B' => 20, 'C' => 12, 'D' => 14, 'E' => 20, 'F' => 55] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    private function append_summary(Worksheet $sheet, int $row, array $result): void {
        $cohort = $result['cohort'];
        $sheet->setCellValue("A{$row}", $cohort->name);
        $sheet->getCell("A{$row}")->getHyperlink()->setUrl("#'" . str_replace("'", "''", $result['sheetname']) . "'!A1");
        $sheet->setCellValueExplicit("B{$row}", (string) $cohort->idnumber, DataType::TYPE_STRING);
        $sheet->setCellValue("C{$row}", $result['courses']);
        $sheet->setCellValue("D{$row}", $result['modules']);
        $sheet->setCellValue("E{$row}", $result['status']);
        $sheet->setCellValue("F{$row}", $result['observation']);
        if ($result['status'] !== 'Completado') {
            $sheet->getStyle("E{$row}:F{$row}")->getFont()->getColor()->setARGB('FF842029');
        }
    }

    private function style_header(Worksheet $sheet, string $range): void {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FF' . self::HEADERCOLOUR]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::HEADERFILL]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);
    }

    private function set_cohort_widths(Worksheet $sheet): void {
        foreach (['A' => 38, 'B' => 22, 'C' => 14, 'D' => 22, 'E' => 48, 'F' => 12, 'G' => 30] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    private function sheet_name(stdClass $cohort, array $usednames): string {
        $base = preg_replace('/[\\\/\?\*\[\]:]+/u', '-', trim((string) $cohort->name));
        $base = trim($base, " '-");
        if ($base === '') {
            $base = 'Cohorte';
        }
        $suffix = '-' . $cohort->id;
        $candidate = \core_text::substr($base, 0, 31 - \core_text::strlen($suffix)) . $suffix;
        $counter = 2;
        while (in_array(\core_text::strtolower($candidate), array_map([\core_text::class, 'strtolower'], $usednames), true)) {
            $extrasuffix = '-' . $counter++;
            $candidate = \core_text::substr($base, 0, 31 - \core_text::strlen($extrasuffix)) . $extrasuffix;
        }
        return $candidate;
    }
}
