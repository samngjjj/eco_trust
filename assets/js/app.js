/**
 * Eco Trust AI — app.js
 * Chart.js helpers, AJAX, inline-edit, PDF export, toast
 */

/* ── Toast Notifications ──────────────────────────────── */
(function(){
  const container = document.createElement('div');
  container.id = 'toast-container';
  document.body.appendChild(container);

  window.toast = function(msg, type='success', duration=3000){
    const el = document.createElement('div');
    el.className = 'toast ' + type;
    el.innerHTML = `<span>${type==='success'?'✅':type==='error'?'❌':'ℹ️'}</span><span>${msg}</span>`;
    container.appendChild(el);
    setTimeout(()=>{ el.style.opacity='0'; el.style.transition='opacity .4s'; setTimeout(()=>el.remove(),400); }, duration);
  };
})();

/* ── Chart.js Global Defaults ─────────────────────────── */
if(typeof Chart !== 'undefined'){
  Chart.defaults.color = '#8892b0';
  Chart.defaults.borderColor = 'rgba(255,255,255,0.06)';
  Chart.defaults.font.family = "'Inter','Noto Sans TC',sans-serif";
  Chart.defaults.font.size   = 12;
  Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(26,29,46,0.97)';
  Chart.defaults.plugins.tooltip.titleColor = '#e8eaf6';
  Chart.defaults.plugins.tooltip.bodyColor   = '#b0b8d8';
  Chart.defaults.plugins.tooltip.borderColor = 'rgba(108,99,255,0.4)';
  Chart.defaults.plugins.tooltip.borderWidth = 1;
  Chart.defaults.plugins.tooltip.padding     = 12;
  Chart.defaults.plugins.tooltip.cornerRadius= 8;
  Chart.defaults.plugins.legend.labels.color = '#b0b8d8';
  Chart.defaults.plugins.legend.labels.boxWidth = 12;
  Chart.defaults.plugins.legend.labels.padding  = 16;
}

/* ── Gradient Helper ──────────────────────────────────── */
window.makeGradient = function(ctx, color1, color2, height=300){
  const g = ctx.createLinearGradient(0, 0, 0, height);
  g.addColorStop(0, color1);
  g.addColorStop(1, color2);
  return g;
};

/* ── AJAX Helpers ─────────────────────────────────────── */
window.apiFetch = async function(url, opts={}){
  try {
    const r = await fetch(url, {
      headers: {'Content-Type':'application/json', 'X-Requested-With':'XMLHttpRequest'},
      ...opts
    });
    const ct = r.headers.get('content-type')||'';
    if(ct.includes('application/json')) return await r.json();
    return { error: await r.text() };
  } catch(e) {
    return { error: e.message };
  }
};

window.apiPost = function(url, data){
  return apiFetch(url, {
    method: 'POST',
    body: JSON.stringify(data)
  });
};

/* ── Inline Edit ──────────────────────────────────────── */
document.addEventListener('click', function(e){
  const cell = e.target.closest('.editable-cell');
  if(!cell || cell.dataset.editing) return;

  const field  = cell.dataset.field;
  const id     = cell.dataset.id;
  const table  = cell.dataset.table || '公司資料';
  const orig   = cell.textContent.trim();

  cell.dataset.editing = '1';
  cell.classList.add('editing');
  const inp = document.createElement('input');
  inp.className = 'inline-input';
  inp.value = orig;
  cell.textContent = '';
  cell.appendChild(inp);
  inp.focus(); inp.select();

  const save = async () => {
    const val = inp.value.trim();
    delete cell.dataset.editing;
    cell.classList.remove('editing');
    if(val === orig){ cell.textContent = orig; return; }

    cell.textContent = '⏳ 儲存中…';
    const res = await apiPost('/eco_sys/api/update_company.php', { id, field, value: val, table });
    if(res && res.success){
      cell.textContent = val;
      toast(`已更新 ${field} → ${val}`);
    } else {
      cell.textContent = orig;
      toast(res.error || '儲存失敗', 'error');
    }
  };

  inp.addEventListener('blur', save);
  inp.addEventListener('keydown', e=>{
    if(e.key==='Enter') inp.blur();
    if(e.key==='Escape'){ delete cell.dataset.editing; cell.classList.remove('editing'); cell.textContent=orig; }
  });
});

/* ── Batch Confirm ────────────────────────────────────── */
window.batchConfirm = async function(){
  const checked = [...document.querySelectorAll('.row-check:checked')];
  if(!checked.length){ toast('請先勾選要確認的資料列','error'); return; }
  const ids = checked.map(c=>c.value);
  const res = await apiPost('/eco_sys/api/batch_confirm.php', {ids});
  if(res && res.success){
    checked.forEach(c=>{ const row=c.closest('tr'); if(row){ const b=row.querySelector('.status-badge'); if(b){b.className='badge badge-success';b.textContent='已確認';} } });
    toast(`已核實 ${ids.length} 筆資料`);
  } else {
    toast(res.error||'操作失敗','error');
  }
};

/* ── Delete Row ───────────────────────────────────────── */
window.deleteRow = async function(id, table){
  if(!confirm('確定要刪除此筆紀錄？')) return;
  const res = await apiPost('/eco_sys/api/delete_record.php', {id, table});
  if(res && res.success){
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if(row){ row.style.opacity='0'; row.style.transition='opacity .3s'; setTimeout(()=>row.remove(),300); }
    toast('已刪除');
  } else {
    toast(res.error||'刪除失敗','error');
  }
};

/* ── PDF Export ───────────────────────────────────────── */
window.exportPDF = function(title='ESG 分析報告'){
  const el = document.querySelector('.page-wrap');
  if(!el){ toast('找不到內容','error'); return; }

  // Use print dialog with landscape orientation
  const css = `
    @media print {
      .site-header, .filter-bar, .btn, .hamburger { display: none !important; }
      body { background: #fff; color: #000; }
      .card { border: 1px solid #ccc; box-shadow: none; }
      canvas { max-width: 100% !important; }
    }
  `;
  const style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  const origTitle = document.title;
  document.title = title + ' — Eco Trust AI';
  window.print();
  document.title = origTitle;
  document.head.removeChild(style);
};

/* ── Select2 Initializer ─────────────────────────────── */
window.initSelect2 = function(selector, placeholder='搜尋…', multiple=false){
  if(typeof $ === 'undefined' || !$.fn.select2) return;
  $(selector).select2({
    placeholder, multiple,
    allowClear: true,
    width: '100%',
    dropdownCssClass: 'eco-select2-drop'
  });
};

/* ── Chart Loader ─────────────────────────────────────── */
window.showLoader = function(chartId){
  const c = document.getElementById(chartId);
  if(!c) return;
  const wrap = c.closest('.chart-card') || c.parentElement;
  let loader = wrap.querySelector('.chart-loader');
  if(!loader){
    loader = document.createElement('div');
    loader.className = 'chart-loader';
    loader.innerHTML = '<div class="spinner"></div>';
    wrap.appendChild(loader);
  }
  loader.style.display='flex';
};
window.hideLoader = function(chartId){
  const c = document.getElementById(chartId);
  if(!c) return;
  const wrap = c.closest('.chart-card') || c.parentElement;
  const loader = wrap.querySelector('.chart-loader');
  if(loader) loader.style.display='none';
};

/* ── Select All Checkboxes ────────────────────────────── */
document.addEventListener('change', function(e){
  if(e.target.id==='checkAll'){
    document.querySelectorAll('.row-check').forEach(cb=>cb.checked=e.target.checked);
  }
});
