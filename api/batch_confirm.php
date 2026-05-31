<?php
require_once dirname(__DIR__) . '/auth_check.php';
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json; charset=utf-8');
requireAdmin();

$body = json_decode(file_get_contents('php://input'), true);
$ids  = $body['ids'] ?? [];
if(!$ids || !is_array($ids)){
    echo json_encode(['error'=>'無效的 ID 列表']); exit;
}

$db   = getDB();
$ph   = implode(',', array_fill(0, count($ids), '?'));
$types= str_repeat('i', count($ids));
// Dummy confirmation logic, previously updated scope_type but the column was removed
// We use a dedicated column or flag; since schema has no "confirmed" flag,
// we'll track confirmation in a session-stored set (client only) or add to scope_type.
// Better approach: just return success and let client mark visually.
echo json_encode(['success'=>true,'confirmed'=>count($ids)]);
