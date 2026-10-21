<?php
/**
 * Hoja de mensajería (WhatsApp) — dos pestañas:
 *   1. Enviar mensaje : composición con botones de formato WhatsApp y
 *      placeholders {{...}} + vista previa en vivo; columna derecha con el
 *      filtro Curso+Fecha, estudiantes con A/L/F del día (color de fondo),
 *      justificación y botones verde (papá) / rosa (mamá) → wa.me + registro.
 *   2. Historial      : filtro mes+curso, paginado (25/página). El profesor
 *      solo ve los cursos de su horario; el admin puede ELIMINAR mensajes.
 *
 * Variables: $tab, $canSend, $courses, $courseId, $date, $filtered, $course,
 *            $incidentes, $defaultMsg, $mes, $hCurso, $hist, $csrf_token,
 *            $flashOk, $flashErr
 */
ob_start();
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$tipoBg = ['A' => '#eef3fc', 'L' => '#fdf3e3', 'F' => '#fdecea'];
$tipoFg = ['A' => '#2e60b0', 'L' => '#9a6a00', 'F' => '#c0303a'];
$CURSO_LABEL = function ($c) {
    return trim(($c['curso'] ?? '') . ' ' . (($c['nivel'] ?? '') !== '' ? "({$c['nivel']} G{$c['grado']} P{$c['paralelo']})" : ''));
};
?>
<div class="section">
  <div class="container">

    <h1 class="title is-3"><i class="fab fa-whatsapp"></i>&nbsp; Hoja de Mensajería</h1>
    <p class="subtitle is-6 mb-4">Avisos a padres de familia · Gestión <?= $h(\App\Models\Management::getActive()->anio ?? date('Y')) ?></p>

    <?php if ($flashOk): ?>
      <div class="notification is-success py-3"><?= $h($flashOk) ?></div>
    <?php endif; ?>
    <?php if ($flashErr): ?>
      <div class="notification is-danger py-3"><?= $h($flashErr) ?></div>
    <?php endif; ?>

    <!-- ===================== PESTAÑAS ===================== -->
    <div class="tabs is-boxed" id="msgTabs">
      <ul>
        <?php if ($canSend): ?>
        <li class="<?= $tab === 'enviar' ? 'is-active' : '' ?>" data-tab="enviar">
          <a href="/messages?tab=enviar<?= $courseId ? '&curso=' . (int)$courseId . '&fecha=' . $h($date) . ($filtered ? '&filtrar=1' : '') : '' ?>">
            <span class="icon"><i class="fas fa-paper-plane"></i></span><span>Enviar mensaje</span>
          </a>
        </li>
        <?php endif; ?>
        <li class="<?= $tab === 'historial' ? 'is-active' : '' ?>" data-tab="historial">
          <a href="/messages?tab=historial&mes=<?= $h($mes) ?><?= $hCurso ? '&hcurso=' . (int)$hCurso : '' ?>">
            <span class="icon"><i class="fas fa-archive"></i></span><span>Historial de mensajes</span>
          </a>
        </li>
      </ul>
    </div>

    <?php /* ============ PESTAÑA 1: ENVIAR MENSAJE ============ */ ?>
    <?php if ($canSend && $tab === 'enviar'): ?>
    <div class="columns" id="panel-enviar">

      <!-- Columna 1: configuración del mensaje -->
      <div class="column is-half-desktop">
        <div class="box">
          <p class="title is-6"><i class="fas fa-pen"></i>&nbsp; Configuración del mensaje</p>

          <div class="field msg-toolbar mb-2">
            <div class="control">
              <div class="buttons has-addons" style="flex-wrap:wrap">
                <button type="button" class="button is-small" data-wrap="*{{x}}*" data-label="texto" title="Formato WhatsApp: *texto*"><b>B</b></button>
                <button type="button" class="button is-small" data-wrap="_{{x}}_" data-label="texto" title="Formato WhatsApp: _texto_"><i>I</i></button>
                <button type="button" class="button is-small" data-wrap="~{{x}}~" data-label="texto" title="Formato WhatsApp: ~texto~"><s>S</s></button>
              </div>
            </div>
            <div class="control mt-1">
              <div class="buttons has-addons" style="flex-wrap:wrap">
                <button type="button" class="button is-small is-info is-light" data-insert="{{estudiante}}" title="Nombre del estudiante">{{estudiante}}</button>
                <button type="button" class="button is-small is-info is-light" data-insert="{{justificacion}}" title="Justificación registrada">{{justificacion}}</button>
                <button type="button" class="button is-small is-warning is-light" data-insert="{{total_alf}}" title="Totales A/L/F del estudiante">{{total_alf}}</button>
                <button type="button" class="button is-small is-warning is-light" data-insert="- Atrasos: {{atrasos}}&#10;- Licencias: {{licencias}}&#10;- Faltas: {{faltas}}" title="Desglose de totales">A/L/F</button>
                <button type="button" class="button is-small is-light" data-insert="{{fecha_hoy}}" title="Hoy: ddd, dd de mmm, del yyyy">{{fecha_hoy}}</button>
                <button type="button" class="button is-small is-light" data-insert="{{evento}} {{evento_nombre}}" title="Evento del día (A/L/F)">{{evento}}</button>
              </div>
            </div>
          </div>

          <textarea id="msgTpl" class="textarea" rows="9" spellcheck="false"><?= $h($defaultMsg) ?></textarea>
          <p class="help">Los marcadores <code>{{...}}</code> se reemplazan automáticamente por los datos de cada estudiante al pulsar enviar.</p>

          <p class="title is-6 mt-4"><i class="fas fa-eye"></i>&nbsp; Vista previa (ejemplo)</p>
          <pre id="msgPreview" class="msg-preview box is-white has-background-light"></pre>
        </div>
      </div>

      <!-- Columna 2: envío -->
      <div class="column">
        <div class="box">
          <p class="title is-6"><i class="fas fa-paper-plane"></i>&nbsp; Envío de mensajes</p>
          <form method="get" action="/messages">
            <input type="hidden" name="tab" value="enviar">
            <input type="hidden" name="filtrar" value="1">
            <div class="columns">
              <div class="column">
                <label class="label is-small">Curso</label>
                <div class="select is-fullwidth is-small">
                  <select name="curso" required>
                    <option value="">Seleccione curso…</option>
                    <?php foreach ($courses as $c): ?>
                      <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $courseId ? 'selected' : '' ?>>
                        <?= $h($CURSO_LABEL($c)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="column">
                <label class="label is-small">Fecha del evento</label>
                <input class="input is-small" type="date" name="fecha" value="<?= $h($date) ?>" required>
              </div>
              <div class="column is-narrow">
                <label class="label is-small">&nbsp;</label>
                <button class="button is-small is-link">
                  <span class="icon"><i class="fas fa-filter"></i></span><span>Filtrar</span>
                </button>
              </div>
            </div>
          </form>

          <?php if ($filtered && $courseId > 0): ?>
            <hr>
            <p class="is-size-7 has-text-grey">
              Estudiantes con atraso/licencia/falta el <strong><?= $h($date) ?></strong>
              en <strong><?= $h($course ? $CURSO_LABEL($course) : ('#' . $courseId)) ?></strong>:
            </p>
            <?php if (!$incidentes): ?>
              <div class="notification is-info py-3 mt-3">Sin incidentes ese día: no hay mensajes que enviar. 🎉</div>
            <?php else: ?>
              <div id="waList">
                <?php foreach ($incidentes as $inc): $t = $inc['tipo']; ?>
                  <div class="box msg-row p-3 mb-2" style="background:<?= $h($tipoBg[$t] ?? '#fff') ?>"
                       data-sid="<?= (int)$inc['id'] ?>" data-tipo="<?= $h($t) ?>">
                    <div class="level is-mobile mb-1">
                      <div class="level-left">
                        <div>
                          <span class="tag" style="background:<?= $h($tipoFg[$t] ?? '#555') ?>;color:#fff"><?= $h($t) ?></span>
                          <strong><?= $h($inc['nombre']) ?></strong>
                        </div>
                      </div>
                      <div class="level-right">
                        <div class="buttons is-small" style="margin:0">
                          <?php if ($inc['tel_papa'] !== ''): ?>
                            <button type="button" class="button is-small wa-btn" style="background:#25d366;color:#fff"
                                    data-dest="papa" data-tel="<?= $h($inc['tel_papa']) ?>" data-nombre="<?= $h($inc['nombre']) ?>"
                                    data-just="<?= $h($inc['justificacion']) ?>" data-fecha="<?= $h($date) ?>" data-sid="<?= (int)$inc['id'] ?>">
                              <span class="icon"><i class="fab fa-whatsapp"></i></span><span>Papá</span>
                            </button>
                          <?php else: ?>
                            <button class="button is-small" disabled title="Sin teléfono del papá">Papá</button>
                          <?php endif; ?>
                          <?php if ($inc['tel_mama'] !== ''): ?>
                            <button type="button" class="button is-small wa-btn" style="background:#e91e63;color:#fff"
                                    data-dest="mama" data-tel="<?= $h($inc['tel_mama']) ?>" data-nombre="<?= $h($inc['nombre']) ?>"
                                    data-just="<?= $h($inc['justificacion']) ?>" data-fecha="<?= $h($date) ?>" data-sid="<?= (int)$inc['id'] ?>">
                              <span class="icon"><i class="fab fa-whatsapp"></i></span><span>Mamá</span>
                            </button>
                          <?php else: ?>
                            <button class="button is-small" disabled title="Sin teléfono de la mamá">Mamá</button>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                    <p class="is-size-7"><em>Justificación:</em> <?= $h($inc['justificacion'] !== '' ? $inc['justificacion'] : '— sin justificación —') ?></p>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          <?php elseif (!$filtered): ?>
            <p class="help mt-3">Seleccione curso y fecha y pulse <strong>Filtrar</strong> para ver los estudiantes a notificar.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php /* ============ PESTAÑA 2: HISTORIAL ============ */ ?>
    <?php if ($tab === 'historial'): ?>
    <div class="box" id="panel-historial">
      <p class="title is-6"><i class="fas fa-archive"></i>&nbsp; Historial de mensajes</p>

      <form method="get" action="/messages" class="mb-3">
        <input type="hidden" name="tab" value="historial">
        <div class="columns is-vcentered">
          <div class="column is-narrow">
            <label class="label is-small">Mes</label>
            <input class="input is-small" type="month" name="mes" value="<?= $h($mes) ?>" required>
          </div>
          <div class="column is-narrow">
            <label class="label is-small">Curso</label>
            <div class="select is-small">
              <select name="hcurso">
                <option value="0">Todos</option>
                <?php foreach ($courses as $c): ?>
                  <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $hCurso ? 'selected' : '' ?>>
                    <?= $h($CURSO_LABEL($c)) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="column is-narrow">
            <button class="button is-small is-link">
              <span class="icon"><i class="fas fa-filter"></i></span><span>Filtrar</span>
            </button>
          </div>
          <div class="column">
            <p class="is-size-7 has-text-grey"><?= (int)$hist['total'] ?> mensaje(s) en el periodo.</p>
          </div>
        </div>
      </form>

      <?php if (!empty($hist['sin_horarios'])): ?>
        <div class="notification is-warning py-3">Aún no hay horarios cargados para tu usuario; contacta a Administración para asignarte cursos en la tabla de horarios.</div>
      <?php elseif (empty($hist['rows'])): ?>
        <p class="has-text-grey">No hay mensajes enviados con esos filtros.</p>
      <?php else: ?>
        <div class="table-container">
          <table class="table is-bordered is-striped is-narrow is-hoverable is-fullwidth">
            <thead>
              <tr>
                <th>Enviado</th><th>Curso</th><th>Estudiante</th><th>Fecha evento</th>
                <th>Evento</th><th>Justificación</th><th>Destinatario</th><th>Teléfono</th>
                <th>Mensaje</th><?php if (isAdmin()): ?><th></th><?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($hist['rows'] as $r): $t = $r['tipo_evento'] ?? ''; ?>
                <tr>
                  <td class="is-size-7"><?= $h(substr((string)$r['fecha_envio'], 0, 16)) ?></td>
                  <td class="is-size-7"><?= $h($r['curso_nombre'] ?? '—') ?></td>
                  <td><?= $h($r['estudiante_nombre'] ?? '—') ?></td>
                  <td class="is-size-7"><?= $h($r['fecha_evento'] ?? '—') ?></td>
                  <td><span class="tag" style="background:<?= $h($tipoFg[$t] ?? '#555') ?>;color:#fff"><?= $h($t ?: '—') ?></span></td>
                  <td class="is-size-7 msg-just-cell"><?= $h($r['justificacion'] ?? '—') ?></td>
                  <td class="is-size-7"><?= $h(ucfirst((string)($r['destinatario'] ?? ''))) ?></td>
                  <td class="is-size-7"><?= $h($r['telefono'] ?? '—') ?></td>
                  <td><a class="button is-small is-light" onclick="return msgShow(this)"
                         data-msg="<?= $h($r['mensaje']) ?>"><span class="icon"><i class="fas fa-eye"></i></span><span>Ver</span></a></td>
                  <?php if (isAdmin()): ?>
                    <td>
                      <form method="post" action="/messages/<?= (int)$r['id'] ?>/delete"
                            onsubmit="return confirm('¿Eliminar este mensaje del historial?')">
                        <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
                        <button class="button is-small is-danger is-light" title="Eliminar (solo administrador)">
                          <span class="icon"><i class="fas fa-trash"></i></span>
                        </button>
                      </form>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if ((int)$hist['pages'] > 1): ?>
          <nav class="pagination is-small is-centered">
            <ul class="pagination-list">
              <?php for ($i = 1; $i <= (int)$hist['pages']; $i++): ?>
                <li>
                  <a class="pagination-link <?= $i === (int)$hist['page'] ? 'is-current' : '' ?>"
                     href="/messages?tab=historial&mes=<?= $h($mes) ?>&hcurso=<?= (int)$hCurso ?>&p=<?= $i ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
            </ul>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- Modal: ver mensaje del historial -->
<div class="modal" id="msgModal">
  <div class="modal-background" id="msgModalBg"></div>
  <div class="modal-card">
    <header class="modal-card-head"><p class="modal-card-title">Mensaje enviado</p></header>
    <section class="modal-card-body"><pre id="msgModalBody" class="msg-preview"></pre></section>
    <footer class="modal-card-foot"><button class="button" id="msgModalClose">Cerrar</button></footer>
  </div>
</div>

<style>
.msg-preview { white-space:pre-wrap; font-family:inherit; }
.msg-just-cell { max-width:220px; overflow:hidden; text-overflow:ellipsis; }
.wa-btn[disabled] { opacity:.4; }
.sent-flash { outline:2px solid #25d366; transition:outline .3s; }
</style>

<script>
(function () {
  var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var tplEl = document.getElementById('msgTpl');
  var prevEl = document.getElementById('msgPreview');

  /* ---- Vista previa con datos de ejemplo (placeholders interpolados) ---- */
  var EJEMPLO = {
    '{{estudiante}}': 'Perez Juan', '{{curso}}': '1ro Secundaria A',
    '{{evento}}': 'A', '{{evento_nombre}}': 'Atraso',
    '{{justificacion}}': 'Llegó tarde por bloqueo de tránsito',
    '{{fecha_hoy}}': 'jueves, 15 de oct, del 2026',
    '{{fecha_evento}}': 'jueves, 15 de oct, del 2026',
    '{{total_alf}}': '22/7/0', '{{atrasos}}': '22', '{{licencias}}': '7', '{{faltas}}': '0'
  };
  function renderPrev() {
    if (!prevEl || !tplEl) return;
    var t = tplEl.value;
    for (var k in EJEMPLO) t = t.split(k).join(EJEMPLO[k]);
    prevEl.textContent = t;
  }
  if (tplEl) { tplEl.addEventListener('input', renderPrev); renderPrev(); }

  /* ---- Botones de barra: insertar placeholder o envolver selección ---- */
  document.querySelectorAll('.msg-toolbar [data-insert], .msg-toolbar [data-wrap]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!tplEl) return;
      var s = tplEl.selectionStart, e = tplEl.selectionEnd, v = tplEl.value;
      if (btn.dataset.insert) {
        tplEl.value = v.slice(0, s) + btn.dataset.insert + v.slice(e);
        tplEl.selectionStart = tplEl.selectionEnd = s + btn.dataset.insert.length;
      } else {
        var w = btn.dataset.wrap, inner = w.replace('{{x}}', v.slice(s, e) || 'texto');
        tplEl.value = v.slice(0, s) + inner + v.slice(e);
        tplEl.selectionStart = s; tplEl.selectionEnd = s + inner.length;
      }
      tplEl.focus(); renderPrev();
    });
  });

  /* ---- Clic en botón wa.me: registrar envío (POST /messages/log) y abrir chat ---- */
  document.querySelectorAll('.wa-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dest = btn.dataset.dest;
      btn.disabled = true;

      fetch('/messages/log', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          csrf_token: CSRF,
          id_estudiante: btn.dataset.sid,
          destinatario: dest,
          mensaje: tplEl ? tplEl.value : '',
          fecha_evento: btn.dataset.fecha
        })
      })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        if (!res.ok || !res.j.ok) throw new Error((res.j && res.j.error) || 'Error al registrar el mensaje');
        window.open(res.j.wa_url, '_blank');
        btn.closest('.msg-row').classList.add('sent-flash');
        btn.innerHTML = '<span class="icon"><i class="fas fa-check"></i></span><span>Enviado #' + res.j.id + '</span>';
      })
      .catch(function (err) {
        alert(err.message);
        btn.disabled = false;
      });
    });
  });

  /* ---- Modal "ver mensaje" del historial ---- */
  window.msgShow = function (a) {
    var m = document.getElementById('msgModal');
    document.getElementById('msgModalBody').textContent = a.dataset.msg;
    m.classList.add('is-active');
    return false;
  };
  ['msgModalBg', 'msgModalClose'].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('click', function () { document.getElementById('msgModal').classList.remove('is-active'); });
  });
})();
</script>

<?php
$content = ob_get_clean();
require APP_ROOT . '/app/views/layouts/app.php';
?>
