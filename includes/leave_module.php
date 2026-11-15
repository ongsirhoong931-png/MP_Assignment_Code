<?php
/**
 * MODULE 6: LEAVE & ABSENCE MANAGEMENT - shared helpers
 *
 * Used only by the pages in /leave. Every function starts with lv_.
 * It reuses the Module 5 helpers (am_*) for login, classes and
 * attendance records, and lang.php for English / Chinese / Malay text.
 *
 * Uses the existing table leave_applications as designed:
 *   status is Pending, Approved or Rejected
 *   applicant_type is Student or Staff
 * The only new table is leave_application_log (who did what, and
 * the reason for a rejection).
 */

require_once __DIR__ . '/attendance_module.php';
require_once __DIR__ . '/lang.php';

const LV_TYPES = [
    'Sick Leave',
    'Family Matters',
    'Personal Leave',
    'Event / Competition',
    'Other',
];

// Leave type used when staff record an absence in advance.
const LV_PRE_APPROVED = 'Pre-Approved Absence';

const LV_STATUSES = ['Pending', 'Approved', 'Rejected'];

const LV_MC_MAX_BYTES = 2 * 1024 * 1024;   // 2 MB
const LV_MC_TYPES = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
];

const LV_MAX_DAYS = 30;        // longest single leave
const LV_LATE_DAYS = 14;       // how far back a leave may start
const LV_AHEAD_DAYS = 180;     // how far ahead a leave may end

// Language is chosen before any page output.
lang_init($pdo);


/* =========================================================
   1. LABELS
   ========================================================= */

function lv_type_label(string $type): string
{
    $slug = trim(strtolower(preg_replace('/[^A-Za-z]+/', '_', $type)), '_');
    $key = 'lv.type.' . $slug;
    $text = t($key);

    return $text === $key ? $type : $text;
}

function lv_status_label(string $status): string
{
    return t('lv.status.' . strtolower($status));
}

function lv_badge(string $status): string
{
    return '<span class="am-badge lv-' . h(strtolower($status)) . '">'
        . h(lv_status_label($status)) . '</span>';
}

function lv_days(string $start, string $end): int
{
    return (int) (new DateTime($start))->diff(new DateTime($end))->days + 1;
}


/* =========================================================
   2. WHO IS WHO
   ========================================================= */

/** The logged-in student or staff member (admins have no leave). */
function lv_self(PDO $pdo): ?array
{
    if (am_is_student() && am_student_id($pdo) !== '') {
        return ['type' => 'Student', 'id' => am_student_id($pdo)];
    }

    if (am_is_staff() && am_staff_id($pdo) !== '') {
        return ['type' => 'Staff', 'id' => am_staff_id($pdo)];
    }

    return null;
}

/** admin_id or staff_id, saved in leave_applications.approved_by_id. */
function lv_reviewer_id(PDO $pdo): string
{
    $row = am_account($pdo);

    return (string) (am_is_admin() ? ($row['admin_id'] ?? '') : ($row['staff_id'] ?? ''));
}

function lv_is_owner(PDO $pdo, array $leave): bool
{
    $me = lv_self($pdo);

    return $me !== null
        && $me['type'] === $leave['applicant_type']
        && $me['id'] === $leave['applicant_id'];
}

/** @return string[] class ids of one student */
function lv_student_class_ids(PDO $pdo, string $studentId): array
{
    $stmt = $pdo->prepare(
        'SELECT class_id FROM student_enrolments WHERE student_id = :a
         UNION
         SELECT student_class FROM students
         WHERE student_id = :b AND student_class IS NOT NULL'
    );
    $stmt->execute(['a' => $studentId, 'b' => $studentId]);

    return array_values(array_filter($stmt->fetchAll(PDO::FETCH_COLUMN)));
}

function lv_student_in_my_classes(PDO $pdo, string $studentId): bool
{
    return (bool) array_intersect(
        lv_student_class_ids($pdo, $studentId),
        array_keys(am_classes($pdo))
    );
}

/**
 * Admin: any leave except their own (they have none).
 * Staff: only students of their classes, never their own leave.
 */
function lv_can_review(PDO $pdo, array $leave): bool
{
    if (am_is_student() || lv_is_owner($pdo, $leave)) {
        return false;
    }

    if (am_is_admin()) {
        return true;
    }

    return $leave['applicant_type'] === 'Student'
        && lv_student_in_my_classes($pdo, $leave['applicant_id']);
}

function lv_is_reviewer(): bool
{
    return am_is_admin() || am_is_staff();
}


/* =========================================================
   3. READING LEAVE APPLICATIONS
   ========================================================= */

const LV_FROM = "
    FROM leave_applications l
    LEFT JOIN students s
        ON l.applicant_type = 'Student' AND s.student_id = l.applicant_id
    LEFT JOIN staff st
        ON l.applicant_type = 'Staff' AND st.staff_id = l.applicant_id";

const LV_COLUMNS = 'l.leave_id, l.applicant_id, l.applicant_type,
        l.leave_type, l.start_date, l.end_date, l.reason, l.status,
        l.approved_by_id, l.attachment_path, l.created_at,
        COALESCE(s.student_name, st.staff_name) AS applicant_name';

/**
 * Filters: status, type, q, date_from, date_to, exclude_own
 * The logged-in user's role limits what can be seen.
 *
 * @return array{0:string,1:array} [WHERE sql, params]
 */
function lv_where(PDO $pdo, array $f): array
{
    $where = [];
    $params = [];

    if (am_is_student()) {
        $where[] = "l.applicant_type = 'Student' AND l.applicant_id = ?";
        $params[] = am_student_id($pdo);
    } elseif (am_is_staff()) {
        $ids = array_keys(am_classes($pdo));
        $scope = "(l.applicant_type = 'Staff' AND l.applicant_id = ?)";
        $params[] = am_staff_id($pdo);

        if ($ids) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $scope .= " OR (l.applicant_type = 'Student' AND l.applicant_id IN (
                            SELECT student_id FROM students
                            WHERE student_class IN ($marks)
                            UNION
                            SELECT student_id FROM student_enrolments
                            WHERE class_id IN ($marks)))";
            $params = array_merge($params, $ids, $ids);
        }

        $where[] = "($scope)";
    }

    if (!empty($f['status']) && in_array($f['status'], LV_STATUSES, true)) {
        $where[] = 'l.status = ?';
        $params[] = $f['status'];
    }

    if (!empty($f['type'])) {
        $where[] = 'l.leave_type = ?';
        $params[] = $f['type'];
    }

    if (!empty($f['date_from'])) {
        $where[] = 'l.end_date >= ?';
        $params[] = $f['date_from'];
    }

    if (!empty($f['date_to'])) {
        $where[] = 'l.start_date <= ?';
        $params[] = $f['date_to'];
    }

    if (!empty($f['q'])) {
        $where[] = '(l.leave_id LIKE ? OR l.applicant_id LIKE ?
                     OR COALESCE(s.student_name, st.staff_name) LIKE ?)';
        $like = '%' . addcslashes((string) $f['q'], '%_\\') . '%';
        array_push($params, $like, $like, $like);
    }

    if (!empty($f['exclude_own']) && ($me = lv_self($pdo)) !== null) {
        $where[] = 'NOT (l.applicant_type = ? AND l.applicant_id = ?)';
        array_push($params, $me['type'], $me['id']);
    }

    return [$where ? implode(' AND ', $where) : '1 = 1', $params];
}

function lv_list(PDO $pdo, array $f, int $limit = 0, int $offset = 0): array
{
    [$where, $params] = lv_where($pdo, $f);

    $sql = 'SELECT ' . LV_COLUMNS . LV_FROM
        . " WHERE $where
            ORDER BY l.created_at DESC, l.leave_id";

    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function lv_count(PDO $pdo, array $f): int
{
    [$where, $params] = lv_where($pdo, $f);

    $stmt = $pdo->prepare('SELECT COUNT(*)' . LV_FROM . " WHERE $where");
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/** One leave the current user is allowed to see, or null. */
function lv_find(PDO $pdo, string $leaveId): ?array
{
    [$where, $params] = lv_where($pdo, []);

    $stmt = $pdo->prepare(
        'SELECT ' . LV_COLUMNS . LV_FROM
        . " WHERE $where AND l.leave_id = ? LIMIT 1"
    );
    $stmt->execute(array_merge($params, [$leaveId]));

    return $stmt->fetch() ?: null;
}

function lv_filters_from_get(): array
{
    $status = (string) ($_GET['status'] ?? '');
    $type = (string) ($_GET['type'] ?? '');

    return [
        'status'    => in_array($status, LV_STATUSES, true) ? $status : '',
        'type'      => (in_array($type, LV_TYPES, true) || $type === LV_PRE_APPROVED) ? $type : '',
        'date_from' => am_parse_date($_GET['date_from'] ?? '') ?? '',
        'date_to'   => am_parse_date($_GET['date_to'] ?? '') ?? '',
        'q'         => trim((string) ($_GET['q'] ?? '')),
    ];
}

/** Name of an admin or staff member from admin_id / staff_id. */
function lv_person_name(PDO $pdo, ?string $id): string
{
    if ($id === null || $id === '') {
        return '-';
    }

    foreach ([
        'SELECT admin_name FROM admins WHERE admin_id = ?',
        'SELECT staff_name FROM staff WHERE staff_id = ?',
    ] as $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();

        if ($name !== false) {
            return (string) $name;
        }
    }

    return $id;
}


/* =========================================================
   4. CHECKING AND WRITING
   ========================================================= */

/** leave_id is VARCHAR(12): "LV" + 10 characters. */
function lv_new_id(PDO $pdo): string
{
    $check = $pdo->prepare('SELECT 1 FROM leave_applications WHERE leave_id = ?');

    do {
        $id = 'LV' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        $check->execute([$id]);
    } while ($check->fetchColumn());

    return $id;
}

/** Translated error message, or null when the dates are fine. */
function lv_check_dates(
    PDO $pdo,
    string $applicantType,
    string $applicantId,
    ?string $start,
    ?string $end,
    ?string $ignoreLeaveId = null
): ?string {
    if ($start === null || $end === null) {
        return t('lv.err.date_invalid');
    }

    if ($end < $start) {
        return t('lv.err.date_order');
    }

    if (lv_days($start, $end) > LV_MAX_DAYS) {
        return t('lv.err.too_long', ['days' => LV_MAX_DAYS]);
    }

    if ($start < date('Y-m-d', strtotime('-' . LV_LATE_DAYS . ' days'))) {
        return t('lv.err.too_old', ['days' => LV_LATE_DAYS]);
    }

    if ($end > date('Y-m-d', strtotime('+' . LV_AHEAD_DAYS . ' days'))) {
        return t('lv.err.too_far', ['days' => LV_AHEAD_DAYS]);
    }

    $stmt = $pdo->prepare(
        "SELECT 1 FROM leave_applications
         WHERE applicant_type = ? AND applicant_id = ?
           AND status IN ('Pending', 'Approved')
           AND start_date <= ? AND end_date >= ?
           AND leave_id <> ?
         LIMIT 1"
    );
    $stmt->execute([$applicantType, $applicantId, $end, $start, (string) $ignoreLeaveId]);

    return $stmt->fetchColumn() ? t('lv.err.overlap') : null;
}

function lv_check_reason(string $reason): ?string
{
    $length = mb_strlen($reason);

    return ($length < 5 || $length > 500) ? t('lv.err.reason') : null;
}

/**
 * Write one line to leave_application_log. Best effort: if the table
 * has not been imported yet, leave still works and the error is logged.
 */
function lv_log(PDO $pdo, string $leaveId, string $action, array $leave, ?string $note = null): void
{
    try {
        $pdo->prepare(
            'INSERT INTO leave_application_log
                (leave_id, action, applicant_id, applicant_type,
                 start_date, end_date, note,
                 acted_by_user_id, acted_by_role)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $leaveId, $action, $leave['applicant_id'], $leave['applicant_type'],
            $leave['start_date'], $leave['end_date'], $note,
            (int) (me()['id'] ?? 0), am_role(),
        ]);
    } catch (PDOException $e) {
        error_log('leave_application_log: ' . $e->getMessage());
    }
}

/** @return array<int,array> newest last */
function lv_timeline(PDO $pdo, string $leaveId): array
{
    try {
        $stmt = $pdo->prepare(
            'SELECT action, note, acted_by_role, acted_at
             FROM leave_application_log
             WHERE leave_id = ?
             ORDER BY acted_at, log_id'
        );
        $stmt->execute([$leaveId]);

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function lv_reject_reason(PDO $pdo, string $leaveId): ?string
{
    foreach (array_reverse(lv_timeline($pdo, $leaveId)) as $line) {
        if ($line['action'] === 'reject') {
            return $line['note'];
        }
    }

    return null;
}


/* =========================================================
   5. MEDICAL CERTIFICATE (file upload)
   ========================================================= */

function lv_mc_dir(): string
{
    return __DIR__ . '/../uploads/medical_certificates';
}

/** Translated error for an uploaded file, or null when it is acceptable. */
function lv_check_upload(array $file): ?string
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($error === UPLOAD_ERR_NO_FILE) {
        return t('lv.err.mc_none');
    }

    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        return t('lv.err.mc_big', ['mb' => LV_MC_MAX_BYTES / 1048576]);
    }

    if ($error !== UPLOAD_ERR_OK) {
        return t('lv.err.mc_failed');
    }

    if (($file['size'] ?? 0) <= 0 || $file['size'] > LV_MC_MAX_BYTES) {
        return t('lv.err.mc_big', ['mb' => LV_MC_MAX_BYTES / 1048576]);
    }

    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

    if (!isset(LV_MC_TYPES[$ext])) {
        return t('lv.err.mc_type');
    }

    // The real content must match the extension (not just the file name).
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);

    return $mime === LV_MC_TYPES[$ext] ? null : t('lv.err.mc_type');
}

/**
 * Save an uploaded certificate.
 * @return array{0:?string,1:?string} [stored path, error message]
 */
function lv_store_mc(array $file, string $leaveId): array
{
    $error = lv_check_upload($file);

    if ($error !== null) {
        return [null, $error];
    }

    $dir = lv_mc_dir();

    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return [null, t('lv.err.mc_failed')];
    }

    // Apache: block direct links. Files are served by leave/mc_file.php.
    $guard = $dir . '/.htaccess';
    if (!is_file($guard)) {
        file_put_contents($guard, "Require all denied\n");
    }

    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $name = 'mc_' . $leaveId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return [null, t('lv.err.mc_failed')];
    }

    return ['uploads/medical_certificates/' . $name, null];
}

/** Full path of a stored certificate, only if it really is inside the folder. */
function lv_mc_path(?string $stored): ?string
{
    if ($stored === null || $stored === '') {
        return null;
    }

    $root = realpath(lv_mc_dir());
    $real = realpath(__DIR__ . '/../' . $stored);

    if ($root === false || $real === false || !is_file($real)) {
        return null;
    }

    return str_starts_with($real, $root . DIRECTORY_SEPARATOR) ? $real : null;
}

function lv_delete_mc(?string $stored): void
{
    $path = lv_mc_path($stored);

    if ($path !== null) {
        @unlink($path);
    }
}


/* =========================================================
   6. UPDATE ATTENDANCE FROM APPROVED LEAVE
   ========================================================= */

/**
 * For an Approved STUDENT leave, mark the student Excused on every class
 * day from the leave start up to today.
 *  - class day  = the class has a timetable slot on that weekday
 *  - skipped    = holidays / non-tuition days and future dates
 *  - Present, Late and Excused records are never changed
 *  - Absent records become Excused; missing records are added
 *
 * @return array{added:int,changed:int,kept:int}
 */
function lv_sync_attendance(PDO $pdo, array $leave): array
{
    $count = ['added' => 0, 'changed' => 0, 'kept' => 0];

    if ($leave['status'] !== 'Approved' || $leave['applicant_type'] !== 'Student') {
        return $count;
    }

    $start = $leave['start_date'];
    $end = min($leave['end_date'], am_today());

    if ($start > $end) {
        return $count;
    }

    $studentId = $leave['applicant_id'];
    $staffId = am_is_staff() ? am_staff_id($pdo) : null;
    $reason = 'Approved leave ' . $leave['leave_id'];

    $holidays = $pdo->prepare(
        'SELECT `date` FROM academic_calendar_overrides
         WHERE is_tuition_day = 0 AND `date` BETWEEN ? AND ?'
    );
    $holidays->execute([$start, $end]);
    $skip = array_flip($holidays->fetchAll(PDO::FETCH_COLUMN));

    $slots = $pdo->prepare(
        'SELECT schedule_id, day_of_week FROM timetable_schedules
         WHERE class_id = ?'
    );

    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }

    try {
        foreach (lv_student_class_ids($pdo, $studentId) as $classId) {
            $slots->execute([$classId]);
            $byDay = [];
            foreach ($slots->fetchAll() as $slot) {
                $byDay[$slot['day_of_week']][] = $slot['schedule_id'];
            }

            if (!$byDay) {
                continue;   // no timetable: we cannot tell which days the class meets
            }

            $existing = $pdo->prepare(
                'SELECT `date`, status FROM attendance_records
                 WHERE student_id = ? AND class_id = ? AND `date` BETWEEN ? AND ?'
            );
            $existing->execute([$studentId, $classId, $start, $end]);
            $have = [];
            foreach ($existing->fetchAll() as $row) {
                $have[$row['date']] = $row['status'];
            }

            for (
                $d = new DateTime($start);
                $d->format('Y-m-d') <= $end;
                $d->modify('+1 day')
            ) {
                $date = $d->format('Y-m-d');
                $day = $d->format('l');

                if (!isset($byDay[$day]) || isset($skip[$date])) {
                    continue;
                }

                if (isset($have[$date]) && $have[$date] !== 'Absent') {
                    $count['kept']++;
                    continue;
                }

                $schedule = count($byDay[$day]) === 1 ? $byDay[$day][0] : null;

                [$result, $old, $id] = am_upsert(
                    $pdo, $studentId, $classId, $date, 'Excused', $schedule, $staffId
                );

                if ($result === 'unchanged') {
                    $count['kept']++;
                    continue;
                }

                am_audit(
                    $pdo,
                    $result === 'inserted' ? 'mark' : 'update',
                    $id, $studentId, $classId, $date, $old, 'Excused', $reason
                );
                $count[$result === 'inserted' ? 'added' : 'changed']++;
            }
        }

        if ($own) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    if ($count['added'] + $count['changed'] > 0) {
        lv_log(
            $pdo, $leave['leave_id'], 'attendance_sync', $leave,
            $count['added'] . ' added, ' . $count['changed'] . ' changed'
        );
    }

    return $count;
}

function lv_sync_message(array $c): string
{
    return t('lv.sync.result', [
        'added'   => $c['added'],
        'changed' => $c['changed'],
        'kept'    => $c['kept'],
    ]);
}


/* =========================================================
   7. SCREEN HELPERS
   ========================================================= */

/** Menu, language switcher and flash message shown on every Module 6 page. */
function lv_nav(string $active = ''): void
{
    $links = [
        'index'  => ['index.php', 'lv.nav.home'],
        'view'   => ['view_leave.php', 'lv.nav.view'],
        'status' => ['leave_status.php', 'lv.nav.status'],
    ];

    if (!am_is_admin()) {
        $links = array_merge(
            array_slice($links, 0, 1, true),
            ['apply' => ['apply_leave.php', 'lv.nav.apply']],
            array_slice($links, 1, null, true),
            ['mc' => ['upload_mc.php', 'lv.nav.mc']]
        );
    }

    if (lv_is_reviewer()) {
        $links['approve'] = ['approve_leave.php', 'lv.nav.approve'];
        $links['absence'] = ['approved_absence.php', 'lv.nav.absence'];
    }

    echo '<link rel="stylesheet" href="'
        . h(url('assets/css/attendance_module.css')) . '">';
    echo '<link rel="stylesheet" href="'
        . h(url('assets/css/leave_module.css')) . '">';

    echo '<div class="lv-bar">';
    echo '<nav class="am-nav" aria-label="' . h(t('lv.title')) . '">';

    foreach ($links as $key => [$file, $label]) {
        echo '<a href="' . h(url('leave/' . $file)) . '"'
            . ($key === $active ? ' class="active"' : '') . '>'
            . h(t($label)) . '</a>';
    }

    echo '</nav>';
    echo lang_switcher();
    echo '</div>';

    am_show_flash();
}

function lv_pager_query(array $filters): array
{
    return array_filter($filters, 'strlen');
}

function lv_type_select(string $name, string $selected, bool $allowAll = false, bool $withPre = false): string
{
    $html = '<select name="' . h($name) . '">';

    if ($allowAll) {
        $html .= '<option value="">' . h(t('lv.all_types')) . '</option>';
    }

    $types = LV_TYPES;
    if ($withPre) {
        $types[] = LV_PRE_APPROVED;
    }

    foreach ($types as $type) {
        $html .= '<option value="' . h($type) . '"'
            . ($type === $selected ? ' selected' : '') . '>'
            . h(lv_type_label($type)) . '</option>';
    }

    return $html . '</select>';
}

function lv_status_select(string $name, string $selected): string
{
    $html = '<select name="' . h($name) . '">'
        . '<option value="">' . h(t('lv.all_statuses')) . '</option>';

    foreach (LV_STATUSES as $status) {
        $html .= '<option value="' . h($status) . '"'
            . ($status === $selected ? ' selected' : '') . '>'
            . h(lv_status_label($status)) . '</option>';
    }

    return $html . '</select>';
}

/** Translated "Page 1 of 3  Previous Next" links. */
function lv_pager(int $total, int $page, array $query): string
{
    $pages = max(1, (int) ceil($total / AM_PAGE_SIZE));

    if ($pages === 1) {
        return '';
    }

    $html = '<p class="am-pager">'
        . h(t('lv.page_of', ['page' => $page, 'pages' => $pages])) . ' ';

    foreach ([['lv.prev', $page - 1], ['lv.next', $page + 1]] as [$key, $to]) {
        if ($to >= 1 && $to <= $pages) {
            $q = http_build_query(array_merge(lv_pager_query($query), ['page' => $to]));
            $html .= '<a href="?' . h($q) . '">' . h(t($key)) . '</a> ';
        }
    }

    return $html . '</p>';
}


/* =========================================================
   8. WHAT THE OWNER MAY DO WITH A LEAVE
   ========================================================= */

/** Pending leave of your own (not an absence recorded by staff). */
function lv_can_edit(PDO $pdo, array $leave): bool
{
    return lv_is_owner($pdo, $leave)
        && $leave['status'] === 'Pending'
        && $leave['leave_type'] !== LV_PRE_APPROVED;
}

/** Pending, or Approved but not started yet. */
function lv_can_cancel(PDO $pdo, array $leave): bool
{
    if (!lv_is_owner($pdo, $leave) || $leave['leave_type'] === LV_PRE_APPROVED) {
        return false;
    }

    return $leave['status'] === 'Pending'
        || ($leave['status'] === 'Approved' && $leave['start_date'] > am_today());
}

function lv_can_upload(PDO $pdo, array $leave): bool
{
    return lv_is_owner($pdo, $leave)
        && in_array($leave['status'], ['Pending', 'Approved'], true);
}

function lv_action_label(string $action): string
{
    $key = 'lv.act.' . $action;
    $text = t($key);

    return $text === $key ? $action : $text;
}