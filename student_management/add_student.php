
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$errors = [];

$classes = $pdo->query("
    SELECT class_id, class_name
    FROM classes
    ORDER BY class_name
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $studentId = trim($_POST['student_id'] ?? '');
    $name = trim($_POST['student_name'] ?? '');
    $email = trim($_POST['student_email'] ?? '');
    $phone = trim($_POST['student_phone_number'] ?? '');
    $classId = trim($_POST['student_class'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_-]{1,12}$/', $studentId)) {
        $errors[] = 'Enter a valid Student ID (maximum 12 characters).';
    }

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
            $errors[] = 'Selected class is invalid.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO students
                (student_id, student_name, student_email,
                 student_phone_number, student_class)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $studentId,
                $name,
                $email ?: null,
                $phone ?: null,
                $classId ?: null
            ]);

            if ($classId !== '') {
                $stmt = $pdo->prepare("
                    INSERT INTO student_enrolments
                    (student_id, class_id)
                    VALUES (?, ?)
                ");

                $stmt->execute([$studentId, $classId]);
            }

            $pdo->commit();

            notice('Student added successfully.');
            go('student_management/index.php');

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = 'Unable to add student. Check whether the Student ID or email already exists.';
        }
    }
}

$pageTitle = 'Add Student';
require __DIR__ . '/../includes/header.php';
?>

<h1>Add New Student</h1>

<?php foreach ($errors as $error): ?>
    <p class="error"><?= h($error) ?></p>
<?php endforeach; ?>

<section class="panel">
<form method="post">

    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">

    <label>Student ID
        <input type="text" name="student_id"
               maxlength="12"
               value="<?= h($_POST['student_id'] ?? '') ?>"
               placeholder="STU011" required>
    </label>

    <label>Student Name
        <input type="text" name="student_name"
               maxlength="120"
               value="<?= h($_POST['student_name'] ?? '') ?>"
               required>
    </label>

    <label>Email
        <input type="email" name="student_email"
               maxlength="180"
               value="<?= h($_POST['student_email'] ?? '') ?>">
    </label>

    <label>Phone Number
        <input type="text" name="student_phone_number"
               maxlength="30"
               value="<?= h($_POST['student_phone_number'] ?? '') ?>">
    </label>

    <label>Assign Class
        <select name="student_class">
            <option value="">Not Assigned</option>

            <?php foreach ($classes as $class): ?>
                <option value="<?= h($class['class_id']) ?>"
                    <?= ($_POST['student_class'] ?? '') ===
                        $class['class_id'] ? 'selected' : '' ?>>
                    <?= h($class['class_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <button type="submit">Save Student</button>

    <a href="<?= h(url('student_management/index.php')) ?>">
        Cancel
    </a>
</form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
