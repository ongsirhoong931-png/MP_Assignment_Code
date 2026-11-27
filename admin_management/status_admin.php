<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$id = trim($_GET['id'] ?? $_POST['admin_id'] ?? '');

if ($id === '') {
    http_response_code(400);
    exit('Admin ID is required.');
}

$stmt = $pdo->prepare("
    SELECT a.admin_id,
           a.admin_name,
           u.user_id,
           u.username,
           u.is_active
    FROM admins a
    LEFT JOIN user_accounts u
        ON u.admin_id = a.admin_id
    WHERE a.admin_id = ?
");

$stmt->execute([$id]);
$admin = $stmt->fetch();

if (!$admin) {
    http_response_code(404);
    exit('Administrator not found.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $status = $_POST['status'] ?? '';

    if ($admin['user_id'] === null) {
        $errors[] = 'This administrator does not have a login account.';
    } elseif (!in_array($status, ['0', '1'], true)) {
        $errors[] = 'Invalid account status.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("
            UPDATE user_accounts
            SET is_active = ?
            WHERE user_id = ?
              AND role = 'admin'
              AND admin_id = ?
        ");

        $stmt->execute([
            (int)$status,
            $admin['user_id'],
            $id
        ]);

        notice('Administrator login status updated successfully.');

        go('admin_management/view_admin.php?id=' .
            urlencode($id));
    }
}

$pageTitle = 'Admin Account Status';
require __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 600px; margin: 0 auto;">
    <div style="margin-bottom: 24px;">
        <h1 style="margin: 0;">Admin Login Status</h1>
        <p style="margin: 4px 0 0 0;">Manage administrator account access and login privileges.</p>
    </div>

    <?php foreach ($errors as $error): ?>
        <p class="error" style="text-align: center; background: rgba(255,107,107,0.1); padding: 12px; border-radius: 8px; margin-bottom: 16px; color: #ff6b6b;"><?= h($error) ?></p>
    <?php endforeach; ?>

    <section class="panel" style="padding: 28px;">

        <p style="margin-bottom: 16px;"><strong>Admin ID:</strong><br><?= h($admin['admin_id']) ?></p>

        <p style="margin-bottom: 16px;"><strong>Admin Name:</strong><br><?= h($admin['admin_name']) ?></p>

        <p style="margin-bottom: 16px;"><strong>Username:</strong><br><?= h($admin['username'] ?? 'No Account') ?></p>

        <p style="margin-bottom: 24px;"><strong>Current Login Status:</strong><br>
            <?php
            if ($admin['user_id'] === null) {
                echo '<span style="color: #888;">No Account</span>';
            } elseif ($admin['is_active']) {
                echo '<span style="color: #51cf66;">Active</span>';
            } else {
                echo '<span style="color: #ff6b6b;">Inactive</span>';
            }
            ?>
        </p>

        <?php if ($admin['user_id'] !== null): ?>

            <form method="post">

                <input type="hidden"
                       name="csrf"
                       value="<?= h(csrf()) ?>">

                <input type="hidden"
                       name="admin_id"
                       value="<?= h($id) ?>">

                <label style="margin-bottom: 20px; display: block;">Change Login Status
                    <select name="status" required style="margin-top: 6px;">
                        <option value="1"
                            <?= $admin['is_active'] ? 'selected' : '' ?>>
                            Active
                        </option>

                        <option value="0"
                            <?= !$admin['is_active'] ? 'selected' : '' ?>>
                            Inactive
                        </option>
                    </select>
                </label>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" style="flex: 1;">
                        Update Status
                    </button>
                    <a href="<?= h(url('admin_management/view_admin.php?id=' . urlencode($id))) ?>" class="button button-outline" style="flex: 1; text-align: center; display: inline-flex; align-items: center; justify-content: center;">
                        Cancel
                    </a>
                </div>

            </form>

        <?php else: ?>

            <p style="margin-bottom: 24px; opacity: 0.9;">
                Create a login account for this administrator
                through User Management before changing
                the account status.
            </p>

            <a href="<?= h(url('admin_management/view_admin.php?id=' . urlencode($id))) ?>" class="button button-outline" style="width: 100%; text-align: center; display: inline-flex; align-items: center; justify-content: center;">
                Back to Admin Profile
            </a>

        <?php endif; ?>

    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>