<?php
require_once __DIR__ . '/auth_check.php';
requireAdmin();
require_once __DIR__ . '/config.php';

$db = getDB();
$message = '';
$error = '';

// ─── Actions ─────────────────────────────────────────────────────────────

// Add User
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'free';

    if ($user && $pass) {
        $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $user, $pass, $role);
        if ($stmt->execute()) {
            $message = '使用者已成功新增。';
        } else {
            $error = '新增使用者失敗：' . $db->error;
        }
    } else {
        $error = '帳號與密碼為必填項。';
    }
}

// Delete User
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Prevent self-deletion
    $self_check = $db->prepare("SELECT username FROM users WHERE id = ?");
    $self_check->bind_param('i', $id);
    $self_check->execute();
    $res = $self_check->get_result()->fetch_assoc();
    
    if ($res && $res['username'] === $_SESSION['user']) {
        $error = '您無法刪除目前的帳號。';
    } else {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $message = '使用者已成功刪除。';
        } else {
            $error = '刪除使用者失敗。';
        }
    }
}

// Update Role
if (isset($_POST['action']) && $_POST['action'] === 'update_role') {
    $id = (int)$_POST['id'];
    $new_role = $_POST['role'];
    $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->bind_param('si', $new_role, $id);
    if ($stmt->execute()) {
        $message = '使用者權限已更新。';
    } else {
        $error = '更新權限失敗。';
    }
}

// Update Password
if (isset($_POST['action']) && $_POST['action'] === 'update_password') {
    $id = (int)$_POST['id'];
    $new_pass = trim($_POST['password'] ?? '');
    if ($new_pass) {
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $new_pass, $id);
        if ($stmt->execute()) {
            $message = '密碼已成功重設。';
        } else {
            $error = '重設密碼失敗。';
        }
    } else {
        $error = '新密碼不可為空。';
    }
}


// ─── Fetch All Users ─────────────────────────────────────────────────────
$users = [];
$result = $db->query("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

$activePage = 'admin_users';
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <title>帳號管理 — Eco Trust AI</title>
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

    /* Reuse patterns from index/login */
    .site-header {
      background: rgba(11, 14, 20, 0.8); backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px); border-bottom: 1px solid var(--border);
      position: sticky; top: 0; z-index: 100;
      padding: 0.8rem 2rem;
    }
    .header-inner { display: flex; align-items: center; justify-content: space-between; max-width: 1400px; margin: 0 auto; }
    .brand { display: flex; align-items: center; gap: 0.6rem; text-decoration: none; }
    .brand-icon { font-size: 1.4rem; }
    .brand-text { font-weight: 700; font-size: 1.2rem; color: #fff; letter-spacing: 0.5px; }
    .main-nav { display: flex; gap: 1.5rem; }
    .nav-link { color: var(--muted); text-decoration: none; font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; transition: 0.3s; }
    .nav-link:hover, .nav-link.active { color: var(--accent2); }
    .user-badge { display: flex; align-items: center; gap: 1rem; }
    .role-chip { font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; background: rgba(0, 230, 118, 0.1); color: var(--accent2); }
    .logout-btn { color: var(--danger); text-decoration: none; font-size: 1.2rem; }

    main { max-width: 1100px; margin: 3rem auto; padding: 0 2rem; }
    .page-title { margin-bottom: 2rem; }
    .page-title h2 { font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; }
    .page-title p { color: var(--muted); font-size: 0.95rem; }

    .grid { display: grid; grid-template-columns: 1fr 340px; gap: 2rem; }
    @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }

    .card {
      background: var(--card); border: 1px solid var(--border); border-radius: 16px;
      backdrop-filter: blur(10px); padding: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    .card-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.6rem; }

    /* Form Styles */
    .field { margin-bottom: 1.2rem; }
    label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--muted); text-transform: uppercase; margin-bottom: 0.4rem; }
    input, select {
      width: 100%; background: #0A0C11; border: 1px solid var(--border);
      border-radius: 8px; padding: 0.8rem; color: var(--text); outline: none; transition: 0.3s;
    }
    input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(41, 121, 255, 0.2); }
    .btn {
      width: 100%; padding: 0.8rem; border-radius: 8px; border: none; cursor: pointer;
      font-weight: 600; background: linear-gradient(135deg, var(--accent), var(--accent2));
      color: #fff; transition: 0.3s;
    }
    .btn:hover { filter: brightness(1.1); transform: translateY(-1px); }

    /* Table Styles */
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; color: var(--muted); font-size: 0.8rem; text-transform: uppercase; padding: 1rem; border-bottom: 1px solid var(--border); }
    td { padding: 1rem; border-bottom: 1px solid rgba(41, 121, 255, 0.05); font-size: 0.95rem; }
    .user-row:hover { background: rgba(41, 121, 255, 0.03); }
    
    .role-select { width: auto; padding: 4px 8px; font-size: 0.85rem; }
    .action-btn { font-size: 0.82rem; padding: 4px 10px; border-radius: 6px; text-decoration: none; cursor: pointer; transition: 0.2s; border: 1px solid transparent; }
    .pwd-btn { color: var(--accent2); border-color: rgba(0, 230, 118, 0.3); background: transparent; }
    .pwd-btn:hover { background: var(--accent2); color: var(--bg); }
    .del-btn { color: var(--danger); border-color: rgba(255, 71, 87, 0.3); background: transparent; }
    .del-btn:hover { background: var(--danger); color: #fff; }


    .toast.success { background: rgba(0, 230, 118, 0.1); border: 1px solid var(--success); color: var(--success); }
    .toast.error { background: rgba(255, 71, 87, 0.1); border: 1px solid var(--danger); color: var(--danger); }

    .current-user-badge {
      display: inline-block;
      font-size: 0.7rem;
      background: rgba(41, 121, 255, 0.15);
      color: var(--accent);
      border: 1px solid var(--accent);
      padding: 1px 6px;
      border-radius: 4px;
      margin-left: 8px;
      font-weight: 700;
      text-transform: uppercase;
      box-shadow: 0 0 10px rgba(41, 121, 255, 0.3);
    }
  </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/header.php'; ?>

<main>
  <div class="page-title">
    <h2>👥 帳號管理中心</h2>
    <p>管理系統使用者，設定其存取權限與身份。</p>
  </div>

  <?php if($message): ?>
    <div class="toast success">✅ <?= htmlspecialchars($message) ?></div>
  <?php endif; ?>
  <?php if($error): ?>
    <div class="toast error">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="grid">
    <!-- User List -->
    <div class="card">
      <div class="card-title">📝 使用者列表</div>
      <div style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>帳號</th>
              <th>權限</th>
              <th>建立於</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
            <?php 
              foreach($users as $u): 
                $isSelf = ($u['username'] === $_SESSION['user']);
            ?>
            <tr class="user-row" style="<?= $isSelf ? 'background: rgba(41, 121, 255, 0.05);' : '' ?>">
              <td style="font-weight: 500; font-family: 'JetBrains Mono'; color: <?= $isSelf ? 'var(--accent)' : 'inherit' ?>">
                <?= htmlspecialchars($u['username']) ?>
                <?php if($isSelf): ?><span class="current-user-badge">目前登入</span><?php endif; ?>
              </td>
              <td>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="update_role">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <select name="role" class="role-select" onchange="this.form.submit()" <?= $isSelf ? 'disabled' : '' ?>>
                    <option value="free" <?= ($u['role']==='free' || $u['role']==='user')?'selected':'' ?>>Free 體驗版</option>
                    <option value="plus" <?= $u['role']==='plus'?'selected':'' ?>>Plus 基礎版</option>
                    <option value="pro" <?= $u['role']==='pro'?'selected':'' ?>>Pro 專業版</option>
                    <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>管理者</option>
                  </select>
                  <?php if($isSelf): ?><input type="hidden" name="role" value="admin"><?php endif; ?>
                </form>
              </td>
              <td style="color: var(--muted); font-size: 0.85rem;"><?= date('Y-m-d H:i', strtotime($u['created_at'])) ?></td>
              <td style="display: flex; gap: 0.5rem;">
                <button type="button" class="action-btn pwd-btn" onclick="resetPassword(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')">重設密碼</button>
                <?php if(!$isSelf): ?>
                <a href="?delete=<?= $u['id'] ?>" class="action-btn del-btn" onclick="return confirm('確定要刪除此帳號嗎？')">刪除</a>
                <?php else: ?>
                <span style="color:var(--muted); font-size:0.8rem; display:flex; align-items:center;">[系統受保護]</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add Form -->
    <div class="card">
      <div class="card-title">➕ 新增使用者</div>
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="field">
          <label>帳號名稱</label>
          <input type="text" name="username" placeholder="例如: sally_admin" required>
        </div>
        <div class="field">
          <label>初始密碼</label>
          <input type="password" name="password" placeholder="建議混合大、小寫文字" required>
        </div>
        <div class="field">
          <label>帳號權限</label>
          <select name="role">
            <option value="free">Free 體驗版</option>
            <option value="plus">Plus 基礎版</option>
            <option value="pro">Pro 專業版</option>
            <option value="admin">管理者 (Admin)</option>
          </select>
        </div>
        <button type="submit" class="btn">建立帳號</button>
      </form>
      <div style="margin-top: 1.5rem; font-size: 0.8rem; color: var(--muted); background: rgba(255,255,255,0.05); padding: 1rem; border-radius: 8px;">
        💡 <b>提示：</b> 管理者具備完整修改權限，包含資料更新、PDF 上傳與此頁面的存取權。
      </div>
    </div>
  </div>
</main>

<!-- Hidden Password Form -->
<form id="pwdForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="update_password">
    <input type="hidden" name="id" id="pwdId">
    <input type="hidden" name="password" id="pwdVal">
</form>

<script>
function resetPassword(id, username) {
    const newPass = prompt(`請輸入「${username}」的新密碼：`);
    if (newPass !== null && newPass.trim() !== '') {
        document.getElementById('pwdId').value = id;
        document.getElementById('pwdVal').value = newPass;
        document.getElementById('pwdForm').submit();
    }
}
</script>

</body>

</html>
