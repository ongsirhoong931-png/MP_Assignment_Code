<?php
require_once __DIR__ . '/../includes/leave_module.php';

allow_roles('admin', 'staff', 'student');

$counts = [];
foreach (LV_STATUSES as $status) {
    $counts[$status] = lv_count($pdo, ['status' => $status]);
}

$tiles = [];

if (!am_is_admin()) {
    $tiles[] = ['apply_leave.php', 'lv.tile.apply', 'lv.tile.apply_text'];
}

$tiles[] = ['view_leave.php', 'lv.tile.view', 'lv.tile.view_text'];

if (!am_is_admin()) {
    $tiles[] = ['view_leave.php', 'lv.tile.edit', 'lv.tile.edit_text'];
    $tiles[] = ['view_leave.php', 'lv.tile.cancel', 'lv.tile.cancel_text'];
    $tiles[] = ['upload_mc.php', 'lv.tile.mc', 'lv.tile.mc_text'];
}

if (lv_is_reviewer()) {
    $tiles[] = ['approve_leave.php', 'lv.tile.approve', 'lv.tile.approve_text'];
    $tiles[] = ['approve_leave.php', 'lv.tile.reject', 'lv.tile.reject_text'];
}

$tiles[] = ['leave_status.php', 'lv.tile.status', 'lv.tile.status_text'];

if (lv_is_reviewer()) {
    $tiles[] = ['approved_absence.php', 'lv.tile.absence', 'lv.tile.absence_text'];
    $tiles[] = ['approved_absence.php', 'lv.tile.sync', 'lv.tile.sync_text'];
}

$pageTitle = t('lv.title');
require __DIR__ . '/../includes/header.php';
lv_nav('index');
?>

<h1><?= h(t('lv.title')) ?></h1>

<div class="am-summary">
    <?php foreach ($counts as $status => $n): ?>
        <div><b><?= (int) $n ?></b><?= h(lv_status_label($status)) ?></div>
    <?php endforeach; ?>
</div>

<div class="am-grid">
    <?php foreach ($tiles as $i => [$file, $title, $text]): ?>
        <a class="am-tile" href="<?= h(url('leave/' . $file)) ?>">
            <strong><?= ($i + 1) ?>. <?= h(t($title)) ?></strong>
            <span><?= h(t($text)) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>