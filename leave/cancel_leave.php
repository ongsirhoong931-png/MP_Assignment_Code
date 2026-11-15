<?php
require_once __DIR__ . '/../includes/leave_module.php';

allow_roles('student', 'staff');

$leaveId = trim((string) ($_GET['id'] ?? $_POST['leave_id'] ?? ''));
$leave = $leaveId !== '' ? lv_find($pdo, $leaveId) : null;

if ($leave === null || !lv_can_cancel($pdo, $leave)) {
    am_flash(t('lv.err.cannot_cancel'), 'error');
    go('leave/view_leave.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();

    try {
        $pdo->beginTransaction();

        // Re-check inside the transaction: a reviewer may have just decided.
        $stmt = $pdo->prepare(
            "DELETE FROM leave_applications
             WHERE leave_id = ?
               AND (status = 'Pending' OR (status = 'Approved' AND start_date > ?))"
        );
        $stmt->execute([$leave['leave_id'], am_today()]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            am_flash(t('lv.err.cannot_cancel'), 'error');
        } else {
            lv_log($pdo, $leave['leave_id'], 'cancel', $leave, $leave['status']);
            $pdo->commit();
            lv_delete_mc($leave['attachment_path']);
            am_flash(t('lv.msg.cancelled', ['id' => $leave['leave_id']]));
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($e->getMessage());
        am_flash(t('lv.err.save'), 'error');
    }

    go('leave/view_leave.php');
}

$pageTitle = t('lv.cancel.title');
require __DIR__ . '/../includes/header.php';
lv_nav('view');
?>

<h1><?= h(t('lv.cancel.title')) ?></h1>

<p class="am-error"><?= h(t('lv.cancel.warning')) ?></p>

<dl class="lv-detail">
    <dt><?= h(t('lv.col.id')) ?></dt><dd><?= h($leave['leave_id']) ?></dd>
    <dt><?= h(t('lv.f.type')) ?></dt><dd><?= h(lv_type_label((string) $leave['leave_type'])) ?></dd>
    <dt><?= h(t('lv.col.dates')) ?></dt><dd><?= h($leave['start_date']) ?> &rarr; <?= h($leave['end_date']) ?></dd>
    <dt><?= h(t('lv.col.status')) ?></dt><dd><?= lv_badge($leave['status']) ?></dd>
    <dt><?= h(t('lv.f.reason')) ?></dt><dd><?= nl2br(h((string) $leave['reason'])) ?></dd>
</dl>

<form method="post" class="lv-actions">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="leave_id" value="<?= h($leave['leave_id']) ?>">
    <button type="submit"><?= h(t('lv.btn.confirm_cancel')) ?></button>
    <a href="<?= h(url('leave/view_leave.php')) ?>"><?= h(t('lv.btn.back')) ?></a>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>