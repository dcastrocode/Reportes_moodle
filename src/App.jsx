import { useEffect, useMemo, useState } from "react";
import axios from "axios";

const previewCohorts = Array.from({ length: 30 }, (_, index) => ({
  id: index + 1,
  name: `Cohorte ${String(index + 1).padStart(2, "0")}`,
  idnumber: `COH-${String(index + 1).padStart(2, "0")}`,
}));

const emptyExport = { id: 0, status: "none", progress: 0, totalcohorts: 0, processedcohorts: 0, currentcohort: "", filename: "", warningcount: 0, message: "", downloadurl: "" };

async function callMoodle(methodname, args = {}) {
  const runtime = window.REPORTS_BOOTSTRAP;
  if (!runtime) throw new Error("La integración con Moodle no está disponible en esta vista previa.");
  const response = await axios.post(`${runtime.ajaxUrl}?sesskey=${encodeURIComponent(runtime.sesskey)}&info=${encodeURIComponent(methodname)}`, [{ index: 0, methodname, args }], { headers: { "Content-Type": "application/json" } });
  const result = response.data?.[0];
  if (result?.error) throw new Error(result.exception?.message || "Moodle rechazó la operación.");
  return result?.data;
}

const icons = {
  layers: <><path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 17l9 5 9-5"/></>,
  file: <><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></>,
  sheets: <><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8M8 15h3M15 15h1"/></>,
  clock: <><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></>,
  download: <><path d="M12 3v12M7 10l5 5 5-5"/><path d="M5 21h14"/></>,
  server: <><rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01M7 17h.01"/></>,
  check: <path d="m5 12 4 4L19 6"/>,
  info: <><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></>,
};

function Icon({ name, size = 24 }) {
  return <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{icons[name]}</svg>;
}

export default function App() {
  const [cohorts, setCohorts] = useState([]);
  const [selectedCohort, setSelectedCohort] = useState("");
  const [loading, setLoading] = useState(false);
  const [catalogueLoading, setCatalogueLoading] = useState(true);
  const [notice, setNotice] = useState(null);
  const [error, setError] = useState(null);
  const [exportJob, setExportJob] = useState(emptyExport);
  const [startingExport, setStartingExport] = useState(false);
  const isMoodle = Boolean(window.REPORTS_BOOTSTRAP);

  useEffect(() => {
    if (!isMoodle) {
      setCohorts(previewCohorts);
      setCatalogueLoading(false);
      return;
    }
    callMoodle("local_reportes_get_catalogue")
      .then((data) => { setCohorts(data.cohorts || []); setExportJob(data.export || emptyExport); })
      .catch(() => setError("No fue posible cargar el catálogo de cohortes."))
      .finally(() => setCatalogueLoading(false));
  }, [isMoodle]);

  useEffect(() => {
    if (!isMoodle || !exportJob.id || !["pending", "running"].includes(exportJob.status)) return undefined;
    const timer = window.setInterval(() => {
      callMoodle("local_reportes_get_export_status", { exportid: exportJob.id })
        .then((status) => setExportJob(status))
        .catch(() => setError("No fue posible actualizar el estado de la exportación."));
    }, 4000);
    return () => window.clearInterval(timer);
  }, [exportJob.id, exportJob.status, isMoodle]);

  const selectedName = useMemo(
    () => cohorts.find((cohort) => String(cohort.id) === selectedCohort)?.name,
    [cohorts, selectedCohort],
  );

  const handleExport = async () => {
    if (!selectedCohort) {
      setError("Seleccione una cohorte antes de generar el reporte individual.");
      return;
    }
    setLoading(true);
    setError(null);
    setNotice(null);
    try {
      if (!isMoodle) throw new Error("Vista previa");
      const runtime = window.REPORTS_BOOTSTRAP;
      const exportUrl = `${runtime.individualExportUrl}?cohortid=${encodeURIComponent(selectedCohort)}&sesskey=${encodeURIComponent(runtime.sesskey)}`;
      const response = await axios.get(exportUrl, { responseType: "blob" });
      const blob = new Blob([response.data], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
      const url = window.URL.createObjectURL(blob);
      const anchor = document.createElement("a");
      anchor.href = url;
      anchor.download = `reporte_cohorte_${selectedCohort}.xlsx`;
      document.body.appendChild(anchor);
      anchor.click();
      anchor.remove();
      window.URL.revokeObjectURL(url);
      setNotice(`El reporte de ${selectedName || selectedCohort} se generó correctamente.`);
    } catch {
      setError(isMoodle ? "No fue posible generar el reporte. Intente nuevamente más tarde." : "La descarga individual requiere ejecutar la interfaz dentro de Moodle.");
    } finally {
      setLoading(false);
    }
  };

  const startCompleteExport = async () => {
    setError(null);
    setNotice(null);
    if (!isMoodle) {
      setNotice("Vista previa: dentro de Moodle este botón agenda la exportación en segundo plano.");
      return;
    }
    setStartingExport(true);
    try {
      const status = await callMoodle("local_reportes_start_export");
      setExportJob(status);
      setNotice("La exportación se agregó a la cola. Puede cerrar esta página sin interrumpirla.");
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : "No fue posible iniciar la exportación.");
    } finally {
      setStartingExport(false);
    }
  };

  const activeExport = ["pending", "running"].includes(exportJob.status);
  const completedExport = ["completed", "completed_with_warnings"].includes(exportJob.status);
  const statusTitle = ({ pending: "Exportación en cola", running: "Generando el libro", completed: "Reporte disponible", completed_with_warnings: "Reporte disponible", failed: "Exportación fallida", expired: "Archivo vencido" })[exportJob.status] || "Sin procesos activos";
  const statusMessage = exportJob.message || (activeExport ? "Preparando el proceso…" : "Listo para iniciar");

  return (
    <main className="reports-theme">
      <div className="reports-shell container-fluid px-3 px-lg-4 py-4">
        {error && <div className="alert alert-danger alert-dismissible fade show rounded-3" role="alert">{error}<button type="button" className="btn-close" aria-label="Cerrar" onClick={() => setError(null)} /></div>}
        {notice && <div className="alert alert-success alert-dismissible fade show rounded-3" role="status">{notice}<button type="button" className="btn-close" aria-label="Cerrar" onClick={() => setNotice(null)} /></div>}

        <section className="reports-hero rounded-4 mb-4">
          <div className="reports-hero__content">
            <div>
              <p className="reports-eyebrow text-uppercase fw-semibold mb-2">Administración académica</p>
              <h1 className="fw-bold mb-2">Reportes de cohortes</h1>
              <p className="reports-hero__description mb-0">Consolide la estructura académica en archivos Excel claros, organizados y listos para revisión.</p>
            </div>
            <button className="btn reports-hero__button" type="button" onClick={completedExport ? () => window.location.assign(exportJob.downloadurl) : startCompleteExport} disabled={activeExport || startingExport}><Icon name="download" size={19} /> {completedExport ? "Descargar reporte completo" : activeExport ? "Exportación en curso" : "Generar reporte completo"}</button>
          </div>
        </section>

        <section className="reports-stats mb-4" aria-label="Resumen del reporte">
          <SummaryCard accent="blue" icon="layers" label="Cohortes disponibles" value={catalogueLoading ? "—" : cohorts.length} />
          <SummaryCard accent="purple" icon="file" label="Archivo de salida" value="1 XLSX" />
          <SummaryCard accent="green" icon="sheets" label="Organización" value={`${catalogueLoading ? "—" : cohorts.length + 1} hojas`} />
          <SummaryCard accent="orange" icon="clock" label="Procesamiento" value="En segundo plano" compact />
        </section>

        <div className="row g-4">
          <div className="col-12 col-xl-8">
            <section className="reports-panel card border-0 rounded-4 shadow-sm h-100">
              <div className="card-body p-4 p-lg-5">
                <div className="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
                  <div><span className="reports-section-icon reports-section-icon--green mb-3"><Icon name="sheets" /></span><h2 className="h4 fw-bold mb-2">Reporte general de cohortes</h2><p className="text-muted mb-0">Un único libro de Excel con una hoja de resumen y una hoja independiente por cada cohorte.</p></div>
                  <span className="reports-badge">Recomendado</span>
                </div>

                <div className="reports-workbook mb-4">
                  <div className="reports-workbook__file"><Icon name="file" size={30} /><div><strong>reporte_cohortes.xlsx</strong><small>Libro consolidado</small></div></div>
                  <div className="reports-workbook__sheets"><span>Resumen</span><span>Cohorte 01</span><span>Cohorte 02</span><span>…</span><span>Cohorte {String(Math.max(cohorts.length, 30)).padStart(2, "0")}</span></div>
                </div>

                <div className="reports-process mb-4">
                  <ProcessStep number="1" title="Se agenda la solicitud" description="Puede cerrar esta página sin interrumpir el proceso." />
                  <ProcessStep number="2" title="Moodle genera el libro" description="Las cohortes se procesan secuencialmente." />
                  <ProcessStep number="3" title="El archivo queda disponible" description="La descarga aparecerá aquí cuando finalice." />
                </div>

                <div className="reports-action-box">
                  <div><strong>Todo está preparado para comenzar</strong><p className="small text-muted mb-0">La generación puede tardar algunos minutos según el número de cursos y elementos.</p></div>
                  {completedExport ? <a className="btn reports-primary-button" href={exportJob.downloadurl}><Icon name="download" size={19} /> Descargar Excel</a> : <button className="btn reports-primary-button" type="button" onClick={startCompleteExport} disabled={activeExport || startingExport}><Icon name="server" size={19} /> {startingExport ? "Agendando…" : activeExport ? "Procesando…" : "Iniciar exportación"}</button>}
                </div>
              </div>
            </section>
          </div>

          <div className="col-12 col-xl-4">
            <section className="reports-panel card border-0 rounded-4 shadow-sm mb-4"><div className="card-body p-4">
              <div className="d-flex align-items-center justify-content-between gap-3 mb-3"><div><p className="small text-muted mb-1">Estado de exportación</p><h2 className="h5 fw-bold mb-0">{statusTitle}</h2></div><span className={`reports-status-dot reports-status-dot--${exportJob.status}`} aria-hidden="true" /></div>
              <div className="progress reports-progress mb-2" role="progressbar" aria-label="Progreso" aria-valuenow={exportJob.progress} aria-valuemin="0" aria-valuemax="100"><div className={`progress-bar${activeExport ? " progress-bar-striped progress-bar-animated" : ""}`} style={{ width: `${exportJob.progress}%` }} /></div>
              <div className="d-flex justify-content-between gap-3 small text-muted"><span>{statusMessage}</span><span>{exportJob.progress} %</span></div>
              {activeExport && exportJob.totalcohorts > 0 && <div className="small text-muted mt-2">{exportJob.processedcohorts} de {exportJob.totalcohorts} cohortes procesadas</div>}
              {exportJob.status === "completed_with_warnings" && <div className="alert alert-warning small mt-3 mb-0">El archivo se generó con {exportJob.warningcount} advertencia(s).</div>}
            </div></section>

            <section className="reports-panel card border-0 rounded-4 shadow-sm"><div className="card-body p-4">
              <span className="reports-section-icon reports-section-icon--blue"><Icon name="info" /></span><h2 className="h5 fw-bold mt-3 mb-2">Contenido del archivo</h2>
              <ul className="reports-checklist mb-0"><li><Icon name="check" size={17} /> Hoja general de resumen</li><li><Icon name="check" size={17} /> Una hoja por cohorte</li><li><Icon name="check" size={17} /> Cursos, actividades y secciones</li><li><Icon name="check" size={17} /> Estado de visibilidad</li></ul>
            </div></section>
          </div>
        </div>

        <section className="reports-panel card border-0 rounded-4 shadow-sm mt-4"><div className="card-body p-4 p-lg-5"><div className="row g-4 align-items-end">
          <div className="col-12 col-lg-8">
            <span className="reports-label">Reporte individual</span><h2 className="h4 fw-bold mt-2 mb-2">¿Solo necesita una cohorte?</h2><p className="text-muted mb-4">Genere inmediatamente el archivo de una cohorte específica con la funcionalidad actual.</p>
            <label className="form-label fw-semibold" htmlFor="cohort-select">Seleccione la cohorte</label>
            <select id="cohort-select" className="form-select form-select-lg" value={selectedCohort} onChange={(event) => setSelectedCohort(event.target.value)} disabled={catalogueLoading || loading}><option value="">Seleccione una cohorte…</option>{cohorts.map((cohort) => <option key={cohort.id} value={cohort.id}>{cohort.name} ({cohort.idnumber || cohort.id})</option>)}</select>
          </div>
          <div className="col-12 col-lg-4 d-grid"><button className="btn reports-secondary-button btn-lg" type="button" onClick={handleExport} disabled={!selectedCohort || loading}>{loading ? <><span className="spinner-border spinner-border-sm" aria-hidden="true" /> Generando…</> : <><Icon name="download" size={19} /> Descargar reporte individual</>}</button></div>
        </div></div></section>
      </div>
    </main>
  );
}

function SummaryCard({ accent, icon, label, value, compact = false }) {
  return <article className={`reports-summary reports-summary--${accent}`}><div><p className="small text-muted mb-1">{label}</p><strong className={compact ? "reports-summary__compact" : "reports-summary__value"}>{value}</strong></div><span className="reports-summary__icon"><Icon name={icon} /></span></article>;
}

function ProcessStep({ number, title, description }) {
  return <div className="reports-process__step"><span>{number}</span><div><strong>{title}</strong><p className="small text-muted mb-0">{description}</p></div></div>;
}
