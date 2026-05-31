<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($isPro) || !$isPro) {
    echo json_encode(['error' => '權限不足，此功能僅限 Pro 專業版使用。']);
    exit;
}

// ── 頁碼感知：中文關鍵字分詞與 N-gram 提取 ──
function getChineseKeywords($query) {
    $query = mb_strtolower($query, 'UTF-8');
    
    // Split by common Chinese and English delimiters
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
        
        // Generate bigrams
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

// ── 頁碼感知：關鍵字匹配選取最相關頁面 ──
function selectRelevantPages($pagesData, $query, $maxPages = 40) {
    if (empty($pagesData['pages'])) return ['', []];
    
    $keywords = getChineseKeywords($query);
    if (empty($keywords)) $keywords = [mb_strtolower($query, 'UTF-8')];
    
    // Score each page by keyword hits
    $scored = [];
    foreach ($pagesData['pages'] as $p) {
        $pageText = mb_strtolower($p['text'] ?? '', 'UTF-8');
        if (mb_strlen($pageText, 'UTF-8') < 20) continue; // skip nearly empty pages
        $score = 0;
        foreach ($keywords as $kw) {
            $score += mb_substr_count($pageText, $kw);
        }
        $scored[] = ['page' => $p['page'], 'text' => $p['text'], 'score' => $score];
    }
    
    // Sort by score descending, take top N
    usort($scored, function($a, $b) { return $b['score'] - $a['score']; });
    $selected = array_slice($scored, 0, $maxPages);
    
    // Re-sort by page number for coherent reading order
    usort($selected, function($a, $b) { return $a['page'] - $b['page']; });
    
    // Build annotated context string
    $contextStr = '';
    $pageNums = [];
    foreach ($selected as $s) {
        $contextStr .= "【第{$s['page']}頁】\n{$s['text']}\n\n";
        $pageNums[] = $s['page'];
    }
    
    // Truncate if too long (approx 30K chars to stay within context window)
    if (mb_strlen($contextStr, 'UTF-8') > 30000) {
        $contextStr = mb_substr($contextStr, 0, 30000, 'UTF-8') . "\n...(已截斷，僅顯示最相關頁面)";
    }
    
    return [$contextStr, $pageNums];
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception("Invalid input.");
    }

    $message = $input['message'] ?? '';
    $companyStr = $input['company'] ?? ''; // e.g. "1101_台泥"
    $year = $input['year'] ?? '';
    $history = $input['history'] ?? [];
    $pdfFileName = '';
    $hasPageIndex = false;

    if (!$message || !$companyStr || !$year) {
        throw new Exception("Missing parameters.");
    }

    $parts = explode('_', $companyStr);
    $companySymbol = $parts[0];
    
    $db = getDB();

    $isAll = ($companyStr === 'ALL_跨公司對比');

    if ($isAll) {
        // ── 跨公司綜合分析模式 ──
        $roeText = "各公司 {$year} 年 ROE 平均：\n";
        $res = $db->query("SELECT company_symbol, AVG(roe) as aroe FROM company_performance WHERE year = ".(int)$year." GROUP BY company_symbol");
        while ($row = $res->fetch_assoc()) {
            $roeText .= "- 股票代號 {$row['company_symbol']}: " . round($row['aroe'], 2) . "%\n";
        }

        $esgText = "各公司 {$year} 年 ESG 核心數據對比：\n";
        $res2 = $db->query("SELECT c.name, ce.confidence_score, ce.intent_score, ce.credibility_index, ce.numeracy_score, ce.kpi_count FROM carbon_emissions ce LEFT JOIN companies c ON ce.company_id = c.symbol WHERE ce.year = ".(int)$year);
        while ($row2 = $res2->fetch_assoc()) {
            $esgText .= "- {$row2['name']}: 誠信信心分 {$row2['confidence_score']}, 意圖分數 {$row2['intent_score']}, 可靠性 {$row2['credibility_index']}, 數字密度 {$row2['numeracy_score']}, 指標多樣性 {$row2['kpi_count']}\n";
        }

        $newsText = "各公司 {$year} 年新聞情感統計：\n";
        $res3 = $db->query("SELECT company_symbol, SUM(sentiment='Positive') as pos, SUM(sentiment='Negative') as neg FROM news WHERE report_year = ".(int)$year." GROUP BY company_symbol");
        while ($row3 = $res3->fetch_assoc()) {
            $newsText .= "- 股票代號 {$row3['company_symbol']}: 正面 {$row3['pos']} 筆, 負面 {$row3['neg']} 筆\n";
        }

        $mdContent = "【跨公司綜合分析模式】為節省資源，此模式不提供單一公司冗長內文，而是提供全體公司的客觀量化數據。請以此為依據進行企業間的橫向對比與評估。";
        
        $systemPrompt = <<<EOT
您是 Eco Trust AI 系統的專業 ESG 投資顧問，具備資深金融分析師與審計專家的嚴謹思維。
使用者正在查詢「全領域跨公司」在 {$year} 年的綜合比較資料與對話式查核。

【核心查核方法論制約】
在進行跨公司對比時，您必須引入系統的兩大核心維度：「財報 ROE」與「永續誠信信心分數」，並深度剖析兩者之間的交叉反映關係：
1. 財報 ROE（硬指標）：反映企業「短期的財務獲利能力與經濟硬實力」。
2. 誠信信心分數（剛性風控）：基於系統解構的「數字密度（數據化敘述佔比）」、「指標多樣性（合規廣度）」與「外部風險係數（新聞輿情加權）」聚合而成，反映企業「長期的永續誠信度與潛在風控偏差」。
3. 兩者交叉關係：反映企業是否具備「高質量的永續發展潛力」。若企業具備【高 ROE 但低信心分數】，反映出其高获利背後充斥著公關文本泡沫與言行不一的實質性誠信風險（誠信偏差）；反之，【同軸雙高】則反映出該企業具備極高投資信任度。您必須依據此因果邏輯進行多維Facts對齊。

以下是系統提取的全部公司聚合數據：

=== 財報 ROE 數據 ({$year}) ===
{$roeText}

=== ESG 排放與承諾數據 ({$year}) ===
{$esgText}

=== 新聞 ({$year}) ===
{$newsText}

=== 知識庫狀態 ===
{$mdContent}

請依據上述標準化 MCP 資源事實，嚴謹回應使用者的對話查核，進行公司間的優劣勢多維對比。
請遵循以下規範：
1. 一律使用「繁體中文」回答。
2. 保持專業、客觀、批判性的風控語氣。
3. 拒絕幻覺，任何推論與質疑必須引經據典，並強烈建議明確指出數據來源或原文頁碼。
4. 可以使用 Markdown 進行清晰的表格與排版。
EOT;

    } else {
        // ── 單一公司深入分析模式 ──
        $isAllYears = ($year === 'ALL');

        // 1. Fetch ROE Data
        $roeData = [];
        if ($isAllYears) {
            $stmt = $db->prepare("SELECT year, quarter, roe FROM company_performance WHERE company_symbol = ? ORDER BY year ASC, quarter ASC");
            $stmt->bind_param("i", $companySymbol);
        } else {
            $stmt = $db->prepare("SELECT quarter, roe FROM company_performance WHERE company_symbol = ? AND year = ? ORDER BY quarter ASC");
            $stmt->bind_param("ii", $companySymbol, $year);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if ($isAllYears) {
                $roeData[] = "{$row['year']} Q{$row['quarter']}: {$row['roe']}%";
            } else {
                $roeData[] = "Q{$row['quarter']}: {$row['roe']}%";
            }
        }
        $roeText = empty($roeData) ? "無相關 ROE 數據。" : implode(", ", $roeData);

        // 2. Fetch ESG Emission Data
        if ($isAllYears) {
            $stmt2 = $db->prepare("SELECT year, confidence_score, total_promises, quant_rate, timeframe_rate, high_confidence_commitments FROM carbon_emissions WHERE company_id = ? ORDER BY year ASC");
            $stmt2->bind_param("i", $companySymbol);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            $esgDataList = [];
            while ($row2 = $res2->fetch_assoc()) {
                $esgDataList[] = "{$row2['year']}年: 信賴度 {$row2['confidence_score']}, 承諾數 {$row2['total_promises']}, 量化比 {$row2['quant_rate']}, 時限比 {$row2['timeframe_rate']}, 高信度 {$row2['high_confidence_commitments']}";
            }
            $esgText = empty($esgDataList) ? "無相關 ESG 排放與承諾數據。" : implode("\n", $esgDataList);
        } else {
            $stmt2 = $db->prepare("SELECT confidence_score, total_promises, quant_rate, timeframe_rate, high_confidence_commitments FROM carbon_emissions WHERE company_id = ? AND year = ?");
            $stmt2->bind_param("ii", $companySymbol, $year);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            $esgText = "無相關 ESG 排放與承諾數據。";
            if ($row2 = $res2->fetch_assoc()) {
                $esgText = "信賴度分數: {$row2['confidence_score']}, 總承諾數: {$row2['total_promises']}, 量化承諾比率: {$row2['quant_rate']}, 有時限承諾比率: {$row2['timeframe_rate']}\n高信度承諾: {$row2['high_confidence_commitments']}";
            }
        }

        // 3. Fetch News
        $newsData = [];
        if ($isAllYears) {
            $stmt3 = $db->prepare("SELECT report_year, published, title, sentiment, confidence FROM news WHERE company_symbol = ? ORDER BY report_year DESC, published DESC LIMIT 30");
            $stmt3->bind_param("i", $companySymbol);
        } else {
            $stmt3 = $db->prepare("SELECT report_year, published, title, sentiment, confidence FROM news WHERE company_symbol = ? AND report_year = ? ORDER BY published DESC LIMIT 10");
            $stmt3->bind_param("ii", $companySymbol, $year);
        }
        $stmt3->execute();
        $res3 = $stmt3->get_result();
        while ($row3 = $res3->fetch_assoc()) {
            $pubDate = !empty($row3['published']) ? $row3['published'] : '日期未知';
            if ($isAllYears) {
                $newsData[] = "- [{$row3['report_year']}年] [發布: {$pubDate}] [{$row3['sentiment']}] {$row3['title']}";
            } else {
                $newsData[] = "- [發布: {$pubDate}] [{$row3['sentiment']}] (信心: {$row3['confidence']}) {$row3['title']}";
            }
        }
        $newsText = empty($newsData) ? "無相關新聞數據。" : implode("\n", $newsData);

        // 4. Read Knowledge Base — 優先使用頁碼索引 JSON，退回 MD 檔案
        $mdContent = "";
        $pdfFileName = ""; // 用於前端 PDF viewer
        $hasPageIndex = false;
        if ($isAllYears) {
            $mdContent = "【歷年綜合分析模式】為節省資源，歷年模式不提供單一年份的完整報告內文，而是提供該公司各年度的量化 ESG 指標與財務表現。請以此為依據進行該公司的歷年趨勢對比與評估。";
        } else {
            // 嘗試讀取頁碼索引 JSON（優先）
            $uploadDir = dirname(__DIR__) . '/uploads/';
            $pdfPattern = "{$companySymbol}_*_{$year}";
            $jsonFiles = glob($uploadDir . $pdfPattern . '_pages.json');
            // Also try direct match
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
            
            // Fallback: 讀取舊的 .md 知識庫
            if (!$hasPageIndex) {
                $mdFilePath = "C:\\Users\\samng\\Desktop\\eco trust v2\\{$companyStr}_{$year}.md";
                if (file_exists($mdFilePath)) {
                    $mdContent = file_get_contents($mdFilePath);
                    if (mb_strlen($mdContent, 'UTF-8') > 40000) {
                        $mdContent = mb_substr($mdContent, 0, 40000, 'UTF-8') . "\n...(受限於長度，已截斷後續內容)";
                    }
                } else {
                    $mdContent = "未找到對應的知識資料庫檔案。";
                }
                // Try to find PDF filename for viewer anyway
                $pdfFiles = glob($uploadDir . "{$companySymbol}_*_{$year}.pdf");
                if (!empty($pdfFiles)) $pdfFileName = basename($pdfFiles[0]);
            }
        }

        // 5. Construct System Prompt
        $yearDisplay = $isAllYears ? "歷年(多年份)" : "{$year} 年";
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
    }

    $messages = [];
    $messages[] = ["role" => "system", "content" => $systemPrompt];
    
    // Append history (last 5 messages to save context limit if needed)
    $recentHistory = array_slice($history, -6);
    foreach ($recentHistory as $msg) {
        $messages[] = [
            "role" => $msg['role'],
            "content" => $msg['content']
        ];
    }
    
    $messages[] = ["role" => "user", "content" => $message];

    // 6. Call Ollama API
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

    $ch = curl_init($ollamaUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 120 seconds timeout for large context

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        throw new Exception("Ollama 呼叫失敗: {$curlError} (HTTP {$httpCode})");
    }

    $responseData = json_decode($response, true);
    $reply = $responseData['message']['content'] ?? '無法取得回覆。';

    $response = ['reply' => $reply];
    if (!empty($pdfFileName)) {
        $response['pdf_file'] = $pdfFileName;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
