<?php
require_once dirname(__DIR__) . '/auth_check.php';
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json; charset=utf-8');

// Set high limits
set_time_limit(240);
ini_set('memory_limit', '512M');

// Capture output
ob_start();

try {
    $company = trim($_GET['company'] ?? '');
    if($company === ''){
        throw new Exception('請提供公司名稱');
    }

    $pythonExe  = 'python';
    // Auto-detect python path
    $detect = trim(shell_exec('where python') ?? 'python');
    if($detect && str_contains($detect, 'python')){
        $paths = explode("\n", $detect);
        $pythonExe = trim($paths[0]);
    }

    $scriptPath = dirname(__DIR__) . '/news_nlp.py';
    $cmd = escapeshellarg($pythonExe) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($company) . ' 2>&1';

    $output = shell_exec($cmd);

    if(!$output){
        throw new Exception('Python 腳本無輸出 (可能超時或崩潰)');
    }

    // Extract JSON
    $lines = explode("\n", trim($output));
    $jsonStr = '';
    for($i = count($lines)-1; $i >= 0; $i--){
        $line = trim($lines[$i]);
        if($line !== '' && (str_starts_with($line, '{') || str_starts_with($line, '['))){
            $jsonStr = $line;
            break;
        }
    }

    if(!$jsonStr){
        file_put_contents(__DIR__ . '/news_error.log', "[" . date('Y-m-d H:i:s') . "] Failed to find JSON in: " . substr($output, 0, 1000) . "\n", FILE_APPEND);
        throw new Exception('無法從分析結果中提取有效數據');
    }

    $result = json_decode($jsonStr, true);
    if(!$result){
        throw new Exception('解析分析結果失敗 (Invalid JSON)');
    }

    ob_clean();
    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
exit;
