-- ============================================================
-- EcoTrust AI v2.0 — Gen-2 資料庫擴充腳本
-- 目標資料庫：test2 (MariaDB)
-- 注意：MariaDB 使用 JSON 型別，不支援 JSONB (為 PostgreSQL 語法)
-- ============================================================

USE `test2`;

-- 1. 擴充 carbon_emissions 資料表以對接 Gen-2 模型輸出
ALTER TABLE `carbon_emissions`
  ADD COLUMN IF NOT EXISTS `total_promises`             INT            DEFAULT NULL COMMENT '總承諾數',
  ADD COLUMN IF NOT EXISTS `quant_rate`                 FLOAT          DEFAULT NULL COMMENT '量化承諾比率 (0.0~1.0)',
  ADD COLUMN IF NOT EXISTS `timeframe_rate`             FLOAT          DEFAULT NULL COMMENT '有時限承諾比率 (0.0~1.0)',
  ADD COLUMN IF NOT EXISTS `topic_distribution`         JSON           DEFAULT NULL COMMENT '各主題分布 E/S/G 子項目',
  ADD COLUMN IF NOT EXISTS `high_confidence_commitments` JSON          DEFAULT NULL COMMENT '高信度承諾列表 (用於報告證據)',
  ADD COLUMN IF NOT EXISTS `raw_gen2_output`            JSON           DEFAULT NULL COMMENT '完整 Gen-2 原始 JSON 備份',
  ADD COLUMN IF NOT EXISTS `analyst_comment`            TEXT           DEFAULT NULL COMMENT '分析師人工修正備注',
  ADD COLUMN IF NOT EXISTS `analyst_score_override`     DECIMAL(5,4)  DEFAULT NULL COMMENT '分析師覆寫信心分數';

-- 2. 驗證欄位已成功加入
SELECT COLUMN_NAME, DATA_TYPE, COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'test2'
  AND TABLE_NAME   = 'carbon_emissions'
ORDER BY ORDINAL_POSITION;
