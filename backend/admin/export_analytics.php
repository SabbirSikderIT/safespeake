<?php
require_once __DIR__ . '/../includes/config.php';
requireAdminLogin();

$db = getDB();
ensureReportingTables();

$statusData = $db->query("SELECT status, COUNT(*) AS count FROM reports GROUP BY status")->fetchAll();
$categoryData = $db->query("SELECT category, COUNT(*) AS total, SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_count FROM reports GROUP BY category ORDER BY total DESC")->fetchAll();
$monthlyData = $db->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS submitted, SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved, SUM(CASE WHEN status IN ('pending', 'under_review', 'investigating') THEN 1 ELSE 0 END) AS active FROM reports WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY month ORDER BY month ASC")->fetchAll();
$surveyOverview = $db->query("SELECT COUNT(*) AS total_surveys, AVG(satisfaction_score) AS avg_satisfaction, AVG(response_time_score) AS avg_response, AVG(fairness_score) AS avg_fairness FROM report_outcome_surveys")->fetch();
$surveyByDept = $db->query("SELECT COALESCE(NULLIF(s.department, ''), 'Unknown') AS department, COUNT(*) AS responses, AVG(os.satisfaction_score) AS avg_satisfaction FROM report_outcome_surveys os JOIN reports r ON os.report_id = r.id LEFT JOIN students s ON r.student_id = s.id GROUP BY department ORDER BY responses DESC")->fetchAll();
$surveyByCategory = $db->query("SELECT r.category, COUNT(*) AS responses, AVG(os.satisfaction_score) AS avg_satisfaction, AVG(os.response_time_score) AS avg_response, AVG(os.fairness_score) AS avg_fairness FROM report_outcome_surveys os JOIN reports r ON os.report_id = r.id GROUP BY r.category ORDER BY responses DESC")->fetchAll();

$filename = 'analytics_export_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

fputcsv($output, ['Section', 'Label', 'Value', 'Value 2']);

foreach ($statusData as $row) {
    fputcsv($output, ['Status Distribution', $row['status'], $row['count'], '']);
}

foreach ($categoryData as $row) {
    fputcsv($output, ['Category Summary', $row['category'], $row['total'], $row['resolved_count']]);
}

foreach ($monthlyData as $row) {
    fputcsv($output, ['Monthly Trend', $row['month'], $row['submitted'], $row['resolved']]);
}

fputcsv($output, ['Survey Overview', 'Total Surveys', $surveyOverview['total_surveys'] ?? 0, '']);
fputcsv($output, ['Survey Overview', 'Avg Satisfaction', $surveyOverview['avg_satisfaction'] ?? 0, '']);
fputcsv($output, ['Survey Overview', 'Avg Response Time', $surveyOverview['avg_response'] ?? 0, '']);
fputcsv($output, ['Survey Overview', 'Avg Fairness', $surveyOverview['avg_fairness'] ?? 0, '']);

foreach ($surveyByDept as $row) {
    fputcsv($output, ['Survey By Department', $row['department'], $row['responses'], $row['avg_satisfaction']]);
}

foreach ($surveyByCategory as $row) {
    fputcsv($output, ['Survey By Category', $row['category'], $row['responses'], $row['avg_satisfaction']]);
}

fclose($output);
exit;
