==============================================================
 ENTREGA v16.3 — CORRECCIONES FINALES DE REPORTES
==============================================================

PROBLEMAS RESUELTOS EN ESTA VERSION:

1) PDF SIN CONTENIDO (paginas en blanco con solo titulos)
   - printable.php: la funcion repGrid() ahora esta definida ANTES de su
     uso al inicio del archivo (las definiciones dentro de bloques if no
     se hoistean en PHP). Ademas exportPdf() renderiza todo el HTML
     estatico y guarda una copia cacheada; la impresion se dispara al
     enfocar la pestana (visibilitychange/focus + timer de respaldo).

2) REPORTE DE LAPSO — ERROR 500
   - buildRange(): validacion estricta de fechas ISO y manejo de rangos
     vacios sin reventar. Report::matrix() tolera claves string/array.

3) REPORTE ANUAL — "Allowed memory size exhausted" @ printable.php:213
   CAIZ RAIZ: marks() traia TODAS las filas de eventos del ano a PHP y
   cada rebuild (pantalla + CSV + PDF) las expandia en arrays gigantes.
   SOLUCION (v16.3):
   - Nuevo Report::marksAgg(): los totales A/L/F por estudiante y mes se
     agregan EN SQL con GROUP BY -> llegan ~12 filas por alumno, memoria
     O(nomina) en vez de O(eventos).
   - buildAnnual() reescrito sobre marksAgg (sin justificaciones ni
     fechas completas en memoria).
   - exportPdf() fija memory_limit=512M temporal para ese request.
   - BONUS corregido: roster($cid, $anio) ahora lista la nomina de la
     gestion consultada (antes siempre usaba la gestion activa, por lo
     que un reporte anual de un anio anterior salia vacio o errado).

4) WARNING "Undefined array key 0" (mensual linea 236 / lapso linea 292)
   - MES_ES/DIA_ES normalizados a indices 0-based coherentes en
     controller, vista index y printable.

5) GRAFICO ANUAL CON NUMEROS FANTASMA EN EL EJE X
   - Los datos van como listas JSON (array_values) de 12 elementos y las
     etiquetas son exactamente ['Ene'...'Dic']; Chart.js ya no interpreta
     claves numericas como categorias extra.

6) FUENTE DESNIVELADA EN TABLAS (nombres 7pt vs marcas 11pt)
   - app.css y printable.php: regla unica 9pt !important para th, td,
     small, .name y .sumcell en todas las rejillas (titulos intactos).

==============================================================
ARCHIVOS A COPIAR (reemplazar en tu hosting):
==============================================================

  entrega-v16/reports/ReportController.php
    -> public_html/app/controllers/ReportController.php

  entrega-v16/reports/Report.php
    -> public_html/app/models/Report.php

  entrega-v16/reports/index.php
    -> public_html/app/views/reports/index.php

  entrega-v16/reports/printable.php
    -> public_html/app/views/reports/printable.php

  entrega-v16/reports/app.css
    -> public_html/assets/css/app.css

NO hay migraciones SQL nuevas. NO hay cambios de rutas.

==============================================================
VALIDACION REALIZADA
==============================================================
- php -l sin errores en los 5 archivos (PHP 8.2).
- Simulacion del patron de memoria viejo vs nuevo y coherencia de
  claves 0..11 entre controller/vistas/graficos (script memtest).

PRUEBA SUGERIDA EN HOSTING:
1. Reportes > Anual > curso con muchos eventos > Generar (debe salir
   la tabla completa sin warning).
2. Mismo reporte > boton PDF > "Guardar como PDF" (debe salir la rejilla
   completa; opcional incluir graficos).
3. Lapso con rango amplio (ene-dic) > Generar y PDF.
4. Verificar fuente uniforme 9pt en nombres y marcas.
