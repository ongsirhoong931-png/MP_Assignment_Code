<?php
require_once __DIR__ . '/../includes/attendance_module.php';

allow_roles('admin', 'staff');

$classId = trim((string) ($_GET['class_id'] ?? $_POST['class_id'] ?? ''));
$date = am_parse_date($_GET['date'] ?? $_POST['date'] ?? '') ?? am_today();

/* ---------- Save ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();

    $error = am_check_day($pdo, $classId, $date);

    if ($error === null) {
        try {
            $posted = is_array($_POST['status'] ?? null) ? $_POST['status'] : [];
            $result = am_save_roster($pdo, $classId, $date, null, $posted);
            am_flash('Attendance saved: ' . am_save_message($result));
        } catch (PDOException $e) {
            error_log($e->getMessage());
            am_flash('Could not save attendance. Please try again.', 'error');
        }
    } else {
        am_flash($error, 'error');
    }

    go('attendance/mark_attendance.php?' . http_build_query([
        'class_id' => $classId,
        'date'     => $date,
    ]));
}

/* ---------- Show ---------- */
$students = [];
$current = [];
$onLeave = [];
$problem = null;

if ($classId !== '') {
    if (!am_can_manage_class($pdo, $classId)) {
        $problem = 'You cannot record attendance for that class.';
        $classId = '';
    } else {
        $students = am_students_in_class($pdo, $classId);
        $current = am_existing_status_map($pdo, $classId, $date);
        $onLeave = am_students_on_leave(
            $pdo, array_column($students, 'student_id'), $date
        );
        $problem = am_check_day($pdo, $classId, $date);
    }
}

$pageTitle = 'Mark Attendance';
require __DIR__ . '/../includes/header.php';
am_nav('mark');
?>

<h1>Mark Student Attendance</h1>

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

        <?php am_roster_table($students, $current, $onLeave); ?>

        <p>
            <button type="submit" <?= $problem ? 'disabled' : '' ?>>
                Save attendance
            </button>
        </p>
    </form>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>