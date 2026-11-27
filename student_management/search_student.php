<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$keyword = trim($_GET['keyword'] ?? '');
$students = [];

if ($keyword !== '') {
    $stmt = $pdo->prepare("
        SELECT *
        FROM students
        WHERE student_id LIKE ?
           OR student_name LIKE ?
           OR student_email LIKE ?
           OR student_phone_number LIKE ?
           OR student_class LIKE ?
        ORDER BY student_name
    ");

    $search = '%' . $keyword . '%';
    $stmt->execute([$search, $search, $search, $search, $search]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $classesMap = [];
    try {
        $rawClasses = $pdo->query("SELECT * FROM classes")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rawClasses as $rc) {
            $cId = $rc['class_id'] ?? array_values($rc)[0];
            $cName = $rc['class_name'] ?? $rc['name'] ?? $rc['title'] ?? $rc['class_title'] ?? $rc['description'] ?? array_values($rc)[1] ?? 'Assigned Class';
            $classesMap[$cId] = $cName;
        }
    } catch (Exception $e) {}

    foreach ($students as &$stu) {
        $clsId = $stu['student_class'] ?? '';
        $stu['class_name'] = $classesMap[$clsId] ?? ($clsId !== '' ? 'Class ID: ' . $clsId : 'Not Assigned');
    }
    unset($stu);
}

$pageTitle = 'Search Student';
require __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="margin: 0;">Search Student</h1>
        <p style="margin: 4px 0 0 0;">Find student profiles by ID, name, email, or class.</p>
    </div>
    <a href="<?= h(url('student_management/index.php')) ?>" class="button button-outline">
        Back to Students
    </a>
</div>

<!-- Search Panel Styled Like Admin Management -->
<section class="panel" style="margin-bottom: 24px; padding: 20px;">
    <form method="get" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 240px;">
            <label style="margin-bottom: 4px; font-size: 14px;">Search Student Information</label>
            <input type="text" name="keyword" value="<?= h($keyword) ?>" placeholder="Search by ID, Name, Email, Phone..." required>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="submit">Search</button>
            <?php if ($keyword !== ''): ?>
                <a href="<?= h(url('student_management/search_student.php')) ?>" class="button button-outline" style="display: inline-flex; align-items: center; justify-content: center;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<?php if ($keyword !== ''): ?>
    <section class="panel">
        <h2 style="font-size: 1.4rem; margin-bottom: 16px;">Search Results</h2>

        <?php if (!$students): ?>
            <p>No students found matching "<?= h($keyword) ?>".</p>
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
                            <?= h($student['class_name']) ?>
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
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>