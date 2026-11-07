<?php
require_once __DIR__ . '/functions.php';
?>

<aside class="sidebar">

    <a href="<?= h(url('student_management/index.php')) ?>">
        Students
    </a>

    <a href="<?= h(url('class_management/index.php')) ?>">
        Classes
    </a>

    <a href="<?= h(url('class_management/subjects.php')) ?>">
        Subjects
    </a>

    <a href="<?= h(url('class_management/timetable.php')) ?>">
        Timetable
    </a>

    <?php if (me()['role'] === 'admin'): ?>
        <a href="<?= h(url('auth/users.php')) ?>">
            User Management
        </a>
    <?php endif; ?>

</aside>