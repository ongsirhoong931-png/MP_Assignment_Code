<?php
require_once __DIR__ . '/../includes/attendance_module.php';

allow_roles('admin', 'staff');

$classId = trim((string) ($_GET['class_id'] ?? $_POST['class_id'] ?? ''));
$date = am_parse_date($_GET['date'] ?? $_POST['date'] ?? '') ?? am_today();

/* ---------- Save ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();

    $error = am_check_day($pdo, $classId, $date);
    $status = (string) ($_POST['status'] ?? '');

    if ($error === null && !in_array($status, AM_STATUSES, true)) {
        $error = 'Please choose a status to apply.';
    }

    if ($error === null) {
        $students = am_students_in_class($pdo, $classId);
        $everyone = !empty($_POST['apply_all']);
        $picked = is_array($_POST['pick'] ?? null) ? $_POST['pick'] : [];
        $overwrite = !empty($_POST['overwrite']);
        $existing = am_existing_status_map($pdo, $classId, $date);

        $posted = [];
        $skipped = 0;

        foreach ($students as $s) {
            $sid = $s['student_id'];

            if (!$everyone && !in_array($sid, $picked, true)) {
                continue;
            }

            if (!$overwrite && isset($existing[$sid])) {
                $skipped++;
                continue;
            }

            $posted[$sid] = $status;
        }

        if (!$posted) {
            $error = 'No students to update. Select students, or tick '
                . '"Replace existing records".';
        } else {
            try {
                $result = am_save_roster($pdo, $classId, $date, null, $posted);
                am_flash(
                    'Bulk ' . $status . ': ' . am_save_message($result)
                    . ($skipped ? " $skipped already recorded (skipped)." : '')
                );
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $error = 'Could not save attendance. Please try again.';
            }
        }
    }

    if ($error !== null) {
        am_flash($error, 'error');
    }

    go('attendance/bulk_attendance.php?' . http_build_query([
        'class_id' => $classId,
        'date'     => $date,
    ]));
}

/* ---------- Show ---------- */
$students = [];
$current = [];
$problem = null;

if ($classId !== '') {
    if (!am_can_manage_class($pdo, $classId)) {
        $problem = 'You cannot record attendance for that class.';
        $classId = '';
    } else {
        $students = am_students_in_class($pdo, $classId);
        $current = am_existing_status_map($pdo, $classId, $date);
        $problem = am_check_day($pdo, $classId, $date);
    }
}

$pageTitle = 'Bulk Attendance Marking';
require __DIR__ . '/../includes/header.php';
am_nav('bulk');
?>

<h1>Bulk Attendance Marking</h1>

<form method="get" class="am-filters">
    <label>Class <?= am_class_select($pdo, 'class_id', $classId) ?></label>
    <label>Date
        <input type="date" name="date" value="<?= h($date) ?>"
               max="<?= h(am_today()) ?>">
    </label>
    <button type="submit">Load students</button>
</form>

<?php if ($problem): ?>
    <p class="am-error"><?= h($problem) ?></p>
<?php endif; ?>

<?php if ($classId !== '' && !$students): ?>
    <p>No students are enrolled in this class yet.</p>
<?php elseif ($classId !== ''): ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="class_id" value="<?= h($classId) ?>">
        <input type="hidden" name="date" value="<?= h($date) ?>">

        <div class="am-filters">
            <label>Apply this status
                <?= am_status_select('status', 'Present') ?>
            </label>
            <label>
                <span><input type="checkbox" name="apply_all" value="1">
                Apply to every student in the class</span>
            </label>
            <label>
                <span><input type="checkbox" name="overwrite" value="1">
                Replace existing records</span>
            </label>
        </div>

        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="pick-all"
                               aria-label="Select all"></th>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Current record</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td>
                        <input type="checkbox" class="pick" name="pick[]"
                               value="<?= h($s['student_id']) ?>">
                    </td>
                    <td><?= h($s['student_id']) ?></td>
                    <td><?= h($s['student_name']) ?></td>
                    <td>
                        <?= isset($current[$s['student_id']])
                            ? am_badge($current[$s['student_id']])
                            : '<span class="am-note">Not recorded</span>' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <p>
            <button type="submit" <?= $problem ? 'disabled' : '' ?>>
                Apply to selected
            </button>
        </p>
    </form>

    <script>
    document.getElementById('pick-all').addEventListener('change', function () {
        var on = this.checked;
        document.querySelectorAll('.pick').forEach(function (c) { c.checked = on; });
    });
    </script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>