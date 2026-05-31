<?php
require_once __DIR__ . '/config.php';
$db = getDB();

echo "🛠️ 正在初始化 test2 資料庫缺失結構...\n";

// 1. 建立 company_performance 資料表
$sql = "CREATE TABLE IF NOT EXISTS `company_performance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_symbol` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `quarter` int(1) NOT NULL,
  `roe` double DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_performance_company` (`company_symbol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($db->query($sql)) {
    echo "✅ 資料表 company_performance 建立成功\n";
} else {
    echo "❌ 建立資料表失敗: " . $db->error . "\n";
}

// 2. 補完外鍵約束 (如果不存在)
$db->query("ALTER TABLE `company_performance` ADD CONSTRAINT `fk_performance_company` FOREIGN KEY (`company_symbol`) REFERENCES `companies` (`symbol`) ON DELETE CASCADE ON UPDATE CASCADE");

// 3. 置入測試資料 (台泥 1101, 台積電 2330, 鴻海 2317 等)
// 我挑選了一些基礎數據以確保各圖表能正確呈現
$test_performance = [
    [1101, 2022, 1, 1.25], [1101, 2022, 2, 0.95], [1101, 2022, 3, 1.12], [1101, 2022, 4, 1.08],
    [1101, 2023, 1, 2.11], [1101, 2023, 2, 2.34], [1101, 2023, 3, 2.51], [1101, 2023, 4, 2.18],
    [1101, 2024, 1, 3.12], [1101, 2024, 2, 3.45], [1101, 2024, 3, 3.67], [1101, 2024, 4, 3.82],

    [2330, 2022, 1, 9.15], [2330, 2022, 2, 8.85], [2330, 2022, 3, 9.42], [2330, 2022, 4, 9.18],
    [2330, 2023, 1, 8.41], [2330, 2023, 2, 8.64], [2330, 2023, 3, 8.91], [2330, 2023, 4, 8.58],
    [2330, 2024, 1, 10.12], [2330, 2024, 2, 10.45], [2330, 2024, 3, 11.27], [2330, 2024, 4, 11.52],

    [2317, 2022, 1, 2.45], [2317, 2022, 2, 2.35], [2317, 2022, 3, 2.52], [2317, 2022, 4, 2.58],
    [2317, 2023, 1, 2.11], [2317, 2023, 2, 2.24], [2317, 2023, 3, 2.61], [2317, 2023, 4, 2.78],
    [2317, 2024, 1, 3.02], [2317, 2024, 2, 3.15], [2317, 2024, 3, 3.37], [2317, 2024, 4, 3.42]
];

$stmt = $db->prepare("INSERT IGNORE INTO `company_performance` (company_symbol, year, quarter, roe) VALUES (?,?,?,?)");
foreach ($test_performance as $p) {
    $stmt->bind_param('iiid', $p[0], $p[1], $p[2], $p[3]);
    $stmt->execute();
}
echo "✅ 測試業績數據 (ROE) 置入完成\n";

// 4. 確保 industries 表中包含這些行業
$industries_test = [
    [13, '水泥工業'],
    [24, '半導體業'],
    [25, '電腦及週邊設備業']
];
$stmt_ind = $db->prepare("INSERT IGNORE INTO `industries` (id, name) VALUES (?,?)");
foreach ($industries_test as $i) {
    $stmt_ind->bind_param('is', $i[0], $i[1]);
    $stmt_ind->execute();
}
echo "✅ 行業分類數據補齊完成\n";

echo "🚀 資料庫修復完畢，所有頁面功能應可正常運作。\n";
