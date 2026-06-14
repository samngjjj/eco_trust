<?php
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$idsStr = $_GET['ids'] ?? '';
$startQ = $_GET['start_q'] ?? '';
$endQ   = $_GET['end_q'] ?? '';

$idsStr = $_GET['ids'] ?? '';
$ids_array = explode(',', $idsStr);
$ids_safe = [];
foreach($ids_array as $id) {
    if (trim($id) !== '') {
        $ids_safe[] = "'" . $db->real_escape_string(trim($id)) . "'";
    }
}

if (empty($ids_safe)) {
    echo json_encode(['confidence_trend' => [], 'roe_trend' => [], 'scatter' => [], 'available_quarters' => []]);
    exit;
}

$idList = implode(',', $ids_safe);

// ── 0. 年份對應新聞加權 (v2.0：按 company+year 各自計算) ──
$weighted = isset($_GET['weighted']) && $_GET['weighted'] == '1';
// sentimentMap 格式：["companyId_year" => offset_float]
$sentimentMap = [];
if ($weighted) {
    foreach ($ids_array as $cid) {
        $cid_safe = $db->real_escape_string(trim($cid));
        $n_res = $db->query(
            "SELECT report_year, sentiment, COUNT(*) as c
             FROM news
             WHERE company_symbol = '$cid_safe'
               AND report_year IS NOT NULL
             GROUP BY report_year, sentiment"
        );
        if ($n_res) {
            $year_counts = [];
            while ($nr = $n_res->fetch_assoc()) {
                $yr   = (int)$nr['report_year'];
                $sent = $nr['sentiment'];
                $cnt  = (int)$nr['c'];
                if (!isset($year_counts[$yr])) {
                    $year_counts[$yr] = ['Positive' => 0, 'Negative' => 0, 'total' => 0];
                }
                $year_counts[$yr]['total'] += $cnt;
                if ($sent === 'Positive') $year_counts[$yr]['Positive'] += $cnt;
                if ($sent === 'Negative') $year_counts[$yr]['Negative'] += $cnt;
            }
            foreach ($year_counts as $yr => $yc) {
                if ($yc['total'] > 0) {
                    $key = "{$cid_safe}_{$yr}";
                    $sentimentMap[$key] = (($yc['Positive'] / $yc['total']) * 0.1)
                                        - (($yc['Negative'] / $yc['total']) * 0.2);
                }
            }
        }
    }
}

// ── 1. Confidence Trend (Zone 2) ──
$confSql = "SELECT ce.company_id, ce.year, ce.confidence_score, c.name 
            FROM carbon_emissions ce
            JOIN companies c ON ce.company_id = c.symbol
            WHERE ce.company_id IN ($idList)
            ORDER BY ce.company_id, ce.year ASC";
$confRes = $db->query($confSql);
$confTrend = [];
while ($row = $confRes->fetch_assoc()) {
    $cid = $row['company_id'];
    $yr  = (int)$row['year'];
    if (!isset($confTrend[$cid])) {
        $confTrend[$cid] = ['code' => $cid, 'name' => $row['name'], 'series' => []];
    }
    $base_score = (float)$row['confidence_score'];
    // v2.0: 只用對應年份的新聞做加權
    $offset      = $sentimentMap["{$cid}_{$yr}"] ?? 0;
    $final_score = max(0, min(1, $base_score + $offset));
    $confTrend[$cid]['series'][] = [
        'year'       => $yr,
        'confidence' => round($final_score, 4)
    ];
}

// ── 2. ROE Quarterly (Zone 1) ──
$roeSql = "SELECT cp.company_symbol, cp.year, cp.quarter, cp.roe, c.name 
           FROM company_performance cp
           JOIN companies c ON cp.company_symbol = c.symbol
           WHERE cp.company_symbol IN ($idList)
           ORDER BY cp.company_symbol, cp.year ASC, cp.quarter ASC";
$roeRes = $db->query($roeSql);
$roeTrend = [];
$availableQuarters = [];

while ($row = $roeRes->fetch_assoc()) {
    $cid = $row['company_symbol'];
    $period = $row['year'] . "Q" . $row['quarter'];
    
    if (!in_array($period, $availableQuarters)) {
        $availableQuarters[] = $period;
    }

    if ($startQ !== '' && $period < $startQ) {
        continue;
    }
    if ($endQ !== '' && $period > $endQ) {
        continue;
    }

    if (!isset($roeTrend[$cid])) {
        $roeTrend[$cid] = [
            'code' => $cid,
            'name' => $row['name'],
            'quarterly_series' => []
        ];
    }
    $roeTrend[$cid]['quarterly_series'][] = [
        'period' => $period,
        'roe' => (float)$row['roe']
    ];
}
sort($availableQuarters);

// ── 3. Scatter/Combined Data (Zone 4) ──
$combinedSql = "SELECT ce.company_id as code, c.name, ce.year, ce.confidence_score as y,
                (SELECT AVG(roe) FROM company_performance cp WHERE cp.company_symbol = ce.company_id AND cp.year = ce.year) as x
                FROM carbon_emissions ce
                JOIN companies c ON ce.company_id = c.symbol
                WHERE ce.company_id IN ($idList)
                ORDER BY ce.year DESC, ce.company_id ASC";
$scRes = $db->query($combinedSql);
$scatter = [];
while ($row = $scRes->fetch_assoc()) {
    $cid   = $row['code'];
    $yr    = (int)$row['year'];
    $base_y = (float)$row['y'];
    // v2.0: 對應年份加權
    $offset  = $sentimentMap["{$cid}_{$yr}"] ?? 0;
    $final_y = max(0, min(1, $base_y + $offset));

    $scatter[] = [
        'code' => $row['code'],
        'name' => $row['name'],
        'year' => $yr,
        'x'    => $row['x'] !== null ? (float)$row['x'] : 0,
        'y'    => round($final_y, 4)
    ];
}

echo json_encode([
    'confidence_trend' => array_values($confTrend),
    'roe_trend'        => array_values($roeTrend),
    'scatter'          => $scatter,
    'available_quarters' => $availableQuarters
], JSON_UNESCAPED_UNICODE);

