<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin', 'staff');

$keyword = trim($_GET['keyword'] ?? '');

$query = "
    SELECT s.*, 
           c.grade_level, 
           c.section,
           u.is_active AS account_active
    FROM students s
    LEFT JOIN classes c ON s.student_class = c.class_id
    LEFT JOIN user_accounts u ON u.student_id = s.student_id
";

$params = [];
if ($keyword !== '') {
    $query .= " WHERE s.student_id LIKE ? 
               OR s.student_name LIKE ? 
               OR s.student_email LIKE ? 
               OR s.student_phone_number LIKE ? 
               OR c.grade_level LIKE ? 
               OR c.section LIKE ?";
    $search = '%' . $keyword . '%';
    $params = [$search, $search, $search, $search, $search, $search];
}

$query .= " ORDER BY s.student_id";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

$pageTitle = 'Student Management';
require __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="margin: 0;">Student Management</h1>
        <p style="margin: 4px 0 0 0;">Manage student information and tuition classes.</p>
    </div>

    <!-- Add Student Button on Top Right (Admin Only) -->
    <?php if (me()['role'] === 'admin'): ?>
        <a href="<?= h(url('student_management/add_student.php')) ?>" class="button">
            + Add Student
        </a>
    <?php endif; ?>
</div>

<!-- Search Form Panel matching Admin Management design -->
<section class="panel" style="margin-bottom: 24px; padding: 20px;">
    <form method="get" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 240px;">
            <label style="margin-bottom: 4px; font-size: 14px;">Search Student</label>
            <input type="text" name="keyword" value="<?= h($keyword) ?>" placeholder="Search by ID, Name, Email, Phone, or Class">
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="submit">Search</button>
            <?php if ($keyword !== ''): ?>
                <a href="<?= h(url('student_management/index.php')) ?>" class="button button-outline" style="display: inline-flex; align-items: center; justify-content: center;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<!-- Students Table Panel -->
<section class="panel">
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
        <?php if (empty($students)): ?>
            <tr>
                <td colspan="7" style="text-align: center; padding: 20px;">No students found.</td>
            </tr>
        <?php else: ?>
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
                            )) ?>" style="color: #FF6B6B;">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>