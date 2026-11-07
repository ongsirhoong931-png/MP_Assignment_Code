
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
    SELECT s.student_id,
           s.student_name,
           u.user_id,
           u.username,
           u.is_active
    FROM students s
    LEFT JOIN user_accounts u
        ON u.student_id = s.student_id
    WHERE s.student_id = ?
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

    $status = $_POST['status'] ?? '';

    if ($student['user_id'] === null) {
        $errors[] = 'This student does not have a login account.';
    } elseif (!in_array($status, ['0', '1'], true)) {
        $errors[] = 'Invalid account status.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("
            UPDATE user_accounts
            SET is_active = ?
            WHERE user_id = ?
              AND role = 'student'
              AND student_id = ?
        ");

        $stmt->execute([
            (int)$status,
            $student['user_id'],
            $id
        ]);

        notice('Student login status updated successfully.');

        go('student_management/view_student.php?id=' .
            urlencode($id));
    }
}

$pageTitle = 'Student Account Status';
require __DIR__ . '/../includes/header.php';
?>

<h1>Student Login Status</h1>

<?php foreach ($errors as $error): ?>
    <p class="error"><?= h($error) ?></p>
<?php endforeach; ?>

<section class="panel">

    <p><strong>Student ID:</strong>
        <?= h($student['student_id']) ?></p>

    <p><strong>Student Name:</strong>
        <?= h($student['student_name']) ?></p>

    <p><strong>Username:</strong>
        <?= h($student['username'] ?? 'No Account') ?></p>

    <p><strong>Current Login Status:</strong>

        <?php
        if ($student['user_id'] === null) {
            echo 'No Account';
        } elseif ($student['is_active']) {
            echo 'Active';
        } else {
            echo 'Inactive';
        }
        ?>
    </p>

    <?php if ($student['user_id'] !== null): ?>

        <form method="post">

            <input type="hidden"
                   name="csrf"
                   value="<?= h(csrf()) ?>">

            <input type="hidden"
                   name="student_id"
                   value="<?= h($id) ?>">

            <label>Change Login Status
                <select name="status" required>
                    <option value="1"
                        <?= $student['is_active'] ? 'selected' : '' ?>>
                        Active
                    </option>

                    <option value="0"
                        <?= !$student['is_active'] ? 'selected' : '' ?>>
                        Inactive
                    </option>
                </select>
            </label>

            <button type="submit">
                Update Status
            </button>

        </form>

    <?php else: ?>

        <p>
            Create a login account for this student
            through User Management before changing
            the account status.
        </p>

    <?php endif; ?>

    <br>

    <a href="<?= h(url(
        'student_management/index.php'
    )) ?>">Back to Student Management</a>

</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
