<?php
$jsonPath = dirname(__DIR__) . '/uploads/1101_台泥_2023_pages.json';
if (!file_exists($jsonPath)) {
    die("Index not found.\n");
}
$data = json_decode(file_get_contents($jsonPath), true);

$keywords = ['附錄', '圖', '表', '溫室氣體排放'];
foreach ($keywords as $kw) {
    echo "--- Search for keyword: '$kw' ---\n";
    $matches = [];
    foreach ($data['pages'] as $p) {
        if (mb_strpos($p['text'], $kw) !== false) {
            $matches[] = $p['page'];
        }
    }
    echo "Found on pages: " . implode(', ', array_slice($matches, 0, 30)) . " (Total: " . count($matches) . ")\n\n";
}
?>
