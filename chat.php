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
      cursor: grab;
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
    .info-msg { align-self: flex-start; }
    .info-msg .msg-content {
      background: rgba(255, 159, 67, 0.08);
      border: 1px solid rgba(255, 159, 67, 0.25);
      color: #ffe0b2;
      border-bottom-left-radius: 0;
    }

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
      gap: 5px;
      background: rgba(0, 120, 255, 0.05);
      border: 1px solid #0056b3;
      color: #5cbbf6 !important;
      padding: 1px 8px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      text-decoration: none !important;
      vertical-align: middle;
      line-height: 1.2;
      margin: 0 3px;
    }

    .db-icon {
      width: 10px;
      height: 12px;
      background: linear-gradient(to bottom, #9c27b0 0%, #7b1fa2 100%);
      border-radius: 2px;
      position: relative;
      display: inline-block;
      flex-shrink: 0;
    }

    .db-icon::before {
      content: '';
      position: absolute;
      width: 6px;
      height: 1.5px;
      background: rgba(255, 255, 255, 0.8);
      left: 2px;
      top: 3px;
      box-shadow: 0 4px 0 rgba(255, 255, 255, 0.8);
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

    /* News source tag */
    .news-citation {
      display: inline-flex;
      align-items: center;
      gap: 2px;
      background: rgba(0, 176, 255, 0.15);
      border: 1px solid rgba(0, 176, 255, 0.35);
      color: #00b0ff !important;
      padding: 1px 7px;
      border-radius: 10px;
      font-size: 0.75rem;
      font-weight: 600;
      text-decoration: none !important;
      vertical-align: super;
      line-height: 1;
      margin: 0 1px;
      transition: all 0.2s;
    }
    .news-citation::before {
      content: '📰';
      font-size: 0.6rem;
    }
    .news-citation:hover {
      background: rgba(0, 176, 255, 0.25);
      border-color: rgba(0, 176, 255, 0.6);
      box-shadow: 0 0 8px rgba(0, 176, 255, 0.2);
    }

    /* ── Demo Suggestion Buttons & Agent Console ── */
    .demo-buttons-container {
      display: flex;
      gap: 0.75rem;
      padding: 0.75rem 1.5rem;
      background: rgba(11, 14, 20, 0.7);
      border-top: 1px solid rgba(41, 121, 255, 0.15);
      justify-content: flex-start;
      flex-wrap: wrap;
      border-bottom: 1px solid rgba(41, 121, 255, 0.1);
    }
    .btn-demo-suggest {
      background: rgba(41, 121, 255, 0.08);
      border: 1px solid rgba(41, 121, 255, 0.25);
      color: #82b1ff;
      padding: 0.35rem 0.8rem;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      gap: 0.35rem;
      user-select: none;
    }
    .btn-demo-suggest:hover {
      background: rgba(0, 230, 118, 0.1);
      border-color: rgba(0, 230, 118, 0.35);
      color: #00e676;
      box-shadow: 0 0 8px rgba(0, 230, 118, 0.15);
    }
    .btn-demo-suggest:active {
      transform: translateY(1px);
    }
    .agent-console-log {
      font-family: 'Consolas', 'Courier New', monospace;
      font-size: 0.78rem;
      background: rgba(0, 0, 0, 0.55);
      border: 1px solid rgba(41, 121, 255, 0.15);
      border-radius: 6px;
      padding: 0.6rem;
      margin-top: 0.5rem;
      color: #00e676;
      max-height: 150px;
      overflow-y: auto;
      line-height: 1.45;
      text-align: left;
    }
    .agent-log-line {
      margin-bottom: 3px;
    }
    .agent-log-line.info { color: #79a6ff; }
    .agent-log-line.success { color: #00e676; }
    .agent-log-line.warn { color: #ffb74d; }
    .agent-log-line.think { color: #b0bec5; font-style: italic; }

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

    .info-msg .msg-avatar {
      background: rgba(255, 159, 67, 0.15);
      border: 1px solid rgba(255, 159, 67, 0.4);
      color: #ff9f43;
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

          <!-- ── Demo Suggestion Buttons Container ── -->
          <div class="demo-buttons-container">
            <button class="btn-demo-suggest" data-question="該公司近年來的 ROE 發生了什麼變化？">
              <span>📊</span> 快速財務資料庫 (ROE 變化)
            </button>
            <button class="btn-demo-suggest" data-question="請幫我綜合整理公司近年來的財務 ROE 表現、報告書中提到的具體減碳行動與措施，以及近年相關的新聞輿情事件。">
              <span>📑</span> 報告具體行動 (綜合整理 ROE、減碳與輿情)
            </button>
            <button class="btn-demo-suggest" data-question="綜合評估該公司在永續治理與環境策略上的整體表現與挑戰為何？">
              <span>🧠</span> 抽象策略評估 (展示思考過程)
            </button>
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

      // Standard links (if text is "新聞" or "新聞連結", style it as a news citation tag)
      html = html.replace(/\[(.*?)\]\((.*?)\)/g, (match, text, url) => {
        if (text === '新聞' || text === '新聞連結' || text === '連結') {
          return `<a href="${url}" target="_blank" class="news-citation">[新聞]</a>`;
        }
        return `<a href="${url}" target="_blank" style="color:#00B0FF">${text}</a>`;
      });

      // [p.XX_COMP_YYYY] or [p.XX_YYYY] or [p.XX] or 【第XX頁】 or 第XX頁 → clickable page citation
      html = html.replace(
        /【第\s*(\d+)\s*頁】|\[p\.\s*(\d+)\s*(?:_(\d+))?(?:_(\d{4}))?\s*\]|第\s*(\d+)\s*頁/g,
        (match, p1, p2, p3, p4, p5) => {
          const page = p1 || p2 || p5;
          let company = companySelect.value;
          let y = null;
          
          if (p3 && p4) {
            // Format: [p.page_companySymbol_year] e.g., [p.120_1103_2023]
            const compSymbol = p3;
            y = p4;
            // Map the company symbol (e.g. 1103) to select option value (e.g. 1103_嘉泥)
            const options = Array.from(companySelect.options);
            const foundOpt = options.find(opt => opt.value.startsWith(compSymbol + '_'));
            if (foundOpt) {
              company = foundOpt.value;
            }
          } else if (p3) {
            // Format: [p.page_year] e.g., [p.120_2023]
            y = p3;
          }
          
          if (y && company && company !== 'ALL_跨公司對比') {
            const pdfFile = `${company}_${y}.pdf`;
            return `<a class="page-citation" data-page="${page}" data-pdf="${pdfFile}" title="跳轉至 ${y} 年原文第 ${page} 頁">[p.${page} (${y})]</a>`;
          }
          return `<a class="page-citation" data-page="${page}" title="跳轉至原文第 ${page} 頁">[p.${page}]</a>`;
        }
      );

      // [資料庫] → database source tag
      html = html.replace(
        /\[資料庫\]/g,
        '<span class="db-citation"><span class="db-icon"></span>[資料庫]</span>'
      );

      // [p.?_YYYY] or [p.?] → unknown source tag
      html = html.replace(
        /\[p\.\?(?:_(\d{4}))?\]/g,
        (match, year) => {
          if (year) {
            return `<span class="unknown-citation">[p.? (${year})]</span>`;
          }
          return '<span class="unknown-citation">[p.?]</span>';
        }
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

    // Demo Suggestion Buttons Click Handler
    document.querySelectorAll('.btn-demo-suggest').forEach(btn => {
      btn.addEventListener('click', () => {
        // Ensure "歷年趨勢" (ALL) is selected if it's available
        const allOption = Array.from(yearSelect.options).find(opt => opt.value === 'ALL');
        if (allOption && yearSelect.value !== 'ALL') {
          yearSelect.value = 'ALL';
        }
        
        chatInput.value = btn.getAttribute('data-question');
        chatInput.dispatchEvent(new Event('input')); // trigger auto-resize
        sendMessage();
      });
    });

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

      // Helper function to check if the query is database-only
      function isDatabaseOnlyQuery(queryText) {
        const norm = queryText.toLowerCase();
        // Keywords indicating financial database (ROE, etc.), ESG metrics/commitments database, or news database
        const dbKeywords = [
          'roe', '報酬率', '獲利', '淨利', '純益', 'eps', '每股盈餘', '營收', '利潤', '盈餘', '損益',
          '信心分數', '信賴度', '誠信分數', '承諾', '量化比', '時限比', '信用分', '誠信信心', '信度',
          '新聞', '輿情', '情感', '媒體', '報導', '新聞評價', '情緒'
        ];
        // Keywords that require full text PDF analysis (details, policies, actions)
        const reportKeywords = [
          '具體', '措施', '政策', '細節', '內容', '怎麼做', '哪些做法', '如何進行', '減碳項目', '環境政策', '治理政策', '實施', '行動方案'
        ];
        
        const hasDb = dbKeywords.some(kw => norm.includes(kw));
        const hasReport = reportKeywords.some(kw => norm.includes(kw));
        
        return hasDb && !hasReport;
      }

      // ⚠️ Warn user about multi-year analysis time
      const isMultiYear = (year === 'ALL' && company !== 'ALL_跨公司對比');
      const isDbOnly = isMultiYear && isDatabaseOnlyQuery(text);

      if (isMultiYear) {
          if (isDbOnly) {
              appendMessage('info', '⚡ **快速資料庫查詢模式啟動**：檢測到您的問題與結構化數據相關，系統將直接讀取資料庫進行歷年趨勢分析，預計幾秒內完成...');
          } else {
              appendMessage('info', '📊 **歷年趨勢分析模式啟動**：系統將逐一針對各年度報告（財務、ESG、新聞）進行深入探索與獨立分析，最後彙整出完整趨勢。此過程需要多次呼叫大語言模型，預估需要 **1 至 2 分鐘**，請耐心等候...');
          }
      }

      // Show loading
      const loadingDiv = document.createElement('div');
      loadingDiv.className = 'message system-msg';
      loadingDiv.innerHTML = `<div class="msg-avatar">🤖</div>
        <div class="msg-content">
          <div class="typing-indicator"><span></span><span></span><span></span></div>
        </div>`;
      chatBox.appendChild(loadingDiv);
      chatBox.scrollTop = chatBox.scrollHeight;

      let consoleDiv = null;
      let thoughtTimer = null;

      try {
        let finalReply = '';
        let finalPdfFile = '';

        // Create console log element inside the active message content
        consoleDiv = document.createElement('div');
        consoleDiv.className = 'agent-console-log';
        loadingDiv.querySelector('.msg-content').appendChild(consoleDiv);

        // Queue-based logging manager
        const logQueue = [];
        let isProcessingQueue = false;

        function queueLog(msg, type = 'info') {
          logQueue.push({ msg, type });
          processLogQueue();
        }

        function processLogQueue() {
          if (isProcessingQueue) return;
          if (logQueue.length === 0) return;
          
          isProcessingQueue = true;
          const item = logQueue.shift();
          
          const line = document.createElement('div');
          line.className = `agent-log-line ${item.type}`;
          let prefix = '';
          if (item.type === 'info') prefix = '[Agent Tool] ';
          else if (item.type === 'success') prefix = '[Agent Success] ';
          else if (item.type === 'warn') prefix = '[Agent Warning] ';
          else if (item.type === 'think') prefix = '[Agent Brain] 🤔 ';
          
          line.textContent = `${prefix}${item.msg}`;
          consoleDiv.appendChild(line);
          consoleDiv.scrollTop = consoleDiv.scrollHeight;
          
          // Smooth scrolling of the main chat container
          chatBox.scrollTop = chatBox.scrollHeight;

          setTimeout(() => {
            isProcessingQueue = false;
            processLogQueue();
          }, 450); // Steady sentence-by-sentence delay (450ms)
        }

        async function flushLogs() {
          while (logQueue.length > 0 || isProcessingQueue) {
            await new Promise(r => setTimeout(r, 100));
          }
        }

        // Active thinking log generator for API calls
        const simulatedThoughts = [
          "正在分析範疇一與範疇二之溫室氣體絕對排放量數據...",
          "正在核實減碳承諾是否具備明確的量化百分比與時間表...",
          "正在比對資料庫季度 ROE 財務走勢與 ESG 投資效益...",
          "正在過濾新聞輿情，分析市場對該公司誠信表現之真實回饋...",
          "正在對 ESG 報告書原文相關章節進行關聯性交叉審計...",
          "正在評估董事會獨立性與氣候風險治理架構之完備度..."
        ];
        let thoughtIndex = 0;
        thoughtTimer = setInterval(() => {
          if (logQueue.length === 0 && !isProcessingQueue) {
            const t = simulatedThoughts[thoughtIndex % simulatedThoughts.length];
            queueLog(t, 'think');
            thoughtIndex++;
          }
        }, 3200);

        if (isMultiYear) {
          if (isDbOnly) {
              // ── Fast Database-Only Flow ──
              queueLog('啟動快速資料庫查詢代理流程...', 'info');
              queueLog(`識別問題屬性：結構化數據檢索 (${text})`, 'think');
              queueLog('正在連接 MariaDB 數據庫...', 'info');
              queueLog(`查詢公司 [${company}] 歷年季度財務 ROE 指標...`, 'info');
              queueLog(`查詢公司 [${company}] 歷年 ESG 誠信可靠性指標...`, 'info');
              queueLog(`查詢公司 [${company}] 歷年相關輿情與新聞...`, 'info');

              const response = await fetch('/eco_sys/api/chat_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  message: text,
                  company: company,
                  year: 'ALL',
                  history: history.slice(0, -1)
                })
              });
              
              queueLog('資料庫檢索成功，正在將歷年數據送交 Ollama 進行單次整合歸納...', 'info');
              const data = await response.json();
              if (data.error) throw new Error(data.error);
              queueLog('Ollama 整合分析成功，已生成最終報告！', 'success');
              
              finalReply = data.reply;
              if (data.pdf_file) finalPdfFile = data.pdf_file;
          } else {
              // ── Map-Reduce Multi-Year Orchestration ──
              queueLog('啟動 Map-Reduce 多年度報告分析代理流程...', 'info');
              queueLog(`識別問題屬性：非結構化 PDF 原文分析 (${text})`, 'think');
              queueLog('執行 get_available_years 獲取可分析年度...', 'info');

              // 2. Fetch available years
              const yearsResp = await fetch('/eco_sys/api/chat_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_available_years', company: company })
              });
              const yearsData = await yearsResp.json();
              if (yearsData.error) throw new Error(yearsData.error);
              const availableYears = yearsData.years || [];
              if (availableYears.length === 0) throw new Error("無可用年度之報告資料。");

              queueLog(`檢測到可用年份：[${availableYears.join(', ')}]，開啟並行子任務探針...`, 'info');

              // 3. Trigger concurrent fetches for all available years
              const analyses = [];
              const promises = availableYears.map(async (y) => {
                queueLog(`正在並行載入 ${y} 年報告特徵與 N-gram 相關度匹配...`, 'info');
                queueLog(`[並行發送 ${y} 年] 正在向 LLM 發送並行子任務分析...`, 'think');
                
                const yearResp = await fetch('/eco_sys/api/chat_api.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({
                    action: 'analyze_year',
                    company: company,
                    year: y,
                    message: text,
                    history: history.slice(0, -1)
                  })
                });
                const yearData = await yearResp.json();
                if (yearData.error) throw new Error(`${y} 年並行分析失敗: ${yearData.error}`);
                
                queueLog(`[並行子任務 ${y} 年] 完成分析並成功回傳！`, 'success');
                return { year: y, reply: yearData.reply, pdf_file: yearData.pdf_file };
              });

              // Wait for all fetches to resolve in parallel
              const results = await Promise.all(promises);

              // Sort results by year to preserve chronological order in final report
              results.sort((a, b) => a.year - b.year);
              results.forEach(res => {
                analyses.push({ year: res.year, reply: res.reply });
                if (res.pdf_file) finalPdfFile = res.pdf_file;
              });

              // 4. Synthesize all analyses
              queueLog('所有年度子任務完成！正在將各年分析結果與來源標籤進行歷年綜合對比...', 'think');
              queueLog('向 Ollama 發送最終整合 (Synthesize) 任務請求...', 'info');
              
              const synthResp = await fetch('/eco_sys/api/chat_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  action: 'synthesize',
                  company: company,
                  message: text,
                  analyses: analyses
                })
              });
              const synthData = await synthResp.json();
              if (synthData.error) throw new Error(synthData.error);
              
              queueLog('歷年綜合對比與來源標註整理完畢！', 'success');
              finalReply = synthData.reply;
          }

        } else {
          // ── Normal Single-Year Flow ──
          queueLog('啟動單一年度報告與數據分析流程...', 'info');
          queueLog(`識別問題屬性：單年 ESG 暨財務狀況綜合評析`, 'think');
          queueLog('正在連接 MariaDB 數據庫...', 'info');
          queueLog(`查詢公司 [${company}] ${year} 年度財務指標與 ROE 數據...`, 'info');
          queueLog(`查詢公司 [${company}] ${year} 年度 ESG 誠信與減碳承諾指標...`, 'info');
          queueLog(`查詢公司 [${company}] ${year} 年度市場新聞輿情數據...`, 'info');
          queueLog('檢索本地 ESG 報告 PDF 原文相關頁面...', 'info');
          queueLog('正在向 Ollama 發送推理與評估請求...', 'think');

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
          if (data.error) throw new Error(data.error);
          
          queueLog('分析完成，已生成最終評估報告！', 'success');
          finalReply = data.reply;
          if (data.pdf_file) finalPdfFile = data.pdf_file;
        }

        clearInterval(thoughtTimer);
        await flushLogs();

        const indicator = loadingDiv.querySelector('.typing-indicator');
        if (indicator) indicator.remove();

        const responseTextDiv = document.createElement('div');
        responseTextDiv.className = 'agent-response-text';
        responseTextDiv.style.marginTop = '1rem';
        responseTextDiv.innerHTML = formatMarkdown(finalReply);
        loadingDiv.querySelector('.msg-content').appendChild(responseTextDiv);

        history.push({role: 'assistant', content: finalReply});

        // Auto-load PDF and auto-jump to the first page cited in reply
        if (finalPdfFile) {
          currentPdfFile = finalPdfFile;
          
          // Extract the first page citation from the reply
          const citationMatch = finalReply.match(/【第\s*(\d+)\s*頁】|\[p\.\s*(\d+)\s*(?:_\d{4})?\]|第\s*(\d+)\s*頁/);
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

      } catch (err) {
        if (thoughtTimer) clearInterval(thoughtTimer);
        const indicator = loadingDiv.querySelector('.typing-indicator');
        if (indicator) indicator.remove();
        
        if (consoleDiv) {
          const errDiv = document.createElement('div');
          errDiv.className = 'agent-log-line warn';
          errDiv.textContent = `[Agent Error] ❌ 發生錯誤: ${err.message}`;
          consoleDiv.appendChild(errDiv);
        } else {
          appendMessage('system', '❌ 發生錯誤: ' + err.message);
        }
        
        chatBox.scrollTop = chatBox.scrollHeight;
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
      if (typeof page === 'number' || !isNaN(page)) {
        toast.textContent = `📄 已跳轉至第 ${page} 頁`;
      } else {
        toast.textContent = `📄 ${page}`;
      }
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

    pdfCanvasWrapper.addEventListener('mousedown', (e) => {
      if (e.button !== 0) return; // Only left click
      isDragging = true;
      pdfCanvasWrapper.style.cursor = 'grabbing';
      if (pdfCanvas) pdfCanvas.style.cursor = 'grabbing';
      
      startX = e.pageX;
      startY = e.pageY;
      scrollLeftStart = pdfCanvasWrapper.scrollLeft;
      scrollTopStart = pdfCanvasWrapper.scrollTop;
      
      e.preventDefault(); // Prevent default text selection/image drag
    });

    pdfCanvasWrapper.addEventListener('dragstart', (e) => {
      e.preventDefault();
    });

    document.addEventListener('mousemove', (e) => {
      if (!isDragging) return;
      
      const walkX = e.pageX - startX;
      const walkY = e.pageY - startY;
      
      pdfCanvasWrapper.scrollLeft = scrollLeftStart - walkX;
      pdfCanvasWrapper.scrollTop = scrollTopStart - walkY;
    });

    document.addEventListener('mouseup', () => {
      if (isDragging) {
        isDragging = false;
        pdfCanvasWrapper.style.cursor = 'grab';
        if (pdfCanvas) pdfCanvas.style.cursor = 'grab';
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

      const targetPdf = citation.dataset.pdf;

      if (targetPdf && targetPdf !== currentLoadedPdf) {
        // Dynamic PDF Switch
        loadPdf(targetPdf).then(() => {
          setTimeout(() => jumpToPage(pageNum), 300);
        });
      } else {
        // Normal behavior
        if (!pdfDoc && (targetPdf || currentPdfFile)) {
          loadPdf(targetPdf || currentPdfFile).then(() => {
            setTimeout(() => jumpToPage(pageNum), 300);
          });
        } else if (pdfDoc) {
          openPdfPanel();
          jumpToPage(pageNum);
        } else {
          // No PDF available — show a hint
          showPageJumpToast(`PDF 尚未載入 (無法跳轉至第 ${pageNum} 頁)`);
        }
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
