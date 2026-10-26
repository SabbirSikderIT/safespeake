<?php
require_once __DIR__ . '/../backend/includes/config.php';
requireStudentLogin();

$db = getDB();
$student_id = $_SESSION['student_id'];

// Get student's reports
$stmt = $db->prepare("SELECT * FROM reports WHERE student_id = ? ORDER BY created_at DESC");
$stmt->execute([$student_id]);
$reports = $stmt->fetchAll();

// Stats
$total = count($reports);
$pending = count(array_filter($reports, fn($r) => $r['status'] === 'pending'));
$resolved = count(array_filter($reports, fn($r) => $r['status'] === 'resolved'));
$active = count(array_filter($reports, fn($r) => in_array($r['status'], ['under_review','investigating','escalated'])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard — SafeSpeak</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
:root{--navy:#0a0e27;--blue:#1a56e8;--cyan:#06b6d4;--white:#fff;--gray-50:#f8fafc;--gray-100:#f1f5f9;--gray-200:#e2e8f0;--gray-400:#94a3b8;--gray-600:#475569;--gray-800:#1e293b;--grad:linear-gradient(135deg,#1a56e8,#06b6d4);--green:#10b981;--amber:#f59e0b;--red:#ef4444;--sidebar:240px;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:#0d1330;color:var(--white);min-height:100vh;display:flex;}
/* SIDEBAR */
.sidebar{width:var(--sidebar);background:rgba(10,14,39,0.95);border-right:1px solid rgba(255,255,255,0.07);display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:100;transition:transform 0.3s;}
.sidebar-header{padding:1.5rem;border-bottom:1px solid rgba(255,255,255,0.07);}
.sidebar-logo{font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:800;}
.sidebar-logo span{color:var(--cyan);}
.sidebar-user{margin-top:0.75rem;font-size:0.75rem;color:var(--gray-400);}
.sidebar-user strong{color:var(--white);display:block;font-size:0.85rem;}
.sidebar-nav{flex:1;padding:1rem 0;overflow-y:auto;}
.nav-section{padding:0.25rem 1rem;font-size:0.7rem;font-weight:600;color:var(--gray-600);letter-spacing:0.08em;text-transform:uppercase;margin-top:0.75rem;}
.nav-item{display:flex;align-items:center;gap:0.6rem;padding:0.65rem 1rem;margin:0.1rem 0.75rem;border-radius:8px;text-decoration:none;color:var(--gray-400);font-size:0.875rem;transition:all 0.2s;cursor:pointer;}
.nav-item:hover,.nav-item.active{background:rgba(26,86,232,0.15);color:var(--white);}
.nav-item.active{border-left:3px solid var(--blue);}
.nav-item .icon{font-size:1rem;width:20px;text-align:center;}
.sidebar-footer{padding:1rem;border-top:1px solid rgba(255,255,255,0.07);}
.logout-btn{display:flex;align-items:center;gap:0.6rem;padding:0.65rem 1rem;border-radius:8px;color:var(--gray-400);font-size:0.875rem;text-decoration:none;transition:all 0.2s;width:100%;}
.logout-btn:hover{background:rgba(239,68,68,0.1);color:#f87171;}
/* MAIN */
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh;}
.topbar{padding:1rem 2rem;border-bottom:1px solid rgba(255,255,255,0.07);display:flex;align-items:center;justify-content:space-between;background:rgba(10,14,39,0.6);backdrop-filter:blur(10px);position:sticky;top:0;z-index:50;}
.topbar h2{font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:700;}
.topbar-actions{display:flex;gap:0.75rem;align-items:center;}
.btn{padding:0.6rem 1.2rem;border-radius:8px;font-size:0.85rem;font-weight:500;cursor:pointer;text-decoration:none;border:none;transition:all 0.2s;display:inline-flex;align-items:center;gap:0.4rem;font-family:'DM Sans',sans-serif;}
.btn-primary{background:var(--grad);color:var(--white);}
.btn-primary:hover{opacity:0.9;transform:translateY(-1px);}
.btn-outline{background:transparent;border:1.5px solid rgba(255,255,255,0.15);color:var(--white);}
.btn-outline:hover{border-color:var(--blue);}
.content{padding:2rem;flex:1;}
/* STATS */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:2rem;}
.stat-card{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:1.25rem;}
.stat-card .stat-icon{font-size:1.5rem;margin-bottom:0.5rem;}
.stat-card .stat-num{font-family:'Syne',sans-serif;font-size:1.8rem;font-weight:800;}
.stat-card .stat-label{font-size:0.78rem;color:var(--gray-400);margin-top:0.2rem;}
.stat-card.blue .stat-num{color:#60a5fa;}
.stat-card.amber .stat-num{color:#fbbf24;}
.stat-card.green .stat-num{color:var(--green);}
.stat-card.cyan .stat-num{color:var(--cyan);}
/* REPORTS TABLE */
.section-card{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:16px;overflow:hidden;margin-bottom:2rem;}
.section-header{padding:1.25rem 1.5rem;border-bottom:1px solid rgba(255,255,255,0.07);display:flex;align-items:center;justify-content:space-between;}
.section-header h3{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;}
table{width:100%;border-collapse:collapse;}
th{padding:0.75rem 1rem;text-align:left;font-size:0.75rem;font-weight:600;color:var(--gray-600);border-bottom:1px solid rgba(255,255,255,0.06);background:rgba(0,0,0,0.15);text-transform:uppercase;letter-spacing:0.05em;}
td{padding:0.85rem 1rem;font-size:0.875rem;border-bottom:1px solid rgba(255,255,255,0.04);color:var(--gray-200);vertical-align:middle;}
tr:hover td{background:rgba(255,255,255,0.02);}
tr:last-child td{border-bottom:none;}
.badge{display:inline-flex;align-items:center;gap:0.25rem;padding:0.3rem 0.65rem;border-radius:6px;font-size:0.75rem;font-weight:600;}
.badge-high,.status-escalated{background:rgba(239,68,68,0.15);color:#f87171;}
.badge-critical{background:rgba(239,68,68,0.25);color:#fca5a5;}
.badge-medium{background:rgba(245,158,11,0.15);color:#fbbf24;}
.badge-low{background:rgba(16,185,129,0.15);color:#6ee7b7;}
.status-pending{background:rgba(245,158,11,0.15);color:#fbbf24;}
.status-review{background:rgba(59,130,246,0.15);color:#93c5fd;}
.status-investigating{background:rgba(139,92,246,0.15);color:#c4b5fd;}
.status-resolved{background:rgba(16,185,129,0.15);color:#6ee7b7;}
.status-rejected{background:rgba(100,116,139,0.15);color:#94a3b8;}
.tracking-id{font-family:monospace;font-size:0.8rem;color:var(--cyan);}
.empty-state{text-align:center;padding:3rem;color:var(--gray-600);}
.empty-state .icon{font-size:3rem;margin-bottom:1rem;}
/* Hamburger */
.hamburger{display:none;flex-direction:column;gap:4px;cursor:pointer;padding:0.4rem;}
.hamburger span{width:22px;height:2px;background:var(--white);border-radius:2px;}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%);}
  .sidebar.open{transform:translateX(0);}
  .main{margin-left:0;}
  .hamburger{display:flex;}
  .content{padding:1rem;}
  .stats-grid{grid-template-columns:1fr 1fr;}
}
</style>
<link rel="stylesheet" href="theme.css">
<script src="theme.js"></script>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">Safe<span>Speak</span></div>
    <div class="sidebar-user">
      <strong><?= htmlspecialchars($_SESSION['student_name']) ?></strong>
      ID: <?= htmlspecialchars($_SESSION['student_sid']) ?>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section">Main</div>
    <a class="nav-item active" href="dashboard.php"><span class="icon">📊</span> Dashboard</a>
    <a class="nav-item" href="submit_report.php"><span class="icon">📝</span> Submit Report</a>
    <a class="nav-item" href="my_reports.php"><span class="icon">📋</span> My Reports</a>
    <div class="nav-section">Tools</div>
    <a class="nav-item" href="report_status.php"><span class="icon">🔍</span> Track by ID</a>
    <a class="nav-item" href="profile.php"><span class="icon">👤</span> My Profile</a>
  </nav>
  <div class="sidebar-footer">
    <a class="logout-btn" href="../backend/logout.php">🚪 Logout</a>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:1rem;">
      <div class="hamburger" onclick="toggleSidebar()"><span></span><span></span><span></span></div>
      <h2>Student Dashboard</h2>
    </div>
    <div class="topbar-actions">
      <a href="submit_report.php" class="btn btn-primary">+ New Report</a>
    </div>
  </div>

  <div class="content">
    <!-- Welcome -->
    <div style="margin-bottom:1.75rem;">
      <h1 style="font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;">Welcome back, <?= htmlspecialchars(explode(' ', $_SESSION['student_name'])[0]) ?>! 👋</h1>
      <p style="color:var(--gray-400);font-size:0.9rem;margin-top:0.3rem;">Here's an overview of your reports and activities.</p>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card blue">
        <div class="stat-icon">📋</div>
        <div class="stat-num"><?= $total ?></div>
        <div class="stat-label">Total Reports</div>
      </div>
      <div class="stat-card amber">
        <div class="stat-icon">⏳</div>
        <div class="stat-num"><?= $pending ?></div>
        <div class="stat-label">Pending</div>
      </div>
      <div class="stat-card cyan">
        <div class="stat-icon">🔍</div>
        <div class="stat-num"><?= $active ?></div>
        <div class="stat-label">Under Review</div>
      </div>
      <div class="stat-card green">
        <div class="stat-icon">✅</div>
        <div class="stat-num"><?= $resolved ?></div>
        <div class="stat-label">Resolved</div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:2rem;">
      <a href="submit_report.php" style="background:var(--grad);border-radius:12px;padding:1.25rem;text-decoration:none;color:var(--white);display:flex;align-items:center;gap:0.75rem;transition:opacity 0.2s;">
        <span style="font-size:1.5rem;">📝</span><div><div style="font-weight:600;font-size:0.9rem;">Submit New Report</div><div style="font-size:0.75rem;opacity:0.8;">Anonymous or identified</div></div>
      </a>
      <a href="report_status.php" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:1.25rem;text-decoration:none;color:var(--white);display:flex;align-items:center;gap:0.75rem;transition:border-color 0.2s;" onmouseover="this.style.borderColor='rgba(26,86,232,0.5)'" onmouseout="this.style.borderColor='rgba(255,255,255,0.1)'">
        <span style="font-size:1.5rem;">🔍</span><div><div style="font-weight:600;font-size:0.9rem;">Track by ID</div><div style="font-size:0.75rem;color:var(--gray-400);">Enter tracking ID</div></div>
      </a>
    </div>

    <!-- Recent Reports -->
    <div class="section-card">
      <div class="section-header">
        <h3>My Recent Reports</h3>
        <a href="my_reports.php" class="btn btn-outline">View All</a>
      </div>
      <?php if (empty($reports)): ?>
      <div class="empty-state">
        <div class="icon">📋</div>
        <div style="font-size:1rem;font-weight:600;margin-bottom:0.5rem;">No reports yet</div>
        <div style="color:var(--gray-600);font-size:0.875rem;">Submit your first report to get started</div>
        <a href="submit_report.php" class="btn btn-primary" style="margin-top:1rem;">Submit Report</a>
      </div>
      <?php else: ?>
      <div style="overflow-x:auto;">
        <table>
          <thead><tr>
            <th>Tracking ID</th><th>Category</th><th>Title</th><th>Priority</th><th>Status</th><th>AI Spam</th><th>Date</th><th>Action</th>
          </tr></thead>
          <tbody>
          <?php foreach (array_slice($reports, 0, 5) as $r): ?>
          <tr>
            <td><span class="tracking-id"><?= htmlspecialchars($r['tracking_id'], ENT_QUOTES, 'UTF-8') ?></span></td>
            <td><?= ucwords(str_replace('_',' ',$r['category'])) ?></td>
            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($r['title']) ?>"><?= htmlspecialchars(mb_strimwidth($r['title'],0,40,'...')) ?></td>
            <td><?= getPriorityBadge($r['priority']) ?></td>
            <td><?= getStatusBadge($r['status']) ?></td>
            <td><?= $r['ai_is_spam'] ? '<span class="badge badge-high">⚠ Spam</span>' : '<span class="badge badge-low">✓ OK</span>' ?></td>
            <td style="color:var(--gray-400);font-size:0.8rem;"><?= timeAgo($r['created_at']) ?></td>
            <td><a href="report_status.php?id=<?= urlencode($r['tracking_id']) ?>" class="btn btn-outline" style="padding:0.35rem 0.75rem;font-size:0.78rem;">View</a></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Info Box -->
    <div style="background:rgba(6,182,212,0.08);border:1px solid rgba(6,182,212,0.2);border-radius:12px;padding:1.25rem;font-size:0.875rem;color:var(--gray-400);">
      <strong style="color:var(--cyan);">🔒 Privacy Notice:</strong> All reports submitted anonymously keep your identity fully protected. Even our administrators cannot link anonymous reports to your account.
    </div>
  </div>
</div>

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}
</script>
</body>
</html>
