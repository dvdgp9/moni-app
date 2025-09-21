<?php
use Moni\Repositories\RemindersRepository;
use Moni\Support\Flash;
use Moni\Support\Csrf;
use Moni\Support\Config;

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$flashAll = Flash::getAll();

// Actions: add, delete, toggle, bulk_enable, bulk_disable
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!Csrf::validate($_POST['_token'] ?? null)) {
    Flash::add('error', 'CSRF inválido.');
    // AJAX friendly
    if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
      header('Content-Type: application/json');
      echo json_encode(['ok' => false, 'error' => 'CSRF inválido']);
      exit;
    } else {
      header('Location: /?page=reminders');
      exit;
    }
  }
  $action = $_POST['_action'] ?? '';
  try {
    if ($action === 'add') {
      $title = trim((string)($_POST['title'] ?? ''));
      $date = trim((string)($_POST['event_date'] ?? ''));
      $recurring = (string)($_POST['recurring'] ?? 'yearly');
      if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        Flash::add('error', 'Título y fecha (YYYY-MM-DD) son obligatorios.');
      } else {
        RemindersRepository::create($title, $date, $recurring === 'none' ? 'none' : 'yearly', null, true);
        Flash::add('success', 'Recordatorio añadido.');
      }
    } elseif ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id > 0) {
        RemindersRepository::delete($id);
        Flash::add('success', 'Recordatorio eliminado.');
      }
    } elseif ($action === 'toggle') {
      $id = (int)($_POST['id'] ?? 0);
      $enabled = (int)($_POST['enabled'] ?? 0) === 1;
      if ($id > 0) {
        RemindersRepository::setEnabled($id, $enabled);
      }
    } elseif ($action === 'bulk_enable' || $action === 'bulk_disable') {
      $ids = array_map('intval', $_POST['ids'] ?? []);
      RemindersRepository::setEnabledMany($ids, $action === 'bulk_enable');
    }
  } catch (Throwable $e) {
    Flash::add('error', 'Acción fallida: ' . $e->getMessage());
  }
  // AJAX response
  if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
  }
  header('Location: /?page=reminders');
  exit;
}

// Load reminders and split groups
$rows = RemindersRepository::all();
$y = (int)date('Y');
$isQuarter = fn(array $r) => str_starts_with((string)$r['title'], 'Inicio trimestre Q') && ($r['recurring'] ?? 'yearly') === 'yearly';
$quarters = array_values(array_filter($rows, $isQuarter));
$custom = array_values(array_filter($rows, fn($r) => !$isQuarter($r)));

function icon_calendar(): string {
  return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="vertical-align:-2px;margin-right:6px"><path d="M8 2v4M16 2v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M3 10h18" stroke="currentColor" stroke-width="1.8"/></svg>';
}
function icon_bell(): string {
  return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="vertical-align:-2px;margin-right:6px"><path d="M14.5 18.5a2.5 2.5 0 0 1-5 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M4 18.5h16l-1.2-2.4a7 7 0 0 1-.8-3.2V10a6 6 0 1 0-12 0v2.9c0 1.1-.27 2.2-.8 3.2L4 18.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

?>
<section>
  <h1>Notificaciones</h1>

  <?php if (!empty($flashAll)): ?>
    <?php foreach ($flashAll as $type => $messages): ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert <?= $type==='error'?'error':'' ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="grid-2">
    <!-- Declaraciones obligatorias -->
    <div class="card">
      <div style="display:flex;align-items:center;gap:8px;justify-content:space-between;margin-bottom:6px">
        <h3 style="display:flex;align-items:center;gap:8px;margin:0;font-size:1.25rem">
          <?= icon_calendar() ?>Declaraciones obligatorias
        </h3>
        <div style="display:flex;gap:6px;align-items:center">
          <?php if (!empty($quarters)): ?>
          <form method="post" class="js-bulk" data-scope="quarters" data-action="bulk_enable" style="margin:0">
            <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
            <?php foreach ($quarters as $q) : ?><input type="hidden" name="ids[]" value="<?= (int)$q['id'] ?>" /><?php endforeach; ?>
            <input type="hidden" name="_action" value="bulk_enable" />
            <button class="btn btn-sm" type="submit" data-role="bulk-enable">Seleccionar todo</button>
          </form>
          <form method="post" class="js-bulk" data-scope="quarters" data-action="bulk_disable" style="margin:0">
            <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
            <?php foreach ($quarters as $q) : ?><input type="hidden" name="ids[]" value="<?= (int)$q['id'] ?>" /><?php endforeach; ?>
            <input type="hidden" name="_action" value="bulk_disable" />
            <button class="btn btn-secondary btn-sm" type="submit" data-role="bulk-disable">Deseleccionar todo</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php if (empty($quarters)): ?>
        <p class="hint">No hay declaraciones trimestrales configuradas.</p>
      <?php else: ?>
        <ul class="kv" style="margin-top:0">
          <?php foreach ($quarters as $r): ?>
            <li style="display:flex;align-items:center;gap:8px">
              <form method="post" style="display:flex;align-items:center;gap:8px" class="js-toggle" data-id="<?= (int)$r['id'] ?>" data-enabled="<?= $r['enabled'] ? 1 : 0 ?>">
                <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
                <input type="hidden" name="_action" value="toggle" />
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                <input type="hidden" name="enabled" value="<?= $r['enabled'] ? 0 : 1 ?>" />
                <button type="submit" class="btn btn-sm <?= $r['enabled'] ? '' : 'btn-secondary' ?>" title="<?= $r['enabled'] ? 'Desactivar' : 'Activar' ?>" data-role="toggle">
                  <?= $r['enabled'] ? '✔' : '○' ?>
                </button>
              </form>
              <span style="flex:1;min-width:0"><?= htmlspecialchars($r['title']) ?></span>
              <span><?= htmlspecialchars((new DateTime($r['event_date']))->format('d/m')) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <!-- Personalizadas -->
    <div class="card">
      <div style="display:flex;align-items:center;gap:8px;justify-content:space-between;margin-bottom:6px">
        <h3 style="display:flex;align-items:center;gap:8px;margin:0;font-size:1.25rem">
          <?= icon_bell() ?>Notificaciones personalizadas
        </h3>
        <?php if (!empty($custom)): ?>
        <div style="display:flex;gap:6px;align-items:center">
          <form method="post" class="js-bulk" data-scope="custom" data-action="bulk_enable" style="margin:0">
            <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
            <?php foreach ($custom as $c) : ?><input type="hidden" name="ids[]" value="<?= (int)$c['id'] ?>" /><?php endforeach; ?>
            <input type="hidden" name="_action" value="bulk_enable" />
            <button class="btn btn-sm" type="submit" data-role="bulk-enable">Seleccionar todo</button>
          </form>
          <form method="post" class="js-bulk" data-scope="custom" data-action="bulk_disable" style="margin:0">
            <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
            <?php foreach ($custom as $c) : ?><input type="hidden" name="ids[]" value="<?= (int)$c['id'] ?>" /><?php endforeach; ?>
            <input type="hidden" name="_action" value="bulk_disable" />
            <button class="btn btn-secondary btn-sm" type="submit" data-role="bulk-disable">Deseleccionar todo</button>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <form method="post" style="display:grid;grid-template-columns:1.2fr 0.8fr 0.7fr auto;gap:8px;align-items:end;margin-bottom:8px" id="js-add-form">
        <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
        <input type="hidden" name="_action" value="add" />
        <div>
          <label>Título</label>
          <input type="text" name="title" placeholder="Ej: Modelo 130" required />
        </div>
        <div>
          <label>Fecha</label>
          <input type="date" name="event_date" required />
        </div>
        <div>
          <label>Repetición</label>
          <select name="recurring" style="width:100%;padding:10px;border:1px solid #E2E8F0;border-radius:8px;background:#fff">
            <option value="yearly" selected>Anual</option>
            <option value="none">Solo una vez</option>
          </select>
        </div>
        <div style="display:flex;justify-content:flex-end">
          <button type="submit" class="btn">Añadir</button>
        </div>
      </form>

      <?php if (empty($custom)): ?>
        <p class="hint">No hay recordatorios personalizados todavía.</p>
      <?php else: ?>
        
        <ul class="kv" style="margin-top:0">
          <?php foreach ($custom as $r): ?>
            <li style="display:flex;align-items:center;gap:8px">
              <form method="post" style="display:flex;align-items:center;gap:8px" class="js-toggle" data-id="<?= (int)$r['id'] ?>" data-enabled="<?= $r['enabled'] ? 1 : 0 ?>">
                <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
                <input type="hidden" name="_action" value="toggle" />
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                <input type="hidden" name="enabled" value="<?= $r['enabled'] ? 0 : 1 ?>" />
                <button type="submit" class="btn btn-sm <?= $r['enabled'] ? '' : 'btn-secondary' ?>" title="<?= $r['enabled'] ? 'Desactivar' : 'Activar' ?>" data-role="toggle">
                  <?= $r['enabled'] ? '✔' : '○' ?>
                </button>
              </form>
              <span style="flex:1;min-width:0"><?= htmlspecialchars($r['title']) ?></span>
              <span><?= htmlspecialchars((new DateTime($r['event_date']))->format('d/m/Y')) ?></span>
              <form method="post" onsubmit="return confirm('¿Eliminar recordatorio?');" style="margin-left:8px">
                <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
                <input type="hidden" name="_action" value="delete" />
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                <button type="submit" class="btn btn-danger btn-sm" title="Eliminar">🗑️</button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</section>
<script>
(function(){
  const csrf = '<?= Csrf::token() ?>';

  function postAjax(data) {
    const body = new URLSearchParams();
    Object.entries(data).forEach(([k,v]) => {
      if (Array.isArray(v)) {
        v.forEach(item => body.append(k, item));
      } else {
        body.append(k, v);
      }
    });
    body.append('ajax', '1');
    return fetch('/?page=reminders', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      credentials: 'same-origin',
      body: body.toString()
    }).then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP '+r.status)));
  }

  function applyToggleUI(form, makeOn) {
    const btn = form.querySelector('[data-role="toggle"]');
    form.dataset.enabled = makeOn ? '1' : '0';
    const hidden = form.querySelector('input[name="enabled"]');
    if (hidden) hidden.value = makeOn ? '0' : '1'; // next post should flip
    if (btn) {
      btn.classList.toggle('btn-secondary', !makeOn);
      btn.textContent = makeOn ? '✔' : '○';
      btn.title = makeOn ? 'Desactivar' : 'Activar';
    }
  }

  function toggleAjax(form) {
    const btn = form.querySelector('[data-role="toggle"]');
    const id = form.dataset.id;
    const enabledNow = form.dataset.enabled === '1';
    const target = enabledNow ? '0' : '1';
    if (btn) btn.disabled = true;
    // Optimistic UI
    applyToggleUI(form, !enabledNow);
    return postAjax({ _token: csrf, _action: 'toggle', id, enabled: target })
      .then(res => {
        if (!res || !res.ok) {
          // rollback
          applyToggleUI(form, enabledNow);
          console.warn('Toggle fallido');
        }
      })
      .catch(err => {
        applyToggleUI(form, enabledNow);
        console.warn('Error AJAX', err && err.message ? err.message : err);
      })
      .finally(() => { if (btn) btn.disabled = false; });
  }

  // Toggle forms (submit + click handlers)
  document.querySelectorAll('.js-toggle').forEach(form => {
    form.addEventListener('submit', function(ev){ ev.preventDefault(); toggleAjax(form); });
    const btn = form.querySelector('[data-role="toggle"]');
    if (btn) btn.addEventListener('click', function(ev){ ev.preventDefault(); toggleAjax(form); });
  });

  // Bulk actions
  document.querySelectorAll('.js-bulk').forEach(f => {
    f.addEventListener('submit', function(ev){
      ev.preventDefault();
      const ids = Array.from(f.querySelectorAll('input[name="ids[]"]')).map(i=>i.value);
      const action = f.dataset.action;
      postAjax({ _token: csrf, _action: action, 'ids[]': ids })
        .then(res => {
          if (!res || !res.ok) { console.warn('Acción masiva fallida'); return; }
          // Update UI of the corresponding scope
          const scope = f.dataset.scope;
          const container = scope === 'quarters' ? document.querySelectorAll('.grid-2 .card')[0] : document.querySelectorAll('.grid-2 .card')[1];
          // mark all toggles accordingly
          const makeOn = action === 'bulk_enable';
          container.querySelectorAll('.js-toggle').forEach(form => {
            if (!ids.includes(form.dataset.id)) return;
            form.dataset.enabled = makeOn ? '1' : '0';
            const btn = form.querySelector('[data-role="toggle"]');
            if (!btn) return;
            btn.classList.toggle('btn-secondary', !makeOn ? true : false);
            btn.textContent = makeOn ? '✔' : '○';
            btn.title = makeOn ? 'Desactivar' : 'Activar';
          });
        })
        .catch(err=>{ console.warn('Error AJAX', err && err.message ? err.message : err); });
    });
  });
})();
</script>
