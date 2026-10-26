<?php
require_once __DIR__ . '/../includes/config.php';
requireAdminLogin();
ensureReportingTables();

$reportId = (int) ($_GET['report_id'] ?? 0);
$index = (int) ($_GET['index'] ?? -1);
$mode = (string) ($_GET['mode'] ?? 'inline');

if ($reportId <= 0 || $index < 0) {
    http_response_code(400);
    echo 'Invalid request.';
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT evidence_files FROM reports WHERE id = ?");
$stmt->execute([$reportId]);
$report = $stmt->fetch();

if (!$report || empty($report['evidence_files'])) {
    http_response_code(404);
    echo 'Evidence not found.';
    exit;
}

$decoded = json_decode((string) $report['evidence_files'], true);
if (!is_array($decoded) || !array_key_exists($index, $decoded)) {
    http_response_code(404);
    echo 'Evidence not found.';
    exit;
}

$entry = (string) $decoded[$index];
$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$ip = $_SERVER['REMOTE_ADDR'] ?? null;

if (stripos($entry, 'drive_link:') === 0) {
    $link = trim(substr($entry, strlen('drive_link:')));
    if ($link === '') {
        http_response_code(404);
        echo 'Link not found.';
        exit;
    }

    $log = $db->prepare("INSERT INTO report_evidence_access_log (report_id, admin_id, file_name, access_type, ip_address) VALUES (?, ?, ?, 'link', ?)");
    $log->execute([$reportId, $adminId > 0 ? $adminId : null, $link, $ip]);

    header('Location: ' . $link);
    exit;
}

$file = basename($entry);
$filePath = rtrim(UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . $file;
if (!is_file($filePath)) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

$accessType = $mode === 'download' ? 'download' : 'view';
$log = $db->prepare("INSERT INTO report_evidence_access_log (report_id, admin_id, file_name, access_type, ip_address) VALUES (?, ?, ?, ?, ?)");
$log->execute([$reportId, $adminId > 0 ? $adminId : null, $file, $accessType, $ip]);

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mimeMap = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'pdf' => 'application/pdf',
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
if ($mode === 'download' || !in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'pdf'], true)) {
    header('Content-Disposition: attachment; filename="' . $file . '"');
} else {
    header('Content-Disposition: inline; filename="' . $file . '"');
}
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
