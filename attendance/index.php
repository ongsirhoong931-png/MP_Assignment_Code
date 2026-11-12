<?php
require_once __DIR__ . '/../includes/attendance_module.php';

allow_roles('admin', 'staff', 'student');

$today = am_today();
$counts = [];

foreach (AM_STATUSES as $status) {
    $counts[$status] = am_records_count($pdo, [
        'status'    => $status,
        'date_from' => $today,
        'date_to'   => $today,
    ]);
}

$tiles = am_is_student()
    ? [
        ['view_attendance.php', 'My Records', 'Every attendance record of yours.'],
        ['search_attendance.php', 'Search', 'Find records by date or status.'],
        ['attendance_history.php', 'My History', 'Full history with your totals.'],
        ['attendance_percentage.php', 'My Percentage', 'Attendance percentage per class.'],
    ]
    : [
        ['mark_attendance.php', '1. Mark Attendance', 'Record each student for a class and day.'],
        ['view_attendance.php', '2. View Records', 'Browse and filter attendance records.'],
        ['view_attendance.php', '3. Update Records', 'Open a record and use Edit.'],
        ['view_attendance.php', '4. Delete Records', 'Open a record and use Delete.'],
        ['search_attendance.php', '5. Search Records', 'Search by student, class, status or date.'],
        ['subject_attendance.php', '6. Mark by Subject', 'Record attendance for one timetable subject.'],
        ['bulk_attendance.php', '7. Bulk Marking', 'Apply one status to many students at once.'],
        ['attendance_history.php', '8. Student History', 'Full attendance history of one student.'],
        ['attendance_percentage.php', '9. Percentage', 'Attendance percentage per student.'],
    ];

if (am_is_admin()) {
    $tiles[] = [
        'attendance_override.php',
        '10. Admin Override',
        'Correct any record with a recorded reason.',
    ];
}

$pageTitle = 'Attendance Recording';
require __DIR__ . '/../includes/header.php';
am_nav('index');
?>

<h1>Attendance Recording</h1>

<h2>Today (<?= h($today) ?>)</h2>
<div class="am-summary">
    <?php foreach ($counts as $status => $n): ?>
        <div><b><?= (int) $n ?></b><?= h($status) ?></div>
    <?php endforeach; ?>
</div>

<div class="am-grid">
    <?php foreach ($tiles as [$file, $title, $text]): ?>
        <a class="am-tile" href="<?= h(url('attendance/' . $file)) ?>">
            <strong><?= h($title) ?></strong>
            <span><?= h($text) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>