=============================================================
ENTREGA v13 · PÁGINA DE ADMINISTRACIÓN (4 pestañas)
Sistema de Registro de Eventos — Gestión Escolar
=============================================================

CONTENIDO
---------
administracion/
├── README.txt                        ← este archivo
├── routes.php                        → reemplaza app/config/routes.php
├── migrations/
│   └── 004_administracion.sql        → ejecutar en phpMyAdmin (1 vez, idempotente)
├── controllers/
│   └── AdminController.php           → app/controllers/AdminController.php   (NUEVO)
├── models/
│   └── Snapshot.php                  → app/models/Snapshot.php               (NUEVO)
├── views/
│   └── admin/index.php               → app/views/admin/index.php             (NUEVA carpeta "admin")
└── layouts/
    └── sidebar.php                   → app/views/layouts/sidebar.php         (reemplaza; agrega enlace "Administración")

FUNCIONALIDAD NUEVA
-------------------
Página /admin — visible SOLO para el rol administrador:

  Pestaña 1 · Usuarios y permisos
    - Crear usuario (con contraseña bcrypt) y cambiar roles.
    - Matriz editable de permisos por rol; crear roles/permisos nuevos.
    - Autoprotección: el admin no puede quitarse su propio rol.

  Pestaña 2 · Horarios y profesores
    - Carga masiva en texto plano tipo CSV: curso,profesor,dia,hora_inicio,hora_fin,asignatura,anio
      (nombres reales → convertidos a ids automáticamente).
    - Validación línea por línea (errores numerados + advertencias de choque horario).
    - Botón "Descargar plantilla CSV", listado por año, edición con lápiz (modal) y eliminación.

  Pestaña 3 · Gestión escolar ("cambio de ropa")
    - BACKUP: comprime todas las tablas de datos de la gestión actual en un snapshot (gzip+JSON).
    - MIGRAR AÑO: exige backup previo + confirmación escrita "MIGRAR AAAA".
      Promueve cursos (P1A→P2A … S5A→S6A), sube estudiantes al nuevo curso,
      los S6 quedan fuera del sistema activo (conservados en el backup),
      horarios y feriados arrancan limpios. Todo en una transacción.
    - RESTAURAR: recarga cualquier snapshot (p. ej. volver al 2026 puntualmente,
      haciendo antes un backup del 2027). Descargar/eliminar snapshots.

  Pestaña 4 · Diagnóstico
    - Entorno PHP/MySQL/timezone/gz, conteo de filas por tabla.
    - Integridad referencial (huérfanos, duplicados A/L/F mismo día, alumnos sin gestión).
    - Checklist post-migración verde/rojo.
    - Bloque de seguridad: contraseñas en texto plano, CSRF, HTTPS, cookies HttpOnly, nº de admins.

INSTALACIÓN EN HOSTING (paso a paso)
------------------------------------
1. HACER UN BACKUP MANUAL de la base de datos completa (phpMyAdmin → Exportar).
   Aunque el sistema tiene sus propios snapshots, este respaldo externo es tu red de seguridad.

2. EJECUTAR LA MIGRACIÓN:
   phpMyAdmin → pestaña SQL → pegar el contenido de migrations/004_administracion.sql → Continuar.
   Es idempotente: si algo ya existe, lo omite sin error. Puede re-ejecutarse.
   Verificaciones previas opcionales (solo si la migración avisara):
     - usuarios.id debe ser INT UNSIGNED (si es otro tipo, ajusta las claves foráneas de snapshots/admin_log).
   Al terminar debe existir: tablas `snapshots` y `admin_log`, columna
   `estudiante_estados.snapshot_json`, y el permiso `manage_admin` asignado al administrador.

3. SUBIR ARCHIVOS (carpeta pública = public_html/, dentro de ella app/):
   - Coloca controllers/AdminController.php   en  app/controllers/
   - Coloca models/Snapshot.php                en  app/models/
   - Crea la carpeta app/views/admin/ y coloca index.php dentro.
   - Reemplaza app/config/routes.php          por el routes.php incluido (v13).
   - Reemplaza app/views/layouts/sidebar.php  por el sidebar.php incluido (v13).

4. REQUISITOS PHP: zlib (gzencode/gzdecode) habilitado — casi todos los hosting lo traen.
   El Diagnóstico (pestaña 4) informa si falta.

5. PROBAR:
   - Ingresar como administrador → debe aparecer "Administración" en el sidebar.
   - Como profesor/regente → NO debe verse ni poder abrirse /admin.
   - Pestaña 2: descargar plantilla, pegar 2-3 líneas de ejemplo, guardar, editar con lápiz.
   - Pestaña 3: crear un BACKUP de la gestión actual (aparecerá en la lista).
     ⚠️ La MIGRACIÓN borra/promociona datos reales: pruébala primero en una BD de copia,
        o haz SIEMPRE el backup interno + export phpMyAdmin antes de ejecutarla.

NOTAS DE SEGURIDAD
------------------
- Todas las acciones POST requieren sesión activa + token CSRF + rol administrador
  (el candado fuerte está en cada método del controlador, no solo en las rutas).
- Cada acción relevante queda registrada en `admin_log` (quién, qué, cuándo, IP).
- Los snapshots se guardan comprimidos en la propia BD; puedes descargarlos como
  archivo .json.gz para conservarlos fuera del servidor.

SOPORTE / REVERSIÓN
-------------------
Para deshacer esta entrega: elimina AdminController.php, Snapshot.php, la carpeta
app/views/admin/, restaura routes.php y sidebar.php desde git/hosting, y deja la
BD como está (las tablas nuevas son inofensivas si nadie las usa).

Versión anterior: entrega-v12 (mensajería + buscar). Esta v13 la incluye todo
(v12 ya aplicado en tu servidor) más la administración. ¡Listo para subir!
