<?php
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/_ui.php';
requireAdminLogin();

$db = getDB();

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $studentId = (int) ($_POST['student_id'] ?? 0);

    if ($studentId <= 0) {
        $errorMessage = 'Invalid student selected.';
    } elseif ($action === 'toggle_active') {
        $stmt = $db->prepare("UPDATE students SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?");
        $stmt->execute([$studentId]);
        $successMessage = "Student {$studentId} activation status updated.";
    } elseif ($action === 'toggle_verified') {
        $stmt = $db->prepare("UPDATE students SET is_verified = IF(is_verified = 1, 0, 1) WHERE id = ?");
        $stmt->execute([$studentId]);
        $successMessage = "Student {$studentId} verification status updated.";
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$activeFilter = (string) ($_GET['active'] ?? '');
$verifiedFilter = (string) ($_GET['verified'] ?? '');

if (!in_array($activeFilter, ['1', '0'], true)) {
    $activeFilter = '';
}
if (!in_array($verifiedFilter, ['1', '0'], true)) {
    $verifiedFilter = '';
}

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(s.full_name LIKE ? OR s.email LIKE ? OR s.student_id LIKE ? OR s.department LIKE ? OR s.batch LIKE ?)";
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}
if ($activeFilter !== '') {
    $where[] = "s.is_active = ?";
    $params[] = (int) $activeFilter;
}
if ($verifiedFilter !== '') {
    $where[] = "s.is_verified = ?";
    $params[] = (int) $verifiedFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$perPage = 20;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countSql = "SELECT COUNT(*) AS total FROM students s {$whereSql}";
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
      s.*,
      COUNT(r.id) AS report_count
    FROM students s
    LEFT JOIN reports r ON r.student_id = s.id
    {$whereSql}
    GROUP BY s.id
    ORDER BY s.created_at DESC
    LIMIT :limit OFFSET :offset
";
$listStmt = $db->prepare($listSql);
foreach ($params as $index => $value) {
    $listStmt->bindValue($index + 1, $value);
}
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$students = $listStmt->fetchAll();

$summary = $db->query("
    SELECT
      COUNT(*) AS total_students,
      SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_students,
      SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive_students,
      SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) AS verified_students
    FROM students
")->fetch();

$preserved = $_GET;
unset($preserved['page']);
$queryBase = http_build_query($preserved);
$queryBase = $queryBase !== '' ? $queryBase . '&' : '';

adminRenderHeader('Students', 'students');
adminRenderSubTitle('Manage student accounts and review registration activity.');
adminRenderFlash($successMessage, $errorMessage);
?>

<div class="grid space-bottom">
  <div class="stat-card">
    <div class="stat-label">Total Students</div>
    <div class="stat-value"><?= adminH((string) ($summary['total_students'] ?? 0)) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Active</div>
    <div class="stat-value" style="color:#86efac;"><?= adminH((string) ($summary['active_students'] ?? 0)) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Inactive</div>
    <div class="stat-value" style="color:#fecaca;"><?= adminH((string) ($summary['inactive_students'] ?? 0)) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Verified</div>
    <div class="stat-value" style="color:#93c5fd;"><?= adminH((string) ($summary['verified_students'] ?? 0)) ?></div>
  </div>
</div>

<div class="panel space-bottom">
  <form method="GET">
    <div class="form-row">
      <input type="text" name="q" placeholder="Search name, email, student ID, department..." value="<?= adminH($search) ?>">
      <select name="active">
        <option value="">All Activity States</option>
        <option value="1" <?= $activeFilter === '1' ? 'selected' : '' ?>>Active</option>
        <option value="0" <?= $activeFilter === '0' ? 'selected' : '' ?>>Inactive</option>
      </select>
      <select name="verified">
        <option value="">All Verification States</option>
        <option value="1" <?= $verifiedFilter === '1' ? 'selected' : '' ?>>Verified</option>
        <option value="0" <?= $verifiedFilter === '0' ? 'selected' : '' ?>>Not Verified</option>
      </select>
      <button class="btn" type="submit"><i class="fas fa-search"></i> Apply</button>
    </div>
  </form>
</div>

<div class="panel">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Student</th>
          <th>Academic</th>
          <th>Status</th>
          <th>Reports</th>
          <th style="min-width: 220px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$students): ?>
        <tr><td colspan="5" class="muted" style="padding: 1rem 0.5rem;">No students found.</td></tr>
        <?php endif; ?>
        <?php foreach ($students as $student): ?>
        <tr>
          <td>
            <strong><?= adminH($student['full_name']) ?></strong><br>
            <span class="muted"><?= adminH($student['student_id']) ?> - <?= adminH($student['email']) ?></span>
          </td>
          <td>
            <?= adminH($student['department'] ?: 'N/A') ?><br>
            <span class="muted">Batch: <?= adminH($student['batch'] ?: 'N/A') ?></span>
          </td>
          <td>
            <span class="chip <?= (int) $student['is_active'] === 1 ? 'resolved' : 'rejected' ?>">
              <?= (int) $student['is_active'] === 1 ? 'Active' : 'Inactive' ?>
            </span><br>
            <span class="chip <?= (int) $student['is_verified'] === 1 ? 'under_review' : 'pending' ?>">
              <?= (int) $student['is_verified'] === 1 ? 'Verified' : 'Unverified' ?>
            </span>
          </td>
          <td>
            <strong><?= adminH((string) $student['report_count']) ?></strong><br>
            <span class="muted">Last login: <?= adminH($student['last_login'] ? date('Y-m-d H:i', strtotime($student['last_login'])) : 'Never') ?></span>
          </td>
          <td>
            <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
              <form method="POST">
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="student_id" value="<?= adminH($student['id']) ?>">
                <button class="btn secondary" type="submit"><?= (int) $student['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
              </form>
              <form method="POST">
                <input type="hidden" name="action" value="toggle_verified">
                <input type="hidden" name="student_id" value="<?= adminH($student['id']) ?>">
                <button class="btn secondary" type="submit"><?= (int) $student['is_verified'] === 1 ? 'Unverify' : 'Verify' ?></button>
              </form>
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
