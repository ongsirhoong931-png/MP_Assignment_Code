<?php
require_once __DIR__ . '/../includes/attendance_functions.php';

att_require_role('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

att_check_csrf();

// Retrieve the correct student_id string (e.g. STU002) for this user account
$studentId = att_student_id();
if (empty($studentId)) {
    $stmtAcc = $pdo->prepare("SELECT student_id FROM user_accounts WHERE user_id = ? LIMIT 1");
    $stmtAcc->execute([att_user_id()]);
    $studentId = (string) ($stmtAcc->fetchColumn() ?: '');
}

$sessionId = filter_input(
    INPUT_POST,
    'session_id',
    FILTER_VALIDATE_INT
);

try {

    if (!$sessionId) {
        throw new RuntimeException('Invalid attendance request.');
    }

    // Verify that the student is enrolled in the class.
    $session = att_student_session(
        $pdo,
        $sessionId,
        $studentId
    );

    if (!$session) {
        throw new RuntimeException(
            'You are not enrolled in this class.'
        );
    }

    $code = $_POST['attendance_code'] ?? '';

    if (!is_string($code) ||
        !preg_match('/^[0-9]{6}$/D', $code)) {
        throw new RuntimeException(
            'Please enter a valid six-digit code.'
        );
    }

    // Allow no more than 5 code attempts per 15 minutes.
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM attendance_attempts
        WHERE student_id = ?
          AND attempted_at >= DATE_SUB(
              NOW(), INTERVAL 15 MINUTE
          )
    ");

    $stmt->execute([$studentId]);

    if ((int) $stmt->fetchColumn() >= 5) {
        throw new RuntimeException(
            'Too many attempts. Please try again later.'
        );
    }

    // Record the attempt before checking the code.
    $stmt = $pdo->prepare("
        INSERT INTO attendance_attempts (student_id, session_id, attendance_method)
        VALUES (?, ?, 'code')
    ");

    $stmt->execute([$studentId, $sessionId]);

    if (!hash_equals(att_code($session), $code)) {
        throw new RuntimeException(
            'Incorrect attendance code.'
        );
    }

    $pdo->beginTransaction();

    // Lock session while validating its current state.
    $stmt = $pdo->prepare("
        SELECT s.status, l.starts_at, l.ends_at
        FROM attendance_sessions s
        JOIN attendance_lessons l
            ON l.lesson_id = s.lesson_id
        WHERE s.session_id = ?
        FOR UPDATE
    ");

    $stmt->execute([$sessionId]);
    $locked = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$locked || $locked['status'] !== 'open') {
        throw new RuntimeException(
            'Teacher has not opened attendance.'
        );
    }

    if (!att_within_window($locked)) {
        throw new RuntimeException(
            'Attendance submission window has expired.'
        );
    }

    // Insert into attendance_submissions (or attendance_records based on your schema setup)
    $stmt = $pdo->prepare("
        INSERT INTO attendance_submissions
            (
                session_id,
                student_id,
                attendance_status,
                attendance_method
            )
        VALUES (?, ?, 'Present', 'code')
    ");

    $stmt->execute([
        $sessionId,
        $studentId
    ]);

    $pdo->commit();

    att_message(
        'Attendance marked successfully!',
        'success'
    );

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e instanceof PDOException
        && $e->getCode() === '23000') {
        att_message(
            'You have already marked attendance for this class.',
            'error'
        );
    } else {
        error_log('Attendance submission: ' . $e->getMessage());

        att_message(
            $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Unable to mark attendance.',
            'error'
        );
    }
}

att_redirect('student/attendance.php');