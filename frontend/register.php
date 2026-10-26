<?php
require_once __DIR__ . '/../backend/includes/config.php';

if (isStudentLoggedIn()) redirect(SITE_URL . '/dashboard.php');

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name   = sanitize($_POST['full_name'] ?? '');
    $student_id  = sanitize($_POST['student_id'] ?? '');
    $email       = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $department  = sanitize($_POST['department'] ?? '');
    $batch       = sanitize($_POST['batch'] ?? '');
    $institution = sanitize($_POST['institution'] ?? '');
    $phone       = sanitize($_POST['phone'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';

    if (!$full_name || !$student_id || !$email || !$password) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM students WHERE email = ? OR student_id = ?");
        $stmt->execute([$email, $student_id]);
        if ($stmt->fetch()) {
            $error = 'An account with this email or student ID already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO students (student_id, full_name, email, password, department, batch, institution, phone) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$student_id, $full_name, $email, $hashed, $department, $batch, $institution, $phone]);
            $success = 'Account created successfully! You can now login.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Registration — SafeSpeak</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
:root{--navy:#0a0e27;--blue:#1a56e8;--cyan:#06b6d4;--white:#fff;--gray-400:#94a3b8;--gray-600:#475569;--grad:linear-gradient(135deg,#1a56e8 0%,#06b6d4 100%);--red:#ef4444;--green:#10b981;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--navy);color:var(--white);min-height:100vh;display:flex;flex-direction:column;}
.bg{position:fixed;inset:0;background:radial-gradient(ellipse 70% 60% at 30% 20%,rgba(26,86,232,0.2) 0%,transparent 60%),radial-gradient(ellipse 50% 40% at 80% 80%,rgba(6,182,212,0.12) 0%,transparent 60%),var(--navy);z-index:0;}
.page{position:relative;z-index:1;flex:1;display:flex;flex-direction:column;}
header{padding:1.25rem 2rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,0.07);}
.logo{font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:800;text-decoration:none;color:var(--white);}
.logo span{color:var(--cyan);}
.back-link{font-size:0.85rem;color:var(--gray-400);text-decoration:none;display:flex;align-items:center;gap:0.4rem;}
.back-link:hover{color:var(--white);}
main{flex:1;display:flex;align-items:center;justify-content:center;padding:2rem;}
.card{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:20px;padding:2.5rem;width:100%;max-width:560px;backdrop-filter:blur(10px);}
.card-header{text-align:center;margin-bottom:2rem;}
.card-icon{width:60px;height:60px;background:var(--grad);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin:0 auto 1rem;}
.card-header h1{font-family:'Syne',sans-serif;font-size:1.6rem;font-weight:700;margin-bottom:0.4rem;}
.card-header p{color:var(--gray-400);font-size:0.875rem;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
.form-group{margin-bottom:1.1rem;}
label{display:block;font-size:0.8rem;font-weight:500;color:var(--gray-400);margin-bottom:0.4rem;letter-spacing:0.02em;}
label .req{color:var(--cyan);}
input,select{width:100%;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.12);border-radius:8px;padding:0.7rem 1rem;color:var(--white);font-size:0.9rem;font-family:'DM Sans',sans-serif;outline:none;transition:border-color 0.2s;}
input::placeholder{color:rgba(255,255,255,0.25);}
input:focus,select:focus{border-color:var(--blue);background:rgba(26,86,232,0.08);}
select option{background:#1e293b;}
.btn{width:100%;padding:0.85rem;background:var(--grad);color:var(--white);border:none;border-radius:10px;font-size:1rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:opacity 0.2s,transform 0.2s;margin-top:0.5rem;}
.btn:hover{opacity:0.9;transform:translateY(-1px);}
.msg{padding:0.85rem 1rem;border-radius:8px;font-size:0.875rem;margin-bottom:1rem;}
.msg.error{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#fca5a5;}
.msg.success{background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);color:#6ee7b7;}
.footer-link{text-align:center;margin-top:1.25rem;font-size:0.875rem;color:var(--gray-400);}
.footer-link a{color:var(--cyan);text-decoration:none;}
.footer-link a:hover{text-decoration:underline;}
.divider{height:1px;background:rgba(255,255,255,0.08);margin:1.25rem 0;}
@media(max-width:480px){.form-row{grid-template-columns:1fr;}}
</style>
<link rel="stylesheet" href="theme.css">
<script src="theme.js"></script>
</head>
<body>
<div class="bg"></div>
<div class="page">
  <header>
    <a class="logo" href="index.php">Safe<span>Speak</span></a>
    <a class="back-link" href="index.php">← Back to Home</a>
  </header>
  <main>
    <div class="card">
      <div class="card-header">
        <div class="card-icon">🎓</div>
        <h1>Student Registration</h1>
        <p>Create your account to submit and track reports</p>
      </div>

      <?php if ($error): ?><div class="msg error">⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <?php if ($success): ?><div class="msg success">✅ <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?> <a href="login.php" style="color:#6ee7b7;">Login now →</a></div><?php endif; ?>

      <?php if (!$success): ?>
      <form method="POST" novalidate>
        <div class="form-row">
          <div class="form-group">
            <label>Full Name <span class="req">*</span></label>
            <input type="text" name="full_name" placeholder="Your full name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Student ID <span class="req">*</span></label>
            <input type="text" name="student_id" placeholder="e.g. 011221157" required value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Email Address <span class="req">*</span></label>
          <input type="email" name="email" placeholder="student@university.bd" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Department</label>
            <input type="text" name="department" placeholder="e.g. CSE" value="<?= htmlspecialchars($_POST['department'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Batch / Year</label>
            <input type="text" name="batch" placeholder="e.g. 2022" value="<?= htmlspecialchars($_POST['batch'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Institution / University</label>
          <input type="text" name="institution" placeholder="Your university name" value="<?= htmlspecialchars($_POST['institution'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Phone (optional)</label>
          <input type="text" name="phone" placeholder="+880..." value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>
        <div class="divider"></div>
        <div class="form-row">
          <div class="form-group">
            <label>Password <span class="req">*</span></label>
            <input type="password" name="password" placeholder="Min 6 characters" required>
          </div>
          <div class="form-group">
            <label>Confirm Password <span class="req">*</span></label>
            <input type="password" name="confirm_password" placeholder="Repeat password" required>
          </div>
        </div>
        <button type="submit" class="btn">Create Account →</button>
      </form>
      <?php endif; ?>

      <div class="footer-link">Already have an account? <a href="login.php">Login here</a></div>
      <div class="footer-link" style="margin-top:0.5rem;"><a href="report_anonymous.php">📝 Report without account</a></div>
    </div>
  </main>
</div>
</body>
</html>
