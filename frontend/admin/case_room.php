<?php
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/_ui.php';
requireAdminLogin();
ensureReportingTables();

$db = getDB();
$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$reportId = (int) ($_GET['report_id'] ?? 0);

if ($reportId <= 0) {
    redirect('reports.php');
}

$successMessage = '';
$errorMessage = '';
$newToken = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add_note') {
        $note = trim((string) ($_POST['note'] ?? ''));
        if ($note === '') {
            $errorMessage = 'Note cannot be empty.';
        } else {
            $stmt = $db->prepare("INSERT INTO report_notes (report_id, admin_id, note, is_internal) VALUES (?, ?, ?, 1)");
            $stmt->execute([$reportId, $adminId > 0 ? $adminId : null, $note]);
            $successMessage = 'Note added.';
        }
    } elseif ($action === 'add_task') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $dueDate = trim((string) ($_POST['due_date'] ?? ''));
        if ($title === '') {
            $errorMessage = 'Task title is required.';
        } else {
            $stmt = $db->prepare("INSERT INTO report_tasks (report_id, title, due_date, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$reportId, $title, $dueDate !== '' ? $dueDate : null, $adminId > 0 ? $adminId : null]);
            $successMessage = 'Task created.';
        }
    } elseif ($action === 'toggle_task') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $newStatus = (string) ($_POST['status'] ?? 'open');
        if ($taskId > 0 && in_array($newStatus, ['open', 'done'], true)) {
            if ($newStatus === 'done') {
                $stmt = $db->prepare("UPDATE report_tasks SET status = 'done', completed_at = NOW() WHERE id = ? AND report_id = ?");
                $stmt->execute([$taskId, $reportId]);
            } else {
                $stmt = $db->prepare("UPDATE report_tasks SET status = 'open', completed_at = NULL WHERE id = ? AND report_id = ?");
                $stmt->execute([$taskId, $reportId]);
            }
            $successMessage = 'Task updated.';
        }
    } elseif ($action === 'regenerate_token') {
        $newToken = createFollowupToken($reportId, 90);
        if ($newToken) {
            $successMessage = 'New follow-up token generated.';
        } else {
            $errorMessage = 'Unable to generate a follow-up token.';
        }
    } elseif ($action === 'disable_token') {
        $stmt = $db->prepare("DELETE FROM report_followup_tokens WHERE report_id = ?");
        $stmt->execute([$reportId]);
        $successMessage = 'Follow-up token disabled.';
    } elseif ($action === 'request_id_verification' || $action === 'approve_id' || $action === 'reject_id') {
        $note = trim((string) ($_POST['id_review_note'] ?? ''));
        if ($action === 'request_id_verification') {
            $update = $db->prepare("UPDATE reports SET id_verification_required = 1, id_verification_status = 'requested', id_verification_requested_by = ?, id_verification_requested_at = NOW() WHERE id = ?");
            $update->execute([$adminId > 0 ? $adminId : null, $reportId]);
            $successMessage = 'ID verification requested.';
        } elseif ($action === 'approve_id') {
            $update = $db->prepare("UPDATE reports SET id_verification_status = 'approved', id_verification_reviewed_by = ?, id_verification_reviewed_at = NOW(), id_verification_review_note = ? WHERE id = ?");
            $update->execute([$adminId > 0 ? $adminId : null, $note !== '' ? $note : null, $reportId]);
            $successMessage = 'ID verified.';
        } else {
            $update = $db->prepare("UPDATE reports SET id_verification_status = 'rejected', id_verification_reviewed_by = ?, id_verification_reviewed_at = NOW(), id_verification_review_note = ? WHERE id = ?");
            $update->execute([$adminId > 0 ? $adminId : null, $note !== '' ? $note : null, $reportId]);
            $successMessage = 'ID verification rejected.';
        }
    }
}

$stmt = $db->prepare("SELECT r.*, s.full_name AS student_name, s.student_id AS student_code, a.username AS assigned_username FROM reports r LEFT JOIN students s ON r.student_id = s.id LEFT JOIN admins a ON r.assigned_to = a.id WHERE r.id = ?");
$stmt->execute([$reportId]);
$report = $stmt->fetch();
if (!$report) {
    redirect('reports.php');
}

$historyStmt = $db->prepare("SELECT h.*, ad.full_name AS admin_name FROM report_status_history h LEFT JOIN admins ad ON h.changed_by = ad.id WHERE h.report_id = ? ORDER BY h.created_at DESC");
$historyStmt->execute([$reportId]);
$history = $historyStmt->fetchAll();

$notesStmt = $db->prepare("SELECT n.*, ad.full_name AS admin_name FROM report_notes n LEFT JOIN admins ad ON n.admin_id = ad.id WHERE n.report_id = ? ORDER BY n.created_at DESC");
$notesStmt->execute([$reportId]);
$notes = $notesStmt->fetchAll();

$tasksStmt = $db->prepare("SELECT t.*, ad.full_name AS admin_name FROM report_tasks t LEFT JOIN admins ad ON t.created_by = ad.id WHERE t.report_id = ? ORDER BY t.created_at DESC");
$tasksStmt->execute([$reportId]);
$tasks = $tasksStmt->fetchAll();

$msgStmt = $db->prepare("SELECT * FROM report_messages WHERE report_id = ? ORDER BY created_at ASC");
$msgStmt->execute([$reportId]);
$messages = $msgStmt->fetchAll();

$surveyStmt = $db->prepare("SELECT * FROM report_outcome_surveys WHERE report_id = ? LIMIT 1");
$surveyStmt->execute([$reportId]);
$survey = $surveyStmt->fetch();

$tokenStmt = $db->prepare("SELECT * FROM report_followup_tokens WHERE report_id = ? LIMIT 1");
$tokenStmt->execute([$reportId]);
$tokenRow = $tokenStmt->fetch();
$tokenStatus = 'Disabled';
if ($tokenRow) {
    $tokenStatus = 'Active';
    if (!empty($tokenRow['expires_at']) && strtotime($tokenRow['expires_at']) < time()) {
        $tokenStatus = 'Expired';
    }
}

$logStmt = $db->prepare("SELECT l.*, ad.full_name AS admin_name FROM report_evidence_access_log l LEFT JOIN admins ad ON l.admin_id = ad.id WHERE l.report_id = ? ORDER BY l.created_at DESC");
$logStmt->execute([$reportId]);
$logs = $logStmt->fetchAll();

$annotationsStmt = $db->prepare("SELECT a.*, ad.full_name AS admin_name FROM report_evidence_annotations a LEFT JOIN admins ad ON a.admin_id = ad.id WHERE a.report_id = ? ORDER BY a.created_at DESC");
$annotationsStmt->execute([$reportId]);
$annotations = $annotationsStmt->fetchAll();

$evidenceItems = [];
if (!empty($report['evidence_files'])) {
    $decoded = json_decode((string) $report['evidence_files'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $idx => $entry) {
            $viewerUrl = SITE_URL . '/admin/evidence_viewer.php?report_id=' . $reportId . '&index=' . (int) $idx;
            $label = strpos((string) $entry, 'drive_link:') === 0 ? 'Cloud Link' : basename((string) $entry);
            $evidenceItems[] = [
                'label' => $label,
                'viewer' => $viewerUrl,
            ];
        }
    }
}

adminRenderHeader('Case Room', 'reports');
adminRenderSubTitle('Confidential case room for internal review and coordination.');
adminRenderFlash($successMessage, $errorMessage);
?>

<style>
.case-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 1rem; }
.case-panel { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 1rem; }
.case-panel h3 { font-family: 'Syne', sans-serif; font-size: 1rem; margin-bottom: 0.7rem; color: var(--cyan); }
.timeline-item { padding: 0.6rem 0; border-bottom: 1px solid rgba(255,255,255,0.08); }
.timeline-item:last-child { border-bottom: none; }
.task-item { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.08); }
.task-item:last-child { border-bottom: none; }
.muted-small { font-size: 0.75rem; color: var(--gray-400); }
.modal { position: fixed; inset: 0; background: rgba(2, 6, 23, 0.7); display: flex; align-items: center; justify-content: center; z-index: 999; }
.modal.hidden { display: none; }
.modal-dialog { width: min(520px, 92vw); background: #0f172a; border: 1px solid rgba(255,255,255,0.15); border-radius: 14px; padding: 1rem; box-shadow: 0 20px 60px rgba(0,0,0,0.35); }
.modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.6rem; }
.modal-title { font-family: 'Syne', sans-serif; font-size: 1rem; }
.modal-body { display: grid; gap: 0.4rem; }
.modal-row { display: flex; justify-content: space-between; gap: 0.6rem; font-size: 0.9rem; }
.modal-label { color: var(--gray-400); }
.modal-actions { display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 0.8rem; }
.btn.disabled { opacity: 0.6; pointer-events: none; }
@media (max-width: 980px) { .case-grid { grid-template-columns: 1fr; } }
</style>

<div class="case-panel space-bottom">
    <div class="form-row">
        <div>
            <div class="muted">Tracking ID</div>
            <div class="stat-value" style="font-size:1.4rem;"><?= adminH($report['tracking_id']) ?></div>
        </div>
        <div>
            <div class="muted">Status</div>
            <div><?= getStatusBadge($report['status']) ?> <?= getPriorityBadge($report['priority']) ?></div>
            <div class="muted-small">Assigned: <?= adminH($report['assigned_username'] ?: 'Unassigned') ?></div>
        </div>
    </div>
</div>

<div class="case-grid">
    <div class="case-panel">
        <h3>Case Summary</h3>
        <p><strong><?= adminH($report['title']) ?></strong></p>
        <p class="muted">Category: <?= adminH(ucwords(str_replace('_', ' ', $report['category']))) ?></p>
        <p class="muted">Submitted: <?= adminH(date('Y-m-d H:i', strtotime($report['created_at']))) ?></p>
        <p class="muted">Reporter: <?= adminH($report['student_name'] ?: 'Anonymous') ?> <?= $report['student_code'] ? '(' . adminH($report['student_code']) . ')' : '' ?></p>
        <p class="muted">Accused ID: <?= adminH($report['accused_student_id'] ?: 'Not provided') ?></p>
                <?php if (!empty($report['accused_student_id'])): ?>
                    <button class="btn secondary" type="button" id="accused-details-btn" data-accused-id="<?= adminH($report['accused_student_id']) ?>">
                        <i class="fas fa-id-card"></i> View Accused Details
                    </button>
                <?php endif; ?>
        <div class="space-top">
            <a class="btn secondary" href="reports.php"><i class="fas fa-arrow-left"></i> Back to Reports</a>
            <a class="btn" href="reports.php?filter=pending"><i class="fas fa-inbox"></i> Pending Queue</a>
        </div>
    </div>

    <div class="case-panel">
        <h3>Evidence</h3>
        <?php if ($evidenceItems): ?>
            <?php foreach ($evidenceItems as $item): ?>
                <div class="timeline-item">
                    <div><?= adminH($item['label']) ?></div>
                    <a class="btn secondary" href="<?= adminH($item['viewer']) ?>" target="_blank" rel="noopener">Open</a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="muted">No evidence uploaded.</div>
        <?php endif; ?>
    </div>
</div>

<div id="accused-modal" class="modal hidden" aria-hidden="true">
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="accused-title">
        <div class="modal-header">
            <div id="accused-title" class="modal-title">Accused Student Details</div>
            <button class="btn secondary" type="button" id="accused-close">Close</button>
        </div>
        <div class="modal-body">
            <div class="modal-row"><span class="modal-label">Student ID</span><span id="accused-id">-</span></div>
            <div class="modal-row"><span class="modal-label">Full name</span><span id="accused-name">-</span></div>
            <div class="modal-row"><span class="modal-label">Email</span><span id="accused-email">-</span></div>
            <div class="modal-row"><span class="modal-label">Department</span><span id="accused-dept">-</span></div>
            <div class="modal-row"><span class="modal-label">GPA</span><span id="accused-gpa">-</span></div>
            <div class="modal-row"><span class="modal-label">Graduation year</span><span id="accused-grad">-</span></div>
            <div id="accused-status" class="muted">Checking directory...</div>
        </div>
        <div class="modal-actions">
            <a id="accused-mail" class="btn" href="#" target="_blank" rel="noopener">Send mail</a>
        </div>
    </div>
</div>

<div class="case-grid space-top">
    <div class="case-panel">
        <h3>ID Verification</h3>
        <div class="timeline-item">Status: <?= adminH(ucwords(str_replace('_', ' ', (string) $report['id_verification_status']))) ?></div>
        <?php if (!empty($report['id_verification_file'])): ?>
            <div class="timeline-item">
                <a class="btn secondary" href="../../backend/admin/id_verification.php?report_id=<?= adminH($reportId) ?>" target="_blank" rel="noopener">View ID Document</a>
            </div>
        <?php endif; ?>

        <?php if (empty($report['id_verification_required'])): ?>
            <form method="POST" class="space-top">
                <input type="hidden" name="action" value="request_id_verification">
                <button class="btn secondary" type="submit">Request ID Verification</button>
            </form>
        <?php elseif ($report['id_verification_status'] === 'submitted'): ?>
            <form method="POST" class="space-top">
                <textarea name="id_review_note" placeholder="Review note (optional)"></textarea>
                <div class="space-top" style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                    <button class="btn" name="action" value="approve_id" type="submit">Approve</button>
                    <button class="btn secondary" name="action" value="reject_id" type="submit">Reject</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="case-grid space-top">
    <div class="case-panel">
        <h3>Anonymous Follow-up Token</h3>
        <div class="timeline-item">Status: <?= adminH($tokenStatus) ?></div>
        <?php if ($tokenRow): ?>
            <div class="muted-small">Created: <?= adminH(date('Y-m-d H:i', strtotime($tokenRow['created_at']))) ?></div>
            <div class="muted-small">Last Used: <?= $tokenRow['last_used_at'] ? adminH(date('Y-m-d H:i', strtotime($tokenRow['last_used_at']))) : 'Never' ?></div>
            <div class="muted-small">Expires: <?= $tokenRow['expires_at'] ? adminH(date('Y-m-d H:i', strtotime($tokenRow['expires_at']))) : 'No expiry' ?></div>
        <?php else: ?>
            <div class="muted">No active token for this report.</div>
        <?php endif; ?>

        <?php if ($newToken): ?>
            <div class="space-top" style="padding: 0.75rem; border: 1px dashed rgba(255,255,255,0.2); border-radius: 12px;">
                <div class="muted-small">New token (share once with reporter):</div>
                <div style="font-weight:600; word-break:break-all;"><?= adminH($newToken) ?></div>
            </div>
        <?php endif; ?>

        <div class="space-top" style="display:flex; gap:0.5rem; flex-wrap:wrap;">
            <form method="POST">
                <input type="hidden" name="action" value="regenerate_token">
                <button class="btn" type="submit"><i class="fas fa-sync"></i> Regenerate Token</button>
            </form>
            <?php if ($tokenRow): ?>
                <form method="POST" onsubmit="return confirm('Disable the current follow-up token? This cannot be undone.');">
                    <input type="hidden" name="action" value="disable_token">
                    <button class="btn secondary" type="submit"><i class="fas fa-ban"></i> Disable Token</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="case-grid space-top">
    <div class="case-panel">
        <h3>Timeline</h3>
        <?php if ($history): ?>
            <?php foreach ($history as $event): ?>
                <div class="timeline-item">
                    <div><?= adminH(ucwords(str_replace('_', ' ', $event['old_status'] ?: 'new'))) ?> → <?= adminH(ucwords(str_replace('_', ' ', $event['new_status']))) ?></div>
                    <div class="muted-small"><?= adminH($event['admin_name'] ?: 'System') ?> • <?= adminH(date('Y-m-d H:i', strtotime($event['created_at']))) ?></div>
                    <?php if (!empty($event['notes'])): ?><div class="muted-small"><?= adminH($event['notes']) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="muted">No status changes logged yet.</div>
        <?php endif; ?>
    </div>

    <div class="case-panel">
        <h3>Tasks</h3>
        <?php if ($tasks): ?>
            <?php foreach ($tasks as $task): ?>
                <div class="task-item">
                    <div>
                        <div><?= adminH($task['title']) ?></div>
                        <div class="muted-small"><?= adminH($task['admin_name'] ?: 'Admin') ?> • <?= adminH(date('Y-m-d', strtotime($task['created_at']))) ?></div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="toggle_task">
                        <input type="hidden" name="task_id" value="<?= adminH($task['id']) ?>">
                        <input type="hidden" name="status" value="<?= $task['status'] === 'done' ? 'open' : 'done' ?>">
                        <button class="btn secondary" type="submit"><?= $task['status'] === 'done' ? 'Reopen' : 'Complete' ?></button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="muted">No tasks yet.</div>
        <?php endif; ?>
        <form method="POST" class="space-top">
            <input type="hidden" name="action" value="add_task">
            <div class="form-row">
                <input type="text" name="title" placeholder="Task title" required>
                <input type="date" name="due_date">
            </div>
            <button class="btn" type="submit"><i class="fas fa-plus"></i> Add Task</button>
        </form>
    </div>
</div>

<div class="case-grid space-top">
    <div class="case-panel">
        <h3>Internal Notes</h3>
        <?php if ($notes): ?>
            <?php foreach ($notes as $note): ?>
                <div class="timeline-item">
                    <div><?= adminH($note['note']) ?></div>
                    <div class="muted-small"><?= adminH($note['admin_name'] ?: 'Admin') ?> • <?= adminH(date('Y-m-d H:i', strtotime($note['created_at']))) ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="muted">No internal notes yet.</div>
        <?php endif; ?>
        <form method="POST" class="space-top">
            <input type="hidden" name="action" value="add_note">
            <textarea name="note" placeholder="Add internal note..." required></textarea>
            <div class="space-top" style="display:flex; justify-content:flex-end;">
                <button class="btn" type="submit">Save Note</button>
            </div>
        </form>
    </div>

    <div class="case-panel">
        <h3>Reporter Messages</h3>
        <?php if ($messages): ?>
            <?php foreach ($messages as $msg): ?>
                <div class="timeline-item">
                    <div><?= adminH($msg['sender_type'] === 'admin' ? 'Admin' : 'Reporter') ?>: <?= adminH($msg['message']) ?></div>
                    <div class="muted-small"><?= adminH(date('Y-m-d H:i', strtotime($msg['created_at']))) ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="muted">No messages yet.</div>
        <?php endif; ?>
    </div>
</div>

<div class="case-grid space-top">
    <div class="case-panel">
        <h3>Evidence Access Log</h3>
        <?php if ($logs): ?>
            <?php foreach ($logs as $log): ?>
                <div class="timeline-item">
                    <div><?= adminH($log['file_name']) ?> • <?= adminH($log['access_type']) ?></div>
                    <div class="muted-small"><?= adminH($log['admin_name'] ?: 'Admin') ?> • <?= adminH(date('Y-m-d H:i', strtotime($log['created_at']))) ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="muted">No evidence access logged.</div>
        <?php endif; ?>
    </div>

    <div class="case-panel">
        <h3>Evidence Annotations</h3>
        <?php if ($annotations): ?>
            <?php foreach ($annotations as $annotation): ?>
                <div class="timeline-item">
                    <div><?= adminH($annotation['note']) ?></div>
                    <div class="muted-small"><?= adminH($annotation['admin_name'] ?: 'Admin') ?> • <?= adminH(date('Y-m-d H:i', strtotime($annotation['created_at']))) ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="muted">No annotations yet.</div>
        <?php endif; ?>
    </div>
</div>

<div class="case-grid space-top">
    <div class="case-panel">
        <h3>Outcome Survey</h3>
        <?php if ($survey): ?>
            <div class="timeline-item">Satisfaction: <?= adminH((string) $survey['satisfaction_score']) ?>/5</div>
            <div class="timeline-item">Response Time: <?= adminH((string) $survey['response_time_score']) ?>/5</div>
            <div class="timeline-item">Fairness: <?= adminH((string) $survey['fairness_score']) ?>/5</div>
            <?php if (!empty($survey['comments'])): ?>
                <div class="timeline-item">Comments: <?= adminH($survey['comments']) ?></div>
            <?php endif; ?>
        <?php else: ?>
            <div class="muted">No survey submitted.</div>
        <?php endif; ?>
    </div>
</div>

<?php adminRenderFooter(); ?>

<script>
    (function () {
        var accusedBtn = document.getElementById('accused-details-btn');
        var modal = document.getElementById('accused-modal');
        var closeBtn = document.getElementById('accused-close');
        var status = document.getElementById('accused-status');
        var mailBtn = document.getElementById('accused-mail');
        var fields = {
            id: document.getElementById('accused-id'),
            name: document.getElementById('accused-name'),
            email: document.getElementById('accused-email'),
            dept: document.getElementById('accused-dept'),
            gpa: document.getElementById('accused-gpa'),
            grad: document.getElementById('accused-grad')
        };

        if (!accusedBtn || !modal) {
            return;
        }

        function setText(el, value) {
            if (el) {
                el.textContent = value || '-';
            }
        }

        function openModal() {
            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeModal() {
            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
        }

        function resetModal() {
            setText(fields.id, '-');
            setText(fields.name, '-');
            setText(fields.email, '-');
            setText(fields.dept, '-');
            setText(fields.gpa, '-');
            setText(fields.grad, '-');
            status.textContent = 'Checking directory...';
            mailBtn.classList.add('disabled');
            mailBtn.setAttribute('href', '#');
        }

        accusedBtn.addEventListener('click', function () {
            var accusedId = accusedBtn.getAttribute('data-accused-id') || '';
            resetModal();
            openModal();
            if (!accusedId) {
                status.textContent = 'Accused ID not provided.';
                return;
            }

            fetch('../../backend/ajax_get_student.php?id=' + encodeURIComponent(accusedId), {
                credentials: 'same-origin'
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data || !data.found) {
                        status.textContent = 'Accused ID not found in student directory.';
                        return;
                    }

                    setText(fields.id, data.student_id);
                    setText(fields.name, data.full_name);
                    setText(fields.email, data.email);
                    setText(fields.dept, data.department || 'N/A');
                    setText(fields.gpa, data.gpa !== null && data.gpa !== undefined ? data.gpa : 'N/A');
                    setText(fields.grad, data.graduation_year || 'N/A');
                    status.textContent = 'Student record found.';

                    if (data.email) {
                        var gmailUrl = 'https://mail.google.com/mail/?view=cm&fs=1&to=' + encodeURIComponent(data.email);
                        mailBtn.setAttribute('href', gmailUrl);
                        mailBtn.classList.remove('disabled');
                    }
                })
                .catch(function () {
                    status.textContent = 'Unable to fetch student details.';
                });
        });

        closeBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });
    })();
</script>
