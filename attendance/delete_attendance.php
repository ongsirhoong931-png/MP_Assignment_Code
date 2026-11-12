<?php
require_once __DIR__ . '/../includes/attendance_module.php';

allow_roles('admin', 'staff');

$id = trim((string) ($_GET['id'] ?? $_POST['id'] ?? ''));
$record = $id !== '' ? am_record($pdo, $id) : null;

if (!$record || !am_can_manage_class($pdo, $record['class_id'])) {
    am_flash('That attendance record was not found.', 'error');
    go('attendance/view_attendance.php');
}

/* ---------- Delete (POST only, after the confirm page) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();

    try {
        $pdo->beginTransaction();

        $pdo->prepare(
            'DELETE FROM attendance_records WHERE attendance_id = ?'
        )->execute([$id]);

        am_audit(
            $pdo, 'delete', $id, $record['student_id'],
            $record['class_id'], $record['date'],
            $record['status'], null
        );

        $pdo->commit();

        am_flash('Attendance record deleted.');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($e->getMessage());
        am_flash('Could not delete the record. Please try again.', 'error');
    }

    go('attendance/view_attendance.php');
}

$pageTitle = 'Delete Attendance Record';
require __DIR__ . '/../includes/header.php';
am_nav('view');
?>

<h1>Delete Attendance Record</h1>

<p class="am-error">
    This permanently removes the record below. This cannot be undone.
</p>

<div class="am-card">
    <p><strong>Student:</strong>
        <?= h($record['student_name']) ?> (<?= h($record['student_id']) ?>)</p>
    <p><strong>Class:</strong>
        <?= h($record['grade_level'] . ' ' . $record['section']) ?></p>
    <p><strong>Date:</strong> <?= h($record['date']) ?></p>
    <p><strong>Status:</strong> <?= am_badge($record['status']) ?></p>
</div>

<form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="id" value="<?= h($id) ?>">
    <button type="submit">Yes, delete this record</button>
    <a href="view_attendance.php">Cancel</a>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>