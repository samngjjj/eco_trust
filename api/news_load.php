<?php
require_once dirname(__DIR__) . '/auth_check.php';
require_once dirname(__DIR__) . '/config.php';

ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$company = $_GET['company'] ?? '';
set_time_limit(180);

$sym = null;
$real_name = $company;
$safe_sym = '';

if ($company) {
    $stmt = $db->prepare("SELECT symbol, name FROM companies WHERE symbol = ? OR name LIKE ? LIMIT 1");
    $lk = "%$company%";
    $stmt->bind_param('ss', $company, $lk);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $sym      = $row['symbol'];
        $real_name = $row['name'];
        $safe_sym = $db->real_escape_string($sym);
    }

    $force = isset($_GET['force']) && $_GET['force'] == '1';

    if ($sym) {
        $check  = $db->query("SELECT COUNT(*) as count FROM news WHERE company_symbol = '$safe_sym'");
        $has_data = (int)($check->fetch_assoc()['count'] ?? 0) > 0;

        if ($force || !$has_data) {
            $db->query("DELETE FROM news WHERE company_symbol = '$safe_sym'");

            $pythonExe = trim(shell_exec('where python') ?? '');
            if (str_contains($pythonExe, "\n")) $pythonExe = trim(explode("\n", $pythonExe)[0]);
            if (!$pythonExe) $pythonExe = 'python';

            $scriptPath = dirname(__DIR__) . '/news_nlp.py';
            $search_payload = $real_name . "|" . $sym;
            $cmd = escapeshellarg($pythonExe) . ' ' . escapeshellarg($scriptPath)
                 . ' ' . escapeshellarg($search_payload) . ' 2>&1';

            file_put_contents(__DIR__ . '/news_debug.log', "[" . date('Y-m-d H:i:s') . "] Executing: $cmd\n", FILE_APPEND);
            $output = shell_exec($cmd);
            file_put_contents(__DIR__ . '/news_debug.log', "[" . date('Y-m-d H:i:s') . "] Output len: " . strlen($output) . "\n", FILE_APPEND);

            $lines = explode("\n", trim($output));
            $jsonStr = '';
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $line = trim($lines[$i]);
                if ($line !== '' && (str_starts_with($line, '{') || str_starts_with($line, '['))) {
                    $jsonStr = $line; break;
                }
            }
            if ($jsonStr) {
                $data = json_decode($jsonStr, true);
                if (isset($data['news']) && !empty($data['news'])) {
                    $db->query("DELETE FROM news WHERE company_symbol = '$safe_sym'");
                    $ins = $db->prepare(
                        "INSERT INTO news (company_symbol, title, link, published, sentiment, confidence,
                                          report_year, action_context, search_query)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    foreach ($data['news'] as $n) {
                        $sentiment  = $n['sentiment']   ?? 'Neutral';
                        $confidence = (float)($n['confidence'] ?? 0.60);
                        $ryr        = isset($n['report_year']) ? (int)$n['report_year'] : null;
                        $ctx        = mb_substr($n['action_context'] ?? '', 0, 500);
                        $qry        = mb_substr($n['search_query']   ?? '', 0, 300);
                        $ins->bind_param('issssdiss',
                            $sym, $n['title'], $n['link'], $n['published'],
                            $sentiment, $confidence, $ryr, $ctx, $qry
                        );
                        $ins->execute();
                    }
                }
            }
        }
    } else {
        echo json_encode(['error' => "找不到公司記錄：'$company'。請確認輸入的公司名稱或股票代碼是否正確。"], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ── 回傳新聞，支援年份篩選 ────────────────────────────────────
$filterYear = isset($_GET['year']) ? intval($_GET['year']) : 0;

if ($safe_sym) {
    $yearCond = $filterYear ? "AND n.report_year = $filterYear" : '';
    $sql = "SELECT n.id, n.title, n.link, n.published, n.sentiment, n.confidence,
                   n.report_year, n.action_context, n.search_query,
                   c.name as company_name
            FROM news n JOIN companies c ON n.company_symbol = c.symbol
            WHERE n.company_symbol = '$safe_sym' $yearCond
            ORDER BY n.report_year DESC, n.published DESC, n.id DESC LIMIT 300";
} else {
    $sql = "SELECT n.id, n.title, n.link, n.published, n.sentiment, n.confidence,
                   n.report_year, n.action_context, n.search_query,
                   c.name as company_name
            FROM news n JOIN companies c ON n.company_symbol = c.symbol
            ORDER BY n.report_year DESC, n.created_at DESC, n.id DESC LIMIT 100";
}

$res  = $db->query($sql);
$news = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// 全域情感統計
$pos = 0; $neu = 0; $neg = 0;
foreach ($news as $n) {
    if ($n['sentiment'] === 'Positive') $pos++;
    elseif ($n['sentiment'] === 'Negative') $neg++;
    else $neu++;
}
$tot = max(1, $pos + $neu + $neg);
$dist = [
    'Positive' => round($pos / $tot * 100, 1),
    'Neutral'  => round($neu / $tot * 100, 1),
    'Negative' => round($neg / $tot * 100, 1),
];

// 按年份分組情感統計（供年份篩選器）
$yearStats = [];
if ($safe_sym) {
    $ysRes = $db->query(
        "SELECT report_year,
                SUM(sentiment='Positive') as pos,
                SUM(sentiment='Neutral')  as neu,
                SUM(sentiment='Negative') as neg,
                COUNT(*) as total
         FROM news
         WHERE company_symbol = '$safe_sym' AND report_year IS NOT NULL
         GROUP BY report_year ORDER BY report_year DESC"
    );
    $yearStats = $ysRes ? $ysRes->fetch_all(MYSQLI_ASSOC) : [];
}

echo json_encode([
    'news'                   => $news,
    'sentiment_distribution' => ($pos + $neu + $neg > 0)
                                    ? $dist : ['Positive' => 0, 'Neutral' => 100, 'Negative' => 0],
    'year_stats'             => $yearStats,
    'available_years'        => array_column($yearStats, 'report_year'),
    'filtered_year'          => $filterYear ?: null,
], JSON_UNESCAPED_UNICODE);
