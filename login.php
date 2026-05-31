<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

$error = '';
$success_user = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $db   = getDB();
    $stmt = $db->prepare("SELECT username, password, role FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row && $row['password'] === $password) {
        $_SESSION['user'] = $row['username'];
        $_SESSION['role'] = $row['role'];
        $success_user = $row['username'];
    } else {
        $error = '帳號或密碼錯誤，請重新登入。';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Eco Trust AI — 登入系統</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root {
  --bg: #0B0E14;
  --card: rgba(22, 27, 34, 0.85);
  --accent: #2979FF;
  --accent2: #00E676;
  --text: #e8eaf6;
  --muted: #8892b0;
  --border: rgba(41, 121, 255, 0.3);
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
  background: var(--bg);
  color: var(--text);
  font-family: 'Inter', 'Noto Sans TC', sans-serif;
  height: 100vh;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
</head>
<style>
:root {
  --bg: #0B0E14;
  --card: rgba(22, 27, 34, 0.85);
  --accent: #2979FF;
  --accent2: #00E676;
  --text: #e8eaf6;
  --muted: #8892b0;
  --border: rgba(41, 121, 255, 0.3);
}

/* HUD Background Styles */
.hud-bg-wrap { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: -1; overflow: hidden; opacity: 0.6; }
.hud-overlay-wrap { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 9999; overflow: hidden; }
#canvas-top, #canvas-hud { width: 100%; height: 100%; display: block; }
.scanline { position: absolute; width: 100%; height: 2px; background: rgba(41, 121, 255, 0.1); top: 0; z-index: 10; animation: scanHUD 12s linear infinite; box-shadow: 0 0 12px rgba(41, 121, 255, 0.2); }
@keyframes scanHUD { from { top: -2%; } to { top: 102%; } }
#cursor-outline { position: fixed; top: 0; left: 0; z-index: 10000; width: 32px; height: 32px; border: 2px solid #00B0FF; border-radius: 50%; pointer-events: none; opacity: 0; transition: opacity 0.3s, width 0.3s, height 0.3s; box-shadow: 0 0 15px rgba(0, 176, 255, 0.4); }
.cursor-hover #cursor-outline { width: 48px; height: 48px; border-color: #00E676; background: rgba(0, 230, 118, 0.1); box-shadow: 0 0 20px rgba(0, 230, 118, 0.5); }

/* ── Back to Landing Button ── */
.btn-back-landing {
  position: absolute; top: 2rem; left: 2rem;
  display: inline-flex; align-items: center; gap: 0.5rem;
  color: var(--muted); font-size: 0.9rem; text-decoration: none;
  padding: 0.5rem 1.1rem; border: 1px solid rgba(255,255,255,0.08);
  border-radius: 8px; background: rgba(22, 27, 34, 0.5);
  backdrop-filter: blur(10px); z-index: 1000;
  transition: all 0.3s; box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}
.btn-back-landing:hover {
  color: var(--text);
  border-color: var(--accent);
  background: rgba(41, 121, 255, 0.1);
  box-shadow: 0 0 15px rgba(41, 121, 255, 0.3);
  transform: translateY(-2px);
}

/* ── Login Wrap (Simplified to rounded card) ── */
.login-wrap {
  width: 100%; max-width: 420px; padding: 2rem;
  position: relative; z-index: 10;
}

.card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 2.5rem;
  backdrop-filter: blur(15px);
  box-shadow: 0 0 20px rgba(41, 121, 255, 0.1), 0 20px 60px rgba(0,0,0,0.6);
  transition: all 0.3s;
}

.logo { text-align: center; margin-bottom: 2rem; }
.logo h1 {
  font-size: 1.8rem; font-weight: 700;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.logo p { color: var(--muted); font-size: 0.85rem; margin-top: 0.4rem; letter-spacing: 1px; }

/* ── Clean Inputs ── */
.field { margin-bottom: 1.4rem; }
label {
  display: block; font-size: 0.82rem; font-weight: 600; color: var(--muted);
  text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.6rem;
}
input {
  width: 100%; background: #0A0C11; border: 1px solid var(--border);
  border-radius: 8px; padding: 0.85rem 1rem; color: var(--text); font-size: 0.95rem;
  transition: all 0.2s; outline: none;
}
input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(41, 121, 255, 0.2);
  background: rgba(41, 121, 255, 0.03);
}

/* ── Clean Button ── */
.btn {
  width: 100%; padding: 0.95rem; border: none; border-radius: 8px; cursor: pointer;
  font-size: 1rem; font-weight: 600; letter-spacing: 0.04em;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  color: #fff; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  margin-top: 0.5rem; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}
.btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 0 20px rgba(41, 121, 255, 0.6);
  filter: brightness(1.1);
}

.error {
  background: rgba(255,71,87,0.12); border: 1px solid rgba(255,71,87,0.3);
  color: #ff6b81; border-radius: 8px; padding: 0.8rem 1rem;
  font-size: 0.88rem; margin-bottom: 1.2rem; text-align: center;
}

/* ── Overlays (Kept but simplified fonts) ── */
#boot-overlay {
  position: fixed; inset: 0; background: var(--bg);
  z-index: 100; display: flex; flex-direction: column;
  align-items: center; justify-content: center;
}
.boot-txt { font-size: 0.9rem; color: var(--accent); margin-top: 20px; font-weight: 500; }

#welcome-overlay {
  position: fixed; inset: 0; background: rgba(11, 14, 20, 0.96);
  display: none; z-index: 200; flex-direction: column;
  align-items: center; justify-content: center;
}
.welcome-card { text-align: center; animation: zoomIn 0.4s ease-out; }
@keyframes zoomIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }

.jarvis-orb {
  width: 70px; height: 70px; border-radius: 50%;
  border: 3px solid var(--accent); margin: 0 auto 20px;
  box-shadow: 0 0 20px var(--accent); position: relative;
}
.jarvis-orb::after {
  content: ''; position: absolute; inset: 6px; border-radius: 50%;
  border: 1.5px dashed var(--accent); animation: spin 4s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.welcome-h1 { font-size: 1.6rem; font-weight: 700; color: #fff; margin-bottom: 8px; }
.welcome-p { color: var(--muted); font-size: 0.9rem; letter-spacing: 1px; }

@keyframes pulse { 0%, 100% { opacity: 0.4; transform: scale(1); } 50% { opacity: 1; transform: scale(1.05); } }
</style>

</head>
<body>

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

<!-- ── Boot Phase (Only if no errors) ── -->
<?php if(!$error): ?>
<div id="boot-overlay">
  <div class="jarvis-orb" style="animation: pulse 2s infinite"></div>
  <div class="boot-txt" id="boot-status">系統初始化中... 0%</div>
</div>
<?php endif; ?>

<!-- ── Success Phase ── -->
<div id="welcome-overlay">
  <div class="welcome-card">
    <div class="jarvis-orb"></div>
    <div class="welcome-h1" id="welcome-user">歡迎回來</div>
    <div class="welcome-p">認證成功，正在載入 ESG 分析儀表板...</div>
  </div>
</div>

<a href="landing.php" class="btn-back-landing">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
  返回首頁
</a>

<div class="login-wrap" id="loginContent">
  <div class="logo">
    <h1>🌿 Eco Trust AI</h1>
    <p>ESG 永續誠信分析平台</p>
  </div>

  <div class="card">
    <?php if($error): ?>
    <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="field">
        <label>帳號名稱 IDENTIFICATION</label>
        <input type="text" name="username" placeholder="請輸入您的帳號" required autofocus>
      </div>
      <div class="field">
        <label>密鑰代碼 AUTHORIZATION</label>
        <input type="password" name="password" placeholder="請輸入密碼" required>
      </div>
      <button type="submit" class="btn">啟動系統登入</button>
      <div style="text-align: right; margin-top: 1rem;">
        <a href="forgot_password.php" style="color: var(--muted); font-size: 0.85rem; text-decoration: none; border-bottom: 1px solid transparent; transition: border-color .2s;">忘記密碼？</a>
      </div>
    </form>
  </div>
</div>

<!-- HUD Script -->
<script src="/eco_sys/assets/js/hud-bg.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
<script>
// ── Boot Sequence (Only if no existing error) ──
<?php if(!$error): ?>
const bootStatus = document.getElementById('boot-status');
let progress = 0;
const bootTimer = setInterval(() => {
  progress += Math.floor(Math.random() * 12) + 3;
  if (progress >= 100) {
    progress = 100;
    clearInterval(bootTimer);
    anime({
      targets: '#boot-overlay',
      opacity: 0,
      translateY: -20,
      duration: 800,
      easing: 'easeOutExpo',
      complete: () => document.getElementById('boot-overlay').style.display = 'none'
    });
  }
  bootStatus.innerText = `系統初始化中... ${progress}%`;
}, 70);
<?php endif; ?>

// ── Auth Success Trigger ──
<?php if ($success_user): ?>
const user = "<?= htmlspecialchars($success_user) ?>";
document.getElementById('welcome-user').innerText = `歡迎回來，${user}`;
document.getElementById('loginContent').style.display = 'none';
document.getElementById('welcome-overlay').style.display = 'flex';

setTimeout(() => {
  window.location.href = 'index.php';
}, 2200);
<?php endif; ?>

// Hover Effect
document.querySelector('.card').addEventListener('mousemove', (e) => {
    const card = e.currentTarget;
    const rect = card.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    card.style.setProperty('--mouse-x', `${x}px`);
    card.style.setProperty('--mouse-y', `${y}px`);
});

</script>

<style>
@keyframes pulse { 0%, 100% { opacity: 0.4; transform: scale(1); } 50% { opacity: 1; transform: scale(1.05); } }
</style>

</body>
</html>
