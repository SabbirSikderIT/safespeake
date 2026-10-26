<?php
require_once __DIR__ . '/../backend/includes/config.php';
if (isStudentLoggedIn()) redirect(SITE_URL . '/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM students WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $student = $stmt->fetch();
        if ($student && verifyAndUpgradePassword($db, 'students', $student['id'], $password, $student['password'])) {
            session_regenerate_id(true);
            $_SESSION['student_id']      = $student['id'];
            $_SESSION['student_name']    = $student['full_name'];
            $_SESSION['student_sid']     = $student['student_id'];
            $_SESSION['student_email']   = $student['email'];
            $db->prepare("UPDATE students SET last_login = NOW() WHERE id = ?")->execute([$student['id']]);
            redirect(SITE_URL . '/dashboard.php');
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Login — SafeSpeak</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
:root{--navy:#0a0e27;--blue:#1a56e8;--cyan:#06b6d4;--white:#fff;--gray-400:#94a3b8;--grad:linear-gradient(135deg,#1a56e8,#06b6d4);--red:#ef4444;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--navy);color:var(--white);min-height:100vh;display:flex;flex-direction:column;}
.bg{position:fixed;inset:0;background:radial-gradient(ellipse 70% 60% at 20% 30%,rgba(26,86,232,0.2),transparent 60%),var(--navy);z-index:0;}
.page{position:relative;z-index:1;flex:1;display:flex;flex-direction:column;}
header{padding:1.25rem 2rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,0.07);}
.logo{font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:800;text-decoration:none;color:var(--white);}
.logo span{color:var(--cyan);}
.back-link{font-size:0.85rem;color:var(--gray-400);text-decoration:none;}
.back-link:hover{color:var(--white);}
main{flex:1;display:flex;align-items:center;justify-content:center;padding:2rem;}
.card{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:20px;padding:2.5rem;width:100%;max-width:420px;backdrop-filter:blur(10px);}
.card-header{text-align:center;margin-bottom:2rem;}
.card-icon{width:60px;height:60px;background:var(--grad);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin:0 auto 1rem;}
.card-header h1{font-family:'Syne',sans-serif;font-size:1.6rem;font-weight:700;margin-bottom:0.4rem;}
.card-header p{color:var(--gray-400);font-size:0.875rem;}
.form-group{margin-bottom:1.1rem;}
label{display:block;font-size:0.8rem;font-weight:500;color:var(--gray-400);margin-bottom:0.4rem;}
input{width:100%;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.12);border-radius:8px;padding:0.75rem 1rem;color:var(--white);font-size:0.95rem;font-family:'DM Sans',sans-serif;outline:none;transition:border-color 0.2s;}
input::placeholder{color:rgba(255,255,255,0.25);}
input:focus{border-color:var(--blue);}
.btn{width:100%;padding:0.85rem;background:var(--grad);color:var(--white);border:none;border-radius:10px;font-size:1rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:opacity 0.2s,transform 0.2s;margin-top:0.5rem;}
.btn:hover{opacity:0.9;transform:translateY(-1px);}
.msg.error{padding:0.85rem 1rem;border-radius:8px;font-size:0.875rem;margin-bottom:1rem;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#fca5a5;}
.footer-link{text-align:center;margin-top:1.25rem;font-size:0.875rem;color:var(--gray-400);}
.footer-link a{color:var(--cyan);text-decoration:none;}
.footer-link a:hover{text-decoration:underline;}
.demo-box{background:rgba(6,182,212,0.08);border:1px solid rgba(6,182,212,0.2);border-radius:8px;padding:0.75rem;margin-bottom:1rem;font-size:0.8rem;color:var(--gray-400);}
.demo-box strong{color:var(--cyan);}
</style>
<link rel="stylesheet" href="theme.css">
<script src="theme.js"></script>
</head>
<body>
<div class="bg"></div>
<div class="page">
  <header>
    <a class="logo" href="index.php">Safe<span>Speak</span></a>
    <a class="back-link" href="index.php">← Home</a>
  </header>
  <main>
    <div class="card">
      <div class="card-header">
        <div class="card-icon">🎓</div>
        <h1>Student Login</h1>
        <p>Access your SafeSpeak account</p>
      </div>

      <div class="demo-box">
        <strong>Demo:</strong> Email: <strong>apon@student.university.bd</strong><br>Password: <strong>password</strong>
      </div>

      <?php if ($error): ?><div class="msg error">⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" placeholder="your@email.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Your password" required>
        </div>
        <button type="submit" class="btn">Login to Dashboard →</button>
      </form>

      <div class="footer-link">Don't have an account? <a href="register.php">Register here</a></div>
      <div class="footer-link" style="margin-top:0.5rem;"><a href="report_anonymous.php">📝 Report without account</a></div>
    </div>
  </main>
</div>
</body>
</html>
