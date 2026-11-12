<?php
require_once __DIR__ . '/../includes/attendance_module.php';

allow_roles('admin');

$classId = trim((string) ($_GET['class_id'] ?? $_POST['class_id'] ?? ''));
$date = am_parse_date($_GET['date'] ?? $_POST['date'] ?? '') ?? am_today();
$studentId = trim((string) ($_GET['student_id'] ?? $_POST['student_id'] ?? ''));

if ($classId !== '' && !isset(am_classes($pdo)[$classId])) {
    $classId = '';
}

$students = $classId !== '' ? am_students_in_class($pdo, $classId) : [];
$current = $classId !== '' ? am_existing_status_map($pdo, $classId, $date) : [];

/* ---------- Override ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();

    $status = (string) ($_POST['status'] ?? '');
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $enrolled = in_array($studentId, array_column($students, 'student_id'), true);
    $error = null;

    if ($classId === '' || !$enrolled) {
        $error = 'Choose a class and a student enrolled in it.';
    } elseif ($date > am_today()) {
        $error = 'The date cannot be in the future.';
    } elseif (!in_array($status, AM_STATUSES, true)) {
        $error = 'Choose a valid status.';
    } elseif (mb_strlen($reason) < 5 || mb_strlen($reason) > 255) {
        $error = 'Give a reason of 5 to 255 characters.';
    }

    if ($error === null) {
        try {
            $pdo->beginTransaction();

            // Admin may override any day, including non-tuition days.
            [$result, $old, $id] = am_upsert(
                $pdo, $studentId, $classId, $date, $status, null, null
            );

            if ($result === 'unchanged') {
                $pdo->commit();
                am_flash('No change: the record already says ' . $status . '.');
            } else {
                am_audit(
                    $pdo, 'override', $id, $studentId, $classId,
                    $date, $old, $status, $reason
                );
                $pdo->commit();
                am_flash(
                    'Override saved (' . ($old ?? 'no record') . ' to '
                    . $status . ').'
                );
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($e->getMessage());
            $error = 'Could not save the override. Please try again.';
        }
    }

    if ($error !== null) {
        am_flash($error, 'error');
    }

    go('attendance/attendance_override.php?' . http_build_query([
        'class_id' => $classId, 'date' => $date, 'student_id' => $studentId,
    ]));
}

/* ---------- Recent overrides ---------- */
$log = [];
$logMissing = false;

try {
    $log = $pdo->query(
        "SELECT l.acted_at, l.student_id, s.student_name, l.class_id,
                l.`date`, l.old_status, l.new_status, l.reason
         FROM attendance_audit_log l
         LEFT JOIN students s ON s.student_id = l.student_id
         WHERE l.action = 'override'
         ORDER BY l.acted_at DESC, l.log_id DESC
         LIMIT 15"
    )->fetchAll();
} catch (PDOException $e) {
    $logMissing = true;
}

$pageTitle = 'Admin Attendance Override';
require __DIR__ . '/../includes/header.php';
am_nav('override');
?>

<h1>Admin Attendance Override</h1>

<p class="am-note">
    Use this to correct a record or to record attendance on a day staff
    cannot (for example a non-tuition day). Every override needs a reason
    and is written to the audit log.
</p>

<form method="get" class="am-filters">
    <label>Class <?= am_class_select($pdo, 'class_id', $classId) ?></label>
    <label>Date
        <input type="date" name="date" value="<?= h($date) ?>"
               max="<?= h(am_today()) ?>">
    </label>
    <button type="submit">Load students</button>
</form>

<?php if ($classId !== '' && $students): ?>
<form method="post" class="am-card">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="class_id" value="<?= h($classId) ?>">
    <input type="hidden" name="date" value="<?= h($date) ?>">

    <div class="am-filters">
        <label>Student
            <select name="student_id" required>
                <option value="">Select a student</option>
                <?php foreach ($students as $s):
                    $sid = $s['student_id']; ?>
                    <option value="<?= h($sid) ?>"
                        <?= $sid === $studentId ? 'selected' : '' ?>>
                        <?= h($sid . ' - ' . $s['student_name']
                            . ' [' . ($current[$sid] ?? 'no record') . ']') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>New status <?= am_status_select('status', 'Present') ?></label>
        <label>Reason
            <input type="text" name="reason" required minlength="5"
                   maxlength="255" size="40"
                   placeholder="e.g. Tutor forgot to mark; bus delay confirmed">
        </label>
        <button type="submit">Apply override</button>
    </div>
</form>
<?php elseif ($classId !== ''): ?>
    <p>No students are enrolled in this class.</p>
<?php endif; ?>

<h2>Recent overrides</h2>
<?php if ($logMissing): ?>
    <p class="am-error">
        The audit log table does not exist yet. Import
        <code>tuition_attendance_db.sql</code> again in phpMyAdmin.
    </p>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead>
        <tr><th>When</th><th>Student</th><th>Class</th><th>Date</th>
            <th>Change</th><th>Reason</th></tr>
    </thead>
    <tbody>
    <?php if (!$log): ?>
        <tr><td colspan="6">No overrides yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($log as $l): ?>
        <tr>
            <td><?= h($l['acted_at']) ?></td>
            <td><?= h($l['student_name'] ?? $l['student_id']) ?></td>
            <td><?= h($l['class_id']) ?></td>
            <td><?= h($l['date']) ?></td>
            <td><?= h(($l['old_status'] ?? 'none') . ' to ' . $l['new_status']) ?></td>
            <td><?= h($l['reason']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>