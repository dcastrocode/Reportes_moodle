# Reportes de cohortes

Plugin local de Moodle para generar un único libro XLSX con una hoja de resumen y una hoja por cohorte.

## Instalación

1. Copiar esta carpeta a `local/reportes` dentro de Moodle.
2. Ejecutar la actualización de Moodle desde Administración del sitio.
3. Confirmar que el cron de Moodle se ejecuta al menos una vez por minuto.
4. Abrir `/local/reportes/index.php` con un usuario que posea `local/reportes:manage`.

Los archivos terminados permanecen disponibles durante 48 horas y se eliminan mediante una tarea programada diaria.
