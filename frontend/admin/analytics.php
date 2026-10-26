<?php
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/_ui.php';
requireAdminLogin();

$db = getDB();
ensureReportingTables();

// Get submission vs resolved vs pending data
$statusData = $db->query("
    SELECT 
        status,
        COUNT(*) AS count
    FROM reports
    GROUP BY status
")->fetchAll();

// Initialize counters
$submitted = 0;
$resolved = 0;
$pending = 0;
$under_review = 0;
$investigating = 0;
$rejected = 0;
$escalated = 0;

foreach ($statusData as $row) {
    switch ($row['status']) {
        case 'resolved': $resolved = (int)$row['count']; break;
        case 'pending': $pending = (int)$row['count']; break;
        case 'under_review': $under_review = (int)$row['count']; break;
        case 'investigating': $investigating = (int)$row['count']; break;
        case 'rejected': $rejected = (int)$row['count']; break;
        case 'escalated': $escalated = (int)$row['count']; break;
    }
}
$submitted = $resolved + $pending + $under_review + $investigating + $rejected + $escalated;
$active = $pending + $under_review + $investigating;

// Get monthly data for trend chart
$monthlyData = $db->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') AS month,
        COUNT(*) AS submitted,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved,
        SUM(CASE WHEN status IN ('pending', 'under_review', 'investigating') THEN 1 ELSE 0 END) AS active
    FROM reports
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY month
    ORDER BY month ASC
")->fetchAll();

// Fill missing months
$monthlyTrend = [];
for ($i = 11; $i >= 0; $i--) {
    $monthKey = date('Y-m', strtotime("-{$i} month"));
    $monthLabel = date('M Y', strtotime($monthKey . '-01'));
    $found = false;
    foreach ($monthlyData as $row) {
        if ($row['month'] === $monthKey) {
            $monthlyTrend[] = [
                'label' => $monthLabel,
                'submitted' => (int)$row['submitted'],
                'resolved' => (int)$row['resolved'],
                'active' => (int)$row['active']
            ];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $monthlyTrend[] = [
            'label' => $monthLabel,
            'submitted' => 0,
            'resolved' => 0,
            'active' => 0
        ];
    }
}

$maxMonthly = 1;
foreach ($monthlyTrend as $row) {
    $maxMonthly = max($maxMonthly, $row['submitted'], $row['resolved']);
}

$surveyOverview = $db->query("SELECT COUNT(*) AS total_surveys, AVG(satisfaction_score) AS avg_satisfaction, AVG(response_time_score) AS avg_response, AVG(fairness_score) AS avg_fairness FROM report_outcome_surveys")->fetch();
$resolvedTotal = (int) ($db->query("SELECT COUNT(*) AS total FROM reports WHERE status = 'resolved'")->fetch()['total'] ?? 0);
$surveyTotal = (int) ($surveyOverview['total_surveys'] ?? 0);
$surveyRate = $resolvedTotal > 0 ? round(($surveyTotal / $resolvedTotal) * 100, 1) : 0;

$surveyByDept = $db->query("SELECT COALESCE(NULLIF(s.department, ''), 'Unknown') AS department, COUNT(*) AS responses, AVG(os.satisfaction_score) AS avg_satisfaction FROM report_outcome_surveys os JOIN reports r ON os.report_id = r.id LEFT JOIN students s ON r.student_id = s.id GROUP BY department ORDER BY responses DESC LIMIT 6")->fetchAll();

$surveyByCategory = $db->query("SELECT r.category, COUNT(*) AS responses, AVG(os.satisfaction_score) AS avg_satisfaction, AVG(os.response_time_score) AS avg_response, AVG(os.fairness_score) AS avg_fairness FROM report_outcome_surveys os JOIN reports r ON os.report_id = r.id GROUP BY r.category ORDER BY responses DESC")->fetchAll();

adminRenderHeader('Analytics', 'analytics');
adminRenderSubTitle('Visual analytics of complaints submitted, resolved, and pending.');
?>

<div class="panel space-bottom" style="display:flex; justify-content:flex-end;">
    <a class="btn secondary" href="../../backend/admin/export_analytics.php"><i class="fas fa-file-csv"></i> Export CSV</a>
</div>

<style>
.chart-container {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 14px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.chart-title {
    font-family: 'Syne', sans-serif;
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: var(--cyan);
}
.pie-chart {
    display: flex;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.25rem;
}
.pie-svg {
    width: 200px;
    height: 200px;
    transform: rotate(-90deg);
}
.pie-chart canvas {
    width: clamp(140px, 22vw, 180px) !important;
    height: clamp(140px, 22vw, 180px) !important;
}
.legend {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
}
.legend-color {
    width: 16px;
    height: 16px;
    border-radius: 4px;
}
.bar-chart {
    margin-top: 1rem;
}
.bar-row {
    display: grid;
    grid-template-columns: 100px 1fr 60px;
    gap: 0.75rem;
    align-items: center;
    margin-bottom: 0.75rem;
}
.bar-label {
    font-size: 0.8rem;
    color: var(--gray-400);
}
.bar-bg {
    background: rgba(255,255,255,0.08);
    height: 32px;
    border-radius: 6px;
    overflow: hidden;
}
.bar-fill {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    color: white;
}
.bar-fill.submitted { background: linear-gradient(90deg, #1a56e8, #3b82f6); }
.bar-fill.resolved { background: linear-gradient(90deg, #10b981, #34d399); }
.bar-fill.active { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.bar-value {
    font-size: 0.85rem;
    font-weight: 600;
    text-align: right;
}
.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.stat-panel {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    padding: 1rem;
    text-align: center;
}
.stat-panel .value {
    font-family: 'Syne', sans-serif;
    font-size: 2rem;
    font-weight: 700;
}
.stat-panel .label {
    font-size: 0.75rem;
    color: var(--gray-400);
    margin-top: 0.25rem;
}
.stat-panel.blue .value { color: #60a5fa; }
.stat-panel.green .value { color: #34d399; }
.stat-panel.amber .value { color: #fbbf24; }
.survey-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.rating-bar {
    height: 10px;
    background: rgba(255,255,255,0.08);
    border-radius: 999px;
    overflow: hidden;
    margin-top: 0.4rem;
}
.rating-fill {
    height: 100%;
    background: linear-gradient(90deg, #06b6d4, #10b981);
}
.survey-table {
    width: 100%;
    border-collapse: collapse;
}
.survey-table th, .survey-table td {
    text-align: left;
    padding: 0.6rem 0.4rem;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    font-size: 0.85rem;
}
.survey-table th { color: var(--gray-400); font-weight: 600; }
</style>

<div class="stats-overview">
    <div class="stat-panel blue">
        <div class="value"><?= number_format($submitted) ?></div>
        <div class="label">Total Complaints Submitted</div>
    </div>
    <div class="stat-panel green">
        <div class="value"><?= number_format($resolved) ?></div>
        <div class="label">Resolved / Closed</div>
    </div>
    <div class="stat-panel amber">
        <div class="value"><?= number_format($active) ?></div>
        <div class="label">Active / Pending</div>
    </div>
    <div class="stat-panel">
        <div class="value"><?= $submitted > 0 ? round(($resolved / $submitted) * 100, 1) : 0 ?>%</div>
        <div class="label">Resolution Rate</div>
    </div>
</div>

<!-- Pie Chart Section -->
<div class="chart-container">
    <div class="chart-title">📊 Complaint Status Distribution</div>
    <div class="pie-chart">
        <canvas id="statusPieChart" width="200" height="200" style="width:200px;height:200px;"></canvas>
        <div class="legend">
            <div class="legend-item"><div class="legend-color" style="background:#3b82f6;"></div> Pending / Under Review / Investigating (<?= $active ?>)</div>
            <div class="legend-item"><div class="legend-color" style="background:#10b981;"></div> Resolved (<?= $resolved ?>)</div>
            <div class="legend-item"><div class="legend-color" style="background:#ef4444;"></div> Rejected (<?= $rejected ?>)</div>
            <div class="legend-item"><div class="legend-color" style="background:#8b5cf6;"></div> Escalated (<?= $escalated ?>)</div>
        </div>
    </div>
</div>

<!-- Monthly Trend Chart -->
<div class="chart-container">
    <div class="chart-title">📈 Monthly Trends (Last 12 Months)</div>
    <div class="bar-chart">
        <?php foreach ($monthlyTrend as $row): ?>
        <?php 
            $submittedWidth = $maxMonthly > 0 ? round(($row['submitted'] / $maxMonthly) * 100) : 0;
            $resolvedWidth = $maxMonthly > 0 ? round(($row['resolved'] / $maxMonthly) * 100) : 0;
        ?>
        <div class="bar-row">
            <div class="bar-label"><?= adminH($row['label']) ?></div>
            <div>
                <div style="display:flex; gap:2px; height:32px;">
                    <div class="bar-fill submitted" style="width:<?= $submittedWidth ?>%; background:linear-gradient(90deg,#1a56e8,#3b82f6);"><?= $row['submitted'] > 0 ? $row['submitted'] : '' ?></div>
                    <div class="bar-fill resolved" style="width:<?= $resolvedWidth ?>%; background:linear-gradient(90deg,#10b981,#34d399);"><?= $row['resolved'] > 0 ? $row['resolved'] : '' ?></div>
                </div>
            </div>
            <div class="bar-value"><?= adminH((string) $row['submitted']) ?> / <?= adminH((string) $row['resolved']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <div style="display:flex; gap:1rem; justify-content: center; margin-top: 1rem; font-size:0.75rem;">
        <span><span style="background:#1a56e8; display:inline-block; width:12px; height:12px; border-radius:2px;"></span> Submitted</span>
        <span><span style="background:#10b981; display:inline-block; width:12px; height:12px; border-radius:2px;"></span> Resolved</span>
    </div>
</div>

<!-- Category vs Resolution Chart -->
<?php
$categoryResolved = $db->query("
    SELECT 
        category,
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_count
    FROM reports
    GROUP BY category
    ORDER BY total DESC
")->fetchAll();

$maxCategory = 1;
foreach ($categoryResolved as $row) {
    $maxCategory = max($maxCategory, (int)$row['total']);
}
?>

<div class="chart-container">
    <div class="chart-title">📋 Complaints by Category (Submitted vs Resolved)</div>
    <?php foreach ($categoryResolved as $row): ?>
    <?php 
        $totalWidth = $maxCategory > 0 ? round(((int)$row['total'] / $maxCategory) * 100) : 0;
        $resolvedWidth = $maxCategory > 0 && (int)$row['total'] > 0 ? round(((int)$row['resolved_count'] / $maxCategory) * 100) : 0;
    ?>
    <div class="bar-row">
        <div class="bar-label"><?= adminH(ucwords(str_replace('_', ' ', $row['category']))) ?></div>
        <div>
            <div style="display:flex; gap:2px; height:28px;">
                <div class="bar-fill submitted" style="width:<?= $totalWidth ?>%; background:linear-gradient(90deg,#1a56e8,#3b82f6);"><?= adminH((string) $row['total']) ?></div>
                <div class="bar-fill resolved" style="width:<?= $resolvedWidth ?>%; background:linear-gradient(90deg,#10b981,#34d399);"><?= adminH((string) $row['resolved_count']) ?></div>
            </div>
        </div>
        <div class="bar-value"><?= adminH((string) $row['resolved_count']) ?>/<?= adminH((string) $row['total']) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Outcome Survey Analytics -->
<?php
$avgSatisfaction = (float) ($surveyOverview['avg_satisfaction'] ?? 0);
$avgResponse = (float) ($surveyOverview['avg_response'] ?? 0);
$avgFairness = (float) ($surveyOverview['avg_fairness'] ?? 0);
?>

<div class="chart-container">
    <div class="chart-title">✅ Outcome Survey Insights</div>
    <div class="survey-grid">
        <div class="stat-panel">
            <div class="value"><?= adminH((string) $surveyTotal) ?></div>
            <div class="label">Total Surveys</div>
        </div>
        <div class="stat-panel">
            <div class="value"><?= adminH(number_format($avgSatisfaction, 2)) ?></div>
            <div class="label">Avg Satisfaction (1-5)</div>
            <div class="rating-bar"><div class="rating-fill" style="width:<?= min(100, max(0, ($avgSatisfaction / 5) * 100)) ?>%;"></div></div>
        </div>
        <div class="stat-panel">
            <div class="value"><?= adminH(number_format($avgResponse, 2)) ?></div>
            <div class="label">Avg Response Time</div>
            <div class="rating-bar"><div class="rating-fill" style="width:<?= min(100, max(0, ($avgResponse / 5) * 100)) ?>%;"></div></div>
        </div>
        <div class="stat-panel">
            <div class="value"><?= adminH(number_format($avgFairness, 2)) ?></div>
            <div class="label">Avg Fairness</div>
            <div class="rating-bar"><div class="rating-fill" style="width:<?= min(100, max(0, ($avgFairness / 5) * 100)) ?>%;"></div></div>
        </div>
        <div class="stat-panel">
            <div class="value"><?= adminH((string) $surveyRate) ?>%</div>
            <div class="label">Survey Response Rate</div>
        </div>
    </div>

    <div class="survey-grid">
        <div class="panel">
            <div class="chart-title" style="font-size:0.95rem; margin-bottom:0.6rem;">By Department (Top 6)</div>
            <table class="survey-table">
                <thead>
                    <tr><th>Department</th><th>Responses</th><th>Avg Satisfaction</th></tr>
                </thead>
                <tbody>
                    <?php if (!$surveyByDept): ?>
                    <tr><td colspan="3" class="muted">No survey data yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($surveyByDept as $row): ?>
                    <tr>
                        <td><?= adminH($row['department']) ?></td>
                        <td><?= adminH((string) $row['responses']) ?></td>
                        <td><?= adminH(number_format((float) $row['avg_satisfaction'], 2)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <div class="chart-title" style="font-size:0.95rem; margin-bottom:0.6rem;">By Category</div>
            <table class="survey-table">
                <thead>
                    <tr><th>Category</th><th>Responses</th><th>Sat.</th><th>Resp.</th><th>Fair.</th></tr>
                </thead>
                <tbody>
                    <?php if (!$surveyByCategory): ?>
                    <tr><td colspan="5" class="muted">No survey data yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($surveyByCategory as $row): ?>
                    <tr>
                        <td><?= adminH(ucwords(str_replace('_', ' ', $row['category']))) ?></td>
                        <td><?= adminH((string) $row['responses']) ?></td>
                        <td><?= adminH(number_format((float) $row['avg_satisfaction'], 2)) ?></td>
                        <td><?= adminH(number_format((float) $row['avg_response'], 2)) ?></td>
                        <td><?= adminH(number_format((float) $row['avg_fairness'], 2)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Pie Chart for Status Distribution
const ctx = document.getElementById('statusPieChart').getContext('2d');
new Chart(ctx, {
    type: 'pie',
    data: {
        labels: ['Active/Pending', 'Resolved', 'Rejected', 'Escalated'],
        datasets: [{
            data: [<?= $active ?>, <?= $resolved ?>, <?= $rejected ?>, <?= $escalated ?>],
            backgroundColor: ['#3b82f6', '#10b981', '#ef4444', '#8b5cf6'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false }
        }
    }
});
</script>

<?php adminRenderFooter(); ?>