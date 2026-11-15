<?php
require_once __DIR__ . '/../includes/leave_module.php';

allow_roles('admin', 'staff');

/* ---------- Approve one application ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();

    $leave = lv_find($pdo, trim((string) ($_POST['leave_id'] ?? '')));

    if ($leave === null || !lv_can_review($pdo, $leave)) {
        am_flash(t('lv.err.cannot_review'), 'error');
    } else {
        try {
            $pdo->beginTransaction();

            // Only a Pending leave can be approved, even if two people click.
            $stmt = $pdo->prepare(
                "UPDATE leave_applications
                 SET status = 'Approved', approved_by_id = ?
                 WHERE leave_id = ? AND status = 'Pending'"
            );
            $stmt->execute([lv_reviewer_id($pdo), $leave['leave_id']]);

            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                am_flash(t('lv.err.already_decided'), 'error');
            } else {
                $leave['status'] = 'Approved';
                lv_log($pdo, $leave['leave_id'], 'approve', $leave);

                // Feature 10: the student's attendance follows the leave.
                $sync = lv_sync_attendance($pdo, $leave);
                $pdo->commit();

                $message = t('lv.msg.approved', ['id' => $leave['leave_id']]);
                if ($leave['applicant_type'] === 'Student') {
                    $message .= ' ' . lv_sync_message($sync);
                }
                am_flash($message);
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($e->getMessage());
            am_flash(t('lv.err.save'), 'error');
        }
    }

    go('leave/approve_leave.php');
}

/* ---------- Waiting list ---------- */
$page = max(1, (int) ($_GET['page'] ?? 1));
$filters = ['status' => 'Pending', 'exclude_own' => true,
            'q' => trim((string) ($_GET['q'] ?? ''))];
$total = lv_count($pdo, $filters);
$rows = lv_list($pdo, $filters, AM_PAGE_SIZE, ($page - 1) * AM_PAGE_SIZE);

$pageTitle = t('lv.approve.title');
require __DIR__ . '/../includes/header.php';
lv_nav('approve');
?>

<h1><?= h(t('lv.approve.title')) ?></h1>

<p class="am-note"><?= h(am_is_admin() ? t('lv.approve.note_admin') : t('lv.approve.note_staff')) ?></p>

<form method="get" class="am-filters">
    <label><?= h(t('lv.f.search')) ?>
        <input type="text" name="q" value="<?= h($filters['q']) ?>"
               placeholder="<?= h(t('lv.f.search_hint')) ?>">
    </label>
    <button type="submit"><?= h(t('lv.btn.filter')) ?></button>
</form>

<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th><?= h(t('lv.col.id')) ?></th>
            <th><?= h(t('lv.col.applicant')) ?></th>
            <th><?= h(t('lv.col.type')) ?></th>
            <th><?= h(t('lv.col.dates')) ?></th>
            <th><?= h(t('lv.f.reason')) ?></th>
            <th><?= h(t('lv.f.mc')) ?></th>
            <th><?= h(t('lv.col.actions')) ?></th>
        </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="7"><?= h(t('lv.approve.empty')) ?></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $l): ?>
        <tr>
            <td><?= h($l['leave_id']) ?></td>
            <td>
                <?= h($l['applicant_name'] ?? $l['applicant_id']) ?>
                <br><small><?= h($l['applicant_id']) ?> (<?= h(t('lv.who.' . strtolower($l['applicant_type']))) ?>)</small>
            </td>
            <td><?= h(lv_type_label((string) $l['leave_type'])) ?></td>
            <td><?= h($l['start_date']) ?> &rarr; <?= h($l['end_date']) ?>
                <br><small><?= h(t('lv.days', ['n' => lv_days($l['start_date'], $l['end_date'])])) ?></small></td>
            <td><?= h(mb_strimwidth((string) $l['reason'], 0, 80, '...')) ?></td>
            <td>
                <?php if ($l['attachment_path']): ?>
                    <a href="<?= h(url('leave/mc_file.php?id=' . urlencode($l['leave_id']))) ?>" target="_blank" rel="noopener"><?= h(t('lv.btn.open_mc')) ?></a>
                <?php else: ?>-<?php endif; ?>
            </td>
            <td class="lv-actions">
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                    <input type="hidden" name="leave_id" value="<?= h($l['leave_id']) ?>">
                    <button type="submit"><?= h(t('lv.btn.approve')) ?></button>
                </form>
                <a href="<?= h(url('leave/reject_leave.php?id=' . urlencode($l['leave_id']))) ?>"><?= h(t('lv.btn.reject')) ?></a>
                <a href="<?= h(url('leave/leave_status.php?id=' . urlencode($l['leave_id']))) ?>"><?= h(t('lv.btn.details')) ?></a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?= lv_pager($total, $page, ['q' => $filters['q']]) ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>