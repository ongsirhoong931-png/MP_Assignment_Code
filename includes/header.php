<?php
require_once __DIR__ . '/functions.php';

$role = logged_in() ? (string) me()['role'] : '';

// Determine profile link based on user role
$profileUrl = '#';
if ($role === 'admin') {
    $profileUrl = url('admin/profile.php');
} elseif ($role === 'staff') {
    $profileUrl = url('tutor/profile.php');
} elseif ($role === 'student') {
    $studentId = me()['student_id'] ?? '';
    $profileUrl = url('student_management/view_student.php?id=' . urlencode($studentId));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        <?= h($pageTitle ?? APP_NAME) ?>
        - <?= h(APP_NAME) ?>
    </title>

    <link rel="stylesheet"
          href="<?= h(url('assets/css/style.css')) ?>">

    <link rel="stylesheet"
          href="<?= h(url('assets/css/responsive.css')) ?>">
</head>

<body>

<?php require __DIR__ . '/navbar.php'; ?>

<?php require __DIR__ . '/floating_nav.php'; ?>

<main class="container app-content">

<?php
if (function_exists('show_notice')) {
    show_notice();
}
?>