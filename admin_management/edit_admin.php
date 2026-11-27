<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$id = trim($_GET['id'] ?? $_POST['original_admin_id'] ?? '');

if ($id === '') {
    http_response_code(400);
    exit('Admin ID is required.');
}

$stmt = $pdo->prepare("
    SELECT a.*, u.username, u.is_active
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

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $newAdminId = trim($_POST['admin_id'] ?? '');
    $name = trim($_POST['admin_name'] ?? '');
    $email = trim($_POST['admin_email'] ?? '');
    $phone = trim($_POST['admin_phone_number'] ?? '');
    $username = trim($_POST['username'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_-]{1,12}$/', $newAdminId)) {
        $errors[] = 'Enter a valid Admin ID (maximum 12 characters, alphanumeric/underscores).';
    }

    if ($name === '' || strlen($name) > 120) {
        $errors[] = 'Enter a valid administrator name.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 180) {
        $errors[] = 'Enter a valid email address.';
    }

    if (strlen($phone) > 30) {
        $errors[] = 'Phone number is too long.';
    }

    if ($username === '' || strlen($username) > 50) {
        $errors[] = 'Enter a valid login username.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // 1. If Admin ID is changing, check if the new ID already exists
            if ($newAdminId !== $id) {
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE admin_id = ?");
                $checkStmt->execute([$newAdminId]);
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception('The new Admin ID is already taken.');
                }
            }

            // 2. Update admins table (including admin_id if changed)
            $stmt = $pdo->prepare("
                UPDATE admins
                SET admin_id = ?,
                    admin_name = ?,
                    admin_email = ?,
                    admin_phone_number = ?
                WHERE admin_id = ?
            ");
            $stmt->execute([
                $newAdminId,
                $name,
                $email,
                $phone ?: null,
                $id
            ]);

            // 3. Update user_accounts table
            $stmtAccount = $pdo->prepare("
                UPDATE user_accounts
                SET admin_id = ?,
                    username = ?,
                    email = ?
                WHERE admin_id = ?
            ");
            $stmtAccount->execute([
                $newAdminId,
                $username,
                $email,
                $id
            ]);

            $pdo->commit();

            notice('Administrator updated successfully.');
            go('admin_management/view_admin.php?id=' . urlencode($newAdminId));

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Unable to update administrator: ' . $e->getMessage();
        }
    }

    $admin['admin_id'] = $newAdminId;
    $admin['admin_name'] = $name;
    $admin['admin_email'] = $email;
    $admin['admin_phone_number'] = $phone;
    $admin['username'] = $username;
}

$pageTitle = 'Edit Admin';
require __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 600px; margin: 0 auto;">
    <div style="margin-bottom: 24px;">
        <h1 style="margin: 0;">Edit Admin</h1>
        <p style="margin: 4px 0 0 0;">Update administrator profile and login credentials.</p>
    </div>

    <?php foreach ($errors as $error): ?>
        <p class="error" style="text-align: center; background: rgba(255,107,107,0.1); padding: 12px; border-radius: 8px; margin-bottom: 16px; color: #ff6b6b;"><?= h($error) ?></p>
    <?php endforeach; ?>

    <section class="panel" style="padding: 28px;">
    <form method="post">

        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="original_admin_id" value="<?= h($id) ?>">

        <label>Admin ID
            <input type="text" name="admin_id"
                   maxlength="12"
                   value="<?= h($admin['admin_id']) ?>"
                   required>
        </label>

        <label>Full Name
            <input type="text" name="admin_name"
                   maxlength="120"
                   value="<?= h($admin['admin_name']) ?>"
                   required>
        </label>

        <label>Email Address
            <input type="email" name="admin_email"
                   maxlength="180"
                   value="<?= h($admin['admin_email']) ?>"
                   required>
        </label>

        <label>Phone Number
            <input type="text" name="admin_phone_number"
                   maxlength="30"
                   value="<?= h($admin['admin_phone_number']) ?>">
        </label>

        <hr style="border: 0; border-top: 1px solid var(--line); margin: 24px 0;">

        <label>Login Username
            <input type="text" name="username"
                   maxlength="50"
                   value="<?= h($admin['username'] ?? '') ?>"
                   required>
        </label>

        <div style="display: flex; gap: 12px; margin-top: 24px;">
            <button type="submit" style="flex: 1;">Update Admin</button>
            <a href="<?= h(url('admin_management/view_admin.php?id=' . urlencode($id))) ?>" class="button button-outline" style="flex: 1; text-align: center; display: inline-flex; align-items: center; justify-content: center;">
                Cancel
            </a>
        </div>
    </form>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>