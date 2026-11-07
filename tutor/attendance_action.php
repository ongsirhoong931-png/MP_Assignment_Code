
<?php

require_once __DIR__ . '/../includes/attendance_functions.php';

att_require_role('staff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

att_check_csrf();

$lessonId = filter_input(
    INPUT_POST,
    'lesson_id',
    FILTER_VALIDATE_INT
);

$action = $_POST['action'] ?? '';

if (!$lessonId || !in_array(
    $action,
    ['generate', 'open', 'close'],
    true
)) {
    http_response_code(400);
    exit('Invalid request.');
}

try {

    // ==========================================
    // 1. GET CURRENT STAFF ID
    // ==========================================

    $staffId = att_staff_id();

    // Fallback: Get staff ID from user_accounts
    // if it is not stored in the login session.
    if (empty($staffId)) {

        $stmt = $pdo->prepare("
            SELECT staff_id
            FROM user_accounts
            WHERE user_id = ?
              AND role = 'staff'
              AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([att_user_id()]);

        $staffId = (string) $stmt->fetchColumn();
    }

    if (empty($staffId)) {
        throw new RuntimeException(
            'Staff account not found. Please log in again.'
        );
    }

    $pdo->beginTransaction();

    // ==========================================
    // 2. VERIFY LESSON ASSIGNED TO STAFF
    // ==========================================

    // FIX:
    // Compare teacher_id with STF001,
    // not numeric user_id.
    //
    // Removed c.class_teacher_id condition
    // because an assigned subject teacher
    // may not be the class teacher.

    $stmt = $pdo->prepare("
        SELECT l.*
        FROM attendance_lessons l
        INNER JOIN timetable_schedules t
            ON t.schedule_id = l.schedule_id
        WHERE l.lesson_id = ?
          AND l.teacher_id = ?
          AND t.assigned_staff_id = ?
        FOR UPDATE
    ");

    $stmt->execute([
        $lessonId,
        $staffId,
        $staffId
    ]);

    $lesson = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lesson) {
        throw new RuntimeException(
            'Class not found or not assigned to you.'
        );
    }

    // ==========================================
    // 3. CHECK EXISTING ATTENDANCE SESSION
    // ==========================================

    $stmt = $pdo->prepare("
        SELECT *
        FROM attendance_sessions
        WHERE lesson_id = ?
        FOR UPDATE
    ");

    $stmt->execute([$lessonId]);

    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    // ==========================================
    // 4. GENERATE ATTENDANCE CODE + QR
    // ==========================================

    if ($action === 'generate') {

        if (!att_within_window($lesson)) {
            throw new RuntimeException(
                'Code generation is not allowed at this time.'
            );
        }

        if ($session) {
            throw new RuntimeException(
                'Attendance already generated.'
            );
        }

        $stmt = $pdo->prepare("
            INSERT INTO attendance_sessions
                (
                    lesson_id,
                    code_nonce,
                    status,
                    generated_by_staff_id
                )
            VALUES (?, ?, 'draft', ?)
        ");

        $stmt->execute([
            $lessonId,
            bin2hex(random_bytes(16)),
            $staffId
        ]);

        $message = 'Attendance code and QR generated.';

    // ==========================================
    // 5. OPEN ATTENDANCE
    // ==========================================

    } elseif ($action === 'open') {

        if (!$session) {
            throw new RuntimeException(
                'Generate attendance first.'
            );
        }

        if (!att_within_window($lesson)) {
            throw new RuntimeException(
                'Cannot open attendance outside the allowed window.'
            );
        }

        $stmt = $pdo->prepare("
            UPDATE attendance_sessions
            SET status = 'open',
                opened_at = NOW(),
                closed_at = NULL
            WHERE session_id = ?
        ");

        $stmt->execute([
            $session['session_id']
        ]);

        $message = 'Attendance is now open.';

    // ==========================================
    // 6. CLOSE ATTENDANCE
    // ==========================================

    } else {

        if (!$session || $session['status'] !== 'open') {
            throw new RuntimeException(
                'Attendance is not currently open.'
            );
        }

        $stmt = $pdo->prepare("
            UPDATE attendance_sessions
            SET status = 'closed',
                closed_at = NOW()
            WHERE session_id = ?
        ");

        $stmt->execute([
            $session['session_id']
        ]);

        $message = 'Attendance has been closed.';
    }

    // ==========================================
    // 7. SAVE DATABASE CHANGES
    // ==========================================

    $pdo->commit();

    att_message(
        $message,
        'success'
    );

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Attendance action: ' . $e->getMessage()
    );

    att_message(
        $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to update attendance.',
        'error'
    );
}

// ==========================================
// 8. RETURN TO ATTENDANCE MANAGEMENT
// ==========================================

att_redirect('tutor/attendance.php');
