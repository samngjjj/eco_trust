<?php
require_once dirname(__DIR__) . '/auth_check.php';
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$db = getDB();

if (!isset($_GET['ids']) || empty($_GET['ids'])) {
    echo json_encode(['error' => 'No company IDs provided']);
    exit;
}

$ids_str = $_GET['ids'] ?? '';
$ids_array = explode(',', $ids_str);
$ids_safe = [];
foreach($ids_array as $id) {
    $id = trim($id);
    if ($id !== '') {
        $ids_safe[] = "'" . $db->real_escape_string($id) . "'";
    }
}

if (empty($ids_safe)) {
    // 沒選公司時，回傳空陣列而非報錯，避免彈窗
    echo json_encode([]);
    exit;
}
$in_clause = implode(',', $ids_safe);

// Join companies with emissions and their quarterly ROE averages
$sql = "
SELECT 
    c.symbol AS id, c.name,
    cp.year AS cp_year, cp.roe,
    ce.year AS ce_year, ce.confidence_score
FROM companies c
JOIN carbon_emissions ce ON c.symbol = ce.company_id
LEFT JOIN company_performance cp ON c.symbol = cp.company_symbol AND ce.year = cp.year
WHERE c.symbol IN ($in_clause)
  AND ce.confidence_score IS NOT NULL
ORDER BY c.symbol, ce.year ASC
";

$result = $db->query($sql);
if (!$result) {
    echo json_encode(['error' => 'Database error', 'db_error' => $db->error]);
    exit;
}

$data = [];
while($row = $result->fetch_assoc()) {
    $cid = $row['id'];
    if (!isset($data[$cid])) {
        $data[$cid] = [
            'id' => $cid,
            'name' => $row['name'],
            'roe_groups' => [],
            'score' => [],
            'year' => []
        ];
    }
    
    $year = (int)$row['ce_year'];
    
    // Collect ROE values for the year from the left join
    if ($row['roe'] !== null) {
        if (!isset($data[$cid]['roe_groups'][$year])) {
            $data[$cid]['roe_groups'][$year] = [];
        }
        $data[$cid]['roe_groups'][$year][] = (float)$row['roe'];
    }
    
    if (!in_array($year, $data[$cid]['year'])) {
        $data[$cid]['score'][] = (float)$row['confidence_score'];
        $data[$cid]['year'][] = $year;
    }
}

// Calculate averages for ROE per year and compute optional sentiment weighting
$apply_weight = isset($_GET['weighted']) && $_GET['weighted'] == '1';

foreach ($data as $cid => &$cdata) {
    // 1. Calculate News Sentiment Offset (Internal System Rule: Pos +0.1, Neg -0.2)
    $sentiment_offset = 0;
    if ($apply_weight) {
        $n_res = $db->query("SELECT sentiment, COUNT(*) as c FROM news WHERE company_symbol = '$cid' GROUP BY sentiment");
        if ($n_res) {
            $pos = 0; $neg = 0; $total_n = 0;
            while($nr = $n_res->fetch_assoc()) {
                if ($nr['sentiment'] == 'Positive') $pos = (int)$nr['c'];
                if ($nr['sentiment'] == 'Negative') $neg = (int)$nr['c'];
                $total_n += (int)$nr['c'];
            }
            if ($total_n > 0) {
                $sentiment_offset = (($pos / $total_n) * 0.1) - (($neg / $total_n) * 0.2);
            }
        }
    }

    $cdata['roe'] = [];
    $new_scores = [];
    foreach ($cdata['year'] as $idx => $yr) {
        // Collect average ROE
        if (isset($cdata['roe_groups'][$yr]) && count($cdata['roe_groups'][$yr]) > 0) {
            $avg = array_sum($cdata['roe_groups'][$yr]) / count($cdata['roe_groups'][$yr]);
            $cdata['roe'][] = round($avg, 2);
        } else {
            $cdata['roe'][] = 0;
        }

        // Apply internal weighting only if requested
        $base_score = (float)$cdata['score'][$idx];
        $final_score = max(0, min(1, $base_score + $sentiment_offset));
        $new_scores[] = round($final_score, 4);
    }
    
    $cdata['score'] = $new_scores;
    unset($cdata['roe_groups']);
}






echo json_encode(array_values($data), JSON_UNESCAPED_UNICODE);
