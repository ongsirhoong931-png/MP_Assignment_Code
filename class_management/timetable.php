<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin', 'staff', 'student');

$role = (string) (me()['role'] ?? '');

// ONLY admins can manage/add schedules. Staff are now view-only.
$canManage = ($role === 'admin');

$sql = "
    SELECT
        t.*,
        c.grade_level,
        c.section,
        sub.subject_name,
        st.staff_name
    FROM timetable_schedules t
    JOIN classes c
        ON c.class_id = t.class_id
    JOIN subjects sub
        ON sub.subject_id = t.subject_id
    LEFT JOIN staff st
        ON st.staff_id = t.assigned_staff_id
";

$params = [];

if ($role === 'student') {
    $sql .= "
        JOIN student_enrolments e
            ON e.class_id = t.class_id
        JOIN user_accounts u
            ON u.student_id = e.student_id
        WHERE u.user_id = ?
          AND u.role = 'student'
    ";

    $params[] = (int) (
        me()['user_id'] ?? me()['id'] ?? 0
    );
}

$sql .= "
    ORDER BY FIELD(
        t.day_of_week,
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday'
    ), t.start_time
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Tuition Timetable';
require __DIR__ . '/../includes/header.php';
?>

<h1>Tuition Timetable</h1>

<?php if ($canManage): ?>
    <p>
        <a class="button"
           href="<?= h(url('class_management/add_timetable.php')) ?>">
            + Add Schedule
        </a>
    </p>
<?php endif; ?>

<div class="table-wrap">
    <table>
        <tr>
            <th>Schedule ID</th>
            <th>Class</th>
            <th>Subject</th>
            <th>Day</th>
            <th>Time</th>
            <th>Room</th>
            <th>Tutor</th>

            <?php if ($role === 'admin' || $role === 'staff'): ?>
                <th>Actions</th>
            <?php endif; ?>
        </tr>

        <?php if (empty($schedules)): ?>
            <tr>
                <td colspan="<?= ($role === 'admin' || $role === 'staff') ? 8 : 7 ?>">
                    No timetable schedules found.
                </td>
            </tr>
        <?php endif; ?>

        <?php foreach ($schedules as $s): ?>
            <tr>
                <td>
                    <?= h($s['schedule_id']) ?>
                </td>

                <td>
                    <?= h($s['class_id']) ?> -
                    <?= h($s['grade_level']) ?>
                    <?= h($s['section']) ?>
                </td>

                <td>
                    <?= h($s['subject_name']) ?>
                </td>

                <td>
                    <?= h($s['day_of_week']) ?>
                </td>

                <td>
                    <?= h(substr($s['start_time'], 0, 5)) ?>
                    -
                    <?= h(substr($s['end_time'], 0, 5)) ?>
                </td>

                <td>
                    <?= h($s['room_number'] ?? '') ?>
                </td>

                <td>
                    <?= h($s['staff_name'] ?? 'Unassigned') ?>
                </td>

                <?php if ($role === 'admin'): ?>
                    <td>
                        <a href="<?= h(url(
                            'class_management/edit_timetable.php?id=' .
                            urlencode($s['schedule_id'])
                        )) ?>">
                            Edit
                        </a>

                        |

                        <a href="<?= h(url(
                            'class_management/delete_timetable.php?id=' .
                            urlencode($s['schedule_id'])
                        )) ?>">
                            Delete
                        </a>
                    </td>

                <?php elseif ($role === 'staff'): ?>
                    <td>
                        View Only
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php
require __DIR__ . '/../includes/footer.php';
?>