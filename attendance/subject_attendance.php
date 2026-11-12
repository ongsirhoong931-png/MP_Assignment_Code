<?php
require_once __DIR__ . '/../includes/attendance_module.php';

allow_roles('admin', 'staff');

$classId = trim((string) ($_GET['class_id'] ?? $_POST['class_id'] ?? ''));
$scheduleId = trim((string) ($_GET['schedule_id'] ?? $_POST['schedule_id'] ?? ''));
$dateInput = am_parse_date($_GET['date'] ?? $_POST['date'] ?? '');

if ($classId !== '' && !am_can_manage_class($pdo, $classId)) {
    $classId = '';
    $scheduleId = '';
}

/** Timetable subjects of the class the user may record. */
function am_subject_schedules(PDO $pdo, string $classId): array
{
    $sql = 'SELECT ts.schedule_id, ts.day_of_week, ts.start_time,
                   ts.end_time, sub.subject_name, sub.subject_code,
                   t.staff_name
            FROM timetable_schedules ts
            INNER JOIN subjects sub ON sub.subject_id = ts.subject_id
            INNER JOIN classes c ON c.class_id = ts.class_id
            LEFT JOIN staff t ON t.staff_id = ts.assigned_staff_id
            WHERE ts.class_id = ?';
    $params = [$classId];

    if (am_is_staff()) {
        $sql .= ' AND (ts.assigned_staff_id = ? OR c.class_teacher_id = ?)';
        $params[] = am_staff_id($pdo);
        $params[] = am_staff_id($pdo);
    }

    $stmt = $pdo->prepare(
        $sql . " ORDER BY FIELD(ts.day_of_week,'Monday','Tuesday',
                 'Wednesday','Thursday','Friday','Saturday','Sunday'),
                 ts.start_time"
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

$schedules = $classId !== '' ? am_subject_schedules($pdo, $classId) : [];
$schedule = null;

foreach ($schedules as $s) {
    if ($s['schedule_id'] === $scheduleId) {
        $schedule = $s;
    }
}

if (!$schedule) {
    $scheduleId = '';
}

/** Most recent date (today or earlier) that falls on the subject's weekday. */
function am_last_weekday(string $dayName): string
{
    for ($i = 0; $i < 7; $i++) {
        $d = strtotime("-$i day");
        if (date('l', $d) === $dayName) {
            return date('Y-m-d', $d);
        }
    }

    return am_today();
}

$date = $dateInput
    ?? ($schedule ? am_last_weekday($schedule['day_of_week']) : am_today());

/** Error for the chosen subject/date, or null. */
function am_subject_check(PDO $pdo, string $classId, ?array $schedule, string $date): ?string
{
    if (!$schedule) {
        return 'Please choose a subject.';
    }

    if (date('l', strtotime($date)) !== $schedule['day_of_week']) {
        return $schedule['subject_name'] . ' is on '
            . $schedule['day_of_week'] . 's, but ' . $date
            . ' is a ' . date('l', strtotime($date)) . '.';
    }

    return am_check_day($pdo, $classId, $date);
}

/* ---------- Save ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();

    $error = am_subject_check($pdo, $classId, $schedule, $date);

    if ($error === null) {
        try {
            $posted = is_array($_POST['status'] ?? null) ? $_POST['status'] : [];
            $result = am_save_roster($pdo, $classId, $date, $scheduleId, $posted);
            am_flash(
                $schedule['subject_name'] . ' attendance saved: '
                . am_save_message($result)
            );
        } catch (PDOException $e) {
            error_log($e->getMessage());
            am_flash('Could not save attendance. Please try again.', 'error');
        }
    } else {
        am_flash($error, 'error');
    }

    go('attendance/subject_attendance.php?' . http_build_query([
        'class_id'    => $classId,
        'schedule_id' => $scheduleId,
        'date'        => $date,
    ]));
}

/* ---------- Show ---------- */
$students = [];
$current = [];
$onLeave = [];
$problem = null;
$otherSubject = 0;

if ($schedule) {
    $students = am_students_in_class($pdo, $classId);
    $current = am_existing_status_map($pdo, $classId, $date);
    $onLeave = am_students_on_leave(
        $pdo, array_column($students, 'student_id'), $date
    );
    $problem = am_subject_check($pdo, $classId, $schedule, $date);

    // The table keeps one record per student, class and day.
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM attendance_records
         WHERE class_id = ? AND `date` = ?
           AND schedule_id IS NOT NULL AND schedule_id <> ?'
    );
    $stmt->execute([$classId, $date, $scheduleId]);
    $otherSubject = (int) $stmt->fetchColumn();
}

/* Per-subject summary for the class. */
$summary = [];
if ($classId !== '') {
    $stmt = $pdo->prepare(
        "SELECT ts.schedule_id, sub.subject_name,
                SUM(a.status = 'Present') AS present,
                SUM(a.status = 'Late') AS late,
                SUM(a.status = 'Absent') AS absent,
                SUM(a.status = 'Excused') AS excused
         FROM attendance_records a
         INNER JOIN timetable_schedules ts ON ts.schedule_id = a.schedule_id
         INNER JOIN subjects sub ON sub.subject_id = ts.subject_id
         WHERE a.class_id = ?
         GROUP BY ts.schedule_id, sub.subject_name
         ORDER BY sub.subject_name"
    );
    $stmt->execute([$classId]);
    $summary = $stmt->fetchAll();
}

$pageTitle = 'Mark Attendance by Subject';
require __DIR__ . '/../includes/header.php';
am_nav('subject');
?>

<h1>Mark Attendance by Subject</h1>

<form method="get" class="am-filters">
    <label>Class <?= am_class_select($pdo, 'class_id', $classId) ?></label>
    <button type="submit">Load subjects</button>
</form>

<?php if ($classId !== ''): ?>
    <?php if (!$schedules): ?>
        <p>No timetable subjects were found for this class
           (or none are assigned to you).</p>
    <?php else: ?>
    <form method="get" class="am-filters">
        <input type="hidden" name="class_id" value="<?= h($classId) ?>">
        <label>Subject
            <select name="schedule_id">
                <option value="">Select a subject</option>
                <?php foreach ($schedules as $s): ?>
                    <option value="<?= h($s['schedule_id']) ?>"
                        <?= $s['schedule_id'] === $scheduleId ? 'selected' : '' ?>>
                        <?= h($s['subject_code'] . ' ' . $s['subject_name']
                            . ' - ' . $s['day_of_week'] . ' '
                            . substr($s['start_time'], 0, 5) . '-'
                            . substr($s['end_time'], 0, 5)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Date
            <input type="date" name="date" value="<?= h($date) ?>"
                   max="<?= h(am_today()) ?>">
        </label>
        <button type="submit">Load students</button>
    </form>
    <?php endif; ?>
<?php endif; ?>

<?php if ($problem && $schedule): ?>
    <p class="am-error"><?= h($problem) ?></p>
<?php endif; ?>

<?php if ($otherSubject > 0): ?>
    <p class="am-note">
        Note: <?= $otherSubject ?> record(s) for this class on this date
        belong to a different subject. The system keeps one record per
        student, per class, per day, so saving will replace them.
    </p>
<?php endif; ?>

<?php if ($schedule && $students): ?>
    <h2><?= h($schedule['subject_name']) ?> - <?= h($date) ?></h2>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="class_id" value="<?= h($classId) ?>">
        <input type="hidden" name="schedule_id" value="<?= h($scheduleId) ?>">
        <input type="hidden" name="date" value="<?= h($date) ?>">

        <?php am_roster_table($students, $current, $onLeave); ?>

        <p>
            <button type="submit" <?= $problem ? 'disabled' : '' ?>>
                Save attendance
            </button>
        </p>
    </form>
<?php elseif ($schedule): ?>
    <p>No students are enrolled in this class yet.</p>
<?php endif; ?>

<?php if ($summary): ?>
    <h2>Attendance by subject</h2>
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Subject</th><th>Present</th><th>Late</th>
                <th>Absent</th><th>Excused</th><th>Percentage</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($summary as $r):
            $p = am_percent((int) $r['present'], (int) $r['late'], (int) $r['absent']);
        ?>
            <tr>
                <td><?= h($r['subject_name']) ?></td>
                <td><?= (int) $r['present'] ?></td>
                <td><?= (int) $r['late'] ?></td>
                <td><?= (int) $r['absent'] ?></td>
                <td><?= (int) $r['excused'] ?></td>
                <td class="<?= ($p !== null && $p < AM_LOW_ATTENDANCE) ? 'am-low' : '' ?>">
                    <?= h(am_percent_text($p)) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>