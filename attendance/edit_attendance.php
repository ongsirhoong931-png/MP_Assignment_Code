<?php
require_once __DIR__ . '/../includes/attendance_module.php';

allow_roles('admin', 'staff');

$id = trim((string) ($_GET['id'] ?? $_POST['id'] ?? ''));
$record = $id !== '' ? am_record($pdo, $id) : null;

if (!$record || !am_can_manage_class($pdo, $record['class_id'])) {
    am_flash('That attendance record was not found.', 'error');
    go('attendance/view_attendance.php');
}

/* ---------- Update ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();

    $status = (string) ($_POST['status'] ?? '');

    if (!in_array($status, AM_STATUSES, true)) {
        am_flash('Please choose a valid status.', 'error');
        go('attendance/edit_attendance.php?id=' . urlencode($id));
    }

    try {
        $pdo->beginTransaction();

        [$result, $old] = am_upsert(
            $pdo,
            $record['student_id'],
            $record['class_id'],
            $record['date'],
            $status,
            null,
            am_staff_id($pdo)
        );

        if ($result !== 'unchanged') {
            am_audit(
                $pdo, 'update', $id, $record['student_id'],
                $record['class_id'], $record['date'], $old, $status
            );
        }

        $pdo->commit();

        am_flash(
            $result === 'unchanged'
                ? 'No change: the status was already ' . $status . '.'
                : 'Attendance record updated.'
        );
        go('attendance/view_attendance.php?' . http_build_query([
            'class_id'  => $record['class_id'],
            'date_from' => $record['date'],
            'date_to'   => $record['date'],
        ]));
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($e->getMessage());
        am_flash('Could not update the record. Please try again.', 'error');
        go('attendance/edit_attendance.php?id=' . urlencode($id));
    }
}

$pageTitle = 'Update Attendance Record';
require __DIR__ . '/../includes/header.php';
am_nav('view');
?>

<h1>Update Attendance Record</h1>

<div class="am-card">
    <p><strong>Student:</strong>
        <?= h($record['student_name']) ?> (<?= h($record['student_id']) ?>)</p>
    <p><strong>Class:</strong>
        <?= h($record['grade_level'] . ' ' . $record['section']) ?></p>
    <p><strong>Date:</strong> <?= h($record['date']) ?></p>
    <p><strong>Subject:</strong> <?= h($record['subject_name'] ?? '-') ?></p>
    <p><strong>Current status:</strong> <?= am_badge($record['status']) ?></p>
    <p class="am-note">The date and student cannot be edited. To record a
        different day, mark attendance for that day instead.</p>
</div>

<form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="id" value="<?= h($id) ?>">

    <div class="am-filters">
        <label>New status
            <?= am_status_select('status', $record['status']) ?>
        </label>
        <button type="submit">Save changes</button>
        <a href="view_attendance.php">Cancel</a>
    </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>