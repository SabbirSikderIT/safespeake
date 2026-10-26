<?php
require_once __DIR__ . '/../backend/includes/config.php';

$tracking_id = $_GET['id'] ?? '';
$report = null;
$error = '';
$messageError = '';
$messageSuccess = '';
$messages = [];
$surveyError = '';
$surveySuccess = '';
$survey = null;
$idError = '';
$idSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string) ($_POST['action'] ?? '');

  if ($action === 'add_message') {
    $tracking_id = trim((string) ($_POST['tracking_id'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($tracking_id === '') {
      $messageError = 'Tracking ID is required.';
    } elseif (strlen($message) < 5) {
      $messageError = 'Message must be at least 5 characters.';
    } else {
      $db = getDB();
      ensureReportingTables();
      $stmt = $db->prepare("SELECT id FROM reports WHERE tracking_id = ?");
      $stmt->execute([$tracking_id]);
      $row = $stmt->fetch();

      if (!$row) {
        $messageError = 'Report not found for this tracking ID.';
      } else {
        $insert = $db->prepare("INSERT INTO report_messages (report_id, sender_type, message) VALUES (?, 'reporter', ?)");
        $insert->execute([(int) $row['id'], $message]);
        $messageSuccess = 'Message sent to the admin team.';
      }
    }
  } elseif ($action === 'submit_survey') {
    $tracking_id = trim((string) ($_POST['tracking_id'] ?? ''));
    $satisfaction = (int) ($_POST['satisfaction_score'] ?? 0);
    $responseTime = (int) ($_POST['response_time_score'] ?? 0);
    $fairness = (int) ($_POST['fairness_score'] ?? 0);
    $comments = trim((string) ($_POST['comments'] ?? ''));

    if ($tracking_id === '') {
      $surveyError = 'Tracking ID is required.';
    } elseif ($satisfaction < 1 || $satisfaction > 5 || $responseTime < 1 || $responseTime > 5 || $fairness < 1 || $fairness > 5) {
      $surveyError = 'Please provide ratings between 1 and 5.';
    } else {
      $db = getDB();
      ensureReportingTables();
      $stmt = $db->prepare("SELECT id, status FROM reports WHERE tracking_id = ?");
      $stmt->execute([$tracking_id]);
      $row = $stmt->fetch();

      if (!$row) {
        $surveyError = 'Report not found for this tracking ID.';
      } elseif ($row['status'] !== 'resolved') {
        $surveyError = 'Outcome survey is available after the report is resolved.';
      } else {
        $check = $db->prepare("SELECT id FROM report_outcome_surveys WHERE report_id = ?");
        $check->execute([(int) $row['id']]);
        if ($check->fetch()) {
          $surveyError = 'Outcome survey has already been submitted.';
        } else {
          $insert = $db->prepare("INSERT INTO report_outcome_surveys (report_id, satisfaction_score, response_time_score, fairness_score, comments) VALUES (?, ?, ?, ?, ?)");
          $insert->execute([
            (int) $row['id'],
            $satisfaction,
            $responseTime,
            $fairness,
            $comments !== '' ? $comments : null,
          ]);
          $surveySuccess = 'Thank you for your feedback.';
        }
      }
    }
  } elseif ($action === 'upload_id') {
    $tracking_id = trim((string) ($_POST['tracking_id'] ?? ''));
    if ($tracking_id === '') {
      $idError = 'Tracking ID is required.';
    } elseif (empty($_FILES['id_document']['name'])) {
      $idError = 'Please select an ID document to upload.';
    } else {
      $db = getDB();
      ensureReportingTables();
      $stmt = $db->prepare("SELECT id, id_verification_required, id_verification_status FROM reports WHERE tracking_id = ?");
      $stmt->execute([$tracking_id]);
      $row = $stmt->fetch();

      if (!$row) {
        $idError = 'Report not found for this tracking ID.';
      } elseif (empty($row['id_verification_required'])) {
        $idError = 'ID verification is not required for this report.';
      } elseif (!in_array($row['id_verification_status'], ['requested', 'rejected'], true)) {
        $idError = 'ID verification has already been submitted.';
      } else {
        $file = $_FILES['id_document'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
          $idError = 'Upload failed. Please try again.';
        } else {
          $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
          $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
          if (!in_array($ext, $allowed, true)) {
            $idError = 'Allowed file types: JPG, PNG, PDF.';
          } elseif ($file['size'] > MAX_FILE_SIZE) {
            $idError = 'File is too large. Max 5MB.';
          } else {
            $fileName = 'id_' . (int) $row['id'] . '_' . uniqid() . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], UPLOAD_ID_PATH . $fileName)) {
              $idError = 'Unable to save the uploaded file.';
            } else {
              $update = $db->prepare("UPDATE reports SET id_verification_file = ?, id_verification_status = 'submitted', id_verification_submitted_at = NOW() WHERE id = ?");
              $update->execute([$fileName, (int) $row['id']]);

              $notifyEmail = getReportNotifyEmail((int) $row['id']);
              if ($notifyEmail) {
                $infoStmt = $db->prepare("SELECT tracking_id, title FROM reports WHERE id = ?");
                $infoStmt->execute([(int) $row['id']]);
                $info = $infoStmt->fetch();
                if ($info) {
                  sendReporterNotificationEmail(
                    $notifyEmail,
                    $info['tracking_id'],
                    $info['title'],
                    'ID Verification Submitted',
                    'Your ID document has been submitted successfully and is now under review.'
                  );
                }
              }

              $idSuccess = 'ID document uploaded successfully. Our team will review it.';
            }
          }
        }
      }
    }
  }
}

if ($tracking_id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT r.*, s.full_name as student_name, s.student_id as student_sid 
                         FROM reports r 
                         LEFT JOIN students s ON r.student_id = s.id 
                         WHERE r.tracking_id = ?");
    $stmt->execute([$tracking_id]);
    $report = $stmt->fetch();
    
    if (!$report) {
        $error = 'No report found with tracking ID: ' . htmlspecialchars($tracking_id);
    } else {
      ensureReportingTables();
      $msgStmt = $db->prepare("SELECT * FROM report_messages WHERE report_id = ? ORDER BY created_at ASC");
      $msgStmt->execute([(int) $report['id']]);
      $messages = $msgStmt->fetchAll();

      $surveyStmt = $db->prepare("SELECT * FROM report_outcome_surveys WHERE report_id = ? LIMIT 1");
      $surveyStmt->execute([(int) $report['id']]);
      $survey = $surveyStmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Track Report — SafeSpeak</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
:root {
  --navy: #0a0e27;
  --blue: #1a56e8;
  --cyan: #06b6d4;
  --white: #fff;
  --gray-400: #94a3b8;
  --gray-600: #475569;
  --grad: linear-gradient(135deg, #1a56e8, #06b6d4);
  --green: #10b981;
  --amber: #f59e0b;
  --red: #ef4444;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
  font-family: 'DM Sans', sans-serif;
  background: var(--navy);
  color: var(--white);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
.bg {
  position: fixed; inset: 0;
  background: radial-gradient(ellipse 70% 60% at 30% 30%, rgba(26,86,232,0.15) 0%, transparent 60%),
              radial-gradient(ellipse 50% 40% at 80% 70%, rgba(6,182,212,0.1) 0%, transparent 60%),
              var(--navy);
  z-index: 0;
}
.page {
  position: relative; z-index: 1;
  flex: 1;
  display: flex;
  flex-direction: column;
}
header {
  padding: 1.25rem 2rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid rgba(255,255,255,0.07);
}
.logo {
  font-family: 'Syne', sans-serif;
  font-size: 1.3rem;
  font-weight: 800;
  text-decoration: none;
  color: var(--white);
}
.logo span { color: var(--cyan); }
.back-link {
  font-size: 0.85rem;
  color: var(--gray-400);
  text-decoration: none;
}
.back-link:hover { color: var(--white); }
main {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}
.card {
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 20px;
  padding: 2.5rem;
  width: 100%;
  max-width: 800px;
  backdrop-filter: blur(10px);
}
.card-header {
  text-align: center;
  margin-bottom: 2rem;
}
.card-icon {
  width: 60px; height: 60px;
  background: var(--grad);
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.6rem;
  margin: 0 auto 1rem;
}
.card-header h1 {
  font-family: 'Syne', sans-serif;
  font-size: 1.6rem;
  font-weight: 700;
  margin-bottom: 0.4rem;
}
.card-header p {
  color: var(--gray-400);
  font-size: 0.875rem;
}
.form-group { margin-bottom: 1.1rem; }
label {
  display: block;
  font-size: 0.8rem;
  font-weight: 500;
  color: var(--gray-400);
  margin-bottom: 0.4rem;
}
input {
  width: 100%;
  background: rgba(255,255,255,0.06);
  border: 1.5px solid rgba(255,255,255,0.12);
  border-radius: 8px;
  padding: 0.75rem 1rem;
  color: var(--white);
  font-size: 0.95rem;
  font-family: 'DM Sans', sans-serif;
  outline: none;
  transition: border-color 0.2s;
}
input::placeholder { color: rgba(255,255,255,0.25); }
input:focus { border-color: var(--blue); }
.btn {
  padding: 0.85rem 1.5rem;
  background: var(--grad);
  color: var(--white);
  border: none;
  border-radius: 10px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  font-family: 'DM Sans', sans-serif;
  transition: opacity 0.2s, transform 0.2s;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}
.btn:hover { opacity: 0.9; transform: translateY(-1px); }
.btn-outline {
  background: transparent;
  border: 1.5px solid rgba(255,255,255,0.2);
}
.msg {
  padding: 0.85rem 1rem;
  border-radius: 8px;
  font-size: 0.875rem;
  margin-bottom: 1rem;
}
.msg.error {
  background: rgba(239,68,68,0.1);
  border: 1px solid rgba(239,68,68,0.3);
  color: #fca5a5;
}
.msg.info {
  background: rgba(59,130,246,0.1);
  border: 1px solid rgba(59,130,246,0.3);
  color: #93c5fd;
}
.report-details {
  margin-top: 2rem;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 16px;
  padding: 1.5rem;
}
.detail-row {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 1rem;
  padding: 0.75rem 0;
  border-bottom: 1px solid rgba(255,255,255,0.05);
}
.detail-row:last-child { border-bottom: none; }
.detail-label {
  font-size: 0.8rem;
  color: var(--gray-400);
  font-weight: 500;
}
.detail-value {
  font-size: 0.9rem;
  color: var(--white);
}
.badge {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.3rem 0.65rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
}
.badge-high { background: rgba(239,68,68,0.15); color: #f87171; }
.badge-medium { background: rgba(245,158,11,0.15); color: #fbbf24; }
.badge-low { background: rgba(16,185,129,0.15); color: #6ee7b7; }
.status-pending { background: rgba(245,158,11,0.15); color: #fbbf24; }
.status-review { background: rgba(59,130,246,0.15); color: #93c5fd; }
.status-resolved { background: rgba(16,185,129,0.15); color: #6ee7b7; }
.tracking-box {
  background: rgba(6,182,212,0.1);
  border: 1px solid rgba(6,182,212,0.3);
  border-radius: 10px;
  padding: 1rem;
  font-family: monospace;
  font-size: 1.3rem;
  color: var(--cyan);
  font-weight: 700;
  letter-spacing: 0.05em;
  text-align: center;
  margin: 1rem 0;
}
.messages {
  margin-top: 2rem;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 14px;
  padding: 1.2rem;
}
.message-item {
  padding: 0.75rem 0;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}
.message-item:last-child { border-bottom: none; }
.message-meta { font-size: 0.75rem; color: var(--gray-400); margin-bottom: 0.3rem; }
.message-body { font-size: 0.9rem; color: var(--white); white-space: pre-wrap; }
.message-form textarea {
  width: 100%;
  background: rgba(255,255,255,0.06);
  border: 1.5px solid rgba(255,255,255,0.12);
  border-radius: 8px;
  padding: 0.75rem 1rem;
  color: var(--white);
  font-size: 0.9rem;
  font-family: 'DM Sans', sans-serif;
  outline: none;
  min-height: 110px;
  resize: vertical;
}
.survey-panel {
  margin-top: 2rem;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 14px;
  padding: 1.2rem;
}
.rating-group { display: grid; gap: 0.5rem; margin-bottom: 0.75rem; }
.rating-options { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.rating-options label {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 999px;
  padding: 0.35rem 0.75rem;
  font-size: 0.8rem;
  cursor: pointer;
}
.rating-options input { width: auto; }
</style>
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<link rel="stylesheet" href="theme.css">
<script src="theme.js"></script>
</head>
<body>
<div class="bg"></div>
<div class="page">
  <header>
    <a class="logo" href="index.php"><img class="brand-logo" src="assets/logo.svg" alt="" width="30" height="30">Safe<span>Speak</span></a>
    <a class="back-link" href="index.php">← Home</a>
  </header>
  <main>
    <div class="card">
      <div class="card-header">
        <div class="card-icon">🔍</div>
        <h1>Track Report Status</h1>
        <p>Enter your tracking ID to view report details and progress</p>
      </div>

      <?php if ($error): ?>
        <div class="msg error">⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form method="GET" action="">
        <div class="form-group">
          <label>Tracking ID</label>
          <input type="text" name="id" placeholder="e.g. SS20250425ABC123" value="<?= htmlspecialchars($tracking_id) ?>" required>
        </div>
        <button type="submit" class="btn">Track Report →</button>
      </form>

      <?php if ($report): ?>
      <div class="report-details">
        <div class="tracking-box"><?= htmlspecialchars($report['tracking_id'], ENT_QUOTES, 'UTF-8') ?></div>
        
        <div class="detail-row">
          <div class="detail-label">Title</div>
          <div class="detail-value"><?= htmlspecialchars($report['title']) ?></div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Category</div>
          <div class="detail-value"><?= ucwords(str_replace('_', ' ', $report['category'])) ?></div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Status</div>
          <div class="detail-value"><?= getStatusBadge($report['status']) ?></div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Priority</div>
          <div class="detail-value"><?= getPriorityBadge($report['priority']) ?></div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Submitted</div>
          <div class="detail-value"><?= date('F j, Y, g:i a', strtotime($report['created_at'])) ?></div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Last Updated</div>
          <div class="detail-value"><?= timeAgo($report['updated_at']) ?></div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Anonymity</div>
          <div class="detail-value"><?= $report['is_anonymous'] ? '🔒 Anonymous' : '👤 Identified' ?></div>
        </div>
        <?php if ((int) $report['is_anonymous'] === 1): ?>
        <div class="detail-row">
          <div class="detail-label">Follow-up</div>
          <div class="detail-value">Use your follow-up token to add evidence: <a href="followup.php" style="color:var(--cyan);">Follow-up portal</a></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($report['id_verification_required'])): ?>
        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.08);">
          <h3 style="font-family: 'Syne', sans-serif; font-size: 1rem; margin-bottom: 0.75rem; color: var(--cyan);">🪪 ID Verification</h3>
          <?php if ($idError): ?>
            <div class="msg error">⚠️ <?= htmlspecialchars($idError, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>
          <?php if ($idSuccess): ?>
            <div class="msg info">✅ <?= htmlspecialchars($idSuccess, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>

          <div class="detail-row">
            <div class="detail-label">Status</div>
            <div class="detail-value">
              <?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) $report['id_verification_status'])), ENT_QUOTES, 'UTF-8') ?>
            </div>
          </div>

          <?php if (in_array($report['id_verification_status'], ['requested', 'rejected'], true)): ?>
            <form method="POST" enctype="multipart/form-data">
              <input type="hidden" name="action" value="upload_id">
              <input type="hidden" name="tracking_id" value="<?= htmlspecialchars($report['tracking_id'], ENT_QUOTES, 'UTF-8') ?>">
              <div class="form-group">
                <label>Upload ID (JPG, PNG, PDF)</label>
                <input type="file" name="id_document" accept=".jpg,.jpeg,.png,.pdf" required>
              </div>
              <button type="submit" class="btn">Upload ID</button>
            </form>
          <?php else: ?>
            <div class="detail-row">
              <div class="detail-label">Next</div>
              <div class="detail-value">Our team will review your submission and update the status.</div>
            </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($report['ai_analysis_done']): ?>
        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.08);">
          <h3 style="font-family: 'Syne', sans-serif; font-size: 1rem; margin-bottom: 0.75rem; color: var(--cyan);">🤖 AI Analysis</h3>
          <div class="detail-row">
            <div class="detail-label">Sentiment</div>
            <div class="detail-value"><?= ucfirst(str_replace('_', ' ', $report['ai_sentiment_label'] ?? 'N/A')) ?></div>
          </div>
          <div class="detail-row">
            <div class="detail-label">Spam Detection</div>
            <div class="detail-value"><?= $report['ai_is_spam'] ? '<span class="badge badge-high">⚠️ Flagged as Spam</span>' : '<span class="badge badge-low">✓ Not Spam</span>' ?></div>
          </div>
        </div>
        <?php endif; ?>
        
        <div style="margin-top: 1.5rem; text-align: center;">
          <a href="index.php" class="btn btn-outline">← Back to Home</a>
          <?php if (isStudentLoggedIn()): ?>
            <a href="dashboard.php" class="btn">Go to Dashboard</a>
          <?php endif; ?>
        </div>
      </div>

      <div class="survey-panel">
        <h3 style="font-family:'Syne',sans-serif;font-size:1rem;margin-bottom:0.75rem;color:var(--cyan);">Case Outcome Survey</h3>
        <?php if ($surveyError): ?>
          <div class="msg error">⚠️ <?= htmlspecialchars($surveyError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($surveySuccess): ?>
          <div class="msg info">✅ <?= htmlspecialchars($surveySuccess, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($report['status'] !== 'resolved'): ?>
          <div class="msg info">Survey becomes available after the report is resolved.</div>
        <?php elseif ($survey): ?>
          <div class="msg info">Thanks for submitting the survey.</div>
          <div class="detail-row">
            <div class="detail-label">Satisfaction</div>
            <div class="detail-value"><?= htmlspecialchars((string) $survey['satisfaction_score'], ENT_QUOTES, 'UTF-8') ?>/5</div>
          </div>
          <div class="detail-row">
            <div class="detail-label">Response Time</div>
            <div class="detail-value"><?= htmlspecialchars((string) $survey['response_time_score'], ENT_QUOTES, 'UTF-8') ?>/5</div>
          </div>
          <div class="detail-row">
            <div class="detail-label">Fairness</div>
            <div class="detail-value"><?= htmlspecialchars((string) $survey['fairness_score'], ENT_QUOTES, 'UTF-8') ?>/5</div>
          </div>
          <?php if (!empty($survey['comments'])): ?>
          <div class="detail-row">
            <div class="detail-label">Comments</div>
            <div class="detail-value"><?= htmlspecialchars($survey['comments'], ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <?php endif; ?>
        <?php else: ?>
          <form method="POST">
            <input type="hidden" name="action" value="submit_survey">
            <input type="hidden" name="tracking_id" value="<?= htmlspecialchars($report['tracking_id'], ENT_QUOTES, 'UTF-8') ?>">

            <div class="rating-group">
              <label>Overall Satisfaction</label>
              <div class="rating-options">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <label><input type="radio" name="satisfaction_score" value="<?= $i ?>" required> <?= $i ?>/5</label>
                <?php endfor; ?>
              </div>
            </div>

            <div class="rating-group">
              <label>Response Time</label>
              <div class="rating-options">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <label><input type="radio" name="response_time_score" value="<?= $i ?>" required> <?= $i ?>/5</label>
                <?php endfor; ?>
              </div>
            </div>

            <div class="rating-group">
              <label>Fairness of Resolution</label>
              <div class="rating-options">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <label><input type="radio" name="fairness_score" value="<?= $i ?>" required> <?= $i ?>/5</label>
                <?php endfor; ?>
              </div>
            </div>

            <label>Comments (optional)</label>
            <textarea name="comments" placeholder="Share what worked well or what could improve..."></textarea>

            <div style="margin-top:0.75rem; display:flex; justify-content:flex-end;">
              <button class="btn" type="submit">Submit Survey</button>
            </div>
          </form>
        <?php endif; ?>
      </div>

      <div class="messages">
        <h3 style="font-family:'Syne',sans-serif;font-size:1rem;margin-bottom:0.75rem;color:var(--cyan);">Messages</h3>
        <?php if ($messageError): ?>
          <div class="msg error">⚠️ <?= htmlspecialchars($messageError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($messageSuccess): ?>
          <div class="msg info">✅ <?= htmlspecialchars($messageSuccess, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($messages): ?>
          <?php foreach ($messages as $msg): ?>
            <div class="message-item">
              <div class="message-meta">
                <?= $msg['sender_type'] === 'admin' ? 'Admin Team' : 'You' ?> • <?= htmlspecialchars(date('M d, Y H:i', strtotime($msg['created_at'])), ENT_QUOTES, 'UTF-8') ?>
              </div>
              <div class="message-body"><?= htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="msg info">No messages yet. You can send a follow-up below.</div>
        <?php endif; ?>

        <form method="POST" class="message-form" style="margin-top:1rem;">
          <input type="hidden" name="action" value="add_message">
          <input type="hidden" name="tracking_id" value="<?= htmlspecialchars($report['tracking_id'], ENT_QUOTES, 'UTF-8') ?>">
          <label style="margin-bottom:0.4rem;">Send a message to the admin team</label>
          <textarea name="message" placeholder="Write a short update or follow-up..." required></textarea>
          <div style="margin-top:0.75rem; display:flex; justify-content:flex-end;">
            <button class="btn" type="submit">Send Message</button>
          </div>
        </form>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>
</body>
</html>
