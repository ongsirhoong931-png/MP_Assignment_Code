
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin', 'staff');

$id = trim($_GET['id'] ?? '');

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

$stmt = $pdo->prepare("
    SELECT s.student_id, s.student_name
    FROM student_enrolments e
    JOIN students s ON s.student_id = e.student_id
    WHERE e.class_id = ?
    ORDER BY s.student_name
");
$stmt->execute([$id]);
$students = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT t.*, sub.subject_name, st.staff_name
    FROM timetable_schedules t
    JOIN subjects sub ON sub.subject_id = t.subject_id
    LEFT JOIN staff st ON st.staff_id = t.assigned_staff_id
    WHERE t.class_id = ?
    ORDER BY FIELD(t.day_of_week,
        'Monday','Tuesday','Wednesday','Thursday',
        'Friday','Saturday','Sunday'), t.start_time
");
$stmt->execute([$id]);
$schedules = $stmt->fetchAll();

$pageTitle = 'View Class';
require __DIR__ . '/../includes/header.php';
?>

<h1>Class Details</h1>

<section class="panel">
    <p><strong>Class ID:</strong> <?= h($class['class_id']) ?></p>
    <p><strong>Grade:</strong> <?= h($class['grade_level']) ?></p>
    <p><strong>Section:</strong> <?= h($class['section']) ?></p>
    <p><strong>Academic Year:</strong> <?= h($class['academic_year']) ?></p>
    <p><strong>Class Tutor:</strong>
        <?= h($class['staff_name'] ?? 'Not Assigned') ?></p>
</section>

<h2>Enrolled Students</h2>
<div class="table-wrap">
<table>
    <tr><th>Student ID</th><th>Name</th></tr>
    <?php foreach ($students as $student): ?>
        <tr>
            <td><?= h($student['student_id']) ?></td>
            <td><?= h($student['student_name']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
</div>

<h2>Class Timetable</h2>
<div class="table-wrap">
<table>
    <tr>
        <th>Subject</th><th>Day</th><th>Time</th>
        <th>Room</th><th>Tutor</th>
    </tr>
    <?php foreach ($schedules as $s): ?>
        <tr>
            <td><?= h($s['subject_name']) ?></td>
            <td><?= h($s['day_of_week']) ?></td>
            <td><?= h(substr($s['start_time'], 0, 5)) ?>
                - <?= h(substr($s['end_time'], 0, 5)) ?></td>
            <td><?= h($s['room_number']) ?></td>
            <td><?= h($s['staff_name'] ?? 'Unassigned') ?></td>
        </tr>
    <?php endforeach; ?>
</table>
</div>

<p>
    <a href="<?= h(url('class_management/index.php')) ?>">Back to Classes</a>
</p>

<?php require __DIR__ . '/../includes/footer.php'; ?>
