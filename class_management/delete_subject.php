
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$id = trim($_GET['id'] ?? $_POST['subject_id'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM subjects WHERE subject_id = ?");
$stmt->execute([$id]);
$subject = $stmt->fetch();

if (!$subject) {
    http_response_code(404);
    exit('Subject not found.');
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
                "SELECT subject_id FROM subjects WHERE subject_id = ? FOR UPDATE"
            );
            $stmt->execute([$id]);

            foreach (['timetable_schedules', 'study_materials'] as $table) {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM $table WHERE subject_id = ?"
                );
                $stmt->execute([$id]);

                if ((int)$stmt->fetchColumn() > 0) {
                    throw new RuntimeException('Subject is still in use.');
                }
            }

            $stmt = $pdo->prepare("DELETE FROM subjects WHERE subject_id = ?");
            $stmt->execute([$id]);

            $pdo->commit();
            notice('Subject deleted successfully.');
            go('class_management/subjects.php');

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Cannot delete this subject while related records exist.';
        }
    }
}

$pageTitle = 'Delete Subject';
require __DIR__ . '/../includes/header.php';
?>

<h1>Delete Subject</h1>

<?php if ($error): ?><p class="error"><?= h($error) ?></p><?php endif; ?>

<section class="panel">
    <p><strong><?= h($subject['subject_name']) ?></strong>
       (<?= h($subject['subject_id']) ?>)</p>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="subject_id" value="<?= h($id) ?>">

        <label>Type DELETE to confirm
            <input name="confirm" required>
        </label>

        <button type="submit">Delete Subject</button>
        <a href="<?= h(url('class_management/subjects.php')) ?>">Cancel</a>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
