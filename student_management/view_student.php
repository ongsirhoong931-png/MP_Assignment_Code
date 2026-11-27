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
    SELECT s.*, 
           c.grade_level, 
           c.section,
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
    SELECT c.class_id, c.grade_level, c.section
    FROM student_enrolments e
    JOIN classes c ON e.class_id = c.class_id
    WHERE e.student_id = ?
    ORDER BY c.grade_level, c.section
");

$stmt->execute([$id]);
$enrolments = $stmt->fetchAll();

$pageTitle = 'View Student';
require __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 600px; margin: 0 auto;">
    <div style="margin-bottom: 24px;">
        <h1 style="margin: 0;">Student Profile</h1>
        <p style="margin: 4px 0 0 0;">View student details and class enrolments.</p>
    </div>

    <section class="panel" style="padding: 28px;">
        <p style="margin-bottom: 12px;"><strong>Student ID:</strong><br><?= h($student['student_id']) ?></p>

        <p style="margin-bottom: 12px;"><strong>Name:</strong><br><?= h($student['student_name']) ?></p>

        <p style="margin-bottom: 12px;"><strong>Email:</strong><br><?= h($student['student_email'] ?? '-') ?></p>

        <p style="margin-bottom: 12px;"><strong>Phone:</strong><br><?= h($student['student_phone_number'] ?? '-') ?></p>

        <p style="margin-bottom: 12px;"><strong>Primary Class:</strong><br><?= h(!empty($student['grade_level']) ? $student['grade_level'] . ' - ' . $student['section'] : 'Not Assigned') ?></p>

        <p style="margin-bottom: 12px;"><strong>Username:</strong><br><?= h($student['username'] ?? 'No Login Account') ?></p>

        <p style="margin-bottom: 20px;"><strong>Account Status:</strong><br>
            <?php
            if ($student['is_active'] === null) {
                echo 'No Account';
            } else {
                echo $student['is_active'] ? 'Active' : 'Inactive';
            }
            ?>
        </p>

        <hr style="border: 0; border-top: 1px solid var(--line); margin: 20px 0;">

        <h3 style="margin-bottom: 12px;">Enrolled Classes</h3>

        <?php if (!$enrolments): ?>
            <p style="margin-bottom: 20px;">No class enrolments found.</p>
        <?php else: ?>
            <ul style="margin-bottom: 24px; padding-left: 20px;">
                <?php foreach ($enrolments as $class): ?>
                    <li style="margin-bottom: 6px;">
                        <?= h($class['class_id']) ?> - <?= h($class['grade_level'] . ' - ' . $class['section']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div style="display: flex; gap: 12px; align-items: center; margin-top: 24px;">
            <?php if (me()['role'] === 'admin'): ?>
                <a class="button" style="flex: 1; text-align: center; display: inline-flex; align-items: center; justify-content: center;" href="<?= h(url(
                    'student_management/edit_student.php?id=' .
                    urlencode($id)
                )) ?>">Edit Student</a>
            <?php endif; ?>

            <a href="<?= h(url('student_management/index.php')) ?>" class="button button-outline" style="flex: 1; text-align: center; display: inline-flex; align-items: center; justify-content: center;">
                Back
            </a>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>