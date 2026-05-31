<?php
require_once dirname(__DIR__) . '/auth_check.php';
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$company_id = trim($_GET['company_id'] ?? '');
$year       = (int)($_GET['year'] ?? 0);

$where = []; $params = []; $types = '';
if($company_id !== ''){ $where[]='company_id=?'; $params[]=$company_id; $types.='s'; }
if($year > 0)         { $where[]='year=?';        $params[]=$year;       $types.='i'; }

$sql = "SELECT id, company_id, year, confidence_score
        FROM carbon_emissions";
if($where) $sql .= " WHERE " . implode(' AND ',$where);
$sql .= " ORDER BY year";

$stmt = $db->prepare($sql);
if($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
echo json_encode($rows, JSON_UNESCAPED_UNICODE);
