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
  <meta name="description" content="Eco Trust AI 以 FinBERT 深度學習與頁碼感知 RAG 技術驅動的 ESG 永續誠信分析平台。">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Noto+Sans+TC:wght@300;400;500;700;900&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #050505;
      --surface: rgba(255,255,255,.04);
      --surface2: rgba(255,255,255,.07);
      --text: #f5f5f7;
      --text2: rgba(255,255,255,.65);
      --text3: rgba(255,255,255,.35);
      --blue: #2997ff;
      --purple: #bf5af2;
      --green: #30d158;
      --teal: #64d2ff;
      --orange: #ff9f0a;
      --pink: #ff375f;
      --border: rgba(255,255,255,.08);
      --border2: rgba(255,255,255,.14);
      --radius: 20px;
      --font: 'Inter','Noto Sans TC',-apple-system,BlinkMacSystemFont,sans-serif;
      --ease: cubic-bezier(.25,.1,.25,1);
    }

    *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
    html { scroll-behavior: smooth; }

    body {
      background: var(--bg);
      color: var(--text);
      font-family: var(--font);
      font-size: 16px;
      line-height: 1.6;
      overflow-x: hidden;
      -webkit-font-smoothing: antialiased;
    }

    a { color: inherit; text-decoration: none; }

    /* ═══ NAV ═══ */
    .nav {
      position: fixed; top: 0; left: 0; right: 0; z-index: 100;
      height: 48px;
      background: rgba(5,5,5,.7);
      backdrop-filter: saturate(180%) blur(20px);
      -webkit-backdrop-filter: saturate(180%) blur(20px);
      border-bottom: 1px solid rgba(255,255,255,.05);
      transition: background .4s;
    }
    .nav.scrolled { background: rgba(5,5,5,.92); }
    .nav-inner {
      max-width: 1080px; margin: 0 auto; padding: 0 22px;
      height: 100%; display: flex; align-items: center; justify-content: space-between;
    }
    .nav-brand { display: flex; align-items: center; gap: .5rem; font-weight: 600; font-size: .95rem; }
    .nav-brand-dot {
      width: 24px; height: 24px;
      background: linear-gradient(135deg, var(--blue), var(--green));
      border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: .7rem;
    }
    .nav-links { display: flex; align-items: center; gap: 1.8rem; }
    .nav-links a { font-size: .8rem; color: var(--text2); transition: color .2s; }
    .nav-links a:hover { color: var(--text); }
    .nav-cta {
      padding: .35rem 1rem; border-radius: 980px; background: var(--blue);
      color: #fff !important; font-weight: 500; font-size: .8rem; transition: all .2s var(--ease);
    }
    .nav-cta:hover { background: #1a8aff; transform: scale(1.04); }

    /* ═══ HERO ═══ */
    .hero {
      position: relative; min-height: 100vh;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      text-align: center; padding: 7rem 2rem 5rem; overflow: hidden;
    }
    .hero::before {
      content: ''; position: absolute; top: 10%; left: 50%; transform: translateX(-50%);
      width: 800px; height: 500px;
      background: radial-gradient(ellipse, rgba(41,151,255,.08), rgba(191,90,242,.04) 40%, transparent 70%);
      pointer-events: none;
      z-index: 1;
    }
    .hero > *:not(.hero-bg-container) { position: relative; z-index: 2; }

    .hero-bg-container {
      position: absolute !important;
      top: 0; left: 0; width: 100%; height: 100%;
      z-index: 0;
      pointer-events: none;
      opacity: 0.95;
      transform: scale(1);
      will-change: opacity, transform;
    }

    .hero-bg-sharp, .hero-bg-blur {
      position: absolute;
      top: 0; left: 0; width: 100%; height: 100%;
      background-image: url('assets/img/hero-bg.png');
      background-size: cover;
      background-position: center;
      pointer-events: none;
    }

    .hero-bg-sharp {
      z-index: 1;
      opacity: 1;
      will-change: opacity;
    }

    .hero-bg-blur {
      z-index: 2;
      opacity: 0;
      filter: blur(25px);
      transform: scale(1.06); /* Prevent edge bleeding from blur */
      will-change: opacity;
    }

    .hero-bg-overlay {
      position: absolute !important;
      top: 0; left: 0; width: 100%; height: 100%;
      background: linear-gradient(to bottom, rgba(5,5,5,0.6) 0%, rgba(5,5,5,0.3) 40%, rgba(5,5,5,0.8) 80%, #050505 100%);
      z-index: 3;
      pointer-events: none;
    }
    .hero-chip {
      display: inline-flex; align-items: center; gap: .45rem;
      padding: .35rem .9rem; border-radius: 980px;
      border: 1px solid rgba(255,255,255,.1); background: rgba(255,255,255,.04);
      font-size: .75rem; font-weight: 500; color: var(--text3); letter-spacing: .04em; margin-bottom: 2rem;
    }
    .hero-chip .dot { width: 5px; height: 5px; border-radius: 50%; background: var(--green); box-shadow: 0 0 6px var(--green); }
    .hero h1 {
      font-size: clamp(2.6rem, 7vw, 5.2rem); font-weight: 800;
      line-height: 1.06; letter-spacing: -.045em; margin-bottom: 1.3rem;
    }
    .hero h1 .grad {
      background: linear-gradient(135deg, var(--blue), var(--teal), var(--green));
      -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    }
    .hero-sub {
      font-size: clamp(.95rem, 1.8vw, 1.2rem); color: var(--text2);
      max-width: 580px; line-height: 1.75; font-weight: 300; margin-bottom: 2.5rem;
    }
    .hero-actions { display: flex; gap: .8rem; align-items: center; flex-wrap: wrap; justify-content: center; }
    .btn-p {
      display: inline-flex; align-items: center; gap: .4rem;
      padding: .8rem 1.8rem; border-radius: 980px; background: var(--blue);
      color: #fff; font-weight: 600; font-size: .95rem; border: none; cursor: pointer;
      transition: all .25s var(--ease); box-shadow: 0 2px 16px rgba(41,151,255,.2);
    }
    .btn-p:hover { transform: translateY(-2px); box-shadow: 0 6px 28px rgba(41,151,255,.35); }
    .btn-s {
      display: inline-flex; align-items: center; gap: .35rem;
      padding: .8rem 1.5rem; border-radius: 980px; background: transparent;
      color: var(--blue); font-weight: 500; font-size: .95rem; border: none; cursor: pointer;
    }
    .btn-s:hover { color: var(--teal); }
    .scroll-ind {
      position: absolute; bottom: 2rem; left: 50%;
      transform: translate(-50%, 0);
      display: flex; flex-direction: column; align-items: center; gap: .4rem;
      z-index: 10;
    }
    .scroll-ind span { font-size: .65rem; color: var(--text3); text-transform: uppercase; letter-spacing: .15em; }
    .scroll-pill {
      width: 20px; height: 32px; border: 1.5px solid rgba(255,255,255,.18); border-radius: 12px; position: relative;
    }
    .scroll-pill::after {
      content: ''; position: absolute; top: 5px; left: 50%; transform: translateX(-50%);
      width: 2.5px; height: 7px; border-radius: 2px; background: rgba(255,255,255,.35);
      animation: scrollDot 1.6s ease-in-out infinite;
    }
    @keyframes scrollDot {
      0%,100% { transform: translateX(-50%) translateY(0); opacity:1; }
      60% { transform: translateX(-50%) translateY(10px); opacity:.2; }
    }
    .hero-enter {
      opacity: 0; transform: translateY(25px);
      animation: heroIn .85s var(--ease) forwards;
    }
    .hero-enter-1 { animation-delay: .15s; }
    .hero-enter-2 { animation-delay: .3s; }
    .hero-enter-3 { animation-delay: .45s; }
    .hero-enter-4 { animation-delay: .6s; }

    .scroll-ind-enter {
      opacity: 0;
      transform: translate(-50%, 25px);
      animation: scrollIndIn .85s var(--ease) forwards;
      animation-delay: .75s;
    }
    @keyframes scrollIndIn {
      to { opacity: 1; transform: translate(-50%, 0); }
    }
    @keyframes heroIn { to { opacity: 1; transform: translateY(0); } }

    /* ═══ TIMELINE ═══ */
    .timeline-section {
      position: relative;
      padding: 6rem 2rem 4rem;
    }

    .timeline-header {
      text-align: center;
      margin-bottom: 5rem;
    }

    .timeline-header .label {
      font-size: .72rem; font-weight: 600; letter-spacing: .14em;
      text-transform: uppercase; color: var(--blue); margin-bottom: .6rem;
    }

    .timeline-header h2 {
      font-size: clamp(1.8rem, 4vw, 2.8rem); font-weight: 700;
      letter-spacing: -.035em; line-height: 1.12; margin-bottom: .8rem;
    }

    .timeline-header p {
      font-size: 1.05rem; color: var(--text2); max-width: 520px;
      margin: 0 auto; font-weight: 300; line-height: 1.7;
    }

    .timeline-wrap {
      position: relative;
      max-width: 1200px;
      margin: 0 auto;
    }

    /* Winding S-curve SVG Track */
    .timeline-svg {
      position: absolute;
      top: 0; left: 0; width: 100%; height: 100%;
      pointer-events: none;
      z-index: 1;
    }
    .timeline-svg-track {
      stroke: rgba(255, 255, 255, 0.05);
      stroke-width: 3.5;
      stroke-dasharray: 6 6;
    }
    .timeline-svg-progress {
      stroke: url(#timelineGrad);
      stroke-width: 4;
      stroke-linecap: round;
      will-change: stroke-dashoffset;
    }

    /* Each timeline node */
    .tl-node {
      position: relative;
      display: grid;
      grid-template-columns: 1.15fr 1fr;
      gap: 6.5rem;
      align-items: center;
      margin-bottom: 9.5rem;
      z-index: 2;
    }

    .tl-node:last-child { margin-bottom: 0; }

    /* Node title/dot inline block */
    .tl-header-row {
      display: flex;
      align-items: center;
      gap: 1.4rem;
      margin-bottom: 1.2rem;
    }

    .tl-dot-wrap {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .tl-dot {
      width: 60px; height: 60px;
      border-radius: 18px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.6rem;
      border: 2px solid rgba(255,255,255,.08);
      background: var(--bg);
      transition: all .6s var(--ease);
      position: relative;
      z-index: 2;
    }

    .tl-node.active .tl-dot {
      border-color: var(--node-color, var(--blue));
      box-shadow: 0 0 28px color-mix(in srgb, var(--node-color, var(--blue)) 35%, transparent);
    }

    .tl-step {
      position: absolute;
      top: -28px;
      left: 50%;
      transform: translateX(-50%);
      font-size: .75rem;
      font-weight: 700;
      letter-spacing: .08em;
      color: var(--text3);
      white-space: nowrap;
      transition: color .6s;
    }

    .tl-node.active .tl-step { color: var(--node-color, var(--blue)); }

    /* Content card — alternates left and right */
    .tl-content {
      opacity: 0;
      transition: all .7s var(--ease);
      background: rgba(255, 255, 255, 0.015);
      border: 1px solid rgba(255, 255, 255, 0.03);
      padding: 2.5rem;
      border-radius: 24px;
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
    }

    .tl-node.active .tl-content {
      background: rgba(255, 255, 255, 0.025);
      border-color: rgba(255, 255, 255, 0.06);
    }

    .tl-node:nth-child(odd) .tl-content {
      grid-column: 1;
      grid-row: 1;
      transform: translateX(-40px);
    }

    .tl-node:nth-child(even) .tl-content {
      grid-column: 2;
      grid-row: 1;
      transform: translateX(40px);
    }

    /* Visual panel — opposite side of content */
    .tl-visual {
      opacity: 0;
      transition: all .7s .1s var(--ease);
    }

    .tl-node:nth-child(odd) .tl-visual {
      grid-column: 2;
      grid-row: 1;
      transform: translateX(40px);
    }

    .tl-node:nth-child(even) .tl-visual {
      grid-column: 1;
      grid-row: 1;
      transform: translateX(-40px);
    }

    /* Active state — slide in */
    .tl-node.active .tl-content,
    .tl-node.active .tl-visual {
      opacity: 1;
      transform: translateX(0);
    }

    .tl-content h3 {
      font-size: 1.6rem; font-weight: 700; letter-spacing: -.02em;
      line-height: 1.25; margin-bottom: .6rem;
    }

    .tl-content .sub {
      font-size: .95rem; color: var(--text3); font-weight: 500;
      letter-spacing: .03em; margin-bottom: .5rem;
    }

    .tl-content p {
      font-size: 1.05rem; color: var(--text2); line-height: 1.7; font-weight: 300;
    }

    .tl-content .tags {
      display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1rem;
    }

    /* All tags left aligned */
    .tl-content .tags { justify-content: flex-start; }

    .tl-content .tag {
      padding: .35rem .8rem;
      border-radius: 980px;
      font-size: .8rem;
      font-weight: 500;
      border: 1px solid rgba(255,255,255,.08);
      background: rgba(255,255,255,.03);
      color: var(--text3);
      transition: all .3s;
    }

    .tl-node.active .tag {
      border-color: color-mix(in srgb, var(--node-color, var(--blue)) 30%, transparent);
      color: var(--node-color, var(--blue));
      background: color-mix(in srgb, var(--node-color, var(--blue)) 6%, transparent);
    }

    /* Visual panel card */
    .tl-vcard {
      border-radius: 18px;
      border: 1px solid var(--border);
      overflow: hidden;
      aspect-ratio: 16 / 10;
      position: relative;
      display: flex; align-items: center; justify-content: center;
      transition: border-color .6s;
    }

    .tl-node.active .tl-vcard {
      border-color: color-mix(in srgb, var(--node-color, var(--blue)) 20%, transparent);
    }

    .tl-vcard-inner {
      width: 100%; height: 100%;
      display: flex; align-items: center; justify-content: center;
      position: relative;
      padding: 10px; /* Mockup window frame padding */
    }

    .tl-vimage {
      width: 100%; height: 100%;
      object-fit: contain; /* Prevent screenshots from being cropped */
      border-radius: 12px;
      z-index: 1;
      opacity: 0.75;
      background: rgba(0, 0, 0, 0.25); /* Sleek backdrop for image boundary */
      transition: all .5s var(--ease);
    }

    .tl-node.active .tl-vimage {
      opacity: 1;
    }

    .tl-vcard:hover .tl-vimage {
      transform: scale(1.025);
    }

    .tl-vcard .glow {
      position: absolute; border-radius: 50%; filter: blur(45px); pointer-events: none;
      animation: floatG 6s ease-in-out infinite;
      opacity: 0; transition: opacity .8s;
      z-index: 2;
    }

    .tl-node.active .tl-vcard .glow { opacity: 0.15; }

    @keyframes floatG {
      0%,100% { transform: translate(0,0) scale(1); }
      33% { transform: translate(8px,-12px) scale(1.05); }
      66% { transform: translate(-6px,8px) scale(.95); }
    }

    .tl-vcard .vlabel {
      position: absolute; bottom: 10px; left: 10px;
      background: rgba(0,0,0,.7); backdrop-filter: blur(10px);
      padding: .3rem .7rem; border-radius: 6px;
      font-size: .65rem; color: var(--text2);
      border: 1px solid rgba(255,255,255,.08);
      z-index: 3;
    }

    /* Color presets for each node */
    .tl-node[data-color="blue"]   { --node-color: var(--blue); }
    .tl-node[data-color="green"]  { --node-color: var(--green); }
    .tl-node[data-color="purple"] { --node-color: var(--purple); }
    .tl-node[data-color="teal"]   { --node-color: var(--teal); }
    .tl-node[data-color="orange"] { --node-color: var(--orange); }
    .tl-node[data-color="pink"]   { --node-color: var(--pink); }

    /* Background gradient per card */
    .vbg-1 { background: linear-gradient(160deg, #0a0f1a, #0d1528 50%, #060a12); }
    .vbg-2 { background: linear-gradient(160deg, #050f0a, #0a1a10 50%, #040a06); }
    .vbg-3 { background: linear-gradient(160deg, #0f0a18, #14082a 50%, #0a0612); }
    .vbg-4 { background: linear-gradient(160deg, #0a1218, #081a22 50%, #060e14); }
    .vbg-5 { background: linear-gradient(160deg, #12100a, #1a1408 50%, #0e0c06); }
    .vbg-6 { background: linear-gradient(160deg, #120a10, #1a0814 50%, #0e060a); }
    .vbg-7 { background: linear-gradient(160deg, #0a0a14, #10102a 50%, #06060e); }

    /* ═══ ARCH CTA ═══ */
    .sec { padding: 6rem 2rem; position: relative; }
    .sec-inner { max-width: 1060px; margin: 0 auto; }

    .arch-cta {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 28px; padding: 4rem 3rem; text-align: center;
      position: relative; overflow: hidden; transition: border-color .4s;
    }
    .arch-cta:hover { border-color: var(--border2); }
    .arch-cta::before {
      content: ''; position: absolute; top: -100px; left: 50%; transform: translateX(-50%);
      width: 350px; height: 350px;
      background: radial-gradient(circle, rgba(191,90,242,.08), transparent 70%);
      pointer-events: none;
    }
    .arch-cta > * { position: relative; z-index: 1; }
    .arch-ico {
      width: 64px; height: 64px; border-radius: 18px;
      background: linear-gradient(135deg, rgba(191,90,242,.12), rgba(41,151,255,.12));
      display: flex; align-items: center; justify-content: center;
      font-size: 1.8rem; margin: 0 auto 1.2rem;
    }
    .arch-cta h3 { font-size: 1.5rem; font-weight: 700; letter-spacing: -.02em; margin-bottom: .6rem; }
    .arch-cta p {
      color: var(--text2); font-size: .95rem; max-width: 520px;
      margin: 0 auto 2rem; line-height: 1.7; font-weight: 300;
    }

    .divider {
      height: 1px; max-width: 1060px; margin: 0 auto;
      background: linear-gradient(90deg, transparent, var(--border), transparent);
    }

    /* ═══ PRICING ═══ */
    .pricing-sec { background: rgba(0,0,0,.3); }
    .sec-header { text-align: center; margin-bottom: 4rem; }
    .sec-label { font-size: .72rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--blue); margin-bottom: .6rem; }
    .sec-heading { font-size: clamp(1.8rem,4vw,2.8rem); font-weight: 700; letter-spacing: -.035em; line-height: 1.12; margin-bottom: .8rem; }
    .sec-desc { font-size: 1.05rem; color: var(--text2); max-width: 520px; margin: 0 auto; font-weight: 300; line-height: 1.7; }

    .pricing-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 1.2rem; align-items: start; }
    .price-card {
      background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
      padding: 2.2rem 1.8rem; position: relative; transition: all .4s var(--ease);
    }
    .price-card:hover { border-color: var(--border2); transform: translateY(-6px); }
    .price-card.feat { border-color: rgba(41,151,255,.25); background: rgba(41,151,255,.03); }
    .price-card.feat::before {
      content: ''; position: absolute; inset: -1px; border-radius: var(--radius); padding: 1px;
      background: linear-gradient(135deg, var(--blue), var(--green));
      -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
      mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
      -webkit-mask-composite: xor; mask-composite: exclude; pointer-events: none;
    }
    .price-badge {
      position: absolute; top: -12px; left: 50%; transform: translateX(-50%);
      padding: .3rem 1rem; border-radius: 980px;
      background: linear-gradient(135deg, var(--blue), var(--green));
      color: #fff; font-size: .68rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; white-space: nowrap;
    }
    .price-tier { font-size: .85rem; font-weight: 600; color: var(--text2); margin-bottom: .4rem; }
    .price-amt { font-size: 2.8rem; font-weight: 800; letter-spacing: -.04em; line-height: 1; margin-bottom: .25rem; }
    .price-amt .cur { font-size: 1.2rem; font-weight: 500; vertical-align: super; }
    .price-amt .per { font-size: .85rem; font-weight: 400; color: var(--text3); }
    .price-desc { color: var(--text3); font-size: .85rem; margin-bottom: 1.5rem; line-height: 1.5; }
    .price-list { list-style: none; margin-bottom: 1.8rem; }
    .price-list li { padding: .45rem 0; font-size: .85rem; color: var(--text2); display: flex; align-items: center; gap: .5rem; }
    .ick { width:16px;height:16px;border-radius:50%;background:rgba(48,209,88,.1);color:var(--green);display:flex;align-items:center;justify-content:center;font-size:.6rem;flex-shrink:0; }
    .ilk { width:16px;height:16px;border-radius:50%;background:rgba(255,255,255,.04);color:var(--text3);display:flex;align-items:center;justify-content:center;font-size:.55rem;flex-shrink:0; }
    .dim { color: var(--text3); }
    .btn-pr {
      display: block; width: 100%; padding: .75rem; border-radius: 980px;
      text-align: center; font-weight: 600; font-size: .88rem;
      transition: all .25s var(--ease); cursor: pointer; border: none;
    }
    .btn-pr.filled { background: var(--blue); color: #fff; }
    .btn-pr.filled:hover { background: #1a8aff; box-shadow: 0 4px 18px rgba(41,151,255,.25); }
    .btn-pr.out { background: transparent; color: var(--blue); border: 1px solid rgba(41,151,255,.25); }
    .btn-pr.out:hover { background: rgba(41,151,255,.05); border-color: var(--blue); }

    /* ═══ FAQ ═══ */
    .faq-list { max-width: 700px; margin: 0 auto; }
    .faq-item { border-bottom: 1px solid var(--border); }
    .faq-q {
      padding: 1.3rem 0; cursor: pointer; display: flex; justify-content: space-between;
      align-items: center; font-weight: 500; font-size: .92rem; user-select: none; transition: color .2s;
    }
    .faq-q:hover { color: var(--blue); }
    .faq-q .chv {
      width: 24px; height: 24px; border-radius: 50%; background: rgba(255,255,255,.05);
      display: flex; align-items: center; justify-content: center;
      transition: all .35s var(--ease); flex-shrink: 0; color: var(--text3); font-size: .7rem;
    }
    .faq-item.open .faq-q .chv { transform: rotate(45deg); background: rgba(41,151,255,.1); color: var(--blue); }
    .faq-a {
      max-height: 0; overflow: hidden; transition: max-height .45s var(--ease), padding .45s var(--ease);
      padding: 0; color: var(--text2); font-size: .88rem; line-height: 1.8;
    }
    .faq-item.open .faq-a { max-height: 450px; padding: 0 0 1.4rem; }

    /* ═══ FOOTER ═══ */
    .footer { padding: 3rem 2rem; text-align: center; border-top: 1px solid var(--border); }
    .footer-brand { display: inline-flex; align-items: center; gap: .4rem; font-weight: 600; font-size: .9rem; margin-bottom: .6rem; }
    .footer p { color: var(--text3); font-size: .78rem; }
    .footer-links { display: flex; gap: 1.5rem; justify-content: center; margin-top: .8rem; }
    .footer-links a { color: var(--text3); font-size: .78rem; transition: color .2s; }
    .footer-links a:hover { color: var(--text); }

    /* ═══ Scroll anim helpers ═══ */
    .anim { opacity:0; transform: translateY(40px); transition: opacity .7s var(--ease), transform .7s var(--ease); }
    .anim.show { opacity:1; transform: translateY(0); }
    .anim-scale { opacity:0; transform: scale(.92) translateY(20px); transition: opacity .7s var(--ease), transform .7s var(--ease); }
    .anim-scale.show { opacity:1; transform: scale(1) translateY(0); }
    .stagger-1{transition-delay:.05s}.stagger-2{transition-delay:.12s}.stagger-3{transition-delay:.19s}

    /* ═══ RESPONSIVE ═══ */
    @media (max-width: 900px) {
      .pricing-grid { grid-template-columns: 1fr; max-width: 400px; margin: 0 auto; }
      .timeline-svg { display: none; } /* Hide S-curve on mobile */
      .tl-node {
        grid-template-columns: 1fr !important;
        gap: 1.8rem !important;
        margin-bottom: 4rem !important;
      }
      .tl-content {
        grid-column: 1 !important; grid-row: 1 !important;
        padding: 1.25rem !important;
        transform: translateY(20px) !important;
      }
      .tl-node.active .tl-content { transform: translateY(0) !important; }
      .tl-content .tags { justify-content: flex-start !important; }
      .tl-visual {
        grid-column: 1 !important; grid-row: 2 !important;
        padding: 0 !important; margin-top: 0.5rem;
        transform: translateY(20px) !important;
      }
      .tl-node.active .tl-visual { transform: translateY(0) !important; }
    }
    @media (max-width: 768px) {
      .nav-links a:not(.nav-cta) { display: none; }
      .hero { padding: 6rem 1.2rem 4rem; }
      .sec { padding: 5rem 1.2rem; }
      .timeline-section { padding: 4rem 1rem; }
      .arch-cta { padding: 2.5rem 1.5rem; }
    }
  </style>
</head>

<body>

  <!-- Nav -->
  <nav class="nav" id="mainNav">
    <div class="nav-inner">
      <a class="nav-brand" href="#"><span class="nav-brand-dot">🌿</span><span>Eco Trust AI</span></a>
      <div class="nav-links">
        <a href="#timeline">分析流程</a>
        <a href="#architecture">系統架構</a>
        <a href="#pricing">方案</a>
        <a href="#faq">FAQ</a>
        <a href="/eco_sys/login.php" class="nav-cta">登入平台</a>
      </div>
    </div>
  </nav>

  <!-- ═══ HERO ═══ -->
  <section class="hero" id="hero">
    <div class="hero-bg-container" id="heroBgContainer">
      <div class="hero-bg-sharp"></div>
      <div class="hero-bg-blur"></div>
      <div class="hero-bg-overlay"></div>
    </div>
    <div class="hero-chip hero-enter hero-enter-1"><span class="dot"></span>FinBERT AI · Ollama Qwen2.5 · Page-Aware RAG</div>
    <h1 class="hero-enter hero-enter-2">重新定義<br><span class="grad">ESG 永續誠信分析</span></h1>
    <p class="hero-sub hero-enter hero-enter-3">結合 FinBERT 深度學習、頁碼感知 RAG 檢索與 ReAct 智能代理，<br>為企業提供可溯源、可驗證的永續報告深度分析。</p>
    <div class="hero-actions hero-enter hero-enter-4">
      <a href="/eco_sys/login.php" class="btn-p">開始使用 <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
      <a href="#timeline" class="btn-s">探索分析流程 →</a>
    </div>
    <div class="scroll-ind scroll-ind-enter"><div class="scroll-pill"></div><span>Scroll</span></div>
  </section>

  <!-- ═══ TIMELINE — Linear Pipeline ═══ -->
  <section class="timeline-section" id="timeline">

    <div class="timeline-header anim">
      <div class="label">端到端分析流程</div>
      <h2>從 PDF 上傳到智能洞察</h2>
      <p>一份報告、九個階段、全程自動化 — 跟隨時間軸探索每一步技術細節。</p>
    </div>

    <div class="timeline-wrap" id="timelineWrap">
      <!-- Winding S-curve SVG -->
      <svg class="timeline-svg" id="timelineSvg">
        <defs>
          <linearGradient id="timelineGrad" x1="0%" y1="0%" x2="0%" y2="100%">
            <stop offset="0%" stop-color="var(--blue)" />
            <stop offset="25%" stop-color="var(--teal)" />
            <stop offset="50%" stop-color="var(--green)" />
            <stop offset="75%" stop-color="var(--purple)" />
            <stop offset="100%" stop-color="var(--pink)" />
          </linearGradient>
        </defs>
        <path class="timeline-svg-track" id="svgTrack" d="" fill="none" />
        <path class="timeline-svg-progress" id="svgProgress" fill="none" />
      </svg>

      <!-- ① PDF Upload & Pre-check -->
      <div class="tl-node" data-color="blue">
        <div class="tl-content">
          <div class="tl-header-row">
            <div class="tl-dot-wrap">
              <span class="tl-step">01</span>
              <div class="tl-dot">📄</div>
            </div>
            <div>
              <div class="sub">STEP 01</div>
              <h3>PDF 上傳與預檢防禦</h3>
            </div>
          </div>
          <p>解析檔名提取股票代號與年份，ESG 哨兵詞彙命中率檢測 — 非 ESG 報告自動退回，防止垃圾文件污染數據庫。</p>
          <div class="tags">
            <span class="tag">pdfplumber</span>
            <span class="tag">元數據校驗</span>
            <span class="tag">排重檢測</span>
          </div>
        </div>
        <div class="tl-visual">
          <div class="tl-vcard vbg-1">
            <div class="tl-vcard-inner">
              <div class="glow" style="width:180px;height:180px;background:rgba(41,151,255,.15);top:15%;left:15%;"></div>
              <img src="assets/img/Upload Pre-check Gate.png" alt="Upload Pre-check Gate" class="tl-vimage">
              <div class="vlabel">Upload Pre-check Gate</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ② FinBERT Analysis -->
      <div class="tl-node" data-color="green">
        <div class="tl-content">
          <div class="tl-header-row">
            <div class="tl-dot-wrap">
              <span class="tl-step">02</span>
              <div class="tl-dot">🧠</div>
            </div>
            <div>
              <div class="sub">STEP 02</div>
              <h3>FinBERT 深度情感分析</h3>
            </div>
          </div>
          <p>隨機抽取 100 句核心承諾句，使用金融預訓練 BERT 模型逐句進行 ESG 情感分類（正面/負面/中立）與意圖判定。</p>
          <div class="tags">
            <span class="tag">finbert-esg</span>
            <span class="tag">情感分類</span>
            <span class="tag">承諾句採樣</span>
          </div>
        </div>
        <div class="tl-visual">
          <div class="tl-vcard vbg-2">
            <div class="tl-vcard-inner">
              <div class="glow" style="width:200px;height:200px;background:rgba(48,209,88,.12);top:10%;right:10%;"></div>
              <img src="assets/img/FinBERT ESG Inference.png" alt="FinBERT ESG Inference" class="tl-vimage">
              <div class="vlabel">FinBERT ESG Inference</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ③ Sigmoid Scoring -->
      <div class="tl-node" data-color="teal">
        <div class="tl-content">
          <div class="tl-header-row">
            <div class="tl-dot-wrap">
              <span class="tl-step">03</span>
              <div class="tl-dot">📐</div>
            </div>
            <div>
              <div class="sub">STEP 03</div>
              <h3>Sigmoid 歸一化信心評分</h3>
            </div>
          </div>
          <p>計算數據密度、KPI 提及率及承諾強度，經由 Sigmoid 壓縮函數進行歸一化處理，拉開不同誠信度企業的得分差距。</p>
          <div class="tags">
            <span class="tag">Sigmoid 壓縮</span>
            <span class="tag">KPI 密度</span>
            <span class="tag">信心分數</span>
          </div>
        </div>
        <div class="tl-visual">
          <div class="tl-vcard vbg-4">
            <div class="tl-vcard-inner">
              <div class="glow" style="width:170px;height:170px;background:rgba(100,210,255,.12);bottom:15%;left:20%;"></div>
              <img src="assets/img/Confidence Score Engine.png" alt="Confidence Score Engine" class="tl-vimage">
              <div class="vlabel">Confidence Score Engine</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ④ Gen-2 Commitment -->
      <div class="tl-node" data-color="purple">
        <div class="tl-content">
          <div class="tl-header-row">
            <div class="tl-dot-wrap">
              <span class="tl-step">04</span>
              <div class="tl-dot">🛡️</div>
            </div>
            <div>
              <div class="sub">STEP 04</div>
              <h3>Gen-2 承諾指標提取</h3>
            </div>
          </div>
          <p>正則表達式 + 語意定位挖掘「碳中和、減碳比例、再生能源」等承諾，依時間明確度與量化程度劃分高/中/低三級信賴等級。</p>
          <div class="tags">
            <span class="tag">Regex 提取</span>
            <span class="tag">高/中/低信度</span>
            <span class="tag">語意定位</span>
          </div>
        </div>
        <div class="tl-visual">
          <div class="tl-vcard vbg-3">
            <div class="tl-vcard-inner">
              <div class="glow" style="width:190px;height:190px;background:rgba(191,90,242,.12);top:10%;left:10%;"></div>
              <img src="assets/img/Gen-2 Commitment Extractor.png" alt="Gen-2 Commitment Extractor" class="tl-vimage">
              <div class="vlabel">Gen-2 Commitment Extractor</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ⑤ News Crawling -->
      <div class="tl-node" data-color="orange">
        <div class="tl-content">
          <div class="tl-header-row">
            <div class="tl-dot-wrap">
              <span class="tl-step">05</span>
              <div class="tl-dot">📰</div>
            </div>
            <div>
              <div class="sub">STEP 05</div>
              <h3>非同步新聞輿情監察</h3>
            </div>
          </div>
          <p>背景爬蟲自動抓取企業相關新聞，NLP 情感極性分析計算輿情指數，動態加權融入 ESG 評分體系 — 對比企業「言」與「行」。</p>
          <div class="tags">
            <span class="tag">背景爬蟲</span>
            <span class="tag">NLP 極性分析</span>
            <span class="tag">加權融合</span>
          </div>
        </div>
        <div class="tl-visual">
          <div class="tl-vcard vbg-5">
            <div class="tl-vcard-inner">
              <div class="glow" style="width:160px;height:160px;background:rgba(255,159,10,.1);top:20%;right:15%;"></div>
              <img src="assets/img/Async News Crawler.png" alt="Async News Crawler" class="tl-vimage">
              <div class="vlabel">Async News Crawler</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ⑥ Core Dashboard -->
      <div class="tl-node" data-color="green">
        <div class="tl-content">
          <div class="tl-header-row">
            <div class="tl-dot-wrap">
              <span class="tl-step">06</span>
              <div class="tl-dot">🖥️</div>
            </div>
            <div>
              <div class="sub">STEP 06</div>
              <h3>ESG 企業誠信核心看板</h3>
            </div>
          </div>
          <p>整合企業基本資料、歷史信用走勢、大宗輿情情感指標，並透過互動式長條圖與圓餅圖即時呈現 E、S、G 各維度之細項得分，打造一站式數據決策中心。</p>
          <div class="tags">
            <span class="tag">E-S-G 指標分析</span>
            <span class="tag">歷史誠信走勢</span>
            <span class="tag">數據決策看板</span>
          </div>
        </div>
        <div class="tl-visual">
          <div class="tl-vcard vbg-2">
            <div class="tl-vcard-inner">
              <div class="glow" style="width:200px;height:200px;background:rgba(48,209,88,.12);top:10%;right:10%;"></div>
              <img src="assets/img/核心看板.png" alt="核心看板" class="tl-vimage">
              <div class="vlabel">Core Dashboard</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ⑦ 2D Bubble Chart -->
      <div class="tl-node" data-color="teal">
        <div class="tl-content">
          <div class="tl-header-row">
            <div class="tl-dot-wrap">
              <span class="tl-step">07</span>
              <div class="tl-dot">🫧</div>
            </div>
            <div>
              <div class="sub">STEP 07</div>
              <h3>ESG 誠信與輿情 2D 氣泡圖</h3>
            </div>
          </div>
          <p>橫軸代表企業誠信得分，縱軸為新聞輿情極性，氣泡大小對應承諾句總量；直觀呈現企業是否「言行一致」，快速識別潛在的綠色宣傳風險。</p>
          <div class="tags">
            <span class="tag">2D 氣泡分佈</span>
            <span class="tag">言行一致性對比</span>
            <span class="tag">綠色合規風險</span>
          </div>
        </div>
        <div class="tl-visual">
          <div class="tl-vcard vbg-4">
            <div class="tl-vcard-inner">
              <div class="glow" style="width:170px;height:170px;background:rgba(100,210,255,.12);bottom:15%;left:20%;"></div>
              <img src="assets/img/2d氣泡圖.png" alt="2d氣泡圖" class="tl-vimage">
              <div class="vlabel">2D Bubble Chart</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ⑧ Page-Aware RAG -->
      <div class="tl-node" data-color="blue">
        <div class="tl-content">
          <div class="tl-header-row">
            <div class="tl-dot-wrap">
              <span class="tl-step">08</span>
              <div class="tl-dot">💬</div>
            </div>
            <div>
              <div class="sub">STEP 08</div>
              <h3>頁碼感知 RAG 智能顧問</h3>
            </div>
          </div>
          <p>Bigram 中文分詞對每頁建立索引，提問時擷取最相關頁面段落，附上 [p.X] 標記 — 點擊即可透過 PDF.js 跳轉至原文頁面。</p>
          <div class="tags">
            <span class="tag">Bigram 索引</span>
            <span class="tag">PDF.js 跳轉</span>
            <span class="tag">[p.X] 溯源</span>
          </div>
        </div>
        <div class="tl-visual">
          <div class="tl-vcard vbg-1">
            <div class="tl-vcard-inner">
              <div class="glow" style="width:200px;height:200px;background:rgba(41,151,255,.12);bottom:10%;left:15%;"></div>
              <img src="assets/img/Page-Aware RAG Engine.png" alt="Page-Aware RAG Engine" class="tl-vimage">
              <div class="vlabel">Page-Aware RAG Engine</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ⑨ ReAct Agent -->
      <div class="tl-node" data-color="pink">
        <div class="tl-content">
          <div class="tl-header-row">
            <div class="tl-dot-wrap">
              <span class="tl-step">09</span>
              <div class="tl-dot">🤖</div>
            </div>
            <div>
              <div class="sub">STEP 09</div>
              <h3>ReAct 智能代理分流</h3>
            </div>
          </div>
          <p>自動判斷問題意圖：精確數據查詢走 Fast SQL Path（毫秒級回應）；抽象分析則啟動 ReAct Agent，自主調度 SQL + RAG + 新聞工具鏈。</p>
          <div class="tags">
            <span class="tag">Fast SQL Path</span>
            <span class="tag">Agent ReAct</span>
            <span class="tag">工具鏈調度</span>
          </div>
        </div>
        <div class="tl-visual">
          <div class="tl-vcard vbg-6">
            <div class="tl-vcard-inner">
              <div class="glow" style="width:180px;height:180px;background:rgba(255,55,95,.1);top:15%;right:10%;"></div>
              <img src="assets/img/ReAct Agent Router.png" alt="ReAct Agent Router" class="tl-vimage">
              <div class="vlabel">ReAct Agent Router</div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>

  <div class="divider"></div>

  <!-- ═══ ARCHITECTURE CTA ═══ -->
  <section class="sec" id="architecture">
    <div class="sec-inner">
      <div class="arch-cta anim">
        <div class="arch-ico">⚡</div>
        <h3>互動式系統架構流程模擬器</h3>
        <p>完整可視化核心管道架構流程：從 PDF 上傳到 ReAct 智能分流的端到端運作。點擊啟動即時路徑模擬與 Console 監控。</p>
        <a href="/eco_sys/chatbot_mcp_flow.html" class="btn-p">啟動流程模擬器 <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
      </div>
    </div>
  </section>

  <div class="divider"></div>

  <!-- ═══ PRICING ═══ -->
  <section class="sec pricing-sec" id="pricing">
    <div class="sec-inner">
      <div class="sec-header anim">
        <div class="sec-label">方案</div>
        <h2 class="sec-heading">選擇適合您的方案</h2>
        <p class="sec-desc">從免費瀏覽到完整 AI 智能顧問，逐級解鎖更深層的 ESG 分析能力。</p>
      </div>
      <div class="pricing-grid">
        <div class="price-card anim-scale stagger-1">
          <div class="price-tier">Free 體驗版</div>
          <div class="price-amt"><span class="cur">NT$</span>0<span class="per"> /月</span></div>
          <div class="price-desc">瀏覽現有 ESG 數據與趨勢分析</div>
          <ul class="price-list">
            <li><span class="ick">✓</span> ESG 核心儀表板</li>
            <li><span class="ick">✓</span> ESG 走勢氣泡分析</li>
            <li><span class="ick">✓</span> 即時新聞監察</li>
            <li><span class="ilk">🔒</span> <span class="dim">上傳 ESG 報告</span></li>
            <li><span class="ilk">🔒</span> <span class="dim">FinBERT 自動評分</span></li>
            <li><span class="ilk">🔒</span> <span class="dim">AI 智能顧問</span></li>
          </ul>
          <a href="/eco_sys/login.php" class="btn-pr out">免費註冊</a>
        </div>
        <div class="price-card anim-scale stagger-2">
          <div class="price-tier">Plus 基礎版</div>
          <div class="price-amt"><span class="cur">NT$</span>299<span class="per"> /月</span></div>
          <div class="price-desc">解鎖 PDF 上傳與 AI 自動評分</div>
          <ul class="price-list">
            <li><span class="ick">✓</span> Free 所有功能</li>
            <li><span class="ick">✓</span> <strong>上傳 ESG 報告 (PDF)</strong></li>
            <li><span class="ick">✓</span> <strong>FinBERT AI 自動評分</strong></li>
            <li><span class="ilk">🔒</span> <span class="dim">ESG 文件深度分析</span></li>
            <li><span class="ilk">🔒</span> <span class="dim">AI 智能顧問</span></li>
            <li><span class="ilk">🔒</span> <span class="dim">優先技術支援</span></li>
          </ul>
          <a href="/eco_sys/login.php" class="btn-pr out">選擇 Plus</a>
        </div>
        <div class="price-card feat anim-scale stagger-3">
          <div class="price-badge">推薦方案</div>
          <div class="price-tier">Pro 專業版</div>
          <div class="price-amt"><span class="cur">NT$</span>899<span class="per"> /月</span></div>
          <div class="price-desc">完整 AI 顧問 + 深度分析 + 審計追蹤</div>
          <ul class="price-list">
            <li><span class="ick">✓</span> Plus 所有功能</li>
            <li><span class="ick">✓</span> <strong>AI 智能顧問 (RAG + Agent)</strong></li>
            <li><span class="ick">✓</span> <strong>ESG 文件深度分析</strong></li>
            <li><span class="ick">✓</span> 報告真實性驗證引擎</li>
            <li><span class="ick">✓</span> 數據管理與匯出</li>
            <li><span class="ick">✓</span> 優先技術支援</li>
          </ul>
          <a href="/eco_sys/login.php" class="btn-pr filled">選擇 Pro</a>
        </div>
      </div>
    </div>
  </section>

  <div class="divider"></div>

  <!-- ═══ FAQ ═══ -->
  <section class="sec" id="faq">
    <div class="sec-inner">
      <div class="sec-header anim">
        <div class="sec-label">常見問題</div>
        <h2 class="sec-heading">深入了解技術細節</h2>
        <p class="sec-desc">關於 Eco Trust AI 系統架構與核心技術的常見疑問。</p>
      </div>
      <div class="faq-list">
        <div class="faq-item anim">
          <div class="faq-q">Eco Trust AI 的核心架構與底層技術是什麼？<span class="chv">＋</span></div>
          <div class="faq-a">系統採用多層次技術架構：<b>分析引擎</b>基於 FinBERT 對承諾語句進行情感分類；<b>數據處理</b>使用 Python + pdfplumber + jieba 分詞；<b>儲存</b>使用 MariaDB + Markdown RAG 快照；<b>前端</b>整合 Plotly.js 動態 ESG 看板。</div>
        </div>
        <div class="faq-item anim">
          <div class="faq-q">智能顧問的「可篩選資料庫」如何消除 LLM 幻覺？<span class="chv">＋</span></div>
          <div class="faq-a">用戶選定公司與年份後，系統對知識檢索範圍進行硬性過濾，確保 AI 只從指定報告中檢索答案。同時大幅降低 Token 消耗並提升回應速度 50% 以上。</div>
        </div>
        <div class="faq-item anim">
          <div class="faq-q">Fast SQL Path 與 Agent ReAct Path 的分流依據？<span class="chv">＋</span></div>
          <div class="faq-a">後端路由器對問題進行意圖分類：精確數據查詢（如「碳排放量多少」）直接翻譯為 SQL；抽象推論（如「是否值得投資」）啟動 ReAct Agent 自主調度多工具鏈。</div>
        </div>
        <div class="faq-item anim">
          <div class="faq-q">上傳報告有什麼限制？<span class="chv">＋</span></div>
          <div class="faq-a">僅支援 PDF（建議 50MB 以內），建議命名格式「股票代號_公司名稱_年份.pdf」。系統預檢機制會自動退回非 ESG 報告。</div>
        </div>
        <div class="faq-item anim">
          <div class="faq-q">數據安全如何保障？<span class="chv">＋</span></div>
          <div class="faq-a">所有模型（FinBERT + Qwen-2.5-7B）皆部署於本地伺服器，報告與對話紀錄絕不上傳外部雲端。帳戶 Session 隔離確保跨權限存取不可能發生。</div>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <div class="footer-brand"><span>🌿</span><span>Eco Trust AI</span></div>
    <p>© 2026 Eco Trust AI — ESG 永續誠信分析平台 · Powered by FinBERT</p>
    <div class="footer-links">
      <a href="/eco_sys/login.php">登入</a>
      <a href="#timeline">流程</a>
      <a href="#pricing">方案</a>
      <a href="#faq">FAQ</a>
    </div>
  </footer>

  <!-- ═══ SCRIPT ═══ -->
  <script>
  (function() {
    'use strict';

    /* ── Nav scroll ── */
    const nav = document.getElementById('mainNav');
    window.addEventListener('scroll', () => {
      nav.classList.toggle('scrolled', window.scrollY > 30);
    }, { passive: true });

    /* ── Generic scroll reveal ── */
    const animEls = document.querySelectorAll('.anim, .anim-scale');
    const revealObs = new IntersectionObserver(entries => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          e.target.classList.add('show');
          revealObs.unobserve(e.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });
    animEls.forEach(el => revealObs.observe(el));

    /* ── Hero parallax ── */
    const hero = document.querySelector('.hero');
    const heroBgContainer = document.getElementById('heroBgContainer');
    const sharpLayer = document.querySelector('.hero-bg-sharp');
    const blurLayer = document.querySelector('.hero-bg-blur');
    const scrollInd = document.querySelector('.scroll-ind');
    const heroEls = hero.querySelectorAll('.hero-enter');
    
    window.addEventListener('scroll', () => {
      const y = window.scrollY;
      const hH = hero.offsetHeight || 800;
      if (y > hH + 50) return;
      const p = Math.min(1, Math.max(0, y / hH));
      
      // Text elements parallax (fade & vertical movement)
      const op = Math.max(0, 1 - p * 1.5);
      const sc = Math.max(.9, 1 - p * .08);
      const ty = y * .3;
      heroEls.forEach(el => {
        el.style.opacity = op;
        el.style.transform = `translateY(${ty}px) scale(${sc})`;
      });

      // Background container animation (GPU-accelerated opacity & transform)
      if (heroBgContainer) {
        const bgY = y * 0.15;
        const bgScale = 1 + p * 0.05;
        const bgOp = Math.max(0, 1 - p * 1.25);
        heroBgContainer.style.opacity = bgOp;
        heroBgContainer.style.transform = `translateY(${bgY}px) scale(${bgScale})`;
      }

      // Layered blur crossfade (No dynamically recalculated filter on scroll = extremely smooth!)
      if (sharpLayer && blurLayer) {
        const blurProgress = Math.min(1, p * 2.0); // Reach full blur at 50% scroll
        blurLayer.style.opacity = blurProgress;
        sharpLayer.style.opacity = 1 - blurProgress;
      }

      // Scroll indicator fade & parallax (stays centered horizontally)
      if (scrollInd) {
        const scrollIndOp = Math.max(0, 1 - y / 120);
        scrollInd.style.opacity = scrollIndOp;
        scrollInd.style.transform = `translate(-50%, ${y * 0.25}px)`;
      }
    }, { passive: true });

    /* ── TIMELINE — scroll-driven progress & node activation ── */
    const tlWrap = document.getElementById('timelineWrap');
    const tlNodes = document.querySelectorAll('.tl-node');

    function updateTimeline() {
      if (!tlWrap) return;

      const wrapRect = tlWrap.getBoundingClientRect();
      const wrapTop = wrapRect.top + window.scrollY;
      const wrapH = tlWrap.offsetHeight;

      // How far we've scrolled into the timeline section
      const scrollInto = window.scrollY + window.innerHeight * 0.5 - wrapTop;
      const progress = Math.max(0, Math.min(1, scrollInto / wrapH));

      // 1. Redraw and animate S-curve SVG dynamically
      const svg = document.getElementById('timelineSvg');
      const track = document.getElementById('svgTrack');
      const progressPath = document.getElementById('svgProgress');
      const dots = document.querySelectorAll('.tl-dot');

      if (svg && track && progressPath && dots.length > 0) {
        let points = [];
        
        // Start point at top center
        points.push({ x: wrapRect.width / 2, y: 0 });

        // Grab current coordinates of each dot relative to the wrapper
        dots.forEach(dot => {
          const dotRect = dot.getBoundingClientRect();
          const x = dotRect.left + dotRect.width / 2 - wrapRect.left;
          const y = dotRect.top + dotRect.height / 2 - wrapRect.top;
          points.push({ x, y });
        });

        // End point at bottom center
        points.push({ x: wrapRect.width / 2, y: wrapRect.height });

        // Build S-curve bezier path string
        let d = `M ${points[0].x} ${points[0].y}`;
        for (let i = 0; i < points.length - 1; i++) {
          const p0 = points[i];
          const p1 = points[i + 1];
          // Use cubic bezier control points for smooth serpentine flow
          const cpY1 = p0.y + (p1.y - p0.y) * 0.5;
          const cpY2 = cpY1;
          d += ` C ${p0.x} ${cpY1}, ${p1.x} ${cpY2}, ${p1.x} ${p1.y}`;
        }

        track.setAttribute('d', d);
        progressPath.setAttribute('d', d);

        // Animate the progress path along the S-curve
        const pathLength = progressPath.getTotalLength();
        progressPath.style.strokeDasharray = pathLength;
        progressPath.style.strokeDashoffset = pathLength * (1 - progress);
      }

      // 2. Activate nodes
      tlNodes.forEach(node => {
        const nodeRect = node.getBoundingClientRect();
        const nodeMid = nodeRect.top + nodeRect.height * 0.3;
        if (nodeMid < window.innerHeight * 0.7) {
          node.classList.add('active');
        }
      });
    }

    window.addEventListener('scroll', updateTimeline, { passive: true });
    window.addEventListener('resize', updateTimeline, { passive: true });
    // Initial check
    setTimeout(updateTimeline, 100);

    /* ── FAQ toggle ── */
    document.querySelectorAll('.faq-q').forEach(q => {
      q.addEventListener('click', () => {
        const item = q.parentElement;
        const wasOpen = item.classList.contains('open');
        document.querySelectorAll('.faq-item.open').forEach(i => i.classList.remove('open'));
        if (!wasOpen) item.classList.add('open');
      });
    });

    /* ── Smooth scroll ── */
    document.querySelectorAll('a[href^="#"]').forEach(a => {
      a.addEventListener('click', e => {
        e.preventDefault();
        const t = document.querySelector(a.getAttribute('href'));
        if (t) {
          const top = t.getBoundingClientRect().top + window.pageYOffset - 56;
          window.scrollTo({ top, behavior: 'smooth' });
        }
      });
    });

  })();
  </script>
</body>

</html>