<?php
require_once dirname(__DIR__) . '/config.php';
$db = getDB();

echo "=== Companies ===\n";
$res = $db->query("SELECT * FROM companies");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}

echo "=== Performance for 1103 ===\n";
$res = $db->query("SELECT * FROM company_performance WHERE company_symbol = 1103");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
