<?php
/**
 * v17 · Dashboard rediseñado — 5 filas (cada fila = ancho completo):
 *  1) Reloj hh:mm:ss en vivo (3/4) + calendario mensual del mes en curso con feriados de Bolivia (1/4)
 *  2) Cuatro botones grandes de acceso rapido
 *  3) Tres graficos de lineas: Atrasos / Licencias / Faltas
 *     (Primaria = linea continua, Secundaria = linea discontinua;
 *      P1A comparte marca con S1A en el eje X ... P6C/S6C; tooltips)
 *  4) Dos graficos de barras acumuladas por curso (Primaria | Secundaria)
 *  5) Rankings de eventos: 6 subcolumnas top-20
 * Variables: $stats, $dash, $recentEvents, $recentMessages, $user
 */
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

// ===== Feriados nacionales de Bolivia (generados para el anio en curso) =====
$bolivianHolidays = function (int $y): array {
    $h = [
        '01-01' => 'Año Nuevo',
        '05-01' => 'Día del Trabajo',
        '08-06' => 'Independencia de Bolivia',
        '12-25' => 'Navidad',
    ];
    // Pascua (algoritmo de Meeus/Jones/Butcher) -> festivos moviles
    $a = $y % 19; $b = intdiv($y, 100); $c = $y % 100; $d = intdiv($b, 4); $e = $b % 4;
    $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3); $w = (19*$a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2*$e + 2*$i - $w - $k) % 7;
    $m = intdiv($w + $k - $l + 114, 31); $n = ($w + $k - $l + 114) % 31;
    $easter = DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $y, $m, $n + 1));
    $ashWed      = $easter->modify('-46 days');
    $h[$ashWed->modify('-2 days')->format('m-d')] = 'Carnaval (Lunes)';
    $h[$ashWed->modify('-1 days')->format('m-d')] = 'Carnaval (Martes)';
    $h[$easter->modify('-2 days')->format('m-d')] = 'Viernes Santo';
    $h[$easter->modify('+60 days')->format('m-d')] = 'Corpus Christi';
    // Efemerides fijas
    $h['03-22'] = 'Día del Agua y Vuelta al Federalismo';
    $h['05-26'] = 'Gesta de la Madre Boliviana';
    $h['08-14'] = 'Refundación de Bolivia';
    $h['10-07'] = 'Día de las Autonomías';
    $h['11-02'] = 'Todos los Santos';
    $h['11-06'] = 'Cultura Indígena Originaria';
    $h['12-21'] = 'Solsticio Andino – Año Nuevo Aymara';
    ksort($h);
    return $h; // clave 'mm-dd' => nombre
};
$yearNow    = (int)date('Y');
$holidaysMd = $bolivianHolidays($yearNow); // mm-dd => nombre
$todayMd    = date('m-d');
$todayName  = $holidaysMd[$todayMd] ?? null;
ob_start();
?>

<div class="section pt-4 pb-2">
    <div class="container is-fluid">

        <h1 class="title is-4 mb-4">
            <i class="fas fa-tachometer-alt"></i>&nbsp; Panel de Control
            <span class="tag is-light ml-2"><?= $h($stats['active_management'] ?? date('Y')) ?></span>
        </h1>

        <!-- ================= FILA 1: reloj + calendario ================= -->
        <div class="columns is-variable is-4 dash-row">
            <div class="column is-three-quarters-desktop is-full-touch">
                <div class="dash-clock-card">
                    <div id="dashClock" class="dash-clock">--:--:--</div>
                    <div id="dashDate" class="dash-date">&mdash;</div>
                </div>
            </div>
            <div class="column is-one-quarter-desktop is-full-touch">
                <?php
                // ===== Calendario mensual del mes en curso (empieza en lunes) =====
                $year  = (int)date('Y');
                $month = (int)date('n');
                $dayToday = (int)date('j');
                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                // Primer dia del mes -> conversion dom(0)..sab(6) a lun(0)..dom(6)
                $dowFirst = (int)date('w', mktime(0, 0, 0, $month, 1, $year));
                $leadBlanks = ($dowFirst + 6) % 7;
                $MESES_ES = ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
                ?>
                <div class="dash-cal-card">
                    <p class="dash-cal-title">
                        <i class="far fa-calendar-alt"></i>&nbsp;
                        <?= $h(ucfirst($MESES_ES[$month])) . ' ' . $year ?>
                    </p>
                    <table class="dash-month-cal">
                        <thead>
                            <tr><th>L</th><th>M</th><th>X</th><th>J</th><th>V</th><th>S</th><th>D</th></tr>
                        </thead>
                        <tbody>
                        <?php
                        $cell = 0;
                        for ($week = 0; $week < 6; $week++) {
                            echo '<tr>';
                            $weekHasDay = false;
                            for ($wd = 0; $wd < 7; $wd++) {
                                $day = $cell - $leadBlanks + 1;
                                if ($day < 1 || $day > $daysInMonth) {
                                    echo '<td class="dash-cal-blank"></td>';
                                } else {
                                    $weekHasDay = true;
                                    $md = sprintf('%02d-%02d', $month, $day);
                                    $isToday    = ($day === $dayToday);
                                    $isHoliday  = isset($holidaysMd[$md]);
                                    $cls = 'dash-cal-day';
                                    if ($isToday)   $cls .= ' is-today';
                                    if ($isHoliday) $cls .= ' is-holiday';
                                    $title = $isHoliday ? $h($holidaysMd[$md]) : '';
                                    printf('<td class="%s"%s><span>%d</span></td>', $cls, $title !== '' ? ' title="' . $title . '"' : '', $day);
                                }
                                $cell++;
                            }
                            echo '</tr>';
                            if (!$weekHasDay) break; // no imprimir fila totalmente vacia al final
                        }
                        ?>
                        </tbody>
                    </table>
                    <div class="dash-cal-legend">
                        <span><i class="dash-leg-sq dash-leg-today"></i> Hoy</span>
                        <span><i class="dash-leg-sq dash-leg-hol"></i> Feriado</span>
                    </div>
                    <?php if ($todayName): ?>
                        <p class="dash-cal-today-name"><i class="fas fa-umbrella-beach"></i>&nbsp; <?= $h($todayName) ?></p>
                    <?php endif; ?>
                    <ul class="dash-cal-holidays">
                        <?php foreach ($holidaysMd as $md => $name):
                            if ((int)substr($md, 0, 2) !== $month) continue; ?>
                            <li><strong><?= (int)substr($md, 3, 2) ?></strong> · <?= $h($name) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- ================= FILA 2: accesos rapidos ================= -->
        <div class="columns is-mobile is-variable is-4 dash-row">
            <div class="column">
                <a class="dash-quick q-atraso" href="/attendance">
                    <span class="icon is-large"><i class="fas fa-clipboard-check fa-2x"></i></span>
                    <span class="dash-quick-label">REGISTRAR<br>ASISTENCIA</span>
                </a>
            </div>
            <div class="column">
                <a class="dash-quick q-mensaje" href="/messages">
                    <span class="icon is-large"><i class="fas fa-envelope-open-text fa-2x"></i></span>
                    <span class="dash-quick-label">MENSAJER&Iacute;A</span>
                </a>
            </div>
            <div class="column">
                <a class="dash-quick q-reporte" href="/reports">
                    <span class="icon is-large"><i class="fas fa-chart-line fa-2x"></i></span>
                    <span class="dash-quick-label">REPORTES</span>
                </a>
            </div>
            <div class="column">
                <a class="dash-quick q-bitacora" href="/bitacora">
                    <span class="icon is-large"><i class="fas fa-chalkboard-teacher fa-2x"></i></span>
                    <span class="dash-quick-label">BIT&Aacute;CORA</span>
                </a>
            </div>
        </div>

        <?php if (isAdmin() || isRegente()): ?>
        <!-- ================= FILA 3: graficos de lineas A / L / F ================= -->
        <div class="columns is-variable is-4 dash-row">
            <?php
            $lineCharts = [
                ['id' => 'chartAtrasos',   'key' => 'A', 'title' => 'Atrasos',   'color' => '#f59e0b'],
                ['id' => 'chartLicencias', 'key' => 'L', 'title' => 'Licencias', 'color' => '#0ea5e9'],
                ['id' => 'chartFaltas',    'key' => 'F', 'title' => 'Faltas',    'color' => '#e11d48'],
            ];
            foreach ($lineCharts as $lc): ?>
            <div class="column is-one-third-desktop is-full-touch">
                <div class="dash-chart-card">
                    <p class="dash-chart-title">
                        <span class="dash-dot" style="background:<?= $lc['color'] ?>"></span>
                        <?= $lc['title'] ?> &mdash; Primaria vs Secundaria
                    </p>
                    <div class="dash-chart-body"><canvas id="<?= $lc['id'] ?>"></canvas></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ================= FILA 4: barras acumuladas por curso ================= -->
        <div class="columns is-variable is-4 dash-row">
            <div class="column is-half-desktop is-full-touch">
                <div class="dash-chart-card">
                    <p class="dash-chart-title"><i class="fas fa-school"></i>&nbsp; Total acumulado por curso &mdash; Primaria</p>
                    <div class="dash-chart-body"><canvas id="chartBarsP"></canvas></div>
                </div>
            </div>
            <div class="column is-half-desktop is-full-touch">
                <div class="dash-chart-card">
                    <p class="dash-chart-title"><i class="fas fa-building"></i>&nbsp; Total acumulado por curso &mdash; Secundaria</p>
                    <div class="dash-chart-body"><canvas id="chartBarsS"></canvas></div>
                </div>
            </div>
        </div>

        <!-- ================= FILA 5: rankings de eventos ================= -->
        <div class="dash-card dash-rankings mb-5">
            <p class="dash-section-title"><i class="fas fa-ranking-star"></i>&nbsp; Rankings de eventos
                <span class="has-text-grey is-size-7">(top 20 &middot; gesti&oacute;n activa)</span>
            </p>
            <div class="columns is-variable is-2 is-multiline">
                <?php
                $rankCols = [];
                foreach (['Primaria', 'Secundaria'] as $nivel) {
                    foreach ([['atraso', 'Atrasos', '#f59e0b'], ['licencia', 'Licencias', '#0ea5e9'], ['falta', 'Faltas', '#e11d48']] as $rc2) {
                        $rankCols[] = ['nivel' => $nivel, 'tipo' => $rc2[0], 'titulo' => $rc2[1], 'color' => $rc2[2]];
                    }
                }
                foreach ($rankCols as $rc):
                    $list = $dash['rank'][$rc['nivel']][$rc['tipo']] ?? []; ?>
                <div class="column is-4-tablet is-4-desktop is-6-touch">
                    <div class="dash-rank-col">
                        <p class="dash-rank-head" style="border-color:<?= $rc['color'] ?>">
                            <?= $rc['titulo'] ?> &middot; <?= $h($rc['nivel']) ?>
                        </p>
                        <ol class="dash-rank-list">
                            <?php if (empty($list)): ?>
                                <li class="dash-rank-empty">Sin registros</li>
                            <?php else: foreach ($list as $i => $r): ?>
                                <li>
                                    <span class="dash-rank-n"><?= $i + 1 ?></span>
                                    <span class="dash-rank-name"><?= $h($r['nombre']) ?> <em>(<?= $h($r['curso']) ?>)</em></span>
                                    <span class="dash-rank-val" style="background:<?= $rc['color'] ?>1a;color:<?= $rc['color'] ?>"><?= (int)$r['n'] ?></span>
                                </li>
                            <?php endforeach; endif; ?>
                        </ol>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<style>
/* ===== v17 Dashboard ===== */
.dash-row { margin-bottom: 1rem; }
.dash-clock-card, .dash-cal-card, .dash-chart-card, .dash-card {
    background: #fff; border-radius: 16px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, .08);
    border: 1px solid #eef2f7;
}
.dash-clock-card { display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:400px;
    background: linear-gradient(135deg,#0f172a 0%,#1e3a8a 60%,#2563eb 100%); color:#fff; border:none; }
.dash-clock { font-family:'Segoe UI', system-ui, monospace; font-size: clamp(3rem, 9vw, 7rem);
    font-weight: 800; letter-spacing:.06em; text-shadow: 0 4px 18px rgba(37,99,235,.55);
    font-variant-numeric: tabular-nums; }
.dash-date { margin-top:.5rem; font-size:1.1rem; opacity:.85; }
.dash-cal-card { padding:.75rem .9rem; height:100%; display:flex; flex-direction:column; }
.dash-cal-title { font-weight:700; color:#334155; margin-bottom:.5rem; }
/* ===== Calendario mensual propio (lunes..domingo) ===== */
.dash-month-cal { width:100%; border-collapse:separate; border-spacing:3px; table-layout:fixed; }
.dash-month-cal th { font-size:.72rem; color:#94a3b8; font-weight:700; padding:.15rem 0; text-align:center; }
.dash-month-cal td { text-align:center; vertical-align:middle; padding:0; }
.dash-cal-day span {
    display:inline-flex; align-items:center; justify-content:center;
    width:2rem; height:2rem; border-radius:50%; font-size:.8rem; color:#334155;
    font-variant-numeric:tabular-nums;
}
.dash-cal-day.is-holiday span { background:#fee2e2; color:#dc2626; font-weight:700; }
.dash-cal-day.is-today span {
    background: linear-gradient(135deg,#2563eb,#1e3a8a); color:#fff; font-weight:800;
    box-shadow:0 3px 10px rgba(37,99,235,.45);
}
.dash-cal-blank { background:transparent; }
.dash-cal-legend { display:flex; gap:1rem; margin-top:.55rem; font-size:.72rem; color:#64748b; }
.dash-leg-sq { display:inline-block; width:.7rem; height:.7rem; border-radius:3px; margin-right:.25rem; vertical-align:-1px; }
.dash-leg-today { background:#2563eb; }
.dash-leg-hol { background:#fecaca; border:1px solid #dc2626; }
.dash-cal-today-name { margin-top:.45rem; font-size:.78rem; font-weight:700; color:#dc2626; }
.dash-cal-holidays { list-style:none; margin:.5rem 0 0; padding:.5rem 0 0; border-top:1px dashed #e2e8f0;
    max-height:110px; overflow-y:auto; font-size:.74rem; color:#475569; }
.dash-cal-holidays li { padding:.12rem 0; }
.dash-cal-holidays strong { color:#dc2626; }
@media (max-width: 768px) { .dash-cal-day span { width:1.7rem; height:1.7rem; font-size:.72rem; } }
.dash-quick { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.6rem;
    min-height:130px; border-radius:16px; color:#fff !important; font-weight:800; letter-spacing:.04em;
    text-align:center; box-shadow:0 8px 20px rgba(15,23,42,.18); transition:transform .15s ease, box-shadow .15s ease; }
.dash-quick:hover { transform: translateY(-4px) scale(1.02); box-shadow:0 14px 28px rgba(15,23,42,.28); }
.q-atraso   { background: linear-gradient(135deg,#f59e0b,#d97706); }
.q-mensaje  { background: linear-gradient(135deg,#0ea5e9,#0284c7); }
.q-reporte  { background: linear-gradient(135deg,#10b981,#059669); }
.q-bitacora { background: linear-gradient(135deg,#8b5cf6,#6d28d9); }
.dash-quick-label { font-size: 1rem; line-height:1.2; }
.dash-chart-card { padding: 1rem 1rem .75rem; }
.dash-chart-title { font-weight:700; color:#334155; margin-bottom:.5rem; font-size:.95rem; }
.dash-chart-body { position:relative; height: 300px; }
.dash-dot { display:inline-block; width:.7rem; height:.7rem; border-radius:50%; margin-right:.35rem; }
.dash-card { padding: 1.25rem; }
.dash-section-title { font-weight:800; color:#1e293b; font-size:1.1rem; margin-bottom:.75rem; }
.dash-rank-col { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:.6rem .7rem; height:100%; }
.dash-rank-head { font-weight:800; color:#334155; border-bottom:3px solid; padding-bottom:.35rem; margin-bottom:.4rem; font-size:.9rem; }
.dash-rank-list { list-style:none; margin:0; padding:0; max-height:460px; overflow-y:auto; }
.dash-rank-list li { display:flex; align-items:center; gap:.4rem; padding:.22rem 0; border-bottom:1px dashed #e2e8f0; font-size:.82rem; }
.dash-rank-n { color:#94a3b8; width:1.6rem; text-align:right; flex-shrink:0; font-variant-numeric:tabular-nums; }
.dash-rank-name { flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:#1e293b; }
.dash-rank-name em { color:#94a3b8; font-style:normal; }
.dash-rank-val { flex-shrink:0; font-weight:800; border-radius:999px; padding:.05rem .5rem; font-variant-numeric:tabular-nums; }
.dash-rank-empty { color:#94a3b8; font-style:italic; }
@media (max-width: 768px) { .dash-clock-card { min-height:220px; } .dash-chart-body { height:240px; } }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    // ---------- Fila 1: reloj hh:mm:ss en vivo (segundos avanzando) ----------
    var clock = document.getElementById('dashClock');
    var dateEl = document.getElementById('dashDate');
    var DIAS = ['Domingo','Lunes','Martes','Mi\u00e9rcoles','Jueves','Viernes','S\u00e1bado'];
    var MESES = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    function pad(n) { return String(n).padStart(2, '0'); }
    function tick() {
        var d = new Date();
        if (clock) clock.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
        if (dateEl) dateEl.textContent = DIAS[d.getDay()] + ', ' + d.getDate() + ' de ' + MESES[d.getMonth()] + ' de ' + d.getFullYear();
    }
    tick(); setInterval(tick, 1000);

    <?php if (isAdmin() || isRegente()): ?>
    // ---------- Datos inyectados desde PHP ----------
    var DASH = <?= json_encode([
        'labels' => $dash['chartLabels'],
        'P' => ['A' => $dash['primaria']['A'] ?? [], 'L' => $dash['primaria']['L'] ?? [], 'F' => $dash['primaria']['F'] ?? []],
        'S' => ['A' => $dash['secundaria']['A'] ?? [], 'L' => $dash['secundaria']['L'] ?? [], 'F' => $dash['secundaria']['F'] ?? []],
        'barsP' => $dash['barsPrimaria'],
        'barsS' => $dash['barsSecundaria'],
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    if (typeof Chart === 'undefined') return; // CDN no carg\u00f3: no romper la p\u00e1gina

    Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
    Chart.defaults.color = '#64748b';

    var COLORS = { A: '#f59e0b', L: '#0ea5e9', F: '#e11d48' };
    var TITLES = { A: 'Atrasos', L: 'Licencias', F: 'Faltas' };

    // ---------- Fila 3: lineas (continua = Primaria, discontinua = Secundaria) ----------
    Object.keys(COLORS).forEach(function (k) {
        var el = document.getElementById('chart' + ({A:'Atrasos',L:'Licencias',F:'Faltas'}[k]));
        if (!el) return;
        new Chart(el, {
            type: 'line',
            data: {
                labels: DASH.labels,
                datasets: [
                    { label: 'Primaria', data: DASH.P[k], borderColor: COLORS[k], backgroundColor: COLORS[k] + '22',
                      tension: .35, fill: true, pointRadius: 4, pointHoverRadius: 7, borderWidth: 3 },
                    { label: 'Secundaria', data: DASH.S[k], borderColor: COLORS[k], backgroundColor: 'transparent',
                      borderDash: [8, 5], tension: .35, fill: false, pointRadius: 4, pointHoverRadius: 7,
                      pointStyle: 'rectRot', borderWidth: 3 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { beginAtZero: true, min: 0, grid: { color: '#eef2f7' }, title: { display: true, text: 'Cursos' } },
                    y: { beginAtZero: true, min: 0, ticks: { precision: 0 }, grid: { color: '#eef2f7' },
                         title: { display: true, text: TITLES[k] + ' (eventos)' } }
                },
                plugins: {
                    legend: { labels: { usePointStyle: true, boxWidth: 8 } },
                    tooltip: {
                        backgroundColor: 'rgba(15,23,42,.92)', padding: 12, cornerRadius: 10,
                        callbacks: {
                            label: function (c) { return ' ' + c.dataset.label + ': ' + c.parsed.y + ' ' + TITLES[k].toLowerCase(); }
                        }
                    }
                },
                animation: { duration: 900, easing: 'easeOutQuart' }
            }
        });
    });

    // ---------- Fila 4: barras agrupadas por curso ----------
    function bars(id, block) {
        var el = document.getElementById(id);
        if (!el || !block) return;
        new Chart(el, {
            type: 'bar',
            data: {
                labels: block.labels,
                datasets: [
                    { label: 'Atrasos',   data: block.A, backgroundColor: COLORS.A, borderRadius: 6, maxBarThickness: 26 },
                    { label: 'Licencias', data: block.L, backgroundColor: COLORS.L, borderRadius: 6, maxBarThickness: 26 },
                    { label: 'Faltas',    data: block.F, backgroundColor: COLORS.F, borderRadius: 6, maxBarThickness: 26 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, min: 0, ticks: { precision: 0 }, grid: { color: '#eef2f7' },
                         title: { display: true, text: 'Eventos acumulados' } }
                },
                plugins: {
                    legend: { labels: { usePointStyle: true, boxWidth: 8 } },
                    tooltip: { backgroundColor: 'rgba(15,23,42,.92)', padding: 12, cornerRadius: 10 }
                },
                animation: { duration: 900, easing: 'easeOutQuart' }
            }
        });
    }
    bars('chartBarsP', DASH.barsP);
    bars('chartBarsS', DASH.barsS);
    <?php endif; ?>
})();
</script>

<?php
$content = ob_get_clean();
require_once APP_ROOT . '/app/views/layouts/app.php';
?>
