<?php
require_once __DIR__ . '/../includes/leave_module.php';

allow_roles('student', 'staff');

$leaveId = trim((string) ($_GET['id'] ?? $_POST['leave_id'] ?? ''));

/* My leaves that can still take a certificate. */
$mine = array_values(array_filter(
    lv_list($pdo, []),
    fn(array $l) => lv_can_upload($pdo, $l)
));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();

    $leave = $leaveId !== '' ? lv_find($pdo, $leaveId) : null;
    $error = null;

    if ($leave === null || !lv_can_upload($pdo, $leave)) {
        $error = t('lv.err.cannot_upload');
    } else {
        [$path, $error] = lv_store_mc($_FILES['mc'] ?? [], $leave['leave_id']);
    }

    if ($error === null) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE leave_applications SET attachment_path = ?
                 WHERE leave_id = ? AND status IN ('Pending', 'Approved')"
            );
            $stmt->execute([$path, $leave['leave_id']]);

            lv_delete_mc($leave['attachment_path']);   // replace the old file
            lv_log($pdo, $leave['leave_id'], 'mc_upload', $leave);
            am_flash(t('lv.msg.mc_saved'));
            go('leave/leave_status.php?id=' . urlencode($leave['leave_id']));
        } catch (PDOException $e) {
            lv_delete_mc($path);
            error_log($e->getMessage());
            $error = t('lv.err.save');
        }
    }

    am_flash($error, 'error');
}

$pageTitle = t('lv.mc.title');
require __DIR__ . '/../includes/header.php';
lv_nav('mc');
?>

<h1><?= h(t('lv.mc.title')) ?></h1>

<?php if (!$mine): ?>
    <p class="am-note"><?= h(t('lv.mc.none')) ?></p>
<?php else: ?>
<form method="post" enctype="multipart/form-data" class="am-card lv-form">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">

    <label><?= h(t('lv.mc.choose')) ?>
        <select name="leave_id" required>
            <option value=""><?= h(t('lv.mc.select')) ?></option>
            <?php foreach ($mine as $l): ?>
                <option value="<?= h($l['leave_id']) ?>"
                    <?= $l['leave_id'] === $leaveId ? 'selected' : '' ?>>
                    <?= h($l['leave_id'] . ' - ' . lv_type_label((string) $l['leave_type'])
                        . ' (' . $l['start_date'] . ' → ' . $l['end_date'] . ')'
                        . ($l['attachment_path'] ? ' ✓' : '')) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label><?= h(t('lv.f.mc')) ?>
        <input type="file" name="mc" required accept=".pdf,.jpg,.jpeg,.png">
        <span class="lv-hint"><?= h(t('lv.f.mc_hint', ['mb' => LV_MC_MAX_BYTES / 1048576])) ?></span>
        <span class="lv-hint"><?= h(t('lv.mc.replace_hint')) ?></span>
    </label>

    <button type="submit"><?= h(t('lv.btn.upload')) ?></button>
</form>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>