<?php
if (session_status() === PHP_SESSION_NONE)
  session_start();
if (!empty($_SESSION['user'])) {
  header('Location: /eco_sys/index.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Eco Trust AI — ESG 永續誠信分析平台</title>
  <meta name="description" content="Eco Trust AI 以 FinBERT AI 技術驅動的 ESG 永續誠信分析平台，自動解析企業永續報告並產生信心評分。">
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Noto+Sans+TC:wght@400;500;700&display=swap"
    rel="stylesheet">
  <style>
    :root {
      --bg: #0B0E14;
      --card: rgba(22, 27, 34, 0.7);
      --accent: #2979FF;
      --accent2: #00E676;
      --text: #e8eaf6;
      --muted: #8892b0;
      --border: rgba(108, 99, 255, .2);
      --border2: rgba(255, 255, 255, .06)
    }

    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0
    }

    html {
      scroll-behavior: smooth
    }

    body {
      background: var(--bg);
      color: var(--text);
      font-family: 'Inter', 'Noto Sans TC', sans-serif;
      font-size: 15px;
      line-height: 1.6;
      overflow-x: hidden;
      background-image: radial-gradient(circle at 15% 50%, rgba(41, 121, 255, .08), transparent 30%), radial-gradient(circle at 85% 30%, rgba(0, 230, 118, .05), transparent 30%)
    }

    a {
      color: var(--accent2);
      text-decoration: none
    }

    /* ── HUD Background ── */
    .hud-bg-wrap {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: -1;
      overflow: hidden;
      opacity: .8;
      background-color: #0b0e14;
      background-image: linear-gradient(rgba(41, 121, 255, .05) 1px, transparent 1px), linear-gradient(90deg, rgba(41, 121, 255, .05) 1px, transparent 1px), linear-gradient(rgba(41, 121, 255, .02) 1px, transparent 1px), linear-gradient(90deg, rgba(41, 121, 255, .02) 1px, transparent 1px);
      background-size: 100px 100px, 100px 100px, 20px 20px, 20px 20px
    }

    .hud-overlay-wrap {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 9999;
      overflow: hidden
    }

    #canvas-top,
    #canvas-hud {
      width: 100%;
      height: 100%;
      display: block
    }

    .scanline {
      position: absolute;
      width: 100%;
      height: 2px;
      background: rgba(41, 121, 255, .1);
      top: 0;
      z-index: 10;
      animation: scanHUD 12s linear infinite;
      box-shadow: 0 0 12px rgba(41, 121, 255, .2)
    }

    @keyframes scanHUD {
      from {
        top: -2%
      }

      to {
        top: 102%
      }
    }

    #cursor-outline {
      position: fixed;
      top: 0;
      left: 0;
      z-index: 10000;
      width: 32px;
      height: 32px;
      border: 2px solid #00B0FF;
      border-radius: 50%;
      pointer-events: none;
      opacity: 0;
      transition: opacity .3s, width .3s, height .3s;
      box-shadow: 0 0 15px rgba(0, 176, 255, .4)
    }

    .cursor-hover #cursor-outline {
      width: 48px;
      height: 48px;
      border-color: #00E676;
      background: rgba(0, 230, 118, .1);
      box-shadow: 0 0 20px rgba(0, 230, 118, .5)
    }

    /* ── Top Nav ── */
    .landing-nav {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 1000;
      height: 64px;
      background: rgba(11, 14, 20, .85);
      border-bottom: 1px solid rgba(41, 121, 255, .2);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      transition: background .3s
    }

    .landing-nav.scrolled {
      background: rgba(11, 14, 20, .95)
    }

    .nav-inner {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 2rem;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: space-between
    }

    .brand {
      display: flex;
      align-items: center;
      gap: .5rem;
      font-weight: 800;
      font-size: 1.15rem;
      text-decoration: none;
      color: var(--text)
    }

    .brand-text {
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text
    }

    .nav-links {
      display: flex;
      align-items: center;
      gap: 1.5rem
    }

    .nav-links a {
      color: var(--muted);
      font-size: .9rem;
      font-weight: 500;
      transition: color .2s
    }

    .nav-links a:hover {
      color: var(--text)
    }

    .btn-login {
      padding: .55rem 1.4rem;
      border-radius: 8px;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      color: #fff !important;
      font-weight: 600;
      font-size: .9rem;
      transition: all .3s;
      border: none;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: .4rem
    }

    .btn-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 0 20px rgba(41, 121, 255, .5);
      filter: brightness(1.1)
    }

    /* ── Hero ── */
    .hero {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 6rem 2rem 4rem;
      position: relative
    }

    .hero-content {
      max-width: 800px;
      animation: fadeUp .8s ease-out
    }

    @keyframes fadeUp {
      from {
        opacity: 0;
        transform: translateY(30px)
      }

      to {
        opacity: 1;
        transform: translateY(0)
      }
    }

    .hero-badge {
      display: inline-block;
      padding: .4rem 1rem;
      border-radius: 20px;
      background: rgba(41, 121, 255, .12);
      border: 1px solid rgba(41, 121, 255, .3);
      color: var(--accent);
      font-size: .8rem;
      font-weight: 600;
      letter-spacing: .05em;
      margin-bottom: 1.5rem
    }

    .hero h1 {
      font-size: 3.2rem;
      font-weight: 800;
      line-height: 1.15;
      margin-bottom: 1.2rem
    }

    .hero h1 .gradient {
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text
    }

    .hero p {
      color: var(--muted);
      font-size: 1.15rem;
      line-height: 1.8;
      max-width: 620px;
      margin: 0 auto 2.5rem
    }

    .hero-btns {
      display: flex;
      gap: 1rem;
      justify-content: center;
      flex-wrap: wrap
    }

    .btn-hero {
      padding: .85rem 2rem;
      border-radius: 10px;
      font-weight: 700;
      font-size: 1rem;
      transition: all .3s;
      cursor: pointer;
      border: none;
      text-decoration: none
    }

    .btn-hero.primary {
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      color: #fff;
      box-shadow: 0 4px 20px rgba(41, 121, 255, .3)
    }

    .btn-hero.primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 30px rgba(41, 121, 255, .5)
    }

    .btn-hero.ghost {
      background: rgba(255, 255, 255, .05);
      color: var(--text);
      border: 1px solid var(--border)
    }

    .btn-hero.ghost:hover {
      background: rgba(255, 255, 255, .1);
      border-color: var(--accent)
    }

    /* ── Orb ── */
    .hero-orb {
      width: 100px;
      height: 100px;
      margin: 0 auto 2rem;
      border-radius: 50%;
      border: 3px solid var(--accent);
      position: relative;
      box-shadow: 0 0 30px rgba(41, 121, 255, .3);
      animation: orbPulse 3s ease-in-out infinite
    }

    .hero-orb::after {
      content: '';
      position: absolute;
      inset: 8px;
      border-radius: 50%;
      border: 2px dashed var(--accent2);
      animation: spin 6s linear infinite
    }

    .hero-orb .orb-icon {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2.5rem
    }

    @keyframes orbPulse {

      0%,
      100% {
        box-shadow: 0 0 30px rgba(41, 121, 255, .3)
      }

      50% {
        box-shadow: 0 0 50px rgba(41, 121, 255, .6)
      }
    }

    @keyframes spin {
      to {
        transform: rotate(360deg)
      }
    }

    /* ── Sections ── */
    section {
      padding: 5rem 2rem
    }

    .section-inner {
      max-width: 1100px;
      margin: 0 auto
    }

    .section-title {
      text-align: center;
      margin-bottom: 3rem
    }

    .section-title h2 {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: .5rem
    }

    .section-title p {
      color: var(--muted);
      font-size: 1rem
    }

    /* ── Features ── */
    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 1.5rem
    }

    .feature-card {
      background: var(--card);
      border: 1px solid var(--border2);
      border-radius: 18px;
      padding: 2rem;
      backdrop-filter: blur(12px);
      transition: all .3s;
      position: relative;
      overflow: hidden
    }

    .feature-card:hover {
      border-color: rgba(41, 121, 255, .4);
      transform: translateY(-4px);
      box-shadow: 0 12px 40px rgba(0, 0, 0, .4)
    }

    .feature-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--accent), var(--accent2));
      opacity: 0;
      transition: opacity .3s
    }

    .feature-card:hover::before {
      opacity: 1
    }

    .feature-icon {
      font-size: 2.2rem;
      margin-bottom: 1rem
    }

    .feature-card h3 {
      font-size: 1.1rem;
      margin-bottom: .5rem;
      color: var(--text)
    }

    .feature-card p {
      color: var(--muted);
      font-size: .9rem;
      line-height: 1.6
    }

    /* ── Pricing ── */
    #pricing {
      background: rgba(0, 0, 0, .2)
    }

    .pricing-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 2rem;
      max-width: 1100px;
      margin: 0 auto
    }

    .price-card {
      background: var(--card);
      border: 1px solid var(--border2);
      border-radius: 20px;
      padding: 2.5rem;
      backdrop-filter: blur(12px);
      position: relative;
      transition: all .3s
    }

    .price-card:hover {
      transform: translateY(-4px)
    }

    .price-card.featured {
      border-color: var(--accent);
      box-shadow: 0 0 30px rgba(41, 121, 255, .15)
    }

    .price-card.featured::before {
      content: '推薦';
      position: absolute;
      top: -12px;
      right: 20px;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      color: #fff;
      padding: .3rem 1rem;
      border-radius: 20px;
      font-size: .75rem;
      font-weight: 700
    }

    .price-name {
      font-size: 1.2rem;
      font-weight: 700;
      margin-bottom: .3rem
    }

    .price-tag {
      font-size: 2.8rem;
      font-weight: 800;
      margin: 1rem 0;
      line-height: 1
    }

    .price-tag span {
      font-size: .9rem;
      font-weight: 400;
      color: var(--muted)
    }

    .price-desc {
      color: var(--muted);
      font-size: .88rem;
      margin-bottom: 1.5rem;
      line-height: 1.6
    }

    .price-features {
      list-style: none;
      margin-bottom: 2rem
    }

    .price-features li {
      padding: .5rem 0;
      font-size: .9rem;
      color: var(--text);
      display: flex;
      align-items: center;
      gap: .5rem;
      border-bottom: 1px solid rgba(255, 255, 255, .04)
    }

    .price-features li:last-child {
      border: none
    }

    .check {
      color: var(--accent2)
    }

    .cross {
      color: #ff4757
    }

    .btn-price {
      display: block;
      width: 100%;
      padding: .85rem;
      border-radius: 10px;
      text-align: center;
      font-weight: 700;
      font-size: .95rem;
      transition: all .3s;
      cursor: pointer;
      border: none;
      text-decoration: none
    }

    .btn-price.primary {
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      color: #fff
    }

    .btn-price.primary:hover {
      box-shadow: 0 0 20px rgba(41, 121, 255, .5)
    }

    .btn-price.outline {
      background: transparent;
      color: var(--text);
      border: 1px solid var(--border)
    }

    .btn-price.outline:hover {
      border-color: var(--accent);
      background: rgba(41, 121, 255, .05)
    }

    /* ── FAQ ── */
    .faq-list {
      max-width: 750px;
      margin: 0 auto
    }

    .faq-item {
      border: 1px solid var(--border2);
      border-radius: 14px;
      margin-bottom: .75rem;
      overflow: hidden;
      background: var(--card);
      backdrop-filter: blur(12px);
      transition: border-color .2s
    }

    .faq-item:hover {
      border-color: rgba(41, 121, 255, .3)
    }

    .faq-q {
      padding: 1.2rem 1.5rem;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-weight: 600;
      font-size: .95rem;
      color: var(--text);
      user-select: none
    }

    .faq-q .arrow {
      transition: transform .3s;
      color: var(--accent);
      font-size: 1.2rem
    }

    .faq-item.open .faq-q .arrow {
      transform: rotate(180deg)
    }

    .faq-a {
      max-height: 0;
      overflow: hidden;
      transition: max-height .35s ease, padding .35s ease;
      padding: 0 1.5rem;
      color: var(--muted);
      font-size: .9rem;
      line-height: 1.7
    }

    .faq-item.open .faq-a {
      max-height: 300px;
      padding: 0 1.5rem 1.2rem
    }

    /* ── Footer ── */
    .landing-footer {
      text-align: center;
      padding: 3rem 2rem;
      border-top: 1px solid var(--border2);
      color: var(--muted);
      font-size: .85rem
    }

    /* ── Responsive ── */
    @media(max-width:768px) {
      .hero h1 {
        font-size: 2rem
      }

      .hero p {
        font-size: 1rem
      }

      .pricing-grid {
        grid-template-columns: 1fr
      }

      .nav-links a:not(.btn-login) {
        display: none
      }
    }

    /* ── Animate on scroll ── */
    .aos {
      opacity: 0;
      transform: translateY(25px);
      transition: all .6s ease-out
    }

    .aos.visible {
      opacity: 1;
      transform: translateY(0)
    }
    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }
  </style>
</head>

<body>

  <!-- HUD Background -->
  <div class="hud-bg-wrap"><canvas id="canvas-hud"></canvas></div>
  <div class="hud-overlay-wrap">
    <div class="scanline"></div><canvas id="canvas-top"></canvas>
  </div>
  <div id="cursor-outline"></div>

  <!-- Navigation -->
  <nav class="landing-nav" id="landingNav">
    <div class="nav-inner">
      <a class="brand" href="#">
        <span>🌿</span>
        <span class="brand-text">Eco Trust AI</span>
      </a>
      <div class="nav-links">
        <a href="#features">功能特色</a>
        <a href="#system-architecture">架構分流</a>
        <a href="#pricing">付費方案</a>
        <a href="#faq">常見問題</a>
        <a href="/eco_sys/login.php" class="btn-login">🔐 登入系統</a>
      </div>
    </div>
  </nav>

  <!-- Hero -->
  <section class="hero" id="hero">
    <div class="hero-content">
      <div class="hero-orb"><span class="orb-icon">🌿</span></div>
      <div class="hero-badge">🔬 Powered by FinBERT AI</div>
      <h1>以 AI 驅動的<br><span class="gradient">ESG 永續誠信分析平台</span></h1>
      <p>Eco Trust AI 結合 FinBERT 深度學習模型與自然語言處理技術，為企業提供全面的 ESG 永續報告解析、誠信信心評分及新聞趨勢監測，賦能您的永續投資決策。</p>
      <div class="hero-btns">
        <a href="/eco_sys/login.php" class="btn-hero primary">立即開始使用</a>
        <a href="#features" class="btn-hero ghost">了解更多 ↓</a>
      </div>
    </div>
  </section>

  <!-- Features -->
  <section id="features">
    <div class="section-inner">
      <div class="section-title">
        <h2>🚀 核心功能</h2>
        <p>全方位 ESG 分析解決方案，從報告解析到趨勢洞察</p>
      </div>
      <div class="features-grid">
        <div class="feature-card aos">
          <div class="feature-icon">🧠</div>
          <h3>FinBERT AI 深度分析</h3>
          <p>基於金融領域預訓練的 BERT 模型，精準解析 ESG 報告中的永續承諾與關鍵指標。</p>
        </div>
        <div class="feature-card aos">
          <div class="feature-icon">📊</div>
          <h3>ESG 誠信信心評分</h3>
          <p>自動計算企業永續報告的誠信信心分數，量化評估 ESG 承諾的可信度與一致性。</p>
        </div>
        <div class="feature-card aos">
          <div class="feature-icon">📰</div>
          <h3>即時新聞監察</h3>
          <p>整合新聞 NLP 分析，即時追蹤與企業 ESG 相關的新聞動態與市場趨勢。</p>
        </div>
        <div class="feature-card aos">
          <div class="feature-icon">🫧</div>
          <h3>ESG 走勢氣泡分析</h3>
          <p>透過動態氣泡圖視覺化企業 ESG 表現趨勢，結合 ROE 財務指標進行多維度交叉分析。</p>
        </div>
        <div class="feature-card aos">
          <div class="feature-icon">🛡️</div>
          <h3>報告真實性驗證</h3>
          <p>AI 自動驗證永續報告的數據一致性與承諾落實度，為投資決策提供更透明的參考依據。</p>
        </div>
        <div class="feature-card aos">
          <div class="feature-icon">📈</div>
          <h3>數據管理中心</h3>
          <p>集中管理與比對歷年 ESG 數據，支援 PDF 報告一鍵上傳，系統自動提取關鍵指標。</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Flowchart Visualizer CTA Section -->
  <section id="system-architecture" style="background: rgba(41, 121, 255, 0.01); border-top: 1px solid rgba(41, 121, 255, 0.08); border-bottom: 1px solid rgba(41, 121, 255, 0.08);">
    <div class="section-inner">
      <div class="section-title">
        <h2>⚡ 系統架構與分流決策</h2>
        <p>實時探索 Eco Trust AI 的端到端數據管道與智慧代理運作邏輯</p>
      </div>
      
      <div style="max-width: 800px; margin: 0 auto; background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 3rem 2rem; text-align: center; backdrop-filter: blur(12px); box-shadow: 0 10px 30px rgba(0,0,0,0.4), inset 0 0 20px rgba(41, 121, 255, 0.05); position: relative; overflow: hidden;" class="aos">
        <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(41, 121, 255, 0.15) 0%, transparent 70%); pointer-events: none;"></div>
        <div style="position: absolute; bottom: -50px; left: -50px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(0, 230, 118, 0.1) 0%, transparent 70%); pointer-events: none;"></div>
        
        <div style="font-size: 3.5rem; margin-bottom: 1.5rem; filter: drop-shadow(0 0 15px rgba(41, 121, 255, 0.4)); display: inline-block; animation: float 3s ease-in-out infinite;">⚙️</div>
        
        <h3 style="font-size: 1.4rem; color: var(--text); margin-bottom: 1rem; font-weight: 700;">互動式系統架構與流程模擬器</h3>
        <p style="color: var(--muted); font-size: 0.95rem; line-height: 1.7; max-width: 600px; margin: 0 auto 2rem;">
          我們將系統底層精密的資料管道（包含 PDF 上傳、FinBERT 信心評分、Gen-2 承諾指標提取、背景爬蟲）與 AI 智能顧問的「精確問答（Fast SQL Path）及抽象決策（Agent ReAct Path）」分流邏輯整合為一頁式視覺化流程圖。點擊下方按鈕啟動實時路徑模擬與 Console 控制台監控。
        </p>
        
        <a href="/eco_sys/chatbot_mcp_flow.html" class="btn-hero primary" style="padding: 1.1rem 2.8rem; font-size: 1.05rem; display: inline-flex; align-items: center; gap: 0.8rem; border-radius: 12px; box-shadow: 0 4px 20px rgba(41, 121, 255, 0.4); text-transform: uppercase; letter-spacing: 0.5px;">
          <span>⚡ 啟動互動式流程模擬器</span>
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transition: transform 0.3s;"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
        </a>
      </div>
    </div>
  </section>

  <!-- Pricing -->
  <section id="pricing">
    <div class="section-inner">
      <div class="section-title">
        <h2>💎 付費方案</h2>
        <p>選擇最適合您需求的方案，解鎖 ESG 分析的完整潛能</p>
      </div>
      <div class="pricing-grid">
        <!-- Free -->
        <div class="price-card aos">
          <div class="price-name" style="color:var(--text)">Free 體驗版</div>
          <div class="price-tag">NT$0<span> /月</span></div>
          <div class="price-desc">適合一般使用者，快速瀏覽現有 ESG 分析數據與趨勢。</div>
          <ul class="price-features">
            <li><span class="check">✓</span> ESG 核心儀表板瀏覽</li>
            <li><span class="check">✓</span> ESG 走勢氣泡分析</li>
            <li><span class="check">✓</span> 即時新聞監察</li>
            <li><span class="cross">✕</span> <s style="color:var(--muted)">上傳 ESG 永續報告</s></li>
            <li><span class="cross">✕</span> <s style="color:var(--muted)">FinBERT AI 自動評分</s></li>
            <li><span class="cross">✕</span> <s style="color:var(--muted)">AI 智能顧問 (Chat Bot)</s></li>
          </ul>
          <a href="/eco_sys/login.php" class="btn-price outline">免費註冊</a>
        </div>
        <!-- Plus -->
        <div class="price-card aos">
          <div class="price-name" style="color:var(--accent)">Plus 基礎版</div>
          <div class="price-tag">NT$299<span> /月</span></div>
          <div class="price-desc">適合個人投資者或研究者，解鎖 ESG 報告上傳與 AI 解析功能。</div>
          <ul class="price-features">
            <li><span class="check">✓</span> Free 所有功能</li>
            <li><span class="check">✓</span> <strong style="color:var(--accent)">上傳 ESG 永續報告 (PDF)</strong></li>
            <li><span class="check">✓</span> <strong style="color:var(--accent)">FinBERT AI 自動評分</strong></li>
            <li><span class="cross">✕</span> <s style="color:var(--muted)">ESG 文件深度分析</s></li>
            <li><span class="cross">✕</span> <s style="color:var(--muted)">AI 智能顧問 (Chat Bot)</s></li>
            <li><span class="cross">✕</span> <s style="color:var(--muted)">優先技術支援</s></li>
          </ul>
          <a href="/eco_sys/login.php" class="btn-price outline"
            style="border-color:var(--accent);color:var(--accent);">選擇 Plus</a>
        </div>
        <!-- Pro -->
        <div class="price-card featured aos">
          <div class="price-name" style="color:var(--accent2)">Pro 專業版</div>
          <div class="price-tag">NT$899<span> /月</span></div>
          <div class="price-desc">適合專業分析師與企業用戶，完整解鎖 AI 顧問與深度分析。</div>
          <ul class="price-features">
            <li><span class="check">✓</span> Plus 所有功能</li>
            <li><span class="check">✓</span> <strong style="color:var(--accent2)">AI 智能顧問 (Chat Bot)</strong></li>
            <li><span class="check">✓</span> <strong style="color:var(--accent2)">ESG 文件深度分析</strong></li>
            <li><span class="check">✓</span> 報告真實性驗證引擎</li>
            <li><span class="check">✓</span> 數據 management 與匯出</li>
            <li><span class="check">✓</span> 優先技術支援</li>
          </ul>
          <a href="/eco_sys/login.php" class="btn-price primary">選擇 Pro — 推薦</a>
        </div>
      </div>
    </div>
  </section>

  <!-- FAQ -->
  <section id="faq">
    <div class="section-inner">
      <div class="section-title">
        <h2>❓ 常見問題</h2>
        <p>關於 Eco Trust AI 的常見疑問與核心架構解析</p>
      </div>
      <div class="faq-list">
        <div class="faq-item">
          <div class="faq-q">1. Eco Trust AI 的 ESG 分析核心架構與底層技術是什麼？<span class="arrow">▼</span></div>
          <div class="faq-a">我們的系統採用多層次技術架構：
            <br>• <b>分析引擎</b>：核心基於 <strong>FinBERT</strong> (金融預訓練 BERT 模型，使用 <code>yiyanghkust/finbert-esg</code>) 對 ESG 報告中的承諾語句進行情感與具體性分類。
            <br>• <b>數據處理</b>：後端採用 Python 指令腳本進行 PDF 文字提取（使用 <code>pdfplumber</code>）與中文分詞（使用 <code>jieba</code> 精確模式），精準抽取關鍵詞與實質指標。
            <br>• <b>儲存與檢索</b>：使用 MySQL 儲存結構化指標與分析數據，並使用 Markdown 格式快照供向量 RAG 知識庫做局部檢索。
            <br>• <b>前端視覺化</b>：整合 Plotly.js 繪製動態 ESG 看板、走勢氣泡圖以及企業財務績效的交叉分析圖表。
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q">2. 智能顧問的「可篩選資料庫功能」是如何運作的？它在實際使用上有什麼優勢？<span class="arrow">▼</span></div>
          <div class="faq-a">在對話界面中，用戶可以透過篩選面板選定特定<b>公司</b>（如 <code>1101_台泥</code>、<code>2330_台積電</code>）與<b>年份</b>（如 <code>2023</code>、<code>2024</code>），或選擇跨公司對比。
            <br><br>
            <b>三大核心優勢：</b>
            <br>1. <b>精準度</b>：確保 AI 只從使用者指定範圍內的永續報告書及數據中尋找答案，不會張冠李戴或跨公司混淆。
            <br>2. <b>消除幻覺</b>：將知識檢索範圍進行硬性過濾，從根本上杜絕大語言模型 (LLM) 在大範圍語料中容易產生的事實幻覺。
            <br>3. <b>節省資源與效率</b>：僅讀取關聯文檔與指標，大幅降低 LLM 上下文 Token 消耗，並將回應速度提升 50% 以上。
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q">3. 當使用者提問時，系統如何區分「精確問題」與「抽象問題」並進行分流處理？<span class="arrow">▼</span></div>
          <div class="faq-a">當用戶在 Chatbot 輸入問題時，系統後端路由器（位於 <code>api/chat_api.php</code>）會對問題進行意圖與抽象度分類：
            <br>• <b>精確路徑 (Fast Path)</b>：如果問題屬於「信心得分是多少？」或「2023年台泥的碳排放量是多少？」等具體數據查詢，系統會直接將問題翻譯為結構化 SQL 語句查詢數據庫，並以毫秒級速度返回精準數據與圖表。
            <br>• <b>智慧代理路徑 (Agent ReAct Path)</b>：如果問題屬於「這家公司今年的 ESG 表現值得投資嗎？」等高度抽象或需要推論的問題，系統會啟動 AI Agent 代理。AI 會利用 <b>ReAct (Reasoning + Acting)</b> 思考框架規劃任務步驟，自主決定呼叫哪一個工具（例如 SQL 查詢工具、本地 RAG 檢索工具、或新聞輿情分析工具），並將多個管道獲取的數據融會貫通，最後寫出邏輯嚴密的評估報告。
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q">4. 當我上傳 PDF 永續報告書後，系統在後端進行了哪些資料管道處理？<span class="arrow">▼</span></div>
          <div class="faq-a">PDF 上傳到 <code>api/upload_pdf.php</code> 後，系統會進行一系列嚴密的自動化處理：
            <br>1. <b>元數據校驗與排重</b>：解析檔案名稱（例如 <code>1101_台泥_2023.pdf</code>）提取股票代號與年份，並檢查資料庫是否已存在該記錄。
            <br>2. <b>預檢防禦網 (Pre-check Gate)</b>：讀取 PDF 內容，過濾字數並檢查關鍵詞密度。若 ESG 核心哨兵詞彙（如「環境、永續、減碳」）命中過低，則判定為非 ESG 報告並退回，防止垃圾文件污染。
            <br>3. <b>文字清洗與採樣</b>：將 PDF 內容進行段落分句，並隨機抽取 100 句核心承諾句。
            <br>4. <b>FinBERT 模型推理</b>：調用預訓練 FinBERT 對抽樣句逐句進行 ESG 情感分類（正面/負面/中立）與意圖判定。
            <br>5. <b>指標量化與信心評分</b>：計算報告中的數據密度、KPI 提及率及承諾強度，將各項權重指標經由 <b>Sigmoid 壓縮函數</b>進行歸一化處理，拉開不同誠信度企業的得分差距，產出「誠信信心評分」。
            <br>6. <b>Gen-2 承諾提取</b>：運用正則表達式與語意定位挖掘「碳中和、控溫」等具體目標，判斷是否有時間表與量化數據，劃分信賴等級，最後與原始文字一併存入資料庫。
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q">5. 新聞輿情抓取與情緒加權功能是如何實現的？對分析有何幫助？<span class="arrow">▼</span></div>
          <div class="faq-a">
            • <b>非同步抓取</b>：在 PDF 上傳成功並完成基礎分析後，後端會利用非同步指令，在背景觸發 <code>background_fetch_news.php</code> 網頁爬蟲，抓取該企業於該報告年份的相關網路新聞。
            <br>• <b>情緒分析</b>：使用 NLP 情感分析模型對抓取的新聞進行正面、中立與負面的極性分類，計算出該企業的「新聞輿情指數」。
            <br>• <b>實時加權</b>：在「ESG 看板」中，我們提供了<b>新聞加權開關</b>。啟用後，Plotly 圖表會將企業的「FinBERT 誠信信心評分」與「新聞輿情指數」進行動態加權運算，結合外界輿論反映出企業是否有「言行不一」的情形，提供最客觀的 ESG 誠信度評分。
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q">6. 什麼是 Gen-2 承諾指標？系統如何評估企業是否在誠信風險？<span class="arrow">▼</span></div>
          <div class="faq-a">Gen-2 承諾指標專門用於挖掘企業在報告書中提出的「具體承諾強度」。系統會針對「碳中和、減碳比例、再生能源」等核心承諾句進行以下檢驗：
            <br>1. <b>時間明確度</b>：是否有明確的目標年份（如 2030 年、2050 年）。
            <br>2. <b>量化程度</b>：是否有明確的比例或數值（如 減碳 30%、使用 100% 綠電）。
            <br>3. <b>信賴等級劃分</b>：如果承諾有時間且有數據，評為 <b>Grade A (高信賴度)</b>；若僅有數據無時間，評為 <b>Grade B</b>；若僅有空泛口號，評為 <b>Grade C</b>。
            <br>透過將 these Grade 結合數據指標密度與 FinBERT 評分進行綜合計算，系統能迅速識別空洞的綠色口號，有效警示潛在的誠信風險。
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q">7. 平台的免費體驗版、Plus 基礎版與 Pro 專業版方案在功能上有什麼具體差異？<span class="arrow">▼</span></div>
          <div class="faq-a">
            • <b>Free 體驗版 (NT$0/月)</b>：免費用戶可瀏覽系統已分析完畢的歷史 ESG 數據看板、Plotly 氣泡分析圖及即時新聞監察。
            <br>• <b>Plus 基礎版 (NT$299/月)</b>：適合個人投資者或獨立研究員。解鎖 <b>PDF 永續報告上傳</b>、<b>後端 FinBERT AI 自動化評分</b>與<b>指標抽取</b>。
            <br>• <b>Pro 專業版 (NT$899/月)</b>：解鎖完整功能。包括 <b>AI 智能顧問 (Chatbot)對話</b>（支援精確與抽象分流、RAG 與 Agent 代理模式）、<b>報告真實性驗證引擎</b>、<b>數據交叉管理與匯出</b>，並享有最優先的 GPU 伺服器分析與專屬技術支援。
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q">8. 系統是如何確保數據庫查詢（SQL Path）與 RAG 檢索（RAG Path）的準確性？<span class="arrow">▼</span></div>
          <div class="faq-a">
            • <b>精準 SQL 對照</b>：系統對常見指標（如碳排放量、水資源消耗、董事會多元性等）建立了標準的數據 Schema。AI 的 SQL 查詢工具會直接對照數據庫表格，精確抓取已提取並經過結構化清洗的數據，保證數值無誤。
            <br>• <b>高關聯度 RAG 塊檢索</b>：報告書上傳後會被切分為段落，並轉為 Markdown 檔案。當 AI 執行 RAG 工具時，會根據用戶提問進行語意比對，只抽取最相關的段落送給 LLM。配合用戶在前端選擇的公司與年份進行硬性過濾，完美實現「所答即所問」。
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q">9. 上傳報告與系統使用上有什麼限制或規範？<span class="arrow">▼</span></div>
          <div class="faq-a">
            • <b>格式限制</b>：目前僅支援標準 PDF 格式的永續報告書，檔案大小建議在 50MB 以內。
            <br>• <b>命名規範</b>：建議使用「<code>股票代號_公司名稱_年份.pdf</code>」（如 <code>1101_台泥_2023.pdf</code>）命名。如果格式不符，系統仍會引導您在介面上手動選擇與校對公司代碼與年份。
            <br>• <b>預檢機制</b>：報告書必須包含實質 ESG 相關內容。若上傳無關的財務報表或產品說明書，系統的預檢防禦網會自動退回。
          </div>
        </div>
        <div class="faq-item">
          <div class="faq-q">10. 我的上傳報告與對話紀錄的安全性如何保障？<span class="arrow">▼</span></div>
          <div class="faq-a">我們非常重視您的數據隱私與商業機密安全：
            <br>• <b>帳戶隔離</b>：所有用戶的上傳記錄與對話歷史均與其帳號 Session 綁定，其他用戶無法跨權限存取。
            <br>• <b>本地化運行</b>：我們的 FinBERT 推理與大語言模型（如 Qwen-2.5-7B）皆部署於本地伺服器運行，報告書內容與對話紀錄絕不會上傳給外部公有雲 API，確保您的敏感資料完全留存在受控環境中。
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="landing-footer">
    <p>© 2026 Eco Trust AI — ESG 永續誠信分析平台 · Powered by FinBERT</p>
  </footer>

  <!-- HUD Script -->
  <script src="/eco_sys/assets/js/hud-bg.js"></script>
  <script>
    // Nav scroll effect
    window.addEventListener('scroll', () => {
      document.getElementById('landingNav').classList.toggle('scrolled', window.scrollY > 50);
    });

    // FAQ Toggle
    document.querySelectorAll('.faq-q').forEach(q => {
      q.addEventListener('click', () => {
        const item = q.parentElement;
        const wasOpen = item.classList.contains('open');
        document.querySelectorAll('.faq-item.open').forEach(i => i.classList.remove('open'));
        if (!wasOpen) item.classList.add('open');
      });
    });

    // Animate on scroll
    const obs = new IntersectionObserver((entries) => {
      entries.forEach((e, i) => {
        if (e.isIntersecting) {
          setTimeout(() => e.target.classList.add('visible'), i * 100);
          obs.unobserve(e.target);
        }
      });
    }, { threshold: 0.15 });
    document.querySelectorAll('.aos').forEach(el => obs.observe(el));

    // Smooth scroll for nav links
    document.querySelectorAll('a[href^="#"]').forEach(a => {
      a.addEventListener('click', e => {
        e.preventDefault();
        const t = document.querySelector(a.getAttribute('href'));
        if (t) t.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  </script>
</body>

</html>