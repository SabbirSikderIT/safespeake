<?php
require_once __DIR__ . '/../backend/includes/config.php';
ensureReportingTables();

$error = '';
$success = '';
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$report = null;

if ($token !== '') {
  $validation = validateFollowupToken($token);
  if ($validation['report_id']) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, tracking_id, title, status, evidence_files FROM reports WHERE id = ?");
    $stmt->execute([$validation['report_id']]);
    $report = $stmt->fetch();
    if (!$report) {
      $error = 'Invalid follow-up token.';
    }
  } else {
    $error = $validation['error'] ?? 'Invalid follow-up token.';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_followup') {
    $token = trim((string) ($_POST['token'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));
    $drive_link = sanitize($_POST['drive_link'] ?? '');

    if ($token === '') {
      $error = 'Follow-up token is required.';
    } else {
      $validation = validateFollowupToken($token);
      if (!$validation['report_id']) {
        $error = $validation['error'] ?? 'Invalid follow-up token.';
      } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, tracking_id, title, status, evidence_files FROM reports WHERE id = ?");
        $stmt->execute([$validation['report_id']]);
        $report = $stmt->fetch();

        if (!$report) {
          $error = 'Report not found for this token.';
        } else {
          $newEvidence = [];

                if (!empty($_FILES['evidence']['name'][0])) {
                    foreach ($_FILES['evidence']['tmp_name'] as $k => $tmp) {
                        if ($_FILES['evidence']['error'][$k] === UPLOAD_ERR_OK) {
                            $ext = strtolower(pathinfo($_FILES['evidence']['name'][$k], PATHINFO_EXTENSION));
                            $allowed = ['jpg','jpeg','png','gif','pdf','doc','docx','mp4','mov'];
                            if (in_array($ext, $allowed, true) && $_FILES['evidence']['size'][$k] <= MAX_FILE_SIZE) {
                                $fname = uniqid('ev_') . '.' . $ext;
                                if (move_uploaded_file($tmp, UPLOAD_PATH . $fname)) {
                                    $newEvidence[] = $fname;
                                }
                            }
                        }
                    }
                }

                if (!empty($drive_link) && filter_var($drive_link, FILTER_VALIDATE_URL)) {
                    $newEvidence[] = 'drive_link:' . $drive_link;
                }

                if ($message === '' && empty($newEvidence)) {
                    $error = 'Please add a message or upload evidence.';
                } else {
                    $existing = [];
                    if (!empty($report['evidence_files'])) {
                        $decoded = json_decode((string) $report['evidence_files'], true);
                        if (is_array($decoded)) {
                            $existing = $decoded;
                        }
                    }
                    $merged = array_merge($existing, $newEvidence);
                    $evidenceJson = $merged ? json_encode($merged) : null;

                    $update = $db->prepare("UPDATE reports SET evidence_files = ?, updated_at = NOW() WHERE id = ?");
                    $update->execute([$evidenceJson, $validation['report_id']]);

                    if ($message !== '') {
                        $insert = $db->prepare("INSERT INTO report_messages (report_id, sender_type, message) VALUES (?, 'reporter', ?)");
                      $insert->execute([$validation['report_id'], $message]);
                    }

                    $success = 'Follow-up submitted successfully.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Anonymous Follow-up — SafeSpeak</title>
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
.page { position: relative; z-index: 1; flex: 1; display: flex; flex-direction: column; }
header { padding: 1.25rem 2rem; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.07); }
.logo { font-family: 'Syne', sans-serif; font-size: 1.3rem; font-weight: 800; text-decoration: none; color: var(--white); }
.logo span { color: var(--cyan); }
.back-link { font-size: 0.85rem; color: var(--gray-400); text-decoration: none; }
.back-link:hover { color: var(--white); }
main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem; }
.card { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 2.5rem; width: 100%; max-width: 700px; backdrop-filter: blur(10px); }
.card-header { text-align: center; margin-bottom: 2rem; }
.card-header h1 { font-family: 'Syne', sans-serif; font-size: 1.6rem; font-weight: 700; margin-bottom: 0.4rem; }
.card-header p { color: var(--gray-400); font-size: 0.875rem; }
.form-group { margin-bottom: 1.1rem; }
label { display: block; font-size: 0.8rem; font-weight: 500; color: var(--gray-400); margin-bottom: 0.4rem; }
input, textarea { width: 100%; background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.12); border-radius: 8px; padding: 0.75rem 1rem; color: var(--white); font-size: 0.9rem; font-family: 'DM Sans', sans-serif; outline: none; }
textarea { resize: vertical; min-height: 120px; }
.btn { padding: 0.85rem 1.5rem; background: var(--grad); color: var(--white); border: none; border-radius: 10px; font-size: 0.95rem; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
.msg { padding: 0.85rem 1rem; border-radius: 8px; font-size: 0.875rem; margin-bottom: 1rem; }
.msg.error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5; }
.msg.success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #6ee7b7; }
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
        <h1>Anonymous Follow-up</h1>
        <p>Use your follow-up token to add more evidence or send a message.</p>
      </div>

      <?php if ($error): ?>
        <div class="msg error">⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="msg success">✅ <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <?php if (!$report): ?>
        <form method="GET">
          <div class="form-group">
            <label>Follow-up Token</label>
            <input type="text" name="token" placeholder="Paste your follow-up token" required>
          </div>
          <button class="btn" type="submit">Continue</button>
        </form>
      <?php else: ?>
        <div class="form-group">
          <label>Report</label>
          <input type="text" value="<?= htmlspecialchars($report['tracking_id'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($report['title'], ENT_QUOTES, 'UTF-8') ?>" disabled>
        </div>

        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="submit_followup">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

          <div class="form-group">
            <label>Message (optional)</label>
            <textarea name="message" placeholder="Add a short update..."></textarea>
          </div>

          <div class="form-group">
            <label>Evidence Upload (optional)</label>
            <input type="file" name="evidence[]" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.mp4,.mov">
          </div>

          <div class="form-group">
            <label>Drive / Cloud Link (optional)</label>
            <input type="url" name="drive_link" placeholder="https://drive.google.com/...">
          </div>

          <button class="btn" type="submit">Submit Follow-up</button>
        </form>
      <?php endif; ?>
    </div>
  </main>
</div>
</body>
</html>
