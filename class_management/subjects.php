
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin', 'staff');

$subjects = $pdo->query("
    SELECT * FROM subjects
    ORDER BY subject_name
")->fetchAll();

$pageTitle = 'Subject Management';
require __DIR__ . '/../includes/header.php';
?>

<h1>Subject Management</h1>

<?php if (me()['role'] === 'admin'): ?>
    <p>
        <a class="button" href="<?= h(url('class_management/add_subject.php')) ?>">
            Add Subject
        </a>
    </p>
<?php endif; ?>

<div class="table-wrap">
<table>
    <tr>
        <th>Subject ID</th>
        <th>Subject Code</th>
        <th>Subject Name</th>
        <th>Department</th>
        <th>Actions</th>
    </tr>

    <?php foreach ($subjects as $s): ?>
        <tr>
            <td><?= h($s['subject_id']) ?></td>
            <td><?= h($s['subject_code']) ?></td>
            <td><?= h($s['subject_name']) ?></td>
            <td><?= h($s['department']) ?></td>
            <td>
                <?php if (me()['role'] === 'admin'): ?>
                    <a href="<?= h(url('class_management/edit_subject.php?id=' . urlencode($s['subject_id']))) ?>">Edit</a>
                    |
                    <a href="<?= h(url('class_management/delete_subject.php?id=' . urlencode($s['subject_id']))) ?>">Delete</a>
                <?php else: ?>
                    View Only
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
</div>

<p><a href="<?= h(url('class_management/index.php')) ?>">Back</a></p>

<?php require __DIR__ . '/../includes/footer.php'; ?>
