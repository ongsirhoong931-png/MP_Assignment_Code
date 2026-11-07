
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin', 'staff', 'student');

$studentId = trim($_GET['student_id'] ?? '');

if (me()['role'] === 'student') {
    $stmt = $pdo->prepare("
        SELECT student_id
        FROM user_accounts
        WHERE user_id = ? AND role = 'student'
    ");
    $stmt->execute([me()['id']]);
    $studentId = (string)$stmt->fetchColumn();
}

$student = null;
$subjects = [];

if ($studentId !== '') {
    $stmt = $pdo->prepare("
        SELECT student_id, student_name
        FROM students WHERE student_id = ?
    ");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if ($student) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                sub.subject_id,
                sub.subject_name,
                sub.subject_code,
                c.class_id,
                c.grade_level,
                c.section
            FROM student_enrolments e
            JOIN classes c ON c.class_id = e.class_id
            JOIN timetable_schedules t ON t.class_id = c.class_id
            JOIN subjects sub ON sub.subject_id = t.subject_id
            WHERE e.student_id = ?
            ORDER BY sub.subject_name, c.class_id
        ");
        $stmt->execute([$studentId]);
        $subjects = $stmt->fetchAll();
    }
}

$pageTitle = 'Student Subjects';
require __DIR__ . '/../includes/header.php';
?>

<h1>Student Subject Enrolment</h1>

<?php if (me()['role'] !== 'student'): ?>
<section class="panel">
    <form method="get">
        <label>Student ID
            <input name="student_id" maxlength="12"
                   value="<?= h($studentId) ?>" required>
        </label>
        <button type="submit">View Subjects</button>
    </form>
</section>
<?php endif; ?>

<?php if ($student): ?>
    <h2><?= h($student['student_name']) ?></h2>

    <div class="table-wrap">
    <table>
        <tr>
            <th>Subject Code</th>
            <th>Subject Name</th>
            <th>Class ID</th>
            <th>Grade</th>
            <th>Section</th>
        </tr>

        <?php foreach ($subjects as $subject): ?>
            <tr>
                <td><?= h($subject['subject_code']) ?></td>
                <td><?= h($subject['subject_name']) ?></td>
                <td><?= h($subject['class_id']) ?></td>
                <td><?= h($subject['grade_level']) ?></td>
                <td><?= h($subject['section']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    </div>

    <?php if (!$subjects): ?>
        <p>No scheduled subjects found for this student's classes.</p>
    <?php endif; ?>

<?php elseif ($studentId !== ''): ?>
    <p>Student not found.</p>
<?php endif; ?>

<p>
    Subject enrolment is determined by class enrolment.
    Administrators can assign students using the
    Class Management page.
</p>

<?php require __DIR__ . '/../includes/footer.php'; ?>
