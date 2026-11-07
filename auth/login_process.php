<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_post();

$login = trim($_POST['login'] ?? '');
$password = (string)($_POST['password'] ?? '');

$stmt = $pdo->prepare(
    'SELECT *
     FROM user_accounts
     WHERE (username = ? OR email = ?)
       AND is_active = 1
     LIMIT 1'
);

$stmt->execute([$login, $login]);
$user = $stmt->fetch();

if (
    !$user ||
    !password_verify($password, $user['password_hash'])
) {
    notice('Invalid username or password.');
    go('auth/login.php');
}

session_regenerate_id(true);

$_SESSION['user_id'] = $user['user_id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];

go('index.php');