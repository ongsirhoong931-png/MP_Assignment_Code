<?php
require_once __DIR__ . '/../includes/leave_module.php';

allow_roles('admin', 'staff', 'student');

$lookup = trim((string) ($_GET['id'] ?? ''));
$leave = $lookup !== '' ? lv_find($pdo, $lookup) : null;
$recent = [];

if ($lookup === '') {
    $recent = lv_list($pdo, [], 10);
}

$pageTitle = t('lv.status.title');
require __DIR__ . '/../includes/header.php';
lv_nav('status');
?>

<h1><?= h(t('lv.status.title')) ?></h1>

<form method="get" class="am-filters">
    <label><?= h(t('lv.status.enter')) ?>
        <input type="text" name="id" value="<?= h($lookup) ?>"
               maxlength="12" placeholder="LV1A2B3C4D5E">
    </label>
    <button type="submit"><?= h(t('lv.btn.check')) ?></button>
</form>

<?php if ($lookup !== '' && $leave === null): ?>
    <p class="am-error"><?= h(t('lv.status.not_found')) ?></p>

<?php elseif ($leave !== null):
    $reviewer = $leave['status'] === 'Pending' ? '-' : lv_person_name($pdo, $leave['approved_by_id']);
    $rejectWhy = $leave['status'] === 'Rejected' ? lv_reject_reason($pdo, $leave['leave_id']) : null;
?>
    <div class="am-card">
        <p><?= lv_badge($leave['status']) ?></p>
        <dl class="lv-detail">
            <dt><?= h(t('lv.col.id')) ?></dt><dd><?= h($leave['leave_id']) ?></dd>
            <dt><?= h(t('lv.col.applicant')) ?></dt>
            <dd><?= h(($leave['applicant_name'] ?? '-') . ' (' . $leave['applicant_id'] . ', '
                . t('lv.who.' . strtolower($leave['applicant_type'])) . ')') ?></dd>
            <dt><?= h(t('lv.f.type')) ?></dt><dd><?= h(lv_type_label((string) $leave['leave_type'])) ?></dd>
            <dt><?= h(t('lv.col.dates')) ?></dt>
            <dd><?= h($leave['start_date']) ?> &rarr; <?= h($leave['end_date']) ?>
                (<?= h(t('lv.days', ['n' => lv_days($leave['start_date'], $leave['end_date'])])) ?>)</dd>
            <dt><?= h(t('lv.f.reason')) ?></dt><dd><?= nl2br(h((string) $leave['reason'])) ?></dd>
            <dt><?= h(t('lv.status.submitted_on')) ?></dt><dd><?= h($leave['created_at']) ?></dd>
            <dt><?= h(t('lv.status.reviewed_by')) ?></dt><dd><?= h($reviewer) ?></dd>
            <?php if ($rejectWhy !== null): ?>
                <dt><?= h(t('lv.reject.reason')) ?></dt><dd><?= nl2br(h($rejectWhy)) ?></dd>
            <?php endif; ?>
            <dt><?= h(t('lv.f.mc')) ?></dt>
            <dd>
                <?php if (lv_mc_path($leave['attachment_path']) !== null): ?>
                    <a href="<?= h(url('leave/mc_file.php?id=' . urlencode($leave['leave_id']))) ?>" target="_blank" rel="noopener"><?= h(t('lv.btn.open_mc')) ?></a>
                <?php else: ?>
                    <?= h(t('lv.status.no_mc')) ?>
                <?php endif; ?>
            </dd>
        </dl>

        <div class="lv-actions">
            <?php if (lv_can_edit($pdo, $leave)): ?>
                <a href="<?= h(url('leave/edit_leave.php?id=' . urlencode($leave['leave_id']))) ?>"><?= h(t('lv.btn.edit')) ?></a>
            <?php endif; ?>
            <?php if (lv_can_upload($pdo, $leave)): ?>
                <a href="<?= h(url('leave/upload_mc.php?id=' . urlencode($leave['leave_id']))) ?>"><?= h(t('lv.btn.mc')) ?></a>
            <?php endif; ?>
            <?php if (lv_can_cancel($pdo, $leave)): ?>
                <a href="<?= h(url('leave/cancel_leave.php?id=' . urlencode($leave['leave_id']))) ?>"><?= h(t('lv.btn.cancel')) ?></a>
            <?php endif; ?>
            <?php if ($leave['status'] === 'Pending' && lv_can_review($pdo, $leave)): ?>
                <form method="post" action="<?= h(url('leave/approve_leave.php')) ?>">
                    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                    <input type="hidden" name="leave_id" value="<?= h($leave['leave_id']) ?>">
                    <button type="submit"><?= h(t('lv.btn.approve')) ?></button>
                </form>
                <a href="<?= h(url('leave/reject_leave.php?id=' . urlencode($leave['leave_id']))) ?>"><?= h(t('lv.btn.reject')) ?></a>
            <?php endif; ?>
        </div>
    </div>

    <?php $timeline = lv_timeline($pdo, $leave['leave_id']); ?>
    <?php if ($timeline): ?>
    <h2><?= h(t('lv.status.history')) ?></h2>
    <ul class="lv-timeline">
        <?php foreach ($timeline as $line): ?>
            <li>
                <?= h(lv_action_label($line['action'])) ?>
                <?= $line['note'] ? ' - ' . h($line['note']) : '' ?>
                <small><?= h($line['acted_at']) ?> (<?= h($line['acted_by_role']) ?>)</small>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

<?php else: ?>
    <h2><?= h(t('lv.status.recent')) ?></h2>
    <div class="table-wrap">
    <table>
        <thead>
            <tr><th><?= h(t('lv.col.id')) ?></th><th><?= h(t('lv.col.applicant')) ?></th>
                <th><?= h(t('lv.col.dates')) ?></th><th><?= h(t('lv.col.status')) ?></th><th></th></tr>
        </thead>
        <tbody>
        <?php if (!$recent): ?>
            <tr><td colspan="5"><?= h(t('lv.none')) ?></td></tr>
        <?php endif; ?>
        <?php foreach ($recent as $l): ?>
            <tr>
                <td><?= h($l['leave_id']) ?></td>
                <td><?= h($l['applicant_name'] ?? $l['applicant_id']) ?></td>
                <td><?= h($l['start_date']) ?> &rarr; <?= h($l['end_date']) ?></td>
                <td><?= lv_badge($l['status']) ?></td>
                <td><a href="?id=<?= h(urlencode($l['leave_id'])) ?>"><?= h(t('lv.btn.details')) ?></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>