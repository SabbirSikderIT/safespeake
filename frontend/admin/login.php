<?php
require_once __DIR__ . '/../../backend/includes/config.php';
if (isAdminLoggedIn()) redirect(SITE_URL . '/admin/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'Please enter your credentials.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM admins WHERE (username = ? OR email = ?) AND is_active = 1");
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();
        if ($admin && verifyAndUpgradePassword($db, 'admins', $admin['id'], $password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_user'] = $admin['username'];
            $_SESSION['admin_last_login'] = $admin['last_login'] ?? null;
            $db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
            redirect(SITE_URL . '/admin/dashboard.php');
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — SafeSpeak</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
:root{--navy:#0a0e27;--blue:#1a56e8;--cyan:#06b6d4;--teal:#0d9488;--white:#fff;--gray-400:#94a3b8;--grad-admin:linear-gradient(135deg,#0d9488,#06b6d4);--red:#ef4444;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--navy);color:var(--white);min-height:100vh;display:flex;flex-direction:column;}
.bg{position:fixed;inset:0;background:radial-gradient(ellipse 70% 60% at 70% 20%,rgba(13,148,136,0.2),transparent 60%),var(--navy);z-index:0;}
.page{position:relative;z-index:1;flex:1;display:flex;flex-direction:column;}
header{padding:1.25rem 2rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,0.07);}
.logo{font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:800;text-decoration:none;color:var(--white);}
.logo span{color:var(--cyan);}
.back-link{font-size:0.85rem;color:var(--gray-400);text-decoration:none;}
.back-link:hover{color:var(--white);}
main{flex:1;display:flex;align-items:center;justify-content:center;padding:2rem;}
.card{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:20px;padding:2.5rem;width:100%;max-width:420px;backdrop-filter:blur(10px);}
.card-header{text-align:center;margin-bottom:2rem;}
.card-icon{width:60px;height:60px;background:var(--grad-admin);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin:0 auto 1rem;}
.card-header h1{font-family:'Syne',sans-serif;font-size:1.6rem;font-weight:700;margin-bottom:0.4rem;}
.card-header p{color:var(--gray-400);font-size:0.875rem;}
.form-group{margin-bottom:1.1rem;}
label{display:block;font-size:0.8rem;font-weight:500;color:var(--gray-400);margin-bottom:0.4rem;}
input{width:100%;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.12);border-radius:8px;padding:0.75rem 1rem;color:var(--white);font-size:0.95rem;font-family:'DM Sans',sans-serif;outline:none;transition:border-color 0.2s;}
input::placeholder{color:rgba(255,255,255,0.25);}
input:focus{border-color:var(--teal);}
.btn{width:100%;padding:0.85rem;background:var(--grad-admin);color:var(--white);border:none;border-radius:10px;font-size:1rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:opacity 0.2s,transform 0.2s;margin-top:0.5rem;}
.btn:hover{opacity:0.9;transform:translateY(-1px);}
.msg.error{padding:0.85rem 1rem;border-radius:8px;font-size:0.875rem;margin-bottom:1rem;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#fca5a5;}
.footer-link{text-align:center;margin-top:1.25rem;font-size:0.875rem;color:var(--gray-400);}
.footer-link a{color:var(--cyan);text-decoration:none;}
.demo-box{background:rgba(13,148,136,0.08);border:1px solid rgba(13,148,136,0.2);border-radius:8px;padding:0.75rem;margin-bottom:1rem;font-size:0.8rem;color:var(--gray-400);}
.demo-box strong{color:#5eead4;}
.admin-badge{display:inline-flex;align-items:center;gap:0.4rem;background:rgba(13,148,136,0.15);border:1px solid rgba(13,148,136,0.3);padding:0.3rem 0.75rem;border-radius:100px;font-size:0.75rem;color:#5eead4;margin-bottom:1rem;}
</style>
<link rel="stylesheet" href="../theme.css">
<script src="../theme.js"></script>
</head>
<body>
<div class="bg"></div>
<div class="page">
  <header>
    <a class="logo" href="../index.php">Safe<span>Speak</span></a>
    <a class="back-link" href="../index.php">← Home</a>
  </header>
  <main>
    <div class="card">
      <div class="card-header">
        <div class="card-icon">🛡️</div>
        <div class="admin-badge">🔒 Restricted Access</div>
        <h1>Admin / Proctor Login</h1>
        <p>Access the SafeSpeak management panel</p>
      </div>

      <div class="demo-box">
        <strong>Demo Accounts:</strong><br>
        Username: <strong>admin</strong> | Password: <strong>password</strong><br>
        Username: <strong>proctor</strong> | Password: <strong>password</strong>
      </div>

      <?php if ($error): ?><div class="msg error">⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <label>Username or Email</label>
          <input type="text" name="username" placeholder="admin / proctor" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Your password" required>
        </div>
        <button type="submit" class="btn">🛡️ Login to Admin Panel →</button>
      </form>

      <div class="footer-link" style="margin-top:1rem;">
        <a href="../index.php">← Back to main site</a>
      </div>
    </div>
  </main>
</div>
</body>
</html>
