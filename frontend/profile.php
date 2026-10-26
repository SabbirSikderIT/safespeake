<?php
require_once __DIR__ . '/../backend/includes/config.php';
requireStudentLogin();
$db = getDB();
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name   = sanitize($_POST['full_name'] ?? '');
    $department  = sanitize($_POST['department'] ?? '');
    $batch       = sanitize($_POST['batch'] ?? '');
    $institution = sanitize($_POST['institution'] ?? '');
    $phone       = sanitize($_POST['phone'] ?? '');
    $new_pass    = $_POST['new_password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';

    if (!$full_name) { $error = 'Name cannot be empty.'; }
    elseif ($new_pass && strlen($new_pass) < 6) { $error = 'New password must be at least 6 characters.'; }
    elseif ($new_pass && $new_pass !== $confirm) { $error = 'Passwords do not match.'; }
    else {
        if ($new_pass) {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $db->prepare("UPDATE students SET full_name=?,department=?,batch=?,institution=?,phone=?,password=? WHERE id=?")->execute([$full_name,$department,$batch,$institution,$phone,$hashed,$_SESSION['student_id']]);
        } else {
            $db->prepare("UPDATE students SET full_name=?,department=?,batch=?,institution=?,phone=? WHERE id=?")->execute([$full_name,$department,$batch,$institution,$phone,$_SESSION['student_id']]);
        }
        $_SESSION['student_name'] = $full_name;
        $success = 'Profile updated successfully!';
    }
}

$stmt = $db->prepare("SELECT * FROM students WHERE id=?");
$stmt->execute([$_SESSION['student_id']]);
$student = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile — SafeSpeak</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
:root{--navy:#0a0e27;--blue:#1a56e8;--cyan:#06b6d4;--white:#fff;--gray-400:#94a3b8;--gray-600:#475569;--grad:linear-gradient(135deg,#1a56e8,#06b6d4);--green:#10b981;--red:#ef4444;--sidebar:240px;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:#0d1330;color:var(--white);min-height:100vh;display:flex;}
.sidebar{width:var(--sidebar);background:rgba(10,14,39,0.95);border-right:1px solid rgba(255,255,255,0.07);display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:100;transition:transform 0.3s;}
.sidebar-header{padding:1.5rem;border-bottom:1px solid rgba(255,255,255,0.07);}
.sidebar-logo{font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:800;}
.sidebar-logo span{color:var(--cyan);}
.sidebar-user{margin-top:0.75rem;font-size:0.75rem;color:var(--gray-400);}
.sidebar-user strong{color:var(--white);display:block;font-size:0.85rem;}
.sidebar-nav{flex:1;padding:1rem 0;}
.nav-section{padding:0.25rem 1rem;font-size:0.7rem;font-weight:600;color:var(--gray-600);letter-spacing:0.08em;text-transform:uppercase;margin-top:0.75rem;}
.nav-item{display:flex;align-items:center;gap:0.6rem;padding:0.65rem 1rem;margin:0.1rem 0.75rem;border-radius:8px;text-decoration:none;color:var(--gray-400);font-size:0.875rem;transition:all 0.2s;}
.nav-item:hover,.nav-item.active{background:rgba(26,86,232,0.15);color:var(--white);}
.nav-item.active{border-left:3px solid var(--blue);}
.nav-item .icon{font-size:1rem;width:20px;text-align:center;}
.sidebar-footer{padding:1rem;border-top:1px solid rgba(255,255,255,0.07);}
.logout-btn{display:flex;align-items:center;gap:0.6rem;padding:0.65rem 1rem;border-radius:8px;color:var(--gray-400);font-size:0.875rem;text-decoration:none;transition:all 0.2s;}
.logout-btn:hover{background:rgba(239,68,68,0.1);color:#f87171;}
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;}
.topbar{padding:1rem 2rem;border-bottom:1px solid rgba(255,255,255,0.07);display:flex;align-items:center;justify-content:space-between;background:rgba(10,14,39,0.6);}
.topbar h2{font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:700;}
.content{padding:2rem;max-width:600px;}
.form-card{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:16px;padding:2rem;margin-bottom:1.5rem;}
.form-card h3{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;margin-bottom:1.25rem;color:var(--cyan);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
.form-group{margin-bottom:1.1rem;}
label{display:block;font-size:0.8rem;font-weight:500;color:var(--gray-400);margin-bottom:0.4rem;}
input{width:100%;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.12);border-radius:8px;padding:0.75rem 1rem;color:var(--white);font-size:0.9rem;font-family:'DM Sans',sans-serif;outline:none;transition:border-color 0.2s;}
input::placeholder{color:rgba(255,255,255,0.25);}
input:focus{border-color:var(--blue);}
input:disabled{opacity:0.5;cursor:not-allowed;}
.btn-submit{padding:0.85rem 2rem;background:var(--grad);color:var(--white);border:none;border-radius:10px;font-size:0.95rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:opacity 0.2s;}
.btn-submit:hover{opacity:0.9;}
.msg{padding:0.85rem 1rem;border-radius:8px;font-size:0.875rem;margin-bottom:1rem;}
.msg.error{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#fca5a5;}
.msg.success{background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);color:#6ee7b7;}
.avatar{width:80px;height:80px;border-radius:50%;background:var(--grad);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;margin-bottom:1rem;font-family:'Syne',sans-serif;}
.hamburger{display:none;flex-direction:column;gap:4px;cursor:pointer;padding:0.4rem;}
.hamburger span{width:22px;height:2px;background:var(--white);border-radius:2px;}
@media(max-width:768px){.sidebar{transform:translateX(-100%);}.sidebar.open{transform:translateX(0);}.main{margin-left:0;}.hamburger{display:flex;}.content{padding:1rem;}.form-row{grid-template-columns:1fr;}}
</style>
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<link rel="stylesheet" href="theme.css">
<script src="theme.js"></script>
</head>
<body>
<div class="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo"><img class="brand-logo" src="assets/logo.svg" alt="" width="30" height="30">Safe<span>Speak</span></div>
    <div class="sidebar-user">Logged in as:<br><strong><?= htmlspecialchars($_SESSION['student_name']) ?></strong></div>
  </div>
  <nav class="sidebar-nav">
    <a class="nav-item" href="dashboard.php"><span class="icon">🏠</span> Dashboard</a>
    <a class="nav-item" href="submit_report.php"><span class="icon">📝</span> Submit Report</a>
    <a class="nav-item" href="my_reports.php"><span class="icon">📄</span> My Reports</a>
    <a class="nav-item active" href="profile.php"><span class="icon">👤</span> My Profile</a>
  </nav>
  <div class="sidebar-footer">
    <a class="logout-btn" href="../backend/logout.php"><span class="icon">🚪</span> Logout</a>
  </div>
</div>
<div class="main">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:1rem;">
      <div class="hamburger" onclick="document.getElementById('sidebar').classList.toggle('open')"><span></span><span></span><span></span></div>
      <h2>My Profile</h2>
    </div>
  </div>
  <div class="content">
    <?php if ($error): ?><div class="msg error">⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($success): ?><div class="msg success">✅ <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <div class="form-card">
      <div class="avatar"><?= strtoupper(substr($student['full_name'], 0, 1)) ?></div>
      <h3>👤 Personal Information</h3>
      <form method="POST">
        <div class="form-row">
          <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="full_name" value="<?= htmlspecialchars($student['full_name']) ?>" required>
          </div>
          <div class="form-group">
            <label>Student ID</label>
            <input type="text" value="<?= htmlspecialchars($student['student_id']) ?>" disabled>
          </div>
        </div>
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" value="<?= htmlspecialchars($student['email']) ?>" disabled>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Department</label>
            <input type="text" name="department" value="<?= htmlspecialchars($student['department'] ?? '') ?>" placeholder="e.g. CSE">
          </div>
          <div class="form-group">
            <label>Batch / Year</label>
            <input type="text" name="batch" value="<?= htmlspecialchars($student['batch'] ?? '') ?>" placeholder="e.g. 2022">
          </div>
        </div>
        <div class="form-group">
          <label>Institution</label>
          <input type="text" name="institution" value="<?= htmlspecialchars($student['institution'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone" value="<?= htmlspecialchars($student['phone'] ?? '') ?>" placeholder="+880...">
        </div>

        <div style="height:1px;background:rgba(255,255,255,0.08);margin:1.25rem 0;"></div>
        <h3 style="margin-bottom:1rem;">🔑 Change Password (leave blank to keep current)</h3>
        <div class="form-row">
          <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password" placeholder="Min 6 characters">
          </div>
          <div class="form-group">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" placeholder="Repeat">
          </div>
        </div>
        <button type="submit" class="btn-submit">Save Changes</button>
      </form>
    </div>

    <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:1.25rem;font-size:0.875rem;color:var(--gray-400);">
      <strong style="color:var(--white);">Account Info</strong><br>
      Member since: <?= date('F j, Y', strtotime($student['created_at'])) ?> •
      Last login: <?= $student['last_login'] ? date('M j, Y H:i', strtotime($student['last_login'])) : 'N/A' ?>
    </div>
  </div>
</div>
</body>
</html>
