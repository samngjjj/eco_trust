<?php
// includes/header.php — Shared nav header
// Requires: $currentUser, $currentRole, $isAdmin (from auth_check.php)
// Usage: define $activePage before including, e.g. $activePage = 'index';
$activePage = $activePage ?? '';
$pages = [
  'index' => ['icon' => '🏠', 'label' => '數據管理中心', 'href' => '/eco_sys/index.php'],
  'news' => ['icon' => '📰', 'label' => '新聞監察看板', 'href' => '/eco_sys/news.php'],
  'dashboard' => ['icon' => '📊', 'label' => 'ESG 核心看板', 'href' => '/eco_sys/esg_dashboard.php'],
  'timecube' => ['icon' => '🫧', 'label' => 'ESG 走勢氣泡分析', 'href' => '/eco_sys/timecube_analysis.php']
];

if (isset($isPro) && $isPro) {
  $pages['chat'] = ['icon' => '💬', 'label' => 'AI 智能顧問', 'href' => '/eco_sys/chat.php'];
}

$pages['profile'] = ['icon' => '⚙️', 'label' => '個人設定', 'href' => '/eco_sys/profile.php'];

if ($isAdmin) {
  $pages['admin_users'] = ['icon' => '👥', 'label' => '管理帳號', 'href' => '/eco_sys/admin_users.php'];
}

$roleLabel = '👤 Free 體驗版';
$roleClass = 'free';
if ($isAdmin) {
    $roleLabel = '👑 管理者';
    $roleClass = 'admin';
} elseif (isset($isPro) && $isPro) {
    $roleLabel = '🚀 Pro 專業版';
    $roleClass = 'pro';
} elseif (isset($isPlus) && $isPlus) {
    $roleLabel = '💎 Plus 基礎版';
    $roleClass = 'plus';
}
?>

<!-- ── HUD BACKGROUND (Bottom) ── -->
<div class="hud-bg-wrap">
  <canvas id="canvas-hud"></canvas>
</div>

<!-- ── HUD OVERLAY (Top - Particles & Scanline) ── -->
<div class="hud-overlay-wrap">
  <div class="scanline"></div>
  <canvas id="canvas-top"></canvas>
</div>

<!-- ── TECH CURSOR HUD (High Contrast) ── -->
<div id="cursor-outline"></div>

<style>
  /* HUD Background (Bottom Layer) */
  .hud-bg-wrap {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: -1;
    overflow: hidden;
    opacity: 0.8;
    background-color: #0b0e14;
    background-image:
      linear-gradient(rgba(41, 121, 255, 0.05) 1px, transparent 1px),
      linear-gradient(90deg, rgba(41, 121, 255, 0.05) 1px, transparent 1px),
      linear-gradient(rgba(41, 121, 255, 0.02) 1px, transparent 1px),
      linear-gradient(90deg, rgba(41, 121, 255, 0.02) 1px, transparent 1px);
    background-size: 100px 100px, 100px 100px, 20px 20px, 20px 20px;
  }

  /* HUD Overlay (Always Visible Top Layer) */
  .hud-overlay-wrap {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 9999;
    /* 確保粒子在所有元素之上 */
    overflow: hidden;
  }

  #canvas-top {
    width: 100%;
    height: 100%;
    display: block;
  }

  #canvas-hud {
    width: 100%;
    height: 100%;
    display: block;
  }

  /* Restore visibility but keep high pointer-events */
  * {
    cursor: auto;
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
    transition: opacity 0.3s, width 0.3s, height 0.3s;
    box-shadow: 0 0 15px rgba(0, 176, 255, 0.4);
  }

  .cursor-hover #cursor-outline {
    width: 48px;
    height: 48px;
    border-color: #00E676;
    background: rgba(0, 230, 118, 0.1);
    box-shadow: 0 0 20px rgba(0, 230, 118, 0.5);
  }

  /* Scanline Style */
  .scanline {
    position: absolute;
    width: 100%;
    height: 2px;
    background: rgba(41, 121, 255, 0.1);
    top: 0;
    z-index: 10;
    animation: scanHUD 12s linear infinite;
    box-shadow: 0 0 12px rgba(41, 121, 255, 0.2);
  }

  @keyframes scanHUD {
    from {
      top: -2%;
    }

    to {
      top: 102%;
    }
  }

  /* Site Header Enhancements */
  .site-header {
    background: rgba(11, 14, 20, 0.7) !important;
    backdrop-filter: blur(15px) !important;
    -webkit-backdrop-filter: blur(15px) !important;
    border-bottom: 1px solid rgba(41, 121, 255, 0.2) !important;
  }

  /* 登出按鈕：統一高科技紅 */
  .logout-btn {
    color: #ff4757 !important;
    /* 能量紅 */
    text-decoration: none !important;
    font-size: 1.3rem !important;
    margin-left: 1rem;
    transition: all 0.3s ease;
    display: flex !important;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    background: rgba(255, 71, 87, 0.05);
  }

  .logout-btn:hover {
    background: rgba(255, 71, 87, 0.2);
    box-shadow: 0 0 15px rgba(255, 71, 87, 0.5);
    transform: scale(1.1);
    color: #ff6b81 !important;
  }

  .logout-btn:visited {
    color: #ff4757 !important;
  }

  /* Role Chips Styling */
  .role-chip {
    font-size: 0.75rem; 
    padding: 3px 8px; 
    border-radius: 6px; 
    font-weight: 700;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    box-shadow: inset 0 0 10px rgba(0,0,0,0.5);
  }
  .role-chip.admin { background: rgba(255, 71, 87, 0.15); color: #ff6b81; border: 1px solid rgba(255, 71, 87, 0.4); }
  .role-chip.pro { background: rgba(156, 39, 176, 0.15); color: #e040fb; border: 1px solid rgba(156, 39, 176, 0.4); }
  .role-chip.plus { background: rgba(0, 176, 255, 0.15); color: #40c4ff; border: 1px solid rgba(0, 176, 255, 0.4); }
  .role-chip.free { background: rgba(136, 146, 176, 0.15); color: #aab4be; border: 1px solid rgba(136, 146, 176, 0.4); }
</style>
<header class="site-header" id="siteHeader">
  <div class="header-inner">
    <a class="brand" href="/eco_sys/index.php">
      <span class="brand-icon">🌿</span>
      <span class="brand-text">Eco Trust AI</span>
    </a>

    <nav class="main-nav" id="mainNav">
      <?php foreach ($pages as $key => $p): ?>
        <a href="<?= $p['href'] ?>" class="nav-link <?= $activePage == $key ? 'active' : '' ?>" title="<?= $p['label'] ?>">
          <span class="nav-icon"><?= $p['icon'] ?></span>
          <span class="nav-label"><?= $p['label'] ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="header-right">
      <div class="user-badge">
        <span class="role-chip <?= $roleClass ?>">
          <?= $roleLabel ?>
        </span>
        <span class="username"><?= htmlspecialchars($currentUser) ?></span>
        <a href="/eco_sys/logout.php" class="logout-btn" title="登出">⏻</a>
      </div>

      <!-- Hamburger removed per user request -->
    </div>
  </div>
</header>

<!-- HUD Script -->
<script src="/eco_sys/assets/js/hud-bg.js"></script>