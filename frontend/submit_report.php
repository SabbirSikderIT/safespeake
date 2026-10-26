<?php
require_once __DIR__ . '/../backend/includes/config.php';
requireStudentLogin();
ensureReportingTables();

$error = '';
$infoMessage = '';
$success = false;
$tracking_id = '';
$followup_token = null;
$showVerification = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_code') {
  $code = trim((string) ($_POST['verification_code'] ?? ''));
  $verification = consumeSpamVerification('submit_report', $code);
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
    if (!empty($pending['is_anonymous']) && $reportId > 0) {
      ensureReportingTables();
      $followup_token = createFollowupToken($reportId);
    }

    if (!empty($pending['confirmation_email'])) {
      sendReportConfirmationEmail(
        $pending['confirmation_email'],
        $tracking_id,
        $pending['title'] ?? 'Report',
        $pending['category'] ?? 'other',
        !empty($pending['is_anonymous'])
      );
    }

    $success = true;
  }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category         = sanitize($_POST['category'] ?? '');
    $title            = sanitize($_POST['title'] ?? '');
    $description      = sanitize($_POST['description'] ?? '');
    $incident_date      = sanitize($_POST['incident_date'] ?? '');
    $incident_location  = sanitize($_POST['incident_location'] ?? '');
    $accused_student_id = sanitize($_POST['accused_student_id'] ?? '');
    $accused_name       = sanitize($_POST['accused_name'] ?? '');
    $accused_department = sanitize($_POST['accused_department'] ?? '');
    $involved_persons   = sanitize($_POST['involved_persons'] ?? '');
    $is_anonymous       = isset($_POST['is_anonymous']) ? 1 : 0;
    $drive_link         = sanitize($_POST['drive_link'] ?? '');
    $contact_email      = sanitize($_POST['contact_email'] ?? '');
    $notify_opt_in       = isset($_POST['notify_opt_in']) ? 1 : 0;

    $valid_cats = ['bullying','harassment','sexual_harassment','academic_misconduct','discrimination','violence','mental_health','other'];

    if (!$category || !in_array($category, $valid_cats, true)) {
        $error = 'Please select a valid category.';
    } elseif (!$title || strlen($title) < 5) {
        $error = 'Please provide a meaningful title (at least 5 characters).';
    } elseif (!$description || strlen($description) < 20) {
        $error = 'Please provide a detailed description (at least 20 characters).';
    } elseif ($contact_email !== '' && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid contact email address.';
    } elseif ($notify_opt_in && $is_anonymous && $contact_email === '') {
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

        // Handle Drive Link
        if (!empty($drive_link) && filter_var($drive_link, FILTER_VALIDATE_URL)) {
            $evidence_files[] = 'drive_link:' . $drive_link;
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
          $contact_email !== '' ? $contact_email : ($_SESSION['student_email'] ?? ''),
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

        // Store evidence files (both uploaded files and drive links)
        $evidence_json = null;
        if (!empty($evidence_files)) {
            $evidence_json = json_encode($evidence_files);
        }

        $tracking_id = generateTrackingID();
        $db = getDB();
        $notify_email = null;
        if ($notify_opt_in) {
          if ($contact_email !== '') {
            $notify_email = $contact_email;
          } elseif (!$is_anonymous) {
            $notify_email = $_SESSION['student_email'] ?? null;
          }
        }

        if ($spamSignals['needs_verification']) {
          $verificationEmail = $contact_email;
          if ($verificationEmail === '' && !$is_anonymous) {
            $verificationEmail = $_SESSION['student_email'] ?? '';
          }

          if ($verificationEmail === '') {
            $error = 'We need a valid email address to verify this report.';
          } else {
            $pending = [
              'tracking_id' => $tracking_id,
              'title' => $title,
              'category' => $category,
              'is_anonymous' => $is_anonymous,
              'confirmation_email' => !$is_anonymous ? ($_SESSION['student_email'] ?? $contact_email) : $contact_email,
              'insert_params' => [
                ':tracking_id' => $tracking_id,
                ':student_id' => $is_anonymous ? null : $_SESSION['student_id'],
                ':category' => $category,
                ':title' => $title,
                ':description' => $description,
                ':incident_date' => $incident_date ?: null,
                ':incident_location' => $incident_location ?: null,
                ':accused_student_id' => $accused_student_id ?: null,
                ':accused_name' => $accused_name ?: null,
                ':accused_department' => $accused_department ?: null,
                ':involved_persons' => $involved_persons ?: null,
                ':is_anonymous' => $is_anonymous,
                ':evidence_files' => $evidence_json,
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

            if (startSpamVerification($verificationEmail, 'submit_report', $pending)) {
              $infoMessage = 'We sent a verification code to ' . htmlspecialchars($verificationEmail, ENT_QUOTES, 'UTF-8') . '. Enter it below to submit your report.';
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
            ':student_id' => $is_anonymous ? null : $_SESSION['student_id'],
            ':category' => $category,
            ':title' => $title,
            ':description' => $description,
            ':incident_date' => $incident_date ?: null,
            ':incident_location' => $incident_location ?: null,
            ':accused_student_id' => $accused_student_id ?: null,
            ':accused_name' => $accused_name ?: null,
            ':accused_department' => $accused_department ?: null,
            ':involved_persons' => $involved_persons ?: null,
            ':is_anonymous' => $is_anonymous,
            ':evidence_files' => $evidence_json,
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
          if ($is_anonymous && $reportId > 0) {
            ensureReportingTables();
            $followup_token = createFollowupToken($reportId);
          }

          if (!$is_anonymous && isset($_SESSION['student_email']) && !empty($_SESSION['student_email'])) {
              sendReportConfirmationEmail(
                  $_SESSION['student_email'],
                  $tracking_id,
                  $title,
                  $category,
                  false
              );
          } elseif (!empty($contact_email)) {
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
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Submit Report — SafeSpeak</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
:root{--navy:#0a0e27;--blue:#1a56e8;--cyan:#06b6d4;--white:#fff;--gray-400:#94a3b8;--gray-600:#475569;--grad:linear-gradient(135deg,#1a56e8,#06b6d4);--green:#10b981;--red:#ef4444;--sidebar:240px;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:#0d1330;color:var(--white);min-height:100vh;display:flex;}
.sidebar{width:var(--sidebar);background:rgba(10,14,39,0.95);border-right:1px solid rgba(255,255,255,0.07);display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:100;transition:transform 0.3s;}
.sidebar-header{padding:1.5rem;border-bottom:1px solid rgba(255,255,255,0.07);}
.sidebar-logo{font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:800;}
.sidebar-logo span{color:var(--cyan);}
.sidebar-user{margin-top:0.75rem;font-size:0.75rem;color:var(--gray-400);}
.sidebar-user strong{color:var(--white);display:block;font-size:0.85rem;}
.sidebar-nav{flex:1;padding:1rem 0;overflow-y:auto;}
.nav-section{padding:0.25rem 1rem;font-size:0.7rem;font-weight:600;color:var(--gray-600);letter-spacing:0.08em;text-transform:uppercase;margin-top:0.75rem;}
.nav-item{display:flex;align-items:center;gap:0.6rem;padding:0.65rem 1rem;margin:0.1rem 0.75rem;border-radius:8px;text-decoration:none;color:var(--gray-400);font-size:0.875rem;transition:all 0.2s;}
.nav-item:hover,.nav-item.active{background:rgba(26,86,232,0.15);color:var(--white);}
.nav-item.active{border-left:3px solid var(--blue);}
.nav-item .icon{font-size:1rem;width:20px;text-align:center;}
.sidebar-footer{padding:1rem;border-top:1px solid rgba(255,255,255,0.07);}
.logout-btn{display:flex;align-items:center;gap:0.6rem;padding:0.65rem 1rem;border-radius:8px;color:var(--gray-400);font-size:0.875rem;text-decoration:none;transition:all 0.2s;}
.logout-btn:hover{background:rgba(239,68,68,0.1);color:#f87171;}
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;}
.topbar{padding:1rem 2rem;border-bottom:1px solid rgba(255,255,255,0.07);display:flex;align-items:center;justify-content:space-between;background:rgba(10,14,39,0.6);}
.topbar h2{font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:700;}
.content{padding:2rem;max-width:800px;}
.form-card{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:16px;padding:2rem;margin-bottom:1.5rem;}
.form-card h3{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;margin-bottom:1.25rem;color:var(--cyan);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
.form-group{margin-bottom:1.1rem;}
label{display:block;font-size:0.8rem;font-weight:500;color:var(--gray-400);margin-bottom:0.4rem;}
label .req{color:var(--cyan);}
input,select,textarea{width:100%;background:rgba(255,255,255,0.06);border:1.5px solid rgba(255,255,255,0.12);border-radius:8px;padding:0.75rem 1rem;color:var(--white);font-size:0.9rem;font-family:'DM Sans',sans-serif;outline:none;transition:border-color 0.2s;}
input::placeholder,textarea::placeholder{color:rgba(255,255,255,0.25);}
input:focus,select:focus,textarea:focus{border-color:var(--blue);background:rgba(26,86,232,0.06);}
select option{background:#1e293b;}
textarea{resize:vertical;min-height:130px;}
.char-count{font-size:0.75rem;color:var(--gray-600);text-align:right;margin-top:0.25rem;}
.toggle-anon{display:flex;align-items:center;gap:0.75rem;background:rgba(6,182,212,0.08);border:1px solid rgba(6,182,212,0.2);border-radius:10px;padding:1rem;cursor:pointer;}
.toggle-anon input[type=checkbox]{width:auto;accent-color:var(--cyan);width:18px;height:18px;}
.toggle-anon .toggle-text strong{display:block;font-size:0.9rem;margin-bottom:0.1rem;}
.toggle-anon .toggle-text span{font-size:0.8rem;color:var(--gray-400);}
.file-drop{border:2px dashed rgba(255,255,255,0.15);border-radius:10px;padding:1.75rem;text-align:center;cursor:pointer;transition:border-color 0.2s;}
.file-drop:hover,.file-drop.dragover{border-color:var(--blue);}
.file-drop .icon{font-size:2rem;margin-bottom:0.5rem;}
.file-drop p{color:var(--gray-400);font-size:0.875rem;}
.file-drop small{color:var(--gray-600);font-size:0.75rem;}
.btn-submit{width:100%;padding:1rem;background:var(--grad);color:var(--white);border:none;border-radius:10px;font-size:1rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:opacity 0.2s,transform 0.2s;}
.btn-submit:hover{opacity:0.9;transform:translateY(-1px);}
.msg.error{padding:0.85rem 1rem;border-radius:8px;font-size:0.875rem;margin-bottom:1rem;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#fca5a5;}
.msg.info{padding:0.85rem 1rem;border-radius:8px;font-size:0.875rem;margin-bottom:1rem;background:rgba(6,182,212,0.1);border:1px solid rgba(6,182,212,0.3);color:#7dd3fc;}
.success-card{background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.3);border-radius:16px;padding:2.5rem;text-align:center;max-width:540px;}
.success-card .check{font-size:4rem;margin-bottom:1rem;}
.success-card h2{font-family:'Syne',sans-serif;font-size:1.6rem;font-weight:700;margin-bottom:0.5rem;}
.success-card p{color:var(--gray-400);margin-bottom:1.5rem;}
.tracking-box{background:rgba(6,182,212,0.1);border:1px solid rgba(6,182,212,0.3);border-radius:10px;padding:1rem;margin:1rem 0;font-family:monospace;font-size:1.3rem;color:var(--cyan);font-weight:700;letter-spacing:0.05em;}
.btn{padding:0.7rem 1.4rem;border-radius:8px;font-size:0.875rem;font-weight:500;cursor:pointer;text-decoration:none;border:none;transition:all 0.2s;display:inline-flex;align-items:center;gap:0.4rem;font-family:'DM Sans',sans-serif;}
.btn-primary{background:var(--grad);color:var(--white);}
.btn-outline{background:transparent;border:1.5px solid rgba(255,255,255,0.2);color:var(--white);}
.hamburger{display:none;flex-direction:column;gap:4px;cursor:pointer;padding:0.4rem;}
.hamburger span{width:22px;height:2px;background:var(--white);border-radius:2px;}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%);}
  .sidebar.open{transform:translateX(0);}
  .main{margin-left:0;}
  .hamburger{display:flex;}
  .content{padding:1rem;}
  .form-row{grid-template-columns:1fr;}
}
</style>
<link rel="stylesheet" href="theme.css">
<script src="theme.js"></script>
</head>
<body>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">Safe<span>Speak</span></div>
    <div class="sidebar-user"><strong><?= htmlspecialchars($_SESSION['student_name'], ENT_QUOTES, 'UTF-8') ?></strong>ID: <?= htmlspecialchars($_SESSION['student_sid'], ENT_QUOTES, 'UTF-8') ?></div>
  </div>
  <nav class="sidebar-nav">
    <a class="nav-item" href="dashboard.php"><span class="icon">🏠</span> Dashboard</a>
    <a class="nav-item active" href="submit_report.php"><span class="icon">📝</span> Submit Report</a>
    <a class="nav-item" href="my_reports.php"><span class="icon">📄</span> My Reports</a>
    <a class="nav-item" href="profile.php"><span class="icon">👤</span> My Profile</a>
  </nav>
  <div class="sidebar-footer"><a class="logout-btn" href="../backend/logout.php">🚪 Logout</a></div>
</aside>

<div class="main">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:1rem;">
      <div class="hamburger" onclick="document.getElementById('sidebar').classList.toggle('open')"><span></span><span></span><span></span></div>
      <h2>Submit a Report</h2>
    </div>
    <a href="dashboard.php" style="font-size:0.85rem;color:var(--gray-400);text-decoration:none;">← Dashboard</a>
  </div>

  <div class="content">
    <?php if ($success): ?>
    <div class="success-card">
      <div class="check">✅</div>
      <h2>Report Submitted!</h2>
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
        <a href="submit_report.php" class="btn btn-outline">Submit Another</a>
        <a href="dashboard.php" class="btn btn-outline">Dashboard</a>
      </div>
    </div>
    <?php else: ?>

    <?php if ($error): ?><div class="msg error">⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($infoMessage): ?><div class="msg info">ℹ️ <?= $infoMessage ?></div><?php endif; ?>

    <?php if ($showVerification): ?>
      <div class="form-card">
        <h3>🔐 Verify to Continue</h3>
        <p class="muted" style="margin-bottom:1rem;">Enter the 6-digit code we sent to your email to finish submitting this report.</p>
        <form method="POST">
          <input type="hidden" name="action" value="verify_code">
          <div class="form-group">
            <label>Verification Code <span class="req">*</span></label>
            <input type="text" name="verification_code" maxlength="6" required>
          </div>
          <button class="btn-submit" type="submit">Verify & Submit</button>
        </form>
      </div>
    <?php else: ?>

    <form method="POST" enctype="multipart/form-data">

      <div class="form-card">
        <h3>🔒 Privacy Setting</h3>
        <label class="toggle-anon">
          <input type="checkbox" name="is_anonymous" id="anonCheck" checked>
          <div class="toggle-text">
            <strong>Submit Anonymously (Recommended)</strong>
            <span>Your name and student ID will NOT be linked to this report. Full anonymity guaranteed.</span>
          </div>
        </label>
        <div class="form-group" style="margin-top:1rem;">
          <label>Contact Email (Optional)</label>
          <input type="email" name="contact_email" placeholder="Enter email if you want confirmation or follow-up">
        </div>
        <div class="form-group">
          <label class="toggle-anon">
            <input type="checkbox" name="notify_opt_in" checked>
            <div class="toggle-text">
              <strong>Send me status updates by email</strong>
              <span>We will email updates if the report status changes.</span>
            </div>
          </label>
        </div>
      </div>

      <div class="form-card">
        <h3>📋 Report Information</h3>
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
      </div>

      <div class="form-card">
        <h3>📍 Incident Details (Optional but helpful)</h3>
        <div class="form-row">
          <div class="form-group">
            <label>Date of Incident</label>
            <input type="date" name="incident_date" max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($incident_date ?? '', ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="form-group">
            <label>Location</label>
            <input type="text" name="incident_location" placeholder="e.g. Main cafeteria, Block A" value="<?= htmlspecialchars($incident_location ?? '', ENT_QUOTES, 'UTF-8') ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Persons Involved (optional)</label>
          <input type="text" name="involved_persons" placeholder="Describe without full names if possible e.g. 'a senior student from CSE dept'" value="<?= htmlspecialchars($involved_persons ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
      </div>

      <!-- Accused Person Information -->
      <div class="form-card">
          <h3>👤 Accused Person Information (Optional)</h3>
          <div class="form-group">
              <label>Accused Student ID (if known)</label>
              <input type="text" name="accused_student_id" id="accusedStudentId"
                     placeholder="e.g., 3336"
                     onblur="lookupAccusedStudent()" value="<?= htmlspecialchars($accused_student_id ?? '', ENT_QUOTES, 'UTF-8') ?>">
              <div id="accusedStudentInfo" style="margin-top: 0.5rem; font-size: 0.8rem;"></div>
          </div>
          <div class="form-group">
              <label>Accused Name (if Student ID not known)</label>
              <input type="text" name="accused_name" placeholder="Name of the accused person" value="<?= htmlspecialchars($accused_name ?? '', ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="form-group">
              <label>Accused Department (if known)</label>
              <input type="text" name="accused_department" placeholder="e.g., Computer Science" value="<?= htmlspecialchars($accused_department ?? '', ENT_QUOTES, 'UTF-8') ?>">
          </div>
      </div>

      <div class="form-card">
        <h3>📎 Evidence Upload (Optional)</h3>
        <div class="form-group">
          <label>Google Drive / Cloud Link (Optional)</label>
          <input type="url" name="drive_link" placeholder="https://drive.google.com/... or any accessible cloud link">
        </div>
        <div class="file-drop" onclick="document.getElementById('evidenceInput').click()" id="fileDrop">
          <div class="icon">📁</div>
          <p>Click to upload or drag & drop files</p>
          <small>Supported: Images, PDF, Documents, Videos (max 5MB each)</small>
          <div id="fileList" style="margin-top:0.5rem;font-size:0.8rem;color:var(--cyan);"></div>
        </div>
        <input type="file" name="evidence[]" id="evidenceInput" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.mp4,.mov" style="display:none" onchange="showFiles(this)">
      </div>

      <button type="submit" class="btn-submit">🚀 Submit Report with AI Analysis</button>
      <p style="text-align:center;color:var(--gray-600);font-size:0.8rem;margin-top:0.75rem;">
        All reports are analyzed by our AI system for urgency and authenticity
      </p>
    </form>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<script>
function showFiles(input) {
  const names = Array.from(input.files).map(f => '📎 ' + f.name).join('<br>');
  document.getElementById('fileList').innerHTML = names;
}
const drop = document.getElementById('fileDrop');
if (drop) {
  drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('dragover'); });
  drop.addEventListener('dragleave', () => drop.classList.remove('dragover'));
  drop.addEventListener('drop', e => {
    e.preventDefault(); drop.classList.remove('dragover');
    const input = document.getElementById('evidenceInput');
    const dt = new DataTransfer();
    Array.from(e.dataTransfer.files).forEach(f => dt.items.add(f));
    input.files = dt.files;
    showFiles(input);
  });
}

function lookupAccusedStudent() {
  const studentId = document.getElementById('accusedStudentId').value.trim();
  const infoDiv = document.getElementById('accusedStudentInfo');

  if (studentId.length === 0) {
    infoDiv.innerHTML = '';
    return;
  }

  fetch(`../backend/ajax_get_student.php?id=${encodeURIComponent(studentId)}`)
      .then(response => response.json())
      .then(data => {
          if (data.found) {
              infoDiv.innerHTML = `<span style="color: #6ee7b7;">✓ Found: ${data.full_name} (${data.department}) - ${data.email}</span>`;
          } else {
              infoDiv.innerHTML = `<span style="color: #f87171;">✗ Student ID not found in directory</span>`;
          }
      })
      .catch(() => {
          infoDiv.innerHTML = `<span style="color: #fbbf24;">⚠ Could not verify ID</span>`;
      });
}
</script>
</body>
</html>