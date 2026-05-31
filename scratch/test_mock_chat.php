<?php
require_once dirname(__DIR__) . '/config.php';

function getChineseKeywords($query) {
    $query = mb_strtolower($query, 'UTF-8');
    $segments = preg_split('/[\s，。、？！,\.\?!：；「」『』（）\(\)\[\]\{\}]+/u', $query);
    $segments = array_filter($segments);
    
    $keywords = [];
    $stopWords = ['可以', '給我', '看一看', '有關', '關於', '請', '如何', '什麼', '是多少', '是不是', '有沒有', '以及', '的', '了', '在', '是', '有', '和', '與', '或', '及', '為', '之', '於', '以', '對', '嗎', '呢', '吧', '啊', '這', '那', '哪', '我們', '你們', '他們', '請個', '一個', '一些', '這個', '那個'];
    $domainTerms = ['碳排放', '溫室氣體', '減碳', '淨零', '排放量', '範疇', 'roe', 'eps', '營收', '獲利', '永續報告', '報告書', '附錄', '圖表', '表格', '數據', 'esg', '水資源', '廢棄物', '氣候變遷', '碳盤查', '確證', '碳中和', '綠電', '再生能源', '碳費', '碳交易'];
    
    foreach ($segments as $segment) {
        $cleanSegment = $segment;
        foreach ($stopWords as $sw) {
            $cleanSegment = str_replace($sw, '', $cleanSegment);
        }
        
        if (mb_strlen($cleanSegment, 'UTF-8') < 2) continue;
        
        $keywords[] = $cleanSegment;
        
        foreach ($domainTerms as $term) {
            if (mb_strpos($cleanSegment, $term) !== false) {
                $keywords[] = $term;
            }
        }
        
        $len = mb_strlen($cleanSegment, 'UTF-8');
        for ($i = 0; $i < $len - 1; $i++) {
            $bigram = mb_substr($cleanSegment, $i, 2, 'UTF-8');
            if (preg_match('/[\x{4e00}-\x{9fa5}a-z0-9]/u', $bigram)) {
                $keywords[] = $bigram;
            }
        }
    }
    
    $keywords = array_unique($keywords);
    $keywords = array_filter($keywords, function($k) { return mb_strlen($k, 'UTF-8') >= 2; });
    return array_values($keywords);
}

function selectRelevantPages($pagesData, $query, $maxPages = 40) {
    if (empty($pagesData['pages'])) return ['', []];
    $keywords = getChineseKeywords($query);
    
    $scored = [];
    foreach ($pagesData['pages'] as $p) {
        $pageText = mb_strtolower($p['text'] ?? '', 'UTF-8');
        if (mb_strlen($pageText, 'UTF-8') < 20) continue;
        $score = 0;
        foreach ($keywords as $kw) {
            $score += mb_substr_count($pageText, $kw);
        }
        $scored[] = ['page' => $p['page'], 'text' => $p['text'], 'score' => $score];
    }
    
    usort($scored, function($a, $b) { return $b['score'] - $a['score']; });
    $selected = array_slice($scored, 0, $maxPages);
    usort($selected, function($a, $b) { return $a['page'] - $b['page']; });
    
    $contextStr = '';
    $pageNums = [];
    foreach ($selected as $s) {
        $contextStr .= "【第{$s['page']}頁】\n{$s['text']}\n\n";
        $pageNums[] = $s['page'];
    }
    
    if (mb_strlen($contextStr, 'UTF-8') > 30000) {
        $contextStr = mb_substr($contextStr, 0, 30000, 'UTF-8') . "\n...(已截斷，僅顯示最相關頁面)";
    }
    
    return [$contextStr, $pageNums];
}

$message = $argv[1] ?? "可以給我看一看有關碳排放圖表嗎？";
$companyStr = "1101_台泥";
$year = "2023";
$history = [];

$parts = explode('_', $companyStr);
$companySymbol = $parts[0];

$roeText = "1101_台泥 在 2023 年的 ROE 為 4.15%。";
$esgText = "1101_台泥 在 2023 年的溫室氣體排放量範疇一為 21,304,150 噸，範疇二為 1,120,340 噸，減碳目標為比 2020 年減少 10%。";
$newsText = "台泥宣布擴大歐洲低碳水泥投資。";

$mdContent = "";
$pdfFileName = "";
$hasPageIndex = false;

$uploadDir = dirname(__DIR__) . '/uploads/';
$pdfPattern = "{$companySymbol}_*_{$year}";
$jsonFiles = glob($uploadDir . $pdfPattern . '_pages.json');
if (empty($jsonFiles)) {
    $jsonFiles = glob($uploadDir . "{$companySymbol}_*{$year}*_pages.json");
}

if (!empty($jsonFiles)) {
    $jsonPath = $jsonFiles[0];
    $pagesData = json_decode(file_get_contents($jsonPath), true);
    if ($pagesData && !empty($pagesData['pages'])) {
        $hasPageIndex = true;
        $pdfFileName = $pagesData['source_pdf'] ?? '';
        list($mdContent, $usedPages) = selectRelevantPages($pagesData, $message);
    }
}

$yearDisplay = "2023 年";
$kbLabel = $hasPageIndex ? 'ESG 報告書知識庫 (含頁碼標註)' : 'ESG 報告書知識庫 (MD 格式)';

$citationRule = $hasPageIndex ? <<<CITE

【頁碼引用鐵律 — 嚴格遵守】
您收到的報告書知識庫內容已按「【第X頁】」標註了每段文字所在的原始 PDF 頁碼。
您必須遵守以下規則：
1. 您的每一項事實陳述、數據引用、或推論依據，都必須在句尾附帶原文頁碼標籤，格式為 [p.頁碼]。
2. 例如：「台泥 2023 年溫室氣體排放量為 1,234 萬噸 [p.87]」
3. 若一句話引用多個頁面的資料，使用 [p.23][p.45] 格式。
4. 若某項資訊來自資料庫數據（ROE、新聞等）而非報告原文，標註為 [資料庫]。
5. 若無法確定來源，必須標註 [p.?] 並附帶說明「此為推論，非報告原文」。
6. 絕對禁止省略頁碼標籤。每一個段落都必須至少有一個來源標籤。
CITE
: "\n請盡量引用具體數據來源。";

$systemPrompt = <<<EOT
您是 Eco Trust AI 系統的專業 ESG 投資顧問。
使用者正在查詢公司「{$companyStr}」在 {$yearDisplay} 的資料。
以下是系統從資料庫及知識庫中提取的相關數據：

=== 財報 ROE 數據 ({$yearDisplay}) ===
{$roeText}

=== ESG 排放與承諾數據 ({$yearDisplay}) ===
{$esgText}

=== 新聞 ({$yearDisplay}) ===
{$newsText}

=== {$kbLabel} ===
{$mdContent}
{$citationRule}

請根據上述資料回答使用者的問題。如果上述資料無法回答，請運用您的常識，但務必優先參考上述提供的真實數據與報告內容。
請一律使用「繁體中文」回答，並且保持專業、客觀的語氣。可以使用 Markdown 排版。
EOT;

$messages = [];
$messages[] = ["role" => "system", "content" => $systemPrompt];
$messages[] = ["role" => "user", "content" => $message];

$ollamaUrl = "http://localhost:11434/api/chat";
$payload = [
    "model" => "qwen2.5:7b",
    "messages" => $messages,
    "stream" => false,
    "options" => [
        "num_ctx" => 32768,
        "temperature" => 0.2
    ]
];

echo "Query: $message\n";
echo "Calling Ollama API...\n";

$ch = curl_init($ollamaUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    die("Ollama failed: $curlError (HTTP $httpCode)\n");
}

$responseData = json_decode($response, true);
$reply = $responseData['message']['content'] ?? 'No reply';

echo "\n--- AI Response ---\n";
echo $reply . "\n";
?>
