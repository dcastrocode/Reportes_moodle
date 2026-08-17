import React, { useState, useEffect } from "react";
import axios from "axios";

const App = () => {
  const [cohorts, setCohorts] = useState([]);
  const [selectedCohort, setSelectedCohort] = useState("");
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    // Cargar las cohortes cuando se monta el componente.
    axios
      .get(`${window.REPORTS_API}?action=cohorts`)
      .then((response) => {
        setCohorts(response.data);
      })
      .catch((error) => {
        console.error("Error al obtener cohortes:", error);
      });
  }, []);

  const handleExport = () => {
    if (!selectedCohort) {
      alert("Por favor, selecciona una cohorte.");
      return;
    }

    setLoading(true);

    const exportUrl = `${window.REPORTS_API}?action=export&idnumber=${selectedCohort}&sesskey=${window.MOODLE_SESSKEY}`; // Usar los nuevos nombres de variables globales

    // Solicitar la exportación con GET
    axios
      .get(exportUrl, { responseType: "blob" })
      .then((response) => {
        // Crear un enlace de descarga para el archivo generado
        const blob = new Blob([response.data], {
          type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `reporte_cohorte_${selectedCohort}.xlsx`;
        document.body.appendChild(a);
        a.click();
        a.remove();
      })
      .catch((error) => {
        console.error("Error al generar el reporte:", error);
        alert(
          "Hubo un error al generar el reporte. Intenta nuevamente más tarde."
        );
      })
      .finally(() => {
        setLoading(false);
      });
  };

  return (
    <div>
      <h1>Generar Reporte de Cohorte</h1>
      <div>
        <select
          onChange={(e) => setSelectedCohort(e.target.value)}
          value={selectedCohort}
        >
          <option value="">Selecciona una cohorte</option>
          {cohorts.map((cohort) => (
            <option key={cohort.idnumber} value={cohort.idnumber}>
              {cohort.name} ({cohort.idnumber})
            </option>
          ))}
        </select>
      </div>
      <div>
        <button onClick={handleExport} disabled={loading}>
          {loading ? "Generando..." : "Generar Reporte"}
        </button>
      </div>
    </div>
  );
};

export default App;
