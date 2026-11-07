
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$id = trim($_GET['id'] ?? $_POST['student_id'] ?? '');

if ($id === '') {
    http_response_code(400);
    exit('Student ID is required.');
}

$stmt = $pdo->prepare("
    SELECT student_id, student_name
    FROM students
    WHERE student_id = ?
");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    http_response_code(404);
    exit('Student not found.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    if (($_POST['confirmation'] ?? '') !== 'DELETE') {
        $errors[] = 'Type DELETE to confirm.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Lock the student record during deletion.
            $stmt = $pdo->prepare("
                SELECT student_id
                FROM students
                WHERE student_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$id]);

            if (!$stmt->fetch()) {
                throw new RuntimeException('Student not found.');
            }

            // Preserve attendance history.
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM attendance_records
                WHERE student_id = ?
            ");
            $stmt->execute([$id]);
            $attendanceCount = (int)$stmt->fetchColumn();

            // Preserve student leave applications.
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM leave_applications
                WHERE applicant_id = ?
                  AND applicant_type = 'Student'
            ");
            $stmt->execute([$id]);
            $leaveCount = (int)$stmt->fetchColumn();

            if ($attendanceCount > 0 || $leaveCount > 0) {
                throw new RuntimeException(
                    'Cannot delete this student because attendance or leave records exist. Disable the login account instead.'
                );
            }

            $stmt = $pdo->prepare("
                DELETE FROM students
                WHERE student_id = ?
            ");
            $stmt->execute([$id]);

            $pdo->commit();

            notice('Student deleted successfully.');
            go('student_management/index.php');

        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e->getMessage();

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Unable to delete student because related database records may exist.';
        }
    }
}

$pageTitle = 'Delete Student';
require __DIR__ . '/../includes/header.php';
?>

<h1>Delete Student</h1>

<?php foreach ($errors as $error): ?>
    <p class="error"><?= h($error) ?></p>
<?php endforeach; ?>

<section class="panel">

    <p>Are you sure you want to delete this student?</p>

    <p><strong>Student ID:</strong>
        <?= h($student['student_id']) ?></p>

    <p><strong>Student Name:</strong>
        <?= h($student['student_name']) ?></p>

    <p class="error">
        Warning: Deleting a student also deletes linked
        login accounts and class enrolments.
        This action cannot be undone.
    </p>

    <form method="post">

        <input type="hidden" name="csrf"
               value="<?= h(csrf()) ?>">

        <input type="hidden" name="student_id"
               value="<?= h($id) ?>">

        <label>Type DELETE to confirm
            <input type="text"
                   name="confirmation"
                   required>
        </label>

        <button type="submit">Delete Student</button>

        <a href="<?= h(url(
            'student_management/index.php'
        )) ?>">Cancel</a>

    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
