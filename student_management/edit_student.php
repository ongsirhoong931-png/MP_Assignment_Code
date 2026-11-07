
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
    SELECT * FROM students WHERE student_id = ?
");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    http_response_code(404);
    exit('Student not found.');
}

$classes = $pdo->query("
    SELECT class_id, class_name
    FROM classes
    ORDER BY class_name
")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $name = trim($_POST['student_name'] ?? '');
    $email = trim($_POST['student_email'] ?? '');
    $phone = trim($_POST['student_phone_number'] ?? '');
    $classId = trim($_POST['student_class'] ?? '');

    if ($name === '' || strlen($name) > 120) {
        $errors[] = 'Enter a valid student name.';
    }

    if ($email !== '' &&
        (!filter_var($email, FILTER_VALIDATE_EMAIL) ||
         strlen($email) > 180)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (strlen($phone) > 30) {
        $errors[] = 'Phone number is too long.';
    }

    if ($classId !== '') {
        $validClass = false;

        foreach ($classes as $class) {
            if ($class['class_id'] === $classId) {
                $validClass = true;
                break;
            }
        }

        if (!$validClass) {
            $errors[] = 'Invalid class selected.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE students
                SET student_name = ?,
                    student_email = ?,
                    student_phone_number = ?,
                    student_class = ?
                WHERE student_id = ?
            ");

            $stmt->execute([
                $name,
                $email ?: null,
                $phone ?: null,
                $classId ?: null,
                $id
            ]);

            if ($classId !== '') {
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO student_enrolments
                    (student_id, class_id)
                    VALUES (?, ?)
                ");

                $stmt->execute([$id, $classId]);
            }

            $pdo->commit();

            notice('Student updated successfully.');
            go('student_management/view_student.php?id=' .
                urlencode($id));

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = 'Unable to update student. The email may already be in use.';
        }
    }

    $student['student_name'] = $name;
    $student['student_email'] = $email;
    $student['student_phone_number'] = $phone;
    $student['student_class'] = $classId;
}

$pageTitle = 'Edit Student';
require __DIR__ . '/../includes/header.php';
?>

<h1>Edit Student</h1>

<?php foreach ($errors as $error): ?>
    <p class="error"><?= h($error) ?></p>
<?php endforeach; ?>

<section class="panel">
<form method="post">

    <input type="hidden" name="csrf"
           value="<?= h(csrf()) ?>">

    <input type="hidden" name="student_id"
           value="<?= h($id) ?>">

    <label>Student ID
        <input type="text"
               value="<?= h($id) ?>" disabled>
    </label>

    <label>Student Name
        <input type="text" name="student_name"
               maxlength="120"
               value="<?= h($student['student_name']) ?>"
               required>
    </label>

    <label>Email
        <input type="email" name="student_email"
               maxlength="180"
               value="<?= h($student['student_email']) ?>">
    </label>

    <label>Phone Number
        <input type="text" name="student_phone_number"
               maxlength="30"
               value="<?= h($student['student_phone_number']) ?>">
    </label>

    <label>Primary Class
        <select name="student_class">
            <option value="">Not Assigned</option>

            <?php foreach ($classes as $class): ?>
                <option value="<?= h($class['class_id']) ?>"
                    <?= $student['student_class'] ===
                        $class['class_id'] ? 'selected' : '' ?>>
                    <?= h($class['class_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <button type="submit">Update Student</button>

    <a href="<?= h(url(
        'student_management/view_student.php?id=' .
        urlencode($id)
    )) ?>">Cancel</a>
</form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
