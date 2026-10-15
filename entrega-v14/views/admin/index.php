<?php
/**
 * Página de Administración — 4 pestañas (solo rol administrador)
 *   1. Usuarios y permisos
 *   2. Horarios y profesores (CSV plano -> ids, listado con lápiz)
 *   3. Gestión escolar (backup / restaurar / migrar año)
 *   4. Diagnóstico (entorno, integridad, seguridad)
 *
 * Variables: $tab, $csrf_token, $flash,
 *   usuarios:  $roles,$perms,$matrix,$users
 *   horarios:  $anioSel,$courses,$profesores,$subjects,$horarios,$editRow
 *   gestion:   $gestiones,$activa,$snapshots,$nextAnio
 *   diagnostico: $diag
 */
ob_start();
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$curTab = $tab ?? 'usuarios';
$errors = $_SESSION['admin_errors'] ?? []; unset($_SESSION['admin_errors']);
$warns  = $_SESSION['admin_warns'] ?? [];  unset($_SESSION['admin_warns']);
?>
<div class="section">
  <div class="container">

    <h1 class="title is-3"><i class="fas fa-shield-alt"></i>&nbsp; Administración</h1>
    <p class="subtitle is-6 mb-4">Panel exclusivo del administrador · usuarios/permisos, horarios, gestiones y diagnóstico.</p>

    <?php if (!empty($flash)): ?>
      <div class="notification <?= $h($flash['type']) ?> py-3"><?= $h($flash['msg']) ?></div>
    <?php endif; ?>

    <!-- ===== Pestañas ===== -->
    <div class="tabs is-boxed mb-5">
      <ul>
        <li class="<?= $curTab === 'usuarios' ? 'is-active' : '' ?>">
          <a href="/admin?tab=usuarios"><span class="icon"><i class="fas fa-users-cog"></i></span><span>Usuarios y permisos</span></a>
        </li>
        <li class="<?= $curTab === 'horarios' ? 'is-active' : '' ?>">
          <a href="/admin?tab=horarios"><span class="icon"><i class="fas fa-calendar-week"></i></span><span>Horarios y profesores</span></a>
        </li>
        <li class="<?= $curTab === 'feriados' ? 'is-active' : '' ?>">
          <a href="/admin?tab=feriados"><span class="icon"><i class="fas fa-umbrella-beach"></i></span><span>Feriados</span></a>
        </li>
        <li class="<?= $curTab === 'gestion' ? 'is-active' : '' ?>">
          <a href="/admin?tab=gestion"><span class="icon"><i class="fas fa-school"></i></span><span>Gestión escolar</span></a>
        </li>
        <li class="<?= $curTab === 'diagnostico' ? 'is-active' : '' ?>">
          <a href="/admin?tab=diagnostico"><span class="icon"><i class="fas fa-stethoscope"></i></span><span>Diagnóstico</span></a>
        </li>
      </ul>
    </div>

    <!-- =========================================================
         PESTAÑA 1 — USUARIOS Y PERMISOS
         ========================================================= -->
    <?php if ($curTab === 'usuarios'): ?>
    <div class="columns">

      <!-- Tabla pequeña A: usuarios -->
      <div class="column is-7">
        <div class="box">
          <p class="title is-5"><i class="fas fa-user-plus"></i>&nbsp; Crear usuario</p>
          <form method="post" action="/admin/users/store">
            <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
            <div class="columns is-mobile">
              <div class="column field"><label class="label is-small">Nombre</label>
                <div class="control"><input class="input is-small" name="nombre" required></div></div>
              <div class="column field"><label class="label is-small">Apellido</label>
                <div class="control"><input class="input is-small" name="apellido" required></div></div>
            </div>
            <div class="columns is-mobile">
              <div class="column field"><label class="label is-small">Usuario</label>
                <div class="control"><input class="input is-small" name="usuario" required></div></div>
              <div class="column field"><label class="label is-small">Contraseña (≥6)</label>
                <div class="control"><input class="input is-small" type="password" name="contrasena" minlength="6" required></div></div>
              <div class="column field"><label class="label is-small">Rol</label>
                <div class="control select is-small is-fullwidth">
                  <select name="role_id" required>
                    <?php foreach ($roles as $r): ?><option value="<?= (int)$r->id ?>"><?= $h($r->name) ?></option><?php endforeach; ?>
                  </select>
                </div></div>
            </div>
            <button class="button is-link is-small is-light"><i class="fas fa-save"></i>&nbsp;Crear usuario</button>
          </form>

          <hr>
          <p class="title is-5 mt-4"><i class="fas fa-users"></i>&nbsp; Usuarios registrados</p>
          <div class="table-container">
            <table class="table is-striped is-narrow is-fullwidth is-size-7">
              <thead><tr><th>Usuario</th><th>Nombre completo</th><th>Rol actual</th><th>Cambiar rol</th></tr></thead>
              <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><strong><?= $h($u->usuario) ?></strong></td>
                  <td><?= $h(trim(($u->nombre ?? '') . ' ' . ($u->apellido ?? ''))) ?></td>
                  <td><span class="tag is-info is-light"><?= $h($u->rol_nombre ?? ($u->rol ?? '—')) ?></span></td>
                  <td>
                    <form method="post" action="/admin/users/role" style="display:flex;gap:.35rem;align-items:center">
                      <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
                      <input type="hidden" name="id" value="<?= (int)$u->id ?>">
                      <div class="select is-mini">
                        <select name="role_id">
                          <?php foreach ($roles as $r): ?>
                            <option value="<?= (int)$r->id ?>" <?= ((int)($u->role_id ?? 0) === (int)$r->id) ? 'selected' : '' ?>><?= $h($r->name) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <button class="button is-mini is-link is-outlined" title="Guardar rol"><i class="fas fa-check"></i></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Tabla pequeña B: matriz de permisos por rol -->
      <div class="column is-5">
        <div class="box">
          <p class="title is-5"><i class="fas fa-key"></i>&nbsp; Permisos por rol</p>
          <?php foreach ($roles as $r): ?>
            <details class="mb-2" <?= $r->name === 'administrador' ? 'open' : '' ?>>
              <summary class="has-text-weight-bold is-size-7" style="cursor:pointer">
                <?= $h($r->name) ?> <span class="has-text-grey">(<?= count($matrix[(int)$r->id] ?? []) ?>)</span>
              </summary>
              <form method="post" action="/admin/permissions/save" class="mt-2">
                <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
                <input type="hidden" name="role_id" value="<?= (int)$r->id ?>">
                <div class="is-size-7" style="max-height:220px;overflow:auto">
                  <?php foreach ($perms as $p): $on = in_array((int)$p->id, $matrix[(int)$r->id] ?? []); ?>
                    <label class="checkbox d-block" style="margin-bottom:.15rem">
                      <input type="checkbox" name="permisos[]" value="<?= (int)$p->id ?>" <?= $on ? 'checked' : '' ?>>
                      <?= $h($p->name) ?>
                    </label>
                  <?php endforeach; ?>
                </div>
                <button class="button is-small is-link mt-2"><i class="fas fa-save"></i>&nbsp;Guardar «<?= $h($r->name) ?>»</button>
              </form>
            </details>
          <?php endforeach; ?>

          <hr class="my-3">
          <div class="columns is-mobile">
            <div class="column">
              <p class="title is-7">Nuevo rol</p>
              <form method="post" action="/admin/roles/create">
                <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
                <div class="field"><div class="control"><input class="input is-small" name="name" placeholder="prefecto" required></div></div>
                <div class="field"><div class="control"><input class="input is-small" name="description" placeholder="Descripción opcional"></div></div>
                <button class="button is-small is-info is-light">Añadir rol</button>
              </form>
            </div>
            <div class="column">
              <p class="title is-7">Nuevo permiso</p>
              <form method="post" action="/admin/permissions/create">
                <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
                <div class="field"><div class="control"><input class="input is-small" name="name" placeholder="view_xxx" pattern="[a-z][a-z0-9_]{2,60}" required></div></div>
                <div class="field"><div class="control"><input class="input is-small" name="description" placeholder="Descripción"></div></div>
                <button class="button is-small is-info is-light">Añadir permiso</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- =========================================================
         PESTAÑA 2 — HORARIOS Y PROFESORES
         ========================================================= -->
    <?php if ($curTab === 'horarios'): ?>
    <div class="columns">
      <div class="column is-6">
        <div class="box">
          <p class="title is-5"><i class="fas fa-file-csv"></i>&nbsp; Carga masiva (texto plano tipo CSV)</p>
          <p class="is-size-7 has-text-grey mb-3">
            Escribe una fila por horario con nombres reales (el sistema los traduce a ids):<br>
            <code>curso, profesor, dia, hora_inicio, hora_fin, asignatura, anio</code>
          </p>
          <a class="button is-small is-info is-light mb-3" href="/admin/horarios/plantilla.csv">
            <i class="fas fa-download"></i>&nbsp;Descargar plantilla CSV
          </a>
          <form method="post" action="/admin/horarios/import">
            <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
            <input type="hidden" name="anio" value="<?= (int)$anioSel ?>">
            <div class="field">
              <div class="control">
                <textarea class="textarea is-small mono" name="csv" rows="9"
placeholder="P1A,Juan Perez,Lunes,07:30,09:00,Matematica,<?= (int)$anioSel ?>
S3B,Maria Lopez,Miercoles,10:00,11:30,Fisica-Quimica,<?= (int)$anioSel ?>"></textarea>
              </div>
            </div>
            <button class="button is-link"><i class="fas fa-save"></i>&nbsp;Guardar horarios</button>
          </form>
          <?php if ($errors): ?>
            <div class="notification is-danger is-light mt-3 py-2 is-size-7">
              <p class="has-text-weight-bold">Filas con error (no se guardaron):</p>
              <ul style="list-style:disc;margin-left:1.2rem"><?php foreach ($errors as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?></ul>
            </div>
          <?php endif; ?>
          <?php if ($warns): ?>
            <div class="notification is-warning is-light mt-3 py-2 is-size-7">
              <p class="has-text-weight-bold">Advertencias (se guardaron igualmente):</p>
              <ul style="list-style:disc;margin-left:1.2rem"><?php foreach ($warns as $w): ?><li><?= $h($w) ?></li><?php endforeach; ?></ul>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="column is-6">
        <div class="box">
          <div class="level is-mobile">
            <div class="level-left"><p class="title is-5"><i class="fas fa-table"></i>&nbsp; Horarios guardados</p></div>
            <div class="level-right">
              <form method="get" action="/admin" class="is-inline">
                <input type="hidden" name="tab" value="horarios">
                <div class="select is-small"><select name="anio" onchange="this.form.submit()">
                  <?php for ($y = (int)date('Y') - 2; $y <= (int)date('Y') + 2; $y++): ?>
                    <option value="<?= $y ?>" <?= $y === (int)$anioSel ? 'selected' : '' ?>><?= $y ?></option>
                  <?php endfor; ?>
                </select></div>
              </form>
            </div>
          </div>
          <div class="table-container" style="max-height:420px;overflow:auto">
            <table class="table is-striped is-narrow is-fullwidth is-size-7">
              <thead><tr><th>Día</th><th>Hora</th><th>Curso</th><th>Asignatura</th><th>Profesor</th><th></th></tr></thead>
              <tbody>
              <?php if (empty($horarios)): ?>
                <tr><td colspan="6" class="has-text-centered has-text-grey py-4">Sin horarios para <?= $h($anioSel) ?>. Usa la carga CSV para llenar la tabla.</td></tr>
              <?php endif; ?>
              <?php foreach ($horarios as $row): ?>
                <tr>
                  <td><?= $h($row->dia) ?></td>
                  <td><?= $h(substr((string)$row->hora_inicio, 0, 5)) ?>–<?= $h(substr((string)$row->hora_fin, 0, 5)) ?></td>
                  <td><strong><?= $h($row->curso_code ?? '—') ?></strong></td>
                  <td><?= $h($row->asignatura) ?></td>
                  <td><?= $h($row->profesor_nombre ?: 'sin asignar') ?></td>
                  <td style="white-space:nowrap">
                    <a class="button is-mini is-warning is-outlined" title="Editar"
                       href="/admin?tab=horarios&anio=<?= (int)$anioSel ?>&editar=<?= (int)$row->id ?>"><i class="fas fa-pencil-alt"></i></a>
                    <form method="post" action="/admin/horarios/delete" style="display:inline"
                          onsubmit="return confirm('¿Eliminar este horario?')">
                      <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
                      <input type="hidden" name="id" value="<?= (int)$row->id ?>">
                      <button class="button is-mini is-danger is-outlined" title="Eliminar"><i class="fas fa-trash"></i></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de edición (abierto si ?editar=ID) -->
    <?php if (!empty($editRow)): ?>
    <div class="modal is-active">
      <div class="modal-background" onclick="location='/admin?tab=horarios&anio=<?= (int)$anioSel ?>'"></div>
      <div class="modal-card">
        <header class="modal-head"><p class="title is-5 mb-0">Editar horario #<?= (int)$editRow->id ?></p></header>
        <section class="modal-card-body">
          <form method="post" action="/admin/horarios/update">
            <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
            <input type="hidden" name="id" value="<?= (int)$editRow->id ?>">
            <div class="columns is-mobile">
              <div class="column field"><label class="label is-small">Curso</label>
                <div class="select is-small is-fullwidth"><select name="id_curso">
                  <?php foreach ($courses as $c): ?>
                    <option value="<?= (int)$c->id ?>" <?= (int)$c->id === (int)$editRow->id_curso ? 'selected' : '' ?>><?= $h($c->curso) ?></option>
                  <?php endforeach; ?>
                </select></div></div>
              <div class="column field"><label class="label is-small">Profesor</label>
                <div class="select is-small is-fullwidth"><select name="id_profesor">
                  <option value="">— sin asignar —</option>
                  <?php foreach ($profesores as $pr): ?>
                    <option value="<?= (int)$pr->id ?>" <?= (int)$pr->id === (int)$editRow->id_profesor ? 'selected' : '' ?>><?= $h($pr->nombre . ' ' . $pr->apellido) ?></option>
                  <?php endforeach; ?>
                </select></div></div>
            </div>
            <div class="columns is-mobile">
              <div class="column field"><label class="label is-small">Día</label>
                <div class="select is-small is-fullwidth"><select name="dia">
                  <?php foreach (['Lunes','Martes','Miércoles','Jueves','Viernes'] as $d): ?>
                    <option value="<?= $h($d) ?>" <?= $d === $editRow->dia ? 'selected' : '' ?>><?= $h($d) ?></option>
                  <?php endforeach; ?>
                </select></div></div>
              <div class="column field"><label class="label is-small">Inicio</label>
                <div class="control"><input class="input is-small" type="time" name="hora_inicio" value="<?= $h(substr((string)$editRow->hora_inicio, 0, 5)) ?>"></div></div>
              <div class="column field"><label class="label is-small">Fin</label>
                <div class="control"><input class="input is-small" type="time" name="hora_fin" value="<?= $h(substr((string)$editRow->hora_fin, 0, 5)) ?>"></div></div>
            </div>
            <div class="columns is-mobile">
              <div class="column field"><label class="label is-small">Asignatura</label>
                <div class="control"><input class="input is-small" name="asignatura" value="<?= $h($editRow->asignatura) ?>"></div></div>
              <div class="column field"><label class="label is-small">Año</label>
                <div class="control"><input class="input is-small" type="number" name="anio" value="<?= (int)$editRow->anio ?>"></div></div>
            </div>
            <div class="buttons">
              <button class="button is-link"><i class="fas fa-save"></i>&nbsp;Guardar cambios</button>
              <a class="button is-light" href="/admin?tab=horarios&anio=<?= (int)$anioSel ?>">Cancelar</a>
            </div>
          </form>
        </section>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- =========================================================
         PESTAÑA 2b — FERIADOS (CSV plano + listado con lápiz/papelera)
         ========================================================= -->
    <?php if ($curTab === 'feriados'): ?>
    <div class="columns">
      <div class="column is-7">
        <div class="box">
          <p class="title is-5"><i class="fas fa-cloud-sun-rain"></i>&nbsp; Carga masiva de feriados</p>
          <p class="is-size-7 has-text-grey mb-3">
            Formato CSV separado por comas: <code>fecha,nombre,anio</code>. Se aceptan fechas
            AAAA-MM-DD o DD/MM/AAAA. Encabezado y líneas que empiezan con # se ignoran.
            Los duplicados por fecha se omiten con aviso.
          </p>
          <form method="post" action="/admin/feriados/import">
            <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
            <div class="field">
              <div class="control">
                <textarea class="textarea" name="csv_text" rows="8"
                  placeholder="2027-01-01,Año Nuevo,2027&#10;2027-05-01,Día del Trabajo,2027"></textarea>
              </div>
            </div>
            <div class="field is-grouped">
              <div class="control">
                <button class="button is-link"><i class="fas fa-save"></i>&nbsp; Guardar</button>
              </div>
              <div class="control">
                <a class="button is-light" href="/admin/feriados/plantilla.csv">
                  <i class="fas fa-file-download"></i>&nbsp; Descargar plantilla CSV
                </a>
              </div>
            </div>
          </form>
        </div>
      </div>

      <div class="column is-5">
        <div class="box">
          <p class="title is-5">Feriados del año <?= $h($anioSel ?? date('Y')) ?></p>
          <div class="select is-small mb-3">
            <form method="get" action="/admin" style="display:inline-flex;gap:.5rem">
              <input type="hidden" name="tab" value="feriados">
              <input class="input is-small" type="number" name="anio" min="2000" max="2100"
                     value="<?= $h($anioSel ?? date('Y')) ?>" style="width:7rem">
              <button class="button is-small is-link">Ver</button>
            </form>
          </div>
          <table class="table is-fullwidth is-narrow">
            <thead><tr><th>Fecha</th><th>Nombre</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($feriados)): ?>
              <tr><td colspan="3" class="has-text-centered has-text-grey py-4">Sin feriados registrados este año.</td></tr>
            <?php else: foreach ($feriados as $f): ?>
              <tr>
                <td><?= $h($f['fecha']) ?></td>
                <td><?= $h($f['nombre']) ?></td>
                <td class="has-text-right is-pulled-right">
                  <a class="button is-small is-light" title="Editar"
                     href="/admin?tab=feriados&amp;editar=<?= (int)$f['id'] ?>">
                    <span class="icon"><i class="fas fa-pen"></i></span></a>
                  <form method="post" action="/admin/feriados/delete" style="display:inline"
                        onsubmit="return confirm('¿Eliminar el feriado del <?= $h($f['fecha']) ?>?');">
                    <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
                    <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                    <button class="button is-small is-danger is-light" title="Eliminar">
                      <span class="icon"><i class="fas fa-trash"></i></span></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <?php if (!empty($editFeriado)): $ef = $editFeriado; ?>
    <!-- Modal de edición de feriado -->
    <div class="modal is-active">
      <div class="modal-background" onclick="location.href='/admin?tab=feriados'"></div>
      <div class="modal-card">
        <header class="modal-card-head">
          <p class="modal-card-title">Editar feriado #<?= (int)$ef->id ?></p>
          <button class="delete" aria-label="cerrar" onclick="location.href='/admin?tab=feriados'"></button>
        </header>
        <form method="post" action="/admin/feriados/update">
          <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
          <input type="hidden" name="id" value="<?= (int)$ef->id ?>">
          <section class="modal-card-body">
            <div class="field">
              <label class="label">Fecha</label>
              <div class="control"><input class="input" type="date" name="fecha" required
                     value="<?= $h($ef->fecha) ?>"></div>
            </div>
            <div class="field">
              <label class="label">Nombre</label>
              <div class="control"><input class="input" type="text" name="nombre" required maxlength="120"
                     value="<?= $h($ef->nombre) ?>"></div>
            </div>
          </section>
          <footer class="modal-card-foot">
            <button class="button is-link">Guardar cambios</button>
            <a class="button is-light" href="/admin?tab=feriados">Cancelar</a>
          </footer>
        </form>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- =========================================================
         PESTAÑA 3 — GESTIÓN ESCOLAR (cambio de ropa)
         ========================================================= -->
    <?php if ($curTab === 'gestion'): ?>
    <div class="columns">
      <div class="column is-6">
        <div class="box">
          <p class="title is-5"><i class="fas fa-box-archive"></i>&nbsp; Backups de gestión</p>
          <p class="is-size-7 has-text-grey mb-3">
            Cada backup guarda TODOS los datos de una gestión (estudiantes, asistencia, mensajes, horarios…)
            dentro de la tabla <code>snapshots</code>. La estructura no cambia: solo se "cambia la ropa".
          </p>
          <form method="post" action="/admin/gestion/backup" class="mb-4">
            <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
            <div class="field has-addons">
              <div class="control"><span class="select"><select name="anio">
                <?php foreach ($gestiones as $g): ?><option value="<?= (int)$g->anio ?>"><?= (int)$g->anio ?></option><?php endforeach; ?>
              </select></span></div>
              <div class="control"><button class="button is-link"><i class="fas fa-download"></i>&nbsp;Hacer backup</button></div>
            </div>
          </form>

          <table class="table is-striped is-narrow is-fullwidth is-size-7">
            <thead><tr><th>Snapshot</th><th>Gestión</th><th>Tamaño</th><th>Creado</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($snapshots)): ?>
              <tr><td colspan="5" class="has-text-centered has-text-grey py-3">Aún no hay backups.</td></tr>
            <?php endif; ?>
            <?php foreach ($snapshots as $s): ?>
              <tr>
                <td>#<?= (int)$s->id ?></td>
                <td><strong><?= $h($s->nombre) ?></strong></td>
                <td><?= number_format((int)$s->tamano_bytes / 1024, 1) ?> KB</td>
                <td><?= $h($s->creado_en) ?></td>
                <td style="white-space:nowrap">
                  <a class="button is-mini is-info is-outlined" title="Descargar .json.gz" href="/admin/gestion/snapshot/download?id=<?= (int)$s->id ?>"><i class="fas fa-file-arrow-down"></i></a>
                  <form method="post" action="/admin/gestion/restore" style="display:inline"
                        onsubmit="return confirm('RESTAURAR <?= (int)$s->anio ?> reemplazará TODOS los datos actuales.\nPrimero haz backup de la gestión vigente. ¿Continuar?')">
                    <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
                    <input type="hidden" name="snapshot_id" value="<?= (int)$s->id ?>">
                    <input type="hidden" name="confirm" value="RESTAURAR <?= (int)$s->anio ?>">
                    <button class="button is-mini is-success is-outlined" title="Restaurar esta gestión"><i class="fas fa-rotate-left"></i></button>
                  </form>
                  <form method="post" action="/admin/gestion/snapshot/delete" style="display:inline"
                        onsubmit="return confirm('¿Eliminar el snapshot #<?= (int)$s->id ?> definitivamente?')">
                    <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
                    <input type="hidden" name="id" value="<?= (int)$s->id ?>">
                    <button class="button is-mini is-danger is-outlined" title="Eliminar snapshot"><i class="fas fa-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="column is-6">
        <div class="box">
          <p class="title is-5"><i class="fas fa-arrows-rotate"></i>&nbsp; Migrar a nueva gestión</p>
          <p class="is-size-7 mb-3">
            Gestión activa ahora: <strong><?= $h($activa ? $activa->anio : 'ninguna') ?></strong>.
            La migración: crea la gestión destino, promueve los cursos un grado
            (P1A→P2A … S5A→S6A), sube a los estudiantes al nuevo curso y deja fuera a los S6
            (su historial queda en el backup). Horarios y feriados arrancan limpios.
          </p>
          <div class="notification is-warning is-light py-2 is-size-7">
            Requiere obligatoriamente un <strong>backup previo de la gestión origen</strong>; sin él el botón falla a propósito.
          </div>
          <form method="post" action="/admin/gestion/migrate" id="migrateForm">
            <input type="hidden" name="csrf_token" value="<?= $h($csrf_token) ?>">
            <div class="columns is-mobile">
              <div class="column field"><label class="label is-small">Desde (año origen)</label>
                <div class="control"><input class="input is-small" type="number" name="from_anio" value="<?= $h($activa ? $activa->anio : date('Y')) ?>" required></div></div>
              <div class="column field"><label class="label is-small">Hacia (año destino)</label>
                <div class="control"><input class="input is-small" type="number" name="to_anio" value="<?= (int)$nextAnio ?>" required></div></div>
            </div>
            <div class="field">
              <label class="label is-small">Escribe exactamente <strong>MIGRAR <span id="migPhrase"><?= (int)$nextAnio ?></span></strong> para habilitar:</label>
              <div class="control"><input class="input is-small" name="confirm" id="migConfirm" autocomplete="off" placeholder="MIGRAR <?= (int)$nextAnio ?>"></div>
            </div>
            <button class="button is-danger" id="migBtn" disabled><i class="fas fa-play"></i>&nbsp;Ejecutar migración</button>
          </form>
          <script>
          (function () {
            var to = document.querySelector('[name=to_anio]'), conf = document.getElementById('migConfirm'),
                btn = document.getElementById('migBtn'), phrase = document.getElementById('migPhrase');
            function refresh() {
              var y = parseInt(to.value || '0', 10);
              phrase.textContent = y;
              btn.disabled = (conf.value.trim().toUpperCase() !== 'MIGRAR ' + y);
            }
            to.addEventListener('input', refresh); conf.addEventListener('input', refresh); refresh();
            document.getElementById('migrateForm').addEventListener('submit', function (e) {
              if (!confirm('Migrar definitivamente los datos a la gestión ' + to.value + '.\nRecuerda que los S6 quedan SOLO en el backup. ¿Continuar?')) e.preventDefault();
            });
          })();
          </script>

          <hr class="my-4">
          <p class="title is-6">Gestiones registradas</p>
          <table class="table is-striped is-narrow is-fullwidth is-size-7">
            <thead><tr><th>Año</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($gestiones as $g): ?>
              <tr>
                <td><strong><?= (int)$g->anio ?></strong></td>
                <td><span class="tag <?= $g->estado === 'activa' ? 'is-success' : 'is-light' ?>"><?= $h($g->estado) ?></span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- =========================================================
         PESTAÑA 4 — DIAGNÓSTICO
         ========================================================= -->
    <?php if ($curTab === 'diagnostico' && !empty($diag)): ?>
    <?php $badge = ['ok' => 'is-success', 'warn' => 'is-warning', 'bad' => 'is-danger', 'info' => 'is-light']; ?>
    <div class="columns">
      <div class="column is-6">
        <div class="box">
          <p class="title is-5"><i class="fas fa-server"></i>&nbsp; Entorno</p>
          <table class="table is-narrow is-fullwidth is-size-7">
            <?php foreach ($diag['env'] as $e): ?>
              <tr><td><?= $h($e[0]) ?></td><td><span class="tag <?= $badge[$e[2]] ?? 'is-light' ?>"><?= $h($e[1]) ?></span></td></tr>
            <?php endforeach; ?>
          </table>
        </div>
        <div class="box">
          <p class="title is-5"><i class="fas fa-database"></i>&nbsp; Tablas y filas</p>
          <table class="table is-narrow is-fullwidth is-size-7">
            <?php foreach ($diag['tables'] as $t): ?>
              <tr><td><code><?= $h($t['tabla']) ?></code></td>
                  <td class="has-text-right"><?= $t['filas'] === 'NO EXISTE' ? '<span class="tag is-danger">NO EXISTE</span>' : number_format((int)$t['filas']) ?></td></tr>
            <?php endforeach; ?>
          </table>
        </div>
      </div>
      <div class="column is-6">
        <div class="box">
          <p class="title is-5"><i class="fas fa-lock"></i>&nbsp; Seguridad</p>
          <table class="table is-narrow is-fullwidth is-size-7">
            <?php foreach ($diag['security'] as $s): ?>
              <tr><td><?= $h($s[0]) ?></td><td><span class="tag <?= $badge[$s[2]] ?? 'is-light' ?>"><?= $h($s[1]) ?></span></td></tr>
            <?php endforeach; ?>
          </table>
        </div>
        <div class="box">
          <p class="title is-5"><i class="fas fa-heart-pulse"></i>&nbsp; Integridad de datos</p>
          <table class="table is-narrow is-fullwidth is-size-7">
            <?php foreach ($diag['integrity'] as $i): ?>
              <tr><td><?= $h($i[0]) ?></td><td><span class="tag <?= $badge[$i[2]] ?? 'is-light' ?>"><?= $h($i[1]) ?></span></td></tr>
            <?php endforeach; ?>
          </table>
          <p class="title is-6 mt-3">Checklist post-migración</p>
          <?php foreach ($diag['checks'] as $c): ?>
            <p class="is-size-7">
              <span class="tag <?= $c[1] ? 'is-success' : 'is-danger' ?>"><?= $c[1] ? 'OK' : 'REVISAR' ?></span>
              <?= $h($c[0]) ?> <em class="has-text-grey">(<?= $h($c[2]) ?>)</em>
            </p>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>
<style>
  .mono { font-family: ui-monospace, Menlo, Consolas, monospace; }
  .d-block { display: block; }
</style>
<?php
$content = ob_get_clean();
require APP_ROOT . '/app/views/layouts/app.php';
?>
