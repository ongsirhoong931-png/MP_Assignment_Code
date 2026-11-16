<?php
require_once __DIR__ . '/../includes/leave_module.php';

allow_roles('admin', 'staff');

$classId = trim((string) ($_GET['class_id'] ?? $_POST['class_id'] ?? ''));
if ($classId !== '' && !isset(am_classes($pdo)[$classId])) {
    $classId = '';
}
$students = $classId !== '' ? am_students_in_class($pdo, $classId) : [];

/* ---------- Actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $action = (string) ($_POST['action'] ?? '');

    /* Feature 10: bring attendance up to date for every approved student leave. */
    if ($action === 'sync_all') {
        $sum = ['added' => 0, 'changed' => 0, 'kept' => 0];

        try {
            foreach (lv_list($pdo, ['status' => 'Approved']) as $l) {
                if ($l['applicant_type'] !== 'Student' || $l['start_date'] > am_today()) {
                    continue;
                }
                foreach (lv_sync_attendance($pdo, $l) as $k => $n) {
                    $sum[$k] += $n;
                }
            }
            am_flash(lv_sync_message($sum));
        } catch (Throwable $e) {
            error_log($e->getMessage());
            am_flash(t('lv.err.save'), 'error');
        }

        go('leave/approved_absence.php');
    }

    /* Feature 9: record absences that were approved in advance. */
    $picked = array_values(array_unique(array_map('strval', (array) ($_POST['student_ids'] ?? []))));
    $inClass = array_column($students, 'student_name', 'student_id');
    $start = am_parse_date($_POST['start_date'] ?? '');
    $end = am_parse_date($_POST['end_date'] ?? '');
    $note = trim((string) ($_POST['reason'] ?? ''));
    $error = null;

    if ($classId === '' || !$picked) {
        $error = t('lv.err.pick_students');
    } elseif (array_diff($picked, array_keys($inClass))) {
        $error = t('lv.err.student_not_in_class');
    } else {
        $error = lv_check_reason($note);
    }

    $made = 0;
    $skipped = [];
    $added = 0;

    if ($error === null) {
        $by = lv_reviewer_id($pdo);

        foreach ($picked as $studentId) {
            // Date rules and overlap check are the same as for a normal leave.
            if (lv_check_dates($pdo, 'Student', $studentId, $start, $end) !== null) {
                $skipped[] = $inClass[$studentId];
                continue;
            }

            try {
                $pdo->beginTransaction();
                $leaveId = lv_new_id($pdo);

                $pdo->prepare(
                    "INSERT INTO leave_applications
                        (leave_id, applicant_id, applicant_type, leave_type,
                         start_date, end_date, reason, status, approved_by_id)
                     VALUES (?, ?, 'Student', ?, ?, ?, ?, 'Approved', ?)"
                )->execute([$leaveId, $studentId, LV_PRE_APPROVED, $start, $end, $note, $by]);

                $leave = [
                    'leave_id' => $leaveId, 'applicant_id' => $studentId,
                    'applicant_type' => 'Student', 'start_date' => $start,
                    'end_date' => $end, 'status' => 'Approved',
                ];
                lv_log($pdo, $leaveId, 'pre_approve', $leave, $note);
                $sync = lv_sync_attendance($pdo, $leave);
                $pdo->commit();

                $made++;
                $added += $sync['added'] + $sync['changed'];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log($e->getMessage());
                $skipped[] = $inClass[$studentId];
            }
        }

        if ($made > 0) {
            $message = t('lv.pre.done', ['n' => $made, 'rows' => $added]);
            if ($skipped) {
                $message .= ' ' . t('lv.pre.skipped', ['names' => implode(', ', $skipped)]);
            }
            am_flash($message);
        } else {
            $error = t('lv.pre.none_made') . ' ' . implode(', ', $skipped);
        }
    }

    if ($error !== null) {
        am_flash($error, 'error');
    }

    go('leave/approved_absence.php?' . http_build_query(['class_id' => $classId]));
}

$recent = lv_list($pdo, ['type' => LV_PRE_APPROVED], 15);

$pageTitle = t('lv.pre.title');
require __DIR__ . '/../includes/header.php';
lv_nav('absence');

$min = date('Y-m-d', strtotime('-' . LV_LATE_DAYS . ' days'));
$max = date('Y-m-d', strtotime('+' . LV_AHEAD_DAYS . ' days'));
?>

<h1><?= h(t('lv.pre.title')) ?></h1>
<p class="am-note"><?= h(t('lv.pre.note')) ?></p>

<form method="get" class="am-filters">
    <label><?= h(t('lv.f.class')) ?> <?= am_class_select($pdo, 'class_id', $classId) ?></label>
    <button type="submit"><?= h(t('lv.btn.load')) ?></button>
</form>

<?php if ($classId !== '' && $students): ?>
<form method="post" class="am-card lv-form">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="class_id" value="<?= h($classId) ?>">

    <p><b><?= h(t('lv.pre.students')) ?></b></p>
    <div class="lv-students">
        <?php foreach ($students as $s): ?>
            <label>
                <input type="checkbox" name="student_ids[]" value="<?= h($s['student_id']) ?>">
                <?= h($s['student_id'] . ' - ' . $s['student_name']) ?>
            </label>
        <?php endforeach; ?>
    </div>

    <label><?= h(t('lv.f.start')) ?>
        <input type="date" name="start_date" required min="<?= h($min) ?>" max="<?= h($max) ?>">
    </label>
    <label><?= h(t('lv.f.end')) ?>
        <input type="date" name="end_date" required min="<?= h($min) ?>" max="<?= h($max) ?>">
    </label>
    <label><?= h(t('lv.f.reason')) ?>
        <textarea name="reason" required minlength="5" maxlength="500"
                  placeholder="<?= h(t('lv.pre.placeholder')) ?>"></textarea>
    </label>

    <button type="submit" name="action" value="record"><?= h(t('lv.pre.submit')) ?></button>
</form>
<?php elseif ($classId !== ''): ?>
    <p><?= h(t('lv.pre.no_students')) ?></p>
<?php endif; ?>

<h2><?= h(t('lv.sync.title')) ?></h2>
<p class="am-note"><?= h(t('lv.sync.note')) ?></p>
<form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <button type="submit" name="action" value="sync_all"><?= h(t('lv.sync.button')) ?></button>
</form>

<h2><?= h(t('lv.pre.recent')) ?></h2>
<div class="table-wrap">
<table>
    <thead>
        <tr><th><?= h(t('lv.col.id')) ?></th><th><?= h(t('lv.col.applicant')) ?></th>
            <th><?= h(t('lv.col.dates')) ?></th><th><?= h(t('lv.f.reason')) ?></th></tr>
    </thead>
    <tbody>
    <?php if (!$recent): ?>
        <tr><td colspan="4"><?= h(t('lv.none')) ?></td></tr>
    <?php endif; ?>
    <?php foreach ($recent as $l): ?>
        <tr>
            <td><a href="<?= h(url('leave/leave_status.php?id=' . urlencode($l['leave_id']))) ?>"><?= h($l['leave_id']) ?></a></td>
            <td><?= h(($l['applicant_name'] ?? '') . ' (' . $l['applicant_id'] . ')') ?></td>
            <td><?= h($l['start_date']) ?> &rarr; <?= h($l['end_date']) ?></td>
            <td><?= h(mb_strimwidth((string) $l['reason'], 0, 80, '...')) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>