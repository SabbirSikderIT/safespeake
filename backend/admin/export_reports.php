<?php
require_once __DIR__ . '/../includes/config.php';
requireAdminLogin();

$db = getDB();

$statusOptions = ['pending', 'under_review', 'investigating', 'resolved', 'rejected', 'escalated'];
$priorityOptions = ['low', 'medium', 'high', 'critical'];
$categoryOptions = ['bullying', 'harassment', 'sexual_harassment', 'academic_misconduct', 'discrimination', 'violence', 'mental_health', 'other'];

$query = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? '');
$priorityFilter = (string) ($_GET['priority'] ?? '');
$categoryFilter = (string) ($_GET['category'] ?? '');

if (!in_array($statusFilter, $statusOptions, true)) {
    $statusFilter = '';
}
if (!in_array($priorityFilter, $priorityOptions, true)) {
    $priorityFilter = '';
}
if (!in_array($categoryFilter, $categoryOptions, true)) {
    $categoryFilter = '';
}

$where = [];
$params = [];
if ($query !== '') {
    $where[] = "(r.tracking_id LIKE ? OR r.title LIKE ? OR r.description LIKE ? OR s.full_name LIKE ? OR s.student_id LIKE ?)";
    $like = '%' . $query . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}
if ($statusFilter !== '') {
    $where[] = "r.status = ?";
    $params[] = $statusFilter;
}
if ($priorityFilter !== '') {
    $where[] = "r.priority = ?";
    $params[] = $priorityFilter;
}
if ($categoryFilter !== '') {
    $where[] = "r.category = ?";
    $params[] = $categoryFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT
      r.tracking_id,
      r.title,
      r.category,
      r.status,
      r.priority,
      r.created_at,
      r.resolved_at,
      r.is_anonymous,
      r.notify_opt_in,
      r.notify_email,
      r.accused_student_id,
      r.accused_name,
      r.accused_department,
            os.satisfaction_score,
            os.response_time_score,
            os.fairness_score,
            os.comments AS survey_comments,
      s.full_name AS student_name,
      s.student_id AS student_code
    FROM reports r
    LEFT JOIN students s ON r.student_id = s.id
        LEFT JOIN report_outcome_surveys os ON os.report_id = r.id
    {$whereSql}
    ORDER BY r.created_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filename = 'reports_export_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'Tracking ID',
    'Title',
    'Category',
    'Status',
    'Priority',
    'Submitted At',
    'Resolved At',
    'Anonymous',
    'Notify Opt In',
    'Notify Email',
    'Reporter Name',
    'Reporter Student ID',
    'Accused Student ID',
    'Accused Name',
    'Accused Department',
    'Survey Satisfaction',
    'Survey Response Time',
    'Survey Fairness',
    'Survey Comments',
]);

foreach ($rows as $row) {
    fputcsv($output, [
        $row['tracking_id'],
        $row['title'],
        $row['category'],
        $row['status'],
        $row['priority'],
        $row['created_at'],
        $row['resolved_at'],
        $row['is_anonymous'] ? 'Yes' : 'No',
        $row['notify_opt_in'] ? 'Yes' : 'No',
        $row['notify_email'],
        $row['student_name'],
        $row['student_code'],
        $row['accused_student_id'],
        $row['accused_name'],
        $row['accused_department'],
        $row['satisfaction_score'],
        $row['response_time_score'],
        $row['fairness_score'],
        $row['survey_comments'],
    ]);
}

fclose($output);
exit;
