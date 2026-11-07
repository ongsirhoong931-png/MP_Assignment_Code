
<?php
require_once __DIR__ . '/../includes/attendance_functions.php';

att_require_role('staff');

$sessionId = filter_input(
    INPUT_GET,
    'session_id',
    FILTER_VALIDATE_INT
);

if (!$sessionId) {
    http_response_code(400);
    exit('Invalid session.');
}

$stmt = $pdo->prepare("
    SELECT l.lesson_id
    FROM attendance_sessions s
    JOIN attendance_lessons l
        ON l.lesson_id = s.lesson_id
    WHERE s.session_id = ?
");

$stmt->execute([$sessionId]);
$lessonId = (int) $stmt->fetchColumn();

$lesson = att_teacher_lesson(
    $pdo,
    $lessonId,
    att_user_id()
);

if (!$lesson ||
    (int) $lesson['session_id'] !== $sessionId) {
    http_response_code(403);
    exit('Access denied.');
}

$stmt = $pdo->prepare("
    SELECT r.student_id,
           r.attendance_status,
           r.attendance_method,
           r.marked_at
    FROM attendance_records r
    WHERE r.session_id = ?
    ORDER BY r.marked_at DESC
");

$stmt->execute([$sessionId]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Attendance Records';

require __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet"
      href="<?= h(url('assets/css/attendance.css')) ?>">

<div class="att-page">

    <p class="att-kicker">EDUVEX ACADEMY</p>

    <h1>Attendance Records</h1>

    <p>
        Class:
        <?= h($lesson['grade_level']) ?>
        -
        <?= h($lesson['section']) ?>
    </p>

    <div class="att-card">

        <h2>
            Present Students:
            <?= count($records) ?>
        </h2>

        <div class="table-wrap">

            <table class="att-table">

                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Status</th>
                        <th>Method</th>
                        <th>Time</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($records as $record): ?>

                        <tr>
                            <td>
                                <?= (int) $record['student_id'] ?>
                            </td>

                            <td>
                                <?= h(ucfirst(
                                    $record['attendance_status']
                                )) ?>
                            </td>

                            <td>
                                <?= h(strtoupper(
                                    $record['attendance_method']
                                )) ?>
                            </td>

                            <td>
                                <?= h($record['marked_at']) ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    <?php if (!$records): ?>

                        <tr>
                            <td colspan="4">
                                No attendance submissions yet.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <a href="<?= h(url('tutor/attendance.php')) ?>"
       class="att-link">
        Back to Attendance
    </a>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
