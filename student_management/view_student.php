
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin', 'staff', 'student');

$id = trim($_GET['id'] ?? '');

if ($id === '') {
    http_response_code(400);
    exit('Student ID is required.');
}

if (me()['role'] === 'student') {
    $stmt = $pdo->prepare("
        SELECT student_id
        FROM user_accounts
        WHERE user_id = ?
          AND role = 'student'
    ");
    $stmt->execute([me()['id']]);

    if ($stmt->fetchColumn() !== $id) {
        http_response_code(403);
        exit('Access denied.');
    }
}

$stmt = $pdo->prepare("
    SELECT s.*, c.class_name,
           u.username, u.is_active
    FROM students s
    LEFT JOIN classes c ON s.student_class = c.class_id
    LEFT JOIN user_accounts u ON u.student_id = s.student_id
    WHERE s.student_id = ?
");

$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    http_response_code(404);
    exit('Student not found.');
}

$stmt = $pdo->prepare("
    SELECT c.class_id, c.class_name
    FROM student_enrolments e
    JOIN classes c ON e.class_id = c.class_id
    WHERE e.student_id = ?
    ORDER BY c.class_name
");

$stmt->execute([$id]);
$enrolments = $stmt->fetchAll();

$pageTitle = 'View Student';
require __DIR__ . '/../includes/header.php';
?>

<h1>Student Profile</h1>

<section class="panel">

    <p><strong>Student ID:</strong>
        <?= h($student['student_id']) ?></p>

    <p><strong>Name:</strong>
        <?= h($student['student_name']) ?></p>

    <p><strong>Email:</strong>
        <?= h($student['student_email']) ?></p>

    <p><strong>Phone:</strong>
        <?= h($student['student_phone_number']) ?></p>

    <p><strong>Primary Class:</strong>
        <?= h($student['class_name'] ?? 'Not Assigned') ?></p>

    <p><strong>Username:</strong>
        <?= h($student['username'] ?? 'No Login Account') ?></p>

    <p><strong>Account Status:</strong>
        <?php
        if ($student['is_active'] === null) {
            echo 'No Account';
        } else {
            echo $student['is_active'] ? 'Active' : 'Inactive';
        }
        ?>
    </p>

    <h2>Enrolled Classes</h2>

    <?php if (!$enrolments): ?>
        <p>No class enrolments found.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($enrolments as $class): ?>
                <li>
                    <?= h($class['class_id']) ?>
                    - <?= h($class['class_name']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (me()['role'] === 'admin'): ?>
        <a class="button" href="<?= h(url(
            'student_management/edit_student.php?id=' .
            urlencode($id)
        )) ?>">Edit Student</a>
    <?php endif; ?>

    <a href="<?= h(url('student_management/index.php')) ?>">
        Back
    </a>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
