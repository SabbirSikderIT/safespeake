<?php
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/_ui.php';
requireAdminLogin();

$db = getDB();
$adminId = (int) ($_SESSION['admin_id'] ?? 0);
ensureReportingTables();

$statusOptions = ['pending', 'under_review', 'investigating', 'resolved', 'rejected', 'escalated'];
$priorityOptions = ['low', 'medium', 'high', 'critical'];
$categoryOptions = ['bullying', 'harassment', 'sexual_harassment', 'academic_misconduct', 'discrimination', 'violence', 'mental_health', 'other'];

$successMessage = '';
$errorMessage = '';

$renderEvidenceItem = function ($entry, $reportId, $index) {
  $entry = (string) $entry;
  if ($entry === '') {
    return '';
  }

  $reportId = (int) $reportId;
  $index = (int) $index;
  $baseUrl = BACKEND_URL . '/admin/evidence.php?report_id=' . $reportId . '&index=' . $index;
  $viewerUrl = SITE_URL . '/admin/evidence_viewer.php?report_id=' . $reportId . '&index=' . $index;

  if (stripos($entry, 'drive_link:') === 0) {
    $link = trim(substr($entry, strlen('drive_link:')));
    if ($link === '') {
      return '';
    }
    $safeLink = adminH($baseUrl . '&mode=link');
    $safeViewer = adminH($viewerUrl);
    return '<div class="evidence-item"><i class="fas fa-link"></i> <a class="evidence-link" href="' . $safeViewer . '" target="_blank" rel="noopener">Review</a> <a class="evidence-link" href="' . $safeLink . '" target="_blank" rel="noopener">Open link</a></div>';
  }

  $file = basename($entry);
  if ($file === '') {
    return '';
  }
  $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
  $thumbUrl = adminH(BACKEND_URL . '/uploads/evidence/' . rawurlencode($file));
  $downloadUrl = adminH($baseUrl . '&mode=download');
  $safeViewer = adminH($viewerUrl);
  $safeFile = adminH($file);

  if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
    return '<div class="evidence-item"><img class="evidence-thumb" src="' . $thumbUrl . '" alt="Evidence"> <a class="evidence-link" href="' . $safeViewer . '" target="_blank" rel="noopener">Review</a> <a class="evidence-link" href="' . $downloadUrl . '" target="_blank" rel="noopener">Download</a></div>';
  }

  if ($ext === 'pdf') {
    return '<div class="evidence-item"><i class="fas fa-file-pdf"></i> <a class="evidence-link" href="' . $safeViewer . '" target="_blank" rel="noopener">Review</a> <a class="evidence-link" href="' . $downloadUrl . '" target="_blank" rel="noopener">Download</a></div>';
  }

  return '<div class="evidence-item"><i class="fas fa-paperclip"></i> <a class="evidence-link" href="' . $safeViewer . '" target="_blank" rel="noopener">Review</a> <a class="evidence-link" href="' . $downloadUrl . '" target="_blank" rel="noopener">' . $safeFile . '</a></div>';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string) ($_POST['action'] ?? '');

  if ($action === 'update_report') {
    $reportId = (int) ($_POST['report_id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    $priority = (string) ($_POST['priority'] ?? '');
    $assignedToRaw = trim((string) ($_POST['assigned_to'] ?? ''));
    $adminNotes = trim((string) ($_POST['admin_notes'] ?? ''));

    if ($reportId <= 0) {
      $errorMessage = 'Invalid report selected.';
    } elseif (!in_array($status, $statusOptions, true) || !in_array($priority, $priorityOptions, true)) {
      $errorMessage = 'Invalid status or priority value.';
    } else {
      $assignedTo = null;
      if ($assignedToRaw !== '') {
        $assignedTo = (int) $assignedToRaw;
        $stmt = $db->prepare("SELECT id FROM admins WHERE id = ? AND is_active = 1");
        $stmt->execute([$assignedTo]);
        if (!$stmt->fetch()) {
          $errorMessage = 'Assigned admin not found or inactive.';
        }
      }

      if (!$errorMessage) {
        $stmt = $db->prepare("SELECT status, notify_opt_in, notify_email, tracking_id, title FROM reports WHERE id = ?");
        $stmt->execute([$reportId]);
        $old = $stmt->fetch();

        if (!$old) {
          $errorMessage = 'Report not found.';
        } else {
          if ($status === 'resolved') {
            $update = $db->prepare("UPDATE reports SET status = ?, priority = ?, assigned_to = ?, admin_notes = ?, resolved_at = COALESCE(resolved_at, NOW()) WHERE id = ?");
            $update->execute([$status, $priority, $assignedTo, $adminNotes !== '' ? $adminNotes : null, $reportId]);
          } else {
            $update = $db->prepare("UPDATE reports SET status = ?, priority = ?, assigned_to = ?, admin_notes = ?, resolved_at = NULL WHERE id = ?");
            $update->execute([$status, $priority, $assignedTo, $adminNotes !== '' ? $adminNotes : null, $reportId]);
          }

          if ($old['status'] !== $status) {
            $history = $db->prepare("INSERT INTO report_status_history (report_id, old_status, new_status, changed_by, notes) VALUES (?, ?, ?, ?, ?)");
            $history->execute([$reportId, $old['status'], $status, $adminId > 0 ? $adminId : null, $adminNotes !== '' ? $adminNotes : null]);

            if (!empty($old['notify_opt_in']) && !empty($old['notify_email'])) {
              sendReportStatusUpdateEmail($old['notify_email'], $old['tracking_id'], $old['title'], $status, $priority);
            }
          }

          $successMessage = "Report {$reportId} updated successfully.";
        }
      }
    }
  } elseif ($action === 'add_message') {
    $reportId = (int) ($_POST['report_id'] ?? 0);
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($reportId <= 0) {
      $errorMessage = 'Invalid report selected.';
    } elseif (strlen($message) < 5) {
      $errorMessage = 'Message must be at least 5 characters.';
    } else {
      $insert = $db->prepare("INSERT INTO report_messages (report_id, sender_type, admin_id, message) VALUES (?, 'admin', ?, ?)");
      $insert->execute([$reportId, $adminId > 0 ? $adminId : null, $message]);
      $notifyEmail = getReportNotifyEmail($reportId);
      if ($notifyEmail) {
        $infoStmt = $db->prepare("SELECT tracking_id, title FROM reports WHERE id = ?");
        $infoStmt->execute([$reportId]);
        $info = $infoStmt->fetch();
        if ($info) {
          sendReporterNotificationEmail(
            $notifyEmail,
            $info['tracking_id'],
            $info['title'],
            'New Message from SafeSpeak',
            'A proctor/admin has sent you a new message. Please log in to view the details.'
          );
        }
      }
      $successMessage = 'Message sent to reporter.';
    }
  } elseif ($action === 'verify_accused' || $action === 'unverify_accused') {
    $reportId = (int) ($_POST['report_id'] ?? 0);
    if ($reportId <= 0) {
      $errorMessage = 'Invalid report selected.';
    } else {
      $stmt = $db->prepare("SELECT accused_student_id FROM reports WHERE id = ?");
      $stmt->execute([$reportId]);
      $row = $stmt->fetch();
      if (!$row || empty($row['accused_student_id'])) {
        $errorMessage = 'No accused student ID to verify.';
      } else {
        $directoryRecord = getStudentFromDirectory($row['accused_student_id']);
        if (!$directoryRecord && $action === 'verify_accused') {
          $errorMessage = 'Accused ID not found in Student Directory.';
        } else {
          if ($action === 'verify_accused') {
            $update = $db->prepare("UPDATE reports SET accused_verified = 1, accused_verified_by = ?, accused_verified_at = NOW() WHERE id = ?");
            $update->execute([$adminId > 0 ? $adminId : null, $reportId]);
            $successMessage = 'Accused identity verified.';
          } else {
            $update = $db->prepare("UPDATE reports SET accused_verified = 0, accused_verified_by = NULL, accused_verified_at = NULL WHERE id = ?");
            $update->execute([$reportId]);
            $successMessage = 'Accused identity marked as unverified.';
          }
        }
      }
    }
  } elseif ($action === 'mark_spam' || $action === 'clear_spam') {
    $reportId = (int) ($_POST['report_id'] ?? 0);
    $note = trim((string) ($_POST['spam_note'] ?? ''));
    if ($reportId <= 0) {
      $errorMessage = 'Invalid report selected.';
    } else {
      if ($action === 'mark_spam') {
        $update = $db->prepare("UPDATE reports SET manual_spam = 1, manual_spam_by = ?, manual_spam_at = NOW(), manual_spam_note = ? WHERE id = ?");
        $update->execute([$adminId > 0 ? $adminId : null, $note !== '' ? $note : null, $reportId]);
        $successMessage = 'Report marked as spam.';
      } else {
        $update = $db->prepare("UPDATE reports SET manual_spam = 0, manual_spam_by = NULL, manual_spam_at = NULL, manual_spam_note = NULL WHERE id = ?");
        $update->execute([$reportId]);
        $successMessage = 'Spam flag cleared.';
      }
    }
  } elseif ($action === 'request_id_verification' || $action === 'approve_id' || $action === 'reject_id') {
    $reportId = (int) ($_POST['report_id'] ?? 0);
    $note = trim((string) ($_POST['id_review_note'] ?? ''));
    if ($reportId <= 0) {
      $errorMessage = 'Invalid report selected.';
    } else {
      if ($action === 'request_id_verification') {
        $update = $db->prepare("UPDATE reports SET id_verification_required = 1, id_verification_status = 'requested', id_verification_requested_by = ?, id_verification_requested_at = NOW() WHERE id = ?");
        $update->execute([$adminId > 0 ? $adminId : null, $reportId]);
        $notifyEmail = getReportNotifyEmail($reportId);
        if ($notifyEmail) {
          $infoStmt = $db->prepare("SELECT tracking_id, title FROM reports WHERE id = ?");
          $infoStmt->execute([$reportId]);
          $info = $infoStmt->fetch();
          if ($info) {
            sendReporterNotificationEmail(
              $notifyEmail,
              $info['tracking_id'],
              $info['title'],
              'ID Verification Requested',
              'Please submit your ID document for verification in the report status page.'
            );
          }
        }
        $successMessage = 'ID verification requested.';
      } elseif ($action === 'approve_id') {
        $update = $db->prepare("UPDATE reports SET id_verification_status = 'approved', id_verification_reviewed_by = ?, id_verification_reviewed_at = NOW(), id_verification_review_note = ? WHERE id = ?");
        $update->execute([$adminId > 0 ? $adminId : null, $note !== '' ? $note : null, $reportId]);
        $notifyEmail = getReportNotifyEmail($reportId);
        if ($notifyEmail) {
          $infoStmt = $db->prepare("SELECT tracking_id, title FROM reports WHERE id = ?");
          $infoStmt->execute([$reportId]);
          $info = $infoStmt->fetch();
          if ($info) {
            sendReporterNotificationEmail(
              $notifyEmail,
              $info['tracking_id'],
              $info['title'],
              'ID Verification Approved',
              'Your ID verification has been approved.'
            );
          }
        }
        $successMessage = 'ID verified.';
      } else {
        $update = $db->prepare("UPDATE reports SET id_verification_status = 'rejected', id_verification_reviewed_by = ?, id_verification_reviewed_at = NOW(), id_verification_review_note = ? WHERE id = ?");
        $update->execute([$adminId > 0 ? $adminId : null, $note !== '' ? $note : null, $reportId]);
        $notifyEmail = getReportNotifyEmail($reportId);
        if ($notifyEmail) {
          $infoStmt = $db->prepare("SELECT tracking_id, title FROM reports WHERE id = ?");
          $infoStmt->execute([$reportId]);
          $info = $infoStmt->fetch();
          if ($info) {
            sendReporterNotificationEmail(
              $notifyEmail,
              $info['tracking_id'],
              $info['title'],
              'ID Verification Rejected',
              'Your ID verification was rejected. Please upload a clearer document.'
            );
          }
        }
        $successMessage = 'ID verification rejected.';
      }
    }
  }
}

$adminsStmt = $db->query("SELECT id, username, full_name FROM admins WHERE is_active = 1 ORDER BY username");
$admins = $adminsStmt->fetchAll();

$query = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? '');
$priorityFilter = (string) ($_GET['priority'] ?? '');
$categoryFilter = (string) ($_GET['category'] ?? '');
$quickFilter = (string) ($_GET['filter'] ?? '');

if ($statusFilter === '' && $priorityFilter === '') {
    if ($quickFilter === 'pending') {
        $statusFilter = 'pending';
    } elseif ($quickFilter === 'critical') {
        $priorityFilter = 'critical';
    }
}

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

$perPage = 15;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countSql = "
    SELECT COUNT(*) AS total
    FROM reports r
    LEFT JOIN students s ON r.student_id = s.id
    {$whereSql}
";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int) ($countStmt->fetch()['total'] ?? 0);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$listSql = "
    SELECT
      r.*,
      s.full_name AS student_name,
      s.student_id AS student_code,
      a.username AS assigned_username
    FROM reports r
    LEFT JOIN students s ON r.student_id = s.id
    LEFT JOIN admins a ON r.assigned_to = a.id
    {$whereSql}
    ORDER BY FIELD(r.priority, 'critical','high','medium','low'), r.created_at DESC
    LIMIT ? OFFSET ?
";
$listStmt = $db->prepare($listSql);
foreach ($params as $index => $value) {
    $listStmt->bindValue($index + 1, $value);
}
  $listStmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
  $listStmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$listStmt->execute();
$reports = $listStmt->fetchAll();

$reportMessages = [];
if ($reports) {
  $reportIds = array_column($reports, 'id');
  $placeholders = implode(',', array_fill(0, count($reportIds), '?'));
  $msgStmt = $db->prepare("SELECT * FROM report_messages WHERE report_id IN ({$placeholders}) ORDER BY created_at ASC");
  $msgStmt->execute($reportIds);
  foreach ($msgStmt->fetchAll() as $message) {
    $reportMessages[$message['report_id']][] = $message;
  }
}

$reportSurveys = [];
if ($reports) {
  $reportIds = array_column($reports, 'id');
  $placeholders = implode(',', array_fill(0, count($reportIds), '?'));
  $surveyStmt = $db->prepare("SELECT * FROM report_outcome_surveys WHERE report_id IN ({$placeholders})");
  $surveyStmt->execute($reportIds);
  foreach ($surveyStmt->fetchAll() as $survey) {
    $reportSurveys[$survey['report_id']] = $survey;
  }
}

$preserved = $_GET;
unset($preserved['page']);
$queryBase = http_build_query($preserved);
$queryBase = $queryBase !== '' ? $queryBase . '&' : '';
$exportQuery = http_build_query($_GET);
$exportQuery = $exportQuery !== '' ? $exportQuery : '';

adminRenderHeader('Reports', 'reports');
adminRenderSubTitle('Review reports, update status, and assign cases to admins.');
adminRenderFlash($successMessage, $errorMessage);
?>

<style>
.evidence-list { display: flex; flex-direction: column; gap: 0.35rem; }
.evidence-item { display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; }
.evidence-thumb { width: 36px; height: 36px; border-radius: 6px; border: 1px solid rgba(255, 255, 255, 0.15); object-fit: cover; }
.evidence-link { color: var(--cyan); text-decoration: none; }
.evidence-link:hover { text-decoration: underline; }
</style>

<div class="panel space-bottom">
  <form method="GET">
    <div class="form-row">
      <input type="text" name="q" placeholder="Search by tracking ID, title, description, reporter..." value="<?= adminH($query) ?>">
      <select name="status">
        <option value="">All Statuses</option>
        <?php foreach ($statusOptions as $status): ?>
        <option value="<?= adminH($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= adminH(ucwords(str_replace('_', ' ', $status))) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="priority">
        <option value="">All Priorities</option>
        <?php foreach ($priorityOptions as $priority): ?>
        <option value="<?= adminH($priority) ?>" <?= $priorityFilter === $priority ? 'selected' : '' ?>><?= adminH(ucfirst($priority)) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="category">
        <option value="">All Categories</option>
        <?php foreach ($categoryOptions as $category): ?>
        <option value="<?= adminH($category) ?>" <?= $categoryFilter === $category ? 'selected' : '' ?>><?= adminH(ucwords(str_replace('_', ' ', $category))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row">
      <button class="btn" type="submit"><i class="fas fa-search"></i> Apply Filters</button>
      <a class="btn secondary" href="reports.php"><i class="fas fa-rotate-left"></i> Reset</a>
      <a class="btn secondary" href="../../backend/admin/export_reports.php<?= $exportQuery !== '' ? '?' . adminH($exportQuery) : '' ?>"><i class="fas fa-file-csv"></i> Export CSV</a>
      <div class="muted" style="align-self:center;">Showing <?= adminH((string) count($reports)) ?> of <?= adminH((string) $totalRows) ?> reports</div>
    </div>
      <div class="form-row">
        <a class="btn secondary" href="reports.php?filter=pending"><i class="fas fa-inbox"></i> Pending</a>
        <a class="btn secondary" href="reports.php?status=under_review"><i class="fas fa-search"></i> Under Review</a>
        <a class="btn secondary" href="reports.php?filter=critical"><i class="fas fa-exclamation-triangle"></i> Critical</a>
      </div>
  </form>
</div>


<div class="panel">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Tracking</th>
          <th>Reporter</th>
          <th>Reporter Info</th>
          <th>Accused Info</th>
          <th>Title & Category</th>
          <th>Current</th>
          <th>Evidence</th>
          <th style="min-width: 320px;">Update</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$reports): ?>
        <tr><td colspan="8" class="muted" style="padding: 1rem 0.5rem;">No reports found for the selected filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($reports as $report): ?>
        <tr>
          <td>
            <strong><?= adminH($report['tracking_id']) ?></strong><br>
            <span class="muted"><?= adminH(date('Y-m-d H:i', strtotime($report['created_at']))) ?></span>
          </td>
          <td>
            <?php if ((int) $report['is_anonymous'] === 1 || !$report['student_name']): ?>
              <span class="chip">Anonymous</span>
            <?php else: ?>
              <?= adminH($report['student_name']) ?><br>
              <span class="muted"><?= adminH($report['student_code']) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ((int)$report['is_anonymous'] === 1): ?>
                <span class="chip">Anonymous</span>
            <?php elseif ($report['student_name']): ?>
                <strong><?= adminH($report['student_name']) ?></strong><br>
                <span class="muted">ID: <?= adminH($report['student_code']) ?></span><br>
                <?php 
                // Look up additional details from directory
                $studentDir = getStudentFromDirectory($report['student_code']);
                if ($studentDir): ?>
                    <span class="muted"><?= adminH($studentDir['department']) ?> | Batch: <?= adminH($studentDir['graduation_year']) ?></span>
                <?php endif; ?>
              <div class="muted" style="margin-top:0.3rem;">Email updates: <?= !empty($report['notify_opt_in']) && !empty($report['notify_email']) ? 'On' : 'Off' ?></div>
            <?php else: ?>
                <span class="muted">Not logged in</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!empty($report['accused_student_id'])): ?>
                <strong>ID: <?= adminH($report['accused_student_id']) ?></strong><br>
                <?php 
                $accused = getStudentFromDirectory($report['accused_student_id']);
                if ($accused): ?>
                    <?= adminH($accused['full_name']) ?><br>
                    <span class="muted"><?= adminH($accused['department']) ?></span>
                    <div class="space-top">
                      <?php if ((int) $report['accused_verified'] === 1): ?>
                        <span class="chip resolved">Verified</span>
                      <?php else: ?>
                        <span class="chip pending">Unverified</span>
                      <?php endif; ?>
                    </div>
                    <div class="space-top" style="display:flex; gap:0.4rem; flex-wrap:wrap;">
                      <?php if ((int) $report['accused_verified'] !== 1): ?>
                        <form method="POST">
                          <input type="hidden" name="action" value="verify_accused">
                          <input type="hidden" name="report_id" value="<?= adminH($report['id']) ?>">
                          <button class="btn secondary" type="submit">Verify ID</button>
                        </form>
                      <?php else: ?>
                        <form method="POST">
                          <input type="hidden" name="action" value="unverify_accused">
                          <input type="hidden" name="report_id" value="<?= adminH($report['id']) ?>">
                          <button class="btn secondary" type="submit">Mark Unverified</button>
                        </form>
                      <?php endif; ?>
                    </div>
                <?php elseif (!empty($report['accused_name'])): ?>
                  <?= adminH($report['accused_name']) ?>
                <?php else: ?>
                  <span class="muted">ID not found in directory</span>
                <?php endif; ?>
            <?php elseif (!empty($report['accused_name'])): ?>
                <?= adminH($report['accused_name']) ?>
            <?php else: ?>
                <span class="muted">Not specified</span>
            <?php endif; ?>
          </td>
          <td>
            <strong><?= adminH($report['title']) ?></strong><br>
            <span class="muted"><?= adminH(ucwords(str_replace('_', ' ', $report['category']))) ?></span>
          </td>
          <td>
            <span class="chip <?= adminH($report['status']) ?>"><?= adminH(ucwords(str_replace('_', ' ', $report['status']))) ?></span><br>
            <span class="chip <?= adminH($report['priority']) ?>"><?= adminH(ucfirst($report['priority'])) ?></span><br>
            <?php if (!empty($report['manual_spam']) || !empty($report['spam_needs_verification']) || !empty($report['ai_is_spam']) || (!empty($report['spam_score']) && (float) $report['spam_score'] >= 0.6)): ?>
              <span class="chip pending">Flagged</span><br>
            <?php endif; ?>
            <span class="muted">Assigned: <?= adminH($report['assigned_username'] ?: 'none') ?></span>
          </td>
          <td>
            <?php
              $evidenceHtml = [];
                if (!empty($report['evidence_files'])) {
                  $decoded = json_decode((string) $report['evidence_files'], true);
                  if (is_array($decoded)) {
                    foreach ($decoded as $idx => $entry) {
                      $item = $renderEvidenceItem($entry, (int) $report['id'], (int) $idx);
                          if ($item !== '') {
                              $evidenceHtml[] = $item;
                          }
                      }
                  }
              }
            ?>
            <?php if ($evidenceHtml): ?>
              <div class="evidence-list"><?= implode('', $evidenceHtml) ?></div>
            <?php else: ?>
              <span class="muted">No evidence</span>
            <?php endif; ?>
          </td>
          <td>
            <form method="POST">
              <input type="hidden" name="action" value="update_report">
              <input type="hidden" name="report_id" value="<?= adminH($report['id']) ?>">
              <div class="form-row">
                <select name="status" required>
                  <?php foreach ($statusOptions as $status): ?>
                  <option value="<?= adminH($status) ?>" <?= $report['status'] === $status ? 'selected' : '' ?>><?= adminH(ucwords(str_replace('_', ' ', $status))) ?></option>
                  <?php endforeach; ?>
                </select>
                <select name="priority" required>
                  <?php foreach ($priorityOptions as $priority): ?>
                  <option value="<?= adminH($priority) ?>" <?= $report['priority'] === $priority ? 'selected' : '' ?>><?= adminH(ucfirst($priority)) ?></option>
                  <?php endforeach; ?>
                </select>
                <select name="assigned_to">
                  <option value="">Unassigned</option>
                  <?php foreach ($admins as $admin): ?>
                  <option value="<?= adminH($admin['id']) ?>" <?= (int) $report['assigned_to'] === (int) $admin['id'] ? 'selected' : '' ?>>
                    <?= adminH($admin['username']) ?> (<?= adminH($admin['full_name']) ?>)
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <textarea name="admin_notes" placeholder="Internal notes for this report..."><?= adminH($report['admin_notes'] ?? '') ?></textarea>
              <div class="space-top" style="display:flex; gap:0.5rem; align-items:center;">
                <button class="btn" type="submit"><i class="fas fa-save"></i> Save</button>
                <a class="btn secondary" href="../report_status.php?id=<?= urlencode((string) $report['tracking_id']) ?>" target="_blank" rel="noopener">Open Public View</a>
                <a class="btn secondary" href="case_room.php?report_id=<?= adminH($report['id']) ?>" target="_blank" rel="noopener">Case Room</a>
              </div>
            </form>

            <div class="space-top">
              <div class="muted" style="margin-bottom:0.4rem;">Public Messages</div>
              <?php $thread = $reportMessages[$report['id']] ?? []; ?>
              <?php if ($thread): ?>
                <div style="max-height:140px; overflow:auto; border:1px solid rgba(255,255,255,0.1); border-radius:8px; padding:0.5rem;">
                  <?php foreach ($thread as $msg): ?>
                    <div style="margin-bottom:0.5rem;">
                      <div class="muted" style="font-size:0.7rem;">
                        <?= $msg['sender_type'] === 'admin' ? 'Admin' : 'Reporter' ?> • <?= adminH(date('Y-m-d H:i', strtotime($msg['created_at']))) ?>
                      </div>
                      <div style="font-size:0.85rem; white-space:pre-wrap;"><?= adminH($msg['message']) ?></div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="muted">No messages yet.</div>
              <?php endif; ?>
            </div>

            <form method="POST" class="space-top">
              <input type="hidden" name="action" value="add_message">
              <input type="hidden" name="report_id" value="<?= adminH($report['id']) ?>">
              <textarea name="message" placeholder="Send a message to the reporter..." required></textarea>
              <div class="space-top" style="display:flex; justify-content:flex-end;">
                <button class="btn secondary" type="submit"><i class="fas fa-paper-plane"></i> Send</button>
              </div>
            </form>

            <div class="space-top">
              <div class="muted" style="margin-bottom:0.4rem;">ID Verification</div>
              <div class="muted" style="font-size:0.8rem;">Status: <?= adminH(ucwords(str_replace('_', ' ', (string) $report['id_verification_status']))) ?></div>
              <?php if (!empty($report['id_verification_file'])): ?>
                <div class="space-top">
                  <a class="btn secondary" href="../../backend/admin/id_verification.php?report_id=<?= adminH($report['id']) ?>" target="_blank" rel="noopener">View ID Document</a>
                </div>
              <?php endif; ?>

              <?php if (empty($report['id_verification_required'])): ?>
                <form method="POST" class="space-top">
                  <input type="hidden" name="action" value="request_id_verification">
                  <input type="hidden" name="report_id" value="<?= adminH($report['id']) ?>">
                  <button class="btn secondary" type="submit">Request ID Verification</button>
                </form>
              <?php elseif ($report['id_verification_status'] === 'submitted'): ?>
                <form method="POST" class="space-top">
                  <input type="hidden" name="report_id" value="<?= adminH($report['id']) ?>">
                  <textarea name="id_review_note" placeholder="Review note (optional)"></textarea>
                  <div class="space-top" style="display:flex; gap:0.5rem; justify-content:flex-end;">
                    <button class="btn" name="action" value="approve_id" type="submit">Approve</button>
                    <button class="btn secondary" name="action" value="reject_id" type="submit">Reject</button>
                  </div>
                </form>
              <?php endif; ?>
            </div>

            <div class="space-top">
              <div class="muted" style="margin-bottom:0.4rem;">Spam Review</div>
              <?php if (!empty($report['manual_spam'])): ?>
                <div class="muted" style="font-size:0.8rem;">Manually flagged as spam.</div>
                <?php if (!empty($report['manual_spam_note'])): ?>
                  <div class="muted" style="font-size:0.8rem; margin-top:0.35rem;">Note: <?= adminH($report['manual_spam_note']) ?></div>
                <?php endif; ?>
                <form method="POST" class="space-top">
                  <input type="hidden" name="action" value="clear_spam">
                  <input type="hidden" name="report_id" value="<?= adminH($report['id']) ?>">
                  <button class="btn secondary" type="submit">Clear Spam Flag</button>
                </form>
              <?php else: ?>
                <form method="POST" class="space-top">
                  <input type="hidden" name="action" value="mark_spam">
                  <input type="hidden" name="report_id" value="<?= adminH($report['id']) ?>">
                  <textarea name="spam_note" placeholder="Reason for spam flag (optional)"></textarea>
                  <div class="space-top" style="display:flex; justify-content:flex-end;">
                    <button class="btn secondary" type="submit">Mark as Spam</button>
                  </div>
                </form>
              <?php endif; ?>
            </div>

            <div class="space-top">
              <div class="muted" style="margin-bottom:0.4rem;">Outcome Survey</div>
              <?php $survey = $reportSurveys[$report['id']] ?? null; ?>
              <?php if ($survey): ?>
                <div class="muted">Satisfaction: <?= adminH((string) $survey['satisfaction_score']) ?>/5</div>
                <div class="muted">Response Time: <?= adminH((string) $survey['response_time_score']) ?>/5</div>
                <div class="muted">Fairness: <?= adminH((string) $survey['fairness_score']) ?>/5</div>
                <?php if (!empty($survey['comments'])): ?>
                  <div class="muted" style="margin-top:0.4rem;">“<?= adminH($survey['comments']) ?>”</div>
                <?php endif; ?>
              <?php else: ?>
                <div class="muted">No survey submitted yet.</div>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="space-top" style="display:flex; gap:0.45rem; flex-wrap:wrap;">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <a class="btn <?= $p === $page ? '' : 'secondary' ?>" href="?<?= adminH($queryBase) ?>page=<?= adminH((string) $p) ?>"><?= adminH((string) $p) ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php adminRenderFooter(); ?>
