
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin', 'staff');

$keyword = trim($_GET['keyword'] ?? '');
$students = [];

if ($keyword !== '') {
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            s.student_id,
            s.student_name,
            s.student_email,
            s.student_phone_number,
            c.class_name
        FROM students s
        LEFT JOIN classes c
            ON s.student_class = c.class_id
        LEFT JOIN student_enrolments e
            ON e.student_id = s.student_id
        LEFT JOIN classes ec
            ON ec.class_id = e.class_id
        WHERE s.student_id LIKE ?
           OR s.student_name LIKE ?
           OR s.student_email LIKE ?
           OR c.class_name LIKE ?
           OR ec.class_name LIKE ?
        ORDER BY s.student_name
    ");

    $search = '%' . $keyword . '%';

    $stmt->execute([
        $search,
        $search,
        $search,
        $search,
        $search
    ]);

    $students = $stmt->fetchAll();
}

$pageTitle = 'Search Student';
require __DIR__ . '/../includes/header.php';
?>

<h1>Search Student</h1>

<section class="panel">
    <form method="get">

        <label>Search by Student ID, Name, Email or Class
            <input type="text"
                   name="keyword"
                   placeholder="Enter student information"
                   value="<?= h($keyword) ?>"
                   maxlength="180"
                   required>
        </label>

        <button type="submit">Search</button>

        <a href="<?= h(url(
            'student_management/index.php'
        )) ?>">Back</a>

    </form>
</section>

<br>

<?php if ($keyword !== ''): ?>

    <h2>Search Results</h2>

    <?php if (!$students): ?>
        <p>No students found.</p>
    <?php else: ?>

        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Primary Class</th>
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
                        <?= h($student['class_name'] ?? 'Not Assigned') ?>
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
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

    <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
