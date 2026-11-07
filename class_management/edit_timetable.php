
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$id = trim($_GET['id'] ?? $_POST['schedule_id'] ?? '');

$stmt = $pdo->prepare("
    SELECT * FROM timetable_schedules WHERE schedule_id = ?
");
$stmt->execute([$id]);
$schedule = $stmt->fetch();

if (!$schedule) {
    http_response_code(404);
    exit('Schedule not found.');
}

$classes = $pdo->query("
    SELECT class_id, grade_level, section FROM classes ORDER BY class_id
")->fetchAll();

$subjects = $pdo->query("
    SELECT subject_id, subject_name FROM subjects ORDER BY subject_name
")->fetchAll();

$staff = $pdo->query("
    SELECT staff_id, staff_name FROM staff ORDER BY staff_name
")->fetchAll();

$days = [
    'Monday','Tuesday','Wednesday','Thursday',
    'Friday','Saturday','Sunday'
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $classId = trim($_POST['class_id'] ?? '');
    $subjectId = trim($_POST['subject_id'] ?? '');
    $staffId = trim($_POST['assigned_staff_id'] ?? '');
    $day = $_POST['day_of_week'] ?? '';
    $start = $_POST['start_time'] ?? '';
    $end = $_POST['end_time'] ?? '';
    $room = trim($_POST['room_number'] ?? '');
    $type = trim($_POST['room_type'] ?? '');

    if (!in_array($day, $days, true)) {
        $errors[] = 'Invalid day.';
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $start) ||
        !preg_match('/^\d{2}:\d{2}$/', $end) ||
        $start >= $end) {
        $errors[] = 'Invalid schedule time.';
    }

    if (!in_array($classId, array_column($classes, 'class_id'), true)) {
        $errors[] = 'Invalid class.';
    }

    if (!in_array($subjectId, array_column($subjects, 'subject_id'), true)) {
        $errors[] = 'Invalid subject.';
    }

    if ($staffId !== '' &&
        !in_array($staffId, array_column($staff, 'staff_id'), true)) {
        $errors[] = 'Invalid tutor.';
    }

    if (strlen($room) > 50 || strlen($type) > 60) {
        $errors[] = 'Room information is too long.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM timetable_schedules
            WHERE schedule_id <> ?
              AND day_of_week = ?
              AND start_time < ?
              AND end_time > ?
              AND (
                  class_id = ?
                  OR (assigned_staff_id = ? AND ? <> '')
                  OR (room_number = ? AND ? <> '')
              )
        ");

        $stmt->execute([
            $id, $day, $end, $start, $classId,
            $staffId, $staffId, $room, $room
        ]);

        if ((int)$stmt->fetchColumn() > 0) {
            $errors[] = 'Schedule conflicts with an existing booking.';
        }
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare("
                UPDATE timetable_schedules
                SET class_id = ?,
                    subject_id = ?,
                    assigned_staff_id = ?,
                    day_of_week = ?,
                    start_time = ?,
                    end_time = ?,
                    room_number = ?,
                    room_type = ?
                WHERE schedule_id = ?
            ");

            $stmt->execute([
                $classId, $subjectId, $staffId ?: null,
                $day, $start, $end,
                $room ?: null, $type ?: null, $id
            ]);

            notice('Schedule updated successfully.');
            go('class_management/timetable.php');

        } catch (PDOException $e) {
            $errors[] = 'Unable to update timetable schedule.';
        }
    }

    $schedule = array_merge($schedule, [
        'class_id' => $classId,
        'subject_id' => $subjectId,
        'assigned_staff_id' => $staffId,
        'day_of_week' => $day,
        'start_time' => $start,
        'end_time' => $end,
        'room_number' => $room,
        'room_type' => $type
    ]);
}

$pageTitle = 'Edit Timetable';
require __DIR__ . '/../includes/header.php';
?>

<h1>Edit Timetable — <?= h($id) ?></h1>

<?php foreach ($errors as $error): ?>
    <p class="error"><?= h($error) ?></p>
<?php endforeach; ?>

<section class="panel">
<form method="post">

    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="schedule_id" value="<?= h($id) ?>">

    <label>Class
        <select name="class_id" required>
            <?php foreach ($classes as $c): ?>
                <option value="<?= h($c['class_id']) ?>"
                    <?= $schedule['class_id'] === $c['class_id'] ? 'selected' : '' ?>>
                    <?= h($c['class_id'] . ' - ' . $c['grade_level'] . ' ' . $c['section']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Subject
        <select name="subject_id" required>
            <?php foreach ($subjects as $s): ?>
                <option value="<?= h($s['subject_id']) ?>"
                    <?= $schedule['subject_id'] === $s['subject_id'] ? 'selected' : '' ?>>
                    <?= h($s['subject_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Assigned Tutor
        <select name="assigned_staff_id">
            <option value="">Not Assigned</option>
            <?php foreach ($staff as $s): ?>
                <option value="<?= h($s['staff_id']) ?>"
                    <?= $schedule['assigned_staff_id'] === $s['staff_id'] ? 'selected' : '' ?>>
                    <?= h($s['staff_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Day
        <select name="day_of_week" required>
            <?php foreach ($days as $day): ?>
                <option value="<?= h($day) ?>"
                    <?= $schedule['day_of_week'] === $day ? 'selected' : '' ?>>
                    <?= h($day) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Start Time
        <input type="time" name="start_time"
               value="<?= h(substr($schedule['start_time'], 0, 5)) ?>"
               required>
    </label>

    <label>End Time
        <input type="time" name="end_time"
               value="<?= h(substr($schedule['end_time'], 0, 5)) ?>"
               required>
    </label>

    <label>Room Number
        <input name="room_number" maxlength="50"
               value="<?= h($schedule['room_number']) ?>">
    </label>

    <label>Room Type
        <input name="room_type" maxlength="60"
               value="<?= h($schedule['room_type']) ?>">
    </label>

    <button type="submit">Update Timetable</button>
    <a href="<?= h(url('class_management/timetable.php')) ?>">Cancel</a>
</form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
