<?php
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('staff');

$pageTitle = 'Tutor Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<h1>Tutor Dashboard</h1>

<p>Welcome, <?= h(me()['username']) ?>.</p>

<?php require __DIR__ . '/../includes/sidebar.php'; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>