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
    // 4. GENERATE 6-DIGIT ATTENDANCE CODE
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

        // Generate a unique 6-digit numeric code matching table schema
        $attendanceCode = '';
        $isUnique = false;
        $attempts = 0;

        while (!$isUnique && $attempts < 10) {
            $attendanceCode = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            
            $checkCode = $pdo->prepare("
                SELECT session_id FROM attendance_sessions 
                WHERE attendance_code = ? AND status = 'open'
            ");
            $checkCode->execute([$attendanceCode]);
            if ($checkCode->rowCount() == 0) {
                $isUnique = true;
            }
            $attempts++;
        }

        if (!$isUnique) {
            throw new RuntimeException('Failed to generate a unique attendance code. Please try again.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO attendance_sessions
                (
                    lesson_id,
                    attendance_code,
                    status,
                    generated_by_staff_id
                )
            VALUES (?, ?, 'draft', ?)
        ");

        $stmt->execute([
            $lessonId,
            $attendanceCode,
            $staffId
        ]);

        $message = 'Attendance code generated successfully.';

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