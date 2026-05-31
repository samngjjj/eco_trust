<?php
// Load the getChineseKeywords and selectRelevantPages from chat_api.php (mimicked here)

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

function selectRelevantPages($pagesData, $query, $maxPages = 40) {
    if (empty($pagesData['pages'])) return ['', []];
    
    $keywords = getChineseKeywords($query);
    echo "Keywords Extracted: " . implode(', ', $keywords) . "\n";
    if (empty($keywords)) $keywords = [mb_strtolower($query, 'UTF-8')];
    
    // Score each page by keyword hits
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
        $contextStr .= "【第{$s['page']}頁】(Score: {$s['score']})\n" . mb_substr($s['text'], 0, 100) . "...\n\n";
        $pageNums[] = $s['page'];
    }
    
    return [$contextStr, $pageNums];
}

$jsonPath = dirname(__DIR__) . '/uploads/1101_台泥_2023_pages.json';
if (!file_exists($jsonPath)) {
    die("Index not found.\n");
}
$pagesData = json_decode(file_get_contents($jsonPath), true);

$query = "可以給我看一看有關碳排放圖表嗎";
echo "Query: $query\n";
list($context, $usedPages) = selectRelevantPages($pagesData, $query, 10);

echo "--- Top 10 Selected Pages ---\n";
echo $context;
echo "Used page numbers: " . implode(', ', $usedPages) . "\n";
?>
