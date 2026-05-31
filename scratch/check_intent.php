<?php
require_once dirname(__DIR__) . '/config.php';
$db = getDB();
$res = $db->query("SELECT id, company_id, year, intent_score FROM carbon_emissions");
while ($row = $res->fetch_assoc()) {
    echo "ID: {$row['id']} | Co: {$row['company_id']} | Year: {$row['year']} | Intent: {$row['intent_score']}\n";
}
