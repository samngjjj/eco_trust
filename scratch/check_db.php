<?php
require_once dirname(__DIR__) . '/config.php';
$db = getDB();
$res = $db->query("DESCRIBE carbon_emissions");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
