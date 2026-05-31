<?php
require_once dirname(__DIR__) . '/auth_check.php';
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if (isset($isFree) && $isFree) {
    echo json_encode(['error' => '您的方案不支援上傳功能，請升級至 Plus 或 Pro 方案。']);
    exit;
}

// Increase execution time for heavy AI model analysis (10 mins)
set_time_limit(600);
ini_set('memory_limit', '1G');
ignore_user_abort(true);

// Enable error logging for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/upload_error.log');

// Capture all output to prevent leaks
ob_start();

try {
    if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        $uploadErr = $_FILES['pdf']['error'] ?? 'MISSING';
        throw new Exception('檔案上傳失敗或未收到檔案 (PHP Upload Error Code: ' . $uploadErr . ')');
    }

    $file = $_FILES['pdf'];
    $tmpPath = $file['tmp_name'];
    $origName = $file['name'];

    // Create upload dir
    $uploadDir = dirname(__DIR__) . '/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $destPath = $uploadDir . basename($origName);
    if (!move_uploaded_file($tmpPath, $destPath)) {
        throw new Exception('無法將檔案儲存至 uploads 目錄');
    }

    // ── 1. 解析檔名 (嚴格抓取 4-6 位股票代號與年份) ──────────────────────
    $base  = pathinfo($origName, PATHINFO_FILENAME);
    $parts = preg_split('/[_\s\-\(\)]+/', $base);
    $company_id = null; // integer stock code
    $year = (int) date('Y');

    foreach ($parts as $p) {
        $p = trim($p);
        if (!$p) continue;
        if (preg_match('/^20\d{2}$/', $p)) {
            $year = (int) $p;
        } elseif (preg_match('/^\d{4,6}$/', $p) && !$company_id) {
            $company_id = (int) $p;
        }
    }

    // 若解析不出股票代號，則報錯
    if (!$company_id) {
        @unlink($destPath);
        ob_clean();
        echo json_encode([
            'error'   => '無法從檔名中解析出股票代號。請依建議格式命名檔案，例如：1101_台泥_2023.pdf，其中 1101 為股票代號。',
            'hint'    => '確認命名格式後重新上傳即可，檔案內容不需更改。',
            'success' => false
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── 1b. 重複資料偵測 ─────────────────────────────────────────────
    $forceOverwrite = !empty($_POST['force']) && $_POST['force'] === '1';
    if ($company_id && !$forceOverwrite) {
        $db = getDB();
        $chk = $db->prepare("SELECT id FROM carbon_emissions WHERE company_id=? AND year=? LIMIT 1");
        $chk->bind_param('ii', $company_id, $year);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            @unlink($destPath); // 先移除暫存檔，等確認後再重傳
            ob_clean();
            echo json_encode([
                'duplicate'  => true,
                'company_id' => $company_id,
                'year'       => $year,
                'filename'   => $origName,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // ── 2. AI 分析與 ESG 驗證 ───────────────────────────────────────
    $pythonPath = 'python';
    $wherePython = shell_exec('where python');
    if ($wherePython) {
        $paths = explode("\n", trim($wherePython));
        $pythonPath = trim($paths[0]);
    }
    // Fallback to common XAMPP path if not in where
    if (!file_exists($pythonPath) && file_exists('C:\Users\samng\AppData\Local\Microsoft\WindowsApps\python.exe')) {
        $pythonPath = 'C:\Users\samng\AppData\Local\Microsoft\WindowsApps\python.exe';
    }

    $scriptPath = dirname(__DIR__) . '/finbert.py';
    $cmd = escapeshellarg($pythonPath) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($destPath);

    // Run Python and capture both output AND exit code
    $descriptors = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
    $proc = proc_open($cmd, $descriptors, $pipes);
    $output = '';
    $exitCode = 0;
    if (is_resource($proc)) {
        fclose($pipes[0]);
        $output    = stream_get_contents($pipes[1]);
        $output   .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode  = proc_close($proc);
    }

    // ── 3. 檢查 ESG 驗證結果 ──────────────────────────────────────────
    if ($exitCode === 2 || str_contains($output, 'NOT_ESG_REPORT')) {
        preg_match('/NOT_ESG_REPORT:\s*(.+)/u', $output, $m);
        $reason = $m[1] ?? '此文件不符合ESG永續報告的特徵，請上傳正確的ESG永續報告 PDF。';
        @unlink($destPath); // 移除不合規檔案
        ob_clean();
        echo json_encode([
            'not_esg'  => true,
            'reason'   => trim($reason),
            'filename' => $origName
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    file_put_contents(__DIR__ . '/upload_debug.log', "[" . date('Y-m-d H:i:s') . "] Executing: " . $cmd . "\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/upload_debug.log', "[" . date('Y-m-d H:i:s') . "] Exit: " . $exitCode . " | Output: " . substr($output, 0, 500) . "...\n", FILE_APPEND);

    // ── 2b. 頁碼索引建構 (背景執行) ──────────────────────────────────
    $indexerScript = dirname(__DIR__) . '/pdf_page_indexer.py';
    $cmdIndexer = escapeshellarg($pythonPath) . ' ' . escapeshellarg($indexerScript) . ' ' . escapeshellarg($destPath);
    if (substr(php_uname(), 0, 7) == "Windows") {
        pclose(popen("start /B " . $cmdIndexer, "r"));
    } else {
        exec($cmdIndexer . " > /dev/null &");
    }
    file_put_contents(__DIR__ . '/upload_debug.log', "[" . date('Y-m-d H:i:s') . "] Page indexer launched: " . $cmdIndexer . "\n", FILE_APPEND);

    // ── 4. 解析分析結果 CSV 與 Gen-2 JSON (防止 UTF-8 亂碼) ──────────────────────────
    $csvPath = dirname($destPath) . '/GWRI_Analysis_Report.csv';
    $confidence = null;
    $roe = null;
    $intent_score = null;
    $credibility_index = null;
    $numeracy_score = null;
    $kpi_count = null;

    // 解析 Gen-2 JSON 控制輸出 (GEN2_JSON:{...} 行)
    $gen2Data = null;
    if (preg_match('/GEN2_JSON:(\{.+\})/u', $output, $gm)) {
        $gen2Data = json_decode($gm[1], true);
    }

    if (file_exists($csvPath)) {
        $handle = fopen($csvPath, 'r');
        $headerLine = fgets($handle);
        if ($headerLine) {
            $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', $headerLine);
            $headers = array_map('trim', str_getcsv($headerLine));

            while (($line = fgets($handle)) !== false) {
                $line = rtrim($line, "\r\n");
                if ($line === '') continue;
                $row = str_getcsv($line);
                if (count($headers) !== count($row)) continue;
                $r = array_combine($headers, $row);

                $csvFile      = trim(basename($r['File Name'] ?? ''));
                $uploadedFile = trim(basename($origName));

                // 比對檔名
                if ($csvFile === $uploadedFile || str_contains($uploadedFile, $csvFile) || str_contains($csvFile, $uploadedFile)) {
                    $confidence = isset($r['Credibility_Index']) ? (float) $r['Credibility_Index'] : null;
                    $roe        = isset($r['ROE']) ? (float) $r['ROE'] : null;
                    $intent_score      = isset($r['意圖強度']) ? (float) $r['意圖強度'] : null;
                    $credibility_index = isset($r['數據實質性']) ? (float) $r['數據實質性'] : null;
                    $numeracy_score    = isset($r['數字密度']) ? (float) $r['數字密度'] : null;
                    $kpi_count         = isset($r['指標豐富度']) ? (int) $r['指標豐富度'] : null;
                    // 若 Gen-2 JSON 解析失敗，嘗試從 CSV 列讀回备用
                    if (!$gen2Data) {
                        $gen2Data = [
                            'total_promises'              => isset($r['total_promises'])  ? (int)$r['total_promises']  : null,
                            'quant_rate'                  => isset($r['quant_rate'])      ? (float)$r['quant_rate']    : null,
                            'timeframe_rate'              => isset($r['timeframe_rate'])  ? (float)$r['timeframe_rate']: null,
                            'topic_distribution'          => isset($r['topic_distribution']) ? json_decode($r['topic_distribution'], true) : null,
                            'high_confidence_commitments' => isset($r['high_confidence_commitments']) ? json_decode($r['high_confidence_commitments'], true) : null,
                        ];
                    }
                    break;
                }
            }
        }
        fclose($handle);
    }

    if ($confidence === null) {
        file_put_contents(__DIR__ . '/upload_debug.log', "[" . date('Y-m-d H:i:s') . "] Warning: Data not found in CSV for " . $origName . "\n", FILE_APPEND);
    }

    // ── 5. 資料庫存入 ────────────────────────────────────────────────
    $db_error = null;
    if ($company_id) {
        $db = getDB();
        // 若為強制覆蓋模式，先刪除舊記錄
        if ($forceOverwrite) {
            $del = $db->prepare("DELETE FROM carbon_emissions WHERE company_id=? AND year=?");
            $del->bind_param('ii', $company_id, $year);
            $del->execute();
        }

        // 準備 Gen-2 數據
        $total_promises  = $gen2Data['total_promises']  ?? null;
        $quant_rate      = $gen2Data['quant_rate']      ?? null;
        $timeframe_rate  = $gen2Data['timeframe_rate']  ?? null;
        $topic_dist_json = isset($gen2Data['topic_distribution'])
            ? json_encode($gen2Data['topic_distribution'], JSON_UNESCAPED_UNICODE) : null;
        $high_conf_json  = isset($gen2Data['high_confidence_commitments'])
            ? json_encode($gen2Data['high_confidence_commitments'], JSON_UNESCAPED_UNICODE) : null;
        $raw_gen2_json   = $gen2Data ? json_encode($gen2Data, JSON_UNESCAPED_UNICODE) : null;

        $stmt = $db->prepare(
            "INSERT INTO carbon_emissions
                (company_id, year, confidence_score,
                 total_promises, quant_rate, timeframe_rate,
                 topic_distribution, high_confidence_commitments, raw_gen2_output,
                 intent_score, credibility_index, numeracy_score, kpi_count)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                confidence_score=VALUES(confidence_score),
                total_promises=VALUES(total_promises),
                quant_rate=VALUES(quant_rate),
                timeframe_rate=VALUES(timeframe_rate),
                topic_distribution=VALUES(topic_distribution),
                high_confidence_commitments=VALUES(high_confidence_commitments),
                raw_gen2_output=VALUES(raw_gen2_output),
                intent_score=VALUES(intent_score),
                credibility_index=VALUES(credibility_index),
                numeracy_score=VALUES(numeracy_score),
                kpi_count=VALUES(kpi_count)"
        );
        $stmt->bind_param('iididdsssdddi',
            $company_id, $year, $confidence,
            $total_promises, $quant_rate, $timeframe_rate,
            $topic_dist_json, $high_conf_json, $raw_gen2_json,
            $intent_score, $credibility_index, $numeracy_score, $kpi_count
        );
        if (!$stmt->execute()) $db_error = $stmt->error;

        // 背景抓取新聞
        $phpPath = trim(shell_exec('where php') ?? 'php');
        if (str_contains($phpPath, "\n")) $phpPath = trim(explode("\n", $phpPath)[0]);
        $backendNewsScript = dirname(__DIR__) . '/api/background_fetch_news.php';
        $p2 = $parts[1] ?? $company_id;
        // v2.0: 傳入 report_year，讓新聞按年份搜尋
        $cmdNews = escapeshellarg($phpPath) . ' '
                 . escapeshellarg($backendNewsScript) . ' '
                 . escapeshellarg($company_id) . ' '
                 . escapeshellarg($p2) . ' '
                 . escapeshellarg($year);
        
        if (substr(php_uname(), 0, 7) == "Windows") {
            pclose(popen("start /B " . $cmdNews, "r"));
        } else {
            exec($cmdNews . " > /dev/null &");
        }
    }

    // ── 6. 回傳成功 JSON ─────────────────────────────────────────────
    ob_clean();
    $finalOutput = [
        'success'    => true,
        'company_id' => $company_id,
        'year'       => $year,
        'confidence' => $confidence,
        'roe'        => $roe,
        'db_error'   => $db_error,
        'python_out' => mb_convert_encoding(substr($output ?? 'No Python Output', 0, 2000), 'UTF-8', 'UTF-8,BIG5,CP950')
    ];
    
    $json = json_encode($finalOutput, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    if ($json === false) {
        echo json_encode(['error' => 'JSON 序列化失敗: ' . json_last_error_msg()]);
    } else {
        echo $json;
    }

} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        'error' => $e->getMessage(),
        'file'  => basename($e->getFile()),
        'line'  => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
exit;
