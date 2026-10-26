<?php
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/_ui.php';
requireAdminLogin();

$db = getDB();
$successMessage = '';
$errorMessage = '';
$importSummary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'import_csv') {
    $source = (string) ($_POST['csv_source'] ?? 'upload');
    $filePath = '';

    if ($source === 'server') {
        $filePath = __DIR__ . '/../../database/studentdata.csv';
        if (!file_exists($filePath)) {
            $errorMessage = 'studentdata.csv not found on the server.';
        }
    } else {
        if (!isset($_FILES['student_csv']) || ($_FILES['student_csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errorMessage = 'Please upload a valid CSV file.';
        } else {
            $filePath = $_FILES['student_csv']['tmp_name'];
        }
    }

    if ($errorMessage === '' && $filePath !== '') {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $errorMessage = 'Could not read the CSV file.';
        } else {
            $firstRow = fgetcsv($handle);
            if (!$firstRow) {
                $errorMessage = 'CSV file appears to be empty.';
            } else {
                $headers = array_map('strtolower', $firstRow);
                $map = [
                    'student_id' => array_search('student_id', $headers, true),
                    'full_name' => array_search('full_name', $headers, true),
                    'age' => array_search('age', $headers, true),
                    'email' => array_search('email', $headers, true),
                    'department' => array_search('department', $headers, true),
                    'gpa' => array_search('gpa', $headers, true),
                    'graduation_year' => array_search('graduation_year', $headers, true),
                ];

                $hasHeader = ($map['student_id'] !== false || $map['full_name'] !== false || $map['email'] !== false);
                if (!$hasHeader) {
                    $map = [
                        'student_id' => 0,
                        'full_name' => 1,
                        'age' => 2,
                        'email' => 3,
                        'department' => 4,
                        'gpa' => 5,
                        'graduation_year' => 6,
                    ];
                    rewind($handle);
                }

                $stmt = $db->prepare(
                    "INSERT INTO student_directory (student_id, full_name, age, email, department, gpa, graduation_year) "
                    . "VALUES (?, ?, ?, ?, ?, ?, ?) "
                    . "ON DUPLICATE KEY UPDATE "
                    . "full_name = VALUES(full_name), age = VALUES(age), email = VALUES(email), "
                    . "department = VALUES(department), gpa = VALUES(gpa), graduation_year = VALUES(graduation_year)"
                );

                $processed = 0;
                $imported = 0;
                $skipped = 0;
                $rowIndex = 1;
                $errors = [];

                while (($data = fgetcsv($handle)) !== false) {
                    $rowIndex++;
                    $processed++;

                    $studentId = trim((string) ($data[$map['student_id']] ?? ''));
                    $fullName = trim((string) ($data[$map['full_name']] ?? ''));
                    $email = trim((string) ($data[$map['email']] ?? ''));

                    if ($studentId === '' || $fullName === '' || $email === '') {
                        $skipped++;
                        if (count($errors) < 5) {
                            $errors[] = "Row {$rowIndex}: Missing required fields.";
                        }
                        continue;
                    }

                    $age = isset($data[$map['age']]) && $data[$map['age']] !== '' ? (int) $data[$map['age']] : null;
                    $department = trim((string) ($data[$map['department']] ?? ''));
                    $gpa = isset($data[$map['gpa']]) && $data[$map['gpa']] !== '' ? (float) $data[$map['gpa']] : null;
                    $graduationYear = isset($data[$map['graduation_year']]) && $data[$map['graduation_year']] !== '' ? (int) $data[$map['graduation_year']] : null;

                    try {
                        $stmt->execute([
                            $studentId,
                            $fullName,
                            $age,
                            $email,
                            $department !== '' ? $department : null,
                            $gpa,
                            $graduationYear,
                        ]);
                        $imported++;
                    } catch (PDOException $e) {
                        $skipped++;
                        if (count($errors) < 5) {
                            $errors[] = "Row {$rowIndex}: " . $e->getMessage();
                        }
                    }
                }
                fclose($handle);

                $importSummary = [
                    'processed' => $processed,
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'errors' => $errors,
                ];

                if ($processed === 0) {
                    $errorMessage = 'No rows were processed from the CSV file.';
                } else {
                    $successMessage = "Student directory synced. Imported/updated {$imported} records.";
                }
            }
        }
    }
}
$search = trim($_GET['q'] ?? '');
$department = trim($_GET['dept'] ?? '');

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(student_id LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
}
if ($department !== '') {
    $where[] = "department = ?";
    $params[] = $department;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$students = $db->prepare("SELECT * FROM student_directory $whereSql ORDER BY student_id LIMIT 100");
$students->execute($params);
$studentsList = $students->fetchAll();

$departments = $db->query("SELECT DISTINCT department FROM student_directory WHERE department IS NOT NULL ORDER BY department")->fetchAll();
$stats = getStudentDirectoryStats();

adminRenderHeader('Student Directory', 'student_directory');
adminRenderSubTitle('Live lookup powered by Student Directory and active student accounts.');
adminRenderFlash($successMessage, $errorMessage);
?>

<div class="panel space-bottom">
    <h3 style="margin-bottom:0.8rem;">Sync Student Directory (CSV)</h3>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="import_csv">
        <div class="form-row">
            <label class="muted" style="display:flex;align-items:center;gap:0.4rem;">
                <input type="radio" name="csv_source" value="upload" checked> Upload CSV
            </label>
            <input type="file" name="student_csv" accept=".csv">
            <label class="muted" style="display:flex;align-items:center;gap:0.4rem;">
                <input type="radio" name="csv_source" value="server"> Use server studentdata.csv
            </label>
            <button class="btn" type="submit"><i class="fas fa-file-upload"></i> Sync Now</button>
        </div>
        <div class="muted" style="margin-top:0.5rem;">
            Required columns: student_id, full_name, email. Optional: age, department, gpa, graduation_year.
        </div>
    </form>

    <?php if ($importSummary): ?>
    <div class="space-top">
        <div class="muted">Rows processed: <?= adminH((string) $importSummary['processed']) ?> | Imported/Updated: <?= adminH((string) $importSummary['imported']) ?> | Skipped: <?= adminH((string) $importSummary['skipped']) ?></div>
        <?php if (!empty($importSummary['errors'])): ?>
            <div class="msg error" style="margin-top:0.6rem;">
                <?= adminH(implode(' | ', $importSummary['errors'])) ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="grid space-bottom">
    <div class="stat-card"><div class="stat-label">Total Students</div><div class="stat-value"><?= $stats['total'] ?? 0 ?></div></div>
    <div class="stat-card"><div class="stat-label">Departments</div><div class="stat-value"><?= $stats['departments'] ?? 0 ?></div></div>
    <div class="stat-card"><div class="stat-label">Average GPA</div><div class="stat-value"><?= number_format($stats['avg_gpa'] ?? 0, 2) ?></div></div>
</div>

<div class="panel space-bottom">
    <form method="GET">
        <div class="form-row">
            <input type="text" name="q" placeholder="Search by ID, Name, or Email" value="<?= adminH($search) ?>">
            <select name="dept">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                <option value="<?= adminH($dept['department']) ?>" <?= $department === $dept['department'] ? 'selected' : '' ?>><?= adminH($dept['department']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn" type="submit"><i class="fas fa-search"></i> Search</button>
            <a class="btn secondary" href="student_directory.php"><i class="fas fa-rotate-left"></i> Reset</a>
        </div>
    </form>
</div>

<div class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Student ID</th><th>Full Name</th><th>Email</th><th>Department</th><th>GPA</th><th>Grad Year</th></tr>
            </thead>
            <tbody>
                <?php foreach ($studentsList as $s): ?>
                <tr>
                    <td><strong><?= adminH($s['student_id']) ?></strong></td>
                    <td><?= adminH($s['full_name']) ?></td>
                    <td><?= adminH($s['email']) ?></td>
                    <td><?= adminH($s['department'] ?? 'N/A') ?></td>
                    <td><?= adminH($s['gpa'] ?? 'N/A') ?></td>
                    <td><?= adminH($s['graduation_year'] ?? 'N/A') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php adminRenderFooter(); ?>