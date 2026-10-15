ENTREGA V12 - Pagina "Buscar" (y mensajeria de apoyo)
=====================================================

ARCHIVOS Y SU DESTINO EN EL SERVIDOR (raiz del proyecto):

1. SearchController.php        -> public_html/app/controllers/SearchController.php  (NUEVO)
2. Horario.php                 -> public_html/app/models/Horario.php                (ACTUALIZADO)
3. views/search/index.php      -> public_html/app/views/search/index.php            (NUEVO, crear carpeta "search")
4. routes.php                  -> public_html/app/config/routes.php                 (ACTUALIZADO: rutas /search y /buscar)
5. layouts/sidebar.php         -> public_html/app/views/layouts/sidebar.php         (ACTUALIZADO: enlace "Buscar")
6. layouts/header.php          -> public_html/app/views/layouts/header.php          (ACTUALIZADO: enlace "Buscar")
7. MessageController.php       -> public_html/app/controllers/MessageController.php (por si falta v11)
8. layouts/app.php, footer.php -> sin cambios relevantes; solo respaldo de layout
9. 003_mensajes_enviados.sql   -> database/migrations/  EJECUTAR en phpMyAdmin (crea tabla horarios + mensajes_enviados)

ORDEN SUGERIDO:
 a) Ejecutar la migracion 003 en la base de datos.
 b) Subir los archivos PHP segun la ruta indicada.
 c) Poblar la tabla "horarios" (curso, dia L-V, hora_inicio, hora_fin, asignatura, profesor_id, anio).
 d) Visitar /search o /buscar.

Nota: hasta poblar "horarios", la pagina funciona pero muestra aviso de horarios vacios.
