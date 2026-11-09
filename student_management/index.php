<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin', 'staff');

$stmt = $pdo->query("
    SELECT s.*, 
           c.grade_level, 
           c.section,
           u.is_active AS account_active
    FROM students s
    LEFT JOIN classes c ON s.student_class = c.class_id
    LEFT JOIN user_accounts u ON u.student_id = s.student_id
    ORDER BY s.student_id
");

$students = $stmt->fetchAll();

$pageTitle = 'Student Management';
require __DIR__ . '/../includes/header.php';
?>

<h1>Student Management</h1>

<p>Manage student information and tuition classes.</p>

<?php if (me()['role'] === 'admin'): ?>
    <a class="button"
       href="<?= h(url('student_management/add_student.php')) ?>">
        Add Student
    </a>
<?php endif; ?>

<a class="button"
   href="<?= h(url('student_management/search_student.php')) ?>">
    Search Student
</a>

<br><br>

<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th>Student ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Class</th>
            <th>Login Status</th>
            <th>Action</th>
        </tr>
    </thead>

    <tbody>
    <?php foreach ($students as $student): ?>
        <tr>
            <td><?= h($student['student_id']) ?></td>
            <td><?= h($student['student_name']) ?></td>
            <td><?= h($student['student_email']) ?></td>
            <td><?= h($student['student_phone_number']) ?></td>
            <td>
                <?php 
                if (!empty($student['grade_level'])) {
                    echo h($student['grade_level'] . ' - ' . $student['section']);
                } else {
                    echo 'Not Assigned';
                }
                ?>
            </td>

            <td>
                <?php
                if ($student['account_active'] === null) {
                    echo 'No Account';
                } elseif ($student['account_active']) {
                    echo 'Active';
                } else {
                    echo 'Inactive';
                }
                ?>
            </td>

            <td>
                <a href="<?= h(url(
                    'student_management/view_student.php?id=' .
                    urlencode($student['student_id'])
                )) ?>">View</a>

                <?php if (me()['role'] === 'admin'): ?>
                    |
                    <a href="<?= h(url(
                        'student_management/edit_student.php?id=' .
                        urlencode($student['student_id'])
                    )) ?>">Edit</a>

                    |
                    <a href="<?= h(url(
                        'student_management/student_status.php?id=' .
                        urlencode($student['student_id'])
                    )) ?>">Status</a>

                    |
                    <a href="<?= h(url(
                        'student_management/delete_student.php?id=' .
                        urlencode($student['student_id'])
                    )) ?>">Delete</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>