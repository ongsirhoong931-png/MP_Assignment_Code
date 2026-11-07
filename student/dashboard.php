<?php
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('student');

$pageTitle = 'Student Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<h1>Student Dashboard</h1>

<p>Welcome, <?= h(me()['username']) ?>.</p>

<?php require __DIR__ . '/../includes/footer.php'; ?>