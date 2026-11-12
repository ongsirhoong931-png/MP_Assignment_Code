<?php
require_once __DIR__ . '/../includes/attendance_module.php';

allow_roles('admin', 'staff', 'student');

$filters = am_filters_from_get();
$filters['q'] = '';
$filters['status'] = '';
$filters['student_id'] = '';

/* Group by student and class. The role limit is applied by am_where(). */
[$where, $params] = am_where($pdo, $filters);

$stmt = $pdo->prepare(
    "SELECT a.student_id, s.student_name, a.class_id,
            c.grade_level, c.section,
            SUM(a.status = 'Present') AS present,
            SUM(a.status = 'Late') AS late,
            SUM(a.status = 'Absent') AS absent,
            SUM(a.status = 'Excused') AS excused
     FROM attendance_records a
     INNER JOIN students s ON s.student_id = a.student_id
     INNER JOIN classes c ON c.class_id = a.class_id
     WHERE $where
     GROUP BY a.student_id, s.student_name, a.class_id,
              c.grade_level, c.section
     ORDER BY c.grade_level, c.section, s.student_name"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

foreach ($rows as &$r) {
    $r['percent'] = am_percent((int) $r['present'], (int) $r['late'], (int) $r['absent']);
}
unset($r);

/* ---------- CSV download ---------- */
if (($_GET['export'] ?? '') === 'csv') {
    $safe = static function ($v): string {
        $v = (string) $v;
        // Stop spreadsheet programs from running text as a formula.
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
    };

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance_percentage.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student ID', 'Name', 'Class', 'Present', 'Late',
                   'Absent', 'Excused', 'Attendance %']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $safe($r['student_id']), $safe($r['student_name']),
            $safe($r['grade_level'] . ' ' . $r['section']),
            $r['present'], $r['late'], $r['absent'], $r['excused'],
            $r['percent'] === null ? 'N/A' : $r['percent'],
        ]);
    }
    exit;
}

$low = count(array_filter(
    $rows,
    static fn($r) => $r['percent'] !== null && $r['percent'] < AM_LOW_ATTENDANCE
));

$pageTitle = am_is_student() ? 'My Attendance Percentage' : 'Attendance Percentage';
require __DIR__ . '/../includes/header.php';
am_nav('percentage');
?>

<h1><?= h($pageTitle) ?></h1>

<form method="get" class="am-filters">
    <?php if (!am_is_student()): ?>
        <label>Class <?= am_class_select($pdo, 'class_id', $filters['class_id'], true) ?></label>
    <?php endif; ?>
    <label>From
        <input type="date" name="date_from" value="<?= h($filters['date_from']) ?>">
    </label>
    <label>To
        <input type="date" name="date_to" value="<?= h($filters['date_to']) ?>">
    </label>
    <button type="submit">Calculate</button>
    <a href="attendance_percentage.php">Clear</a>
    <?php if ($rows): ?>
        <a href="?<?= h(http_build_query(array_merge(array_filter($filters, 'strlen'), ['export' => 'csv']))) ?>">
            Download CSV</a>
    <?php endif; ?>
</form>

<p class="am-note">
    Percentage = (Present + Late) / (Present + Late + Absent) x 100.
    Excused days are not counted. Below <?= (int) AM_LOW_ATTENDANCE ?>% is
    highlighted<?= $rows ? ' (' . $low . ' below)' : '' ?>.
</p>

<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th>Student</th><th>Class</th><th>Present</th><th>Late</th>
            <th>Absent</th><th>Excused</th><th>Attendance</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="7">No attendance records to calculate yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r):
        $isLow = $r['percent'] !== null && $r['percent'] < AM_LOW_ATTENDANCE;
    ?>
        <tr>
            <td><?= h($r['student_name']) ?>
                <span class="am-note">(<?= h($r['student_id']) ?>)</span></td>
            <td><?= h($r['grade_level'] . ' ' . $r['section']) ?></td>
            <td><?= (int) $r['present'] ?></td>
            <td><?= (int) $r['late'] ?></td>
            <td><?= (int) $r['absent'] ?></td>
            <td><?= (int) $r['excused'] ?></td>
            <td class="<?= $isLow ? 'am-low' : '' ?>">
                <?= h(am_percent_text($r['percent'])) ?>
                <?= $isLow ? ' (low)' : '' ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>