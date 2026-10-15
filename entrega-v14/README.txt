==============================================================
ENTREGA v14 — Saneamiento de rutas, menú unificado y Feriados
==============================================================

NO requiere migración nueva (la carpeta migrations queda vacía a
propósito). Asume aplicadas las migraciones 001–004.

ARCHIVOS Y DESTINOS (relativo a la raíz pública del proyecto):
--------------------------------------------------------------
NUEVOS:
  controllers/CourseController.php   -> app/controllers/
  controllers/ReportController.php   -> app/controllers/
  views/courses/index.php            -> app/views/courses/   (crear carpeta)
  views/courses/form.php             -> app/views/courses/
  views/reports/index.php            -> app/views/reports/   (crear carpeta)

REEMPLAZAR:
  controllers/AdminController.php    -> app/controllers/  (+ pestaña Feriados)
  models/Feriado.php                 -> app/models/       (+ update/allForAdmin)
  views/admin/index.php              -> app/views/admin/  (+ pestaña Feriados)
  views/layouts/sidebar.php          -> app/views/layouts/ (menú unificado)
  views/layouts/header.php           -> app/views/layouts/ (menú unificado)
  routes.php                         -> app/config/       (saneamiento)

QUÉ CAMBIA:
- Menú (sidebar y navbar idénticos): Inicio, Estudiantes, Asistencia,
  Mensajería, Reportes, Buscar, Administración (solo admin).
  Eliminados: Plantillas "pronto", Profesores "pronto", Usuarios y
  Gestión Escolar como entradas separadas.
- /users* y /management redirigen a /admin?tab=usuarios|gestion.
- /courses ahora funciona (CRUD real, CourseController + vistas).
- /reports ya no da error 500: placeholder con contenido previsto.
- Nueva pestaña "Feriados" en Administración: carga CSV
  (fecha,nombre,anio), plantilla descargable, listado con editar
  (modal) y eliminar. Bloquea duplicados por fecha.

VERIFICADO: php -l sin errores en todos los archivos.
==============================================================

=== ACTUALIZACIÓN v14.1 (corrección Registro de Eventos) ===
Archivo: registro-eventos/index.php  ->  sube a app/views/events/index.php (reemplaza)

Cambios en la rejilla semanal (pestaña masiva):
- Los días futuros ya NO muestran "P" de relleno: aparecen en blanco, salvo
  que exista una marca real registrada (p. ej. rango futuro cargado desde
  registro individual), en cuyo caso se muestra esa letra.
- Si un día editable aparece en blanco, el primer clic lo normaliza a "P"
  y luego continúa el ciclo P→A→L→F→P.
- Feriados: verificado, la interacción eventos↔feriados YA estaba implementada
  (columna bloqueada con tag "feriado", aviso y botón Guardar deshabilitado;
  también validación server-side en POST /attendance/save).
Sin cambios en BD ni rutas. Basta reemplazar el archivo de vista.
