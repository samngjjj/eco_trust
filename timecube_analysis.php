<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config.php';

$activePage = 'timecube';
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>ESG 走勢氣泡分析 — Eco Trust AI</title>
    <link rel="stylesheet" href="/eco_sys/assets/css/main.css">
    <!-- Plotly.js -->
    <script src="https://cdn.plot.ly/plotly-2.35.2.min.js"></script>
    <style>
        body { margin: 0; overflow: hidden; }
        .page-wrap { padding: 0; max-width: none; }
               .timecube-wrapper {
            position: relative;
            width: 100vw;
            height: calc(100vh - var(--header-h));
            background: var(--bg);
            display: flex;
            flex-direction: column;
        }

        #timecubeGraph {
            flex: 1;
            width: 100%;
        }

        .floating-controls {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 10;
            background: var(--card2);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            width: 320px;
            box-shadow: var(--shadow-lg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        
        .floating-controls h2 {
            font-size: 1.1rem;
            margin-bottom: 0.2rem;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .control-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .control-group label {
            font-weight: 600;
            color: var(--text-sub);
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .control-group input, .control-group select {
            padding: 0.6rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.03);
            color: var(--text);
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s;
        }

        .multi-select-container {
            max-height: 160px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.5rem;
            background: rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }
        
        .multi-select-container::-webkit-scrollbar { width: 4px; }
        .multi-select-container::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

        .multi-select-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.2rem 0;
        }
        
        .multi-select-item label {
            font-size: 0.85rem;
            color: var(--text);
            cursor: pointer;
            text-transform: none;
        }

        .generate-btn {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: #fff;
            border: none;
            padding: 0.8rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 1.5rem;
            width: 100%;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }
        .generate-btn:hover {
            filter: brightness(1.2);
            transform: translateY(-2px);
        }

        #noDataMessage {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            color: var(--muted);
            font-size: 1.1rem;
            pointer-events: none;
            background: rgba(15,17,23,0.7);
            padding: 1rem 2rem;
            border-radius: 12px;
            border: 1px solid var(--border2);
            z-index: 5;
        }

        /* ── iOS Style Toggle ── */
        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider-toggle {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(255,255,255,0.1);
            transition: .3s;
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.15);
        }
        .slider-toggle:before {
            position: absolute;
            content: "";
            height: 18px; width: 18px;
            left: 2px; bottom: 2px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        input:checked + .slider-toggle {
            background-color: #34c759; /* iOS Green */
            border-color: #34c759;
        }
        input:checked + .slider-toggle:before {
            transform: translateX(20px);
        }
        .switch-label {
            font-size: 0.82rem;
            color: var(--accent2);
            font-weight: 600;
            margin-bottom: 0 !important;
            cursor: pointer;
        }


        /* ── Info Panel ──────────────────────────────────────── */
        .info-toggle-btn {
            position: absolute;
            bottom: 16px;
            right: 16px;
            background: linear-gradient(135deg, #6c63ff, #00d2ff);
            border: none;
            border-radius: 50px;
            padding: 0.5rem 1rem;
            color: #fff;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            box-shadow: 0 4px 14px rgba(108, 99, 255, 0.4);
            transition: all 0.25s;
            z-index: 20;
        }
        .info-toggle-btn:hover { filter: brightness(1.15); transform: translateY(-2px); }

        .info-panel {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 360px;
            max-height: 90vh;
            background: rgba(15, 17, 28, 0.97);
            border: 1px solid rgba(108, 99, 255, 0.35);
            border-radius: 20px 0 0 0;
            box-shadow: -8px -8px 40px rgba(0,0,0,0.5);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            z-index: 15;
            display: flex;
            flex-direction: column;
            transform: translateX(110%);
            transition: transform 0.45s cubic-bezier(0.23, 1, 0.32, 1);
            overflow: hidden;
        }
        .info-panel.open { transform: translateX(0); }

        .info-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.2rem 1.4rem 0.8rem;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            flex-shrink: 0;
        }
        .info-panel-header h3 {
            font-size: 1rem;
            background: linear-gradient(135deg, #a29bfe, #00d2ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }
        .info-close-btn {
            background: rgba(255,255,255,0.06);
            border: none;
            border-radius: 50%;
            width: 28px; height: 28px;
            cursor: pointer;
            color: var(--muted);
            font-size: 1rem;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s;
        }
        .info-close-btn:hover { background: rgba(255,71,87,0.2); color: #ff4757; }

        .info-panel-body {
            overflow-y: auto;
            padding: 1.2rem 1.4rem 1.6rem;
            flex: 1;
        }
        .info-panel-body::-webkit-scrollbar { width: 4px; }
        .info-panel-body::-webkit-scrollbar-thumb { background: rgba(108,99,255,0.4); border-radius: 2px; }

        .info-section { margin-bottom: 1.4rem; }
        .info-section h4 {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--accent2);
            margin: 0 0 0.7rem;
        }
        .info-item {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            margin-bottom: 0.65rem;
        }
        .info-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .info-icon.blue  { background: rgba(41,121,255,0.15); }
        .info-icon.green { background: rgba(46,213,115,0.15); }
        .info-icon.red   { background: rgba(255,71,87,0.15); }
        .info-icon.purple { background: rgba(108,99,255,0.15); }
        .info-icon.teal  { background: rgba(0,210,255,0.15); }

        .info-text strong {
            display: block;
            font-size: 0.85rem;
            color: var(--text);
            margin-bottom: 0.1rem;
        }
        .info-text span {
            font-size: 0.78rem;
            color: var(--muted);
            line-height: 1.5;
        }

        .score-bar-wrap {
            margin-top: 0.8rem;
        }
        .score-bar-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.7rem;
            color: var(--muted);
            margin-bottom: 4px;
        }
        .score-bar-track {
            height: 10px;
            border-radius: 10px;
            background: linear-gradient(90deg,
                #ff4757 0%, #ff4757 30%,
                #ffa502 30%, #ffa502 55%,
                #2ed573 55%, #2ed573 100%);
            position: relative;
        }
        .score-bar-zones {
            display: flex;
            justify-content: space-between;
            font-size: 0.68rem;
            margin-top: 4px;
        }
        .zone-low  { color: #ff4757; }
        .zone-mid  { color: #ffa502; }
        .zone-high { color: #2ed573; }

        .bubble-legend {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.6rem;
            flex-wrap: wrap;
        }
        .legend-bubble {
            border-radius: 50%;
            background: rgba(108,99,255,0.5);
            border: 1px solid rgba(108,99,255,0.7);
            flex-shrink: 0;
        }

        .info-divider {
            height: 1px;
            background: rgba(255,255,255,0.06);
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="page-wrap">
        <div class="timecube-wrapper">
            <div id="timecubeGraph"></div>
            <div id="noDataMessage">請於左側選擇公司並點擊「執行 2D 泡泡分析」</div>
            
            <div class="floating-controls">
                <h2>🫧 ESG 走勢氣泡分析</h2>
                <div style="font-size:0.75rem; color:var(--muted); margin-bottom: 1rem;">2D 多維度風險分析</div>
                
                <div class="control-group">
                    <label>🏢 對比公司</label>
                    <div class="multi-select-container" id="companySelectList">
                        <div style="color:var(--muted); font-size: 0.8rem; padding: 0.5rem;">載入中...</div>
                    </div>
                </div>
                
                <div class="control-group">
                    <label>🚨 高獲利閾值 (ROE %)</label>
                    <input type="number" id="roeThreshold" value="3" step="1" onchange="generateGraph()">
                </div>
                
                <div class="control-group">
                    <label>⚠️ 低信心閾值 (Confidence Threshold)</label>
                    <input type="number" id="confThreshold" value="0.85" step="0.01" onchange="generateGraph()">
                </div>

                <div class="control-group" style="padding-top:1.2rem; margin-top:0.5rem; border-top:1px solid rgba(255,255,255,0.06); flex-direction:row; align-items:center; justify-content:space-between;">
                    <label class="switch-label" onclick="document.getElementById('newsWeighted').click()">使用 新聞加權</label>
                    <label class="switch">
                        <input type="checkbox" id="newsWeighted" onchange="generateGraph()">
                        <span class="slider-toggle"></span>
                    </label>
                </div>

                <div class="control-group" style="margin-top:1.2rem;">
                    <button class="generate-btn" onclick="generateGraph()">🚀 執行 2D 泡泡分析</button>
                </div>






                <!-- Info toggle button inside controls panel -->
                <button class="info-toggle-btn" style="position:static; margin-top:0.8rem; width:100%; justify-content:center;" onclick="toggleInfoPanel()">
                    📖 圖表說明 &amp; 分數含義
                </button>
            </div>

            <!-- ── Sliding Info Panel ──────────────────────────── -->
            <div class="info-panel" id="infoPanel">
                <div class="info-panel-header">
                    <h3>📊 圖表說明 &amp; 分數解讀</h3>
                    <button class="info-close-btn" onclick="toggleInfoPanel()" title="關閉">✕</button>
                </div>
                <div class="info-panel-body">

                    <!-- Section 1: Axes -->
                    <div class="info-section">
                        <h4>📐 座標軸說明</h4>
                        <div class="info-item">
                            <div class="info-icon blue">X</div>
                            <div class="info-text">
                                <strong>X 軸 — 年份 (Year)</strong>
                                <span>顯示 2022～2024 年間的數據點，每個點代表該公司在該年度的表現快照。</span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon purple">Y</div>
                            <div class="info-text">
                                <strong>Y 軸 — 誠信信心得分 (Confidence Score)</strong>
                                <span>由 FinBERT 模型分析 ESG 永續報告所產生的綜合誠信分數，範圍 0.0 ～ 1.0。分數越高代表 ESG 揭露品質越佳、誠信度越高。</span>
                            </div>
                        </div>
                    </div>

                    <div class="info-divider"></div>

                    <!-- Section 2: Score scale -->
                    <div class="info-section">
                        <h4>🎯 信心分數區間說明</h4>
                        <div class="score-bar-wrap">
                            <div class="score-bar-labels"><span>0.0</span><span>0.3</span><span>0.55</span><span>1.0</span></div>
                            <div class="score-bar-track"></div>
                            <div class="score-bar-zones">
                                <span class="zone-low">低誠信</span>
                                <span class="zone-mid">中等</span>
                                <span class="zone-high">高誠信</span>
                            </div>
                        </div>
                        <div class="info-item" style="margin-top:0.9rem">
                            <div class="info-icon red">↓</div>
                            <div class="info-text">
                                <strong>0.0 – 0.3 低誠信區</strong>
                                <span>ESG 揭露不足或未發布永續報告，風險警示。</span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon" style="background:rgba(255,165,2,0.15)">~</div>
                            <div class="info-text">
                                <strong>0.3 – 0.55 中等區</strong>
                                <span>部分揭露，但數據實質性或 KPI 多樣性有待提升。</span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon green">↑</div>
                            <div class="info-text">
                                <strong>0.55 – 1.0 高誠信區</strong>
                                <span>充分揭露、有第三方確信或豐富量化指標，具高度可信度。</span>
                            </div>
                        </div>
                    </div>

                    <div class="info-divider"></div>

                    <!-- Section 3: Bubble size -->
                    <div class="info-section">
                        <h4>🫧 氣泡大小含義</h4>
                        <div class="bubble-legend">
                            <div class="legend-bubble" style="width:18px;height:18px;"></div>
                            <span style="font-size:0.75rem;color:var(--muted);">ROE 低</span>
                            <div class="legend-bubble" style="width:28px;height:28px;"></div>
                            <span style="font-size:0.75rem;color:var(--muted);">ROE 中</span>
                            <div class="legend-bubble" style="width:44px;height:44px;"></div>
                            <span style="font-size:0.75rem;color:var(--muted);">ROE 高</span>
                        </div>
                        <p style="font-size:0.78rem;color:var(--muted);margin-top:0.6rem;line-height:1.6;">
                            氣泡直徑反映 <strong style="color:var(--text)">股東權益報酬率 (ROE%)</strong> 的絕對值大小。
                            氣泡越大代表獲利能力越強；若氣泡呈<span style="color:#ff4757">紅色</span>則表示 ROE 為負（虧損）。
                        </p>
                    </div>

                    <div class="info-divider"></div>

                    <!-- Section 4: Risk zone -->
                    <div class="info-section">
                        <h4>⚠️ 風險區域說明</h4>
                        <div class="info-item">
                            <div class="info-icon red">⚡</div>
                            <div class="info-text">
                                <strong>低信心風險區（紅色背景）</strong>
                                <span>Y 軸低於「低信心閾值」的區域（預設 0.40）。落於此區的公司 ESG 揭露度偏低，值得特別留意。</span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon red">🚨</div>
                            <div class="info-text">
                                <strong>高獲利 / 低誠信雙重警示</strong>
                                <span>當 ROE 超過高獲利閾值（預設 3%）且信心分低於低信心閾值時，系統會標記「⚠️ 高獲利/低誠信風險」，代表公司帳面績效亮眼但 ESG 透明度不足，為潛在信號。</span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon green">✅</div>
                            <div class="info-text">
                                <strong>正常信心區（綠色標示）</strong>
                                <span>高於閾值的區域代表公司 ESG 誠信表現達到基本標準。</span>
                            </div>
                        </div>
                    </div>

                    <div class="info-divider"></div>

                    <!-- Section 5: Model -->
                    <div class="info-section">
                        <h4>🤖 模型說明</h4>
                        <div class="info-item">
                            <div class="info-icon teal">🧠</div>
                            <div class="info-text">
                                <strong>FinBERT-Tone 中文情感模型</strong>
                                <span>基於 <code style="background:rgba(255,255,255,0.08);padding:1px 4px;border-radius:3px;">yiyanghkust/finbert-tone-chinese</code> 模型，結合數字密度、KPI 多樣性與外部風險係數，透過 Sigmoid 函數計算最終信心得分，有效放大好壞公司之間的差距。</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        let availableCompanies = [];

        // Helper function for clamping
        function minVal(a, b) { return a < b ? a : b; }

        async function loadCompanies() {

            try {
                const response = await fetch('/eco_sys/api/timecube_companies.php');
                availableCompanies = await response.json();
                
                const listContainer = document.getElementById('companySelectList');
                listContainer.innerHTML = '';
                
                if (availableCompanies.length === 0) {
                    listContainer.innerHTML = '<div style="color:var(--muted); font-size: 0.8rem; padding: 0.5rem;">無符合條件的公司</div>';
                    return;
                }
                
                const selectAllDiv = document.createElement('div');
                selectAllDiv.className = 'multi-select-item';
                selectAllDiv.style.borderBottom = '1px solid rgba(255,255,255,0.1)';
                selectAllDiv.style.paddingBottom = '0.4rem';
                selectAllDiv.style.marginBottom = '0.4rem';
                selectAllDiv.innerHTML = `
                    <input type="checkbox" id="selectAllCompanies" onchange="toggleAllCompanies(this)">
                    <label for="selectAllCompanies" style="font-weight:700; color:var(--accent2)">全選 所有公司</label>
                `;
                listContainer.appendChild(selectAllDiv);

                availableCompanies.forEach(company => {
                    const div = document.createElement('div');
                    div.className = 'multi-select-item';
                    div.innerHTML = `
                        <input type="checkbox" class="company-checkbox" value="${company.symbol}" id="comp_${company.symbol}" onchange="generateGraph()">
                        <label for="comp_${company.symbol}">${company.symbol} - ${company.name}</label>
                    `;
                    listContainer.appendChild(div);
                });
            } catch (error) {
                console.error("Error loading companies:", error);
            }
        }

        function toggleAllCompanies(checkbox) {
            const checkboxes = document.querySelectorAll('.company-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
            generateGraph();
        }

        async function generateGraph() {
            const selectedBoxes = document.querySelectorAll('.company-checkbox:checked');
            const selectedIds = Array.from(selectedBoxes).map(cb => cb.value);
            
            const graphDiv = document.getElementById('timecubeGraph');
            const noDataDiv = document.getElementById('noDataMessage');

            if (selectedIds.length === 0) {
                graphDiv.style.opacity = '0';
                noDataDiv.style.display = 'block';
                return;
            }
            
            graphDiv.style.opacity = '1';
            noDataDiv.style.display = 'none';

            let roeThresh = parseFloat(document.getElementById('roeThreshold').value) || 3;
            let confThreshInput = parseFloat(document.getElementById('confThreshold').value) || 0.85;
            let confThresh = confThreshInput > 1 ? confThreshInput / 100 : confThreshInput;

            try {
                const weighted = document.getElementById('newsWeighted').checked ? '&weighted=1' : '';
                const response = await fetch('/eco_sys/api/timecube_data.php?ids=' + selectedIds.join(',') + weighted);
                const data = await response.json();




                
                if (data.error) {
                    alert("Error fetching data: " + data.error);
                    return;
                }

                const traces = [];
                const colorPalette = ['#6c63ff', '#00d2ff', '#2ed573', '#ffa502', '#a29bfe', '#fab1a0'];
                const annotations = [];

                data.forEach((companyData, index) => {
                    const themeColor = colorPalette[index % colorPalette.length];
                    
                    // 強制放大氣泡，讓 ROE 的差異肉眼更直觀
                    const markerSizes = companyData.roe.map(r => Math.max(Math.sqrt(Math.abs(r)) * 18, 20)); 
                    const markerColors = companyData.roe.map((r, i) => {
                        if (r < 0) return '#ff4757'; // 負數 ROE 顯示紅色
                        return themeColor; // 正數則使用公司主題色
                    });

                    // Risk Logic & Glow/Labels
                    companyData.year.forEach((year, i) => {
                        const r = companyData.roe[i];
                        const s = companyData.score[i];
                        const isRisk = (r > roeThresh && s < confThresh);


                        
                        if (isRisk) {
                            annotations.push({
                                x: year,
                                y: s,
                                text: '⚠️ 高獲利/低誠信風險',
                                showarrow: true,
                                arrowhead: 2,
                                ax: 0,
                                ay: -40,
                                bgcolor: 'rgba(255, 71, 87, 0.9)',
                                bordercolor: '#ff4757',
                                font: { color: 'white', size: 10 }
                            });
                        }
                    });

                    traces.push({
                        x: companyData.year,
                        y: companyData.score,
                        mode: 'lines+markers',
                        type: 'scatter',
                        name: `${companyData.id} ${companyData.name}`,
                        line: { color: themeColor, width: 2, dash: 'dot' },
                        marker: {
                            size: markerSizes,
                            color: markerColors,
                            opacity: 0.7,
                            line: {
                                width: companyData.roe.map((r, i) => (r > roeThresh && companyData.score[i] < confThresh) ? 3 : 1),
                                color: companyData.roe.map((r, i) => (r > roeThresh && companyData.score[i] < confThresh) ? '#ff4757' : 'white')
                            }
                        },
                        text: companyData.roe.map((r, i) => `ROE: ${r}%<br>信心分: ${companyData.score[i]}<br>年份: ${companyData.year[i]}`),
                        hovertemplate: '<b>%{fullData.name}</b><br>%{text}<extra></extra>'
                    });


                });

                const layout = {
                    paper_bgcolor: 'rgba(0,0,0,0)',
                    plot_bgcolor: 'rgba(0,0,0,0)',
                    font: { color: '#e8eaf6', family: 'Inter' },
                    margin: { l: 420, r: 80, b: 80, t: 40 }, // 將左邊距從 60 拉大到 360，避開導覽方塊
                    xaxis: {
                        title: '年份 (Year)',
                        gridcolor: 'rgba(255,255,255,0.05)',
                        dtick: 1,
                        range: [2021.5, 2024.5], // 強制顯示 2022-2024 區影
                        tickvals: [2022, 2023, 2024]
                    },
                    yaxis: {
                        title: '誠信信心得分 (Confidence Score)',
                        gridcolor: 'rgba(255,255,255,0.05)',
                        range: [0, 1.1],
                        zeroline: false
                    },
                    shapes: [
                        // Low Confidence Zone Background
                        {
                            type: 'rect',
                            xref: 'paper', yref: 'y',
                            x0: 0, x1: 1,
                            y0: 0, y1: confThresh,
                            fillcolor: 'rgba(255, 71, 87, 0.05)',
                            line: { width: 0 },
                            layer: 'below'
                        },
                        // Thresh Line
                        {
                            type: 'line',
                            xref: 'paper', yref: 'y',
                            x0: 0, x1: 1,
                            y0: confThresh, y1: confThresh,
                            line: { color: 'rgba(255, 71, 87, 0.3)', width: 1, dash: 'dash' }
                        }
                    ],
                    annotations: [
                        ...annotations,
                        {
                            xref: 'paper', yref: 'y',
                            x: 0.98, y: confThresh - 0.05,
                            text: '⚠️ 低信心風險區 (Low Confidence Zone)',
                            showarrow: false,
                            font: { color: 'rgba(255, 71, 87, 0.8)', size: 11 },
                            xanchor: 'right'
                        },
                        {
                            xref: 'paper', yref: 'y',
                            x: 0.98, y: confThresh + 0.05,
                            text: '✅ 正常信心區 (Normal Confidence)',
                            showarrow: false,
                            font: { color: 'rgba(46, 213, 115, 0.8)', size: 11 },
                            xanchor: 'right'
                        }
                    ],
                    legend: { orientation: 'h', y: -0.2 },
                    hovermode: 'closest'
                };

                Plotly.newPlot('timecubeGraph', traces, layout, {responsive: true});
            } catch (error) {
                console.error("Error generating graph:", error);
            }
        }

        function toggleInfoPanel() {
            const panel = document.getElementById('infoPanel');
            panel.classList.toggle('open');
        }

        document.addEventListener('DOMContentLoaded', loadCompanies);
    </script>
</body>
</html>
