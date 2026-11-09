<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

// Fetch all attendance records across the academy for admin oversight
$stmt = $pdo->query("
    SELECT a.*, s.student_name, c.grade_level, c.section 
    FROM attendance a
    JOIN students s ON a.student_id = s.student_id
    JOIN classes c ON a.class_id = c.class_id
    ORDER BY a.attendance_date DESC
");
$attendance_records = $stmt->fetchAll();

$pageTitle = 'Admin Attendance Overview';
require __DIR__ . '/../includes/header.php';
?>

<h1>Admin Attendance Overview</h1>
<p>Monitor all student attendance records across the academy.</p>

<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Student Name</th>
            <th>Class</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($attendance_records)): ?>
            <tr><td colspan="4">No attendance records found.</td></tr>
        <?php else: ?>
            <?php foreach ($attendance_records as $record): ?>
                <tr>
                    <td><?= h($record['attendance_date']) ?></td>
                    <td><?= h($record['student_name']) ?></td>
                    <td><?= h($record['grade_level'] . ' - ' . $record['section']) ?></td>
                    <td><?= h($record['status']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>