# Reportes de cohortes

Interfaz React y plugin local de Moodle para exportar la estructura de las cohortes.

## Funcionalidad

- Exportación individual inmediata.
- Exportación completa en segundo plano mediante una tarea ad hoc.
- Un único libro XLSX con hoja de resumen y una hoja por cohorte.
- Progreso persistente y recuperación del estado al recargar la página.
- Descarga protegida mediante la File API de Moodle.
- Bloqueo de exportaciones completas simultáneas.
- Eliminación automática de archivos después de 48 horas.

## Desarrollo

```shell
npm run dev
npm run lint
npm run build
```

La compilación se genera directamente en:

```text
backend/public/local/reportes/dist
```

## Instalación en Moodle

Copie `backend/public/local/reportes` en `local/reportes` dentro de Moodle y ejecute la actualización de plugins. El cron de Moodle debe ejecutarse al menos una vez por minuto para procesar la cola.

También se genera un paquete instalable en `release/local_reportes-1.0.0.zip`.
