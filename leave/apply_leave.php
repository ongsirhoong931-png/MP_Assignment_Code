<?php
require_once __DIR__ . '/../includes/leave_module.php';

allow_roles('student', 'staff');

$self = lv_self($pdo);
$form = ['leave_type' => 'Sick Leave', 'start_date' => '', 'end_date' => '', 'reason' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();

    $form = [
        'leave_type' => (string) ($_POST['leave_type'] ?? ''),
        'start_date' => (string) ($_POST['start_date'] ?? ''),
        'end_date'   => (string) ($_POST['end_date'] ?? ''),
        'reason'     => trim((string) ($_POST['reason'] ?? '')),
    ];

    $file = $_FILES['mc'] ?? null;
    $hasFile = $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $error = null;

    if ($self === null) {
        $error = t('lv.err.no_profile');
    } elseif (!in_array($form['leave_type'], LV_TYPES, true)) {
        $error = t('lv.err.type');
    } else {
        $error = lv_check_dates(
            $pdo, $self['type'], $self['id'],
            am_parse_date($form['start_date']), am_parse_date($form['end_date'])
        ) ?? lv_check_reason($form['reason']);
    }

    if ($error === null && $hasFile) {
        $error = lv_check_upload($file);
    }

    if ($error === null) {
        $leaveId = lv_new_id($pdo);
        $path = null;

        if ($hasFile) {
            [$path, $error] = lv_store_mc($file, $leaveId);
        }

        if ($error === null) {
            try {
                $pdo->prepare(
                    "INSERT INTO leave_applications
                        (leave_id, applicant_id, applicant_type, leave_type,
                         start_date, end_date, reason, status, attachment_path)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?)"
                )->execute([
                    $leaveId, $self['id'], $self['type'], $form['leave_type'],
                    am_parse_date($form['start_date']), am_parse_date($form['end_date']),
                    $form['reason'], $path,
                ]);

                $leave = [
                    'applicant_id' => $self['id'], 'applicant_type' => $self['type'],
                    'start_date' => am_parse_date($form['start_date']),
                    'end_date' => am_parse_date($form['end_date']),
                ];
                lv_log($pdo, $leaveId, 'submit', $leave);
                if ($path !== null) {
                    lv_log($pdo, $leaveId, 'mc_upload', $leave);
                }

                am_flash(t('lv.msg.submitted', ['id' => $leaveId]));
                go('leave/leave_status.php?id=' . urlencode($leaveId));
            } catch (PDOException $e) {
                lv_delete_mc($path);
                error_log($e->getMessage());
                $error = t('lv.err.save');
            }
        }
    }

    am_flash($error, 'error');
}

$pageTitle = t('lv.apply.title');
require __DIR__ . '/../includes/header.php';
lv_nav('apply');

$min = date('Y-m-d', strtotime('-' . LV_LATE_DAYS . ' days'));
$max = date('Y-m-d', strtotime('+' . LV_AHEAD_DAYS . ' days'));
?>

<h1><?= h(t('lv.apply.title')) ?></h1>

<?php if ($self === null): ?>
    <p class="am-error"><?= h(t('lv.err.no_profile')) ?></p>
<?php else: ?>
<form method="post" enctype="multipart/form-data" class="am-card lv-form">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">

    <label><?= h(t('lv.f.type')) ?>
        <?= lv_type_select('leave_type', $form['leave_type']) ?>
    </label>

    <label><?= h(t('lv.f.start')) ?>
        <input type="date" name="start_date" required
               min="<?= h($min) ?>" max="<?= h($max) ?>"
               value="<?= h($form['start_date']) ?>">
    </label>

    <label><?= h(t('lv.f.end')) ?>
        <input type="date" name="end_date" required
               min="<?= h($min) ?>" max="<?= h($max) ?>"
               value="<?= h($form['end_date']) ?>">
    </label>

    <label><?= h(t('lv.f.reason')) ?>
        <textarea name="reason" required minlength="5" maxlength="500"><?= h($form['reason']) ?></textarea>
        <span class="lv-hint"><?= h(t('lv.f.reason_hint')) ?></span>
    </label>

    <label><?= h(t('lv.f.mc_optional')) ?>
        <input type="file" name="mc" accept=".pdf,.jpg,.jpeg,.png">
        <span class="lv-hint"><?= h(t('lv.f.mc_hint', ['mb' => LV_MC_MAX_BYTES / 1048576])) ?></span>
    </label>

    <button type="submit"><?= h(t('lv.apply.submit')) ?></button>
</form>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>