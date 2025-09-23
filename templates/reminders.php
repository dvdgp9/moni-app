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
// Consideramos "obligatorias" las que tienen JSON de enlaces (campo links) -> vienen de BD con información oficial
$isMandatory = fn(array $r) => isset($r['links']) && trim((string)$r['links']) !== '';
$quarters = array_values(array_filter($rows, $isMandatory));
$custom = array_values(array_filter($rows, fn($r) => !$isMandatory($r)));

function icon_calendar(): string {
  return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="vertical-align:-2px;margin-right:6px"><path d="M8 2v4M16 2v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M3 10h18" stroke="currentColor" stroke-width="1.8"/></svg>';
}
function icon_bell(): string {
  return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="vertical-align:-2px;margin-right:6px"><path d="M14.5 18.5a2.5 2.5 0 0 1-5 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M4 18.5h16l-1.2-2.4a7 7 0 0 1-.8-3.2V10a6 6 0 1 0-12 0v2.9c0 1.1-.27 2.2-.8 3.2L4 18.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

// Helper to format a date range (supports nullable end_date)
function format_range(?string $start, ?string $end): string {
  try {
    $s = $start ? (new DateTime($start))->format('d/m') : '';
    $e = $end ? (new DateTime($end))->format('d/m') : '';
    if ($s && $e) return $s . ' — ' . $e;
    return $s ?: ($e ?: '');
  } catch (Throwable $e) { return htmlspecialchars((string)$start); }
}

?>
<section>
  <h1>Notificaciones</h1>
  <p style="margin-top:-6px;margin-bottom:14px;color:var(--gray-600)">
    Activa o desactiva cada aviso con el interruptor. Las declaraciones trimestrales y tus recordatorios se repiten
    cada año en la misma fecha. Usa "Todo/Nada" para activar o desactivar en bloque. Puedes añadir recordatorios
    personalizados indicando título y fecha.
  </p>

  <?php if (!empty($flashAll)): ?>
    <?php foreach ($flashAll as $type => $messages): ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert <?= $type==='error'?'error':'' ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="grid-2">
    <div class="card">
      <div class="section-header">
        <h3 class="section-title"><?= icon_calendar() ?>Declaraciones obligatorias</h3>
        <?php if (!empty($quarters)): ?>
        <div class="section-actions">
          <form method="post" class="js-bulk" data-scope="quarters" data-action="bulk_enable">
            <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
            <?php foreach ($quarters as $q) : ?><input type="hidden" name="ids[]" value="<?= (int)$q['id'] ?>" /><?php endforeach; ?>
            <input type="hidden" name="_action" value="bulk_enable" />
            <button class="btn btn-sm" type="submit">Todo</button>
          </form>
          <form method="post" class="js-bulk" data-scope="quarters" data-action="bulk_disable">
            <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
            <?php foreach ($quarters as $q) : ?><input type="hidden" name="ids[]" value="<?= (int)$q['id'] ?>" /><?php endforeach; ?>
            <input type="hidden" name="_action" value="bulk_disable" />
            <button class="btn btn-secondary btn-sm" type="submit">Nada</button>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <?php if (empty($quarters)): ?>
        <p style="color:var(--gray-500);font-style:italic">No configurado</p>
      <?php else: ?>
        <?php foreach ($quarters as $r): ?>
          <div class="reminder-item">
            <form method="post" class="js-toggle" data-id="<?= (int)$r['id'] ?>" data-enabled="<?= $r['enabled'] ? 1 : 0 ?>">
              <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
              <input type="hidden" name="_action" value="toggle" />
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
              <input type="hidden" name="enabled" value="<?= $r['enabled'] ? 0 : 1 ?>" />
              <button type="submit" class="toggle-switch <?= $r['enabled'] ? 'active' : '' ?>" data-role="toggle"></button>
            </form>
            <div class="reminder-title"><?= htmlspecialchars($r['title']) ?></div>
            <?php $range = format_range($r['event_date'] ?? null, $r['end_date'] ?? null); ?>
            <div class="reminder-date"><?= htmlspecialchars($range) ?></div>
          </div>
          <?php
            $links = [];
            if (!empty($r['links'])) {
              $decoded = json_decode((string)$r['links'], true);
              if (is_array($decoded)) { $links = $decoded; }
            }
          ?>
          <?php if (!empty($links)): ?>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin:6px 0 12px 40px">
              <?php foreach ($links as $lk): if (!isset($lk['url']) || !isset($lk['label'])) continue; ?>
                <a href="<?= htmlspecialchars((string)$lk['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm" style="text-decoration:none;padding:6px 10px;background:var(--gray-0);border:1px solid var(--gray-200);border-radius:999px;color:var(--gray-800);">
                  <?= htmlspecialchars((string)$lk['label']) ?> ↗
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="section-header">
        <h3 class="section-title"><?= icon_bell() ?>Personalizadas</h3>
        <?php if (!empty($custom)): ?>
        <div class="section-actions">
          <form method="post" class="js-bulk" data-scope="custom" data-action="bulk_enable">
            <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
            <?php foreach ($custom as $c) : ?><input type="hidden" name="ids[]" value="<?= (int)$c['id'] ?>" /><?php endforeach; ?>
            <input type="hidden" name="_action" value="bulk_enable" />
            <button class="btn btn-sm" type="submit">Todo</button>
          </form>
          <form method="post" class="js-bulk" data-scope="custom" data-action="bulk_disable">
            <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
            <?php foreach ($custom as $c) : ?><input type="hidden" name="ids[]" value="<?= (int)$c['id'] ?>" /><?php endforeach; ?>
            <input type="hidden" name="_action" value="bulk_disable" />
            <button class="btn btn-secondary btn-sm" type="submit">Nada</button>
          </form>
        </div>
        <?php endif; ?>
      </div>
      
      <form method="post" style="display:grid;grid-template-columns:2fr 1fr auto;gap:8px;margin-bottom:16px;padding:12px;background:var(--gray-50);border-radius:8px">
        <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
        <input type="hidden" name="_action" value="add" />
        <input type="text" name="title" placeholder="Nuevo recordatorio..." required style="margin:0" />
        <input type="date" name="event_date" required style="margin:0" />
        <button type="submit" class="btn btn-sm">+</button>
        <input type="hidden" name="recurring" value="yearly" />
      </form>

      <?php if (empty($custom)): ?>
        <p style="color:var(--gray-500);font-style:italic">Ninguno creado</p>
      <?php else: ?>
        <?php foreach ($custom as $r): ?>
          <div class="reminder-item">
            <form method="post" class="js-toggle" data-id="<?= (int)$r['id'] ?>" data-enabled="<?= $r['enabled'] ? 1 : 0 ?>">
              <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
              <input type="hidden" name="_action" value="toggle" />
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
              <input type="hidden" name="enabled" value="<?= $r['enabled'] ? 0 : 1 ?>" />
              <button type="submit" class="toggle-switch <?= $r['enabled'] ? 'active' : '' ?>" data-role="toggle"></button>
            </form>
            <div class="reminder-title"><?= htmlspecialchars($r['title']) ?></div>
            <div class="reminder-actions">
              <?php $range = format_range($r['event_date'] ?? null, $r['end_date'] ?? null); ?>
              <div class="reminder-date"><?= htmlspecialchars($range ?: (new DateTime($r['event_date']))->format('d/m/Y')) ?></div>
              <form method="post" onsubmit="return confirm('¿Eliminar?');">
                <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
                <input type="hidden" name="_action" value="delete" />
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                <button type="submit" class="btn btn-danger btn-sm" style="padding:4px 6px">×</button>
              </form>
            </div>
          </div>
          <?php
            $links = [];
            if (!empty($r['links'])) {
              $decoded = json_decode((string)$r['links'], true);
              if (is_array($decoded)) { $links = $decoded; }
            }
          ?>
          <?php if (!empty($links)): ?>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin:6px 0 12px 40px">
              <?php foreach ($links as $lk): if (!isset($lk['url']) || !isset($lk['label'])) continue; ?>
                <a href="<?= htmlspecialchars((string)$lk['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm" style="text-decoration:none;padding:6px 10px;background:var(--gray-0);border:1px solid var(--gray-200);border-radius:999px;color:var(--gray-800);">
                  <?= htmlspecialchars((string)$lk['label']) ?> ↗
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
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
    if (hidden) hidden.value = makeOn ? '0' : '1';
    if (btn) {
      btn.classList.toggle('active', makeOn);
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
            applyToggleUI(form, makeOn);
          });
        })
        .catch(err=>{ console.warn('Error AJAX', err && err.message ? err.message : err); });
    });
  });
})();
</script>
