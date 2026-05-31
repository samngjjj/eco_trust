<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config.php';

$activePage = 'index';
$db = getDB();

// Data Management ONLY (Confidence and ROE management)
$query = "SELECT ce.*, c.name,
          (SELECT AVG(roe) FROM company_performance cp WHERE cp.company_symbol = ce.company_id AND cp.year = ce.year) as avg_roe,
          (SELECT COUNT(*) FROM company_performance cp WHERE cp.company_symbol = ce.company_id AND cp.year = ce.year) as q_count
          FROM carbon_emissions ce 
          LEFT JOIN companies c ON ce.company_id = c.symbol 
          ORDER BY ce.company_id, ce.year";
$carbons = $db->query($query)->fetch_all(MYSQLI_ASSOC);
$hasPdfData = count($carbons) > 0;
?>
<!DOCTYPE html>
<html lang="zh-Hant">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>數據管理中心 — Eco Trust AI</title>
  <link rel="stylesheet" href="/eco_sys/assets/css/main.css">
  <style>
    .upload-zone {
      border: 2px dashed var(--border);
      border-radius: var(--radius-lg);
      padding: 3.5rem 2rem;
      text-align: center;
      cursor: pointer;
      transition: all .25s;
      background: rgba(41, 121, 255, .03);
    }

    .upload-zone:hover,
    .upload-zone.drag-over {
      border-color: #2979FF;
      background: rgba(41, 121, 255, .08);
      box-shadow: 0 0 20px rgba(41, 121, 255, 0.2) inset;
    }

    .upload-icon {
      font-size: 3.5rem;
      margin-bottom: 1rem;
    }

    .upload-zone h3 {
      color: var(--text);
      margin-bottom: .5rem;
    }

    .upload-zone p {
      color: var(--muted);
      font-size: .9rem;
    }

    .upload-btn {
      margin-top: 1.2rem;
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      padding: .7rem 1.6rem;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      color: #fff;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: .95rem;
      font-weight: 600;
    }

    .no-data-banner {
      background: rgba(255, 165, 2, .07);
      border: 1px solid rgba(255, 165, 2, .3);
      border-radius: var(--radius-lg);
      padding: 1rem 1.4rem;
      display: flex;
      align-items: center;
      gap: .75rem;
      margin-bottom: 1.25rem;
      color: var(--warning);
    }

    .progress-wrap {
      background: rgba(108, 99, 255, .1);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 1.2rem;
      display: none;
      margin-bottom: 1rem;

      /* 頂部進度條現代化 */
      .progress-container {
        height: 8px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        margin: 1.5rem 0;
        overflow: hidden;
        position: relative;
        box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.3);
      }

      .progress-bar {
        height: 100%;
        width: 0%;
        background: linear-gradient(90deg, #00C853, #64FFDA);
        box-shadow: 0 0 15px rgba(0, 200, 83, 0.5);
        border-radius: 10px;
        transition: width 0.5s ease;
        position: relative;
      }

      /* 精品級流光動畫 */
      .scanning::after {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-image: linear-gradient(-45deg,
            rgba(255, 255, 255, .2) 25%,
            transparent 25%,
            transparent 50%,
            rgba(255, 255, 255, .2) 50%,
            rgba(255, 255, 255, .2) 75%,
            transparent 75%,
            transparent);
        z-index: 1;
        background-size: 50px 50px;
        animation: moveStripes 1.5s linear infinite;
      }

      @keyframes moveStripes {
        0% {
          background-position: 0 0;
        }

        100% {
          background-position: 50px 50px;
        }
      }

      .upload-status {
        background: rgba(11, 14, 20, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        padding: 1.5rem;
        margin-top: 1rem;
      }
    }

    /* ── DataTable Zebra Stripes ── */
    .table-wrap table tbody tr:nth-child(even) td {
      background: rgba(255, 255, 255, 0.03);
    }
  </style>
</head>

<body>
  <?php include __DIR__ . '/includes/header.php'; ?>

  <div class="page-wrap">
    <div class="page-title">
      <h2>🏠 數據管理中心</h2>
      <span class="badge">誠信信心與 ROE 資料管理</span>
      <?php if ($isAdmin): ?><span class="badge badge-success">管理者模式</span><?php endif; ?>
    </div>

    <!-- Upload Section -->
    <?php if (!$isFree): ?>
    <div class="card" style="margin-bottom:1.5rem">
      <div class="card-header">
        <span class="card-title">📤 上傳 ESG 永續報告 (PDF)</span>
        <small style="color:var(--muted)">系統將自動解析誠信信心分數等關鍵指標</small>
      </div>
      <div class="upload-zone" id="uploadZone">
        <div class="upload-icon">📄</div>
        <h3>拖曳 PDF 至此，或點擊選擇檔案</h3>
        <p>支援格式：PDF | 建議每份報告命名為「股票代號_公司名稱_年份.pdf」<br>例如：<code style="color:var(--accent2)">1101_台泥_2023.pdf</code></p>
        <button class="upload-btn" onclick="document.getElementById('pdfInput').click()">
          📂 選擇 PDF 檔案
        </button>
        <input type="file" id="pdfInput" accept=".pdf" multiple style="display:none"
          onchange="handleUpload(this.files)">
      </div>

      <!-- Upload progress -->
      <div class="progress-wrap" id="progressWrap">
        <div style="color:var(--text);font-weight:600" id="progressLabel">正在分析…</div>
        <div class="progress-container">
          <div class="progress-bar" id="uploadBar" style="width:0%"></div>
        </div>
        <div style="color:var(--muted);font-size:.82rem" id="progressDetail"></div>
      </div>
    </div>
    <?php else: ?>
    <div class="card" style="margin-bottom:1.5rem">
      <div class="card-header">
        <span class="card-title">🔒 上傳 ESG 永續報告 (PDF)</span>
      </div>
      <div style="padding: 2.5rem 2rem; text-align: center; background: rgba(255, 255, 255, 0.02); border-radius: 8px;">
        <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.6;">💎</div>
        <h3 style="color: var(--text); margin-bottom: 0.5rem;">此功能為 Plus / Pro 專屬</h3>
        <p style="color: var(--muted); margin-bottom: 1.5rem;">升級至 Plus 或 Pro 方案即可解鎖上傳 PDF 報告並自動解析 ESG 指標。</p>
        <a href="/eco_sys/landing.php#pricing" class="upload-btn" style="text-decoration: none;">了解升級方案</a>
      </div>
    </div>
    <?php endif; ?>

    <!-- No data warning -->
    <?php if (!$hasPdfData): ?>
      <div class="no-data-banner">
        <span style="font-size:1.4rem">⚠️</span>
        <div>
          <strong>尚無 ESG 報告資料</strong>
          <div style="font-size:.88rem;color:var(--text-sub);margin-top:.2rem">
            請先上傳公司的 ESG PDF 永續報告，系統才能提取誠信信心分數等指標。
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Confidence & ROE Management Table -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">🛡️ 誠信信心分數 & ROE 管理</span>
        <div style="display:flex;align-items:center;gap:.75rem">
          <small style="color:var(--muted)"><?= count($carbons) ?> 筆紀錄</small>
        </div>
      </div>

      <?php if (!$hasPdfData): ?>
        <div style="text-align:center;padding:4rem 2rem">
          <div style="font-size:3.5rem;margin-bottom:1rem;opacity:.5">📊</div>
          <h3 style="color:var(--muted);font-weight:400;margin-bottom:.5rem">尚無資料</h3>
          <p style="color:var(--muted)">請先上傳 ESG PDF 報告，系統將自動解析並填入此表格</p>
        </div>
      <?php else: ?>

        <!-- Filter Bar -->
        <div class="filter-bar" style="padding:0 0 1rem">
          <div class="filter-group">
            <label>資料狀態</label>
            <select class="form-select" id="statusFilter" onchange="filterTable()">
              <option value="">全部</option>
              <option value="confirmed">已確認</option>
              <option value="pending">待修正</option>
            </select>
          </div>
          <div class="filter-group">
            <label>搜尋代號/公司</label>
            <input type="text" class="form-input" id="tableSearch" placeholder="輸入關鍵字…" oninput="filterTable()">
          </div>
        </div>

        <div class="table-wrap">
          <table id="carbonTable">
            <thead class="table-dark">
              <tr>
                <th>公司代號</th>
                <th>公司名稱</th>
                <th>年份</th>
                <th class="td-num">誠信信心分 (ESG)</th>
                <th class="td-num">綜合指標 (ROE)</th>
                <th>資料完整度</th>
                <th>核實狀態</th>
                <th>審計報告</th>
                <?php if ($isAdmin): ?>
                  <th>操作</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($carbons as $ce):
                $cs = (float) ($ce['confidence_score'] ?? 0);
                $statusClass = $cs >= 0.6 ? 'badge-success' : ($cs >= 0.4 ? 'badge-warning' : 'badge-danger');
                $statusText = $cs >= 0.6 ? '已確認' : ($cs >= 0.4 ? '待審查' : '待修正');

                // Get the actual count from the database query
                $roeCount = (int) ($ce['q_count'] ?? 0);
                $statusKey = 'complete';
                if ($roeCount < 4)
                  $statusKey = 'missing';

                if ($roeCount >= 4) {
                  $dataStatusClass = 'badge-success';
                  $dataStatusText = '數據完整 (4季)';
                } else {
                  $dataStatusClass = 'badge-warning';
                  $dataStatusText = '數據缺漏 (' . $roeCount . '季)';
                }
                ?>
                <tr data-id="<?= $ce['id'] ?>" class="carbon-row" data-status="<?= $statusKey ?>">
                  <td>
                    <?php if ($isAdmin): ?>
                      <span class="editable-cell td-code" data-field="company_id" data-id="<?= $ce['id'] ?>"
                        data-table="carbon_emissions">
                        <?= htmlspecialchars($ce['company_id'] ?? '—') ?>
                      </span>
                    <?php else: ?>
                      <span class="td-code"><?= htmlspecialchars($ce['company_id'] ?? '—') ?></span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($isAdmin): ?>
                      <span class="editable-cell" data-field="name" data-id="<?= $ce['company_id'] ?>" data-table="companies">
                        <?= htmlspecialchars($ce['name'] ?? '—') ?>
                      </span>
                    <?php else: ?>
                      <?= htmlspecialchars($ce['name'] ?? '—') ?>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($isAdmin): ?>
                      <span class="editable-cell" data-field="year" data-id="<?= $ce['id'] ?>" data-table="carbon_emissions">
                        <?= $ce['year'] ?>
                      </span>
                    <?php else: ?>
                      <?= $ce['year'] ?>
                    <?php endif; ?>
                  </td>
                  <td class="td-num">
                    <?php if ($isAdmin): ?>
                      <span class="editable-cell" data-field="confidence_score" data-id="<?= $ce['id'] ?>"
                        data-table="carbon_emissions">
                        <?php if ($ce['confidence_score'] !== null): ?>
                          <?= number_format($cs, 4) ?>
                        <?php else: ?>—<?php endif; ?>
                      </span>
                    <?php else: ?>
                      <?php if ($ce['confidence_score'] !== null): ?>
                        <span class="badge <?= $statusClass ?>"><?= number_format($cs, 4) ?></span>
                      <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
                    <?php endif; ?>
                  </td>
                  <td class="td-num">
                    <?php
                    $avgRoeIndex = $ce['avg_roe'] !== null ? (float) $ce['avg_roe'] : null;
                    ?>
                    <?= $avgRoeIndex !== null ? number_format($avgRoeIndex, 2) . '%' : '—' ?>
                  </td>
                  <td><span class="badge <?= $dataStatusClass ?>"><?= $dataStatusText ?></span></td>
                  <td><span class="badge status-badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                  <td>
                    <a href="/eco_sys/audit_report.php?id=<?= $ce['id'] ?>" class="btn btn-ghost btn-sm" title="查看 Gen-2 審計報告">📋 審計報告</a>
                  </td>
                  <?php if ($isAdmin): ?>
                    <td>
                      <button class="btn btn-danger btn-sm"
                        onclick="deleteRow(<?= $ce['id'] ?>,'carbon_emissions')">🗑</button>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Disclaimer -->
    <div
      style="margin-top: 2rem; padding: 1.5rem; border-top: 1px solid var(--border); color: var(--muted); font-size: 0.85rem; line-height: 1.6;">
      <p><strong>⚖️ 免責聲明 (Disclaimer)：</strong></p>
      <p>
        本系統所提供之「誠信信心分數」與相關 ESG 數據分析，係由 AI 模型基於公開資料（如 ESG 永續報告）自動解析生成，僅供內部決策參考。
        分析結果受限於原始資料之品質、完整性及解析技術之限制，不保證其絕對正確性或及時性。使用者在採取任何行動或做出商業投資決定前，應獨立核實相關資訊。
        本平台不對因使用本系統數據而產生之任何明示或暗示損失承擔法律責任。
      </p>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="/eco_sys/assets/js/app.js"></script>
  <script>
    const zone = document.getElementById('uploadZone');
    if (zone) {
      ['dragenter', 'dragover'].forEach(e => zone.addEventListener(e, ev => { ev.preventDefault(); zone.classList.add('drag-over'); }));
      ['dragleave', 'drop'].forEach(e => zone.addEventListener(e, ev => { ev.preventDefault(); zone.classList.remove('drag-over'); }));
      zone.addEventListener('drop', ev => handleUpload(ev.dataTransfer.files));
    }


    async function handleUpload(files) {
      if (!files || !files.length) return;
      const wrap = document.getElementById('progressWrap');
      const bar = document.getElementById('uploadBar');
      const container = bar.parentElement;
      container.style.display = 'block';
      bar.classList.add('scanning'); // 加入流體動畫
      const label = document.getElementById('progressLabel');
      const detail = document.getElementById('progressDetail');
      wrap.style.display = 'block';

      for (let i = 0; i < files.length; i++) {
        const f = files[i];
        label.textContent = `正在分析 (${i + 1}/${files.length}): ${f.name}`;
        detail.innerHTML = `<div style="color:var(--accent2)">🚀 正在上傳並進行 AI 深度分析，請稍候 (約需 1-2 分鐘)...</div>`;
        bar.style.width = (((i + 0.5) / files.length) * 90) + '%';

        const fd = new FormData();
        fd.append('pdf', f);

        try {
          const resp = await fetch('/eco_sys/api/upload_pdf.php', { method: 'POST', body: fd });
          const rawText = await resp.text();
          let res;
          try {
            res = JSON.parse(rawText);
          } catch (jsonErr) {
            console.error('Server response was not JSON:', rawText);
            throw new Error('伺服器回傳格式錯誤 (非 JSON)。可能是因為上傳逾時或主機內部錯誤。');
          }

          if (res.duplicate) {
            detail.innerHTML = '';
            bar.parentElement.style.display = 'none';
            await showDuplicateModal(res, f);
            continue; 
          }
          if (res.not_esg) {
            detail.innerHTML = ``;
            bar.parentElement.style.display = 'none';
            showStatusModal({
              title: '🚫 AI 偵測：文件不合規',
              message: `系統偵測到 <b>${f.name}</b> 並非標準的 ESG 永續報告書。<br><br><b>原因：</b>${res.reason}`,
              type: 'error',
              hint: '💡 提示：系統僅接受包含環境、誠信、社會責任等篇章的年度報告。'
            });
            return;
          }
          if (res.error) {
            detail.innerHTML = ``;
            bar.parentElement.style.display = 'none';
            if (res.error.includes('無法從檔名中解析')) {
              showStatusModal({
                title: '⚠️ 命名格式錯誤',
                message: `檔案 <b>${f.name}</b> 無法被系統識別。<br><br>${res.error}`,
                type: 'warning',
                hint: '💡 正確範例：<b>1101_台泥_2023.pdf</b><br>請包含股票代號與年份。'
              });
            } else {
              showStatusModal({ title: '❌ 系統錯誤', message: res.error, type: 'error', hint: res.file ? `Error in ${res.file} on line ${res.line}` : '' });
            }
          } else {
            showStatusModal({
              title: '✅ 分析完成',
              message: `<b>${f.name}</b> 報表已成功解析並進行 AI 訓練對比。<br><br>ESG 誠信信心分：<b>${(res.confidence * 100).toFixed(1)}%</b>`,
              type: 'success',
              hint: '📊 [下一步]：點擊確定後將更新資料表，您可於看板查看趨勢。'
            });
            detail.innerHTML = `<div style="color:var(--success)">✅ ${f.name} 分析完畢</div>`;
          }
        } catch (e) {
          showStatusModal({
            title: '❌ 上傳系統異常',
            message: `發生錯誤：${e.message}`,
            type: 'error'
          });
        }
      }
    }

    /**
     * 重複資料確認對話框
     * 回傳 Promise： resolve(true) = 用戶選覆蓋， resolve(false) = 用戶取消
     */
    function showDuplicateModal(res, file) {
      return new Promise(resolve => {
        const old = document.getElementById('status-modal-wrap');
        if (old) old.remove();

        const html = `
    <div id="status-modal-wrap" style="position:fixed;inset:0;background:rgba(2,5,10,0.88);z-index:9999;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(8px);">
      <div class="jarvis-modal" style="width:100%;max-width:500px;background:#0B0E14;border:1px solid #ff9f43;border-radius:16px;padding:2.5rem;box-shadow:0 0 30px #ff9f4344;">
        <div style="font-family:'Inter','Noto Sans TC',sans-serif;">
          <h3 style="color:#ff9f43;margin-bottom:1rem;font-size:1.35rem;">⚠️ 資料重複偵測</h3>
          <div style="color:#e8eaf6;font-size:.95rem;line-height:1.7;margin-bottom:1.2rem;">
            資料庫中已存在 <b style="color:#ff9f43;">${res.year} 年『公司代號 ${res.company_id}』</b> 的 ESG 分析資料。<br><br>
            如果繼續，現有資料將被刪除並以新報告重新分析取代。
          </div>
          <div style="background:rgba(255,159,67,.08);padding:1rem;border-radius:8px;border-left:4px solid #ff9f43;color:#aaa;font-size:.85rem;margin-bottom:1.8rem;">
            📄 檔案：<b style="color:#e8eaf6;">${res.filename}</b>
          </div>
          <div style="display:flex;gap:.75rem;">
            <button id="dup-overwrite-btn" style="flex:1;padding:.9rem;border:none;border-radius:8px;background:#ff9f43;color:#fff;font-weight:700;cursor:pointer;font-size:.95rem;">&#128465; 刪除並覆蓋</button>
            <button id="dup-cancel-btn" style="flex:1;padding:.9rem;border:1px solid var(--border);border-radius:8px;background:transparent;color:var(--muted);font-weight:600;cursor:pointer;font-size:.95rem;">取消</button>
          </div>
        </div>
      </div>
    </div>`;

        document.body.insertAdjacentHTML('beforeend', html);

        if (typeof anime !== 'undefined') {
          anime({ targets: '.jarvis-modal', scale: [0.9, 1], opacity: [0, 1], duration: 400, easing: 'easeOutExpo' });
        }

        document.getElementById('dup-overwrite-btn').onclick = async () => {
          const modal = document.getElementById('status-modal-wrap');
          if (modal) modal.remove();

          // 重新顯示進度條
          const wrap = document.getElementById('progressWrap');
          const bar = document.getElementById('uploadBar');
          const label = document.getElementById('progressLabel');
          const detail = document.getElementById('progressDetail');
          wrap.style.display = 'block';
          bar.parentElement.style.display = 'block';
          bar.classList.add('scanning');
          bar.style.width = '30%';
          label.textContent = `正在覆蓋並重新分析: ${file.name}`;
          detail.innerHTML = `<div style="color:#ff9f43">⚠️ 刪除舊資料並重新執行 AI 分析，請稍候...</div>`;

          try {
            const fd2 = new FormData();
            fd2.append('pdf', file);
            fd2.append('force', '1'); // 強制覆蓋旗標

            const resp2 = await fetch('/eco_sys/api/upload_pdf.php', { method: 'POST', body: fd2 });
            const res2 = await resp2.json();

            bar.style.width = '100%';
            if (res2.error) {
              showStatusModal({ title: '❌ 覆蓋失敗', message: res2.error, type: 'error' });
            } else {
              showStatusModal({
                title: '✅ 覆蓋完成',
                message: `<b>${file.name}</b> 已成功覆蓋舊資料並完成重新分析。<br><br>ESG 誠信信心分：<b>${(res2.confidence * 100).toFixed(1)}%</b>`,
                type: 'success',
                hint: '📊 [下一步]：點擊確定後將更新資料表。'
              });
            }
          } catch (e) {
            showStatusModal({ title: '❌ 覆蓋失敗', message: e.message, type: 'error' });
          }
          resolve(true);
        };

        document.getElementById('dup-cancel-btn').onclick = () => {
          const modal = document.getElementById('status-modal-wrap');
          if (modal) modal.remove();
          resolve(false);
        };
      });
    }

    /**
     * 顯示自定義的狀態彈窗 (JARVIS Style)
     */
    function showStatusModal({ title, message, type, hint = '' }) {
      const old = document.getElementById('status-modal-wrap');
      if (old) old.remove();

      const color = type === 'success' ? '#00E676' : (type === 'warning' ? '#ff9f43' : '#ff4757');

      // 無論成功或錯誤，按下確定後都統一執行重新整理，清空 UI 狀態
      const btnAction = "location.reload()";

      const html = `
    <div id="status-modal-wrap" style="position:fixed; inset:0; background:rgba(2,5,10,0.85); z-index:9999; display:flex; align-items:center; justify-content:center; backdrop-filter:blur(8px);">
        <div class="jarvis-modal" style="width:100%; max-width:480px; background:#0B0E14; border:1px solid ${color}; border-radius:16px; padding:2.5rem; position:relative; box-shadow: 0 0 30px ${color}33;">
            <div style="font-family:'Inter','Noto Sans TC',sans-serif;">
                <h3 style="color:${color}; margin-bottom:1rem; font-size:1.4rem; display:flex; align-items:center; gap:10px;">${title}</h3>
                <div style="color:#e8eaf6; font-size:0.95rem; line-height:1.6; margin-bottom:1.5rem;">${message}</div>
                ${hint ? `<div style="background:rgba(255,255,255,0.05); padding:1rem; border-radius:8px; border-left:4px solid ${color}; color:var(--muted); font-size:0.85rem; margin-bottom:1.5rem;">${hint}</div>` : ''}
                <button onclick="${btnAction}" style="width:100%; padding:0.95rem; border:none; border-radius:8px; background:${color}; color:#fff; font-weight:700; cursor:pointer; transition:filter 0.2s;">
                    確定並了解
                </button>
            </div>
        </div>
    </div>`;

      document.body.insertAdjacentHTML('beforeend', html);

      if (typeof anime !== 'undefined') {
        anime({
          targets: '.jarvis-modal',
          scale: [0.9, 1],
          opacity: [0, 1],
          duration: 400,
          easing: 'easeOutExpo'
        });
      }
    }

    function filterTable() {
      const status = document.getElementById('statusFilter')?.value || '';
      const search = (document.getElementById('tableSearch')?.value || '').toLowerCase();
      document.querySelectorAll('.carbon-row').forEach(tr => {
        const matchS = !status || tr.dataset.status === status;
        const matchQ = !search || tr.textContent.toLowerCase().includes(search);
        tr.style.display = matchS && matchQ ? '' : 'none';
      });
    }
  </script>
</body>

</html>