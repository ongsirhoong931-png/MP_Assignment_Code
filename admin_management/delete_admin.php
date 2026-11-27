<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$id = trim($_GET['id'] ?? '');

if ($id === '') {
    http_response_code(400);
    exit('Admin ID is required.');
}

// Check if admin exists
$stmt = $pdo->prepare("SELECT * FROM admins WHERE admin_id = ?");
$stmt->execute([$id]);
$admin = $stmt->fetch();

if (!$admin) {
    http_response_code(404);
    exit('Administrator not found.');
}

// Prevent deleting your own currently logged-in account
if (me()['admin_id'] === $id || (isset(me()['id']) && me()['id'] === $id)) {
    notice('You cannot delete your own currently active administrator account.');
    go('admin_management/index.php');
}

try {
    $pdo->beginTransaction();

    // 1. Delete associated user account first (if exists)
    $stmtAccount = $pdo->prepare("DELETE FROM user_accounts WHERE admin_id = ?");
    $stmtAccount->execute([$id]);

    // 2. Delete admin profile record
    $stmtAdmin = $pdo->prepare("DELETE FROM admins WHERE admin_id = ?");
    $stmtAdmin->execute([$id]);

    $pdo->commit();

    notice('Administrator deleted successfully.');
    go('admin_management/index.php');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    notice('Unable to delete administrator due to database constraints.');
    go('admin_management/index.php');
}