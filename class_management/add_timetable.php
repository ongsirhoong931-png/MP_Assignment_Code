
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

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

    $id = trim($_POST['schedule_id'] ?? '');
    $classId = trim($_POST['class_id'] ?? '');
    $subjectId = trim($_POST['subject_id'] ?? '');
    $staffId = trim($_POST['assigned_staff_id'] ?? '');
    $day = $_POST['day_of_week'] ?? '';
    $start = $_POST['start_time'] ?? '';
    $end = $_POST['end_time'] ?? '';
    $room = trim($_POST['room_number'] ?? '');
    $type = trim($_POST['room_type'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_-]{1,12}$/', $id)) {
        $errors[] = 'Invalid Schedule ID.';
    }
    if (!in_array($day, $days, true)) {
        $errors[] = 'Invalid day.';
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $start) ||
        !preg_match('/^\d{2}:\d{2}$/', $end) ||
        $start >= $end) {
        $errors[] = 'Enter valid start and end times.';
    }
    if (strlen($room) > 50 || strlen($type) > 60) {
        $errors[] = 'Room information is too long.';
    }

    foreach ([
        [$classes, 'class_id', $classId, 'Class'],
        [$subjects, 'subject_id', $subjectId, 'Subject']
    ] as [$items, $key, $value, $label]) {
        if (!in_array($value, array_column($items, $key), true)) {
            $errors[] = "Invalid $label selected.";
        }
    }

    if ($staffId !== '' &&
        !in_array($staffId, array_column($staff, 'staff_id'), true)) {
        $errors[] = 'Invalid tutor selected.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM timetable_schedules
            WHERE day_of_week = ?
              AND start_time < ?
              AND end_time > ?
              AND (
                  class_id = ?
                  OR (assigned_staff_id = ? AND ? <> '')
                  OR (room_number = ? AND ? <> '')
              )
        ");
        $stmt->execute([
            $day, $end, $start, $classId,
            $staffId, $staffId, $room, $room
        ]);

        if ((int)$stmt->fetchColumn() > 0) {
            $errors[] = 'Schedule conflicts with another class, tutor or room.';
        }
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO timetable_schedules
                (schedule_id, class_id, subject_id, assigned_staff_id,
                 day_of_week, start_time, end_time, room_number, room_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $id, $classId, $subjectId, $staffId ?: null,
                $day, $start, $end, $room ?: null, $type ?: null
            ]);

            notice('Timetable schedule added.');
            go('class_management/timetable.php');
        } catch (PDOException $e) {
            $errors[] = 'Unable to save schedule. Schedule ID may already exist.';
        }
    }
}

$pageTitle = 'Add Timetable';
require __DIR__ . '/../includes/header.php';
?>

<h1>Add Timetable Schedule</h1>

<?php foreach ($errors as $error): ?>
    <p class="error"><?= h($error) ?></p>
<?php endforeach; ?>

<section class="panel">
<form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">

    <label>Schedule ID
        <input name="schedule_id" maxlength="12"
               placeholder="SCH011"
               value="<?= h($_POST['schedule_id'] ?? '') ?>" required>
    </label>

    <label>Class
        <select name="class_id" required>
            <option value="">Select Class</option>
            <?php foreach ($classes as $c): ?>
                <option value="<?= h($c['class_id']) ?>"
                    <?= ($_POST['class_id'] ?? '') === $c['class_id'] ? 'selected' : '' ?>>
                    <?= h($c['class_id'] . ' - ' . $c['grade_level'] . ' ' . $c['section']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Subject
        <select name="subject_id" required>
            <option value="">Select Subject</option>
            <?php foreach ($subjects as $s): ?>
                <option value="<?= h($s['subject_id']) ?>"
                    <?= ($_POST['subject_id'] ?? '') === $s['subject_id'] ? 'selected' : '' ?>>
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
                    <?= ($_POST['assigned_staff_id'] ?? '') === $s['staff_id'] ? 'selected' : '' ?>>
                    <?= h($s['staff_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Day
        <select name="day_of_week" required>
            <?php foreach ($days as $day): ?>
                <option value="<?= h($day) ?>"
                    <?= ($_POST['day_of_week'] ?? '') === $day ? 'selected' : '' ?>>
                    <?= h($day) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Start Time
        <input type="time" name="start_time"
               value="<?= h($_POST['start_time'] ?? '') ?>" required>
    </label>

    <label>End Time
        <input type="time" name="end_time"
               value="<?= h($_POST['end_time'] ?? '') ?>" required>
    </label>

    <label>Room Number
        <input name="room_number" maxlength="50"
               value="<?= h($_POST['room_number'] ?? '') ?>">
    </label>

    <label>Room Type
        <input name="room_type" maxlength="60"
               value="<?= h($_POST['room_type'] ?? '') ?>">
    </label>

    <button type="submit">Save Schedule</button>
    <a href="<?= h(url('class_management/timetable.php')) ?>">Cancel</a>
</form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
