<?php
require_once dirname(__DIR__) . '/auth_check.php';
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$search   = trim($_GET['search']   ?? '');
$industry = trim($_GET['industry'] ?? '');

$where = []; $params = []; $types = '';

if($search !== ''){
    $like = "%$search%";
    $where[] = "(c.symbol LIKE ? OR c.name LIKE ?)";
    $params[] = $like; $params[] = $like;
    $types .= 'ss';
}
if($industry !== ''){
    $where[] = "i.name = ?";
    $params[] = $industry; $types .= 's';
}

$sql = "SELECT c.symbol, c.name, i.name as industry 
        FROM companies c 
        LEFT JOIN industries i ON c.industry_id = i.id";
if($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY c.symbol LIMIT 100";

$stmt = $db->prepare($sql);
if($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
echo json_encode($rows, JSON_UNESCAPED_UNICODE);
