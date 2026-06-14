<?php
require_once __DIR__ . '/auth_check.php';
if (!isset($isPro) || !$isPro) {
    header("Location: /eco_sys/index.php");
    exit;
}
require_once __DIR__ . '/config.php';
$activePage = 'chat';

$mdDir = __DIR__ . "/eco trust v2";
$validCombos = [];
if (is_dir($mdDir)) {
    foreach (glob($mdDir . "/*.md") as $file) {
        $basename = basename($file, ".md");
        if (preg_match('/^(\d+_.+)_(\d{4})$/', $basename, $matches)) {
            $company = $matches[1];
            $year = $matches[2];
            if (!isset($validCombos[$company])) {
                $validCombos[$company] = [];
            }
            if (!in_array($year, $validCombos[$company])) {
                $validCombos[$company][] = $year;
            }
        }
    }
}

// 額外掃描已上傳的 PDF 檔案 (uploads/)
$uploadsDir = __DIR__ . '/uploads';
if (is_dir($uploadsDir)) {
    foreach (glob($uploadsDir . "/*.pdf") as $file) {
        $basename = basename($file, ".pdf");
        if (preg_match('/^(\d+_.+)_(\d{4})$/', $basename, $matches)) {
            $company = $matches[1];
            $year = $matches[2];
            if (!isset($validCombos[$company])) {
                $validCombos[$company] = [];
            }
            if (!in_array($year, $validCombos[$company])) {
                $validCombos[$company][] = $year;
            }
        }
    }
}

foreach ($validCombos as $c => &$years) {
    rsort($years);
}
ksort($validCombos);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>AI 智能顧問 — Eco Trust AI</title>
  <link rel="stylesheet" href="/eco_sys/assets/css/main.css">

  <!-- PDF.js Local -->
  <script src="/eco_sys/assets/js/pdf.min.js"></script>


  <style>
    /* ====== Premium Chat Interface + PDF Viewer Layout ====== */

    .chat-pdf-container {
      display: flex;
      height: calc(100vh - 180px);
      gap: 0;
      position: relative;
    }

    /* ── Left: Chat Panel ── */
    .chat-panel {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      transition: flex 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .chat-panel.with-pdf {
      flex: 0 0 58%;
    }

    .chat-card {
      display: flex;
      flex-direction: column;
      height: 100%;
      background: rgba(11, 14, 20, 0.6) !important;
      border: 1px solid rgba(41, 121, 255, 0.2) !important;
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.5), inset 0 0 20px rgba(41, 121, 255, 0.05);
      backdrop-filter: blur(10px);
      border-radius: 12px;
    }

    /* ── Right: PDF Viewer Panel ── */
    .pdf-panel {
      width: 0;
      overflow: hidden;
      transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.4s ease;
      opacity: 0;
      display: flex;
      flex-direction: column;
      position: relative;
      border-radius: 12px;
    }

    .pdf-panel.visible {
      width: 42%;
      opacity: 1;
      margin-left: 12px;
    }

    .pdf-panel-inner {
      display: flex;
      flex-direction: column;
      height: 100%;
      background: rgba(11, 14, 20, 0.85);
      border: 1px solid rgba(0, 230, 118, 0.25);
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 0 30px rgba(0, 230, 118, 0.08), inset 0 0 20px rgba(0, 230, 118, 0.02);
    }

    .pdf-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.75rem 1rem;
      background: rgba(0, 230, 118, 0.06);
      border-bottom: 1px solid rgba(0, 230, 118, 0.2);
      flex-shrink: 0;
    }

    .pdf-header-title {
      color: #00e676;
      font-weight: 600;
      font-size: 0.9rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 30%;
    }

    .pdf-zoom-indicator {
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.15);
      color: #b0b0b0;
      padding: 0.2rem 0.5rem;
      border-radius: 6px;
      font-size: 0.8rem;
      font-weight: 600;
      min-width: 48px;
      text-align: center;
      cursor: pointer;
      user-select: none;
      transition: all 0.2s;
    }
    
    .pdf-zoom-indicator:hover {
      background: rgba(0, 230, 118, 0.15);
      border-color: rgba(0, 230, 118, 0.4);
      color: #00e676;
    }

    .pdf-header-controls {
      display: flex;
      gap: 0.5rem;
      align-items: center;
    }

    .pdf-page-indicator {
      background: rgba(0, 230, 118, 0.12);
      border: 1px solid rgba(0, 230, 118, 0.3);
      color: #00e676;
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      font-size: 0.82rem;
      font-weight: 600;
      font-variant-numeric: tabular-nums;
      min-width: 80px;
      text-align: center;
    }

    .pdf-nav-btn {
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.15);
      color: #b0b0b0;
      width: 30px;
      height: 30px;
      border-radius: 6px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
      font-size: 0.85rem;
    }

    .pdf-nav-btn:hover {
      background: rgba(0, 230, 118, 0.15);
      border-color: rgba(0, 230, 118, 0.4);
      color: #00e676;
    }

    .pdf-close-btn {
      background: transparent;
      border: 1px solid rgba(255, 71, 87, 0.3);
      color: rgba(255, 71, 87, 0.7);
      width: 28px;
      height: 28px;
      border-radius: 6px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
      font-size: 1rem;
      margin-left: 0.5rem;
    }

    .pdf-close-btn:hover {
      background: rgba(255, 71, 87, 0.15);
      border-color: rgba(255, 71, 87, 0.6);
      color: #ff4757;
    }

    .pdf-canvas-wrapper {
      flex: 1;
      overflow-y: auto;
      overflow-x: auto;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      padding: 1rem;
      gap: 0.5rem;
      background: rgba(0, 0, 0, 0.3);
    }

    .pdf-canvas-wrapper::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }
    .pdf-canvas-wrapper::-webkit-scrollbar-track {
      background: rgba(0, 0, 0, 0.2);
    }
    .pdf-canvas-wrapper::-webkit-scrollbar-thumb {
      background: rgba(0, 230, 118, 0.3);
      border-radius: 10px;
    }

    #pdfCanvas {
      display: block;
      margin: 0 auto;
      border-radius: 4px;
      box-shadow: 0 2px 20px rgba(0, 0, 0, 0.5);
      cursor: grab;
      user-select: none;
    }

    .pdf-loading-overlay {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background: rgba(11, 14, 20, 0.9);
      z-index: 10;
      gap: 1rem;
    }

    .pdf-loading-spinner {
      width: 40px;
      height: 40px;
      border: 3px solid rgba(0, 230, 118, 0.1);
      border-top-color: #00e676;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    .pdf-loading-text {
      color: rgba(255, 255, 255, 0.6);
      font-size: 0.85rem;
    }

    /* ── Chat Header ── */
    .chat-header-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem 1.5rem;
      border-bottom: 1px solid rgba(41, 121, 255, 0.2);
      background: rgba(41, 121, 255, 0.05);
      flex-shrink: 0;
      border-radius: 12px 12px 0 0;
    }

    .chat-controls {
      display: flex;
      gap: 1rem;
      align-items: center;
    }

    .cyber-select {
      background: rgba(2, 5, 10, 0.8);
      color: #00B0FF;
      border: 1px solid rgba(41, 121, 255, 0.4);
      padding: 0.5rem 1.2rem;
      border-radius: 6px;
      font-size: 0.95rem;
      outline: none;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 0 10px rgba(41, 121, 255, 0.1);
      appearance: none;
      -webkit-appearance: none;
      background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2300B0FF%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E");
      background-repeat: no-repeat;
      background-position: right 1rem top 50%;
      background-size: 0.65rem auto;
      padding-right: 2.5rem;
    }

    .cyber-select:hover, .cyber-select:focus {
      border-color: #00e676;
      box-shadow: 0 0 15px rgba(0, 230, 118, 0.2);
    }

    .btn-reset {
      background: transparent;
      color: var(--muted);
      border: 1px solid rgba(255, 255, 255, 0.2);
      padding: 0.5rem 1rem;
      border-radius: 6px;
      cursor: pointer;
      font-size: 0.9rem;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .btn-reset:hover {
      background: rgba(255, 71, 87, 0.1);
      border-color: rgba(255, 71, 87, 0.5);
      color: #ff4757;
      box-shadow: 0 0 15px rgba(255, 71, 87, 0.2);
    }

    /* ── Chat Box ── */
    .chat-box {
      flex: 1;
      overflow-y: auto;
      padding: 1.5rem;
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
      background: url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAAAXNSR0IArs4c6QAAACVJREFUKFNjZCASMDKgA0YmFBsYGJDVM2IAvWE0VUA8DJoqIAUAGjEABd7eWdEAAAAASUVORK5CYII=') repeat;
      background-color: rgba(2, 5, 10, 0.6);
      background-blend-mode: overlay;
    }

    .chat-box::-webkit-scrollbar { width: 6px; }
    .chat-box::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.2); }
    .chat-box::-webkit-scrollbar-thumb { background: rgba(41, 121, 255, 0.3); border-radius: 10px; }
    .chat-box::-webkit-scrollbar-thumb:hover { background: rgba(41, 121, 255, 0.6); }

    /* ── Messages ── */
    .message {
      max-width: 75%;
      display: flex;
      flex-direction: column;
      animation: fadeIn 0.4s ease-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .user-msg { align-self: flex-end; }
    .system-msg { align-self: flex-start; }

    .msg-content {
      padding: 1rem 1.25rem;
      border-radius: 12px;
      line-height: 1.7;
      word-wrap: break-word;
      font-size: 0.95rem;
      position: relative;
    }

    .user-msg .msg-content {
      background: linear-gradient(135deg, rgba(41, 121, 255, 0.15), rgba(41, 121, 255, 0.05));
      border: 1px solid rgba(41, 121, 255, 0.3);
      color: #e3f2fd;
      border-bottom-right-radius: 0;
      box-shadow: 0 4px 15px rgba(41, 121, 255, 0.1);
    }

    .system-msg .msg-content {
      background: linear-gradient(135deg, rgba(0, 230, 118, 0.1), rgba(0, 230, 118, 0.02));
      border: 1px solid rgba(0, 230, 118, 0.2);
      color: #e0e0e0;
      border-bottom-left-radius: 0;
      box-shadow: 0 4px 15px rgba(0, 230, 118, 0.05);
    }

    .msg-content p { margin: 0 0 0.5rem 0; }
    .msg-content p:last-child { margin: 0; }
    .msg-content code {
      background: rgba(0, 0, 0, 0.3);
      padding: 0.2rem 0.4rem;
      border-radius: 4px;
      font-family: monospace;
      color: #ff9f43;
    }
    .msg-content strong { color: #fff; }

    /* ── Page Citation Tags ── */
    .page-citation {
      display: inline-flex;
      align-items: center;
      gap: 2px;
      background: rgba(0, 230, 118, 0.1);
      border: 1px solid rgba(0, 230, 118, 0.35);
      color: #00e676 !important;
      padding: 1px 7px;
      border-radius: 10px;
      font-size: 0.75rem;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none !important;
      transition: all 0.25s ease;
      vertical-align: super;
      line-height: 1;
      margin: 0 1px;
      font-variant-numeric: tabular-nums;
      position: relative;
      user-select: none;
    }

    .page-citation::before {
      content: '📄';
      font-size: 0.65rem;
    }

    .page-citation:hover {
      background: rgba(0, 230, 118, 0.25);
      border-color: #00e676;
      box-shadow: 0 0 12px rgba(0, 230, 118, 0.4);
      transform: translateY(-1px) scale(1.05);
      color: #fff !important;
    }

    .page-citation:active {
      transform: scale(0.95);
    }

    .page-citation.clicked {
      animation: citationPulse 0.6s ease-out;
    }

    @keyframes citationPulse {
      0% { box-shadow: 0 0 0 0 rgba(0, 230, 118, 0.6); }
      50% { box-shadow: 0 0 0 8px rgba(0, 230, 118, 0); }
      100% { box-shadow: 0 0 0 0 rgba(0, 230, 118, 0); }
    }

    /* DB source tag */
    .db-citation {
      display: inline-flex;
      align-items: center;
      gap: 2px;
      background: rgba(41, 121, 255, 0.1);
      border: 1px solid rgba(41, 121, 255, 0.35);
      color: #64b5f6 !important;
      padding: 1px 7px;
      border-radius: 10px;
      font-size: 0.75rem;
      font-weight: 600;
      text-decoration: none !important;
      vertical-align: super;
      line-height: 1;
      margin: 0 1px;
    }

    .db-citation::before {
      content: '🗄️';
      font-size: 0.6rem;
    }

    /* Unknown source tag */
    .unknown-citation {
      display: inline-flex;
      align-items: center;
      gap: 2px;
      background: rgba(255, 159, 67, 0.1);
      border: 1px solid rgba(255, 159, 67, 0.35);
      color: #ffb74d !important;
      padding: 1px 7px;
      border-radius: 10px;
      font-size: 0.75rem;
      font-weight: 600;
      text-decoration: none !important;
      vertical-align: super;
      line-height: 1;
      margin: 0 1px;
    }

    .unknown-citation::before {
      content: '⚠️';
      font-size: 0.6rem;
    }

    /* ── Chat Input ── */
    .chat-input-area {
      display: flex;
      gap: 1rem;
      padding: 1.5rem;
      background: rgba(11, 14, 20, 0.9);
      border-top: 1px solid rgba(41, 121, 255, 0.2);
      align-items: center;
      border-radius: 0 0 12px 12px;
      flex-shrink: 0;
    }

    .chat-input-wrapper {
      flex: 1;
      position: relative;
    }

    #chatInput {
      width: 100%;
      background: rgba(2, 5, 10, 0.7);
      color: #fff;
      border: 1px solid rgba(41, 121, 255, 0.3);
      border-radius: 8px;
      padding: 1rem 1.5rem;
      font-size: 1rem;
      resize: none;
      outline: none;
      transition: all 0.3s;
      box-shadow: inset 0 2px 5px rgba(0,0,0,0.5);
      font-family: 'Inter', 'Noto Sans TC', sans-serif;
    }

    #chatInput:focus {
      border-color: #00e676;
      box-shadow: 0 0 15px rgba(0, 230, 118, 0.2), inset 0 2px 5px rgba(0,0,0,0.5);
    }

    #chatInput::placeholder {
      color: rgba(255, 255, 255, 0.3);
    }

    .btn-send {
      background: linear-gradient(135deg, #00C853, #64FFDA);
      color: #000;
      border: none;
      padding: 0 2rem;
      height: 52px;
      border-radius: 8px;
      font-size: 1.1rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      box-shadow: 0 0 15px rgba(0, 200, 83, 0.4);
      flex-shrink: 0;
    }

    .btn-send:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 20px rgba(0, 200, 83, 0.6);
      background: linear-gradient(135deg, #00e676, #69f0ae);
    }

    .btn-send:active { transform: translateY(0); }

    /* ── Typing Indicator ── */
    .typing-indicator {
      display: flex;
      align-items: center;
      gap: 4px;
      padding: 0.5rem 0;
    }
    .typing-indicator span {
      width: 6px;
      height: 6px;
      background: #00e676;
      border-radius: 50%;
      animation: bounce 1.4s infinite ease-in-out both;
    }
    .typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
    .typing-indicator span:nth-child(2) { animation-delay: -0.16s; }
    
    @keyframes bounce {
      0%, 80%, 100% { transform: scale(0); }
      40% { transform: scale(1); box-shadow: 0 0 8px #00e676; }
    }
    
    /* ── Message Avatar ── */
    .msg-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      margin-bottom: 0.5rem;
    }
    
    .system-msg .msg-avatar {
      background: rgba(0, 230, 118, 0.15);
      border: 1px solid rgba(0, 230, 118, 0.4);
      color: #00e676;
    }
    
    .user-msg .msg-avatar {
      background: rgba(41, 121, 255, 0.15);
      border: 1px solid rgba(41, 121, 255, 0.4);
      color: #2979FF;
      align-self: flex-end;
    }

    /* ── Page Jump Toast ── */
    .page-jump-toast {
      position: fixed;
      bottom: 80px;
      right: 30px;
      background: rgba(0, 230, 118, 0.15);
      border: 1px solid rgba(0, 230, 118, 0.5);
      color: #00e676;
      padding: 0.6rem 1.2rem;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 600;
      backdrop-filter: blur(10px);
      z-index: 9999;
      animation: toastSlide 0.3s ease-out, toastFade 0.4s ease-in 1.5s forwards;
      box-shadow: 0 4px 20px rgba(0, 230, 118, 0.2);
      pointer-events: none;
    }

    @keyframes toastSlide {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes toastFade {
      to { opacity: 0; transform: translateY(-10px); }
    }

    /* ── Responsive ── */
    @media (max-width: 1024px) {
      .pdf-panel.visible {
        position: fixed;
        top: 80px;
        right: 0;
        bottom: 0;
        width: 90%;
        max-width: 500px;
        margin-left: 0;
        z-index: 1000;
        border-radius: 12px 0 0 12px;
      }
      .chat-panel.with-pdf {
        flex: 1;
      }
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/includes/header.php'; ?>

  <div class="page-wrap">
    <div class="page-title">
      <h2>💬 AI 智能顧問</h2>
      <span class="badge badge-success">Ollama Qwen 2.5 驅動</span>
      <p style="color:var(--muted); margin-top:0.5rem; font-size:0.9rem;">以本地化的大型語言模型，深度解析您的 ESG 報告與財務數據。每句推論皆附帶<span style="color:#00e676;">頁碼回索標籤</span>，可直接跳轉原文 PDF 頁面。</p>
    </div>

    <div class="chat-pdf-container">
      <!-- ═══ LEFT: Chat Panel ═══ -->
      <div class="chat-panel" id="chatPanel">
        <div class="card chat-card">
          <div class="chat-header-bar">
            <div style="color: #e0e0e0; font-weight: 600; display:flex; align-items:center; gap:0.5rem;">
              <span style="font-size:1.2rem;">📊</span> 領域知識範圍選擇
            </div>
            <div class="chat-controls">
              <select id="companySelect" class="cyber-select">
                <option value="">載入中...</option>
              </select>
              <select id="yearSelect" class="cyber-select">
                <option value="">載入中...</option>
              </select>
              <button id="resetChatBtn" class="btn-reset">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                重置對話
              </button>
            </div>
          </div>

          <div id="chatBox" class="chat-box">
            <!-- Messages rendered here -->
          </div>

          <div class="chat-input-area">
            <div class="chat-input-wrapper">
              <textarea id="chatInput" placeholder="請輸入您的問題... (例如：該公司在 2023 年的減碳目標為何？ROE 表現如何？)" rows="1"></textarea>
            </div>
            <button id="sendBtn" class="btn-send">
              發送 
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
            </button>
          </div>
          <div style="text-align: center; color: rgba(255,255,255,0.4); font-size: 0.8rem; padding: 0.5rem 1rem 1rem;">
            ⚠️ 免責聲明：Ollama Qwen 2.5 是 AI 語言模型，生成的內容可能會出錯或產生幻覺。在進行商業決策前，請務必親自核實重要數據與資訊。點擊 <span style="color:#00e676;">[p.頁碼]</span> 標籤可即時回溯 PDF 原文。
          </div>
        </div>
      </div>

      <!-- ═══ RIGHT: PDF Viewer Panel ═══ -->
      <div class="pdf-panel" id="pdfPanel">
        <div class="pdf-panel-inner">
          <div class="pdf-header">
            <div class="pdf-header-title">
              <span>📑</span>
              <span id="pdfTitle">原文 PDF 閱覽器</span>
            </div>
            <div class="pdf-header-controls">
              <button class="pdf-nav-btn" id="pdfZoomOutBtn" title="縮小">－</button>
              <span class="pdf-zoom-indicator" id="pdfZoomIndicator" title="點擊重置為 100%">100%</span>
              <button class="pdf-nav-btn" id="pdfZoomInBtn" title="放大">＋</button>
              <span style="color: rgba(255,255,255,0.15); margin: 0 2px;">|</span>
              <button class="pdf-nav-btn" id="pdfPrevBtn" title="上一頁">◀</button>
              <span class="pdf-page-indicator" id="pdfPageIndicator">- / -</span>
              <button class="pdf-nav-btn" id="pdfNextBtn" title="下一頁">▶</button>
              <button class="pdf-close-btn" id="pdfCloseBtn" title="關閉 PDF 面板">✕</button>
            </div>
          </div>
          <div class="pdf-canvas-wrapper" id="pdfCanvasWrapper">
            <canvas id="pdfCanvas"></canvas>
          </div>
          <!-- Loading overlay -->
          <div class="pdf-loading-overlay" id="pdfLoading" style="display:none;">
            <div class="pdf-loading-spinner"></div>
            <div class="pdf-loading-text">載入 PDF 中...</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="/eco_sys/assets/js/app.js"></script>
  <script>
  // Capture client-side errors and log them to the backend
  window.onerror = function(message, source, lineno, colno, error) {
    fetch('/eco_sys/api/log_error.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        type: 'onerror',
        message: message,
        source: source,
        lineno: lineno,
        colno: colno,
        stack: error ? error.stack : ''
      })
    }).catch(() => {});
  };
  window.onunhandledrejection = function(event) {
    fetch('/eco_sys/api/log_error.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        type: 'unhandledrejection',
        reason: event.reason ? (event.reason.message || event.reason.toString()) : '',
        stack: event.reason ? event.reason.stack : ''
      })
    }).catch(() => {});
  };

  document.addEventListener('DOMContentLoaded', () => {
    // ═══════════════════════════════════════════
    //  Chat Logic (preserved from original)
    // ═══════════════════════════════════════════
    const chatBox = document.getElementById('chatBox');
    const chatInput = document.getElementById('chatInput');
    const sendBtn = document.getElementById('sendBtn');
    const companySelect = document.getElementById('companySelect');
    const yearSelect = document.getElementById('yearSelect');
    const resetBtn = document.getElementById('resetChatBtn');

    const validCombos = <?= json_encode($validCombos) ?>;
    
    function updateYearOptions() {
      const comp = companySelect.value;
      const years = validCombos[comp] || [];
      yearSelect.innerHTML = '';
      if (years.length === 0) {
          yearSelect.innerHTML = '<option value="">(無報告)</option>';
          yearSelect.disabled = true;
          sendBtn.disabled = true;
      } else {
          yearSelect.disabled = false;
          sendBtn.disabled = false;
          
          if (comp !== 'ALL_跨公司對比') {
              const allOpt = document.createElement('option');
              allOpt.value = 'ALL';
              allOpt.textContent = '📈 歷年趨勢 (多年份)';
              yearSelect.appendChild(allOpt);
          }

          years.forEach(y => {
              const opt = document.createElement('option');
              opt.value = y;
              opt.textContent = y + ' 年度';
              yearSelect.appendChild(opt);
          });
      }
    }

    companySelect.innerHTML = '';
    const companies = Object.keys(validCombos);
    if (companies.length === 0) {
        companySelect.innerHTML = '<option value="">(無公司資料)</option>';
        companySelect.disabled = true;
        yearSelect.innerHTML = '<option value="">(無報告)</option>';
        yearSelect.disabled = true;
        sendBtn.disabled = true;
    } else {
        const allOpt = document.createElement('option');
        allOpt.value = 'ALL_跨公司對比';
        allOpt.textContent = '🌟 跨公司綜合分析 (ALL)';
        companySelect.appendChild(allOpt);
        
        companies.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c;
            opt.textContent = c.replace('_', ' ');
            companySelect.appendChild(opt);
        });
        
        const allYears = new Set();
        Object.values(validCombos).forEach(years => years.forEach(y => allYears.add(y)));
        validCombos['ALL_跨公司對比'] = Array.from(allYears).sort().reverse();
        
        companySelect.addEventListener('change', updateYearOptions);
        updateYearOptions();
    }

    let history = JSON.parse(sessionStorage.getItem('chat_history') || '[]');
    let currentPdfFile = sessionStorage.getItem('chat_pdf_file') || '';

    const defaultMsg = `歡迎使用 **Eco Trust AI 智能顧問**！\n\n請在上方面板選擇您想分析的**公司**與**年份**。我將自動調閱該年度的 ESG 永續報告書、新聞指標、碳排放數據及 ROE 財務表現 來為您解答。\n\n✨ **全新功能**：AI 回覆中的每項事實都附帶 **頁碼回索標籤**，點擊即可跳轉到 PDF 原文頁面，讓您零延遲驗證每一筆數據。`;

    // ═══════════════════════════════════════════
    //  Enhanced Markdown + Page Citation Renderer
    // ═══════════════════════════════════════════
    function formatMarkdown(text) {
      let html = text.replace(/</g, '&lt;').replace(/>/g, '&gt;');

      // Bold
      html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

      // Standard links
      html = html.replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2" target="_blank" style="color:#00B0FF">$1</a>');

      // ── Page Citation Tags ──
      // [p.XX] or 【第XX頁】 or 第XX頁 → clickable page citation
      html = html.replace(
        /【第\s*(\d+)\s*頁】|\[p\.\s*(\d+)\s*\]|第\s*(\d+)\s*頁/g,
        (match, p1, p2, p3) => {
          const page = p1 || p2 || p3;
          return `<a class="page-citation" data-page="${page}" title="跳轉至原文第 ${page} 頁">第${page}頁</a>`;
        }
      );

      // [資料庫] → database source tag
      html = html.replace(
        /\[資料庫\]/g,
        '<span class="db-citation">資料庫</span>'
      );

      // [p.?] → unknown source tag
      html = html.replace(
        /\[p\.\?\]/g,
        '<span class="unknown-citation">p.?</span>'
      );

      // Newlines
      html = html.replace(/\n/g, '<br>');
      return html;
    }

    function appendMessage(role, content) {
      const msgDiv = document.createElement('div');
      msgDiv.className = `message ${role === 'user' ? 'user-msg' : 'system-msg'}`;
      
      const avatar = document.createElement('div');
      avatar.className = 'msg-avatar';
      avatar.innerHTML = role === 'user' ? '👤' : '🤖';
      
      const contentDiv = document.createElement('div');
      contentDiv.className = 'msg-content';
      contentDiv.innerHTML = formatMarkdown(content);

      msgDiv.appendChild(avatar);
      msgDiv.appendChild(contentDiv);
      chatBox.appendChild(msgDiv);
      chatBox.scrollTop = chatBox.scrollHeight;
    }

    function initChat() {
      chatBox.innerHTML = '';
      if(history.length > 0) {
        history.forEach(m => appendMessage(m.role, m.content));
        // Restore PDF if previously loaded
        if (currentPdfFile) {
          loadPdf(currentPdfFile);
        }
      } else {
        appendMessage('system', defaultMsg);
      }
    }

    function saveHistory() {
      sessionStorage.setItem('chat_history', JSON.stringify(history));
      sessionStorage.setItem('chat_pdf_file', currentPdfFile);
    }

    resetBtn.addEventListener('click', () => {
      history = [];
      currentPdfFile = '';
      saveHistory();
      closePdfPanel();
      initChat();
    });

    // Auto-resize textarea
    chatInput.addEventListener('input', function() {
      this.style.height = 'auto';
      this.style.height = (this.scrollHeight < 120 ? this.scrollHeight : 120) + 'px';
    });

    async function sendMessage() {
      const text = chatInput.value.trim();
      if (!text) return;

      const company = companySelect.value;
      const year = yearSelect.value;

      appendMessage('user', text);
      history.push({role: 'user', content: text});
      saveHistory();
      
      chatInput.value = '';
      chatInput.style.height = 'auto';

      // Show loading
      const loadingDiv = document.createElement('div');
      loadingDiv.className = 'message system-msg';
      loadingDiv.innerHTML = `<div class="msg-avatar">🤖</div>
        <div class="msg-content">
          <div class="typing-indicator"><span></span><span></span><span></span></div>
        </div>`;
      chatBox.appendChild(loadingDiv);
      chatBox.scrollTop = chatBox.scrollHeight;

      try {
        const response = await fetch('/eco_sys/api/chat_api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            message: text,
            company: company,
            year: year,
            history: history.slice(0, -1)
          })
        });

        const data = await response.json();
        chatBox.removeChild(loadingDiv);

        if (data.error) {
          appendMessage('system', '❌ 發生錯誤: ' + data.error);
        } else {
          appendMessage('system', data.reply);
          history.push({role: 'assistant', content: data.reply});

          // Auto-load PDF and auto-jump to the first page cited in reply
          if (data.pdf_file) {
            currentPdfFile = data.pdf_file;
            
            // Extract the first page citation from the reply
            const citationMatch = data.reply.match(/【第\s*(\d+)\s*頁】|\[p\.\s*(\d+)\s*\]|第\s*(\d+)\s*頁/);
            const firstPage = citationMatch ? parseInt(citationMatch[1] || citationMatch[2] || citationMatch[3], 10) : null;
            
            loadPdf(currentPdfFile).then(() => {
              if (firstPage !== null) {
                // Delay slightly to ensure panel slides open and rendering completes
                setTimeout(() => {
                  jumpToPage(firstPage);
                }, 300);
              }
            });
          }
          saveHistory();
        }

      } catch (err) {
        chatBox.removeChild(loadingDiv);
        appendMessage('system', '❌ 系統連線異常，請檢查本地端 Ollama 服務是否正常運作。');
      }
    }

    sendBtn.addEventListener('click', sendMessage);
    chatInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    // ═══════════════════════════════════════════
    //  PDF.js Viewer Engine
    // ═══════════════════════════════════════════
    const pdfPanel = document.getElementById('pdfPanel');
    const chatPanel = document.getElementById('chatPanel');
    let pdfCanvas = document.getElementById('pdfCanvas');
    const pdfCanvasWrapper = document.getElementById('pdfCanvasWrapper');
    const pdfPageIndicator = document.getElementById('pdfPageIndicator');
    const pdfPrevBtn = document.getElementById('pdfPrevBtn');
    const pdfNextBtn = document.getElementById('pdfNextBtn');
    const pdfCloseBtn = document.getElementById('pdfCloseBtn');
    const pdfLoading = document.getElementById('pdfLoading');
    const pdfTitle = document.getElementById('pdfTitle');
    const pdfZoomInBtn = document.getElementById('pdfZoomInBtn');
    const pdfZoomOutBtn = document.getElementById('pdfZoomOutBtn');
    const pdfZoomIndicator = document.getElementById('pdfZoomIndicator');

    // Configure PDF.js worker
    pdfjsLib.GlobalWorkerOptions.workerSrc = '/eco_sys/assets/js/pdf.worker.min.js';

    let pdfDoc = null;
    let currentPage = 1;
    let totalPages = 0;
    let rendering = false;
    let pendingPage = null;
    let currentLoadedPdf = '';
    let zoomScaleMultiplier = 1.0;


    async function loadPdf(filename) {
      if (!filename) return;
      if (currentLoadedPdf === filename && pdfDoc) {
        // Already loaded, just show panel
        openPdfPanel();
        return;
      }

      openPdfPanel();
      pdfLoading.style.display = 'flex';
      pdfTitle.textContent = filename;

      // Clear any previous error message and restore canvas
      pdfCanvasWrapper.innerHTML = '';
      // Re-create canvas if it was destroyed by previous innerHTML assignment
      if (!document.getElementById('pdfCanvas')) {
        const c = document.createElement('canvas');
        c.id = 'pdfCanvas';
        pdfCanvas = c;
      }
      pdfCanvasWrapper.appendChild(pdfCanvas);

      // Reset zoom scale to 100% on new PDF load
      zoomScaleMultiplier = 1.0;
      updateZoomIndicator();

      try {
        const url = `/eco_sys/api/serve_pdf.php?file=${encodeURIComponent(filename)}`;

        // Pre-flight: verify the server actually returns a PDF
        // (catches auth redirects that would silently serve HTML)
        const probe = await fetch(url, { method: 'HEAD', credentials: 'same-origin' });
        if (!probe.ok) {
          let errMsg = `HTTP ${probe.status}`;
          try {
            // Try to read error JSON from a GET (HEAD has no body)
            const errResp = await fetch(url, { credentials: 'same-origin' });
            const errData = await errResp.json();
            errMsg = errData.error || errMsg;
          } catch (_) { /* ignore */ }
          throw new Error(errMsg);
        }
        const ct = probe.headers.get('content-type') || '';
        if (!ct.includes('application/pdf')) {
          throw new Error('伺服器未回傳 PDF（可能需要重新登入）');
        }

        const loadingTask = pdfjsLib.getDocument({ url, withCredentials: true });
        pdfDoc = await loadingTask.promise;
        totalPages = pdfDoc.numPages;
        currentPage = 1;
        currentLoadedPdf = filename;
        updatePageIndicator();
        await renderPage(currentPage);
      } catch (err) {
        console.error('PDF load error:', err);
        pdfDoc = null;
        currentLoadedPdf = '';
        const escapedFilename = filename.replace(/</g, '&lt;').replace(/>/g, '&gt;');
        pdfCanvasWrapper.innerHTML = `<div style="color:#ff4757; text-align:center; padding:2rem;">
          <div style="font-size:2rem; margin-bottom:1rem;">⚠️</div>
          <div>PDF 載入失敗</div>
          <div style="font-size:0.8rem; color:#999; margin-top:0.5rem;">${escapedFilename}</div>
          <div style="font-size:0.75rem; color:#666; margin-top:0.5rem;">${(err.message || '').replace(/</g, '&lt;')}</div>
          <button onclick="loadPdf('${filename.replace(/'/g, "\\'")}')"
                  style="margin-top:1rem; padding:0.5rem 1.2rem; background:rgba(0,230,118,0.15);
                         border:1px solid rgba(0,230,118,0.4); color:#00e676; border-radius:6px;
                         cursor:pointer; font-size:0.85rem;">🔄 重試</button>
        </div>`;
      } finally {
        pdfLoading.style.display = 'none';
      }
    }
    // Expose loadPdf globally so the retry button onclick can call it
    window.loadPdf = loadPdf;

    async function renderPage(pageNum) {
      if (!pdfDoc) return;
      if (rendering) {
        pendingPage = pageNum;
        return;
      }
      rendering = true;

      try {
        const page = await pdfDoc.getPage(pageNum);
        
        let wrapperWidth = pdfCanvasWrapper.clientWidth - 32; // minus padding
        if (wrapperWidth <= 100) {
          // Fallback to parent container width if panel is still animating open (width 0)
          const container = document.querySelector('.chat-pdf-container');
          if (container && container.clientWidth > 0) {
            wrapperWidth = (container.clientWidth * 0.42) - 32;
          }
          if (wrapperWidth <= 100) {
            wrapperWidth = 800; // hard fallback
          }
        }

        const unscaledViewport = page.getViewport({ scale: 1 });
        const autoScale = wrapperWidth / unscaledViewport.width;
        const scale = Math.max(0.1, autoScale * zoomScaleMultiplier);
        const viewport = page.getViewport({ scale: scale });

        const canvas = pdfCanvas;
        if (!pdfCanvasWrapper.contains(canvas)) {
          pdfCanvasWrapper.innerHTML = '';
          pdfCanvasWrapper.appendChild(canvas);
        }
        const ctx = canvas.getContext('2d');
        canvas.height = viewport.height;
        canvas.width = viewport.width;

        await page.render({ canvasContext: ctx, viewport: viewport }).promise;
        currentPage = pageNum;
        updatePageIndicator();
      } catch (err) {
        console.error('Render error:', err);
        // Log rendering error to backend for diagnostics
        fetch('/eco_sys/api/log_error.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            type: 'render_error',
            message: err.message || err.toString(),
            stack: err.stack || ''
          })
        }).catch(() => {});
      } finally {
        rendering = false;
        if (pendingPage !== null) {
          const next = pendingPage;
          pendingPage = null;
          renderPage(next);
        }
      }
    }

    function updatePageIndicator() {
      pdfPageIndicator.textContent = `${currentPage} / ${totalPages}`;
    }

    function jumpToPage(pageNum) {
      if (!pdfDoc) return;
      pageNum = Math.max(1, Math.min(pageNum, totalPages));
      renderPage(pageNum);
      // Scroll canvas into view
      pdfCanvasWrapper.scrollTop = 0;
      // Show toast
      showPageJumpToast(pageNum);
    }

    function showPageJumpToast(page) {
      // Remove any existing toast
      document.querySelectorAll('.page-jump-toast').forEach(el => el.remove());
      const toast = document.createElement('div');
      toast.className = 'page-jump-toast';
      toast.textContent = `📄 已跳轉至第 ${page} 頁`;
      document.body.appendChild(toast);
      setTimeout(() => toast.remove(), 2000);
    }

    // PDF Zoom Controls
    pdfZoomInBtn.addEventListener('click', () => {
      if (zoomScaleMultiplier < 3.0) {
        zoomScaleMultiplier = parseFloat((zoomScaleMultiplier + 0.2).toFixed(1));
        updateZoomIndicator();
        if (pdfDoc) renderPage(currentPage);
      }
    });

    pdfZoomOutBtn.addEventListener('click', () => {
      if (zoomScaleMultiplier > 0.5) {
        zoomScaleMultiplier = parseFloat((zoomScaleMultiplier - 0.2).toFixed(1));
        updateZoomIndicator();
        if (pdfDoc) renderPage(currentPage);
      }
    });

    pdfZoomIndicator.addEventListener('click', () => {
      if (zoomScaleMultiplier !== 1.0) {
        zoomScaleMultiplier = 1.0;
        updateZoomIndicator();
        if (pdfDoc) renderPage(currentPage);
      }
    });

    function updateZoomIndicator() {
      pdfZoomIndicator.textContent = `${Math.round(zoomScaleMultiplier * 100)}%`;
    }

    // PDF navigation
    pdfPrevBtn.addEventListener('click', () => {
      if (currentPage > 1) jumpToPage(currentPage - 1);
    });
    pdfNextBtn.addEventListener('click', () => {
      if (currentPage < totalPages) jumpToPage(currentPage + 1);
    });
    pdfCloseBtn.addEventListener('click', closePdfPanel);

    // PDF Click-and-Drag Panning (Grab-to-Pan)
    let isDragging = false;
    let startX, startY, scrollLeftStart, scrollTopStart;

    pdfCanvas.addEventListener('mousedown', (e) => {
      if (e.button !== 0) return; // Only left click
      isDragging = true;
      pdfCanvas.style.cursor = 'grabbing';
      
      startX = e.pageX - pdfCanvasWrapper.offsetLeft;
      startY = e.pageY - pdfCanvasWrapper.offsetTop;
      scrollLeftStart = pdfCanvasWrapper.scrollLeft;
      scrollTopStart = pdfCanvasWrapper.scrollTop;
      
      e.preventDefault(); // Prevent default text selection/image drag
    });

    document.addEventListener('mousemove', (e) => {
      if (!isDragging) return;
      
      const x = e.pageX - pdfCanvasWrapper.offsetLeft;
      const y = e.pageY - pdfCanvasWrapper.offsetTop;
      const walkX = x - startX;
      const walkY = y - startY;
      
      pdfCanvasWrapper.scrollLeft = scrollLeftStart - walkX;
      pdfCanvasWrapper.scrollTop = scrollTopStart - walkY;
    });

    document.addEventListener('mouseup', () => {
      if (isDragging) {
        isDragging = false;
        pdfCanvas.style.cursor = 'grab';
      }
    });

    function openPdfPanel() {
      pdfPanel.classList.add('visible');
      chatPanel.classList.add('with-pdf');
    }

    function closePdfPanel() {
      pdfPanel.classList.remove('visible');
      chatPanel.classList.remove('with-pdf');
    }

    // ═══════════════════════════════════════════
    //  Page Citation Click Handler (Event Delegation)
    // ═══════════════════════════════════════════
    chatBox.addEventListener('click', (e) => {
      const citation = e.target.closest('.page-citation');
      if (!citation) return;

      e.preventDefault();
      const pageNum = parseInt(citation.dataset.page, 10);
      if (isNaN(pageNum)) return;

      // Add click animation
      citation.classList.remove('clicked');
      void citation.offsetWidth; // trigger reflow
      citation.classList.add('clicked');

      // If PDF is not loaded yet, try to load it
      if (!pdfDoc && currentPdfFile) {
        loadPdf(currentPdfFile).then(() => {
          setTimeout(() => jumpToPage(pageNum), 300);
        });
      } else if (pdfDoc) {
        openPdfPanel();
        jumpToPage(pageNum);
      } else {
        // No PDF available — show a hint
        showPageJumpToast(`第 ${pageNum} 頁 (PDF 尚未載入)`);
      }
    });

    // Keyboard support for PDF navigation
    document.addEventListener('keydown', (e) => {
      if (!pdfDoc || !pdfPanel.classList.contains('visible')) return;
      if (document.activeElement === chatInput) return; // don't intercept typing
      if (e.key === 'ArrowLeft') jumpToPage(currentPage - 1);
      if (e.key === 'ArrowRight') jumpToPage(currentPage + 1);
    });

    // ═══════════════════════════════════════════
    //  Initialize
    // ═══════════════════════════════════════════
    initChat();
  });
  </script>
</body>
</html>
