<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config.php';
$activePage = 'news';
$db = getDB();

// 最近搜尋的公司清單（帶可用年份）
$historySql = "SELECT DISTINCT n.company_symbol, c.name
               FROM news n JOIN companies c ON n.company_symbol = c.symbol
               ORDER BY n.created_at DESC LIMIT 8";
$history = $db->query($historySql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>新聞監察看板 — Eco Trust AI</title>
  <link rel="stylesheet" href="/eco_sys/assets/css/main.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
  <style>
    .search-section { background:var(--card); border:1px solid var(--border2); border-radius:var(--radius-lg); padding:2rem; margin-bottom:1.5rem; box-shadow:var(--shadow); position:relative; overflow:hidden; }
    .search-section::before { content:''; position:absolute; top:0; left:0; width:4px; height:100%; background:var(--accent); box-shadow:0 0 15px var(--accent); }
    .search-bar { display:flex; gap:.75rem; align-items:stretch; }
    .search-bar input { flex:1; background:rgba(0,0,0,.3); border:1px solid var(--border); border-radius:8px; padding:.85rem 1.2rem; color:var(--text); font-size:1rem; outline:none; transition:all .3s; font-family:'Inter','Noto Sans TC',sans-serif; }
    .search-bar input:focus { border-color:var(--accent); box-shadow:0 0 12px rgba(41,121,255,.2); }
    .search-bar button { padding:.85rem 1.8rem; border:none; border-radius:8px; background:linear-gradient(135deg,var(--accent),var(--accent2)); color:#fff; font-size:.95rem; font-weight:700; cursor:pointer; transition:all .3s; }
    .search-bar button:hover { transform:translateY(-1px); box-shadow:0 8px 20px rgba(41,121,255,.4); }

    .history-wrap { margin-top:1.2rem; display:flex; flex-wrap:wrap; gap:.6rem; align-items:center; }
    .history-label { font-size:.75rem; color:var(--muted); font-weight:600; text-transform:uppercase; }
    .history-tag { background:rgba(41,121,255,.08); border:1px solid rgba(41,121,255,.2); padding:5px 14px; border-radius:20px; color:var(--accent); font-size:.8rem; cursor:pointer; transition:all .2s; font-weight:500; display:flex; align-items:center; gap:6px; }
    .history-tag:hover { background:var(--accent); border-color:var(--accent); color:#fff; transform:translateY(-1px); }
    .history-tag .del-btn { font-size:14px; opacity:.6; transition:opacity .2s; }
    .history-tag .del-btn:hover { opacity:1; color:#ffeb3b; }

    /* ── Year Tab Filter ── */
    .year-tabs { display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1.5rem; }
    .year-tab { padding:.4rem 1rem; border-radius:20px; border:1px solid var(--border2); background:rgba(255,255,255,.04); color:var(--muted); font-size:.82rem; cursor:pointer; transition:all .2s; font-weight:500; }
    .year-tab:hover { border-color:var(--accent); color:var(--accent); }
    .year-tab.active { background:rgba(41,121,255,.18); border-color:var(--accent); color:var(--accent); font-weight:700; }
    .year-tab .yt-badge { font-size:.7rem; background:rgba(255,255,255,.08); border-radius:8px; padding:1px 5px; margin-left:4px; }
    .year-tab.active .yt-badge { background:rgba(41,121,255,.25); color:#90caf9; }

    /* ── Month Groups ── */
    .month-group { margin-bottom:1.5rem; }
    .month-header { display:flex; align-items:center; gap:.75rem; margin-bottom:.75rem; font-size:.8rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.08em; }
    .month-header::after { content:''; flex:1; height:1px; background:var(--border2); }
    .month-badge { background:rgba(41,121,255,.12); border:1px solid rgba(41,121,255,.2); border-radius:6px; padding:2px 8px; color:var(--accent); }

    /* ── News Cards ── */
    .news-card { background:var(--card2); border:1px solid var(--border2); border-radius:12px; padding:1.2rem; transition:all .3s; text-decoration:none; display:block; margin-bottom:.6rem; }
    .news-card:hover { border-color:var(--accent); transform:translateX(4px); box-shadow:0 8px 24px rgba(0,0,0,.3); }
    .news-card.Positive { border-left:4px solid #00e676; }
    .news-card.Neutral  { border-left:4px solid #2979ff; }
    .news-card.Negative { border-left:4px solid #ff4757; }
    .news-card.Positive:hover { box-shadow:0 8px 30px rgba(0,230,118,.1); }
    .news-card.Neutral:hover  { box-shadow:0 8px 30px rgba(41,121,255,.1); }
    .news-card.Negative:hover { box-shadow:0 8px 30px rgba(255,71,87,.1); }
    .nc-title { color:var(--text); font-weight:600; font-size:.95rem; margin-bottom:.5rem; line-height:1.5; }
    .nc-meta { display:flex; align-items:center; gap:.75rem; font-size:.78rem; color:var(--muted); flex-wrap:wrap; }
    .nc-sentiment { padding:3px 10px; border-radius:6px; font-weight:800; font-size:.78rem; text-transform:uppercase; }
    .nc-sentiment.Positive { color:#00e676; background:rgba(0,230,118,.15); border:1px solid rgba(0,230,118,.3); }
    .nc-sentiment.Neutral  { color:#2979ff; background:rgba(41,121,255,.15); border:1px solid rgba(41,121,255,.3); }
    .nc-sentiment.Negative { color:#ff4757; background:rgba(255,71,87,.15); border:1px solid rgba(255,71,87,.3); }
    .nc-action-tag { font-size:.72rem; background:rgba(108,99,255,.12); border:1px solid rgba(108,99,255,.25); color:#a29bfe; border-radius:4px; padding:2px 7px; max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; display:inline-block; }
    .nc-query-tag  { font-size:.72rem; background:rgba(255,255,255,.05); border:1px solid var(--border2); color:var(--muted); border-radius:4px; padding:2px 7px; }

    /* ── Results Layout ── */
    .results-area { display:none; animation:fadeInCustom .5s ease-out; }
    @keyframes fadeInCustom { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
    .results-grid { display:grid; grid-template-columns:320px 1fr; gap:1.5rem; margin-bottom:1.5rem; }
    @media(max-width:900px){ .results-grid{ grid-template-columns:1fr; } }

    .pie-card { background:var(--card); border:1px solid var(--border2); border-radius:var(--radius-lg); padding:1.5rem; box-shadow:var(--shadow); }
    .year-stat-row { display:flex; align-items:center; gap:.6rem; padding:.5rem .6rem; border-radius:8px; transition:.15s; cursor:pointer; margin-bottom:.2rem; }
    .year-stat-row:hover { background:rgba(255,255,255,.04); }
    .year-stat-row.active { background:rgba(41,121,255,.1); border:1px solid rgba(41,121,255,.2); }
    .ys-bar-wrap { flex:1; height:6px; background:rgba(255,255,255,.06); border-radius:4px; overflow:hidden; }
    .ys-bar { height:100%; border-radius:4px; background:linear-gradient(90deg,#00E676,#2979FF); }

    .news-scroll { max-height:70vh; overflow-y:auto; padding-right:.4rem; }
    .news-scroll::-webkit-scrollbar { width:4px; }
    .news-scroll::-webkit-scrollbar-thumb { background:var(--border); border-radius:2px; }

    .loading-overlay { position:absolute; inset:0; background:rgba(15,17,23,.85); display:none; flex-direction:column; align-items:center; justify-content:center; z-index:100; backdrop-filter:blur(8px); border-radius:inherit; }
    .loading-overlay.active { display:flex; }
    .dna-spinner { width:36px; height:36px; border:3px solid rgba(41,121,255,.2); border-top-color:var(--accent); border-radius:50%; animation:spin 1s linear infinite; }
    @keyframes spin { to{transform:rotate(360deg)} }
  </style>
</head>
<body>
  <?php include __DIR__ . '/includes/header.php'; ?>

  <div class="page-wrap">
    <div class="page-title">
      <h2>📡 新聞監察中心</h2>
      <span class="badge">年度新聞 × 月份分類 × 承諾驗證</span>
    </div>

    <!-- Search -->
    <div class="search-section">
      <div class="search-bar">
        <input type="text" id="newsSearchInput" placeholder="輸入公司名稱或股票代號..." onkeydown="if(event.key==='Enter')searchNews(true)">
        <button id="searchBtn" onclick="searchNews(true)">🔍 搜尋</button>
      </div>
      <?php if (!empty($history)): ?>
      <div class="history-wrap">
        <span class="history-label">最近搜尋:</span>
        <?php foreach ($history as $h): ?>
        <div class="history-tag" onclick="quickLoad('<?= htmlspecialchars($h['company_symbol']) ?>')">
          <?= htmlspecialchars($h['name']) ?> (<?= htmlspecialchars($h['company_symbol']) ?>)
          <span class="del-btn" onclick="event.stopPropagation(); deleteHistory('<?= $h['company_symbol'] ?>', this)" title="刪除">✕</span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Results -->
    <div class="results-area" id="resultsArea">
      <div class="results-grid">

        <!-- Left: Pie + Year Stats -->
        <div>
          <div class="pie-card" style="position:relative;margin-bottom:1rem;">
            <h3 style="font-size:1rem;color:var(--text);margin-bottom:1rem;">📊 情緒分佈</h3>
            <div id="sentimentPie" style="width:100%;height:260px;"></div>
            <div id="pieStats" style="margin-top:.75rem;color:var(--muted);font-size:.82rem;text-align:center;"></div>
            <div class="loading-overlay" id="pieLoader"><div class="dna-spinner"></div></div>
          </div>

          <!-- Year Stats Panel -->
          <div class="pie-card" id="yearStatsPanel" style="display:none;">
            <h3 style="font-size:.9rem;color:var(--text);margin-bottom:.75rem;">📅 年份篩選</h3>
            <div id="yearStatsList"></div>
          </div>
        </div>

        <!-- Right: News by Month -->
        <div style="position:relative;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--border2);">
            <h3 id="newsListTitle" style="font-size:1.05rem;color:var(--text);">📰 新聞記錄</h3>
            <small id="newsCount" style="color:var(--accent2);font-weight:600;"></small>
          </div>

          <!-- Year Tabs -->
          <div class="year-tabs" id="yearTabs"></div>

          <!-- Month-grouped news -->
          <div class="news-scroll" id="newsList"></div>

          <div class="loading-overlay" id="newsLoader" style="border-radius:12px;">
            <div class="dna-spinner"></div>
            <div style="margin-top:1rem;color:var(--accent);font-size:.85rem;font-weight:600;">抓取新聞記錄中...</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.plot.ly/plotly-2.35.2.min.js"></script>
  <script src="/eco_sys/assets/js/app.js"></script>
  <script>
  let _currentCompany = '';
  let _currentYear    = 0;
  let _allNews        = [];
  let _yearStats      = [];

  function quickLoad(name) {
    document.getElementById('newsSearchInput').value = name;
    searchNews();
  }

  async function searchNews(force = false) {
    const company = document.getElementById('newsSearchInput').value.trim();
    if (!company) { toast('請輸入欲查詢的公司名稱或股票代碼', 'error'); return; }

    _currentCompany = company;
    _currentYear    = 0;

    const btn = document.getElementById('searchBtn');
    document.getElementById('resultsArea').style.display = 'block';
    document.getElementById('pieLoader').classList.add('active');
    document.getElementById('newsLoader').classList.add('active');
    btn.disabled = true;

    try {
      const fp  = force ? '&force=1' : '';
      const data = await apiFetch(`/eco_sys/api/news_load.php?company=${encodeURIComponent(company)}${fp}`);
      if (data.error) { toast(data.error, 'error'); document.getElementById('resultsArea').style.display = 'none'; return; }

      _allNews   = data.news   || [];
      _yearStats = data.year_stats || [];

      renderSentimentPie(data.sentiment_distribution);
      renderYearStats(_yearStats, data.available_years || []);
      renderNewsByMonth(_allNews, 0);

      document.getElementById('newsListTitle').textContent = `📡 ${company}`;
    } catch(e) {
      toast('連線中斷或錯誤：' + e.message, 'error');
    } finally {
      btn.disabled = false;
      document.getElementById('pieLoader').classList.remove('active');
      document.getElementById('newsLoader').classList.remove('active');
    }
  }

  // ── 日期解析輔助（Google RSS: "Wed, 17 Dec 2025 00:00:00 GMT"）──
  const MONTH_EN = {Jan:1,Feb:2,Mar:3,Apr:4,May:5,Jun:6,Jul:7,Aug:8,Sep:9,Oct:10,Nov:11,Dec:12};
  function parsePubDate(pub, fallbackYear) {
    if (!pub) return { year: fallbackYear || 0, month: 0 };
    // 格式："Wed, 17 Dec 2025 ..." 或 "17 Dec 2025"
    const m1 = pub.match(/(\d{1,2})\s+([A-Za-z]{3})\s+(\d{4})/);
    if (m1) return { year: parseInt(m1[3]), month: MONTH_EN[m1[2]] || 0 };
    // 格式："2025-09-10" 或 "2025/09/10"
    const m2 = pub.match(/(\d{4})[-/](\d{2})/);
    if (m2) return { year: parseInt(m2[1]), month: parseInt(m2[2]) };
    // 格式："2025年9月"
    const m3 = pub.match(/(\d{4})年(\d{1,2})月/);
    if (m3) return { year: parseInt(m3[1]), month: parseInt(m3[2]) };
    // 嘗試使用 JS 原生 Date 解析作為備份
    const d = new Date(pub);
    if (!isNaN(d.getTime())) {
      return { year: d.getFullYear(), month: d.getMonth() + 1 };
    }
    return { year: fallbackYear || 0, month: 0 };
  }
  function getNewsYear(n) {
    const ry = parseInt(n.report_year);
    if (ry > 2000) return ry;
    const pubYear = parseInt(parsePubDate(n.published, 0).year);
    if (pubYear > 2000) return pubYear;
    return 0;
  }

  // ── 年份篩選 ──
  function filterByYear(year) {
    const targetYear = parseInt(year) || 0;
    _currentYear = targetYear;
    document.querySelectorAll('.year-tab').forEach(t => {
      const ty = parseInt(t.dataset.year) || 0;
      t.classList.toggle('active', ty === targetYear);
    });
    document.querySelectorAll('.year-stat-row').forEach(r => {
      const ry = parseInt(r.dataset.year) || 0;
      r.classList.toggle('active', ry === targetYear);
    });
    // 篩選時也用 getNewsYear 回退
    const filtered = targetYear === 0 ? _allNews : _allNews.filter(n => getNewsYear(n) === targetYear);
    renderNewsByMonth(filtered, targetYear);
    const pos = filtered.filter(n => n.sentiment === 'Positive').length;
    const neg = filtered.filter(n => n.sentiment === 'Negative').length;
    const neu = filtered.length - pos - neg;
    const tot = Math.max(1, filtered.length);
    renderSentimentPie({
      Positive: Math.round(pos/tot*1000)/10,
      Neutral:  Math.round(neu/tot*1000)/10,
      Negative: Math.round(neg/tot*1000)/10,
    });
  }

  // ── 年份統計列表 ──
  function renderYearStats(yearStats, years) {
    const panel = document.getElementById('yearStatsPanel');
    const list  = document.getElementById('yearStatsList');
    const tabs  = document.getElementById('yearTabs');

    if (!years.length) { panel.style.display = 'none'; tabs.innerHTML = ''; return; }
    panel.style.display = 'block';

    // Year tabs
    let tabsHtml = `<div class="year-tab active" data-year="0" onclick="filterByYear(0)">全部年份</div>`;
    years.forEach(yr => {
      const ys = yearStats.find(s => parseInt(s.report_year) === parseInt(yr));
      const cnt = ys ? ys.total : '';
      tabsHtml += `<div class="year-tab" data-year="${yr}" onclick="filterByYear(${yr})">${yr}<span class="yt-badge">${cnt}</span></div>`;
    });
    tabs.innerHTML = tabsHtml;

    // Year stats rows
    const maxTotal = Math.max(...yearStats.map(s => parseInt(s.total)), 1);
    let html = '';
    yearStats.forEach(ys => {
      const yr    = ys.report_year;
      const total = parseInt(ys.total);
      const pos   = parseInt(ys.pos);
      const neg   = parseInt(ys.neg);
      const pct   = Math.round(total / maxTotal * 100);
      const posP  = Math.round(pos / Math.max(1, total) * 100);
      const negP  = Math.round(neg / Math.max(1, total) * 100);
      const sentiment_color = posP > negP ? '#00E676' : (negP > posP ? '#ff4757' : '#2979FF');
      html += `
        <div class="year-stat-row" data-year="${yr}" onclick="filterByYear(${yr})">
          <span style="font-size:.85rem;font-weight:700;color:var(--text);width:42px">${yr}</span>
          <div class="ys-bar-wrap">
            <div class="ys-bar" style="width:${pct}%;background:${sentiment_color}"></div>
          </div>
          <span style="font-size:.75rem;color:var(--muted);min-width:54px;text-align:right">
            ${total}篇 <span style="color:${sentiment_color}">${posP > negP ? '↑正' : (negP > posP ? '↓負' : '=中')}</span>
          </span>
        </div>`;
    });
    list.innerHTML = html;
  }

  // ── 月份分組顯示 ──
  function renderNewsByMonth(news, filterYear) {
    const container = document.getElementById('newsList');
    const count     = document.getElementById('newsCount');
    count.textContent = `共 ${news.length} 則`;
    container.innerHTML = '';

    if (!news.length) {
      container.innerHTML = '<div style="color:var(--muted);padding:3rem;text-align:center;">📭 此年份尚無新聞記錄<br><small>上傳 PDF 後系統將自動抓取對應年度新聞</small></div>';
      return;
    }

    // 按年份 + 月份分組，key = "YYYY_MM"
    const groups = {};
    news.forEach(n => {
      const yr  = getNewsYear(n);
      const { month } = parsePubDate(n.published, yr);
      const key = `${yr || 0}_${String(month || 0).padStart(2,'0')}`;
      if (!groups[key]) groups[key] = { year: yr, month: month || 0, items: [] };
      groups[key].items.push(n);
    });

    // 排序：年份降序，月份降序
    const sortedKeys = Object.keys(groups).sort((a, b) => {
      const [ya, ma] = a.split('_').map(Number);
      const [yb, mb] = b.split('_').map(Number);
      return ya !== yb ? yb - ya : mb - ma;
    });

    sortedKeys.forEach(key => {
      const g = groups[key];
      const posN = g.items.filter(i => i.sentiment === 'Positive').length;
      const negN = g.items.filter(i => i.sentiment === 'Negative').length;
      const sentCol = posN > negN ? '#00E676' : (negN > posN ? '#ff4757' : '#2979ff');
      const yrLabel = g.year > 0 ? g.year : '年份未知';
      const moLabel = g.month > 0 ? `${g.month}月` : '月份未知';

      let html = `
        <div class="month-group">
          <div class="month-header">
            <span class="month-badge">${yrLabel} ${moLabel}</span>
            <span style="color:${sentCol};font-size:.72rem">▲${posN} ▼${negN}</span>
          </div>`;

      g.items.forEach(n => {
        const s = n.sentiment || 'Neutral';
        const sLabel = s === 'Positive' ? '正面' : (s === 'Negative' ? '負面' : '中立');
        const isValidation = !!n.action_context;
        // 顯示日期：取 published 中區段
        const pubParsed = parsePubDate(n.published, 0);
        const pubLabel  = pubParsed.year > 0
          ? `${pubParsed.year}-${String(pubParsed.month).padStart(2,'0')}`
          : (n.published || '').substring(5, 16);
        html += `
          <a class="news-card ${s}" href="${n.link || '#'}" target="_blank">
            ${isValidation ? `<div style="font-size:.7rem;color:#a29bfe;margin-bottom:.4rem;">🔬 承諾驗證新聞</div>` : ''}
            <div class="nc-title">${n.title}</div>
            <div class="nc-meta">
              <span class="nc-sentiment ${s}">${sLabel}</span>
              <span style="font-weight:600;color:var(--text);opacity:.8">準確度: ${(parseFloat(n.confidence||0)*100).toFixed(1)}%</span>
              ${pubLabel ? `<span>${pubLabel}</span>` : ''}
              ${isValidation
                ? `<span class="nc-action-tag" title="${n.action_context||''}">📌 ${(n.action_context||'').substring(0,40)}...</span>`
                : (n.search_query ? `<span class="nc-query-tag">🔍 ${(n.search_query||'').substring(0,30)}...</span>` : '')
              }
            </div>
          </a>`;
      });

      html += '</div>';
      container.insertAdjacentHTML('beforeend', html);
    });
  }

  function renderSentimentPie(dist) {
    const trace = {
      values: [dist.Positive, dist.Neutral, dist.Negative],
      labels: ['正面', '中立', '負面'],
      type: 'pie', hole: 0.45,
      marker: { colors: ['#00E676', '#2979FF', '#ff4757'] },
      textinfo: 'percent', hoverinfo: 'label+percent',
    };
    const layout = {
      paper_bgcolor: 'rgba(0,0,0,0)', plot_bgcolor: 'rgba(0,0,0,0)',
      font: { color: '#8892b0', family: 'Inter' },
      margin: { l: 10, r: 10, b: 10, t: 10 }, showlegend: true,
      legend: { orientation: 'h', y: -0.12 }
    };
    Plotly.react('sentimentPie', [trace], layout, { displayModeBar: false, responsive: true });
    document.getElementById('pieStats').innerHTML =
      `正面 ${dist.Positive}% · 中立 ${dist.Neutral}% · 負面 ${dist.Negative}%`;
  }

  async function deleteHistory(symbol, el) {
    if (!confirm('確定要刪除此公司的搜尋歷史記錄嗎？')) return;
    try {
      const res = await apiFetch(`/eco_sys/api/news_delete_history.php?symbol=${symbol}`);
      if (res.success) { el.closest('.history-tag').remove(); toast('已刪除歷史記錄', 'success'); }
      else toast(res.error || '刪除失敗', 'error');
    } catch(e) { toast('刪除出錯', 'error'); }
  }
  </script>
</body>
</html>