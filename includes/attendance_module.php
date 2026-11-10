<?php
/**
 * MODULE 5: ATTENDANCE RECORDING - shared helpers
 *
 * Used only by the pages in /attendance.
 * All function names start with am_ so they never clash with the
 * teammate's att_* helpers in includes/attendance_functions.php.
 *
 * Uses the existing table attendance_records exactly as designed:
 *   UNIQUE (student_id, class_id, date)  => one record per student,
 *   per class, per day.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/role_check.php';

const AM_STATUSES = ['Present', 'Absent', 'Late', 'Excused'];

// Students below this percentage are highlighted.
const AM_LOW_ATTENDANCE = 80.0;

const AM_PAGE_SIZE = 25;


/* =========================================================
   1. WHO IS LOGGED IN
   The login only stores user_id / username / role in the
   session, so staff_id and student_id are read from
   user_accounts.
   ========================================================= */

function am_account(PDO $pdo): array
{
    static $account = null;

    if ($account === null) {
        $stmt = $pdo->prepare(
            'SELECT user_id, role, admin_id, staff_id, student_id
             FROM user_accounts
             WHERE user_id = ? AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute([(int) (me()['id'] ?? 0)]);
        $account = $stmt->fetch() ?: [];
    }

    return $account;
}

function am_role(): string
{
    return (string) (me()['role'] ?? '');
}

function am_is_admin(): bool
{
    return am_role() === 'admin';
}

function am_is_staff(): bool
{
    return am_role() === 'staff';
}

function am_is_student(): bool
{
    return am_role() === 'student';
}

function am_staff_id(PDO $pdo): string
{
    return (string) (am_account($pdo)['staff_id'] ?? '');
}

function am_student_id(PDO $pdo): string
{
    return (string) (am_account($pdo)['student_id'] ?? '');
}


/* =========================================================
   2. CLASSES THE USER MAY WORK WITH
   admin   : every class
   staff   : classes they are class teacher of, or teach a
             timetable slot in
   student : classes they are enrolled in
   ========================================================= */

function am_class_label(array $c): string
{
    return $c['class_id'] . ' - '
        . $c['grade_level'] . ' ' . $c['section']
        . ' (' . $c['academic_year'] . ')';
}

/** @return array<string,array> class_id => class row */
function am_classes(PDO $pdo): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    if (am_is_admin()) {
        $stmt = $pdo->query(
            'SELECT * FROM classes
             ORDER BY academic_year DESC, grade_level, section'
        );
    } elseif (am_is_staff()) {
        $stmt = $pdo->prepare(
            'SELECT c.* FROM classes c
             WHERE c.class_teacher_id = :sid
                OR EXISTS (
                    SELECT 1 FROM timetable_schedules t
                    WHERE t.class_id = c.class_id
                      AND t.assigned_staff_id = :sid2
                )
             ORDER BY c.academic_year DESC, c.grade_level, c.section'
        );
        $stmt->execute([
            'sid'  => am_staff_id($pdo),
            'sid2' => am_staff_id($pdo),
        ]);
    } else {
        // Enrolment table, or the class saved on the student's own row.
        $stmt = $pdo->prepare(
            'SELECT c.* FROM classes c
             WHERE c.class_id IN (
                    SELECT e.class_id FROM student_enrolments e
                    WHERE e.student_id = :sid1
                )
                OR c.class_id IN (
                    SELECT s.student_class FROM students s
                    WHERE s.student_id = :sid2
                )
             ORDER BY c.academic_year DESC, c.grade_level, c.section'
        );
        $stmt->execute([
            'sid1' => am_student_id($pdo),
            'sid2' => am_student_id($pdo),
        ]);
    }

    $cache = [];
    foreach ($stmt->fetchAll() as $row) {
        $cache[$row['class_id']] = $row;
    }

    return $cache;
}

/** Admin or the class's own staff may change records. */
function am_can_manage_class(PDO $pdo, string $classId): bool
{
    if (am_is_student()) {
        return false;
    }

    return isset(am_classes($pdo)[$classId]);
}

function am_students_in_class(PDO $pdo, string $classId): array
{
    // Students enrolled in the class, or whose own row names this class.
    $stmt = $pdo->prepare(
        'SELECT s.student_id, s.student_name
         FROM students s
         WHERE s.student_id IN (
                SELECT e.student_id FROM student_enrolments e
                WHERE e.class_id = :c1
            )
            OR s.student_class = :c2
         ORDER BY s.student_name'
    );
    $stmt->execute(['c1' => $classId, 'c2' => $classId]);

    return $stmt->fetchAll();
}


/* =========================================================
   3. DATE AND CALENDAR HELPERS
   ========================================================= */

/** Returns Y-m-d if the text is a real date, otherwise null. */
function am_parse_date(?string $text): ?string
{
    $text = trim((string) $text);
    $d = DateTime::createFromFormat('!Y-m-d', $text);

    if (!$d || $d->format('Y-m-d') !== $text) {
        return null;
    }

    return $text;
}

function am_today(): string
{
    return date('Y-m-d');
}

/** Row from academic_calendar_overrides when the day is NOT a tuition day. */
function am_non_tuition_day(PDO $pdo, string $date): ?array
{
    $stmt = $pdo->prepare(
        'SELECT day_type, description
         FROM academic_calendar_overrides
         WHERE `date` = ? AND is_tuition_day = 0
         LIMIT 1'
    );
    $stmt->execute([$date]);

    return $stmt->fetch() ?: null;
}

/** @return array<string,true> student_id => true for students on approved leave */
function am_students_on_leave(PDO $pdo, array $studentIds, string $date): array
{
    if (!$studentIds) {
        return [];
    }

    $marks = implode(',', array_fill(0, count($studentIds), '?'));

    $stmt = $pdo->prepare(
        "SELECT DISTINCT applicant_id
         FROM leave_applications
         WHERE applicant_type = 'Student'
           AND status = 'Approved'
           AND ? BETWEEN start_date AND end_date
           AND applicant_id IN ($marks)"
    );
    $stmt->execute(array_merge([$date], array_values($studentIds)));

    $found = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $found[$id] = true;
    }

    return $found;
}

/* =========================================================
   4. WRITING RECORDS
   ========================================================= */

/** attendance_id is VARCHAR(12): "ATT" + 9 characters. */
function am_new_id(PDO $pdo): string
{
    $check = $pdo->prepare(
        'SELECT 1 FROM attendance_records WHERE attendance_id = ?'
    );

    do {
        $id = 'ATT' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 9));
        $check->execute([$id]);
    } while ($check->fetchColumn());

    return $id;
}

/**
 * Insert or update one student's record for a class and day.
 *
 * @return array{0:string,1:?string,2:string}
 *         [result, old status, attendance_id]
 *         result = inserted | updated | unchanged
 */
function am_upsert(
    PDO $pdo,
    string $studentId,
    string $classId,
    string $date,
    string $status,
    ?string $scheduleId,
    ?string $staffId
): array {
    $find = $pdo->prepare(
        'SELECT attendance_id, status
         FROM attendance_records
         WHERE student_id = ? AND class_id = ? AND `date` = ?
         LIMIT 1 FOR UPDATE'
    );
    $find->execute([$studentId, $classId, $date]);
    $existing = $find->fetch();

    $staffId = ($staffId === '') ? null : $staffId;

    if ($existing) {
        if (
            $existing['status'] === $status
            && ($scheduleId === null)
        ) {
            return ['unchanged', $existing['status'], $existing['attendance_id']];
        }

        $pdo->prepare(
            'UPDATE attendance_records
             SET status = ?,
                 schedule_id = COALESCE(?, schedule_id),
                 marked_by_staff_id = COALESCE(?, marked_by_staff_id),
                 marked_at = NOW()
             WHERE attendance_id = ?'
        )->execute([
            $status,
            $scheduleId,
            $staffId,
            $existing['attendance_id'],
        ]);

        return ['updated', $existing['status'], $existing['attendance_id']];
    }

    $id = am_new_id($pdo);

    $pdo->prepare(
        'INSERT INTO attendance_records
            (attendance_id, student_id, class_id, `date`, status,
             schedule_id, marked_by_staff_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $id, $studentId, $classId, $date, $status,
        $scheduleId, $staffId,
    ]);

    return ['inserted', null, $id];
}

/**
 * Write one line to attendance_audit_log.
 * Best effort: if the attendance_audit_log table has not been
 * imported yet, attendance still works and the error is logged.
 */
function am_audit(
    PDO $pdo,
    string $action,
    ?string $attendanceId,
    string $studentId,
    string $classId,
    string $date,
    ?string $oldStatus,
    ?string $newStatus,
    ?string $reason = null
): void {
    try {
        $pdo->prepare(
            'INSERT INTO attendance_audit_log
                (action, attendance_id, student_id, class_id, `date`,
                 old_status, new_status, reason,
                 acted_by_user_id, acted_by_role)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $action, $attendanceId, $studentId, $classId, $date,
            $oldStatus, $newStatus, $reason,
            (int) (me()['id'] ?? 0), am_role(),
        ]);
    } catch (PDOException $e) {
        error_log('attendance_audit_log: ' . $e->getMessage());
    }
}


/* =========================================================
   5. READING RECORDS (view, search, history, reports)
   ========================================================= */

/**
 * Filters: class_id, student_id, status, date_from, date_to, q,
 *          schedule_id
 * The logged-in user's role limits what can be seen.
 *
 * @return array{0:string,1:array} [WHERE sql, params]
 */
function am_where(PDO $pdo, array $f): array
{
    $where = [];
    $params = [];

    if (am_is_student()) {
        $where[] = 'a.student_id = ?';
        $params[] = am_student_id($pdo);
    } elseif (am_is_staff()) {
        $ids = array_keys(am_classes($pdo));

        if (!$ids) {
            return ['1 = 0', []];
        }

        $where[] = 'a.class_id IN ('
            . implode(',', array_fill(0, count($ids), '?')) . ')';
        $params = array_merge($params, $ids);
    }

    if (!empty($f['class_id'])) {
        $where[] = 'a.class_id = ?';
        $params[] = $f['class_id'];
    }
    if (!empty($f['student_id'])) {
        $where[] = 'a.student_id = ?';
        $params[] = $f['student_id'];
    }
    if (!empty($f['schedule_id'])) {
        $where[] = 'a.schedule_id = ?';
        $params[] = $f['schedule_id'];
    }
    if (!empty($f['status']) && in_array($f['status'], AM_STATUSES, true)) {
        $where[] = 'a.status = ?';
        $params[] = $f['status'];
    }
    if (!empty($f['date_from'])) {
        $where[] = 'a.`date` >= ?';
        $params[] = $f['date_from'];
    }
    if (!empty($f['date_to'])) {
        $where[] = 'a.`date` <= ?';
        $params[] = $f['date_to'];
    }
    if (!empty($f['q'])) {
        $where[] = '(s.student_name LIKE ? OR a.student_id LIKE ?
                     OR a.attendance_id LIKE ?)';
        $like = '%' . addcslashes((string) $f['q'], '%_\\') . '%';
        array_push($params, $like, $like, $like);
    }

    return [$where ? implode(' AND ', $where) : '1 = 1', $params];
}

const AM_FROM = '
    FROM attendance_records a
    INNER JOIN students s ON s.student_id = a.student_id
    INNER JOIN classes c ON c.class_id = a.class_id
    LEFT JOIN timetable_schedules ts ON ts.schedule_id = a.schedule_id
    LEFT JOIN subjects sub ON sub.subject_id = ts.subject_id';

function am_records(PDO $pdo, array $f, int $limit = 0, int $offset = 0): array
{
    [$where, $params] = am_where($pdo, $f);

    $sql = 'SELECT a.attendance_id, a.student_id, a.class_id, a.`date`,
                   a.status, a.schedule_id, a.marked_at,
                   s.student_name, c.grade_level, c.section,
                   c.academic_year, sub.subject_name'
        . AM_FROM
        . " WHERE $where
            ORDER BY a.`date` DESC, c.grade_level, c.section,
                     s.student_name";

    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function am_records_count(PDO $pdo, array $f): int
{
    [$where, $params] = am_where($pdo, $f);

    $stmt = $pdo->prepare('SELECT COUNT(*)' . AM_FROM . " WHERE $where");
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/** One record the current user is allowed to see, or null. */
function am_record(PDO $pdo, string $attendanceId): ?array
{
    [$where, $params] = am_where($pdo, []);

    $stmt = $pdo->prepare(
        'SELECT a.attendance_id, a.student_id, a.class_id, a.`date`,
                a.status, a.schedule_id, s.student_name,
                c.grade_level, c.section, c.academic_year,
                sub.subject_name'
        . AM_FROM
        . " WHERE $where AND a.attendance_id = ? LIMIT 1"
    );
    $stmt->execute(array_merge($params, [$attendanceId]));

    return $stmt->fetch() ?: null;
}

/**
 * Attendance percentage.
 * Same rule as the team's view v_student_attendance_summary:
 *   (Present + Late) / (Present + Late + Absent)
 * Excused days are left out. Null when nothing is countable.
 */
function am_percent(int $present, int $late, int $absent): ?float
{
    $counted = $present + $late + $absent;

    if ($counted === 0) {
        return null;
    }

    return round(100 * ($present + $late) / $counted, 2);
}

function am_percent_text(?float $p): string
{
    return $p === null ? 'N/A' : number_format($p, 2) . '%';
}

/* =========================================================
   6. SMALL UI HELPERS
   ========================================================= */

function am_flash(string $message, string $type = 'ok'): void
{
    $_SESSION['am_flash'] = [$type, $message];
}

function am_show_flash(): void
{
    if (!empty($_SESSION['am_flash'])) {
        [$type, $message] = $_SESSION['am_flash'];
        unset($_SESSION['am_flash']);

        echo '<p class="' . ($type === 'error' ? 'am-error' : 'notice')
            . '">' . h($message) . '</p>';
    }
}

function am_badge(string $status): string
{
    return '<span class="am-badge am-' . h(strtolower($status)) . '">'
        . h($status) . '</span>';
}

function am_class_select(
    PDO $pdo,
    string $name,
    string $selected,
    bool $allowAll = false
): string {
    $html = '<select name="' . h($name) . '">';

    if ($allowAll) {
        $html .= '<option value="">All classes</option>';
    } else {
        $html .= '<option value="">Select a class</option>';
    }

    foreach (am_classes($pdo) as $id => $c) {
        $html .= '<option value="' . h($id) . '"'
            . ($id === $selected ? ' selected' : '') . '>'
            . h(am_class_label($c)) . '</option>';
    }

    return $html . '</select>';
}

function am_status_select(string $name, string $selected, bool $allowAll = false): string
{
    $html = '<select name="' . h($name) . '">';

    if ($allowAll) {
        $html .= '<option value="">All statuses</option>';
    }

    foreach (AM_STATUSES as $s) {
        $html .= '<option value="' . h($s) . '"'
            . ($s === $selected ? ' selected' : '') . '>'
            . h($s) . '</option>';
    }

    return $html . '</select>';
}

/** Module menu shown at the top of every Module 5 page. */
function am_nav(string $active = ''): void
{
    $links = [];

    if (am_is_student()) {
        $links = [
            'index'      => ['index.php', 'Attendance Home'],
            'view'       => ['view_attendance.php', 'My Records'],
            'search'     => ['search_attendance.php', 'Search'],
            'history'    => ['attendance_history.php', 'My History'],
            'percentage' => ['attendance_percentage.php', 'My Percentage'],
            'scan'       => ['scan_qr.php', 'Scan QR'],
        ];
    } else {
        $links = [
            'index'      => ['index.php', 'Attendance Home'],
            'mark'       => ['mark_attendance.php', 'Mark'],
            'subject'    => ['subject_attendance.php', 'By Subject'],
            'bulk'       => ['bulk_attendance.php', 'Bulk'],
            'view'       => ['view_attendance.php', 'Records'],
            'search'     => ['search_attendance.php', 'Search'],
            'history'    => ['attendance_history.php', 'Student History'],
            'percentage' => ['attendance_percentage.php', 'Percentage'],
            'qr'         => ['qr_attendance.php', 'QR Attendance'],
        ];

        if (am_is_admin()) {
            $links['override'] = ['attendance_override.php', 'Admin Override'];
        }
    }

    echo '<link rel="stylesheet" href="'
        . h(url('assets/css/attendance_module.css')) . '">';
    echo '<nav class="am-nav" aria-label="Attendance recording">';

    foreach ($links as $key => [$file, $label]) {
        echo '<a href="' . h(url('attendance/' . $file)) . '"'
            . ($key === $active ? ' class="active"' : '') . '>'
            . h($label) . '</a>';
    }

    echo '</nav>';
    am_show_flash();
}

/** Pagination links that keep the current filters. */
function am_pager(int $total, int $page, array $query): string
{
    $pages = max(1, (int) ceil($total / AM_PAGE_SIZE));

    if ($pages === 1) {
        return '';
    }

    $html = '<p class="am-pager">Page ' . $page . ' of ' . $pages . ' ';

    foreach ([['Previous', $page - 1], ['Next', $page + 1]] as [$label, $to]) {
        if ($to >= 1 && $to <= $pages) {
            $q = http_build_query(array_merge($query, ['page' => $to]));
            $html .= '<a href="?' . h($q) . '">' . $label . '</a> ';
        }
    }

    return $html . '</p>';
}

/** Read the shared filter fields from the URL (GET). */
function am_filters_from_get(): array
{
    $from = am_parse_date($_GET['date_from'] ?? '');
    $to = am_parse_date($_GET['date_to'] ?? '');
    $status = (string) ($_GET['status'] ?? '');

    return [
        'class_id'   => trim((string) ($_GET['class_id'] ?? '')),
        'student_id' => trim((string) ($_GET['student_id'] ?? '')),
        'status'     => in_array($status, AM_STATUSES, true) ? $status : '',
        'date_from'  => $from ?? '',
        'date_to'    => $to ?? '',
        'q'          => trim((string) ($_GET['q'] ?? '')),
    ];
}


/* =========================================================
   7. ROSTER (shared by Mark, By Subject, Bulk and QR sync)
   ========================================================= */

/** sid => status for one class on one day. */
function am_existing_status_map(PDO $pdo, string $classId, string $date): array
{
    $stmt = $pdo->prepare(
        'SELECT student_id, status
         FROM attendance_records
         WHERE class_id = ? AND `date` = ?'
    );
    $stmt->execute([$classId, $date]);

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[$row['student_id']] = $row['status'];
    }

    return $map;
}

/** Returns an error message, or null when marking is allowed. */
function am_check_day(PDO $pdo, string $classId, string $date): ?string
{
    if (!am_can_manage_class($pdo, $classId)) {
        return 'You cannot record attendance for that class.';
    }

    if (am_parse_date($date) === null) {
        return 'Please choose a valid date.';
    }

    if ($date > am_today()) {
        return 'Attendance cannot be recorded for a future date.';
    }

    if (!am_is_admin() && am_non_tuition_day($pdo, $date)) {
        return 'That date is not a tuition day. Ask an admin to use '
            . 'Admin Override if a record is really needed.';
    }

    return null;
}

/**
 * Save posted statuses for students of one class.
 *
 * @param array<string,string> $posted student_id => status
 * @return array{inserted:int,updated:int,unchanged:int}
 */
function am_save_roster(
    PDO $pdo,
    string $classId,
    string $date,
    ?string $scheduleId,
    array $posted
): array {
    $valid = [];
    foreach (am_students_in_class($pdo, $classId) as $s) {
        $valid[$s['student_id']] = true;
    }

    $staffId = am_staff_id($pdo);
    $count = ['inserted' => 0, 'updated' => 0, 'unchanged' => 0];

    $pdo->beginTransaction();

    try {
        foreach ($posted as $studentId => $status) {
            $studentId = (string) $studentId;

            if (
                !isset($valid[$studentId])
                || !in_array($status, AM_STATUSES, true)
            ) {
                continue;
            }

            [$result, $old, $id] = am_upsert(
                $pdo, $studentId, $classId, $date,
                $status, $scheduleId, $staffId
            );

            $count[$result]++;

            if ($result !== 'unchanged') {
                am_audit(
                    $pdo,
                    $result === 'inserted' ? 'mark' : 'update',
                    $id, $studentId, $classId, $date, $old, $status
                );
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return $count;
}

function am_save_message(array $c): string
{
    return $c['inserted'] . ' added, '
        . $c['updated'] . ' updated, '
        . $c['unchanged'] . ' unchanged.';
}

/**
 * Table of students with one status choice each.
 *
 * @param array<string,string> $current  existing status by student_id
 * @param array<string,true>   $onLeave  students with approved leave
 */
function am_roster_table(array $students, array $current, array $onLeave): void
{
    ?>
    <p class="am-note">
        <button type="button" data-all="Present">All Present</button>
        <button type="button" data-all="Absent">All Absent</button>
        Students with an approved leave are pre-set to Excused.
    </p>

    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student ID</th>
                <th>Name</th>
                <th>Status</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($students as $i => $s):
            $sid = $s['student_id'];
            $leave = isset($onLeave[$sid]);
            $value = $current[$sid]
                ?? ($leave ? 'Excused' : 'Present');
        ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= h($sid) ?></td>
                <td><?= h($s['student_name']) ?></td>
                <td class="am-radios">
                    <?php foreach (AM_STATUSES as $st): ?>
                        <label>
                            <input type="radio"
                                   name="status[<?= h($sid) ?>]"
                                   value="<?= h($st) ?>"
                                   <?= $value === $st ? 'checked' : '' ?>>
                            <?= h($st) ?>
                        </label>
                    <?php endforeach; ?>
                </td>
                <td class="am-note">
                    <?php if ($leave): ?>Approved leave<?php endif; ?>
                    <?php if (isset($current[$sid])): ?>
                        Already recorded
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <script>
    document.querySelectorAll('[data-all]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document
                .querySelectorAll('input[type=radio][value="' + btn.dataset.all + '"]')
                .forEach(function (r) { r.checked = true; });
        });
    });
    </script>
    <?php
}