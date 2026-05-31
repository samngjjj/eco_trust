# news_nlp.py — v2.0：年度新聞驗證系統
# 模式一：fetch_year  — 按年份每月抓取 5 篇 (共 60 篇)
# 模式二：fetch_actions — 對每個行動承諾搜尋對應年份新聞以驗證完成狀態
# 模式三：analyze     — 單筆文本情感分析
import feedparser
import re
import sys
import json
import os
import time
import warnings
import logging
import urllib.parse
from datetime import datetime
from pathlib import Path

warnings.filterwarnings("ignore")

if sys.stdout.encoding != 'utf-8':
    try: sys.stdout.reconfigure(encoding='utf-8')
    except: pass

os.environ["HF_HUB_DISABLE_PROGRESS_BARS"] = "1"
os.environ["TRANSFORMERS_NO_ADVISORY_WARNINGS"] = "1"
logging.getLogger("transformers").setLevel(logging.ERROR)

# ── 本地 ESG 情感詞典 ─────────────────────────────────────────────────────
POSITIVE_KEYWORDS = [
    "減碳", "碳中和", "零碳", "淨零", "再生能源", "綠電", "節能", "永續",
    "ESG", "CSR", "社會責任", "獲獎", "認證", "ISO", "TCFD", "GRI",
    "榮獲", "優良", "提升", "達標", "通過", "創新", "落實", "承諾",
    "第一", "領先", "優秀", "卓越", "良好", "正面", "積極", "改善",
    "綠色", "環保", "清潔", "低碳", "循環", "公益", "捐助", "志工",
    "多元", "包容", "平等", "人才", "培訓", "效率", "完成", "實現",
]
NEGATIVE_KEYWORDS = [
    "醜聞", "違規", "罰款", "懲處", "訴訟", "告發", "調查", "檢察",
    "汙染", "污染", "排放超標", "廢水", "廢氣", "毒", "違法",
    "裁員", "解雇", "勞資", "糾紛", "抗議", "罷工", "剝削",
    "弊案", "貪汙", "造假", "不實", "虛報", "欺騙", "操縱",
    "下跌", "虧損", "衰退", "縮減", "停產", "停業",
    "事故", "意外", "爆炸", "火災", "洩漏", "breach", "leak",
    "撤資", "退出", "降評", "負面", "批評", "質疑", "危機",
    "困境", "倒閉", "破產", "債務", "違約", "未完成", "延遲", "跳票",
]
STRONG_POSITIVE = {"碳中和", "淨零", "零碳", "榮獲", "ESG", "永續報告", "再生能源", "GRI", "TCFD", "達標", "完成"}
STRONG_NEGATIVE = {"汙染", "污染", "違法", "罰款", "造假", "貪汙", "訴訟", "醜聞", "未完成", "跳票"}


def keyword_sentiment(text: str) -> tuple[str, float]:
    pos_score = 0.0
    neg_score = 0.0
    for kw in POSITIVE_KEYWORDS:
        if kw in text:
            pos_score += 2.0 if kw in STRONG_POSITIVE else 1.0
    for kw in NEGATIVE_KEYWORDS:
        if kw in text:
            neg_score += 2.0 if kw in STRONG_NEGATIVE else 1.0
    total = pos_score + neg_score
    if total == 0:
        return "Neutral", 0.60
    ratio = pos_score / total
    if ratio >= 0.6:
        return "Positive", min(round(0.65 + min(pos_score, 5) * 0.06, 4), 0.95)
    elif ratio <= 0.4:
        return "Negative", min(round(0.65 + min(neg_score, 5) * 0.06, 4), 0.95)
    else:
        return "Neutral", round(0.55 + ratio * 0.1, 4)


def parse_published_year(published_str: str) -> int | None:
    """從 RSS published 字串中解析年份。"""
    if not published_str:
        return None
    m = re.search(r'\b(20\d{2})\b', published_str)
    return int(m.group(1)) if m else None


# ── FinBERT 懶載入 ────────────────────────────────────────────────────────
BASE_DIR = Path(__file__).resolve().parent
LOCAL_MODEL_PATH = BASE_DIR / "finbert_model"
_tokenizer = _model = _device = None

def _load_finbert() -> bool:
    global _tokenizer, _model, _device
    if _model is not None:
        return True
    if not (LOCAL_MODEL_PATH / "config.json").exists():
        return False
    try:
        import torch
        from transformers import AutoTokenizer, AutoModelForSequenceClassification
        _device = torch.device("cpu")
        _tokenizer = AutoTokenizer.from_pretrained(str(LOCAL_MODEL_PATH))
        _model = AutoModelForSequenceClassification.from_pretrained(str(LOCAL_MODEL_PATH)).to(_device)
        _model.eval()
        return True
    except Exception:
        return False


def finbert_batch_sentiment(texts: list[str]) -> list[tuple[str, float]]:
    if not _load_finbert() or not texts:
        return [keyword_sentiment(t) for t in texts]
    import torch
    label_map = {0: "Neutral", 1: "Positive", 2: "Negative"}
    results = []
    for i in range(0, len(texts), 16):
        batch = texts[i:i + 16]
        inputs = _tokenizer(batch, return_tensors="pt", truncation=True,
                            max_length=128, padding=True).to(_device)
        with torch.no_grad():
            logits = _model(**inputs).logits
        probs = torch.softmax(logits, dim=1).tolist()
        for prob in probs:
            idx = prob.index(max(prob))
            results.append((label_map[idx], round(max(prob), 4)))
    return results


# ── 核心搜尋函式 ─────────────────────────────────────────────────────────
def _rss_search(query: str, max_items: int = 5) -> list[dict]:
    """執行一次 Google News RSS 搜尋，回傳新聞列表。"""
    q = urllib.parse.quote(query)
    url = f"https://news.google.com/rss/search?q={q}&hl=zh-TW&gl=TW&ceid=TW:zh-Hant"
    try:
        feed = feedparser.parse(url)
        items = []
        for entry in feed.entries[:max_items]:
            items.append({
                "title":     entry.title,
                "published": entry.get("published", ""),
                "link":      entry.link,
            })
        return items
    except Exception:
        return []


def fetch_year_news(company_name: str, company_symbol: str,
                    report_year: int, per_month: int = 5) -> list[dict]:
    """
    按年份搜尋新聞：12 個月，每月 {per_month} 篇。
    搜尋查詢加入年月限制，讓 Google News 聚焦該時段。
    """
    all_news = []
    for month in range(1, 13):
        # 搜尋關鍵字：公司名 + ESG類詞 + 年月
        month_str = f"{report_year}年{month}月"
        query = f"{company_name} (ESG OR 永續 OR 碳中和 OR 淨零 OR 治理 OR 環保) {month_str}"
        items = _rss_search(query, max_items=per_month)
        for item in items:
            item['report_year'] = report_year
            item['search_query'] = query[:200]
            item['action_context'] = None
            # 過濾：只保留發布年份與 report_year 相符或相差 ±1 的新聞
            pub_year = parse_published_year(item['published'])
            if pub_year is None or abs(pub_year - report_year) <= 1:
                all_news.append(item)
        time.sleep(0.3)  # 避免觸發 rate limit
    return all_news


def fetch_action_verification_news(company_name: str, company_symbol: str,
                                   report_year: int,
                                   actions: list[dict]) -> list[dict]:
    """
    承諾驗證模式：
    對每個 Gen-2 高信度承諾，提取關鍵字後搜尋對應年份新聞，
    驗證承諾是否有被外部新聞佐證（完成）或反駁（未完成）。
    """
    all_news = []
    for action in actions[:8]:  # 最多 8 個承諾，避免太慢
        text = action.get('text', '')
        topic = action.get('topic', 'OTHER')

        # 從承諾文本提取 2-4 個最具辨識力的關鍵字
        keywords = _extract_action_keywords(text, topic)
        if not keywords:
            continue

        # 組合搜尋查詢：公司名 + 關鍵字 + 年份
        kw_str = ' '.join(keywords[:3])
        query = f"{company_name} {kw_str} {report_year}年"

        items = _rss_search(query, max_items=5)
        for item in items:
            pub_year = parse_published_year(item['published'])
            if pub_year and abs(pub_year - report_year) > 1:
                continue  # 跳過不相關年份
            item['report_year'] = report_year
            item['search_query'] = query[:200]
            item['action_context'] = text[:200]
            all_news.append(item)
        time.sleep(0.3)

    return all_news


def _extract_action_keywords(text: str, topic: str) -> list[str]:
    """
    從承諾文本中提取有辨識力的關鍵字。
    策略：優先抓 ESG 領域專有名詞 > 數字+單位 > 主題類別詞。
    """
    keywords = []

    # ESG 專有名詞（高辨識力）
    esg_terms = [
        "碳中和", "淨零", "再生能源", "綠電", "TCFD", "SBTi", "RE100",
        "GRI", "ISO 14064", "碳排", "溫室氣體", "Scope", "範疇",
        "廢棄物", "循環經濟", "生物多樣性", "水資源", "節能",
        "董事會", "獨立董事", "薪酬", "反貪腐", "資安", "供應鏈",
        "離職率", "培訓", "職業安全", "人才", "多元", "女性",
    ]
    for term in esg_terms:
        if term in text and term not in keywords:
            keywords.append(term)
            if len(keywords) >= 3:
                break

    # 數字+目標（如「減少30%」「2030年」）
    num_matches = re.findall(r'\d+(?:\.\d+)?%|\d{4}年', text)
    keywords.extend(num_matches[:1])

    # 若未提取到任何詞，用主題類別補充
    if not keywords:
        topic_fallback = {'E': '環境', 'S': '社會責任', 'G': '公司治理',
                          'HARD_METRICS': 'ESG認證', 'OTHER': '永續'}
        keywords.append(topic_fallback.get(topic, '永續'))

    return keywords[:4]


def run_analysis_with_sentiment(news_list: list[dict]) -> list[dict]:
    """對新聞列表批次執行情感分析（詞典 + FinBERT 中性修正）。"""
    titles = [item["title"] for item in news_list]
    kw_results = [keyword_sentiment(t) for t in titles]

    # 對詞典「中性」條目嘗試 FinBERT 精修
    neutral_indices = [i for i, (label, _) in enumerate(kw_results) if label == "Neutral"]
    if neutral_indices:
        neutral_titles = [titles[i] for i in neutral_indices]
        fb_results = finbert_batch_sentiment(neutral_titles)
        for j, idx in enumerate(neutral_indices):
            kw_results[idx] = fb_results[j]

    for i, item in enumerate(news_list):
        label, score = kw_results[i]
        item["sentiment"] = label
        item["confidence"] = score

    return news_list


# ── 命令列介面 ─────────────────────────────────────────────────────────────
if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "缺少參數"}, ensure_ascii=False))
        sys.exit(1)

    mode = sys.argv[1]

    if mode == "fetch_year" and len(sys.argv) >= 4:
        # 用法：news_nlp.py fetch_year <公司名> <股票代號> <年份> [每月篇數=5]
        company_name   = sys.argv[2]
        company_symbol = sys.argv[3]
        report_year    = int(sys.argv[4]) if len(sys.argv) > 4 else datetime.now().year
        per_month      = int(sys.argv[5]) if len(sys.argv) > 5 else 5

        news = fetch_year_news(company_name, company_symbol, report_year, per_month)
        news = run_analysis_with_sentiment(news)
        print(json.dumps({"news": news, "total": len(news), "year": report_year}, ensure_ascii=False))

    elif mode == "fetch_actions" and len(sys.argv) >= 5:
        # 用法：news_nlp.py fetch_actions <公司名> <股票代號> <年份> <actions_json>
        company_name   = sys.argv[2]
        company_symbol = sys.argv[3]
        report_year    = int(sys.argv[4])
        actions_json   = sys.argv[5] if len(sys.argv) > 5 else '[]'
        actions        = json.loads(actions_json)

        news = fetch_action_verification_news(company_name, company_symbol, report_year, actions)
        news = run_analysis_with_sentiment(news)
        print(json.dumps({"news": news, "total": len(news), "year": report_year}, ensure_ascii=False))

    elif mode == "analyze" and len(sys.argv) > 2:
        text = sys.argv[2]
        label, score = keyword_sentiment(text)
        print(json.dumps({"sentiment": label, "confidence": score}, ensure_ascii=False))

    elif mode == "fetch" and len(sys.argv) > 2:
        # 舊版相容（background_fetch_news.php 舊呼叫）
        company = sys.argv[2]
        items = _rss_search(company, max_items=30)
        print(json.dumps({"company": company, "news": items}, ensure_ascii=False))

    else:
        # 舊版完整分析模式（相容）
        raw_input = sys.argv[1]
        if "|" in raw_input:
            name, sym = raw_input.split("|", 1)
            query = f"{name} {sym} (ESG OR 永續 OR 淨零)"
        else:
            query = f"{raw_input} (ESG OR 永續 OR 淨零)"
        items = _rss_search(query, max_items=30)
        items = run_analysis_with_sentiment(items)
        total = len(items)
        counts = {"Positive": 0, "Neutral": 0, "Negative": 0}
        for it in items:
            counts[it.get("sentiment", "Neutral")] += 1
        result = {
            "company": raw_input,
            "news": items,
            "total_news": total,
            "sentiment_distribution": {k: round(v / max(1, total) * 100, 1) for k, v in counts.items()}
        }
        print(json.dumps(result, ensure_ascii=False))