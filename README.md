# EcoTrust AI v2.0 

> **ESG Disclosure Confidence & Text Integrity Evaluation Platform**  
> *A robust system for evaluating corporate ESG report auditability and disclosure confidence through Page-Aware RAG.*

---

## 📌 Core Positioning Directive (核心定位宣告)
EcoTrust AI **不偵測「綠漂」 (Greenwashing)**。本平台旨在透過自然語言處理 (NLP) 與深度學習模型，針對企業永續報告書進行**「資訊信賴度 (Disclosure Confidence)」**與**「文本數據實質性 (Text Integrity)」**的量化評估。平台結合頁面感知檢索 (Page-Aware RAG)，確保每一筆被引用的數據與承諾皆具備實體頁碼之可追溯性與可審計性，從技術源頭防範 LLM 幻覺。

---

## 🌟 Core Features (核心特色)

1. **去碳排化評估 (Decarbonized Evaluation)**：評估重點不在於企業自稱的碳排放數值高低，而在於其揭露文本的數據實質性 (Numeracy Density) 與關鍵指標豐富度 (KPI Diversity)。
2. **智慧採樣切片 (Smart Chunking)**：採用「硬指標優先 + 全文均勻抽樣」之雙軌採樣策略，克服預訓練 Transformer 模型 512-token 的輸入限制，最大化保留具審計價值的文本。
3. **承諾生命週期驗證 (Gen-2 Engine)**：自動提取具備「明確時限」與「量化數值」的高信度行動承諾，並背景調用 Google News RSS 輿情與本地 ESG 字典進行交叉比對。
4. **頁碼感知與並行多線程代理 (Page-Aware RAG & Parallel Agent)**：將 MariaDB 結構化財務指標 (ROE)、輿情情緒與 PDF 實體頁面內容深度融合，採用並行多線程處理 (Promise.all) 與意圖智能路由，搭配實時定速思考終端機 (Agent Console Log) 展示思考過程，並自動於句尾強制標記可跳轉的 `[p.X]`、`[資料庫]` 與新聞連結。

---

## 🛠️ Pipeline Architecture (系統管道架構)

```mermaid
flowchart TD
    subgraph Ingestion["1. 資料輸入與前置處理 (Ingestion)"]
        A1[上傳 ESG 報告 PDF] --> A2{檔名解析與重複偵測}
        A2 -- 通過 --> A3[ESG 報告驗證門檻 Gate]
        A3 -- 未過門檻 --> A4[中止並判定為非ESG報告]
    end

    subgraph CoreEngine["2. 核心數據分析引擎 (Analysis)"]
        A3 -- 通過 --> B1[pdf_page_indexer.py]
        B1 --> B2[建立頁碼索引 JSON]
        
        A3 -- 通過 --> B3[finbert.py 數據挖掘]
        B3 --> B4[智慧採樣切片 Smart Chunking]
        B4 --> B5[FinBERT 意圖情感推理]
        B3 --> B6[數字密度與 KPI 多樣性計算]
        B3 --> B7[Gen-2 承諾提取與信度分類]
    end

    subgraph Storage["3. 資料庫儲存與背景驗證 (Storage & Background)"]
        B2 --> C1[(MariaDB: test2)]
        B5 --> C1
        B6 --> C1
        B7 --> C1
        
        C1 --> C2[背景新聞輿情分析]
        C2 --> C3[news_nlp.py 網路爬蟲]
        C3 --> C4[Google News RSS 搜尋]
        C4 --> C5[FinBERT & 詞典情感標註]
        C5 --> C1
    end

    subgraph RAGConsultant["4. RAG 並行代理人問答系統 (Agentic RAG Chatbot)"]
        D1[使用者提問] --> D2[chat.php / 意圖感知與路由]
        D2 -- 結構化數據提問 --> D3[⚡ 快速資料庫查詢代理]
        D3 --> D4[1x DB 讀取 + 1x LLM 整合歸納]
        
        D2 -- 報告具體行動/策略評估 --> D5[📊 並行 Map-Reduce 代理]
        D5 --> D6[🚀 Promise.all 多線程發送分析]
        D6 --> D7[逐年 N-gram 頁碼檢索與子代理審計]
        D7 --> D8[按年升序排序與 LLM 合流整合]
        
        D2 --> D9[💻 佇列化思考歷程終端機 Agent Console]
        D9 --> D10[450ms 定速行輸出 + 載入期動態思維模擬]
        
        D4 --> D11[s2t.py 繁體校正轉換防護網]
        D8 --> D11
        D11 --> D12[最終 Markdown 回覆: 含 [p.X] / [資料庫] / [新聞] 標籤]
    end
```

---

## 📊 Evaluation Formulas (核心評分演算法)

*   **意圖情感強度 ($I$)**：基於 FinBERT 判斷 Chunks 的積極度平均：
    $$S_{\text{chunk}} = \frac{P(\text{Positive}) - P(\text{Negative}) + 1}{2}$$
    $$I = \frac{1}{N} \sum_{i=1}^{N} S_{\text{chunk}, i} \quad (N \le 100)$$
*   **數字密度歸一化 ($N_{\text{norm}}$)**：以 `jieba` 分詞為基準，評估數字字詞佔比：
    $$N_{\text{norm}} = \min\left(1.0, \frac{N_s}{0.20} \times 1.5\right)$$
*   **關鍵指標豐富度 ($K_{\text{norm}}$)**：使用平方根函數突顯首幾個關鍵指標的有無：
    $$K_{\text{norm}} = \min\left(1.0, \sqrt{\frac{K_c}{15}}\right)$$
*   **誠信可靠性指數 ($C$)**：
    $$C = (0.45 \cdot N_{\text{norm}}) + (0.35 \cdot K_{\text{norm}})$$
*   **原始總分 ($S_{\text{raw}}$)**：
    $$S_{\text{raw}} = (I \cdot 0.6) + (C \cdot 0.4) + \left[\left(1 - \frac{R_e}{100}\right) \cdot 0.2\right] \quad (R_e = \text{外部風險分})$$
*   **對比拉伸信賴度 ($Y$)**：利用 Sigmoid 將分數區間擴散至極端：
    $$Y = \text{Sigmoid}(S_{\text{raw}}) = \frac{1}{1 + e^{-10 \cdot (S_{\text{raw}} - 0.5)}}$$

---

## ⚙️ Operating Parameter Thresholds (運作參數與門檻)

| 項目 (Item) | 運作規則 (Rule) | 門檻值 (Threshold) |
| :--- | :--- | :--- |
| **最低報告長度** | 預防無內容文件或短網頁遭上傳 | $> 500$ 字元 |
| **ESG 關鍵字命中** | 全文命中定義於 `ESG_KEYWORDS_MAP` 的相異詞數 | $> 5$ 個 |
| **核心哨兵詞命中** | 全文必須包含核心永續識別詞彙 | $\ge 2$ 個 (如：永續、ESG、溫室氣體) |
| **無效句子過濾** | 切割句子後過濾無意義之短句 | 捨棄 $\le 8$ 字元之句子 |
| **硬指標優先提取** | 優先提取包含審計、認證、確信字詞的代表性段落 | 前 $30$ 句 |
| **代表段落總量上限**| 送入 FinBERT 情感預測的 Smart Chunks 總量 | $100$ 句 (優先保留硬指標，餘均勻採樣) |

---

## 🗄️ Database Schema (MariaDB · test2)

*   **`companies`**：公司基本資料表（股票代號、公司名稱、產業代碼）
*   **`industries`**：產業類別 lookup 對照表
*   **`company_performance`**：企業歷史財務指標（以季度 Return on Equity, ROE 為主）
*   **`carbon_emissions`**：評估核心數據表（信心得分、意圖分數、數字密度、KPI數、承諾分布與 HIGH 信度承諾列表）
*   **`news`**：外部輿情表（標題、發布時間、連結、FinBERT/字典情感標籤、對應之承諾上下文與 RSS 查詢關鍵字）

---

## 🚀 Installation & Environment Setup (環境設定與部署)

### 1. 資料庫設定
*   請於本地 MariaDB / MySQL 中建立名稱為 `test2` 的資料庫。
*   將專案目錄下的 `test2.sql` 或 `migrate_gen2.sql` 匯入資料庫。
*   確認 [config.php](file:///c:/xampp/htdocs/eco_sys/config.php) 連線參數是否正確：
    ```php
    // config.php 單例連線
    function getDB() {
        return new mysqli("localhost", "root", "your_password", "test2");
    }
    ```

### 2. 網頁伺服器
*   請將本專案放置於 XAMPP / Apache 網頁伺服器的 `htdocs/eco_sys` 資料夾下。
*   啟用 Apache 與 MySQL 模組。

### 3. Python 依賴安裝
```bash
pip install -r requirements.txt
```
*主要依賴庫包含：`transformers`、`torch`、`jieba`、`pdfplumber`、`feedparser`、`pandas`、`numpy`*

### 4. 本地 FinBERT 模型下載
*   為維護本地運行之隱私與速度，系統優先尋找目錄下的本地模型。
*   請在 `eco_sys` 目錄下建立一個名稱為 `finbert_model` 的資料夾。
*   自 Hugging Face 下載 `yiyanghkust/finbert-tone-chinese` 模型的所有設定檔與權重，並放置於該目錄。
*   *若目錄不存在，程式將自動嘗試從網路上載入模型。*

### 5. Ollama & 本地 LLM 配置
*   本專案之 RAG Chatbot 核心問答採用 [Qwen2.5:7b](https://ollama.com/library/qwen2.5) 模型。
*   請下載並安裝 [Ollama](https://ollama.com/)。
*   於終端機中下載指定模型：
    ```bash
    ollama run qwen2.5:7b
    ```
*   確認 Ollama 本地 API 服務於 `http://localhost:11434` 正常運行。

---

## 📂 Code Module Reference (程式碼模組對照)

| 模組路徑 | 負責之技術模組與 NLP 邏輯 | 主要語言 |
| :--- | :--- | :--- |
| [upload_pdf.php](file:///c:/xampp/htdocs/eco_sys/api/upload_pdf.php) | PDF 檔案接收、檔名智能拆解、資料庫更新及背景進程調用控制 | PHP |
| [pdf_page_indexer.py](file:///c:/xampp/htdocs/eco_sys/pdf_page_indexer.py) | PDF 檔案頁面級解構，並輸出 JSON 格式的頁碼文字映射檔案 | Python |
| [finbert.py](file:///c:/xampp/htdocs/eco_sys/finbert.py) | 驗證門檻 gating、智慧採樣、FinBERT 情感推論、數字/KPI 計分及 Gen-2 承諾提取 | Python |
| [news_nlp.py](file:///c:/xampp/htdocs/eco_sys/news_nlp.py) | 輿情搜集爬蟲、依據承諾動態提取關鍵字、情緒字典評級及 FinBERT 精修 | Python |
| [background_fetch_news.php](file:///c:/xampp/htdocs/eco_sys/api/background_fetch_news.php) | 背景非同步執行爬取腳本，排程將搜尋數據入庫 | PHP |
| [chat.php](file:///c:/xampp/htdocs/eco_sys/chat.php) | 智能顧問 Chatbot 介面與 RAG 代理人控制邏輯（含並行多線程與定速佇列終端機） | PHP/JS |
| [chat_api.php](file:///c:/xampp/htdocs/eco_sys/api/chat_api.php) | RAG 檢索邏輯、資料庫指標直讀路由、多年度分析子任務整合與 Ollama 對接 | PHP |
| [s2t.py](file:///c:/xampp/htdocs/eco_sys/api/s2t.py) | 繁體中文自動校正防護網，基於 OpenCC 的台灣繁體字形與用語轉換 | Python |
| [demo_skills.md](file:///c:/xampp/htdocs/eco_sys/demo_skills.md) | 展示手冊：引導展示者一鍵測試「快速資料庫」、「具體行動」與「抽象策略」三大場景 | Markdown |
