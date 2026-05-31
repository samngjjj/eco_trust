import os
import re
import math
import numpy as np
import pdfplumber
import torch
import pandas as pd
import jieba
import logging
import warnings
import sys
from pathlib import Path
from transformers import AutoTokenizer, AutoModelForSequenceClassification

# -------------------------------
# 0. 環境與編碼設定
# -------------------------------
if sys.stdout.encoding != 'utf-8':
    try:
        sys.stdout.reconfigure(encoding='utf-8')
    except: pass

os.environ["HF_HUB_DISABLE_PROGRESS_BARS"] = "1"
os.environ["TRANSFORMERS_NO_ADVISORY_WARNINGS"] = "1"
warnings.filterwarnings("ignore")
logging.getLogger("transformers").setLevel(logging.ERROR)

# -------------------------------
# 1. 嚴謹的權重與門檻設定 (防止分數飽和)
# -------------------------------
WEIGHT_CONFIG = {
    "W_INTENT": 0.6,          # 意圖分數權重 (FinBERT)
    "W_CREDIBILITY": 0.4,     # 誠信可靠性權重
    "SUB_W_NUMERACY": 0.45,   # 數字密度權重
    "SUB_W_KPI": 0.35,        # 關鍵指標多樣性權重
    "SUB_W_RISK": 0.20        # 外部風險係數權重
}

NORM_THRESHOLDS = {
    "NUMERACY_MAX": 0.20,     # 數字佔比達到 20% 才拿滿分 (原為 0.05)
    "KPI_COUNT_MAX": 15,      # 關鍵指標需達到 15 個才拿滿分 (原為 5)
    "RISK_MAX_SCALE": 100.0   # 外部風險總分
}

# -------------------------------
# Gen-2: 承諾提取關鍵字
# -------------------------------
PROMISE_VERBS = [
    "承諾", "目標", "計畫", "預計", "將", "擬", "設定", "達成", "實現",
    "commit", "target", "aim", "plan", "pledge", "goal", "objective",
    "intend", "will achieve", "by 20"
]
QUANT_PATTERNS = [
    r'\d+(?:\.\d+)?\s*%',          # 百分比
    r'\d+(?:\.\d+)?\s*tCO2e',      # 碳排單位
    r'\d+(?:\.\d+)?\s*(噸|萬噸|千噸|公噸)',
    r'\d+(?:\.\d+)?\s*(kWh|MWh|GWh|MW)',
    r'\d+(?:\.\d+)?\s*(億|萬|千)\s*(元|度)',
]
TIMEFRAME_PATTERNS = [
    r'20[2-5]\d\s*年',              # 2020-2059 年
    r'by\s*20[2-5]\d',             # by 20XX
    r'\d{4}\s*(年|年底|年前|年末)',
    r'(第|第[一二三四])[一二三四五]\s*年',
]

# -------------------------------
# 2. 擴展關鍵字矩陣
# -------------------------------
ESG_KEYWORDS_MAP = {
    "E": ["carbon", "emission", "sustainability", "減碳", "溫室氣體", "再生能源", "水資源", "廢棄物", "net zero", "範疇一", "範疇二", "範疇三", "Scope 1/2/3", "tCO2e", "溫室氣體盤查", "碳中和", "SBTi", "RE100", "能源密集度", "內部碳定價", "廢棄物轉化率", "循環經濟", "再生能源", "綠電採購", "CPPA", "生質能", "生物多樣性", "減塑", "零填埋", "UL2799", "TCFD", "氣候相關財務揭露", "實體風險", "轉型風險", "碳邊境稅", "CBAM", "氣候情境分析", "Scenario Analysis"],
    "S": ["human rights", "勞工權益", "職業安全", "社區參與", "人才發展", "供應鏈", "diversity", "離職率", "員工滿意度", "薪資性別平等", "Gender Pay Gap", "多元平等包容", "DEI", "人權盡職調查", "強迫勞動", "結社自由", "ISO 45001", "培訓時數", "育嬰留停復職率", "接班人計畫", "員工持股", "關鍵人才留才", "供應鏈審核", "衝突礦產", "在地採購比率", "社會影響力評估", "隱私保護", "GDPR"],
    "G": ["governance", "ethics", "compliance", "董事會", "誠信經營", "風險管理", "反貪腐", "audit", "獨立董事", "董事多元化", "審計委員會", "薪酬委員會", "董事出席率", "董事長與總經理兼任", "反洗錢", "反壟斷", "檢舉機制", "Whistleblowing", "誠信經營手冊", "稅務透明", "政治捐獻", "資安 ISO 27001", "高階主管薪酬連動", "ESG指導委員會", "永續發展長", "CSO"],
    "HARD_METRICS": ["KPI", "目標達成率", "基準年", "下降%", "增長%", "驗證", "第三方認證", "ISO", "SASB", "TCFD", "第三方確信", "獨立保證報告", "SGS", "BSI", "DNV", "Deloitte", "PwC", "EY", "KPMG", "會計師核閱", "GRI 2021", "AA1000", "ISAE 3000", "ISO 14064", "ISO 14067", "有限確信", "Limited Assurance", "合理確信", "Reasonable Assurance"]
}
ALL_KEYWORDS = [k for sub in ESG_KEYWORDS_MAP.values() for k in sub]

# Minimum distinct ESG keyword hits for a document to be considered an ESG report
ESG_MIN_KEYWORD_HITS = 5

# Core ESG sentinel keywords - at least 2 of these must appear for a basic pass
ESG_SENTINEL_KEYWORDS = [
    "sustainability", "ESG", "永續", "溫室氣體", "碳排", "碳中和",
    "governance", "董事會", "人才發展", "再生能源", "net zero",
    "TCFD", "GRI", "SASB", "tCO2e", "環境", "誠信", "社會責任",
    "Scope", "範疇", "ISO 14064", "勞工"
]

def is_esg_report(text: str) -> tuple[bool, int]:
    """Quick pre-check: returns (is_esg, keyword_hit_count)."""
    text_lower = text.lower()
    hits = sum(1 for k in ALL_KEYWORDS if k.lower() in text_lower)
    sentinel_hits = sum(1 for k in ESG_SENTINEL_KEYWORDS if k.lower() in text_lower)
    return (hits >= ESG_MIN_KEYWORD_HITS and sentinel_hits >= 2), hits

# -------------------------------
# 3. 模型載入
# -------------------------------
BASE_DIR = Path(__file__).resolve().parent
LOCAL_MODEL_DIR = BASE_DIR / "finbert_model"
FALLBACK_MODEL_ID = "yiyanghkust/finbert-tone-chinese"

print("正在初始化 FinBERT 模型...")
try:
    if LOCAL_MODEL_DIR.exists():
        tokenizer = AutoTokenizer.from_pretrained(str(LOCAL_MODEL_DIR), local_files_only=True)
        model = AutoModelForSequenceClassification.from_pretrained(str(LOCAL_MODEL_DIR), local_files_only=True)
    else:
        tokenizer = AutoTokenizer.from_pretrained(FALLBACK_MODEL_ID)
        model = AutoModelForSequenceClassification.from_pretrained(FALLBACK_MODEL_ID)
    device = torch.device("cpu")
    model.to(device)
except Exception as e:
    print(f"模型載入失敗: {e}")
    sys.exit(1)

# -------------------------------
# 4. 文本處理與動態採樣
# -------------------------------
def pdf_to_text(pdf_path):
    text = ""
    try:
        with pdfplumber.open(pdf_path) as pdf:
            for page in pdf.pages:
                page_text = page.extract_text()
                if page_text: text += page_text + "\n"
    except Exception as e:
        print(f"PDF 讀取錯誤: {e}")
    return text

def get_smart_chunks(text, max_chunks=100):
    # 以句號或換行分割
    sentences = re.split(r'[。\n]', text)
    sentences = [s.strip() for s in sentences if len(s.strip()) > 8]
    
    if not sentences: return []

    # 策略：優先提取含有「硬指標」關鍵字的段落，其餘進行均勻抽樣
    critical_chunks = [s for s in sentences if any(k in s for k in ESG_KEYWORDS_MAP["HARD_METRICS"])]
    
    # 均勻抽樣（覆蓋前、中、後段落）
    indices = np.linspace(0, len(sentences)-1, max_chunks).astype(int)
    sampled_chunks = [sentences[i] for i in indices]
    
    # 合併並確保不重複 (優先保留 30 個關鍵指標段落，其餘補足至 100 個)
    final_chunks = list(dict.fromkeys(critical_chunks[:30] + sampled_chunks))
    return final_chunks[:max_chunks]

# -------------------------------
# Gen-2: 承諾提取與分析
# -------------------------------
def extract_gen2_promises(text: str) -> dict:
    """提取 Gen-2 精細指標：承諾數、量化比率、時限比率、主題分布、高信度承諾。"""
    sentences = re.split(r'[。\n]', text)
    sentences = [s.strip() for s in sentences if len(s.strip()) > 10]

    promises = []
    for s in sentences:
        s_lower = s.lower()
        if any(v in s_lower for v in PROMISE_VERBS):
            has_quant = any(re.search(p, s) for p in QUANT_PATTERNS)
            has_time  = any(re.search(p, s) for p in TIMEFRAME_PATTERNS)

            # 主題分類
            topic_scores = {cat: 0 for cat in ['E', 'S', 'G', 'HARD_METRICS']}
            for cat, kws in ESG_KEYWORDS_MAP.items():
                if any(k.lower() in s_lower for k in kws):
                    topic_scores[cat] += 1
            topic = max(topic_scores, key=topic_scores.get)
            if topic_scores[topic] == 0:
                topic = 'OTHER'

            # 信度評分 (有量化且有時限 = 高信度)
            confidence_level = 'HIGH' if (has_quant and has_time) else \
                               'MED'  if (has_quant or has_time) else 'LOW'

            promises.append({
                'text':       s[:200],          # 截短至 200 字
                'has_quant':  has_quant,
                'has_time':   has_time,
                'topic':      topic,
                'confidence': confidence_level,
            })

    total = len(promises)
    quant_rate    = round(sum(1 for p in promises if p['has_quant']) / max(1, total), 4)
    timeframe_rate = round(sum(1 for p in promises if p['has_time'])  / max(1, total), 4)

    # 主題分布統計
    topic_dist = {'E': 0, 'S': 0, 'G': 0, 'HARD_METRICS': 0, 'OTHER': 0}
    for p in promises:
        topic_dist[p['topic']] = topic_dist.get(p['topic'], 0) + 1

    # 高信度承諾清單 (最多 10 筆)
    high_conf = [p for p in promises if p['confidence'] == 'HIGH'][:10]

    return {
        'total_promises':              total,
        'quant_rate':                  quant_rate,
        'timeframe_rate':              timeframe_rate,
        'topic_distribution':          topic_dist,
        'high_confidence_commitments': high_conf,
    }

# -------------------------------
# 5. 核心評分邏輯 (去碳排化)
# -------------------------------
def analyze_sentiment(chunks):
    if not chunks: return 0.5
    scores = []
    for chunk in chunks:
        inputs = tokenizer(chunk, return_tensors="pt", truncation=True, max_length=512).to(device)
        with torch.no_grad():
            logits = model(**inputs).logits
        probs = torch.softmax(logits, dim=1).tolist()[0]
        
        # 動態判定計分邏輯
        if len(probs) == 3: # 假設為 [Neutral, Positive, Negative]
            # 意圖強度 = 積極機率 (probs[1]) 加上中立機率的一半 (以反映非消極性)
            # 或者直接使用 Positive 機率以求精準。這裡採用 (Positive - Negative + 1) / 2
            sentiment_score = (probs[1] - probs[2] + 1) / 2
            scores.append(sentiment_score)
        elif len(probs) >= 4: # 假設為 [E, S, G, None] 或更多
            # 取 E, S, G 的總和 (代表這段話與 ESG 相關的強度)
            scores.append(sum(probs[:3]))
        else:
            scores.append(max(probs))
    return np.mean(scores)

def get_metrics(text):
    # 1. 數字密度 (Numeracy)
    clean_text = re.sub(r'\s+', '', text)
    words = list(jieba.cut(clean_text))
    digits = re.findall(r'\d+(?:\.\d+)?%?', text)
    numeracy_score = len(digits) / max(1, len(words))

    # 2. KPI 多樣性 (使用正則匹配多個維度)
    found_kpis = 0
    for k in ALL_KEYWORDS:
        if k in text: found_kpis += 1
    
    return numeracy_score, found_kpis

def calculate_integrity_score(intent_avg, raw_num, raw_kpi, ext_risk):
    # 1. 數字密度：加強權重，只要高於平均就大幅加分
    norm_num = min(1.0, (raw_num / NORM_THRESHOLDS["NUMERACY_MAX"]) * 1.5)
    
    # 2. KPI 數量：改用更激進的平方根，讓「有做」跟「沒做」差距更大
    norm_kpi = min(1.0, (raw_kpi / NORM_THRESHOLDS["KPI_COUNT_MAX"]) ** 0.5)
    
    # 3. 誠信可靠性 (Credibility) — 引用 WEIGHT_CONFIG 中的 SUB_W 權重
    credibility_index = (WEIGHT_CONFIG["SUB_W_NUMERACY"] * norm_num) + \
                        (WEIGHT_CONFIG["SUB_W_KPI"] * norm_kpi)
    
    # 4. 強制拉開差距 (Contrast Boost) — 引用 WEIGHT_CONFIG 中的主權重 W_
    # 原本是 0.4~0.6 的區間，透過這行會擴散到 0.2~0.8
    raw_final = (intent_avg * WEIGHT_CONFIG["W_INTENT"]) + \
                (credibility_index * WEIGHT_CONFIG["W_CREDIBILITY"]) + \
                ((1 - ext_risk / 100) * WEIGHT_CONFIG.get("SUB_W_RISK", 0.2))
    
    # 5. 使用 Sigmoid 邏輯：好的更好，壞的更壞 (讓分數具有侵略性)
    # 中間點設為 0.5，斜率 10
    final_score = 1 / (1 + math.exp(-10 * (raw_final - 0.5)))
    
    return round(final_score, 4), round(credibility_index, 4)

# -------------------------------
# 6. 外部數據載入 (ROE & Risk)
# -------------------------------
def load_external_data(csv_path):
    if not os.path.exists(csv_path): return {}
    try:
        df = pd.read_csv(csv_path)
        return {str(row['Company']): {"risk": row['Risk_Score'], "roe": row['ROE']} for _, row in df.iterrows()}
    except: return {}

# -------------------------------
# 7. 主程式執行
# -------------------------------
def main():
    target_path = sys.argv[1] if len(sys.argv) > 1 else str(BASE_DIR)
    pdf_paths = list(Path(target_path).rglob("*.pdf")) if os.path.isdir(target_path) else [Path(target_path)]
    
    ext_data = load_external_data(os.path.join(os.path.dirname(target_path), "external_data.csv"))
    results = []

    print(f"開始處理 {len(pdf_paths)} 份文件...")

    for path in pdf_paths:
        print(f"正在分析: {path.name}")
        text = pdf_to_text(str(path))
        if len(text) < 300:
            print(f"NOT_ESG_REPORT: 文件內容過短（少於300字），無法判斷是否為ESG報告")
            sys.exit(2)

        # ── ESG Validation Gate ──────────────────────────────────────
        valid, kw_hits = is_esg_report(text)
        if not valid:
            print(f"NOT_ESG_REPORT: 偵測到的ESG關鍵字數量不足（{kw_hits} / 需要 {ESG_MIN_KEYWORD_HITS}）。此文件可能不是ESG永續報告，已中止分析。")
            sys.exit(2)
        # ─────────────────────────────────────────────────────────────

        # 1. 提取年份與公司名 (假設檔名包含公司名)
        year_match = re.search(r'20\d{2}', path.name)
        year = int(year_match.group()) if year_match else 2023
        company_name = path.stem.split('_')[0] # 假設命名格式為 公司名_年份.pdf

        # 2. 分析
        chunks = get_smart_chunks(text)
        intent_avg = analyze_sentiment(chunks)
        raw_num, raw_kpi = get_metrics(text)
        
        # 3. 獲取外部 ROE/風險 (若無則使用預設)
        ext = ext_data.get(company_name, {"risk": 30.0, "roe": 0.0})
        
        # 4. 計算最終信心得分 (Y軸)
        confidence_score, credibility = calculate_integrity_score(intent_avg, raw_num, raw_kpi, ext["risk"])

        # 5. Gen-2：承諾提取與分析
        gen2 = extract_gen2_promises(text)

        # Gen-2 JSON 摘要輸出供 PHP 解析
        import json
        print(f"GEN2_JSON:{json.dumps(gen2, ensure_ascii=False)}")

        results.append({
            "File Name":                    path.name,
            "公司名稱":                     company_name,
            "年份":                         year,
            "誠信信心分":                   confidence_score,
            "Credibility_Index":            confidence_score,
            "ROE":                          ext["roe"],
            "意圖強度":                     round(intent_avg, 4),
            "數據實質性":                   round(credibility, 4),
            "數字密度":                     round(raw_num, 4),
            "指標豐富度":                   raw_kpi,
            "total_promises":               gen2['total_promises'],
            "quant_rate":                   gen2['quant_rate'],
            "timeframe_rate":               gen2['timeframe_rate'],
            "topic_distribution":           json.dumps(gen2['topic_distribution'],          ensure_ascii=False),
            "high_confidence_commitments":  json.dumps(gen2['high_confidence_commitments'], ensure_ascii=False),
            "raw_gen2_output":              json.dumps(gen2,                                ensure_ascii=False),
            "狀態":                         "數據完整"
        })

    # 輸出結果
    if results:
        df_final = pd.DataFrame(results)
        if os.path.isdir(target_path):
            output_file = os.path.join(target_path, "GWRI_Analysis_Report.csv")
        else:
            output_file = os.path.join(os.path.dirname(target_path), "GWRI_Analysis_Report.csv")
        df_final.to_csv(output_file, index=False, encoding="utf-8-sig")
        print(f"分析完成！報告已儲存至: {output_file}")
    else:
        print("未發現有效數據。")

if __name__ == "__main__":
    main()