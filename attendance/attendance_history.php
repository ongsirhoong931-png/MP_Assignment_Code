<?php
require_once __DIR__ . '/../includes/attendance_module.php';

allow_roles('admin', 'staff', 'student');

$page = max(1, (int) ($_GET['page'] ?? 1));

/* Students the user may look up. */
$choices = [];

if (am_is_student()) {
    $studentId = am_student_id($pdo);
} else {
    if (am_is_admin()) {
        $stmt = $pdo->query(
            'SELECT student_id, student_name FROM students
             ORDER BY student_name'
        );
    } else {
        // Students enrolled in my classes, or whose own row names one of them.
        $ids = array_keys(am_classes($pdo));
        $marks = $ids ? implode(',', array_fill(0, count($ids), '?')) : "''";
        $stmt = $pdo->prepare(
            "SELECT DISTINCT s.student_id, s.student_name
             FROM students s
             WHERE s.student_class IN ($marks)
                OR s.student_id IN (
                    SELECT e.student_id FROM student_enrolments e
                    WHERE e.class_id IN ($marks)
                )
             ORDER BY s.student_name"
        );
        $stmt->execute(array_merge($ids, $ids));
    }

    $choices = $stmt->fetchAll();
    $studentId = trim((string) ($_GET['student_id'] ?? ''));

    if ($studentId !== '' && !in_array($studentId, array_column($choices, 'student_id'), true)) {
        am_flash('That student was not found in your classes.', 'error');
        go('attendance/attendance_history.php');
    }
}

$student = null;
$rows = [];
$total = 0;
$totals = array_fill_keys(AM_STATUSES, 0);
$perClass = [];

if ($studentId !== '') {
    $stmt = $pdo->prepare(
        'SELECT student_id, student_name FROM students WHERE student_id = ?'
    );
    $stmt->execute([$studentId]);
    $student = $stmt->fetch() ?: null;
}

if ($student) {
    $filters = ['student_id' => $studentId];
    $total = am_records_count($pdo, $filters);
    $rows = am_records($pdo, $filters, AM_PAGE_SIZE, ($page - 1) * AM_PAGE_SIZE);

    // Totals and per-class percentage (limited to what the user may see).
    [$where, $params] = am_where($pdo, $filters);
    $stmt = $pdo->prepare(
        "SELECT a.class_id, c.grade_level, c.section, a.status, COUNT(*) AS n"
        . AM_FROM
        . " WHERE $where
            GROUP BY a.class_id, c.grade_level, c.section, a.status"
    );
    $stmt->execute($params);

    foreach ($stmt->fetchAll() as $r) {
        $totals[$r['status']] += (int) $r['n'];

        $key = $r['class_id'];
        $perClass[$key] ??= [
            'label' => $r['grade_level'] . ' ' . $r['section'],
            'Present' => 0, 'Late' => 0, 'Absent' => 0, 'Excused' => 0,
        ];
        $perClass[$key][$r['status']] += (int) $r['n'];
    }
}

$overall = am_percent($totals['Present'], $totals['Late'], $totals['Absent']);

$pageTitle = am_is_student() ? 'My Attendance History' : 'Student Attendance History';
require __DIR__ . '/../includes/header.php';
am_nav('history');
?>

<h1><?= h($pageTitle) ?></h1>

<?php if (!am_is_student()): ?>
<form method="get" class="am-filters">
    <label>Student
        <select name="student_id">
            <option value="">Select a student</option>
            <?php foreach ($choices as $c): ?>
                <option value="<?= h($c['student_id']) ?>"
                    <?= $c['student_id'] === $studentId ? 'selected' : '' ?>>
                    <?= h($c['student_id'] . ' - ' . $c['student_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit">Show history</button>
</form>
<?php endif; ?>

<?php if ($student): ?>
    <h2><?= h($student['student_name']) ?> (<?= h($student['student_id']) ?>)</h2>

    <div class="am-summary">
        <?php foreach ($totals as $status => $n): ?>
            <div><b><?= (int) $n ?></b><?= h($status) ?></div>
        <?php endforeach; ?>
        <div>
            <b class="<?= ($overall !== null && $overall < AM_LOW_ATTENDANCE) ? 'am-low' : '' ?>">
                <?= h(am_percent_text($overall)) ?>
            </b>
            Attendance
        </div>
    </div>

    <?php if ($perClass): ?>
    <h2>By class</h2>
    <div class="table-wrap">
    <table>
        <thead>
            <tr><th>Class</th><th>Present</th><th>Late</th>
                <th>Absent</th><th>Excused</th><th>Percentage</th></tr>
        </thead>
        <tbody>
        <?php foreach ($perClass as $c):
            $p = am_percent($c['Present'], $c['Late'], $c['Absent']);
        ?>
            <tr>
                <td><?= h($c['label']) ?></td>
                <td><?= (int) $c['Present'] ?></td>
                <td><?= (int) $c['Late'] ?></td>
                <td><?= (int) $c['Absent'] ?></td>
                <td><?= (int) $c['Excused'] ?></td>
                <td class="<?= ($p !== null && $p < AM_LOW_ATTENDANCE) ? 'am-low' : '' ?>">
                    <?= h(am_percent_text($p)) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <h2>All records</h2>
    <div class="table-wrap">
    <table>
        <thead>
            <tr><th>Date</th><th>Class</th><th>Subject</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="4">No attendance has been recorded yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= h($r['date']) ?></td>
                <td><?= h($r['grade_level'] . ' ' . $r['section']) ?></td>
                <td><?= h($r['subject_name'] ?? '-') ?></td>
                <td><?= am_badge($r['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?= am_pager($total, $page, ['student_id' => $studentId]) ?>
<?php elseif (am_is_student()): ?>
    <p>Your account is not linked to a student record yet.</p>
<?php else: ?>
    <p class="am-note">Choose a student to see their attendance history.</p>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>