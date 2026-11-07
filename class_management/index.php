
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin', 'staff');

$stmt = $pdo->query("
    SELECT c.*, s.staff_name,
        (SELECT COUNT(*)
         FROM student_enrolments e
         WHERE e.class_id = c.class_id) AS total_students
    FROM classes c
    LEFT JOIN staff s ON s.staff_id = c.class_teacher_id
    ORDER BY c.academic_year DESC, c.grade_level, c.section
");
$classes = $stmt->fetchAll();

$pageTitle = 'Class Management';
require __DIR__ . '/../includes/header.php';
?>

<h1>Class Management</h1>

<?php if (me()['role'] === 'admin'): ?>
    <p>
        <a class="button" href="<?= h(url('class_management/add_class.php')) ?>">
            Add Class
        </a>
    </p>
<?php endif; ?>

<p>
    <a href="<?= h(url('class_management/subjects.php')) ?>">Subjects</a> |
    <a href="<?= h(url('class_management/timetable.php')) ?>">Timetable</a>
</p>

<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th>Class ID</th>
            <th>Grade</th>
            <th>Section</th>
            <th>Academic Year</th>
            <th>Assigned Tutor</th>
            <th>Students</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($classes as $c): ?>
        <tr>
            <td><?= h($c['class_id']) ?></td>
            <td><?= h($c['grade_level']) ?></td>
            <td><?= h($c['section']) ?></td>
            <td><?= h($c['academic_year']) ?></td>
            <td><?= h($c['staff_name'] ?? 'Unassigned') ?></td>
            <td><?= h($c['total_students']) ?></td>
            <td>
                <a href="<?= h(url('class_management/view_class.php?id=' . urlencode($c['class_id']))) ?>">View</a>
                <?php if (me()['role'] === 'admin'): ?>
                    |
                    <a href="<?= h(url('class_management/edit_class.php?id=' . urlencode($c['class_id']))) ?>">Edit</a>
                    |
                    <a href="<?= h(url('class_management/assign_student.php?id=' . urlencode($c['class_id']))) ?>">Students</a>
                    |
                    <a href="<?= h(url('class_management/assign_tutor.php?id=' . urlencode($c['class_id']))) ?>">Tutor</a>
                    |
                    <a href="<?= h(url('class_management/delete_class.php?id=' . urlencode($c['class_id']))) ?>">Delete</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
