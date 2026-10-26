<?php

if (!function_exists('adminH')) {
    function adminH($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('adminNavItems')) {
    function adminNavItems() {
        return [
            'dashboard' => ['href' => 'dashboard.php', 'icon' => 'fa-tachometer-alt', 'label' => 'Dashboard'],
            'reports'   => ['href' => 'reports.php',   'icon' => 'fa-flag',            'label' => 'Reports'],
            'students'  => ['href' => 'students.php',  'icon' => 'fa-user-graduate',   'label' => 'Students'],
        'student_directory' => ['href' => 'student_directory.php', 'icon' => 'fa-address-book', 'label' => 'Student Directory'],
            'admins'    => ['href' => 'admins.php',    'icon' => 'fa-user-shield',     'label' => 'Admins'],
            'analytics' => ['href' => 'analytics.php', 'icon' => 'fa-chart-bar',       'label' => 'Analytics'],
        'university_terms' => ['href' => 'university_terms.php', 'icon' => 'fa-file-pdf', 'label' => 'University Terms'],
            'settings'  => ['href' => 'settings.php',  'icon' => 'fa-cog',             'label' => 'Settings'],
        ];
    }
}

if (!function_exists('adminRenderHeader')) {
    function adminRenderHeader($title, $activeNav = '') {
        $title = adminH($title);
        $activeNav = (string) $activeNav;
        $adminName = adminH($_SESSION['admin_name'] ?? 'Administrator');
        $adminRole = adminH($_SESSION['admin_role'] ?? 'admin');
        $navItems = adminNavItems();

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title} - SafeSpeak</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
  --navy: #0a0e27;
  --blue: #1a56e8;
  --cyan: #06b6d4;
  --teal: #0d9488;
  --white: #fff;
  --gray-100: #f1f5f9;
  --gray-300: #cbd5e1;
  --gray-400: #94a3b8;
  --gray-600: #475569;
  --green: #10b981;
  --red: #ef4444;
  --amber: #f59e0b;
  --grad-admin: linear-gradient(135deg, #0d9488, #06b6d4);
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
  font-family: 'DM Sans', sans-serif;
  background: var(--navy);
  color: var(--white);
  min-height: 100vh;
}
.bg {
  position: fixed;
  inset: 0;
  background: radial-gradient(ellipse 70% 60% at 70% 20%, rgba(13, 148, 136, 0.2), transparent 60%), var(--navy);
  z-index: 0;
}
.page { position: relative; z-index: 1; min-height: 100vh; display: flex; flex-direction: column; }
.admin-header {
  padding: 1.1rem 1.8rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid rgba(255, 255, 255, 0.07);
  background: rgba(10, 14, 39, 0.88);
  backdrop-filter: blur(10px);
}
.logo {
  font-family: 'Syne', sans-serif;
  font-size: 1.3rem;
  font-weight: 800;
  text-decoration: none;
  color: var(--white);
}
.logo span { color: var(--cyan); }
.admin-info { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; justify-content: flex-end; }
.admin-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  background: rgba(13, 148, 136, 0.15);
  border: 1px solid rgba(13, 148, 136, 0.3);
  padding: 0.35rem 0.8rem;
  border-radius: 100px;
  font-size: 0.78rem;
  color: #5eead4;
}
.logout-btn {
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.35);
  color: #fecaca;
  padding: 0.55rem 1rem;
  border-radius: 10px;
  text-decoration: none;
  font-size: 0.88rem;
  font-weight: 600;
}
.main-content { display: flex; flex: 1; min-height: 0; }
.sidebar {
  width: 250px;
  background: rgba(255, 255, 255, 0.03);
  border-right: 1px solid rgba(255, 255, 255, 0.07);
  padding: 1.2rem 0;
  flex-shrink: 0;
}
.sidebar-nav { display: flex; flex-direction: column; gap: 0.45rem; padding: 0 0.85rem; }
.nav-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 0.9rem;
  color: var(--gray-400);
  text-decoration: none;
  border-radius: 10px;
  font-weight: 500;
}
.nav-item:hover { background: rgba(255, 255, 255, 0.06); color: var(--white); }
.nav-item.active { background: var(--grad-admin); color: var(--white); }
.content {
  flex: 1;
  padding: 1.7rem;
  overflow-y: auto;
}
.page-title { font-family: 'Syne', sans-serif; font-size: 1.8rem; margin-bottom: 0.3rem; }
.page-subtitle { color: var(--gray-400); margin-bottom: 1.2rem; }
.panel {
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 14px;
  padding: 1rem;
}
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 0.9rem;
}
.stat-card {
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 0.9rem 1rem;
}
.stat-label { font-size: 0.8rem; color: var(--gray-400); margin-bottom: 0.3rem; }
.stat-value { font-family: 'Syne', sans-serif; font-size: 1.6rem; font-weight: 700; }
.msg {
  margin-bottom: 0.9rem;
  padding: 0.75rem 0.95rem;
  border-radius: 10px;
  border: 1px solid transparent;
}
.msg.success { background: rgba(16, 185, 129, 0.12); border-color: rgba(16, 185, 129, 0.35); color: #86efac; }
.msg.error { background: rgba(239, 68, 68, 0.12); border-color: rgba(239, 68, 68, 0.35); color: #fecaca; }
.form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.65rem; margin-bottom: 0.75rem; }
input, select, textarea, button {
  font-family: inherit;
}
input, select, textarea {
  width: 100%;
  background: rgba(255, 255, 255, 0.07);
  color: var(--white);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 9px;
  padding: 0.58rem 0.72rem;
}
select {
  appearance: none;
  background-color: rgba(255, 255, 255, 0.07);
}
select option {
  background-color: #0f172a;
  color: var(--white);
}
input::placeholder, textarea::placeholder { color: var(--gray-400); }
textarea { resize: vertical; min-height: 84px; }
.btn {
  background: var(--grad-admin);
  color: var(--white);
  border: none;
  border-radius: 9px;
  padding: 0.56rem 0.86rem;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  cursor: pointer;
  font-weight: 600;
  font-size: 0.86rem;
}
.btn.secondary {
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.15);
}
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 0.62rem 0.48rem; border-bottom: 1px solid rgba(255, 255, 255, 0.08); vertical-align: top; }
th { color: var(--gray-300); font-size: 0.8rem; font-weight: 700; }
td { color: var(--white); font-size: 0.88rem; }
.chip {
  display: inline-block;
  padding: 0.23rem 0.5rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
}
.chip.pending { background: rgba(245, 158, 11, 0.18); color: #fcd34d; }
.chip.under_review { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
.chip.investigating { background: rgba(236, 72, 153, 0.17); color: #f9a8d4; }
.chip.resolved { background: rgba(16, 185, 129, 0.18); color: #86efac; }
.chip.rejected { background: rgba(239, 68, 68, 0.2); color: #fecaca; }
.chip.escalated { background: rgba(244, 63, 94, 0.2); color: #fda4af; }
.chip.low { background: rgba(16, 185, 129, 0.18); color: #86efac; }
.chip.medium { background: rgba(245, 158, 11, 0.18); color: #fcd34d; }
.chip.high { background: rgba(239, 68, 68, 0.2); color: #fecaca; }
.chip.critical { background: rgba(220, 38, 38, 0.22); color: #fecaca; }
.muted { color: var(--gray-400); font-size: 0.82rem; }
.space-top { margin-top: 1rem; }
.space-bottom { margin-bottom: 1rem; }
@media (max-width: 860px) {
  .main-content { flex-direction: column; }
  .sidebar { width: 100%; border-right: none; border-bottom: 1px solid rgba(255, 255, 255, 0.07); }
  .sidebar-nav { flex-direction: row; overflow-x: auto; }
}
</style>
<link rel="icon" type="image/svg+xml" href="../assets/logo.svg">
<link rel="stylesheet" href="../theme.css">
<script src="../theme.js"></script>
</head>
<body>
<div class="bg"></div>
<div class="page">
  <header class="admin-header">
    <a class="logo" href="../index.php"><img class="brand-logo" src="../assets/logo.svg" alt="" width="30" height="30">Safe<span>Speak</span></a>
    <div class="admin-info">
      <div class="admin-badge"><i class="fas fa-shield-alt"></i> {$adminRole}</div>
      <div><i class="fas fa-user-circle"></i> {$adminName}</div>
      <a href="../../backend/admin/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </header>
  <div class="main-content">
    <nav class="sidebar">
      <div class="sidebar-nav">
HTML;

        foreach ($navItems as $key => $item) {
            $activeClass = $key === $activeNav ? 'active' : '';
            $href = adminH($item['href']);
            $icon = adminH($item['icon']);
            $label = adminH($item['label']);
            echo "<a href=\"{$href}\" class=\"nav-item {$activeClass}\"><i class=\"fas {$icon}\"></i> {$label}</a>";
        }

        echo <<<HTML
      </div>
    </nav>
    <main class="content">
      <h1 class="page-title">{$title}</h1>
HTML;
    }
}

if (!function_exists('adminRenderSubTitle')) {
    function adminRenderSubTitle($subtitle) {
        echo '<p class="page-subtitle">' . adminH($subtitle) . '</p>';
    }
}

if (!function_exists('adminRenderFlash')) {
    function adminRenderFlash($successMessage, $errorMessage) {
        if ($successMessage) {
            echo '<div class="msg success">' . adminH($successMessage) . '</div>';
        }
        if ($errorMessage) {
            echo '<div class="msg error">' . adminH($errorMessage) . '</div>';
        }
    }
}

if (!function_exists('adminRenderFooter')) {
    function adminRenderFooter() {
        $year = date('Y');
        echo <<<HTML
    </main>
  </div>
</div>
<footer style="padding:1rem;text-align:center;color:#94a3b8;border-top:1px solid rgba(255,255,255,0.08);">
  SafeSpeak Admin Panel - {$year}
</footer>
</body>
</html>
HTML;
    }
}
