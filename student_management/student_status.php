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

<div style="max-width: 600px; margin: 0 auto;">
    <div style="margin-bottom: 24px;">
        <h1 style="margin: 0;">Student Login Status</h1>
        <p style="margin: 4px 0 0 0;">Manage student account access and login privileges.</p>
    </div>

    <?php foreach ($errors as $error): ?>
        <p class="error" style="text-align: center; background: rgba(255,107,107,0.1); padding: 12px; border-radius: 8px; margin-bottom: 16px; color: #ff6b6b;"><?= h($error) ?></p>
    <?php endforeach; ?>

    <section class="panel" style="padding: 28px;">

        <p style="margin-bottom: 16px;"><strong>Student ID:</strong><br><?= h($student['student_id']) ?></p>

        <p style="margin-bottom: 16px;"><strong>Student Name:</strong><br><?= h($student['student_name']) ?></p>

        <p style="margin-bottom: 16px;"><strong>Username:</strong><br><?= h($student['username'] ?? 'No Account') ?></p>

        <p style="margin-bottom: 24px;"><strong>Current Login Status:</strong><br>
            <?php
            if ($student['user_id'] === null) {
                echo '<span style="color: #888;">No Account</span>';
            } elseif ($student['is_active']) {
                echo '<span style="color: #51cf66;">Active</span>';
            } else {
                echo '<span style="color: #ff6b6b;">Inactive</span>';
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

                <label style="margin-bottom: 20px; display: block;">Change Login Status
                    <select name="status" required style="margin-top: 6px;">
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

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" style="flex: 1;">
                        Update Status
                    </button>
                    <a href="<?= h(url('student_management/view_student.php?id=' . urlencode($id))) ?>" class="button button-outline" style="flex: 1; text-align: center; display: inline-flex; align-items: center; justify-content: center;">
                        Cancel
                    </a>
                </div>

            </form>

        <?php else: ?>

            <p style="margin-bottom: 24px; opacity: 0.9;">
                Create a login account for this student
                through User Management before changing
                the account status.
            </p>

            <a href="<?= h(url('student_management/view_student.php?id=' . urlencode($id))) ?>" class="button button-outline" style="width: 100%; text-align: center; display: inline-flex; align-items: center; justify-content: center;">
                Back to Student Profile
            </a>

        <?php endif; ?>

    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>