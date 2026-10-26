<?php
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/_ui.php';
requireAdminLogin();

$db = getDB();
$db->exec(
    "CREATE TABLE IF NOT EXISTS university_terms (\n"
    . "id INT AUTO_INCREMENT PRIMARY KEY,\n"
    . "institution_name VARCHAR(255) NOT NULL,\n"
    . "title VARCHAR(255) NOT NULL,\n"
    . "description TEXT NULL,\n"
    . "file_name VARCHAR(255) NOT NULL,\n"
    . "uploaded_by INT NULL,\n"
    . "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n"
    . "FOREIGN KEY (uploaded_by) REFERENCES admins(id) ON DELETE SET NULL\n"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add_term') {
        $institution = trim((string) ($_POST['institution_name'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($institution === '' || $title === '') {
            $errorMessage = 'Institution name and title are required.';
        } elseif (!isset($_FILES['terms_pdf']) || ($_FILES['terms_pdf']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errorMessage = 'Please upload a PDF file.';
        } else {
            $file = $_FILES['terms_pdf'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $maxSize = 10 * 1024 * 1024;

            if ($ext !== 'pdf') {
                $errorMessage = 'Only PDF files are allowed.';
            } elseif ($file['size'] > $maxSize) {
                $errorMessage = 'PDF must be 10MB or smaller.';
            } else {
                $safeName = uniqid('term_', true) . '.pdf';
                $targetPath = rtrim(UPLOAD_TERMS_PATH, '/\\') . DIRECTORY_SEPARATOR . $safeName;

                if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $errorMessage = 'Upload failed. Please try again.';
                } else {
                    $stmt = $db->prepare(
                        "INSERT INTO university_terms (institution_name, title, description, file_name, uploaded_by) "
                        . "VALUES (?, ?, ?, ?, ?)"
                    );
                    $stmt->execute([
                        $institution,
                        $title,
                        $description !== '' ? $description : null,
                        $safeName,
                        (int) ($_SESSION['admin_id'] ?? 0) ?: null,
                    ]);
                    $successMessage = 'University terms PDF uploaded successfully.';
                }
            }
        }
    } elseif ($action === 'delete_term') {
        $termId = (int) ($_POST['term_id'] ?? 0);
        if ($termId <= 0) {
            $errorMessage = 'Invalid record selected.';
        } else {
            $stmt = $db->prepare("SELECT file_name FROM university_terms WHERE id = ?");
            $stmt->execute([$termId]);
            $term = $stmt->fetch();

            if (!$term) {
                $errorMessage = 'Terms record not found.';
            } else {
                $delete = $db->prepare("DELETE FROM university_terms WHERE id = ?");
                $delete->execute([$termId]);
                $filePath = rtrim(UPLOAD_TERMS_PATH, '/\\') . DIRECTORY_SEPARATOR . $term['file_name'];
                if (is_file($filePath)) {
                    @unlink($filePath);
                }
                $successMessage = 'Terms record deleted.';
            }
        }
    }
}

$terms = $db->query(
    "SELECT t.*, a.full_name AS admin_name "
    . "FROM university_terms t "
    . "LEFT JOIN admins a ON t.uploaded_by = a.id "
    . "ORDER BY t.created_at DESC"
)->fetchAll();

adminRenderHeader('University Terms', 'university_terms');
adminRenderSubTitle('Upload and manage university rules/terms PDFs (admin-only).');
adminRenderFlash($successMessage, $errorMessage);
?>

<div class="panel space-bottom">
    <h3 style="margin-bottom:0.8rem;">Upload Terms PDF</h3>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_term">
        <div class="form-row">
            <input type="text" name="institution_name" placeholder="Institution name" required>
            <input type="text" name="title" placeholder="Document title" required>
        </div>
        <div class="form-row">
            <textarea name="description" placeholder="Short description (optional)"></textarea>
        </div>
        <div class="form-row">
            <input type="file" name="terms_pdf" accept="application/pdf" required>
            <button class="btn" type="submit"><i class="fas fa-file-upload"></i> Upload PDF</button>
        </div>
        <div class="muted">Accepted format: PDF. Max size: 10MB.</div>
    </form>
</div>

<div class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Institution</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>File</th>
                    <th>Uploaded By</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$terms): ?>
                <tr><td colspan="7" class="muted" style="padding: 1rem 0.5rem;">No university terms uploaded yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($terms as $term): ?>
                <tr>
                    <td><strong><?= adminH($term['institution_name']) ?></strong></td>
                    <td><?= adminH($term['title']) ?></td>
                    <td><?= adminH($term['description'] ?: '—') ?></td>
                    <td>
                        <a class="btn secondary" href="<?= adminH(UPLOAD_TERMS_URL . '/' . rawurlencode($term['file_name'])) ?>" target="_blank" rel="noopener">
                            <i class="fas fa-file-pdf"></i> View PDF
                        </a>
                    </td>
                    <td><?= adminH($term['admin_name'] ?: 'System') ?></td>
                    <td class="muted"><?= adminH(date('Y-m-d H:i', strtotime($term['created_at']))) ?></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Delete this terms document?');">
                            <input type="hidden" name="action" value="delete_term">
                            <input type="hidden" name="term_id" value="<?= adminH($term['id']) ?>">
                            <button class="btn secondary" type="submit"><i class="fas fa-trash"></i> Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php adminRenderFooter(); ?>
