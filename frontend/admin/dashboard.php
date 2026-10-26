<?php
require_once __DIR__ . '/../../backend/includes/config.php';
requireAdminLogin();

try {
    $db = getDB();
    $db_connected = true;
    
    // Fetch dashboard statistics
    $stats = [];
    
    // Total reports
    $stmt = $db->query("SELECT COUNT(*) as total FROM reports");
    $stats['total_reports'] = $stmt->fetch()['total'];
    
    // Pending reports
    $stmt = $db->query("SELECT COUNT(*) as total FROM reports WHERE status = 'pending'");
    $stats['pending_reports'] = $stmt->fetch()['total'];
    
    // Under review reports
    $stmt = $db->query("SELECT COUNT(*) as total FROM reports WHERE status = 'under_review'");
    $stats['review_reports'] = $stmt->fetch()['total'];
    
    // Investigating reports
    $stmt = $db->query("SELECT COUNT(*) as total FROM reports WHERE status = 'investigating'");
    $stats['investigating_reports'] = $stmt->fetch()['total'];
    
    // Resolved reports
    $stmt = $db->query("SELECT COUNT(*) as total FROM reports WHERE status = 'resolved'");
    $stats['resolved_reports'] = $stmt->fetch()['total'];
    
    // Total students
    $stmt = $db->query("SELECT COUNT(*) as total FROM students WHERE is_active = 1");
    $stats['total_students'] = $stmt->fetch()['total'];
    
    // Total admins
    $stmt = $db->query("SELECT COUNT(*) as total FROM admins WHERE is_active = 1");
    $stats['total_admins'] = $stmt->fetch()['total'];
    
    // Recent reports (last 7 days)
    $stmt = $db->query("SELECT COUNT(*) as total FROM reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['recent_reports'] = $stmt->fetch()['total'];
    
    // Critical priority reports
    $stmt = $db->query("SELECT COUNT(*) as total FROM reports WHERE priority = 'critical' AND status NOT IN ('resolved', 'rejected')");
    $stats['critical_reports'] = $stmt->fetch()['total'];
    
    // Fetch recent reports for the table
    $stmt = $db->prepare("
        SELECT r.*, s.full_name as student_name, s.student_id as student_code
        FROM reports r
        LEFT JOIN students s ON r.student_id = s.id
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_reports = $stmt->fetchAll();
    
    // Fetch reports by category for chart
    $stmt = $db->query("
        SELECT category, COUNT(*) as count
        FROM reports
        GROUP BY category
        ORDER BY count DESC
    ");
    $category_stats = $stmt->fetchAll();
    
    // Fetch reports by status for chart
    $stmt = $db->query("
        SELECT status, COUNT(*) as count
        FROM reports
        GROUP BY status
        ORDER BY FIELD(status, 'pending', 'under_review', 'investigating', 'resolved', 'rejected', 'escalated')
    ");
    $status_stats = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $db_connected = false;
    $stats = [
        'total_reports' => 0,
        'pending_reports' => 0,
        'review_reports' => 0,
        'investigating_reports' => 0,
        'resolved_reports' => 0,
        'total_students' => 0,
        'total_admins' => 0,
        'recent_reports' => 0,
        'critical_reports' => 0
    ];
    $recent_reports = [];
    $category_stats = [];
    $status_stats = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — SafeSpeak</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --navy: #0a0e27;
    --blue: #1a56e8;
    --cyan: #06b6d4;
    --teal: #0d9488;
    --white: #fff;
    --gray-100: #f1f5f9;
    --gray-400: #94a3b8;
    --gray-600: #475569;
    --gray-800: #1e293b;
    --red: #ef4444;
    --orange: #f97316;
    --yellow: #eab308;
    --green: #10b981;
    --purple: #8b5cf6;
    --grad-admin: linear-gradient(135deg, #0d9488, #06b6d4);
    --grad-card: linear-gradient(135deg, rgba(13, 148, 136, 0.1), rgba(6, 182, 212, 0.1));
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--navy);
    color: var(--white);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.bg {
    position: fixed;
    inset: 0;
    background: radial-gradient(ellipse 70% 60% at 70% 20%, rgba(13, 148, 136, 0.2), transparent 60%), var(--navy);
    z-index: 0;
}

.page {
    position: relative;
    z-index: 1;
    flex: 1;
    display: flex;
    flex-direction: column;
}

/* Header */
.admin-header {
    padding: 1.25rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    background: rgba(10, 14, 39, 0.9);
    backdrop-filter: blur(10px);
    position: sticky;
    top: 0;
    z-index: 100;
}

.logo {
    font-family: 'Syne', sans-serif;
    font-size: 1.3rem;
    font-weight: 800;
    text-decoration: none;
    color: var(--white);
}

.logo span {
    color: var(--cyan);
}

.admin-info {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.admin-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: rgba(13, 148, 136, 0.15);
    border: 1px solid rgba(13, 148, 136, 0.3);
    padding: 0.3rem 0.75rem;
    border-radius: 100px;
    font-size: 0.75rem;
    color: #5eead4;
}

.admin-name {
    font-weight: 500;
}

.logout-btn {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.85rem;
    transition: all 0.2s;
}

.logout-btn:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #fecaca;
}

/* Main Layout */
.main-content {
    display: flex;
    flex: 1;
}

/* Sidebar */
.sidebar {
    width: 240px;
    background: rgba(255, 255, 255, 0.03);
    border-right: 1px solid rgba(255, 255, 255, 0.07);
    padding: 1.5rem 0;
    flex-shrink: 0;
}

.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0 1rem;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    color: var(--gray-400);
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.2s;
}

.nav-item:hover {
    background: rgba(255, 255, 255, 0.05);
    color: var(--white);
}

.nav-item.active {
    background: var(--grad-admin);
    color: var(--white);
    font-weight: 500;
}

.nav-item i {
    width: 20px;
    text-align: center;
}

/* Content Area */
.content {
    flex: 1;
    padding: 2rem;
    overflow-y: auto;
}

.welcome-section {
    margin-bottom: 2rem;
}

.welcome-section h1 {
    font-family: 'Syne', sans-serif;
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.welcome-section p {
    color: var(--gray-400);
    font-size: 0.95rem;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2.5rem;
}

.stat-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 1.5rem;
    backdrop-filter: blur(10px);
    transition: transform 0.2s, border-color 0.2s;
}

.stat-card:hover {
    transform: translateY(-4px);
    border-color: rgba(13, 148, 136, 0.3);
}

.stat-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.stat-icon.total { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
.stat-icon.pending { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
.stat-icon.review { background: rgba(139, 92, 246, 0.15); color: #a78bfa; }
.stat-icon.investigating { background: rgba(239, 68, 68, 0.15); color: #f87171; }
.stat-icon.resolved { background: rgba(16, 185, 129, 0.15); color: #34d399; }
.stat-icon.students { background: rgba(6, 182, 212, 0.15); color: #5eead4; }
.stat-icon.admins { background: rgba(99, 102, 241, 0.15); color: #818cf8; }
.stat-icon.critical { background: rgba(220, 38, 38, 0.15); color: #f87171; }
.stat-icon.recent { background: rgba(168, 85, 247, 0.15); color: #c084fc; }

.stat-value {
    font-family: 'Syne', sans-serif;
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.85rem;
    color: var(--gray-400);
}

.stat-change {
    font-size: 0.75rem;
    color: var(--gray-400);
    margin-top: 0.5rem;
}

/* Charts & Tables Section */
.dashboard-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2.5rem;
}

.chart-card, .table-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 1.5rem;
    backdrop-filter: blur(10px);
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
}

.card-header h3 {
    font-family: 'Syne', sans-serif;
    font-size: 1.2rem;
    font-weight: 600;
}

.card-header a {
    font-size: 0.85rem;
    color: var(--cyan);
    text-decoration: none;
}

.card-header a:hover {
    text-decoration: underline;
}

/* Chart containers */
.chart-container {
    height: 240px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-400);
    font-size: 0.9rem;
}

.chart-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1rem;
}

.chart-placeholder i {
    font-size: 3rem;
    opacity: 0.5;
}

/* Table */
.reports-table {
    width: 100%;
    border-collapse: collapse;
}

.reports-table th {
    text-align: left;
    padding: 0.75rem 1rem;
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--gray-400);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.reports-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    font-size: 0.85rem;
}

.reports-table tr:hover {
    background: rgba(255, 255, 255, 0.03);
}

.tracking-id {
    font-family: monospace;
    font-weight: 500;
    color: var(--cyan);
}

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-pending { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
.status-under_review { background: rgba(139, 92, 246, 0.15); color: #a78bfa; }
.status-investigating { background: rgba(239, 68, 68, 0.15); color: #f87171; }
.status-resolved { background: rgba(16, 185, 129, 0.15); color: #34d399; }
.status-rejected { background: rgba(100, 116, 139, 0.15); color: #cbd5e1; }
.status-escalated { background: rgba(220, 38, 38, 0.15); color: #f87171; }

.priority-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

.priority-critical { background: rgba(220, 38, 38, 0.15); color: #f87171; }
.priority-high { background: rgba(239, 68, 68, 0.15); color: #f87171; }
.priority-medium { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
.priority-low { background: rgba(16, 185, 129, 0.15); color: #34d399; }

/* Quick Actions */
.quick-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-top: 2rem;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    background: var(--grad-admin);
    color: var(--white);
    border: none;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    transition: opacity 0.2s, transform 0.2s;
}

.action-btn:hover {
    opacity: 0.9;
    transform: translateY(-2px);
    color: var(--white);
}

.action-btn.secondary {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.action-btn.secondary:hover {
    background: rgba(255, 255, 255, 0.12);
}

/* Footer */
.admin-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.07);
    text-align: center;
    color: var(--gray-400);
    font-size: 0.85rem;
}

/* Responsive */
@media (max-width: 1200px) {
    .dashboard-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .main-content {
        flex-direction: column;
    }
    
    .sidebar {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    }
    
    .sidebar-nav {
        flex-direction: row;
        overflow-x: auto;
        padding: 0 1rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    }
    
    .admin-info {
        flex-wrap: wrap;
        gap: 0.75rem;
    }
}
</style>
<link rel="stylesheet" href="../theme.css">
<script src="../theme.js"></script>
</head>
<body>
<div class="bg"></div>
<div class="page">
    <header class="admin-header">
        <a class="logo" href="../index.php">Safe<span>Speak</span></a>
        <div class="admin-info">
            <div class="admin-badge">
                <i class="fas fa-shield-alt"></i>
                <?php echo htmlspecialchars($_SESSION['admin_role'] ?? 'Admin'); ?>
            </div>
            <div class="admin-name">
                <i class="fas fa-user-circle"></i>
                <?php echo htmlspecialchars($_SESSION['admin_name']); ?>
            </div>
            <a href="../../backend/admin/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <div class="main-content">
        <nav class="sidebar">
            <div class="sidebar-nav">
                <a href="dashboard.php" class="nav-item active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-flag"></i> Reports
                </a>
                <a href="students.php" class="nav-item">
                    <i class="fas fa-users"></i> Students
                </a>
                <a href="student_directory.php" class="nav-item">
                    <i class="fas fa-address-book"></i> Student Directory
                </a>
                <a href="admins.php" class="nav-item">
                    <i class="fas fa-user-shield"></i> Admins
                </a>
                <a href="analytics.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i> Analytics
                </a>
                <a href="university_terms.php" class="nav-item">
                    <i class="fas fa-file-pdf"></i> University Terms
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </div>
        </nav>

        <main class="content">
            <div class="welcome-section">
                <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['admin_name']); ?>!</h1>
                <p>Here's what's happening with your SafeSpeak platform today.</p>
                
                <?php if (!$db_connected): ?>
                <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 1rem; margin-top: 1rem; color: #fca5a5; font-size: 0.9rem;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Database Connection Issue:</strong> Statistics are showing demo data. Please ensure MySQL is running and the database is imported.
                </div>
                <?php endif; ?>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon total">
                            <i class="fas fa-flag"></i>
                        </div>
                        <div class="stat-change">+12% from last week</div>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_reports']; ?></div>
                    <div class="stat-label">Total Reports</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-change">Requires attention</div>
                    </div>
                    <div class="stat-value"><?php echo $stats['pending_reports']; ?></div>
                    <div class="stat-label">Pending Reports</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon review">
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="stat-change">Under review</div>
                    </div>
                    <div class="stat-value"><?php echo $stats['review_reports']; ?></div>
                    <div class="stat-label">Under Review</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon investigating">
                            <i class="fas fa-user-secret"></i>
                        </div>
                        <div class="stat-change">Active investigations</div>
                    </div>
                    <div class="stat-value"><?php echo $stats['investigating_reports']; ?></div>
                    <div class="stat-label">Investigating</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon resolved">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-change">Successfully resolved</div>
                    </div>
                    <div class="stat-value"><?php echo $stats['resolved_reports']; ?></div>
                    <div class="stat-label">Resolved Reports</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon students">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-change">Active students</div>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_students']; ?></div>
                    <div class="stat-label">Registered Students</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon admins">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="stat-change">Team members</div>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_admins']; ?></div>
                    <div class="stat-label">Admin Team</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon critical">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-change">Urgent attention needed</div>
                    </div>
                    <div class="stat-value"><?php echo $stats['critical_reports']; ?></div>
                    <div class="stat-label">Critical Reports</div>
                </div>
            </div>

            <!-- Charts & Tables -->
            <div class="dashboard-row">
                <div class="chart-card">
                    <div class="card-header">
                        <h3>Reports by Category</h3>
                        <a href="analytics.php">View details →</a>
                    </div>
                    <div class="chart-container">
                        <div class="chart-placeholder">
                            <i class="fas fa-chart-pie"></i>
                            <p>Category distribution chart</p>
                            <div style="text-align: left; width: 100%; padding: 1rem;">
                                <?php foreach ($category_stats as $cat): ?>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span><?php echo ucwords(str_replace('_', ' ', $cat['category'])); ?></span>
                                    <span style="color: var(--cyan); font-weight: 500;"><?php echo $cat['count']; ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="card-header">
                        <h3>Reports by Status</h3>
                        <a href="reports.php">View all →</a>
                    </div>
                    <div class="chart-container">
                        <div class="chart-placeholder">
                            <i class="fas fa-chart-bar"></i>
                            <p>Status distribution chart</p>
                            <div style="text-align: left; width: 100%; padding: 1rem;">
                                <?php foreach ($status_stats as $stat): ?>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span><?php echo ucwords(str_replace('_', ' ', $stat['status'])); ?></span>
                                    <span style="color: var(--cyan); font-weight: 500;"><?php echo $stat['count']; ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Reports Table -->
            <div class="table-card">
                <div class="card-header">
                    <h3>Recent Reports</h3>
                    <a href="reports.php">View all →</a>
                </div>
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>Tracking ID</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_reports)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem; color: var(--gray-400);">
                                No reports found.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recent_reports as $report): ?>
                        <tr>
                            <td>
                                <span class="tracking-id"><?php echo htmlspecialchars($report['tracking_id']); ?></span>
                            </td>
                            <td>
                                <?php echo htmlspecialchars(substr($report['title'], 0, 40)) . (strlen($report['title']) > 40 ? '...' : ''); ?>
                                <?php if ($report['student_name']): ?>
                                <br><small style="color: var(--gray-400);">by <?php echo htmlspecialchars($report['student_name']); ?></small>
                                <?php elseif ($report['is_anonymous']): ?>
                                <br><small style="color: var(--gray-400);">Anonymous</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo ucwords(str_replace('_', ' ', $report['category'])); ?></td>
                            <td>
                                <?php
                                $status_class = 'status-' . $report['status'];
                                echo '<span class="status-badge ' . $status_class . '">' .
                                     ucwords(str_replace('_', ' ', $report['status'])) .
                                     '</span>';
                                ?>
                            </td>
                            <td>
                                <?php
                                $priority_class = 'priority-' . $report['priority'];
                                echo '<span class="priority-badge ' . $priority_class . '">' .
                                     ucfirst($report['priority']) .
                                     '</span>';
                                ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($report['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="reports.php?filter=pending" class="action-btn">
                    <i class="fas fa-inbox"></i> Review Pending Reports
                </a>
                <a href="reports.php?filter=critical" class="action-btn secondary">
                    <i class="fas fa-exclamation-triangle"></i> Handle Critical Reports
                </a>
                <a href="students.php" class="action-btn secondary">
                    <i class="fas fa-user-plus"></i> Manage Students
                </a>
                <a href="university_terms.php" class="action-btn secondary">
                    <i class="fas fa-file-pdf"></i> University Terms
                </a>
                <a href="analytics.php" class="action-btn secondary">
                    <i class="fas fa-chart-line"></i> View Analytics
                </a>
            </div>
        </main>
    </div>

    <footer class="admin-footer">
        <p>SafeSpeak Admin Panel • <?php echo date('Y'); ?> • Last login:
        <?php
        if (isset($_SESSION['admin_last_login'])) {
            echo date('M d, Y H:i', strtotime($_SESSION['admin_last_login']));
        } else {
            echo 'Today';
        }
        ?>
        </p>
    </footer>
</div>
</body>
</html>
