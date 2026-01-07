<?php
use Moni\Repositories\ExpensesRepository;
use Moni\Services\PdfExtractorService;
use Moni\Services\InvoiceParserService;
use Moni\Support\Csrf;
use Moni\Support\Flash;

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$flashAll = Flash::getAll();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;
$errors = [];
$extracted = [];
$categories = ExpensesRepository::getCategories();

// Default values
$expense = [
    'supplier_name' => '',
    'supplier_nif' => '',
    'invoice_number' => '',
    'invoice_date' => date('Y-m-d'),
    'base_amount' => '',
    'vat_rate' => '21',
    'vat_amount' => '',
    'total_amount' => '',
    'category' => 'otros',
    'pdf_path' => '',
    'notes' => '',
    'status' => 'pending',
];

if ($editing) {
    $found = ExpensesRepository::find($id);
    if ($found) {
        $expense = array_merge($expense, $found);
    } else {
        Flash::add('error', 'Gasto no encontrado.');
        header('Location: /?page=expenses');
        exit;
    }
}

// Handle PDF upload and extraction (AJAX or form submit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'extract') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    
    if (!Csrf::validate($_POST['_token'] ?? null)) {
        echo json_encode(['error' => 'Token CSRF inválido']);
        exit;
    }

    if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'Error al subir el archivo']);
        exit;
    }

    $file = $_FILES['pdf'];
    $maxSize = 10 * 1024 * 1024; // 10MB

    if ($file['size'] > $maxSize) {
        echo json_encode(['error' => 'El archivo es demasiado grande (máx 10MB)']);
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($mimeType !== 'application/pdf') {
        echo json_encode(['error' => 'El archivo debe ser un PDF']);
        exit;
    }

    // Save the file
    $storageDir = dirname(__DIR__) . '/storage/expenses';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0755, true);
    }

    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.pdf';
    $destPath = $storageDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        echo json_encode(['error' => 'Error al guardar el archivo']);
        exit;
    }

    // Extract text
    try {
        $text = PdfExtractorService::extractText($destPath);
        $hasContent = PdfExtractorService::hasUsefulContent($text);

        // Parse the text
        $parsed = InvoiceParserService::parse($text);
    } catch (\Throwable $e) {
        error_log("Error en extracción PDF: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
        echo json_encode(['error' => 'Error interno al procesar el PDF. Revisa los logs.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'pdf_path' => 'storage/expenses/' . $filename,
        'has_content' => $hasContent,
        'extracted' => $parsed,
    ]);
    exit;
}

// Handle form submission (save)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] === 'save')) {
    if (!Csrf::validate($_POST['_token'] ?? null)) {
        Flash::add('error', 'Token CSRF inválido.');
        header('Location: /?page=expenses');
        exit;
    }

    $expense['supplier_name'] = trim($_POST['supplier_name'] ?? '');
    $expense['supplier_nif'] = trim($_POST['supplier_nif'] ?? '');
    $expense['invoice_number'] = trim($_POST['invoice_number'] ?? '');
    $expense['invoice_date'] = trim($_POST['invoice_date'] ?? '');
    $expense['base_amount'] = str_replace(',', '.', trim($_POST['base_amount'] ?? '0'));
    $expense['vat_rate'] = str_replace(',', '.', trim($_POST['vat_rate'] ?? '21'));
    $expense['vat_amount'] = str_replace(',', '.', trim($_POST['vat_amount'] ?? '0'));
    $expense['total_amount'] = str_replace(',', '.', trim($_POST['total_amount'] ?? '0'));
    $expense['category'] = $_POST['category'] ?? 'otros';
    $expense['pdf_path'] = $_POST['pdf_path'] ?? '';
    $expense['notes'] = trim($_POST['notes'] ?? '');
    $expense['status'] = $_POST['status'] ?? 'pending';

    // Validation
    if (empty($expense['supplier_name'])) {
        $errors['supplier_name'] = 'El nombre del proveedor es obligatorio';
    }
    if (empty($expense['invoice_date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expense['invoice_date'])) {
        $errors['invoice_date'] = 'Fecha de factura inválida';
    }
    if ((float)$expense['total_amount'] <= 0) {
        $errors['total_amount'] = 'El importe total debe ser mayor que 0';
    }

    if (empty($errors)) {
        if ($editing) {
            ExpensesRepository::update($id, $expense);
            Flash::add('success', 'Gasto actualizado correctamente.');
        } else {
            ExpensesRepository::create($expense);
            Flash::add('success', 'Gasto registrado correctamente.');
        }
        header('Location: /?page=expenses');
        exit;
    }
}
?>
<section>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <h1 style="margin:0"><?= $editing ? 'Editar gasto' : 'Nuevo gasto' ?></h1>
    <a href="/?page=expenses" class="btn" style="background:var(--gray-200);color:var(--gray-700)">← Volver</a>
  </div>

  <?php if (!empty($flashAll)): ?>
    <?php foreach ($flashAll as $type => $messages): ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert <?= $type==='error'?'error':'' ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="alert error">
      <strong>Por favor, corrige los siguientes errores:</strong>
      <ul style="margin:8px 0 0 20px">
        <?php foreach ($errors as $err): ?>
          <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if (!$editing): ?>
  <div class="card" style="margin-bottom:20px" id="upload-section">
    <h3 style="margin-top:0">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-4px;margin-right:8px">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
        <line x1="12" y1="18" x2="12" y2="12"/>
        <polyline points="9 15 12 12 15 15"/>
      </svg>
      Subir factura (PDF)
    </h3>
    <p style="color:var(--gray-600);margin:-8px 0 16px">Sube el PDF de la factura y extraeremos los datos automáticamente.</p>
    
    <div id="dropzone" style="border:2px dashed var(--gray-300);border-radius:8px;padding:32px;text-align:center;cursor:pointer;transition:all 0.2s">
      <input type="file" id="pdf-input" accept="application/pdf" style="display:none" />
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--gray-400)" stroke-width="1.5" style="margin-bottom:12px">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="17 8 12 3 7 8"/>
        <line x1="12" y1="3" x2="12" y2="15"/>
      </svg>
      <p style="margin:0;color:var(--gray-600)">Arrastra un PDF aquí o <span style="color:var(--primary-600);text-decoration:underline">haz clic para seleccionar</span></p>
      <p style="margin:8px 0 0;font-size:0.85rem;color:var(--gray-500)">Máximo 10MB</p>
    </div>

    <div id="upload-progress" style="display:none;margin-top:16px">
      <div style="display:flex;align-items:center;gap:12px">
        <div class="spinner"></div>
        <span>Subiendo y analizando PDF...</span>
      </div>
    </div>

    <div id="extraction-result" style="display:none;margin-top:16px">
      <div class="alert" style="background:var(--success-50);border-color:var(--success-200);color:var(--success-700)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px"><polyline points="20 6 9 17 4 12"/></svg>
        <span id="extraction-message">PDF procesado. Revisa los datos extraídos abajo.</span>
      </div>
    </div>

    <div id="extraction-warning" style="display:none;margin-top:16px">
      <div class="alert" style="background:var(--warning-50);border-color:var(--warning-200);color:var(--warning-700)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span>No se pudo extraer texto del PDF. Puede ser una imagen escaneada. Introduce los datos manualmente.</span>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <form method="post" class="card" id="expense-form">
    <input type="hidden" name="_token" value="<?= Csrf::token() ?>" />
    <input type="hidden" name="action" value="save" />
    <input type="hidden" name="pdf_path" id="pdf_path" value="<?= htmlspecialchars($expense['pdf_path']) ?>" />

    <div class="form-grid-2">
      <div>
        <label for="supplier_name">Proveedor *</label>
        <input type="text" id="supplier_name" name="supplier_name" value="<?= htmlspecialchars($expense['supplier_name']) ?>" required class="<?= isset($errors['supplier_name']) ? 'error' : '' ?>" />
        <div class="field-hint" id="hint_supplier_name"></div>
      </div>
      <div>
        <label for="supplier_nif">NIF/CIF</label>
        <input type="text" id="supplier_nif" name="supplier_nif" value="<?= htmlspecialchars($expense['supplier_nif']) ?>" placeholder="B12345678" />
        <div class="field-hint" id="hint_supplier_nif"></div>
      </div>
    </div>

    <div class="form-grid-2">
      <div>
        <label for="invoice_number">Nº Factura</label>
        <input type="text" id="invoice_number" name="invoice_number" value="<?= htmlspecialchars($expense['invoice_number']) ?>" />
        <div class="field-hint" id="hint_invoice_number"></div>
      </div>
      <div>
        <label for="invoice_date">Fecha factura *</label>
        <input type="date" id="invoice_date" name="invoice_date" value="<?= htmlspecialchars($expense['invoice_date']) ?>" required class="<?= isset($errors['invoice_date']) ? 'error' : '' ?>" />
        <div class="field-hint" id="hint_invoice_date"></div>
      </div>
    </div>

    <div class="sep"></div>

    <div class="form-grid-4">
      <div>
        <label for="base_amount">Base imponible (€)</label>
        <input type="text" id="base_amount" name="base_amount" value="<?= htmlspecialchars((string)$expense['base_amount']) ?>" placeholder="0,00" inputmode="decimal" />
        <div class="field-hint" id="hint_base_amount"></div>
      </div>
      <div>
        <label for="vat_rate">% IVA</label>
        <select id="vat_rate" name="vat_rate">
          <option value="21" <?= (float)$expense['vat_rate'] == 21 ? 'selected' : '' ?>>21%</option>
          <option value="10" <?= (float)$expense['vat_rate'] == 10 ? 'selected' : '' ?>>10%</option>
          <option value="4" <?= (float)$expense['vat_rate'] == 4 ? 'selected' : '' ?>>4%</option>
          <option value="0" <?= (float)$expense['vat_rate'] == 0 ? 'selected' : '' ?>>0% (exento)</option>
        </select>
        <div class="field-hint" id="hint_vat_rate"></div>
      </div>
      <div>
        <label for="vat_amount">Cuota IVA (€)</label>
        <input type="text" id="vat_amount" name="vat_amount" value="<?= htmlspecialchars((string)$expense['vat_amount']) ?>" placeholder="0,00" inputmode="decimal" />
        <div class="field-hint" id="hint_vat_amount"></div>
      </div>
      <div>
        <label for="total_amount">Total (€) *</label>
        <input type="text" id="total_amount" name="total_amount" value="<?= htmlspecialchars((string)$expense['total_amount']) ?>" placeholder="0,00" inputmode="decimal" required class="<?= isset($errors['total_amount']) ? 'error' : '' ?>" style="font-weight:600" />
        <div class="field-hint" id="hint_total_amount"></div>
      </div>
    </div>

    <div class="sep"></div>

    <div class="form-grid-2">
      <div>
        <label for="category">Categoría</label>
        <select id="category" name="category">
          <?php foreach ($categories as $key => $label): ?>
            <option value="<?= $key ?>" <?= $expense['category'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="status">Estado</label>
        <select id="status" name="status">
          <option value="pending" <?= $expense['status'] === 'pending' ? 'selected' : '' ?>>Pendiente de revisar</option>
          <option value="validated" <?= $expense['status'] === 'validated' ? 'selected' : '' ?>>Validado</option>
        </select>
      </div>
    </div>

    <div>
      <label for="notes">Notas</label>
      <textarea id="notes" name="notes" rows="2" placeholder="Observaciones opcionales..."><?= htmlspecialchars($expense['notes']) ?></textarea>
    </div>

    <?php if ($expense['pdf_path']): ?>
    <div style="margin-top:12px;padding:12px;background:var(--gray-50);border-radius:6px;display:flex;align-items:center;gap:12px">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary-600)" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
      </svg>
      <span>PDF adjunto: <a href="/<?= htmlspecialchars($expense['pdf_path']) ?>" target="_blank" style="color:var(--primary-600)"><?= basename($expense['pdf_path']) ?></a></span>
    </div>
    <?php endif; ?>

    <div class="sep"></div>

    <div style="display:flex;gap:12px;justify-content:flex-end">
      <a href="/?page=expenses" class="btn" style="background:var(--gray-200);color:var(--gray-700)">Cancelar</a>
      <button type="submit" class="btn btn-primary"><?= $editing ? 'Guardar cambios' : 'Registrar gasto' ?></button>
    </div>
  </form>
</section>

<style>
.field-hint {
  font-size: 0.8rem;
  color: var(--primary-600);
  margin-top: 4px;
  min-height: 18px;
}
.field-hint:empty { display: none; }
.form-grid-4 {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
@media (max-width: 768px) {
  .form-grid-4 { grid-template-columns: repeat(2, 1fr); }
}
#dropzone.dragover {
  border-color: var(--primary-500);
  background: var(--primary-50);
}
.spinner {
  width: 20px;
  height: 20px;
  border: 2px solid var(--gray-300);
  border-top-color: var(--primary-600);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<script>
(function() {
  const dropzone = document.getElementById('dropzone');
  const fileInput = document.getElementById('pdf-input');
  const uploadSection = document.getElementById('upload-section');
  const progressDiv = document.getElementById('upload-progress');
  const resultDiv = document.getElementById('extraction-result');
  const warningDiv = document.getElementById('extraction-warning');
  const form = document.getElementById('expense-form');

  if (!dropzone) return; // Skip if editing

  // Drag and drop
  ['dragenter', 'dragover'].forEach(e => {
    dropzone.addEventListener(e, ev => { ev.preventDefault(); dropzone.classList.add('dragover'); });
  });
  ['dragleave', 'drop'].forEach(e => {
    dropzone.addEventListener(e, ev => { ev.preventDefault(); dropzone.classList.remove('dragover'); });
  });
  dropzone.addEventListener('drop', ev => {
    const files = ev.dataTransfer.files;
    if (files.length) handleFile(files[0]);
  });
  dropzone.addEventListener('click', () => fileInput.click());
  fileInput.addEventListener('change', () => {
    if (fileInput.files.length) handleFile(fileInput.files[0]);
  });

  function handleFile(file) {
    if (file.type !== 'application/pdf') {
      alert('Por favor, selecciona un archivo PDF.');
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
      alert('El archivo es demasiado grande (máximo 10MB).');
      return;
    }
    uploadAndExtract(file);
  }

  function uploadAndExtract(file) {
    dropzone.style.display = 'none';
    progressDiv.style.display = 'block';
    resultDiv.style.display = 'none';
    warningDiv.style.display = 'none';

    const formData = new FormData();
    formData.append('pdf', file);
    formData.append('_token', '<?= Csrf::token() ?>');
    formData.append('action', 'extract');

    fetch('/?page=expense_form', {
      method: 'POST',
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      progressDiv.style.display = 'none';
      
      if (data.error) {
        dropzone.style.display = 'block';
        alert(data.error);
        return;
      }

      // Set PDF path
      document.getElementById('pdf_path').value = data.pdf_path;

      if (data.has_content) {
        resultDiv.style.display = 'block';
        fillForm(data.extracted);
      } else {
        warningDiv.style.display = 'block';
      }
    })
    .catch(err => {
      progressDiv.style.display = 'none';
      dropzone.style.display = 'block';
      alert('Error al procesar el PDF: ' + err.message);
    });
  }

  function fillForm(extracted) {
    const fields = ['supplier_nif', 'invoice_number', 'invoice_date', 'base_amount', 'vat_rate', 'vat_amount', 'total_amount'];
    
    fields.forEach(field => {
      const input = document.getElementById(field);
      const hint = document.getElementById('hint_' + field);
      const value = extracted[field];
      
      if (value !== null && value !== undefined && value !== '') {
        if (field === 'vat_rate') {
          input.value = String(Math.round(parseFloat(value)));
        } else if (['base_amount', 'vat_amount', 'total_amount'].includes(field)) {
          input.value = parseFloat(value).toFixed(2).replace('.', ',');
        } else {
          input.value = value;
        }
        
        // Show confidence hint
        const conf = extracted.confidence?.[field];
        if (conf && hint) {
          const confText = {
            'high': '✓ Detectado automáticamente',
            'medium': '~ Detectado (revisar)',
            'low': '? Estimado (revisar)',
            'calculated': '= Calculado'
          };
          hint.textContent = confText[conf] || '';
        }
      }
    });

    // Auto-calculate if we have total but not base/vat
    recalculate();
  }

  // Auto-calculation between base, vat_rate, vat_amount, total
  const baseInput = document.getElementById('base_amount');
  const vatRateInput = document.getElementById('vat_rate');
  const vatAmountInput = document.getElementById('vat_amount');
  const totalInput = document.getElementById('total_amount');

  function parseNum(str) {
    return parseFloat(String(str).replace(',', '.')) || 0;
  }
  function formatNum(n) {
    return n.toFixed(2).replace('.', ',');
  }

  function recalculate(source) {
    const base = parseNum(baseInput.value);
    const rate = parseFloat(vatRateInput.value) || 0;
    const vat = parseNum(vatAmountInput.value);
    const total = parseNum(totalInput.value);

    if (source === 'base' || source === 'rate') {
      // Calculate VAT and total from base
      if (base > 0) {
        const calcVat = base * (rate / 100);
        vatAmountInput.value = formatNum(calcVat);
        totalInput.value = formatNum(base + calcVat);
      }
    } else if (source === 'total') {
      // Calculate base and VAT from total
      if (total > 0 && rate > 0) {
        const calcBase = total / (1 + rate / 100);
        const calcVat = total - calcBase;
        baseInput.value = formatNum(calcBase);
        vatAmountInput.value = formatNum(calcVat);
      }
    } else if (!source && total > 0 && base === 0) {
      // Initial load with only total
      const calcBase = total / (1 + rate / 100);
      const calcVat = total - calcBase;
      baseInput.value = formatNum(calcBase);
      vatAmountInput.value = formatNum(calcVat);
    }
  }

  baseInput.addEventListener('input', () => recalculate('base'));
  vatRateInput.addEventListener('change', () => recalculate('rate'));
  totalInput.addEventListener('input', () => recalculate('total'));
})();
</script>
