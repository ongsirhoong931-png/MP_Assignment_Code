<?php
/**
 * includes/calendar_functions.php
 * All logic for the holiday / academic calendar module.
 * Pages in /calender only call these functions.
 *
 * ADJUST THESE 3 THINGS to match your project:
 *  1. DB file below must give you a PDO object called $pdo
 *  2. cal_require_login(): your login page path + session key
 *  3. cal_can_manage(): your session key for role
 */

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const CAL_DAY_TYPES = [
    'Public Holiday',
    'School Holiday',
    'Centre Closed',
    'Replacement Class',
    'Special Event',
];

/* ---------- basic helpers ---------- */

function cal_pdo(): PDO
{
    global $pdo;
    return $pdo;
}

function cal_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* ---------- access control ---------- */

function cal_require_login(): void
{
    if (empty($_SESSION['user_id'])) {                 // <-- your session key
        header('Location: ../auth/login.php');         // <-- your login page
        exit;
    }
}

function cal_can_manage(): bool
{
    return ($_SESSION['role'] ?? '') === 'admin';      // only admin can add/edit/delete
}

function cal_require_admin(): void
{
    cal_require_login();
    if (!cal_can_manage()) {
        cal_flash('error', 'You do not have permission to do that.');
        header('Location: index.php');
        exit;
    }
}

/* ---------- CSRF + flash message ---------- */

function cal_csrf_token(): string
{
    if (empty($_SESSION['cal_csrf'])) {
        $_SESSION['cal_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['cal_csrf'];
}

function cal_csrf_check(?string $token): bool
{
    return !empty($_SESSION['cal_csrf']) && is_string($token)
        && hash_equals($_SESSION['cal_csrf'], $token);
}

function cal_flash(string $type, string $message): void
{
    $_SESSION['cal_flash'] = ['type' => $type, 'message' => $message];
}

function cal_flash_get(): ?array
{
    $f = $_SESSION['cal_flash'] ?? null;
    unset($_SESSION['cal_flash']);
    return $f;
}

/* ---------- read ---------- */

function cal_list(array $filters = []): array
{
    $sql    = 'SELECT override_id, `date`, day_type, description, is_tuition_day
               FROM academic_calendar_overrides WHERE 1=1';
    $params = [];

    if (!empty($filters['year'])) {
        $sql .= ' AND YEAR(`date`) = ?';
        $params[] = (int)$filters['year'];
    }
    if (!empty($filters['type']) && in_array($filters['type'], CAL_DAY_TYPES, true)) {
        $sql .= ' AND day_type = ?';
        $params[] = $filters['type'];
    }
    if (!empty($filters['q'])) {
        $sql .= ' AND description LIKE ?';
        $params[] = '%' . $filters['q'] . '%';
    }
    $sql .= ' ORDER BY `date` ASC';

    $stmt = cal_pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function cal_years(): array
{
    $stmt = cal_pdo()->query(
        'SELECT DISTINCT YEAR(`date`) AS y FROM academic_calendar_overrides ORDER BY y DESC'
    );
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function cal_find(string $id): ?array
{
    $stmt = cal_pdo()->prepare(
        'SELECT override_id, `date`, day_type, description, is_tuition_day
         FROM academic_calendar_overrides WHERE override_id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/* ---------- validation ---------- */

/**
 * @return array [array $errors, array $clean]
 */
function cal_validate(array $in, ?string $ignoreId = null): array
{
    $errors = [];

    $date = trim($in['date'] ?? '');
    $dt   = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) {
        $errors['date'] = 'Please choose a valid date.';
    } else {
        $sql    = 'SELECT override_id FROM academic_calendar_overrides WHERE `date` = ?';
        $params = [$date];
        if ($ignoreId !== null) {
            $sql .= ' AND override_id <> ?';
            $params[] = $ignoreId;
        }
        $stmt = cal_pdo()->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $errors['date'] = 'This date already has an entry.';
        }
    }

    $type = trim($in['day_type'] ?? '');
    if (!in_array($type, CAL_DAY_TYPES, true)) {
        $errors['day_type'] = 'Please choose a day type.';
    }

    $desc = trim($in['description'] ?? '');
    if ($desc === '') {
        $errors['description'] = 'Description is required.';
    } elseif (mb_strlen($desc) > 255) {
        $errors['description'] = 'Description must be 255 characters or less.';
    }

    $clean = [
        'date'           => $date,
        'day_type'       => $type,
        'description'    => $desc,
        'is_tuition_day' => !empty($in['is_tuition_day']) ? 1 : 0,
    ];

    return [$errors, $clean];
}

/* ---------- create / update / delete ---------- */

function cal_next_id(): string
{
    $n = (int)cal_pdo()->query(
        "SELECT COALESCE(MAX(CAST(SUBSTRING(override_id, 4) AS UNSIGNED)), 0)
         FROM academic_calendar_overrides WHERE override_id LIKE 'HOL%'"
    )->fetchColumn();
    return 'HOL' . str_pad((string)($n + 1), 3, '0', STR_PAD_LEFT);
}

function cal_create(array $c): string
{
    $id   = cal_next_id();
    $stmt = cal_pdo()->prepare(
        'INSERT INTO academic_calendar_overrides
         (override_id, `date`, day_type, description, is_tuition_day)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$id, $c['date'], $c['day_type'], $c['description'], $c['is_tuition_day']]);
    return $id;
}

function cal_update(string $id, array $c): bool
{
    $stmt = cal_pdo()->prepare(
        'UPDATE academic_calendar_overrides
         SET `date` = ?, day_type = ?, description = ?, is_tuition_day = ?
         WHERE override_id = ?'
    );
    return $stmt->execute([$c['date'], $c['day_type'], $c['description'], $c['is_tuition_day'], $id]);
}

function cal_delete(string $id): bool
{
    $stmt = cal_pdo()->prepare('DELETE FROM academic_calendar_overrides WHERE override_id = ?');
    return $stmt->execute([$id]);
}

/* ---------- used by the ATTENDANCE module ---------- */

/**
 * Get the calendar entry for a date (or null if nothing set).
 */
function cal_get_by_date(string $date): ?array
{
    $stmt = cal_pdo()->prepare(
        'SELECT override_id, `date`, day_type, description, is_tuition_day
         FROM academic_calendar_overrides WHERE `date` = ?'
    );
    $stmt->execute([$date]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * TRUE if attendance must NOT be taken on this date
 * (a holiday/closed entry exists with is_tuition_day = 0).
 * Replacement class (is_tuition_day = 1) is NOT blocked.
 */
function cal_is_attendance_blocked(string $date): bool
{
    $row = cal_get_by_date($date);
    return $row !== null && (int)$row['is_tuition_day'] === 0;
}

/**
 * Message to show the user when the date is blocked, or null if allowed.
 */
function cal_attendance_block_message(string $date): ?string
{
    $row = cal_get_by_date($date);
    if ($row === null || (int)$row['is_tuition_day'] === 1) {
        return null;
    }
    return 'Attendance cannot be taken on ' . date('d M Y', strtotime($date))
         . ' (' . $row['day_type'] . ': ' . $row['description'] . ').';
}