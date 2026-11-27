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
    SELECT a.*, u.username, u.is_active, u.email as account_email
    FROM admins a
    LEFT JOIN user_accounts u ON a.admin_id = u.admin_id
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

<div style="max-width: 600px; margin: 0 auto;">
    <div style="margin-bottom: 24px;">
        <h1 style="margin: 0;">Admin Profile</h1>
        <p style="margin: 4px 0 0 0;">View administrator details and login credentials.</p>
    </div>

    <section class="panel" style="padding: 28px;">
        <p style="margin-bottom: 12px;"><strong>Admin ID:</strong><br><?= h($admin['admin_id']) ?></p>

        <p style="margin-bottom: 12px;"><strong>Name:</strong><br><?= h($admin['admin_name']) ?></p>

        <p style="margin-bottom: 12px;"><strong>Email:</strong><br><?= h($admin['admin_email'] ?? '-') ?></p>

        <p style="margin-bottom: 20px;"><strong>Phone:</strong><br><?= h($admin['admin_phone_number'] ?? '-') ?></p>

        <hr style="border: 0; border-top: 1px solid var(--line); margin: 20px 0;">

        <h3 style="margin-bottom: 16px;">Login Credentials</h3>

        <p style="margin-bottom: 12px;"><strong>Login Username:</strong><br><?= h($admin['username'] ?? 'No Login Account') ?></p>

        <p style="margin-bottom: 12px;"><strong>Account Status:</strong><br>
            <?php
            if ($admin['is_active'] === null) {
                echo 'No Account';
            } else {
                echo $admin['is_active'] ? 'Active' : 'Inactive';
            }
            ?>
        </p>

        <p style="margin-bottom: 24px; font-style: italic; opacity: 0.8; font-size: 13px;">
            Note: Passwords are securely hashed using bcrypt in the database and cannot be viewed in plaintext for security reasons.
        </p>

        <div style="display: flex; gap: 12px; align-items: center; margin-top: 24px;">
            <a class="button" style="flex: 1; text-align: center; display: inline-flex; align-items: center; justify-content: center;" href="<?= h(url('admin_management/edit_admin.php?id=' . urlencode($id))) ?>">Edit Admin</a>
            <a href="<?= h(url('admin_management/index.php')) ?>" class="button button-outline" style="flex: 1; text-align: center; display: inline-flex; align-items: center; justify-content: center;">Back</a>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>