
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

    $studentId = trim($_POST['student_id'] ?? '');
    $action = $_POST['action'] ?? '';

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT student_id FROM students WHERE student_id = ? FOR UPDATE"
        );
        $stmt->execute([$studentId]);

        if (!$stmt->fetch()) {
            throw new RuntimeException('Student not found.');
        }

        if ($action === 'enrol') {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO student_enrolments (student_id, class_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$studentId, $id]);

            $stmt = $pdo->prepare("
                UPDATE students
                SET student_class = COALESCE(student_class, ?)
                WHERE student_id = ?
            ");
            $stmt->execute([$id, $studentId]);

        } elseif ($action === 'remove') {
            $stmt = $pdo->prepare("
                DELETE FROM student_enrolments
                WHERE student_id = ? AND class_id = ?
            ");
            $stmt->execute([$studentId, $id]);

            $stmt = $pdo->prepare("
                UPDATE students
                SET student_class = (
                    SELECT MIN(class_id)
                    FROM student_enrolments
                    WHERE student_id = ?
                )
                WHERE student_id = ? AND student_class = ?
            ");
            $stmt->execute([$studentId, $studentId, $id]);

        } else {
            throw new RuntimeException('Invalid action.');
        }

        $pdo->commit();
        notice('Student enrolment updated.');
        go('class_management/assign_student.php?id=' . urlencode($id));

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Unable to update enrolment. ' .
                 ($e instanceof RuntimeException ? $e->getMessage() : '');
    }
}

$stmt = $pdo->prepare("
    SELECT s.student_id, s.student_name
    FROM students s
    WHERE NOT EXISTS (
        SELECT 1 FROM student_enrolments e
        WHERE e.student_id = s.student_id AND e.class_id = ?
    )
    ORDER BY s.student_name
");
$stmt->execute([$id]);
$available = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT s.student_id, s.student_name
    FROM student_enrolments e
    JOIN students s ON s.student_id = e.student_id
    WHERE e.class_id = ?
    ORDER BY s.student_name
");
$stmt->execute([$id]);
$enrolled = $stmt->fetchAll();

$pageTitle = 'Assign Students';
require __DIR__ . '/../includes/header.php';
?>

<h1>Assign Students — <?= h($id) ?></h1>

<?php if ($error): ?>
    <p class="error"><?= h($error) ?></p>
<?php endif; ?>

<section class="panel">
<h2>Enrol Student</h2>

<form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="class_id" value="<?= h($id) ?>">
    <input type="hidden" name="action" value="enrol">

    <label>Select Student
        <select name="student_id" required>
            <option value="">Select Student</option>
            <?php foreach ($available as $student): ?>
                <option value="<?= h($student['student_id']) ?>">
                    <?= h($student['student_id']) ?>
                    - <?= h($student['student_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <button type="submit">Enrol Student</button>
</form>
</section>

<h2>Currently Enrolled Students</h2>

<div class="table-wrap">
<table>
    <tr><th>Student ID</th><th>Name</th><th>Action</th></tr>

    <?php foreach ($enrolled as $student): ?>
        <tr>
            <td><?= h($student['student_id']) ?></td>
            <td><?= h($student['student_name']) ?></td>
            <td>
                <form method="post"
                      onsubmit="return confirm('Remove this enrolment?')">
                    <input type="hidden" name="csrf"
                           value="<?= h(csrf()) ?>">
                    <input type="hidden" name="class_id"
                           value="<?= h($id) ?>">
                    <input type="hidden" name="student_id"
                           value="<?= h($student['student_id']) ?>">
                    <input type="hidden" name="action" value="remove">
                    <button type="submit">Remove</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
</div>

<p><a href="<?= h(url('class_management/index.php')) ?>">Back</a></p>

<?php require __DIR__ . '/../includes/footer.php'; ?>
