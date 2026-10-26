<?php
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/_ui.php';
requireAdminLogin();
ensureReportingTables();

$db = getDB();
$adminId = (int) ($_SESSION['admin_id'] ?? 0);

$reportId = (int) ($_GET['report_id'] ?? 0);
$index = (int) ($_GET['index'] ?? -1);

if ($reportId <= 0 || $index < 0) {
    redirect('reports.php');
}

$stmt = $db->prepare("SELECT id, tracking_id, evidence_files, title FROM reports WHERE id = ?");
$stmt->execute([$reportId]);
$report = $stmt->fetch();
if (!$report || empty($report['evidence_files'])) {
    redirect('reports.php');
}

$decoded = json_decode((string) $report['evidence_files'], true);
if (!is_array($decoded) || !array_key_exists($index, $decoded)) {
    redirect('reports.php');
}

$entry = (string) $decoded[$index];
$evidenceKey = $entry;
$viewerUrl = '';
$downloadUrl = '';
$openLinkUrl = '';
$displayType = 'file';

if (stripos($entry, 'drive_link:') === 0) {
    $displayType = 'link';
    $openLinkUrl = BACKEND_URL . '/admin/evidence.php?report_id=' . $reportId . '&index=' . $index . '&mode=link';
} else {
    $viewerUrl = BACKEND_URL . '/admin/evidence.php?report_id=' . $reportId . '&index=' . $index . '&mode=inline';
    $downloadUrl = BACKEND_URL . '/admin/evidence.php?report_id=' . $reportId . '&index=' . $index . '&mode=download';
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_annotation') {
    $note = trim((string) ($_POST['note'] ?? ''));
    if ($note === '') {
        $errorMessage = 'Annotation cannot be empty.';
    } else {
        $insert = $db->prepare("INSERT INTO report_evidence_annotations (report_id, admin_id, evidence_key, note) VALUES (?, ?, ?, ?)");
        $insert->execute([$reportId, $adminId > 0 ? $adminId : null, $evidenceKey, $note]);
        $successMessage = 'Annotation saved.';
    }
}

$noteStmt = $db->prepare("SELECT a.*, ad.full_name AS admin_name FROM report_evidence_annotations a LEFT JOIN admins ad ON a.admin_id = ad.id WHERE a.report_id = ? AND a.evidence_key = ? ORDER BY a.created_at DESC");
$noteStmt->execute([$reportId, $evidenceKey]);
$annotations = $noteStmt->fetchAll();

adminRenderHeader('Evidence Review', 'reports');
adminRenderSubTitle('Full-screen evidence viewer with annotations.');
adminRenderFlash($successMessage, $errorMessage);
?>

<style>
.viewer-layout {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 1rem;
  min-height: 70vh;
}
.viewer-panel {
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 16px;
  padding: 0.8rem;
}
.viewer-frame {
  width: 100%;
  height: 70vh;
  border: none;
  border-radius: 12px;
  background: rgba(2,6,23,0.7);
}
.viewer-actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-bottom: 0.75rem;
}
.annotation-list {
  max-height: 52vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.annotation-item {
  padding: 0.65rem;
  border-radius: 10px;
  background: rgba(15,23,42,0.6);
  border: 1px solid rgba(255,255,255,0.08);
}
.annotation-meta {
  font-size: 0.72rem;
  color: var(--gray-300);
  margin-bottom: 0.35rem;
}
@media (max-width: 980px) {
  .viewer-layout { grid-template-columns: 1fr; }
  .viewer-frame { height: 60vh; }
}
</style>

<div class="panel space-bottom">
  <div class="muted">Report: <?= adminH($report['tracking_id']) ?> • <?= adminH($report['title']) ?></div>
</div>

<div class="viewer-layout">
  <div class="viewer-panel">
    <div class="viewer-actions">
      <a class="btn secondary" href="reports.php"><i class="fas fa-arrow-left"></i> Back to Reports</a>
      <?php if ($displayType === 'link'): ?>
        <a class="btn" href="<?= adminH($openLinkUrl) ?>" target="_blank" rel="noopener"><i class="fas fa-link"></i> Open Link</a>
      <?php else: ?>
        <a class="btn secondary" href="<?= adminH($downloadUrl) ?>" target="_blank" rel="noopener"><i class="fas fa-download"></i> Download</a>
      <?php endif; ?>
    </div>

    <?php if ($displayType === 'link'): ?>
      <div class="panel">
        <p class="muted">This evidence is an external link. Use "Open Link" to view it.</p>
      </div>
    <?php else: ?>
      <iframe class="viewer-frame" src="<?= adminH($viewerUrl) ?>" title="Evidence"></iframe>
    <?php endif; ?>
  </div>

  <div class="viewer-panel">
    <h3 style="font-family:'Syne',sans-serif;font-size:1rem;margin-bottom:0.75rem;color:var(--cyan);">Annotations</h3>
    <form method="POST" class="space-bottom">
      <input type="hidden" name="action" value="add_annotation">
      <textarea name="note" placeholder="Add an annotation for this evidence..." required></textarea>
      <div class="space-top" style="display:flex; justify-content:flex-end;">
        <button class="btn" type="submit"><i class="fas fa-pen"></i> Add Note</button>
      </div>
    </form>

    <div class="annotation-list">
      <?php if (!$annotations): ?>
        <div class="muted">No annotations yet.</div>
      <?php endif; ?>
      <?php foreach ($annotations as $annotation): ?>
        <div class="annotation-item">
          <div class="annotation-meta">
            <?= adminH($annotation['admin_name'] ?: 'Admin') ?> • <?= adminH(date('Y-m-d H:i', strtotime($annotation['created_at']))) ?>
          </div>
          <div><?= adminH($annotation['note']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php adminRenderFooter(); ?>
