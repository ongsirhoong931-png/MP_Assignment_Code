<?php
require_once __DIR__ . '/includes/functions.php';

if (!logged_in()) {
    go('auth/login.php');
}

$dashboards = [
    'admin' => 'admin',
    'staff' => 'tutor',
    'student' => 'student'
];

$folder = $dashboards[me()['role']] ?? 'student';

go($folder . '/dashboard.php');