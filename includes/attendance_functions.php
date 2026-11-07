
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

// Keep this file private.
// Do not delete it after attendance codes are generated.

function att_secret(): string
{
    static $secret = null;

    if ($secret !== null) {
        return $secret;
    }

    // Use an existing valid constant if available.
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

    // Otherwise, use a persistent local key file.
    $secretFile = __DIR__
        . '/../config/attendance_secret.key';

    $configDir = dirname($secretFile);

    if (!is_dir($configDir)) {
        throw new RuntimeException(
            'Attendance config directory does not exist.'
        );
    }

    // Create the key only when it does not exist.
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

// Check the secret when this file is loaded.
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
        $_SESSION['att_csrf'] =
            bin2hex(random_bytes(32));
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

// Starts 2 hours before the lesson.
// Ends 2 hours after the lesson.

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
// 11. GENERATE SIX-DIGIT ATTENDANCE CODE
// =====================================================

function att_code(array $session): string
{
    $message =
        'code:' .
        $session['session_id'] .
        ':' .
        $session['code_nonce'];

    $hash = hash_hmac(
        'sha256',
        $message,
        att_secret()
    );

    // Generate six digits, including leading zeroes.
    $number = hexdec(
        substr($hash, 0, 12)
    ) % 1000000;

    return str_pad(
        (string) $number,
        6,
        '0',
        STR_PAD_LEFT
    );
}


// =====================================================
// 12. GENERATE QR TOKEN
// =====================================================

function att_qr_token(array $session): string
{
    $message =
        'qr:' .
        $session['session_id'] .
        ':' .
        $session['code_nonce'];

    return hash_hmac(
        'sha256',
        $message,
        att_secret()
    );
}


// =====================================================
// 13. GET TEACHER LESSON
// =====================================================

// lesson_id is numeric.
// teacher_id is a string such as STF001.

function att_teacher_lesson(
    PDO $pdo,
    int $lessonId,
    string $teacherId
): ?array {

    $stmt = $pdo->prepare("
        SELECT
            l.*,
            s.session_id,
            s.code_nonce,
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
// 14. CHECK STUDENT ENROLMENT
// =====================================================

// student_id: STU001
// class_id: CLS001

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
// 15. GET STUDENT ATTENDANCE SESSION
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
// 16. STORE ATTENDANCE MESSAGE
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
// 17. DISPLAY ATTENDANCE MESSAGE
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
// 18. ATTENDANCE REDIRECT
// =====================================================

function att_redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}


// =====================================================
// END OF ATTENDANCE FUNCTIONS
// =====================================================
