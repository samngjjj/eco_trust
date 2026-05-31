<?php
require_once dirname(__DIR__) . '/auth_check.php';
require_once dirname(__DIR__) . '/config.php';
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');


$db = getDB();
$symbol = $_GET['symbol'] ?? '';

if (!$symbol) {
    echo json_encode(['error' => '未提供股票代碼'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 刪除該公司的所有新聞，從而使其從「最近搜尋」中消失
$stmt = $db->prepare("DELETE FROM news WHERE company_symbol = ?");
$stmt->bind_param('s', $symbol);
$success = $stmt->execute();

if ($success) {
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['error' => '刪除失敗: ' . $db->error], JSON_UNESCAPED_UNICODE);
}
