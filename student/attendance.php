<?php
require_once __DIR__ . '/../includes/attendance_functions.php';

if (!logged_in()) {
    $_SESSION['after_login_redirect'] =
        $_SERVER['REQUEST_URI'] ?? url('student/attendance.php');

    att_redirect('auth/login.php');
}

att_require_role('student');

$pageTitle = 'Mark Attendance';

// Retrieve the correct student_id string (e.g. STU002) for this user account
$studentId = att_student_id();
if (empty($studentId)) {
    // Fallback: fetch student_id from user_accounts if session helper is blank
    $stmtAcc = $pdo->prepare("SELECT student_id FROM user_accounts WHERE user_id = ? LIMIT 1");
    $stmtAcc->execute([att_user_id()]);
    $studentId = (string) ($stmtAcc->fetchColumn() ?: '');
}

$sessionId = filter_input(
    INPUT_GET,
    'session_id',
    FILTER_VALIDATE_INT
);

// Fetches only OPEN sessions for classes the student is enrolled in
$stmt = $pdo->prepare("
    SELECT s.session_id, s.status,
           l.starts_at, l.ends_at,
           c.grade_level, c.section
    FROM attendance_sessions s
    JOIN attendance_lessons l
        ON l.lesson_id = s.lesson_id
    JOIN classes c ON c.class_id = l.class_id
    JOIN student_enrolments e
        ON e.class_id = l.class_id
    WHERE e.student_id = ?
      AND s.status = 'open'
      AND l.starts_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
      AND l.starts_at < DATE_ADD(NOW(), INTERVAL 7 DAY)
    ORDER BY l.starts_at
");

$stmt->execute([$studentId]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet"
      href="<?= h(url('assets/css/attendance.css')) ?>">

<div class="att-page att-student">

    <p class="att-kicker">EDUVEX ACADEMY</p>

    <h1>Mark Attendance</h1>

    <p>
        Enter your teacher's six-digit code to mark your attendance.
    </p>

    <?php att_flash(); ?>

    <div class="att-card">

        <h2>Enter Attendance Code</h2>

        <form method="post"
              action="<?= h(url('student/submit_attendance.php')) ?>">

            <input type="hidden"
                   name="csrf"
                   value="<?= h(att_csrf()) ?>">

            <input type="hidden"
                   name="method"
                   value="code">

            <label for="session_id">
                Select Class Session
            </label>

            <select id="session_id"
                    name="session_id"
                    required>

                <option value="">Choose your class</option>

                <?php foreach ($sessions as $session): ?>

                    <option value="<?= (int) $session['session_id'] ?>"
                        <?= $sessionId == $session['session_id']
                            ? 'selected'
                            : '' ?>>

                        <?= h($session['grade_level']) ?>
                        -
                        <?= h($session['section']) ?>

                        (
                        <?= h(date(
                            'd M, h:i A',
                            strtotime($session['starts_at'])
                        )) ?>
                        )

                    </option>

                <?php endforeach; ?>

            </select>

            <label for="attendance_code">
                6-Digit Attendance Code
            </label>

            <input
                type="text"
                id="attendance_code"
                name="attendance_code"
                inputmode="numeric"
                pattern="[0-9]{6}"
                maxlength="6"
                placeholder="000000"
                autocomplete="off"
                required>

            <button type="submit">
                Submit Attendance
            </button>

        </form>

    </div>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>