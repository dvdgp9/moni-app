(() => {
  const root = document.querySelector('[data-tax-center]');
  if (!root) return;
  const draft = root.querySelector('[data-tax-draft]');
  const dirtyNotice = root.querySelector('[data-tax-dirty]');
  const copyStatus = root.querySelector('[data-tax-copy-status]');
  const stateForms = root.querySelectorAll('[data-tax-state-form]');
  let dirty = false;
  const setDirty = () => {
    dirty = true;
    dirtyNotice.hidden = false;
    root.querySelectorAll('[data-tax-copy], [data-tax-export]').forEach(button => { button.disabled = true; });
    stateForms.forEach(form => form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; }));
  };
  if (root.dataset.unsaved === '1') setDirty();
  draft.addEventListener('input', setDirty);
  draft.addEventListener('submit', () => {
    const button = draft.querySelector('button[type="submit"]');
    button.textContent = 'Guardando…';
    button.disabled = true;
  });
  draft.addEventListener('change', setDirty);
  const showZero = root.querySelector('[data-tax-show-zero]');
  const updateRows = () => {
    root.querySelectorAll('[data-tax-row]').forEach(row => { row.hidden = row.dataset.zero === '1' && !showZero.checked; });
    root.querySelectorAll('[data-tax-group]').forEach(group => {
      group.hidden = !Array.from(group.querySelectorAll('[data-tax-row]')).some(row => !row.hidden);
    });
  };
  showZero.addEventListener('change', updateRows);
  updateRows();
  async function copyText(value) {
    if (navigator.clipboard && window.isSecureContext) {
      try { await navigator.clipboard.writeText(value); return; } catch (_) { /* Local HTTP and denied clipboard fallback. */ }
    }
    const field = document.createElement('textarea');
    field.value = value;
    field.setAttribute('aria-label', 'Importe a copiar');
    field.style.position = 'absolute';
    field.style.opacity = '0';
    root.appendChild(field);
    field.select();
    const ok = document.execCommand('copy');
    field.remove();
    if (!ok) throw new Error('clipboard');
  }
  root.querySelectorAll('[data-tax-copy]').forEach(button => {
    button.addEventListener('click', async () => {
      if (dirty || button.disabled) return;
      const row = button.closest('[data-tax-row]');
      const label = `Casilla ${row.dataset.box}`;
      try {
        await copyText(row.dataset.value);
        copyStatus.textContent = `${label}: copiado ${row.dataset.value}, sin símbolo de euro.`;
        button.textContent = 'Copiado';
        setTimeout(() => { button.textContent = 'Copiar'; }, 1800);
      } catch (_) {
        copyStatus.textContent = `No se pudo copiar. Selecciona el importe de la ${label.toLowerCase()}.`;
      }
    });
  });
  root.querySelector('[data-tax-export]').addEventListener('click', () => {
    if (dirty) return;
    const quote = text => '"' + String(text).replaceAll('"', '""') + '"';
    const rows = [['Modelo', 'Ejercicio', 'Trimestre', 'Casilla', 'Concepto', 'Importe']];
    root.querySelectorAll('[data-tax-row]').forEach(row => {
      rows.push([root.dataset.model, root.dataset.year, root.dataset.quarter + 'T', row.dataset.box, row.dataset.label, row.dataset.value || 'Pendiente']);
    });
    const blob = new Blob(['\ufeff' + rows.map(row => row.map(quote).join(';')).join('\r\n')], {type: 'text/csv;charset=utf-8'});
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `guia-modelo-${root.dataset.model}-${root.dataset.year}-${root.dataset.quarter}T.csv`;
    root.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
    copyStatus.textContent = 'Guía descargada. Es una referencia de casillas para rellenar la AEAT.';
  });
  const historySelect = root.querySelector('[data-tax-history-model]');
  const updateHistory = () => {
    root.querySelectorAll('[data-tax-history-fields]').forEach(fieldset => {
      const active = fieldset.dataset.taxHistoryFields === historySelect.value;
      fieldset.hidden = !active;
      fieldset.disabled = !active;
    });
  };
  historySelect.addEventListener('change', updateHistory);
  updateHistory();
  const correction = root.querySelector('[data-tax-correction]');
  const previousReceipt = root.querySelector('#previous-receipt');
  const updateCorrection = () => { previousReceipt.required = correction.checked; };
  correction.addEventListener('change', updateCorrection);
  updateCorrection();
  // Open collapsed history registration when arriving through its anchor.
  const openTarget = () => {
    if (location.hash === '#registrar-anterior') root.querySelector('#registrar-anterior').open = true;
  };
  window.addEventListener('hashchange', openTarget);
  openTarget();
})();
