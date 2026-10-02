<?php
namespace local_reportes\local;

defined('MOODLE_INTERNAL') || die();

final class export_service {
    public static function empty_status(): array {
        return [
            'id' => 0, 'status' => 'none', 'progress' => 0, 'totalcohorts' => 0,
            'processedcohorts' => 0, 'currentcohort' => '', 'filename' => '',
            'warningcount' => 0, 'message' => '', 'downloadurl' => '',
            'timecreated' => 0, 'expiresat' => 0,
        ];
    }

    public static function format_status(?\stdClass $export): array {
        if (!$export) {
            return self::empty_status();
        }
        $downloadurl = '';
        if ($export->filename && in_array($export->status, ['completed', 'completed_with_warnings'], true)) {
            $downloadurl = \moodle_url::make_pluginfile_url(
                \context_system::instance()->id,
                'local_reportes',
                'exports',
                $export->id,
                '/',
                $export->filename,
                true
            )->out(false);
        }
        return [
            'id' => (int) $export->id,
            'status' => $export->status,
            'progress' => (int) $export->progress,
            'totalcohorts' => (int) $export->totalcohorts,
            'processedcohorts' => (int) $export->processedcohorts,
            'currentcohort' => (string) ($export->currentcohort ?? ''),
            'filename' => (string) ($export->filename ?? ''),
            'warningcount' => (int) $export->warningcount,
            'message' => self::public_message($export),
            'downloadurl' => $downloadurl,
            'timecreated' => (int) $export->timecreated,
            'expiresat' => (int) ($export->expiresat ?? 0),
        ];
    }

    public static function status_structure(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'id' => new \core_external\external_value(PARAM_INT, 'Export id'),
            'status' => new \core_external\external_value(PARAM_ALPHANUMEXT, 'Export status'),
            'progress' => new \core_external\external_value(PARAM_INT, 'Progress percentage'),
            'totalcohorts' => new \core_external\external_value(PARAM_INT, 'Total cohorts'),
            'processedcohorts' => new \core_external\external_value(PARAM_INT, 'Processed cohorts'),
            'currentcohort' => new \core_external\external_value(PARAM_TEXT, 'Current cohort'),
            'filename' => new \core_external\external_value(PARAM_FILE, 'Generated filename'),
            'warningcount' => new \core_external\external_value(PARAM_INT, 'Warning count'),
            'message' => new \core_external\external_value(PARAM_TEXT, 'Public status message'),
            'downloadurl' => new \core_external\external_value(PARAM_URL, 'Download URL'),
            'timecreated' => new \core_external\external_value(PARAM_INT, 'Creation timestamp'),
            'expiresat' => new \core_external\external_value(PARAM_INT, 'Expiry timestamp'),
        ]);
    }

    private static function public_message(\stdClass $export): string {
        switch ($export->status) {
            case 'pending': return 'La exportación está en cola.';
            case 'running': return $export->currentcohort ? 'Procesando ' . $export->currentcohort . '.' : 'Generando el libro de Excel.';
            case 'completed': return 'El reporte se generó correctamente.';
            case 'completed_with_warnings': return 'El reporte terminó con ' . (int) $export->warningcount . ' advertencia(s).';
            case 'failed': return 'No fue posible completar la exportación. Consulte al administrador del servidor.';
            case 'expired': return 'El archivo venció. Puede generar uno nuevo.';
            default: return '';
        }
    }
}
