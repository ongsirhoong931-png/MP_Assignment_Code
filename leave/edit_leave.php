<?php
require_once __DIR__ . '/../includes/leave_module.php';

allow_roles('student', 'staff');

$leaveId = trim((string) ($_GET['id'] ?? $_POST['leave_id'] ?? ''));
$leave = $leaveId !== '' ? lv_find($pdo, $leaveId) : null;

if ($leave === null || !lv_can_edit($pdo, $leave)) {
    am_flash(t('lv.err.cannot_edit'), 'error');
    go('leave/view_leave.php');
}

$form = [
    'leave_type' => (string) $leave['leave_type'],
    'start_date' => $leave['start_date'],
    'end_date'   => $leave['end_date'],
    'reason'     => (string) $leave['reason'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();

    $form = [
        'leave_type' => (string) ($_POST['leave_type'] ?? ''),
        'start_date' => (string) ($_POST['start_date'] ?? ''),
        'end_date'   => (string) ($_POST['end_date'] ?? ''),
        'reason'     => trim((string) ($_POST['reason'] ?? '')),
    ];
    $start = am_parse_date($form['start_date']);
    $end = am_parse_date($form['end_date']);

    if (!in_array($form['leave_type'], LV_TYPES, true)) {
        $error = t('lv.err.type');
    } else {
        // Unchanged dates are not checked again (they may already be older
        // than the "late" limit); new dates are.
        $same = $start === $leave['start_date'] && $end === $leave['end_date'];
        $error = ($same ? null : lv_check_dates(
            $pdo, $leave['applicant_type'], $leave['applicant_id'],
            $start, $end, $leave['leave_id']
        )) ?? lv_check_reason($form['reason']);
    }

    if ($error === null) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE leave_applications
                 SET leave_type = ?, start_date = ?, end_date = ?, reason = ?
                 WHERE leave_id = ? AND status = 'Pending'"
            );
            $stmt->execute([
                $form['leave_type'], $start, $end, $form['reason'], $leave['leave_id'],
            ]);

            if ($stmt->rowCount() === 0) {
                // Same values, or a reviewer decided in the meantime.
                $fresh = lv_find($pdo, $leave['leave_id']);
                $error = ($fresh && $fresh['status'] !== 'Pending')
                    ? t('lv.err.already_decided') : t('lv.err.no_change');
            } else {
                $leave['start_date'] = $start;
                $leave['end_date'] = $end;
                lv_log($pdo, $leave['leave_id'], 'update', $leave);
                am_flash(t('lv.msg.updated'));
                go('leave/leave_status.php?id=' . urlencode($leave['leave_id']));
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = t('lv.err.save');
        }
    }

    am_flash($error, 'error');
}

$pageTitle = t('lv.edit.title');
require __DIR__ . '/../includes/header.php';
lv_nav('view');

$min = date('Y-m-d', strtotime('-' . LV_LATE_DAYS . ' days'));
$max = date('Y-m-d', strtotime('+' . LV_AHEAD_DAYS . ' days'));
?>

<h1><?= h(t('lv.edit.title')) ?> <small>(<?= h($leave['leave_id']) ?>)</small></h1>

<form method="post" class="am-card lv-form">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="leave_id" value="<?= h($leave['leave_id']) ?>">

    <label><?= h(t('lv.f.type')) ?>
        <?= lv_type_select('leave_type', $form['leave_type']) ?>
    </label>

    <label><?= h(t('lv.f.start')) ?>
        <input type="date" name="start_date" required max="<?= h($max) ?>"
               min="<?= h(min($min, $leave['start_date'])) ?>"
               value="<?= h($form['start_date']) ?>">
    </label>

    <label><?= h(t('lv.f.end')) ?>
        <input type="date" name="end_date" required max="<?= h($max) ?>"
               min="<?= h(min($min, $leave['start_date'])) ?>"
               value="<?= h($form['end_date']) ?>">
    </label>

    <label><?= h(t('lv.f.reason')) ?>
        <textarea name="reason" required minlength="5" maxlength="500"><?= h($form['reason']) ?></textarea>
    </label>

    <div class="lv-actions">
        <button type="submit"><?= h(t('lv.btn.save')) ?></button>
        <a href="<?= h(url('leave/view_leave.php')) ?>"><?= h(t('lv.btn.back')) ?></a>
    </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>