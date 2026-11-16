<?php
require_once __DIR__ . '/../includes/leave_module.php';

allow_roles('admin', 'staff');

$leaveId = trim((string) ($_GET['id'] ?? $_POST['leave_id'] ?? ''));
$leave = $leaveId !== '' ? lv_find($pdo, $leaveId) : null;

if ($leave === null || !lv_can_review($pdo, $leave)) {
    am_flash(t('lv.err.cannot_review'), 'error');
    go('leave/approve_leave.php');
}

if ($leave['status'] !== 'Pending') {
    am_flash(t('lv.err.already_decided'), 'error');
    go('leave/leave_status.php?id=' . urlencode($leave['leave_id']));
}

$reason = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();

    $reason = trim((string) ($_POST['reject_reason'] ?? ''));
    $length = mb_strlen($reason);

    if ($length < 5 || $length > 255) {
        am_flash(t('lv.err.reject_reason'), 'error');
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "UPDATE leave_applications
                 SET status = 'Rejected', approved_by_id = ?
                 WHERE leave_id = ? AND status = 'Pending'"
            );
            $stmt->execute([lv_reviewer_id($pdo), $leave['leave_id']]);

            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                am_flash(t('lv.err.already_decided'), 'error');
            } else {
                lv_log($pdo, $leave['leave_id'], 'reject', $leave, $reason);
                $pdo->commit();
                am_flash(t('lv.msg.rejected', ['id' => $leave['leave_id']]));
                go('leave/approve_leave.php');
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($e->getMessage());
            am_flash(t('lv.err.save'), 'error');
        }
    }
}

$pageTitle = t('lv.reject.title');
require __DIR__ . '/../includes/header.php';
lv_nav('approve');
?>

<h1><?= h(t('lv.reject.title')) ?></h1>

<dl class="lv-detail">
    <dt><?= h(t('lv.col.id')) ?></dt><dd><?= h($leave['leave_id']) ?></dd>
    <dt><?= h(t('lv.col.applicant')) ?></dt><dd><?= h(($leave['applicant_name'] ?? '') . ' (' . $leave['applicant_id'] . ')') ?></dd>
    <dt><?= h(t('lv.f.type')) ?></dt><dd><?= h(lv_type_label((string) $leave['leave_type'])) ?></dd>
    <dt><?= h(t('lv.col.dates')) ?></dt><dd><?= h($leave['start_date']) ?> &rarr; <?= h($leave['end_date']) ?></dd>
    <dt><?= h(t('lv.f.reason')) ?></dt><dd><?= nl2br(h((string) $leave['reason'])) ?></dd>
</dl>

<form method="post" class="am-card lv-form">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="leave_id" value="<?= h($leave['leave_id']) ?>">

    <label><?= h(t('lv.reject.reason')) ?>
        <textarea name="reject_reason" required minlength="5" maxlength="255"><?= h($reason) ?></textarea>
        <span class="lv-hint"><?= h(t('lv.reject.hint')) ?></span>
    </label>

    <div class="lv-actions">
        <button type="submit"><?= h(t('lv.btn.confirm_reject')) ?></button>
        <a href="<?= h(url('leave/approve_leave.php')) ?>"><?= h(t('lv.btn.back')) ?></a>
    </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>