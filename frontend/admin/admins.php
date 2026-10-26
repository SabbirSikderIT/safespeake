<?php
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/_ui.php';
requireAdminLogin();

$db = getDB();
$currentAdminId = (int) ($_SESSION['admin_id'] ?? 0);
$currentRole = (string) ($_SESSION['admin_role'] ?? 'proctor');
$canManageAdmins = $currentRole === 'super_admin';

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add_admin') {
        if (!$canManageAdmins) {
            $errorMessage = 'Only super admins can create new admin accounts.';
        } else {
            $username = trim((string) ($_POST['username'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $institution = trim((string) ($_POST['institution'] ?? ''));
            $role = (string) ($_POST['role'] ?? 'proctor');
            $password = (string) ($_POST['password'] ?? '');

            if ($username === '' || $email === '' || $fullName === '' || $password === '') {
                $errorMessage = 'Please fill all required admin fields.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errorMessage = 'Please enter a valid admin email.';
            } elseif (!in_array($role, ['super_admin', 'proctor', 'moderator'], true)) {
                $errorMessage = 'Invalid role selected.';
            } elseif (strlen($password) < 6) {
                $errorMessage = 'Password must be at least 6 characters.';
            } else {
                $check = $db->prepare("SELECT id FROM admins WHERE username = ? OR email = ?");
                $check->execute([$username, $email]);
                if ($check->fetch()) {
                    $errorMessage = 'Admin with this username or email already exists.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO admins (username, email, password, full_name, institution, role, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([$username, $email, $hash, $fullName, $institution !== '' ? $institution : null, $role]);
                    $successMessage = 'New admin created successfully.';
                }
            }
        }
    } elseif ($action === 'toggle_active') {
        if (!$canManageAdmins) {
            $errorMessage = 'Only super admins can change admin activation state.';
        } else {
            $targetAdminId = (int) ($_POST['target_admin_id'] ?? 0);
            if ($targetAdminId <= 0) {
                $errorMessage = 'Invalid admin selected.';
            } elseif ($targetAdminId === $currentAdminId) {
                $errorMessage = 'You cannot deactivate your own account while logged in.';
            } else {
                $stmt = $db->prepare("UPDATE admins SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?");
                $stmt->execute([$targetAdminId]);
                $successMessage = "Admin {$targetAdminId} activation status updated.";
            }
        }
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$where = '';
$params = [];
if ($search !== '') {
    $where = "WHERE a.username LIKE ? OR a.email LIKE ? OR a.full_name LIKE ? OR a.role LIKE ?";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}

$adminsSql = "
    SELECT
      a.*,
      COUNT(r.id) AS assigned_reports
    FROM admins a
    LEFT JOIN reports r ON r.assigned_to = a.id
    {$where}
    GROUP BY a.id
    ORDER BY FIELD(a.role, 'super_admin', 'proctor', 'moderator'), a.created_at ASC
";
$adminsStmt = $db->prepare($adminsSql);
$adminsStmt->execute($params);
$admins = $adminsStmt->fetchAll();

$summary = $db->query("
    SELECT
      COUNT(*) AS total_admins,
      SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_admins,
      SUM(CASE WHEN role = 'super_admin' THEN 1 ELSE 0 END) AS super_admins,
      SUM(CASE WHEN role = 'proctor' THEN 1 ELSE 0 END) AS proctors
    FROM admins
")->fetch();

adminRenderHeader('Admins', 'admins');
adminRenderSubTitle('Manage administrator accounts and permissions.');
adminRenderFlash($successMessage, $errorMessage);
?>

<?php if (!$canManageAdmins): ?>
<div class="msg error">You are logged in as <strong><?= adminH($currentRole) ?></strong>. Only super admins can create/disable admins.</div>
<?php endif; ?>

<div class="grid space-bottom">
  <div class="stat-card">
    <div class="stat-label">Total Admins</div>
    <div class="stat-value"><?= adminH((string) ($summary['total_admins'] ?? 0)) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Active</div>
    <div class="stat-value" style="color:#86efac;"><?= adminH((string) ($summary['active_admins'] ?? 0)) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Super Admins</div>
    <div class="stat-value" style="color:#93c5fd;"><?= adminH((string) ($summary['super_admins'] ?? 0)) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Proctors</div>
    <div class="stat-value" style="color:#fcd34d;"><?= adminH((string) ($summary['proctors'] ?? 0)) ?></div>
  </div>
</div>

<?php if ($canManageAdmins): ?>
<div class="panel space-bottom">
  <h3 style="margin-bottom:0.8rem;">Create Admin</h3>
  <form method="POST">
    <input type="hidden" name="action" value="add_admin">
    <div class="form-row">
      <input type="text" name="username" placeholder="Username" required>
      <input type="email" name="email" placeholder="Email" required>
      <input type="text" name="full_name" placeholder="Full name" required>
      <input type="text" name="institution" placeholder="Institution">
      <select name="role" required>
        <option value="proctor">Proctor</option>
        <option value="moderator">Moderator</option>
        <option value="super_admin">Super Admin</option>
      </select>
      <input type="password" name="password" placeholder="Password (min 6 chars)" required>
    </div>
    <button class="btn" type="submit"><i class="fas fa-user-plus"></i> Add Admin</button>
  </form>
</div>
<?php endif; ?>

<div class="panel space-bottom">
  <form method="GET">
    <div class="form-row">
      <input type="text" name="q" placeholder="Search by username, email, full name, role..." value="<?= adminH($search) ?>">
      <button class="btn" type="submit"><i class="fas fa-search"></i> Search</button>
      <a class="btn secondary" href="admins.php"><i class="fas fa-rotate-left"></i> Reset</a>
    </div>
  </form>
</div>

<div class="panel">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Admin</th>
          <th>Role</th>
          <th>Status</th>
          <th>Assigned Reports</th>
          <th>Last Login</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$admins): ?>
        <tr><td colspan="6" class="muted" style="padding: 1rem 0.5rem;">No admins found.</td></tr>
        <?php endif; ?>
        <?php foreach ($admins as $admin): ?>
        <tr>
          <td>
            <strong><?= adminH($admin['full_name']) ?></strong><br>
            <span class="muted"><?= adminH($admin['username']) ?> - <?= adminH($admin['email']) ?></span>
          </td>
          <td>
            <span class="chip <?= $admin['role'] === 'super_admin' ? 'under_review' : ($admin['role'] === 'proctor' ? 'resolved' : 'pending') ?>">
              <?= adminH(str_replace('_', ' ', ucfirst($admin['role']))) ?>
            </span>
          </td>
          <td>
            <span class="chip <?= (int) $admin['is_active'] === 1 ? 'resolved' : 'rejected' ?>">
              <?= (int) $admin['is_active'] === 1 ? 'Active' : 'Inactive' ?>
            </span>
          </td>
          <td><?= adminH((string) $admin['assigned_reports']) ?></td>
          <td class="muted"><?= adminH($admin['last_login'] ? date('Y-m-d H:i', strtotime($admin['last_login'])) : 'Never') ?></td>
          <td>
            <?php if ($canManageAdmins): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="target_admin_id" value="<?= adminH($admin['id']) ?>">
                <button class="btn secondary" type="submit" <?= (int) $admin['id'] === $currentAdminId ? 'disabled' : '' ?>>
                  <?= (int) $admin['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                </button>
              </form>
            <?php else: ?>
              <span class="muted">Read only</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php adminRenderFooter(); ?>
