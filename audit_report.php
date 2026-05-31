<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config.php';
$activePage = 'audit';
$db = getDB();

// ── 取得參數 ──────────────────────────────────────────────
$id   = (int)($_GET['id'] ?? 0);
$row  = null;

if ($id) {
    $stmt = $db->prepare(
        "SELECT ce.*, c.name, c.symbol,
                (SELECT AVG(roe) FROM company_performance cp WHERE cp.company_symbol=ce.company_id AND cp.year=ce.year) as avg_roe
         FROM carbon_emissions ce
         LEFT JOIN companies c ON ce.company_id = c.symbol
         WHERE ce.id = ?"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
}

// ── 分析師修正 (POST) ────────────────────────────────────
$saveMsg = '';
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['analyst_comment'])) {
    $comment  = trim($_POST['analyst_comment']);
    $override = $_POST['analyst_score_override'] !== '' ? (float)$_POST['analyst_score_override'] : null;
    $upd = $db->prepare(
        "UPDATE carbon_emissions SET analyst_comment=?, analyst_score_override=? WHERE id=?"
    );
    $upd->bind_param('sdi', $comment, $override, $id);
    $upd->execute();
    $saveMsg = '✅ 修正已儲存';
    // 重新讀取
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
}

// ── 解析 JSON 欄位 ────────────────────────────────────────
$topicDist   = $row ? json_decode($row['topic_distribution']          ?? '{}', true) : [];
$highConf    = $row ? json_decode($row['high_confidence_commitments'] ?? '[]', true) : [];
$gen2Raw     = $row ? json_decode($row['raw_gen2_output']             ?? '{}', true) : [];

// 顯示分數：若分析師有覆寫則用覆寫值
$displayScore = $row['analyst_score_override'] ?? $row['confidence_score'] ?? null;
$quantRate    = $row['quant_rate']    ?? null;
$timeRate     = $row['timeframe_rate'] ?? null;
$totalProm    = $row['total_promises'] ?? null;

// ── 多公司的數據對比 ──────────────────────────────
$comparisonData = [];
if ($row) {
    $cmp = $db->prepare(
        "SELECT c.name, c.symbol, ce.confidence_score, ce.intent_score, ce.credibility_index, ce.numeracy_score, ce.kpi_count
         FROM carbon_emissions ce
         LEFT JOIN companies c ON ce.company_id = c.symbol
         WHERE ce.year = ?
         ORDER BY ce.confidence_score DESC"
    );
    $cmp->bind_param('i', $row['year']);
    $cmp->execute();
    $comparisonData = $cmp->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>ESG 誠信診斷報告 — <?= htmlspecialchars($row['name'] ?? '未知公司') ?> <?= $row['year'] ?? '' ?> — Eco Trust AI</title>
  <link rel="stylesheet" href="/eco_sys/assets/css/main.css">
  <style>
    .report-header { background: linear-gradient(135deg,rgba(41,121,255,.12),rgba(0,230,118,.08)); border:1px solid var(--border2); border-radius:var(--radius-lg); padding:2rem; margin-bottom:1.5rem; }
    .score-ring { display:flex; flex-direction:column; align-items:center; }
    .score-ring canvas { display:block; }
    .score-num { font-size:2.8rem; font-weight:800; background:linear-gradient(135deg,#2979FF,#00E676); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
    .gauge-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem; }
    .gauge-card { background:var(--card); border:1px solid var(--border2); border-radius:12px; padding:1.25rem; }
    .gauge-label { font-size:.8rem; color:var(--muted); text-transform:uppercase; margin-bottom:.5rem; }
    .gauge-bar-wrap { height:10px; background:rgba(255,255,255,.08); border-radius:6px; overflow:hidden; margin:.5rem 0; }
    .gauge-bar { height:100%; border-radius:6px; transition:width .8s ease; }
    .gauge-value { font-size:1.6rem; font-weight:700; }
    .gauge-hint  { font-size:.78rem; color:var(--muted); margin-top:.2rem; }
    .red-flag { background:rgba(255,71,87,.08); border-left:3px solid #ff4757; padding:.5rem .8rem; border-radius:4px; font-size:.85rem; color:#ff6b7a; margin-bottom:.4rem; }
    .green-flag{ background:rgba(0,230,118,.08); border-left:3px solid #00E676; padding:.5rem .8rem; border-radius:4px; font-size:.85rem; color:#00E676; margin-bottom:.4rem; }
    .commit-card { background:var(--card); border:1px solid var(--border2); border-radius:10px; padding:1rem; margin-bottom:.75rem; }
    .commit-meta { display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:.4rem; }
    .tag { font-size:.72rem; padding:2px 7px; border-radius:4px; font-weight:600; }
    .tag-e  { background:rgba(0,210,255,.15); color:#00d2ff; }
    .tag-s  { background:rgba(0,230,118,.15); color:#00E676; }
    .tag-g  { background:rgba(108,99,255,.15); color:#6c63ff; }
    .tag-hm { background:rgba(255,165,0,.15); color:#ffa502; }
    .tag-oth{ background:rgba(255,255,255,.08); color:var(--muted); }
    .tag-hi { background:rgba(0,230,118,.2); color:#00E676; border:1px solid rgba(0,230,118,.4); }
    .tag-me { background:rgba(255,165,0,.15); color:#ffa502; }
    .tag-lo { background:rgba(255,71,87,.12); color:#ff4757; }
    .disclaimer-box { margin-top:2.5rem; padding:1.25rem 1.5rem; border-top:1px solid var(--border); border-radius:0 0 var(--radius-lg) var(--radius-lg); background:rgba(255,71,87,.04); font-size:.82rem; color:var(--muted); line-height:1.7; }
    .disclaimer-box strong { color:#ff9f43; }
    .back-btn { display:inline-flex; align-items:center; gap:.4rem; text-decoration:none; color:var(--muted); font-size:.9rem; margin-bottom:1.5rem; transition:.2s; }
    .back-btn:hover { color:var(--text); }
    .analyst-box { background:var(--card); border:1px solid var(--border2); border-radius:var(--radius-lg); padding:1.5rem; margin-top:1.5rem; }
    .override-badge { font-size:.75rem; background:rgba(255,165,0,.15); color:#ffa502; border:1px solid rgba(255,165,0,.3); padding:2px 8px; border-radius:4px; margin-left:.5rem; }
    @media(max-width:768px){ .gauge-row{ grid-template-columns:1fr; } }
  </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="page-wrap">
  <a href="/eco_sys/index.php" class="back-btn">← 返回數據管理中心</a>

  <?php if (!$row): ?>
    <div class="card" style="text-align:center;padding:4rem;">
      <div style="font-size:3rem;margin-bottom:1rem;">🔍</div>
      <h3 style="color:var(--muted)">找不到此報告</h3>
      <p style="color:var(--muted);margin-top:.5rem">請從數據管理中心選擇有效的報告記錄。</p>
    </div>
  <?php else: ?>

  <!-- ── 報告標題區 ── -->
  <div class="report-header">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
      <div>
        <div style="font-size:.8rem;color:var(--muted);text-transform:uppercase;margin-bottom:.3rem;">ESG 誠信診斷報告 · Gen-2</div>
        <h2 style="font-size:1.8rem;font-weight:800;margin:0 0 .3rem;">
          <?= htmlspecialchars($row['name'] ?? '未知公司') ?>
          <span style="font-size:1rem;color:var(--muted);font-weight:400;">(<?= htmlspecialchars($row['symbol'] ?? $row['company_id']) ?>)</span>
          <?php if($row['analyst_score_override'] !== null): ?><span class="override-badge">⚙️ 已覆寫</span><?php endif; ?>
        </h2>
        <div style="color:var(--muted);font-size:.9rem;">報告年份：<strong style="color:var(--text)"><?= $row['year'] ?></strong> &nbsp;|&nbsp; 生成時間：<?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></div>
      </div>
      <div class="score-ring">
        <canvas id="scoreGauge" width="120" height="120"></canvas>
        <div class="score-num"><?= $displayScore !== null ? number_format($displayScore * 100, 1).'%' : '—' ?></div>
        <div style="font-size:.75rem;color:var(--muted);">誠信信心分</div>
      </div>
    </div>
  </div>

  <!-- ── 雷達圖 + 統計 ── -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">
    <div class="card">
      <div class="card-header"><span class="card-title">🕸️ ESG 主題分布雷達圖</span></div>
      <?php if ($topicDist): ?>
        <div id="radarChart" style="height:320px;"></div>
      <?php else: ?>
        <div style="text-align:center;padding:3rem;color:var(--muted);">此報告尚無主題分布數據（請重新上傳以獲取 Gen-2 數據）</div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">📋 報告摘要統計</span></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
        <?php
        $intent = $row['intent_score'] ?? 0;
        $intent_hint = $intent >= 0.5 ? '<span style="color:#00E676">表現優異 (偏高)</span>' : ($intent >= 0.2 ? '<span style="color:#ffa502">表現中等 (標準)</span>' : '<span style="color:#ff4757">表現偏弱 (偏低)</span>');

        $cred = $row['credibility_index'] ?? 0;
        $cred_hint = $cred >= 0.7 ? '<span style="color:#00E676">極具說服力 (偏高)</span>' : ($cred >= 0.4 ? '<span style="color:#ffa502">具備基礎 (中等)</span>' : '<span style="color:#ff4757">證據薄弱 (偏低)</span>');

        $num = $row['numeracy_score'] ?? 0;
        $num_hint = $num >= 0.08 ? '<span style="color:#00E676">數據詳實 (偏高)</span>' : ($num >= 0.04 ? '<span style="color:#ffa502">部分量化 (中等)</span>' : '<span style="color:#ff4757">缺乏數據 (偏低)</span>');

        $kpi = $row['kpi_count'] ?? 0;
        $kpi_hint = $kpi >= 40 ? '<span style="color:#00E676">指標豐富 (極多)</span>' : ($kpi >= 20 ? '<span style="color:#ffa502">指標普通 (適中)</span>' : '<span style="color:#ff4757">指標匱乏 (極少)</span>');

        $stats = [
          ['label'=>'意圖分數 (FinBERT)', 'val'=>isset($row['intent_score']) && $row['intent_score'] !== null ? number_format($row['intent_score'], 4) : '—', 'icon'=>'🧠', 'hint'=>$row['intent_score']!==null ? $intent_hint : null],
          ['label'=>'誠信可靠性', 'val'=>isset($row['credibility_index']) && $row['credibility_index'] !== null ? number_format($row['credibility_index'], 4) : '—', 'icon'=>'⚖️', 'hint'=>$row['credibility_index']!==null ? $cred_hint : null],
          ['label'=>'數字密度', 'val'=>isset($row['numeracy_score']) && $row['numeracy_score'] !== null ? number_format($row['numeracy_score'], 4) : '—', 'icon'=>'🔢', 'hint'=>$row['numeracy_score']!==null ? $num_hint : null],
          ['label'=>'關鍵指標多樣性', 'val'=>isset($row['kpi_count']) && $row['kpi_count'] !== null ? $row['kpi_count'] : '—', 'icon'=>'📈', 'hint'=>$row['kpi_count']!==null ? $kpi_hint : null],
          ['label'=>'平均ROE',     'val'=>$row['avg_roe'] !== null ? number_format($row['avg_roe'],2).'%' : '—', 'icon'=>'💰'],
          ['label'=>'誠信信心分',  'val'=>$displayScore !== null ? number_format($displayScore*100,1).'%' : '—', 'icon'=>'🛡️'],
        ];
        foreach($stats as $s): ?>
        <div style="background:rgba(255,255,255,.03);border:1px solid var(--border2);border-radius:10px;padding:.9rem;">
          <div style="font-size:1.1rem;margin-bottom:.2rem;"><?= $s['icon'] ?></div>
          <div style="font-size:.75rem;color:var(--muted);margin-bottom:.15rem;"><?= $s['label'] ?></div>
          <div style="font-size:1.15rem;font-weight:700;"><?= $s['val'] ?></div>
          <?php if(isset($s['hint']) && $s['hint'] !== null): ?>
          <div style="font-size:.7rem;margin-top:.4rem;padding-top:.4rem;border-top:1px solid rgba(255,255,255,.05);"><?= $s['hint'] ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- ── 業界多公司數據對比 ── -->
  <div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
      <span class="card-title">🏢 業界多公司數據對比（<?= $row['year'] ?> 年度）</span>
      <small style="color:var(--muted)">橫向比較其他公司的誠信與指標表現</small>
    </div>
    <?php if (empty($comparisonData)): ?>
      <div style="text-align:center;padding:2rem;color:var(--muted);">
        尚未有其他公司於 <?= $row['year'] ?> 年度的數據可供比對。
      </div>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.9rem;">
          <thead>
            <tr style="border-bottom:1px solid var(--border); color:var(--muted);">
              <th style="padding:1rem 0.5rem; font-weight:600;">公司名稱</th>
              <th style="padding:1rem 0.5rem; font-weight:600;">誠信信心分</th>
              <th style="padding:1rem 0.5rem; font-weight:600;">意圖分數</th>
              <th style="padding:1rem 0.5rem; font-weight:600;">誠信可靠性</th>
              <th style="padding:1rem 0.5rem; font-weight:600;">數字密度</th>
              <th style="padding:1rem 0.5rem; font-weight:600;">指標多樣性</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($comparisonData as $cmp): 
              $isCurrent = (string)$cmp['symbol'] === (string)$row['company_id'];
              $bg = $isCurrent ? 'background:rgba(41,121,255,0.08);' : 'border-bottom:1px solid var(--border2);';
            ?>
            <tr style="<?= $bg ?>">
              <td style="padding:1rem 0.5rem; font-weight:<?= $isCurrent?'700':'400' ?>; color:<?= $isCurrent?'#2979FF':'var(--text)' ?>;">
                <?= htmlspecialchars($cmp['name'] ?? '未知') ?> (<?= htmlspecialchars($cmp['symbol'] ?? '') ?>)
                <?php if($isCurrent) echo '<span style="font-size:0.7rem; background:#2979FF; color:#fff; padding:2px 6px; border-radius:4px; margin-left:6px;">當前</span>'; ?>
              </td>
              <td style="padding:1rem 0.5rem; font-weight:600; color:<?= ($cmp['confidence_score']??0)>=0.7 ? '#00E676' : 'var(--text)' ?>;">
                <?= $cmp['confidence_score'] !== null ? number_format($cmp['confidence_score']*100, 1).'%' : '—' ?>
              </td>
              <td style="padding:1rem 0.5rem;"><?= $cmp['intent_score'] !== null ? number_format($cmp['intent_score'], 4) : '—' ?></td>
              <td style="padding:1rem 0.5rem;"><?= $cmp['credibility_index'] !== null ? number_format($cmp['credibility_index'], 4) : '—' ?></td>
              <td style="padding:1rem 0.5rem;"><?= $cmp['numeracy_score'] !== null ? number_format($cmp['numeracy_score'], 4) : '—' ?></td>
              <td style="padding:1rem 0.5rem;"><?= $cmp['kpi_count'] !== null ? $cmp['kpi_count'] : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- ── 強制免責聲明 ── -->
  <div class="disclaimer-box">
    <p><strong>⚖️ 系統聲明 (Disclaimer)：</strong></p>
    <p>本報告由 <strong>EcoTrust AI</strong> 透過非結構化文本推理產生，<strong>不代表法律建議</strong>。分析範圍不包含 PDF 內之圖表、影像及原始財務報表之審核。使用者應對照原始 PDF 文件進行最終投資決策，<strong>本系統不對決策盈虧負責</strong>。本系統所提供之「誠信信心分數」係由 AI 模型基於公開資料自動解析生成，僅供內部決策參考，不保證其絕對正確性或及時性。</p>
  </div>

  <?php endif; ?>
</div>

<script src="https://cdn.plot.ly/plotly-2.35.2.min.js"></script>
<script>
// ── 半圓進度計 ──
(function(){
  const score = <?= $displayScore ?? 'null' ?>;
  const canvas = document.getElementById('scoreGauge');
  if (!canvas || score === null) return;
  const ctx = canvas.getContext('2d');
  const cx = 60, cy = 80, r = 50, start = Math.PI, end = start + Math.PI * score;
  ctx.clearRect(0,0,120,120);
  // BG arc
  ctx.beginPath(); ctx.arc(cx,cy,r,Math.PI,2*Math.PI);
  ctx.strokeStyle='rgba(255,255,255,.08)'; ctx.lineWidth=10; ctx.lineCap='round'; ctx.stroke();
  // Value arc
  const grad = ctx.createLinearGradient(0,0,120,0);
  grad.addColorStop(0,'#2979FF'); grad.addColorStop(1,'#00E676');
  ctx.beginPath(); ctx.arc(cx,cy,r,start,end);
  ctx.strokeStyle=grad; ctx.lineWidth=10; ctx.lineCap='round'; ctx.stroke();
})();

// ── 雷達圖 ──
<?php if ($topicDist): ?>
(function(){
  const labels = ['環境 (E)','社會 (S)','治理 (G)'];
  const vals   = [
    <?= (int)($topicDist['E']??0) ?>,
    <?= (int)($topicDist['S']??0) ?>,
    <?= (int)($topicDist['G']??0) ?>
  ];
  const data = [{
    type:'scatterpolar', r:vals, theta:labels, fill:'toself',
    fillcolor:'rgba(41,121,255,.2)', line:{color:'#2979FF',width:2},
    name:'ESG 主題承諾分布'
  }];
  const layout = {
    polar:{ radialaxis:{ visible:true, color:'rgba(255,255,255,.3)', gridcolor:'rgba(255,255,255,.1)' },
            angularaxis:{ color:'rgba(255,255,255,.6)' }, bgcolor:'rgba(0,0,0,0)' },
    paper_bgcolor:'rgba(0,0,0,0)', plot_bgcolor:'rgba(0,0,0,0)',
    font:{color:'#e0e0e0', family:'Inter'}, showlegend:false,
    margin:{l:40,r:40,t:20,b:20}
  };
  Plotly.newPlot('radarChart', data, layout, {responsive:true, displayModeBar:false});
})();
<?php endif; ?>
</script>
</body>
</html>
