
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$id = trim($_GET['id'] ?? $_POST['class_id'] ?? '');

$stmt = $pdo->prepare("
    SELECT c.*, s.staff_name
    FROM classes c
    LEFT JOIN staff s ON s.staff_id = c.class_teacher_id
    WHERE c.class_id = ?
");
$stmt->execute([$id]);
$class = $stmt->fetch();

if (!$class) {
    http_response_code(404);
    exit('Class not found.');
}

$staff = $pdo->query("
    SELECT staff_id, staff_name, staff_role
    FROM staff
    ORDER BY staff_name
")->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $staffId = trim($_POST['staff_id'] ?? '');

    if ($staffId !== '' &&
        !in_array($staffId, array_column($staff, 'staff_id'), true)) {
        $error = 'Invalid staff member.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE classes
                SET class_teacher_id = ?
                WHERE class_id = ?
            ");

            $stmt->execute([$staffId ?: null, $id]);

            notice('Class tutor assignment updated.');
            go('class_management/view_class.php?id=' . urlencode($id));

        } catch (PDOException $e) {
            $error = 'Unable to assign tutor.';
        }
    }
}

$pageTitle = 'Assign Tutor';
require __DIR__ . '/../includes/header.php';
?>

<h1>Assign Tutor to Class</h1>

<?php if ($error): ?>
    <p class="error"><?= h($error) ?></p>
<?php endif; ?>

<section class="panel">
    <p><strong>Class ID:</strong>
        <?= h($class['class_id']) ?></p>

    <p><strong>Grade:</strong>
        <?= h($class['grade_level']) ?></p>

    <p><strong>Current Tutor:</strong>
        <?= h($class['staff_name'] ?? 'Not Assigned') ?></p>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="class_id" value="<?= h($id) ?>">

        <label>Select Tutor
            <select name="staff_id">
                <option value="">Not Assigned</option>

                <?php foreach ($staff as $s): ?>
                    <option value="<?= h($s['staff_id']) ?>"
                        <?= $class['class_teacher_id'] === $s['staff_id'] ? 'selected' : '' ?>>
                        <?= h($s['staff_name']) ?>
                        (<?= h($s['staff_role']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <button type="submit">Save Tutor Assignment</button>

        <a href="<?= h(url('class_management/index.php')) ?>">
            Cancel
        </a>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
