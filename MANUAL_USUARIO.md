# MANUAL DE USUARIO — SISTEMA REGENTE
### Plataforma de gestión de asistencia, eventos y bitácora · Colegio Marista (Bolivia)
**Versión del documento:** v17 · **Fecha:** 21 de octubre de 2026

---

## ÍNDICE

1. [Visión general](#1-visión-general)
2. [Instalación](#2-instalación)
3. [Uso diario por rol](#3-uso-diario-por-rol)
4. [Guía módulo por módulo](#4-guía-módulo-por-módulo)
5. [Mantenimiento](#5-mantenimiento)
6. [Respaldo y restauración](#6-respaldo-y-restauración)
7. [Solución de problemas frecuentes](#7-solución-de-problemas-frecuentes)
8. [Anexos](#8-anexos)

---

## 1. VISIÓN GENERAL

**Sistema Regente** es una aplicación web (PHP + MySQL) para el registro y seguimiento de
asistencia, atrasos, licencias y faltas de estudiantes, mensajería a acudientes, reportes
imprimibles y bitácora del profesorado.

### 1.1 Roles del sistema

| Rol | Quién es | Qué puede hacer |
|---|---|---|
| **Administrador** | Soporte / dirección TI | Todo: usuarios, horarios, feriados, gestión escolar, diagnóstico, borrado de datos. |
| **Regente** | Regencia académica | Asistencia completa, mensajería, reportes, **Bitácora del profesorado**, dashboard. |
| **Profesor / Prefecto** | Docentes y prefectos | Registrar asistencia de sus cursos, ver sus estudiantes, mensajería limitada. **No ve Bitácora ni Administración.** |

> Nota: «educador» = «profesor» en este manual; ambos se refieren al usuario con rol `profesor`.

### 1.2 Módulos principales (menú lateral / superior)

- **Inicio (Dashboard)** — reloj, calendario con feriados, accesos rápidos, gráficos y rankings.
- **Estudiantes** — alta, edición, cambio de estado y ficha individual.
- **Asistencia** — registro diario de eventos (llegada tardía, licencia, falta…).
- **Mensajería** — plantillas y envío (WhatsApp / correo) a acudientes.
- **Reportes** — reportes por curso/periodo e impresión/PDF.
- **Buscar** — búsqueda predictiva global (estudiantes, cursos).
- **Bitácora** *(solo regente/administrador)* — registro de actividades y novedades del profesorado.
- **Administración** *(solo administrador)* — 5 pestañas: Usuarios, Horarios, Feriados, Gestión, Diagnóstico.

### 1.3 Estructura de carpetas del proyecto

```
public_html/               ← raíz pública del sitio (DocumentRoot)
├── index.php              ← front controller (único punto de entrada)
├── Router.php             ← motor de rutas
├── diagnostico.php        ← chequeo rápido de entorno (quitar en producción)
├── app/
│   ├── App.php            ← arranque de la aplicación
│   ├── config/            ← routes.php, database.php, security.php, recaptcha.php
│   ├── controllers/       ← un controlador por módulo
│   ├── models/            ← Student, Event, Message, Bitacora, Report, …
│   ├── middleware/        ← AuthMiddleware (sesión), etc.
│   ├── helpers/           ← permissions.php (can(), isRegente(), …)
│   └── views/             ← plantillas PHP por módulo + layouts/
├── assets/                ← CSS/JS/imagen
├── logs/                  ← error.log (¡revisarlo primero ante cualquier falla!)
└── uploads/               ← archivos subidos (exportaciones cacheadas, recibos)

database/migrations/       ← scripts SQL 001…005 (ejecutarlos en orden)
```

---

## 2. INSTALACIÓN

### 2.1 Requisitos mínimos

| Componente | Versión recomendada | Verificación |
|---|---|---|
| PHP | **8.1 – 8.3** | `php -v` |
| Extensiones PHP | `pdo_mysql`, `mbstring`, `curl`, `json`, `fileinfo`, `openssl` | `php -m` |
| MySQL / MariaDB | MySQL 5.7+ / MariaDB 10.3+ | — |
| Servidor web | Apache (con `mod_rewrite`) o Nginx | — |
| HTTPS | **Obligatorio en producción** (login + reCAPTCHA) | — |
| Memoria / disco | 128 MB PHP; ~500 MB libres para logs/exportaciones | — |

### 2.2 Instalación paso a paso (hosting cPanel típico)

1. **Subir el código.** Copia el contenido de `public_html/` al directorio público del
   dominio (p. ej. `dominiodelcolegio.edu.bo`). Nada debe quedar fuera del DocumentRoot
   salvo que puedas configurar vhosts propios.

2. **Crear la base de datos** en cPanel → *MySQL Databases*:
   - BD: `rege_colmarista2` (o la que uses)
   - Usuario con contraseña fuerte y **todos los privilegios** sobre esa BD.

3. **Configurar credenciales.** Edita `app/config/database.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'rege_colmarista2');
   define('DB_USER', 'rege_colmarista2');
   define('DB_PASS', 'TU-CONTRASENA');   // cámbiala, no dejes la de desarrollo
   ```

4. **Importar esquema + migraciones** (phpMyAdmin → pestaña *SQL*, ejecutar en orden):
   ```
   database/test_schema.sql                 ← tablas base
   database/migrations/001_estados_estudiantes.sql
   database/migrations/002_feriados.sql
   database/migrations/003_mensajes_enviados.sql
   database/migrations/004_administracion.sql
   database/migrations/005_bitacora.sql     ← módulo Bitácora (v17)
   ```
   Todas las migraciones son **idempotentes**: pueden ejecutarse dos veces sin error.

5. **Permisos de escritura** sobre:
   ```
   logs/    uploads/
   ```
   (cPanel → Administrador de archivos → permisos `755`; dueño = usuario del servidor web).

6. **Usuario inicial.** Si la BD quedó vacía de usuarios, crea el administrador vía SQL:
   ```sql
   INSERT INTO roles (name) VALUES ('administrador'),('regente'),('profesor');
   -- genera el hash en una consola PHP:  php -r "echo password_hash('ClaveSegura2026', PASSWORD_DEFAULT);"
   INSERT INTO usuarios (username, password, role_id, nombre_completo)
   VALUES ('admin', '<hash-generado>', 1, 'Administrador del Sistema');
   ```

7. **Primer arranque.** Abre `https://tudominio/login`, inicia sesión y ejecuta de inmediato
   **Administración → Diagnóstico** (ver §5.4). Todos los apartados deben estar verdes.

8. **Higiene de producción.** Elimina o restringe por IP estos archivos de desarrollo:
   `diagnostico.php`, `test-students.php`, `/tools/*`, y la carpeta `entrega-v*` si fue copiada.

### 2.3 Configuración opcional

- **reCAPTCHA** (`app/config/recaptcha.php`): registra el dominio en Google reCAPTCHA v2 y
  coloca `SITE_KEY` / `SECRET_KEY`. Sin esto el login funciona pero sin protección anti-bot.
- **Zona horaria**: el sistema usa `America/La_Paz`; ajústala en `app/config/security.php`
  si cambia.

---

## 3. USO DIARIO POR ROL

### 3.1 Flujo del profesor/prefecto (día típico)

1. Login → **Dashboard**: revisa reloj, calendario del mes (feriados en rojo) y accesos rápidos.
2. Botón **REGISTRAR ASISTENCIA** (o menú *Asistencia*): selecciona curso/hora del día,
   marca presentes y registra eventos (**Atraso**, **Licencia**, **Falta**) por estudiante.
3. Para avisar a un acudiente: **Mensajería** → elegir estudiante(s) → plantilla → enviar.
4. Cerrar sesión (⏻) al terminar; nunca dejar sesión abierta en computadores compartidos.

### 3.2 Flujo del regente

Todo lo del profesor, además de:
- **Reportes**: parciales, semanales y anuales; exportar a PDF con el botón *Imprimir/PDF*.
- **Bitácora**: registrar actividades/novedades del profesorado (§4.7).
- Supervisión de rankings y gráficos del dashboard para detección temprana de problemas.

### 3.3 Flujo del administrador

Todo lo anterior, más **Administración** (§4.8): altas de usuarios y asignación de roles,
carga de horarios CSV, feriados, gestión de datos masivos y diagnóstico del sistema.

---

## 4. GUÍA MÓDULO POR MÓDULO

### 4.1 Inicio / Dashboard (v17)

Cinco filas:

1. **Reloj digital HH:MM:SS** (¾ del ancho, segundos en vivo) + **calendario mensual propio**
   (¼): la semana empieza en **lunes**, el día actual aparece resaltado en azul y los
   **feriados de Bolivia** en rojo (nombre al pasar el mouse). Incluye lista de feriados del mes.
   No requiere internet ni API de Google.
2. **Cuatro accesos rápidos**: Registrar asistencia · Mensajería · Reportes · Bitácora.
3. **Tres gráficos de líneas** (cuadrante I): Atrasos, Licencias y Faltas comparando
   Primaria (línea continua) vs Secundaria (línea discontinua); eje X compartido P1A↔S1A … P6C↔S6C;
   tooltips con la cantidad exacta.
4. **Dos gráficos de barras agrupadas**: totales acumulados por curso
   (Atraso/Licencia/Falta) — uno para P1A…P6C y otro para S1A…S6C.
5. **Rankings de eventos**: 6 columnas con el top-20 de estudiantes por tipo de evento,
   con su curso entre paréntesis (Primaria ×3, Secundaria ×3).

### 4.2 Estudiantes

- **Alta**: Estudiantes → *Nuevo*. Campos clave: nombre completo, curso (P/S + grado + paralelo),
  CI o código, datos del acudiente (nombre, teléfono/celular, correo).
- **Estado**: cada estudiante tiene estado activo/inactivo/retirado (migración 001).
  Cambiar estado NO borra historial.
- **Ficha individual** (`/students/{id}`): foto, datos, historial completo de eventos y mensajes.

### 4.3 Asistencia (Eventos)

- Registro diario por curso-hora según el **horario** cargado en Administración.
- Tipos de evento: **Llegada tardía (atraso)**, **Licencia**, **Falta** y otros definidos en el sistema.
- El calendario académico y los feriados **bloquean** el registro de asistencia en días no laborables
  (a diferencia de Bitácora, que no está restringida).
- Correcciones: busca al estudiante, edita el evento; los cambios quedan auditados en el log.

### 4.4 Mensajería

- Plantillas predefinidas por tipo de evento; variables como `{nombre}`, `{curso}`, `{fecha}`.
- Destinatarios: acudientes de los estudiantes seleccionados; envío individual o masivo.
- Cada envío queda registrado (tabla `mensajes_enviados`, migración 003) con fecha y estado.

### 4.5 Reportes

- Filtros por curso, periodo y tipo de evento.
- Vista en pantalla + botón **Imprimir/PDF**: abre una página imprimible optimizada
  (`printable.php`) — usa `Ctrl+P` del navegador → *Guardar como PDF*.
- Los reportes salen de los mismos datos del dashboard, por lo que siempre cuadran entre sí.

### 4.6 Buscar

- Búsqueda predictiva global (API `/api/suggest`): escribe 2+ letras y sugiere estudiantes y cursos.
- El resultado lleva directo a la ficha del estudiante o al listado filtrado.

### 4.7 Bitácora del profesorado *(regente / administrador)*

Registro de **actividades y novedades de los profesores** (un profesor = usuario con rol `profesor`).

- **Alta**: Bitácora → *Nuevo registro*. Selecciona al profesor, tipo
  (actividad / novedad / reunión / capacitación / otro), título y detalle.
- **Fecha**: es **opcional**; si no la indicas, el sistema asume **hoy**. La tabla `bitacora`
  guarda siempre el campo `fecha` (DATE). Este módulo **no** se rige por el calendario
  académico ni feriados (es administrativo, no estudiantil).
- **Búsqueda**: un solo cuadro de texto libre busca **por nombre del profesor Y por detalle del
  evento** simultáneamente.
- **Edición/borrado**: solo regente/administrador; los permisos `view/create/edit/delete_bitacora`
  ya vienen otorgados por la migración 005.
- **Exportar PDF**: botón *Exportar* genera un HTML imprimible (cacheado en `uploads/`, protegido
  con token `_k`) que el navegador convierte a PDF con `Ctrl+P`.

### 4.8 Administración *(solo administrador)* — 5 pestañas

| Pestaña | Contenido |
|---|---|
| **Usuarios y permisos** | Alta/edición/baja de usuarios, cambio de rol, reset de contraseña, permisos finos. |
| **Horarios y profesores** | Carga CSV del horario anual (curso-día-hora-profesor), edición por año. Base del módulo Asistencia. |
| **Feriados** | Alta/baja de feriados oficiales y pedagógicos (afectan bloqueo de asistencia y el calendario del dashboard). |
| **Gestión escolar** | Utilidades masivas: snapshots/recibos descargables, limpieza selectiva con doble confirmación. |
| **Diagnóstico** | Estado de salud del sistema: páginas/rutas, errores del log, archivos críticos, seguridad e integridad de datos. |

---

## 5. MANTENIMIENTO

### 5.1 Rutinas recomendadas

| Frecuencia | Tarea |
|---|---|
| **Diaria** | Nada obligatorio; si algo falló, mirar *Diagnóstico → Errores registrados*. |
| **Semanal** | Ejecutar Diagnóstico completo (verde = OK). Revisar tamaño de `logs/error.log`. |
| **Mensual** | Respaldo full de BD + `uploads/`; actualizar dependencias del servidor (PHP/MySQL parches de seguridad). |
| **Por versión nueva** | Aplicar las nuevas migraciones `database/migrations/*.sql` en orden. |
| **Anual** | Cargar horario del año nuevo; revisar feriados del año; depurar registros antiguos. |

### 5.2 Actualizar el sistema a una nueva versión

1. Respaldar BD y carpeta `uploads/` (§6).
2. Subir **solo los archivos cambiados** (sobrescribir); mantener intactos
   `app/config/database.php`, `logs/` y `uploads/`.
3. Ejecutar las migraciones nuevas que acompañen a la versión.
4. Abrir **Administración → Diagnóstico** y confirmar que todo esté verde.
5. Probar el flujo crítico: login → asistencia → reporte → bitácora.

### 5.3 Logs

- Ubicación: `public_html/logs/error.log`. Rotarlo si supera ~50 MB:
  ```bash
  mv error.log error-$(date +%Y%m%d).log && touch error.log
  ```
- Errores nuevos aparecen automáticamente en *Diagnóstico → Errores registrados*
  (últimas entradas y conteo de errores/warnings recientes).

### 5.4 Cómo leer la pestaña Diagnóstico

Encabezado: **banner de salud global** (verde OK / amarillo advertencias / rojo problemas)
+ fecha/hora de la verificación. Debajo, tarjetas:

| Sección | Qué revisa | Semáforo |
|---|---|---|
| Páginas y rutas | Las 15 vistas existen, sus rutas están registradas y responden HTTP 200/302 vía loopback; detecta vistas huérfanas | 🔴 = página cae (500) o ruta inexistente |
| Errores registrados | Últimas 15 líneas críticas de `error.log` | 🔴 = errores recientes |
| Archivos críticos | Front controller, configs, modelos/vistas de cada módulo, permisos de escritura de `logs/` y `uploads/` | 🟡 = sin permiso de escritura |
| Entorno / Tablas | Versión PHP, extensiones, conexión BD, conteo de filas por tabla (incl. `bitacora`) | 🔴 = BD inaccesible |
| Seguridad | HTTPS, tokens CSRF, exposición de archivos sensibles | 🟡 = recomendaciones |
| Integridad de datos | Huellas digitales de los archivos del código (detección de ediciones manuales/daño) | 🔴 = archivo modificado inesperadamente |

> Si una sección aparece vacía, actualiza `AdminController.php` y `views/admin/index.php`
> juntos (las correcciones de v17 incluyen respaldo automático cuando faltan datos).

### 5.5 Rendimiento

- Habilitar **opcache** de PHP en producción (`opcache.enable=1`, `validate_timestamps=0`
  salvo durante despliegues).
- Índices: el esquema ya trae índices sobre `id_estudiante`, `fecha`, `curso`; verificar con
  phpMyAdmin → *Estructura* si se agregan filtros nuevos.
- Caché de PDFs imprimibles en `uploads/`: purgar trimestralmente (son regenerables).

---

## 6. RESPALDO Y RESTAURACIÓN

### 6.1 Respaldo

**Vía cPanel:** *Backups → Base de datos MySQL* + descargar `uploads/` y `logs/`.

**Vía SSH (recomendado, agregar a cron semanal):**
```bash
#!/bin/bash
# backup-regente.sh — respalda BD + archivos; retiene 8 semanas
FECHA=$(date +%F)
DEST=/respaldos/regente
mkdir -p "$DEST"
mysqldump -u rege_colmarista2 -p'PASSWORD' --single-transaction --routines \
    rege_colmarista2 | gzip > "$DEST/db-$FECHA.sql.gz"
tar czf "$DEST/files-$FECHA.tar.gz" public_html/uploads public_html/logs
find "$DEST" -mtime +56 -delete          # retención 8 semanas
```

### 6.2 Restauración

```bash
gunzip -c db-2026-10-21.sql.gz | mysql -u USUARIO -p rege_colmarista2
tar xzf files-2026-10-21.tar.gz -C /ruta/del/sitio
```
Luego: login → Diagnóstico → probar un reporte. **Practica la restauración al menos 1 vez
por semestre** en un ambiente aparte.

### 6.3 Qué respaldar SIEMPRE antes de tocar nada

Base de datos completa · `app/config/` (credenciales/recaptcha) · `uploads/` · usuarios del sistema.

---

## 7. SOLUCIÓN DE PROBLEMAS FRECUENTES

| Síntoma | Causa probable | Solución |
|---|---|---|
| **«Error interno del servidor»** al abrir un módulo | Vista o método faltante (típico tras subir unos archivos y no otros) | Abrir `logs/error.log`: la primera línea dice archivo y línea exactos. Subir **todos** los archivos de la versión juntos. |
| Menú no muestra «Bitácora» | Sesión con rol sin permiso | Verificar en BD: `SELECT u.username,r.name FROM usuarios u JOIN roles r ON u.role_id=r.id;` y que la migración 005 se ejecutó. |
| Página 404 en `/bitacora` u otra ruta nueva | `routes.php` desactualizado o `mod_rewrite` apagado | Subir el `routes.php` nuevo; en Apache asegurar `AllowOverride All` y `.htaccess` presente. |
| Diagnóstico muestra secciones vacías | Mezcla de versiones de `AdminController.php` + vista | Subir ambos archivos de la v17 y limpiar opcache (reiniciar PHP-FPM o `opcache_reset()`). |
| `Class "App\Controllers\RecursiveIteratorIterator" not found` | Clase global referenciada sin `\` en código personalizado | Anteponer `\` a clases globales (`\RecursiveIteratorIterator`, `\RegexIterator`). Corregido en v17. |
| Calendario del dashboard vacío o gris | Era el iframe de Google Calendar (bloqueado/offline) | Desde v17 el calendario es local (PHP puro): no necesita internet. Si aún ves el iframe, tu `index.php` es antiguo — reemplázalo completo. |
| Login redirige pero vuelve al login | Cookies de sesión bloqueadas o `session.save_path` sin permiso | Probar otra ventana/incógnito; verificar espacio y permisos de la carpeta de sesiones del servidor. |
| Exportar PDF descarga una página en blanco | Token `_k` expirado o carpeta `uploads/` sin escritura | Comprobar permiso 755 de `uploads/`; regenerar el PDF desde el botón (la caché se rehace sola). |
| Asistencia no deja registrar un día | Es feriado o día fuera del calendario académico | Normal: la asistencia sí se restringe. *Bitácora* no tiene esa restricción a propósito. |
| Cambios en PHP «no se ven» | Caché opcache | Reiniciar el servicio PHP o pedir a hosting flush de opcache; en desarrollo usar `validate_timestamps=1`. |

**Regla de oro:** ante cualquier 500 → 1° `logs/error.log`, 2° pestaña *Diagnóstico*,
3° comparar que todos los archivos de la última versión estén subidos.

---

## 8. ANEXOS

### 8.1 Rutas principales del sistema

| Ruta | Módulo | Acceso |
|---|---|---|
| `/` , `/login` , `/logout` | Autenticación | Público |
| `/dashboard` | Panel principal | Autenticado |
| `/students` (+ create/store/show/edit/update/delete) | Estudiantes | Autenticado con permiso |
| `/events` | Asistencia | Profesor+, con permiso |
| `/messages` | Mensajería | Con permiso |
| `/reports` | Reportes | Con permiso |
| `/search` , `/api/suggest` | Búsqueda predictiva | Autenticado |
| `/bitacora` (+ store/update/delete/export/pdf) | Bitácora docente | **Regente/Administrador** |
| `/admin?tab=usuarios\|horarios\|feriados\|gestion\|diagnostico` | Administración | Administrador |

### 8.2 Tablas de base de datos

`usuarios`, `roles`, `permissions`, `role_permissions`, `estudiantes`, `eventos`,
`estados_estudiantes` (mig. 001), `feriados` (002), `mensajes_enviados` (003),
`horarios` y gestión administrativa (004), **`bitacora`** (005) — campos:
`id_profesor`, `tipo`, `titulo`, `detalle`, `fecha` (default = hoy si se omite), timestamps.

### 8.3 Feriados de Bolivia incluidos en el calendario del dashboard

**Fijos:** 1/1 Año Nuevo · 22/3 Día del Agua · 1/5 Día del Trabajo · 26/5 Madre Boliviana ·
6/8 Independencia · 14/8 Refundación · 7/10 Autonomías · 2/11 Todos los Santos ·
6/11 Cultura Indígena · 21/12 Solsticio Andino · 25/12 Navidad.
**Móviles (calculados por algoritmo de Pascua):** lunes y martes de Carnaval, Viernes Santo,
Corpus Christi. Los feriados *oficiales adicionales del año* se gestionan en
Administración → Feriados.

### 8.4 Glosario

- **Regente** — autoridad académica que supervisa asistencia y profesorado (rol `regente`).
- **Bitácora** — registro administrativo de actividades/novedades del profesorado.
- **Snapshot** — corte/copia puntual de datos para resguardo (Gestión escolar).
- **P1A…P6C / S1A…S6C** — cursos de Primaria y Secundaria (grado y paralelo).
- **Evento** — cada incidencia registrada a un estudiante (atraso, licencia, falta).

---

*Documento generado para el Sistema Regente v17. Cualquier corrección o duda de instalación,
acudir primero al apartado 7 (Solución de problemas) y adjuntar el contenido de
`logs/error.log` al equipo de soporte.*
