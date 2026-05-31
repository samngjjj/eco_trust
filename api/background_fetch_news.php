<?php
/**
 * background_fetch_news.php — v2.0
 * 新聞抓取策略：
 *   1. 年份全年模式：按 report_year 每月搜尋 5 篇 (共 60 篇)
 *   2. 承諾驗證模式：讀取 Gen-2 高信度承諾，針對每個承諾搜尋對應年份新聞
 *
 * 呼叫方式：
 *   php background_fetch_news.php <company_symbol> <company_name> <report_year>
 */
require_once dirname(__DIR__) . '/config.php';
$db = getDB();

if ($argc < 4) {
    exit("Usage: background_fetch_news.php <symbol> <name> <year>\n");
}

$company_symbol = intval($argv[1]);
$company_name   = $argv[2];
$report_year    = intval($argv[3]);

if (!$company_symbol || !$report_year) {
    exit("Invalid symbol or year.\n");
}

// ── 防重複：同一公司同一年份若已有 ≥ 30 筆新聞則跳過 ───────────────────
$chk = $db->prepare(
    "SELECT COUNT(*) as c FROM news WHERE company_symbol=? AND report_year=?"
);
$chk->bind_param('ii', $company_symbol, $report_year);
$chk->execute();
$existing = (int)($chk->get_result()->fetch_assoc()['c'] ?? 0);
if ($existing >= 30) {
    exit("Year {$report_year} already has {$existing} news articles. Skipping.\n");
}

// ── 偵測 Python 路徑 ────────────────────────────────────────────────────
$pythonExe = 'python';
$detect = trim(shell_exec('where python') ?? '');
if ($detect && str_contains($detect, 'python')) {
    $paths = explode("\n", $detect);
    $pythonExe = trim($paths[0]);
}
$scriptPath = dirname(__DIR__) . '/news_nlp.py';

// ── 讀取 Gen-2 高信度承諾（用於承諾驗證搜尋）──────────────────────────
$actRes = $db->prepare(
    "SELECT high_confidence_commitments FROM carbon_emissions
     WHERE company_id=? AND year=? LIMIT 1"
);
$actRes->bind_param('ii', $company_symbol, $report_year);
$actRes->execute();
$actRow = $actRes->get_result()->fetch_assoc();
$highConfActions = [];
if ($actRow && $actRow['high_confidence_commitments']) {
    $decoded = json_decode($actRow['high_confidence_commitments'], true);
    if (is_array($decoded)) {
        $highConfActions = $decoded;
    }
}

// ── 模式一：全年度新聞（每月 5 篇，共 60 篇）─────────────────────────
$cmd1 = escapeshellarg($pythonExe) . ' ' . escapeshellarg($scriptPath)
      . ' fetch_year '
      . escapeshellarg($company_name) . ' '
      . escapeshellarg((string)$company_symbol) . ' '
      . escapeshellarg((string)$report_year)
      . ' 5 2>&1';

$out1 = shell_exec($cmd1);
_save_news($db, $company_symbol, $out1, $report_year, null, null);

// ── 模式二：承諾驗證新聞（有高信度承諾才執行）────────────────────────
if (!empty($highConfActions)) {
    $actionsJson = json_encode($highConfActions, JSON_UNESCAPED_UNICODE);
    $cmd2 = escapeshellarg($pythonExe) . ' ' . escapeshellarg($scriptPath)
          . ' fetch_actions '
          . escapeshellarg($company_name) . ' '
          . escapeshellarg((string)$company_symbol) . ' '
          . escapeshellarg((string)$report_year) . ' '
          . escapeshellarg($actionsJson)
          . ' 2>&1';

    $out2 = shell_exec($cmd2);
    _save_news($db, $company_symbol, $out2, $report_year, null, null);
}

// ── 儲存新聞至資料庫 ──────────────────────────────────────────────────
function _save_news(mysqli $db, int $symbol, ?string $output,
                    int $report_year, ?string $default_query, ?string $default_ctx): void
{
    if (!$output) return;

    // 從輸出最後一行提取 JSON
    $lines = explode("\n", trim($output));
    $jsonStr = '';
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        $line = trim($lines[$i]);
        if ($line !== '' && (str_starts_with($line, '{') || str_starts_with($line, '['))) {
            $jsonStr = $line;
            break;
        }
    }
    if (!$jsonStr) return;

    $data = json_decode($jsonStr, true);
    if (!isset($data['news']) || !is_array($data['news'])) return;

    $stmt = $db->prepare(
        "INSERT IGNORE INTO news
            (company_symbol, title, link, published, sentiment, confidence,
             report_year, action_context, search_query)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $inserted = 0;
    foreach ($data['news'] as $n) {
        $title     = mb_substr($n['title']     ?? '', 0, 1000);
        $link      = mb_substr($n['link']      ?? '', 0, 1000);
        $published = mb_substr($n['published'] ?? '', 0, 100);
        $sentiment = $n['sentiment'] ?? 'Pending';
        $conf      = (float)($n['confidence'] ?? 0);
        $yr        = (int)($n['report_year']  ?? $report_year);
        $ctx       = mb_substr($n['action_context'] ?? $default_ctx ?? '', 0, 500);
        $query     = mb_substr($n['search_query']   ?? $default_query ?? '', 0, 300);

        $stmt->bind_param('issssdiss',
            $symbol, $title, $link, $published, $sentiment, $conf,
            $yr, $ctx, $query
        );
        if ($stmt->execute()) $inserted++;
    }
    echo "[{$symbol}][{$report_year}] 寫入 {$inserted} 筆新聞\n";
}
