<?php
// =====================================================
// EDUVEX ACADEMY
// ATTENDANCE SYSTEM FUNCTIONS - FIXED VERSION
// =====================================================

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';

date_default_timezone_set('Asia/Kuala_Lumpur');


// =====================================================
// 1. START SESSION
// =====================================================

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


// =====================================================
// 2. ATTENDANCE SECURITY SECRET
// =====================================================

function att_secret(): string
{
    static $secret = null;

    if ($secret !== null) {
        return $secret;
    }

    if (defined('ATTENDANCE_SECRET')) {
        $configured = (string) ATTENDANCE_SECRET;

        if (
            strlen($configured) >= 32 &&
            $configured !== 'REPLACE_WITH_A_LONG_RANDOM_SECRET'
        ) {
            $secret = $configured;
            return $secret;
        }
    }

    $secretFile = __DIR__ . '/../config/attendance_secret.key';
    $configDir = dirname($secretFile);

    if (!is_dir($configDir)) {
        throw new RuntimeException(
            'Attendance config directory does not exist.'
        );
    }

    if (!is_file($secretFile)) {
        $newSecret = bin2hex(random_bytes(32));

        $created = @file_put_contents(
            $secretFile,
            $newSecret,
            LOCK_EX
        );

        if ($created === false) {
            throw new RuntimeException(
                'Cannot create attendance security key. ' .
                'Check config folder permissions.'
            );
        }
    }

    $savedSecret = @file_get_contents($secretFile);

    if ($savedSecret === false) {
        throw new RuntimeException(
            'Cannot read attendance security key.'
        );
    }

    $savedSecret = trim($savedSecret);

    if (strlen($savedSecret) < 32) {
        throw new RuntimeException(
            'Attendance security key is invalid.'
        );
    }

    $secret = $savedSecret;

    return $secret;
}

att_secret();


// =====================================================
// 3. GET CURRENT ACCOUNT ID
// =====================================================

function att_user_id(): int
{
    $user = me();

    return (int) (
        $user['user_id']
        ?? $user['id']
        ?? 0
    );
}


// =====================================================
// 4. GET CURRENT STAFF ID
// =====================================================

function att_staff_id(): string
{
    $user = me();

    return (string) (
        $user['staff_id']
        ?? ''
    );
}


// =====================================================
// 5. GET CURRENT STUDENT ID
// =====================================================

function att_student_id(): string
{
    $user = me();

    return (string) (
        $user['student_id']
        ?? ''
    );
}


// =====================================================
// 6. REQUIRE USER ROLE
// =====================================================

function att_require_role(string $role): void
{
    if (!logged_in()) {
        http_response_code(403);
        exit('Please log in first.');
    }

    $user = me();

    if (($user['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('Access denied.');
    }
}


// =====================================================
// 7. GENERATE CSRF TOKEN
// =====================================================

function att_csrf(): string
{
    if (empty($_SESSION['att_csrf'])) {
        $_SESSION['att_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['att_csrf'];
}


// =====================================================
// 8. VALIDATE CSRF TOKEN
// =====================================================

function att_check_csrf(): void
{
    if (
        !isset($_POST['csrf']) ||
        !is_string($_POST['csrf']) ||
        !hash_equals(
            att_csrf(),
            $_POST['csrf']
        )
    ) {
        http_response_code(419);
        exit('Invalid security token.');
    }
}


// =====================================================
// 9. ATTENDANCE TIME WINDOW
// =====================================================

function att_window(array $lesson): array
{
    $start = new DateTimeImmutable(
        $lesson['starts_at']
    );

    $end = new DateTimeImmutable(
        $lesson['ends_at']
    );

    return [
        $start->modify('-2 hours'),
        $end->modify('+2 hours')
    ];
}


// =====================================================
// 10. CHECK ATTENDANCE WINDOW
// =====================================================

function att_within_window(array $lesson): bool
{
    [$from, $until] = att_window($lesson);

    $now = new DateTimeImmutable();

    return (
        $now >= $from &&
        $now <= $until
    );
}


// =====================================================
// 11. RETURN SIX-DIGIT ATTENDANCE CODE
// =====================================================

function att_code(array $session): string
{
    // Directly return the generated 6-digit attendance code from the database session record
    return (string) ($session['attendance_code'] ?? '------');
}


// =====================================================
// 12. GET TEACHER LESSON
// =====================================================

function att_teacher_lesson(
    PDO $pdo,
    int $lessonId,
    string $teacherId
): ?array {

    $stmt = $pdo->prepare("
        SELECT
            l.*,
            s.session_id,
            s.attendance_code,
            s.status,
            s.opened_at,
            s.closed_at,
            c.grade_level,
            c.section,
            c.academic_year

        FROM attendance_lessons l

        INNER JOIN classes c
            ON c.class_id = l.class_id

        LEFT JOIN attendance_sessions s
            ON s.lesson_id = l.lesson_id

        WHERE l.lesson_id = ?
          AND l.teacher_id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $lessonId,
        $teacherId
    ]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result ?: null;
}


// =====================================================
// 13. CHECK STUDENT ENROLMENT
// =====================================================

function att_student_enrolled(
    PDO $pdo,
    string $studentId,
    string $classId
): bool {

    $stmt = $pdo->prepare("
        SELECT 1

        FROM student_enrolments

        WHERE student_id = ?
          AND class_id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $studentId,
        $classId
    ]);

    return (bool) $stmt->fetchColumn();
}


// =====================================================
// 14. GET STUDENT ATTENDANCE SESSION
// =====================================================

function att_student_session(
    PDO $pdo,
    int $sessionId,
    string $studentId
): ?array {

    $stmt = $pdo->prepare("
        SELECT
            s.*,
            l.class_id,
            l.teacher_id,
            l.starts_at,
            l.ends_at,
            l.topic,
            c.grade_level,
            c.section

        FROM attendance_sessions s

        INNER JOIN attendance_lessons l
            ON l.lesson_id = s.lesson_id

        INNER JOIN classes c
            ON c.class_id = l.class_id

        WHERE s.session_id = ?

        LIMIT 1
    ");

    $stmt->execute([$sessionId]);

    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        return null;
    }

    $classId = (string) $session['class_id'];

    if (
        !att_student_enrolled(
            $pdo,
            $studentId,
            $classId
        )
    ) {
        return null;
    }

    return $session;
}


// =====================================================
// 15. STORE ATTENDANCE MESSAGE
// =====================================================

function att_message(
    string $message,
    string $type
): void {

    $_SESSION['att_flash'] = [
        'message' => $message,
        'type' => $type
    ];
}


// =====================================================
// 16. DISPLAY ATTENDANCE MESSAGE
// =====================================================

function att_flash(): void
{
    if (empty($_SESSION['att_flash'])) {
        return;
    }

    $flash = $_SESSION['att_flash'];

    unset($_SESSION['att_flash']);

    $class = (
        ($flash['type'] ?? '') === 'success'
    )
        ? 'att-success'
        : 'att-error';

    echo '<div class="att-alert '
        . $class
        . '">'
        . h((string) ($flash['message'] ?? ''))
        . '</div>';
}


// =====================================================
// 17. ATTENDANCE REDIRECT
// =====================================================

function att_redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}


// =====================================================
// END OF ATTENDANCE FUNCTIONS
// =====================================================