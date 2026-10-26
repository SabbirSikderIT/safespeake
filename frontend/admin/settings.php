<?php
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/_ui.php';
requireAdminLogin();

$db = getDB();
$adminId = (int) ($_SESSION['admin_id'] ?? 0);

$successMessage = '';
$errorMessage = '';
$spamSettings = getSpamSettings();

$fetchAdmin = function () use ($db, $adminId) {
    $stmt = $db->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$adminId]);
    return $stmt->fetch();
};

$admin = $fetchAdmin();
if (!$admin) {
    redirect(BACKEND_URL . '/admin/logout.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $institution = trim((string) ($_POST['institution'] ?? ''));

        if ($fullName === '' || $email === '') {
            $errorMessage = 'Name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Please enter a valid email address.';
        } else {
            $check = $db->prepare("SELECT id FROM admins WHERE email = ? AND id <> ?");
            $check->execute([$email, $adminId]);
            if ($check->fetch()) {
                $errorMessage = 'This email is already used by another admin.';
            } else {
                $update = $db->prepare("UPDATE admins SET full_name = ?, email = ?, institution = ? WHERE id = ?");
                $update->execute([$fullName, $email, $institution !== '' ? $institution : null, $adminId]);
                $_SESSION['admin_name'] = $fullName;
                $successMessage = 'Profile settings saved successfully.';
                $admin = $fetchAdmin();
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $errorMessage = 'Please complete all password fields.';
        } elseif (strlen($newPassword) < 6) {
            $errorMessage = 'New password must be at least 6 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = 'New password and confirmation do not match.';
        } elseif (!verifyAndUpgradePassword($db, 'admins', $adminId, $currentPassword, $admin['password'])) {
            $errorMessage = 'Current password is incorrect.';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = $db->prepare("UPDATE admins SET password = ? WHERE id = ?");
            $update->execute([$newHash, $adminId]);
            $successMessage = 'Password changed successfully.';
            $admin = $fetchAdmin();
        }
        } elseif ($action === 'update_spam_settings') {
          $blacklist = trim((string) ($_POST['blacklist_keywords'] ?? ''));
          $disposable = trim((string) ($_POST['disposable_domains'] ?? ''));
          $rateLimitMax = (int) ($_POST['rate_limit_max'] ?? 5);
          $hardRateLimit = (int) ($_POST['hard_rate_limit'] ?? 7);
          $duplicateWindow = (int) ($_POST['duplicate_window_days'] ?? 7);
          $duplicateThreshold = (int) ($_POST['duplicate_threshold'] ?? 2);
          $verificationThreshold = (float) ($_POST['verification_threshold'] ?? 0.6);
          $spamScoreThreshold = (float) ($_POST['spam_score_threshold'] ?? 0.7);
          $linkShortWordCount = (int) ($_POST['link_short_wordcount'] ?? 25);

          if ($rateLimitMax < 1 || $hardRateLimit < $rateLimitMax) {
            $errorMessage = 'Rate limit values are invalid.';
          } elseif ($duplicateWindow < 1 || $duplicateThreshold < 1) {
            $errorMessage = 'Duplicate detection values are invalid.';
          } elseif ($verificationThreshold <= 0 || $verificationThreshold > 1 || $spamScoreThreshold <= 0 || $spamScoreThreshold > 1) {
            $errorMessage = 'Thresholds must be between 0 and 1.';
          } else {
            $saved = saveSpamSettings([
              'blacklist_keywords' => $blacklist,
              'disposable_domains' => $disposable,
              'rate_limit_max' => $rateLimitMax,
              'hard_rate_limit' => $hardRateLimit,
              'duplicate_window_days' => $duplicateWindow,
              'duplicate_threshold' => $duplicateThreshold,
              'verification_threshold' => $verificationThreshold,
              'spam_score_threshold' => $spamScoreThreshold,
              'link_short_wordcount' => $linkShortWordCount,
            ], $adminId > 0 ? $adminId : null);

            if ($saved) {
              $successMessage = 'Spam rules updated successfully.';
              $spamSettings = getSpamSettings();
            } else {
              $errorMessage = 'Unable to save spam rules.';
            }
          }
    }
}

$settingsSnapshot = [
    'site_name' => SITE_NAME,
    'site_url' => SITE_URL,
    'db_host' => DB_HOST,
    'db_port' => DB_PORT,
    'db_name' => DB_NAME,
];

$blacklistValue = adminH(implode(', ', $spamSettings['blacklist_keywords'] ?? []));
$disposableValue = adminH(implode(', ', $spamSettings['disposable_domains'] ?? []));

$health = $db->query("
    SELECT
      (SELECT COUNT(*) FROM reports) AS total_reports,
      (SELECT COUNT(*) FROM students WHERE is_active = 1) AS active_students,
      (SELECT COUNT(*) FROM admins WHERE is_active = 1) AS active_admins
")->fetch();

adminRenderHeader('Settings', 'settings');
adminRenderSubTitle('Update your admin profile and review live system settings.');
adminRenderFlash($successMessage, $errorMessage);
?>

<div class="grid space-bottom">
  <div class="stat-card">
    <div class="stat-label">Total Reports</div>
    <div class="stat-value"><?= adminH((string) ($health['total_reports'] ?? 0)) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Active Students</div>
    <div class="stat-value" style="color:#86efac;"><?= adminH((string) ($health['active_students'] ?? 0)) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Active Admins</div>
    <div class="stat-value" style="color:#93c5fd;"><?= adminH((string) ($health['active_admins'] ?? 0)) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Current Role</div>
    <div class="stat-value" style="font-size:1.2rem;"><?= adminH(str_replace('_', ' ', ucfirst((string) $admin['role']))) ?></div>
  </div>
</div>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));">
  <div class="panel">
    <h3 style="margin-bottom:0.8rem;">Profile Settings</h3>
    <form method="POST">
      <input type="hidden" name="action" value="update_profile">
      <div class="form-row">
        <input type="text" name="full_name" value="<?= adminH($admin['full_name']) ?>" placeholder="Full name" required>
        <input type="email" name="email" value="<?= adminH($admin['email']) ?>" placeholder="Email" required>
      </div>
      <div class="form-row">
        <input type="text" value="<?= adminH($admin['username']) ?>" disabled>
        <input type="text" name="institution" value="<?= adminH((string) ($admin['institution'] ?? '')) ?>" placeholder="Institution">
      </div>
      <button class="btn" type="submit"><i class="fas fa-save"></i> Save Profile</button>
    </form>
  </div>

  <div class="panel">
    <h3 style="margin-bottom:0.8rem;">Change Password</h3>
    <form method="POST">
      <input type="hidden" name="action" value="change_password">
      <div class="form-row">
        <input type="password" name="current_password" placeholder="Current password" required>
      </div>
      <div class="form-row">
        <input type="password" name="new_password" placeholder="New password" required>
        <input type="password" name="confirm_password" placeholder="Confirm new password" required>
      </div>
      <button class="btn" type="submit"><i class="fas fa-key"></i> Update Password</button>
    </form>
  </div>
</div>

<div class="panel space-top">
  <h3 style="margin-bottom:0.8rem;">System Snapshot</h3>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Key</th>
          <th>Value</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($settingsSnapshot as $key => $value): ?>
        <tr>
          <td><?= adminH(ucwords(str_replace('_', ' ', $key))) ?></td>
          <td><?= adminH($value) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel space-top">
  <h3 style="margin-bottom:0.8rem;">Spam Detection Rules</h3>
  <form method="POST">
    <input type="hidden" name="action" value="update_spam_settings">
    <div class="form-row">
      <div>
        <label class="muted" style="font-size:0.8rem;">Blacklist Keywords (comma-separated)</label>
        <textarea name="blacklist_keywords" placeholder="crypto, free money, buy now" style="min-height:90px;"><?= $blacklistValue ?></textarea>
      </div>
      <div>
        <label class="muted" style="font-size:0.8rem;">Disposable Email Domains (comma-separated)</label>
        <textarea name="disposable_domains" placeholder="mailinator.com, 10minutemail.com" style="min-height:90px;"><?= $disposableValue ?></textarea>
      </div>
    </div>
    <div class="form-row">
      <div>
        <label class="muted" style="font-size:0.8rem;">Rate Limit Max (per hour)</label>
        <input type="number" name="rate_limit_max" min="1" value="<?= adminH((string) ($spamSettings['rate_limit_max'] ?? 5)) ?>">
      </div>
      <div>
        <label class="muted" style="font-size:0.8rem;">Hard Rate Limit (forces verification)</label>
        <input type="number" name="hard_rate_limit" min="1" value="<?= adminH((string) ($spamSettings['hard_rate_limit'] ?? 7)) ?>">
      </div>
      <div>
        <label class="muted" style="font-size:0.8rem;">Duplicate Window (days)</label>
        <input type="number" name="duplicate_window_days" min="1" value="<?= adminH((string) ($spamSettings['duplicate_window_days'] ?? 7)) ?>">
      </div>
    </div>
    <div class="form-row">
      <div>
        <label class="muted" style="font-size:0.8rem;">Duplicate Threshold</label>
        <input type="number" name="duplicate_threshold" min="1" value="<?= adminH((string) ($spamSettings['duplicate_threshold'] ?? 2)) ?>">
      </div>
      <div>
        <label class="muted" style="font-size:0.8rem;">Verification Threshold (0-1)</label>
        <input type="number" step="0.01" min="0.1" max="1" name="verification_threshold" value="<?= adminH((string) ($spamSettings['verification_threshold'] ?? 0.6)) ?>">
      </div>
      <div>
        <label class="muted" style="font-size:0.8rem;">Spam Score Threshold (0-1)</label>
        <input type="number" step="0.01" min="0.1" max="1" name="spam_score_threshold" value="<?= adminH((string) ($spamSettings['spam_score_threshold'] ?? 0.7)) ?>">
      </div>
    </div>
    <div class="form-row">
      <div>
        <label class="muted" style="font-size:0.8rem;">Link in Short Report Word Count</label>
        <input type="number" name="link_short_wordcount" min="5" value="<?= adminH((string) ($spamSettings['link_short_wordcount'] ?? 25)) ?>">
      </div>
    </div>
    <button class="btn" type="submit"><i class="fas fa-shield-alt"></i> Save Spam Rules</button>
  </form>
</div>

<?php adminRenderFooter(); ?>
