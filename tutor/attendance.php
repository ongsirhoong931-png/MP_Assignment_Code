
<?php
require_once __DIR__ . '/../includes/attendance_functions.php';

att_require_role('staff');

$pageTitle = 'Teacher Attendance';

// Get the actual staff ID, e.g. STF001.
$staffId = att_staff_id();

// Fallback: retrieve staff ID directly from user_accounts
// if the login session does not contain staff_id.
if ($staffId === '') {
    $stmt = $pdo->prepare("
        SELECT staff_id
        FROM user_accounts
        WHERE user_id = ?
          AND role = 'staff'
          AND is_active = 1
        LIMIT 1
    ");

    $stmt->execute([att_user_id()]);
    $staffId = (string) ($stmt->fetchColumn() ?: '');
}

$lessons = [];

if ($staffId !== '') {
    $stmt = $pdo->prepare("
        SELECT
            l.*,
            c.grade_level,
            c.section,
            s.session_id,
            s.code_nonce,
            s.status

        FROM attendance_lessons l

        INNER JOIN classes c
            ON c.class_id = l.class_id

        LEFT JOIN attendance_sessions s
            ON s.lesson_id = l.lesson_id

        WHERE l.teacher_id = ?

          AND l.starts_at >= DATE_SUB(
              NOW(),
              INTERVAL 1 DAY
          )

          AND l.starts_at < DATE_ADD(
              NOW(),
              INTERVAL 30 DAY
          )

        ORDER BY l.starts_at ASC
    ");

    $stmt->execute([$staffId]);

    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet"
      href="<?= h(url('assets/css/attendance.css')) ?>">

<div class="att-page">

    <div class="att-heading">
        <div>
            <p class="att-kicker">EDUVEX ACADEMY</p>
            <h1>Teacher Attendance</h1>
            <p>Generate, open and close attendance sessions.</p>
        </div>
    </div>

    <?php att_flash(); ?>

    <?php if ($staffId === ''): ?>
        <div class="att-card">
            Your staff account is not linked to a staff ID.
            Please contact the administrator.
        </div>
    <?php endif; ?>

    <div class="att-grid">

        <?php foreach ($lessons as $lesson): ?>

            <?php
            $allowed = att_within_window($lesson);
            $status = $lesson['status'] ?? 'not_generated';
            $sessionId = $lesson['session_id'] ?? null;

            [$from, $until] = att_window($lesson);
            ?>

            <article class="att-card">

                <div class="att-card-top">
                    <h2>
                        <?= h($lesson['grade_level']) ?>
                        -
                        <?= h($lesson['section']) ?>
                    </h2>

                    <span class="att-status <?= h($status) ?>">
                        <?= h(
                            ucwords(
                                str_replace('_', ' ', $status)
                            )
                        ) ?>
                    </span>
                </div>

                <p>
                    <?= h(
                        $lesson['topic'] ?: 'Class Session'
                    ) ?>
                </p>

                <p>
                    <strong>Class:</strong>
                    <?= h(date(
                        'd M Y, h:i A',
                        strtotime($lesson['starts_at'])
                    )) ?>
                    –
                    <?= h(date(
                        'h:i A',
                        strtotime($lesson['ends_at'])
                    )) ?>
                </p>

                <p class="att-small">
                    Attendance window:
                    <?= h($from->format('h:i A')) ?>
                    –
                    <?= h($until->format('h:i A')) ?>
                </p>

                <?php if ($sessionId): ?>

                    <div class="att-code-box">
                        <span>6-Digit Attendance Code</span>
                        <strong>
                            <?= h(att_code($lesson)) ?>
                        </strong>
                    </div>

                    <div
                        class="att-qr"
                        data-qr-url="<?= h(
                            url(
                                'student/attendance.php?session_id='
                                . (int) $sessionId
                                . '&token='
                                . att_qr_token($lesson)
                            )
                        ) ?>">
                    </div>

                    <p class="att-small att-center">
                        Scan QR or enter the code.
                        Both use this same session.
                    </p>

                <?php endif; ?>

                <form method="post"
                      action="<?= h(
                          url('tutor/attendance_action.php')
                      ) ?>"
                      class="att-actions">

                    <input type="hidden"
                           name="csrf"
                           value="<?= h(att_csrf()) ?>">

                    <input type="hidden"
                           name="lesson_id"
                           value="<?= (int) $lesson['lesson_id'] ?>">

                    <?php if (!$sessionId): ?>

                        <button
                            type="submit"
                            name="action"
                            value="generate"
                            <?= !$allowed ? 'disabled' : '' ?>>
                            Generate Code + QR
                        </button>

                    <?php elseif ($status !== 'open'): ?>

                        <button
                            type="submit"
                            name="action"
                            value="open"
                            <?= !$allowed ? 'disabled' : '' ?>>
                            <?= $status === 'closed'
                                ? 'Reopen Attendance'
                                : 'Open Attendance' ?>
                        </button>

                    <?php else: ?>

                        <button
                            type="submit"
                            name="action"
                            value="close"
                            class="att-danger">
                            Close Attendance
                        </button>

                    <?php endif; ?>

                </form>

                <?php if ($sessionId): ?>
                    <a class="att-link"
                       href="<?= h(url(
                           'tutor/attendance_records.php?session_id='
                           . (int) $sessionId
                       )) ?>">
                        View Attendance Records
                    </a>
                <?php endif; ?>

                <?php if (!$allowed): ?>
                    <p class="att-small">
                        Outside the allowed generation window.
                    </p>
                <?php endif; ?>

            </article>

        <?php endforeach; ?>

    </div>

    <?php if (!$lessons && $staffId !== ''): ?>
        <div class="att-card">
            No scheduled classes found for your staff account.
        </div>
    <?php endif; ?>

</div>

<script
    src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"
    defer>
</script>

<script
    src="<?= h(url('assets/js/attendance.js')) ?>"
    defer>
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
