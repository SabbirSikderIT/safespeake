<?php
require_once __DIR__ . '/../backend/includes/config.php';
ensureReportingTables();

$error = '';
$infoMessage = '';
$success = false;
$tracking_id = '';
$followup_token = null;
$showVerification = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_code') {
  $code = trim((string) ($_POST['verification_code'] ?? ''));
  $verification = consumeSpamVerification('report_anonymous', $code);
  if (!$verification['ok']) {
    $error = $verification['error'];
    $showVerification = true;
  } else {
    $pending = $verification['pending'] ?? [];
    $db = getDB();

    $stmt = $db->prepare(
      "INSERT INTO reports (tracking_id, student_id, category, title, description, incident_date, incident_location, accused_student_id, accused_name, accused_department, involved_persons, is_anonymous, evidence_files, notify_email, notify_opt_in, priority, ai_sentiment_score, ai_sentiment_label, ai_priority_score, ai_spam_score, ai_is_spam, ai_risk_keywords, ai_analysis_done, ai_notes, ip_address, user_agent, content_hash, spam_score, spam_flags, spam_needs_verification, id_verification_required, id_verification_status) "
      . "VALUES (:tracking_id, :student_id, :category, :title, :description, :incident_date, :incident_location, :accused_student_id, :accused_name, :accused_department, :involved_persons, :is_anonymous, :evidence_files, :notify_email, :notify_opt_in, :priority, :ai_sentiment_score, :ai_sentiment_label, :ai_priority_score, :ai_spam_score, :ai_is_spam, :ai_risk_keywords, :ai_analysis_done, :ai_notes, :ip_address, :user_agent, :content_hash, :spam_score, :spam_flags, :spam_needs_verification, :id_verification_required, :id_verification_status)"
    );
    $stmt->execute($pending['insert_params'] ?? []);

    $tracking_id = $pending['tracking_id'] ?? '';
    $reportId = (int) $db->lastInsertId();
    if ($reportId > 0) {
      ensureReportingTables();
      $followup_token = createFollowupToken($reportId);
    }

    if (!empty($pending['confirmation_email'])) {
      sendReportConfirmationEmail(
        $pending['confirmation_email'],
        $tracking_id,
        $pending['title'] ?? 'Report',
        $pending['category'] ?? 'other',
        true
      );
    }

    $success = true;
  }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = sanitize($_POST['category'] ?? '');
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $incident_date = sanitize($_POST['incident_date'] ?? '');
    $incident_location = sanitize($_POST['incident_location'] ?? '');
    $accused_student_id = sanitize($_POST['accused_student_id'] ?? '');
    $accused_name = sanitize($_POST['accused_name'] ?? '');
    $accused_department = sanitize($_POST['accused_department'] ?? '');
    $involved_persons = sanitize($_POST['involved_persons'] ?? '');
    $contact_email = sanitize($_POST['contact_email'] ?? '');
    $notify_opt_in = isset($_POST['notify_opt_in']) ? 1 : 0;
    
    $valid_cats = ['bullying','harassment','sexual_harassment','academic_misconduct','discrimination','violence','mental_health','other'];
    
    if (!$category || !in_array($category, $valid_cats, true)) {
        $error = 'Please select a valid category.';
    } elseif (!$title || strlen($title) < 5) {
        $error = 'Please provide a meaningful title (at least 5 characters).';
    } elseif (!$description || strlen($description) < 20) {
        $error = 'Please provide a detailed description (at least 20 characters).';
    } elseif ($contact_email !== '' && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif ($notify_opt_in && $contact_email === '') {
      $error = 'Please provide an email address for status updates.';
    } else {
        // Handle file uploads
        $evidence_files = [];
        if (!empty($_FILES['evidence']['name'][0])) {
            foreach ($_FILES['evidence']['tmp_name'] as $k => $tmp) {
                if ($_FILES['evidence']['error'][$k] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['evidence']['name'][$k], PATHINFO_EXTENSION));
                    $allowed = ['jpg','jpeg','png','gif','pdf','doc','docx','mp4','mov'];
                    if (in_array($ext, $allowed, true) && $_FILES['evidence']['size'][$k] <= MAX_FILE_SIZE) {
                        $fname = uniqid('ev_') . '.' . $ext;
                        if (move_uploaded_file($tmp, UPLOAD_PATH . $fname)) {
                            $evidence_files[] = $fname;
                        }
                    }
                }
            }
        }
        
        $reportText = $title . ' ' . $description;
        $contentHash = buildContentHash($reportText);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $spamSettings = getSpamSettings();
        $rateCheck = checkRateLimit($ipAddress, 'submit_report', 3600, (int) ($spamSettings['rate_limit_max'] ?? 5));
        $duplicateCount = $contentHash ? getDuplicateReportCount($contentHash, (int) ($spamSettings['duplicate_window_days'] ?? 7)) : 0;

        // AI Analysis
        $ai = analyzeReport($reportText);
        $priority = applyPriorityOverrides($category, $title . ' ' . $description, $ai['priority']);

        $spamSignals = assessSpamSignals(
          $reportText,
          $contact_email,
          $rateCheck['count'],
          $duplicateCount,
          $spamSettings
        );
        $combinedSpamScore = max($ai['spam_score'], $spamSignals['score']);
        $spamScoreThreshold = (float) ($spamSettings['spam_score_threshold'] ?? 0.7);
        $combinedIsSpam = ($ai['is_spam'] || $combinedSpamScore >= $spamScoreThreshold) ? 1 : 0;
        $requireIdVerification = ($spamSignals['needs_verification'] || $combinedIsSpam) ? 1 : 0;
        $idVerificationStatus = $requireIdVerification ? 'requested' : 'not_requested';
        $spamFlags = array_unique(array_merge($spamSignals['flags'], $ai['is_spam'] ? ['text_patterns'] : []));
        $spamFlagsText = $spamFlags ? implode(', ', $spamFlags) : null;
        
        $tracking_id = generateTrackingID();
        $db = getDB();
        $notify_email = $notify_opt_in ? $contact_email : null;

        if ($spamSignals['needs_verification']) {
          if ($contact_email === '') {
            $error = 'We need a valid email address to verify this report.';
          } else {
            $pending = [
              'tracking_id' => $tracking_id,
              'title' => $title,
              'category' => $category,
              'confirmation_email' => $contact_email,
              'insert_params' => [
                ':tracking_id' => $tracking_id,
                ':student_id' => null,
                ':category' => $category,
                ':title' => $title,
                ':description' => $description,
                ':incident_date' => $incident_date ?: null,
                ':incident_location' => $incident_location ?: null,
                ':accused_student_id' => $accused_student_id ?: null,
                ':accused_name' => $accused_name ?: null,
                ':accused_department' => $accused_department ?: null,
                ':involved_persons' => $involved_persons ?: null,
                ':is_anonymous' => 1,
                ':evidence_files' => $evidence_files ? json_encode($evidence_files) : null,
                ':notify_email' => $notify_email,
                ':notify_opt_in' => $notify_opt_in,
                ':priority' => $priority,
                ':ai_sentiment_score' => $ai['sentiment_score'],
                ':ai_sentiment_label' => $ai['sentiment_label'],
                ':ai_priority_score' => $ai['priority_score'],
                ':ai_spam_score' => $ai['spam_score'],
                ':ai_is_spam' => $combinedIsSpam,
                ':ai_risk_keywords' => $ai['risk_keywords'],
                ':ai_analysis_done' => 1,
                ':ai_notes' => $ai['ai_notes'],
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
                ':content_hash' => $contentHash,
                ':spam_score' => $combinedSpamScore,
                ':spam_flags' => $spamFlagsText,
                ':spam_needs_verification' => 1,
                ':id_verification_required' => $requireIdVerification,
                ':id_verification_status' => $idVerificationStatus,
              ],
            ];

            if (startSpamVerification($contact_email, 'report_anonymous', $pending)) {
              $infoMessage = 'We sent a verification code to ' . htmlspecialchars($contact_email, ENT_QUOTES, 'UTF-8') . '. Enter it below to submit your report.';
              $showVerification = true;
            } else {
              $error = 'Unable to send verification code. Please try again.';
            }
          }
        } else {
          $stmt = $db->prepare(
            "INSERT INTO reports (tracking_id, student_id, category, title, description, incident_date, incident_location, accused_student_id, accused_name, accused_department, involved_persons, is_anonymous, evidence_files, notify_email, notify_opt_in, priority, ai_sentiment_score, ai_sentiment_label, ai_priority_score, ai_spam_score, ai_is_spam, ai_risk_keywords, ai_analysis_done, ai_notes, ip_address, user_agent, content_hash, spam_score, spam_flags, spam_needs_verification, id_verification_required, id_verification_status) "
            . "VALUES (:tracking_id, :student_id, :category, :title, :description, :incident_date, :incident_location, :accused_student_id, :accused_name, :accused_department, :involved_persons, :is_anonymous, :evidence_files, :notify_email, :notify_opt_in, :priority, :ai_sentiment_score, :ai_sentiment_label, :ai_priority_score, :ai_spam_score, :ai_is_spam, :ai_risk_keywords, :ai_analysis_done, :ai_notes, :ip_address, :user_agent, :content_hash, :spam_score, :spam_flags, :spam_needs_verification, :id_verification_required, :id_verification_status)"
          );
          $stmt->execute([
            ':tracking_id' => $tracking_id,
            ':student_id' => null,
            ':category' => $category,
            ':title' => $title,
            ':description' => $description,
            ':incident_date' => $incident_date ?: null,
            ':incident_location' => $incident_location ?: null,
            ':accused_student_id' => $accused_student_id ?: null,
            ':accused_name' => $accused_name ?: null,
            ':accused_department' => $accused_department ?: null,
            ':involved_persons' => $involved_persons ?: null,
            ':is_anonymous' => 1,
            ':evidence_files' => $evidence_files ? json_encode($evidence_files) : null,
            ':notify_email' => $notify_email,
            ':notify_opt_in' => $notify_opt_in,
            ':priority' => $priority,
            ':ai_sentiment_score' => $ai['sentiment_score'],
            ':ai_sentiment_label' => $ai['sentiment_label'],
            ':ai_priority_score' => $ai['priority_score'],
            ':ai_spam_score' => $ai['spam_score'],
            ':ai_is_spam' => $combinedIsSpam,
            ':ai_risk_keywords' => $ai['risk_keywords'],
            ':ai_analysis_done' => 1,
            ':ai_notes' => $ai['ai_notes'],
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
            ':content_hash' => $contentHash,
            ':spam_score' => $combinedSpamScore,
            ':spam_flags' => $spamFlagsText,
            ':spam_needs_verification' => 0,
            ':id_verification_required' => $requireIdVerification,
            ':id_verification_status' => $idVerificationStatus,
          ]);

          $reportId = (int) $db->lastInsertId();
          if ($reportId > 0) {
            ensureReportingTables();
            $followup_token = createFollowupToken($reportId);
          }

          if (!empty($contact_email)) {
              sendReportConfirmationEmail(
                  $contact_email,
                  $tracking_id,
                  $title,
                  $category,
                  true
              );
          }

          $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Anonymous Report — SafeSpeak</title>
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
  max-width: 700px;
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
label .req { color: var(--cyan); }
input, select, textarea {
  width: 100%;
  background: rgba(255,255,255,0.06);
  border: 1.5px solid rgba(255,255,255,0.12);
  border-radius: 8px;
  padding: 0.75rem 1rem;
  color: var(--white);
  font-size: 0.9rem;
  font-family: 'DM Sans', sans-serif;
  outline: none;
  transition: border-color 0.2s;
}
input::placeholder, textarea::placeholder { color: rgba(255,255,255,0.25); }
input:focus, select:focus, textarea:focus {
  border-color: var(--blue);
  background: rgba(26,86,232,0.06);
}
textarea { resize: vertical; min-height: 130px; }
.char-count {
  font-size: 0.75rem;
  color: var(--gray-600);
  text-align: right;
  margin-top: 0.25rem;
}
.btn-submit {
  width: 100%;
  padding: 1rem;
  background: var(--grad);
  color: var(--white);
  border: none;
  border-radius: 10px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  font-family: 'DM Sans', sans-serif;
  transition: opacity 0.2s, transform 0.2s;
}
.btn-submit:hover { opacity: 0.9; transform: translateY(-1px); }
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
  background: rgba(6,182,212,0.1);
  border: 1px solid rgba(6,182,212,0.3);
  color: #7dd3fc;
}
.msg.success {
  background: rgba(16,185,129,0.1);
  border: 1px solid rgba(16,185,129,0.3);
  color: #6ee7b7;
}
.success-card {
  background: rgba(16,185,129,0.08);
  border: 1px solid rgba(16,185,129,0.3);
  border-radius: 16px;
  padding: 2.5rem;
  text-align: center;
}
.success-card .check { font-size: 4rem; margin-bottom: 1rem; }
.success-card h2 {
  font-family: 'Syne', sans-serif;
  font-size: 1.6rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
}
.success-card p { color: var(--gray-400); margin-bottom: 1.5rem; }
.tracking-box {
  background: rgba(6,182,212,0.1);
  border: 1px solid rgba(6,182,212,0.3);
  border-radius: 10px;
  padding: 1rem;
  margin: 1rem 0;
  font-family: monospace;
  font-size: 1.3rem;
  color: var(--cyan);
  font-weight: 700;
  letter-spacing: 0.05em;
}
.btn {
  padding: 0.7rem 1.4rem;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  text-decoration: none;
  border: none;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-family: 'DM Sans', sans-serif;
}
.btn-primary { background: var(--grad); color: var(--white); }
.btn-outline {
  background: transparent;
  border: 1.5px solid rgba(255,255,255,0.2);
  color: var(--white);
}
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
        <div class="card-icon">🔒</div>
        <h1>Submit Anonymous Report</h1>
        <p>Your identity will remain completely hidden. No account required.</p>
      </div>

      <?php if ($success): ?>
      <div class="success-card">
        <div class="check">✅</div>
        <h2>Report Submitted Anonymously!</h2>
        <p>Your report has been received and AI analysis has been completed. Use the tracking ID below to monitor progress.</p>
        <div class="tracking-box"><?= htmlspecialchars($tracking_id, ENT_QUOTES, 'UTF-8') ?></div>
        <?php if ($followup_token): ?>
          <p style="font-size:0.85rem;color:var(--gray-400);">Anonymous follow-up token (save this to add more evidence later):</p>
          <div class="tracking-box"><?= htmlspecialchars($followup_token, ENT_QUOTES, 'UTF-8') ?></div>
          <a href="followup.php?token=<?= urlencode($followup_token) ?>" class="btn btn-outline" style="margin-bottom:1rem;">Add Follow-up Evidence</a>
        <?php endif; ?>
        <p style="font-size:0.8rem;color:var(--gray-600);">Save this ID! You'll need it to track your report status.</p>
        <div style="display:flex;gap:0.75rem;justify-content:center;margin-top:1.5rem;flex-wrap:wrap;">
          <a href="report_status.php?id=<?= urlencode($tracking_id) ?>" class="btn btn-primary">View Report Status</a>
          <a href="report_anonymous.php" class="btn btn-outline">Submit Another</a>
          <a href="index.php" class="btn btn-outline">Home</a>
        </div>
      </div>
      <?php else: ?>

      <?php if ($error): ?><div class="msg error">⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <?php if ($infoMessage): ?><div class="msg info">ℹ️ <?= $infoMessage ?></div><?php endif; ?>

      <?php if ($showVerification): ?>
        <div class="form-group" style="margin-bottom:1rem;">
          <p class="muted" style="text-align:center;">Enter the 6-digit verification code we sent to your email to finish submitting this report.</p>
        </div>
        <form method="POST">
          <input type="hidden" name="action" value="verify_code">
          <div class="form-group">
            <label>Verification Code <span class="req">*</span></label>
            <input type="text" name="verification_code" maxlength="6" required>
          </div>
          <button type="submit" class="btn-submit">Verify & Submit</button>
        </form>
      <?php else: ?>

      <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
          <label>Incident Category <span class="req">*</span></label>
          <select name="category" required>
            <option value="">-- Select Category --</option>
            <option value="bullying">Bullying</option>
            <option value="harassment">Harassment</option>
            <option value="sexual_harassment">Sexual Harassment</option>
            <option value="academic_misconduct">Academic Misconduct</option>
            <option value="discrimination">Discrimination</option>
            <option value="violence">Violence / Physical Assault</option>
            <option value="mental_health">Mental Health / Distress</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="form-group">
          <label>Report Title <span class="req">*</span></label>
          <input type="text" name="title" placeholder="Brief summary of the incident" required maxlength="500" id="titleInput" oninput="document.getElementById('titleCount').textContent=this.value.length+'/500'">
          <div class="char-count" id="titleCount">0/500</div>
        </div>
        <div class="form-group">
          <label>Detailed Description <span class="req">*</span></label>
          <textarea name="description" placeholder="Describe what happened in detail. Include when, where, what happened, and any other relevant information. The more detail you provide, the better we can help." required id="descInput" oninput="document.getElementById('descCount').textContent=this.value.length+' characters'"></textarea>
          <div class="char-count" id="descCount">0 characters</div>
        </div>
        <div class="form-group">
          <label>Email (Optional - for confirmation)</label>
          <input type="email" name="contact_email" placeholder="your@email.com (to receive confirmation)">
          <div class="char-count" style="color:var(--gray-600);">
            We'll send a confirmation to this email. Your identity stays anonymous.
          </div>
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:0.6rem;">
            <input type="checkbox" name="notify_opt_in" checked style="width:auto;">
            <span>Send me status updates by email</span>
          </label>
        </div>
        <div class="form-group">
          <label>Date of Incident (Optional)</label>
          <input type="date" name="incident_date" max="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label>Location (Optional)</label>
          <input type="text" name="incident_location" placeholder="e.g. Main cafeteria, Block A">
        </div>
        <div class="form-group">
          <label>Persons Involved (Optional)</label>
          <input type="text" name="involved_persons" placeholder="Describe without full names if possible">
        </div>
        <div class="form-group">
          <label>Evidence Upload (Optional)</label>
          <input type="file" name="evidence[]" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.mp4,.mov">
          <div style="font-size:0.75rem;color:var(--gray-600);margin-top:0.25rem;">Max 5MB per file. Images, PDF, Documents, Videos allowed.</div>
        </div>
        <button type="submit" class="btn-submit">🚀 Submit Anonymous Report</button>
        <p style="text-align:center;color:var(--gray-600);font-size:0.8rem;margin-top:0.75rem;">
          All reports are analyzed by our AI system for urgency and authenticity
        </p>
      </form>
      <?php endif; ?>
      
      <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid rgba(255,255,255,0.08);">
        <p style="font-size:0.85rem;color:var(--gray-400);">
          <strong>Note:</strong> By submitting anonymously, you cannot later edit or delete this report. 
          However, you can track its status using the tracking ID provided after submission.
        </p>
        <p style="font-size:0.85rem;color:var(--gray-400);margin-top:0.5rem;">
          Want to create an account to manage your reports? <a href="register.php" style="color:var(--cyan);">Register here</a>.
        </p>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>
</body>
</html>