<?php
// Mocking selectRelevantPages helper from chat_api.php for verification
function selectRelevantPages($pagesData, $query, $maxPages = 40) {
    if (empty($pagesData['pages'])) return ['', []];
    
    // Extract keywords from query (split by common delimiters, filter short words)
    $keywords = preg_split('/[\s，。、？！,\.\?!]+/u', mb_strtolower($query, 'UTF-8'));
    $keywords = array_filter($keywords, function($k) { return mb_strlen($k, 'UTF-8') >= 2; });
    if (empty($keywords)) $keywords = [mb_strtolower($query, 'UTF-8')];
    
    echo "Keywords extracted: " . implode(', ', $keywords) . "\n";
    
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
        $contextStr .= "【第{$s['page']}頁】(Score: {$s['score']})\n" . mb_substr($s['text'], 0, 100) . "...\n\n";
        $pageNums[] = $s['page'];
    }
    
    return [$contextStr, $pageNums];
}

$jsonPath = dirname(__DIR__) . '/uploads/1101_台泥_2023_pages.json';
if (!file_exists($jsonPath)) {
    die("Error: JSON index file not found at $jsonPath\n");
}

$pagesData = json_decode(file_get_contents($jsonPath), true);
$query = "溫室氣體排放量 碳排放";

echo "Running query test: '$query'\n";
list($context, $usedPages) = selectRelevantPages($pagesData, $query, 5);

echo "\n--- Top 5 Selected Pages ---\n";
echo $context;
echo "Used page numbers: " . implode(', ', $usedPages) . "\n";
?>
