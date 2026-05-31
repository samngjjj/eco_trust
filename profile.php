<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config.php';

$db = getDB();
$message = '';
$error = '';

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_user = trim($_POST['username'] ?? '');
    $new_pass = trim($_POST['password'] ?? '');
    $old_user = $_SESSION['user'];

    if ($new_user && $new_pass) {
        // Check if username is taken (by someone else)
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND username != ?");
        $stmt->bind_param('ss', $new_user, $old_user);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = '此帳號名稱已被使用。';
        } else {
            $stmt = $db->prepare("UPDATE users SET username = ?, password = ? WHERE username = ?");
            $stmt->bind_param('sss', $new_user, $new_pass, $old_user);
            if ($stmt->execute()) {
                $_SESSION['user'] = $new_user; // Update session
                $message = '個人資料已成功更新。';
            } else {
                $error = '更新失敗：' . $db->error;
            }
        }
    } else {
        $error = '帳號與密碼為必填項。';
    }
}

$activePage = 'profile';
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <title>個人設定 — Eco Trust AI</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #0B0E14; --card: rgba(22, 27, 34, 0.7); --accent: #2979FF; --accent2: #00E676;
      --text: #e8eaf6; --muted: #8892b0; --border: rgba(41, 121, 255, 0.2);
      --danger: #ff4757; --success: #00E676;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: var(--bg); color: var(--text);
      font-family: 'Inter', 'Noto Sans TC', sans-serif;
      min-height: 100vh; line-height: 1.6;
    }

    .site-header {
      background: rgba(11, 14, 20, 0.8); backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px); border-bottom: 1px solid var(--border);
      position: sticky; top: 0; z-index: 100;
      padding: 0.8rem 2rem;
    }
    .header-inner { display: flex; align-items: center; justify-content: space-between; max-width: 1400px; margin: 0 auto; }
    .brand { display: flex; align-items: center; gap: 0.6rem; text-decoration: none; }
    .brand-text { font-weight: 700; font-size: 1.2rem; color: #fff; letter-spacing: 0.5px; }
    .main-nav { display: flex; gap: 1.5rem; }
    .nav-link { color: var(--muted); text-decoration: none; font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; transition: 0.3s; }
    .nav-link:hover, .nav-link.active { color: var(--accent2); }
    .user-badge { display: flex; align-items: center; gap: 1rem; }
    .role-chip { font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; background: rgba(41, 121, 255, 0.1); color: var(--accent); }
    .role-chip.admin { background: rgba(0, 230, 118, 0.1); color: var(--accent2); }

    main { max-width: 500px; margin: 5rem auto; padding: 0 2rem; }
    .card {
      background: var(--card); border: 1px solid var(--border); border-radius: 20px;
      backdrop-filter: blur(12px); padding: 2.5rem; box-shadow: 0 20px 50px rgba(0,0,0,0.4);
    }
    .card h2 { font-size: 1.6rem; margin-bottom: 0.5rem; background: linear-gradient(135deg, var(--accent), var(--accent2)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .card p { color: var(--muted); font-size: 0.9rem; margin-bottom: 2rem; }

    .field { margin-bottom: 1.5rem; }
    label { display: block; font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase; margin-bottom: 0.6rem; letter-spacing: 1px; }
    input {
      width: 100%; background: rgba(0, 0, 0, 0.3); border: 1px solid var(--border);
      border-radius: 10px; padding: 0.9rem 1.2rem; color: var(--text); outline: none; transition: 0.3s;
    }
    input:focus { border-color: var(--accent); box-shadow: 0 0 0 4px rgba(41, 121, 255, 0.15); }

    .btn {
      width: 100%; padding: 1rem; border-radius: 10px; border: none; cursor: pointer;
      font-weight: 700; font-size: 1rem; background: linear-gradient(135deg, var(--accent), var(--accent2));
      color: #fff; transition: 0.3s; box-shadow: 0 10px 20px rgba(41, 121, 255, 0.2);
    }
    .btn:hover { filter: brightness(1.1); transform: translateY(-2px); box-shadow: 0 15px 30px rgba(41, 121, 255, 0.3); }

    .toast {
      padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; font-size: 0.9rem;
      display: flex; align-items: center; gap: 0.8rem; animation: slideIn 0.4s ease;
    }
    @keyframes slideIn { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .toast.success { background: rgba(0, 230, 118, 0.1); border: 1px solid var(--success); color: var(--success); }
    .toast.error { background: rgba(255, 71, 87, 0.1); border: 1px solid var(--danger); color: var(--danger); }
  </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/header.php'; ?>

<main>
  <div class="card">
    <h2>⚙️ 個人資料設定</h2>
    <p>在此修改您的登入帳號與密碼。</p>

    <?php if($message): ?>
      <div class="toast success">✅ <?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if($error): ?>
      <div class="toast error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="field">
        <label>帳號名稱 (Username)</label>
        <input type="text" name="username" value="<?= htmlspecialchars($_SESSION['user']) ?>" required>
      </div>
      <div class="field">
        <label>重設密碼 (New Password)</label>
        <input type="password" name="password" placeholder="輸入新密碼" required>
      </div>
      <button type="submit" class="btn">更新個人資料</button>
    </form>
    
    <div style="margin-top: 1.5rem; text-align: center;">
      <a href="index.php" style="color: var(--muted); font-size: 0.85rem; text-decoration: none;">← 返回管理中心</a>
    </div>
  </div>
</main>

</body>
</html>
