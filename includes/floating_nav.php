<?php
require_once __DIR__ . '/functions.php';

$role = logged_in() ? (string) me()['role'] : '';

$dashboard = match ($role) {
    'admin'   => 'admin/dashboard.php',
    'staff'   => 'tutor/dashboard.php',
    'student' => 'student/dashboard.php',
    default   => 'auth/login.php'
};

// Default navigation
$items = [
    ['Home', 'index.php', '⌂'],
    ['Dashboard', $dashboard, '▦']
];

// ========================================
// ADMIN AND STAFF NAVIGATION
// ========================================
if ($role === 'admin' || $role === 'staff') {

    $items[] = [
        'Students',
        'student_management/index.php',
        '♙'
    ];

    // Admin-only Management modules right under Students
    if ($role === 'admin') {
        $items[] = [
            'Admin Management',
            'admin_management/index.php',
            '🛡'
        ];

        $items[] = [
            'Tutor Management',
            'tutor_management/index.php',
            '🗂'
        ];
    }

    $items[] = [
        'Classes',
        'class_management/index.php',
        '▤'
    ];

    $items[] = [
        'Subjects',
        'class_management/subjects.php',
        '▥'
    ];

    $items[] = [
        'Timetable',
        'class_management/timetable.php',
        '◷'
    ];
}

// ========================================
// ADMIN ATTENDANCE
// ========================================
if ($role === 'admin') {
    $items[] = [
        'Attendance Overview',
        'admin/attendance.php',
        '▦'
    ];
}

// ========================================
// STAFF / TEACHER NAVIGATION
// ========================================
if ($role === 'staff') {

    $items[] = [
        'Attendance Management',
        'tutor/attendance.php',
        '▦'
    ];
}

// ========================================
// STUDENT NAVIGATION
// ========================================
if ($role === 'student') {

    $items[] = [
        'Mark Attendance',
        'student/attendance.php',
        '✓'
    ];
}

// ========================================
// ROLE-BASED PROFILE NAVIGATION
// ========================================
if ($role === 'admin') {
    $items[] = ['My Profile', 'admin/profile.php', '👤'];
} elseif ($role === 'staff') {
    $items[] = ['My Profile', 'tutor/profile.php', '👤'];
} elseif ($role === 'student') {
    $items[] = ['My Profile', 'student_management/view_student.php?id=' . urlencode(me()['student_id'] ?? ''), '👤'];
}

// ========================================
// GUEST NAVIGATION
// ========================================
if (!$role) {

    $items[] = [
        'Features',
        'index.php#features',
        '✧'
    ];

    $items[] = [
        'About',
        'index.php#about',
        'ⓘ'
    ];
}

// ========================================
// LOGIN / LOGOUT
// ========================================
$items[] = [
    $role ? 'Logout' : 'Sign In',
    $role ? 'auth/logout.php' : 'auth/login.php',
    $role ? '↪' : '→'
];

// ========================================
// DETECT CURRENT PAGE
// ========================================
$currentPath = parse_url(
    $_SERVER['REQUEST_URI'] ?? '',
    PHP_URL_PATH
) ?: '';

?>

<!-- ======================================
     FLOATING LEFT NAVIGATION
======================================= -->

<aside
    class="floating-nav"
    id="floating-navigation"
    aria-label="Main navigation"
    data-floating-nav>

    <nav class="floating-nav-links">

        <?php foreach ($items as [$label, $path, $symbol]): ?>

            <?php

            $targetPath = parse_url(
                url($path),
                PHP_URL_PATH
            ) ?: '';

            $isActive = (
                $currentPath === $targetPath
                && !in_array(
                    $label,
                    [
                        'Logout',
                        'Sign In',
                        'Features',
                        'About'
                    ],
                    true
                )
            );

            ?>

            <a
                class="float-link<?= $isActive ? ' active' : '' ?>"
                href="<?= h(url($path)) ?>"
                title="<?= h($label) ?>"
                <?= $isActive ? 'aria-current="page"' : '' ?>>

                <span
                    class="float-icon"
                    aria-hidden="true">
                    <?= h($symbol) ?>
                </span>

                <span class="float-label">
                    <?= h($label) ?>
                </span>

            </a>

        <?php endforeach; ?>

    </nav>

</aside>