<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/_ui.php';
requireAdminLogin();
ensureReportingTables();

$reportId = (int) ($_GET['report_id'] ?? 0);
if ($reportId <= 0) {
    redirect('reports.php');
}

$db = getDB();
$stmt = $db->prepare("SELECT id_verification_file FROM reports WHERE id = ?");
$stmt->execute([$reportId]);
$row = $stmt->fetch();
if (!$row || empty($row['id_verification_file'])) {
    redirect('reports.php');
}

$fileName = basename((string) $row['id_verification_file']);
$filePath = UPLOAD_ID_PATH . $fileName;
if (!is_file($filePath)) {
    redirect('reports.php');
}

$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$mimeMap = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'pdf' => 'application/pdf',
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
