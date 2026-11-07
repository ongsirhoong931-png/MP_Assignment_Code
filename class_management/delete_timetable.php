
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$id = trim($_GET['id'] ?? $_POST['schedule_id'] ?? '');

$stmt = $pdo->prepare("
    SELECT t.*, sub.subject_name
    FROM timetable_schedules t
    JOIN subjects sub ON sub.subject_id = t.subject_id
    WHERE t.schedule_id = ?
");
$stmt->execute([$id]);
$schedule = $stmt->fetch();

if (!$schedule) {
    http_response_code(404);
    exit('Schedule not found.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    if (($_POST['confirm'] ?? '') !== 'DELETE') {
        $error = 'Type DELETE to confirm.';
    } else {
        try {
            $stmt = $pdo->prepare("
                DELETE FROM timetable_schedules
                WHERE schedule_id = ?
            ");
            $stmt->execute([$id]);

            notice('Timetable schedule deleted successfully.');
            go('class_management/timetable.php');

        } catch (PDOException $e) {
            $error = 'Unable to delete schedule.';
        }
    }
}

$pageTitle = 'Delete Timetable';
require __DIR__ . '/../includes/header.php';
?>

<h1>Delete Timetable Schedule</h1>

<?php if ($error): ?>
    <p class="error"><?= h($error) ?></p>
<?php endif; ?>

<section class="panel">
    <p><strong>Schedule ID:</strong>
        <?= h($schedule['schedule_id']) ?></p>

    <p><strong>Class:</strong>
        <?= h($schedule['class_id']) ?></p>

    <p><strong>Subject:</strong>
        <?= h($schedule['subject_name']) ?></p>

    <p><strong>Day:</strong>
        <?= h($schedule['day_of_week']) ?></p>

    <p class="error">
        This action removes the schedule. Existing attendance
        records will no longer reference this schedule.
    </p>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="schedule_id" value="<?= h($id) ?>">

        <label>Type DELETE to confirm
            <input name="confirm" required>
        </label>

        <button type="submit">Delete Schedule</button>
        <a href="<?= h(url('class_management/timetable.php')) ?>">Cancel</a>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
