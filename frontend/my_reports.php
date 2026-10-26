<?php
require_once __DIR__ . '/../backend/includes/config.php';
requireStudentLogin();
$db = getDB();
$stmt = $db->prepare("SELECT * FROM reports WHERE student_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['student_id']]);
$reports = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Reports — SafeSpeak</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
:root{--navy:#0a0e27;--blue:#1a56e8;--cyan:#06b6d4;--white:#fff;--gray-400:#94a3b8;--gray-600:#475569;--grad:linear-gradient(135deg,#1a56e8,#06b6d4);--green:#10b981;--sidebar:240px;}
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
.content{padding:2rem;}
.btn{padding:0.6rem 1.2rem;border-radius:8px;font-size:0.85rem;font-weight:500;cursor:pointer;text-decoration:none;border:none;transition:all 0.2s;display:inline-flex;align-items:center;gap:0.4rem;font-family:'DM Sans',sans-serif;}
.btn-primary{background:var(--grad);color:var(--white);}
.btn-outline{background:transparent;border:1.5px solid rgba(255,255,255,0.15);color:var(--white);}
.report-card{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:16px;padding:1.5rem;margin-bottom:1.25rem;transition:border-color 0.2s;}
.report-card:hover{border-color:rgba(26,86,232,0.3);}
.report-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:0.75rem;}
.report-title{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;}
.report-meta{display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;}
.badge{display:inline-flex;align-items:center;gap:0.25rem;padding:0.3rem 0.65rem;border-radius:6px;font-size:0.75rem;font-weight:600;}
.badge-high{background:rgba(239,68,68,0.15);color:#f87171;}
.badge-medium{background:rgba(245,158,11,0.15);color:#fbbf24;}
.badge-low{background:rgba(16,185,129,0.15);color:#6ee7b7;}
.status-pending{background:rgba(245,158,11,0.15);color:#fbbf24;}
.status-review,.status-investigating{background:rgba(59,130,246,0.15);color:#93c5fd;}
.status-resolved{background:rgba(16,185,129,0.15);color:#6ee7b7;}
.status-rejected{background:rgba(100,116,139,0.15);color:#94a3b8;}
.status-escalated{background:rgba(239,68,68,0.15);color:#f87171;}
.report-body{font-size:0.875rem;color:var(--gray-400);line-height:1.6;margin-bottom:1rem;}
.report-footer{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;}
.ai-insights{background:rgba(6,182,212,0.08);border:1px solid rgba(6,182,212,0.15);border-radius:8px;padding:0.6rem 0.9rem;font-size:0.78rem;color:var(--gray-400);display:flex;gap:1rem;flex-wrap:wrap;}
.ai-insights span strong{color:var(--cyan);}
.tracking-id{font-family:monospace;font-size:0.85rem;color:var(--cyan);}
.empty-state{text-align:center;padding:4rem;color:var(--gray-600);}
.empty-state .icon{font-size:4rem;margin-bottom:1rem;}
.hamburger{display:none;flex-direction:column;gap:4px;cursor:pointer;padding:0.4rem;}
.hamburger span{width:22px;height:2px;background:var(--white);border-radius:2px;}
@media(max-width:768px){.sidebar{transform:translateX(-100%);}.sidebar.open{transform:translateX(0);}.main{margin-left:0;}.hamburger{display:flex;}.content{padding:1rem;}}
</style>
<link rel="stylesheet" href="theme.css">
<script src="theme.js"></script>
</head>
<body>
<div class="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">Safe<span>Speak</span></div>
    <div class="sidebar-user">Logged in as:<br><strong><?= htmlspecialchars($_SESSION['student_name']) ?></strong></div>
  </div>
  <nav class="sidebar-nav">
    <a class="nav-item" href="dashboard.php"><span class="icon">🏠</span> Dashboard</a>
    <a class="nav-item" href="submit_report.php"><span class="icon">📝</span> Submit Report</a>
    <a class="nav-item active" href="my_reports.php"><span class="icon">📄</span> My Reports</a>
    <a class="nav-item" href="profile.php"><span class="icon">👤</span> My Profile</a>
  </nav>
  <div class="sidebar-footer">
    <a class="logout-btn" href="../backend/logout.php"><span class="icon">🚪</span> Logout</a>
  </div>
</div>

<div class="main">
  <div class="topbar">
    <h2>My Reports</h2>
    <a href="submit_report.php" class="btn btn-primary">+ New Report</a>
  </div>
  <div class="content">
    <?php if (empty($reports)): ?>
    <div class="empty-state">
      <div class="icon">📋</div>
      <div style="font-size:1.1rem;font-weight:600;margin-bottom:0.5rem;">No reports yet</div>
      <div style="margin-bottom:1.5rem;">You haven't submitted any reports. Your identity-linked reports appear here.</div>
      <a href="submit_report.php" class="btn btn-primary">Submit Your First Report</a>
    </div>
    <?php else: ?>
    <?php foreach ($reports as $r): ?>
    <div class="report-card">
      <div class="report-header">
        <div>
          <div class="report-title"><?= htmlspecialchars($r['title']) ?></div>
          <div style="margin-top:0.3rem;font-size:0.8rem;color:var(--gray-600);"><?= ucwords(str_replace('_',' ',$r['category'])) ?> • <span class="tracking-id"><?= htmlspecialchars($r['tracking_id'], ENT_QUOTES, 'UTF-8') ?></span> • <?= date('M j, Y', strtotime($r['created_at'])) ?></div>
        </div>
        <div class="report-meta">
          <?= getPriorityBadge($r['priority']) ?>
          <?= getStatusBadge($r['status']) ?>
          <?= $r['is_anonymous'] ? '<span class="badge" style="background:rgba(6,182,212,0.1);color:var(--cyan);">🔒 Anonymous</span>' : '' ?>
        </div>
      </div>
      <div class="report-body"><?= htmlspecialchars(mb_strimwidth($r['description'], 0, 200, '...')) ?></div>
      <?php if ($r['ai_analysis_done']): ?>
      <div class="ai-insights">
        <span>🤖 AI: <strong>Sentiment: <?= htmlspecialchars(ucfirst(str_replace('_',' ', $r['ai_sentiment_label'] ?? 'N/A')), ENT_QUOTES, 'UTF-8') ?></strong></span>
        <span>Priority Score: <strong><?= number_format(($r['ai_priority_score'] ?? 0)*100, 0) ?>%</strong></span>
        <span>Spam Score: <strong><?= number_format(($r['ai_spam_score'] ?? 0)*100, 0) ?>%</strong></span>
        <?php if ($r['ai_is_spam']): ?><span style="color:#f87171;">⚠ Flagged as Spam</span><?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="report-footer" style="margin-top:0.75rem;">
        <div style="font-size:0.8rem;color:var(--gray-600);">Updated <?= timeAgo($r['updated_at']) ?></div>
        <a href="report_status.php?id=<?= urlencode($r['tracking_id']) ?>" class="btn btn-outline" style="padding:0.4rem 0.9rem;font-size:0.8rem;">View Full Report →</a>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
