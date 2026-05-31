<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

$db = getDB();
$error = '';
$message = '';
$step = 1; // 1: Enter Username, 2: Reset Password
$target_user = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['find_user'])) {
        $username = trim($_POST['username'] ?? '');
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $step = 2;
            $target_user = $username;
        } else {
            $error = '找不到此帳號，請重新確認。';
        }
    } elseif (isset($_POST['reset_pwd'])) {
        $username = $_POST['username'];
        $new_pass = trim($_POST['password'] ?? '');
        
        if ($new_pass) {
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE username = ?");
            $stmt->bind_param('ss', $new_pass, $username);
            if ($stmt->execute()) {
                $message = '密碼已重設成功！請使用新密碼登入。';
                $step = 3; // Finished
            } else {
                $error = '重設失敗，請稍後再試。';
                $step = 2;
                $target_user = $username;
            }
        } else {
            $error = '新密碼不可為空。';
            $step = 2;
            $target_user = $username;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>重設密碼 — Eco Trust AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#0B0E14;--card:rgba(22, 27, 34, 0.7);--accent:#2979FF;--accent2:#00E676;
  --text:#e8eaf6;--muted:#8892b0;--danger:#ff4757;--success:#00E676;
  --border:rgba(41,121,255,.2);
}
*{box-sizing:border-box;margin:0;padding:0}
body{
  background:var(--bg);color:var(--text);
  font-family:'Inter','Noto Sans TC',sans-serif;
  min-height:100vh;display:flex;align-items:center;justify-content:center;
  overflow:hidden;
}
#canvas-container { position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: 0; }
.login-wrap{width:100%;max-width:420px;padding:2rem;position:relative;z-index:1;}
.card{
  background:var(--card);border:1px solid rgba(41,121,255,0.3);border-radius:16px;
  padding:2.5rem;box-shadow: 0 20px 60px rgba(0,0,0,.5);
  backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px);
}
.card h2 { margin-bottom: 0.5rem; font-size: 1.4rem; background: linear-gradient(135deg,var(--accent),var(--accent2)); -webkit-background-clip:text;-webkit-text-fill-color:transparent; }
.card p { color: var(--muted); font-size: 0.85rem; margin-bottom: 2rem; }
.field{margin-bottom:1.4rem}
label{display:block;font-size:.82rem;font-weight:600;color:var(--muted);text-transform:uppercase;margin-bottom:.5rem}
input{
  width:100%;background:#0A0C11;border:1px solid var(--border);
  border-radius:8px;padding:.85rem 1rem;color:var(--text);font-size:.95rem;transition:0.2s;outline:none;
}
input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(41,121,255,.2)}
.btn{
  width:100%;padding:.9rem;border:none;border-radius:8px;cursor:pointer;
  font-size:1rem;font-weight:600;background:linear-gradient(135deg,var(--accent),var(--accent2));
  color:#fff;transition:all .3s ease;margin-top:.5rem;
}
.btn:hover{ filter:brightness(1.2); transform:translateY(-2px); }
.error{ background:rgba(255,71,87,.12);border:1px solid rgba(255,71,87,.35); color:#ff6b81;border-radius:8px;padding:.8rem 1rem;font-size:.88rem;margin-bottom:1.2rem; }
.success{ background:rgba(0,230,118,0.1); border:1px solid var(--success); color:var(--success); border-radius:8px;padding:.8rem 1rem;font-size:.88rem;margin-bottom:1.2rem; }
.back-link { display: block; text-align: center; margin-top: 1.5rem; color: var(--muted); text-decoration: none; font-size: 0.85rem; transition: 0.3s; }
.back-link:hover { color: var(--accent); }
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

<style>
/* HUD Background Styles */
.hud-bg-wrap { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: -1; overflow: hidden; opacity: 0.6; }
.hud-overlay-wrap { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 9999; overflow: hidden; }
#canvas-top, #canvas-hud { width: 100%; height: 100%; display: block; }
.scanline { position: absolute; width: 100%; height: 2px; background: rgba(41, 121, 255, 0.1); top: 0; z-index: 10; animation: scanHUD 12s linear infinite; box-shadow: 0 0 12px rgba(41, 121, 255, 0.2); }
@keyframes scanHUD { from { top: -2%; } to { top: 102%; } }
#cursor-outline { position: fixed; top: 0; left: 0; z-index: 10000; width: 32px; height: 32px; border: 2px solid #00B0FF; border-radius: 50%; pointer-events: none; opacity: 0; transition: opacity 0.3s, width 0.3s, height 0.3s; box-shadow: 0 0 15px rgba(0, 176, 255, 0.4); }
.cursor-hover #cursor-outline { width: 48px; height: 48px; border-color: #00E676; background: rgba(0, 230, 118, 0.1); box-shadow: 0 0 20px rgba(0, 230, 118, 0.5); }
body.cursor-hover { cursor: auto; }
</style>
<div class="login-wrap">
  <div class="card">
    <h2>🔑 重設登入密碼</h2>
    
    <?php if($error): ?> <div class="error">⚠️ <?= htmlspecialchars($error) ?></div> <?php endif; ?>
    <?php if($message): ?> <div class="success">✅ <?= htmlspecialchars($message) ?></div> <?php endif; ?>

    <?php if($step === 1): ?>
      <p>請輸入您的帳號名稱，系統將協助您重設密碼。</p>
      <form method="POST">
        <div class="field">
          <label>您的帳號</label>
          <input type="text" name="username" placeholder="輸入原本的帳號" required autofocus>
        </div>
        <button type="submit" name="find_user" class="btn">核對帳號</button>
      </form>
    <?php elseif($step === 2): ?>
      <p>帳號已確認！請為 <b><?= htmlspecialchars($target_user) ?></b> 設定新密碼。</p>
      <form method="POST">
        <input type="hidden" name="username" value="<?= htmlspecialchars($target_user) ?>">
        <div class="field">
          <label>輸入新密密碼</label>
          <input type="password" name="password" placeholder="請輸入新密碼" required autofocus>
        </div>
        <button type="submit" name="reset_pwd" class="btn">確定重設密碼</button>
      </form>
    <?php elseif($step === 3): ?>
      <p>您的密碼已更新完成，現在可以重新登入了。</p>
      <a href="login.php" class="btn" style="text-align:center; text-decoration:none; display:block;">前往登入頁面</a>
    <?php endif; ?>

    <a href="login.php" class="back-link">← 返回登入頁面</a>
  </div>
</div>

<!-- HUD Script -->
<script src="/eco_sys/assets/js/hud-bg.js"></script>
</body>
</html>
