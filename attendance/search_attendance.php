<?php
require_once __DIR__ . '/../includes/attendance_module.php';

allow_roles('admin', 'staff', 'student');

$filters = am_filters_from_get();
$page = max(1, (int) ($_GET['page'] ?? 1));

// Only run the search once the form has been submitted.
$searched = ($_GET !== []) && array_filter(
    array_diff_key($filters, ['student_id' => 1]),
    'strlen'
) !== [];

$total = 0;
$rows = [];

if ($searched) {
    $total = am_records_count($pdo, $filters);
    $rows = am_records($pdo, $filters, AM_PAGE_SIZE, ($page - 1) * AM_PAGE_SIZE);
}

$canManage = !am_is_student();

$pageTitle = 'Search Attendance Records';
require __DIR__ . '/../includes/header.php';
am_nav('search');
?>

<h1>Search Attendance Records</h1>

<form method="get" class="am-filters">
    <label>Student name, student ID or record ID
        <input type="search" name="q" value="<?= h($filters['q']) ?>"
               maxlength="80" placeholder="e.g. STU001 or Aisyah">
    </label>
    <label>Class <?= am_class_select($pdo, 'class_id', $filters['class_id'], true) ?></label>
    <label>Status <?= am_status_select('status', $filters['status'], true) ?></label>
    <label>From
        <input type="date" name="date_from" value="<?= h($filters['date_from']) ?>">
    </label>
    <label>To
        <input type="date" name="date_to" value="<?= h($filters['date_to']) ?>">
    </label>
    <button type="submit">Search</button>
    <a href="search_attendance.php">Clear</a>
</form>

<?php if (!$searched): ?>
    <p class="am-note">Enter a keyword or pick a filter, then press Search.</p>
<?php else: ?>
    <p class="am-note"><?= (int) $total ?> record(s) found.</p>

    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Record ID</th>
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
            <tr><td colspan="<?= $canManage ? 7 : 6 ?>">No matching records.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= h($r['attendance_id']) ?></td>
                <td><?= h($r['date']) ?></td>
                <td>
                    <?php if ($canManage): ?>
                        <a href="<?= h(url('attendance/attendance_history.php?student_id=' . urlencode($r['student_id']))) ?>">
                            <?= h($r['student_name']) ?></a>
                    <?php else: ?>
                        <?= h($r['student_name']) ?>
                    <?php endif; ?>
                    <span class="am-note">(<?= h($r['student_id']) ?>)</span>
                </td>
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
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>