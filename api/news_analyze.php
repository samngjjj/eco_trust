<?php
require_once dirname(__DIR__) . '/auth_check.php';
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$id = $_GET['id'] ?? 0;
if (!$id) exit(json_encode(['error' => 'No ID']));

// ── 本地 ESG 中文情感詞典（與 news_nlp.py 同步）──────────────────────────
$POSITIVE = [
    "減碳","碳中和","零碳","淨零","再生能源","綠電","節能","永續",
    "ESG","CSR","社會責任","獲獎","認證","ISO","TCFD","GRI",
    "榮獲","優良","提升","增長","成長","突破","達標","通過",
    "合作","創新","投資","擴大","推動","落實","承諾","發布",
    "第一","領先","優秀","卓越","良好","正面","積極","改善",
    "綠色","環保","清潔","低碳","循環","公益","捐助","志工",
    "多元","包容","平等","人才","培訓","效率","獲益",
    "股息","獲利","盈利","成功","上漲","增加","拓展",
];
$NEGATIVE = [
    "醜聞","違規","罰款","懲處","訴訟","告發","調查","檢察",
    "汙染","污染","排放超標","廢水","廢氣","毒","違法",
    "裁員","解雇","勞資","糾紛","抗議","罷工","剝削",
    "弊案","貪汙","造假","不實","虛報","欺騙","操縱",
    "下跌","虧損","衰退","縮減","停產","停業",
    "事故","意外","爆炸","火災","洩漏",
    "撤資","退出","降評","負面","批評","質疑","風險",
    "危機","困境","倒閉","破產","債務","違約",
];
$STRONG_POS = ["碳中和","淨零","零碳","ESG","永續報告","再生能源","GRI","TCFD","榮獲"];
$STRONG_NEG = ["汙染","污染","違法","罰款","造假","貪汙","訴訟","醜聞"];

function keyword_sentiment(string $text, array $pos, array $neg, array $sp, array $sn): array {
    $posScore = 0.0;
    $negScore = 0.0;
    foreach ($pos as $kw) {
        if (mb_strpos($text, $kw) !== false)
            $posScore += in_array($kw, $sp) ? 2.0 : 1.0;
    }
    foreach ($neg as $kw) {
        if (mb_strpos($text, $kw) !== false)
            $negScore += in_array($kw, $sn) ? 2.0 : 1.0;
    }
    $total = $posScore + $negScore;
    if ($total === 0.0) return ['sentiment' => 'Neutral', 'confidence' => 0.60];
    $ratio = $posScore / $total;
    if ($ratio >= 0.6) {
        return ['sentiment' => 'Positive', 'confidence' => round(min(0.65 + min($posScore, 5) * 0.06, 0.95), 4)];
    } elseif ($ratio <= 0.4) {
        return ['sentiment' => 'Negative', 'confidence' => round(min(0.65 + min($negScore, 5) * 0.06, 0.95), 4)];
    } else {
        return ['sentiment' => 'Neutral',  'confidence' => round(0.55 + $ratio * 0.1, 4)];
    }
}

// ── 取得標題並分析 ─────────────────────────────────────────────────────
$db  = getDB();
$res = $db->query("SELECT title FROM news WHERE id = " . intval($id));
if ($row = $res->fetch_assoc()) {
    $result = keyword_sentiment($row['title'], $POSITIVE, $NEGATIVE, $STRONG_POS, $STRONG_NEG);

    $stmt = $db->prepare("UPDATE news SET sentiment=?, confidence=? WHERE id=?");
    $stmt->bind_param('sdi', $result['sentiment'], $result['confidence'], $id);
    $stmt->execute();

    echo json_encode(['id' => $id, 'sentiment' => $result['sentiment'], 'confidence' => $result['confidence']]);
    exit;
}
echo json_encode(['error' => 'News not found']);
