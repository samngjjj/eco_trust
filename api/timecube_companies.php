<?php
require_once dirname(__DIR__) . '/auth_check.php';
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$db = getDB();

$sql = "
SELECT c.symbol, c.name
FROM companies c
JOIN (
    SELECT company_id
    FROM carbon_emissions
    WHERE confidence_score IS NOT NULL 
      AND year IN (2022, 2023, 2024)
    GROUP BY company_id
    HAVING COUNT(DISTINCT year) = 3
) ce ON c.symbol = ce.company_id
WHERE EXISTS (SELECT 1 FROM company_performance cp WHERE cp.company_symbol = c.symbol AND cp.year = 2022)
  AND EXISTS (SELECT 1 FROM company_performance cp WHERE cp.company_symbol = c.symbol AND cp.year = 2023)
  AND EXISTS (SELECT 1 FROM company_performance cp WHERE cp.company_symbol = c.symbol AND cp.year = 2024)
ORDER BY c.symbol
";

$result = $db->query($sql);
$rows = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}
echo json_encode($rows, JSON_UNESCAPED_UNICODE);
?>
