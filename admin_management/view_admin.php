<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$id = trim($_GET['id'] ?? '');

if ($id === '') {
    http_response_code(400);
    exit('Admin ID is required.');
}

$stmt = $pdo->prepare("
    SELECT a.*, 
           u.username, u.is_active
    FROM admins a
    LEFT JOIN user_accounts u ON u.admin_id = a.admin_id
    WHERE a.admin_id = ?
");

$stmt->execute([$id]);
$admin = $stmt->fetch();

if (!$admin) {
    http_response_code(404);
    exit('Administrator not found.');
}

$pageTitle = 'View Admin Profile';
require __DIR__ . '/../includes/header.php';
?>

<h1>Admin Profile</h1>

<section class="panel">

    <p><strong>Admin ID:</strong>
        <?= h($admin['admin_id']) ?></p>

    <p><strong>Name:</strong>
        <?= h($admin['admin_name']) ?></p>

    <p><strong>Email:</strong>
        <?= h($admin['admin_email']) ?></p>

    <p><strong>Phone:</strong>
        <?= h($admin['admin_phone_number']) ?></p>

    <h2>Login Credentials</h2>

    <p><strong>Login Username:</strong>
        <code><?= h($admin['username'] ?? 'No Login Account Assigned') ?></code></p>

    <p><strong>Account Status:</strong>
        <?php
        if ($admin['is_active'] === null) {
            echo 'No Account';
        } else {
            echo $admin['is_active'] ? 'Active' : 'Inactive';
        }
        ?>
    </p>

    <p class="att-small" style="color: #a0aec0;">
        <em>Note: Passwords are securely hashed using bcrypt in the database and cannot be viewed in plaintext for security reasons.</em>
    </p>

    <br>
    <a class="button" href="<?= h(url('admin_management/index.php')) ?>">
        Back to Admin List
    </a>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>