<?php
require_once __DIR__ . '/../includes/calendar_functions.php';
cal_require_admin();

// Delete only works via POST (button with confirm), never by opening the URL
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!cal_csrf_check($_POST['csrf'] ?? null)) {
    cal_flash('error', 'Session expired. Please try again.');
    header('Location: index.php');
    exit;
}

$id = $_POST['id'] ?? '';

if ($id === '' || !cal_find($id)) {
    cal_flash('error', 'Holiday not found.');
} else {
    try {
        cal_delete($id);
        cal_flash('success', 'Holiday deleted.');
    } catch (PDOException $e) {
        cal_flash('error', 'Could not delete. Please try again.');
    }
}

header('Location: index.php');
exit;