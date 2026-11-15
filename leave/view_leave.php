<?php
require_once __DIR__ . '/../includes/leave_module.php';

allow_roles('admin', 'staff', 'student');

$filters = lv_filters_from_get();
$page = max(1, (int) ($_GET['page'] ?? 1));
$total = lv_count($pdo, $filters);
$rows = lv_list($pdo, $filters, AM_PAGE_SIZE, ($page - 1) * AM_PAGE_SIZE);

$pageTitle = t('lv.view.title');
require __DIR__ . '/../includes/header.php';
lv_nav('view');
?>

<h1><?= h(t('lv.view.title')) ?></h1>

<form method="get" class="am-filters">
    <label><?= h(t('lv.f.search')) ?>
        <input type="text" name="q" value="<?= h($filters['q']) ?>"
               placeholder="<?= h(t('lv.f.search_hint')) ?>">
    </label>
    <label><?= h(t('lv.f.status')) ?>
        <?= lv_status_select('status', $filters['status']) ?>
    </label>
    <label><?= h(t('lv.f.type')) ?>
        <?= lv_type_select('type', $filters['type'], true, true) ?>
    </label>
    <label><?= h(t('lv.f.from')) ?>
        <input type="date" name="date_from" value="<?= h($filters['date_from']) ?>">
    </label>
    <label><?= h(t('lv.f.to')) ?>
        <input type="date" name="date_to" value="<?= h($filters['date_to']) ?>">
    </label>
    <button type="submit"><?= h(t('lv.btn.filter')) ?></button>
</form>

<p class="am-note"><?= h(t('lv.view.count', ['n' => $total])) ?></p>

<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th><?= h(t('lv.col.id')) ?></th>
            <th><?= h(t('lv.col.applicant')) ?></th>
            <th><?= h(t('lv.col.type')) ?></th>
            <th><?= h(t('lv.col.dates')) ?></th>
            <th><?= h(t('lv.col.days')) ?></th>
            <th><?= h(t('lv.col.status')) ?></th>
            <th><?= h(t('lv.col.actions')) ?></th>
        </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="7"><?= h(t('lv.none')) ?></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $l): ?>
        <tr>
            <td><?= h($l['leave_id']) ?></td>
            <td>
                <?= h($l['applicant_name'] ?? $l['applicant_id']) ?>
                <br><small><?= h($l['applicant_id']) ?> (<?= h(t('lv.who.' . strtolower($l['applicant_type']))) ?>)</small>
            </td>
            <td><?= h(lv_type_label((string) $l['leave_type'])) ?></td>
            <td><?= h($l['start_date']) ?> &rarr; <?= h($l['end_date']) ?></td>
            <td><?= (int) lv_days($l['start_date'], $l['end_date']) ?></td>
            <td><?= lv_badge($l['status']) ?></td>
            <td class="lv-actions">
                <a href="<?= h(url('leave/leave_status.php?id=' . urlencode($l['leave_id']))) ?>"><?= h(t('lv.btn.details')) ?></a>

                <?php if (lv_can_edit($pdo, $l)): ?>
                    <a href="<?= h(url('leave/edit_leave.php?id=' . urlencode($l['leave_id']))) ?>"><?= h(t('lv.btn.edit')) ?></a>
                <?php endif; ?>

                <?php if (lv_can_upload($pdo, $l)): ?>
                    <a href="<?= h(url('leave/upload_mc.php?id=' . urlencode($l['leave_id']))) ?>"><?= h(t('lv.btn.mc')) ?></a>
                <?php endif; ?>

                <?php if (lv_can_cancel($pdo, $l)): ?>
                    <a href="<?= h(url('leave/cancel_leave.php?id=' . urlencode($l['leave_id']))) ?>"><?= h(t('lv.btn.cancel')) ?></a>
                <?php endif; ?>

                <?php if ($l['status'] === 'Pending' && lv_can_review($pdo, $l)): ?>
                    <form method="post" action="<?= h(url('leave/approve_leave.php')) ?>">
                        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                        <input type="hidden" name="leave_id" value="<?= h($l['leave_id']) ?>">
                        <button type="submit"><?= h(t('lv.btn.approve')) ?></button>
                    </form>
                    <a href="<?= h(url('leave/reject_leave.php?id=' . urlencode($l['leave_id']))) ?>"><?= h(t('lv.btn.reject')) ?></a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?= lv_pager($total, $page, $filters) ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>