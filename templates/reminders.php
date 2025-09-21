<?php
use Moni\Repositories\RemindersRepository;
use Moni\Support\Flash;
use Moni\Support\Csrf;
use Moni\Support\Config;

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$flashAll = Flash::getAll();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!Csrf::validate($_POST['_token'] ?? null)) {
    Flash::add('error', 'CSRF inválido.');
    header('Location: /?page=reminders');
    exit;
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
        RemindersRepository::create($title, $date, in_array($recurring, ['none','yearly'], true) ? $recurring : 'yearly');
        Flash::add('success', 'Recordatorio añadido.');
      }
    } elseif ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id > 0) {
        RemindersRepository::delete($id);
        Flash::add('success', 'Recordatorio eliminado.');
      }
    }
  } catch (Throwable $e) {
    Flash::add('error', 'Acción fallida: ' . $e->getMessage());
  }
  header('Location: /?page=reminders');
  exit;
}

// Compute mandatory quarterly declarations for the current year
$tz = Config::get('settings.timezone', 'Europe/Madrid');
@date_default_timezone_set($tz);
$y = (int)date('Y');
$mandatory = [
  ["title" => 'Inicio trimestre Q1', "date" => "$y-01-01"],
  ["title" => 'Inicio trimestre Q2', "date" => "$y-04-01"],
  ["title" => 'Inicio trimestre Q3', "date" => "$y-07-01"],
  ["title" => 'Inicio trimestre Q4', "date" => "$y-10-01"],
];

$rows = RemindersRepository::all();
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
    <div class="card">
      <h3>Declaraciones obligatorias (trimestral)</h3>
      <ul class="kv" style="margin-top:8px">
        <?php foreach ($mandatory as $m): ?>
          <li><span><?= htmlspecialchars($m['title']) ?></span><span><?= htmlspecialchars((new DateTime($m['date']))->format('d/m/Y')) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="card">
      <h3>Nuevo recordatorio</h3>
      <form method="post">
        <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
        <input type="hidden" name="_action" value="add" />

        <label>Título</label>
        <input type="text" name="title" placeholder="Ej: Segundo pago fraccionado IRPF" required />

        <label>Fecha</label>
        <input type="date" name="event_date" required />

        <label>Repetición</label>
        <select name="recurring" style="width:100%;padding:10px;border:1px solid #E2E8F0;border-radius:8px;background:#fff">
          <option value="yearly" selected>Anual</option>
          <option value="none">Solo una vez</option>
        </select>

        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:10px">
          <button type="submit" class="btn">Guardar</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <h3>Tus recordatorios</h3>
    <?php if (empty($rows)): ?>
      <p>No hay recordatorios todavía.</p>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Título</th>
            <th>Fecha</th>
            <th>Repetición</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['title']) ?></td>
              <td><?= htmlspecialchars((new DateTime($r['event_date']))->format('d/m/Y')) ?></td>
              <td><?= htmlspecialchars($r['recurring'] === 'none' ? 'Una vez' : 'Anual') ?></td>
              <td class="table-actions">
                <form method="post" onsubmit="return confirm('¿Eliminar recordatorio?');">
                  <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
                  <input type="hidden" name="_action" value="delete" />
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                  <button type="submit" class="btn btn-danger">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</section>
