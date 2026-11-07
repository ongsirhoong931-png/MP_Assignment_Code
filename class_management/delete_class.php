
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$id = trim($_GET['id'] ?? $_POST['class_id'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM classes WHERE class_id = ?");
$stmt->execute([$id]);
$class = $stmt->fetch();

if (!$class) {
    http_response_code(404);
    exit('Class not found.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    if (($_POST['confirm'] ?? '') !== 'DELETE') {
        $error = 'Type DELETE to confirm.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT class_id FROM classes WHERE class_id = ? FOR UPDATE"
            );
            $stmt->execute([$id]);

            $dependencies = [
                'attendance_records',
                'timetable_schedules',
                'study_materials',
                'announcements'
            ];

            foreach ($dependencies as $table) {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM $table WHERE class_id = ?"
                );
                $stmt->execute([$id]);

                if ((int)$stmt->fetchColumn() > 0) {
                    throw new RuntimeException(
                        'Class has related records and cannot be deleted.'
                    );
                }
            }

            $stmt = $pdo->prepare("DELETE FROM classes WHERE class_id = ?");
            $stmt->execute([$id]);

            $pdo->commit();
            notice('Class deleted successfully.');
            go('class_management/index.php');

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Cannot delete this class. Remove related records first or keep it for historical reporting.';
        }
    }
}

$pageTitle = 'Delete Class';
require __DIR__ . '/../includes/header.php';
?>

<h1>Delete Class</h1>

<?php if ($error): ?>
    <p class="error"><?= h($error) ?></p>
<?php endif; ?>

<section class="panel">
    <p>Class ID: <strong><?= h($class['class_id']) ?></strong></p>
    <p>Grade: <?= h($class['grade_level']) ?></p>

    <p class="error">
        Warning: Deleting a class removes its enrolments and
        clears students' primary class assignments.
    </p>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="class_id" value="<?= h($id) ?>">

        <label>Type DELETE to confirm
            <input name="confirm" required>
        </label>

        <button type="submit">Delete Class</button>
        <a href="<?= h(url('class_management/index.php')) ?>">Cancel</a>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
