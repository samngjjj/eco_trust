<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config.php';
$activePage = 'dashboard';

$db = getDB();

// Get company list for the company picker
$companies = $db->query("SELECT c.symbol, c.name, i.name as industry 
                    FROM companies c 
                    LEFT JOIN industries i ON c.industry_id = i.id 
                    ORDER BY c.symbol")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>ESG 核心看板 — Eco Trust AI</title>
    <link rel="stylesheet" href="/eco_sys/assets/css/main.css">
    <style>
        /* ── Charts Grid ─────────────────────────────── */
        .charts-section{
            margin-top:1.5rem;
        }
        .charts-row-2x2{
            display:grid; grid-template-columns:1fr 1fr;
            gap:1.5rem;
        }
        @media(max-width:768px){ .charts-row-2x2{ grid-template-columns:1fr; } }

        /* ── Company Picker for Charts ───────────────── */
        .company-picker-bar{
            background:var(--card); border:1px solid var(--border2);
            border-radius:var(--radius-lg); padding:1.25rem 1.5rem;
            box-shadow:var(--shadow); margin-bottom:1.5rem;
        }
        .company-picker-bar h3{
            color:var(--text); font-size:1rem; margin-bottom:.75rem;
        }
        .company-checkboxes{
            display:flex; flex-wrap:wrap; gap:.5rem;
            max-height:150px; overflow-y:auto;
        }
        .company-checkboxes::-webkit-scrollbar{ width:4px; }
        .company-checkboxes::-webkit-scrollbar-thumb{ background:var(--border); border-radius:2px; }
        .company-chip{
            display:flex; align-items:center; gap:.3rem;
            padding:.3rem .65rem; border-radius:8px;
            background:rgba(255,255,255,.04); border:1px solid var(--border2);
            font-size:.8rem; color:var(--text-sub); cursor:pointer;
            transition:all .15s;
        }
        .company-chip:hover{ border-color:var(--accent); color:var(--text); }
        .company-chip.selected{
            background:rgba(108,99,255,.2); border-color:var(--accent);
            color:var(--accent2); font-weight:600;
        }
        .company-chip input{ display:none; }

        .picker-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
            align-items: center;
        }
        .picker-filter-input {
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.5rem 0.8rem;
            color: var(--text);
            font-size: 0.9rem;
            min-width: 200px;
        }
        .picker-select {
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.5rem 0.8rem;
            color: var(--text);
            font-size: 0.9rem;
            cursor: pointer;
        }
        
        .chart-card {
            background:var(--card); border:1px solid var(--border2);
            border-radius:var(--radius-lg); padding:1.5rem;
            box-shadow:var(--shadow);
            position:relative;
            display:flex;
            flex-direction:column;
        }
        .card-header {
            display:flex; justify-content:space-between; align-items:center;
            margin-bottom:1rem;
        }
        .card-title {
            font-weight:600; color:var(--text); font-size:1.05rem;
        }
        .danger-zone-label {
            font-size:0.8rem; color:var(--danger); background:rgba(255,71,87,0.1); padding:0.2rem 0.5rem; border-radius:4px;
        }
        
        .plotly-graph-div {
            width: 100%; height: 100%;
        }
        
        .empty-overlay {
            position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
            background:rgba(15,17,23,0.5); color:var(--muted); font-size:0.9rem;
            z-index:5; pointer-events:none; border-radius:inherit;
        }

        /* ── Missing Data Alert ── */
        .missing-data-alert {
            display: none;
            background: rgba(255, 71, 87, 0.1);
            border: 1px solid rgba(255, 71, 87, 0.3);
            border-radius: var(--radius-lg);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            animation: slide-in 0.4s ease-out;
        }
        .missing-data-alert h4 {
            color: var(--danger);
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }
        .missing-data-alert p {
            margin: 0;
            color: var(--text-sub);
            font-size: 0.85rem;
            line-height: 1.5;
        }
        .missing-list {
            font-weight: 600;
            color: var(--text);
            text-decoration: underline;
            text-underline-offset: 3px;
            color: #ff9f43;
        }
        @keyframes slide-in {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ── iOS Style Toggle ── */
        .switch {
            position: relative;
            display: inline-block;
            width: 38px;
            height: 20px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider-toggle {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(255,255,255,0.1);
            transition: .3s;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.15);
        }
        .slider-toggle:before {
            position: absolute;
            content: "";
            height: 14px; width: 14px;
            left: 2px; bottom: 2px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        input:checked + .slider-toggle {
            background-color: #34c759;
            border-color: #34c759;
        }
        input:checked + .slider-toggle:before {
            transform: translateX(18px);
        }
        .switch-label {
            font-size: 0.75rem;
            color: var(--accent2);
            font-weight: 600;
            margin-right: 0.5rem;
            cursor: pointer;
        }

    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="page-wrap">
    <div class="page-title">
        <h2>📊 ESG 核心看板</h2>
        <span class="badge">快速掃描與趨勢對照</span>
    </div>

    <!-- Company Picker -->
    <div class="company-picker-bar">
        <h3>🏢 選擇要顯示的公司 <small style="color:var(--muted);font-weight:400">（點選公司標籤以篩選圖表資料）</small></h3>
        
        <div class="picker-controls">
            <input type="text" class="picker-filter-input" id="companySearch" placeholder="🔍 搜尋代號或名稱..." oninput="filterChips()">
            
            <select class="picker-select" id="industryFilter" onchange="filterChips()">
                <option value="">📁 所有行業</option>
                <?php 
                $industries = array_unique(array_column($companies, 'industry'));
                sort($industries);
                foreach($industries as $ind): if(!$ind) continue;
                ?>
                <option value="<?= htmlspecialchars($ind) ?>"><?= htmlspecialchars($ind) ?></option>
                <?php endforeach; ?>
            </select>

            <div style="display:flex;gap:.8rem;margin-left:auto;align-items:center;">
                <div style="display:flex; align-items:center;  padding-right:1rem; border-right:1px solid rgba(255,255,255,0.1);">
                    <label class="switch-label" onclick="document.getElementById('newsWeighted').click()">新聞加權</label>
                    <label class="switch">
                        <input type="checkbox" id="newsWeighted" onchange="loadCharts()">
                        <span class="slider-toggle"></span>
                    </label>
                </div>
                <button class="btn btn-ghost btn-sm" onclick="selectAllCompanies()">全選</button>
                <button class="btn btn-ghost btn-sm" onclick="deselectAllCompanies()">取消全選</button>
                <button class="btn btn-primary btn-sm" onclick="loadCharts()">🔄 更新圖表</button>
            </div>


        </div>

        <div class="company-checkboxes" id="companyCheckboxes">
            <?php foreach($companies as $c): ?>
            <label class="company-chip" 
                   data-id="<?= htmlspecialchars($c['symbol']) ?>" 
                   data-name="<?= htmlspecialchars($c['name']) ?>"
                   data-industry="<?= htmlspecialchars($c['industry'] ?? '') ?>">
                <input type="checkbox" value="<?= htmlspecialchars($c['symbol']) ?>">
                <span><?= htmlspecialchars($c['symbol'].' '.$c['name']) ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Missing Data Alert Section -->
    <div id="missingDataAlert" class="missing-data-alert">
        <h4>⚠️ 偵測到數據缺漏</h4>
        <p>以下選定的公司尚未上傳或解析 ESG 報告，建議前往數據管理中心完成補件：<br>
           <span id="missingCompanyList" class="missing-list"></span>
        </p>
    </div>

    <!-- Grid Layout: Zone 1 & 2 on top, Zone 4 full width below -->
    <div class="charts-row-2x2">
        <!-- Zone 1: ROE Quarterly -->
        <div class="chart-card">
            <div class="card-header">
                <div style="display:flex; flex-direction:column;">
                    <span class="card-title">📊 季度 ROE (Zone 1)</span>
                    <small style="color:var(--muted)">柱狀圖對比</small>
                </div>
                <div style="display:flex; gap:0.5rem; align-items:center;">
                    <select id="startQuarter" class="picker-select" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;" onchange="loadCharts()">
                        <option value="">開始季度</option>
                    </select>
                    <span style="color:var(--muted); font-size:0.8rem;">~</span>
                    <select id="endQuarter" class="picker-select" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;" onchange="loadCharts()">
                        <option value="">結束季度</option>
                    </select>
                </div>
            </div>
            <div style="flex:1; position:relative; min-height:350px;">
                <div id="roeQuarterlyChart" class="plotly-graph-div"></div>
            </div>
        </div>

        <!-- Zone 2: Confidence Trend -->
        <div class="chart-card">
            <div class="card-header">
                <span class="card-title">📈 信心得分趨勢 (Zone 2)</span>
                <small style="color:var(--muted)">年份 vs 信心 (Spline)</small>
            </div>
            <div style="flex:1; position:relative; min-height:350px;">
                <div id="confTrendChart" class="plotly-graph-div"></div>
            </div>
        </div>
    </div>

    <!-- Zone 4: Full Width Analysis Area -->
    <div class="charts-section">
        <div class="chart-card">
            <div class="card-header">
                <span class="card-title">🏢 企業核心指標對比 (ROE vs 誠信信心)</span>
                <div style="display:flex; gap:1rem; align-items:center;">
                    <select id="zone3YearFilter" class="picker-select" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;" onchange="loadCharts()">
                        <option value="all">📅 所有年份對比</option>
                        <option value="2024">2024 年度</option>
                        <option value="2023">2023 年度</option>
                        <option value="2022">2022 年度</option>
                    </select>
                    <small style="color:var(--muted)">橫向對比所選企業之 誠信信心分 & ROE</small>
                </div>
            </div>
            <div style="flex:1; position:relative; min-height:500px;">
                <div id="industryBarChart" class="plotly-graph-div"></div>
            </div>
        </div>
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.plot.ly/plotly-2.35.2.min.js"></script>
<script src="/eco_sys/assets/js/app.js"></script>
<script>
// ── Company chip toggle ──
document.querySelectorAll('.company-chip input').forEach(input => {
    input.addEventListener('change', () => {
        input.closest('.company-chip').classList.toggle('selected', input.checked);
    });
});

function selectAllCompanies(){
    document.querySelectorAll('.company-chip').forEach(c => {
        const cb = c.querySelector('input');
        cb.checked = true;
        c.classList.add('selected');
    });
    loadCharts();
}
function deselectAllCompanies(){
    document.querySelectorAll('.company-chip').forEach(c => {
        const cb = c.querySelector('input');
        cb.checked = false;
        c.classList.remove('selected');
    });
    loadCharts();
}
function getSelectedIds(){
    return [...document.querySelectorAll('.company-chip input:checked')].map(c => c.value);
}

function filterChips(){
    const q = document.getElementById('companySearch').value.toLowerCase();
    const ind = document.getElementById('industryFilter').value;
    
    document.querySelectorAll('.company-chip').forEach(chip => {
        const name = (chip.dataset.name || '').toLowerCase();
        const code = (chip.dataset.id || '').toLowerCase();
        const industry = chip.dataset.industry || '';
        
        const matchQ = !q || name.includes(q) || code.includes(q);
        const matchInd = !ind || industry === ind;
        
        chip.style.display = (matchQ && matchInd) ? '' : 'none';
    });
}

// ── Color Palette ──
const PALETTE = [
    '#6c63ff','#00d2ff','#ff6b9d','#2ed573','#ffa502','#ff4757',
    '#74b9ff','#fdcb6e','#81ecec','#e84393','#a29bfe','#00cec9',
    '#fab1a0','#55efc4','#dfe6e9','#636e72'
];

let baseLayout = {
    paper_bgcolor: 'rgba(0,0,0,0)',
    plot_bgcolor: 'rgba(0,0,0,0)',
    font: { color: '#e0e0e0', family: 'Inter' },
    margin: { l: 40, r: 20, b: 60, t: 20 },
    legend: { orientation: 'h', y: -0.2 }
};

async function loadCharts(){
    if (typeof Plotly === 'undefined') {
        toast('Plotly 庫加載失敗，請檢查網路連線', 'error');
        return;
    }

    const ids = getSelectedIds();
    const param = ids.join(',');
    
    // Quarterly range filters
    const startQ = document.getElementById('startQuarter').value;
    const endQ = document.getElementById('endQuarter').value;

    // Global weights toggle
    const weighted = document.getElementById('newsWeighted').checked ? '&weighted=1' : '';
    
    try {
        let url = '/eco_sys/api/chart_data.php?ids=' + encodeURIComponent(param) + weighted;
        if(startQ) url += '&start_q=' + encodeURIComponent(startQ);
        if(endQ) url += '&end_q=' + encodeURIComponent(endQ);


        const data = await apiFetch(url);

        if(!data || data.error){
            toast(data.error || '無法取得圖表資料', 'error');
        } else {
            if (ids.length === 0) {
                showEmptyMessage('roeQuarterlyChart', '請選擇公司以開始分析');
                showEmptyMessage('confTrendChart', '請選擇公司以開始分析');
                showEmptyMessage('industryBarChart', '請選擇公司以開始分析');
            } else {
                hideEmptyMessage('roeQuarterlyChart');
                hideEmptyMessage('confTrendChart');
                hideEmptyMessage('industryBarChart');
            }

            // Populate Quarter selectors if empty
            updateQuarterSelectors(data.available_quarters || []);

            renderROEQuarterly(data.roe_trend || []);
            renderConfTrend(data.confidence_trend || []);
            renderIndustryComparison(data.scatter || []);

            // Check for missing data
            checkMissingReports(ids, data);
        }
    } catch(e) {
        toast('載入圖表時發生錯誤: ' + e.message, 'error');
    }
}

function updateQuarterSelectors(quarters) {
    const s1 = document.getElementById('startQuarter');
    const s2 = document.getElementById('endQuarter');
    
    // Only populate if they only have the default option
    if (s1.options.length <= 1) {
        const current1 = s1.value;
        const current2 = s2.value;
        
        // Clear except first
        while(s1.options.length > 1) s1.remove(1);
        while(s2.options.length > 1) s2.remove(1);
        
        quarters.forEach(q => {
            const opt1 = new Option(q, q);
            const opt2 = new Option(q, q);
            s1.add(opt1);
            s2.add(opt2);
        });
        
        s1.value = current1;
        s2.value = current2;
    }
}

function showEmptyMessage(chartId, msg) {
    const container = document.getElementById(chartId);
    if (!container) return;
    const wrap = container.closest('.chart-card');
    let overlay = wrap.querySelector('.empty-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'empty-overlay';
        wrap.appendChild(overlay);
    }
    overlay.textContent = msg;
    overlay.style.display = 'flex';
    container.style.visibility = 'hidden';
}

function checkMissingReports(selectedIds, data) {
    const alertDiv = document.getElementById('missingDataAlert');
    const listSpan = document.getElementById('missingCompanyList');
    
    if (!selectedIds || selectedIds.length === 0) {
        alertDiv.style.display = 'none';
        return;
    }

    // Map: company_code => Set of years present
    const dataPresenceMap = new Map();
    (data.confidence_trend || []).forEach(co => {
        const years = new Set();
        (co.series || []).forEach(s => years.add(parseInt(s.year)));
        dataPresenceMap.set(co.code.toString(), years); // 強制轉為字串進行比對
    });

    const targetYears = [2022, 2023, 2024];
    const missingDetails = [];

    selectedIds.forEach(id => {
        const idStr = id.toString(); // 強制轉為字串
        const presentYears = dataPresenceMap.get(idStr) || new Set();
        const missingFromTarget = targetYears.filter(y => !presentYears.has(y));
        
        if (missingFromTarget.length > 0) {
            const chip = document.querySelector(`.company-chip[data-id="${id}"]`);
            const name = chip ? (chip.dataset.name || id) : id;
            missingDetails.push(`<strong>${id} ${name}</strong> (<span style="color:#ff4757">欠缺 ${missingFromTarget.join(', ')} 年份</span>)`);
        }
    });

    if (missingDetails.length > 0) {
        listSpan.innerHTML = missingDetails.join('；');
        alertDiv.style.display = 'block';
    } else {
        alertDiv.style.display = 'none';
    }
}

function hideEmptyMessage(chartId) {
    const container = document.getElementById(chartId);
    if (!container) return;
    const wrap = container.closest('.chart-card');
    const overlay = wrap.querySelector('.empty-overlay');
    if (overlay) overlay.style.display = 'none';
    container.style.visibility = 'visible';
}

// ── Zone 1: ROE Quarterly (Bar Chart) ──
function renderROEQuarterly(data){
    let traces = [];
    data.forEach((co, idx) => {
        let xVals = [];
        let yVals = [];
        co.quarterly_series.forEach(s => {
            xVals.push(s.period);
            yVals.push(s.roe);
        });
        
        traces.push({
            x: xVals,
            y: yVals,
            name: `${co.code} ${co.name}`,
            type: 'bar',
            marker: { color: PALETTE[idx % PALETTE.length] }
        });
    });

    let layout = Object.assign({}, baseLayout, {
        barmode: 'group',
        xaxis: {
            title: '季度',
            tickangle: -45, 
            gridcolor: 'rgba(255,255,255,0.06)'
        },
        yaxis: {
            title: 'ROE (%)',
            gridcolor: 'rgba(255,255,255,0.06)'
        }
    });

    Plotly.react('roeQuarterlyChart', traces, layout, {responsive: true});
}

// ── Zone 2: Confidence Trend (Spline Line Chart) ──
function renderConfTrend(data){
    let traces = [];
    data.forEach((co, idx) => {
        let xVals = [];
        let yVals = [];
        co.series.sort((a,b)=>a.year - b.year).forEach(s => {
            xVals.push(s.year);
            yVals.push(s.confidence);
        });
        
        traces.push({
            x: xVals,
            y: yVals,
            name: `${co.code} ${co.name}`,
            type: 'scatter',
            mode: 'lines',
            line: { 
                shape: 'spline', 
                color: PALETTE[idx % PALETTE.length],
                width: 3
            }
        });
    });

    let layout = Object.assign({}, baseLayout, {
        xaxis: {
            title: '年份',
            gridcolor: 'rgba(255,255,255,0.06)',
            dtick: 1
        },
        yaxis: {
            title: '信心分數',
            gridcolor: 'rgba(255,255,255,0.06)',
            range: [0, 1]
        }
    });

    Plotly.react('confTrendChart', traces, layout, {responsive: true});
}

// ── Zone 4: Core Indicators Comparison (ROE vs Confidence) ──
function renderIndustryComparison(scatterData){
    const selectedYear = document.getElementById('zone3YearFilter').value;
    let companyStats = [];
    
    scatterData.forEach(item => {
        // Filter by year if not 'all'
        if (selectedYear !== 'all' && item.year.toString() !== selectedYear) {
            return;
        }

        companyStats.push({
            label: selectedYear === 'all' ? `${item.code} ${item.name}<br>(${item.year})` : `${item.code} ${item.name}`,
            roe: item.x,
            conf: item.y,
            code: item.code,
            year: item.year
        });
    });

    // Sort by code then year for better chronological grouping
    companyStats.sort((a,b) => {
        const codeA = String(a.code || '');
        const codeB = String(b.code || '');
        if (codeA !== codeB) return codeA.localeCompare(codeB);
        return a.year - b.year;
    });

    if(companyStats.length === 0){
        Plotly.react('industryBarChart', [], baseLayout);
        return;
    }

    let labels = companyStats.map(s => s.label);
    
    let traces = [
        {
            x: labels,
            y: companyStats.map(s => s.roe),
            name: 'ROE (%)',
            type: 'bar',
            marker: {
                color: 'rgba(0, 210, 255, 0.7)',
                line: { color: '#00d2ff', width: 1 }
            },
            offsetgroup: 1,
            hovertemplate: '%{x}<br>ROE: %{y}%<extra></extra>'
        },
        {
            x: labels,
            y: companyStats.map(s => s.conf),
            name: '誠信信心分 (0.0 - 1.0)',
            type: 'bar',
            marker: {
                color: 'rgba(46, 213, 115, 0.7)',
                line: { color: '#2ed573', width: 1 }
            },
            offsetgroup: 2,
            yaxis: 'y2', // Use second axis for better scaling visibility
            hovertemplate: '%{x}<br>信心分: %{y}<extra></extra>'
        }
    ];

    let layout = Object.assign({}, baseLayout, {
        barmode: 'group',
        bargap: 0.2,
        bargroupgap: 0.1,
        xaxis: {
            title: '',
            tickfont: { size: 10, color: '#b0b8d8' },
            gridcolor: 'rgba(255,255,255,0.03)'
        },
        yaxis: {
            title: 'ROE (%)',
            gridcolor: 'rgba(255,255,255,0.06)',
            side: 'left',
            zeroline: false
        },
        yaxis2: {
            title: '誠信信心分',
            overlaying: 'y',
            side: 'right',
            range: [0, 1.1],
            showgrid: false,
            color: '#2ed573',
            zeroline: false
        },
        legend: {
            orientation: 'h',
            y: -0.2,
            x: 0.5,
            xanchor: 'center',
            bgcolor: 'rgba(0,0,0,0)',
            font: { size: 11 }
        },
        margin: { l: 60, r: 60, b: 80, t: 20 }
    });

    Plotly.react('industryBarChart', traces, layout, {
        responsive: true,
        displayModeBar: false
    });
}

// ── Init ──
loadCharts();
</script>
</body>
</html>
