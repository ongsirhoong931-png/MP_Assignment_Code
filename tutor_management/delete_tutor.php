<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$id = trim($_GET['id'] ?? '');

if ($id === '') {
    http_response_code(400);
    exit('Tutor ID is required.');
}

// Check if tutor exists
$stmt = $pdo->prepare("SELECT * FROM staff WHERE staff_id = ?");
$stmt->execute([$id]);
$tutor = $stmt->fetch();

if (!$tutor) {
    http_response_code(404);
    exit('Tutor not found.');
}

// Prevent deleting your own currently logged-in account
if ((me()['staff_id'] ?? '') === $id || (isset(me()['id']) && me()['id'] === $id)) {
    notice('You cannot delete your own currently active tutor account.');
    go('tutor_management/index.php');
}

try {
    $pdo->beginTransaction();

    // 1. Delete associated user account first (if exists)
    $stmtAccount = $pdo->prepare("DELETE FROM user_accounts WHERE staff_id = ?");
    $stmtAccount->execute([$id]);

    // 2. Delete staff record
    $stmtTutor = $pdo->prepare("DELETE FROM staff WHERE staff_id = ?");
    $stmtTutor->execute([$id]);

    $pdo->commit();

    notice('Tutor deleted successfully.');
    go('tutor_management/index.php');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    notice('Unable to delete tutor due to database constraints.');
    go('tutor_management/index.php');
}