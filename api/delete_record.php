<?php
require_once dirname(__DIR__) . '/auth_check.php';
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json; charset=utf-8');
requireAdmin();

$body  = json_decode(file_get_contents('php://input'), true);
$id    = (int)($body['id'] ?? 0);
$table = $body['table'] ?? 'carbon_emissions';

if(!$id){ echo json_encode(['error'=>'缺少 ID']); exit; }

$db = getDB();
$allowed_tables = ['carbon_emissions'];
if(!in_array($table, $allowed_tables)){ echo json_encode(['error'=>'不允許刪除此資料表']); exit; }

$stmt = $db->prepare("DELETE FROM `$table` WHERE id=?");
$stmt->bind_param('i', $id);
if($stmt->execute()){
    echo json_encode(['success'=>true,'affected'=>$stmt->affected_rows]);
} else {
    echo json_encode(['error'=>'刪除失敗: '.$db->error]);
}
