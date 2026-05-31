<?php
require_once dirname(__DIR__) . '/auth_check.php';
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json; charset=utf-8');
requireAdmin();

$body = json_decode(file_get_contents('php://input'), true);
$id    = $body['id']    ?? null;
$field = $body['field'] ?? null;
$value = $body['value'] ?? null;
$table = $body['table'] ?? 'companies';

if(!$id || !$field || $value === null){
    echo json_encode(['error'=>'缺少必要參數']); exit;
}

$db = getDB();

// Whitelist allowed fields to prevent SQL injection
$allowed = [
    'companies'       => ['name','industry_id'],
    'carbon_emissions'=> ['company_id','year','confidence_score']
];

if(!isset($allowed[$table]) || !in_array($field, $allowed[$table])){
    echo json_encode(['error'=>'不允許修改此欄位']); exit;
}

if($table === 'companies'){
    $stmt = $db->prepare("UPDATE `companies` SET `$field`=? WHERE symbol=?");
    $stmt->bind_param('ss', $value, $id);
} else {
    $stmt = $db->prepare("UPDATE `$table` SET `$field`=? WHERE id=?");
    $stmt->bind_param('si', $value, $id);
}

if($stmt->execute()){
    echo json_encode(['success'=>true,'affected'=>$stmt->affected_rows]);
} else {
    echo json_encode(['error'=>'更新失敗: '.$db->error]);
}
