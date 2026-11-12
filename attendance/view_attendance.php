<?php
require_once __DIR__ . '/../includes/attendance_module.php';

allow_roles('admin', 'staff', 'student');

$filters = am_filters_from_get();
$filters['q'] = '';
$page = max(1, (int) ($_GET['page'] ?? 1));

$total = am_records_count($pdo, $filters);
$rows = am_records($pdo, $filters, AM_PAGE_SIZE, ($page - 1) * AM_PAGE_SIZE);
$canManage = !am_is_student();

$pageTitle = am_is_student() ? 'My Attendance Records' : 'Attendance Records';
require __DIR__ . '/../includes/header.php';
am_nav('view');
?>

<h1><?= h($pageTitle) ?></h1>

<form method="get" class="am-filters">
    <label>Class <?= am_class_select($pdo, 'class_id', $filters['class_id'], true) ?></label>
    <label>Status <?= am_status_select('status', $filters['status'], true) ?></label>
    <label>From
        <input type="date" name="date_from" value="<?= h($filters['date_from']) ?>">
    </label>
    <label>To
        <input type="date" name="date_to" value="<?= h($filters['date_to']) ?>">
    </label>
    <button type="submit">Filter</button>
    <a href="view_attendance.php">Clear</a>
</form>

<p class="am-note"><?= (int) $total ?> record(s) found.</p>

<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Student</th>
            <th>Class</th>
            <th>Subject</th>
            <th>Status</th>
            <?php if ($canManage): ?><th>Actions</th><?php endif; ?>
        </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="<?= $canManage ? 6 : 5 ?>">No attendance records found.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= h($r['date']) ?></td>
            <td><?= h($r['student_name']) ?>
                <span class="am-note">(<?= h($r['student_id']) ?>)</span></td>
            <td><?= h($r['grade_level'] . ' ' . $r['section']) ?></td>
            <td><?= h($r['subject_name'] ?? '-') ?></td>
            <td><?= am_badge($r['status']) ?></td>
            <?php if ($canManage): ?>
            <td>
                <a href="<?= h(url('attendance/edit_attendance.php?id=' . urlencode($r['attendance_id']))) ?>">Edit</a>
                |
                <a href="<?= h(url('attendance/delete_attendance.php?id=' . urlencode($r['attendance_id']))) ?>">Delete</a>
            </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?= am_pager($total, $page, array_filter($filters, 'strlen')) ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>